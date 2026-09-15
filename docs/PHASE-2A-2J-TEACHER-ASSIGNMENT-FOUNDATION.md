# Phase 2A.2-J — Teacher Assignment Foundation

## Boundary and identity

Teacher Assignment is a first-class aggregate representing the currently applicable Teacher authority for one canonical Enrolment. It is deliberately separate from `enrolments.teacher_id`: that Enrolment field remains the historical Teacher frozen from the Accepted Service Arrangement at conversion time and is never updated by Assignment commands.

A canonical Enrolment may validly have zero Assignments. Migration 016 performs no backfill. A new Assignment is allowed only while the canonical Enrolment is `authorised`, `current`, or `paused`; `closed` cannot receive one. Assignment operations do not mutate Enrolment lifecycle, Term, Lesson, scheduling, capacity, payment, notification, calendar, Amelia, Hamnavaz, or CRM state.

## Storage and lifecycle

Schema 16 / `016_teacher_assignment_foundation` adds three InnoDB tables:

- `teacher_assignments` stores aggregate identity, ordered lineage, current state, and minimised source identifiers. `UNIQUE(enrolment_id, applicable_slot)` is final database arbitration for at most one applicable Assignment; the applicable row uses slot `1`, and terminal rows use `NULL`.
- `teacher_assignment_lifecycle_events` is append-only evidence. An Assignment starts with `initial_assigned` or `replacement_assigned`; its terminal transition is `replaced`, `ended`, or `cancelled`.
- `teacher_assignment_commands` is immutable, Teacher-Assignment-domain idempotency evidence. It stores only HMAC/SHA-256 key and payload digests, never raw command keys.

No future replacement may be staged. Replacement atomically terminates the predecessor and creates its successor. The command requires the expected current Assignment ID, so concurrent replacements cannot silently serialize into two accepted changes. Database uniqueness remains final arbitration.

## Initial Teacher continuity

The initial Assignment must use the exact Teacher retained by the Enrolment's Accepted Service Arrangement provenance. The service locks the Enrolment, read-revalidates the immutable Enrolment → Accepted Service Arrangement → Proposal Version → retained Availability Assent snapshot/evidence identity, then locks the exact Teacher before creating authority. It deliberately does not lock historical Assent before Teacher, preserving the established Teacher-before-Assent order. Historical Assent does not have to remain presently valid merely because validity/review time passed or the mutable Assent record later transitioned; the frozen final-arrangement lineage remains the evidence. A Teacher must nevertheless be active and unarchived when Assignment authority is created.

## Replacement agreement evidence

A different Teacher requires new Assignment-specific agreement through exactly one route:

- authenticated Teacher acceptance, revalidated against the active Teacher principal link and readiness state; or
- authorised staff attestation with a controlled channel, evidence time, and opaque evidence reference.

Availability, eligibility, proposal history, `enrolments.teacher_id`, or administrator preference alone is never replacement authority. Raw evidence references are HMAC-digested before persistence; names, contact details, message bodies, addresses, calendar data, and provider payloads are not copied into Assignment storage.

## Authorization, reads, and offboarding

Administrators receive `dzn_manage_teacher_assignments`; the Teacher role receives `dzn_accept_own_teacher_assignments` solely for the exact authenticated acceptance path. Readiness and the stable current-Assignment read seam are protected and informational. The read seam returns only Assignment ID/UID, Enrolment ID, Teacher ID, sequence, state, and assigned time.

Teacher archival is rechecked inside a transaction while holding the Teacher row lock. An applicable Assignment blocks archival. Assignment creation/replacement locks the canonical Enrolment first, then involved Teachers in ascending numeric ID order, then concrete Assignment rows. Transactions use `READ COMMITTED`; the parent Enrolment row serializes commands while avoiding stale snapshots during an attributed waiter.

## Idempotency and concurrency

Same key plus same payload is a clean replay. Same key plus a different payload raises `IdempotencyConflictException`. A different key requesting the already-existing identical authority returns the existing result without creating a second Assignment or command. Expected-current identity rejects stale competing replacement with `assignment_changed`.

The disposable runtime suite covers clean Schema 15 → 16 migration, repeat upgrade, capability repair, no-backfill/zero-Assignment reads, historical initial-Teacher continuity, both replacement-evidence routes, end/cancel, closed-Enrolment rejection, rollback hooks, database uniqueness, offboarding protection, privacy minimisation, no Enrolment mutation, and three attributed two-process races: different-key initial convergence, same-key replay, and competing replacement arbitration.

This candidate is for independent review only. It is not merged or deployed.
