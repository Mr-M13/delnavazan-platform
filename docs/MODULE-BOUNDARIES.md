# Delnavazan Platform Module Boundaries

## 1. Boundary rule

Core owns business identity and canonical state. Modules collaborate through
application services, explicit read models, commands, and versioned domain
events. They do not reach into another module's tables or use an external
provider as an implicit shared domain model.

The dependency direction is inward toward Core contracts:

```text
Portals ───────┐
Academy ───────┤
Attendance ────┤
Notifications ─┼──> Core contracts and identities
Finance ───────┤
Hamnavaz ──────┘

Integrations and Legacy Adapters implement provider-facing ports.
Core never depends on those implementations.
```

## 2. Core

### Owns

- stable Teacher and Student identities;
- canonical Lesson identity and minimum lifecycle foundation;
- stable identifiers and audit metadata;
- schema/data migration coordinator;
- LegacyReference contract and mapping integrity;
- domain event envelope, idempotency contract, and durable publication boundary;
- common clock, actor, transaction, and archive conventions.

### May use

- WordPress storage/lifecycle infrastructure;
- provider-neutral interfaces implemented by other modules;
- explicitly published read models or commands.

### Must not

- call Meta, Google, Stripe, or Amelia APIs;
- query Amelia tables;
- derive identity from a provider ID, email, name, or WordPress user;
- own Hamnavaz public profile fields;
- own notification templates, attendance evidence, or finance reports;
- depend on a portal controller or theme.

## 3. Academy

### Owns

- Enrolment and Term educational relationships;
- lesson allocation/consumption policy;
- course/instrument/service catalogue rules approved for academy use;
- term lifecycle and remaining-session facts;
- introductory/trial classification at the business level.

### May use

- Core Teacher, Student, and Lesson identities;
- Finance status through a documented eligibility service;
- scheduling commands that create or amend Core Lessons.

### Must not

- treat Amelia service/category IDs as canonical catalogue identity;
- send notifications directly;
- charge through Stripe directly;
- determine attendance from Google payloads;
- expose provider credentials.

## 4. Attendance

### Owns

- attendance aggregate per Lesson;
- absence and join-action policy;
- participant/click/manual evidence;
- overlap evaluation and review-required decisions;
- administrator review and correction audit;
- attendance-specific archive/read models.

### May use

- Core Lesson, Teacher, and Student identities;
- a Google evidence provider interface;
- domain events to request follow-up notifications.

### Must not

- own Teacher or Student identity;
- change Lesson scheduling state as a side effect of attendance;
- mark attendance complete because a join link was clicked;
- call Meta directly;
- decide financial rate or issue payment;
- query Amelia directly after the relevant adapter migration.

## 5. Notifications

### Owns

- notification workflow definitions and versions;
- eligibility, schedule, idempotency, and retry policy;
- rendered template parameter snapshots;
- notification/attempt/delivery lifecycle;
- channel-independent diagnostics and operational read models.

### May use

- Core and Academy read models needed by an approved workflow;
- Attendance events such as absence reported;
- provider-neutral transport interfaces from Integrations.

### Must not

- query Amelia directly after migration;
- own student/teacher identity or phone/email source-of-truth;
- let a Meta adapter decide business eligibility;
- roll back a business event when delivery fails;
- mark production history from a controlled test send.

## 6. Integrations

### Owns

- provider clients and protocol translation;
- Google OAuth/refresh/revoke and Meet API access;
- Meta Graph transport and webhook authentication/normalization;
- Stripe API/webhook translation;
- provider health, rate-limit, and redacted error handling;
- provider-specific references and credentials.

### May use

- stable Core entity IDs passed through explicit commands;
- integration-specific storage linked to those IDs;
- callback interfaces that submit verified provider facts to an owning module.

### Must not

- create or merge Core identities independently;
- let a Stripe event insert a Lesson directly;
- let a Google response set final attendance without Attendance policy;
- let a Meta webhook update an unknown notification/message;
- expose reusable secrets outside the adapter;
- make provider IDs authorization credentials.

## 7. Finance & Reporting

### Owns

- payability policy and audited overrides;
- teacher rate/currency snapshots;
- statement/report calculations;
- finance-specific reconciliation and read models;
- references to Stripe payment facts where applicable.

### May use

- Core Teacher, Student, Lesson, and Term identities;
- final Attendance outcomes;
- provider-neutral payment facts from Stripe integration;
- LegacyReference during parity reporting.

### Must not

- create or reschedule Lessons;
- reinterpret Amelia category ID `3` as a permanent domain rule;
- alter Attendance outcomes;
- use teacher email as canonical identity;
- hide introductory lessons merely because they are non-payable.

## 8. Portals

### Owns

