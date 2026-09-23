#!/usr/bin/env bash
set -euo pipefail
source "$(dirname "${BASH_SOURCE[0]}")/common.sh"

if ! dzn_wp core is-installed >/dev/null 2>&1; then
  echo "error: WordPress is not installed. Run bin/up.sh && bin/install-wordpress.sh && bin/install-plugin.sh first." >&2
  exit 1
fi

MODES="${DZN_CONCURRENCY_MODES:-duplicate_evidence handoff_vs_schedule settlement_vs_lesson_seven promotion_global_limit conflicting_evidence_replay release_vs_satisfaction unrelated_commitments unattributed_conflict unattributed_convergence}"

export DZN_PHASE_2A2R1_REPO="${DZN_REPO_ROOT}"
export DZN_PHASE_2A2R1_WP_DIR="${DZN_WP_DIR}"
export DZN_PHASE_2A2R1_NET="${DZN_NETWORK}"

echo "=== Phase 2A.2-R1 concurrency suite (isolated DB state per mode) ==="
fail=0
for mode in ${MODES}; do
  echo "--- mode: ${mode} ---"
  if "${DZN_REPO_ROOT}/tests/phase-2a2r1-concurrency-runner.sh" "${mode}"; then
    echo "PASS  ${mode}"
  else
    echo "FAIL  ${mode}"
    fail=1
  fi
done

echo
if [ "${fail}" -ne 0 ]; then
  echo "Concurrency suite: FAILURES"
  exit 1
fi
echo "Concurrency suite: PASS"
