# Delnavazan Platform Migration Strategy

## 1. Objective

Replace Amelia as Delnavazan's business authority without interrupting the beta
academy, losing evidence, or discarding proven behaviour. Migration is reversible,
observable, and module-by-module.

## 2. Non-negotiable rules

1. No big-bang rewrite.
2. Amelia remains installed, operational, and readable during coexistence.
3. Initial Core catalogue and active academy data are recreated manually; the
   Platform does not build an automated Amelia importer or synchronizer.
4. Schema migrations, domain operations, provider calls, and event processing
   are retry-safe and idempotent where required.
5. Operationally required provider IDs remain traceable through explicit
   mappings; historical Amelia data may remain in a separate read-only archive.
6. Each replacement is compared with current production behaviour through
   controlled validation, not a standing shadow/parity engine.
7. Authority moves one bounded capability at a time with a named owner,
   activation time, rollback procedure, and acceptance evidence.
8. Initial cutover never deletes Amelia tables.
9. New Platform Core work must not introduce fresh Amelia data-model coupling.
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
- implement minimum canonical identity/state, schedule-history, exception, and
  generic extension/audit foundations;
- define domain commands/events and durable idempotency boundary;
- add no production authority change, provider write, or Amelia retirement.

See [Platform Phase 1 — Core Foundation & Canonical Data Model](PHASE-1-CORE-FOUNDATION.md)
for the proposed scope.

### Phase 2 — Core Data Setup & Cutover Preparation

- manually create and validate the initial Instrument/Course catalogue;
- manually create the handful of active Teachers and relevant active Students;
- manually create and validate their Enrolments, Terms, and required Lessons;
- create an Introductory Lesson directly for a Student when required, before any
  continuing Enrolment/Term exists;
- record setup provenance, review outcomes, and reconciled counts;
- prepare authority, rollback, validation, and operational cutover checklists;
- do not build an Amelia importer, synchronizer, parity engine, or new Core
  Amelia data-model dependency.

### Phase 3 — Attendance Migration

- move attendance records and evidence onto canonical Core `lesson_id`;
- preserve signed Join/Absence behaviour, review workflows, Meet overlap rules,
  manual decisions, archive/restore, and source traceability;
- separate Lesson scheduling state from Attendance outcome;
- use controlled regression and runtime comparison before moving attendance read
  or write authority;
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
- connect payments to canonical Enrolment and Term lifecycles;
- generate Lessons only through approved Academy rules and idempotent commands;
- preserve payment/term reconciliation and rollback evidence.

### Phase 9 — Finance / Teacher Reporting Migration

- move payability, rate snapshots, statements, payouts, and reporting completely
  onto Core Lesson and Attendance identities;
- preserve introductory lesson visibility, distinct lesson counting, archive
  exclusion, manual override audit, and approved historical totals;
- retire teacher-email and Amelia-appointment report identity only after finance
  sign-off.

### Phase 10 — Amelia Cutover & Retirement

- perform the final dependency and authority audit;
- execute controlled final authority cutover and observation period;
- remove remaining runtime hooks, jobs, reads, and panel dependencies;
- deactivate Amelia only with explicit authorization and rehearsed rollback;
- retain Amelia tables and approved historical archive/reference evidence
  initially;
- handle eventual archive or deletion under a separate retention decision.

After Core stabilisation, resume Hamnavaz Phase 4 against the shared architecture
under its own approval.

## 5. Cross-phase migration techniques

The authority ledger, bounded workflow cutovers, rollback gates, Amelia exit
gates, and strangler-style migration are used throughout the applicable
canonical phases. Idempotency remains required for schema migrations, domain
commands/events, provider operations, and webhooks. Controlled comparisons are
acceptance evidence for a replacement, not a permanent shadow/parity system.

Phase 0 considered automated read-only Amelia import, repeatable mapping,
checkpoints, and a shadow/parity engine. On 3 September 2026 that approach was
deliberately rejected because the live dataset is small enough for controlled
manual recreation. Those historical concepts do not authorize or require
Amelia import infrastructure.

## 6. Manual Core data setup contract

The Phase 2 setup must have:

- a reviewed list of the initial Instruments and approximately 13 Courses;
- an approved list of active Teachers and relevant active Students;
- explicit Enrolment, Term, and required Lesson creation decisions;
- stable Core identifiers and required country/city/timezone provenance;
- per-entity actor, source/provenance, and review evidence;
- expected and actual counts plus a second-person validation checklist;
- no automated Amelia reads, provider side effects, portal invitations,
  notifications, payments, or Amelia writes.

Setup errors are corrected through audited Core actions. They are not resolved
by silently overwriting or auto-merging identities.

## 7. Identity setup

### Teacher

1. Create each active Core Teacher deliberately.
2. Treat email, phone, WordPress, Google, Amelia, and other provider values as
   attributes/mappings, not identity.
3. Let matching data suggest a duplicate but never auto-merge.
4. Record any merge as an explicit, administrator-authorized, audited action.
5. Separately propose and explicitly authorize any optional Hamnavaz profile
   link; linking or unlinking does not create or delete either identity.

### Student

