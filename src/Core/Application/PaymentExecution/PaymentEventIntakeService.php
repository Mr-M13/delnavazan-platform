<?php
namespace Delnavazan\Platform\Core\Application\PaymentExecution;

use Delnavazan\Platform\Core\Application\{CollectionIntentService,CommercialExceptionService,CommercialIdempotency,CommercialPaymentService,RefundReviewService,RenewalCycleService};
use Delnavazan\Platform\Core\Infrastructure\Repository\{PaymentExecutionRepository,PaymentProviderRepository,PaymentSecretRepository};
use Delnavazan\Platform\Core\Support\Identifier;

/**
 * Provider-event intake: durable receipt, verification, event identity, translation and decision
 * (contract §9, §10).
 *
 * The webhook is the first externally supplied, unauthenticated request path that can reach commercial
 * authority, so it can never change commercial truth directly: it may only produce provider-neutral
 * evidence that the existing R1 acceptance boundary re-validates. A verified event is translated only
 * inside the bounded worker principal of §9.7, and the adapter writes no commercial row.
 */
final class PaymentEventIntakeService {
    private const CAPABILITY='dzn_ingest_payment_provider_events';
    private const VIEW_CAPABILITY='dzn_view_payment_execution_authority';
    /** How often a delivery that cannot own a claim re-reads the decision the owner is publishing. */
    private const CLAIM_WAIT_INTERVAL_MICROSECONDS=25000;
    /** The controlled HTTP status of each refusal reason: nothing distinguishes provider from account. */
    private const HTTP_STATUS=array(
        'signature_verified'=>200,'signature_invalid'=>400,'signature_outside_tolerance'=>400,
        'webhook_secret_unconfigured'=>503,'unsupported_signature_scheme'=>400,
        'webhook_account_unresolved'=>404,'provider_account_inactive'=>404,'unsupported_payment_provider'=>404,
        'method_not_allowed'=>405,'https_required'=>400,'unsupported_content_type'=>400,
        'payload_too_large'=>413,'empty_payload'=>400,'unexpected_request_shape'=>400,
    );

    public function __construct(
        private ?PaymentProviderRepository $repository=null,
        private ?PaymentProviderAccountService $accounts=null,
        private ?PaymentExecutionRepository $execution=null,
        private ?CommercialPaymentService $payments=null,
        private ?CommercialExceptionService $exceptions=null,
        private ?PaymentExecutionWorkerContext $worker=null
    ){
        $this->repository??=new PaymentProviderRepository();
        $this->accounts??=new PaymentProviderAccountService();
        $this->execution??=new PaymentExecutionRepository();
        $this->payments??=new CommercialPaymentService();
        $this->exceptions??=new CommercialExceptionService();
        $this->worker??=new PaymentExecutionWorkerContext();
    }

    public static function statusFor(string $reason):int{
        return self::HTTP_STATUS[$reason]??200;
    }

    /**
     * Receive one inbound provider request.
     *
     * Every request that reaches the controller is receipted — including a refusal decided before the
     * body is parsed — so a burst of unverified traffic stays observable. The HTTP-level requirements
     * of §9.2 are decided by the controller and passed in as a controlled `precheck_refusal` reason.
     */
    public function receive(string $providerKey,?string $accountSelector,string $rawBody,array $headers,array $meta=array()):array{
        $now=(string)($meta['received_at']??PaymentExecutionSupport::now());
        $source=(string)($meta['source']??'');
        $signature=$this->signatureHeader($headers);
        $selector=trim((string)$accountSelector);
        $precheck=isset($meta['precheck_refusal'])?(string)$meta['precheck_refusal']:null;
        $providerKey=strtolower(trim($providerKey));
        if($precheck!==null){
            $this->recordReceipt($providerKey,null,$selector,$rawBody,$signature,'refused',$precheck,'',$source,$now);
            return $this->outcome($precheck);
        }
        if(PaymentExecutionRule::provider($providerKey)===null){
            $this->recordReceipt($providerKey,null,$selector,$rawBody,$signature,'refused','unsupported_payment_provider','',$source,$now);
            return $this->outcome('unsupported_payment_provider');
        }
        if($selector===''){
            $this->recordReceipt($providerKey,null,$selector,$rawBody,$signature,'refused','webhook_account_unresolved','',$source,$now);
            return $this->outcome('webhook_account_unresolved');
        }
        $account=$this->accounts->resolveByReferenceCode($selector);
        if(!$account){
            $this->recordReceipt($providerKey,null,$selector,$rawBody,$signature,'refused','webhook_account_unresolved','',$source,$now);
            return $this->outcome('webhook_account_unresolved');
        }
        if((string)$account->state!=='active'){
            $this->recordReceipt($providerKey,(int)$account->id,$selector,$rawBody,$signature,'refused','provider_account_inactive','',$source,$now);
            return $this->outcome('provider_account_inactive');
        }
        $keyVersion=$this->activeSigningKeyVersion((string)$account->provider_key,(int)$account->id,(string)$account->mode);
        $context=new ProviderVerificationContext((string)$account->provider_key,(int)$account->id,(string)$account->mode,$keyVersion);
        $translator=PaymentProviderRegistry::translator((string)$account->provider_key);
        $verdict=$translator->verify($rawBody,$headers,$context);
        $state=$verdict->isVerified()?'verified':'refused';
        $reason=$verdict->isVerified()?null:$verdict->reason();
        $receiptId=$this->recordReceipt((string)$account->provider_key,(int)$account->id,$selector,$rawBody,$signature,$state,$reason,$verdict->keyVersion(),$source,$now);
        if(!$verdict->isVerified())return array('status'=>self::statusFor($verdict->reason()),'verification_state'=>'refused','reason_code'=>$verdict->reason(),'receipt_id'=>$receiptId);
        $envelopes=$translator->translate($rawBody,$headers,$context,$now);
        $results=array();
        foreach($envelopes as $envelope)$results[]=$this->recordVerifiedEvent($receiptId,$account,$envelope,$now);
        return array('status'=>200,'verification_state'=>'verified','reason_code'=>'signature_verified','receipt_id'=>$receiptId,'events'=>$results);
    }

