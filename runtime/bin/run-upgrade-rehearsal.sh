#!/usr/bin/env bash
set -euo pipefail
source "$(dirname "${BASH_SOURCE[0]}")/common.sh"
require_env_file

# The direct Schema-24 predecessor of migration 025. Confirm with:
#   git log --oneline 10fe406^ -1
DZN_PRE25_REF="${DZN_PRE25_REF:-1b9d7aa}"

ORIGINAL_HEAD="$(git -C "${DZN_REPO_ROOT}" rev-parse HEAD)"
ORIGINAL_BRANCH="$(git -C "${DZN_REPO_ROOT}" branch --show-current)"

restore() {
  git -C "${DZN_REPO_ROOT}" checkout --quiet "${ORIGINAL_HEAD}" 2>/dev/null || \
    git -C "${DZN_REPO_ROOT}" checkout --quiet "${ORIGINAL_BRANCH}" 2>/dev/null || true
}
trap restore EXIT

if [ -n "$(git -C "${DZN_REPO_ROOT}" status --porcelain --untracked-files=no)" ]; then
  echo "error: the repository has uncommitted tracked changes. Commit or stash them before the upgrade rehearsal." >&2
  exit 1
fi

echo "=== Upgrade rehearsal: Schema 24 (${DZN_PRE25_REF}) -> Schema 25 (${ORIGINAL_HEAD}) ==="

# 1. Move the checkout to the Schema-24 state and install a fresh database there.
git -C "${DZN_REPO_ROOT}" checkout --quiet "${DZN_PRE25_REF}"

if [ -d "${DZN_WP_DIR}" ]; then rm -rf "${DZN_WP_DIR}"; fi
dzn_compose down --volumes --remove-orphans
dzn_compose up -d --wait db
dzn_compose up -d wordpress cli mailpit
"${DZN_BIN_DIR}/install-wordpress.sh"
"${DZN_BIN_DIR}/install-plugin.sh"

echo "--- asserting the Schema 24 base state ---"
dzn_wp eval '
if ((string) get_option("dzn_platform_schema_version") !== "24") {
  fwrite(STDERR, "expected Schema 24 base, got " . get_option("dzn_platform_schema_version") . "\n");
  exit(1);
}
echo "schema_24_base_ok\n";
'

# 2. Return to the Schema-25 checkout and run migration 025 in place.
git -C "${DZN_REPO_ROOT}" checkout --quiet "${ORIGINAL_HEAD}"

echo "--- running migration 024 -> 025 in place ---"
dzn_wp eval '
\Delnavazan\Platform\Core\Infrastructure\Migration\Migrator::maybe_upgrade();
'

"${DZN_BIN_DIR}/verify-schema25.sh"

# 3. Re-run the R1 migration-runtime verifier (repeat-safety + fail-closed verifier).
dzn_wp_env "DZN_PHASE_2A2R1_RUNTIME_TEST=migration" -- \
  eval-file "${DZN_REPO_ROOT}/tests/phase-2a2r1-migration-runtime.php" --user=1

echo
echo "Upgrade rehearsal: PASS (Schema 24 -> Schema 25)"
