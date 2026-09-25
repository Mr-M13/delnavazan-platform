# Delnavazan Platform Migration Strategy

## 028_payment_execution_seam_provider_adapter (Phase 2A.2-T, candidate, unmerged)

Additive on top of the Phase-V candidate at Schema 27. It creates exactly sixteen tables
(`payment_provider_accounts`, `_account_events`, `_account_commands`, `payment_provider_objects`,
`_object_events`, `_object_commands`, `payment_provider_secrets`, `payment_execution_commands`,
`payment_execution_attempts`, `payment_execution_results`, `payment_execution_dispatches`,
`payment_provider_event_receipts`, `payment_provider_events`, `payment_provider_event_decisions`, `payment_provider_event_decision_claims`,
`payment_provider_secret_events`), performs no backfill, infers no provider account, mapping, secret or
event, adds no column to any existing table, opens no provider connection and makes no external call.
`verify_payment_execution_schema()` runs after migration 028, on current-schema verification and
unconditionally before the schema option may advance to 28 — including the retained-028/stale-version
path — and it rejects a table outside the declared set, a non-InnoDB table, a missing `id`/`PRIMARY
KEY(id)` or `uid`/`UNIQUE uid`, a raw-reference column, a plaintext credential column, a mutable column on
an append-only table, a digest outside the six declared optional digests, a `*_id` whose parent does not
itself declare the identity it is referenced by, a dispatch-claim defect (missing arbitration index, a
non-`DISPATCH_STATES` state, two live claims on one subject, a claim coexisting with anything but its own
descriptor refusal, an incomplete or tampered sealed envelope, a non-positive generation, a lease rule
violation), a NULL-able or wrongly-ordered secret scope, and any academic, notification or settlement
table smuggled into the phase. The R1 and R2 verifiers are re-run rather than duplicated, so the phase
must add no column to any `commercial_*` or R2 table. Schema 25 → 28 and 26 → 28 are repeat-safe and leave
every R1 and R2 row unchanged. **The migration has not been executed in this environment: PHP and the
disposable WordPress + MariaDB runtime are unavailable.**

`025_commercial_purchase_funding_authority` (Phase 2A.2-R1, **merged and closed on `main`**) is
additive only: it creates the commercial purchase, offer, obligation, funding, pattern,
protected-capacity and exception storage, performs no backfill, infers no purchase, settles no
obligation, creates no Term/Lesson/schedule row and makes no external call. It creates no
provider-specific column, no notification table and no cross-Term recurring-enrolment storage. Its
verifier runs after migration 025, on current-schema verification and unconditionally before Schema
25 activation (including the retained-025/stale-version path), and rejects provider-specific
columns, mutable columns on append-only evidence/history/command tables, non-InnoDB tables,
malformed digest columns and any academic or notification table smuggled into the phase.

`026_renewal_recurring_enrolment_authority` (Phase 2A.2-R2 **candidate**) is additive only: it
creates the cross-Term recurring-enrolment, renewal-cycle, collection-intent, recovery-case,
refund-review and continuous-protection storage with their append-only events and digest-only
commands, performs no backfill, infers no renewal, settles no obligation, creates no Term, Lesson,
schedule, notification or provider row and makes no external call. Schema 25 → 26 is repeat-safe and
leaves every R1 row unchanged. Its verifier
(`verify_renewal_recurring_enrolment_schema()`) runs after migration 026, on current-schema
verification and unconditionally before Schema 26 activation (including the
retained-026/stale-version path), and rejects provider-specific columns, a mutable column on any
append-only event/command table, a raw key/reference column beside the keyed digests, non-InnoDB
tables, malformed `char(64)` digests, a non-nullable refund `academic_consequence`, and any
Lesson/Term/schedule/notification table smuggled into the phase.

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

## Current authoritative migration — Schema 25 (authoritative on `main`)

### Schema 26 — renewal, next-Term, recurring collection, recovery, lapse & refund review authority (Phase 2A.2-R2 **candidate**)