    /**
     * The explicit, idempotent drain for an event whose decision is still owed (contract §9.7).
     *
     * A received event has no durable raw payload by design, so a drain completes it from a
     * **re-delivered** body: the body is verified against the recorded account's single active signing
     * secret against the exact bytes supplied, the resulting event identity must equal the recorded
     * one, and only then is the owed decision appended — exactly once. Safe to run repeatedly.
     *
     * [C9-1] The drain appends nothing directly: like every other delivery it goes through the event's
     * decision claim, so a drain that races a webhook redelivery produces one decision, and the loser
     * converges on it rather than running the translation and the R2 consequence a second time.
     */
    public function drain(int $eventId,string $rawBody,array $headers):array{
        PaymentExecutionSupport::requireCapability(self::CAPABILITY);
        $eventId=PaymentExecutionSupport::positiveInt($eventId,'Valid provider event required');
        $event=$this->repository->event($eventId);
        if(!$event)throw new \InvalidArgumentException('payment_provider_event_required');
        PaymentExecutionIntegrity::event($event);
        $last=$this->decisionForEvent($eventId);
        if(!$this->decisionIsOwed($last))return $this->convergedDecision($eventId,$last);
        $receipt=$this->repository->receipt((int)$event->receipt_id);
        if(!$receipt||$receipt->payment_provider_account_id===null)throw new \RuntimeException('payment_event_receipt_corrupt');
        $account=$this->accounts->resolveByReferenceCode((string)$this->selectorOf($event,$receipt));
        if(!$account)throw new \InvalidArgumentException('webhook_account_unresolved');
        $context=new ProviderVerificationContext((string)$account->provider_key,(int)$account->id,(string)$account->mode,$this->activeSigningKeyVersion((string)$account->provider_key,(int)$account->id,(string)$account->mode));
        $translator=PaymentProviderRegistry::translator((string)$account->provider_key);
        $verdict=$translator->verify($rawBody,$headers,$context);
        if(!$verdict->isVerified())throw new \InvalidArgumentException('provider_event_not_authoritative');
        $match=null;
        foreach($translator->translate($rawBody,$headers,$context,PaymentExecutionSupport::now()) as $envelope){
            if(hash_equals((string)$event->event_reference_digest,PaymentExecutionIdempotency::eventReference((string)$envelope->eventReference()))){$match=$envelope;break;}
        }
        if($match===null)throw new \InvalidArgumentException('provider_event_not_authoritative');
        // [C8-4] The recorded event is immutable. A body that matches the event identity but not the
        // recorded immutable facts is a conflicting duplicate: the original event is preserved unchanged,
        // the controlled conflict decision is appended, and no evidence is submitted for the changed
        // facts — a drain may never translate a payload the event never recorded.
        if(!hash_equals((string)$event->event_fact_digest,$this->factDigest((string)$event->provider_key,(string)$event->event_reference_digest,$match)))
            return $this->recordConflict($event);
        return $this->completeDecision($event,fn():array=>$this->decide($event,$match),'drain');
    }

    public function receipts(int $limit=50):array{
        PaymentExecutionSupport::requireCapability(self::VIEW_CAPABILITY);
        return array_slice(array_map(array($this,'receiptView'),$this->repository->receipts()),-max(1,min($limit,200)));
    }
    public function events(int $limit=50):array{
        PaymentExecutionSupport::requireCapability(self::VIEW_CAPABILITY);
        return array_slice(array_map(array($this,'eventView'),$this->repository->events()),-max(1,min($limit,200)));
    }
    public function decisions(int $eventId):array{
        PaymentExecutionSupport::requireCapability(self::VIEW_CAPABILITY);
        return array_map(array($this,'decisionView'),$this->repository->decisions($eventId));
    }
    /** Outstanding R2 consequences by state (§13 diagnostics). */
    public function outstandingConsequences():array{
        PaymentExecutionSupport::requireCapability(self::VIEW_CAPABILITY);
        $counts=array_fill_keys(PaymentExecutionRule::R2_CONSEQUENCE_STATES,0);
        foreach($this->repository->allDecisions() as $decision)if(isset($counts[(string)$decision->r2_consequence_state]))$counts[(string)$decision->r2_consequence_state]++;
        return $counts;
    }
    /** Outstanding events that are durably recorded but still owe a decision. */
    public function outstandingEvents():int{
        PaymentExecutionSupport::requireCapability(self::VIEW_CAPABILITY);
        $outstanding=0;
        foreach($this->repository->events() as $event)if($this->decisionIsOwed($this->decisionForEvent((int)$event->id)))$outstanding++;
        return $outstanding;
    }

    /**
     * [C9-1] Live per-event decision claims by state, age and fencing generation (§13).
     *
     * Never a claim token, a payload or a provider reference: an operator sees which events one worker is
     * currently completing, how long it has held them and which generation owns them.
     */
    public function outstandingDecisionClaims():array{
        PaymentExecutionSupport::requireCapability(self::VIEW_CAPABILITY);
        $rows=array();
        foreach($this->repository->decisionClaims() as $claim){
            PaymentExecutionIntegrity::decisionClaim($claim);
            if((string)$claim->claim_state!=='claimed')continue;
            $rows[]=array(
                'claim_id'=>(int)$claim->id,'provider_event_id'=>(int)$claim->provider_event_id,
                'claim_state'=>(string)$claim->claim_state,'claim_generation'=>(int)$claim->claim_generation,
                'age_seconds'=>max(0,time()-(int)strtotime((string)$claim->claimed_at.' UTC')),
            );
        }
        return $rows;
    }

    private function decisionIsPending(object $decision):bool{
        return (string)$decision->r2_consequence_state==='pending'||(string)$decision->reason_code==='payment_worker_principal_required';
    }
    /** An event owes a decision while it has none at all, or while its latest one is still pending. */
    private function decisionIsOwed(?object $decision):bool{
        return $decision===null||$this->decisionIsPending($decision);
    }
    /**
     * [C9-1] The decision that decides one event: the newest row that is not the controlled conflict record.
     *
     * A conflict row records that a *different* delivery carried materially different facts; it never
     * discharges the event's own decision and never replaces it, so "does this event still owe a decision"
     * is asked of the non-conflict timeline. An event whose only rows are conflict records still owes its
     * first decision.
     */
    private function decisionForEvent(int $providerEventId):?object{
        $latest=$this->repository->latestDecision($providerEventId);
        if($latest&&(string)$latest->reason_code!=='conflicting_provider_event')return $latest;
        foreach(array_reverse($this->repository->decisions($providerEventId)) as $decision)
            if((string)$decision->reason_code!=='conflicting_provider_event')return $decision;
        return null;
    }

