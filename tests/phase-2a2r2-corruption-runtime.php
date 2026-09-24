<?php
/**
 * Disposable Phase-R2 corruption proof.
 *
 * A corrupted recurring aggregate — frozen currency or collection mode, derived boundary or guarantee
 * window, recovery/lapse representation, cross-Term protection ownership, refund evidence — must fail
 * every read closed. A corrupted digest-only command row must fail every replay closed. Nothing is
 * ever silently repaired: the stored corruption survives the refused read, and restoring the exact
 * value converges again.
 */
if(getenv('DZN_PHASE_2A2R2_RUNTIME_TEST')!=='corruption'||!defined('WP_CLI')||!WP_CLI||!in_array(wp_get_environment_type(),array('local','development'),true)){fwrite(STDERR,"Phase 2A.2-R2 corruption runtime refused.\n");exit(1);}
require __DIR__.'/phase-2a2r1-fixture.php';
require __DIR__.'/phase-2a2r2-fixture.php';
use Delnavazan\Platform\Core\Application\{CollectionIntentService,RecurringEnrolmentService,RecurringProtectionService,RecoveryService,RefundReviewService,RenewalCycleService};
use Delnavazan\Platform\Core\Application\{CollectionReadService,RecoveryReadService,RecurringEnrolmentReadService,RecurringProtectionReadService,RefundReviewReadService,RenewalCycleReadService};
global $wpdb;$p=$wpdb->prefix.'dzn_';

$fixture=get_option('dzn_phase_2a2j_fixture');
dzn_r2_fix_assert(is_array($fixture)&&count($fixture['sources']??array())>=6,'Phase-J production fixture required');
dzn_r1_fix_reset(array('commercial_commands','commercial_capacity_claim_intervals','commercial_capacity_claims','commercial_recurring_patterns','commercial_obligation_settlements','commercial_payment_facts','commercial_payment_evidence','commercial_term_funding_plans','commercial_entitlements','commercial_purchases','commercial_offer_obligations','commercial_offer_policies','commercial_offer_adjustments','commercial_offers','commercial_promotion_redemptions','commercial_account_adjustment_events','commercial_account_adjustments','commercial_promotions','commercial_prices','commercial_products','commercial_policies','commercial_account_roots','commercial_exceptions','canonical_continuation_commands','canonical_continuation_interventions','canonical_continuation_reservations','canonical_continuation_decisions','canonical_continuation_cases','canonical_continuation_slot_authorities'));
dzn_r2_fix_reset();
wp_set_current_user(1);
dzn_r1_fix_availability($fixture['teachers']??array());

$funded=dzn_r2_fix_funded_enrolment($fixture['sources'][0],'corruption-a',1);
// A fixed key and a fixed evidence reference make the digest-only command row replayable, so the
// command-row corruption proof below is a real replay rather than a structural inspection.
$establishKey='corruption-establish-key';
$establishInput=array('enrolment_id'=>$funded['enrolment_id'],'collection_mode'=>'manual','evidence_channel'=>'staff_record','evidence_reference'=>'corruption-establish','evidence_at'=>gmdate('Y-m-d H:i:s'));
$recurringId=(int)(new RecurringEnrolmentService())->establish($establishInput,$establishKey)['recurring_enrolment_id'];
$cycle=dzn_r2_fix_cycle($recurringId,$funded['term_id'],'corruption-a');
$cycleId=(int)$cycle['cycle_id'];
(new RenewalCycleService())->activateManualGuarantee($cycleId,dzn_r2_fix_evidence('g'),dzn_r2_fix_key('g'));
(new RenewalCycleService())->requirePayment($cycleId,dzn_r2_fix_evidence('r'),dzn_r2_fix_key('r'));
$intentId=(int)(new CollectionIntentService())->openManualPaymentRequired($cycleId,array('obligation_id'=>$funded['obligation_id'],'evidence_channel'=>'staff_record','evidence_reference'=>'i','evidence_at'=>gmdate('Y-m-d H:i:s')),dzn_r2_fix_key('i'))['collection_intent_id'];
(new CollectionIntentService())->submit($intentId,dzn_r2_fix_evidence('s'),dzn_r2_fix_key('s'));
(new CollectionIntentService())->recordFailure($intentId,array('failure_reason_code'=>'declined','evidence_channel'=>'staff_record','evidence_reference'=>'f','evidence_at'=>gmdate('Y-m-d H:i:s')),dzn_r2_fix_key('f'));
$recoveryId=(int)(new RecoveryService())->openRecovery($intentId,dzn_r2_fix_evidence('rc'),dzn_r2_fix_key('rc'))['recovery_case_id'];
$purchaseId=(int)$wpdb->get_var($wpdb->prepare("SELECT purchase_id FROM {$p}commercial_entitlements WHERE id=%d",(int)$funded['entitlement_id']));
$evidenceId=(int)$wpdb->get_var($wpdb->prepare("SELECT id FROM {$p}commercial_payment_evidence WHERE obligation_id=%d ORDER BY id LIMIT 1",$funded['obligation_id']));
$refundId=(int)(new RefundReviewService())->recordRefundEvidence(array('purchase_id'=>$purchaseId,'obligation_id'=>$funded['obligation_id'],'evidence_id'=>$evidenceId,'kind'=>'refund','amount_minor'=>25000,'currency'=>'AUD','evidence_channel'=>'staff_record','evidence_reference'=>'rf','evidence_at'=>gmdate('Y-m-d H:i:s')),dzn_r2_fix_key('rf'))['refund_review_id'];
$protectionId=(int)(new RecurringProtectionService())->establishProtection($cycleId,(int)$funded['claim_id'],dzn_r2_fix_evidence('p'),dzn_r2_fix_key('p'))['recurring_protection_id'];

