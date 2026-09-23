#!/usr/bin/env bash
set -euo pipefail
source "$(dirname "${BASH_SOURCE[0]}")/common.sh"
require_env_file

echo "=== Fresh-schema migration test: zero -> Schema 25 ==="

# 1. Start from a genuinely empty database + WordPress directory.
if [ -d "${DZN_WP_DIR}" ]; then
  rm -rf "${DZN_WP_DIR}"
fi
dzn_compose down --volumes --remove-orphans
dzn_compose up -d --wait db
dzn_compose up -d wordpress cli mailpit

# 2. WordPress core + wp-config.php are populated by the wordpress image entrypoint.
"${DZN_BIN_DIR}/install-wordpress.sh"

# 3. Activating the plugin runs migrations 001..025 against an empty database.
"${DZN_BIN_DIR}/install-plugin.sh"

# 4. Assert the ledger reached Schema 25 exactly once, end to end.
"${DZN_BIN_DIR}/verify-schema25.sh"

echo
echo "Fresh-schema migration test: PASS (zero -> Schema 25)"