`026_renewal_recurring_enrolment_authority` is additive only: it creates the Phase-R2 recurring-enrolment aggregate with append-only events and digest-only commands, the renewal-cycle aggregate and its `boundary_derived_at`/`guarantee_deadline_at`/`collection_mode`/frozen whole-Term `amount_minor` snapshot, provider-neutral collection intents, recovery cases, refund/reversal review cases (with `academic_consequence` deliberately nullable and never written) and continuous cross-Term protections. It performs no backfill, infers no renewal, computes no provider charge, creates no Term/Lesson/schedule/notification row and makes no external call. Schema 25 → 26 is repeat-safe and leaves every R1 row unchanged; `dzn_recurring_enrolments.enrolment_id`, `dzn_renewal_cycles.cycle_sequence`, `dzn_collection_intents.cycle_obligation`, `dzn_recovery_cases.intent_case`, `dzn_refund_review_cases.evidence_case` and `dzn_recurring_protections.cycle_protection`/`claim_protection` are the named unique indexes that arbitrate duplicate creation. Its verifier runs after migration 026, on current-schema verification and unconditionally before Schema 26 activation (including the retained-026/stale-version path). Only the six aggregate roots are mutable (state/version, mode, guarantee deadline, charge/failure facts, resolution note); every event and command table stays append-only and digest-only.

### Schema 25 — commercial purchase, funding & current-Term capacity authority (Phase 2A.2-R1, merged and closed)

`025_commercial_purchase_funding_authority` is additive only: it creates the versioned runtime commercial policy registry (class-B keys only), product/price, promotions and immutable redemptions, account adjustments with append-only events, immutable purchase offers with their pricing snapshot, applied adjustments, applied policy versions and ordered payment obligations, purchases, bounded entitlements, Term funding plans, provider-neutral payment evidence/facts/settlements, the canonical Regular recurring pattern, current-Term protected-capacity claims with per-interval state, the commercial exception registry and digest-only commercial command evidence. Money is an exact integer number of minor units with an explicit currency. It performs no backfill, infers no purchase, settles no obligation, creates no Term/Lesson/schedule row and makes no external call. Its verifier runs after migration 025, on current-schema verification and unconditionally before Schema 25 activation (including the retained-025/stale-version path).

### Schema 24 — post-intro continuation & slot reservation authority (Phase 2A.2-Q, merged and closed)

`024_post_intro_continuation_slot_reservation_authority` is additive only: it creates the Phase-Q explicit first-regular-slot authority, continuation case, decision, reservation, administrator-intervention and command tables, performs no backfill and invents no continuation decision or reservation. It creates no payment object, `payment_state`, Term, standard/replacement Lesson, academy obligation or delivery/attendance outcome, and migration 024 makes no external call. `dzn_canonical_continuation_slot_authorities` is immutable and is the only source that may justify a capacity hold — no weekly recurrence is ever derived from the introduction. Only `dzn_canonical_continuation_cases` (current decision/case version) and `dzn_canonical_continuation_reservations` (lifecycle state/version) are mutable; decisions, interventions and commands stay append-only and digest-only. Its verifier runs after migration 024, on current-schema verification and unconditionally before Schema 24 activation (including the retained-024/stale-version path), and rejects payment/provider-specific columns, missing indexes and any mutable column on an append-only Phase-Q table.

### Schema 23 — canonical attendance intake & review authority (Phase 2A.2-P, merged and closed, predecessor)

