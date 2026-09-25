<?php
/** Disposable Phase-T concurrency verifier. Synthetic local data only. */
if(getenv('DZN_PHASE_2A2T_RUNTIME_TEST')!=='concurrency'||!defined('WP_CLI')||!WP_CLI||!in_array(wp_get_environment_type(),array('local','development'),true)){fwrite(STDERR,"Phase 2A.2-T concurrency verifier refused.\n");exit(1);}
global $wpdb;$p=$wpdb->prefix.'dzn_';
function dzn_tcv_assert(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);}
$fixture=get_option('dzn_phase_2a2t_concurrency_fixture');
dzn_tcv_assert(is_array($fixture),'the concurrency fixture must exist');
$mode=(string)$fixture['mode'];
$gate=(string)getenv('DZN_PHASE_2A2T_GATE_DIR');
$records=array();
foreach(array('w1','w2') as $worker){
    $file=$gate.'/'.$worker.'.json';
    if(is_file($file))$records[$worker]=json_decode((string)file_get_contents($file),true);
}
$refusals=(int)$wpdb->get_var("SELECT COUNT(*) FROM {$p}payment_execution_results WHERE result_state='refused' AND reason_code='dispatch_in_flight'");
$settled=(int)$wpdb->get_var("SELECT COUNT(*) FROM {$p}payment_execution_dispatches WHERE dispatch_state='settled'");
$live=(int)$wpdb->get_var("SELECT COUNT(*) FROM {$p}payment_execution_dispatches WHERE active_claim_slot=1");
$duplicateClaims=$wpdb->get_results("SELECT arbitration_subject_kind,arbitration_subject_id,COUNT(*) AS total FROM {$p}payment_execution_dispatches WHERE active_claim_slot=1 GROUP BY arbitration_subject_kind,arbitration_subject_id HAVING total>1");
$duplicateResults=$wpdb->get_results("SELECT execution_command_id,COUNT(*) AS total FROM {$p}payment_execution_results GROUP BY execution_command_id HAVING total>1");
$duplicateDispatches=$wpdb->get_results("SELECT execution_command_id,COUNT(*) AS total FROM {$p}payment_execution_dispatches GROUP BY execution_command_id HAVING total>1");
$attempts=$wpdb->get_results("SELECT execution_command_id,COUNT(*) AS total FROM {$p}payment_execution_attempts GROUP BY execution_command_id HAVING total>1");
dzn_tcv_assert(!$duplicateClaims,'two live claims must never share one arbitration subject');
dzn_tcv_assert(!$duplicateResults,'one command must never hold two terminal results');
dzn_tcv_assert(!$duplicateDispatches,'one command must never hold two dispatch claims');
dzn_tcv_assert(!$attempts,'one command must never hold two attempts');
$releasedWithLease=(int)$wpdb->get_var("SELECT COUNT(*) FROM {$p}payment_execution_dispatches WHERE dispatch_state='released' AND lease_expires_at IS NOT NULL");
dzn_tcv_assert($releasedWithLease===0,'a released claim may never retain a lease');
$generations=$wpdb->get_col("SELECT claim_generation FROM {$p}payment_execution_dispatches");
foreach($generations as $generation)dzn_tcv_assert((int)$generation>=1,'a dispatch generation must be positive');

// [C8-3] The duplicate-delivery race: one event identity, one recorded event, and never a second
// translation or a second R1/R2 consequence for a duplicate the unique index arbitrated.
if(in_array($mode,array('duplicate_webhook','conflicting_duplicate_webhook'),true)){
    dzn_tcv_assert(isset($fixture['webhook']['obligation_id'],$fixture['webhook']['body'],$fixture['webhook']['body_changed']),'the duplicate-webhook race fixture must exist');
    $webhook=$fixture['webhook'];
    $events=(int)$wpdb->get_var("SELECT COUNT(*) FROM {$p}payment_provider_events");
    $decisions=(int)$wpdb->get_var("SELECT COUNT(*) FROM {$p}payment_provider_event_decisions");
    $conflicts=(int)$wpdb->get_var("SELECT COUNT(*) FROM {$p}payment_provider_event_decisions WHERE decision_state='conflicted'");
    $settlements=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$p}commercial_obligation_settlements WHERE obligation_id=%d",(int)$webhook['obligation_id']));
    $evidence=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$p}commercial_payment_evidence WHERE obligation_id=%d",(int)$webhook['obligation_id']));
    dzn_tcv_assert($events===1,'two deliveries of one event identity must converge on exactly one recorded event');
    dzn_tcv_assert($settlements===1&&$evidence===1,'a duplicate delivery must settle exactly once through R1');
    dzn_tcv_assert((string)$wpdb->get_var($wpdb->prepare("SELECT state FROM {$p}collection_intents WHERE id=%d",(int)$webhook['intent_id']))==='confirmed','the collection intent must be confirmed exactly once');
    dzn_tcv_assert((string)$wpdb->get_var($wpdb->prepare("SELECT state FROM {$p}renewal_cycles WHERE id=%d",(int)$webhook['cycle_id']))==='collected','the renewal cycle must be collected exactly once');
    $payloadDigest=(string)$wpdb->get_var("SELECT payload_digest FROM {$p}payment_provider_events");
    dzn_tcv_assert(in_array($payloadDigest,array(
        \Delnavazan\Platform\Core\Application\PaymentExecution\PaymentExecutionIdempotency::payloadDigest((string)$webhook['body']),
        \Delnavazan\Platform\Core\Application\PaymentExecution\PaymentExecutionIdempotency::payloadDigest((string)$webhook['body_changed']),
    ),true),'the recorded event must be one of the two deliveries that raced');
    if($mode==='duplicate_webhook'){
        dzn_tcv_assert($decisions===1&&$conflicts===0,'identical deliveries must append no second decision at all');
    }else{
        dzn_tcv_assert($decisions===2&&$conflicts===1,'materially different facts for one event identity must append exactly one controlled conflict decision');
    }
}

