# Delnavazan Platform Conceptual Data Model

## 1. Purpose

This document defines canonical concepts and invariants. It deliberately does
not choose final WordPress table names, column types, ORM technology, or public
API shapes. Those physical decisions belong to an approved implementation phase.

## 2. Shared rules

- Every Core entity has an internal numeric primary key and an immutable opaque
  UID, both independent of WordPress and external providers.
- Operationally useful entities may also receive a human-readable `DZN-*`
  reference code. Numeric IDs, UIDs, and reference codes never authorize access.
- Public actions use independent opaque or signed, purpose-bound, revocable
  capabilities.
- External identifiers are represented by mappings and are never silently used
  as Core primary keys.
- Mutable attributes such as email, phone, display name, and username are not
  identity keys.
- Canonical Lesson timestamps are stored in UTC. Teacher, Student, and recurring
  schedules use explicit IANA timezones, retaining wall-clock intent where it
  matters.
- Calendar/locale presentation is separate from timezone, supports at least
  Gregorian and Persian/Jalali, and never changes a canonical instant.
- Normal operational deletion uses soft archive. Permanent deletion follows an
  explicit retention and authorization policy.
- Required provider provenance is retained only for an explicit operational,
  audit, or support purpose.
- Created, updated, archived, merged, linked, and corrected actions retain
  auditable actor/source metadata.

## 3. Relationship overview

```text
Instrument 1 ─── * Course 1 ─── * Enrolment * ─── 1 Student
                                  │
Teacher 1 ────────────────────────┘
                                  │
                                  * Term
                                  │
                                  * Lesson
                                    ├── 0..1 Attendance aggregate
                                    ├── 0..* Attendance evidence items
                                    ├── 0..* Notifications
                                    └── 0..* Legacy / provider references

Teacher 1 ──────── 0..* GoogleConnection
Teacher 1 ──────── 0..1 optional Hamnavaz Profile link

Any mapped Core entity 1 ──────── 0..* LegacyReference
```

## 4. Teacher

### Responsibility

Represents a stable Delnavazan academy teacher identity. It is not a Google
account, Amelia employee, WordPress user, or Hamnavaz profile.

### Conceptual attributes

- internal numeric primary key and immutable opaque `teacher_uid`;
- preferred/display name and normalized administrative name components;
- primary contact points needed for academy operations;
- locale, calendar preference, and explicit IANA timezone with provenance;
- lifecycle state, such as active, inactive, or archived;
- Finance-owned effective-dated teacher-rate history;
- created, updated, and archived audit metadata.

Provider accounts and WordPress users are mappings. Sensitive contact data is
not automatically public.

### Invariants

- A Teacher survives a changed email or replaced Amelia employee record.
- Matching data may suggest a duplicate but never merges Teachers automatically.
  A merge is an explicit, administrator-authorized, audited action that redirects
  approved mappings to one survivor.
- Academy teachers default to `Asia/Tehran`; an authorized user may change the
  timezone. Iran-based Persian teachers default to the Persian calendar.
- Academy operational state stays separate from the Hamnavaz listing lifecycle.
- A teacher may have multiple historical provider references and Google
  connections, but only approved active connections may be used.
- Changing the current teacher rate never recalculates a completed or payable
  Lesson snapshot.

## 5. Student

### Responsibility

Represents a stable learner identity independent of Amelia, Stripe, and
WordPress authentication.

### Conceptual attributes

- internal numeric primary key and immutable opaque `student_uid`;
- preferred/display and administrative name fields;
- private contact channels;
- locale, calendar preference, and explicit IANA timezone with provenance;
- lifecycle/consent state needed for academy operation;
- optional portal-account link;
- created, updated, and archived audit metadata.

### Invariants

- A Student survives a changed email, phone, Stripe customer, Amelia customer,
  or WordPress user.
- Duplicate detection may propose matches but never merges records
  automatically. A merge requires an explicit, administrator-authorized,
  audited action.
