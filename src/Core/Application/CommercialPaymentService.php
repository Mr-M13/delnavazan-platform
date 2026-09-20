<?php
namespace Delnavazan\Platform\Core\Application;

use Delnavazan\Platform\Core\Infrastructure\Repository\{CommercialAuthorityRepository,CommercialPaymentRepository};
use Delnavazan\Platform\Core\Support\Identifier;

/**
 * Provider-neutral payment evidence, obligation settlement and purchase acceptance authority.
 *
 * A provider is only a source of evidence. Nothing here trusts a browser redirect, success page,
 * button click or client-supplied amount: acceptance requires verified evidence attributed to one
 * exact Platform-issued obligation, matching that obligation's amount and currency, whose
 * provider-confirmed occurrence instant falls inside the offer window. Financial settlement and
 * academic effectiveness are deliberately distinct: an obligation may be settled while its tranche
 * remains academically ineffective until every lower-sequence obligation is settled.
 */
final class CommercialPaymentService {
    private const CAPABILITY='dzn_ingest_commercial_payment_evidence';
    public function __construct(
        private ?CommercialAuthorityRepository $repository=null,
        private ?CommercialPaymentRepository $payments=null,
        private ?CommercialExceptionService $exceptions=null
    ){
        $this->repository??=new CommercialAuthorityRepository();
        $this->payments??=new CommercialPaymentRepository();
        $this->exceptions??=new CommercialExceptionService();
    }

