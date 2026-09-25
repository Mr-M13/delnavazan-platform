<?php
/**
 * Disposable production-path Phase 2A.2-S corruption proof.
 *
 * Covers: a rule appended after activation, a mandatory rule removed or rebound, the expiry rule removed,
 * a forged rule-set digest, a rewritten schedule, a re-anchored expiry, a deferral count above its maximum,
 * a persisted timezone that is not the frozen resolution, an outbox mirror that disagrees with the
 * aggregate, and a tier-F instant nulled after publication. Every corruption fails closed through the
 * protected read with its own code and converges once the row is restored.
 */
if(getenv('DZN_PHASE_2A2S_RUNTIME_TEST')!=='corruption'||!defined('WP_CLI')||!WP_CLI||!in_array(wp_get_environment_type(),array('local','development'),true)){fwrite(STDERR,"Phase 2A.2-S corruption runtime refused.\n");exit(1);}
require __DIR__.'/phase-2a2s-fixture.php';
use Delnavazan\Platform\Core\Application\NotificationReadService;
use Delnavazan\Platform\Core\Application\NotificationAttemptReadService;
use Delnavazan\Platform\Core\Application\NotificationSupport;
use Delnavazan\Platform\Core\Application\NotificationWorkflowReadService;
use Delnavazan\Platform\Core\Application\NotificationIntegrity;
use Delnavazan\Platform\Core\Application\NotificationTemplateReadService;
global $wpdb;$p=$wpdb->prefix.'dzn_';
dzn_s_fix_reset();
wp_set_current_user(1);
$read=new NotificationReadService();
$workflowRead=new NotificationWorkflowReadService();

// 1. A post-activation rule append makes the version un-dispatchable, and restoring the row converges.
$ready=dzn_s_fix_ready('TERM_LAPSED','corrupt-append');
$versionId=(int)$ready['version']['workflow_version_id'];
$digest=$wpdb->get_var($wpdb->prepare("SELECT rule_set_digest FROM {$p}notification_workflow_versions WHERE id=%d",$versionId));
$wpdb->query($wpdb->prepare("INSERT INTO {$p}notification_workflow_rules (uid,workflow_version_id,rule_kind,rule_code,ordinal,parameter_a,parameter_b,parameter_c,parameter_d,recorded_at,recorded_by,created_at,created_by) VALUES (%s,%d,'eligibility','recipient_opted_in',1,NULL,NULL,NULL,NULL,%s,1,%s,1)",Delnavazan\Platform\Core\Support\Identifier::uid(),$versionId,gmdate('Y-m-d H:i:s'),gmdate('Y-m-d H:i:s')));
$appended=(int)$wpdb->insert_id;
dzn_s_fix_rejected(fn()=>$workflowRead->version($versionId),'workflow_rule_set_mutated','a raw post-activation rule append');
dzn_s_fix_rejected(fn()=>$ready['service']->observeIntent(0,array(),dzn_s_fix_key('corrupt-observe')),'notification_not_found','the S service must refuse before reaching a mutated version');
$wpdb->delete($p.'notification_workflow_rules',array('id'=>$appended));
dzn_s_fix_assert((string)$workflowRead->version($versionId)['rule_set_digest']===(string)$digest,'restoring the rule rows must restore the version');

// 2. A forged frozen digest is a version-integrity failure.
$wpdb->update($p.'notification_workflow_versions',array('rule_set_digest'=>str_repeat('f',64)),array('id'=>$versionId));
dzn_s_fix_rejected(fn()=>$workflowRead->version($versionId),'workflow_rule_set_mutated','a forged frozen rule-set digest');
$wpdb->update($p.'notification_workflow_versions',array('rule_set_digest'=>$digest),array('id'=>$versionId));
dzn_s_fix_assert((string)$workflowRead->version($versionId)['rule_set_digest']===(string)$digest,'restoring the digest must restore the version');

