#!/usr/bin/env bash
# The only no-runtime evidence: parse/source-contract tests inside the cached CLI image.
set -euo pipefail
source "$(dirname "$BASH_SOURCE")/common.sh"
dzn_require_cached_images; dzn_candidate; dzn_assert_runtime_paths
tests=(tests/static.php tests/schema-contract.php tests/phase-2a2w-contract.php)
for test in "${tests[@]}"; do
  [ -f "$DZN_PLUGIN_WORKTREE/$test" ] || continue
  docker run --rm --pull=never -v "$DZN_PLUGIN_WORKTREE:$DZN_PLUGIN_WORKTREE:ro" --entrypoint php "$DZN_CLI_IMAGE" "$DZN_PLUGIN_WORKTREE/$test"
  echo "PASS  $test"
done
