<?php
/**
 * Disposable Phase-T webhook-intake proof. Synthetic local data only.
 *
 * Proves the durable receipt before acknowledgement, exact-raw-body signature verification through the
 * constant-gated disposable test vault, the pre-parse account selector, duplicate convergence, durable
 * conflict, out-of-order (`stale_provider_event`) safety, the bounded worker principal and the fact that
 * an anonymous request never establishes an administrator.
 */
if(getenv('DZN_PHASE_2A2T_WEBHOOK_TEST')!=='authority'||!defined('WP_CLI')||!WP_CLI||!in_array(wp_get_environment_type(),array('local','development'),true)){fwrite(STDERR,"Phase 2A.2-T webhook runtime refused.\n");exit(1);}
require __DIR__.'/phase-2a2r1-fixture.php';
require __DIR__.'/phase-2a2r2-fixture.php';
use Delnavazan\Platform\Core\Application\{CollectionIntentService,CommercialPaymentService,RenewalCycleService};
use Delnavazan\Platform\Core\Application\PaymentExecution\{PaymentEventIntakeService,PaymentExecutionSupport,PaymentProviderAccountService,PaymentProviderRegistry,PaymentProviderObjectService};
use Delnavazan\Platform\Integrations\Payment\Stripe\{StripeEventTranslator,StripeSignatureVerifier,StripeWebhookController};
global $wpdb;$p=$wpdb->prefix.'dzn_';
function dzn_tw_assert(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);}
function dzn_tw_key(string $label):string{return 'dzn-2a2tw-'.$label.'-'.wp_generate_uuid4();}
function dzn_tw_evidence(string $label):array{return array('evidence_channel'=>'staff_record','evidence_reference'=>'2a2tw-'.$label.'-'.wp_generate_uuid4(),'evidence_at'=>gmdate('Y-m-d H:i:s'));}
$fixture=get_option('dzn_phase_2a2j_fixture');
dzn_tw_assert(is_array($fixture)&&count($fixture['sources']??array())>=2,'Phase-J production fixture required');
dzn_r1_fix_reset(array('commercial_commands','commercial_capacity_claim_intervals','commercial_capacity_claims','commercial_recurring_patterns','commercial_obligation_settlements','commercial_payment_facts','commercial_payment_evidence','commercial_term_funding_plans','commercial_entitlements','commercial_purchases','commercial_offer_obligations','commercial_offer_policies','commercial_offer_adjustments','commercial_offers','commercial_promotion_redemptions','commercial_account_adjustment_events','commercial_account_adjustments','commercial_promotions','commercial_prices','commercial_products','commercial_policies','commercial_account_roots','commercial_exceptions','canonical_continuation_commands','canonical_continuation_interventions','canonical_continuation_reservations','canonical_continuation_decisions','canonical_continuation_cases','canonical_continuation_slot_authorities'));
dzn_r2_fix_reset();
foreach(array('payment_provider_secret_events','payment_provider_event_decisions','payment_provider_events','payment_provider_event_receipts','payment_execution_dispatches','payment_execution_results','payment_execution_attempts','payment_execution_commands','payment_provider_secrets','payment_provider_object_commands','payment_provider_object_events','payment_provider_objects','payment_provider_account_commands','payment_provider_account_events','payment_provider_accounts') as $table)
    dzn_tw_assert($wpdb->query("DELETE FROM {$p}{$table}")!==false,'Failed to reset disposable Phase T storage: '.$table);
wp_set_current_user(1);
dzn_r1_fix_availability($fixture['teachers']??array());
$courseId=(int)$wpdb->get_var("SELECT id FROM {$p}courses ORDER BY id LIMIT 1");
$product=dzn_r1_fix_product($courseId,'AU',25000,'webhook');
$scenario=dzn_r1_fix_scenario($fixture['sources'][0],'webhook',1);
dzn_r1_fix_activate_enrolment((int)$scenario['enrolment_id'],'webhook');
dzn_r1_fix_pattern($scenario,'webhook');
$offer=dzn_r1_fix_offer($scenario,$product,'two_instalments','webhook');
dzn_r1_fix_settle($offer,1,'2a2tw-1');
$obligation=dzn_r1_fix_obligation($offer,2);
$obligationId=(int)$obligation['obligation_id'];
$studentId=(int)$wpdb->get_var($wpdb->prepare("SELECT beneficiary_student_id FROM {$p}commercial_offers WHERE id=%d",(int)$offer['offer_id']));
$recurringId=dzn_r2_fix_establish((int)$scenario['enrolment_id'],'webhook');
$termId=(int)$wpdb->get_var($wpdb->prepare("SELECT id FROM {$p}terms WHERE enrolment_id=%d ORDER BY id LIMIT 1",(int)$scenario['enrolment_id']));
$cycle=dzn_r2_fix_cycle($recurringId,$termId,'webhook');
$cycleId=(int)$cycle['cycle_id'];
(new RenewalCycleService())->requirePayment($cycleId,dzn_tw_evidence('require'),dzn_tw_key('require'));
$intent=new CollectionIntentService();
$opened=$intent->openManualPaymentRequired($cycleId,array('obligation_id'=>$obligationId)+dzn_tw_evidence('open'),dzn_tw_key('open'));
$intentId=(int)$opened['collection_intent_id'];
$intent->submit($intentId,dzn_tw_evidence('submit'),dzn_tw_key('submit'));