// [C9-1] The decision-claim races: an event that already owes its decision is completed by exactly one
// worker, the loser performs no work, and the event ends with one settled claim and one terminal decision.
if(in_array($mode,array('pending_decision_retry','undecided_event_recovery'),true)){
    dzn_tcv_assert(isset($fixture['webhook']['obligation_id'],$fixture['webhook']['intent_id'],$fixture['webhook']['cycle_id'],$fixture['prepared_event_id']),'the decision-claim race fixture must exist');
    $webhook=$fixture['webhook'];
    $prepared=(int)$fixture['prepared_event_id'];
    $events=(int)$wpdb->get_var("SELECT COUNT(*) FROM {$p}payment_provider_events");
    $decisions=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$p}payment_provider_event_decisions WHERE provider_event_id=%d",$prepared));
    $applied=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$p}payment_provider_event_decisions WHERE provider_event_id=%d AND r2_consequence_state='applied'",$prepared));
    $claims=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$p}payment_provider_event_decision_claims WHERE provider_event_id=%d",$prepared));
    $settledClaims=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$p}payment_provider_event_decision_claims WHERE provider_event_id=%d AND claim_state='settled'",$prepared));
    $liveClaimsForEvent=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$p}payment_provider_event_decision_claims WHERE provider_event_id=%d AND active_claim_slot=1",$prepared));
    $liveClaims=$wpdb->get_results("SELECT provider_event_id,COUNT(*) AS total FROM {$p}payment_provider_event_decision_claims WHERE active_claim_slot=1 GROUP BY provider_event_id HAVING total>1");
    $evidence=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$p}commercial_payment_evidence WHERE obligation_id=%d",(int)$webhook['obligation_id']));
    $settlements=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$p}commercial_obligation_settlements WHERE obligation_id=%d",(int)$webhook['obligation_id']));
    dzn_tcv_assert($events===1,'the decision race must leave exactly one recorded event');
    dzn_tcv_assert($claims>=1&&$liveClaimsForEvent===0,'each decision must leave a terminal claim and no live claim');
    dzn_tcv_assert($settledClaims===$decisions,'every appended decision must have exactly one settled claim and no decision may be unclaimed');
    dzn_tcv_assert(!$liveClaims,'two live decision claims must never share one event');
    dzn_tcv_assert($live===0&&$duplicateClaims===array(),'no execution claim may be left live by the decision race');
    dzn_tcv_assert($evidence===1&&$settlements===1,'the R1 evidence must be submitted exactly once');
    dzn_tcv_assert((string)$wpdb->get_var($wpdb->prepare("SELECT state FROM {$p}collection_intents WHERE id=%d",(int)$webhook['intent_id']))==='confirmed','the collection intent must be confirmed exactly once');
    dzn_tcv_assert((string)$wpdb->get_var($wpdb->prepare("SELECT state FROM {$p}renewal_cycles WHERE id=%d",(int)$webhook['cycle_id']))==='collected','the renewal cycle must be collected exactly once');
    if($mode==='pending_decision_retry'){
        dzn_tcv_assert($decisions===2&&$applied===1,'a pending-decision retry must append exactly one terminal decision beside the deferred one');
    }else{
        dzn_tcv_assert($decisions===1&&$applied===1,'an event that was recorded and then left owing must gain exactly one first decision');
    }
}
echo "phase-2a2t-concurrency-verify: ".$mode." OK (settled=".$settled.", live=".$live.", dispatch_in_flight refusals=".$refusals.")\n";
