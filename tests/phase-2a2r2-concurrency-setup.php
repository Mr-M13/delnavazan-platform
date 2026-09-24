<?php
/** Disposable Phase-R2 concurrency pre-state builder. Synthetic local data only. */
if(getenv('DZN_PHASE_2A2R2_RUNTIME_TEST')!=='concurrency'||!defined('WP_CLI')||!WP_CLI||!in_array(wp_get_environment_type(),array('local','development'),true)){fwrite(STDERR,"Phase 2A.2-R2 concurrency setup refused.\n");exit(1);}
require __DIR__.'/phase-2a2r1-fixture.php';
require __DIR__.'/phase-2a2r2-fixture.php';
use Delnavazan\Platform\Core\Application\{CommercialTermFundingService};
global $wpdb;$p=$wpdb->prefix.'dzn_';
$mode=(string)getenv('DZN_PHASE_2A2R2_MODE');
$fixture=get_option('dzn_phase_2a2j_fixture');
dzn_r2_fix_assert(is_array($fixture)&&count($fixture['sources']??array())>=6,'Phase-J production fixture required');
dzn_r1_fix_reset(array('commercial_commands','commercial_capacity_claim_intervals','commercial_capacity_claims','commercial_recurring_patterns','commercial_obligation_settlements','commercial_payment_facts','commercial_payment_evidence','commercial_term_funding_plans','commercial_entitlements','commercial_purchases','commercial_offer_obligations','commercial_offer_policies','commercial_offer_adjustments','commercial_offers','commercial_promotion_redemptions','commercial_account_adjustment_events','commercial_account_adjustments','commercial_promotions','commercial_prices','commercial_products','commercial_policies','commercial_account_roots','commercial_exceptions','canonical_continuation_commands','canonical_continuation_interventions','canonical_continuation_reservations','canonical_continuation_decisions','canonical_continuation_cases','canonical_continuation_slot_authorities'));
dzn_r2_fix_reset();
dzn_r2_fix_assert($wpdb->query("DELETE FROM {$p}platform_outbox")!==false,'Failed to reset the intent seam');
wp_set_current_user(1);
dzn_r1_fix_availability($fixture['teachers']??array());

$state=array('mode'=>$mode);
$duo=function(string $mode) use($fixture,$state):array{
    $fundedA=dzn_r2_fix_funded_enrolment($fixture['sources'][0],$mode.'-a',1);
    $recurringA=dzn_r2_fix_establish($fundedA['enrolment_id'],$mode.'-a');
    $fundedB=dzn_r2_fix_funded_enrolment($fixture['sources'][1],$mode.'-b',2);
    $recurringB=dzn_r2_fix_establish($fundedB['enrolment_id'],$mode.'-b');
    return array('funded_a'=>$fundedA,'recurring_a'=>$recurringA,'funded_b'=>$fundedB,'recurring_b'=>$recurringB);
};