- Stripe and Amelia mappings do not grant portal or record access.
- Notification consent and channel deliverability are separate from identity.

## 6. Instrument

### Responsibility

Represents shared Delnavazan reference data for a musical instrument. Instrument
identity is not owned by an Amelia service, an Academy Course, or a Hamnavaz
taxonomy term, although explicit mappings may connect them.

### Minimum conceptual attributes

- internal numeric primary key and immutable opaque UID;
- canonical display name and normalized administrative key;
- lifecycle/archive and audit metadata;
- optional `DZN-*` reference code if operationally justified.

## 7. Course

### Responsibility

Represents the Academy teaching product. A Course references one Instrument and
is the catalogue item selected by an Enrolment. Phase 1 keeps its fields minimal
and does not mirror Amelia service configuration.

### Minimum conceptual attributes

- internal numeric primary key and immutable opaque UID;
- course display name;
- required `instrument_id`;
- minimum lifecycle/archive and audit metadata;
- optional `DZN-*` reference code if operationally justified.

## 8. Enrolment

### Responsibility

Represents the continuing relationship between one Student, one Teacher, and one
Course. It is independent of any single payment, renewal cycle, or lesson block.

### Conceptual attributes

- internal numeric primary key and immutable opaque `enrolment_uid`;
- `student_id` and `teacher_id`;
- required `course_id`;
- start date and optional end date;
- lifecycle state;
- created, updated, archived, and decision provenance.

### Invariants

- An Enrolment belongs to exactly one Student, one Teacher, and one Course at a
  time.
- Reassignment creates an audited transition rather than rewriting history.
- A new Term does not create a new Enrolment when the continuing relationship is
  unchanged.

## 9. Term

### Responsibility

Represents one bounded lesson-allocation, payment, and renewal cycle within an
Enrolment. Enrolment and Term are separate first-class entities from Phase 1.

### Conceptual attributes

- internal numeric primary key and immutable opaque `term_uid`;
- required `enrolment_id`;
- allocated lesson count and consumption policy;
- start/end dates and lifecycle state;
- student commercial/payment references without using a provider as identity;
- created, updated, archived, and decision provenance.

### Invariants

- A Term belongs to exactly one Enrolment.
- Lesson allocation and payment state are related but distinct; payment does not
  create a Lesson directly.
- Remaining-session calculations come from explicit allocation and canonical
  Lessons, not an untraceable provider count.
- Introductory/trial teaching is modelled explicitly rather than permanently
  depending on Amelia category ID `3`.

### Candidate lifecycles

Enrolment and Term each require their own lifecycle and may use concepts such as
draft, active, paused, completed, cancelled, and archived. There is no combined
Enrolment/Term status. Final names and transition rules require product-owner
approval before implementation.

## 10. Lesson

### Responsibility

Represents one operational teaching occurrence and is the centre of scheduling,
attendance, notifications, Google evidence, finance, and reporting.

### Conceptual attributes

- internal numeric primary key and immutable opaque `lesson_uid`;
- required `enrolment_id` and `term_id`;
- participating `teacher_id` and `student_id` derived from the approved
  enrolment relationship;
- canonical `starts_at_utc` and `ends_at_utc`;
- source timezone and wall-clock provenance;
- scheduling lifecycle and append-only reschedule/version history;
- optional replacement/make-up link to the original Lesson;
- sequence/allocation position where applicable;
- effective teacher-rate and currency snapshot used by Finance;
- delivery mode and provider-neutral join-resource reference;
- created, updated, cancelled, completed, and archived audit metadata.

### Invariants

- A Lesson can exist before any provider appointment is created.
- Scheduling state is separate from Attendance outcome, Notification delivery,
  and Finance payability.
- Normal rescheduling preserves Lesson identity, appends the prior schedule to
  audited history, and invalidates or rotates affected public capabilities. It
  does not rewrite evidence tied to an earlier occurrence without an audited
  correction.
