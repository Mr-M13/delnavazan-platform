<?php
/** Phase 2A.2-R1 commercial purchase, funding & current-Term capacity authority source contract. */
$root=dirname(__DIR__);
$plugin=file_get_contents($root.'/delnavazan-platform.php');
$migration=file_get_contents($root.'/src/Core/Infrastructure/Migration/Migrator.php');
$rule=file_get_contents($root.'/src/Core/Application/CommercialRule.php');
$money=file_get_contents($root.'/src/Core/Application/CommercialMoney.php');
$validator=file_get_contents($root.'/src/Core/Application/CommercialValidator.php');
$offer=file_get_contents($root.'/src/Core/Application/CommercialOfferService.php');
$payment=file_get_contents($root.'/src/Core/Application/CommercialPaymentService.php');
$funding=file_get_contents($root.'/src/Core/Application/CommercialTermFundingService.php');
$capacity=file_get_contents($root.'/src/Core/Application/CommercialCapacityService.php');
$capacityAuthority=file_get_contents($root.'/src/Core/Application/CommercialCapacityAuthority.php');
$pattern=file_get_contents($root.'/src/Core/Application/CommercialPatternService.php');
$policy=file_get_contents($root.'/src/Core/Application/CommercialPolicyService.php');
$catalogue=file_get_contents($root.'/src/Core/Application/CommercialCatalogueService.php');
$promotion=file_get_contents($root.'/src/Core/Application/CommercialPromotionService.php');
$adjustment=file_get_contents($root.'/src/Core/Application/CommercialAdjustmentService.php');
$exceptions=file_get_contents($root.'/src/Core/Application/CommercialExceptionService.php');
$read=file_get_contents($root.'/src/Core/Application/CommercialReadService.php');
$support=file_get_contents($root.'/src/Core/Application/CommercialSupport.php');
$idempotency=file_get_contents($root.'/src/Core/Application/CommercialIdempotency.php');
$termAuthority=file_get_contents($root.'/src/Core/Application/CanonicalTermAuthorityService.php');
$lessonAuthority=file_get_contents($root.'/src/Core/Application/CanonicalLessonAuthorityService.php');
$schedules=file_get_contents($root.'/src/Core/Application/CanonicalLessonScheduleService.php');
$continuation=file_get_contents($root.'/src/Core/Application/CanonicalContinuationService.php');
$continuationRule=file_get_contents($root.'/src/Core/Application/CanonicalContinuationRule.php');
$authorityRepo=file_get_contents($root.'/src/Core/Infrastructure/Repository/CommercialAuthorityRepository.php');
$paymentRepo=file_get_contents($root.'/src/Core/Infrastructure/Repository/CommercialPaymentRepository.php');
$capacityRepo=file_get_contents($root.'/src/Core/Infrastructure/Repository/CommercialCapacityRepository.php');
$phaseR1=$rule.$money.$validator.$offer.$payment.$funding.$capacity.$capacityAuthority.$pattern.$policy.$catalogue.$promotion.$adjustment.$exceptions.$read.$support.$idempotency.$authorityRepo.$paymentRepo.$capacityRepo;
$phaseR1Application=$rule.$money.$validator.$offer.$payment.$funding.$capacity.$capacityAuthority.$pattern.$policy.$catalogue.$promotion.$adjustment.$exceptions.$read.$support.$idempotency;

if(!preg_match("/DZN_PLATFORM_SCHEMA_VERSION', '([0-9]+)'/",$plugin,$schema)||(int)$schema[1]<25)throw new RuntimeException('Missing Phase R1 schema identity');
if(!preg_match("/DZN_PLATFORM_BUILD_ID', 'phase2a2r1-[a-z0-9-]+-[0-9]{8}\.[0-9]+'/",$plugin))throw new RuntimeException('Missing Phase R1 build identity');

