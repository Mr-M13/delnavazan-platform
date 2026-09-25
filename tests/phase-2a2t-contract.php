<?php
/** Phase 2A.2-T provider-neutral payment execution seam and Stripe adapter source contract. */
$root=dirname(__DIR__);
$plugin=file_get_contents($root.'/delnavazan-platform.php');
$migration=file_get_contents($root.'/src/Core/Infrastructure/Migration/Migrator.php');
$seam='';
foreach(glob($root.'/src/Core/Application/PaymentExecution/*.php') as $file)$seam.=file_get_contents($file);
$rule=file_get_contents($root.'/src/Core/Application/PaymentExecution/PaymentExecutionRule.php');
$support=file_get_contents($root.'/src/Core/Application/PaymentExecution/PaymentExecutionSupport.php');
$service=file_get_contents($root.'/src/Core/Application/PaymentExecution/PaymentExecutionService.php');
$intake=file_get_contents($root.'/src/Core/Application/PaymentExecution/PaymentEventIntakeService.php');
$seal=file_get_contents($root.'/src/Core/Application/PaymentExecution/PaymentExecutionDispatchSeal.php');
$vault=file_get_contents($root.'/src/Core/Application/PaymentExecution/PaymentSecretVault.php');
$accountService=file_get_contents($root.'/src/Core/Application/PaymentExecution/PaymentProviderAccountService.php');
$objectService=file_get_contents($root.'/src/Core/Application/PaymentExecution/PaymentProviderObjectService.php');
$worker=file_get_contents($root.'/src/Core/Application/PaymentExecution/PaymentExecutionWorkerContext.php');
$execRepository=file_get_contents($root.'/src/Core/Infrastructure/Repository/PaymentExecutionRepository.php');
$providerRepository=file_get_contents($root.'/src/Core/Infrastructure/Repository/PaymentProviderRepository.php');
$secretRepository=file_get_contents($root.'/src/Core/Infrastructure/Repository/PaymentSecretRepository.php');
$adapter=file_get_contents($root.'/src/Integrations/Payment/Stripe/StripePaymentAdapter.php');
$translator=file_get_contents($root.'/src/Integrations/Payment/Stripe/StripeEventTranslator.php');
$verifier=file_get_contents($root.'/src/Integrations/Payment/Stripe/StripeSignatureVerifier.php');
$controller=file_get_contents($root.'/src/Integrations/Payment/Stripe/StripeWebhookController.php');
$contractAdapter=file_get_contents($root.'/src/Integrations/Payment/ContractPaymentAdapter.php');
$runtime=file_get_contents($root.'/tests/phase-2a2t-runtime.php');
$migrationRuntime=file_get_contents($root.'/tests/phase-2a2t-migration-runtime.php');
$webhookRuntime=file_get_contents($root.'/tests/phase-2a2t-webhook-runtime.php');
$secretRuntime=file_get_contents($root.'/tests/phase-2a2t-secret-runtime.php');
$corruption=file_get_contents($root.'/tests/phase-2a2t-corruption-runtime.php');
$failure=file_get_contents($root.'/tests/phase-2a2t-failure-runtime.php');
$concurrency=file_get_contents($root.'/tests/phase-2a2t-concurrency-runner.sh')
    .file_get_contents($root.'/tests/phase-2a2t-concurrency-setup.php')
    .file_get_contents($root.'/tests/phase-2a2t-concurrency-worker.php')
    .file_get_contents($root.'/tests/phase-2a2t-concurrency-verify.php');
$tables=array('payment_provider_accounts','payment_provider_account_events','payment_provider_account_commands','payment_provider_objects','payment_provider_object_events','payment_provider_object_commands','payment_provider_secrets','payment_execution_commands','payment_execution_attempts','payment_execution_results','payment_execution_dispatches','payment_provider_event_receipts','payment_provider_events','payment_provider_event_decisions','payment_provider_secret_events');

// Build/schema identity: Phase T is Schema 28, sequenced additively after the V candidate.
if(!preg_match("/DZN_PLATFORM_SCHEMA_VERSION', '([0-9]+)'/",$plugin,$schema)||(int)$schema[1]<28)throw new RuntimeException('Missing Phase T schema identity');
if(!preg_match("/DZN_PLATFORM_BUILD_ID', 'phase2a2t-[a-z0-9-]+-[0-9]{8}\.[0-9]+'/",$plugin))throw new RuntimeException('Missing Phase T build identity');

