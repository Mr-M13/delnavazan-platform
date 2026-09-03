# Delnavazan Platform Conceptual Data Model

## 1. Purpose

This document defines canonical concepts and invariants. It deliberately does
not choose final WordPress table names, column types, ORM technology, or public
API shapes. Those physical decisions belong to an approved implementation phase.

## 2. Shared rules

- Every Core entity has a stable Delnavazan identifier that is independent of
  WordPress and external providers.
- Internal identifiers may be implementation-specific, but public capabilities
  use separate opaque, revocable values.
- External identifiers are represented by mappings and are never silently used
  as Core primary keys.
- Mutable attributes such as email, phone, display name, and username are not
  identity keys.
- Core timestamps are stored in UTC. A source timezone and provenance are kept
  wherever wall-clock meaning matters.
- Normal operational deletion uses soft archive. Permanent deletion follows an
  explicit retention and authorization policy.
- Every imported record retains provenance and can be reconciled repeatedly.
- Created/updated/imported/archived timestamps and actor/source metadata are
  auditable.

## 3. Relationship overview

```text
Teacher 1 ──────── * Term / Enrolment * ──────── 1 Student
                             │
                             │ 1
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

- `teacher_id` — stable Core identity;
- preferred/display name and normalized administrative name components;
- primary contact points needed for academy operations;
- locale and timezone with provenance;
- lifecycle state, such as active, inactive, or archived;
- created, updated, and archived audit metadata.

Provider accounts and WordPress users are mappings. Sensitive contact data is
not automatically public.

### Invariants

- A Teacher survives a changed email or replaced Amelia employee record.
- Merging duplicate teachers is explicit, audited, and redirects mappings to one
  survivor; it is never inferred from name alone.
- Academy operational state stays separate from the Hamnavaz listing lifecycle.
- A teacher may have multiple historical provider references and Google
  connections, but only approved active connections may be used.

## 5. Student

### Responsibility

Represents a stable learner identity independent of Amelia, Stripe, and
WordPress authentication.

### Conceptual attributes

- `student_id` — stable Core identity;
- preferred/display and administrative name fields;
- private contact channels;
- locale and explicit timezone with provenance;
- lifecycle/consent state needed for academy operation;
- optional portal-account link;
- created, updated, and archived audit metadata.

### Invariants

- A Student survives a changed email, phone, Stripe customer, Amelia customer,
  or WordPress user.
- Duplicate detection may propose matches but does not silently merge records.
- Stripe and Amelia mappings do not grant portal or record access.
- Notification consent and channel deliverability are separate from identity.

## 6. Term / Enrolment

### Responsibility

Represents the educational relationship between one Student and one Teacher for
an approved course/instrument context and allocation period. “Enrolment” is the
durable relationship; “Term” is the bounded allocation/lifecycle within that
relationship. Phase 1 may implement them as one aggregate initially if it keeps
those semantics explicit.

### Conceptual attributes

- `enrolment_id` and, if separated, `term_id`;
- `student_id` and `teacher_id`;
- course, instrument, or service reference owned by Academy;
- allocated lesson count and consumption policy;
- start/end dates and source timezone where applicable;
- lifecycle state;
- commercial/payment state references without storing provider identity as
  authority;
- created, updated, archived, and decision provenance.

### Invariants

- A Term / Enrolment belongs to exactly one Student and one Teacher at a time.
- Reassignment creates an audited transition rather than rewriting history.
- Lesson allocation and payment state are related but distinct; payment does not
  create a Lesson directly.
- Remaining-session calculations come from explicit allocation and canonical
  Lessons, not an untraceable provider count.
- Introductory/trial teaching is modelled explicitly rather than permanently
  depending on Amelia category ID `3`.

### Candidate lifecycle

`draft → active → completed`

With explicit branches for `paused`, `cancelled`, and `archived`. Final names and
transition rules require product-owner approval before implementation.

## 7. Lesson

### Responsibility

Represents one operational teaching occurrence and is the centre of scheduling,
attendance, notifications, Google evidence, finance, and reporting.

### Conceptual attributes

- `lesson_id` — stable Core identity;
- `enrolment_id` / `term_id`;
- participating `teacher_id` and `student_id` derived from the approved
  enrolment relationship;
- canonical `starts_at_utc` and `ends_at_utc`;
- source timezone and wall-clock provenance;
- scheduling lifecycle and reschedule/version history;
- sequence/allocation position where applicable;
- delivery mode and provider-neutral join-resource reference;
- created, updated, cancelled, completed, and archived audit metadata.

### Invariants

- A Lesson can exist before any provider appointment is created.
- Scheduling state is separate from Attendance outcome, Notification delivery,
  and Finance payability.
- Rescheduling records the prior schedule and invalidates or rotates affected
  public capabilities; it does not rewrite evidence already tied to an earlier
  occurrence without an audited correction.
- Cancellation preserves the Lesson and its source/provider history.
- External appointments/customer bookings map to a Lesson through
  `LegacyReference`; their IDs are not accepted as authorization.
- A public join/absence action never exposes a predictable `lesson_id`.

### Candidate scheduling lifecycle

At minimum the model must distinguish planned/scheduled, rescheduled, cancelled,
and completed occurrences. Whether `rescheduled` is a state or an event with a
new schedule version is a Phase 1 design decision. Attendance states do not
appear in this lifecycle.

## 8. Attendance

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

## 9. Notification

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

## 10. GoogleConnection

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

## 11. LegacyReference

### Responsibility

Provides traceable, idempotent mapping between external/legacy records and Core
entities without promoting provider IDs into business identity.

### Conceptual attributes

- `legacy_reference_id`;
- provider/source system;
- external entity type and external ID;
- Core entity type and Core entity ID;
- source version or account/tenant scope where necessary;
- first/last observed timestamps;
- source updated timestamp and safe fingerprint/import hash;
- import run/checkpoint and mapping provenance;
- active, superseded, conflicted, or retired state;
- optional redacted diagnostic, never a secret-bearing raw payload.

### Invariants

- The scoped `(provider, external_type, external_id)` mapping is unique unless an
  explicit historical-version policy says otherwise.
- Re-import updates the same mapping or records a conflict; it never creates an
  unbounded duplicate Core entity.
- A mapping cannot by itself authorize a user or public action.
- Conflicts require deterministic reporting and human review.
- Legacy references remain queryable after a provider is retired for audit and
  support.

## 12. Hamnavaz profile relationship

```text
Core Teacher
   └── optional explicit link to Hamnavaz Profile