    /** One verified event: durable identity first, then idempotency, conflict or a first decision. */
    private function recordVerifiedEvent(int $receiptId,object $account,ProviderEventEnvelope $envelope,string $receivedAt):array{
        $providerKey=(string)$account->provider_key;
        $eventReferenceDigest=PaymentExecutionIdempotency::eventReference((string)$envelope->eventReference());
        $factDigest=$this->factDigest($providerKey,$eventReferenceDigest,$envelope);
        // A duplicate this worker can already read converges before anything is inserted.
        $existing=$this->repository->eventByReferenceDigest($providerKey,$eventReferenceDigest);
        if($existing)return $this->convergeExisting($existing,$factDigest,$envelope);
        // [C8-3] The unique `provider_event` index — never the read — is the arbiter between two workers
        // that both saw no event: the insert reports whether this worker created the row, and a worker
        // that lost the index takes exactly the same convergence path as the worker that read a duplicate.
        $inserted=$this->insertEvent($receiptId,$account,$envelope,$eventReferenceDigest,$factDigest,$receivedAt);
        $event=$this->repository->event((int)$inserted['event_id']);
        if(!$event)throw new \RuntimeException('payment_event_corrupt');
        if(!$inserted['created'])return $this->convergeExisting($event,$factDigest,$envelope);
        // [C9-1] The event this worker created still owes its first decision, and that decision is owned
        // by exactly one worker: the claim is taken before any R1/R2 work is run.
        return $this->completeDecision($event,fn():array=>$this->decide($event,$envelope),'first_decision');
    }

    /**
     * [C8-3] The single convergence path of §9.5.
     *
     * Identical facts converge idempotently; a duplicate delivery may complete an R2 consequence that is
     * still `pending`, and it never appends a row for a consequence that is already applied, refused or
     * not applicable. Materially different facts preserve the original event unchanged and append the
     * controlled `conflicting_provider_event` decision — a worker that lost the insert race must never
     * translate its own view of the facts.
     *
     * [C9-1] Convergence never means "no decision": an event that was recorded and then left owing — a
     * worker that committed the event row and died before its first decision, which the round-8 candidate
     * returned as `recorded` forever — is completed here by the next delivery, through the same
     * serialised claim path a first decision and a pending retry take.
     */
    private function convergeExisting(object $existing,string $factDigest,ProviderEventEnvelope $envelope):array{
        if(hash_equals((string)$existing->event_fact_digest,$factDigest)){
            return $this->completeDecision($existing,fn():array=>$this->decide($existing,$envelope),'duplicate');
        }
        return $this->recordConflict($existing);
    }

    /**
     * Insert the durable event row or resolve the winner of a concurrent delivery ([C8-3]).
     *
     * Returns the event id together with the proof of ownership: `created` is true only for the worker
     * whose insert actually wrote the row. A worker that meets the unique `provider_event` index gets the
     * winner's recorded event id and `created = false`, so it never translates or decides a duplicate.
     */
    private function insertEvent(int $receiptId,object $account,ProviderEventEnvelope $envelope,string $eventReferenceDigest,string $factDigest,string $receivedAt):array{
        $now=PaymentExecutionSupport::now();
        $this->repository->begin();
        try{
            $eventId=$this->repository->insertEvent(array(
                'uid'=>Identifier::uid(),'receipt_id'=>$receiptId,'provider_key'=>(string)$account->provider_key,
                'payment_provider_account_id'=>(int)$account->id,'event_reference_digest'=>$eventReferenceDigest,
                'event_fact_digest'=>$factDigest,'event_type'=>(string)$envelope->eventType(),
                'raw_type_digest'=>PaymentExecutionIdempotency::rawType((string)$envelope->rawType()),
                'payload_digest'=>(string)$envelope->payloadDigest(),'provider_occurred_at'=>$envelope->providerOccurredAt(),
                'received_at'=>$receivedAt,'created_at'=>$now,'created_by'=>null,
            ));
            $this->repository->commit();
        }catch(\Throwable $e){
            $this->repository->rollback();
            if($this->repository->duplicate($e)==='provider_event'){
                $winner=$this->repository->eventByReferenceDigest((string)$account->provider_key,$eventReferenceDigest);
                if($winner)return array('event_id'=>(int)$winner->id,'created'=>false);
            }
            throw $e;
        }
        return array('event_id'=>$eventId,'created'=>true);
    }

    /**
     * The controlled conflict decision: the original event is preserved unchanged and no evidence is
     * ever submitted for the changed facts ([C8-4]).
     *
     * [C9-1] It is appended through the same per-event claim as every other decision, so two concurrent
     * deliveries of one event identity with different facts append exactly one conflict row, and the R1
     * commercial exception is recorded once, by the one worker that appended it.
     */
    private function recordConflict(object $event):array{
        $result=$this->completeDecision($event,fn():array=>$this->conflictDecision(),'conflict',true);
        if(!empty($result['created']))$this->exceptions->recordAfterFailure(array(
            'reason_code'=>'conflicting_payment_evidence','severity'=>'warning',
            'summary'=>'A provider event was re-delivered with materially different facts',
            'safe_detail'=>'conflicting_provider_event',
            'fingerprint_value'=>((int)$event->id).':'.(string)$event->event_reference_digest,
        ));
        return $result;
    }
    private function conflictDecision():array{
        return array(
            'decision_state'=>'conflicted','reason_code'=>'conflicting_provider_event','evidence_kind'=>null,
            'offer_id'=>null,'obligation_id'=>null,'purchase_id'=>null,'commercial_evidence_id'=>null,
            'collection_intent_id'=>null,'renewal_cycle_id'=>null,'renewal_cycle_state'=>null,
            'collection_intent_state'=>null,'r2_consequence_state'=>'not_applicable','r2_reason_code'=>null,
            'execution_command_id'=>null,'recorded_by'=>null,
        );
    }