// Migration, storage, verifier wiring and capabilities.
foreach(array(
    '028_payment_execution_seam_provider_adapter','install_payment_execution_seam','verify_payment_execution_schema',
    'dzn_manage_payment_providers','dzn_manage_payment_execution','dzn_ingest_payment_provider_events',
    'dzn_view_payment_execution_authority','dzn_platform_capability_version_2a2t','$phaseTGrants','$teacherTRole',
) as $needle)if(!str_contains($migration.$plugin,$needle))throw new RuntimeException('Missing Phase T migration contract: '.$needle);
if(!str_contains($migration,"if(\$id==='028_payment_execution_seam_provider_adapter')self::verify_payment_execution_schema();"))throw new RuntimeException('Migration 028 must invoke the Phase T schema verifier before it is recorded');
if(substr_count($migration,'self::verify_payment_execution_schema();')<3)throw new RuntimeException('Phase T verifier must run after migration 028, on current-schema verification and before schema activation');
if(!str_contains($migration,"in_array( '028_payment_execution_seam_provider_adapter', (array) get_option( self::COMPLETED, array() ), true )"))throw new RuntimeException('Retained-028 pre-activation verification is missing');
if(!str_contains($migration,"'028_payment_execution_seam_provider_adapter' )"))throw new RuntimeException('Phase T migration must be listed as required');
$installStart=strpos($migration,'private static function install_payment_execution_seam');
$installEnd=strpos($migration,'private static function verify_payment_execution_schema');
$install=substr($migration,$installStart,$installEnd-$installStart);
$install=substr($install,strpos($install,'{')); // body only: the signature names the phase, the storage must not
if(str_contains($install,'UPDATE ')||str_contains($install,'INSERT INTO')||str_contains($install,'ALTER TABLE'))throw new RuntimeException('Phase T migration must be additive only');
if(stripos($install,'stripe_')!==false)throw new RuntimeException('Phase T storage must stay provider-neutral');
preg_match_all('/CREATE TABLE \{\$p\}([a-z_]+)/',$install,$created);
$createdTables=$created[1];sort($createdTables);$declared=$tables;sort($declared);
if($createdTables!==$declared)throw new RuntimeException('Migration 028 must create exactly the fifteen declared Phase T tables');
if(count(array_unique($createdTables))!==15)throw new RuntimeException('Migration 028 must declare each Phase T table exactly once');

// Locked Phase-T vocabulary and structural constants (§5.2).
foreach(array(
    "PROVIDERS=array('stripe')","MODES=array('test','live')","ACCOUNT_STATES=array('active','suspended','closed')",
    "EXECUTION_STATES=array('disabled','enabled')","CREDENTIAL_STATES=array('unconfigured','configured','invalid')",
    "OBJECT_KINDS=array('customer','payment_method','intent','charge','subscription','mandate')",
    "CANONICAL_KINDS=array('student','purchase','obligation','collection_intent','recurring_enrolment')",
    "OPERATIONS=array('submit_collection','cancel_collection','reconcile_collection')",
    "COMMAND_STATES=array('authorised','dispatching','completed','refused','conflicted')",
    "DISPATCH_STATES=array('claimed','in_flight','settled','released')",
    "RESULT_STATES=array('completed','refused','conflicted')",
    "OUTCOME_STATES=array('accepted_by_provider','declined','requires_action','unavailable','invalid_request','not_attempted')",
    "COMMAND_CONFLICT_REASON='duplicate_command_key_materially_different_payload'",
    "EVENT_TYPES=array(", "DECISION_STATES=array('translated','ignored','refused','conflicted')",
    "SECRET_CLASSES=array('webhook_signing_secret','api_key')","CIPHER='sodium_secretbox_v1'",
    "PROVISIONABLE_PROVIDERS=array()","SECRET_AUDIT_TYPES=array('stored','rotated','retired','revoked','write_refused','decrypt_failed')",
    "VERIFICATION_STATES=array('verified','refused')","R2_CONSEQUENCE_STATES=array('not_applicable','pending','applied','refused')",
    "SIGNATURE_TOLERANCE_SECONDS=300","MAX_WEBHOOK_BYTES=262144","TIMESTAMP_TOLERANCE_CLAMP_SECONDS=600",
    "DISPATCH_LEASE_SECONDS=120","DISPATCH_CALL_TIMEOUT_SECONDS=30","DISPATCH_LEASE_MARGIN_SECONDS=60",
    "DISPATCH_DESCRIPTOR_DOMAIN='payment_dispatch_descriptor_v1'",
    "DESCRIPTOR_PREFLIGHT_STATES=array('ok','dispatch_descriptor_unavailable')","LIVE_EXECUTION_PROVIDERS=array()",
    "'dispatch_descriptor_unavailable','dispatch_descriptor_incomplete',",
) as $needle)if(!str_contains($rule,$needle))throw new RuntimeException('Missing Phase T vocabulary: '.$needle);
if(!str_contains($rule,"'command_key_digest','idempotency_key_digest',"))throw new RuntimeException('The sealed descriptor must carry its binding pair');
if(!str_contains($rule,'public static function operationReferences')||!str_contains($rule,'public static function arbitrationSubject'))throw new RuntimeException('Phase T rule must own the reference map and the arbitration subject');

