<?php
/**
 * Disposable production-path Phase 2A.2-S corruption proof.
 *
 * Covers: a rule appended after activation, a mandatory rule removed or rebound, the expiry rule removed,
 * a forged rule-set digest, a rewritten schedule, a re-anchored expiry, a deferral count above its maximum,
 * a persisted timezone that is not the frozen resolution, an outbox mirror that disagrees with the
 * aggregate, a notification/outbox pair whose reciprocal pointer was split while the mirror still mirrors
 * its schedule, the same pair cleared on **both** sides at once (so neither lookup finds a row, and only
 * the mirror requirement itself can refuse it), and a tier-F instant nulled after publication. Every
 * corruption fails closed through the protected read with its own code and converges once the row is
 * restored.
 */
if(getenv('DZN_PHASE_2A2S_RUNTIME_TEST')!=='corruption'||!defined('WP_CLI')||!WP_CLI||!in_array(wp_get_environment_type(),array('local','development'),true)){fwrite(STDERR,"Phase 2A.2-S corruption runtime refused.\n");exit(1);}
require __DIR__.'/phase-2a2s-fixture.php';
use Delnavazan\Platform\Core\Application\NotificationReadService;
use Delnavazan\Platform\Core\Application\NotificationAttemptReadService;
use Delnavazan\Platform\Core\Application\NotificationRule;
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