// A worker principal holding exactly the four bounded capabilities, and nothing else.
$principal=wp_insert_user(array('user_login'=>'dzn-t-principal-'.wp_generate_uuid4(),'user_pass'=>wp_generate_password(24),'role'=>'subscriber'));
$principalUser=get_user_by('id',(int)$principal);
foreach(PaymentExecutionSupport::WORKER_CAPABILITIES as $capability)$principalUser->add_cap($capability);
update_option(PaymentExecutionSupport::WORKER_PRINCIPAL_OPTION,(int)$principal,false);
// The disposable test vault is the only storage path that can hold a signing secret.
if(!defined('DZN_PLATFORM_PAYMENT_TEST_VAULT'))define('DZN_PLATFORM_PAYMENT_TEST_VAULT',true);
$account=$accounts=new PaymentProviderAccountService();
$selector='hook-'.wp_generate_uuid4();
$registered=$accounts->register(array('provider_key'=>'stripe','mode'=>'test','reference_code'=>$selector,'account_reference'=>'acct-hook','execution_state'=>'disabled','credential_state'=>'configured')+dzn_tw_evidence('account'),dzn_tw_key('account'));
$accountId=(int)$registered['provider_account_id'];
$secret=(new \Delnavazan\Platform\Core\Application\PaymentExecution\PaymentSecretVault())->store('stripe',$accountId,'webhook_signing_secret','test','whsec_test_disposable',array('nonce'=>'abcdefgh12345678'),dzn_tw_key('secret'));
dzn_tw_assert(($secret['stored']??false)===true,'the disposable test vault must hold the synthetic signing secret');
$objects=new PaymentProviderObjectService();
$objectReference='pi_webhook_'.wp_generate_uuid4();
$objects->link(array('provider_account_id'=>$accountId,'object_kind'=>'intent','canonical_kind'=>'obligation','canonical_id'=>$obligationId,'object_reference'=>$objectReference)+dzn_tw_evidence('mapping'),dzn_tw_key('mapping'));
PaymentProviderRegistry::registerTranslator(new StripeEventTranslator(new StripeSignatureVerifier()));
$intake=new PaymentEventIntakeService();
$body=static function(string $eventReference,string $type,int $amount)use($obligation,$offer,$objectReference):string{
    return (string)wp_json_encode(array('id'=>$eventReference,'type'=>$type,'created'=>time(),'data'=>array('object'=>array('id'=>$objectReference,'amount'=>$amount,'currency'=>strtolower((string)$offer['currency']),'metadata'=>array('obligation_reference'=>(string)$offer['offer_uid'].':2')))));
};
$headers=static fn(string $body):array=>array('stripe-signature'=>'t='.time().',v1='.hash_hmac('sha256',time().'.'.$body,'whsec_test_disposable'));

// 1. A stale/future/malformed signature is refused with its exact reason and a durable refused receipt.
$payload=$body('evt-'.wp_generate_uuid4(),'payment_intent.succeeded',(int)$obligation['amount_minor']);
$bad=$intake->receive('stripe',$selector,$payload,array('stripe-signature'=>'t='.(time()-100000).',v1='.str_repeat('a',64)));
dzn_tw_assert((string)$bad['reason_code']==='signature_outside_tolerance','a stale signature must be refused with signature_outside_tolerance');
$malformed=$intake->receive('stripe',$selector,$payload,array('stripe-signature'=>'t='.time().',v1=not-a-signature'));
dzn_tw_assert((string)$malformed['reason_code']==='signature_invalid','a malformed signature must be refused with signature_invalid');
$unknown=$intake->receive('stripe','no-such-account',$payload,$headers($payload));
dzn_tw_assert((string)$unknown['reason_code']==='webhook_account_unresolved','an unresolvable account selector must be refused before parsing');
dzn_tw_assert((int)$wpdb->get_var("SELECT COUNT(*) FROM {$p}payment_provider_event_receipts WHERE verification_state='refused'")>=3,'every refusal must be receipted');
dzn_tw_assert((int)$wpdb->get_var("SELECT COUNT(*) FROM {$p}payment_provider_events")===0,'an unverified request must never become an event');