- authenticated student/teacher/admin presentation and workflow orchestration;
- session-to-Core-principal resolution;
- accessible forms, confirmations, and object-level access checks;
- account invitation/onboarding experience once Core identity is authoritative.

### May use

- application services exposed by Core and feature modules;
- provider-neutral authentication/mapping services;
- short-lived action capabilities issued by the owning module.

### Must not

- query module or Amelia tables directly;
- rely on browser local storage as identity authority;
- infer Teacher/Student identity from email alone;
- own business state transitions;
- reproduce brittle Amelia DOM/private-state coupling as a permanent design.

## 9. Hamnavaz

### Owns

- directory teacher/profile data;
- public eligibility and verification lifecycle;
- Country → normalized City → Instrument discovery data;
- public biography, media, contact allowlist, commercial profile fields, cards,
  profiles, routing, and SEO presentation.

### May use

- an optional, explicit Core Teacher link;
- approved public or portal services that operate on that linked identity;
- its existing private WordPress CPT/taxonomy architecture.

### Must not

- become the canonical academy Teacher record;
- require every directory teacher to exist in Core;
- copy all public/verification/commercial fields into Core;
- infer the link from name, email, phone, or WordPress author;
- expose Core operational state through public profiles.

## 10. Legacy Adapters

### Owns

- read-only Amelia access during early migration;
- typed translation of legacy records/events into import commands;
- idempotent import checkpoints, source fingerprints, and conflict reports;
- shadow comparison between legacy and Core outcomes;
- transitional Amelia session/notification hook bridges.

### May use

- Amelia tables/APIs/hooks with the minimum approved read scope;
- Core import/mapping services;
- explicit feature flags and cutover configuration.

### Must not

- become a permanent domain layer;
- write to Amelia unless a later phase explicitly authorises one narrow path;
- allow Core to call back into Amelia;
- silently merge Core people;
- authorize access using an Amelia ID alone;
- delete Amelia tables or source data.

## 11. Allowed service boundaries

| Caller | Allowed boundary | Example |
| --- | --- | --- |
| Legacy Amelia Adapter | Core import/mapping service | Upsert a source observation for an Amelia customer booking and resolve its Core mapping |
| Academy | Core lesson command service | Allocate and schedule a Lesson for an approved Enrolment |
| Attendance | Core lesson read service | Confirm participants and schedule for an attendance evaluation |
| Attendance | Google evidence port | Request normalized conference/participant intervals for a Lesson |
| Notifications | Meta transport port | Submit an authorised template send command with an idempotency key |
| Integrations/Meta | Notifications delivery service | Submit a verified, normalized provider delivery event |
| Finance | Attendance outcome read model | Calculate payable lessons without changing attendance |
| Portals | Feature application service | Submit a capability-protected absence command |
| Hamnavaz | Core teacher-link service | Resolve an explicitly linked Core Teacher without exposing private Core data |

## 12. Forbidden dependency examples

- Core calling the Meta API.
- Attendance joining directly to Amelia user tables after migration.
- Notifications scanning Amelia appointments after its Core event cutover.
- Stripe integration inserting Lessons or Enrolments.
- Google integration marking a Lesson attended directly.
- Finance updating an Attendance outcome.
- Portals reading encrypted Google or Meta tokens.
- Hamnavaz making a listing active because a Core Teacher is active.
- Legacy Adapter becoming the only place where Lesson lifecycle rules live.

## 13. Conceptual event flows

### Scheduled lesson reminder

```text
Academy/Core commits Lesson schedule
  → publishes lesson.scheduled
  → Notifications evaluates versioned reminder policy
  → records Notification + idempotency key
  → Meta adapter sends approved template
  → Meta webhook is verified and normalized
  → Notifications records delivery progression
```

### Student absence

```text
Signed capability resolves to Lesson
  → Attendance confirms current eligibility
  → records absence_reported atomically
  → publishes attendance.absence_reported
  → Notifications independently requests teacher/admin alerts
  → delivery failure cannot undo the absence fact
```

### Google attendance reconciliation

```text
Reconciliation selects due Core Lesson
  → Attendance requests normalized Meet evidence
  → Google adapter fetches provider records
  → Attendance evaluates assigned teacher/student overlap
  → attended or review_needed is recorded with evidence
  → Finance and reporting consume the resulting event/read model
```

### Amelia coexistence import

```text
Legacy Adapter reads Amelia in a bounded window
  → maps external IDs through LegacyReference
  → creates/updates Core shadow records idempotently
  → records conflicts without provider writes
  → parity report compares Core and current operational results
```

## 14. Enforcement expectations

An approved implementation must make these boundaries visible in namespaces,
directories, interfaces, tests, database access ownership, and code review.
Documentation alone is not sufficient evidence that a boundary is enforced.
