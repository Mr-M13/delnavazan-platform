<?php
/** Disposable Phase-R1 corruption proof: a corrupted commercial aggregate must fail every consumer closed. */
if(getenv('DZN_PHASE_2A2R1_RUNTIME_TEST')!=='corruption'||!defined('WP_CLI')||!WP_CLI||!in_array(wp_get_environment_type(),array('local','development'),true)){fwrite(STDERR,"Phase 2A.2-R1 corruption runtime refused.\n");exit(1);}
require __DIR__.'/phase-2a2r1-fixture.php';
global $wpdb;$p=$wpdb->prefix.'dzn_';
$fixture=get_option('dzn_phase_2a2j_fixture');
dzn_r1_fix_assert(is_array($fixture)&&count($fixture['sources']??array())>=6,'Phase-J production fixture required');
$sources=$fixture['sources'];
dzn_r1_fix_reset(array('commercial_commands','commercial_capacity_claim_intervals','commercial_capacity_claims','commercial_recurring_patterns','commercial_obligation_settlements','commercial_payment_facts','commercial_payment_evidence','commercial_term_funding_plans','commercial_entitlements','commercial_purchases','commercial_offer_obligations','commercial_offer_policies','commercial_offer_adjustments','commercial_offers','commercial_promotion_redemptions','commercial_account_adjustment_events','commercial_account_adjustments','commercial_promotions','commercial_prices','commercial_products','commercial_policies','commercial_account_roots','commercial_exceptions','canonical_continuation_commands','canonical_continuation_interventions','canonical_continuation_reservations','canonical_continuation_decisions','canonical_continuation_cases','canonical_continuation_slot_authorities'));
wp_set_current_user(1);
dzn_r1_fix_availability($fixture['teachers']??array());
$rejected=static function(callable $call,string $expected,string $message):void{$caught=null;try{$call();}catch(Throwable$e){$caught=$e;}dzn_r1_fix_assert($caught!==null,$message.' was accepted');dzn_r1_fix_assert($caught->getMessage()===$expected,$message.' failed with an unexpected error: '.$caught->getMessage());};

$a=dzn_r1_fix_scenario($sources[0],'corruption-a',1);
$productA=dzn_r1_fix_product((int)$a['course_id'],'AU',25000,'corruption-a');
$patternA=dzn_r1_fix_pattern($a,'corruption-a');
$offerA=dzn_r1_fix_offer($a,$productA,'full','corruption-a');
$obligationA=dzn_r1_fix_obligation($offerA,1);

// 1. Corrupted pricing snapshot: acceptance must refuse rather than settle an incoherent offer.
$wpdb->query($wpdb->prepare("UPDATE {$p}commercial_offers SET amount_due_minor=%d WHERE id=%d",(int)$offerA['amount_due_minor']+1,(int)$offerA['offer_id']));
$rejected(fn()=>dzn_r1_fix_settle($offerA,1,'prov-corrupt-offer'),'commercial_offer_integrity_conflict','acceptance against a corrupted pricing snapshot');
$wpdb->query($wpdb->prepare("UPDATE {$p}commercial_offers SET amount_due_minor=%d WHERE id=%d",(int)$offerA['amount_due_minor'],(int)$offerA['offer_id']));
$settled=dzn_r1_fix_settle($offerA,1,'prov-corruption-1');
dzn_r1_fix_assert($settled['processing_state']==='accepted','the repaired offer must settle normally');

// 2. Corrupted settlement: the funding derivation and the canonical Lesson guard must fail closed.
$settlementId=(int)$wpdb->get_var($wpdb->prepare("SELECT id FROM {$p}commercial_obligation_settlements WHERE obligation_id=%d",(int)$obligationA['obligation_id']));
dzn_r1_fix_assert($settlementId>0,'the settlement row must exist');
$wpdb->query($wpdb->prepare("UPDATE {$p}commercial_obligation_settlements SET amount_minor=%d WHERE id=%d",(int)$obligationA['amount_minor']-1,$settlementId));
$rejected(fn()=>(new \Delnavazan\Platform\Core\Application\CommercialTermFundingService())->effectiveSessions((int)$offerA['offer_id']),'commercial_funding_integrity_conflict','the funded allowance from a corrupted settlement');
$wpdb->query($wpdb->prepare("UPDATE {$p}commercial_obligation_settlements SET amount_minor=%d WHERE id=%d",(int)$obligationA['amount_minor'],$settlementId));
dzn_r1_fix_activate_enrolment((int)$a['enrolment_id'],'corruption-a');
$entitlementA=dzn_r1_fix_entitlement((int)$offerA['offer_id']);
$handoffA=dzn_r1_fix_handoff($entitlementA,'corruption-a');
$bindingA=dzn_r1_fix_bind($entitlementA,'corruption-a');
dzn_r1_fix_assert((new \Delnavazan\Platform\Core\Application\CommercialTermFundingService())->standardAllowanceForTerm((int)$bindingA['term_id'])===12,'the repaired settlement must fund twelve sessions');