    /** Ingest one provider evidence fact. Duplicate evidence converges; it never settles twice. */
    public function ingest(array $input,string $key):array{
        CommercialSupport::requireCapability(self::CAPABILITY);
        $actor=CommercialSupport::actor();
        $providerKey=strtolower(trim((string)($input['provider_key']??'')));
        if(preg_match('/^[a-z0-9_]{1,32}$/D',$providerKey)!==1)throw new \InvalidArgumentException('Controlled provider key required');
        $providerReference=trim((string)($input['provider_reference']??''));
        if($providerReference==='')throw new \InvalidArgumentException('Provider evidence reference required');
        $providerDigest=CommercialIdempotency::providerReference($providerKey,$providerReference);
        $kind=(string)($input['evidence_kind']??'');
        if(!in_array($kind,CommercialRule::EVIDENCE_KINDS,true))throw new \InvalidArgumentException('Controlled evidence kind required');
        $amount=($input['amount_minor']??null)===null||$input['amount_minor']===''?null:CommercialSupport::amount($input['amount_minor'],'Exact minor-unit amount required');
        $currency=($input['currency']??null)===null||$input['currency']===''?null:CommercialRule::currency((string)$input['currency']);
        if(($amount===null)!==($currency===null))throw new \InvalidArgumentException('Exact amount and explicit currency must be supplied together');
        if($currency===null&&(string)($input['currency']??'')!=='')throw new \InvalidArgumentException('Supported currency required');
        $occurredAt=($input['provider_occurred_at']??null)===null||$input['provider_occurred_at']===''?null:CommercialSupport::utc($input['provider_occurred_at'],'Valid provider occurrence time required');
        if($kind==='success'&&($amount===null||$occurredAt===null))throw new \InvalidArgumentException('Successful evidence requires an exact amount and its provider occurrence time');
        if($occurredAt!==null&&$occurredAt>gmdate('Y-m-d H:i:s',time()+300))throw new \InvalidArgumentException('Provider occurrence time cannot be in the future');
        $obligationReference=trim((string)($input['obligation_reference']??''));
        $obligationDigest=$obligationReference===''?null:CommercialIdempotency::obligationReference($obligationReference);
        $accountReference=trim((string)($input['provider_account_reference']??''));
        $accountDigest=$accountReference===''?null:CommercialIdempotency::evidence($accountReference);
        $evidence=CommercialSupport::evidence($input);
        $now=CommercialSupport::now();

        $existing=$this->payments->evidenceByProviderReference($providerKey,$providerDigest);
        if($existing){
            if((string)$existing->evidence_kind!==$kind||($amount!==null&&(int)$existing->amount_minor!==$amount)||($currency!==null&&(string)$existing->currency!==$currency)){
                throw new \RuntimeException('Idempotency conflict');
            }
            return $this->evidenceOutcome($existing);
        }

        $obligation=$obligationDigest===null?null:$this->repository->obligationByReferenceDigest($obligationDigest);
        $offer=$obligation===null?null:$this->repository->offer((int)$obligation->offer_id);
        if($obligation===null||$offer===null){
            // Unattributable evidence is never guessed: it is preserved and routed for reconciliation.
            return $this->recordUnattributed($input,$providerKey,$providerDigest,$kind,$amount,$currency,$occurredAt,$obligationDigest,$accountDigest,$evidence,$actor,$now);
        }
        $studentId=(int)$offer->beneficiary_student_id;
        $digest=CommercialSupport::keyString($key);
        $payload=CommercialIdempotency::payload(array(
            'domain'=>CommercialRule::DOMAIN,'operation'=>'ingest_payment_evidence','provider_key'=>$providerKey,
            'provider_reference_digest'=>$providerDigest,'evidence_kind'=>$kind,'amount_minor'=>$amount,'currency'=>$currency,
            'obligation_id'=>(int)$obligation->id,'provider_occurred_at'=>$occurredAt,'evidence_reference_digest'=>$evidence['digest'],
        ));
        $this->repository->begin();
        try{
            if($winner=$this->repository->command($digest)){$result=$this->replay($winner,$payload);$this->repository->commit();return $result;}
            $this->repository->lockAccountRoot($studentId,$actor);
            $offer=$this->repository->offer((int)$offer->id,true);
            $obligation=$this->repository->obligation((int)$obligation->id,true);
            if(!$offer||!$obligation)throw new \InvalidArgumentException('commercial_obligation_required');
            $obligations=$this->repository->obligationsForOffer((int)$offer->id,true);
            if(!CommercialValidator::offerValid($offer,$obligations,$this->repository->offerAdjustments((int)$offer->id)))throw new \InvalidArgumentException('commercial_offer_integrity_conflict');
            if($kind!=='success')return $this->recordNonSettlement($offer,$obligation,$kind,$providerKey,$providerDigest,$amount,$currency,$occurredAt,$accountDigest,$evidence,$digest,$payload,$actor,$now);

            // Exact, provider-neutral acceptance checks. Nothing here can be influenced by a client.
            if(!in_array((string)$offer->state,array('issued','accepted'),true)){
                $this->repository->commit();
                return $this->reject($offer,$obligation,$providerKey,$providerDigest,$kind,$amount,$currency,$occurredAt,$obligationDigest,$accountDigest,$evidence,$actor,$now,'invalid_or_expired_offer');
            }
            if($offer->expires_at!==null&&$occurredAt!==null&&$occurredAt>(string)$offer->expires_at){
                $this->repository->commit();
                return $this->reject($offer,$obligation,$providerKey,$providerDigest,$kind,$amount,$currency,$occurredAt,$obligationDigest,$accountDigest,$evidence,$actor,$now,'late_payment_after_offer_window');
            }
            if((int)$obligation->amount_minor!==(int)$amount){
                $this->repository->commit();
                return $this->reject($offer,$obligation,$providerKey,$providerDigest,$kind,$amount,$currency,$occurredAt,$obligationDigest,$accountDigest,$evidence,$actor,$now,'amount_mismatch');
            }
            if((string)$obligation->currency!==(string)$currency){
                $this->repository->commit();
                return $this->reject($offer,$obligation,$providerKey,$providerDigest,$kind,$amount,$currency,$occurredAt,$obligationDigest,$accountDigest,$evidence,$actor,$now,'currency_mismatch');
            }
            $settlement=$this->payments->settlementForObligation((int)$obligation->id,true);
            if($settlement){
                $this->repository->commit();
                return $this->reject($offer,$obligation,$providerKey,$providerDigest,$kind,$amount,$currency,$occurredAt,$obligationDigest,$accountDigest,$evidence,$actor,$now,'conflicting_payment_evidence');
            }
            $purchase=$this->repository->purchaseByOffer((int)$offer->id,true);
            $evidenceId=$this->payments->insertEvidence(array(
                'uid'=>Identifier::uid(),'reference_code'=>null,'provider_key'=>$providerKey,'provider_account_digest'=>$accountDigest,
                'evidence_reference_digest'=>$providerDigest,'evidence_kind'=>$kind,'amount_minor'=>$amount,'currency'=>$currency,
                'obligation_reference_digest'=>$obligationDigest,'provider_occurred_at'=>$occurredAt,'ingested_at'=>$now,
                'processing_state'=>'accepted','reason_code'=>null,'offer_id'=>(int)$offer->id,
                'purchase_id'=>$purchase?(int)$purchase->id:null,'obligation_id'=>(int)$obligation->id,
                'created_at'=>$now,'created_by'=>$actor,
            ));
            do_action('dzn_phase_2a2r1_after_evidence_insert',$evidenceId);
            $this->payments->insertSettlement(array(
                'uid'=>Identifier::uid(),'obligation_id'=>(int)$obligation->id,'evidence_id'=>$evidenceId,
                'amount_minor'=>(int)$amount,'currency'=>(string)$currency,'settled_at'=>$now,
                'created_at'=>$now,'created_by'=>$actor,
            ));
            $purchaseId=$purchase?(int)$purchase->id:$this->createPurchase($offer,$evidenceId,$occurredAt,$now,$actor);
            $this->payments->insertFact(array(
                'uid'=>Identifier::uid(),'purchase_id'=>$purchaseId,'evidence_id'=>$evidenceId,'obligation_id'=>(int)$obligation->id,
                'amount_minor'=>(int)$amount,'currency'=>(string)$currency,'occurred_at'=>$occurredAt,'recorded_at'=>$now,
                'created_at'=>$now,'created_by'=>$actor,
            ));
            do_action('dzn_phase_2a2r1_after_settlement',$purchaseId);
            $entitlementId=$this->ensureEntitlement($purchaseId,$offer,$now,$actor);
            if((string)$offer->state!=='accepted')$this->repository->updateOfferState((int)$offer->id,(int)$offer->offer_version,array('state'=>'accepted','updated_at'=>$now,'updated_by'=>$actor));
            $this->repository->insertCommand(array(
                'uid'=>Identifier::uid(),'command_domain'=>CommercialRule::DOMAIN,'operation'=>'ingest_payment_evidence',
                'command_key_digest'=>$digest,'command_payload_digest'=>$payload,'student_id'=>$studentId,'teacher_id'=>(int)$offer->teacher_id,
                'offer_id'=>(int)$offer->id,'obligation_id'=>(int)$obligation->id,'purchase_id'=>$purchaseId,'entitlement_id'=>$entitlementId,
                'result_state'=>'settled','result_id'=>$evidenceId,'created_at'=>$now,'created_by'=>$actor,
            ));
            $obligations=$this->repository->obligationsForOffer((int)$offer->id);
            $status=$this->funding()->obligationStatus((int)$offer->id);
            if($obligation->obligation_sequence>1&&!$status['prerequisites_satisfied']){
                $this->exceptions->recordWithinTransaction(array(
                    'reason_code'=>CommercialRule::RECONCILIATION_SIGNAL_PREREQUISITE,'severity'=>'info',
                    'summary'=>'Instalment settled before its predecessor; academic funding stays blocked',
                    'student_id'=>$studentId,'offer_id'=>(int)$offer->id,'purchase_id'=>$purchaseId,
                    'obligation_id'=>(int)$obligation->id,'evidence_id'=>$evidenceId,'teacher_id'=>(int)$offer->teacher_id,
                    'fingerprint_value'=>$purchaseId.':'.(int)$obligation->obligation_sequence,
                ));
            }
            $this->repository->commit();
            return array(
                'evidence_id'=>$evidenceId,'processing_state'=>'accepted','reason_code'=>null,
                'offer_id'=>(int)$offer->id,'obligation_id'=>(int)$obligation->id,'obligation_sequence'=>(int)$obligation->obligation_sequence,
                'purchase_id'=>$purchaseId,'entitlement_id'=>$entitlementId,
                'effective_sessions'=>$this->funding()->effectiveSessions((int)$offer->id),
                'created'=>true,
            );
        }catch(\Throwable$e){
            $this->repository->rollback();
            if($this->repository->duplicate($e)==='command_key_digest'&&($winner=$this->repository->command($digest)))return $this->replay($winner,$payload);
            throw $e;
        }
    }

