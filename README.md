# Delnavazan Platform

Delnavazan Platform is the incremental, internally controlled foundation for moving academy authority away from architectural dependence on Amelia without disrupting the live academy. It owns durable business identity and canonical state; external systems reference that state through explicit service boundaries.

> **Safety warning:** Source completion never authorises deployment, production changes, Amelia writes/removal, payment activity, external communication or authority cutover.

## Current status

| Item | State |
|---|---|
| Authoritative main | Phase 2A.2-R1 implementation merge at `f9df3bfb0fda79fba7dee916c4687464ee67d480` (Schema 25 / R1 complete) |
| Platform / schema | 0.1.0 / **26 candidate** |
| Migrations | 001–026 candidate; latest `026_renewal_recurring_enrolment_authority` |
| Latest merged slice | Phase 2A.2-R1 — Commercial Purchase, Funding & Current-Term Capacity Authority (Schema 25; merged / closed) |
| Previous merged slice | Phase 2A.2-Q — Post-Intro Continuation & Slot Reservation Authority (Schema 24) |
| Active Platform candidate | Phase 2A.2-R2 — Renewal, Next-Term, Recurring Enrolment/Collection, Recovery, Lapse & Refund Authority (Schema 26; candidate awaiting independent review, correction rounds 1–6 applied, contract §13 pre-implementation prerequisites closed) |
| Next boundary | **R2 candidate awaiting independent review.** No Stripe, notification delivery, Theme, deployment or production access is authorised |

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