// §8.3: the dispatch claim is the only mutable execution row and is written inside transaction 1.
if(!str_contains($execRepository,"'claimed|in_flight'")&&!str_contains($rule,"'claimed|in_flight'"))throw new RuntimeException('The dispatch lifecycle must be locked');
foreach(array('acquireLease','takeoverLease','renewLease','settleClaim','releaseClaim','noCallAbort') as $method)
    if(!str_contains($execRepository,'function '.$method.'('))throw new RuntimeException('Missing fenced dispatch transition: '.$method);
if(!str_contains($execRepository,'claim_generation=claim_generation+1'))throw new RuntimeException('The takeover must advance the fencing generation');
if(!str_contains($execRepository,'dispatch_state=\'released\',lease_expires_at=NULL'))throw new RuntimeException('The generation-1 no-call abort must clear the lease in the same statement');
if(!str_contains($execRepository,'dispatch_state=\'released\',active_claim_slot=NULL'))throw new RuntimeException('The descriptor refusal must release the live slot');
foreach(array('insertCommand','insertAttempt','insertResult') as $method)if(!str_contains($execRepository,'function '.$method.'('))throw new RuntimeException('Missing insert-only execution method: '.$method);
foreach(array('function updateCommand','function updateResult','function deleteResult','function updateAttempt') as $forbidden)if(str_contains($execRepository,$forbidden))throw new RuntimeException('Execution evidence must be insert-only: '.$forbidden);
if(!str_contains($service,'SUBJECT')&&!str_contains($service,'subject_claim'))throw new RuntimeException('Cross-operation arbitration must be represented');
if(!str_contains($service,"'dispatch_in_flight'"))throw new RuntimeException('The losing opposing operation must be refused with dispatch_in_flight');
if(!str_contains($service,'acquireLease')||!str_contains($service,'settleClaim'))throw new RuntimeException('The execution service must use the fenced transitions');
if(!str_contains($service,'providerIdempotencyKey')||!str_contains($service,"'dzn-phase2a2t-'"))throw new RuntimeException('The provider idempotency key must be deterministically re-derivable');
if(!str_contains($service,'reconcile('))throw new RuntimeException('A takeover must reconcile before it re-issues');
if(!str_contains($service,'proveFence'))throw new RuntimeException('A takeover re-issue must pass a conditional pre-call ownership check');
if(strpos($service,'acquireLease')>strpos($service,'preflightDispatchDescriptor'))throw new RuntimeException('The acquisition must follow the pre-call preflight');
if(!str_contains($service,'sealedCommandKeyDigest')||!str_contains($service,'sealedIdempotencyKeyDigest'))throw new RuntimeException("Core's mandatory pre-lease binding comparison is missing");
if(strpos($service,'hash_equals($preflight->sealedIdempotencyKeyDigest()')>strpos($service,'acquireLease'))throw new RuntimeException("Core's binding comparison must be ordered before the acquisition");
if(strpos($service,'renewLease')===false)throw new RuntimeException('The takeover winner must renew its lease before acting');

// §5.3/§7: the port, the sealed descriptor, the preflight, the capability and the mapping registry.
foreach(array('sealDispatchDescriptor','preflightDispatchDescriptor','submit','cancel','reconcile') as $method)
    if(!str_contains($seam,'function '.$method.'('))throw new RuntimeException('Missing port method: '.$method);
