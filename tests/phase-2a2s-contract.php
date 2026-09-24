<?php
/**
 * Phase 2A.2-S canonical notification & communications authority source contract (Schema 027).
 *
 * A pure source-string guard, runnable without WordPress: it proves the schema identity, the migration and
 * verifier wiring, the eighteen declared tables, the exact `platform_outbox` extension, the two routing
 * slots and their unique keys, the rule-freeze columns, the notification schedule columns, the attempt
 * retry columns, the closed intent registry and binding matrix, the §6.2.1 required-code vocabulary, the
 * closed §6.3 composition and canonical encoding, the §9 retry encoding with its narrow-only intervals,
 * the §6.2.4 tier-F instant sources, the non-null-with-`scheduled_for` expiry rule, the closed failure
 * vocabulary, the capability boundaries, the status superset, the draft-only rule command, and the absence
 * of any commercial-policy, charge-lead-time, pattern-wall-clock or guarantee-fallback reference.
 */
$root=dirname(__DIR__);
$plugin=file_get_contents($root.'/delnavazan-platform.php');
$migration=file_get_contents($root.'/src/Core/Infrastructure/Migration/Migrator.php');
$rule=file_get_contents($root.'/src/Core/Application/NotificationRule.php');
$support=file_get_contents($root.'/src/Core/Application/NotificationSupport.php');
$schedule=file_get_contents($root.'/src/Core/Application/NotificationSchedule.php');
$retry=file_get_contents($root.'/src/Core/Application/NotificationRetry.php');
$eligibility=file_get_contents($root.'/src/Core/Application/NotificationEligibility.php');
$integrity=file_get_contents($root.'/src/Core/Application/NotificationIntegrity.php');
$notificationService=file_get_contents($root.'/src/Core/Application/NotificationService.php');
$dispatch=file_get_contents($root.'/src/Core/Application/NotificationDispatchService.php');
$workflowService=file_get_contents($root.'/src/Core/Application/NotificationWorkflowService.php');
$templateService=file_get_contents($root.'/src/Core/Application/NotificationTemplateService.php');
$suppressionService=file_get_contents($root.'/src/Core/Application/NotificationSuppressionService.php');
$privacyService=file_get_contents($root.'/src/Core/Application/NotificationPrivacyService.php');
$readServices=file_get_contents($root.'/src/Core/Application/NotificationWorkflowReadService.php').file_get_contents($root.'/src/Core/Application/NotificationTemplateReadService.php').file_get_contents($root.'/src/Core/Application/NotificationReadService.php').file_get_contents($root.'/src/Core/Application/NotificationAttemptReadService.php').file_get_contents($root.'/src/Core/Application/NotificationDeliveryReadService.php').file_get_contents($root.'/src/Core/Application/NotificationSuppressionReadService.php').file_get_contents($root.'/src/Core/Application/NotificationDiagnosticsReadService.php');
$repositories=file_get_contents($root.'/src/Core/Infrastructure/Repository/NotificationOutboxRepository.php').file_get_contents($root.'/src/Core/Infrastructure/Repository/NotificationWorkflowRepository.php').file_get_contents($root.'/src/Core/Infrastructure/Repository/NotificationRepository.php').file_get_contents($root.'/src/Core/Infrastructure/Repository/NotificationAttemptRepository.php').file_get_contents($root.'/src/Core/Infrastructure/Repository/NotificationTemplateRepository.php').file_get_contents($root.'/src/Core/Infrastructure/Repository/NotificationDeliveryRepository.php').file_get_contents($root.'/src/Core/Infrastructure/Repository/NotificationSuppressionRepository.php').file_get_contents($root.'/src/Core/Infrastructure/Repository/NotificationPrivacyRepository.php');
$services=$notificationService.$dispatch.$workflowService.$templateService.$suppressionService.$privacyService;
$r2Amendment=file_get_contents($root.'/src/Core/Application/RenewalCycleService.php').file_get_contents($root.'/src/Core/Application/CollectionIntentService.php');
$runtimeSuites='';
foreach(array('migration','outbox-compatibility','workflow','schedule-derivation','retry','tier-f-instant','eligibility-matrix','binding-matrix','corruption','failure','privacy') as $suite){
    $path=$root.'/tests/phase-2a2s-'.$suite.'-runtime.php';
    if(!is_readable($path))throw new RuntimeException('Missing the Phase 2A.2-S runtime suite: '.$suite);
    $runtimeSuites.=file_get_contents($path);
}
$runtimeSuites.=file_get_contents($root.'/tests/phase-2a2s-concurrency-runner.sh');
require_once $root.'/src/Core/Application/NotificationRule.php';
$ruleClass='Delnavazan\\Platform\\Core\\Application\\NotificationRule';