if($mode==='renewal_vs_schedule'){
    // A funded recurring enrolment whose cycle must be guaranteed while a competing Phase-N schedule
    // for the same Teacher root is attempted.
    $pair=$duo($mode);
    $free=dzn_r1_fix_free_lesson($fixture['sources'][2],'race-schedule');
    $target=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$p}canonical_lesson_schedule_versions version JOIN {$p}lessons lesson ON lesson.id=version.lesson_id WHERE lesson.term_id=%d AND version.applicable_slot=1 ORDER BY version.id LIMIT 1",(int)$free['term_id']));
    $state=array_merge($state,array('funded'=>$pair['funded_a'],'recurring'=>$pair['recurring_a'],'free'=>$free,'target'=>array('local_wall_date'=>(string)$target->local_wall_date,'local_wall_time'=>(string)$target->local_wall_time,'schedule_timezone'=>(string)$target->schedule_timezone)));
}elseif($mode==='guarantee_vs_close'){
    $pair=$duo($mode);
    $state=array_merge($state,array('funded'=>$pair['funded_a'],'recurring'=>$pair['recurring_a']));
}elseif($mode==='recovery_vs_satisfaction'){
    $pair=$duo($mode);
    $cycle=(new \Delnavazan\Platform\Core\Application\RenewalCycleService())->openCycle($pair['recurring_a'],array('source_term_id'=>$pair['funded_a']['term_id'],'collection_mode'=>'manual','evidence_channel'=>'staff_record','evidence_reference'=>'race-cycle','evidence_at'=>gmdate('Y-m-d H:i:s')),dzn_r2_fix_key('race-cycle'));
    $intent=(new \Delnavazan\Platform\Core\Application\CollectionIntentService())->openManualPaymentRequired((int)$cycle['renewal_cycle_id'],array('obligation_id'=>$pair['funded_a']['obligation_id'],'evidence_channel'=>'staff_record','evidence_reference'=>'race-intent','evidence_at'=>gmdate('Y-m-d H:i:s')),dzn_r2_fix_key('race-intent'));
    (new \Delnavazan\Platform\Core\Application\CollectionIntentService())->submit((int)$intent['collection_intent_id'],dzn_r2_fix_evidence('race-submit'),dzn_r2_fix_key('race-submit'));
    (new \Delnavazan\Platform\Core\Application\CollectionIntentService())->recordFailure((int)$intent['collection_intent_id'],array('failure_reason_code'=>'declined','evidence_channel'=>'staff_record','evidence_reference'=>'race-failed','evidence_at'=>gmdate('Y-m-d H:i:s')),dzn_r2_fix_key('race-failed'));
    $recovery=(new \Delnavazan\Platform\Core\Application\RecoveryService())->openRecovery((int)$intent['collection_intent_id'],dzn_r2_fix_evidence('race-recovery'),dzn_r2_fix_key('race-recovery'));
    $free=dzn_r1_fix_free_lesson($fixture['sources'][2],'race-satisfy');
    $target=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$p}canonical_lesson_schedule_versions version JOIN {$p}lessons lesson ON lesson.id=version.lesson_id WHERE lesson.term_id=%d AND version.applicable_slot=1 ORDER BY version.id LIMIT 1",(int)$free['term_id']));
    $state=array_merge($state,array('funded'=>$pair['funded_a'],'recurring'=>$pair['recurring_a'],'cycle'=>array('cycle_id'=>(int)$cycle['renewal_cycle_id']),'intent_id'=>(int)$intent['collection_intent_id'],'recovery_id'=>(int)$recovery['recovery_case_id'],'free'=>$free,'target'=>array('local_wall_date'=>(string)$target->local_wall_date,'local_wall_time'=>(string)$target->local_wall_time,'schedule_timezone'=>(string)$target->schedule_timezone)));
}elseif($mode==='release_vs_succession'){
    $pair=$duo($mode);
    $cycle=(new \Delnavazan\Platform\Core\Application\RenewalCycleService())->openCycle($pair['recurring_a'],array('source_term_id'=>$pair['funded_a']['term_id'],'collection_mode'=>'manual','evidence_channel'=>'staff_record','evidence_reference'=>'race-cycle','evidence_at'=>gmdate('Y-m-d H:i:s')),dzn_r2_fix_key('race-cycle'));
    $protection=(new \Delnavazan\Platform\Core\Application\RecurringProtectionService())->establishProtection((int)$cycle['renewal_cycle_id'],(int)$pair['funded_a']['claim_id'],dzn_r2_fix_evidence('race-protection'),dzn_r2_fix_key('race-protection'));
    $state=array_merge($state,array('funded'=>$pair['funded_a'],'recurring'=>$pair['recurring_a'],'cycle'=>array('cycle_id'=>(int)$cycle['renewal_cycle_id']),'protection_id'=>(int)$protection['recurring_protection_id']));
}elseif($mode==='mode_change_vs_cycle'){
    $pair=$duo($mode);
    $state=array_merge($state,array('funded'=>$pair['funded_a'],'recurring'=>$pair['recurring_a']));
}elseif($mode==='refund_vs_settlement'){
    $pair=$duo($mode);
    $purchaseId=(int)$wpdb->get_var($wpdb->prepare("SELECT purchase_id FROM {$p}commercial_entitlements WHERE id=%d",(int)$pair['funded_a']['entitlement_id']));
    $evidenceId=(int)$wpdb->get_var($wpdb->prepare("SELECT id FROM {$p}commercial_payment_evidence WHERE obligation_id=%d ORDER BY id LIMIT 1",$pair['funded_a']['obligation_id']));
    $offerUid=(string)$wpdb->get_var($wpdb->prepare("SELECT offer_uid FROM {$p}commercial_offers WHERE id=%d",(int)$pair['funded_a']['offer_id']));
    $state=array_merge($state,array('funded'=>$pair['funded_a'],'recurring'=>$pair['recurring_a'],'purchase_id'=>$purchaseId,'evidence_id'=>$evidenceId,'offer_uid'=>$offerUid));
}elseif($mode==='unrelated_recurring_enrolments'){
    $pair=$duo($mode);
    $state=array_merge($state,array('funded_a'=>$pair['funded_a'],'recurring_a'=>$pair['recurring_a'],'funded_b'=>$pair['funded_b'],'recurring_b'=>$pair['recurring_b']));
}else{
    fwrite(STDERR,"Unknown Phase R2 concurrency mode: ".$mode."\n");exit(1);
}
update_option('dzn_phase_2a2r2_concurrency',$state,false);
echo "Phase 2A.2-R2 concurrency setup prepared: ".$mode."\n";