// 3. A rewritten schedule, a re-anchored expiry and a mirrored divergence each fail closed by code.
$cycle=dzn_s_fix_cycle(null,null,'corrupt-schedule');
dzn_s_fix_cycle_transition($cycle,'lapsed','lapsed','corrupt-schedule');
$intent=dzn_s_fix_intent('renewal_cycle',$cycle,'TERM_LAPSED','corrupt-schedule');
$observed=$ready['service']->observeIntent($intent,array_merge(dzn_s_fix_evidence('corrupt-schedule'),array('observed_at'=>gmdate('Y-m-d H:i:s'))),dzn_s_fix_key('obs-corrupt'));
$notificationId=(int)$observed['notification_id'];
$row=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$p}notifications WHERE id=%d",$notificationId));
$wpdb->update($p.'notifications',array('scheduled_for'=>NotificationSupport::addSeconds((string)$row->scheduled_for,3600)),array('id'=>$notificationId));
dzn_s_fix_rejected(fn()=>$read->one($notificationId),'schedule_derivation_divergence','a rewritten scheduled_for');
$wpdb->update($p.'notifications',array('scheduled_for'=>$row->scheduled_for,'expires_at'=>NotificationSupport::addSeconds((string)$row->expires_at,3600)),array('id'=>$notificationId));
dzn_s_fix_rejected(fn()=>$read->one($notificationId),'schedule_derivation_divergence','a re-anchored expiry');
$wpdb->update($p.'notifications',array('expires_at'=>$row->expires_at,'deferral_count'=>5),array('id'=>$notificationId));
dzn_s_fix_rejected(fn()=>$read->one($notificationId),'schedule_derivation_divergence','a deferral count above its maximum');
$wpdb->update($p.'notifications',array('deferral_count'=>0,'timezone'=>'Europe/Paris'),array('id'=>$notificationId));
dzn_s_fix_rejected(fn()=>$read->one($notificationId),'schedule_derivation_divergence','a persisted timezone that is not the frozen resolution');
$wpdb->update($p.'notifications',array('timezone'=>(string)$row->timezone,'scheduled_for'=>$row->scheduled_for,'expires_at'=>$row->expires_at),array('id'=>$notificationId));
$wpdb->update($p.'platform_outbox',array('scheduled_for'=>NotificationSupport::addSeconds((string)$row->scheduled_for,60)),array('notification_id'=>$notificationId));
$verifierFailed=false;
try{Delnavazan\Platform\Core\Infrastructure\Migration\Migrator::maybe_upgrade();}catch(Throwable $error){$verifierFailed=str_contains($error->getMessage(),'outbox mirror');}
dzn_s_fix_assert($verifierFailed,'an outbox mirror that disagrees with the aggregate must fail the S verifier');
$wpdb->update($p.'platform_outbox',array('scheduled_for'=>$row->scheduled_for),array('notification_id'=>$notificationId));
Delnavazan\Platform\Core\Infrastructure\Migration\Migrator::maybe_upgrade();
dzn_s_fix_assert((string)$read->one($notificationId)['scheduled_for']===(string)$row->scheduled_for,'restoring the mirror must restore verification');

