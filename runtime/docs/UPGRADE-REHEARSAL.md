# Upgrade rehearsal (Schema 24 -> Schema 25)

The upgrade rehearsal proves that a database built by the **Schema 24** plugin
is cleanly upgraded in place by the **Schema 25** plugin.

## Supported pre-25 state

The direct predecessor of migration 025 is the Phase 2A.2-Q closeout:

```text
1b9d7aa Close Phase 2A.2-Q continuity          (DZN_PLATFORM_SCHEMA_VERSION = '24')
10fe406 Add Phase 2A.2-R1 commercial ...       (DZN_PLATFORM_SCHEMA_VERSION = '25')
```

`run-upgrade-rehearsal.sh` defaults `DZN_PRE25_REF` to `1b9d7aa`.

## What it does

1. Records the current `HEAD` and branch, then checks out `1b9d7aa`.
2. Builds a fresh disposable WordPress + database and activates the plugin, so
   migrations 001..024 run against an empty database.
3. Asserts `dzn_platform_schema_version === '24'`.
4. Checks out the Schema 25 `HEAD` and runs `Migrator::maybe_upgrade()`, which
   executes migration 025 only (001..024 are already recorded).
5. Asserts Schema 25, then re-runs `phase-2a2r1-migration-runtime.php` to prove
   repeat-safety and the fail-closed verifier.
6. Restores the original checkout on exit (success or failure).

## Safety

- The script refuses to run when the repository has uncommitted **tracked**
  changes (`git status --porcelain --untracked-files=no`).
- The `runtime/` directory is untracked and is therefore left in place across
  the checkouts.
- The original `HEAD` is restored by a `trap`, so a failed rehearsal does not
  leave the checkout detached.

## Running one historical phase (example)

```bash
git checkout phase-2a2n-canonical-lesson-schedule-authority
bin/run-fresh-migration.sh          # builds the schema of that phase
DZN_PHASE_2A2N_MODE=capacity_first \
  tests/phase-2a2n-concurrency-runner.sh
git checkout main
```