    /**
     * [C9-1]/[C9-2] The one serialised path that appends a decision for one event.
     *
     * A decision is appended by **exactly one worker**: the worker must own the event's live decision
     * claim *before* it runs any R1/R2 work, the claim's generation and token fence the append inside the
     * same transaction that records the row, and a delivery that cannot own the claim performs no work at
     * all — it re-reads what the owner recorded and converges. The claim therefore covers the three
     * cases a redelivery may legitimately complete: the first decision of a newly recorded event, the
     * first decision of an event that was recorded and then left owing (a worker that died between the
     * event insert and its decision), and the terminal consequence of a decision that is still `pending`
     * or was refused for want of the §9.7 worker principal.
     *
     * `$alwaysAppends` is true only for the controlled conflict decision: materially different facts for
     * a recorded event identity owe their own conflict row even though a terminal decision already
     * exists, because a duplicate must never converge on changed facts (§9.5).
     */
    private function completeDecision(object $event,callable $decide,string $context,bool $alwaysAppends=false):array{
        $eventId=(int)$event->id;
        $last=$this->decisionForEvent($eventId);
        if(!$alwaysAppends&&!$this->decisionIsOwed($last))return $this->convergedDecision($eventId,$last);
        $claim=$this->acquireDecisionClaim($eventId);
        // A conflict row is owed unconditionally, so its worker waits, bounded, for the event's own
        // decision to be published rather than dropping the changed facts because one owner was busy.
        if($claim===null&&$alwaysAppends)$claim=$this->awaitDecisionClaim($eventId);
        if($claim===null)return $this->convergeOnOwner($eventId);
        try{
            // The owner always re-reads under its own claim before it does any work: a generation that
            // took over an abandoned claim may find that the owed decision was already completed, and a
            // decision that is no longer owed is never appended a second time.
            $current=$this->decisionForEvent($eventId);
            if(!$alwaysAppends&&!$this->decisionIsOwed($current)){
                $this->abandonDecisionClaim($claim);
                return $this->convergedDecision($eventId,$current);
            }
            $decision=$decide();
        }catch(\Throwable $e){
            $this->abandonDecisionClaim($claim);
            throw $e;
        }
        return $this->appendDecisionUnderClaim($event,$decision,$claim,$context);
    }

    /** The recorded decision a delivery converges on, or the recorded state when the event owes one. */
    private function convergedDecision(int $eventId,?object $decision):array{
        return array(
            'event_id'=>$eventId,'decision_id'=>$decision?(int)$decision->id:null,
            'decision_state'=>$decision?(string)$decision->decision_state:'recorded',
            'reason_code'=>$decision?$decision->reason_code:'recorded',
            'converged'=>true,'pending'=>false,'created'=>false,
        );
    }

    /**
     * [C9-1]/[C9-2] Take the live decision claim of one event, or report that another worker owns it.
     *
     * The unique `event_claim` index — never a read — is the arbiter between two workers that both saw a
     * decision owed: the insert reports whether this worker claimed the event, and a worker that meets
     * the index runs no decision and no consequence work. An abandoned live claim (its lease has expired)
     * is taken over by exactly one conditional statement that issues a new fencing generation and token;
     * every other worker observes zero affected rows and converges instead of working.
     */
    private function acquireDecisionClaim(int $providerEventId):?array{
        $now=PaymentExecutionSupport::now();
        $live=$this->repository->liveDecisionClaim($providerEventId);
        if($live){
            PaymentExecutionIntegrity::decisionClaim($live);
            if(!$this->claimIsAbandoned($live,$now))return null;
            $token=$this->claimToken();
            if($this->repository->takeoverDecisionClaim((int)$live->id,(int)$live->claim_generation,$token,$this->claimLease($now),$now)!==1)return null;
            return array('claim_id'=>(int)$live->id,'generation'=>(int)$live->claim_generation+1,'token'=>$token);
        }
        $token=$this->claimToken();
        $this->repository->begin();
        try{
            $claimId=$this->repository->insertDecisionClaim(array(
                'uid'=>Identifier::uid(),'provider_event_id'=>$providerEventId,'claim_state'=>'claimed',
                'claim_generation'=>1,'claim_token_digest'=>$token,'lease_expires_at'=>$this->claimLease($now),
                'claimed_at'=>$now,'settled_at'=>null,'active_claim_slot'=>1,'created_at'=>$now,'updated_at'=>$now,
                'created_by'=>$this->recordedBy(),'updated_by'=>$this->recordedBy(),
            ));
            $this->repository->commit();
        }catch(\Throwable $e){
            $this->repository->rollback();
            // A worker that meets `UNIQUE event_claim` adopts nothing: it owns no decision work at all.
            if($this->repository->duplicate($e)==='event_claim')return null;
            throw $e;
        }
        return array('claim_id'=>$claimId,'generation'=>1,'token'=>$token);
    }

    /**
     * [C9-1]/[C9-2] Bounded re-attempts of the decision claim, for the one row every changed-facts
     * delivery owes.
     *
     * It never performs decision work of its own and never waits longer than the structural window; when
     * the window closes the caller converges, and the next delivery or drain records the conflict.
     */
    private function awaitDecisionClaim(int $providerEventId):?array{
        $deadline=microtime(true)+PaymentExecutionRule::DECISION_CLAIM_WAIT_MILLISECONDS/1000;
        do{
            usleep(self::CLAIM_WAIT_INTERVAL_MICROSECONDS);
            $claim=$this->acquireDecisionClaim($providerEventId);
            if($claim!==null)return $claim;
        }while(microtime(true)<$deadline);
        return null;
    }

