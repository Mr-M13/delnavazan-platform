# Test matrix

The repository contains 174 test files spanning pure-PHP contract/static
guards, WordPress-backed runtime assertions and Docker-backed concurrency
races. They are classified below by what they need, not by phase number.

## Classification

| Kind | Marker | Needs | Current (Schema 25) |
|---|---|---|---|
| static lint | `tests/static.php` | PHP CLI only | yes |
| schema contract | `tests/schema-contract.php` | PHP CLI only | yes |
| fresh-install capability | `tests/fresh-install-capability-bootstrap.php` | PHP CLI only (mocks roles/options) | yes |
| migrator failure path | `tests/migrator-runtimeexception-runtime.php` | PHP CLI only (mocks `$wpdb`) | yes |
| source contract | `tests/*-contract.php`, `phase-2a2g-contract-mutations.php`, `phase-2a2f-concurrency-contract.php` | PHP CLI only | yes |
| pure computation | `phase-2a1b-time.php`, `phase-2a1c-time.php`, `phase-2a2a-capability-lifecycle.php`, `phase-2a2p-overlap-runtime.php` | PHP CLI only | yes |
| runtime | `phase-2a2r1-runtime.php` | WP + DB + Phase-J fixture | yes |
| migration runtime | `phase-2a2r1-migration-runtime.php` | WP + DB (24->25 verifier) | yes |
| failure runtime | `phase-2a2r1-failure-runtime.php` | WP + DB + Phase-J fixture | yes |
| corruption runtime | `phase-2a2r1-corruption-runtime.php` | WP + DB + Phase-J fixture | yes |
| concurrency | `phase-2a2r1-concurrency-*` | WP + DB + Docker network + Phase-J fixture | yes |

## Historical phase tests

The per-phase `*-migration-runtime.php`, `*-isolated-runtime.php`,
`*-runtime.php` and `*-concurrency-runner.sh` files from earlier phases are
**pinned to their own commit** via an exact `DZN_PLATFORM_BUILD_ID` and/or
`DZN_PLATFORM_SCHEMA_VERSION` assertion. They are replayable only by checking
out the matching commit (for example `git checkout phase-2a2n-...`) and running
that phase's own harness. They are not part of the Schema 25 acceptance.

This is intentional: migration 001..025 are additive and retry-safe, so the
Schema 25 acceptance exercises the *current* authority (R1) plus the two
migration proofs (fresh zero->25 and upgrade 24->25), while each historical
phase's boundary remains replayable at its own snapshot.

## Invocation

```bash
make pure-tests       # pure-PHP set above
make runtime-tests    # Phase-J fixture + R1 runtime/migration/failure/corruption
make concurrency      # 9 R1 race modes, isolated DB state per mode
make fresh-migration  # zero -> Schema 25
make upgrade-rehearsal# Schema 24 -> Schema 25
```
