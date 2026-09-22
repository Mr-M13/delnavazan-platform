# ORCHESTRATOR V1.3 — ONE-PAGE CONTROL PANEL

GOAL
Build a reusable local web control panel for the generic orchestration engine. It must be functional first, visually clear, and reusable across projects. Do not modify product repositories.

LOCATION
~/Library/Application Support/DelnavazanAgentBridge/

ARCHITECTURE
Serve locally on 127.0.0.1 only. Prefer a small Python HTTP server using stdlib or already-installed lightweight dependencies. No cloud dependency. UI may be plain HTML/CSS/JS.

CORE PRINCIPLE
The UI is a control/observability layer over existing durable state. It must not become a second orchestration engine.

REQUIRED V1.3 FEATURES

1. PROJECTS
- Project selector.
- V1 may bootstrap Delnavazan as the first project.
- Design storage so future projects can have isolated queue/state/run/workspace namespaces.
- Show project name, root, queue summary, planner state, orchestrator state.

2. AGENT CARDS
Cards for at least:
- DeepSeek implementation
- OpenAI independent reviewer
- planner
- orchestrator
Optional generic slots for Codex Cloud / Hamed Cloud / Ina.
Each card shows:
- RUNNING / IDLE / BLOCKED / ERROR
- current task
- PID if local
- elapsed time
- model/provider
- real spinner only when process/PID fingerprint confirms alive
- last transition/error

3. CURRENT WORKFLOW
- state
- queue item
- execution attempt ID
- implementation/review round
- candidate SHA/tree
- expected next transition
- human/merge/deploy gates

4. PROJECT QUEUE
- table/list with queued, running, blocked, done, cancelled
- dependencies
- priority
- agent/model
- human gate
- merge/deploy authorization
- actions: add, cancel, approve human gate, authorize merge, authorize deploy, retry blocked task
- reorder/priority edit is desirable but can be simple numeric edit

5. ERRORS / EVENTS
- recent planner/orchestrator events
- blocked/error reason prominently visible
- expandable live tail of current events.jsonl
- clear distinction between historical and active errors

6. SETTINGS / FIRST-TIME SETUP
- local paths
- Codex executable path
- implementation model default
- review model default
- review CODEX_HOME
- GitHub repository defaults
- API/provider credential references
Do NOT store raw API keys in browser localStorage or queue JSON.
Prefer macOS Keychain integration or protected local env/secret file with permissions 0600; UI stores only secret reference/name.
Include concise first-run checklist.

7. CONTROLS
- Start/stop planner
- Start/stop orchestrator
- Pause project queue
- Resume
- retry blocked queue item
- refresh state
All actions must call existing planner/orchestrator APIs/CLI logic, not reimplement state transitions.

8. LOCAL API
Provide read endpoints:
- /api/status
- /api/projects
- /api/queue
- /api/events
- /api/runs/current
Provide action endpoints for the supported controls.
Validate inputs and bind only to 127.0.0.1.

9. LIVE UPDATES
Use Server-Sent Events or modest polling (1-2s).
No wasteful heavy polling.
Spinner/status must reflect actual process liveness, not stale state alone.

10. SAFETY
- no production deployment by default
- deploy authorization requires explicit separate action
- human-gated tasks remain gated
- no arbitrary shell command textbox
- CSRF protection/token for mutating local endpoints if practical
- reject non-local Host/origin where practical
- redact secrets from logs/UI
- read-only by default for status panels

11. VISUAL DESIGN
One page only.
Clean, compact dashboard.
No fancy framework required.
Desktop-first but responsive.
Top: project selector + global status.
Then: agent cards.
Then: current workflow.
Then: queue.
Bottom/side: events/errors and setup/settings.
Use clear badges and a spinner animation for genuinely live agents.

12. TESTS
- API status/queue/events
- planner/orchestrator start/stop controls
- human gate approval does not imply deploy authorization
- deploy authorization separate
- retry blocked item creates next immutable attempt
- process liveness drives spinner state
- secret values never returned by API
- local-only binding
- malformed action rejected
- no arbitrary command execution endpoint
- project isolation schema

13. DELNAVAZAN CANARY
Run UI against current real Delnavazan state:
- planner/orchestrator visible
- 4 done / 1 gated queue state rendered correctly
- publication task visibly human-gated
- no product mutation or deployment
- start/stop controls tested safely
- active fake/synthetic task can demonstrate spinner if needed without touching product repos

DELIVERABLE
- files changed
- launch command
- local URL
- first-run setup steps
- screenshots are optional; functional browser verification required if available
- tests/results
- known limitations
- recommendation SAFE FOR DAILY LOCAL USE or NOT SAFE

END EXACTLY:
ORCHESTRATOR CONTROL PANEL V1.3 IMPLEMENTED
<SAFE FOR DAILY LOCAL USE|NOT SAFE FOR DAILY LOCAL USE>
