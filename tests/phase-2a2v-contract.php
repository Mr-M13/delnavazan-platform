<?php
/** Phase 2A.2-V provider-neutral Google Calendar & Meet integration source contract. */
$root=dirname(__DIR__);
$plugin=file_get_contents($root.'/delnavazan-platform.php');
$migration=file_get_contents($root.'/src/Core/Infrastructure/Migration/Migrator.php');
$rule=file_get_contents($root.'/src/Core/Application/ProviderIntegrationRule.php');
$idempotency=file_get_contents($root.'/src/Core/Application/ProviderIntegrationIdempotency.php');
$secrets=file_get_contents($root.'/src/Core/Application/IntegrationSecretService.php');
$validator=file_get_contents($root.'/src/Core/Application/ProviderIntegrationValidator.php');
$service=file_get_contents($root.'/src/Core/Application/ProviderIntegrationService.php');
$ingest=file_get_contents($root.'/src/Core/Application/ProviderEventIngestService.php');
$read=file_get_contents($root.'/src/Core/Application/ProviderIntegrationReadService.php');
$repository=file_get_contents($root.'/src/Core/Infrastructure/Repository/ProviderIntegrationRepository.php');
$adapter=file_get_contents($root.'/src/Integrations/GoogleCalendarMeetAdapter.php');
$contractAdapters=file_get_contents($root.'/src/Integrations/ContractProviderAdapters.php');
$ports='';
foreach(array('ProviderOAuthPort','ProviderCalendarPort','ProviderMeetingPort','ProviderEventNormalizer') as $port)$ports.=file_get_contents($root.'/src/Core/Application/Port/'.$port.'.php');
$phaseV=$rule.$idempotency.$secrets.$validator.$service.$ingest.$read.$repository.$adapter.$contractAdapters.$ports;
$runtime=file_get_contents($root.'/tests/phase-2a2v-runtime.php');
$migrationRuntime=file_get_contents($root.'/tests/phase-2a2v-migration-runtime.php');
$corruption=file_get_contents($root.'/tests/phase-2a2v-corruption-runtime.php');
$failure=file_get_contents($root.'/tests/phase-2a2v-failure-runtime.php');
$concurrency=file_get_contents($root.'/tests/phase-2a2v-concurrency-runner.sh').file_get_contents($root.'/tests/phase-2a2v-concurrency-setup.php').file_get_contents($root.'/tests/phase-2a2v-concurrency-worker.php').file_get_contents($root.'/tests/phase-2a2v-concurrency-verify.php');

// Build/schema identity: Phase V is Schema 27, sequenced additively after the R2 candidate.
if(!preg_match("/DZN_PLATFORM_SCHEMA_VERSION', '([0-9]+)'/",$plugin,$schema)||(int)$schema[1]<27)throw new RuntimeException('Missing Phase V schema identity');
if(!preg_match("/DZN_PLATFORM_BUILD_ID', 'phase2a2v-[a-z0-9-]+-[0-9]{8}\.[0-9]+'/",$plugin))throw new RuntimeException('Missing Phase V build identity');

// Migration, storage, verifier wiring and capabilities.
foreach(array(
    '027_google_calendar_meet_provider_integration','install_google_calendar_meet_provider_integration','verify_google_calendar_meet_provider_integration_schema',
    'integration_connections','integration_credentials','integration_oauth_authorizations',
    'provider_identity_mappings','provider_calendar_event_mappings','provider_meeting_mappings',
    'provider_ingest_events','provider_event_conflicts','provider_integration_commands',
    'dzn_connect_own_provider_calendar','dzn_manage_provider_integrations','dzn_revoke_provider_integrations',
    'dzn_ingest_provider_events','dzn_view_provider_integrations','dzn_platform_capability_version_2a2v',
) as $needle)if(!str_contains($migration.$plugin,$needle))throw new RuntimeException('Missing Phase V migration contract: '.$needle);
if(!str_contains($migration,"if(\$id==='027_google_calendar_meet_provider_integration')self::verify_google_calendar_meet_provider_integration_schema();"))throw new RuntimeException('Migration 027 must invoke the Phase V schema verifier before it is recorded');
if(substr_count($migration,'self::verify_google_calendar_meet_provider_integration_schema();')<3)throw new RuntimeException('Phase V verifier must run after migration 027, on current-schema verification and before schema activation');
if(!str_contains($migration,"in_array( '027_google_calendar_meet_provider_integration', (array) get_option( self::COMPLETED, array() ), true )"))throw new RuntimeException('Retained-027 pre-activation verification is missing');
if(!str_contains($migration,"'027_google_calendar_meet_provider_integration' )"))throw new RuntimeException('Phase V migration must be listed as required');
$installStart=strpos($migration,'private static function install_google_calendar_meet_provider_integration');
$installEnd=strpos($migration,'private static function verify_google_calendar_meet_provider_integration_schema');
$install=substr($migration,$installStart,$installEnd-$installStart);
$install=substr($install,strpos($install,'{')); // body only: the signature names the phase, the storage must not
if(str_contains($install,'UPDATE ')||str_contains($install,'INSERT INTO'))throw new RuntimeException('Phase V migration must be additive only');
if(stripos($install,'google_')!==false||stripos($install,'zoom')!==false||stripos($install,'teams')!==false)throw new RuntimeException('Phase V storage must stay provider-neutral');
if(stripos($install,'token')!==false||stripos($install,'secret')!==false||stripos($install,'password')!==false)throw new RuntimeException('Phase V domain storage must never carry a plaintext credential column');
preg_match_all('/CREATE TABLE \{\$p\}([a-z_]+)/',$install,$created);
$declaredTables=array('integration_connections','integration_credentials','integration_oauth_authorizations','provider_identity_mappings','provider_calendar_event_mappings','provider_meeting_mappings','provider_ingest_events','provider_event_conflicts','provider_integration_commands');
$createdTables=$created[1];sort($createdTables);sort($declaredTables);
if($createdTables!==$declaredTables)throw new RuntimeException('Migration 027 must create exactly the nine declared Phase V tables');
if(count(array_unique($createdTables))!==9)throw new RuntimeException('Migration 027 must declare each Phase V table exactly once');
if(!str_contains($migration,'$phaseVGrants')||!str_contains($migration,'$teacherVRole'))throw new RuntimeException('Phase V capability repair must ensure every grant and deny the Teacher role');

