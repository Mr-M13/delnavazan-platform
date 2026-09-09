# Phase 2A.2-F deterministic local race plan

Run only against a disposable local/development WordPress database, in separate WP-CLI/MySQL sessions, with a post-lock hook that writes an explicit held gate and waits for controlled release. Do not use timing-only ordering.

| Race | Holder / contender | Expected invariant |
|---|---|---|
| Identity vs erasure (identity first) | Resolution holds `booking_requests` lock; erasure waits | Resolved event and Student projection commit; erasure then clears request PII; no further identity event can be written. |
| Identity vs erasure (erasure first) | Erasure holds request lock; resolution waits | Erasure commits; resolver rejects; no Student is created and no resolution event is appended. |
| Capacity vs guardian grant | Capacity update and grant compete for one Student | Student lock serializes writes; authority read sees either old complete state or new complete state, never a mismatched current pointer. |
| Principal vs guardian grant | Both attempt to use one WordPress user | Locked WordPress user row plus active-slot constraints permit at most one authority role; the loser rejects. |
| Grant revoke/supersede | Current grant is revoked or superseded while a second mutation waits | Version CAS permits one state change, leaves history append-only, and produces at most one active grant for the exact scope. |

The source contract in `phase-2a2f-concurrency-contract.php` guards the required locks/CAS hooks. Execute the behavioural races only where the separate local WP-CLI sessions and explicit gate directory are available.
