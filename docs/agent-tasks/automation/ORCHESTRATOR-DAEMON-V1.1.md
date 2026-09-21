# DELNAVAZAN ORCHESTRATOR DAEMON V1.1 — FAIL/CORRECTION LOOP HARDENING

GOAL
Complete the autonomous REVIEW_FAIL -> correction -> implementation -> review loop and remove event-log spam.

LOCATION
~/Library/Application Support/DelnavazanAgentBridge/

DO NOT modify Delnavazan product repositories except read-only inspection.
DO NOT merge or deploy product code.
This is bridge/orchestrator work only.

CURRENT PROVEN STATE
- Real local Codex implementation launch works.
- DeepSeek completion is detected automatically.
- Independent OpenAI review launches automatically.
- Review result is detected automatically.
- REVIEW_FAIL transition works.
- Current issue: daemon emits awaiting_correction every tick and cannot autonomously construct/launch the correction task without an externally supplied correction payload.

REQUIRED V1.1 BEHAVIOR

1. REVIEW_FAIL -> CORRECTION TASK GENERATION
On independent review FAIL:
- read the review final.txt;
- require a non-empty reviewer finding;
- construct a deterministic correction prompt/spec containing:
  * exact failed candidate SHA/tree;
  * exact reviewer result/verdict text;
  * instruction to preserve additive history;
  * no merge/deploy;
  * request to fix all blocking findings and return new candidate SHA/tree;
- derive unique correction task_id from workflow/round, e.g. WF-...-C2;
- increment round exactly once;
- persist correction spec before launch;
- launch implementation automatically with the workflow's implementation runner/model/worktree rules;
- never launch duplicate corrections after restart/tick.

2. REVIEW_FAIL EVENT DE-DUPLICATION
- emit awaiting_correction / correction_planned / correction_launch at most once per state transition;
- no per-second repeated event spam;
- daemon ticks while blocked/waiting must be quiet unless state actually changes or a periodic heartbeat interval >= 5 minutes is intentionally configured.

3. CORRECTION RESULT -> RE-REVIEW
- implementation correction completion extracts new candidate SHA/tree;
- auto-launch next independent review;
- preserve review sequence/round;
- support repeated FAIL -> correction rounds safely.

4. HUMAN GATE
If review final.txt is empty/unparseable or candidate identity cannot be extracted:
- transition BLOCKED with precise reason;
- do not invent a correction;
- no duplicate launch.

5. REAL RUNNER RESTART RECOVERY
Test with a harmless live Codex smoke workflow:
- daemon launches real DeepSeek child;
- restart daemon while child is running;
- restarted daemon reattaches/recognizes the same child using PID/start/fingerprint;
- no duplicate child;
- completion still advances to review;
- repeat/review side if practical.

6. TESTS
Add/extend tests for:
- REVIEW_FAIL auto-generates correction spec;
- correction task launches once;
- event de-duplication;
- repeated correction rounds;
- malformed/empty review -> BLOCKED;
- restart during implementation child;
- restart during review child if practical;
- duplicate task prevention remains intact.

7. SYNTHETIC + LIVE SMOKE
Run:
A. synthetic FAIL -> correction -> PASS end-to-end;
B. live harmless real runner smoke proving fail correction launch path;
C. restart recovery smoke.

RETURN
- files changed;
- state-machine changes;
- tests/results;
- synthetic E2E result;
- live runner smoke result;
- restart recovery result;
- known limitations;
- exact recommendation: SAFE TO ENABLE LIVE or NOT SAFE TO ENABLE LIVE.

END EXACTLY:
ORCHESTRATOR DAEMON V1.1 HARDENING COMPLETE
<SAFE TO ENABLE LIVE|NOT SAFE TO ENABLE LIVE>