if(!str_contains($seam,'string $expectedClaimIdempotencyKeyDigest'))throw new RuntimeException('The preflight must receive the expected claim digest explicitly');
if(!str_contains($seam,'class ProviderDispatchCapability'))throw new RuntimeException('The one-use dispatch capability is missing');
if(!str_contains($seam,'class ProviderReferenceClaims'))throw new RuntimeException('The memory-only reference claims are missing');
if(!str_contains($seam,'class PaymentProviderRegistry'))throw new RuntimeException('The provider registry is missing');
if(!str_contains($seam,"'unsupported_payment_provider'"))throw new RuntimeException('An unregistered provider must fail closed');
if(!str_contains($objectService,'activeObject')||!str_contains($objectService,'object_reference_digest'))throw new RuntimeException('The mapping registry must arbitrate on the keyed reference digest');
if(!str_contains($objectService,'unlink')&&!str_contains($objectService,'detach'))throw new RuntimeException('A mapping must be detachable');
if(!str_contains($providerRepository,'active_slot=1'))throw new RuntimeException('The mapping registry must keep one active link');

// §11: the vault refuses every provider write, and the descriptor seal is not a credential path.
if(!str_contains($vault,'PROVISIONABLE_PROVIDERS')&&!str_contains($vault,'provisionableProvider'))throw new RuntimeException("The vault must gate on the locked provisionable-provider constant");
if(!str_contains($vault,'provider_secret_write_not_authorised'))throw new RuntimeException('Every provider secret write must be refused with the locked reason');
if(strpos($vault,'provider_secret_write_not_authorised')>strpos($vault,'requireCapability'))throw new RuntimeException('The provider refusal must precede the capability check');
if(!str_contains($vault,"'write_refused'"))throw new RuntimeException('A refused write must be audited');
if(!str_contains($vault,'DZN_PLATFORM_PAYMENT_TEST_VAULT'))throw new RuntimeException('The constant-gated test vault is missing');
if(substr_count($vault,'sodium_crypto_secretbox')<1||!str_contains($vault,'random_bytes'))throw new RuntimeException('The vault must use authenticated encryption with a fresh nonce');
if(!str_contains($seal,'sodium_crypto_secretbox'))throw new RuntimeException('The dispatch seal must use authenticated encryption');
if(!str_contains($seal,'DISPATCH_DESCRIPTOR_FIELDS')&&!str_contains($seal,'fieldsMatch'))throw new RuntimeException('The sealed payload must be the locked field set');
if(str_contains($seal,'PaymentSecretVault'))throw new RuntimeException('The dispatch seal must not be the credential vault');
foreach(array('key_version','nonce','ciphertext') as $needle)if(!str_contains($seal,$needle))throw new RuntimeException('The sealed envelope shape is incomplete: '.$needle);
// Only an adapter may seal or open an envelope; Core may only digest a stored one.
foreach(glob($root.'/src/*/*.php') as $file)$allSources[]=array('file'=>$file,'code'=>file_get_contents($file));
foreach(glob($root.'/src/*/*/*.php') as $file)$allSources[]=array('file'=>$file,'code'=>file_get_contents($file));
foreach(glob($root.'/src/*/*/*/*.php') as $file)$allSources[]=array('file'=>$file,'code'=>file_get_contents($file));
foreach($allSources as $source){
    if(str_contains($source['code'],'PaymentExecutionDispatchSeal::seal(')||str_contains($source['code'],'PaymentExecutionDispatchSeal::open(')){
        if(!str_contains($source['file'],'/Integrations/'))throw new RuntimeException('Only an adapter may seal or open a dispatch descriptor: '.$source['file']);
    }
    if(str_contains($source['code'],'sodium_crypto_secretbox')&&!str_contains($source['file'],'/Integrations/')&&!str_contains($source['file'],'PaymentExecutionDispatchSeal')&&!str_contains($source['file'],'PaymentSecretVault'))throw new RuntimeException('An unexpected sealing boundary: '.$source['file']);
}
// No port method other than the preflight opens an envelope, and the port never stores anything.
if(substr_count($adapter,'PaymentExecutionDispatchSeal::open(')!==1)throw new RuntimeException('The adapter must open an envelope exactly once, in its preflight');
foreach(array('submit','cancel','reconcile') as $method){
    $start=strpos($adapter,'function '.$method.'(');
    $body=substr($adapter,$start,400);
    if(str_contains($body,'PaymentExecutionDispatchSeal::open('))throw new RuntimeException('A port call must never open an envelope: '.$method);
}
if(str_contains($adapter,'$wpdb'))throw new RuntimeException('The adapter must not touch storage');
if(!str_contains($adapter,'DISPATCH_CALL_TIMEOUT_SECONDS'))throw new RuntimeException('The outbound call must use the structural timeout');
if(!str_contains($adapter,'redirection')&&!str_contains($adapter,'redact'))throw new RuntimeException('The adapter must bound its request and redact its errors');
if(!str_contains($rule,'callFitsWithinLease')&&!str_contains($seam,'callFitsWithinLease'))throw new RuntimeException('The structural timeout inequality must be stated');

