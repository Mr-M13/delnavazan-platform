<?php
namespace Delnavazan\Platform\Core\Application;

use Delnavazan\Platform\Core\Application\Port\ProviderEventNormalizer;
use Delnavazan\Platform\Core\Infrastructure\Repository\ProviderIntegrationRepository;
use Delnavazan\Platform\Core\Support\Identifier;

/**
 * Provider-event ingestion into the Phase-P evidence seam (Phase 2A.2-V, V-D8/V-D9).
 *
 * The integration layer is a trusted evidence *source*, never an evidence owner. It verifies the
 * delivery, translates it to provider-neutral facts, deduplicates on the provider event key against
 * the complete immutable context, records a durable conflict receipt for any changed context, and
 * then hands the facts to `CanonicalAttendanceIntakeService::ingestProviderEvidence`.
 *
 * It never resolves a participant identity, never asserts verification, never writes Phase-O outcome
 * storage, never settles attendance and never completes or cancels a Lesson. A provider conference or
 * a calendar event existing is an integration reference, not attendance evidence.
 */
final class ProviderEventIngestService {
    public function __construct(
        private ?ProviderIntegrationRepository $repository=null,
        private ?ProviderEventNormalizer $normalizer=null,
        private ?CanonicalAttendanceIntakeService $intake=null
    ){
        $this->repository??=new ProviderIntegrationRepository();
        if($this->normalizer===null)throw new \RuntimeException('Provider event normaliser must be supplied by the Integrations module');
        $this->intake??=new CanonicalAttendanceIntakeService();
    }