// Migration, storage, verifier wiring and capabilities.
foreach(array(
    '025_commercial_purchase_funding_authority','install_commercial_purchase_authority','verify_commercial_purchase_schema',
    'commercial_account_roots','commercial_policies','commercial_products','commercial_prices','commercial_promotions',
    'commercial_promotion_redemptions','commercial_account_adjustments','commercial_account_adjustment_events',
    'commercial_offers','commercial_offer_adjustments','commercial_offer_policies','commercial_offer_obligations',
    'commercial_purchases','commercial_entitlements','commercial_term_funding_plans','commercial_payment_evidence',
    'commercial_payment_facts','commercial_obligation_settlements','commercial_recurring_patterns',
    'commercial_capacity_claims','commercial_capacity_claim_intervals','commercial_exceptions','commercial_commands',
    'dzn_manage_commercial_catalogue','dzn_manage_commercial_promotions','dzn_manage_commercial_adjustments',
    'dzn_issue_commercial_offers','dzn_ingest_commercial_payment_evidence','dzn_bind_commercial_term_funding',
    'dzn_manage_commercial_capacity','dzn_manage_commercial_policies','dzn_manage_commercial_exceptions',
    'dzn_view_commercial_authority',
) as $needle) if(!str_contains($migration.$plugin,$needle))throw new RuntimeException('Missing Phase R1 migration contract: '.$needle);
if(!str_contains($migration,"if(\$id==='025_commercial_purchase_funding_authority')self::verify_commercial_purchase_schema();"))throw new RuntimeException('Migration 025 must invoke the Phase R1 schema verifier before it is recorded');
if(substr_count($migration,'self::verify_commercial_purchase_schema();')<3)throw new RuntimeException('Phase R1 verifier must run after migration 025, on current-schema verification and before schema activation');
if(!str_contains($migration,"in_array( '025_commercial_purchase_funding_authority', (array) get_option( self::COMPLETED, array() ), true )"))throw new RuntimeException('Retained-025 pre-activation verification is missing');
if(!str_contains($migration,"'025_commercial_purchase_funding_authority' )"))throw new RuntimeException('Phase R1 migration must be listed as required');
$install=substr($migration,strpos($migration,'private static function install_commercial_purchase_authority'),strpos($migration,'private static function verify_commercial_purchase_schema')-strpos($migration,'private static function install_commercial_purchase_authority'));
if(str_contains($install,'UPDATE ')||str_contains($install,'INSERT INTO'))throw new RuntimeException('Phase R1 migration must be additive only: no backfill, no inference, no settlement import');
if(stripos($install,'stripe')!==false||stripos($install,'google')!==false||stripos($install,'provider_intent')!==false)throw new RuntimeException('Phase R1 storage must stay provider-neutral');
if(str_contains($migration,'CAPABILITY_OPTION_R1')===false||str_contains($migration,'dzn_platform_capability_version_2a2r1')===false)throw new RuntimeException('Phase R1 capability marker is missing');
if(!str_contains($migration,'$phaseR1Grants')||!str_contains($migration,'$teacherR1Role'))throw new RuntimeException('Phase R1 capability repair must independently ensure every grant and deny the Teacher role');

// Locked R1 rule constants.
foreach(array(
    "RULE_VERSION='commercial_purchase_v1'","PLAN_KINDS=array('full','two_instalments')",
    "INSTALMENT_TRANCHES=2","TRANCHE_ONE_FROM=1","TRANCHE_ONE_TO=6","TRANCHE_TWO_FROM=7","TRANCHE_TWO_TO=12",
    "OFFER_STATES=array('issued','accepted','expired','withdrawn')","ENTITLEMENT_STATES=array('issued','term_bound')",
    "CLAIM_STATES=array('active','released','expired')","CLAIM_INTERVAL_STATES=array('protected','satisfied','released')",
    "CLAIM_SOURCE_KINDS=array('q_succession','regular_pattern')","EVIDENCE_KINDS=array('attempt','success','failure','refund','mandate')",
    "ADJUSTMENT_SOURCE_TYPES=array('promotion','account_adjustment','credit')","IMPLEMENTED_ADJUSTMENT_SOURCES=array('promotion','account_adjustment')",
) as $constant) if(!str_contains($rule,$constant))throw new RuntimeException('Missing locked Phase R1 rule constant: '.$constant);
foreach(array('unmatched_payment_evidence','ambiguous_obligation_attribution','amount_mismatch','currency_mismatch','invalid_or_expired_offer','conflicting_payment_evidence','missing_instalment_predecessor','capacity_handoff_failed','duplicate_entitlement_binding','funding_term_mismatch','late_payment_after_offer_window','capacity_conflict_during_transition','refund_evidence_received') as $reason) if(!str_contains($rule,$reason))throw new RuntimeException('Missing controlled commercial exception reason: '.$reason);
if(!str_contains($rule,'instalment_prerequisite_unsettled'))throw new RuntimeException('The informational prerequisite-pending reconciliation signal is missing');
if(!str_contains($rule,'public static function exceptionReason')||!str_contains($exceptions,'CommercialRule::exceptionReason'))throw new RuntimeException('The controlled exception vocabulary must include the informational reconciliation signals');

