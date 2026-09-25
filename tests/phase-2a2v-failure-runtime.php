<?php
/**
 * Disposable Phase-V write-boundary failure injection.
 *
 * A failure at every owning mutation of the Phase-V path must roll back completely: no connection
 * state change, no credential row, no identity/calendar/meeting mapping, no provider-event receipt
 * and no command evidence may survive, and no provider reference or Phase-P evidence may be orphaned.
 * The identical retry must then converge. Structural boundaries (an exact duplicate converging, a
 * changed context conflicting exactly once, a second active projection and a repeated retraction)
 * are covered directly.
 */
if(getenv('DZN_PHASE_2A2V_RUNTIME_TEST')!=='failure'||!defined('WP_CLI')||!WP_CLI||!in_array(wp_get_environment_type(),array('local','development'),true)){fwrite(STDERR,"Phase 2A.2-V failure runtime refused.\n");exit(1);}
require __DIR__.'/phase-2a2r1-fixture.php';
require __DIR__.'/phase-2a2r2-fixture.php';
use Delnavazan\Platform\Core\Application\{ProviderEventIngestService,ProviderIntegrationService};
use Delnavazan\Platform\Integrations\ContractProviderAdapters;
global $wpdb;$p=$wpdb->prefix.'dzn_';
function dzn_vf_assert(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);}
function dzn_vf_key(string $label):string{return 'dzn-2a2vf-'.$label.'-'.wp_generate_uuid4();}
function dzn_vf_rejected(callable $call,string $expected,string $message):void{$caught=null;try{$call();}catch(Throwable$e){$caught=$e;}dzn_vf_assert($caught!==null,$message.' was accepted');dzn_vf_assert($caught->getMessage()===$expected,$message.' was refused with an unexpected error: '.$caught->getMessage());}
$fixture=get_option('dzn_phase_2a2j_fixture');
dzn_vf_assert(is_array($fixture)&&count($fixture['sources']??array())>=1,'Phase-J production fixture required');
dzn_r1_fix_reset(array('commercial_commands','commercial_capacity_claim_intervals','commercial_capacity_claims','commercial_recurring_patterns','commercial_obligation_settlements','commercial_payment_facts','commercial_payment_evidence','commercial_term_funding_plans','commercial_entitlements','commercial_purchases','commercial_offer_obligations','commercial_offer_policies','commercial_offer_adjustments','commercial_offers','commercial_promotion_redemptions','commercial_account_adjustment_events','commercial_account_adjustments','commercial_promotions','commercial_prices','commercial_products','commercial_policies','commercial_account_roots','commercial_exceptions','canonical_continuation_commands','canonical_continuation_interventions','canonical_continuation_reservations','canonical_continuation_decisions','canonical_continuation_cases','canonical_continuation_slot_authorities'));
dzn_r2_fix_reset();
foreach(array('provider_event_conflicts','provider_ingest_events','provider_integration_commands','provider_calendar_event_mappings','provider_meeting_mappings','provider_identity_mappings','integration_oauth_authorizations','integration_credentials','integration_connections') as $table)
    dzn_vf_assert($wpdb->query("DELETE FROM {$p}{$table}")!==false,'Failed to reset disposable Phase V storage: '.$table);