// 1. Schema identity, build shape and migration wiring.
if(!preg_match("/DZN_PLATFORM_SCHEMA_VERSION', '([0-9]+)'/",$plugin,$schema)||(int)$schema[1]!==27)throw new RuntimeException('The S schema identity must be 27');
if(!preg_match("/DZN_PLATFORM_BUILD_ID', 'phase2a2s-notification-communications-authority-[0-9]{8}\.[0-9]+'/",$plugin))throw new RuntimeException('The S build identity is missing');
foreach(array('027_notification_communications_authority','install_notification_communications_authority','verify_notification_communications_schema') as $needle)if(!str_contains($migration,$needle))throw new RuntimeException('Missing the S migration contract: '.$needle);
if(!str_contains($migration,"if(\$id==='027_notification_communications_authority')self::verify_notification_communications_schema();"))throw new RuntimeException('Migration 027 must invoke the S schema verifier before it is recorded');
if(substr_count($migration,'self::verify_notification_communications_schema();')<3)throw new RuntimeException('The S verifier must run after migration 027, on current-schema verification and before schema activation');
if(!str_contains($migration,"in_array( '027_notification_communications_authority', (array) get_option( self::COMPLETED, array() ), true )"))throw new RuntimeException('The retained-027 pre-activation verification is missing');
if(!str_contains($migration,"'027_notification_communications_authority' )"))throw new RuntimeException('The S migration must be listed as required');
if(!str_contains($migration,'CAPABILITY_OPTION_S')||!str_contains($migration,'dzn_platform_capability_version_2a2s'))throw new RuntimeException('The S capability marker is missing');

// 2. The migration is additive only and declares exactly the eighteen S-owned tables, each once.
$installStart=strpos($migration,'private static function install_notification_communications_authority');
$installEnd=strpos($migration,'private static function install_notification_outbox_extension');
$install=substr($migration,$installStart,$installEnd-$installStart);
if(str_contains($install,'INSERT INTO')||str_contains($install,'UPDATE '))throw new RuntimeException('Migration 027 must be additive only: no INSERT/UPDATE statement');
preg_match_all('/CREATE TABLE \{\$p\}([a-z_]+)/',$install,$created);
$declaredTables=array('notification_workflows','notification_workflow_versions','notification_workflow_rules','notification_workflow_commands','notification_templates','notification_template_versions','notification_template_commands','notification_rendered_snapshots','notifications','notification_events','notification_commands','notification_attempts','notification_attempt_events','notification_deliveries','notification_suppressions','notification_suppression_events','notification_suppression_commands','notification_privacy_tombstones');
$createdTables=$created[1];sort($createdTables);sort($declaredTables);
if($createdTables!==$declaredTables)throw new RuntimeException('Migration 027 must create exactly the eighteen declared S tables');
if(count(array_unique($createdTables))!==18)throw new RuntimeException('Migration 027 must declare each S table exactly once');
foreach(array('notification_workflow_versions','notification_workflow_rules','notification_events','notification_attempts','notification_deliveries','notification_privacy_tombstones') as $table)if(!preg_match('/CREATE TABLE \{\$p\}'.preg_quote($table,'/').' \(/',$install))throw new RuntimeException('Missing the declared S table: '.$table);
foreach(array('UNIQUE KEY workflow_active(workflow_id,active_slot)','UNIQUE KEY intent_active(intent_key,intent_active_slot)','UNIQUE KEY version_rule(workflow_version_id,rule_kind,rule_code,ordinal)','UNIQUE KEY notification_key_digest(notification_key_digest)','UNIQUE KEY outbox_id(outbox_id)','UNIQUE KEY attempt_lease(notification_id,attempt_sequence)','UNIQUE KEY lease_token_digest(lease_token_digest)','UNIQUE KEY provider_reference(provider_event_reference_digest)') as $needle)if(!str_contains($install,$needle))throw new RuntimeException('Missing the declared S unique key: '.$needle);

