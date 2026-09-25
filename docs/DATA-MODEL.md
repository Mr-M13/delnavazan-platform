# Delnavazan Platform — Current Data Model

## Purpose
Provide a usable conceptual map. Exact columns, indexes and migration verification are authoritative in `src/Core/Infrastructure/Migration/Migrator.php` and runtime tests.

## Accepted schema
- Accepted baseline for this pass: Schema 26 / Phase R2.
- Schema 27 Notifications is in-flight and is not listed as accepted until review/acceptance completes.

## Canonical domain groups
1. **Identity and access** — teachers, students, WordPress/principal links, invitations, capability/authority evidence.
2. **Teaching setup** — courses/instruments, teacher eligibility, accepting state, availability profiles/rules/exceptions.
3. **Booking and intake** — booking requests, contact snapshots, requested times, idempotency keys, duplicate review and privacy tombstones.
4. **Coordination and proposal** — coordination cases/candidates, teacher assent, proposal families/options/versions and acceptance evidence.
5. **Enrolment and term** — canonical enrolments, lifecycle events, Terms and Term authority.
6. **Lesson authority** — canonical Lessons, assignment, schedule versions, delivery and attendance evidence/outcomes.
7. **Continuation/capacity** — post-intro continuation cases, slot authority/reservations and capacity protection.
8. **Commercial authority** — products, prices, promotions, offers, purchases, entitlements, obligations, payment evidence/facts and Term funding.
9. **Recurring/renewal authority** — recurring enrolments/patterns, renewal cycles, collection intents, recovery cases, protections and refund/reversal review.
10. **Cross-cutting evidence** — audit events, command/idempotency records, exception registries and outbox/intents.

## Data invariants
- Canonical IDs are stable; public/reference IDs are separate from internal numeric IDs.
- Time-sensitive decisions persist timezone and frozen facts needed to reproduce the decision.
- Money is integer minor units with explicit ISO-style currency.
- Provider references are stored only when required and should be digest/safe-reference based where possible.
- Immutable evidence/event/command rows are never repurposed as mutable aggregate state.
- Mutable aggregates use explicit lifecycle state/versioning and preserve audit lineage.
- Privacy erasure removes or anonymises identifying content while preserving non-identifying integrity/audit evidence where required.
- Migrations are additive and verified; no migration may silently infer business decisions from legacy ambiguity.

## Source-of-truth rule
If this document conflicts with a verified migration/runtime test, the code/test wins and this document becomes NEEDS_REVIEW immediately.

## Staleness trigger
Update after every accepted schema migration or change to canonical entity ownership/relationship. Hard threshold: it may not lag more than one accepted schema.