    public function evidence(int $evidenceId):array{
        CommercialSupport::requireCapability('dzn_view_commercial_authority');
        $row=$this->payments->evidence($evidenceId);
        if(!$row||!CommercialValidator::evidenceValid($row))throw new \InvalidArgumentException('commercial_evidence_required');
        return $this->evidenceOutcome($row);
    }
    public function evidenceForPurchase(int $purchaseId):array{
        CommercialSupport::requireCapability('dzn_view_commercial_authority');
        $rows=array();
        foreach($this->payments->evidenceForPurchase($purchaseId) as $row)$rows[]=$this->evidenceOutcome($row);
        return $rows;
    }

    /** Preserve unattributable evidence and route it; never guess which obligation it belongs to. */
    private function recordUnattributed(array $input,string $providerKey,string $providerDigest,string $kind,?int $amount,?string $currency,?string $occurredAt,?string $obligationDigest,?string $accountDigest,array $evidence,int $actor,string $now):array{
        $requiresAttribution=in_array($kind,array('success','refund'),true);
        $this->payments->begin();
        try{
            $existing=$this->payments->evidenceByProviderReference($providerKey,$providerDigest,true);
            if($existing){$this->payments->commit();return $this->evidenceOutcome($existing);}
            $evidenceId=$this->payments->insertEvidence(array(
                'uid'=>Identifier::uid(),'reference_code'=>null,'provider_key'=>$providerKey,'provider_account_digest'=>$accountDigest,
                'evidence_reference_digest'=>$providerDigest,'evidence_kind'=>$kind,'amount_minor'=>$amount,'currency'=>$currency,
                'obligation_reference_digest'=>$obligationDigest,'provider_occurred_at'=>$occurredAt,'ingested_at'=>$now,
                'processing_state'=>$requiresAttribution?'unmatched':'accepted',
                'reason_code'=>$requiresAttribution?'unmatched_payment_evidence':null,
                'offer_id'=>null,'purchase_id'=>null,'obligation_id'=>null,'created_at'=>$now,'created_by'=>$actor,
            ));
            if($requiresAttribution){
                $this->exceptions->recordWithinTransaction(array(
                    'reason_code'=>'unmatched_payment_evidence','severity'=>'warning',
                    'summary'=>'Provider evidence could not be attributed to a Platform obligation',
                    'evidence_id'=>$evidenceId,'fingerprint_value'=>$providerKey.':'.$providerDigest,
                ));
            }
            $this->payments->commit();
            $row=$this->payments->evidence($evidenceId);
            return $this->evidenceOutcome($row);
        }catch(\Throwable$e){
            $this->payments->rollback();
            $existing=$this->payments->evidenceByProviderReference($providerKey,$providerDigest);
            if($existing)return $this->evidenceOutcome($existing);
            throw $e;
        }
    }

