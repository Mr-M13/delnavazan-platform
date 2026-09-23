# ORCHESTRATOR CONTROL PANEL V1.4.1 — ACTIVITY/STATUS CORRECTION

GOAL
Correct the live project-status semantics and activity UX before upgrading the live panel.

BASE
Current V1.4 implementation in ~/Library/Application Support/DelnavazanAgentBridge/

DO NOT modify product repositories.
DO NOT deploy.
Preserve queue/planner/orchestrator state.

REQUIRED CORRECTIONS

1. PROJECT STATUS MUST REPRESENT PROJECT WORK, NOT SERVICE UPTIME
Planner and Orchestrator are infrastructure services.
They MUST NOT make top-level Project status RUNNING.
Top-level project/work status must derive from actual queue/workflow/worker state.

Rules:
- RUNNING only when a real implementation/review/correction/packaging/audit child is alive.
- WAITING FOR APPROVAL when next eligible task is human-gated.
- BLOCKED CONFIGURATION when next/blocked task lacks required prompt/spec/target/config.
- BLOCKED ERROR for execution failure.
- DEPENDENCY BLOCKED when applicable.
- MERGE READY when workflow is at that gate.
- DEPLOY GATED when applicable.
- NO WORK when no eligible work exists.
- COMPLETE only when project queue is truly complete.
Do not show generic IDLE when meaningful planner state exists.

2. SERVICES ARE SEPARATE
Show:
- Control Panel service health
- Planner service health
- Orchestrator service health
These may be RUNNING while Project status is NO WORK/BLOCKED/etc.
Planner/Orchestrator must not be counted as active project agents/workers.

3. CURRENT / NEXT / BLOCKER
Add prominent workflow summary:
- CURRENT: active execution task or "None"
- NEXT: next eligible or next blocked/gated queue task
- BLOCKER: exact blocker class/reason or "None"
For current Delnavazan state:
CURRENT: None
NEXT: THEME-0.7.0-USER-ARTEFACT-PUBLICATION
BLOCKER: Configuration required — missing publication destination/task spec

4. EVENTS / ERRORS AUTO-FOLLOW
- On first load and while user has not intentionally scrolled away, events/log panel stays pinned to newest entry at bottom.
- New events should appear without user manually scrolling.
- If user scrolls upward away from bottom, pause auto-follow and visibly indicate "Auto-follow paused".
- Provide Resume/Jump to latest control.
- When user returns to bottom, auto-follow resumes automatically.
- Do not continuously force-scroll while user is reading older events.

5. LIVE ACTIVITY SPINNER
Add spinner/activity indicator in Events/Live Activity area.
Spinner ACTIVE only when a real project worker child is validated alive:
- implementation
- review
- correction
- packaging/audit where represented by actual child
Spinner INACTIVE for:
- planner daemon alive only
- orchestrator daemon alive only
- blocked/waiting/no-work
Label should say e.g. "Live activity — DeepSeek / TASK-ID" or "No active worker".

6. NEXT TASK DERIVATION
Create deterministic helper used by backend/UI:
- actual running queue item if any
- otherwise highest-priority eligible queued task
- otherwise highest-priority blocked/gated task with reason
Expose in /api/status or equivalent structured response.

7. TESTS
Add regression tests:
- planner+orchestrator alive, no child => project NOT RUNNING
- real live child => project RUNNING
- configuration-blocked publication => top-level BLOCKED CONFIGURATION
- current/next/blocker correct for live Delnavazan queue
- next task derivation priority/dependency aware
- spinner false when only services alive
- spinner true only for validated live worker
- auto-follow JS logic has pause/resume/jump-to-latest behavior (unit/static assertion acceptable)
- existing V1.4 tests remain green

8. LIVE CANARY
Against current Delnavazan state:
- services show healthy
- project shows BLOCKED CONFIGURATION, not RUNNING/IDLE
- CURRENT=None
- NEXT=THEME-0.7.0-USER-ARTEFACT-PUBLICATION
- BLOCKER mentions missing publication destination/task spec
- activity spinner is OFF
- queue remains 4 done / 1 blocked
- no state mutation

RETURN
- files changed
- tests
- canary
- recommendation SAFE TO RESTART LIVE PANEL or NOT SAFE

END EXACTLY:
CONTROL PANEL V1.4.1 CORRECTION COMPLETE
<SAFE TO RESTART LIVE PANEL|NOT SAFE TO RESTART LIVE PANEL>