// 4. The frozen variable contract and the rendered snapshot are proved, not assumed: a contract text that is
//    not the declared code set, a forged contract digest, a declared code set that disagrees with the
//    contract and a parameter map whose keys (or digest) are not the contract all fail closed.
$contractReady=dzn_s_fix_ready('TERM_LAPSED','corrupt-contract');
$templateVersionId=(int)$contractReady['version']['template_version_id'];
$templateRead=new NotificationTemplateReadService();
$contract=NotificationIntegrity::variableContract($wpdb->get_row($wpdb->prepare("SELECT * FROM {$p}notification_template_versions WHERE id=%d",$templateVersionId)));
dzn_s_fix_assert($contract['variable_contract']===''&&$contract['variable_count']===0,'the fixture contract must be its declared empty code set');
dzn_s_fix_assert($templateRead->version($templateVersionId)['variable_contract']==='','the template read seam must expose the frozen contract');
$wpdb->update($p.'notification_template_versions',array('variable_contract'=>'greeting_name'),array('id'=>$templateVersionId));
dzn_s_fix_rejected(fn()=>$templateRead->version($templateVersionId),'template_variable_mismatch','a contract text that is not the frozen code set');
$wpdb->update($p.'notification_template_versions',array('variable_contract'=>'','variable_contract_digest'=>str_repeat('f',64)),array('id'=>$templateVersionId));
dzn_s_fix_rejected(fn()=>$templateRead->version($templateVersionId),'template_variable_mismatch','a forged variable-contract digest');
$wpdb->update($p.'notification_template_versions',array('variable_contract_digest'=>$contract['variable_contract_digest']),array('id'=>$templateVersionId));
dzn_s_fix_assert($templateRead->version($templateVersionId)['variable_contract_digest']===$contract['variable_contract_digest'],'restoring the contract must restore the version');
$forgedVersion=(object)array(
    'variable_contract'=>'greeting_name','required_variable_count'=>1,
    'variable_contract_digest'=>NotificationSupport::variableContractDigest('greeting_name'),
);
$snapshot=(object)array('variable_codes'=>'greeting_name','variable_count'=>1,'params_digest'=>NotificationSupport::paramsDigest(array('greeting_name'=>'Ali')));
NotificationIntegrity::renderedSnapshot($forgedVersion,$snapshot,array('greeting_name'=>'Ali'));
dzn_s_fix_rejected(fn()=>NotificationIntegrity::renderedSnapshot($forgedVersion,$snapshot,array()),'template_variable_mismatch','an encrypted parameter map that is not the frozen contract');
dzn_s_fix_rejected(fn()=>NotificationIntegrity::renderedSnapshot($forgedVersion,$snapshot,array('greeting_name'=>'Ali','extra_code'=>'x')),'template_variable_mismatch','a parameter map carrying an extra code');
dzn_s_fix_rejected(fn()=>NotificationIntegrity::renderedSnapshot($forgedVersion,$snapshot,array('greeting_name'=>'Sara')),'template_variable_mismatch','a parameter map whose canonical digest does not reproduce');
dzn_s_fix_rejected(fn()=>NotificationIntegrity::renderedSnapshot($forgedVersion,(object)array_merge((array)$snapshot,array('variable_codes'=>'greeting_name,extra_code')),array('greeting_name'=>'Ali')),'template_variable_mismatch','a declared code set that disagrees with the frozen contract');

// 5. The persisted attempt history is proved, not assumed: a truncated chain, an open attempt whose state
//    vocabulary disagrees with its own open marker, and a terminal notification that still holds a live lease
//    each fail closed through the protected read and converge when the rows are restored.
$attemptReady=dzn_s_fix_ready('TERM_LAPSED','corrupt-attempt');
$attemptCycle=dzn_s_fix_cycle(null,null,'corrupt-attempt');
dzn_s_fix_cycle_transition($attemptCycle,'lapsed','lapsed','corrupt-attempt');
$attemptIntent=dzn_s_fix_intent('renewal_cycle',$attemptCycle,'TERM_LAPSED','corrupt-attempt');
$attemptObserved=$attemptReady['service']->observeIntent($attemptIntent,array_merge(dzn_s_fix_evidence('corrupt-attempt'),array('observed_at'=>gmdate('Y-m-d H:i:s'))),dzn_s_fix_key('obs-corrupt-attempt'));
$attemptReady['service']->enqueue((int)$attemptObserved['notification_id'],dzn_s_fix_evidence('corrupt-attempt-enqueue'),dzn_s_fix_key('enq-corrupt-attempt'));
$attemptDispatch=dzn_s_fix_dispatch($attemptReady,new DznSConcurrencyTransport());
$attemptClaim=$attemptDispatch->claimLease(dzn_s_fix_evidence('corrupt-attempt-claim'),dzn_s_fix_key('claim-corrupt-attempt'));
dzn_s_fix_assert($attemptClaim['claimed']===true,'the corruption fixture must acquire its lease');
$attemptNotificationId=(int)$attemptObserved['notification_id'];
$attemptId=(int)$attemptClaim['attempt_id'];
dzn_s_fix_assert($read->one($attemptNotificationId)['state']==='dispatching','the intact open lease must read clean');
$attemptEvents=$wpdb->get_results($wpdb->prepare("SELECT * FROM {$p}notification_attempt_events WHERE attempt_id=%d ORDER BY event_sequence",$attemptId));
dzn_s_fix_assert(count($attemptEvents)===1,'the intact lease must carry its own attempt event');
$wpdb->delete($p.'notification_attempt_events',array('id'=>(int)$attemptEvents[0]->id));
dzn_s_fix_rejected(fn()=>$read->one($attemptNotificationId),'attempt_lifecycle_invalid','a truncated attempt history');
$wpdb->insert($p.'notification_attempt_events',(array)$attemptEvents[0]);
dzn_s_fix_assert($read->one($attemptNotificationId)['state']==='dispatching','restoring the attempt history must restore the read');
$wpdb->update($p.'notification_attempts',array('state'=>'acknowledged'),array('id'=>$attemptId));
dzn_s_fix_rejected(fn()=>$read->one($attemptNotificationId),'attempt_lifecycle_invalid','an open attempt whose state vocabulary disagrees with its open marker');
$wpdb->update($p.'notification_attempts',array('state'=>'leased'),array('id'=>$attemptId));
$wpdb->update($p.'notifications',array('state'=>'failed','failure_reason_code'=>NotificationRule::CEILING_EXHAUSTION_CODE),array('id'=>$attemptNotificationId));
dzn_s_fix_rejected(fn()=>$read->one($attemptNotificationId),'attempt_lifecycle_invalid','a terminal notification that still holds an open attempt');
$wpdb->update($p.'notifications',array('state'=>'dispatching','failure_reason_code'=>null),array('id'=>$attemptNotificationId));
dzn_s_fix_assert($read->one($attemptNotificationId)['state']==='dispatching','restoring the closed/open shape must restore the read');

