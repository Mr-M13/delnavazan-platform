# Delnavazan Platform — Migration Strategy

## Purpose
Describe how canonical schema evolves safely. Historical phase-by-phase migration narratives are archived.

## Current accepted line
- Accepted baseline: Schema 27 / Phase V on Platform `main` at `1cb9d16b0beb5bec293b41a065b63ffa5f1318f6`.
- Schema 25 introduced commercial purchase/funding/current-Term capacity authority.
- Schema 26 extended this with renewal/recurring enrolment, collection-intent, recovery, protection and refund/reversal-review authority.
- Schema 27 added the provider-neutral Google Calendar/Meet integration migration and verifier; it is accepted because the reviewed candidate was merged to `main`.
- The completed Notifications candidate on `recovery/platform-s-notifications-3f67d3d` remains unmerged and still declares Schema 27 from the pre-Phase-V baseline. It must be reconciled and renumbered against current `main` before it can enter an acceptance gate.
- Schema 28 Payment execution is in-flight only; its current candidate is not accepted until implementation, independent review and merge gates pass.

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
