#!/bin/sh
# Deterministic Phase 2A.2-T payment-execution race runner.
set -eu
: "${DZN_PHASE_2A2T_REPO:?}" "${DZN_PHASE_2A2T_WP_DIR:?}" "${DZN_PHASE_2A2T_NET:?}"
mode=${1:?usage: phase-2a2t-concurrency-runner.sh MODE}
repo=$DZN_PHASE_2A2T_REPO
wpdir=$DZN_PHASE_2A2T_WP_DIR
net=$DZN_PHASE_2A2T_NET
case $mode in
  duplicate_webhook|out_of_order_event|submit_vs_cancel|settlement_vs_attempt|mapping_change_vs_intake|\
  secret_rotation_vs_intake|unrelated_students|duplicate_command_replay|settlement_vs_r2_consequence|\
  submit_vs_cancel_in_flight|redrive_after_crash|concurrent_expired_lease|takeover_reissue_fenced|\
  fenced_settlement_lost|initial_dispatch_descriptor_failure|post_preflight_capability_failure) ;;
  *) echo "Unknown Phase T concurrency mode: $mode" >&2; exit 2;;
esac
# The gate directory must be visible to the worker containers at the same absolute path, so it lives
# inside the mounted candidate repository and is removed again when the run finishes.
gate=$(mktemp -d "$repo/.dzn-2a2t-gate.XXXXXX")
cleanup(){ case "$gate" in "$repo"/.dzn-2a2t-gate.*) rm -rf "$gate";; esac; }
trap cleanup EXIT INT TERM
wprun(){
  gateArg=$1; workerArg=$2; file=$3
  set -- -e "DZN_PHASE_2A2T_RUNTIME_TEST=concurrency" -e "DZN_PHASE_2A2T_MODE=$mode"
  [ "$workerArg" != "-" ] && set -- "$@" -e "DZN_PHASE_2A2T_WORKER=$workerArg"
  [ "$gateArg" != "-" ] && set -- "$@" -e "DZN_PHASE_2A2T_GATE_DIR=$gateArg"
  docker run --rm --network "$net" -u 0 -v "$wpdir:/var/www/html" -v "$repo:$repo" "$@" \
    wordpress:cli-php8.3 wp eval-file "$file" --path=/var/www/html --user=1 --allow-root
}
waitfor(){
  i=0
  while [ ! -f "$1" ] && [ "$i" -lt 1200 ]; do i=$((i+1)); sleep 0.1; done
  [ -f "$1" ]
}
wprun - - "$repo/tests/phase-2a2t-concurrency-setup.php" >"$gate/setup.out" 2>&1
wprun "$gate" w1 "$repo/tests/phase-2a2t-concurrency-worker.php" >"$gate/w1.out" 2>&1 &
w1=$!
waitfor "$gate/w1.started" || { echo "holder worker never gated"; cat "$gate/w1.out"; exit 1; }
wprun "$gate" w2 "$repo/tests/phase-2a2t-concurrency-worker.php" >"$gate/w2.out" 2>&1 &
w2=$!
case $mode in
  unrelated_students)
    # The contender owns a different Student's account root, so it must complete while the holder's
    # transaction is still open; only then is the holder released.
    if waitfor "$gate/w2.finished"; then : >"$gate/w2.independent"; fi
    : >"$gate/release"
    ;;
  *)
    # The contender serialises on the same account root, claim row or subject, so it blocks until release.
    sleep 1
    : >"$gate/release"
    ;;
esac
wait "$w1" || true
wait "$w2" || true
wprun "$gate" - "$repo/tests/phase-2a2t-concurrency-verify.php"
