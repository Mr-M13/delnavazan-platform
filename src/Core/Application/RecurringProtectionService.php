<?php
namespace Delnavazan\Platform\Core\Application;

use Delnavazan\Platform\Core\Infrastructure\Repository\RecurringProtectionRepository;
use Delnavazan\Platform\Core\Support\Identifier;

/** Phase 2A.2-R2 continuous cross-Term protection: links a renewal cycle to an R1 protected claim. */
final class RecurringProtectionService {
    private const CAPABILITY='dzn_manage_recurring_protection';
    public function __construct(private ?RecurringProtectionRepository $repository=null){$this->repository??=new RecurringProtectionRepository();}

    public function establishProtection(int $cycleId,int $claimId,array $input,string $key):array{
        RecurringSupport::requireCapability(self::CAPABILITY);
        $actor=RecurringSupport::actor();
        $evidence=RecurringSupport::evidence($input);
        // Provisional guard only: the authoritative claim check runs again inside the serialised
        // transaction below, so a claim released between these two points can never be adopted.
        if(!$this->claimActive($claimId))throw new \InvalidArgumentException('commercial_capacity_claim_not_active');
        if(!$this->cycleLive($cycleId))throw new \InvalidArgumentException('invalid_renewal_cycle_state');
        $digest=RecurringSupport::keyString($key);
        $payload=RecurringIdempotency::payload(array('domain'=>RecurringRule::DOMAIN,'operation'=>'establish_protection','renewal_cycle_id'=>$cycleId,'claim_id'=>$claimId,'evidence_reference_digest'=>$evidence['digest']));
        $this->repository->begin();
        try{
            RecurringSupport::guardAggregate('renewal_cycle',$cycleId,$actor);
            if($winner=$this->repository->command($digest)){$result=$this->replay($winner,$payload);$this->repository->commit();return $result;}
            // §5.6: a protection extends the cycle's own current-Term protected capacity across the
            // next-Term boundary, so the claim it adopts must still be an active claim of the same
            // beneficiary Student and Course and must not already be protection-linked elsewhere.
            $this->assertClaimOwnership($cycleId,$claimId);
            if($this->repository->forCycle($cycleId))throw new \InvalidArgumentException('recurring_protection_already_exists');
            if($this->repository->forClaim($claimId))throw new \InvalidArgumentException('recurring_protection_already_exists');
            $now=RecurringSupport::now();
            $id=$this->repository->insertProtection(array(
                'uid'=>Identifier::uid(),'renewal_cycle_id'=>$cycleId,'claim_id'=>$claimId,'state'=>'active',
                'recurring_protection_version'=>1,'created_at'=>$now,'updated_at'=>$now,'created_by'=>$actor,'updated_by'=>$actor,
            ));
            $this->repository->insertEvent(array(
                'uid'=>Identifier::uid(),'recurring_protection_id'=>$id,'event_sequence'=>1,'event_type'=>'established',
                'from_state'=>null,'to_state'=>'active','reason_code'=>'established','evidence_channel'=>$evidence['channel'],
                'evidence_reference_digest'=>$evidence['digest'],'evidence_at'=>$evidence['at'],'occurred_at'=>$now,'recorded_at'=>$now,'recorded_by'=>$actor,'created_at'=>$now,'created_by'=>$actor,
            ));
            $this->repository->insertCommand(array(
                'uid'=>Identifier::uid(),'command_domain'=>RecurringRule::DOMAIN,'operation'=>'establish_protection',
                'command_key_digest'=>$digest,'command_payload_digest'=>$payload,'recurring_protection_id'=>$id,
                'result_state'=>'active','result_id'=>$id,'created_at'=>$now,'created_by'=>$actor,
            ));
            RecurringSupport::hook('dzn_phase_2a2r2_after_protection_event_insert','establish_protection',$id);
            $this->repository->commit();
            return array('recurring_protection_id'=>$id,'state'=>'active','created'=>true);
        }catch(\Throwable $e){
            $this->repository->rollback();
            if($this->repository->duplicate($e)==='command_key_digest'&&($winner=$this->repository->command($digest)))return $this->replay($winner,$payload);
            throw $e;
        }
    }
    public function extendProtection(int $protectionId,array $input,string $key):array{
        RecurringSupport::requireCapability(self::CAPABILITY);
        $actor=RecurringSupport::actor();
        $evidence=RecurringSupport::evidence($input);
        $digest=RecurringSupport::keyString($key);
        $payload=RecurringIdempotency::payload(array('domain'=>RecurringRule::DOMAIN,'operation'=>'extend_protection','recurring_protection_id'=>$protectionId,'evidence_reference_digest'=>$evidence['digest']));
        $this->repository->begin();
        try{
            RecurringSupport::guardAggregate('recurring_protection',$protectionId,$actor);
            if($winner=$this->repository->command($digest)){$result=$this->replay($winner,$payload);$this->repository->commit();return $result;}
            $protection=$this->repository->find($protectionId,true);
            if(!$protection||(string)$protection->state!=='active')throw new \InvalidArgumentException('invalid_recurring_protection_state');
            $now=RecurringSupport::now();
            $this->repository->insertEvent(array(
                'uid'=>Identifier::uid(),'recurring_protection_id'=>$protectionId,'event_sequence'=>$this->repository->nextSequence($protectionId),'event_type'=>'extended',
                'from_state'=>'active','to_state'=>'active','reason_code'=>'extended','evidence_channel'=>$evidence['channel'],
                'evidence_reference_digest'=>$evidence['digest'],'evidence_at'=>$evidence['at'],'occurred_at'=>$now,'recorded_at'=>$now,'recorded_by'=>$actor,'created_at'=>$now,'created_by'=>$actor,
            ));
            $this->repository->insertCommand(array(
                'uid'=>Identifier::uid(),'command_domain'=>RecurringRule::DOMAIN,'operation'=>'extend_protection',
                'command_key_digest'=>$digest,'command_payload_digest'=>$payload,'recurring_protection_id'=>$protectionId,
                'result_state'=>'active','result_id'=>$protectionId,'created_at'=>$now,'created_by'=>$actor,
            ));
            RecurringSupport::hook('dzn_phase_2a2r2_after_protection_event_insert','extend_protection',$protectionId);
            $this->repository->commit();
            return array('recurring_protection_id'=>$protectionId,'state'=>'active','created'=>true);
        }catch(\Throwable $e){
            $this->repository->rollback();
            if($this->repository->duplicate($e)==='command_key_digest'&&($winner=$this->repository->command($digest)))return $this->replay($winner,$payload);
            throw $e;
        }
    }
    public function releaseProtection(int $protectionId,array $input,string $key):array{
        RecurringSupport::requireCapability(self::CAPABILITY);
        $actor=RecurringSupport::actor();
        $evidence=RecurringSupport::evidence($input);
        $digest=RecurringSupport::keyString($key);
        $payload=RecurringIdempotency::payload(array('domain'=>RecurringRule::DOMAIN,'operation'=>'release_protection','recurring_protection_id'=>$protectionId,'evidence_reference_digest'=>$evidence['digest']));
        // 1. Authorise the recorded aggregate position before any delegation.
        $hint=$this->repository->find($protectionId);
        if(!$hint||(string)$hint->state!=='active')throw new \InvalidArgumentException('invalid_recurring_protection_state');
        $claimId=(int)$hint->claim_id;
        // 2. The underlying protected-capacity release is delegated to R1, which owns the transaction
        //    and releases the intervals under the same per-Teacher scheduling root as R1-D10. A
        //    predecessor claim or hold is released there only after its successor is durable.
        (new CommercialCapacityService())->releaseClaim($claimId,array('evidence_channel'=>$evidence['channel'],'evidence_reference'=>(string)($input['evidence_reference']??''),'evidence_at'=>$evidence['at'],'release_reason_code'=>'commercial_resolution'),$key);
        // 3. Record the durable R1 outcome in R2's own serialised transaction, after verifying it.
        $this->repository->begin();
        try{
            RecurringSupport::guardAggregate('recurring_protection',$protectionId,$actor);
            if($winner=$this->repository->command($digest)){$result=$this->replay($winner,$payload);$this->repository->commit();return $result;}
            $protection=$this->repository->find($protectionId,true);
            if(!$protection||(string)$protection->state!=='active')throw new \InvalidArgumentException('invalid_recurring_protection_state');
            if(!$this->claimReleased((int)$protection->claim_id))throw new \RuntimeException('recurring_protection_release_conflict');
            $now=RecurringSupport::now();
            $this->repository->updateProtection($protectionId,(int)$protection->recurring_protection_version,array('state'=>'released'),$now,$actor);
            $this->repository->insertEvent(array(
                'uid'=>Identifier::uid(),'recurring_protection_id'=>$protectionId,'event_sequence'=>$this->repository->nextSequence($protectionId),'event_type'=>'released',
                'from_state'=>'active','to_state'=>'released','reason_code'=>'released','evidence_channel'=>$evidence['channel'],
                'evidence_reference_digest'=>$evidence['digest'],'evidence_at'=>$evidence['at'],'occurred_at'=>$now,'recorded_at'=>$now,'recorded_by'=>$actor,'created_at'=>$now,'created_by'=>$actor,
            ));
            $this->repository->insertCommand(array(
                'uid'=>Identifier::uid(),'command_domain'=>RecurringRule::DOMAIN,'operation'=>'release_protection',
                'command_key_digest'=>$digest,'command_payload_digest'=>$payload,'recurring_protection_id'=>$protectionId,
                'result_state'=>'released','result_id'=>$protectionId,'created_at'=>$now,'created_by'=>$actor,
            ));
            RecurringSupport::hook('dzn_phase_2a2r2_after_protection_event_insert','release_protection',$protectionId);
            $this->repository->commit();
            return array('recurring_protection_id'=>$protectionId,'state'=>'released','created'=>true);
        }catch(\Throwable $e){
            $this->repository->rollback();
            if($this->repository->duplicate($e)==='command_key_digest'&&($winner=$this->repository->command($digest)))return $this->replay($winner,$payload);
            throw $e;
        }
    }