    /**
     * Ingest one provider delivery.
     *
     * An exact duplicate converges on the recorded receipt; a materially different context is durably
     * refused as a conflict and the original evidence row is never overwritten.
     */
    public function ingest(array $delivery,string $key):array{
        $this->requireCapability();
        $actor=$this->actor();
        $providerCode=ProviderIntegrationRule::evidenceProviderCode((string)($delivery['provider_code']??''));
        if(!$this->normalizer->verify($delivery))throw new \InvalidArgumentException('provider_event_unverified');
        $facts=$this->normalizer->normalise($delivery);
        $facts['provider_code']=$providerCode;
        $eventKey=trim((string)($facts['provider_event_key']??''));
        if($eventKey==='')throw new \InvalidArgumentException('Provider event key required');
        $eventKeyDigest=ProviderIntegrationIdempotency::providerEventKey($eventKey);
        $lessonId=(int)($delivery['lesson_id']??$facts['lesson_id']??0);
        $scheduleVersionId=(int)($delivery['schedule_version_id']??$facts['schedule_version_id']??0);
        if($lessonId<1||$scheduleVersionId<1)throw new \InvalidArgumentException('exact_occurrence_required');
        $context=$this->contextFacts($facts,$lessonId,$scheduleVersionId);
        $eventFactDigest=ProviderIntegrationIdempotency::payload($context);
        $digest=ProviderIntegrationIdempotency::key($key);
        $now=gmdate('Y-m-d H:i:s');
        $this->repository->begin();
        try{
            $this->repository->lockLessonRoots($lessonId);
            $existing=$this->repository->ingestEvent($providerCode,$eventKeyDigest,true);
            if($existing){
                if(hash_equals((string)$existing->event_fact_digest,$eventFactDigest)){
                    // Exact duplicate: no double count, no new receipt, and the recorded row must still
                    // satisfy the aggregate contract before the duplicate is acknowledged.
                    if(!ProviderIntegrationValidator::ingestEventShape($existing))throw new \RuntimeException('provider_event_receipt_corrupt');
                    $this->repository->commit();
                    return array('ingest_event_id'=>(int)$existing->id,'processing_state'=>(string)$existing->processing_state,'conflict'=>false,'idempotent'=>true,'operation'=>'ingest_provider_event');
                }
                $kind=$this->conflictKind($existing,$context,$lessonId,$scheduleVersionId);
                $conflictId=$this->recordConflict($existing,$providerCode,$eventKeyDigest,$kind,$eventFactDigest,$lessonId,$scheduleVersionId,(string)$facts['observed_at'],$now,$actor);
                $this->repository->commit();
                return array('ingest_event_id'=>(int)$existing->id,'conflict_id'=>$conflictId,'conflict_kind'=>$kind,'processing_state'=>'conflicted','conflict'=>true,'idempotent'=>false,'operation'=>'ingest_provider_event');
            }
            $eventId=$this->repository->insertIngestEvent(array(
                'uid'=>Identifier::uid(),'connection_id'=>isset($delivery['connection_id'])?(int)$delivery['connection_id']:null,
                'provider_code'=>$providerCode,'provider_event_key_digest'=>$eventKeyDigest,'event_fact_digest'=>$eventFactDigest,
                'lesson_id'=>$lessonId,'schedule_version_id'=>$scheduleVersionId,
                'participant_role'=>(string)$context['participant_role'],'provider_account_digest'=>(string)$context['provider_account_digest'],
                'event_sequence'=>$this->repository->maxEventSequence($providerCode)+1,
                'join_at_utc'=>$context['join_at_utc'],'leave_at_utc'=>$context['leave_at_utc'],
                'occurred_at'=>(string)$context['observed_at'],'received_at'=>$now,
                'processing_state'=>'admitted','reason_code'=>null,'conflicting_fact_digest'=>null,
                'created_at'=>$now,'created_by'=>$actor,
            ));
            // Deterministic gated boundary: the ingest receipt is written and the integration
            // transaction is still open. Phase P is never called with an integration row held.
            do_action('dzn_phase_2a2v_after_ingest_write',$eventId);
            $this->repository->commit();
        }catch(\Throwable$e){
            $this->repository->rollback();
            if($this->repository->duplicate($e)==='provider_event'&&($winner=$this->repository->ingestEvent($providerCode,$eventKeyDigest))){
                if(!hash_equals((string)$winner->event_fact_digest,$eventFactDigest))throw new IdempotencyConflictException('Idempotency conflict');
                return array('ingest_event_id'=>(int)$winner->id,'processing_state'=>(string)$winner->processing_state,'conflict'=>false,'idempotent'=>true,'operation'=>'ingest_provider_event');
            }
            throw $e;
        }
        // Phase P owns intake, identity resolution, assessment and any canonical consequence. The
        // integration layer supplies facts only, and its own transaction is already closed so no
        // integration row is held across the Phase-P transaction.
        $intakeInput=array(
            'provider_code'=>$providerCode,
            'provider_account_key'=>(string)$facts['provider_account_key'],
            'provider_event_key'=>$eventKey,
            'provider_payload_key'=>(string)($facts['provider_payload_key']??$eventKey),
            'participant_role'=>(string)$facts['participant_role'],
            'observed_at'=>(string)$facts['observed_at'],
            'join_at_utc'=>$facts['join_at_utc']??null,
            'leave_at_utc'=>$facts['leave_at_utc']??null,
            'provenance_reference'=>(string)($facts['provenance_reference']??$eventKey),
            'evidence_reference'=>(string)($facts['evidence_reference']??$eventKey),
        );
        try{
            $result=$this->intake->ingestProviderEvidence($lessonId,$scheduleVersionId,$intakeInput,$key);
        }catch(\Throwable$e){
            $this->markProcessingState($eventId,'refused',$this->refusalCode($e));
            throw $e;
        }
        $this->markProcessingState($eventId,'admitted',null);
        return array('ingest_event_id'=>$eventId,'processing_state'=>'admitted','conflict'=>false,'idempotent'=>false,'intake'=>$result,'operation'=>'ingest_provider_event');
    }