- A genuine replacement or make-up occurrence may be a new Lesson explicitly
  linked to the original; it is not created implicitly by an ordinary time
  change.
- Cancellation preserves the Lesson and its source/provider history.
- External appointments/customer bookings map to a Lesson through
  `LegacyReference`; their IDs are not accepted as authorization.
- A public join/absence action never exposes a predictable `lesson_id`.
- The teacher-rate/currency snapshot for a completed or payable Lesson is not
  recalculated when the current Teacher rate changes. Post-completion corrections
  are explicit and audited; Student pricing remains separate.

### Candidate scheduling lifecycle

At minimum the model must distinguish planned/scheduled, cancelled, and completed
occurrences. `rescheduled` is an audited event/history fact rather than a required
terminal state. Exact state names and transition authorities remain an
implementation decision. Attendance states do not appear in this lifecycle.

## 11. Attendance

### Responsibility

Records the attendance outcome and the evidence used to reach it for one Core
Lesson.

### Conceptual attributes

- `attendance_id` and required `lesson_id`;
- outcome such as pending, student absence reported, attended, no-show, or
  review required;
- source and method of the current decision;
- student/teacher participant evidence and overlap duration;
- join-link click evidence;
- human review note and audited manual decision;
- outcome timestamps and archive metadata.

Evidence items may be modelled separately so provider payload observations,
clicks, and manual decisions do not overwrite one another.

### Invariants

- A link click is evidence only and never completes a Lesson.
- Automatic attendance requires the assigned Teacher and booked Student to be
  identified in the same conference and overlap for the configured minimum.
- Missing identities, missing intervals, ambiguous matches, or insufficient
  overlap produce review rather than automatic attendance.
- A student absence report is idempotent and cannot overwrite a final attended
  or no-show decision.
- A provider or notification failure cannot undo an accepted absence report.
- Manual correction records actor, time, prior outcome, new outcome, and reason.

## 12. Notification

### Responsibility

Represents a requested communication independently of channel/provider delivery.

### Conceptual attributes

- `notification_id`;
- workflow key and version;
- optional `lesson_id`, `enrolment_id`, Teacher, or Student context;
- intended recipient entity/contact reference;
- channel and template key/version/language;
- rendered parameter snapshot or safe template data reference;
- scheduled time, attempts, provider message IDs, and delivery events;
- idempotency key;
- lifecycle, error code, and redacted diagnostic;
- created, completed, and retention metadata.

### Candidate lifecycle

`requested → queued → attempting → accepted → sent → delivered → read`

With terminal or diagnostic branches for `skipped`, `failed`, and `cancelled`.
Provider ordering rules must prevent a late lower-rank event from regressing an
already advanced state while still retaining the raw event for audit.

### Invariants

- Eligibility belongs to a versioned workflow policy, not the transport.
- A production send has a deterministic idempotency key.
- Test sends use an administrator-controlled recipient and never set production
  send history.
- Provider acceptance and later delivery/read/failure are distinct facts.
- Only known provider message IDs can update a Notification.
- Templates and parameters never contain secrets.

## 13. GoogleConnection

### Responsibility

Represents renewable, revocable Google authorization for a Core Teacher.

### Conceptual attributes

- `google_connection_id` and `teacher_id`;
- verified Google subject and email/display identity;
- OAuth client/configuration key and connection source;
- encrypted access and refresh tokens;
- granted scopes and token expiry;
- connected, refreshed, invalidated, disconnected, and revoked timestamps;
- last redacted error and health state;
- created/updated actor and provenance.

### Invariants

- The connection directly references `teacher_id`; email is an attribute, not a
  foreign key.
- OAuth state is unpredictable, short-lived, one-use, and bound to the initiating
  Teacher and return target.
- The granted Google identity must match the teacher identity approved for that
  flow under an explicit matching rule.
- Requested scopes are the minimum required for the approved capability.
- Access and refresh tokens are encrypted at rest and never logged or returned to
  a public client.