/** A corrupted aggregate must be refused, must not be repaired, and must converge once restored. */
$probe=static function(string $table,int $id,string $column,mixed $corrupt,mixed $restore,string $reason,callable $read,string $message) use($wpdb,$p):void{
    dzn_r2_fix_corrupt($table,$id,$column,$corrupt);
    $observed=dzn_r2_fix_refused($read,$message);
    dzn_r2_fix_assert($observed===$reason,$message.' must fail closed with '.$reason.', saw '.$observed);
    dzn_r2_fix_assert((string)dzn_r2_fix_column($table,$id,$column)===(string)$corrupt,$message.' must never silently repair the stored value');
    dzn_r2_fix_corrupt($table,$id,$column,$restore);
    dzn_r2_fix_accepted($read,$message.' after restoration');
};

// 1. Frozen Recurring Enrolment facts: currency, collection mode, aggregate version.
$probe('recurring_enrolments',$recurringId,'currency','ZZZ','AUD','recurring_enrolment_integrity_conflict',fn()=>(new RecurringEnrolmentReadService())->one($recurringId),'a corrupted frozen currency');
$probe('recurring_enrolments',$recurringId,'collection_mode','bogus','manual','recurring_enrolment_integrity_conflict',fn()=>(new RecurringEnrolmentReadService())->one($recurringId),'a corrupted collection mode');
$probe('recurring_enrolments',$recurringId,'state','bogus','active','recurring_enrolment_integrity_conflict',fn()=>(new RecurringEnrolmentReadService())->one($recurringId),'a corrupted recurring state');
$probe('recurring_enrolments',$recurringId,'rule_version','other_v9','recurring_enrolment_v1','recurring_enrolment_integrity_conflict',fn()=>(new RecurringEnrolmentReadService())->one($recurringId),'an unknown rule version');
$probe('recurring_enrolments',$recurringId,'recurring_enrolment_version','0','2','recurring_enrolment_integrity_conflict',fn()=>(new RecurringEnrolmentReadService())->one($recurringId),'a zero aggregate version');

