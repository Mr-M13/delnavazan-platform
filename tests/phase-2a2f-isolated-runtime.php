<?php
/** Disposable local-only behavioral checks; no production execution is permitted. */
if(getenv('DZN_PHASE_2A2F_RUNTIME_TEST')!=='isolated'||!defined('WP_CLI')||!WP_CLI||!in_array(wp_get_environment_type(),array('local','development'),true)){fwrite(STDERR,"Phase 2A.2-F runtime refused.\n");exit(1);}
use Delnavazan\Platform\Core\Application\{StudentIdentityResolutionService,StudentAcceptanceAuthorityService,StudentAcceptanceAuthorityReadService,BookingRequestPrivacyService};
global $wpdb;$p=$wpdb->prefix.'dzn_';
function dzn_2a2f_assert(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);}
$request=$wpdb->get_row("SELECT * FROM {$p}booking_requests WHERE lifecycle_status='submitted' AND resolution_state='unresolved' AND privacy_erased_at IS NULL AND student_id IS NULL ORDER BY id DESC LIMIT 1");
dzn_2a2f_assert((bool)$request,'Disposable unresolved Booking Request fixture unavailable');$actor=get_current_user_id();dzn_2a2f_assert($actor>0,'Administrator actor unavailable');$at=gmdate('Y-m-d H:i:s');
$identity=new StudentIdentityResolutionService();$event=$identity->createAndResolve((int)$request->id,array('display_name'=>'Synthetic Phase 2A.2-F Student'),'human_review','synthetic_fixture',$at,$actor);
$resolved=$wpdb->get_row($wpdb->prepare("SELECT r.*,e.outcome,e.student_id AS event_student_id FROM {$p}booking_requests r INNER JOIN {$p}booking_request_identity_resolution_events e ON e.id=r.current_identity_resolution_id WHERE r.id=%d",$request->id));dzn_2a2f_assert($resolved&&$resolved->outcome==='resolved'&&(int)$resolved->student_id===(int)$resolved->event_student_id,'Reviewed Student resolution did not project correctly');
$authority=new StudentAcceptanceAuthorityService();$capacity=$authority->classify((int)$resolved->student_id,'adult','human_review','synthetic_fixture',$at,$actor);dzn_2a2f_assert($capacity>0,'Capacity classification failed');
$read=new StudentAcceptanceAuthorityReadService();$before=$read->assess((int)$request->id,$actor);dzn_2a2f_assert(!$before['eligible_adult_self']&&$before['blocked_authority_missing'],'Adult eligibility was inferred without a principal link');
(new BookingRequestPrivacyService())->erase((int)$request->id,$actor,'synthetic_runtime');$after=$read->assess((int)$request->id,$actor);dzn_2a2f_assert($after['blocked_request_privacy_erased'],'Erased request retained acceptance authority');
$blocked=false;try{$identity->recordOutcome((int)$request->id,'ambiguous','human_review','synthetic_fixture',$at,$actor);}catch(Throwable){$blocked=true;}dzn_2a2f_assert($blocked,'Erased request accepted a new identity outcome');
echo "Phase 2A.2-F isolated runtime passed\n";