    /**
     * [C9-1]/[C9-2] Converge without doing any work: the loser of the claim re-reads the owner's decision.
     *
     * The wait is bounded and finite, and it never appends, translates or runs an R1/R2 consequence. If
     * the owner is still working when the wait ends, the delivery reports the event as durably still
     * owing its decision — the §9.5 pending state, visible in the read model and completed by the next
     * delivery or drain, never by a second worker here.
     */
    private function convergeOnOwner(int $providerEventId):array{
        $deadline=microtime(true)+PaymentExecutionRule::DECISION_CLAIM_WAIT_MILLISECONDS/1000;
        do{
            $last=$this->decisionForEvent($providerEventId);
            if(!$this->decisionIsOwed($last))return $this->convergedDecision($providerEventId,$last);
            if($this->repository->liveDecisionClaim($providerEventId)===null)break;
            usleep(self::CLAIM_WAIT_INTERVAL_MICROSECONDS);
        }while(microtime(true)<$deadline);
        $last=$this->decisionForEvent($providerEventId);
        if(!$this->decisionIsOwed($last))return $this->convergedDecision($providerEventId,$last);
        return array(
            'event_id'=>$providerEventId,'decision_id'=>$last?(int)$last->id:null,
            'decision_state'=>$last?(string)$last->decision_state:'recorded',
            'reason_code'=>$last?$last->reason_code:'decision_claim_in_flight',
            'converged'=>false,'pending'=>true,'context'=>'claim_in_flight','created'=>false,
        );
    }

    /**
     * [C9-1]/[C9-2] Append the decision under the claim's fence, or write nothing at all.
     *
     * The conditional `claimed → settled` transition and the decision insert are **one transaction**: the
     * affected-row count proves this generation still owns the event and takes the claim row's lock, so
     * the next `decision_sequence` is allocated under that lock and two workers can never derive the same
     * one. An owner that lost its fence — an expired claim another generation took over — rolls back,
     * writes no decision and converges on whatever the successor recorded.
     */
    private function appendDecisionUnderClaim(object $event,array $decision,array $claim,string $context):array{
        $now=PaymentExecutionSupport::now();
        $this->repository->begin();
        try{
            if($this->repository->settleDecisionClaim((int)$claim['claim_id'],(int)$claim['generation'],(string)$claim['token'],$now)!==1){
                $this->repository->rollback();
                return $this->convergeOnOwner((int)$event->id);
            }
            $decisionId=$this->repository->insertDecision(array(
                'uid'=>Identifier::uid(),'provider_event_id'=>(int)$event->id,
                'decision_sequence'=>$this->repository->maxDecisionSequence((int)$event->id),
                'decision_state'=>$decision['decision_state'],'reason_code'=>$decision['reason_code'],
                'evidence_kind'=>$decision['evidence_kind']??null,'offer_id'=>$decision['offer_id']??null,
                'obligation_id'=>$decision['obligation_id']??null,'purchase_id'=>$decision['purchase_id']??null,
                'commercial_evidence_id'=>$decision['commercial_evidence_id']??null,
                'collection_intent_id'=>$decision['collection_intent_id']??null,'renewal_cycle_id'=>$decision['renewal_cycle_id']??null,
                'renewal_cycle_state'=>$decision['renewal_cycle_state']??null,'collection_intent_state'=>$decision['collection_intent_state']??null,
                'r2_consequence_state'=>$decision['r2_consequence_state']??'not_applicable','r2_reason_code'=>$decision['r2_reason_code']??null,
                'execution_command_id'=>$decision['execution_command_id']??null,'decided_at'=>$now,'recorded_at'=>$now,
                'recorded_by'=>$decision['recorded_by']??$this->recordedBy(),'created_at'=>$now,'created_by'=>$decision['recorded_by']??$this->recordedBy(),
            ));
            $this->repository->commit();
        }catch(\Throwable $e){
            $this->repository->rollback();
            $this->abandonDecisionClaim($claim);
            throw $e;
        }
        PaymentExecutionSupport::hook('dzn_phase_2a2t_after_provider_event_decision',(int)$event->id,$decisionId);
        return array(
            'event_id'=>(int)$event->id,'decision_id'=>$decisionId,'decision_state'=>$decision['decision_state'],
            'reason_code'=>$decision['reason_code'],'r2_consequence_state'=>$decision['r2_consequence_state']??'not_applicable',
            'context'=>$context,'converged'=>false,'pending'=>false,'created'=>true,
        );
    }

    /**
     * Free the live slot after this worker appended nothing.
     *
     * A release that cannot be written is not fatal: the claim's lease is the durable recovery path, so a
     * claim this worker still owns expires and exactly one later generation takes it over.
     */
    private function abandonDecisionClaim(array $claim):void{
        try{
            $this->repository->releaseDecisionClaim((int)$claim['claim_id'],(int)$claim['generation'],(string)$claim['token'],PaymentExecutionSupport::now());
        }catch(\Throwable $ignored){
            // Deliberately swallowed: the lease, not this statement, is what makes the claim recoverable.
        }
    }

    /** A live claim is abandoned once its lease has expired; a live claim always carries one ([C9-1]). */
    private function claimIsAbandoned(object $claim,string $now):bool{
        $lease=$claim->lease_expires_at===null?null:(string)$claim->lease_expires_at;
        return $lease!==null&&$lease<$now;
    }
    private function claimLease(string $now):string{
        return gmdate('Y-m-d H:i:s',strtotime($now.' UTC')+PaymentExecutionRule::DECISION_CLAIM_LEASE_SECONDS);
    }
    private function claimToken():string{
        return hash('sha256',Identifier::uid().'|'.bin2hex(random_bytes(32)));
    }

