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

## 4. Canonical Platform roadmap

Platform phase numbers are independent from Hamnavaz phase numbers. The
migration techniques documented below support relevant phases; they do not
replace or renumber this roadmap.

### Phase 0 — Existing System Audit & Architecture

Status: **COMPLETE**

- inventory current behaviours and dependencies;
- define Core ownership, module boundaries, security, data concepts, migration
  techniques, and Amelia exit philosophy;
- record unresolved product decisions;
- add no runtime code or schema.

### Phase 1 — Core Foundation & Canonical Data Model

Status: **NOT STARTED — requires explicit approval**

- establish the approved plugin/lifecycle shell and module loader;
- introduce separate application and schema versioning;
- implement minimum canonical identity/state and `LegacyReference` foundations;
- define domain commands/events and durable idempotency boundary;
- add no production authority change, provider write, or Amelia retirement.

See [Platform Phase 1 — Core Foundation & Canonical Data Model](PHASE-1-CORE-FOUNDATION.md)
for the proposed scope.

### Phase 2 — Amelia Read Adapter & Migration Tools

- implement typed, bounded, read-only access to supported Amelia tables/APIs;
- import Teacher, Student, Term/Enrolment, Lesson, and provider references into
  shadow Core records;
- checkpoint every import and report created, updated, unchanged, conflicted,
  skipped, and failed counts;
- never silently merge ambiguous people;
- write nothing back to Amelia;
- prove repeated imports converge.

### Phase 3 — Attendance Migration

- move attendance records and evidence onto canonical Core `lesson_id`;
- preserve signed Join/Absence behaviour, review workflows, Meet overlap rules,
  manual decisions, archive/restore, and source traceability;
- separate Lesson scheduling state from Attendance outcome;
- use shadow/parity comparison before moving attendance read or write authority;
- keep Google provider access transitional until Phase 4.

### Phase 4 — Direct Google Integration

- move teacher Google ownership away from Amelia and onto Core Teacher identity;
- implement proper connect, refresh, disconnect, and provider revoke lifecycle;
- isolate OAuth credentials and Meet API access behind the Google adapter;
- migrate/re-consent active teacher connections with explicit reconciliation;
- retire Amelia combined OAuth and the Google-specific Employee Panel identity
  bridge once Direct Google ownership is complete; broader Amelia Employee and
  Customer Panel retirement remains Phase 7.

### Phase 5 — Notification Platform / WhatsApp Migration

- extract generic Notification, attempt, provider-message, and delivery state;
- extract Meta transport and provider-authenticated webhook handling;
- move reminder/confirmation/absence/renewal workflows away from Amelia hooks;
- trigger workflows from Core, Academy, and Attendance events;
- preserve template contracts, idempotency, disabled settings, test-send
  isolation, retries, and delivery progression.

### Phase 6 — Availability & Scheduling

- implement native Delnavazan teacher availability;
- support recurring scheduling, rescheduling, and schedule-version history;
- define canonical timezone handling and wall-clock provenance;
- implement lesson buffers and capacity constraints;
- transfer scheduling authority only through bounded cohorts and rollback gates.

### Phase 7 — Native Teacher & Student Portals

- implement Core-authenticated teacher and student portal workflows;
- replace operational dependence on Amelia Employee and Customer panels;
- provide object-level authorization, onboarding, and account-recovery flows;
- retire the Amelia password helper and session bridge only after end-to-end
  portal acceptance.

### Phase 8 — Stripe Payments & Term Automation

- map Stripe customers/payments to Core identities without making Stripe an
  identity authority;
- connect payments to canonical Term/Enrolment lifecycle;
- generate Lessons only through approved Academy rules and idempotent commands;
- preserve payment/term reconciliation and rollback evidence.

### Phase 9 — Finance / Teacher Reporting Migration

- move payability, rate snapshots, statements, payouts, and reporting completely
  onto Core Lesson and Attendance identities;
- preserve introductory lesson visibility, distinct lesson counting, archive
  exclusion, manual override audit, and historical parity;
- retire teacher-email and Amelia-appointment report identity only after finance
  sign-off.

### Phase 10 — Amelia Cutover & Retirement

- perform the final dependency and authority audit;
- execute controlled final authority cutover and observation period;
- remove remaining runtime hooks, jobs, reads, and panel dependencies;
- deactivate Amelia only with explicit authorization and rehearsed rollback;
- retain Amelia tables and legacy references initially;
- handle eventual archive or deletion under a separate retention decision.

After Core stabilisation, resume Hamnavaz Phase 4 against the shared architecture
under its own approval.

## 5. Cross-phase migration techniques

Read-only import, idempotent migration, shadow/parity comparison, the authority
ledger, bounded workflow cutovers, rollback gates, Amelia exit gates, and the
strangler-style migration are used throughout the applicable canonical phases.
They are techniques and controls, not separate replacement phases.

## 6. Idempotent import contract

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

## 7. Identity migration

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

## 8. Lesson and enrolment migration

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

## 9. Shadow comparison

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

## 10. Authority ledger

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

## 11. Cutover procedure per capability

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

## 12. Rollback principles

- Rollback changes authority flags/routes; it does not delete new or legacy data.
- Events accepted during the cutover are reconciled before or after rollback so
  they are not lost or duplicated.
- Provider idempotency keys and mapping references survive rollback.
- Schema rollback favors forward-compatible repair over destructive downgrade.
- The exact prior plugin package, database backup, configuration, and operational
  test checklist remain available.
- Any temporary dual-written records have a source-of-truth marker and conflict
  report.

## 13. Amelia exit gates

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

## 14. Observability and evidence

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

## 15. Data and secret handling during migration

- Use anonymised or controlled fixtures in source control and automated tests.
- Do not place production exports, IDs, emails, phone numbers, OAuth credentials,
  signed links, or webhook URLs in commits or PRs.
- Limit production inspection to the minimum data needed and keep diagnostics
  redacted.
- Encrypt reusable secrets and version their key/cipher metadata.
- Clean up controlled fixtures only after ownership/reference verification.
- Preserve evidence needed for rollback, audit, and financial reconciliation
  under an approved retention policy.

## 16. Open product decisions

The decisions listed in [DATA-MODEL.md](DATA-MODEL.md) must be resolved before
their corresponding schema or workflow becomes authoritative. In particular,
Phase 1 requires agreement on identity conflict handling, Term/Enrolment shape,
minimum lifecycle vocabularies, timezone provenance, and Core/Hamnavaz linking.

## 17. Phase 0 restriction

This strategy is documentation only. It does not authorize Platform Phase 1,
production import, provider reconfiguration, Amelia writes, or any authority
cutover.