```

The link is optional in both workflows:

- an academy Teacher may have no public directory profile;
- a Hamnavaz directory teacher may have no academy/Core Teacher.

The link must be explicitly confirmed and must not be inferred from matching
name, email, phone, or WordPress author. Where present it is one-to-one unless a
future product decision deliberately permits historical/superseded profiles.

Core retains academy identity and operational contact. Hamnavaz retains public
name/biography/media, discovery taxonomies, Country → normalized City →
Instrument filtering data, public contacts, listing status, verification state,
directory commercial information, and SEO presentation.

## 13. State separation matrix

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

## 14. Import and compatibility rules

- Amelia remains the source of truth during read-only import phases.
- Store both Amelia appointment and customer-booking references when required;
  one is not a substitute for the other.
- Imported timestamps are normalized to UTC while retaining source timezone and
  raw source timestamp where needed for parity.
- Repeat import must be idempotent and produce counts for created, updated,
  unchanged, conflicted, skipped, and failed records.
- Source deletions/cancellations create a traceable state transition; they do not
  erase completed Core history.
- Import does not grant portal access, send notifications, charge payments, or
  write back to Amelia.

## 15. Product decisions required before physical schema approval

1. Final Teacher and Student duplicate-resolution/merge policy.
2. Whether Term and Enrolment are separate records in the first schema or one
   aggregate with explicit term allocation.
3. Course/instrument catalogue ownership and versioning.
4. Final scheduling and enrolment state names and transition authority.
5. Reschedule history model and treatment of provider appointment replacements.
6. Canonical customer timezone capture, override, and fallback policy.
7. Financial rate/currency snapshot point and manual override audit rules.
8. Google disconnect-versus-revoke support policy and token-retention period.
9. Data retention periods for attendance evidence, provider snapshots,
   notifications, and archived identities.
10. One-to-one Core Teacher/Hamnavaz Profile linking workflow and conflict UI.
