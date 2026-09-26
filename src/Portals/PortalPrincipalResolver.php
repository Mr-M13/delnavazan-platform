<?php
namespace Delnavazan\Platform\Portals;

final class PortalPrincipalResolver {
    public function resolve(string $requiredKind):array {
        $userId=(int)get_current_user_id();
        if($userId<1) throw new \InvalidArgumentException('portal_principal_required');
        global $wpdb; $p=$wpdb->prefix.'dzn_'; $now=current_time('mysql',true); $candidates=array();
        if($requiredKind==='teacher'||$requiredKind==='any'){$r=$wpdb->get_results($wpdb->prepare("SELECT teacher_id id FROM {$p}teacher_principal_links WHERE wordpress_user_id=%d AND status='active' AND revoked_at IS NULL",$userId));foreach($r as$row)$candidates[]=array('kind'=>'teacher','id'=>(int)$row->id);}
        if($requiredKind==='student'||$requiredKind==='any'){$r=$wpdb->get_results($wpdb->prepare("SELECT student_id id FROM {$p}student_principal_links WHERE wordpress_user_id=%d AND status='active' AND revoked_at IS NULL",$userId));foreach($r as$row)$candidates[]=array('kind'=>'student','id'=>(int)$row->id);}
        if($requiredKind==='guardian'){$r=$wpdb->get_results($wpdb->prepare("SELECT student_id id FROM {$p}student_acceptance_authority_grants WHERE acting_wordpress_user_id=%d AND authority_type='guardian_representative' AND state='active' AND effective_from<=%s AND (effective_until IS NULL OR effective_until>%s)",$userId,$now,$now));foreach($r as$row)$candidates[]=array('kind'=>'guardian','id'=>(int)$row->id);}
        if(count($candidates)!==1) throw new \InvalidArgumentException(count($candidates)?'portal_principal_ambiguous':'portal_principal_unresolved');
        if($requiredKind!=='any'&&$candidates[0]['kind']!==$requiredKind) throw new \InvalidArgumentException('portal_principal_kind_not_permitted');
        return $candidates[0];
    }
}
