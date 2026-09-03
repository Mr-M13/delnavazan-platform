# Changelog

All notable changes to the Delnavazan Platform repository are documented here.
Platform phase numbers are independent of Hamnavaz phase numbers.

## Unreleased

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
- Platform Phase 1 — Core Foundation & Canonical Data Model is not started and
  requires explicit approval.
- Amelia remains installed, operational, authoritative, and readable.
- Hamnavaz Phase 4 remains intentionally paused.
