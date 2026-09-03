# Delnavazan Platform Architecture

## 1. Status and scope

This is the canonical Phase 0 architecture for Delnavazan Platform. It defines
direction, ownership, and migration constraints before implementation begins.
It is not an implementation plan for a big-bang Amelia replacement and does not
authorise Platform Phase 1.

## 2. Architectural goal

Delnavazan needs one durable business model that can support the academy without
making a booking plugin, payment provider, communications channel, or directory
the authority for identity. The platform must preserve the behaviours that work
today while allowing each external dependency to be replaced independently.

The defining principle is:

> **Delnavazan Core owns business identity and business state. Integrations
> reference Core entities. Core entities do not derive their identity from
> integrations.**

The immediate goal is not feature expansion. It is to establish canonical
Teacher, Student, Term / Enrolment, and Lesson records and safe boundaries around
them.

## 3. Modular platform

The intended modules are:

| Module | Primary responsibility |
| --- | --- |
| Core | Stable business identities, canonical state, identifiers, migrations, domain events, and audit metadata |
| Academy | Enrolment, term allocation, course/instrument relationships, and academy policy |
| Attendance | Attendance outcomes, evidence, review, and absence/join actions tied to a Lesson |
| Notifications | Workflow eligibility, message attempts, idempotency, and delivery lifecycle across channels |
| Integrations | Provider adapters for Google, Meta, Stripe, email, calendars, and future external services |
| Finance & Reporting | Payability decisions, rate snapshots, statements, and financial reporting |
| Portals | Authenticated student, teacher, and administrator application surfaces |
| Hamnavaz | Directory profiles, discovery, public eligibility, and profile verification |
| Legacy Adapters | Transitional read/import/compare paths for Amelia and other legacy sources |

Detailed dependency rules appear in
[MODULE-BOUNDARIES.md](MODULE-BOUNDARIES.md).

## 4. Core ownership of identity

Core assigns stable Delnavazan identifiers. The following values are mappings,
not primary identities:

- WordPress user and post IDs;
- Amelia employee, customer, appointment, customer-booking, and service IDs;
- Google subject, account, email, conference, and calendar identifiers;
- Stripe customer, payment, subscription, price, and product identifiers;
- Meta message and template identifiers;
- Hamnavaz teacher/profile post IDs.

Mappings are recorded explicitly through `LegacyReference` or an
integration-specific reference linked to a Core entity. A changed email, merged
provider record, re-created Amelia customer, or replaced integration must not
change the Core identity.

Core owns only business identity and canonical state. It does not absorb every
provider payload, public profile field, or operational log.

## 5. Lesson as the operational centre

Every scheduled teaching occurrence is represented by a Core Lesson. Modules
attach their own state to that lesson:

```text
Teacher ─┐
         ├─ Term / Enrolment ─ Lesson
Student ─┘                      ├─ Attendance
                               ├─ Google
                               ├─ Notifications
                               ├─ Scheduling
                               ├─ Finance
                               └─ Reporting
```

The Lesson owns the canonical scheduled occurrence and its lifecycle. It does
not directly own attendance evidence, Meta delivery states, OAuth credentials,
or accounting reports. Scheduling state and attendance outcome remain distinct:
a cancelled lesson is a scheduling fact; a student absence is an attendance
fact; a delivered reminder is a notification fact.

Times are stored canonically in UTC. The originating timezone and wall-clock
intent are retained when required for correct rescheduling and display.

## 6. Integrations as adapters and providers

An integration translates between a provider contract and a Delnavazan service
boundary. It may hold provider references and provider-specific state, but it may
not invent or become the owner of a Teacher, Student, Term, or Lesson.

Examples:

- the Amelia adapter maps appointments and customer bookings to Core Lessons;
- the Google adapter maps a Core Teacher to a renewable Google connection and
  maps Meet evidence to a Lesson;
- the Meta adapter sends an already-authorised notification command and reports
  provider delivery events;
- a Stripe adapter maps external customers and transactions to Core identities
  and Finance records without creating lessons;
- Hamnavaz optionally maps a directory profile to a Core Teacher.

Provider reads and writes must be explicit. During migration, the Amelia adapter
is read-only unless a later cutover phase authorises a narrowly defined write.

## 7. Event-driven internal workflows

Modules coordinate through versioned domain events and application services,
not direct database access across module boundaries. Illustrative events include:

- `lesson.scheduled`;
- `lesson.rescheduled`;
- `lesson.cancelled`;
- `attendance.absence_reported`;
- `attendance.confirmed`;
- `enrolment.sessions_remaining_changed`;
- `notification.requested`;
- `notification.delivery_updated`;
- `google.connection_invalidated`.

An event states a fact that already occurred. Commands request an action. Event
handlers must be retry-safe and use an idempotency key. A failed notification or
provider call must not roll back an already accepted attendance fact.

The first implementation may use WordPress-native hooks and a durable outbox,
but hook names are not themselves the domain model. Important external side
effects require durable attempt and completion records so a process crash does
not silently lose or duplicate work.

## 8. Data lifecycle

The platform distinguishes:

1. active canonical state used by current workflows;
2. historical business evidence that must remain queryable;
3. archived records hidden from normal operations but restorable;
4. provider snapshots retained for traceability and parity checks;
5. secrets with a shorter, revocable lifecycle;
6. data eligible for permanent deletion under an approved retention policy.

Archive is normally soft and auditable. Archiving a Lesson or Attendance record
must not silently erase payment evidence, delivery history, or legacy mappings.
Permanent deletion is an explicit, authorised retention operation and is not an
ordinary status transition.

## 9. Schema migrations

Application version and schema version are separate. Each migration must be:

- explicit and ordered;
- safe to retry or able to prove it completed atomically;
- bounded and observable;
- compatible with rollback or forward repair;
- unable to reinterpret external IDs as Core IDs;
- tested against an anonymised production-shaped dataset;
- backed up before production execution.

Large imports use checkpoints and an import run identifier. A schema migration
must not make a provider write. Data import and authority cutover are distinct
operations.

## 10. Amelia retirement philosophy

Amelia is replaced through strangler-style migration:

- keep it installed and readable;
- import with repeatable mappings;
- run Core calculations in shadow mode;
- compare results with current production behaviour;
- move one workflow's read or write authority at a time;
- keep an immediate rollback path;
- stop creating new Amelia coupling in new work;
- do not delete Amelia tables at initial cutover.

Retirement is a verified operational outcome, not a code-completion milestone.
It requires evidence that no active route, portal, automation, report, or support
procedure still depends on Amelia.

## 11. Hamnavaz relationship

The relationship is optional and directional:

```text
Core Teacher ── optional link ── Hamnavaz Profile
```

A Core Teacher represents the academy/business identity. A Hamnavaz Profile
represents directory and public-presentation state. Hamnavaz listing status,
verification lifecycle, public contact fields, biography, profile media,
discovery taxonomies, commercial profile fields, and SEO data stay in Hamnavaz.

Hamnavaz must also support independent directory teachers with no Core Teacher.
Linking must therefore be nullable, explicit, capability-protected, and
one-to-one where present. Neither system may infer identity from matching email
or name alone.

## 12. Platform migration phases

These are Platform phases and do not renumber Hamnavaz phases.

1. **Phase 0 — Architecture and audit:** canonical documentation and migration
   map; complete with this baseline.
2. **Phase 1 — Core foundation:** plugin/lifecycle shell, migration framework,
   canonical identifiers, minimum Core records, LegacyReference, and domain
   contracts; not started.
3. **Phase 2 — Read-only Amelia bridge:** idempotent import and reconciliation,
   no authority change.
4. **Phase 3 — Shadow operations:** Core Lesson projections and parity reports
   beside current production workflows.
5. **Phase 4 — Workflow cutovers:** attendance, notifications, finance, and
   portal capabilities moved in separately approved increments.
6. **Phase 5 — Scheduling authority:** Core scheduling becomes authoritative for
   approved cohorts after rollback and support readiness.
7. **Phase 6 — Amelia retirement:** freeze legacy writes, retain traceability,
   remove runtime dependencies, and archive rather than delete source tables.

Every phase has its own approval, runtime validation, rollback evidence, and PR.

## 13. Architectural non-goals

This architecture does not:

- authorise removal, deactivation, or modification of Amelia;
- require rewriting proven Google, Meta, attendance, or reporting algorithms;
- make WordPress user accounts mandatory business identities;
- turn Hamnavaz profiles into academy identities;
- define a public API or mobile application;
- commit to microservices, a specific queue product, or a non-WordPress runtime;
- copy entire provider payloads into Core without a retention purpose;
- make Stripe, Google, Meta, WordPress, or Amelia a scheduling authority;
- merge scheduling state, attendance outcome, notification delivery, and
  payability into one status field;
- begin Platform Phase 1 implementation.
