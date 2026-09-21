# DELNAVAZAN ORCHESTRATOR V1.2 — PROJECT PLANNER / DURABLE TASK QUEUE

GOAL
Add a durable project-level planner above the proven workflow daemon so the system can autonomously move from one completed workflow to the next pre-authorised task without Hamed manually triggering each task.

LOCATION
~/Library/Application Support/DelnavazanAgentBridge/

DO NOT modify Delnavazan product repositories except read-only inspection.
DO NOT deploy.
DO NOT invent product scope.
DO NOT auto-merge product code in V1.2 unless a queue item explicitly carries merge_authorized=true and the existing workflow safety gate permits it.

CURRENT PROVEN STATE
- Workflow daemon V1.1 can autonomously:
  implementation -> independent review -> PASS/FAIL;
  FAIL -> correction -> re-review;
  repeated correction rounds;
  crash/restart recovery;
  duplicate prevention.
- Real Delnavazan canary completed implementation -> review -> PASS -> MERGE_READY.
- Current gap: after a workflow closes/gates, no next project task is automatically selected.

REQUIRED V1.2 ARCHITECTURE

1. DURABLE PROJECT QUEUE
Create:
state/project-queue.json
state/project-planner-events.jsonl

Queue item schema should include at minimum:
- task_id
- title
- status: queued|ready|running|blocked|done|cancelled
- role: implementation|packaging|audit|other supported workflow role
- repo
- branch
- task_spec_path OR inline prompt
- dependencies: list of task_ids
- runner
- model
- cwd/worktree rule
- merge_authorized bool
- deploy_authorized bool (must default false)
- human_gate bool
- priority integer
- created_at
- completed_at
- workflow_id if launched
- source/provenance (e.g. continuity checkpoint / Git commit)

2. PLANNER
Add bridge/project_planner.py and bridge/projectctl.py.

Planner loop:
- read project queue + orchestrator state;
- if orchestrator has active workflow, do nothing;
- if orchestrator state is MERGE_READY or COMPLETE and current queue item maps to that workflow, mark queue item done only when its configured completion condition is satisfied;
- choose next eligible item:
  * status queued/ready
  * all dependencies done
  * human_gate false
  * deploy_authorized not required
  * highest priority, then FIFO
- enqueue exactly one item into orchestrator;
- persist workflow_id/task mapping atomically;
- never launch duplicates;
- if no eligible work: stable IDLE_NO_WORK state/event, no polling spam;
- if next item requires human gate: BLOCKED_HUMAN_GATE with exact task/reason;
- if task is deployment and deploy_authorized=false: never launch.

3. CONTINUITY RECONCILIATION
V1.2 MUST NOT free-form “decide what to build next” from prose.
Provide a deterministic importer/sync command that can take an explicitly curated queue spec JSON derived from authoritative continuity.
Optional helper may inspect continuity and propose queue entries, but proposals must not auto-execute unless written into project-queue.json.

4. CURRENT REAL DELNAVAZAN QUEUE BOOTSTRAP
Prepare but DO NOT auto-run a real queue file yet.
Use known closed checkpoints as dependencies:
- PLATFORM-R1-CLOSED = done
- THEME-SINGLE-CONTENT-V1-CLOSED = done
- THEME-0.7.0-PACKAGE-VERIFIED = done

Create queued placeholders only for genuinely known remaining work from continuity, such as:
- THEME-0.7.0-USER-ARTEFACT-PUBLICATION (human_gate / external-surface dependent)
- STAGING-VALIDATION-PREP (human_gate false only if it is read-only/prep; actual staging deployment must remain gated)
Do NOT invent future product features not explicitly present in continuity.

5. DAEMON INTEGRATION
- project planner can run persistently alongside orchestrator daemon;
- single-instance lock;
- atomic queue/state writes;
- quiet idle loop;
- restart-safe workflow mapping;
- if orchestrator daemon is down, planner must not launch directly; it may start daemon only if configured auto_start_orchestrator=true.

6. CLI
projectctl status
projectctl list
projectctl add <spec.json>
projectctl cancel <task_id>
projectctl approve <task_id>   # clears human gate only; does not imply deploy authorization
projectctl authorize-merge <task_id>
projectctl authorize-deploy <task_id>   # explicit and separate
projectctl start
projectctl stop
projectctl sync <queue-spec.json>

7. TESTS
At minimum:
- completed task -> next dependency-ready task launches automatically;
- dependency blocking;
- priority ordering;
- FIFO tie-break;
- no duplicate workflow launch;
- human-gated task blocks cleanly;
- deploy task without deploy_authorized never launches;
- approve human gate does not authorize deploy;
- restart recovery with running workflow;
- orchestrator MERGE_READY mapping -> queue completion;
- no eligible tasks -> quiet IDLE_NO_WORK;
- malformed queue state -> safe fallback/block;
- planner never launches when orchestrator already has active child/workflow.

8. SYNTHETIC E2E
Run a 3-item synthetic project:
A -> B -> C
A implementation PASS,
B depends on A and FAILs once -> correction -> PASS,
C depends on B and is human-gated.
Expected final planner state: BLOCKED_HUMAN_GATE on C, with A/B done and no duplicate workflows.

9. REAL CANARY
After synthetic pass, run a READ-ONLY real Delnavazan planner canary using a queue containing one audit task and one gated placeholder. Prove:
- audit auto-launches;
- audit review completes;
- queue advances;
- planner stops on the gated placeholder;
- no product mutation, merge or deployment.

RETURN
- files changed
- schema
- planner algorithm
- CLI commands
- tests/results
- synthetic E2E result
- real canary result
- exact recommendation SAFE TO ENABLE LIVE PROJECT QUEUE or NOT SAFE

END EXACTLY:
PROJECT PLANNER V1.2 IMPLEMENTED
<SAFE TO ENABLE LIVE PROJECT QUEUE|NOT SAFE TO ENABLE LIVE PROJECT QUEUE>