    /** A non-success fact is durable evidence with no financial or academic consequence. */
    private function recordNonSettlement(object $offer,object $obligation,string $kind,string $providerKey,string $providerDigest,?int $amount,?string $currency,?string $occurredAt,?string $accountDigest,array $evidence,string $digest,string $payload,int $actor,string $now):array{
        $purchase=$this->repository->purchaseByOffer((int)$offer->id);
        $evidenceId=$this->payments->insertEvidence(array(
            'uid'=>Identifier::uid(),'reference_code'=>null,'provider_key'=>$providerKey,'provider_account_digest'=>$accountDigest,
            'evidence_reference_digest'=>$providerDigest,'evidence_kind'=>$kind,'amount_minor'=>$amount,'currency'=>$currency,
            'obligation_reference_digest'=>(string)$obligation->obligation_reference_digest,'provider_occurred_at'=>$occurredAt,
            'ingested_at'=>$now,'processing_state'=>'accepted','reason_code'=>null,'offer_id'=>(int)$offer->id,
            'purchase_id'=>$purchase?(int)$purchase->id:null,'obligation_id'=>(int)$obligation->id,'created_at'=>$now,'created_by'=>$actor,
        ));
        if($kind==='refund'){
            $this->exceptions->recordWithinTransaction(array(
                'reason_code'=>'refund_evidence_received','severity'=>'warning',
                'summary'=>'Refund evidence recorded; academic and commercial consequences require explicit authority',
                'student_id'=>(int)$offer->beneficiary_student_id,'offer_id'=>(int)$offer->id,
                'obligation_id'=>(int)$obligation->id,'evidence_id'=>$evidenceId,'teacher_id'=>(int)$offer->teacher_id,
                'fingerprint_value'=>$evidenceId,
            ));
        }
        $this->repository->insertCommand(array(
            'uid'=>Identifier::uid(),'command_domain'=>CommercialRule::DOMAIN,'operation'=>'ingest_payment_evidence',
            'command_key_digest'=>$digest,'command_payload_digest'=>$payload,'student_id'=>(int)$offer->beneficiary_student_id,
            'teacher_id'=>(int)$offer->teacher_id,'offer_id'=>(int)$offer->id,'obligation_id'=>(int)$obligation->id,
            'purchase_id'=>$purchase?(int)$purchase->id:null,'result_state'=>'recorded','result_id'=>$evidenceId,
            'created_at'=>$now,'created_by'=>$actor,
        ));
        $this->repository->commit();
        $row=$this->payments->evidence($evidenceId);
        return $this->evidenceOutcome($row);
    }

