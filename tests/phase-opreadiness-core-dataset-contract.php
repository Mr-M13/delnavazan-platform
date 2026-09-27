<?php
/**
 * Source contract for the Schema-033 Core-dataset readiness slice.
 *
 * WordPress-free: it reads the tree and proves the three reviewed corrections — the Schema-033
 * migration is locked, a reconciliation run is persisted, and an exact operator replay converges —
 * so a later edit cannot silently reopen any of them.
 */
$root=dirname(__DIR__);
$migrator=file_get_contents($root.'/src/Core/Infrastructure/Migration/Migrator.php');
$service=file_get_contents($root.'/src/Core/Application/CoreDatasetReadinessService.php');
$plugin=file_get_contents($root.'/delnavazan-platform.php');

function dzn_readiness_assert(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);}

// §1 Schema 033 is a locked migration, verified before it is recorded and before the option advances.
dzn_readiness_assert(str_contains($migrator,"'033_core_dataset_technical_prerequisites'=>array(__CLASS__,'install_core_dataset_technical_prerequisites')"),'Schema 033 must be a member of the locked migration map');
dzn_readiness_assert(str_contains($migrator,'private static function install_core_dataset_technical_prerequisites'),'Schema 033 must own its install step');
dzn_readiness_assert(str_contains($migrator,'private static function verify_core_dataset_technical_prerequisites_schema'),'Schema 033 must own its verifier');
$verified=strpos($migrator,"if(\$id==='033_core_dataset_technical_prerequisites')self::verify_core_dataset_technical_prerequisites_schema();");
$recorded=strpos($migrator,'$done[]=$id;');
$advanced=strpos($migrator,'update_option( self::OPTION, DZN_PLATFORM_SCHEMA_VERSION, false );');
dzn_readiness_assert($verified!==false&&$recorded!==false&&$advanced!==false,'The migration loop must verify Schema 033, record it and advance the option');
dzn_readiness_assert($verified<$recorded&&$recorded<$advanced,'Schema 033 must be verified before it is recorded and before the schema option advances');
dzn_readiness_assert(str_contains($migrator,"'032_notification_communications_authority', '033_core_dataset_technical_prerequisites' );"),'The current-schema check must require the Schema-033 ledger entry');
dzn_readiness_assert(str_contains($migrator,'self::verify_portal_facing_services_schema(); self::verify_notification_communications_schema(); self::verify_core_dataset_technical_prerequisites_schema();'),'The current-schema check must run the Schema-033 verifier');
dzn_readiness_assert(!str_contains($migrator,'ensure_core_dataset_technical_prerequisites')&&!str_contains($plugin,'ensure_core_dataset_technical_prerequisites'),'The separate post-upgrade installation path must be gone');

// §2 A reconciliation run is persisted as append-only evidence; no Core row is written or repaired.
dzn_readiness_assert(str_contains($service,"SELECT id,uid,created_by,created_at FROM {\$table}{\$where} ORDER BY id ASC"),'The projection must stay a bounded read of the four declared columns');
dzn_readiness_assert(str_contains($service,'core_dataset_reconciliation_runs')&&str_contains($service,'core_dataset_reconciliation_findings'),'Both reconciliation evidence tables must be written');
dzn_readiness_assert(str_contains($service,"'scope_kind'=>\$scope")&&str_contains($service,"'match_state'=>")&&str_contains($service,"'recorded_by'=>\$actor"),'The run row must record its scope, match state and actor');
dzn_readiness_assert(str_contains($service,"'expected_count'=>\$expectedCount")&&str_contains($service,"'actual_count'=>\$actualCount")&&str_contains($service,"'expected_digest'=>\$expectedDigest")&&str_contains($service,"'actual_digest'=>\$actualDigest"),'The run row must record the expected and actual counts and digests');
dzn_readiness_assert(str_contains($service,"'scope_count_mismatch'")&&str_contains($service,"'scope_digest_mismatch'"),'A mismatch must record its deterministic findings');
dzn_readiness_assert(str_contains($service,"'run_id'=>\$runId"),'The recorded run id must be returned');

// §3 An exact replay converges on the recorded row; only a conflicting payload fails closed.
dzn_readiness_assert(str_contains($service,'command_payload_digest FROM {$table} WHERE command_key_digest=%s'),'The operator recorder must load the row its command key already produced');
dzn_readiness_assert(substr_count($service,'WHERE command_key_digest=%s')>=2,'A lost insert race must re-read the recorded row');
dzn_readiness_assert(str_contains($service,'Core operator evidence replay conflict'),'A same-key/different-payload replay must fail closed');
$replay=strpos($service,'private static function replayedCommandId');
dzn_readiness_assert($replay!==false&&strpos($service,'self::replayedCommandId($existing,$payloadDigest)')!==false,'Both replay paths must converge through the recorded row');

echo "Core-dataset readiness contract passed\n";
