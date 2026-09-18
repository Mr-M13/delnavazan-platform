<?php
namespace Delnavazan\Platform\Core\Application;

use Delnavazan\Platform\Core\Infrastructure\Repository\{CanonicalAcademyObligationRepository,CanonicalLessonAuthorityRepository,CanonicalLessonDeliveryRepository};

/**
 * Integrity gate for canonical academy-owed occurrences.
 *
 * An obligation is trustworthy only while it still names the exact source occurrence and the exact
 * source fact that created it: either the effective `teacher_non_delivery` outcome, or the
 * controlled advance-cancellation reason `canonical_lesson_cancelled_academy_unavailable`.
 */
final class CanonicalAcademyObligationValidator {
    private const MODEL='canonical_term_lesson_v1';
    private const KINDS=array('teacher_non_delivery','academy_cancellation');
    private const ACADEMY_CANCELLATION_REASON='canonical_lesson_cancelled_academy_unavailable';
    private const CHANNELS=array('staff_record','authenticated_platform','document_reference');
    public static function academyCancellationReason():string{return self::ACADEMY_CANCELLATION_REASON;}
    public static function kinds():array{return self::KINDS;}

    /** Hydrate and validate the obligations of one canonical Lesson against its source facts. */
    public static function validForLesson(int $lessonId,?CanonicalAcademyObligationRepository $repository=null,?CanonicalLessonAuthorityRepository $lessons=null,?CanonicalLessonDeliveryRepository $delivery=null,bool $lock=false):bool{
        $repository??=new CanonicalAcademyObligationRepository();
        $lessons??=new CanonicalLessonAuthorityRepository();
        $delivery??=new CanonicalLessonDeliveryRepository();
        $lesson=$lessons->lesson($lessonId,$lock);
        if(!$lesson)return false;
        $outcomes=$delivery->outcomesForLesson($lessonId,$lock);
        $lifecycle=$lessons->events($lessonId,$lock);
        return self::valid($lesson,$repository->forLesson($lessonId,$lock),$outcomes,$lifecycle);
    }

    /** Pure validation over hydrated rows. */
    public static function valid(object $lesson,array $obligations,array $outcomes,array $lifecycle):bool{
        $lessonId=(int)($lesson->id??0);
        if($lessonId<1||(string)($lesson->record_model??'')!==self::MODEL)return false;
        $effective=CanonicalLessonDeliveryValidator::effective($outcomes);
        $academyCancellation=false;
        foreach($lifecycle as$event)if((string)($event->to_state??'')==='cancelled'&&(string)($event->reason_code??'')===self::ACADEMY_CANCELLATION_REASON)$academyCancellation=true;
        $expected=array();
        if($effective&&(string)$effective->outcome_code==='teacher_non_delivery')$expected['teacher_non_delivery']=(int)$effective->id;
        if($academyCancellation)$expected['academy_cancellation']=null;
        if(!$expected)return $obligations===array();
        if(count($obligations)!==count($expected))return false;
        foreach($obligations as$obligation){
            $kind=(string)($obligation->source_kind??'');
            if(!in_array($kind,self::KINDS,true)||!array_key_exists($kind,$expected))return false;
            if((int)($obligation->source_lesson_id??0)!==$lessonId)return false;
            if((int)($obligation->enrolment_id??0)!==(int)($lesson->enrolment_id??0))return false;
            if((int)($obligation->term_id??0)!==(int)($lesson->term_id??0))return false;
            if((int)($obligation->student_id??0)<1||(int)($obligation->course_id??0)<1||(int)($obligation->teacher_id??0)<1)return false;
            if((string)($obligation->state??'')!=='owed')return false;
            if(!self::evidenceShape($obligation->reason_code??null,$obligation->evidence_channel??null,$obligation->evidence_reference_digest??null,$obligation->evidence_at??null))return false;
            if(!CanonicalLessonDeliveryValidator::utc($obligation->recorded_at??null)||(int)($obligation->recorded_by??0)<1||(int)($obligation->created_by??0)<1)return false;
            $sourceOutcome=$obligation->source_outcome_id===null?null:(int)$obligation->source_outcome_id;
            if($expected[$kind]!==null&&$sourceOutcome!==$expected[$kind])return false;
            if($kind==='teacher_non_delivery'){
                if($sourceOutcome===null)return false;
                if((int)($obligation->schedule_version_id??0)!==(int)($effective->schedule_version_id??0))return false;
            }elseif((string)($obligation->reason_code??'')!==self::ACADEMY_CANCELLATION_REASON)return false;
        }
        return true;
    }

    private static function evidenceShape(mixed $reason,mixed $channel,mixed $digest,mixed $at):bool{
        $reason=(string)($reason??'');
        if($reason===''||strlen($reason)>64||!preg_match('/^[a-z0-9_]+$/D',$reason))return false;
        if(!in_array((string)($channel??''),self::CHANNELS,true))return false;
        if(!preg_match('/^[a-f0-9]{64}$/D',(string)($digest??'')))return false;
        return CanonicalLessonDeliveryValidator::utc($at);
    }
}
