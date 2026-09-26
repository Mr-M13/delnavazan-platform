<?php
namespace Delnavazan\Platform\Portals;

abstract class PortalReadModel { public const VERSION='portal_lesson_v1'; protected function field(array $row,string $key){if(!array_key_exists($key,$row))throw new \InvalidArgumentException('portal_upstream_aggregate_invalid');return $row[$key];} }
final class PortalStudentLessonReadModel extends PortalReadModel {
    public function fromOwner(array $row):array { return array('version'=>self::VERSION,'lesson_uid'=>$this->field($row,'lesson_uid'),'schedule_version_uid'=>$this->field($row,'schedule_version_uid'),'join_available'=>(bool)($row['join_available']??false),'absence_available'=>(bool)($row['absence_available']??false)); }
}
final class PortalTeacherLessonReadModel extends PortalReadModel {
    public function fromOwner(array $row):array { return array('version'=>self::VERSION,'lesson_uid'=>$this->field($row,'lesson_uid'),'schedule_version_uid'=>$this->field($row,'schedule_version_uid')); }
}
final class PortalPrincipalReadModel {
    public const VERSION='portal_principal_v1';
    public function fromPrincipal(array $principal):array { if(!isset($principal['kind'],$principal['id']))throw new \InvalidArgumentException('portal_principal_unresolved');return array('version'=>self::VERSION,'principal_kind'=>$principal['kind'],'principal_id'=>(int)$principal['id']); }
}