// §9: the webhook receipt, the pre-parse account selector, single-secret verification and the worker.
if(!str_contains($plugin,'StripeWebhookController'))throw new RuntimeException('The webhook controller must be registered');
if(!str_contains($controller,'payment-provider-events'))throw new RuntimeException('The webhook route is missing');
if(!str_contains($controller,"'permission_callback'=>'__return_true'"))throw new RuntimeException('The webhook must defer its trust decision to signature verification');
if(!str_contains($controller,"(?P<account>[A-Za-z0-9_-]{1,32})"))throw new RuntimeException('The route must carry the pre-parse account selector');
if(!str_contains($controller,"'method_not_allowed'")||!str_contains($controller,"'https_required'")||!str_contains($controller,"'unsupported_content_type'")||!str_contains($controller,"'payload_too_large'")||!str_contains($controller,"'empty_payload'")||!str_contains($controller,"'unexpected_request_shape'"))throw new RuntimeException('The §9.2 request requirements are incomplete');
if(!str_contains($intake,'recordReceipt')||!str_contains($intake,'account_selector_digest'))throw new RuntimeException('Every inbound request must be receipted');
if(!str_contains($intake,"'unsupported_payment_provider'")||!str_contains($intake,"'webhook_account_unresolved'")||!str_contains($intake,"'provider_account_inactive'"))throw new RuntimeException('The account resolution reasons are incomplete');
if(strpos($intake,'resolveByReferenceCode')>strpos($intake,'$translator->verify'))throw new RuntimeException('The account must resolve before the body is verified');
if(!str_contains($verifier,'hash_equals'))throw new RuntimeException('The signature comparison must be constant-time');
if(str_contains($verifier,'foreach')&&str_contains($verifier,'activeSecret(')&&substr_count($verifier,'activeSecret(')>1)throw new RuntimeException('Exactly one secret may ever be tried');
if(!str_contains($verifier,'SIGNATURE_TOLERANCE_SECONDS')||!str_contains($verifier,'TIMESTAMP_TOLERANCE_CLAMP_SECONDS'))throw new RuntimeException('The signature tolerance must be bounded and clamped');
if(!str_contains($verifier,"'webhook_secret_unconfigured'"))throw new RuntimeException('A missing signing secret must fail closed');
foreach(array('payment_succeeded','payment_failed','payment_requires_action','refund_recorded','mandate_recorded','provider_recurring_semantics_unresolved','unrecognised_provider_event') as $type)
    if(!str_contains($translator,$type))throw new RuntimeException('The translation mapping is incomplete: '.$type);
if(!str_contains($translator,"'provider_evidence'")&&!str_contains($intake,"'provider_evidence'"))throw new RuntimeException('Evidence must be submitted on the provider_evidence channel');
if(!str_contains($intake,"'evidence_submitted'")||!str_contains($intake,"'conflicting_provider_event'")||!str_contains($intake,"'stale_provider_event'"))throw new RuntimeException('The decision vocabulary is incomplete');
if(!str_contains($intake,'intentForObligation'))throw new RuntimeException('The R2 consequence must resolve the exact intent');
if(!str_contains($intake,"'confirm_intent'")||!str_contains($intake,"'confirm_cycle'"))throw new RuntimeException('The ordered R2 consequence must confirm the collection intent and then the cycle');
if(strpos($intake,"'confirm_intent'")>strpos($intake,"'confirm_cycle'"))throw new RuntimeException('Step 1 must precede step 2');
if(!str_contains($intake,'RenewalCycleService')||!str_contains($intake,'CollectionIntentService'))throw new RuntimeException('The consequence must delegate both existing R2 commands');
if(!str_contains($intake,'recordRefundEvidence'))throw new RuntimeException('A refund event must route to the R2 refund review');
if(str_contains($intake,'requirePayment(')||str_contains($intake,'->lapse(')||str_contains($intake,'->cancel('))throw new RuntimeException('Phase T must never advance an R2 cycle itself');
if(str_contains($intake,'CommercialRule::EVIDENCE_KINDS')||str_contains($intake,'$wpdb->insert')||str_contains($intake,'$wpdb->query("INSERT'))throw new RuntimeException('The intake must not write commercial storage directly');
if(!str_contains($intake,'CommercialPaymentService')||!str_contains($intake,'->ingest('))throw new RuntimeException('Translation must route through the existing R1 ingest boundary');