// Structural invariants stay single-sourced and are NOT configurable policy values.
foreach(array('TERM_SESSION_COUNT','TERM_STUDENT_CHANGE_ALLOWANCE','TERM_CHANGE_ALLOWANCE') as $structural) if(str_contains($rule,$structural))throw new RuntimeException('A structural invariant must not be a configurable commercial policy: '.$structural);
foreach(array('INTRO_BOOKING_HORIZON','MANUAL_RENEWAL_SLOT_GUARANTEE_WEEKS','AUTOMATIC_RENEWAL_CHARGE_LEAD_TIME','PAYMENT_RECOVERY_POLICY','INSTALMENT_DUE_DATE_POLICY') as $policyKey) if(!str_contains($rule,$policyKey))throw new RuntimeException('Missing class-B runtime commercial policy key: '.$policyKey);
if(!str_contains($policy,"in_array(\$policyKey,CommercialRule::POLICY_KEYS,true)"))throw new RuntimeException('The policy authority must reject non-configurable keys');
// Documentation traceability: the registry must document both policy classes without becoming configuration.
$registry=@file_get_contents($root.'/docs/COMMERCIAL-POLICY-REGISTRY.md');
if(!is_string($registry))throw new RuntimeException('docs/COMMERCIAL-POLICY-REGISTRY.md must exist');
foreach(array('TERM_SESSION_COUNT','TERM_STUDENT_CHANGE_ALLOWANCE','CAPACITY_SUCCESSION','INTRO_BOOKING_HORIZON','MANUAL_RENEWAL_SLOT_GUARANTEE_WEEKS','AUTOMATIC_RENEWAL_CHARGE_LEAD_TIME','PAYMENT_RECOVERY_POLICY','INSTALMENT_DUE_DATE_POLICY') as $documented) if(!str_contains($registry,$documented))throw new RuntimeException('The commercial policy registry must document: '.$documented);
if(!str_contains($registry,'structural invariant')||!str_contains($registry,'runtime configurable'))throw new RuntimeException('The commercial policy registry must classify structural invariants and runtime policies');
$phaseDoc=@file_get_contents($root.'/docs/PHASE-2A-2R1-COMMERCIAL-PURCHASE-FUNDING-CAPACITY-AUTHORITY.md');
if(!is_string($phaseDoc)||!str_contains($phaseDoc,'CAPACITY SUCCESSION')&&!str_contains($phaseDoc,'Phase-Q hold'))throw new RuntimeException('The Phase R1 document must record the capacity-succession contract');
if(!str_contains($termAuthority,'public const SESSION_ALLOCATION = 12;')||!str_contains($termAuthority,'public const REPLACEMENT_ALLOWANCE = 2;'))throw new RuntimeException('The canonical Term authority must own the session allocation and change allowance constants');
if(!str_contains($termAuthority,"'lesson_allocation'=>self::SESSION_ALLOCATION,'replacement_allowance'=>self::REPLACEMENT_ALLOWANCE"))throw new RuntimeException('Canonical Term creation must use the single-sourced structural constants');
if(!str_contains($lessonAuthority,'(int)$term->lesson_allocation'))throw new RuntimeException('Lesson issuance must read the recorded Term allocation');
if(!str_contains($lessonAuthority,'(int)$term->replacement_allowance'))throw new RuntimeException('Replacement issuance must read the recorded Term change allowance');
if(!str_contains($lessonAuthority,'CommercialTermFundingService::standardAllowance'))throw new RuntimeException('Lesson issuance must be guarded by the commercial funded allowance');
if(!str_contains($lessonAuthority,"'standard_funding_exhausted'"))throw new RuntimeException('Lesson issuance must fail closed with standard_funding_exhausted');
if(!str_contains($termAuthority,'assertTermClosable'))throw new RuntimeException('Term closure must consult protected commercial capacity');

// Provider neutrality and the exact-money invariant.
if(preg_match('/\b(floatval|doubleval|float|round|number_format)\s*\(/',$phaseR1))throw new RuntimeException('Commercial authority must not use floating-point money handling');
if(str_contains($money,'/ '))throw new RuntimeException('Commercial money arithmetic must be integer-only');
foreach(array('stripe','Stripe','wp_remote_','wp_mail','curl_','webhook') as $forbidden) if(str_contains($phaseR1,$forbidden))throw new RuntimeException('Phase R1 must not acquire this authority: '.$forbidden);
foreach(array('INSERT INTO','UPDATE ','DELETE FROM') as $sql) if(str_contains($phaseR1Application,$sql))throw new RuntimeException('Phase R1 application services must not write storage with raw SQL: '.$sql);
foreach(array('cron','wp_schedule_event','wp_schedule_single_event') as $forbidden) if(str_contains($phaseR1,$forbidden))throw new RuntimeException('Phase R1 must not become time-authoritative through scheduling: '.$forbidden);

