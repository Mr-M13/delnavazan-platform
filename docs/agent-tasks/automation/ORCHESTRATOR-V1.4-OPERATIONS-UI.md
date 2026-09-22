# ORCHESTRATOR V1.4 — OPERATIONS & PROJECT MANAGEMENT UI

GOAL
Upgrade the proven V1.3 local Control Panel into a clearer reusable project-operations interface without changing product repositories or weakening any safety gate.

LOCATION
~/Library/Application Support/DelnavazanAgentBridge/

CURRENT PROVEN STATE
- Control Panel V1.3 is live at 127.0.0.1:8790.
- Planner + orchestrator are healthy.
- Queue has 4 done and 1 blocked Theme 0.7.0 publication item.
- Publication human gate was approved, but task is blocked because no executable task spec/publication destination exists.
- No deployment or merge authority is granted.
- 61 V1.3 tests passed.

REQUIRED V1.4 FEATURES

1. PROJECT QUEUE FILTERS
Add client-side/server-supported filtering for:
- search text
- status
- role
- agent/model
- repo/project
- human gate
- blocked
- dependency-ready / dependency-blocked
- priority
Quick presets:
- Active
- Needs Me
- Blocked
- Queued
- Completed
Default view should de-emphasize or hide completed items when useful.
Filters must not mutate queue state.

2. CLEAR SERVICE VS WORKFLOW STATE
Separate:
- SERVICES: Planner / Orchestrator / Control Panel health
- WORKFLOW: NO WORK / WAITING FOR APPROVAL / BLOCKED CONFIGURATION / IMPLEMENTING / REVIEWING / CORRECTING / MERGE READY / DEPLOY GATED / COMPLETE
Do not present plain IDLE when planner has meaningful blocked/gated state.
Current live-tail label must state what is being tailed.

3. AGENT REGISTRY
Create durable agent registry, e.g. state/agents.json.
Agent fields:
- id
- display_name
- enabled
- role(s)
- provider
- runner_type from allowlist
- model
- credential_ref (name only, never raw secret)
- local executable/profile metadata where appropriate
- capabilities
- created_at / updated_at
UI actions:
- add
- edit
- enable/disable
- remove from future scheduling
Rules:
- historical records must remain intact
- disabling/removing an agent must not kill unrelated historical runs
- no arbitrary shell command field
- runner_type must be allowlisted
- reject deleting an agent currently executing a task unless explicitly stopped first

4. DOCUMENTATION HEALTH
Add project documentation registry, e.g. state/documentation.json.
Each doc entry:
- id
- title
- repo/path
- type: authoritative|required|informational
- last_known_commit / last_updated
- status: CURRENT|STALE|MISSING|NEEDS_REVIEW|UNKNOWN
- reason
- related task/phase
For Delnavazan bootstrap at minimum track:
- docs/DELNAVAZAN-CORE-CONTINUITY.md as authoritative
- Theme README / CHANGELOG
- Platform README or relevant architecture docs if configured
Show:
- last update
- status badge
- why stale/needs review
- latest project transition after doc update
Provide a deterministic docs-health check; do not free-form rewrite docs automatically.
May create a queued documentation-maintenance task only when configured and pre-authorised.

5. CONFIGURATION-REQUIRED BLOCKERS
Distinguish:
- human approval required
- configuration required
- execution error
- dependency blocked
- deploy authorization required
For the current Theme publication item, display:
  BLOCKED — CONFIGURATION REQUIRED
  Missing publication destination/task spec
Do not retry it every tick.
Planner must emit blocker event once per state/reason, not spam every 2 seconds.

6. PROJECT QUEUE ACTIONS
Retain existing actions and add:
- retry blocked
- edit priority
- edit allowed task metadata where safe
- attach/replace task spec or inline prompt
- set publication/output target metadata
Any action affecting deploy remains separately authorised.

7. DOCUMENTATION CHECKPOINT TASK
Provide a safe way to add/execute a documentation reconciliation task that:
- updates authoritative continuity only on its dedicated continuity branch/worktree
- records durable automation milestones and current queue/blockers
- never edits reviewed product code
- never deploys

8. PROJECT/AGENT REUSABILITY
Make UI/schema explicitly generic across projects.
Project configuration may define:
- root
- repositories
- agents
- docs registry
- queue
No Delnavazan-specific branching inside generic engine logic beyond bootstrap data.

9. UI
Keep one-page layout.
Recommended sections:
- top bar: project selector + global health
- services
- agents
- workflow
- queue with filters
- documentation health
- events/errors
- settings
Compact desktop-first responsive layout.

10. TESTS
Add tests for:
- queue filters/presets
- meaningful workflow summary when orchestrator is IDLE but planner blocked/gated
- agent add/edit/disable/remove
- runner allowlist
- cannot remove active agent
- secret refs only; no secret return
- docs registry CRUD/read
- documentation health statuses
- configuration blocker does not spam
- attaching task spec unblocks eligible item
- project isolation
- no arbitrary shell runner
- existing V1.3 safety/API tests remain green

11. LIVE DELNAVAZAN CANARY
Against current real state:
- Control Panel still available on 127.0.0.1:8790
- services all rendered correctly
- publication shows BLOCKED CONFIGURATION REQUIRED, not generic IDLE
- queue filters work
- default agent registry contains planner, orchestrator, DeepSeek implementation, OpenAI reviewer
- continuity doc appears in Documentation Health and is flagged appropriately if behind automation milestones
- no product mutation
- no deployment
- current queue state remains intact

RETURN
- files changed
- schema changes
- tests/results
- canary result
- launch/restart steps
- known limitations
- exact recommendation SAFE TO UPGRADE LIVE PANEL or NOT SAFE

END EXACTLY:
ORCHESTRATOR CONTROL PANEL V1.4 IMPLEMENTED
<SAFE TO UPGRADE LIVE PANEL|NOT SAFE TO UPGRADE LIVE PANEL>
