<?php
namespace Delnavazan\Platform\Core\Application;

use Delnavazan\Platform\Core\Infrastructure\Repository\{CanonicalAttendanceRepository,CanonicalLessonAuthorityRepository,CanonicalLessonScheduleRepository};

/**
 * Staged convergence of a Phase-P assessment into existing canonical authority.
 *
 * Phase P never writes Phase-O, lifecycle, obligation or replacement storage directly: it delegates
 * to the established application services (`CanonicalLessonDeliveryService` for canonical delivered
 * truth, `CanonicalLessonAuthorityService` for completion). Those services each own their own
 * transaction, so this class deliberately does NOT attempt a nested transaction. Instead it uses
 * durable staged convergence:
 *
 *   1. the caller has already durably recorded the settlement intent (a `settlement_requested`
 *      decision) in Phase-P storage;
 *   2. this service records canonical delivered truth through Phase O using a deterministic
 *      idempotency key derived from the case UID;
 *   3. it then completes the Lesson through Lesson authority using a second deterministic key;
 *   4. it re-reads both canonical facts and only then reports success, so a partial failure can never
 *      be acknowledged as settlement and a retry converges on the exact intended result without
 *      duplicating authority.
 */
final class CanonicalAttendanceSettlementService {
    private const REASON_AUTOMATIC='attendance_automatic_provider_settlement';
    private const REASON_ADJUDICATED='attendance_adjudicated_settlement';
    public function __construct(
        private ?CanonicalLessonAuthorityRepository $lessons=null,
        private ?CanonicalLessonScheduleRepository $schedules=null,
        private ?CanonicalAttendanceRepository $repository=null,
        private ?CanonicalLessonDeliveryService $delivery=null,
        private ?CanonicalLessonAuthorityService $authority=null
    ){
        $this->lessons??=new CanonicalLessonAuthorityRepository();
        $this->schedules??=new CanonicalLessonScheduleRepository();
        $this->repository??=new CanonicalAttendanceRepository();
        $this->delivery??=new CanonicalLessonDeliveryService();
        $this->authority??=new CanonicalLessonAuthorityService();
    }

    /**
     * Settle ordinary delivery for one occurrence: canonical `delivered` truth plus Lesson completion.
     *
     * @return array{lesson_id:int,outcome_id:int,lesson_state:string,converged:bool}
     */
    public function settleDelivered(object $case,bool $adjudicated=false):array{
        $lessonId=(int)($case->lesson_id??0);
        if($lessonId<1)throw new \InvalidArgumentException('canonical_lesson_required');
        $uid=(string)($case->uid??'');
        if($uid==='')throw new \InvalidArgumentException('canonical_attendance_case_required');
        $reason=$adjudicated?self::REASON_ADJUDICATED:self::REASON_AUTOMATIC;
        $evidenceReference='attendance-case-'.$uid;
        $guard=new CanonicalLessonDeliveryGuard();
        $lesson=$this->lessons->lesson($lessonId);
        if(!$lesson)throw new \InvalidArgumentException('canonical_lesson_required');
        if((string)$lesson->lifecycle_state==='cancelled')throw new \InvalidArgumentException('lesson_cancelled');

        // 1. Canonical delivered truth (Phase O authority).
        $effective=$guard->effective($lessonId);
        if($effective===null){
            $this->delivery->record($lessonId,(string)$lesson->lifecycle_state==='completed'?'completed':'authorised',array(
                'outcome_code'=>'delivered','reason_code'=>$reason,
                'evidence_channel'=>'authenticated_platform','evidence_reference'=>$evidenceReference,
                'evidence_at'=>gmdate('Y-m-d H:i:s'),
            ),'attendance-settlement-outcome-'.$uid);
        }elseif((string)$effective->outcome_code!=='delivered'){
            throw new \InvalidArgumentException('canonical_truth_conflict');
        }

        // 2. Lesson completion (Lesson authority).
        $lesson=$this->lessons->lesson($lessonId);
        $state=(string)($lesson->lifecycle_state??'');
        if($state!=='completed'){
            if($state!=='authorised')throw new \InvalidArgumentException('canonical_truth_conflict');
            $this->authority->complete($lessonId,'authorised',array(
                'evidence_channel'=>'authenticated_platform','evidence_reference'=>$evidenceReference,
                'evidence_at'=>gmdate('Y-m-d H:i:s'),
            ),'attendance-settlement-complete-'.$uid);
        }

        // 3. Verify canonical truth before reporting success.
        $effective=$guard->effective($lessonId);
        $lesson=$this->lessons->lesson($lessonId);
        if(!$effective||(string)$effective->outcome_code!=='delivered')throw new \RuntimeException('attendance_settlement_incomplete');
        if(!$lesson||(string)$lesson->lifecycle_state!=='completed')throw new \RuntimeException('attendance_settlement_incomplete');
        return array('lesson_id'=>$lessonId,'outcome_id'=>(int)$effective->id,'lesson_state'=>'completed','converged'=>true);
    }
}