    /**
     * Decide one already-recorded, verified event.
     *
     * Attribution is exact or refused, amounts are compared and never adopted, and the R1/R2
     * consequences run inside the bounded worker principal of §9.7 — never under a caller's identity.
     */
    private function decide(object $event,ProviderEventEnvelope $envelope):array{
        $eventType=(string)$event->event_type;
        if(!PaymentExecutionRule::member($eventType,PaymentExecutionRule::EVENT_TYPES))return $this->refusal('unrecognised_provider_event');
        if($eventType==='provider_recurring_semantics_unresolved')return $this->refusal('provider_recurring_semantics_unresolved');
        if($eventType==='unrecognised_provider_event')return $this->refusal('unrecognised_provider_event');
        // §9.6: the provider occurrence instant is authority, never the receive order.
        $obligationId=$envelope->obligationReference()===null?null:$this->obligationFor((string)$envelope->obligationReference());
        if($envelope->obligationReference()!==null&&$obligationId===null)return $this->refusal('ambiguous_obligation_attribution');
        if($obligationId!==null&&$this->isStale($obligationId,$envelope->providerOccurredAt()))return $this->refusal('stale_provider_event','ignored');
        if($envelope->providerAccountReference()!==null&&!hash_equals($this->accountDigest((int)$event->payment_provider_account_id),PaymentExecutionIdempotency::reference((string)$envelope->providerAccountReference())))return $this->refusal('unmapped_provider_account');
        // [C8-2] Attribution is exact or refused (§10 rule 1).
        if($envelope->providerObjectReference()!==null){
            $attribution=$this->objectAttribution((int)$event->payment_provider_account_id,(string)$envelope->providerObjectReference(),$obligationId);
            if($attribution!==null)return $this->refusal($attribution);
        }
        if(PaymentExecutionSupport::workerPrincipalId()<1)return $this->refusal('payment_worker_principal_required');
        try{
            return $this->worker->run(fn():array=>$this->translate($event,$envelope,$obligationId));
        }catch(\RuntimeException $e){
            if($e->getMessage()==='payment_worker_principal_required')return $this->refusal('payment_worker_principal_required');
            throw $e;
        }
    }

    /** Submit the normalised facts to R1 and run the bounded R2 consequence under the worker context. */
    private function translate(object $event,ProviderEventEnvelope $envelope,?int $obligationId):array{
        $evidenceKind=$this->evidenceKind((string)$envelope->eventType());
        $occurredAt=$envelope->providerOccurredAt()??(string)$event->received_at;
        $input=array(
            'provider_key'=>(string)$envelope->providerKey(),
            'provider_reference'=>(string)$envelope->eventReference(),
            'evidence_kind'=>$evidenceKind,
            'amount_minor'=>$envelope->amountMinor(),'currency'=>$envelope->currency(),
            'provider_occurred_at'=>$occurredAt,
            'obligation_reference'=>$envelope->obligationReference(),
            'provider_account_reference'=>$envelope->providerAccountReference(),
            'evidence_channel'=>'provider_evidence','evidence_at'=>$occurredAt,
            'evidence_reference'=>'dzn_phase_2a2t_event:'.(string)$event->uid,
        );
        $key='dzn_phase_2a2t_evidence:'.(string)$event->uid.':'.$evidenceKind;
        try{
            $result=$this->payments->ingest($input,$key);
        }catch(\InvalidArgumentException $e){
            return $this->refusedSubmission($evidenceKind,$obligationId,null,null);
        }
        $processing=(string)($result['processing_state']??'rejected');
        $evidenceId=isset($result['evidence_id'])?(int)$result['evidence_id']:null;
        $purchaseId=isset($result['purchase_id'])&&$result['purchase_id']!==null?(int)$result['purchase_id']:null;
        $recordedObligation=$obligationId??(isset($result['obligation_id'])&&$result['obligation_id']!==null?(int)$result['obligation_id']:null);
        $offerId=isset($result['offer_id'])?(int)$result['offer_id']:null;
        if($processing!=='accepted')return $this->refusedSubmission($evidenceKind,$recordedObligation,$offerId,$purchaseId,$evidenceId);
        $consequence=$this->consequence((string)$envelope->eventType(),$recordedObligation,$purchaseId,$evidenceId,$occurredAt);
        return array_merge(array(
            'decision_state'=>'translated','reason_code'=>'evidence_submitted','evidence_kind'=>$evidenceKind,
            'offer_id'=>$offerId,'obligation_id'=>$recordedObligation,'purchase_id'=>$purchaseId,'commercial_evidence_id'=>$evidenceId,
            'execution_command_id'=>null,'recorded_by'=>$this->recordedBy(),
        ),$consequence);
    }

    private function refusedSubmission(string $evidenceKind,?int $obligationId,?int $offerId,?int $purchaseId,?int $evidenceId=null):array{
        return array('decision_state'=>'refused','reason_code'=>'provider_event_not_authoritative','evidence_kind'=>$evidenceKind,
            'offer_id'=>$offerId,'obligation_id'=>$obligationId,'purchase_id'=>$purchaseId,'commercial_evidence_id'=>$evidenceId,
            'collection_intent_id'=>null,'renewal_cycle_id'=>null,'renewal_cycle_state'=>null,'collection_intent_state'=>null,
            'r2_consequence_state'=>'not_applicable','r2_reason_code'=>null,'execution_command_id'=>null,'recorded_by'=>$this->recordedBy());
    }

