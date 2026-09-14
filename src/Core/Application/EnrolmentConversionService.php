<?php
namespace Delnavazan\Platform\Core\Application;

use Delnavazan\Platform\Core\Infrastructure\Repository\EnrolmentConversionRepository;
use Delnavazan\Platform\Core\Support\Identifier;

/** Explicit atomic authority for Accepted Service Arrangement conversion only. */
final class EnrolmentConversionService {
    private const CAPABILITY='dzn_convert_service_arrangements_to_enrolments';
    public function __construct(private ?EnrolmentConversionRepository$repository=null){$this->repository??=new EnrolmentConversionRepository();}
    public function convert(int$acceptedServiceArrangementId,string$idempotencyKey):array{
        if(!current_user_can(self::CAPABILITY))throw new \RuntimeException('Unauthorized');
        $actor=get_current_user_id();if($actor<1)throw new \RuntimeException('Enrolment conversion actor is unavailable');
        if($acceptedServiceArrangementId<1)throw new \InvalidArgumentException('Accepted Service Arrangement identity required');
        $key=EnrolmentConversionIdempotency::keyDigest($idempotencyKey);$payload=EnrolmentConversionIdempotency::payloadDigest($acceptedServiceArrangementId);
        if($command=$this->repository->commandForDigest($key))return$this->replay($command,$payload);
        $hint=$this->repository->arrangementById($acceptedServiceArrangementId);if(!$hint)throw new \InvalidArgumentException('source_integrity_conflict');
        $this->repository->begin();
        try{
            $graph=$this->repository->sourceGraphForUpdate($hint);if(!$graph)throw new \InvalidArgumentException('source_integrity_conflict');
            if($command=$this->repository->commandForDigest($key)){ $result=$this->replay($command,$payload);$this->repository->commit();return$result; }
            $a=$graph['arrangement'];
            $sourceOnly=EnrolmentConversionAssessment::evaluate($this->repository,$graph,array(),array(),$this->repository->student((int)$a->student_id,false),$a->course_id===null?null:$this->repository->course((int)$a->course_id,false),$this->repository->teacher((int)$a->teacher_id,false));
            if(in_array($sourceOnly->classification,array('source_integrity_conflict','source_not_final'),true))throw new \InvalidArgumentException($sourceOnly->classification);
            $now=gmdate('Y-m-d H:i:s');$this->repository->materializeAndLockIdentityRoot((int)$a->student_id,(int)$a->course_id,$actor,$now);do_action('dzn_phase_2a2i_identity_root_locked');
            $student=$this->repository->student((int)$a->student_id,true);$course=$this->repository->course((int)$a->course_id,true);$teacher=$this->repository->teacher((int)$a->teacher_id,true);
            $rows=$this->repository->enrolments((int)$a->student_id,(int)$a->course_id,true);$events=$this->repository->lifecycleEvents($rows,true);
            $assessment=EnrolmentConversionAssessment::evaluate($this->repository,$graph,$rows,$events,$student,$course,$teacher);do_action('dzn_phase_2a2i_conversion_locks_held');
            if($assessment->classification==='already_converted'){ $result=$this->alreadyConverted($assessment->linkedEnrolment);$this->repository->commit();return$result; }
            if($assessment->classification!=='ready')throw new \InvalidArgumentException($assessment->classification);
            $predecessor=$assessment->predecessor;$lineage=$predecessor?'return_after_closure':null;
            $enrolmentId=$this->repository->insertCanonical(array('uid'=>Identifier::uid(),'reference_code'=>null,'student_id'=>(int)$a->student_id,'teacher_id'=>(int)$a->teacher_id,'course_id'=>(int)$a->course_id,'status'=>'canonical','record_model'=>'canonical_student_course_v1','accepted_service_arrangement_id'=>(int)$a->id,'lifecycle_state'=>'authorised','applicable_slot'=>1,'predecessor_enrolment_id'=>$predecessor?(int)$predecessor->id:null,'lineage_meaning'=>$lineage,'created_at'=>$now,'updated_at'=>$now,'created_by'=>$actor,'updated_by'=>$actor));
            $this->repository->assignReference($enrolmentId,Identifier::reference('ENR',$enrolmentId));do_action('dzn_phase_2a2i_after_enrolment_insert');
            $this->repository->insertLifecycleEvent(array('uid'=>Identifier::uid(),'enrolment_id'=>$enrolmentId,'event_sequence'=>1,'from_state'=>null,'to_state'=>'authorised','lineage_meaning'=>$lineage,'reason_code'=>'accepted_service_arrangement_conversion','evidence_channel'=>'platform_conversion_command','evidence_reference'=>hash_hmac('sha256','enrolment-conversion:'.$a->uid,wp_salt('dzn_enrolment_conversion_evidence')),'occurred_at'=>$now,'recorded_at'=>$now,'recorded_by'=>$actor,'created_at'=>$now,'created_by'=>$actor));do_action('dzn_phase_2a2i_after_lifecycle_event_insert');
            $this->repository->insertCommand(array('uid'=>Identifier::uid(),'command_key_digest'=>$key,'command_payload_digest'=>$payload,'accepted_service_arrangement_id'=>(int)$a->id,'enrolment_id'=>$enrolmentId,'created_at'=>$now,'created_by'=>$actor));do_action('dzn_phase_2a2i_after_command_insert');
            $this->repository->commit();return array('enrolment_id'=>$enrolmentId,'created'=>true,'idempotent'=>false,'already_converted'=>false);
        }catch(\Throwable$e){$this->repository->rollback();if($this->repository->isDuplicate($e)){if($winner=$this->repository->commandForDigest($key))return$this->replay($winner,$payload);if($linked=$this->repository->linkedEnrolment($acceptedServiceArrangementId))return$this->alreadyConverted($linked);$readiness=(new EnrolmentConversionReadinessService($this->repository))->assess($acceptedServiceArrangementId);if($readiness==='canonical_conflict')throw new \InvalidArgumentException('canonical_conflict');}throw$e;}
    }
    private function replay(object$command,string$payload):array{if(!hash_equals((string)$command->command_payload_digest,$payload))throw new IdempotencyConflictException('Idempotency conflict');return array('enrolment_id'=>(int)$command->enrolment_id,'created'=>false,'idempotent'=>true,'already_converted'=>false);}
    private function alreadyConverted(?object$enrolment):array{if(!$enrolment)throw new \RuntimeException('Converted Enrolment disappeared');return array('enrolment_id'=>(int)$enrolment->id,'created'=>false,'idempotent'=>false,'already_converted'=>true);}
}
