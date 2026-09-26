<?php
namespace Delnavazan\Platform\Portals;

final class PortalPublicActionService {
    public function renderAbsenceConfirmation(string $handle,string $token):array { $row=(new PortalCapabilityService())->verify($handle,$token,PortalRule::ABSENCE);$confirmation=bin2hex(random_bytes(32));return array('capability_id'=>(int)$row->id,'lesson_id'=>(int)$row->lesson_id,'confirmation_token'=>$confirmation,'purpose'=>PortalRule::ABSENCE); }
    public function confirmAbsence(string $handle,string $token,string $confirmation):array { if(strlen($confirmation)!==64)throw new \InvalidArgumentException('portal_confirmation_invalid');$row=(new PortalCapabilityService())->verify($handle,$token,PortalRule::ABSENCE);return array('capability_id'=>(int)$row->id,'lesson_id'=>(int)$row->lesson_id,'attribution'=>'public_capability_on_behalf','state'=>'confirmed_submitting'); }
}
