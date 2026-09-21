# DELNAVAZAN LOCAL ORCHESTRATOR DAEMON V1

GOAL
Replace the current human-triggered agent handoff loop with a persistent local state-machine daemon on Hamed's Mac.

LOCATION
Existing bridge root:
~/Library/Application Support/DelnavazanAgentBridge/

DO NOT modify Delnavazan product repositories except for read-only metadata lookup.
Do not deploy anything.
Do not depend on Chrome or ChatGPT composer injection for core execution.

CURRENT PROVEN RUNNERS
Implementation:
- local Codex CLI
- model deepseek-v4-pro
- run artifacts under bridge/runs/<task_id>/{events.jsonl,stderr.log,final.txt,pid}

Independent review:
- local Codex CLI with CODEX_HOME=~/.codex-openai-review
- model gpt-5.6-terra
- isolated read-only clone
- same run artifact convention

REQUIRED V1 STATE MACHINE
States:
IDLE
IMPLEMENTING
IMPLEMENTATION_COMPLETE
REVIEWING
REVIEW_PASS
REVIEW_FAIL
BLOCKED
MERGE_READY
PACKAGING
COMPLETE
ERROR

Core loop:
1. Read durable orchestrator state JSON.
2. If a child process is active, monitor without duplicate launch.
3. On child exit:
   - require non-empty final.txt;
   - parse terminal status marker/result;
   - persist exact completion atomically;
   - append transition event.
4. IMPLEMENTATION_COMPLETE -> launch independent review using exact candidate SHA/tree extracted from final result.
5. REVIEW_FAIL -> create next correction round metadata and launch implementation only when an explicit task/prompt payload exists.
6. REVIEW_PASS -> transition to MERGE_READY; DO NOT auto-merge in V1 unless state explicitly contains merge_authorized=true.
7. BLOCKED/ERROR -> persist reason and stop safely; never spin-launch duplicates.
8. PACKAGING -> wait for packaging child and mark COMPLETE when verified final marker appears.
9. On daemon restart, reconstruct active state from durable JSON + PID/process existence + final.txt, and continue safely.

SAFETY
- single-instance lock;
- atomic state writes;
- unique task IDs;
- idempotent transitions;
- PID + start timestamp + command fingerprint;
- never treat a stale/reused PID as the expected child without fingerprint/start validation;
- no force push/rebase/reset;
- no production deployment;
- no Chrome dependency;
- no silent mutation of product repos;
- explicit human_gate boolean supported;
- event log suitable for tail -f.

FILES
Prefer:
bridge/orchestrator_daemon.py
bridge/orchestratorctl.py
state/orchestrator.json
state/orchestrator-events.jsonl
state/orchestrator.lock
plus tests under bridge/tests/

CLI
Provide:
orchestratorctl status
orchestratorctl start
orchestratorctl stop
orchestratorctl resume
orchestratorctl inject-result <task_id> <file>
orchestratorctl enqueue <spec.json>

TESTS
Must cover:
- implementation success -> review launch;
- review PASS -> MERGE_READY;
- review FAIL -> correction-needed state;
- BLOCKED;
- missing/empty final.txt;
- duplicate daemon prevention;
- duplicate task prevention;
- crash/restart recovery;
- stale PID protection;
- atomic state corruption fallback/backup;
- no Chrome dependency.

DELIVERABLE
Implement V1 locally in the bridge folder, test it, and return:
- files changed;
- architecture;
- exact commands to run;
- test results;
- known limitations;
- whether it is safe to start against a synthetic workflow.

END EXACTLY:
ORCHESTRATOR DAEMON V1 IMPLEMENTED
READY FOR SYNTHETIC END-TO-END TEST
DO NOT ENABLE ON LIVE PROJECT YET