    /** Refuse a success that cannot be accepted, and keep the evidence plus its reason durable. */
    private function reject(object $offer,object $obligation,string $providerKey,string $providerDigest,string $kind,?int $amount,?string $currency,?string $occurredAt,?string $obligationDigest,?string $accountDigest,array $evidence,int $actor,string $now,string $reason):array{
        $purchase=$this->repository->purchaseByOffer((int)$offer->id);
        $evidenceId=$this->payments->insertEvidence(array(
            'uid'=>Identifier::uid(),'reference_code'=>null,'provider_key'=>$providerKey,'provider_account_digest'=>$accountDigest,
            'evidence_reference_digest'=>$providerDigest,'evidence_kind'=>$kind,'amount_minor'=>$amount,'currency'=>$currency,
            'obligation_reference_digest'=>$obligationDigest,'provider_occurred_at'=>$occurredAt,'ingested_at'=>$now,
            'processing_state'=>'rejected','reason_code'=>$reason,'offer_id'=>(int)$offer->id,
            'purchase_id'=>$purchase?(int)$purchase->id:null,'obligation_id'=>(int)$obligation->id,'created_at'=>$now,'created_by'=>$actor,
        ));
        $this->exceptions->recordWithinTransaction(array(
            'reason_code'=>$reason,'severity'=>in_array($reason,array('amount_mismatch','currency_mismatch','conflicting_payment_evidence','late_payment_after_offer_window'),true)?'error':'warning',
            'summary'=>'Verified provider evidence could not be accepted for this obligation',
            'student_id'=>(int)$offer->beneficiary_student_id,'offer_id'=>(int)$offer->id,
            'obligation_id'=>(int)$obligation->id,'evidence_id'=>$evidenceId,'teacher_id'=>(int)$offer->teacher_id,
            'fingerprint_value'=>$providerKey.':'.$providerDigest,
        ));
        $this->repository->commit();
        $row=$this->payments->evidence($evidenceId);
        $outcome=$this->evidenceOutcome($row);
        $outcome['rejected']=true;
        return $outcome;
    }

