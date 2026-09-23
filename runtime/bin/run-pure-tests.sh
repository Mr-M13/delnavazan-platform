#!/usr/bin/env bash
set -euo pipefail
source "$(dirname "${BASH_SOURCE[0]}")/common.sh"

echo "Running pure-PHP tests (no WordPress/database required)..."

PURE=(
  tests/static.php
  tests/schema-contract.php
  tests/fresh-install-capability-bootstrap.php
  tests/migrator-runtimeexception-runtime.php
  tests/phase-2a1b-time.php
  tests/phase-2a1c-time.php
  tests/phase-2a2a-capability-lifecycle.php
  tests/phase-2a2p-overlap-runtime.php
  tests/phase-2a2g-contract-mutations.php
)

# Every contract test is a source-string guard and is runnable without WordPress.
shopt -s nullglob
for f in "${DZN_REPO_ROOT}"/tests/*-contract.php; do
  PURE+=( "${f#${DZN_REPO_ROOT}/}" )
done

fail=0
for rel in "${PURE[@]}"; do
  abs="${DZN_REPO_ROOT}/${rel}"
  if [ ! -f "${abs}" ]; then
    echo "SKIP  ${rel} (missing)"
    continue
  fi
  if docker run --rm \
      -v "${DZN_REPO_ROOT}:${DZN_REPO_ROOT}" \
      "${DZN_CLI_IMAGE}" \
      php "${abs}" >/tmp/dzn-pure.out 2>&1; then
    echo "PASS  ${rel}"
  else
    echo "FAIL  ${rel}"
    sed 's/^/      /' /tmp/dzn-pure.out
    fail=1
  fi
done

echo
if [ "${fail}" -ne 0 ]; then
  echo "Pure-PHP tests: FAILURES"
  exit 1
fi
echo "Pure-PHP tests: PASS"
