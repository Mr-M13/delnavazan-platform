#!/usr/bin/env bash
set -euo pipefail
source "$(dirname "${BASH_SOURCE[0]}")/common.sh"

if ! dzn_wp core is-installed >/dev/null 2>&1; then
  echo "error: WordPress is not installed. Run bin/up.sh && bin/install-wordpress.sh && bin/install-plugin.sh first." >&2
  exit 1
fi

echo "=== Building the Phase 2A.2-J production fixture ==="
dzn_wp_env "DZN_PHASE_2A2J_RUNTIME_TEST=fixture" -- \
  eval-file "${DZN_REPO_ROOT}/tests/phase-2a2j-fixture.php" --user=1

echo
echo "=== Phase 2A.2-R1 runtime tests ==="

run_wp_test() {
  local label="$1" env="$2" file="$3"
  echo "--- ${label} ---"
  if dzn_wp_env "${env}" -- eval-file "${DZN_REPO_ROOT}/tests/${file}" --user=1; then
    echo "PASS  ${label}"
  else
    echo "FAIL  ${label}"
    exit 1
  fi
}

run_wp_test "R1 authority runtime"          "DZN_PHASE_2A2R1_RUNTIME_TEST=authority"  "phase-2a2r1-runtime.php"
run_wp_test "R1 migration runtime (24->25)" "DZN_PHASE_2A2R1_RUNTIME_TEST=migration"  "phase-2a2r1-migration-runtime.php"
run_wp_test "R1 failure runtime"            "DZN_PHASE_2A2R1_RUNTIME_TEST=failure"    "phase-2a2r1-failure-runtime.php"
run_wp_test "R1 corruption runtime"         "DZN_PHASE_2A2R1_RUNTIME_TEST=corruption" "phase-2a2r1-corruption-runtime.php"

echo
echo "Runtime tests: PASS"
