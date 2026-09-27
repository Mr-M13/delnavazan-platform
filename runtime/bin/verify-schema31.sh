#!/usr/bin/env bash
set -euo pipefail
source "$(dirname "$BASH_SOURCE")/common.sh"
dzn_wp eval '
global $wpdb;
$expected=array("portal_lesson_capability_roots","portal_public_capabilities","portal_public_capability_events","portal_public_capability_commands","portal_public_action_events","portal_access_denials");
$p=$wpdb->prefix."dzn_";
if ((string)get_option("dzn_platform_schema_version") !== "31") throw new RuntimeException("expected Schema 31");
$done=(array)get_option("dzn_platform_completed_migrations",array());
if (!in_array("031_portal_facing_services_principal_authorization",$done,true)) throw new RuntimeException("migration 031 missing from ledger");
foreach($expected as $table) if ((string)$wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s",$p.$table)) !== $p.$table) throw new RuntimeException("missing portal table ".$table);
if (get_option("dzn_platform_portal_actions",null)!==null) throw new RuntimeException("portal public action option was seeded");
echo "schema_31_ok
";
'