    /**
     * The complete immutable context of one provider event.
     *
     * Every field that could change the meaning of the event is part of the digest, so a changed
     * payload, a moved occurrence, a different participant or a shifted interval can never converge.
     */
    private function contextFacts(array $facts,int $lessonId,int $scheduleVersionId):array{
        $account=trim((string)($facts['provider_account_key']??''));
        $role=(string)($facts['participant_role']??'');
        $observed=(string)($facts['observed_at']??'');
        if($account==='')throw new \InvalidArgumentException('Provider account identity required');
        if(!in_array($role,array('teacher','student'),true))throw new \InvalidArgumentException('Controlled participant role required');
        if(!ProviderIntegrationRule::utc($observed))throw new \InvalidArgumentException('Valid UTC observed time required');
        $join=$facts['join_at_utc']??null;$leave=$facts['leave_at_utc']??null;
        foreach(array($join,$leave) as $instant)if($instant!==null&&!ProviderIntegrationRule::utc($instant))throw new \InvalidArgumentException('Valid UTC join instant required');
        return array(
            'provider_code'=>ProviderIntegrationRule::evidenceProviderCode((string)$facts['provider_code']),
            'lesson_id'=>$lessonId,'schedule_version_id'=>$scheduleVersionId,'participant_role'=>$role,
            'provider_account_digest'=>ProviderIntegrationIdempotency::evidence($account),
            'provider_payload_digest'=>ProviderIntegrationIdempotency::providerPayload((string)($facts['provider_payload_key']??$facts['provider_event_key'])),
            'observed_at'=>$observed,'join_at_utc'=>$join,'leave_at_utc'=>$leave,
        );
    }

    /** Classify the divergence so the receipt is actionable without ever being a silent overwrite. */
    private function conflictKind(object $existing,array $context,int $lessonId,int $scheduleVersionId):string{
        if((int)$existing->lesson_id!==$lessonId)return 'cross_lesson';
        if((int)$existing->schedule_version_id!==$scheduleVersionId)return 'cross_schedule_version';
        if((string)$existing->participant_role!==(string)$context['participant_role'])return 'cross_participant';
        if(!hash_equals((string)$existing->provider_account_digest,(string)$context['provider_account_digest']))return 'cross_participant';
        if((string)$existing->join_at_utc!==(string)($context['join_at_utc']??'')||(string)$existing->leave_at_utc!==(string)($context['leave_at_utc']??''))return 'cross_interval';
        return 'changed_payload';
    }

    private function recordConflict(object $existing,string $providerCode,string $eventKeyDigest,string $kind,string $conflictingDigest,int $lessonId,int $scheduleVersionId,string $observedAt,string $now,int $actor):int{
        if($found=$this->repository->conflict($providerCode,$eventKeyDigest,$conflictingDigest))return(int)$found->id;
        return $this->repository->insertConflict(array(
            'uid'=>Identifier::uid(),'provider_ingest_event_id'=>(int)$existing->id,'provider_code'=>$providerCode,
            'provider_event_key_digest'=>$eventKeyDigest,'conflict_kind'=>$kind,'conflicting_fact_digest'=>$conflictingDigest,
            'lesson_id'=>$lessonId,'schedule_version_id'=>$scheduleVersionId,
            'observed_at'=>ProviderIntegrationRule::utc($observedAt)?$observedAt:$now,
            'recorded_at'=>$now,'recorded_by'=>$actor,'created_at'=>$now,'created_by'=>$actor,
        ));
    }

    /** The recorded outcome of one ingest attempt is write-once: it is set exactly once, after Phase P. */
    private function markProcessingState(int $eventId,string $state,?string $reason):void{
        global $wpdb;
        $result=$wpdb->update($wpdb->prefix.'dzn_provider_ingest_events',array('processing_state'=>$state,'reason_code'=>$reason),array('id'=>$eventId,'processing_state'=>'admitted'));
        if(false===$result)throw new \RuntimeException('Provider event receipt update failed');
        if($result===0&&$state!=='admitted')throw new \RuntimeException('Provider event receipt already settled');
    }

    private function refusalCode(\Throwable $e):string{
        $message=strtolower($e->getMessage());
        if(str_contains($message,'unauthorized'))return 'capability_refused';
        if(str_contains($message,'conflict'))return 'evidence_conflict';
        if(str_contains($message,'identity'))return 'participant_identity_unresolved';
        return 'evidence_refused';
    }

    private function requireCapability():void{
        if(!current_user_can(ProviderIntegrationService::INGEST_CAPABILITY))throw new \RuntimeException('Unauthorized');
    }

    private function actor():int{
        $id=get_current_user_id();
        if($id<1)throw new \RuntimeException('Integration actor unavailable');
        return $id;
    }
}
