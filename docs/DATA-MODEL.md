# Delnavazan Platform Conceptual Data Model

## 1. Purpose

This document defines canonical concepts and invariants. The approved Phase 1
storage direction is dedicated WordPress-prefixed InnoDB custom tables using
`$wpdb->get_charset_collate()`: `{prefix}dzn_teachers`,
`{prefix}dzn_students`, `{prefix}dzn_instruments`, `{prefix}dzn_courses`,
`{prefix}dzn_enrolments`, `{prefix}dzn_terms`, `{prefix}dzn_lessons`,
`{prefix}dzn_lesson_schedule_versions`,
`{prefix}dzn_operational_exceptions`, and
`{prefix}dzn_teacher_profile_links`. It does not authorise creating them.

Application and schema versions are separate. Defined business fields use
defined columns; generic JSON metadata columns are not a substitute for Core
business fields. Final column types, indexes, and implementation mechanics
belong to the approved Phase 1 implementation brief.

## 2. Shared rules

- Every Core entity has an internal numeric primary key and an immutable opaque
  ULID-style UID, both independent of WordPress and external providers.
- Operational entities use human-readable reference codes: Teacher `DZN-TCH-`,
  Student `DZN-STU-`, Instrument `DZN-INS-`, Course `DZN-CRS-`, Enrolment
  `DZN-ENR-`, Term `DZN-TRM-`, and Lesson `DZN-LSN-`. Numeric padding is a
  minimum display format, not a maximum; `DZN-LSN-001842` is one example.
  Numeric IDs, UIDs, and reference codes never authorize access.
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
                                  * standard/replacement Lesson
                                    ├── 0..1 Attendance aggregate
                                    ├── 0..* Schedule Versions
                                    ├── 0..* Notifications
                                    └── 0..* Legacy / provider references

Student 1 ─── 0..* introductory Lesson (no Enrolment or Term required)
Any Core entity 1 ─── 0..* OperationalException

Teacher 1 ──────── 0..* GoogleConnection
Teacher 1 ──────── 0..1 optional Hamnavaz Profile link

