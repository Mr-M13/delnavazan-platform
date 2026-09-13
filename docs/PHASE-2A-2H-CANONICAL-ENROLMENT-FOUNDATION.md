# Phase 2A.2-H — Canonical Enrolment Foundation

Schema 14 / migration `014_canonical_enrolment_foundation` establishes storage and protected read classification for a future canonical Enrolment. It creates no conversion, lifecycle-transition, Term, Lesson, Teacher Assignment, payment, scheduling, notification, calendar, Amelia, CRM or Hamnavaz authority.

## Record models and identity

- Existing rows remain `legacy_phase1`; their status and historical `teacher_id` are preserved exactly.
- A future `canonical_student_course_v1` row uses the neutral legacy-column sentinel `status=canonical`; `lifecycle_state` alone carries its lifecycle. Its structural identity is Student + Course. Nullable `teacher_id` is context only and never Teacher Assignment authority.
- Canonical provenance is one required `accepted_service_arrangement_id`, unique where present. Booking Request contact PII is not copied.
- `UNIQUE(student_id, course_id, applicable_slot)` permits only one applicable canonical row for a Student + Course. Applicable lifecycle states use slot `1`; closed history and all legacy rows use `NULL`.

## Lifecycle and lineage

The reserved lifecycle is `authorised → current → paused → closed`. `dzn_enrolment_lifecycle_events` is append-only and deliberately has no update path. Reserved predecessor meanings are `successor`, `return_after_closure`, `correction`, and `distinct_concurrent_service`; none is an operational bypass code.

## Applicability classifications

`EnrolmentApplicabilityService` is a capability-protected, read-only inspection boundary. It returns exactly `none`, `canonical_applicable`, `canonical_closed_history`, `legacy_review_required`, `already_linked_source`, or `data_integrity_conflict`. It inspects every matching Student + Course row and explicit Accepted Service Arrangement linkage; it never chooses silently among ambiguity or contamination.

## Authority boundary

Generic Enrolment creation is disabled and the admin Enrolment screen is read/history only. Canonical rows cannot use Phase 1 archive/restore or act as Phase 1 Term/Lesson/Teacher authority. A later reviewed phase must add readiness, then distinct conversion authority and idempotent atomic conversion. Teacher Assignment remains later and separate.
