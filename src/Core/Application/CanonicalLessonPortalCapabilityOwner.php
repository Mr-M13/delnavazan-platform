<?php
namespace Delnavazan\Platform\Core\Application;

use Delnavazan\Platform\Portals\PortalCapabilityOwnerPort;
use Delnavazan\Platform\Core\Infrastructure\Repository\{CanonicalLessonAuthorityRepository,CanonicalLessonScheduleRepository};

/** Core-owned, read-only proof used by Portal capabilities. */
final class CanonicalLessonPortalCapabilityOwner implements PortalCapabilityOwnerPort {
    public function binding(int $lessonId,int $scheduleVersionId,string $purpose,?int $studentId):array {
        if($purpose !== \Delnavazan\Platform\Portals\PortalRule::JOIN) throw new \InvalidArgumentException('portal_parent_not_live');
        $lessons=new CanonicalLessonAuthorityRepository(); $schedules=new CanonicalLessonScheduleRepository();
        $lesson=$lessons->lesson($lessonId); $events=$lesson?$lessons->events($lessonId):array();
        $version=$schedules->version($scheduleVersionId);
        if(!$lesson||!CanonicalLessonAuthorityValidator::valid($lesson,$events,$lessons)||!CanonicalLessonScheduleValidator::validForLesson($lessonId,$schedules,$lessons)) throw new \InvalidArgumentException('portal_upstream_aggregate_invalid');
        if(!$version||(int)$version->lesson_id!==$lessonId||!$version->uid) throw new \InvalidArgumentException('portal_upstream_aggregate_invalid');
        if((int)$version->applicable_slot!==1) throw new \InvalidArgumentException('portal_capability_stale_schedule');
        if((string)$lesson->lifecycle_state!=='authorised') throw new \InvalidArgumentException('portal_object_not_portal_visible');
        if($studentId!==null && (int)$studentId!==(int)$lesson->student_id) throw new \InvalidArgumentException('portal_capability_binding_mismatch');
        return array('lesson_id'=>$lessonId,'schedule_version_id'=>$scheduleVersionId,'purpose'=>$purpose,'lesson_uid'=>(string)$lesson->uid,'schedule_version_uid'=>(string)$version->uid);
    }
}