// 3. The exactly-declared outbox extension and its required preserved contract.
foreach(array('notification_id','workflow_key','workflow_version','intent_key','audience','scheduled_for','expires_at','deferral_count','priority','lease_token_digest','failure_reason_code') as $column)if(!str_contains(substr($migration,strpos($migration,'install_notification_outbox_extension')),"'".$column."'"))throw new RuntimeException('Missing the added outbox column: '.$column);
foreach(array("array('notification_id','notification_id',true)","array('dispatch','status,scheduled_for,available_at',false)","array('intent_version','intent_key,workflow_version',false)") as $needle)if(!str_contains($migration,$needle))throw new RuntimeException('Missing the added outbox index: '.$needle);
$verifyOutbox=substr($migration,strpos($migration,'function verify_notification_outbox_extension'));
foreach(array("array('idempotency_key',true)","array('notification_id',true)","'available'","'invitation_generation'","'varchar(16)'","'smallint unsigned'","'updated_at'") as $needle)if(!str_contains($verifyOutbox,$needle))throw new RuntimeException('The outbox verifier must assert: '.$needle);

// 4. Locked S vocabularies, the closed intent registry and the §6.2.2 binding matrix.
foreach(array(
    "INTENTS=array(","AUTOMATIC_RENEWAL_UPCOMING","AUTOMATIC_RENEWAL_CHARGED","AUTOMATIC_RENEWAL_FAILED","MANUAL_RENEWAL_PAYMENT_REQUIRED",
    "GUARANTEE_DEADLINE_APPROACHING","GUARANTEE_EXPIRED","PAYMENT_FAILED","PAYMENT_RECOVERED","TERM_LAPSED","REFUND_REVIEW_REQUIRED","REFUND_RESOLVED",
    "UNBOUND_INTENTS=array('GUARANTEE_EXPIRED')","MANDATORY_BASELINE=array('subject_exists','subject_state_is','recipient_resolvable','recipient_opted_in','guardian_authority_present','not_suppressed')",
    "'F'=>array('subject_instant_in_future','lead_time_at_least')","AUTHORISED_PAIRS=array(array('student','student'))",
    "RESERVED_PAIRS=array(array('guardian','guardian'),array('academy','staff'))",
    "TIMEZONE_BASES=array('recipient_local','academy_local','subject_local')",
    "TIMEZONE_SENSITIVE_CODES=array('fixed_local_time','send_window')",
    "TERMINAL_REASONS=array('contact_unusable','send_refused','no_route')",
    "CEILING_EXHAUSTION_CODE='retry_exhausted'","WINDOW_EXHAUSTION_CODE='retry_window_exhausted'",
    "LEASE_EXPIRED_OUTCOME='lease_expired'","LEASE_EXPIRED_CLASS='retryable'",
    "FAILURE_CLASSES=array('retryable','defer','terminal')",
) as $needle)if(!str_contains($rule,$needle))throw new RuntimeException('Missing the locked S rule constant: '.$needle);
if(count($ruleClass::INTENTS)!==11)throw new RuntimeException('The consumed intent registry must contain exactly the eleven R2 intents');
foreach(array(
    "'AUTOMATIC_RENEWAL_UPCOMING'=>array(" , "'aggregate'=>'renewal_cycle','event_type'=>'opened'",
    "'MANUAL_RENEWAL_PAYMENT_REQUIRED'=>array(","'aggregate'=>'renewal_cycle','event_type'=>'payment_required'",
    "'GUARANTEE_DEADLINE_APPROACHING'=>array(","'aggregate'=>'renewal_cycle','event_type'=>'guarantee_protected'",
    "'AUTOMATIC_RENEWAL_CHARGED'=>array(","'aggregate'=>'collection_intent','event_type'=>'confirmed'",
    "'PAYMENT_RECOVERED'=>array(","'aggregate'=>'recovery_case','event_type'=>'recovered'",
    "'TERM_LAPSED'=>array(","'aggregate'=>'renewal_cycle','event_type'=>'lapsed'",
    "'REFUND_REVIEW_REQUIRED'=>array(","'aggregate'=>'refund_review','event_type'=>'review_required'",
    "'REFUND_RESOLVED'=>array(","'aggregate'=>'refund_review','event_type'=>'resolved'",
    "'GUARANTEE_EXPIRED'=>null",
    "'instant'=>'automatic_charge_at'","'instant'=>'guarantee_deadline_at'",
    "'to_states'=>array('pending')","'to_states'=>array('lapsed')",
) as $needle)if(!str_contains($rule,$needle))throw new RuntimeException('Missing the §6.2.2 binding matrix row or field: '.$needle);
if(!str_contains($rule,"'AUTOMATIC_RENEWAL_UPCOMING'=>array(\n            'aggregate'=>'renewal_cycle','event_type'=>'opened','transitions'=>array('|pending')"))throw new RuntimeException('The advance notice must bind the cycle-open fact alone');
// 5. The closed §6.3 composition, the canonical one-row-per-parameter encoding and its declared ranges.
foreach(array(
    "SCHEDULE_CODES=array(","'immediate'=>array()","'lead_time'=>array(1=>array('lead_time_minutes','minutes',1,525600000))",
    "'deferral'=>array(1=>array('defer_ceiling_minutes','minutes',1,52560000),2=>array('max_deferrals','count',0,65535))",
    "'expiry'=>array(1=>array('expiry_minutes','minutes',1,525600000))","DEFERRAL_PRODUCT_MAX=52560000","SEND_WINDOW_SEARCH_DAYS=14",
    "SCHEDULE_SLOTS=array(","'anchor_F'=>'lead_time'","'anchor_P'=>'immediate'","'required'=>array('expiry')",
) as $needle)if(!str_contains($rule,$needle))throw new RuntimeException('Missing the closed schedule composition or encoding: '.$needle);
foreach(array('canonicalUnsigned','canonicalTime','schedule_composition_invalid','schedule_expiry_missing','schedule_timezone_basis_conflict','schedule_timezone_unresolved','schedule_derivation_divergence','eligibility_expired') as $needle)if(!str_contains($schedule.$support,$needle))throw new RuntimeException('The schedule derivation must implement: '.$needle);
if(!str_contains($schedule,'$composition[\'timezone_basis\']')||!str_contains($schedule,'count(array_unique($bases))!==1'))throw new RuntimeException('A version must have exactly one shared timezone basis');
if(!str_contains($schedule,'if(NotificationSupport::seconds($scheduledFor)>=NotificationSupport::seconds($subjectInstant))throw new \InvalidArgumentException(\'eligibility_expired\');'))throw new RuntimeException('The tier-F postcondition must be strict over the final result');