// [C8-1] Every HTTP method reaches the controlled handler, and every receipt carries the exact raw body.
if(!str_contains($controller,'ROUTE_METHODS'))throw new RuntimeException('[C8-1] the webhook method set must be declared once');
if(!str_contains($controller,'GET,POST,PUT,PATCH,DELETE,HEAD,OPTIONS'))throw new RuntimeException('[C8-1] every HTTP method must be routed to the controlled handler');
if(substr_count($controller,"'methods'=>self::ROUTE_METHODS")!==2)throw new RuntimeException('[C8-1] both webhook routes must declare the all-method set');
if(!str_contains($controller,'$intake->receive($providerKey,$accountSelector,$rawBody,$headers,$meta)'))throw new RuntimeException('[C8-1] the receipt must always receive the exact raw body, whatever a precheck decided');
if(str_contains($controller,'$reason===null?'))throw new RuntimeException('[C8-1] a precheck refusal must never substitute an empty or rewritten body');
if(!str_contains($intake,'precheck_refusal'))throw new RuntimeException('[C8-1] the controller must pass a controlled precheck refusal to the intake');
if(!str_contains($intake,'body_bytes')||!str_contains($intake,"'request_digest'=>PaymentExecutionIdempotency::payloadDigest(\$rawBody)"))throw new RuntimeException('[C8-1] the receipt must record the digest and byte count of the body it was given');

// [C8-2] Attribution is exact: the event object's active mapping must own the obligation the event names.
if(!str_contains($providerRepository,'function activeObjectsByReferenceDigest('))throw new RuntimeException('[C8-2] the active mapping must be resolvable by reference digest');
if(!str_contains($providerRepository,'object_reference_digest=%s AND active_slot=1'))throw new RuntimeException('[C8-2] a historical mapping must never be returned as attribution authority');
if(!str_contains($providerRepository,'function obligationForCollectionIntent('))throw new RuntimeException('[C8-2] an R2 collection-intent mapping must resolve the obligation it owns');
if(str_contains($intake,'objectIsMapped'))throw new RuntimeException('[C8-2] the loose "any historical mapping" check must be gone');
if(!str_contains($intake,'activeObjectsByReferenceDigest'))throw new RuntimeException('[C8-2] the intake must resolve the event object through the active mapping');
if(!str_contains($intake,'$this->canonicalObligation($mapping)===$obligationId?null'))throw new RuntimeException('[C8-2] the mapping\'s canonical obligation must equal the obligation the event resolved');
if(!str_contains($intake,'function canonicalObligation(')||!str_contains($intake,"'collection_intent'=>\$this->repository->obligationForCollectionIntent("))throw new RuntimeException('[C8-2] the canonical obligation of both obligation and collection-intent mappings must be resolved');
if(!str_contains($intake,"'unmapped_provider_object'")||!str_contains($intake,"'ambiguous_obligation_attribution'"))throw new RuntimeException('[C8-2] a mismatch and an absent mapping must each be refused');

// [C8-3] Insert-or-resolve reports ownership, and a lost insert race converges like any duplicate.
if(!str_contains($intake,'private function convergeExisting('))throw new RuntimeException('[C8-3] duplicates must converge through one shared path');
if(!str_contains($intake,"string \$eventReferenceDigest,string \$factDigest,string \$receivedAt):array"))throw new RuntimeException('[C8-3] insert-or-resolve must report whether this worker created the event');
if(!str_contains($intake,"return array('event_id'=>(int)\$winner->id,'created'=>false);"))throw new RuntimeException('[C8-3] a worker that loses the unique event index must adopt the winner\'s event without ownership');
if(!str_contains($intake,"if(!\$inserted['created'])return \$this->convergeExisting(\$event,\$factDigest,\$envelope);"))throw new RuntimeException('[C8-3] a lost insert race must route through the same convergence path as a read duplicate');
if(!str_contains($intake,"if(\$existing)return \$this->convergeExisting(\$existing,\$factDigest,\$envelope);"))throw new RuntimeException('[C8-3] a read duplicate must use the same convergence path');

// [C8-4] A drain recomputes the full fact digest and never translates changed facts.
if(!str_contains($intake,'$this->factDigest((string)$event->provider_key,(string)$event->event_reference_digest,$match)'))throw new RuntimeException('[C8-4] the drain must recompute the recorded event fact digest');
if(!str_contains($intake,'return $this->recordConflict($event);'))throw new RuntimeException('[C8-4] a changed drain payload must append the controlled conflict decision');

