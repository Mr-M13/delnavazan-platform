<?php
namespace Delnavazan\Platform\Core\Application;

/**
 * Locked Phase 2A.2-Q post-intro continuation rule (owner decisions Q-D1…Q-D12).
 *
 * The rule derives the ONE expected first regular class slot from the authoritative introductory
 * occurrence's own wall-clock/timezone provenance — the intro day/time is expected to become the
 * ongoing regular day/time — and freezes the hold expiry as the earlier of that slot start and six
 * days after the introductory occurrence boundary. Nothing here creates a Lesson, schedule, Term,
 * payment or entitlement.
 */
final class CanonicalContinuationRule {
    public const RULE_VERSION='canonical_continuation_v1';
    /** Six-day maximum hold, measured from the authoritative introductory occurrence boundary. */
    public const HOLD_DAYS=6;
    public const DECISION_CONTINUE='continue_with_teacher';
    public const DECISION_DIFFERENT_TEACHER='different_teacher';
    public const DECISION_CONTACT_ME='contact_me';
    public const DECISION_NOT_CONTINUING='not_continuing';
    public const DECISION_TEACHER_UNSUITABLE='teacher_unsuitable';
    public const STUDENT_DECISIONS=array(
        self::DECISION_CONTINUE,self::DECISION_DIFFERENT_TEACHER,self::DECISION_CONTACT_ME,self::DECISION_NOT_CONTINUING,
    );
    public const ALL_DECISIONS=array(
        self::DECISION_CONTINUE,self::DECISION_DIFFERENT_TEACHER,self::DECISION_CONTACT_ME,self::DECISION_NOT_CONTINUING,self::DECISION_TEACHER_UNSUITABLE,
    );
    /** Only a continuing Student decision may hold capacity (Q-D3/Q-D4). */
    public const HOLDING_DECISIONS=array(self::DECISION_CONTINUE);
    /** Controlled, privacy-minimised optional feedback for not_continuing (Q-D10). */
    public const FEEDBACK_REASONS=array('teacher_fit','schedule','price','changed_mind','technical_experience','other','prefer_not_to_say');
    public const INTERVENTION_REASONS=array(
        'student_requested_different_teacher','student_requested_contact','teacher_match_unsuitable','integrity_conflict',
    );
    public const RESERVATION_STATES=array('active','expired','released');

    /**
     * Derive the expected first regular slot from the exact introductory occurrence.
     *
     * @param object $occurrence authoritative introductory occurrence (starts/ends UTC + local wall provenance)
     * @param object $course authoritative Course of the introductory Lesson
     * @return array{starts_at_utc:string,ends_at_utc:string,occupied_ends_at_utc:string,duration_minutes:int,buffer_minutes:int,schedule_timezone:string,local_wall_date:string,local_wall_time:string}
     */
    public static function expectedFirstRegularSlot(object $occurrence,object $course):array{
        $timezone=(string)($occurrence->schedule_timezone??'');
        $localDate=(string)($occurrence->local_wall_date??'');
        $localTime=(string)($occurrence->local_wall_time??'');
        if($timezone===''||$localDate===''||$localTime==='')throw new \InvalidArgumentException('continuation_intro_provenance_required');
        try{$zone=new \DateTimeZone($timezone);}catch(\Throwable$e){throw new \InvalidArgumentException('continuation_intro_provenance_required');}
        $local=\DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$localDate.' '.$localTime,$zone);
        if(!$local||$local->format('Y-m-d H:i:s')!==$localDate.' '.$localTime)throw new \InvalidArgumentException('continuation_intro_provenance_required');
        $duration=(int)($course->default_duration_minutes??0);
        if($duration<5||$duration>480)throw new \InvalidArgumentException('continuation_course_duration_required');
        $buffer=max(0,min(240,(int)($course->default_buffer_minutes??0)));
        $next=$local->modify('+7 days');
        if($next->format('H:i:s')!==$localTime)throw new \InvalidArgumentException('continuation_slot_not_derivable');
        $nextEnd=$next->modify('+'.$duration.' minutes');
        if($nextEnd<=$next||$nextEnd->format('H:i:s')===null)throw new \InvalidArgumentException('continuation_slot_not_derivable');
        $utc=new \DateTimeZone('UTC');
        $starts=$next->setTimezone($utc)->format('Y-m-d H:i:s');
        $ends=$nextEnd->setTimezone($utc)->format('Y-m-d H:i:s');
        $occupied=$nextEnd->modify('+'.$buffer.' minutes')->setTimezone($utc)->format('Y-m-d H:i:s');
        return array(
            'starts_at_utc'=>$starts,'ends_at_utc'=>$ends,'occupied_ends_at_utc'=>$occupied,
            'duration_minutes'=>$duration,'buffer_minutes'=>$buffer,
            'schedule_timezone'=>$timezone,'local_wall_date'=>$next->format('Y-m-d'),'local_wall_time'=>$localTime,
        );
    }

    /**
     * Frozen hold expiry: the earlier of the expected first regular slot start and six days after the
     * authoritative introductory occurrence boundary. Both bounds are exact UTC instants.
     */
    public static function expiresAt(string $introOccurrenceEndUtc,string $slotStartUtc):string{
        if(!CanonicalContinuationValidator::utc($introOccurrenceEndUtc)||!CanonicalContinuationValidator::utc($slotStartUtc))throw new \InvalidArgumentException('continuation_expiry_source_required');
        $boundary=gmdate('Y-m-d H:i:s',strtotime($introOccurrenceEndUtc.' UTC')+self::HOLD_DAYS*86400);
        return $slotStartUtc<$boundary?$slotStartUtc:$boundary;
    }

    /** A reservation is capacity-effective only while it is active AND its frozen expiry has not passed. */
    public static function capacityEffective(string $state,string $expiresAt,string $now):bool{
        return $state==='active'&&$expiresAt>$now;
    }
}
