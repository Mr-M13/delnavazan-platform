<?php
namespace Delnavazan\Platform\Core\Application;

/** Validates the small immutable canonical Lesson aggregate before authority decisions. */
final class CanonicalLessonAuthorityValidator {
    public static function valid(object $lesson,array $events):bool{
        if(($lesson->record_model??null)!=='canonical_term_lesson_v1'||!in_array((string)($lesson->lifecycle_state??''),array('authorised','completed','cancelled'),true)||(int)($lesson->enrolment_id??0)<1||(int)($lesson->term_id??0)<1||(int)($lesson->teacher_assignment_id??0)<1||(int)($lesson->teacher_id??0)<1||(int)($lesson->canonical_sequence??0)<1||!in_array((string)($lesson->lesson_type??''),array('standard','replacement'),true))return false;
        if(($lesson->lesson_type??null)==='standard'&&$lesson->canonical_replacement_origin_lesson_id!==null)return false;
        if(($lesson->lesson_type??null)==='replacement'&&(int)($lesson->canonical_replacement_origin_lesson_id??0)<1)return false;
        if(!$events)return false;$state=null;$sequence=1;
        foreach($events as$event){if((int)($event->event_sequence??0)!==$sequence++||(string)($event->to_state??'')===''||!in_array((string)$event->to_state,array('authorised','completed','cancelled'),true)||$event->from_state!==$state)return false;if($state===null&&$event->to_state!=='authorised')return false;if($state!==null&&$state!=='authorised')return false;$state=$event->to_state;}
        return $state===$lesson->lifecycle_state;
    }
}
