#!/usr/bin/env bash
set -euo pipefail
source "$(dirname "${BASH_SOURCE[0]}")/common.sh"
require_env_file

echo "=============================================================="
echo " Delnavazan Platform — local disposable runtime acceptance"
echo "=============================================================="
dzn_status
echo

"${DZN_BIN_DIR}/up.sh"
echo
"${DZN_BIN_DIR}/run-fresh-migration.sh"
echo
"${DZN_BIN_DIR}/run-pure-tests.sh"
echo
"${DZN_BIN_DIR}/run-runtime-tests.sh"
echo
"${DZN_BIN_DIR}/run-concurrency.sh"
echo

echo "=== Clean rebuild proof (destroy -> rebuild -> retest) ==="
"${DZN_BIN_DIR}/destroy.sh" <<<"YES"
echo
"${DZN_BIN_DIR}/up.sh"
echo
"${DZN_BIN_DIR}/run-fresh-migration.sh"
echo
"${DZN_BIN_DIR}/run-pure-tests.sh"
echo

echo "=============================================================="
echo " ACCEPTANCE COMPLETE"
echo "=============================================================="
echo " Remaining (optional): bin/run-upgrade-rehearsal.sh"