// 6. The §9 retry encoding, its narrow-only partial order and the deterministic keyed jitter.
foreach(array(
    "RETRY_CODE='retry'","1=>array('retry_max_attempts',1,3,3)","2=>array('retry_initial_backoff_seconds',1,120,120)",
    "3=>array('retry_backoff_multiplier_bp',10000,30000,30000)","4=>array('retry_max_backoff_seconds',1,3600,3600)","5=>array('retry_jitter_bp',0,1000,1000)",
    "RETRY_BASELINE=array(","'retry_max_attempts'=>3","'retry_initial_backoff_seconds'=>120","'retry_backoff_multiplier_bp'=>30000","'retry_max_backoff_seconds'=>3600","'retry_jitter_bp'=>1000",
) as $needle)if(!str_contains($rule,$needle))throw new RuntimeException('Missing the canonical retry encoding or baseline: '.$needle);
foreach(array('baseBackoff','appliedJitter','jitterEntropy','retry_policy_invalid','retry_schedule_divergence','if($attemptSequence>=$maxAttempts)','retry_window_exhausted','retry_exhausted') as $needle)if(!str_contains($retry.$support,$needle))throw new RuntimeException('The retry derivation must implement: '.$needle);
if(!str_contains($retry,'if($attemptSequence>=$maxAttempts){')||!str_contains($retry,"return array('exhaustion'=>'ceiling'"))throw new RuntimeException('The ceiling gate must be evaluated first');
if(str_contains($retry,'pow('))throw new RuntimeException('The closed form must never be used: the bounded recurrence is the sole semantics');
if(!str_contains($support,"RETRY_KEY_PREFIX.'\$notificationKeyDigest'")&&!str_contains($support,'NotificationRule::RETRY_KEY_PREFIX.$notificationKeyDigest'))throw new RuntimeException('The jitter entropy must be keyed on the immutable notification identity');
foreach(array('rand(','mt_rand(','random_int(','microtime(','time()') as $ambient)if(str_contains($retry,$ambient)||str_contains($schedule,$ambient))throw new RuntimeException('No ambient randomness or clock may enter a derivation: '.$ambient);

// 7. The §6.3/§6.5 non-null-with-`scheduled_for` expiry rule and its pre-scheduling exemption.
if(!str_contains($integrity,'if($scheduledFor!==null&&$expiresAt===null)throw new \RuntimeException(\'schedule_derivation_divergence\');'))throw new RuntimeException('A one-sided schedule must be rejected');
if(!str_contains($integrity,'if($scheduledFor===null&&$expiresAt!==null)throw new \RuntimeException(\'schedule_derivation_divergence\');'))throw new RuntimeException('A one-sided pre-scheduling row must be rejected');
if(!str_contains($integrity,'if($notification->scheduled_for===null)return;'))throw new RuntimeException('The non-null rule must be keyed on `scheduled_for IS NOT NULL`');

