<?php
/** Focused owner-binding regression coverage for consumed confirmation replay. */
$root=dirname(__DIR__);
require_once $root.'/src/Portals/PortalOwnerPorts.php';
require_once $root.'/src/Portals/PortalCapabilityService.php';

final class PhaseWRevokedPrincipalOwner implements \Delnavazan\Platform\Portals\PortalCapabilityOwnerPort {
    public ?bool $receivedRequirePrincipal=null;

    public function binding(int $lessonId,int $scheduleVersionId,string $purpose,?int $studentId,bool $requirePrincipal=true):array {
        $this->receivedRequirePrincipal=$requirePrincipal;
        if($requirePrincipal)throw new \InvalidArgumentException('portal_principal_required');
        // This stub models the immutable lesson/schedule/student proof remaining
        // available after the student's principal link was revoked_at.
        return array('lesson_uid'=>'lesson-replay','schedule_version_uid'=>'schedule-replay');
    }
}

$owner=new PhaseWRevokedPrincipalOwner();
\Delnavazan\Platform\Portals\PortalOwnerPorts::configureCapability($owner);
$service=new \Delnavazan\Platform\Portals\PortalCapabilityService();
$method=(new \ReflectionClass($service))->getMethod('owner');
$method->setAccessible(true);
$binding=$method->invoke($service,1,2,'lesson_absence',3,false);
if($owner->receivedRequirePrincipal!==false||$binding['lesson_uid']!=='lesson-replay')throw new \RuntimeException('same consumed confirmation did not replay after revoked_at principal link');
echo "Phase-W revoked-principal replay coverage passed\n";
