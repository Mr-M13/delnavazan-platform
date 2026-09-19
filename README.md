# Delnavazan Platform

Delnavazan Platform is the incremental, internally controlled foundation for moving academy authority away from architectural dependence on Amelia without disrupting the live academy. It owns durable business identity and canonical state; external systems reference that state through explicit service boundaries.

> **Safety warning:** Source completion never authorises deployment, production changes, Amelia writes/removal, payment activity, external communication or authority cutover.

## Current status

| Item | State |
|---|---|
| Authoritative main | `7b9aea68fddd651cd614f279f77e78b104885c4d` (post-Phase-O maintenance merge; the Phase-O closeout and Phase-P notes are recorded in their task closeouts) |
| Platform / schema | 0.1.0 / 22 authoritative on `main`; Phase 2A.2-P candidate at Schema 23 |
| Migrations | 001–022 authoritative; latest `022_canonical_lesson_delivery_attendance_authority` |
| Latest merged slice | Phase 2A.2-O — Canonical Lesson Delivery & Attendance Outcome Authority (complete / independently reviewed after correction rounds 1–3 / merged / closed) |
| Previous merged slice | Phase 2A.2-N — Canonical Lesson Scheduling & Teacher Capacity Authority (Schema 21) |
| Active Platform candidate | Phase 2A.2-P canonical attendance intake & review authority — **Correction Round 3 candidate** (Schema 23, unmerged, owner-verified, awaiting final independent re-review). Original candidate `6a6a4ce`, Round-1 candidate `629310e` and Round-2 candidate `0d6f0f3` all failed independent review; `eda3df2a` is historical provenance only |
| Next boundary | **Final independent re-review of the Phase-P Correction Round 3 candidate.** Beyond that, no next slice is authorised: the next Platform slice requires an explicit product/architecture decision, and delivery/attendance cutover, provider integration, payment, notification and Finance remain outside current authority |

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
