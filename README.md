# Delnavazan Platform

Delnavazan Platform is the new architectural home for Delnavazan Core and the
long-term program to replace Amelia without disrupting the live academy. It will
provide stable identities for teachers and students, explicit enrolments and
terms, and a canonical lesson lifecycle that operational modules can share.

The platform is being created because Amelia currently owns booking, customer,
employee, service, and appointment identity. That coupling makes attendance,
notifications, finance, portals, and future scheduling harder to evolve safely.
The replacement is an incremental authority migration, not a clean-slate rewrite.

> **Safety warning:** Do not remove Amelia, write to Amelia tables, or modify
> production integrations merely because this repository exists. Amelia must
> remain installed and readable until the relevant cutover gates are met and a
> separately authorised retirement plan is complete.

## Current status

- Delnavazan remains a beta platform operating alongside the live academy.
- Platform Phase 0 — Existing System Audit & Architecture is complete in this
  documentation baseline.
- Platform Phase 1 — Core Foundation & Canonical Data Model has not started and
  requires explicit approval.
- This repository is documentation-only until that approval. It intentionally
  contains no PHP, plugin bootstrap, implementation directories, or database
  migrations.
- Hamnavaz Phase 3 is complete. Hamnavaz Phase 4 is intentionally paused while
  the shared platform architecture is established.
- After Core stabilisation, Hamnavaz Phase 4 will resume against the shared
  architecture under its own separate approval.

## Architectural direction

The canonical business entities are:

- Teacher
- Student
- Term / Enrolment
- Lesson

Lesson is the operational centre for attendance, Google, notifications,
scheduling, finance, and reporting. WordPress, Amelia, Google, Stripe, Meta, and
Hamnavaz identifiers are external mappings to Core records rather than business
identities.

The defining principle is:

> Delnavazan Core owns business identity and business state. Integrations
> reference Core entities. Core entities do not derive their identity from
> integrations.

## Migration approach

The migration is architecture-first and incremental:

1. document boundaries, invariants, and exit criteria;
2. establish a small Core foundation with versioned, retry-safe migrations;
3. import Amelia data read-only into traceable Core mappings;
4. compare Core results with proven production behaviour in shadow mode;
5. move authority one module and workflow at a time;
6. retain rollback and legacy traceability throughout;
7. retire Amelia only after every exit gate is independently verified.

There is no big-bang cutover, and initial cutover never deletes Amelia tables.

## Canonical Platform roadmap

Platform phase numbers are independent from Hamnavaz phase numbers.

0. **Existing System Audit & Architecture** — complete.
1. **Core Foundation & Canonical Data Model**.
2. **Amelia Read Adapter & Migration Tools**.
3. **Attendance Migration**.
4. **Direct Google Integration** — including Core-owned connect, refresh,
   disconnect, and revoke lifecycle.
5. **Notification Platform / WhatsApp Migration**.
6. **Availability & Scheduling**.
7. **Native Teacher & Student Portals**.
8. **Stripe Payments & Term Automation**.
9. **Finance / Teacher Reporting Migration**.
10. **Amelia Cutover & Retirement**.

Read-only imports, idempotent migration, shadow/parity comparison, the authority
ledger, bounded cutovers, rollback gates, Amelia exit gates, and the strangler
pattern apply across relevant phases; they do not replace this numbering. After
Core stabilisation, Hamnavaz Phase 4 resumes against the shared architecture.

## Relationship to existing repositories

### Delnavazan Enhancements

[`Mr-M13/delnavazan-enhancements`](https://github.com/Mr-M13/delnavazan-enhancements)
is the current operational plugin. It contains proven attendance evidence,
Google Meet reconciliation, WhatsApp workflows, teacher payment reporting,
Amelia adapters, password handoff behaviour, and site enhancements. Those
behaviours are migration inputs. They remain operational until authority moves
through explicit cutovers.

### Hamnavaz

[`Mr-M13/delnavazan-expansion-hamnavaz`](https://github.com/Mr-M13/delnavazan-expansion-hamnavaz)
owns the structured teacher directory and public profile lifecycle. A Core
Teacher may have an optional Hamnavaz Profile, but Hamnavaz must remain usable
for directory-only teachers who are not academy teachers. Directory verification,
public presentation, and commercial profile fields do not become Core identity.

## Canonical documents

- [Architecture](docs/ARCHITECTURE.md)
- [Phase 0 migration map](docs/PHASE-0-MIGRATION-MAP.md)
- [Conceptual data model](docs/DATA-MODEL.md)
- [Security architecture](docs/SECURITY.md)
- [Module boundaries](docs/MODULE-BOUNDARIES.md)
- [Migration strategy](docs/MIGRATION-STRATEGY.md)
- [Phase 1 Core foundation specification](docs/PHASE-1-CORE-FOUNDATION.md)
- [Changelog](docs/CHANGELOG.md)

## Repository rule

Until Platform Phase 1 is approved, changes here must be documentation only.
No production code, schema, automation, public endpoint, integration write, or
Amelia retirement action is authorised by this baseline.
