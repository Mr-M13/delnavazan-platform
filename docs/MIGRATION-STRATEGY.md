# Delnavazan Platform — Migration Strategy

## Purpose
Describe how canonical schema evolves safely. Historical phase-by-phase migration narratives are archived.

## Current accepted line
- Accepted baseline for this pass: Schema 26 / Phase R2.
- Schema 25 introduced commercial purchase/funding/current-Term capacity authority.
- Schema 26 extends this with renewal/recurring enrolment, collection-intent, recovery, protection and refund/reversal-review authority.
- Schema 27 Notifications is in-flight only until independent review/acceptance.

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