// §9.7: the bounded worker principal is adopted, proved and restored.
if(!str_contains($support,'dzn_platform_payment_worker_principal'))throw new RuntimeException('The worker principal option is missing');
foreach(array("'dzn_ingest_payment_provider_events'","'dzn_ingest_commercial_payment_evidence'","'dzn_manage_collection_intents'","'dzn_manage_renewal_cycles'") as $capability)
    if(!str_contains($support,$capability))throw new RuntimeException('The worker principal capability set is incomplete: '.$capability);
foreach(array("'manage_options'","'dzn_manage_payment_execution'","'dzn_manage_payment_providers'") as $forbidden)
    if(!str_contains($support,$forbidden))throw new RuntimeException('The worker principal must refuse an administrative capability: '.$forbidden);
if(!str_contains($worker,'wp_set_current_user')||!str_contains($worker,'get_current_user_id')||!str_contains($worker,'finally'))throw new RuntimeException('The worker context must adopt, prove and restore the principal');
if(!str_contains($worker,'payment_worker_context_reentrant'))throw new RuntimeException('The worker context must refuse re-entry');
if(str_contains($worker,'user 1')||str_contains($worker,'add_role')||str_contains($worker,'add_cap'))throw new RuntimeException('The worker context must never escalate');
foreach($allSources as $source)if(str_contains($source['code'],'wp_set_current_user')&&!str_contains($source['file'],'PaymentExecutionWorkerContext'))throw new RuntimeException('Only the worker context may set a current user: '.$source['file']);
if(!str_contains($service,"'dzn_manage_payment_execution'"))throw new RuntimeException('The execution surface must keep its own actor rule');
if(str_contains($intake,'dzn_manage_payment_execution'))throw new RuntimeException('The event worker must never hold the execution capability');

// §8.4: hooks fire after the context exits and carry declared ids only.
if(!str_contains($service,'dzn_phase_2a2t_after_execution_command'))throw new RuntimeException('The execution hook is missing');
if(!str_contains($intake,'dzn_phase_2a2t_after_provider_event_decision'))throw new RuntimeException('The decision hook is missing');
$hookPos=strpos($service,'dzn_phase_2a2t_after_execution_command');
if(str_contains(substr($service,$hookPos,120),'wp_set_current_user'))throw new RuntimeException('The execution hook must fire outside the worker context');

// §13: read models and diagnostics are view-capability gated and secret-free.
if(!str_contains($seam,"'dzn_view_payment_execution_authority'"))throw new RuntimeException('The read models must require the view capability');
if(!str_contains($seam,'descriptorRefusals')||!str_contains($seam,'outstandingDispatches'))throw new RuntimeException('The dispatch diagnostics are incomplete');
if(!str_contains($seam,'refused_writes')||!str_contains($seam,'decrypt_failures'))throw new RuntimeException('The secret diagnostics are incomplete');

// §14: no scheduler is introduced, and nothing in Core names a provider SDK or performs I/O.
$core='';
foreach(glob($root.'/src/Core/Application/*.php') as $file)$core.=file_get_contents($file);
foreach(glob($root.'/src/Core/Application/PaymentExecution/*.php') as $file)$core.=file_get_contents($file);
foreach(array('wp_schedule_event','wp_schedule_single_event','new \\Cron','Stripe\\','stripe-php','curl_init','curl_exec','wp_remote_post','wp_remote_get') as $forbidden)
    if(str_contains($core,$forbidden))throw new RuntimeException('Core must hold no scheduler or outbound capability: '.$forbidden);
if(str_contains($rule,'DESCRIPTOR_VERIFY')||str_contains($seam,'file_get_contents'))throw new RuntimeException('The seam must perform no I/O');

// §12.4: the verifier rejects the declared malformed shapes and re-runs the R1/R2 verifiers.
foreach(array(
    "table outside the declared Phase 2A.2-T set","must use InnoDB","identity contract","unique public handle",
    "provider-neutral and secret-free","only the dispatch claim may carry a sealed envelope","a raw reference column may not exist",
    "evidence must be append-only","digest nullability","reference type","reference parent","reference parent identity",
    "reference index","secret scope index","may never be unscoped","two live dispatch claims share one arbitration subject",
    "a claim may only coexist with its own descriptor refusal","an incomplete sealed descriptor","non-positive dispatch generation",
    "must not own academic, notification or settlement storage",
) as $needle)if(!str_contains($migration,$needle))throw new RuntimeException('The Phase T verifier is incomplete: '.$needle);
foreach(array("'payment_terms'","'payment_lessons'","'payment_schedules'","'payment_notifications'","'payment_evidence'","'payment_settlements'") as $table)
    if(!str_contains($migration,$table))throw new RuntimeException('The phase-owned academic table rejection is incomplete');