`023_canonical_attendance_intake_authority` is additive only: it creates the Phase-P cutover-policy, participant-identity-mapping, case, evidence, decision, anomaly, durable-conflict and command tables, performs no historical attendance import, no dual-read, activates no production cutover and reinterprets no Phase-O data. `dzn_canonical_attendance_participant_mappings` is the durable provider-neutral provider-account → canonical-participant identity registry, and `dzn_canonical_attendance_conflicts` records refused conflicting provider events; `dzn_canonical_attendance_cases` stores the exact immutable cutover-policy binding. `dzn_canonical_attendance_cutover_policies` enforces one immutable policy per cutover instant (`UNIQUE cutover_instant`), so applicability can never fall back to the database insertion id. Its verifier runs after migration 023, on current-schema verification and unconditionally before Schema 23 activation (including the retained-023/stale-version path), and rejects provider-specific storage, missing registry/conflict/cutover-instant indexes, a nullable cutover-policy binding and any mutable column on an append-only Phase-P table. Only `canonical_attendance_cases` (state/case version) and `canonical_attendance_participant_mappings` (state/mapping version) are mutable.

Schema 22 / `022_canonical_lesson_delivery_attendance_authority` remains authoritative and is the direct predecessor of Schema 23. It retains Schema 21's canonical Lesson scheduling, Teacher capacity and terminal lifecycle, legacy `legacy_phase1` isolation, immutable Assignment/Teacher provenance, append-only lifecycle and digest-only command evidence, and adds the Phase-O delivery/attendance outcome and academy-obligation authority described below. It creates no payment, notification, calendar, Amelia or provider authority.

### Schema 22 — canonical Lesson delivery & attendance outcome authority (Phase 2A.2-O, merged and closed)

`022_canonical_lesson_delivery_attendance_authority` is additive only. It creates `dzn_canonical_lesson_delivery_outcomes` (append-only outcome history with one applicable outcome per Lesson, bound to the exact canonical schedule version, occurrence start/end and any reconciled completion event), `dzn_canonical_lesson_delivery_commands` (digest-only durable command evidence) and `dzn_canonical_academy_obligations` (immutable academy-owed occurrences, `UNIQUE(source_lesson_id)`, independent of Term lifecycle). It adds no column to `dzn_lessons`, never reclassifies a Phase-M replacement, and performs **no** legacy attendance backfill, no dual-read and no provider or Amelia coupling. The verifier runs after migration 022, on current-schema verification, and unconditionally before the schema option may advance whenever migration 022 is already recorded.

## Earlier authoritative migration — Schema 18 / migration 018

`017_canonical_term_foundation` additively classifies every existing Term as `legacy_phase1`, preserving exact Phase-1 status, payment, allocation, date, sequence and archive facts. It adds nullable canonical lifecycle/applicability fields, one-applicable-Term-per-Enrolment arbitration and an append-only InnoDB `term_lifecycle_events` table. It does not scan for or infer canonical Term authority and creates no canonical Term rows.

The capability marker advances to `2a2k` only to retain and repair the existing administrator `dzn_manage_terms` read/legacy-management capability. No canonical creation capability is added. Migration verification checks the default record model, exact indexes, InnoDB history, digest shape, absence of a Term Teacher field and absence of mutable history.

This is a storage/read foundation only. It introduces no ordinary canonical Term writer, lifecycle command, command-idempotency table, Lesson/consumption authority, scheduling, payment, renewal, notification, calendar, Amelia, Hamnavaz or CRM authority. Schema 18 / `018_canonical_term_authority` is authoritative after independent review and merge. Migration 018 adds immutable digest-only canonical-Term command evidence and repairs `dzn_manage_canonical_terms`; it performs no Term backfill, lifecycle inference, legacy translation or downstream integration.

## Schema 16 / migration 016

`016_teacher_assignment_foundation` adds the three Teacher Assignment tables without scanning or backfilling Enrolments. An Enrolment with zero Assignment remains valid after upgrade. Migration verification requires InnoDB, exact columns, digest shapes, append-only evidence tables, and the applicable-slot/sequence/lineage indexes. The capability marker advances to `2a2j` and repairs both administrator management and exact authenticated-Teacher acceptance capabilities on repeated startup.

The migration is storage-only: it does not infer Assignment authority from `enrolments.teacher_id`, availability, eligibility, proposal history, or current time. It performs no downstream cutover and changes no Term, Lesson, scheduling, capacity, payment, notification, calendar, Amelia, Hamnavaz, or CRM authority. The disposable runtime reconstructs a Schema 15 state, executes 015 → 016, verifies repeat safety/capability repair, and then exercises the Assignment application boundary.

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


