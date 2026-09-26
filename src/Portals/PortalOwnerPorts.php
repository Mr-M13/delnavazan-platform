<?php
namespace Delnavazan\Platform\Portals;

interface TeacherAssignmentPortalReadPort { public function forTeacher(int $teacherId,int $assignmentId):array; }
interface CanonicalLessonSchedulePortalReadPort { public function forLesson(int $lessonId,int $studentId,int $teacherId):array; }
interface CanonicalLessonDeliveryPortalReadPort { public function forLesson(int $lessonId,int $studentId,int $teacherId):array; }
interface CanonicalAttendancePortalReadPort { public function forLesson(int $lessonId,int $studentId,int $teacherId):array; }
interface PortalCapabilityOwnerPort { public function binding(int $lessonId,int $scheduleVersionId,string $purpose,?int $studentId):array; }

final class PortalOwnerPorts {
    private static ?PortalCapabilityOwnerPort $capability=null;
    public static function configureCapability(PortalCapabilityOwnerPort $port):void { self::$capability=$port; }
    public static function capability():PortalCapabilityOwnerPort { if(!self::$capability) throw new \InvalidArgumentException('portal_parent_not_live'); return self::$capability; }
}

final class PortalAccessPolicy {
    public static function assertObject(string $surface,string $kind,int $targetId,array $principal,object $port):array {
        try {
            if($principal['kind']==='student'&&$kind==='lesson') return $port->forLesson($targetId,(int)$principal['id'],0);
            if($principal['kind']==='teacher'&&$kind==='assignment') return $port->forTeacher((int)$principal['id'],$targetId);
            throw new \InvalidArgumentException('portal_object_not_portal_visible');
        } catch(\Throwable $e) { self::deny($surface,$kind,$targetId,$principal,(string)$e->getMessage()); throw $e; }
    }
    private static function deny(string $surface,string $kind,int $target,array $principal,string $reason):void {
        global $wpdb; $p=$wpdb->prefix.'dzn_portal_access_denials'; if(!$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$p)))return;
        $reason=in_array($reason,PortalRule::REASON_CODES,true)?$reason:'portal_upstream_aggregate_invalid';
        $wpdb->insert($p,array('uid'=>substr(hash('sha256',wp_generate_uuid4()),0,26),'surface'=>$surface,'principal_kind'=>$principal['kind']??null,'principal_id'=>$principal['id']??null,'target_kind'=>$kind,'target_id'=>$target,'reason_code'=>$reason,'request_fingerprint_digest'=>hash('sha256',wp_json_encode($_REQUEST)),'occurred_at'=>gmdate('Y-m-d H:i:s'),'created_at'=>gmdate('Y-m-d H:i:s')));
    }
}