wp_set_current_user(1);
dzn_r1_fix_availability($fixture['teachers']??array());
$funded=dzn_r2_fix_funded_enrolment($fixture['sources'][0],'failure',1);
$target=$wpdb->get_row($wpdb->prepare("SELECT version.* FROM {$p}canonical_lesson_schedule_versions version INNER JOIN {$p}lessons lesson ON lesson.id=version.lesson_id WHERE lesson.term_id=%d AND version.applicable_slot=1 ORDER BY version.id LIMIT 1",(int)$funded['term_id']));
dzn_vf_assert($target!==null,'the fixture must leave one applicable canonical schedule version');
$lessonId=(int)$target->lesson_id;$versionId=(int)$target->id;
$teacherId=(int)$wpdb->get_var($wpdb->prepare("SELECT teacher_id FROM {$p}lessons WHERE id=%d",$lessonId));
$evidence=static fn(string $reference):array=>array('evidence_channel'=>'staff_record','evidence_reference'=>$reference,'evidence_at'=>gmdate('Y-m-d H:i:s'));
$ports=new ContractProviderAdapters(array());
$service=new ProviderIntegrationService(null,null,$ports,$ports,$ports);
$ingestService=new ProviderEventIngestService(null,new ContractProviderAdapters(array()),null);
$scope='https://www.googleapis.com/auth/calendar.events';
/** One-shot write-boundary injector: the first dispatch of the named Phase-V hook throws. */
$inject=static function(string $hook):void{
    $state=(object)array('fired'=>false);
    add_action($hook,static function() use($state,$hook):void{
        if($state->fired)return;
        $state->fired=true;
        throw new RuntimeException('injected_write_boundary:'.$hook);
    },1);
};
$clear=static function(string $hook):void{remove_all_actions($hook);};
$count=static function(string $table,string $where='1=1',array $args=array()) use($wpdb,$p):int{
    $sql="SELECT COUNT(*) FROM {$p}{$table} WHERE ".$where;
    return(int)($args?$wpdb->get_var($wpdb->prepare($sql,...$args)):$wpdb->get_var($sql));
};

// 1. Consent completion: a failure after the connection write leaves no partial connection state.
$begin=$service->beginAuthorization(array('provider_code'=>'google_calendar','teacher_id'=>$teacherId,'client_reference'=>'client-1','redirect_uri'=>'https://academy.example/cb','scope_snapshot'=>$scope)+$evidence('begin'),dzn_vf_key('begin'));
$connectionId=(int)$begin['connection_id'];
$authorizingBefore=$count('integration_connections','id=%d',array($connectionId));
$inject('dzn_phase_2a2v_after_connection_write');
$caught=null;try{$service->completeAuthorization(array('state'=>(string)$begin['state'],'code'=>'code-fail','code_verifier'=>(string)$begin['code_verifier'],'redirect_uri'=>'https://academy.example/cb')+$evidence('complete-fail'),dzn_vf_key('complete-fail'));}catch(Throwable$e){$caught=$e;}
$clear('dzn_phase_2a2v_after_connection_write');
dzn_vf_assert($caught!==null&&str_contains($caught->getMessage(),'injected_write_boundary'),'the injected completion failure must surface');
dzn_vf_assert($count('integration_connections','id=%d',array($connectionId))===$authorizingBefore,'a failed completion must keep exactly the authorizing connection row');
dzn_vf_assert((string)$wpdb->get_var($wpdb->prepare("SELECT connection_state FROM {$p}integration_connections WHERE id=%d",$connectionId))==='authorizing','a failed completion must leave the connection authorizing');
dzn_vf_assert($count('integration_credentials','connection_id=%d',array($connectionId))===0,'a failed completion must leave no credential row');
dzn_vf_assert($count('provider_identity_mappings','connection_id=%d',array($connectionId))===0,'a failed completion must leave no identity mapping');
dzn_vf_assert($count('provider_integration_commands',"operation='complete_authorization'")===0,'a failed completion must leave no command evidence');
dzn_vf_assert($count('integration_oauth_authorizations','id=%d AND authorization_state=%s',array((int)$begin['authorization_id'],'issued'))===1,'a failed completion must leave the authorization intent unconsumed');
$completed=$service->completeAuthorization(array('state'=>(string)$begin['state'],'code'=>'code-fail','code_verifier'=>(string)$begin['code_verifier'],'redirect_uri'=>'https://academy.example/cb')+$evidence('complete-fail'),dzn_vf_key('complete-fail'));
dzn_vf_assert((string)$completed['connection_state']==='connected','the identical retry must converge on a connected connection');
dzn_vf_assert($count('integration_credentials','connection_id=%d AND state=%s',array($connectionId,'active'))===1,'the convergent retry must leave exactly one active credential');
dzn_vf_assert($count('provider_identity_mappings','connection_id=%d AND mapping_state=%s',array($connectionId,'verified'))===1,'the convergent retry must leave exactly one verified identity mapping');
dzn_vf_assert($count('provider_integration_commands',"operation='complete_authorization'")===1,'the convergent retry must leave exactly one completion command');

