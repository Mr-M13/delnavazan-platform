#!/usr/bin/env bash
set -euo pipefail
source "$(dirname "${BASH_SOURCE[0]}")/common.sh"

echo "Verifying Schema 25 migration ledger and storage..."
dzn_wp eval '
$v = get_option("dzn_platform_schema_version");
$done = (array) get_option("dzn_platform_completed_migrations", array());
echo "schema_version=" . $v . "\n";
echo "migrations_recorded=" . count($done) . "\n";
$expected = array("001_initial_core_schema","025_commercial_purchase_funding_authority");
if ((string)$v !== DZN_PLATFORM_SCHEMA_VERSION) { fwrite(STDERR,"schema identity mismatch\n"); exit(1); }
if (!in_array($expected[1], $done, true)) { fwrite(STDERR,"migration 025 not recorded\n"); exit(1); }
echo "schema_25_ok\n";
'
echo "Schema 25 verification complete."