// 8. The exhaustion and terminal-reason partition, including the forged terminal-beside-expired closure.
foreach(array('terminal_reason_invalid','retry_exhaustion_invalid','retry_window_exhaustion_invalid') as $code)if(!str_contains($integrity,$code))throw new RuntimeException('The closure partition must report: '.$code);
if(!str_contains($integrity,"if((string)\$notification->state!=='failed')throw new \RuntimeException('terminal_reason_invalid');"))throw new RuntimeException('A terminal class must only ever close the notification as terminal failed');
if(!str_contains($integrity,'$persisted=array_filter($quadruple,static fn($value):bool=>$value!==null)!==array();'))throw new RuntimeException('The persisted-retry-schedule rule must be scoped to the closures that re-arm');
foreach(array('applied_jitter_bp','base_backoff_seconds','backoff_seconds','next_available_at') as $column)if(!str_contains($install,$column))throw new RuntimeException('Missing the attempt retry column: '.$column);
foreach(array('observed_at','schedule_anchor_at','deferral_count','timezone') as $column)if(!str_contains($install,$column))throw new RuntimeException('Missing the notification schedule column: '.$column);

// 9. The draft-only rule command, the routing claims and the notification/outbox 1:1 pair.
// The guarded write lives in the service and in the repository that performs it, not in the migration
// installer, so the assertion is scoped to the sources that actually hold the draft-only guard.
if(!str_contains($workflowService,"throw new \RuntimeException('workflow_rules_frozen');"))throw new RuntimeException('The rule attach path must refuse a frozen version in the service too');
if(!str_contains($repositories,"throw new \RuntimeException('workflow_rules_frozen');"))throw new RuntimeException('The rule attach path must refuse a frozen version in the repository too');
foreach(array('intent_active','workflow_active','intent_routing_conflict') as $needle)if(!str_contains($workflowService.$migration,$needle))throw new RuntimeException('The routing arbitration must be storage-enforced: '.$needle);
// 10. Capability boundaries, the status superset and provider neutrality.
foreach($ruleClass::CAPABILITIES as $capability)if(!str_contains($migration,$capability))throw new RuntimeException('The S capability must be granted through ensure_capabilities: '.$capability);
if(!str_contains($migration,"foreach(array('dzn_teacher')as\$roleName)"))throw new RuntimeException('The Teacher role must be denied every S capability');
foreach($ruleClass::OUTBOX_STATUS_VOCABULARY as $status)if(strlen($status)>16)throw new RuntimeException('Every status must fit the shared varchar(16) column: '.$status);
foreach(array('prepared','pending','scheduled','queued','leased','dispatched','delivered','failed','cancelled','expired','suppressed') as $status)if(!in_array($status,$ruleClass::OUTBOX_STATUS_VOCABULARY,true))throw new RuntimeException('The status vocabulary must stay a superset: '.$status);
foreach(array('stripe','Stripe','wp_remote_','wp_mail(','curl_','webhook','provider_sdk','meta_graph') as $forbidden)if(str_contains($services.$repositories,$forbidden))throw new RuntimeException('S must not acquire a transport or provider authority: '.$forbidden);
foreach(array('INSERT INTO','UPDATE ','DELETE FROM') as $sql)if(str_contains($services,$sql))throw new RuntimeException('The S services must not write storage with raw SQL: '.$sql);
foreach($ruleClass::FORBIDDEN_S_INPUTS as $forbidden)foreach(array($support,$schedule,$retry,$eligibility,$integrity,$services,$repositories) as $source)if(str_contains($source,$forbidden))throw new RuntimeException('S must not consult another authority: '.$forbidden);

// 11. The import/cite absence of every forbidden seam and the presence of every runtime suite.
foreach(array('CommercialPolicyService','MANUAL_RENEWAL_SLOT_GUARANTEE_WEEKS','automaticChargeAt','guaranteeFallback','guaranteeDeadlineForPattern','resolveWallClock','AUTOMATIC_RENEWAL_CHARGE_LEAD_TIME') as $forbidden){
    foreach(array('NotificationSupport','NotificationSchedule','NotificationRetry','NotificationEligibility','NotificationIntegrity','NotificationService','NotificationDispatchService') as $class){
        if(str_contains(file_get_contents($root.'/src/Core/Application/'.$class.'.php'),$forbidden))throw new RuntimeException('The S sources must not reference '.$forbidden.' ('.$class.')');
    }
}
if(!str_contains($r2Amendment,'automatic_charge_at'))throw new RuntimeException('The R2 amendment must persist the announced instant');
if(str_contains($r2Amendment,'automaticChargeAt((string)$cycle'))throw new RuntimeException('The collection side must read the persisted instant instead of re-deriving it');
if(!str_contains($r2Amendment,"\$mode==='manual'?'MANUAL_RENEWAL_PAYMENT_REQUIRED':null"))throw new RuntimeException('The automatic require_payment transition must publish no second advance notice');
foreach(array('phase-2a2s-concurrency-runner.sh','phase-2a2s-concurrency-setup.php','phase-2a2s-concurrency-worker.php','phase-2a2s-concurrency-verify.php') as $file)if(!is_readable($root.'/tests/'.$file))throw new RuntimeException('Missing the S concurrency harness: '.$file);
foreach(array('competing_activation_same_intent','rule_attach_vs_activation','deferral_vs_claim','cancel_vs_dispatch','suppress_vs_enqueue','unrelated_notifications') as $mode)if(!str_contains($runtimeSuites,$mode))throw new RuntimeException('The concurrency harness must exercise: '.$mode);