// 2. Projection: a failure after the integration reference write leaves no orphan reference or command.
$inject('dzn_phase_2a2v_after_projection_write');
$caught=null;try{$service->projectCalendarEvent(array('lesson_id'=>$lessonId,'schedule_version_id'=>$versionId,'projection_reference'=>'calendar-ref-fail')+$evidence('project-fail'),dzn_vf_key('project-fail'));}catch(Throwable$e){$caught=$e;}
$clear('dzn_phase_2a2v_after_projection_write');
dzn_vf_assert($caught!==null&&str_contains($caught->getMessage(),'injected_write_boundary'),'the injected projection failure must surface');
dzn_vf_assert($count('provider_calendar_event_mappings','lesson_id=%d',array($lessonId))===0,'a failed projection must leave no integration reference');
dzn_vf_assert($count('provider_integration_commands',"operation='project_calendar_event'")===0,'a failed projection must leave no command evidence');
dzn_vf_assert((int)$ports->count('google_calendar')===1,'the provider write may have been attempted exactly once and must not be replayable');
$projected=$service->projectCalendarEvent(array('lesson_id'=>$lessonId,'schedule_version_id'=>$versionId,'projection_reference'=>'calendar-ref-fail')+$evidence('project-fail'),dzn_vf_key('project-fail'));
dzn_vf_assert((int)$projected['mapping_id']>0&&$count('provider_calendar_event_mappings','lesson_id=%d',array($lessonId))===1,'the identical retry must record exactly one integration reference');