// 3. Corrupted protected interval: capacity arbitration must refuse instead of silently approving.
$intervalId=(int)$wpdb->get_var($wpdb->prepare("SELECT id FROM {$p}commercial_capacity_claim_intervals WHERE claim_id=%d AND interval_sequence=1",(int)$handoffA['claim_id']));
$intervalRow=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$p}commercial_capacity_claim_intervals WHERE id=%d",$intervalId));
$wpdb->query($wpdb->prepare("UPDATE {$p}commercial_capacity_claim_intervals SET state='bogus' WHERE id=%d",$intervalId));
$rejected(fn()=>\Delnavazan\Platform\Core\Application\CommercialCapacityAuthority::assertNoConflictingClaim((int)$a['teacher_id'],(string)$intervalRow->starts_at_utc,(string)$intervalRow->occupied_ends_at_utc),'commercial_capacity_integrity_conflict','capacity arbitration over a corrupted protected interval');
$wpdb->query($wpdb->prepare("UPDATE {$p}commercial_capacity_claim_intervals SET state='protected' WHERE id=%d",$intervalId));

// 4. Corrupted entitlement: binding must refuse rather than create an unauthorised Term.
$b=dzn_r1_fix_scenario($sources[1],'corruption-b',2);
$productB=dzn_r1_fix_product((int)$b['course_id'],'AU',25000,'corruption-b');
dzn_r1_fix_pattern($b,'corruption-b');
$offerB=dzn_r1_fix_offer($b,$productB,'full','corruption-b');
dzn_r1_fix_settle($offerB,1,'prov-corruption-b');
dzn_r1_fix_activate_enrolment((int)$b['enrolment_id'],'corruption-b');
$entitlementB=dzn_r1_fix_entitlement((int)$offerB['offer_id']);
$wpdb->query($wpdb->prepare("UPDATE {$p}commercial_entitlements SET state='bogus' WHERE id=%d",$entitlementB));
$rejected(fn()=>dzn_r1_fix_handoff($entitlementB,'corruption-b'),'commercial_entitlement_integrity_conflict','a corrupted entitlement');
$wpdb->query($wpdb->prepare("UPDATE {$p}commercial_entitlements SET state='issued' WHERE id=%d",$entitlementB));
$handoffB=dzn_r1_fix_handoff($entitlementB,'corruption-b');
dzn_r1_fix_assert((int)$handoffB['interval_count']===12,'the repaired entitlement must hand off normally');

// 5. Corrupted evidence: the read seam must refuse to present it as a canonical fact.
$evidenceId=(int)$wpdb->get_var($wpdb->prepare("SELECT id FROM {$p}commercial_payment_evidence WHERE obligation_id=%d",(int)$obligationA['obligation_id']));
$wpdb->query($wpdb->prepare("UPDATE {$p}commercial_payment_evidence SET processing_state='bogus' WHERE id=%d",$evidenceId));
$rejected(fn()=>(new \Delnavazan\Platform\Core\Application\CommercialPaymentService())->evidence($evidenceId),'commercial_evidence_required','reading corrupted payment evidence');
$wpdb->query($wpdb->prepare("UPDATE {$p}commercial_payment_evidence SET processing_state='accepted' WHERE id=%d",$evidenceId));
dzn_r1_fix_assert((new \Delnavazan\Platform\Core\Application\CommercialPaymentService())->evidence($evidenceId)['processing_state']==='accepted','the repaired evidence must read normally');

echo "Phase 2A.2-R1 corruption runtime passed\n";