// 12. The independent-review corrections: the audited eligibility-abort closure, the strict transport
//     allowlist, the claim-time aggregate proof, the single exhaustion mapping and the frozen variable
//     contract every rendered snapshot and hand-off is proved against.
if(!str_contains($rule,"ELIGIBILITY_ABORT_CLASS='eligibility_abort'"))throw new RuntimeException('The audited eligibility-abort class is missing');
if(count($ruleClass::ELIGIBILITY_ABORT_OUTCOMES)!==8)throw new RuntimeException('The abort refusal set must stay closed');
if(!str_contains($rule,"'eligibility_abort_invalid'"))throw new RuntimeException('The abort diagnostic is missing from the closed vocabulary');
if(!$ruleClass::eligibilityAbortOutcome('suppressed')||$ruleClass::eligibilityAbortOutcome('retryable')||$ruleClass::eligibilityAbortOutcome('retry_exhausted'))throw new RuntimeException('The abort refusal set must admit exactly its own codes');
if(!$ruleClass::closureClass('eligibility_abort')||$ruleClass::closureClass('aborted'))throw new RuntimeException('The persisted closure-class vocabulary must admit the abort class alone');
if($ruleClass::controlledState('suppressed')!=='suppressed'||$ruleClass::controlledState('lead_time_insufficient')!=='expired'||$ruleClass::controlledState('consent_absent')!=='failed')throw new RuntimeException('The refusal-to-state mapping must be the one closed mapping');
if(!str_contains($integrity,"throw new \RuntimeException('eligibility_abort_invalid');"))throw new RuntimeException('The closure partition must reject a malformed abort closure');
if(!str_contains($integrity,'function variableContract(')||!str_contains($integrity,'function renderParameters(')||!str_contains($integrity,'function renderedSnapshot('))throw new RuntimeException('The frozen variable-contract proofs are missing');
if(!str_contains($support,'function paramsDigest('))throw new RuntimeException('The canonical key-ordered parameter digest is missing');
if(!str_contains($retry,'function exhaustionReasonCode('))throw new RuntimeException('The exhaustion gate mapping must be single-sourced');
foreach(array('retry_exhausted','retry_window_exhausted') as $code)if(!str_contains($retry,$code))throw new RuntimeException('The single exhaustion mapping must name: '.$code);
if(!str_contains($dispatch,'private function authorisedCommand(object $notification,object $attempt):array'))throw new RuntimeException('The transport command must take no caller input');
if(str_contains($dispatch,'array_merge($supplied'))throw new RuntimeException('A caller-supplied field must never be merged into the transport command');
foreach(array("'notification_key_digest'=>","'attempt_sequence'=>","'audience'=>","'template_version_id'=>","'variable_codes'=>","'parameters'=>") as $field)if(!str_contains(substr($dispatch,strpos($dispatch,'private function authorisedCommand')),$field))throw new RuntimeException('The transport command must carry the frozen field: '.$field);
if(!str_contains($dispatch,'private function aggregateGuard(object $notification,?object $version,?object $row):array'))throw new RuntimeException('The claim must prove the aggregate under its own locks');
if(!str_contains($dispatch,'$this->aggregateGuard($notification,$version,$locked)'))throw new RuntimeException('The claim must run the aggregate guard before any lease');
if(!str_contains($dispatch,'NotificationRule::ELIGIBILITY_ABORT_CLASS'))throw new RuntimeException('The dispatch re-evaluation must close through the audited abort class');
if(!str_contains($install,'variable_contract varchar(191)'))throw new RuntimeException('The template variable contract must be persisted');
if(!str_contains($templateService,"NotificationSupport::variableContract((array)(\$input['variable_codes']"))throw new RuntimeException('A template version must derive its variable contract, digest and count');
if(!str_contains($notificationService,'NotificationIntegrity::renderParameters($templateVersion,$parameters)'))throw new RuntimeException('A frozen snapshot must be proved against the contract');
if(!str_contains($readServices,"'variable_contract'=>\$contract['variable_contract']"))throw new RuntimeException('The template read seam must expose the frozen contract');

