#!/bin/sh
set -eu
: "${DZN_PHASE_2A2J_WP_CLI:?}" "${DZN_PHASE_2A2J_WP_PATH:?}" "${DZN_PHASE_2A2J_WP_USER:?}" "${DZN_PHASE_2A2J_GATE_DIR:?}"
[ "${DZN_PHASE_2A2J_RUNTIME_TEST:-}" = concurrency ] || exit 1
case "${DZN_PHASE_2A2J_MODE:-}" in initial|replay|replacement);; *) exit 1;; esac
root=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd); gate=$DZN_PHASE_2A2J_GATE_DIR
[ -d "$gate" ] && [ -z "$(find "$gate" -mindepth 1 -maxdepth 1 -print -quit)" ] || exit 1
wp(){ "$DZN_PHASE_2A2J_WP_CLI" --path="$DZN_PHASE_2A2J_WP_PATH" --user="$DZN_PHASE_2A2J_WP_USER" eval-file "$1"; }
wait_gate(){ i=0; while [ ! -f "$gate/$1" ] && [ "$i" -lt 600 ]; do i=$((i+1)); sleep 0.1; done; [ -f "$gate/$1" ] || exit 1; }
wp "$root/tests/phase-2a2j-concurrency-setup.php"
env DZN_PHASE_2A2J_WORKER=w1 "$DZN_PHASE_2A2J_WP_CLI" --path="$DZN_PHASE_2A2J_WP_PATH" --user="$DZN_PHASE_2A2J_WP_USER" eval-file "$root/tests/phase-2a2j-concurrency-worker.php" >"$gate/w1.out" 2>&1 & p1=$!
wait_gate w1.locked; wait_gate w1.connection
env DZN_PHASE_2A2J_WORKER=w2 "$DZN_PHASE_2A2J_WP_CLI" --path="$DZN_PHASE_2A2J_WP_PATH" --user="$DZN_PHASE_2A2J_WP_USER" eval-file "$root/tests/phase-2a2j-concurrency-worker.php" >"$gate/w2.out" 2>&1 & p2=$!
wait_gate w2.started; wait_gate w2.connection
wp "$root/tests/phase-2a2j-concurrency-wait.php" >"$gate/wait.out"; wait_gate w2.blocked
: >"$gate/release"; wait "$p1"; wait "$p2"
! grep -Eiq 'deadlock|lock wait timeout|WordPress database error|mysqli_sql_exception|Duplicate entry' "$gate/w1.out" "$gate/w2.out"
grep -q 'outcome=created' "$gate/w1.out"
if [ "$DZN_PHASE_2A2J_MODE" = initial ]; then grep -q 'outcome=already_applied' "$gate/w2.out"; elif [ "$DZN_PHASE_2A2J_MODE" = replay ]; then grep -q 'outcome=replay' "$gate/w2.out"; else grep -q 'outcome=assignment_changed' "$gate/w2.out"; fi
wp "$root/tests/phase-2a2j-concurrency-verify.php"
printf 'race=%s attributed_wait=%s holder="%s" contender="%s"\n' "$DZN_PHASE_2A2J_MODE" "$(tr '\n' ' ' <"$gate/w2.blocked")" "$(tr '\n' ' ' <"$gate/w1.out")" "$(tr '\n' ' ' <"$gate/w2.out")"
rm -f "$gate"/*