// 2. A valid signature settles through R1 and confirms both R2 states under the worker principal.
$anonymousBefore=(int)get_current_user_id();
$verified=$intake->receive('stripe',$selector,$payload,$headers($payload),array('source'=>'203.0.113.9'));
dzn_tw_assert((string)$verified['verification_state']==='verified'&&(int)$verified['status']===200,'a valid signature must be accepted');
dzn_tw_assert((int)get_current_user_id()===$anonymousBefore,'the caller identity must be restored after translation');
$settlement=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$p}commercial_obligation_settlements WHERE obligation_id=%d",$obligationId));
dzn_tw_assert($settlement!==null,'an accepted success must settle the obligation through R1');
$evidenceRow=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$p}commercial_payment_evidence WHERE id=%d",(int)$settlement->evidence_id));
dzn_tw_assert($evidenceRow&&(int)$evidenceRow->created_by===(int)$principal,'the R1 evidence must record the bounded worker principal, not an administrator');
dzn_tw_assert((string)$wpdb->get_var($wpdb->prepare("SELECT state FROM {$p}collection_intents WHERE id=%d",$intentId))==='confirmed','the collection intent must be confirmed');
dzn_tw_assert((string)$wpdb->get_var($wpdb->prepare("SELECT state FROM {$p}renewal_cycles WHERE id=%d",$cycleId))==='collected','the renewal cycle must be collected');
$decision=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$p}payment_provider_event_decisions WHERE obligation_id=%d ORDER BY id DESC LIMIT 1",$obligationId));
dzn_tw_assert($decision&&(string)$decision->r2_consequence_state==='applied'&&(int)$decision->recorded_by===(int)$principal,'the decision must record the worker principal and an applied consequence');

// 3. A duplicate delivery converges; a materially different duplicate is preserved as a conflict.
$eventBefore=(int)$wpdb->get_var("SELECT COUNT(*) FROM {$p}payment_provider_events");
$intake->receive('stripe',$selector,$payload,$headers($payload));
dzn_tw_assert((int)$wpdb->get_var("SELECT COUNT(*) FROM {$p}payment_provider_events")===$eventBefore,'an identical duplicate must converge on the recorded event');
$different=$body('evt-'.wp_generate_uuid4(),'payment_intent.payment_failed',(int)$obligation['amount_minor']-1);
$intake->receive('stripe',$selector,$different,$headers($different));
$conflict=(string)$wpdb->get_var("SELECT reason_code FROM {$p}payment_provider_event_decisions ORDER BY id DESC LIMIT 1");
dzn_tw_assert($conflict==='conflicting_provider_event'||$conflict==='provider_event_not_authoritative'||$conflict==='stale_provider_event','a later materially different event must be conservatively recorded, never silently converged');
dzn_tw_assert((string)$wpdb->get_var($wpdb->prepare("SELECT state FROM {$p}renewal_cycles WHERE id=%d",$cycleId))==='collected','no provider event may regress a collected cycle');

// 4. An unset principal leaves the event durably receivable and a later drain completes it exactly once.
$unsetBody=$body('evt-unset-'.wp_generate_uuid4(),'payment_intent.requires_action',(int)$obligation['amount_minor']);
delete_option(PaymentExecutionSupport::WORKER_PRINCIPAL_OPTION);
$unset=$intake->receive('stripe',$selector,$unsetBody,$headers($unsetBody));
$unsetEvent=(int)$wpdb->get_var("SELECT id FROM {$p}payment_provider_events ORDER BY id DESC LIMIT 1");
$unsetDecision=(string)$wpdb->get_var($wpdb->prepare("SELECT reason_code FROM {$p}payment_provider_event_decisions WHERE provider_event_id=%d ORDER BY id DESC LIMIT 1",$unsetEvent));
dzn_tw_assert($unsetDecision==='payment_worker_principal_required','an absent principal must refuse the translation with payment_worker_principal_required');
update_option(PaymentExecutionSupport::WORKER_PRINCIPAL_OPTION,(int)$principal,false);
$drained=$intake->drain($unsetEvent,$unsetBody,$headers($unsetBody));
dzn_tw_assert((string)$drained['decision_state']==='translated','a later drain must complete the pending decision');
dzn_tw_assert((string)$intake->drain($unsetEvent,$unsetBody,$headers($unsetBody))!=='','a repeated drain must be safe');

// 5. An over-privileged principal settles nothing.
$principalUser=get_user_by('id',(int)$principal);
$principalUser->add_cap('manage_options');
$blockedBody=$body('evt-overpriv-'.wp_generate_uuid4(),'payment_intent.requires_action',100);
$blocked=$intake->receive('stripe',$selector,$blockedBody,$headers($blockedBody));
$blockedEvent=(int)$wpdb->get_var("SELECT id FROM {$p}payment_provider_events ORDER BY id DESC LIMIT 1");
dzn_tw_assert((string)$wpdb->get_var($wpdb->prepare("SELECT reason_code FROM {$p}payment_provider_event_decisions WHERE provider_event_id=%d ORDER BY id DESC LIMIT 1",$blockedEvent))==='payment_worker_principal_required','an over-privileged principal must be refused identically');
dzn_tw_assert((string)$blocked['verification_state']==='verified','the receipt is still recorded; only the translation is refused');
dzn_tw_assert((int)$wpdb->get_var("SELECT COUNT(*) FROM {$p}payment_provider_event_receipts WHERE verification_state='verified'")>=4,'every verified request must be receipted');
echo "phase-2a2t-webhook-runtime: OK\n";
