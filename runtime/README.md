# Delnavazan Platform — local disposable test runtime

A reproducible, disposable WordPress + MariaDB/MySQL runtime for Delnavazan
Platform development, migration testing, runtime/concurrency tests and
future provider-adapter test doubles. It replaces the retired
`niu-nailhouse.com` staging environment for local validation.

The runtime is **local-only** and **disposable**: it binds WordPress and the
mail catcher to `127.0.0.1`, keeps MariaDB/MySQL container-only, uses no
production credentials, and performs no external send.

---

## Prerequisites

| Requirement | Minimum | Notes |
|---|---|---|
| Docker Desktop | recent (engine 25+) | tested against Docker 29.x + Compose v5 |
| Disk | ~4 GB free | WordPress core + images + a small database |
| Memory | 4 GB for the engine | more helps the 9-mode concurrency suite |
| Network | one-time image pull | later runs use the local image cache |
| Shell | bash | macOS default |

Apple Silicon (arm64) and Intel are both supported; the official
`wordpress`, `mariadb`/`mysql` and `mailpit` images are multi-arch.

The host needs **no PHP, WP-CLI or MySQL** installed — everything runs in
containers (this is why the host PHP binary is irrelevant).

---

## One-command lifecycle

```bash
cd runtime
cp .env.example .env        # edit DZN_REPO_ROOT to match this checkout
make env                   # or: cp .env.example .env
make all                   # full acceptance: build -> schema 25 -> tests -> rebuild
```

The small-command equivalents are:

| Action | Make | Script |
|---|---|---|
| start | `make up` | `bin/up.sh` |
| install WordPress | `make install-wordpress` | `bin/install-wordpress.sh` |
| install + activate plugin | `make install-plugin` | `bin/install-plugin.sh` |
| fresh migration 0->25 | `make fresh-migration` | `bin/run-fresh-migration.sh` |
| upgrade rehearsal 24->25 | `make upgrade-rehearsal` | `bin/run-upgrade-rehearsal.sh` |
| pure tests | `make pure-tests` | `bin/run-pure-tests.sh` |
| runtime tests | `make runtime-tests` | `bin/run-runtime-tests.sh` |
| concurrency | `make concurrency` | `bin/run-concurrency.sh` |
| schema 25 verify | `make verify-schema25` | `bin/verify-schema25.sh` |
| stop (keep data) | `make down` | `bin/down.sh` |
| wipe + restart | `make reset` | `bin/reset.sh` |
| remove everything | `make destroy` | `bin/destroy.sh` |

`make destroy` deletes the disposable database, volumes and WordPress files; it
prompts for `YES` and does not touch the repository checkout or anything else on
the host.

---

## What it runs

### 1. Reproducible runtime definition

[`compose.yaml`](compose.yaml) defines four services on a shared network:

- `db` — MariaDB 11.4 (switch to `mysql:8.4` via `DZN_DB_IMAGE`), local only.
- `wordpress` — `wordpress:php8.3-apache` on `127.0.0.1:8080`.
- `cli` — `wordpress:cli-php8.3` for interactive WP-CLI debugging.
- `mailpit` — mail catcher on `127.0.0.1:1025` (SMTP) and `:8025` (web UI).

WordPress core and `wp-config.php` are written into the bind-mounted
`wordpress/` directory by the official image entrypoint, so `wp core download`
is never needed at runtime.

### 2. WP-CLI and PHP test execution path

All scripted WP-CLI calls use a one-off `wordpress:cli-php8.3` container on the
shared network — the same invocation shape the existing concurrency runners use
(`wp --path=/var/www/html --allow-root ...`). Pure-PHP tests run `php` inside
the same image. See [`bin/common.sh`](bin/common.sh) for the exact wrappers.

### 3. Fresh-schema migration test (zero -> Schema 25)

`make fresh-migration` wipes the database and WordPress directory, installs
WordPress, activates the plugin (which runs migrations 001..025 against an
empty database) and asserts `dzn_platform_schema_version === '25'` with all 25
migrations recorded.

### 4. Upgrade rehearsal (Schema 24 -> Schema 25)

`make upgrade-rehearsal` checks out the Schema 24 predecessor (`1b9d7aa`),
builds a database at Schema 24, returns to Schema 25 and runs migration 025 in
place, then re-runs the R1 migration verifier. See
[`docs/UPGRADE-REHEARSAL.md`](docs/UPGRADE-REHEARSAL.md).

### 5. Existing contract/runtime tests, no production services

- **Pure PHP**: `static.php` (lint), `schema-contract.php`,
  `fresh-install-capability-bootstrap.php`, `migrator-runtimeexception-runtime.php`,
  every `*-contract.php`, and the pure time/lifecycle/overlap computations.
- **Runtime (WP + DB)**: the Phase 2A.2-J fixture, then the R1
  `authority`, `migration`, `failure` and `corruption` runtime proofs.
- **Concurrency**: the 9 R1 race modes (`duplicate_evidence`,
  `handoff_vs_schedule`, `settlement_vs_lesson_seven`, `promotion_global_limit`,
  `conflicting_evidence_replay`, `release_vs_satisfaction`,
  `unrelated_commitments`, `unattributed_conflict`,
  `unattributed_convergence`), each with isolated DB state.

Full taxonomy in [`docs/TEST-MATRIX.md`](docs/TEST-MATRIX.md).

### 6. Mail catcher seam

Mailpit is included for future notification phases. No platform code sends
mail today, and no test sends to a real transport. The SMTP seam is available at
`localhost:1025` for future work; see
[`fixtures/providers/README.md`](fixtures/providers/README.md).

### 7. Provider fixtures/mocks

[`fixtures/providers/`](fixtures/providers/) defines the Stripe / Google / Meta
adapter contract for future phases: digest-over-raw-values, provider-identity-as-
mapping, and a strict "no live credentials / no live traffic" rule.

---

## Environment variables

See [`.env.example`](.env.example). The values that matter most:

| Variable | Default | Purpose |
|---|---|---|
| `DZN_REPO_ROOT` | *(set to this checkout)* | absolute plugin path |
| `DZN_DB_IMAGE` | `mariadb:11.4` | `mysql:8.4` also supported |
| `DZN_WP_PORT` | `8080` | loopback WordPress port |
| `DZN_WP_ENVIRONMENT_TYPE` | `local` | gates runtime tests to non-production |

---

## Security and scope boundaries

- WordPress and Mailpit bind to `127.0.0.1` only; the database has no published
  port.
- No production credentials, no production traffic, no `niu-nailhouse.com`, no
  external send, no deployment.
- The platform business semantics are not modified; the only repository change
  is this additive `runtime/` directory.
- `runtime/.gitignore` excludes `wordpress/`, `.env` and logs, so no disposable
  state or secrets can be committed.

---

## Known limitations

- Historical per-phase runtime/migration/concurrency tests are pinned to their
  own commits and are replayed by checking out that commit, not by the Schema 25
  acceptance (see the test matrix).
- Theme installation and live plugin upload remain manual and are outside this
  task by design.
- The first `up` pulls images and therefore needs network; later runs are
  offline using the local Docker image cache.