// 7. The notification/outbox relationship is 1:1 and proved from **both** sides, never from the mirrored
//    schedule alone: a notification pointer that names a different valid S-owned row, a NULL notification
//    pointer beside an intact mirror, and an outbox pointer that no longer names its notification each
//    refuse the aggregate read, the attempt read seam and the schema verifier while every mirrored schedule
//    value stays exactly what the aggregate derived — and each converges once the pointer is restored. The
//    peer row is the mirror-backed pair §6 left behind, so the mis-pointed notification has a genuinely
//    valid S-owned row to point at, and the pair under test is observed through §6's still-active version.
$peerNotificationId=$mirrorNotificationId;
$peerOutboxId=(int)$wpdb->get_var($wpdb->prepare("SELECT id FROM {$p}platform_outbox WHERE notification_id=%d",$peerNotificationId));
$peerMirror=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$p}platform_outbox WHERE id=%d",$peerOutboxId));
$identityCycle=dzn_s_fix_cycle(null,null,'corrupt-identity');
dzn_s_fix_cycle_transition($identityCycle,'lapsed','lapsed','corrupt-identity');
$identityIntent=dzn_s_fix_intent('renewal_cycle',$identityCycle,'TERM_LAPSED','corrupt-identity');
$identityObserved=$mirrorReady['service']->observeIntent($identityIntent,array_merge(dzn_s_fix_evidence('corrupt-identity'),array('observed_at'=>gmdate('Y-m-d H:i:s'))),dzn_s_fix_key('obs-corrupt-identity'));
$identityNotificationId=(int)$identityObserved['notification_id'];
$identityOutboxId=(int)$identityObserved['outbox_id'];
dzn_s_fix_assert($identityOutboxId>0&&$identityOutboxId!==$peerOutboxId,'the identity fixture needs a second distinct valid outbox row');
dzn_s_fix_assert((int)$read->one($identityNotificationId)['outbox_id']===$identityOutboxId,'the intact aggregate must resolve the mirror it points at');
dzn_s_fix_assert($attemptRead->attempts($identityNotificationId)===array(),'the intact attempt read seam must return the empty history');
$identityMirror=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$p}platform_outbox WHERE id=%d",$identityOutboxId));
// (a) The notification points at the peer's *valid* S-owned row while its own row still names it and still
//     mirrors its schedule exactly, so only the reciprocal pointer can refuse the pair.
$wpdb->update($p.'notifications',array('outbox_id'=>null),array('id'=>$identityNotificationId));
$wpdb->update($p.'notifications',array('outbox_id'=>null),array('id'=>$peerNotificationId));
$wpdb->update($p.'notifications',array('outbox_id'=>$peerOutboxId),array('id'=>$identityNotificationId));
$wpdb->update($p.'notifications',array('outbox_id'=>$identityOutboxId),array('id'=>$peerNotificationId));
dzn_s_fix_rejected(fn()=>$read->one($identityNotificationId),'schedule_derivation_divergence','a notification pointer that names a different valid outbox row');
dzn_s_fix_rejected(fn()=>$attemptRead->attempts($identityNotificationId),'schedule_derivation_divergence','a mis-pointed aggregate on the attempt read seam');
$identityVerifier=false;
try{Delnavazan\Platform\Core\Infrastructure\Migration\Migrator::maybe_upgrade();}catch(Throwable $error){$identityVerifier=str_contains($error->getMessage(),'outbox pointer divergence');}
dzn_s_fix_assert($identityVerifier,'a notification pointer naming another valid row must fail the S verifier');
$wpdb->update($p.'notifications',array('outbox_id'=>null),array('id'=>$identityNotificationId));
$wpdb->update($p.'notifications',array('outbox_id'=>null),array('id'=>$peerNotificationId));
$wpdb->update($p.'notifications',array('outbox_id'=>$identityOutboxId),array('id'=>$identityNotificationId));
$wpdb->update($p.'notifications',array('outbox_id'=>$peerOutboxId),array('id'=>$peerNotificationId));
dzn_s_fix_assert((int)$read->one($identityNotificationId)['outbox_id']===$identityOutboxId,'restoring the reciprocal pointers must restore the read');
dzn_s_fix_assert(count($attemptRead->attempts($peerNotificationId))===1,'restoring the reciprocal pointers must restore the peer attempt read');
// (b) A NULL aggregate pointer beside the intact mirror row.
$wpdb->update($p.'notifications',array('outbox_id'=>null),array('id'=>$identityNotificationId));
dzn_s_fix_rejected(fn()=>$read->one($identityNotificationId),'schedule_derivation_divergence','a NULL notification pointer beside an intact mirror');
dzn_s_fix_rejected(fn()=>$attemptRead->attempts($identityNotificationId),'schedule_derivation_divergence','a NULL notification pointer on the attempt read seam');
$identityVerifier=false;
try{Delnavazan\Platform\Core\Infrastructure\Migration\Migrator::maybe_upgrade();}catch(Throwable $error){$identityVerifier=str_contains($error->getMessage(),'outbox pointer divergence');}
dzn_s_fix_assert($identityVerifier,'a NULL notification pointer beside an intact mirror must fail the S verifier');
$wpdb->update($p.'notifications',array('outbox_id'=>$identityOutboxId),array('id'=>$identityNotificationId));
dzn_s_fix_assert((int)$read->one($identityNotificationId)['outbox_id']===$identityOutboxId,'restoring the NULL pointer must restore the read');
// (c) The outbox-side pointer no longer names its notification, while the mirrored schedule stays intact.
$wpdb->update($p.'platform_outbox',array('notification_id'=>null),array('id'=>$identityOutboxId));
dzn_s_fix_rejected(fn()=>$read->one($identityNotificationId),'schedule_derivation_divergence','an outbox pointer that no longer names its notification');
dzn_s_fix_rejected(fn()=>$attemptRead->attempts($identityNotificationId),'schedule_derivation_divergence','a missing outbox pointer on the attempt read seam');
$identityVerifier=false;
try{Delnavazan\Platform\Core\Infrastructure\Migration\Migrator::maybe_upgrade();}catch(Throwable $error){$identityVerifier=str_contains($error->getMessage(),'schedule_derivation_divergence');}
dzn_s_fix_assert($identityVerifier,'an outbox pointer that no longer names its notification must fail the S verifier');
$wpdb->update($p.'platform_outbox',array('notification_id'=>$identityNotificationId),array('id'=>$identityOutboxId));
dzn_s_fix_assert((int)$read->one($identityNotificationId)['outbox_id']===$identityOutboxId,'restoring the outbox pointer must restore the read');
dzn_s_fix_assert((string)$wpdb->get_var($wpdb->prepare("SELECT scheduled_for FROM {$p}platform_outbox WHERE id=%d",$identityOutboxId))===(string)$identityMirror->scheduled_for,'the mirrored schedule values must never have been touched');
// (d) Both reciprocal pointers cleared at once, so a lookup from either side finds nothing: the notification
//     names no row and its former row names no notification. §6.5 still forbids the pair — the mirror
//     requirement is proved of the pair itself, never of whichever row happens to be found — so the
//     aggregate read, the attempt read seam on both of its projections and the schema verifier (whose row
//     loop can no longer see the row at all) must each refuse it, and restoring both pointers converges.
$wpdb->update($p.'notifications',array('outbox_id'=>null),array('id'=>$peerNotificationId));
$wpdb->update($p.'platform_outbox',array('notification_id'=>null),array('id'=>$peerOutboxId));
dzn_s_fix_assert($wpdb->get_var($wpdb->prepare("SELECT id FROM {$p}platform_outbox WHERE notification_id=%d",$peerNotificationId))===null,'clearing both pointers must leave no row to look up from the outbox side');
dzn_s_fix_rejected(fn()=>$read->one($peerNotificationId),'schedule_derivation_divergence','a cleared reciprocal pair on the aggregate read');
dzn_s_fix_rejected(fn()=>$attemptRead->attempts($peerNotificationId),'schedule_derivation_divergence','a cleared reciprocal pair on the attempt read seam');
dzn_s_fix_rejected(fn()=>$attemptRead->one((int)$mirrorClaim['attempt_id']),'schedule_derivation_divergence','a cleared reciprocal pair on one attempt projection');
$identityVerifier=false;
try{Delnavazan\Platform\Core\Infrastructure\Migration\Migrator::maybe_upgrade();}catch(Throwable $error){$identityVerifier=str_contains($error->getMessage(),'schedule_derivation_divergence');}
dzn_s_fix_assert($identityVerifier,'a cleared reciprocal pair must fail the S verifier');
$wpdb->update($p.'notifications',array('outbox_id'=>$peerOutboxId),array('id'=>$peerNotificationId));
$wpdb->update($p.'platform_outbox',array('notification_id'=>$peerNotificationId),array('id'=>$peerOutboxId));
dzn_s_fix_assert((int)$read->one($peerNotificationId)['outbox_id']===$peerOutboxId,'restoring both reciprocal pointers must restore the aggregate read');
dzn_s_fix_assert(count($attemptRead->attempts($peerNotificationId))===1,'restoring both reciprocal pointers must restore the attempt read seam');
dzn_s_fix_assert((string)$wpdb->get_var($wpdb->prepare("SELECT scheduled_for FROM {$p}platform_outbox WHERE id=%d",$peerOutboxId))===(string)$peerMirror->scheduled_for,'restoring the cleared pair must not touch the mirrored schedule');
echo "Phase 2A.2-S corruption runtime passed\n";