- Disconnect disables local use immediately. Revoke attempts provider revocation
  and records whether it succeeded; support policy defines safe handling when the
  provider is unavailable.

## 14. External and legacy references

### Responsibility

Provides traceable mapping between external/legacy records and Core entities
without promoting provider IDs into business identity. Phase 1 needs a minimal
mapping contract for approved manual setup and later integrations, not an Amelia
import pipeline.

### Conceptual attributes

- `legacy_reference_id`;
- provider/source system;
- external entity type and external ID;
- Core entity type and Core entity ID;
- source version or account/tenant scope where necessary;
- first/last observed timestamps;
- mapping source, actor, reason, and created/updated timestamps;
- active, superseded, or retired state;
- optional redacted diagnostic, never a secret-bearing raw payload.

### Invariants

- The scoped `(provider, external_type, external_id)` mapping is unique unless an
  explicit historical-version policy says otherwise.
- A mapping cannot by itself authorize a user or public action.
- A mapping candidate cannot auto-create or merge a Core identity. Conflicts
  require explicit administrator review.
- Legacy references remain queryable after a provider is retired for audit and
  support.

## 15. Hamnavaz profile relationship

```text
Core Teacher
   └── optional explicit link to Hamnavaz Profile
```

The link is optional in both workflows:

- an academy Teacher may have no public directory profile;
- a Hamnavaz directory teacher may have no academy/Core Teacher.

The link must be explicitly confirmed by an authorized administrator and audited.
It must not be inferred from matching name, email, phone, or WordPress author;
those fields may only suggest a candidate. Where present it is one-to-one.
Linking or unlinking does not create or delete either identity.

Core retains academy identity and operational contact. Hamnavaz retains public
name/biography/media, discovery taxonomies, Country → normalized City →
Instrument filtering data, public contacts, listing status, verification state,
directory commercial information, and SEO presentation.

## 16. State separation matrix

| Concern | Owning model/module | Example states |
| --- | --- | --- |
| Lesson scheduling | Lesson / Core or Academy service | scheduled, cancelled, completed |
| Attendance outcome | Attendance | pending, absence reported, attended, review needed, no-show |
| Notification delivery | Notification | requested, accepted, delivered, failed |
| Payability | Finance record/policy | pending, payable, non-payable, overridden |
| Archive | Each owning aggregate | active, archived |
| Hamnavaz publication | Hamnavaz Profile | draft, active, verification due, on hold, archived |

One state change may emit events used by other modules, but it does not directly
overwrite their state.

## 17. Manual setup and coexistence rules

- Phase 2 manually creates and validates the small initial catalogue, active
  identities, Enrolments, Terms, and required Lessons.
- Manual setup records actor, source/provenance where needed, validation outcome,
  and counts without introducing an Amelia importer or synchronizer.
- Existing Amelia-dependent runtime functionality remains in Delnavazan
  Enhancements until its bounded replacement phase transfers authority.
- New Core code does not query Amelia or acquire an Amelia data-model dependency.
- Historical Amelia data may be exported to an access-controlled, read-only
  archive outside operational Core under an approved retention procedure.
- Historical source cancellation or deletion never erases completed Core
  business history.
- Manual mapping never grants portal access, sends notifications, charges
  payments, or authorizes a public action.

## 18. Approved direction and remaining design work

The binding decisions are recorded in
[PRODUCT-DECISIONS.md](PRODUCT-DECISIONS.md). Remaining physical or workflow
choices include:

1. Exact lifecycle enum names and transition authorities.
2. Exact minimum Course fields and catalogue governance/versioning workflow.
3. Which entities receive `DZN-*` reference codes and their format.
4. Physical table, index, schedule-history, and financial-correction shapes.
5. Retention durations and anonymization rules by data class.
6. Google failed-revoke support policy and token-retention period.
7. Historical Amelia archive format, storage, access, and inspection procedure.
