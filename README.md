# Delnavazan Platform

Delnavazan Platform is the incremental, internally controlled foundation for moving academy authority away from architectural dependence on Amelia without disrupting the live academy. It owns durable business identity and canonical state; external systems reference that state through explicit service boundaries.

> **Safety warning:** Source completion never authorises deployment, production changes, Amelia writes/removal, payment activity, external communication or authority cutover.

## Current status

| Item | State |
|---|---|
| Authoritative main | `b0687fec98748144f96e3fc56f7e4fb53e01f673` (Phase N closeout commit; docs-only closeout hash is recorded in the task closeout) |
| Platform / schema | 0.1.0 / 21 authoritative on `main` |
| Migrations | 001–021 authoritative; latest `021_canonical_lesson_schedule_authority` |
| Latest merged slice | Phase 2A.2-N — Canonical Lesson Scheduling & Teacher Capacity Authority (complete / independently reviewed / merged / closed) |
| Active Platform candidate | Phase 2A.2-O canonical Lesson delivery & attendance outcome authority — Schema 22, unmerged, owner-verified, awaiting independent review |
| Next boundary | Independent review of the Phase O candidate; delivery/attendance cutover, payment, notification and provider integration remain outside current authority |

Read [the continuity record](docs/DELNAVAZAN-CORE-CONTINUITY.md) before beginning work. It records exact SHAs, the locked Booking Request → Proposal → Acceptance → Conversion hierarchy, Theme/staging state, commercial facts and the current execution posture.

## Architectural direction

The canonical business concepts are Teacher, Student, Instrument, Course, Enrolment, Term and Lesson. Lesson is the later operational centre for attendance, scheduling, finance, notifications and provider integrations. Provider identifiers are mappings, not business identity.

The completed coordination path is deliberately layered:

```text
Booking Request → Coordination Case → Candidate Teacher → Availability Assent
→ Proposal Family → Teacher-specific Option → immutable Proposal Version
→ Provisional Acceptance → Final Acceptance / Accepted Service Arrangement
→ canonical Enrolment conversion → M0 current Enrolment → Teacher Assignment → canonical Term → canonical Lesson authority
```

Proposal is not acceptance. Acceptance is not conversion authority. Conversion creates one Student + Course Enrolment and does not create Teacher Assignment. Current Teacher authority belongs to the separate Assignment aggregate. Phase L owns canonical Term creation/lifecycle authority. M0 makes the Enrolment lifecycle graph operational; Phase M makes canonical Lesson issuance and its bounded terminal lifecycle authoritative only.

## Repository rule

Platform development is incremental and bounded. New work requires an explicit phase contract, migrations are versioned/retry-safe, and business-critical changes require focused source/runtime/concurrency validation. Do not add new Amelia coupling, public authority, payment, notification, calendar or provider behaviour without explicit approval.

## Canonical documents

- [Core continuity record](docs/DELNAVAZAN-CORE-CONTINUITY.md)
- [Architecture](docs/ARCHITECTURE.md)
- [Module boundaries](docs/MODULE-BOUNDARIES.md)
- [Security architecture](docs/SECURITY.md)
- [Migration strategy](docs/MIGRATION-STRATEGY.md)
- [Approved product decisions](docs/PRODUCT-DECISIONS.md)
- [Changelog](docs/CHANGELOG.md)