Any mapped Core entity 1 ──────── 0..* LegacyReference
```

## 4. Teacher

### Responsibility

Represents a stable Delnavazan academy teacher identity. It is not a Google
account, Amelia employee, WordPress user, or Hamnavaz profile.

### Conceptual attributes

- internal numeric primary key, immutable opaque ULID-style `teacher_uid`, and
  required `DZN-TCH-` human reference code;
- preferred/display name and normalized administrative name components;
- primary contact points needed for academy operations;
- locale, calendar preference, and explicit IANA timezone with provenance;
- lifecycle state: `active`, `inactive`, or `archived`;
- created, updated, and archived audit metadata.

Provider accounts and WordPress users are mappings. Sensitive contact data is
not automatically public.

### Invariants

- A Teacher survives a changed email or replaced Amelia employee record.
- Matching data may suggest a duplicate but never merges Teachers automatically.
  A merge is an explicit, administrator-authorized, audited action that redirects
  approved mappings to one survivor.
- Academy teachers default to country Iran, `Asia/Tehran`, and Persian
  locale/calendar; an authorized user may change each value.
- Academy operational state stays separate from the Hamnavaz listing lifecycle.
- A teacher may have multiple historical provider references and Google
  connections, but only approved active connections may be used.

## 5. Student

### Responsibility

Represents a stable learner identity independent of Amelia, Stripe, and
WordPress authentication.

### Conceptual attributes

- internal numeric primary key, immutable opaque ULID-style `student_uid`, and
  required `DZN-STU-` human reference code;
- preferred/display and administrative name fields;
- private contact channels;
- human-readable country and city, stored separately from resolved timezone;
- locale, calendar preference, and explicit IANA timezone with provenance;
- lifecycle state: `active`, `inactive`, or `archived`; consent state remains
  separate;
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
- Changing a Student timezone through Profile/Preferences changes display only;
  it never reschedules an existing Lesson or changes a recurring schedule.

## 6. Instrument

### Responsibility

Represents shared Delnavazan reference data for a musical instrument. Instrument
identity is not owned by an Amelia service, an Academy Course, or a Hamnavaz
taxonomy term, although explicit mappings may connect them.

### Minimum conceptual attributes

- internal numeric primary key, immutable opaque ULID-style UID, and required
  `DZN-INS-` human reference code;
- canonical display name and normalized administrative key;
- lifecycle/archive and audit metadata.

## 7. Course

### Responsibility

Represents the Academy teaching product. A Course references one Instrument and
is the catalogue item selected by an Enrolment. Phase 1 keeps its fields minimal
and does not mirror Amelia service configuration.

### Minimum conceptual attributes

- internal numeric primary key, immutable opaque ULID-style UID, and required
  `DZN-CRS-` human reference code;
- required `name_fa` and `name_en`;
- required `instrument_id`;
- required `course_type` (`standard` or `introductory`);
- required `status` (`active`, `inactive`, or `archived`);
- required `default_duration_minutes` and `default_buffer_minutes`;
- audit/archive metadata.

Pricing, location, Stripe information, curriculum, and teacher-specific
commercial configuration are not Phase 1 Course fields.

## 8. Enrolment

### Responsibility

Represents the continuing relationship between one Student, one Teacher, and one
Course. It is independent of any single payment, renewal cycle, or lesson block.

### Conceptual attributes

- internal numeric primary key, immutable opaque ULID-style `enrolment_uid`,
  and required `DZN-ENR-` human reference code;
- `student_id` and `teacher_id`;
- required `course_id`;
- start date and optional end date;
- lifecycle state: `draft`, `active`, `paused`, `ending`, `completed`,
  `cancelled`, or `archived`;
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

- internal numeric primary key, immutable opaque ULID-style `term_uid`, and
  required `DZN-TRM-` human reference code;
- required `enrolment_id`;
- allocated lesson count and consumption policy;
- start/end dates and lifecycle state: `draft`, `awaiting_payment`, `active`,
  `completed`, `cancelled`, or `archived`;
- created, updated, archived, and decision provenance.

### Invariants

- A Term belongs to exactly one Enrolment.
- Lesson allocation and payment state are related but distinct; payment does not
  create a Lesson directly.
- Remaining-session calculations come from explicit allocation and canonical
  Lessons, not an untraceable provider count.
- Introductory/trial teaching is modelled explicitly rather than permanently
  depending on Amelia category ID `3`.
- Standard allocation defaults to 12 Lessons and two eligible replacement
  Lessons per Term. These are policy defaults, not immutable database constants.
- An eligible replacement belongs to the same Term and must be scheduled and
  used before that Term closes. Unused entitlement expires at closure and does
  not carry into the next Term without an explicit, audited administrator
  override.

### Candidate lifecycles

Enrolment and Term each have their own approved lifecycle above. There is no
combined Enrolment/Term status. Future migrations may deliberately extend the
initial vocabulary.

## 10. Lesson

### Responsibility

Represents one operational teaching occurrence and is the centre of scheduling,
attendance, notifications, Google evidence, finance, and reporting.

### Conceptual attributes

- internal numeric primary key, immutable opaque ULID-style `lesson_uid`, and
  required `DZN-LSN-` human reference code;
- required `lesson_type` (`introductory` or `standard`);
- nullable `enrolment_id` and `term_id` for an `introductory` Lesson; required
  for a `standard` Lesson and normally required for a replacement Lesson;
- required `student_id`; `teacher_id` is direct for introductory Lessons and is
  otherwise derived from the approved Enrolment relationship;
- canonical `starts_at_utc` and `ends_at_utc`;
- source timezone and wall-clock provenance;
- scheduling lifecycle and append-only reschedule/version history;
- optional `replacement_for_lesson_id` for a genuine replacement/make-up;
- sequence/allocation position where applicable;
- delivery mode and provider-neutral join-resource reference;
- created, updated, cancelled, completed, and archived audit metadata.

### Invariants

- A Lesson can exist before any provider appointment is created.
- An `introductory` Lesson may exist for a Core Student before that Student has
  an Enrolment or Term.
- A `standard` Lesson requires its Enrolment and Term. A genuine replacement
  Lesson normally uses that same Enrolment/Term and explicitly links to the
  original Lesson.
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

### Candidate scheduling lifecycle

Lesson states are `draft`, `scheduled`, `cancelled`, `completed`, and `archived`.
`rescheduled` is an audited event/history fact, not a persistent Lesson state.
Attendance states do not appear in this lifecycle.

## 11. Lesson Schedule Version

Normal rescheduling preserves the same Lesson identity. Schedule history is
append-only in `{prefix}dzn_lesson_schedule_versions`.

### Required conceptual attributes

- `lesson_id` and monotonically increasing `version_number`;
- `starts_at_utc`, `ends_at_utc`, and `schedule_timezone`;
- `local_wall_date` and `local_wall_time`;
- `reason`, `changed_by`, `created_at`, and `superseded_at`.

### Invariants

- Exactly one schedule version is current for a Lesson.
- Changing a Student personal timezone changes display only; it never rewrites a
  schedule version or an Enrolment recurring schedule timezone.
- A genuine replacement/make-up occurrence is a new Lesson with
  `replacement_for_lesson_id`, not an ordinary schedule-version change.

Finance rate/currency snapshots and audited financial corrections remain future
Platform Phase 9 Finance responsibilities. They are not Phase 1 Lesson fields.

## 12. Attendance

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

## 13. Notification

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

## 14. GoogleConnection

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

## 15. External and legacy references

### Responsibility

Provides traceable mapping between external/legacy records and Core entities
without promoting provider IDs into business identity. It is a later
provider-specific integration concern, not Phase 1 persistence and not an Amelia
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

## 16. Operational Exception

`OperationalException` represents a failure or ambiguity that requires human
judgement. Its approved states are `open`, `acknowledged`, `resolved`, and
`dismissed`.

The platform principle is: **Automate the happy path. Surface failures and
ambiguity. Require human intervention only where judgement is needed.**

Administration is primarily exception management, not routine booking
operation. Normal form-validation errors are not Operational Exceptions.

## 17. Hamnavaz profile relationship

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

## 18. State separation matrix

| Concern | Owning model/module | Example states |
| --- | --- | --- |
| Lesson scheduling | Lesson / Core or Academy service | scheduled, cancelled, completed |
| Attendance outcome | Attendance | pending, absence reported, attended, review needed, no-show |
| Notification delivery | Notification | requested, accepted, delivered, failed |
| Operational exception | OperationalException | open, acknowledged, resolved, dismissed |
| Payability | Finance record/policy | pending, payable, non-payable, overridden |
| Archive | Each owning aggregate | active, archived |
| Hamnavaz publication | Hamnavaz Profile | draft, active, verification due, on hold, archived |

One state change may emit events used by other modules, but it does not directly
overwrite their state.

## 19. Manual setup and coexistence rules

- Phase 2 manually creates and validates the small initial catalogue, active
  identities, Enrolments, Terms, and required Lessons.
- Introductory Lessons may be created for active Students before an Enrolment or
  Term exists; standard and replacement Lessons use the applicable relationship.
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

## 20. Approved direction and remaining design work

The binding decisions are recorded in
[PRODUCT-DECISIONS.md](PRODUCT-DECISIONS.md). Remaining physical or workflow
choices include:

1. Retention durations and anonymization rules by data class.
2. Google failed-revoke support policy and token-retention period.
3. Historical Amelia archive format, storage, access, and inspection procedure.
4. Platform Phase 9 Finance physical tables and audited correction implementation.
5. Later provider-specific integration schemas.
