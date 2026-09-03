# Delnavazan Platform Migration Strategy

## 1. Objective

Replace Amelia as Delnavazan's business authority without interrupting the beta
academy, losing evidence, or discarding proven behaviour. Migration is reversible,
observable, and module-by-module.

## 2. Non-negotiable rules

1. No big-bang rewrite.
2. Amelia remains installed, operational, and readable during coexistence.
3. Early imports are read-only; the platform does not write to Amelia.
4. Imports and event processing are repeatable and idempotent.
5. Legacy IDs remain traceable through `LegacyReference`.
6. Core results are compared against current production behaviour before any
   authority transfer.
7. Authority moves one bounded capability at a time with a named owner,
   activation time, rollback procedure, and acceptance evidence.
8. Initial cutover never deletes Amelia tables.
9. New work must not introduce fresh Amelia coupling outside Legacy Adapters.
10. Hamnavaz Phase 4 remains separate and paused until explicitly resumed.

## 3. Migration dimensions

Each capability moves through independent dimensions:

- **identity authority** — which system owns Teacher/Student identity;
- **read authority** — which system supplies the state shown or evaluated;
- **write authority** — which system accepts canonical changes;
- **workflow authority** — which system decides eligibility and triggers work;
- **provider transport** — which adapter performs external calls;
- **reporting authority** — which data set produces operational/financial reports.

A capability is not “migrated” merely because data was copied or a new screen
exists.

## 4. Platform phases

### Phase 0 — Architecture and audit

Status: complete when this documentation is reviewed and merged.

- inventory current behaviours and dependencies;
- define Core ownership, module boundaries, security, data concepts, and exit
  philosophy;
- record unresolved product decisions;
- add no runtime code or schema.

### Phase 1 — Core foundation

Status: not started; requires explicit approval.

- establish the approved plugin/lifecycle shell and module loader;
- introduce separate application and schema versioning;
- implement minimum canonical identity/state and `LegacyReference` foundations;
- define domain commands/events and durable idempotency boundary;
- add no production authority change, provider write, or Amelia retirement.

See [Phase 1 Core foundation](PHASE-1-CORE-FOUNDATION.md) for the proposed scope.

### Phase 2 — Read-only Amelia bridge

- implement typed, bounded reads from supported Amelia tables/APIs;
- import Teacher, Student, Enrolment/Term, Lesson, and provider references into
  shadow Core records;
- checkpoint every import and report created/updated/unchanged/conflicted/skipped/
  failed counts;
- never silently merge ambiguous people;
- write nothing back to Amelia;
- prove repeated imports converge.

### Phase 3 — Shadow operations and parity

- run Core Lesson projections beside current Amelia/Enhancements operation;
- compare lesson schedules, approval/cancellation, attendance eligibility,
  reminder eligibility, term counts, and teacher payment totals;
- classify every difference as a Core defect, source inconsistency, intended
  policy change, or unresolved product decision;
- make no authority change from parity data alone.

### Phase 4 — Workflow cutovers

Move capabilities independently, for example:

1. notification delivery storage and Meta transport;
2. attendance read model and Google evidence integration;
3. teacher payment reporting;
4. absence/join public capabilities;
5. student and teacher portal functions.

Each cutover has its own branch, review, production flag, runtime matrix, rollback,
and observation window. Phase numbering here is for Platform and is unrelated to
Hamnavaz Phase 4.

### Phase 5 — Scheduling authority

- introduce Core scheduling for a deliberately bounded cohort or workflow;
- maintain a documented coexistence contract with Amelia;
- avoid uncontrolled dual-write; if temporary dual-write is approved, record a
  durable outbox and reconciliation state;
- expand only after operational support and rollback are proven.

### Phase 6 — Amelia retirement

- freeze new Amelia authority after the final cutover;
- retain read-only access and legacy mappings for the approved period;
- remove hooks, scheduled jobs, portal dependencies, and report reads only after
  dependency scans and runtime evidence;
- deactivate Amelia under explicit authorization;
- preserve database tables/backups initially;
- decide long-term archive or deletion in a separate data-governance action.

## 5. Idempotent import contract

Every import run must have:

- a unique run ID, source version, bounded time/ID range, and checkpoint;
- a stable source key `(provider, external_type, external_id, scope)`;
- a normalized source fingerprint that excludes volatile/noisy values;
- deterministic mapping and conflict outcomes;
- per-record created, updated, unchanged, skipped, conflicted, or failed result;
- aggregate counts and redacted diagnostics;
- safe retry after interruption;
- no external side effects such as messages, payments, portal invitations, or
  Amelia writes.

Source deletion is not mapped to hard deletion. It becomes a source-observation
or cancellation/archive candidate and is resolved by owning business rules.

## 6. Identity migration

### Teacher

1. Import Amelia employee/provider references into candidates.
2. Match only through explicit approved evidence; email/name matches may suggest
   but do not silently establish identity.
3. Create a Core Teacher or attach the reference to an approved existing Teacher.
4. Separately propose/confirm an optional Hamnavaz profile link.
5. Preserve all historical provider references.

### Student

1. Import Amelia customers and known WordPress/Stripe references independently.
2. Do not treat shared/reused email or phone as conclusive identity.
3. Surface conflicts and duplicate candidates for controlled review.
4. Grant no portal access merely because a mapping exists.

## 7. Lesson and enrolment migration

- Map both Amelia appointment and customer-booking identifiers where one
  appointment can have multiple booking rows.