    private function replay(object $command,string $payload):array{
        if(!hash_equals((string)$command->command_payload_digest,$payload))throw new \RuntimeException('Idempotency conflict');
        if((string)$command->command_domain!==RecurringRule::DOMAIN)throw new \RuntimeException('Contaminated recurring protection command');
        $protection=$this->repository->find((int)$command->result_id);
        if(!$protection||(string)$protection->state!==(string)$command->result_state)throw new \RuntimeException('Contaminated recurring protection result');
        return array('recurring_protection_id'=>(int)$protection->id,'state'=>(string)$protection->state,'created'=>false,'idempotent'=>true);
    }
    private function claimActive(int $claimId):bool{global $wpdb;$p=$wpdb->prefix.'dzn_';$row=$wpdb->get_row($wpdb->prepare("SELECT state FROM {$p}commercial_capacity_claims WHERE id=%d",$claimId));return $row&&(string)$row->state==='active';}
    private function claimReleased(int $claimId):bool{global $wpdb;$p=$wpdb->prefix.'dzn_';$row=$wpdb->get_row($wpdb->prepare("SELECT state FROM {$p}commercial_capacity_claims WHERE id=%d",$claimId));return $row&&(string)$row->state==='released';}
    /**
     * The adopted claim must be active and must belong to the cycle's own beneficiary Student and
     * Course. Ownership is re-proven here (after the cycle is locked) rather than trusted from the
     * provisional read, so a claim released or replaced in the interim fails this command closed.
     */
    private function assertClaimOwnership(int $cycleId,int $claimId):void{
        global $wpdb;$p=$wpdb->prefix.'dzn_';
        $claim=$wpdb->get_row($wpdb->prepare("SELECT claim.state AS state,claim.student_id AS student_id,claim.course_id AS course_id,recurring.student_id AS cycle_student_id,recurring.course_id AS cycle_course_id FROM {$p}commercial_capacity_claims claim JOIN {$p}renewal_cycles cycle ON cycle.id=%d JOIN {$p}recurring_enrolments recurring ON recurring.id=cycle.recurring_enrolment_id WHERE claim.id=%d",$cycleId,$claimId));
        if(!$claim||(string)$claim->state!=='active')throw new \InvalidArgumentException('commercial_capacity_claim_not_active');
        if((int)$claim->student_id!==(int)$claim->cycle_student_id||(int)$claim->course_id!==(int)$claim->cycle_course_id)throw new \InvalidArgumentException('recurring_protection_claim_conflict');
    }
    /** Protection extends a live cycle's claim across the next-Term boundary; a terminal cycle has none. */
    private function cycleLive(int $cycleId):bool{
        global $wpdb;$p=$wpdb->prefix.'dzn_';
        $row=$wpdb->get_row($wpdb->prepare("SELECT state FROM {$p}renewal_cycles WHERE id=%d",$cycleId));
        return $row&&in_array((string)$row->state,RecurringRule::CYCLE_LIVE_STATES,true);
    }
}