// 13. The §15 concurrency matrix is complete, and the audited attempt/closure vocabulary the race harness
//     proves is the vocabulary the sources implement.
$requiredModes=array(
    'dispatch_vs_retry','lease_expiry_vs_handoff','retry_exhaustion_vs_recovery',
    'subject_transition_after_enqueue_vs_dispatch','policy_change_after_publication_vs_dispatch',
    'deferral_vs_claim','activation_vs_dispatch','competing_activation_same_intent',
    'rule_attach_vs_activation','suppress_vs_enqueue','cancel_vs_dispatch',
    'delivery_vs_attempt_close','erase_vs_dispatch','unrelated_notifications',
);
$runnerPath=$root.'/tests/phase-2a2s-concurrency-runner.sh';
$runner=file_get_contents($runnerPath);
if(!preg_match("/^MODES='([^']+)'/m",$runner,$declared))throw new RuntimeException('The runner must declare its mode list');
if(preg_split('/\s+/',trim($declared[1]))!==$requiredModes)throw new RuntimeException('The runner must declare exactly the fourteen §15 modes in the contract order');
if(!str_contains($runner,'exit 2'))throw new RuntimeException('An unimplemented mode must fail the runner instead of passing silently');
if(!is_executable($runnerPath)||(fileperms($runnerPath)&0777)!==0755)throw new RuntimeException('The concurrency runner must be committed executable as 0755');
foreach(array('setup','worker','verify') as $harness){
    $source=file_get_contents($root.'/tests/phase-2a2s-concurrency-'.$harness.'.php');
    if($source===false)throw new RuntimeException('Missing the S concurrency harness: '.$harness);
    foreach($requiredModes as $mode)if(!str_contains($source,"'".$mode."'"))throw new RuntimeException('The '.$harness.' harness must drive the §15 mode: '.$mode);
}
foreach(array(
    "LEASE_CANCELLED_CLASS='lease_cancelled'","LEASE_CANCELLED_OUTCOMES=array('cancelled','suppressed','expired')",
    "ATTEMPT_OPEN_STATES=array('leased','handed_off')","ATTEMPT_CLOSED_STATES=array('acknowledged','failed','expired','abandoned')",
    "'handed_off|abandoned'","'dispatching|queued'","'handed_off|failed'","'handed_off|acknowledged'","'leased|handed_off'",
) as $needle)if(!str_contains($rule,$needle))throw new RuntimeException('The locked attempt/notification vocabulary must carry: '.$needle);
foreach(array('attempt_lifecycle_invalid','lease_cancellation_invalid','notification_attempt_state_conflict') as $code){
    if(!str_contains($rule,"'".$code."'"))throw new RuntimeException('The closed S vocabulary must name: '.$code);
    if(!$ruleClass::failureCode($code))throw new RuntimeException('The S failure vocabulary must admit: '.$code);
}
if(!$ruleClass::closureClass($ruleClass::LEASE_CANCELLED_CLASS)||$ruleClass::closureClass('cancelled'))throw new RuntimeException('The closure-class vocabulary must admit the lease-cancellation class alone');
if(!$ruleClass::leaseCancellationOutcome('cancelled')||!$ruleClass::leaseCancellationOutcome('suppressed')||!$ruleClass::leaseCancellationOutcome('expired')||$ruleClass::leaseCancellationOutcome('failed')||$ruleClass::leaseCancellationOutcome('retryable'))throw new RuntimeException('The lease-cancellation outcome set must stay closed');
if(!$ruleClass::legalAttemptTransition('handed_off','abandoned')||!$ruleClass::legalAttemptTransition('leased','abandoned'))throw new RuntimeException('A held lease must be closable through the audited cancellation path');
if(!$ruleClass::legalNotificationTransition('dispatching','queued'))throw new RuntimeException('A re-arming closure must return the notification to its claimable state');
if($ruleClass::legalAttemptTransition('acknowledged','failed')||$ruleClass::legalAttemptTransition('failed','abandoned'))throw new RuntimeException('A closed attempt must never be closed again');
foreach(array(
    'private function reserveHandOff(int $attemptId,string $digest,int $actor):array',
    'private function replayHandOff(int $attemptId,object $winner):array',
) as $needle)if(!str_contains($dispatch,$needle))throw new RuntimeException('The durable hand-off reservation is missing: '.$needle);
if(!str_contains($dispatch,'$reservation=$this->reserveHandOff($attemptId,$digest,$actor);'))throw new RuntimeException('The hand-off must reserve durably before it calls the port');
if(!str_contains($dispatch,"if((\$reservation['reserved']??false)!==true)return \$reservation;"))throw new RuntimeException('A repeated hand-off must return the persisted reservation without a second port call');
if(!str_contains($dispatch,"if(\$source!=='handed_off')throw new \RuntimeException('notification_attempt_state_conflict');"))throw new RuntimeException('An acknowledgement must be admitted only from a handed-off attempt');
if(!str_contains($dispatch,'elseif(!in_array($source,NotificationRule::ATTEMPT_OPEN_STATES,true)){'))throw new RuntimeException('Only an open attempt may be closed');
if(!str_contains($dispatch,"public function releaseLease(int \$attemptId,array \$input,string \$key):array{return \$this->recordOutcome("))throw new RuntimeException('A lease release must close through the bounded ceiling-first closure path');
if(str_contains($dispatch,"'outcome_code'=>'released'"))throw new RuntimeException('The release path must not keep the unbounded writer that re-armed without the two gates');
if(!str_contains($dispatch,'||$locked->scheduled_for===null||(string)$locked->available_at>$now'))throw new RuntimeException('The claim must re-validate the mirrored instant under its own lock');
foreach(array('public function recordOutcome','public function recoverExpiredLeases') as $path){
    $body=substr($dispatch,strpos($dispatch,$path),3600);
    $aggregateLock=strpos($body,'$this->repository->find((int)$attempt->notification_id,true)');
    $outboxLock=strpos($body,'$this->outbox->row((int)$attempt->outbox_id,true)');
    $attemptLock=strpos($body,'$this->attempts->find($attemptId,true)');
    if($aggregateLock===false||$outboxLock===false||$attemptLock===false)throw new RuntimeException('The S lock order must be explicit on '.$path);
    if(!($aggregateLock<$outboxLock&&$outboxLock<$attemptLock))throw new RuntimeException('The S lock order must stay aggregate -> outbox row -> attempt row on '.$path);
}
if(!str_contains($notificationService,'private function resolveLiveAttempt(object $notification,string $state,string $now,int $actor):void'))throw new RuntimeException('A terminal command must resolve the live lease in its own transaction');
if(!str_contains($notificationService,'$this->resolveLiveAttempt($notification,$state,$now,$actor);'))throw new RuntimeException('The terminal command must resolve the live lease before it transitions');
$terminalCommand=substr($notificationService,strpos($notificationService,'private function closeByCommand'),2600);
$liveLease=strpos($terminalCommand,'$this->resolveLiveAttempt(');
$closeOutbox=strpos($terminalCommand,'$this->outbox->closeAny(');
if($liveLease===false||$closeOutbox===false||$liveLease>$closeOutbox)throw new RuntimeException('The live lease must be resolved before the terminal command closes its outbox row');
if(!str_contains($integrity,'if((string)$failureClass===NotificationRule::LEASE_CANCELLED_CLASS)'))throw new RuntimeException('The closure partition must judge the lease-cancellation class');
if(!str_contains($integrity,"throw new \RuntimeException('lease_cancellation_invalid');"))throw new RuntimeException('A malformed lease-cancellation closure must fail closed');
if(!str_contains($integrity,'public static function attemptHistoryIntegrity(object $notification,array $attempts):void'))throw new RuntimeException('The persisted attempt history must be proved against the lifecycle');
if(!str_contains($integrity,'self::attemptHistoryIntegrity($notification,$attempts);'))throw new RuntimeException('Aggregate integrity must prove the attempt history before it partitions the closures');
if(!str_contains($integrity,"if((int)\$attempt->attempt_sequence!==\$expected)throw new \RuntimeException('attempt_lifecycle_invalid');"))throw new RuntimeException('The attempt sequence must be contiguous from one');
if(!str_contains($privacyService,'$this->repository->begin();'))throw new RuntimeException('Erasure must open its transaction');
if(!str_contains(substr($privacyService,strpos($privacyService,'function eraseRecipient')),'$notification=$this->notifications->find($notificationId,true);'))throw new RuntimeException('Erasure must take the aggregate root lock inside its own transaction');
if(!str_contains(file_get_contents($root.'/tests/phase-2a2s-fixture.php'),'implements NotificationTransportPort'))throw new RuntimeException('The concurrency fixture must drive hand-off through the channel-neutral port only');
echo "Phase 2A.2-S contract static test passed\n";
