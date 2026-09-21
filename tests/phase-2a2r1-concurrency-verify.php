<?php
/** Disposable Phase-R1 concurrency verifier: consumes worker artefacts and asserts the invariant. */
if(getenv('DZN_PHASE_2A2R1_RUNTIME_TEST')!=='concurrency'||!defined('WP_CLI')||!WP_CLI||!in_array(wp_get_environment_type(),array('local','development'),true)){fwrite(STDERR,"Phase 2A.2-R1 concurrency verifier refused.\n");exit(1);}
require __DIR__.'/phase-2a2r1-fixture.php';
use Delnavazan\Platform\Core\Application\{CanonicalLessonAuthorityService,CommercialTermFundingService};
global $wpdb;$p=$wpdb->prefix.'dzn_';
$mode=(string)getenv('DZN_PHASE_2A2R1_MODE');
$gate=(string)getenv('DZN_PHASE_2A2R1_GATE_DIR');
$state=get_option('dzn_phase_2a2r1_concurrency');
dzn_r1_fix_assert(is_array($state)&&(string)($state['mode']??'')===$mode,'Phase R1 concurrency setup required');
$worker=static function(string $name) use($gate):array{
    $path=$gate.'/'.$name.'.result';
    dzn_r1_fix_assert(is_file($path),'missing worker result artefact: '.$name);
    $decoded=json_decode((string)file_get_contents($path),true);
    dzn_r1_fix_assert(is_array($decoded),'malformed worker result artefact: '.$name);
    return $decoded;
};
$count=static function(string $table) use($wpdb,$p):int{return(int)$wpdb->get_var("SELECT COUNT(*) FROM {$p}{$table}");};
$w1=$worker('w1');$w2=$worker('w2');
if($mode==='duplicate_evidence'){
    dzn_r1_fix_assert($w1['ok']===true,'the first settlement must succeed');
    dzn_r1_fix_assert($w2['ok']===true&&($w2['outcome']['idempotent']??false)===true,'duplicate evidence must converge idempotently, not fail');
    dzn_r1_fix_assert($count('commercial_obligation_settlements')===1,'duplicate evidence must produce exactly one settlement');
    dzn_r1_fix_assert($count('commercial_payment_evidence')===1,'duplicate evidence must produce exactly one evidence row');
    dzn_r1_fix_assert($count('commercial_purchases')===1&&$count('commercial_entitlements')===1,'duplicate evidence must produce exactly one purchase and entitlement');
    dzn_r1_fix_assert((new CommercialTermFundingService())->effectiveSessions((int)$state['offer']['offer_id'])===12,'duplicate evidence must not fund twice');
}elseif($mode==='handoff_vs_schedule'){
    dzn_r1_fix_assert($w1['ok']===true,'the protected-capacity handoff must succeed');
    dzn_r1_fix_assert($w2['ok']===false&&($w2['message']??'')==='teacher_slot_conflict','a competing schedule must lose to the successor claim');
    dzn_r1_fix_assert($count('commercial_capacity_claims')===1,'exactly one successor claim must exist');
    dzn_r1_fix_assert((int)$wpdb->get_var($wpdb->prepare("SELECT interval_count FROM {$p}commercial_capacity_claims WHERE entitlement_id=%d",(int)$state['entitlement_id']))===12,'the successor must protect every committed interval');
    dzn_r1_fix_assert((string)$wpdb->get_var($wpdb->prepare("SELECT state FROM {$p}canonical_continuation_reservations WHERE id=%d",(int)$state['scenario']['reservation_id']))==='released','the predecessor hold must be released once the successor is durable');
}elseif($mode==='settlement_vs_lesson_seven'){
    dzn_r1_fix_assert($w1['ok']===true,'the second-tranche settlement must succeed');
    dzn_r1_fix_assert($w2['ok']===false&&($w2['message']??'')==='standard_funding_exhausted','the seventh Lesson must be impossible while its tranche is uncommitted');
    dzn_r1_fix_assert((int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$p}lessons WHERE term_id=%d AND canonical_sequence=7",(int)$state['term_id']))===0,'no unpaid seventh Lesson may exist');
    dzn_r1_fix_assert((new CommercialTermFundingService())->standardAllowanceForTerm((int)$state['term_id'])===12,'the committed settlement must fund twelve sessions');
    wp_set_current_user(1);
    (new CanonicalLessonAuthorityService())->createStandard((int)$state['term_id'],(int)$state['assignment_id'],dzn_r1_fix_evidence('after-race-seven'),dzn_r1_fix_key('after-race-seven'));
    dzn_r1_fix_assert((int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$p}lessons WHERE term_id=%d AND canonical_sequence=7",(int)$state['term_id']))===1,'the seventh Lesson must be materialisable after the committed settlement');
}elseif($mode==='promotion_global_limit'){
    dzn_r1_fix_assert($w1['ok']===true,'the first acceptance must succeed');
    dzn_r1_fix_assert($w2['ok']===false&&str_contains((string)($w2['message']??''),'promotion_usage_limit_reached'),'the second acceptance must fail closed on the global redemption limit');
    dzn_r1_fix_assert((int)$wpdb->get_var("SELECT COUNT(*) FROM {$p}commercial_promotion_redemptions")===1,'a global promotion maximum must never be exceeded under concurrency');
    dzn_r1_fix_assert((int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$p}commercial_purchases WHERE offer_id=%d",(int)$state['offer_b']['offer_id']))===0,'the refused acceptance must not create a purchase');
    dzn_r1_fix_assert((int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$p}commercial_purchases WHERE offer_id=%d",(int)$state['offer_a']['offer_id']))===1,'the winning acceptance must create exactly one purchase');
}elseif($mode==='conflicting_evidence_replay'){
    dzn_r1_fix_assert($w1['ok']===true,'the original evidence must settle');
    dzn_r1_fix_assert($w2['ok']===true&&($w2['outcome']['conflicting']??false)===true,'a concurrent conflicting replay must be reported as conflicting');
    dzn_r1_fix_assert((int)$wpdb->get_var("SELECT COUNT(*) FROM {$p}commercial_obligation_settlements")===1,'a conflicting replay must never manufacture a second settlement');
    dzn_r1_fix_assert((int)$wpdb->get_var("SELECT COUNT(*) FROM {$p}commercial_payment_evidence")===1,'a conflicting replay must never rewrite or duplicate the original evidence');
    dzn_r1_fix_assert((int)$wpdb->get_var("SELECT COUNT(*) FROM {$p}commercial_exceptions WHERE state='open'")>=1,'a conflicting replay must be routed into durable review');
}elseif($mode==='release_vs_satisfaction'){
    dzn_r1_fix_assert($w1['ok']===true,'the authorised claim release must succeed');
    dzn_r1_fix_assert($w2['ok']===true,'a schedule after an authorised release must succeed');
    dzn_r1_fix_assert((string)$wpdb->get_var($wpdb->prepare("SELECT state FROM {$p}commercial_capacity_claims WHERE id=%d",(int)$state['claim_id']))==='released','the claim must remain released');
    dzn_r1_fix_assert((int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$p}commercial_capacity_claim_intervals WHERE claim_id=%d AND state='protected'",(int)$state['claim_id']))===0,'no protected interval may survive the release');
    dzn_r1_fix_assert((int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$p}canonical_lesson_schedule_versions WHERE lesson_id=%d AND applicable_slot=1",(int)$state['lesson_id']))===1,'the released interval must be reusable by the surviving schedule');
}elseif($mode==='unrelated_commitments'){
    dzn_r1_fix_assert($w1['ok']===true&&$w2['ok']===true,'independent commitments must both establish their capacity');
    dzn_r1_fix_assert($count('commercial_capacity_claims')===2,'each commitment must own exactly one claim');
    foreach(array((int)$state['scenario_a']['reservation_id'],(int)$state['scenario_b']['reservation_id']) as $reservationId)dzn_r1_fix_assert((string)$wpdb->get_var($wpdb->prepare("SELECT state FROM {$p}canonical_continuation_reservations WHERE id=%d",$reservationId))==='released','each predecessor hold must be released');
}
echo "Phase 2A.2-R1 concurrency verified: ".$mode."\n";
