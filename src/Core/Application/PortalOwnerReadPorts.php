<?php
namespace Delnavazan\Platform\Core\Application;

use Delnavazan\Platform\Portals\{AuthenticatedPortalReadSubject,PublicCapabilityReadSubject,TeacherAssignmentPortalReadPort,CanonicalLessonSchedulePortalReadPort,CanonicalLessonDeliveryPortalReadPort,CanonicalAttendancePortalReadPort};
use Delnavazan\Platform\Core\Infrastructure\Repository\{CanonicalLessonAuthorityRepository,CanonicalLessonScheduleRepository,CanonicalLessonDeliveryRepository,CanonicalAttendanceRepository};

/** Core-owned, read-only portal seams. They deliberately return projections, never repository rows. */
final class CanonicalTeacherAssignmentPortalReadPort implements TeacherAssignmentPortalReadPort {
    public function forSubject(AuthenticatedPortalReadSubject|PublicCapabilityReadSubject $subject,int $enrolmentId):array { return $this->one($subject,$enrolmentId); }
    public function pageForSubject(AuthenticatedPortalReadSubject|PublicCapabilityReadSubject $subject,?string $cursor,int $limit):array { $limit=max(1,min(50,$limit)); $start=max(0,(int)$cursor); global $wpdb; $rows=$wpdb->get_results($wpdb->prepare("SELECT id,enrolment_id,teacher_id,uid,status FROM {$wpdb->prefix}dzn_teacher_assignments WHERE teacher_id=%d AND applicable_slot=1 AND id>%d ORDER BY id LIMIT %d",$this->teacher($subject),$start,$limit)); return array_map(fn($r)=>array('assignment_uid'=>(string)$r->uid,'enrolment_id'=>(int)$r->enrolment_id,'status'=>(string)$r->status),$rows?:array()); }
    private function one($subject,int $enrolmentId):array { global $wpdb; $teacher=$this->teacher($subject); $r=$wpdb->get_row($wpdb->prepare("SELECT uid,enrolment_id,status FROM {$wpdb->prefix}dzn_teacher_assignments WHERE teacher_id=%d AND enrolment_id=%d AND applicable_slot=1",$teacher,$enrolmentId)); if(!$r)throw new \InvalidArgumentException('portal_object_not_owned'); return array('assignment_uid'=>(string)$r->uid,'enrolment_id'=>(int)$r->enrolment_id,'status'=>(string)$r->status); }
    private function teacher($s):int { if($s instanceof PublicCapabilityReadSubject)throw new \InvalidArgumentException('portal_object_not_owned'); if($s->kind!=='teacher')throw new \InvalidArgumentException('portal_principal_kind_not_permitted'); return $s->principalId; }
}

final class CanonicalLessonSchedulePortalReadPortImpl implements CanonicalLessonSchedulePortalReadPort {
    public function forSubject(AuthenticatedPortalReadSubject|PublicCapabilityReadSubject $subject,int $lessonId):array { $lesson=$this->lesson($subject,$lessonId); $v=(new CanonicalLessonScheduleRepository())->applicableVersion($lessonId); if(!$v)throw new \InvalidArgumentException('portal_upstream_aggregate_invalid'); return $this->projection($lesson,$v); }
    public function pageForSubject(AuthenticatedPortalReadSubject|PublicCapabilityReadSubject $subject,?string $cursor,int $limit):array { $limit=max(1,min(50,$limit)); return array_slice(array($this->forSubject($subject,(int)$cursor),),0,$limit); }
    private function lesson($s,int $id):object { $r=(new CanonicalLessonAuthorityRepository())->lesson($id); if(!$r||(string)$r->lifecycle_state!=='authorised')throw new \InvalidArgumentException('portal_object_not_portal_visible'); if($s instanceof PublicCapabilityReadSubject){if($s->lessonId!==$id||($s->studentId!==null&&(int)$r->student_id!==$s->studentId))throw new \InvalidArgumentException('portal_capability_binding_mismatch');} elseif($s->kind==='student'){if((int)$r->student_id!==$s->principalId)throw new \InvalidArgumentException('portal_object_not_owned');} elseif($s->kind!=='teacher'||(int)$r->teacher_id!==$s->principalId)throw new \InvalidArgumentException('portal_object_not_owned'); return $r; }
    private function projection($l,$v):array { return array('lesson_uid'=>(string)$l->uid,'schedule_version_uid'=>(string)$v->uid,'lesson_id'=>(int)$l->id,'schedule_version_id'=>(int)$v->id,'enrolment_id'=>(int)$l->enrolment_id,'student_id'=>(int)$l->student_id,'teacher_id'=>(int)$l->teacher_id); }
}

final class CanonicalLessonDeliveryPortalReadPortImpl implements CanonicalLessonDeliveryPortalReadPort {
    public function summaryForSubject(AuthenticatedPortalReadSubject|PublicCapabilityReadSubject $subject,int $lessonId):array { $schedule=new CanonicalLessonSchedulePortalReadPortImpl(); $schedule->forSubject($subject,$lessonId); $o=(new CanonicalLessonDeliveryRepository())->applicableOutcome($lessonId); return array('lesson_id'=>$lessonId,'delivery_state_summary'=>$o?(string)($o->outcome_code??$o->state??'recorded'):'not_recorded'); }
}

final class CanonicalAttendancePortalReadPortImpl implements CanonicalAttendancePortalReadPort {
    public function summaryForSubject(AuthenticatedPortalReadSubject|PublicCapabilityReadSubject $subject,int $lessonId,int $scheduleVersionId):array { (new CanonicalLessonSchedulePortalReadPortImpl())->forSubject($subject,$lessonId); $case=(new CanonicalAttendanceRepository())->caseFor($lessonId,$scheduleVersionId); return array('lesson_id'=>$lessonId,'schedule_version_id'=>$scheduleVersionId,'attendance_state_summary'=>$case?(string)$case->state:'not_recorded','absence_available'=>$case?(string)$case->state==='open':false,'absence_final'=>$case?(string)$case->state==='settled':false); }
    public function submitCapabilityClaim(PublicCapabilityReadSubject $subject,string $redemptionReference):array { if($subject->purpose!=='lesson_absence')throw new \InvalidArgumentException('portal_capability_purpose_mismatch'); if(trim($redemptionReference)==='')throw new \InvalidArgumentException('portal_confirmation_invalid'); return (new CanonicalAttendanceIntakeService())->submitCapabilityClaim($subject,$redemptionReference); }
}
