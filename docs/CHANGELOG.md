# Changelog

All notable changes to the Delnavazan Platform repository are documented here.
Platform phase numbers are independent of Hamnavaz phase numbers.

## 0.1.0 — 2026-09-09

Phase 2A.2-E merge main: `b1ad277b1ddbe0be49c024d6f39a1d68e6f584dd`

Schema 11 / migrations 001–011.

### Phase 2A.2-F — Student Identity & Acceptance Authority Foundation

- Added Schema 12 / migration `012_student_identity_acceptance_authority` on the Phase 2A.2-F candidate branch.
- Added human-reviewed, append-only Booking Request identity resolution and Student capacity classification records, versioned Student principal-link provenance, and effective/revocable guardian representative grants.
- Added protected internal administration and an informational authority-read service. No final acceptance, conversion, public workflow, calendar, payment, notification, or Amelia authority was added.
- Added atomic Student-principal supersession, a common authority lock order, strict guardian intervals with append-preserving expiry, real Schema 11 → 12 migration evidence, and an executable deterministic race harness.

### Phase 2A.2-E — Provisional Acceptance Evidence

- Merged Schema 11 / migration `011_provisional_acceptance_evidence` from reviewed candidate `740a29dbe3fce081ba0fd19d6b6259cb4d293134` through PR #14.
- Added append-only `accepted_pending_conditions` evidence with `authority_unresolved`; no final acceptance, Student/guardian authority, Accepted Service Arrangement, conversion or downstream operational authority.

### Phase 2A.2-D — Proposal Foundation

- Added Schema 10 / migration 010 for canonical Proposal Families, Teacher-specific Options, and immutable Versions.
- Added Assent-authorised initial and replacement issuance with Proposal-scoped idempotency and guarded pointer advancement.
- Added a dedicated protected coordination capability and internal issuance surface; no public endpoint or acceptance authority.
- Added source, migration, isolated-runtime, revision-race, and Assent-invalidation-race test coverage.
- Validated candidate `5b67442218baeba94b68d988f31f51e09ae1a58b` passed with non-blocking limitations and was merged through PR #13.

## Historical development record

### Phase 2A.0 — Principal invitation runtime-validation preparation

- Added an isolated-only, reversible WordPress/MySQL validation matrix and a
  WP-CLI delivery-preparation assertion helper. The helper is not packaged,
  creates no accounts, emits no raw secret, and requires an explicit
  non-production database marker.
- Stamped the clean runtime-validation package identity for the approved
  schema-version-3 Phase 2A.0 head. No deployment, provider send, Amelia
  change, or authority cutover is included.

### Phase 1 — Core Foundation & Controlled Runtime Validation

- Implemented the Phase 1 canonical schema, migration verification/locking,
  capabilities, Core identities, relationship validation, Lesson schedule
  history, archive safeguards, Operational Exceptions, and minimal protected
  engineering admin surfaces.
- Added a controlled Phase 1F beta validation runbook and clean-package
  preparation. Runtime validation remains pending; no business authority,
  Amelia dependency, provider integration, or Phase 2 work has begun.

### Product decision reconciliation — 3 September 2026

- Confirmed permanent Core Teacher and Student identities; contact details and
  provider IDs are attributes/mappings, and suspected duplicates never
  auto-merge.
- Confirmed separate first-class Instrument, Course, Enrolment, Term, and Lesson
  concepts, with Enrolment as the continuing relationship and Term as its
  bounded allocation/payment/renewal cycle.
- Confirmed that normal rescheduling preserves Lesson identity and audited
  schedule history; genuine replacement/make-up occurrences may be separately
  linked Lessons.
- Confirmed UTC canonical Lesson instants, explicit IANA timezones, retained
  recurring wall-clock intent, and separate Gregorian/Persian calendar
  presentation.
- Confirmed numeric internal keys plus immutable opaque ULID-style UIDs,
  approved `DZN-*` reference prefixes, and independent public-action
  capabilities.
- Confirmed explicit, administrator-authorized, audited, one-to-one optional
  Core Teacher ↔ Hamnavaz Profile links.
- Confirmed soft archive/default retention, separately authorized deletion or
  anonymization, and purpose-limited provider provenance.
- Confirmed effective-dated teacher rates and a teacher-rate/currency snapshot
  per Lesson, independent from Student pricing, as Platform Phase 9 Finance
  architecture rather than Phase 1 persistence.
- Confirmed the approved initial lifecycle vocabulary, Phase 1 Course fields,
  ULID-style UIDs/reference prefixes, custom-table direction, append-only Lesson
  Schedule Versions, introductory-Lesson relationship rules, Term replacement
  defaults, timezone onboarding, and Operational Exception framework.
- Corrected the initial Lesson vocabulary to `introductory`, `standard`, and
  `replacement`, and confirmed direct Lesson Student/Teacher/Course identity
  snapshots with creation-time Enrolment consistency validation.
- Removed premature Finance persistence from the proposed Phase 1 foundation;
  Finance tables, rate snapshots, and audited corrections remain Phase 9 work.
- Rejected the Phase 0 proposal for an automated Amelia importer, shadow
  synchronizer, parity engine, and repeatable mapping/checkpoint pipeline. The
  small active dataset will be recreated manually; historical Amelia data may
  be retained outside operational Core as a read-only archive.
- Renamed canonical Platform Phase 2 to **Core Data Setup & Cutover
  Preparation** without changing Phase 0–10 numbering.
- Preserved the runtime strangler strategy, authority ledger, bounded cutovers,
  rollback, and exit gates while prohibiting new Amelia data-model dependencies
  in Platform Core.

### Phase 0 — Existing System Audit & Architecture

- Established the initial canonical architecture for Core-owned Teacher,
  Student, Term/Enrolment, and Lesson identity/state; the combined
  Term/Enrolment question was resolved by the 3 September 2026 decision above.
- Defined Lesson as the operational centre while keeping attendance,
  notifications, scheduling, finance, reporting, and provider integrations in
  separate module boundaries.
- Documented the complete Delnavazan Enhancements migration map using KEEP,
  EXTRACT, REFACTOR, ADAPT, REPLACE, and RETIRE classifications.
- Defined the conceptual data model, `LegacyReference`, Google connection model,
  Hamnavaz/Core Teacher relationship, and state-separation rules.
- Defined the minimum security architecture for secrets, OAuth, webhooks, public
  lesson actions, authorization, logs, retention, and provider isolation.
- Considered a no-big-bang Amelia migration strategy with read-only imports and
  shadow comparison. The automated data-migration elements are superseded by the
  3 September 2026 decision above; per-capability authority cutovers, rollback,
  and exit gates remain current.
- Restored the canonical Platform Phase 0–10 roadmap and documented those
  migration mechanisms as cross-phase techniques rather than substitute phases.
- Scoped the proposed Platform Phase 1 Core foundation without implementing it.
- Added repository hygiene rules for local, secret-bearing, generated, export,
  and backup files.

### Current status

- Platform Phase 0 — Existing System Audit & Architecture is complete.
- Platform Phase 1 — Core Foundation & Canonical Data Model is implemented on
  its review branch and awaiting controlled Phase 1F beta runtime validation.
- Amelia remains installed, operational, authoritative, and readable.
- Hamnavaz Phase 4 remains intentionally paused.
