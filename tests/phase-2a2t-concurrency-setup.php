<?php
/** Disposable Phase-T concurrency pre-state builder. Synthetic local data only. */
if(getenv('DZN_PHASE_2A2T_RUNTIME_TEST')!=='concurrency'||!defined('WP_CLI')||!WP_CLI||!in_array(wp_get_environment_type(),array('local','development'),true)){fwrite(STDERR,"Phase 2A.2-T concurrency setup refused.\n");exit(1);}
require __DIR__.'/phase-2a2r1-fixture.php';
require __DIR__.'/phase-2a2r2-fixture.php';
use Delnavazan\Platform\Core\Application\{CollectionIntentService,RenewalCycleService};
use Delnavazan\Platform\Core\Application\PaymentExecution\{PaymentExecutionSupport,PaymentProviderAccountService,PaymentProviderObjectService,PaymentProviderRegistry,ProviderReferenceClaims};
use Delnavazan\Platform\Integrations\Payment\ContractPaymentAdapter;
global $wpdb;$p=$wpdb->prefix.'dzn_';
function dzn_tcs_assert(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);}
function dzn_tcs_key(string $label):string{return 'dzn-2a2tc-'.$label.'-'.wp_generate_uuid4();}
function dzn_tcs_evidence(string $label):array{return array('evidence_channel'=>'staff_record','evidence_reference'=>'2a2tc-'.$label.'-'.wp_generate_uuid4(),'evidence_at'=>gmdate('Y-m-d H:i:s'));}
$mode=(string)getenv('DZN_PHASE_2A2T_MODE');
$fixture=get_option('dzn_phase_2a2j_fixture');
dzn_tcs_assert(is_array($fixture)&&count($fixture['sources']??array())>=3,'Phase-J production fixture required');
dzn_r1_fix_reset(array('commercial_commands','commercial_capacity_claim_intervals','commercial_capacity_claims','commercial_recurring_patterns','commercial_obligation_settlements','commercial_payment_facts','commercial_payment_evidence','commercial_term_funding_plans','commercial_entitlements','commercial_purchases','commercial_offer_obligations','commercial_offer_policies','commercial_offer_adjustments','commercial_offers','commercial_promotion_redemptions','commercial_account_adjustment_events','commercial_account_adjustments','commercial_promotions','commercial_prices','commercial_products','commercial_policies','commercial_account_roots','commercial_exceptions','canonical_continuation_commands','canonical_continuation_interventions','canonical_continuation_reservations','canonical_continuation_decisions','canonical_continuation_cases','canonical_continuation_slot_authorities'));
dzn_r2_fix_reset();
foreach(array('payment_provider_secret_events','payment_provider_event_decisions','payment_provider_events','payment_provider_event_receipts','payment_execution_dispatches','payment_execution_results','payment_execution_attempts','payment_execution_commands','payment_provider_secrets','payment_provider_object_commands','payment_provider_object_events','payment_provider_objects','payment_provider_account_commands','payment_provider_account_events','payment_provider_accounts') as $table)
    dzn_tcs_assert($wpdb->query("DELETE FROM {$p}{$table}")!==false,'Failed to reset disposable Phase T storage: '.$table);
wp_set_current_user(1);
dzn_r1_fix_availability($fixture['teachers']??array());
$fixtures=array();
foreach(array(0,1) as $index){
    $courseId=(int)$wpdb->get_var($wpdb->prepare("SELECT course_id FROM {$p}commercial_products WHERE id=%d",(int)dzn_r2_fix_funded_enrolment($fixture['sources'][$index],$mode,$index+1)['product_id']));
    $product=dzn_r1_fix_product($courseId>0?$courseId:(int)$wpdb->get_var("SELECT id FROM {$p}courses ORDER BY id LIMIT 1"),'AU',25000,$mode.'-'.$index);
    $scenario=dzn_r1_fix_scenario($fixture['sources'][$index+1],$mode.'-'.$index,$index+3);
    dzn_r1_fix_activate_enrolment((int)$scenario['enrolment_id'],$mode.'-'.$index);
    dzn_r1_fix_pattern($scenario,$mode.'-'.$index);
    $offer=dzn_r1_fix_offer($scenario,$product,'two_instalments',$mode.'-'.$index);
    dzn_r1_fix_settle($offer,1,'2a2tc-'.$index);
    $obligation=dzn_r1_fix_obligation($offer,2);
    $recurringId=dzn_r2_fix_establish((int)$scenario['enrolment_id'],$mode.'-'.$index);
    $termId=(int)$wpdb->get_var($wpdb->prepare("SELECT id FROM {$p}terms WHERE enrolment_id=%d ORDER BY id LIMIT 1",(int)$scenario['enrolment_id']));
    $cycle=dzn_r2_fix_cycle($recurringId,$termId,$mode.'-'.$index);
    $cycleId=(int)$cycle['cycle_id'];
    (new RenewalCycleService())->requirePayment($cycleId,dzn_tcs_evidence('require-'.$index),dzn_tcs_key('require-'.$index));
    $intent=new CollectionIntentService();
    $opened=$intent->openManualPaymentRequired($cycleId,array('obligation_id'=>(int)$obligation['obligation_id'])+dzn_tcs_evidence('open-'.$index),dzn_tcs_key('open-'.$index));
    $intentId=(int)$opened['collection_intent_id'];
    $intent->submit($intentId,dzn_tcs_evidence('submit-'.$index),dzn_tcs_key('submit-'.$index));
    $fixtures[]=array('obligation_id'=>(int)$obligation['obligation_id'],'intent_id'=>$intentId,'cycle_id'=>$cycleId,'student_id'=>(int)$wpdb->get_var($wpdb->prepare("SELECT beneficiary_student_id FROM {$p}commercial_offers WHERE id=%d",(int)$offer['offer_id'])));
}
$accounts=new PaymentProviderAccountService();
$account=$accounts->register(array('provider_key'=>'stripe','mode'=>'test','reference_code'=>'conc-'.wp_generate_uuid4(),'account_reference'=>'acct-conc','execution_state'=>'enabled','credential_state'=>'configured')+dzn_tcs_evidence('account'),dzn_tcs_key('account'));
$accountId=(int)$account['provider_account_id'];
$wpdb->update($p.'payment_provider_accounts',array('account_reference_digest'=>hash_hmac('sha256','payment_execution_reference:acct-conc',wp_salt('dzn_payment_execution'))),array('id'=>$accountId));
$objects=new PaymentProviderObjectService();
foreach($fixtures as $index=>$row){
    $references=array('student'=>'cus-'.$index,'obligation'=>'pi-'.$index,'collection_intent'=>'pii-'.$index);
    foreach(array('student'=>$row['student_id'],'obligation'=>$row['obligation_id'],'collection_intent'=>$row['intent_id']) as $canonicalKind=>$canonicalId)
        $objects->link(array('provider_account_id'=>$accountId,'object_kind'=>'intent','canonical_kind'=>$canonicalKind,'canonical_id'=>$canonicalId,'object_reference'=>$references[$canonicalKind])+dzn_tcs_evidence('link-'.$index.'-'.$canonicalKind),dzn_tcs_key('link-'.$index.'-'.$canonicalKind));
    $fixtures[$index]['references']=$references;
}
PaymentProviderRegistry::registerExecutionPort(new ContractPaymentAdapter());
update_option('dzn_phase_2a2t_concurrency_fixture',array('mode'=>$mode,'account_id'=>$accountId,'rows'=>$fixtures),false);
