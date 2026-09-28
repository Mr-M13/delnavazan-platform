# Phase 2 — Minimum Canonical Seed Inventory Template

Status: **WORKING TEMPLATE — no production/private data belongs in Git**

## Purpose

Define the smallest human-reviewable dataset required to start Delnavazan operations on the accepted canonical Platform. This document records structure and required provenance only. Real names, emails, phone numbers, student details and other private data must be supplied outside Git at execution time.

## Seed rules

- Seed only records required for launch/continuity.
- Do not bulk-import Amelia.
- Teachers create fresh WordPress accounts and are linked to canonical Teacher identities.
- Every manually seeded operational record must have a source/provenance note and operator receipt.
- Re-running the seed workflow must be idempotent or refuse a duplicate deterministically.
- Historical Amelia data remains read-only evidence until explicit retirement gates pass.
- Do not pre-create future commercial/payment/provider facts merely because tables exist.

## A. Instruments

| Seed key | Required fields | Launch value | Provenance | Approved |
|---|---|---|---|---|
| `instrument-01` | `slug`, `name_fa`, `name_en`, `status` | _TBD_ | _owner-approved catalogue_ | ☐ |

Canonical fields: `uid`, `reference_code`, `slug`, `name_fa`, `name_en`, `status`. `uid`/timestamps are system-generated where supported.

## B. Courses

| Seed key | Instrument | Required fields | Launch value | Provenance | Approved |
|---|---|---|---|---|---|
| `course-01` | `instrument-01` | `name_fa`, `name_en`, `course_type`, `status`, `default_duration_minutes`, `default_buffer_minutes` | _TBD_ | _owner-approved catalogue_ | ☐ |

Do not infer course duration/buffer from historical bookings without owner confirmation.

## C. Teachers

Private values are entered only in the controlled operator workflow, never committed here.

| Seed key | Required operational fields | Account/link status | Provenance | Approved |
|---|---|---|---|---|
| `teacher-01` | `display_name`, `status`, `country_code`, `timezone`; optional Persian/English names and contact fields | fresh WP account + canonical link | _owner/teacher confirmed_ | ☐ |

Teacher scheduling/availability is a separate authority. Do not invent availability while creating identity.

## D. Active Students

| Seed key | Required operational fields | Principal/account status | Provenance | Approved |
|---|---|---|---|---|
| `student-01` | `display_name`, `status`, `country_code`, `timezone`; contact fields only where operationally required | _TBD_ | _owner-reviewed active learner_ | ☐ |

Use the minimum personal data required for current service. Guardian/acceptance authority must follow the existing Platform authority model rather than being inferred from contact details.

## E. Active Enrolments

| Seed key | Student | Teacher | Course | Required fields | Provenance | Approved |
|---|---|---|---|---|---|---|
| `enrolment-01` | `student-01` | `teacher-01` | `course-01` | `status`; optional `started_at`, preferred weekday/time and schedule timezone when verified | _current service arrangement_ | ☐ |

An Enrolment is canonical Student + Teacher + Course context. Do not create future payment, notification or provider state as a side effect.

## F. Current Terms

| Seed key | Enrolment | Required fields | Provenance | Approved |
|---|---|---|---|---|
| `term-01` | `enrolment-01` | `sequence_number`, `status`, `lesson_allocation`, `replacement_allowance`, `payment_state`; dates only when verified | _current term evidence_ | ☐ |

Default business wording remains one term / 12 weekly private lessons, but seeded values must match the actual current learner state rather than being assumed.

## G. Lessons required for continuity

| Seed key | Student | Teacher | Course | Enrolment | Term | Required fields | Provenance | Approved |
|---|---|---|---|---|---|---|---|---|
| `lesson-01` | `student-01` | `teacher-01` | `course-01` | `enrolment-01` | `term-01` | `lesson_type`, `status`, `sequence_number`; schedule only through canonical schedule authority | _verified upcoming service_ | ☐ |

Create only lessons genuinely needed to maintain continuity. Do not fabricate completed historical lessons merely to make counts look complete.

## H. Per-record provenance envelope

For each real seed action, capture outside Git and write through the accepted operator/audit path:

- seed key / canonical resulting UID or reference;
- source type (`owner_confirmed`, `teacher_confirmed`, `legacy_record_reviewed`, etc.);
- source reference or digest appropriate to the authority;
- evidence timestamp;
- operator identity;
- command/idempotency key;
- result (`created`, `matched_existing`, `refused_conflict`);
- audit/receipt identifier;
- review note for any uncertainty.

## I. Pre-seed approval checklist

- ☐ Instrument/Course catalogue approved.
- ☐ Teachers in launch cohort confirmed.
- ☐ Active students requiring continuity confirmed.
- ☐ Student ↔ Teacher ↔ Course relationships confirmed.
- ☐ Current term number/status/allocation confirmed per active enrolment.
- ☐ Only genuinely required upcoming lessons identified.
- ☐ Timezones checked explicitly.
- ☐ No private values committed to Git.
- ☐ Rollback/re-entry procedure available before first real seed action.
