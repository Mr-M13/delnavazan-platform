# Delnavazan Platform Product Decisions

## 1. Status

These decisions were approved on 3 September 2026 after Platform Phase 0. They
are binding inputs to the Platform Phase 1 implementation brief. They do not
authorise implementation, production data access, an Amelia cutover, or
Hamnavaz Phase 4.

## 2. Identity

- Teacher and Student are permanent Core identities.
- Email, phone, WordPress, Google, Stripe, Amelia, and other provider IDs are
  attributes or explicit mappings, never identity.
- Matching data may identify a possible duplicate but must never auto-merge
  Core identities.
- A merge requires an explicit, capability-protected administrator action that
  records the actor, reason, surviving identity, superseded identity, mapping
  changes, and time.

## 3. Academy model

- Enrolment and Term are separate first-class entities.
- Enrolment is the continuing Student–Teacher–Course relationship.
- Term is a bounded lesson-allocation, payment, and renewal cycle within one
  Enrolment.
- Instrument is shared Delnavazan reference data.
- Academy Course is the teaching product, references an Instrument, and is the
  catalogue item referenced by Enrolments.
- Phase 1 keeps Course fields to the minimum needed for identity, display,
  Instrument association, lifecycle, and audit.

## 4. Lesson scheduling

- Normal rescheduling preserves Lesson identity and appends an audited schedule
  version/history record.
- `rescheduled` is an event/history fact, not necessarily a terminal Lesson
  state.
- A genuine replacement or make-up occurrence may be a new Lesson explicitly
  linked to the original. Creating it must be an intentional business action,
  not an automatic consequence of changing time.

## 5. Timezone and calendar

- Canonical Lesson instants are stored in UTC.
- Teacher and Student have explicit IANA timezones.
- Academy teachers default to `Asia/Tehran`; an authorised user may change that
  value.
- Recurring schedules retain the intended wall-clock time and the IANA schedule
  timezone so future occurrences survive offset-rule changes correctly.
- Calendar and locale presentation are separate from timezone. Presentation
  supports at least Gregorian and Persian/Jalali calendars.
- Iran-based Persian teachers default to the Persian calendar.
- Calendar conversion never changes the canonical instant.

## 6. Identifiers and public capabilities

- Every Core entity uses an internal numeric primary key and an immutable,
  opaque UID.
- Operationally useful entities may additionally receive a human-readable
  `DZN-*` reference code.
- Numeric IDs, opaque UIDs, and reference codes are identifiers, not
  authorization credentials.
- Public actions use independent opaque or signed, purpose-bound capabilities.

## 7. Hamnavaz linking

- A Core Teacher ↔ Hamnavaz Profile link is optional, explicit,
  administrator-authorized, audited, and one-to-one where present.
- Matching fields may suggest a candidate link but never establish it.
- Linking or unlinking does not create or delete either a Core Teacher or a
  Hamnavaz Profile.

## 8. Retention and archive

- Historical business records are soft-archived and retained by default.
- Permanent deletion or anonymization is a separate, explicitly authorized
  process.
- Temporary technical data has an explicit retention policy.
- Provider provenance may be retained where it supports operations, audit, or
  support; unnecessary raw provider payloads need not be retained indefinitely.

## 9. Finance

- Teacher rates are effective-dated.
- Each Lesson receives a teacher-rate and currency snapshot.
- Changing a current rate does not recalculate historical completed or payable
  Lessons.
- A post-completion financial correction is explicit and audited.
- Student pricing and teacher compensation are separate concepts.

## 10. Amelia data setup and coexistence

The automated Amelia migration approach considered during Phase 0 is rejected.
The Platform will not build an Amelia importer, shadow synchronizer, parity
engine, repeatable import checkpoint system, or automated Amelia-to-Core mapping
pipeline for the initial data setup.

The live dataset is small enough for controlled manual recreation:

- approximately 13 services/courses;
- the handful of active teachers;
- the relevant active student or students;
- their Enrolments, Terms, and required Lessons.

Phase 2 therefore performs manual Core Data Setup & Cutover Preparation with
explicit counts, review, and validation. Historical Amelia data may be exported
and retained outside operational Core as a read-only archive when needed. The
archive is evidence, not an operational identity or synchronization source.

The runtime migration remains strangler-style. Amelia-dependent functionality
may continue temporarily in Delnavazan Enhancements while Attendance, Google,
Notifications/WhatsApp, Scheduling, Portals, Stripe, and Finance are replaced
through bounded, reversible cutovers. New Platform Core code must not acquire a
new Amelia data-model dependency.

Idempotency remains required for schema migrations, domain commands/events,
provider calls, and webhooks. Controlled before/after comparison remains a
validation technique for each replacement. Neither requirement reinstates an
Amelia importer, synchronizer, or parity engine.

## 11. Remaining decisions before relevant implementation

The approved direction does not yet choose:

- exact lifecycle enum names and transition authorities for Teacher, Student,
  Course, Enrolment, Term, and Lesson;
- the exact minimum Course fields and catalogue governance/versioning workflow;
- which entities receive `DZN-*` reference codes and their formatting rules;
- physical table, key, index, schedule-history, and financial-correction shapes;
- retention durations and anonymization rules for each business, technical, and
  archived provider-data class;
- Google failed-revoke support procedure and token-retention period;
- historical Amelia export format, storage location, access control, retention,
  and restore/inspection procedure.

These items require explicit resolution or deliberate deferral in the relevant
implementation brief. Platform Phase 1 remains not started until separately
authorised.