// 2. Renewal Cycle: frozen mode, price snapshot, derived boundary and guarantee window.
$probe('renewal_cycles',$cycleId,'currency','ZZZ','AUD','renewal_cycle_integrity_conflict',fn()=>(new RenewalCycleReadService())->one($cycleId),'a corrupted cycle currency');
$probe('renewal_cycles',$cycleId,'collection_mode','bogus','manual','renewal_cycle_integrity_conflict',fn()=>(new RenewalCycleReadService())->one($cycleId),'a corrupted frozen cycle mode');
$probe('renewal_cycles',$cycleId,'sequence','0','1','renewal_cycle_integrity_conflict',fn()=>(new RenewalCycleReadService())->one($cycleId),'a corrupted cycle sequence');
$probe('renewal_cycles',$cycleId,'next_term_id','0',null,'renewal_cycle_integrity_conflict',fn()=>(new RenewalCycleReadService())->one($cycleId),'a corrupted next-Term identity');
$probe('renewal_cycles',$cycleId,'boundary_derived_at','bogus','2026-01-01 00:00:00','renewal_cycle_integrity_conflict',fn()=>(new RenewalCycleReadService())->one($cycleId),'a corrupted next-Term boundary');
$probe('renewal_cycles',$cycleId,'guarantee_deadline_at','bogus',null,'renewal_cycle_integrity_conflict',fn()=>(new RenewalCycleReadService())->one($cycleId),'a corrupted guarantee window');
$probe('renewal_cycles',$cycleId,'state','bogus','payment_required','renewal_cycle_integrity_conflict',fn()=>(new RenewalCycleReadService())->one($cycleId),'a corrupted cycle state');
$probe('renewal_cycles',$cycleId,'source_term_id','0',(string)$funded['term_id'],'renewal_cycle_integrity_conflict',fn()=>(new RenewalCycleReadService())->one($cycleId),'a missing source Term');

// 3. Collection Intent: provider-neutral kind, null-state and controlled failure reason.
$probe('collection_intents',$intentId,'kind','bogus','manual_payment_required','collection_intent_integrity_conflict',fn()=>(new CollectionReadService())->one($intentId),'a corrupted collection kind');
$probe('collection_intents',$intentId,'state','bogus','failed','collection_intent_integrity_conflict',fn()=>(new CollectionReadService())->one($intentId),'a corrupted collection state');
$probe('collection_intents',$intentId,'charge_at','bogus',null,'collection_intent_integrity_conflict',fn()=>(new CollectionReadService())->one($intentId),'a corrupted charge instant');
$probe('collection_intents',$intentId,'failure_reason_code','Not A Reason',null,'collection_intent_integrity_conflict',fn()=>(new CollectionReadService())->one($intentId),'an uncontrolled failure reason');

// 4. Recovery Case representation and ownership.
$probe('recovery_cases',$recoveryId,'state','bogus','open','recovery_case_integrity_conflict',fn()=>(new RecoveryReadService())->one($recoveryId),'a corrupted recovery state');
$probe('recovery_cases',$recoveryId,'renewal_cycle_id','0',(string)$cycleId,'recovery_case_integrity_conflict',fn()=>(new RecoveryReadService())->one($recoveryId),'a recovery case detached from its cycle');
$probe('recovery_cases',$recoveryId,'collection_intent_id','0',(string)$intentId,'recovery_case_integrity_conflict',fn()=>(new RecoveryReadService())->one($recoveryId),'a recovery case detached from its collection intent');

// 5. Refund/reversal evidence and the unresolved academic-consequence seam.
$probe('refund_review_cases',$refundId,'kind','bogus','refund','refund_review_integrity_conflict',fn()=>(new RefundReviewReadService())->one($refundId),'a corrupted refund kind');
$probe('refund_review_cases',$refundId,'state','bogus','open','refund_review_integrity_conflict',fn()=>(new RefundReviewReadService())->one($refundId),'a corrupted refund state');
$probe('refund_review_cases',$refundId,'currency','ZZZ','AUD','refund_review_integrity_conflict',fn()=>(new RefundReviewReadService())->one($refundId),'a corrupted refund currency');
// R2 never records an academic consequence: a stored value is corruption, and it still reports unresolved.
$probe('refund_review_cases',$refundId,'academic_consequence','clawback',null,'refund_review_integrity_conflict',fn()=>(new RefundReviewReadService())->one($refundId),'an invented academic consequence');
dzn_r2_fix_assert((new RefundReviewReadService())->one($refundId)['academic_consequence_state']==='unresolved','the restored refund review must report the unresolved academic consequence');

// 6. Continuous cross-Term protection and its R1 claim ownership.
$probe('recurring_protections',$protectionId,'state','bogus','active','recurring_protection_integrity_conflict',fn()=>(new RecurringProtectionReadService())->one($protectionId),'a corrupted protection state');
$probe('recurring_protections',$protectionId,'claim_id','0',(string)$funded['claim_id'],'recurring_protection_integrity_conflict',fn()=>(new RecurringProtectionReadService())->one($protectionId),'a protection detached from its R1 claim');
$probe('recurring_protections',$protectionId,'renewal_cycle_id','0',(string)$cycleId,'recurring_protection_integrity_conflict',fn()=>(new RecurringProtectionReadService())->one($protectionId),'a protection detached from its cycle');