    /**
     * The bounded, ordered, idempotent R2 consequence of §10.1.
     *
     * Step 1 always precedes step 2, both under the same bounded identity, and a cycle that cannot be
     * collected is refused with a controlled reason rather than silently left stranded.
     */
    private function consequence(string $eventType,?int $obligationId,?int $purchaseId,?int $evidenceId,string $occurredAt):array{
        $blank=array('collection_intent_id'=>null,'renewal_cycle_id'=>null,'renewal_cycle_state'=>null,'collection_intent_state'=>null,'r2_consequence_state'=>'not_applicable','r2_reason_code'=>null);
        if($eventType!=='payment_succeeded'&&$eventType!=='payment_failed'&&$eventType!=='refund_recorded')return $blank;
        if($obligationId===null)return $blank;
        $intent=$this->execution->intentForObligation($obligationId);
        if(!$intent)return $blank;
        $intentState=(string)$intent->state;
        $cycle=$this->execution->cycleForIntent((int)$intent->id);
        $state=array('collection_intent_id'=>(int)$intent->id,'renewal_cycle_id'=>$cycle?(int)$cycle->id:(int)$intent->renewal_cycle_id,
            'renewal_cycle_state'=>$cycle?(string)$cycle->state:null,'collection_intent_state'=>$intentState);
        if($eventType==='payment_failed'){
            if($intentState!=='submitted')return array_merge($state,array('r2_consequence_state'=>'refused','r2_reason_code'=>'collection_intent_not_submitted'));
            $this->r2($state,'failure',$occurredAt);
            return array_merge($state,array('collection_intent_state'=>'failed','r2_consequence_state'=>'applied','r2_reason_code'=>null));
        }
        if($eventType==='refund_recorded'){
            if($purchaseId===null||$evidenceId===null)return array_merge($state,array('r2_consequence_state'=>'refused','r2_reason_code'=>'accepted_payment_evidence_required'));
            $this->r2($state,'refund',$occurredAt,$purchaseId,$evidenceId,(int)$intent->obligation_id);
            return array_merge($state,array('r2_consequence_state'=>'applied','r2_reason_code'=>null));
        }
        // payment_succeeded: confirm the intent, then confirm the cycle, from one decision unit.
        if($intentState==='confirmed'){
            $state['collection_intent_state']='confirmed';
        }elseif($intentState==='submitted'){
            $this->r2($state,'confirm_intent',$occurredAt);
            $state['collection_intent_state']='confirmed';
        }else{
            return array_merge($state,array('r2_consequence_state'=>$intentState==='pending'?'pending':'refused','r2_reason_code'=>'collection_intent_not_submitted'));
        }
        if(in_array($state['renewal_cycle_state'],array('collected','term_bound'),true))return array_merge($state,array('r2_consequence_state'=>'applied','r2_reason_code'=>null));
        if($state['renewal_cycle_state']==='payment_required'){
            $this->r2($state,'confirm_cycle',$occurredAt);
            return array_merge($state,array('renewal_cycle_state'=>'collected','r2_consequence_state'=>'applied','r2_reason_code'=>null));
        }
        return array_merge($state,array('r2_consequence_state'=>'refused','r2_reason_code'=>'renewal_cycle_not_collectable'));
    }

    /** Delegate exactly one R2 command with a deterministic, decision-derived key (§10.1 rule 2). */
    private function r2(array $state,string $step,string $occurredAt,?int $purchaseId=null,?int $evidenceId=null,?int $obligationId=null):void{
        $reference='dzn_phase_2a2t_r2_consequence:'.(int)$state['collection_intent_id'].':'.$step;
        $input=array('evidence_channel'=>'provider_evidence','evidence_reference'=>$reference,'evidence_at'=>$occurredAt,'confirmed'=>true);
        if($step==='confirm_intent'){
            (new CollectionIntentService())->confirm((int)$state['collection_intent_id'],$input,$reference);
        }elseif($step==='confirm_cycle'){
            (new RenewalCycleService())->confirmCollection((int)$state['renewal_cycle_id'],$input,$reference);
        }elseif($step==='failure'){
            $input['failure_reason_code']='provider_payment_failed';
            (new CollectionIntentService())->recordFailure((int)$state['collection_intent_id'],$input,$reference);
        }elseif($step==='refund'){
            $input['purchase_id']=$purchaseId;
            $input['evidence_id']=$evidenceId;
            $input['obligation_id']=$obligationId;
            $input['kind']='refund';
            (new RefundReviewService())->recordRefundEvidence($input,$reference);
        }
    }

    private function refusal(string $reason,string $state='refused'):array{
        return array('decision_state'=>$state,'reason_code'=>$reason,'evidence_kind'=>null,'offer_id'=>null,'obligation_id'=>null,
            'purchase_id'=>null,'commercial_evidence_id'=>null,'collection_intent_id'=>null,'renewal_cycle_id'=>null,
            'renewal_cycle_state'=>null,'collection_intent_state'=>null,'r2_consequence_state'=>'not_applicable','r2_reason_code'=>null,
            'execution_command_id'=>null,'recorded_by'=>$this->recordedBy());
    }

