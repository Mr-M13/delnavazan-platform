#!/bin/sh
# Deterministic two-session controller for disposable Phase 2A.2-F runtimes.
set -eu
: "${DZN_PHASE_2A2F_WP_CLI:?Set the isolated wp executable}"
: "${DZN_PHASE_2A2F_WP_PATH:?Set the isolated WordPress root}"
: "${DZN_PHASE_2A2F_WP_USER:?Set a disposable administrator}"
: "${DZN_PHASE_2A2F_GATE_DIR:?Set an empty shared gate directory}"
[ "${DZN_PHASE_2A2F_RUNTIME_TEST:-}" = isolated ] || { echo "Phase 2A.2-F runner refused." >&2; exit 1; }
case "${DZN_PHASE_2A2F_MODE:-}" in
  r1|r2a|r2b|r3|r4|r5|r6|r7|r8|r9|r10|r11c|r11pr|r11ps|r11gr|r11gs|r11e) ;;
  *) echo "Unknown Phase 2A.2-F race mode." >&2; exit 1 ;;
esac

root=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
gate=$DZN_PHASE_2A2F_GATE_DIR
[ -d "$gate" ] && [ -z "$(find "$gate" -mindepth 1 -maxdepth 1 -print -quit)" ] || {
  echo "Gate directory must exist and be empty." >&2
  exit 1
}
wp() {
  "$DZN_PHASE_2A2F_WP_CLI" --path="$DZN_PHASE_2A2F_WP_PATH" --user="$DZN_PHASE_2A2F_WP_USER" eval-file "$1"
}
wait_gate() {
  file=$1
  i=0
  while [ ! -f "$gate/$file" ] && [ "$i" -lt 600 ]; do
    i=$((i+1))
    sleep 0.1
  done
  [ -f "$gate/$file" ] || { echo "Timed out waiting for explicit gate $file" >&2; exit 1; }
}

wp "$root/tests/phase-2a2f-concurrency-setup.php"
env DZN_PHASE_2A2F_WORKER=w1 "$DZN_PHASE_2A2F_WP_CLI" --path="$DZN_PHASE_2A2F_WP_PATH" --user="$DZN_PHASE_2A2F_WP_USER" eval-file "$root/tests/phase-2a2f-concurrency-worker.php" >"$gate/w1.out" 2>&1 &
first_pid=$!
wait_gate w1.locked
env DZN_PHASE_2A2F_WORKER=w2 "$DZN_PHASE_2A2F_WP_CLI" --path="$DZN_PHASE_2A2F_WP_PATH" --user="$DZN_PHASE_2A2F_WP_USER" eval-file "$root/tests/phase-2a2f-concurrency-worker.php" >"$gate/w2.out" 2>&1 &
second_pid=$!
wait_gate w2.started
case "$DZN_PHASE_2A2F_MODE" in
  r11*) wait_gate w2.observed ;;
  *) wp "$root/tests/phase-2a2f-concurrency-wait.php" ;;
esac
: >"$gate/release"
wait "$first_pid"
wait "$second_pid"

grep -q 'outcome=error' "$gate/w1.out" && { cat "$gate/w1.out"; exit 1; }
grep -q 'outcome=error' "$gate/w2.out" && { cat "$gate/w2.out"; exit 1; }
case "$DZN_PHASE_2A2F_MODE" in
  r1|r2b|r4|r5|r6|r7|r8|r9)
    grep -q 'outcome=success' "$gate/w1.out"
    grep -q 'outcome=rejected' "$gate/w2.out"
    ;;
  r2a|r3|r10)
    grep -q 'outcome=success' "$gate/w1.out"
    grep -q 'outcome=success' "$gate/w2.out"
    ;;
  r11*)
    grep -q 'outcome=success' "$gate/w1.out"
    grep -q 'outcome=observed' "$gate/w2.out"
    ;;
esac
wp "$root/tests/phase-2a2f-concurrency-verify.php"
printf '%s\n' "holder=$(tr '\n' ' ' < "$gate/w1.out")"
printf '%s\n' "contender=$(tr '\n' ' ' < "$gate/w2.out")"
case "$DZN_PHASE_2A2F_MODE" in
  r11*) printf '%s\n' "gates=w1.locked,w2.started,w2.observed,release" ;;
  *) printf '%s\n' "gates=w1.locked,w2.started,w2.blocked,release" ;;
esac
rm -f "$gate"/*
wp "$root/tests/phase-2a2f-concurrency-cleanup.php"