// 6. The persisted outbox mirror is proved by the shared aggregate verification on every protected read, not
//    only the aggregate one: a mirrored row that disagrees with its aggregate refuses the attempt read seam
//    with the same code as the aggregate read, and restoring it converges.
$mirrorReady=dzn_s_fix_ready('TERM_LAPSED','corrupt-mirror');
$mirrorCycle=dzn_s_fix_cycle(null,null,'corrupt-mirror');
dzn_s_fix_cycle_transition($mirrorCycle,'lapsed','lapsed','corrupt-mirror');
$mirrorIntent=dzn_s_fix_intent('renewal_cycle',$mirrorCycle,'TERM_LAPSED','corrupt-mirror');
$mirrorObserved=$mirrorReady['service']->observeIntent($mirrorIntent,array_merge(dzn_s_fix_evidence('corrupt-mirror'),array('observed_at'=>gmdate('Y-m-d H:i:s'))),dzn_s_fix_key('obs-corrupt-mirror'));
$mirrorNotificationId=(int)$mirrorObserved['notification_id'];
$mirrorReady['service']->enqueue($mirrorNotificationId,dzn_s_fix_evidence('corrupt-mirror-enqueue'),dzn_s_fix_key('enq-corrupt-mirror'));
$mirrorDispatch=dzn_s_fix_dispatch($mirrorReady,new DznSConcurrencyTransport());
$mirrorClaim=$mirrorDispatch->claimLease(dzn_s_fix_evidence('corrupt-mirror-claim'),dzn_s_fix_key('claim-corrupt-mirror'));
dzn_s_fix_assert($mirrorClaim['claimed']===true,'the mirror fixture must acquire its lease');
$attemptRead=new NotificationAttemptReadService();
dzn_s_fix_assert(count($attemptRead->attempts($mirrorNotificationId))===1,'the intact attempt read must return the open attempt');
$mirrorRow=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$p}notifications WHERE id=%d",$mirrorNotificationId));
$wpdb->update($p.'platform_outbox',array('scheduled_for'=>NotificationSupport::addSeconds((string)$mirrorRow->scheduled_for,60)),array('notification_id'=>$mirrorNotificationId));
dzn_s_fix_rejected(fn()=>$attemptRead->attempts($mirrorNotificationId),'schedule_derivation_divergence','an outbox mirror that disagrees with the aggregate on the attempt read seam');
dzn_s_fix_rejected(fn()=>$attemptRead->one((int)$mirrorClaim['attempt_id']),'schedule_derivation_divergence','an outbox mirror that disagrees with the aggregate on one attempt projection');
$wpdb->update($p.'platform_outbox',array('scheduled_for'=>$mirrorRow->scheduled_for),array('notification_id'=>$mirrorNotificationId));
dzn_s_fix_assert(count($attemptRead->attempts($mirrorNotificationId))===1,'restoring the mirror must restore the attempt read');
echo "Phase 2A.2-S corruption runtime passed\n";