    private function recordReceipt(string $providerKey,?int $accountId,string $selector,string $rawBody,?string $signature,string $state,?string $reason,string $keyVersion,string $source,string $now):int{
        $this->repository->begin();
        try{
            $receiptId=$this->repository->insertReceipt(array(
                'uid'=>Identifier::uid(),'provider_key'=>$providerKey===''?'unknown':$providerKey,
                'payment_provider_account_id'=>$accountId,'account_selector_digest'=>$selector===''?null:PaymentExecutionIdempotency::accountSelector($selector),
                'request_digest'=>PaymentExecutionIdempotency::payloadDigest($rawBody),
                'signature_digest'=>$signature===null?null:PaymentExecutionIdempotency::signature($signature),
                'signature_key_version'=>$keyVersion===''?null:$keyVersion,'verification_state'=>$state,
                'refusal_reason_code'=>$reason,'source_digest'=>$source===''?null:PaymentExecutionIdempotency::source($source),
                'body_bytes'=>strlen($rawBody),'received_at'=>$now,'created_at'=>$now,'created_by'=>null,
            ));
            $this->repository->commit();
        }catch(\Throwable $e){
            $this->repository->rollback();
            throw $e;
        }
        return $receiptId;
    }
    private function outcome(string $reason):array{
        return array('status'=>self::statusFor($reason),'verification_state'=>'refused','reason_code'=>$reason,'receipt_id'=>null);
    }
    private function factDigest(string $providerKey,string $eventReferenceDigest,ProviderEventEnvelope $envelope):string{
        return PaymentExecutionIdempotency::eventFact(array(
            'provider_key'=>$providerKey,'event_reference_digest'=>$eventReferenceDigest,
            'event_type'=>(string)$envelope->eventType(),'raw_type_digest'=>PaymentExecutionIdempotency::rawType((string)$envelope->rawType()),
            'payload_digest'=>(string)$envelope->payloadDigest(),'provider_occurred_at'=>$envelope->providerOccurredAt(),
            'amount_minor'=>$envelope->amountMinor(),'currency'=>$envelope->currency(),
            'obligation_reference_digest'=>$envelope->obligationReference()===null?null:PaymentExecutionIdempotency::reference((string)$envelope->obligationReference()),
        ));
    }
    private function selectorOf(object $event,object $receipt):string{
        global $wpdb;$p=$wpdb->prefix.'dzn_';
        $reference=(string)$wpdb->get_var($wpdb->prepare("SELECT reference_code FROM {$p}payment_provider_accounts WHERE id=%d",(int)$event->payment_provider_account_id));
        return $reference;
    }
    private function obligationFor(string $obligationReference):?int{
        global $wpdb;$p=$wpdb->prefix.'dzn_';
        $digest=CommercialIdempotency::obligationReference($obligationReference);
        $id=(int)$wpdb->get_var($wpdb->prepare("SELECT id FROM {$p}commercial_offer_obligations WHERE obligation_reference_digest=%s",$digest));
        return $id>0?$id:null;
    }
    private function accountDigest(int $accountId):string{
        global $wpdb;$p=$wpdb->prefix.'dzn_';
        return (string)$wpdb->get_var($wpdb->prepare("SELECT account_reference_digest FROM {$p}payment_provider_accounts WHERE id=%d",$accountId));
    }
    /**
     * [C8-2] Exact provider-object-to-obligation attribution (§10 rule 1).
     *
     * The event's own provider object must carry **exactly one active mapping**, and the canonical
     * obligation that mapping already owns must be exactly the obligation the event resolved:
     *
     * - an absent mapping, or a historical (`superseded`/`detached`) one, is never authority and is
     *   refused with `unmapped_provider_object`;
     * - more than one active candidate — the provider-side unique index is kind-scoped — is refused as
     *   ambiguous rather than arbitrated;
     * - a mapping whose canonical row owns a different obligation (or owns none while the event names
     *   one) can never submit evidence for the obligation the event names; it is refused with
     *   `ambiguous_obligation_attribution`. A signed event for an object linked to one canonical row can
     *   therefore never settle, or attempt to settle, an obligation that mapping does not own.
     *
     * Returns the controlled refusal reason, or null when the attribution is exact. An event that names no
     * obligation and an object whose mapping owns none are compatible: neither claims an attribution, so
     * R1's own routing (§10 rule 1, `unmatched_payment_evidence`) decides what the evidence is worth.
     */
    private function objectAttribution(int $accountId,string $objectReference,?int $obligationId):?string{
        $candidates=$this->repository->activeObjectsByReferenceDigest($accountId,PaymentExecutionIdempotency::reference($objectReference));
        if(count($candidates)!==1)return $candidates===array()?'unmapped_provider_object':'ambiguous_obligation_attribution';
        $mapping=$candidates[0];
        PaymentExecutionIntegrity::mapping($mapping);
        if((string)$mapping->state!=='linked')return 'unmapped_provider_object';
        return $this->canonicalObligation($mapping)===$obligationId?null:'ambiguous_obligation_attribution';
    }
    /** The one R1 obligation an active mapping already owns, or null when it owns none ([C8-2]). */
    private function canonicalObligation(object $mapping):?int{
        return match((string)$mapping->canonical_kind){
            'obligation'=>(int)$mapping->canonical_id,
            'collection_intent'=>$this->repository->obligationForCollectionIntent((int)$mapping->canonical_id),
            default=>null,
        };
    }
    private function isStale(?int $obligationId,?string $occurredAt):bool{
        if($obligationId===null||$occurredAt===null)return false;
        $settlement=$this->execution->settlementForObligation($obligationId);
        return $settlement&&(string)$settlement->settled_at>$occurredAt;
    }
    private function evidenceKind(string $eventType):string{
        return match($eventType){
            'payment_succeeded'=>'success','payment_failed'=>'failure','payment_requires_action'=>'attempt',
            'refund_recorded'=>'refund','mandate_recorded'=>'mandate',default=>'attempt',
        };
    }
    private function recordedBy():?int{
        $id=PaymentExecutionSupport::workerPrincipalId();
        return $id>0?$id:null;
    }
    private function activeSigningKeyVersion(string $providerKey,int $accountId,string $mode):string{
        $row=(new PaymentSecretRepository())->activeSecret($providerKey,'webhook_signing_secret',$accountId,$mode);
        return $row?(string)$row->key_version:'';
    }
    private function signatureHeader(array $headers):?string{
        foreach($headers as $name=>$value)if(strtolower((string)$name)==='stripe-signature')return (string)$value;
        return null;
    }
    private function receiptView(object $receipt):array{
        return array('receipt_id'=>(int)$receipt->id,'provider_key'=>(string)$receipt->provider_key,
            'payment_provider_account_id'=>$receipt->payment_provider_account_id===null?null:(int)$receipt->payment_provider_account_id,
            'verification_state'=>(string)$receipt->verification_state,'refusal_reason_code'=>$receipt->refusal_reason_code,
            'signature_key_version'=>$receipt->signature_key_version,'body_bytes'=>(int)$receipt->body_bytes,'received_at'=>(string)$receipt->received_at);
    }
    private function eventView(object $event):array{
        return array('event_id'=>(int)$event->id,'provider_key'=>(string)$event->provider_key,
            'payment_provider_account_id'=>(int)$event->payment_provider_account_id,'event_type'=>(string)$event->event_type,
            'provider_occurred_at'=>$event->provider_occurred_at,'received_at'=>(string)$event->received_at);
    }
    private function decisionView(object $decision):array{
        return array('decision_id'=>(int)$decision->id,'decision_sequence'=>(int)$decision->decision_sequence,
            'decision_state'=>(string)$decision->decision_state,'reason_code'=>$decision->reason_code,
            'evidence_kind'=>$decision->evidence_kind,'obligation_id'=>$decision->obligation_id===null?null:(int)$decision->obligation_id,
            'purchase_id'=>$decision->purchase_id===null?null:(int)$decision->purchase_id,
            'collection_intent_id'=>$decision->collection_intent_id===null?null:(int)$decision->collection_intent_id,
            'renewal_cycle_id'=>$decision->renewal_cycle_id===null?null:(int)$decision->renewal_cycle_id,
            'renewal_cycle_state'=>$decision->renewal_cycle_state,'collection_intent_state'=>$decision->collection_intent_state,
            'r2_consequence_state'=>(string)$decision->r2_consequence_state,'r2_reason_code'=>$decision->r2_reason_code,
            'recorded_by'=>$decision->recorded_by===null?null:(int)$decision->recorded_by,'decided_at'=>(string)$decision->decided_at);
    }
}