## Schema 10 — 010_proposal_foundation

Migration 010 creates the three InnoDB Proposal tables after Schema 9. Verification checks all required columns, engine type, canonical Family/Option uniqueness, linear Version/supersession constraints, idempotency and material-fingerprint uniqueness, the guarded current pointer, and the absence of a mutable `updated_at` field on Proposal Version.

The migration is recorded once in `dzn_platform_completed_migrations`; `maybe_upgrade()` verifies rather than re-applies it after the schema marker reaches 10. Capability version `2a2d` repairs the dedicated administrator capability `dzn_issue_booking_request_proposals` independently of table creation.
# Schema 13

Migration `013_final_acceptance_arrangement_foundation` adds append-only `accepted_service_arrangements` and `proposal_option_outcome_events`. It is repeat-safe through the completed-migration ledger, verifies InnoDB, required immutable columns, and uniqueness for one arrangement per Proposal Family/Option and one outcome per Option, and repairs `dzn_finalize_service_arrangements` through capability marker `2a2g`.

# Schema 14

Migration `014_canonical_enrolment_foundation` additively classifies every existing Enrolment as `legacy_phase1`, preserves its exact status and historical Teacher value, makes Teacher context nullable for the future canonical model, and adds canonical provenance, lifecycle, applicability-slot and lineage columns. It adds uniqueness for Accepted Service Arrangement provenance and one applicable canonical Enrolment per Student + Course, plus append-only lifecycle-event storage. Verification runs before the ledger entry is recorded and on every current-schema load; repeated upgrade does not rewrite legacy rows.

# Schema 15

Migration `015_enrolment_conversion_authority` additively creates the InnoDB `enrolment_identity_roots` and `enrolment_conversion_commands` tables. Verification requires every semantic column, absence of mutable `updated_at`, and exact uniqueness for Student + Course identity, command UID/digest, source provenance and resulting Enrolment before ledger completion. Repeated upgrade verifies the schema and repairs capability `dzn_convert_service_arrangements_to_enrolments` through marker `2a2i` without rewriting domain rows.

### 018_canonical_term_authority
Additive Schema 17→18 migration creating only `dzn_term_commands` and repairing the administrator `dzn_manage_canonical_terms` capability. It performs no Term backfill, lifecycle inference, legacy translation or downstream integration.

### Schema 19 — canonical Enrolment lifecycle authority

`019_canonical_enrolment_lifecycle_authority` additively creates immutable `enrolment_lifecycle_commands` and repairs the dedicated administrator capability. It does not alter or backfill Enrolments, infer lifecycle, rewrite conversion evidence, or add canonical Lesson storage. Existing lifecycle history is strengthened by the explicit legal transition graph.

### Schema 21 — canonical Lesson scheduling & Teacher capacity authority (Phase 2A.2-N candidate)

`021_canonical_lesson_schedule_authority` is additive only. It creates `dzn_teacher_schedule_roots`, `dzn_canonical_lesson_schedule_versions`, `dzn_canonical_lesson_schedule_events` and `dzn_canonical_lesson_schedule_commands`, verifies their engines, columns and indexes, repairs the `dzn_manage_canonical_lesson_schedules` and `dzn_override_canonical_lesson_schedule_availability` administrator capabilities, and backfills one serialization root per existing Teacher.

It performs **no** legacy schedule backfill, no reinterpretation, no authority translation and no dual-read: legacy `lesson_schedule_versions` rows belong to legacy Phase-1 Lessons only, and canonical Lessons provably hold no legacy scheduling projection (`current_schedule_version_id` is `NULL` at issuance and the legacy writer rejects canonical rows). Canonical scheduling history therefore begins empty. The rehearsal path verified in the runtime covers a fresh Schema 21 install, an exact Schema 20 → 21 upgrade with repeat safety, legacy preservation, capability repair and Teacher-root backfill.
