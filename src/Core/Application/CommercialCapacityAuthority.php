<?php
namespace Delnavazan\Platform\Core\Application;

use Delnavazan\Platform\Core\Infrastructure\Repository\CommercialCapacityRepository;

/**
 * The single seam through which Phase-R1 protected capacity participates in canonical Teacher
 * capacity arbitration, and the seam through which a materialised Phase-N schedule satisfies the
 * protected interval that authorised it.
 *
 * A protected interval is durable non-Lesson capacity. The succession rule is mandatory and
 * one-directional: Phase-Q hold → R1 protected claim → Phase-N concrete schedule occupancy. An
 * existing authority is never released until its successor is durable under the same per-Teacher
 * scheduling root, so no interval is ever unowned and no interval is ever counted twice.
 */
final class CommercialCapacityAuthority {
    /**
     * Fail closed if a protected interval owned by ANOTHER commitment already covers this exact
     * Teacher interval. `$owningClaimId` excludes only the claim whose interval is being handed off
     * or satisfied by this very command.
     */
    public static function assertNoConflictingClaim(int $teacherId,string $startsAt,string $occupiedEnd,int $owningClaimId=0,?CommercialCapacityRepository $repository=null):void{
        $repository??=new CommercialCapacityRepository();
        $intervals=$repository->overlappingIntervals($teacherId,$startsAt,$occupiedEnd,$owningClaimId);
        if(!$intervals)return;
        foreach($intervals as $interval){
            $claim=$repository->claim((int)$interval->claim_id);
            $siblings=$repository->intervals((int)$interval->claim_id);
            if(!$claim||!CommercialValidator::claimValid($claim,$siblings))throw new \InvalidArgumentException('commercial_capacity_integrity_conflict');
            if((string)$claim->state!=='active')throw new \InvalidArgumentException('commercial_capacity_integrity_conflict');
            if((string)$interval->state!=='protected')continue;
        }
        throw new \InvalidArgumentException('teacher_slot_conflict');
    }

    /** Whether any protected interval owned by another commitment blocks this interval (diagnostic). */
    public static function conflictingClaimCount(int $teacherId,string $startsAt,string $occupiedEnd,int $owningClaimId=0,?CommercialCapacityRepository $repository=null):int{
        $repository??=new CommercialCapacityRepository();
        $blocking=0;
        foreach($repository->overlappingIntervals($teacherId,$startsAt,$occupiedEnd,$owningClaimId) as $interval)if((string)$interval->state==='protected')$blocking++;
        return $blocking;
    }

    /**
     * The claim interval (if any) that authorises this exact canonical Lesson. The match is
     * structural: the claim must belong to the Lesson's Term or entitlement context and the interval
     * must expect the Lesson's own canonical session sequence.
     */
    public static function authorisingInterval(object $lesson,?CommercialCapacityRepository $repository=null):?object{
        $repository??=new CommercialCapacityRepository();
        $termId=(int)($lesson->term_id??0);
        $sequence=(int)($lesson->canonical_sequence??0);
        if($termId<1||$sequence<1)return null;
        foreach($repository->claimsForTerm($termId) as $claim){
            if((string)$claim->state!=='active')continue;
            foreach($repository->intervals((int)$claim->id) as $interval){
                if((int)$interval->expected_session===$sequence&&(string)$interval->state!=='released')return $interval;
            }
        }
        return null;
    }

    /** Refuse a schedule that would occupy an interval protected for a different commitment. */
    public static function assertScheduleAllowed(object $lesson,string $startsAt,string $occupiedEnd,?CommercialCapacityRepository $repository=null):void{
        $repository??=new CommercialCapacityRepository();
        $authorising=self::authorisingInterval($lesson,$repository);
        $owningClaimId=$authorising===null?0:(int)$authorising->claim_id;
        self::assertNoConflictingClaim((int)$lesson->teacher_id,$startsAt,$occupiedEnd,$owningClaimId,$repository);
    }

    /**
     * Mark the protected interval that authorised this Lesson as satisfied by the exact schedule
     * version that now occupies it. Runs inside the Phase-N transaction that wrote that version, so
     * protection and occupancy change together and the interval is never briefly unowned.
     */
    public static function satisfyForLesson(object $lesson,int $scheduleVersionId,int $actor,string $now,?CommercialCapacityRepository $repository=null):?int{
        $repository??=new CommercialCapacityRepository();
        $interval=self::authorisingInterval($lesson,$repository);
        if($interval===null)return null;
        if((string)$interval->state==='satisfied'){
            if((int)$interval->satisfied_lesson_id===(int)$lesson->id)return (int)$interval->id;
            throw new \InvalidArgumentException('commercial_capacity_integrity_conflict');
        }
        $repository->satisfyInterval((int)$interval->id,(int)$interval->interval_version,(int)$lesson->id,$scheduleVersionId,$now,$actor);
        return (int)$interval->id;
    }

    /**
     * Return the interval to protection when its canonical schedule is released but the commercial
     * commitment that protects it is still valid. A released or inactive claim is left released.
     */
    public static function reopenForLesson(object $lesson,int $expectedScheduleVersionId,int $actor,string $now,?CommercialCapacityRepository $repository=null):?int{
        $repository??=new CommercialCapacityRepository();
        $termId=(int)($lesson->term_id??0);
        $sequence=(int)($lesson->canonical_sequence??0);
        if($termId<1||$sequence<1)return null;
        foreach($repository->claimsForTerm($termId) as $claim){
            foreach($repository->intervals((int)$claim->id) as $interval){
                if((int)$interval->expected_session!==$sequence)continue;
                if((string)$interval->state!=='satisfied')continue;
                if((int)$interval->satisfied_lesson_id!==(int)$lesson->id)throw new \InvalidArgumentException('commercial_capacity_integrity_conflict');
                if((int)$interval->satisfied_schedule_version_id!==$expectedScheduleVersionId)throw new \InvalidArgumentException('commercial_capacity_integrity_conflict');
                if((string)$claim->state!=='active')return null;
                $repository->reopenInterval((int)$interval->id,(int)$interval->interval_version,$now,$actor);
                return (int)$interval->id;
            }
        }
        return null;
    }
}
