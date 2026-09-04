<?php
namespace Delnavazan\Platform\Core\Infrastructure\Repository;

/** Persistence boundary for the 2A.0 identity and invitation aggregate. */
final class PrincipalInvitationRepository {
    private string $prefix;
    public function __construct(){ global $wpdb; $this->prefix=$wpdb->prefix.'dzn_'; }
    public function begin():void{global $wpdb;if($wpdb->query('START TRANSACTION')===false)throw new \RuntimeException('Transaction start failed');}
    public function commit():void{global $wpdb;if($wpdb->query('COMMIT')===false)throw new \RuntimeException('Transaction commit failed');}
    public function rollback():void{global $wpdb;$wpdb->query('ROLLBACK');}
    public function teacherForUpdate(int $id):?object{global $wpdb;return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->prefix}teachers WHERE id=%d FOR UPDATE",$id));}
    public function invitationForTeacher(int $teacherId):?object{global $wpdb;return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->prefix}teacher_invitations WHERE teacher_id=%d FOR UPDATE",$teacherId));}
    public function generationForUpdate(int $id):?object{global $wpdb;return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->prefix}teacher_invitation_generations WHERE id=%d FOR UPDATE",$id));}
    public function attemptForCommand(string $key):?object{global $wpdb;return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->prefix}account_claim_attempts WHERE command_key=%s FOR UPDATE",$key));}
    public function eventExists(string $key):bool{global $wpdb;return(bool)$wpdb->get_var($wpdb->prepare("SELECT id FROM {$this->prefix}platform_audit_events WHERE idempotency_key=%s",$key));}
    public function insert(string $table,array $data):int{global $wpdb;if($wpdb->insert($this->prefix.$table,$data)===false)throw new \RuntimeException('Persistence failed: '.$wpdb->last_error);return(int)$wpdb->insert_id;}
    public function update(string $table,array $data,array $where):int{global $wpdb;$n=$wpdb->update($this->prefix.$table,$data,$where);if($n===false)throw new \RuntimeException('Persistence failed: '.$wpdb->last_error);return$n;}
    public function onboardingForTeacher(int $teacherId):?object{global $wpdb;return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->prefix}teacher_onboarding_states WHERE teacher_id=%d FOR UPDATE",$teacherId));}
    public function activeTeacherLinkForUser(int $userId):?object{global $wpdb;return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->prefix}teacher_principal_links WHERE wordpress_user_id=%d AND status='active' FOR UPDATE",$userId));}
    public function activeTeacherLinkForTeacher(int $teacherId):?object{global $wpdb;return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->prefix}teacher_principal_links WHERE teacher_id=%d AND status='active' FOR UPDATE",$teacherId));}
    public function activeStudentLinkForUser(int $userId):?object{global $wpdb;return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->prefix}student_principal_links WHERE wordpress_user_id=%d AND status='active'",$userId));}
    public function audit(string $aggregate,int $id,string $event,?int $actor,string $key,?string $detail=null):void{$this->insert('platform_audit_events',['aggregate_type'=>$aggregate,'aggregate_id'=>$id,'event_type'=>$event,'actor_type'=>$actor?'user':'system','actor_id'=>$actor,'safe_detail'=>$detail,'idempotency_key'=>$key,'occurred_at'=>current_time('mysql',true)]);}
    public function outbox(int $invitationId,int $generationId,string $event,string $key):void{$this->insert('platform_outbox',['aggregate_type'=>'teacher_invitation','aggregate_id'=>$invitationId,'event_type'=>$event,'invitation_id'=>$invitationId,'generation_id'=>$generationId,'idempotency_key'=>$key,'status'=>'pending','available_at'=>current_time('mysql',true),'created_at'=>current_time('mysql',true)]);}
    public function hasActiveTeacherAuthority(int $userId,int $teacherId):bool{global $wpdb;return(bool)$wpdb->get_var($wpdb->prepare("SELECT l.id FROM {$this->prefix}teacher_principal_links l INNER JOIN {$this->prefix}teacher_onboarding_states o ON o.teacher_id=l.teacher_id WHERE l.wordpress_user_id=%d AND l.teacher_id=%d AND l.status='active' AND o.state='active' LIMIT 1",$userId,$teacherId));}
    public function adminRows():array{global $wpdb;return $wpdb->get_results("SELECT t.id teacher_id,t.display_name,t.status teacher_status,o.state onboarding_state,l.wordpress_user_id,l.status principal_status,i.current_generation_number,i.status invitation_status FROM {$this->prefix}teachers t LEFT JOIN {$this->prefix}teacher_onboarding_states o ON o.teacher_id=t.id LEFT JOIN {$this->prefix}teacher_principal_links l ON l.teacher_id=t.id AND l.status='active' LEFT JOIN {$this->prefix}teacher_invitations i ON i.teacher_id=t.id ORDER BY t.id DESC LIMIT 100");}
}