// 7. Digest-only command rows: an altered payload digest, operation or recorded result must fail every
//    replay closed, and the unchanged key must converge again once the row is restored exactly.
$commandTable=$p.'recurring_enrolment_commands';
$command=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$commandTable} WHERE operation='establish' AND recurring_enrolment_id=%d",$recurringId));
dzn_r2_fix_assert($command!==null,'the establishment command row is required');
$commandId=(int)$command->id;
$originalPayload=(string)$command->command_payload_digest;
$originalOperation=(string)$command->operation;
$originalState=(string)$command->result_state;
dzn_r2_fix_assert(strlen((string)$command->command_key_digest)===64&&(string)$command->command_domain==='recurring_v1','a command row must stay domain-bound and digest-only');
$replayEstablish=fn()=>(new RecurringEnrolmentService())->establish($establishInput,$establishKey);
// An unchanged successful command must replay idempotently.
$replayed=$replayEstablish();
dzn_r2_fix_assert($replayed['idempotent']===true&&(int)$replayed['recurring_enrolment_id']===$recurringId,'an unchanged successful establishment must replay idempotently');

dzn_r2_fix_corrupt('recurring_enrolment_commands',$commandId,'command_payload_digest',str_repeat('f',64));
dzn_r2_fix_rejected($replayEstablish,'Idempotency conflict','a replay against an altered payload digest');
dzn_r2_fix_assert((string)dzn_r2_fix_column('recurring_enrolment_commands',$commandId,'command_payload_digest')===str_repeat('f',64),'a refused replay must never silently repair the stored digest');
dzn_r2_fix_corrupt('recurring_enrolment_commands',$commandId,'command_payload_digest',$originalPayload);
dzn_r2_fix_assert($replayEstablish()['idempotent']===true,'the restored command row must converge on replay');

dzn_r2_fix_corrupt('recurring_enrolment_commands',$commandId,'operation','withdrawn_operation');
dzn_r2_fix_rejected($replayEstablish,'Contaminated recurring enrolment command','a replay against an altered operation');
dzn_r2_fix_corrupt('recurring_enrolment_commands',$commandId,'operation',$originalOperation);

dzn_r2_fix_corrupt('recurring_enrolment_commands',$commandId,'result_state','bogus');
dzn_r2_fix_rejected($replayEstablish,'Contaminated recurring enrolment result','a replay against an altered recorded result');
dzn_r2_fix_corrupt('recurring_enrolment_commands',$commandId,'result_state',$originalState);
dzn_r2_fix_assert((string)dzn_r2_fix_column('recurring_enrolment_commands',$commandId,'operation')===$originalOperation&&(string)dzn_r2_fix_column('recurring_enrolment_commands',$commandId,'result_state')===$originalState&&(string)dzn_r2_fix_column('recurring_enrolment_commands',$commandId,'command_payload_digest')===$originalPayload,'the command row must be restored exactly');

// A command row for another domain or another aggregate must never be adopted.
dzn_r2_fix_corrupt('recurring_enrolment_commands',$commandId,'command_domain','commercial_v1');
dzn_r2_fix_rejected($replayEstablish,'Contaminated recurring enrolment command','a replay against a foreign command domain');
dzn_r2_fix_corrupt('recurring_enrolment_commands',$commandId,'command_domain','recurring_v1');
dzn_r2_fix_assert($replayEstablish()['idempotent']===true,'the restored domain must converge on replay');

// 8. No aggregate read may write: history and command counts are unchanged after every refused read.
dzn_r2_fix_assert(dzn_r2_fix_count('recurring_enrolment_events','recurring_enrolment_id',$recurringId)===1,'a refused read or replay must never append recurring history');
dzn_r2_fix_assert(dzn_r2_fix_count('renewal_cycle_events','renewal_cycle_id',$cycleId)===3,'a refused read must never append cycle history');
dzn_r2_fix_assert(dzn_r2_fix_count('recurring_enrolment_commands','recurring_enrolment_id',$recurringId)===1,'a refused replay must never append a command');
foreach(array('recurring_enrolments','renewal_cycles','collection_intents','recovery_cases','refund_review_cases','recurring_protections') as $table)dzn_r2_fix_assert(dzn_r2_fix_count($table)>0,'the disposable aggregate must exist: '.$table);

echo "Phase 2A.2-R2 corruption runtime passed\n";
