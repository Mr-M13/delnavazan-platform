# Delnavazan Platform — Current Data Model

## Purpose
Provide a usable conceptual map. Exact columns, indexes and migration verification are authoritative in `src/Core/Infrastructure/Migration/Migrator.php` and runtime tests.

## Accepted schema
- Accepted baseline: Schema 29 / Phase T on Platform `main` at `b36561dc6bb6e87fd142a28ae67fbc4f2fdc9279`; Schema 27 / Phase V remains part of that accepted line.
- Migration 028 adds 15 additive provider-neutral payment-execution tables covering provider accounts/object mappings, encrypted secrets, commands, attempts, results, dispatch claims, provider-event receipts/events and initial decisions.
- Migration 029 adds the provider-event decision-claim table and fencing authority required for retry-safe decision processing.
- The completed Notifications candidate on `recovery/platform-s-notifications-3f67d3d` remains unmerged and declares Schema 27 from an older baseline; it must be reconciled and renumbered before future integration.
- Runtime acceptance for fresh install/upgrade, webhook, secret, failure and concurrency suites remains outstanding until the required PHP and disposable WordPress/MariaDB host is available.

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
10. **Provider integration authority** — provider-neutral connections, encrypted credentials, OAuth authorisations, identity/event mappings, provider ingest evidence/outcomes/conflicts and digest-only commands; provider state never replaces Core identity or Lesson authority.
11. **Payment execution authority** — provider-neutral account/object registries, encrypted adapter secrets, commands, attempts, results, dispatch claims, provider-event receipts/events/decisions and fenced decision claims; provider events do not directly create commercial truth.
12. **Cross-cutting evidence** — audit events, command/idempotency records, exception registries and outbox/intents.

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