// Locked Phase-V owner decisions and controlled vocabularies.
foreach(array(
    "PROVIDER_CODES=array('google_calendar','google_meet')","EVIDENCE_PROVIDER_CODES=array('google_meet')",
    "PURPOSES=array('connection_identity','calendar_event','meeting_conference')",
    "CONNECTION_STATES=array('disconnected','authorizing','connected','refresh_failed','revoking','revoke_failed','revoked')",
    "CREDENTIAL_STATES=array('active','quarantined','revoked')","MAPPING_STATES=array('verified','unverified','revoked')",
    "CONFLICT_KINDS=array('changed_payload','cross_lesson','cross_schedule_version','cross_context','cross_participant','cross_interval')",
    "COMMAND_DOMAIN='provider_integration_v1'","RULE_VERSION='provider_neutral_google_calendar_meet_v1'",
    "EVIDENCE_CHANNELS=array('staff_record','authenticated_platform','document_reference','provider_callback','system_ingest')",
) as $needle)if(!str_contains($rule,$needle))throw new RuntimeException('Missing Phase V vocabulary: '.$needle);
if(!str_contains($rule,'public static function canTransition')||!str_contains($rule,'public static function sameRedirectUri'))throw new RuntimeException('Phase V rule must own the lifecycle transition and exact redirect comparison');

// V-D4: authenticated encryption, domain separation, versioned key/cipher, fail-closed decrypt.
foreach(array('sodium_crypto_secretbox','sodium_crypto_secretbox_open','CIPHER_VERSION','KEY_VERSION','random_bytes','base64_encode','hash_hmac','wp_salt') as $needle)
    if(!str_contains($secrets,$needle))throw new RuntimeException('Credential sealing boundary is incomplete: '.$needle);
if(!str_contains($secrets,"'key_version'")||!str_contains($secrets,"'cipher_version'")||!str_contains($secrets,"'nonce'")||!str_contains($secrets,"'ciphertext'"))throw new RuntimeException('Credential storage shape must be ciphertext/nonce plus key and cipher version');
if(str_contains($secrets,"'access_token'")||str_contains($secrets,"'refresh_token'"))throw new RuntimeException('Credential storage must never declare a token column');
if(substr_count($secrets,'throw new \\RuntimeException')<4)throw new RuntimeException('Credential opening must fail closed on every malformed input');
if(!str_contains($secrets,'binding mismatch'))throw new RuntimeException('A sealed credential must be bound to exactly one connection');

// V-D10/V-D11: digest-only keys, payloads and provider references.
foreach(array('provider_integration_key:','provider_integration_event_key:','provider_integration_payload:','provider_integration_evidence:','provider_integration_subject:','hash_hmac','sha256') as $needle)
    if(!str_contains($idempotency,$needle))throw new RuntimeException('Digest boundary is incomplete: '.$needle);
if(!str_contains($idempotency,'ksort'))throw new RuntimeException('Payload digests must be canonicalised so an exact replay reconstructs the same digest');
if(!str_contains($service,'hash_equals((string)$command->command_payload_digest,$payload)'))throw new RuntimeException('Replay must compare the reconstructed payload digest unconditionally');
if(!str_contains($service,'IdempotencyConflictException'))throw new RuntimeException('A divergent replay must fail as an idempotency conflict');
foreach(array('raw_key','payload_json','access_token','refresh_token') as $forbidden)if(str_contains($migration,"`{$forbidden}`"))throw new RuntimeException('Integration storage must stay digest-only: '.$forbidden);

