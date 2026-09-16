# Delnavazan Platform

Delnavazan Platform is the incremental, internally controlled foundation for moving academy authority away from architectural dependence on Amelia without disrupting the live academy. It owns durable business identity and canonical state; external systems reference that state through explicit service boundaries.

> **Safety warning:** Source completion never authorises deployment, production changes, Amelia writes/removal, payment activity, external communication or authority cutover.

## Current status

| Item | State |
|---|---|
| Phase-L implementation merge | `36e1d6b754079efcf6fdff02ed2a029099071455` |
| Platform / schema | 0.1.0 / 18 |
| Migrations | 001–018; latest `018_canonical_term_authority` |
| Completed coordination work | 2A.2-A through 2A.2-L |
| Latest merged slice | Canonical Term Creation & Lifecycle Authority |
| Next planned boundary | Requires separate authorisation; Phase M has not started |

Read [the continuity record](docs/DELNAVAZAN-CORE-CONTINUITY.md) before beginning work. It records exact SHAs, the locked Booking Request → Proposal → Acceptance → Conversion hierarchy, Theme/staging state, commercial facts and the current execution posture.

## Architectural direction

The canonical business concepts are Teacher, Student, Instrument, Course, Enrolment, Term and Lesson. Lesson is the later operational centre for attendance, scheduling, finance, notifications and provider integrations. Provider identifiers are mappings, not business identity.

The completed coordination path is deliberately layered:

```text
Booking Request → Coordination Case → Candidate Teacher → Availability Assent
→ Proposal Family → Teacher-specific Option → immutable Proposal Version
→ Provisional Acceptance → Final Acceptance / Accepted Service Arrangement
→ canonical Enrolment conversion → Teacher Assignment → canonical Term foundation
```

Proposal is not acceptance. Acceptance is not conversion authority. Conversion creates one Student + Course Enrolment and does not create Teacher Assignment. Current Teacher authority belongs to the separate Assignment aggregate. Phase K adds canonical Term storage and protected reads only; Phase L creation/lifecycle authority and later Lesson authority do not yet exist.

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