- Preserve service, provider, customer, approval, start/end, timezone, and Meet
  URL observations needed for parity, but attach canonical state to Core IDs.
- Group lessons into an Enrolment/Term only under explicit rules; do not assume
  every 12 appointments are a term without retaining the current heuristic and
  its uncertainty.
- Keep schedule versions or an audit trail so reschedules do not erase prior
  facts or leave valid public capabilities attached to the wrong time.
- Completed attendance/payment evidence survives source cancellation or deletion.

## 8. Shadow comparison

Before a cutover, compare at least:

- Teacher and Student mapping counts/conflicts;
- upcoming and historical Lesson counts;
- appointment/customer-booking mapping cardinality;
- start/end UTC and display timezone;
- approved/cancelled eligibility;
- assigned teacher/student/service;
- Meet code and conference candidate;
- attendance overlap and review outcomes;
- next-day notification eligible/skipped/sent sets;
- term-renewal eligible sets;
- payability, introductory classification, distinct lesson counts, and statement
  totals;
- archive/restore visibility.

Comparisons use stable identifiers and machine-readable difference reasons. No
private production data belongs in Git or PR descriptions.

## 9. Authority ledger

Maintain a production authority ledger for each capability:

| Field | Meaning |
| --- | --- |
| Capability | Narrow business function, such as next-day reminder eligibility |
| Read authority | System currently used for decisions/display |
| Write authority | System accepting canonical state changes |
| Workflow owner | Module deciding when work should happen |
| Provider owner | Adapter performing external calls |
| Cohort/scope | Teachers, students, services, or dates included |
| Activated at/by | Auditable cutover record |
| Rollback flag/procedure | Exact safe return path |
| Validation evidence | Tests, parity window, and runtime observations |

Ambiguous dual authority is a release blocker.

## 10. Cutover procedure per capability

1. Define scope, owner, success metrics, and rollback.
2. Back up files/database and confirm restoration path.
3. Run repeatable import and resolve blocking conflicts.
4. Complete shadow parity for the agreed period/data range.
5. Review security and privacy boundary changes.
6. Deploy inactive/dark code where practical.
7. Enable for controlled test data or a bounded beta cohort.
8. Verify the actual user/provider outcome, not just a queued job or accepted API
   request.
9. Expand gradually with monitoring.
10. Record authority change and observe through a defined window.
11. Retain the old read/rollback path until explicit closeout.

## 11. Rollback principles

- Rollback changes authority flags/routes; it does not delete new or legacy data.
- Events accepted during the cutover are reconciled before or after rollback so
  they are not lost or duplicated.
- Provider idempotency keys and mapping references survive rollback.
- Schema rollback favors forward-compatible repair over destructive downgrade.
- The exact prior plugin package, database backup, configuration, and operational
  test checklist remain available.
- Any temporary dual-written records have a source-of-truth marker and conflict
  report.

## 12. Amelia exit gates

Amelia may be deactivated only when all applicable gates pass:

- all active Teachers, Students, Terms/Enrolments, and Lessons have canonical
  Core identities and resolved mappings;
- no active portal, public route, scheduled job, notification, attendance,
  finance, reporting, or support procedure depends on Amelia runtime behaviour;
- scheduling and approval writes are Core-authoritative for every in-scope
  cohort;
- Google connections no longer depend on Amelia employee sessions or OAuth
  storage;
- Meta workflows no longer depend on Amelia hooks, cron, or webhook callback;
- password/account onboarding no longer depends on Amelia controls;
- financial and attendance parity has been signed off for agreed historical and
  live periods;
- imports have reached a stable final checkpoint and legacy mappings remain
  queryable;
- rollback and business-continuity procedures have been rehearsed;
- support/admin tools expose required diagnostics without Amelia;
- legal/retention decisions for the source data have been approved;
- explicit owner authorization is obtained for deactivation.

Even after those gates pass, initial retirement preserves Amelia tables and a
recoverable backup. Permanent deletion is a separate decision.

## 13. Observability and evidence

Every migration phase should expose:

- current authority per capability;
- last successful import and checkpoint;
- record/result counts and conflicts;
- queue/outbox backlog and retry age;
- provider connection health without secrets;
- notification acceptance and delivery progression;
- reconciliation/parity differences;
- last schema/data migration result;
- archive/purge job scope and outcome.

“Job ran”, “API accepted”, “row imported”, and “page loaded” are intermediate
states. Acceptance requires the defined end-to-end business outcome.

## 14. Data and secret handling during migration

- Use anonymised or controlled fixtures in source control and automated tests.
- Do not place production exports, IDs, emails, phone numbers, OAuth credentials,
  signed links, or webhook URLs in commits or PRs.
- Limit production inspection to the minimum data needed and keep diagnostics
  redacted.
- Encrypt reusable secrets and version their key/cipher metadata.
- Clean up controlled fixtures only after ownership/reference verification.
- Preserve evidence needed for rollback, audit, and financial reconciliation
  under an approved retention policy.

## 15. Open product decisions

The decisions listed in [DATA-MODEL.md](DATA-MODEL.md) must be resolved before
their corresponding schema or workflow becomes authoritative. In particular,
Phase 1 requires agreement on identity conflict handling, Term/Enrolment shape,
minimum lifecycle vocabularies, timezone provenance, and Core/Hamnavaz linking.

## 16. Phase 0 restriction

This strategy is documentation only. It does not authorize Platform Phase 1,
production import, provider reconfiguration, Amelia writes, or any authority
cutover.