1. Create each relevant active Core Student deliberately.
2. Treat WordPress, Amelia, Stripe, email, and phone as attributes/mappings.
3. Surface possible duplicates for controlled review without auto-merging.
4. Grant no portal access merely because a mapping exists.

## 8. Catalogue, Enrolment, Term, and Lesson setup

- Create shared Instruments, then minimal Academy Courses that reference them.
- Create each continuing Student–Teacher–Course relationship as an Enrolment.
- Create each bounded allocation/payment/renewal cycle as a separate Term within
  its Enrolment.
- Create every Lesson with direct `student_id`, `teacher_id`, and `course_id`
  to preserve its operational/historical identity. Create Introductory Lessons
  directly for Students as needed, with nullable Enrolment/Term. Create standard
  Lessons under their Enrolment and Term; replacement Lessons normally remain in
  that Term and explicitly link to the original through
  `replacement_for_lesson_id`. On standard/replacement creation, verify direct
  Teacher/Student/Course references against the Enrolment.
- Use the approved default Term policy of 12 standard Lessons and two eligible
  replacement Lessons, with explicit audited exceptions only.
- Create required Lessons with canonical UTC instants and explicit IANA schedule
  timezones/wall-clock intent.
- Normal rescheduling preserves Lesson identity and appends schedule history. A
  genuine replacement/make-up Lesson explicitly links to the original.
- Finance rate/currency snapshots and audited financial corrections are deferred
  to Platform Phase 9 and are not Phase 1 or Phase 2 setup work.

## 9. Replacement validation during coexistence

Before each bounded runtime cutover, compare the affected business outcomes, as
applicable:

- manually approved Core setup counts and relationships;
- upcoming Lesson counts and schedule instances;
- start/end UTC, wall-clock time, IANA timezone, and calendar presentation;
- approved/cancelled eligibility;
- assigned teacher/student/service;
- Meet code and conference candidate;
- attendance overlap and review outcomes;
- next-day notification eligible/skipped/sent sets;
- term-renewal eligible sets;
- introductory classification, distinct lesson counts, and, when Finance is in
  scope during Phase 9, payability and statement totals;
- archive/restore visibility.

Comparisons use stable Core identifiers and documented difference reasons. They
may be test scripts, reports, or controlled runtime checklists scoped to the
replacement; they are not a continuously synchronized Amelia parity engine. No
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
| Validation evidence | Tests, controlled comparison, and runtime observations |

Ambiguous dual authority is a release blocker.

## 11. Cutover procedure per capability

1. Define scope, owner, success metrics, and rollback.
2. Back up files/database and confirm restoration path.
3. Confirm required Core records were manually created and independently
   validated for the bounded scope.
4. Complete the defined controlled before/after comparison.
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
- Provider idempotency keys and required mapping references survive rollback.
- Schema rollback favors forward-compatible repair over destructive downgrade.
- The exact prior plugin package, database backup, configuration, and operational
  test checklist remain available.
- Any temporary dual-written records have a source-of-truth marker and audited
  reconciliation procedure.

## 13. Amelia exit gates

Amelia may be deactivated only when all applicable gates pass:

- all in-scope active Instruments, Courses, Teachers, Students, Enrolments,
  Terms, and Lessons have been manually created and independently validated;
- no active portal, public route, scheduled job, notification, attendance,
  finance, reporting, or support procedure depends on Amelia runtime behaviour;
- scheduling and approval writes are Core-authoritative for every in-scope
  cohort;
- Google connections no longer depend on Amelia employee sessions or OAuth
  storage;
- Meta workflows no longer depend on Amelia hooks, cron, or webhook callback;
- password/account onboarding no longer depends on Amelia controls;
- financial and attendance outcomes have been signed off for agreed controlled
  and operational periods;
- required provider mappings remain queryable and the approved historical Amelia
  archive is available under its retention/access procedure;
- rollback and business-continuity procedures have been rehearsed;
- support/admin tools expose required diagnostics without Amelia;
- legal/retention decisions for the source data have been approved;
- explicit owner authorization is obtained for deactivation.

Even after those gates pass, initial retirement preserves Amelia tables and a
recoverable backup. Permanent deletion is a separate decision.

## 14. Observability and evidence

Every migration phase should expose:

- current authority per capability;
- manually approved setup counts and outstanding review items;
- queue/outbox backlog and retry age;
- provider connection health without secrets;
- notification acceptance and delivery progression;
- controlled replacement-validation differences;
- last schema migration and manual setup result;
- archive/purge job scope and outcome.

“Job ran”, “API accepted”, “record created”, and “page loaded” are intermediate
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

## 16. Product decisions and remaining design work

The binding post-Phase-0 decisions are recorded in
[PRODUCT-DECISIONS.md](PRODUCT-DECISIONS.md). Remaining later decisions are
retention/anonymization durations, Google failed-revoke handling and token
retention, the historical Amelia archive procedure, Platform Phase 9 Finance
physical tables/audited correction implementation, and later provider-specific
integration schemas.

## 17. Phase 0 restriction

This strategy is documentation only. It does not authorize Platform Phase 1,
production data setup, provider reconfiguration, Amelia writes, or any authority
cutover.