if(!str_contains($migration,'externalParents')||!str_contains($migration,"'commercial_offer_obligations'")||!str_contains($migration,"'collection_intents'"))throw new RuntimeException('The frozen external authoritative parents are missing');

// §17: every required suite exists and the concurrency runner names every required mode.
foreach(array($root.'/tests/phase-2a2t-migration-runtime.php',$root.'/tests/phase-2a2t-runtime.php',$root.'/tests/phase-2a2t-webhook-runtime.php',$root.'/tests/phase-2a2t-secret-runtime.php',$root.'/tests/phase-2a2t-corruption-runtime.php',$root.'/tests/phase-2a2t-failure-runtime.php',$root.'/tests/phase-2a2t-concurrency-runner.sh') as $suite)
    if(!is_file($suite))throw new RuntimeException('A required Phase T suite is missing: '.basename($suite));
foreach(array(
    'duplicate_webhook','out_of_order_event','submit_vs_cancel','settlement_vs_attempt','mapping_change_vs_intake',
    'secret_rotation_vs_intake','unrelated_students','duplicate_command_replay','settlement_vs_r2_consequence',
    'submit_vs_cancel_in_flight','redrive_after_crash','concurrent_expired_lease','takeover_reissue_fenced',
    'fenced_settlement_lost','initial_dispatch_descriptor_failure','post_preflight_capability_failure',
    'conflicting_duplicate_webhook',
) as $mode)if(!str_contains($concurrency,$mode))throw new RuntimeException('The concurrency runner must cover: '.$mode);
if(!str_contains($concurrency,'webhook_delivery'))throw new RuntimeException('[C8-3] the duplicate-webhook race must actually deliver a provider event');
if(!str_contains($concurrency,'body_changed'))throw new RuntimeException('[C8-3] the conflicting duplicate must re-deliver one event identity with different facts');
if(!str_contains($concurrency,'converge on exactly one recorded event'))throw new RuntimeException('[C8-3] the duplicate race must assert one recorded event');
foreach(array('retained','repeat','fresh','active_slot','descriptor_ciphertext','claim_generation') as $needle)
    if(!str_contains($migrationRuntime,$needle))throw new RuntimeException('The migration-runtime suite is incomplete: '.$needle);
foreach(array('redrive','reconcile','dispatch_descriptor_unavailable','provider_credentials_unconfigured','live_execution_not_authorised','dispatch_in_flight') as $needle)
    if(!str_contains($runtime,$needle))throw new RuntimeException('The runtime suite is incomplete: '.$needle);
foreach(array('signature_invalid','signature_outside_tolerance','webhook_account_unresolved','conflicting_provider_event','stale_provider_event','payment_worker_principal_required','method_not_allowed','payload_too_large','request_digest','unmapped_provider_object','ambiguous_obligation_attribution','active_slot') as $needle)
    if(!str_contains($webhookRuntime,$needle))throw new RuntimeException('The webhook suite is incomplete: '.$needle);
foreach(array('provider_secret_write_not_authorised','write_refused','decrypt_failed','REDACTED_SECRET') as $needle)
    if(!str_contains($secretRuntime,$needle))throw new RuntimeException('The secret suite is incomplete: '.$needle);
foreach(array('result_id','dispatches','transplanted','generation') as $needle)
    if(!str_contains($corruption,$needle))throw new RuntimeException('The corruption suite is incomplete: '.$needle);
foreach(array('rollback','worker principal','claim','result') as $needle)
    if(!str_contains($failure,$needle))throw new RuntimeException('The failure suite is incomplete: '.$needle);

// The network-free contract adapter is the only provider the suites may exercise.
if(!str_contains($contractAdapter,'class ContractPaymentAdapter'))throw new RuntimeException('The network-free contract adapter is missing');
if(str_contains($contractAdapter,'wp_remote_post')||str_contains($contractAdapter,'curl_'))throw new RuntimeException('The contract adapter must be network-free');
echo "phase-2a2t-contract: OK\n";
