<?php
/** Phase 2A.2-P canonical attendance intake & review authority source contract. */
$root=dirname(__DIR__);
$plugin=file_get_contents($root.'/delnavazan-platform.php');
$migration=file_get_contents($root.'/src/Core/Infrastructure/Migration/Migrator.php');
$rule=file_get_contents($root.'/src/Core/Application/CanonicalAttendanceRule.php');
$intake=file_get_contents($root.'/src/Core/Application/CanonicalAttendanceIntakeService.php');
$settlement=file_get_contents($root.'/src/Core/Application/CanonicalAttendanceSettlementService.php');
$validator=file_get_contents($root.'/src/Core/Application/CanonicalAttendanceValidator.php');
$read=file_get_contents($root.'/src/Core/Application/CanonicalAttendanceReadService.php');
$repo=file_get_contents($root.'/src/Core/Infrastructure/Repository/CanonicalAttendanceRepository.php');
$phaseP=$rule.$intake.$settlement.$validator.$read.$repo;

if(!preg_match("/DZN_PLATFORM_SCHEMA_VERSION', '([0-9]+)'/",$plugin,$schema)||(int)$schema[1]<23)throw new RuntimeException('Missing Phase P schema identity');
if(!preg_match("/DZN_PLATFORM_BUILD_ID', 'phase2a2p-[a-z0-9-]+-[0-9]{8}\.[0-9]+'/",$plugin))throw new RuntimeException('Missing Phase P build identity');

// Migration, storage, verifier wiring, capabilities.
foreach(array('023_canonical_attendance_intake_authority','install_canonical_attendance_intake_authority','verify_canonical_attendance_intake_schema','canonical_attendance_cases','canonical_attendance_evidence','canonical_attendance_decisions','canonical_attendance_case_anomalies','canonical_attendance_commands','canonical_attendance_cutover_policies','dzn_ingest_canonical_attendance_evidence','dzn_submit_own_attendance_claim','dzn_submit_own_delivery_claim','dzn_manage_canonical_attendance_review','dzn_view_canonical_attendance_review')as$n)if(!str_contains($migration.$plugin,$n))throw new RuntimeException('Missing Phase P migration contract: '.$n);
if(!str_contains($migration,"if(\$id==='023_canonical_attendance_intake_authority')self::verify_canonical_attendance_intake_schema();"))throw new RuntimeException('Migration 023 must invoke the Phase P schema verifier before it is recorded');
if(substr_count($migration,'self::verify_canonical_attendance_intake_schema();')<3)throw new RuntimeException('Phase P verifier must run after migration 023, on current-schema verification and before schema activation');
if(!str_contains($migration,"in_array( '023_canonical_attendance_intake_authority', (array) get_option( self::COMPLETED, array() ), true )"))throw new RuntimeException('Retained-023 pre-activation verification is missing');
if(!str_contains($migration,"'023_canonical_attendance_intake_authority' )"))throw new RuntimeException('Phase P migration must be listed as required');
$install=substr($migration,strpos($migration,'private static function install_canonical_attendance_intake_authority'),strpos($migration,'private static function verify_canonical_attendance_intake_schema')-strpos($migration,'private static function install_canonical_attendance_intake_authority'));
if(substr_count($install,'updated_at datetime')!==1)throw new RuntimeException('Only the mutable intake case aggregate may carry updated_at; Phase P evidence must stay append-only');
if(str_contains($install,'UPDATE ')||str_contains($install,'INSERT INTO'))throw new RuntimeException('Phase P migration must be additive only: no backfill or legacy import');
if(stripos($install,'google')!==false)throw new RuntimeException('Phase P storage must not contain provider-specific columns');
if(str_contains($migration,"SHOW COLUMNS FROM {\$physical} LIKE '%google%'")){/* provider-neutrality guard present */}else throw new RuntimeException('Phase P verifier must reject provider-specific storage');

// Locked temporal window and threshold.
foreach(array("RULE_VERSION='canonical_attendance_overlap_v1'","THRESHOLD_SECONDS=1200","PRE_GRACE_SECONDS=0","POST_GRACE_SECONDS=900")as$n)if(!str_contains($rule,$n))throw new RuntimeException('Missing locked Phase P rule constant: '.$n);
if(!str_contains($rule,'$end+self::POST_GRACE_SECONDS'))throw new RuntimeException('Qualifying window must end at scheduled end plus the post-class grace');
if(!str_contains($rule,'$start+self::PRE_GRACE_SECONDS'))throw new RuntimeException('Qualifying window must start at the scheduled start with zero pre-class grace');
if(!str_contains($rule,'$seconds>=self::THRESHOLD_SECONDS'))throw new RuntimeException('Automatic success must require at least the locked threshold');
if(!str_contains($rule,'EXCLUDED_OPEN_INTERVAL')||!str_contains($rule,'EXCLUDED_IMPOSSIBLE_INTERVAL'))throw new RuntimeException('Open and impossible intervals must be explicitly excluded');
if(!str_contains($rule,'private static function union')||!str_contains($rule,'private static function intersect'))throw new RuntimeException('Overlap must union per participant then intersect');

