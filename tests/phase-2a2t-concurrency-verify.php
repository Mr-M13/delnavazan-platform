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
echo "phase-2a2t-concurrency-verify: ".$mode." OK (settled=".$settled.", live=".$live.", dispatch_in_flight refusals=".$refusals.")\n";