// V-D5/V-D6/V-D7/V-D8: mappings are references; M/N/O/P authority is never written by V.
if(!str_contains($validator,'CanonicalLessonScheduleValidator::validForLesson'))throw new RuntimeException('A projection must compose the canonical schedule validator');
if(!str_contains($validator,'CanonicalLessonDeliveryValidator::validForLesson'))throw new RuntimeException('Occurrence validity must compose the canonical delivery validator');
if(!str_contains($validator,'stale_schedule_version'))throw new RuntimeException('A projection against a stale schedule version must fail closed');
if(!str_contains($service,'CanonicalAttendanceIntakeService')&&!str_contains($ingest,'CanonicalAttendanceIntakeService'))throw new RuntimeException('Provider facts must enter through the Phase-P intake seam');
if(!str_contains($ingest,'ingestProviderEvidence'))throw new RuntimeException('Phase V must delegate provider evidence to CanonicalAttendanceIntakeService::ingestProviderEvidence');
if(!str_contains($ingest,'conflict_kind'))throw new RuntimeException('A changed provider-event context must leave a durable conflict receipt');
if(str_contains($ingest,'canonical_lesson_lifecycle_events')||str_contains($ingest,'canonical_attendance_evidence'))throw new RuntimeException('The integration layer must never write Phase-M/P storage directly');
if(str_contains($service,'UPDATE '))throw new RuntimeException('The integration authority must never write canonical storage with raw SQL');
if(!str_contains($ingest,'CanonicalAttendanceIdentityService')&&str_contains($ingest,'resolved_teacher_id'))throw new RuntimeException('Phase V must never assert a resolved participant');
foreach(array('sign_','signed_capability','public_join') as $forbidden)if(str_contains($phaseV,$forbidden))throw new RuntimeException('Phase V must never mint a public capability');

// V-D13 least privilege: capability-gated commands and object-level authorisation.
foreach(array('dzn_connect_own_provider_calendar','dzn_manage_provider_integrations','dzn_revoke_provider_integrations','dzn_ingest_provider_events','dzn_view_provider_integrations') as $capability)
    if(!str_contains($service,$capability))throw new RuntimeException('Phase V capability wiring is incomplete: '.$capability);
if(!str_contains($service,'teacher_principal_links')&&!str_contains($repository,'teacher_principal_links'))throw new RuntimeException('Teacher self-connect must revalidate the Phase-J principal link');
if(!str_contains($read,'authorizeTeacher')||!str_contains($read,'authorizeLesson'))throw new RuntimeException('Reads must enforce object-level authorisation against the exact Teacher and Lesson');

// V-D12/V-D14: no migration-time or build-time external call, credential read or provider traffic anywhere.
foreach(array('wp_remote_','wp_safe_remote_','curl_','fsockopen','stream_socket_client','new \\SoapClient','google-api-php-client') as $forbidden)
    if(str_contains($phaseV.$install,$forbidden))throw new RuntimeException('Phase V must make no external call: '.$forbidden);
if(str_contains($install,'get_option')||str_contains($install,'get_transient'))throw new RuntimeException('The migration must not read runtime configuration or a credential');
if(str_contains($phaseV,'Integrations\\GoogleCalendarMeetAdapter')&&str_contains($service,'Integrations\\'))throw new RuntimeException('Core must not depend on the Integrations module');
foreach(glob($root.'/src/Core/Application/*.php') as $file)
    if(str_contains((string)file_get_contents($file),'Delnavazan\\Platform\\Integrations\\'))throw new RuntimeException('Core must not import the Integrations module: '.basename($file));

// §13 test surface: every artefact exists, is gated on the disposable harness and covers its subject.
foreach(array(
    array($migrationRuntime,'DZN_PHASE_2A2V_RUNTIME_TEST','migration'),array($runtime,'DZN_PHASE_2A2V_RUNTIME_TEST','authority'),
    array($corruption,'DZN_PHASE_2A2V_RUNTIME_TEST','corruption'),array($failure,'DZN_PHASE_2A2V_RUNTIME_TEST','failure'),
) as $gate)if(!str_contains($gate[0],$gate[1])||!str_contains($gate[0],$gate[2])||!str_contains($gate[0],'wp_get_environment_type'))throw new RuntimeException('Phase V runtime artefact must be harness-gated: '.$gate[2]);
foreach(array('connect','revoke','authorization','projection','completion','duplicate','conflict','archival','unrelated') as $mode)
    if(!str_contains($concurrency,$mode))throw new RuntimeException('Concurrency matrix is missing mode: '.$mode);
if(!str_contains($contractAdapters,'implements ProviderOAuthPort')||!str_contains($contractAdapters,'ProviderCalendarPort')||!str_contains($contractAdapters,'ProviderMeetingPort')||!str_contains($contractAdapters,'ProviderEventNormalizer'))throw new RuntimeException('The deterministic contract adapters must implement all four ports');
if(!str_contains($contractAdapters,'[redacted]'))throw new RuntimeException('The deterministic adapters must never record credential material');
if(str_contains($contractAdapters,'wp_remote_')||str_contains($adapter,'wp_remote_'))throw new RuntimeException('The adapter seam must make no provider call');
if(!str_contains($adapter,'code_challenge_method')||!str_contains($adapter,'PKCE')&&!str_contains($adapter,'S256'))throw new RuntimeException('The Google translation seam must render a PKCE-bound authorization request');
echo "Phase 2A.2-V contract static test passed\n";