// Intake is not delivery truth; settlement delegates to Phase O and Lesson authority.
foreach(array("const PROVIDER_CAPABILITY='dzn_ingest_canonical_attendance_evidence'","const STUDENT_CLAIM_CAPABILITY='dzn_submit_own_attendance_claim'","const TEACHER_CLAIM_CAPABILITY='dzn_submit_own_delivery_claim'","const REVIEW_CAPABILITY='dzn_manage_canonical_attendance_review'",'ingestProviderEvidence','submitClaim','adjudicate','assessAndDecide','cutover_policy_required','occurrence_before_cutover','schedule_version_conflict','provider_evidence_missing','overlap_below_threshold','teacher_participation_unproven','student_participation_unproven','late_evidence','canonical_outcome_exists','lesson_cancelled')as$n)if(!str_contains($intake,$n))throw new RuntimeException('Missing Phase P intake contract: '.$n);
if(!str_contains($intake,'new CanonicalLessonDeliveryService()')&&!str_contains($intake,'CanonicalLessonDeliveryService') )throw new RuntimeException('Phase P must delegate canonical outcomes to Phase O');
if(!str_contains($settlement,'CanonicalLessonDeliveryService')||!str_contains($settlement,'CanonicalLessonAuthorityService'))throw new RuntimeException('Settlement must delegate to Phase O and Lesson authority');
if(!str_contains($settlement,'attendance-settlement-outcome-')||!str_contains($settlement,'attendance-settlement-complete-'))throw new RuntimeException('Settlement must use deterministic staged convergence keys');
if(!str_contains($settlement,'attendance_settlement_incomplete'))throw new RuntimeException('Settlement must verify canonical truth before reporting success');
if(str_contains($intake,'INSERT INTO')||str_contains($settlement,'INSERT INTO'))throw new RuntimeException('Phase P must not write canonical storage with raw SQL');
foreach(array('canonical_lesson_delivery_outcomes','canonical_academy_obligations','canonical_replacement_origin_lesson_id')as$n)if(str_contains($phaseP,'INSERT INTO '.$n))throw new RuntimeException('Phase P must never insert canonical Phase-O/M storage directly: '.$n);
if(str_contains($intake,'createReplacement'))throw new RuntimeException('Phase P must never create Phase-M replacements');
if(str_contains($settlement,'owe('))throw new RuntimeException('Phase P must never create academy obligations directly');

// Automation may not be replaced by click/link evidence, and unresolved review is not auto-published.
if(stripos($phaseP,'join_click')!==false||stripos($phaseP,'link_click')!==false)throw new RuntimeException('A join/link click must never settle attendance');
$assessBody=substr($intake,strpos($intake,'private function assessAndDecide'),strpos($intake,'private function maybeSettle')-strpos($intake,'private function assessAndDecide'));
if(str_contains($assessBody,"'review_required'"))throw new RuntimeException('Phase P must not auto-publish Phase-O review_required');
if(str_contains($assessBody,"'teacher_non_delivery'"))throw new RuntimeException('Failed assessment must never identify Teacher responsibility automatically');
if(!str_contains($assessBody,"'ready_for_review'"))throw new RuntimeException('Phase P anomalies must surface as its own review state');
if(str_contains($settlement,"review_required")||str_contains($settlement,"teacher_non_delivery"))throw new RuntimeException('Automatic settlement must only ever publish delivered truth');
if(!str_contains($intake,'lesson_not_delivery_recordable'))throw new RuntimeException('Administrative publishing must respect Lesson lifecycle recordability');
if(!str_contains($settlement,'lesson_cancelled'))throw new RuntimeException('Settlement must refuse cancelled Lessons');

// Protected read composes canonical truth and stays provider-neutral.
foreach(array("const CAPABILITY='dzn_view_canonical_attendance_review'",'CanonicalLessonDeliveryValidator::effective','CanonicalAcademyObligationRepository','canonical_attendance_integrity_conflict','evidence_set_digest')as$n)if(!str_contains($read,$n))throw new RuntimeException('Missing Phase P protected read contract: '.$n);
if(stripos($read,'provider_payload')!==false&&!str_contains($read,'No raw provider payload'))throw new RuntimeException('Protected read must never expose raw provider payloads');

// Commands are digest-only and staged.
foreach(array('command_key_digest','provider_event_key_digest','provider_payload_digest','IdempotencyConflictException')as$n)if(!str_contains($intake.$repo,$n))throw new RuntimeException('Missing Phase P idempotency contract: '.$n);

foreach(array('phase-2a2p-runtime.php','phase-2a2p-overlap-runtime.php','phase-2a2p-corruption-runtime.php','phase-2a2p-failure-runtime.php','phase-2a2p-migration-runtime.php','phase-2a2p-concurrency-runner.sh','phase-2a2p-concurrency-setup.php','phase-2a2p-concurrency-worker.php','phase-2a2p-concurrency-wait.php','phase-2a2p-concurrency-verify.php')as$f)if(!is_file($root.'/tests/'.$f))throw new RuntimeException('Missing committed Phase P validation artefact: '.$f);
echo "phase-2a2p-contract: pass\n";