// 3. Provider evidence: a failure after the receipt write must leave neither a receipt nor a Phase-P fact.
$eventKey='v-failure-'.substr(str_replace('-','',wp_generate_uuid4()),0,8);
$delivery=static fn(string $joinAt):array=>array('provider_code'=>'google_meet','lesson_id'=>$lessonId,'schedule_version_id'=>$versionId,'verified'=>true,'facts'=>array('provider_code'=>'google_meet','provider_event_key'=>$eventKey,'provider_payload_key'=>$eventKey,'participant_role'=>'teacher','provider_account_key'=>'acct-'.$eventKey,'observed_at'=>gmdate('Y-m-d H:i:s'),'join_at_utc'=>$joinAt,'leave_at_utc'=>gmdate('Y-m-d H:i:s')));
$inject('dzn_phase_2a2v_after_ingest_write');
$caught=null;try{$ingestService->ingest($delivery(gmdate('Y-m-d H:i:s')),dzn_vf_key('ingest-fail'));}catch(Throwable$e){$caught=$e;}
$clear('dzn_phase_2a2v_after_ingest_write');
dzn_vf_assert($caught!==null&&str_contains($caught->getMessage(),'injected_write_boundary'),'the injected ingest failure must surface');
dzn_vf_assert($count('provider_ingest_events','provider_event_key_digest=%s',array(hash_hmac('sha256','provider_integration_event_key:'.$eventKey,wp_salt('dzn_provider_integration'))))===0,'a failed ingest must leave no integration receipt');
$phasePKey=hash_hmac('sha256','canonical_attendance_event_key:'.$eventKey,wp_salt('dzn_canonical_attendance'));
dzn_vf_assert((int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$p}canonical_attendance_evidence WHERE provider_event_key_digest=%s",$phasePKey))===0,'a rolled-back ingest must never have handed the fact to Phase P');
$admitted=$ingestService->ingest($delivery(gmdate('Y-m-d H:i:s')),dzn_vf_key('ingest-fail'));
dzn_vf_assert((int)$admitted['ingest_event_id']>0,'the identical retry must record exactly one receipt');
dzn_vf_assert($count('provider_ingest_events','lesson_id=%d',array($lessonId))===1,'the convergent retry must leave exactly one receipt for the occurrence');

// 4. Structural boundaries: convergence, one conflict receipt, one active projection, one retraction.
$duplicate=$ingestService->ingest($delivery(gmdate('Y-m-d H:i:s')),dzn_vf_key('ingest-duplicate'));
dzn_vf_assert($duplicate['idempotent']===true,'an exact duplicate provider event must converge');
dzn_vf_assert($count('provider_ingest_events','lesson_id=%d',array($lessonId))===1,'a duplicate provider event must not create a second receipt');
$conflicting=$ingestService->ingest(array('provider_code'=>'google_meet','lesson_id'=>$lessonId,'schedule_version_id'=>$versionId,'verified'=>true,'facts'=>array_merge($delivery(gmdate('Y-m-d H:i:s'))['facts'],array('join_at_utc'=>gmdate('Y-m-d H:i:s',strtotime('+600 seconds'))))),dzn_vf_key('ingest-conflict'));
dzn_vf_assert($conflicting['conflict']===true,'a changed provider-event context must be refused as a conflict');
dzn_vf_assert($count('provider_event_conflicts','lesson_id=%d',array($lessonId))===1,'a conflicting provider event must leave exactly one conflict receipt');
dzn_vf_assert($count('provider_ingest_events','lesson_id=%d',array($lessonId))===1,'a conflicting provider event must never overwrite or duplicate the original receipt');
$second=$ingestService->ingest(array('provider_code'=>'google_meet','lesson_id'=>$lessonId,'schedule_version_id'=>$versionId,'verified'=>true,'facts'=>array_merge($delivery(gmdate('Y-m-d H:i:s'))['facts'],array('join_at_utc'=>gmdate('Y-m-d H:i:s',strtotime('+1200 seconds'))))),dzn_vf_key('ingest-conflict-2'));
dzn_vf_assert($conflicting['conflict_id']===$second['conflict_id'],'an identical conflict must reuse exactly one conflict receipt');
dzn_vf_rejected(fn()=>$service->projectCalendarEvent(array('lesson_id'=>$lessonId,'schedule_version_id'=>$versionId,'projection_reference'=>'calendar-ref-other')+$evidence('project-other'),dzn_vf_key('project-other')),'projection_already_recorded','a second active projection for one occurrence');
$retracted=$service->retractCalendarProjection((int)$projected['mapping_id'],array('reason_code'=>'failure_probe')+$evidence('retract'),dzn_vf_key('retract'));
dzn_vf_assert((string)$retracted['mapping_state']==='revoked','the retraction must quarantine the active reference');
$again=$service->retractCalendarProjection((int)$projected['mapping_id'],array('reason_code'=>'failure_probe')+$evidence('retract'),dzn_vf_key('retract'));
dzn_vf_assert($again['idempotent']===true,'an identical retraction must replay idempotently');
dzn_vf_rejected(fn()=>$service->retractMeetingProjection((int)$projected['mapping_id'],array('reason_code'=>'wrong_purpose')+$evidence('retract-wrong'),dzn_vf_key('retract-wrong')),'integration_mapping_required','a meeting retraction of a calendar mapping');
$unknown=$service->retractCalendarProjection((int)$projected['mapping_id'],array('reason_code'=>'again')+$evidence('retract-again'),dzn_vf_key('retract-again'));
dzn_vf_assert($unknown['mapping_state']==='revoked','a retraction of an already-retracted mapping must stay revoked, never become active again');
dzn_vf_assert($count('provider_calendar_event_mappings','lesson_id=%d AND active_slot=1',array($lessonId))===0,'a retraction must never leave an active reference behind');

echo "Phase 2A.2-V failure runtime passed\n";
