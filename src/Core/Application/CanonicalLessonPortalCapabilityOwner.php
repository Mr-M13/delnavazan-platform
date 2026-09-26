<?php
namespace Delnavazan\Platform\Core\Application;

use Delnavazan\Platform\Portals\PortalCapabilityOwnerPort;

/** Core-owned, read-only proof used by Portal capabilities. */
final class CanonicalLessonPortalCapabilityOwner implements PortalCapabilityOwnerPort {
    public function binding(int $lessonId,int $scheduleVersionId,string $purpose,?int $studentId):array {
        if($purpose !== \Delnavazan\Platform\Portals\PortalRule::JOIN) throw new \InvalidArgumentException('portal_parent_not_live');
        global $wpdb; $p=$wpdb->prefix.'dzn_';
        $lesson=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$p}lessons WHERE id=%d LIMIT 1",$lessonId));
        $version=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$p}canonical_lesson_schedule_versions WHERE id=%d AND lesson_id=%d AND applicable_slot=1 LIMIT 1",$scheduleVersionId,$lessonId));
        if(!$lesson||$lesson->record_model!=='canonical_term_lesson_v1'||!in_array((string)$lesson->lifecycle_state,array('authorised','scheduled'),true)||$lesson->archived_at!==null||!$version||!$version->uid) throw new \InvalidArgumentException('portal_object_not_portal_visible');
        if($studentId!==null && (int)$studentId!==(int)$lesson->student_id) throw new \InvalidArgumentException('portal_capability_binding_mismatch');
        return array('lesson_id'=>$lessonId,'schedule_version_id'=>$scheduleVersionId,'purpose'=>$purpose,'lesson_uid'=>(string)$lesson->uid,'schedule_version_uid'=>(string)$version->uid);
    }
}
