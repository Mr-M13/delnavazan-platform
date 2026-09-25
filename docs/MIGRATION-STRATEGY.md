# Delnavazan Platform — Migration Strategy

## Purpose
Describe how canonical schema evolves safely. Historical phase-by-phase migration narratives are archived.

## Current accepted line
- Accepted baseline: Schema 29 / Phase T on Platform `main` at `b36561dc6bb6e87fd142a28ae67fbc4f2fdc9279`.
- Schema 25 introduced commercial purchase/funding/current-Term capacity authority; Schema 26 added renewal/recurring, collection-intent, recovery, protection and refund/reversal-review authority.
- Schema 27 added the accepted provider-neutral Google Calendar/Meet integration migration and verifier.
- Migration 028 adds 15 payment-execution tables without backfill, external calls or changes to existing commercial/R1/R2 tables.
- Migration 029 adds one provider-event decision-claim table for retry-safe lease/generation/token fencing.
- The reviewed Payment candidate is merged, but host runtime acceptance remains outstanding; it must pass fresh-install/upgrade, webhook, secret, corruption/failure and concurrency evidence before operational activation.
- The Notifications candidate remains unmerged and must be reconciled and renumbered against current `main`.

## Migration rules
- Migrations are sequential, additive and repeat-safe.
- Never backfill a business decision that cannot be proven from authoritative evidence.
- Never infer payment, renewal, Term/Lesson creation, capacity or provider action merely to satisfy a schema.
- Every schema has a verifier and must fail closed on malformed/partial capability.
- A retained current-schema marker does not bypass structural verification.
- Existing accepted rows must remain valid unless an explicit, reviewed migration says otherwise.
- Provider-specific transport/storage must not leak into provider-neutral domain authority unless intentionally approved.
- Rollback means restore the previous application/schema snapshot or use an explicit forward correction; do not improvise destructive SQL in production.

## Acceptance gate for a new schema
1. Contract/authority boundary reviewed.
2. Migration + verifier implemented.
3. Fresh install and previous-schema upgrade tested.
4. Repeat execution tested.
5. Malformed/partial schema rejection tested.
6. Domain/runtime/concurrency tests pass as applicable.
7. Independent review passes.
8. Canonical docs updated.
9. Only then may the schema be treated as accepted.

## Detailed history
Exact historical phase contracts remain under `docs/archive/phase-history/` and Git history. They are consulted only when forensic detail is needed.

## Staleness trigger
Update on every accepted schema migration or change to migration/verifier rules. Hard threshold: documentation must be current before the next schema is accepted.