    private function createPurchase(object $offer,int $evidenceId,string $occurredAt,string $now,int $actor):int{
        return $this->repository->insertPurchase(array(
            'uid'=>Identifier::uid(),'reference_code'=>null,'offer_id'=>(int)$offer->id,
            'beneficiary_student_id'=>(int)$offer->beneficiary_student_id,'product_id'=>(int)$offer->product_id,
            'currency'=>(string)$offer->currency,'amount_minor'=>(int)$offer->amount_due_minor,'plan_kind'=>(string)$offer->plan_kind,
            'state'=>'accepted','reconciliation_state'=>'none','accepted_at'=>$occurredAt,'first_evidence_id'=>$evidenceId,
            'purchase_version'=>1,'created_at'=>$now,'created_by'=>$actor,'updated_at'=>$now,'updated_by'=>$actor,
        ));
    }
    private function ensureEntitlement(int $purchaseId,object $offer,string $now,int $actor):int{
        $existing=$this->repository->entitlementForPurchase($purchaseId,true);
        if($existing)return (int)$existing->id;
        return $this->repository->insertEntitlement(array(
            'uid'=>Identifier::uid(),'reference_code'=>null,'purchase_id'=>$purchaseId,
            'beneficiary_student_id'=>(int)$offer->beneficiary_student_id,'session_count'=>(int)$offer->committed_sessions,
            'state'=>'issued','term_id'=>null,'enrolment_id'=>null,'issued_at'=>$now,'bound_at'=>null,
            'entitlement_version'=>1,'created_at'=>$now,'created_by'=>$actor,'updated_at'=>$now,'updated_by'=>$actor,
        ));
    }
    private function funding():CommercialTermFundingService{return new CommercialTermFundingService($this->repository,$this->payments);}
    private function evidenceOutcome(object $evidence):array{
        $purchaseId=$evidence->purchase_id===null?null:(int)$evidence->purchase_id;
        $entitlementId=null;
        if($purchaseId!==null){$entitlement=$this->repository->entitlementForPurchase($purchaseId);$entitlementId=$entitlement?(int)$entitlement->id:null;}
        return array(
            'evidence_id'=>(int)$evidence->id,'processing_state'=>(string)$evidence->processing_state,
            'reason_code'=>$evidence->reason_code===null?null:(string)$evidence->reason_code,
            'offer_id'=>$evidence->offer_id===null?null:(int)$evidence->offer_id,
            'obligation_id'=>$evidence->obligation_id===null?null:(int)$evidence->obligation_id,
            'purchase_id'=>$purchaseId,'entitlement_id'=>$entitlementId,
            'effective_sessions'=>$evidence->offer_id===null?0:$this->funding()->effectiveSessions((int)$evidence->offer_id),
            'created'=>false,'idempotent'=>true,
        );
    }
    private function replay(object $command,string $payload):array{
        if(!hash_equals((string)$command->command_payload_digest,$payload))throw new \RuntimeException('Idempotency conflict');
        if((string)$command->command_domain!==CommercialRule::DOMAIN||(string)$command->operation!=='ingest_payment_evidence')throw new \RuntimeException('Contaminated commercial payment command');
        $evidence=$this->payments->evidence((int)$command->result_id);
        if(!$evidence||!CommercialValidator::evidenceValid($evidence))throw new \RuntimeException('Contaminated commercial payment result');
        return $this->evidenceOutcome($evidence);
    }
}