// The success invariant chain: offer → evidence → settlement → effectiveness → binding → guard.
if(!str_contains($payment,"array('issued','accepted')")||!str_contains($payment,'late_payment_after_offer_window'))throw new RuntimeException('Payment acceptance must honour the offer window and its explicit late-payment reason');
if(!str_contains($payment,'conflicting_payment_evidence'))throw new RuntimeException('A second settlement attempt must fail closed');
if(!str_contains($payment,'CommercialValidator::offerValid'))throw new RuntimeException('Payment acceptance must validate the offer aggregate');
if(!str_contains($payment,'purchaseByOffer'))throw new RuntimeException('One purchase per offer must be enforced through the canonical lookup');
if(!str_contains($funding,'$isEffective=$settled&&!$blocked;'))throw new RuntimeException('Academic effectiveness must require every lower-sequence obligation to be settled');
if(!str_contains($funding,'min((int)$plan->committed_sessions,$this->effectiveSessions((int)$plan->offer_id))'))throw new RuntimeException('The Term allowance must be derived and bounded by the commitment');
if(!str_contains($funding,'CommercialValidator::entitlementValid'))throw new RuntimeException('Term binding must validate the entitlement aggregate');
if(!str_contains($funding,'commercial_capacity_handoff_required'))throw new RuntimeException('A Term must not be bound before its successor capacity is durable');
if(!str_contains($funding,'create(')||!str_contains($funding,'self::CAPABILITY'))throw new RuntimeException('Term binding must use the existing canonical Term authority');
foreach(array('insertTerm','insertLesson','insertVersion') as $forbidden) if(str_contains($phaseR1,$forbidden))throw new RuntimeException('Phase R1 must not become a parallel academic writer: '.$forbidden);

// Capacity succession: Q hold → R1 claim → N schedule, all under the same Teacher root.
if(!str_contains($capacity,'ensureAndLockTeacherRoot'))throw new RuntimeException('Protected capacity must serialize on the canonical per-Teacher scheduling root');
if(!str_contains($capacity,'setReservationState'))throw new RuntimeException('The predecessor hold must be released through the existing version-checked Phase-Q seam');
if(!str_contains($capacity,"'released'"))throw new RuntimeException('The predecessor hold must be released only after the successor claim is durable');
if(!str_contains($capacityAuthority,'assertNoConflictingClaim')||!str_contains($capacityAuthority,'satisfyForLesson')||!str_contains($capacityAuthority,'reopenForLesson'))throw new RuntimeException('The protected-capacity arbitration seam is incomplete');
if(!str_contains($schedules,'CommercialCapacityAuthority::assertScheduleAllowed'))throw new RuntimeException('Phase N must consult protected capacity');
if(!str_contains($schedules,'CommercialCapacityAuthority::satisfyForLesson'))throw new RuntimeException('Phase N must satisfy the protected interval atomically with its schedule');
if(!str_contains($schedules,'CommercialCapacityAuthority::reopenForLesson'))throw new RuntimeException('Phase N must restore protection when a canonical schedule is released');
if(!str_contains($continuation,'CommercialCapacityAuthority::assertNoConflictingClaim'))throw new RuntimeException('Phase Q must consult protected capacity before holding a slot');
if(!str_contains($continuationRule,"RESERVATION_STATES=array('active','expired','released')"))throw new RuntimeException('Phase Q reservation vocabulary must not be extended by Phase R1');
if(str_contains($continuationRule,'+7 days')||str_contains($pattern,'+7 days'))throw new RuntimeException('Neither Phase Q nor Phase R1 may infer a future paid slot from the introduction date');
if(!str_contains($pattern,'CanonicalContinuationRule::resolveWallClock'))throw new RuntimeException('Recurring occurrences must reuse the authoritative wall-clock resolver');
if(!str_contains($pattern,'continuation_slot_duration_mismatch'))throw new RuntimeException('The pattern anchor must fail closed when it does not match the authorised slot');
if(!str_contains($capacity,"'q_succession'"))throw new RuntimeException('A Flexible commitment must claim only the explicitly authorised interval');

// Idempotency and durable evidence.
foreach(array('command_key_digest','command_payload_digest') as $column) if(!str_contains($migration,$column)||!str_contains($phaseR1Application,$column))throw new RuntimeException('Commercial command evidence must be digest-only: '.$column);
if(!str_contains($paymentRepo,'provider_reference'))throw new RuntimeException('Payment evidence must be deduplicated by provider reference');
if(!str_contains($payment,'evidenceByProviderReference'))throw new RuntimeException('Duplicate provider evidence must converge on the recorded outcome');
if(!str_contains($idempotency,'hash_hmac')||!str_contains($idempotency,'wp_salt'))throw new RuntimeException('Commercial digests must be keyed');
if(!str_contains($capacityRepo,'protected')||!str_contains($capacityRepo,"state='protected'"))throw new RuntimeException('Protected interval arbitration storage is missing');

// Commercial failures never silently drop verified financial evidence.
if(!str_contains($exceptions,'recordAfterFailure')||!str_contains($exceptions,'openException')||!str_contains($migration,'fingerprint_state'))throw new RuntimeException('Commercial exceptions must be durable and deduplicated');
if(!str_contains($capacity,'recordHandoffFailure'))throw new RuntimeException('A paid commitment whose capacity cannot converge must be preserved and routed');

echo "Phase 2A.2-R1 contract passed\n";
