# Offline Schema-31 disposable runtime

This is the bounded WordPress/MariaDB evidence harness for Schema 31. It is
not a deployment tool and it never reads from, checks out, or symlinks the
shared checkout into a running WordPress instance.

Every run creates detached Git worktrees beneath `DZN_RUNTIME_STATE_DIR`
(default: `/tmp/dzn-platform-schema31-local`). Path boundaries are enforced
physically, never lexically. The state directory, both worktree paths
(`candidate`, `schema30`), the database and WordPress bind mounts and
`DZN_PLUGIN_SOURCE` are each resolved with `realpath` and refused unless they
land inside the canonical state directory, so a symlink, a relative alias or a
`..` component cannot redirect a run; the state directory itself is also
refused when it resolves inside this checkout. A pre-existing worktree path
that is a symlink is refused outright — including
`state/candidate -> <shared checkout>`, which would otherwise be reused as the
mounted plugin source whenever its Git `HEAD` happened to match the wanted ref.
Those checks run when `bin/common.sh` is sourced and again at every container
entry point (`dzn_compose`, `dzn_wp`, `dzn_wp_env`, `dzn_php`), so a script that
retargets `DZN_PLUGIN_SOURCE` after sourcing — or a tampered
`historical-source` file — still cannot reach docker with non-disposable
source. The only writable plugin source is therefore disposable. WordPress
binds only to `127.0.0.1`; MariaDB has no published port.

The state directory must itself be a dedicated, narrowly scoped disposable area.
`/`, `/tmp`, `/var`, `/var/tmp`, `/etc`, `/usr`, `/opt`, `/srv`, `/mnt`,
`/media`, `/Volumes`, `/System`, `/Library`, `/Applications`, `/Users`, `/home`,
`/root` and the equivalent `/private/...` spellings are refused, as are the home
directory (or any ancestor of it), a directory that contains this checkout, a
path shallower than two levels below the root and a mount point. Consistently,
`dzn_assert_disposable_path` accepts only a path *strictly below* the canonical
state directory — the state directory is never itself a disposable path, so no
cleanup can be aimed at a broad directory by treating it as its own child.

## Offline contract

All three required images must already be present:

- `mariadb:11.4`
- `wordpress:php8.3-apache`
- `wordpress:cli-php8.3`

The compose definition uses `pull_policy: never`, compose starts use
`--pull never`, and direct CLI containers use `--pull=never`.
`bin/check-cache.sh` fails before any lifecycle action if a required image is
not cached. The harness does not run `wp core download`; the cached
WordPress image supplies core to the disposable bind mount.

Copy `.env.example` to `.env` only to choose a different disposable state
directory, project name, or loopback port. It contains no real credentials.
The fixed administrator values are local synthetic values and email delivery
is skipped.

## Evidence commands

`../tests/runtime-path-guards.sh` (also `make path-guards`) is the offline
boundary regression test for the guards above. It needs no cached image, no
container and no network: `docker` is replaced by a recording stub. It proves,
for each clause of the contract, that a symlinked worktree path, a symlinked or
aliased database/WordPress/plugin-source path, an unresolved symlink and a path
escaping the state directory are refused, that a retarget introduced after
sourcing still never reaches docker, and that genuinely disposable paths keep
working. It also re-asserts the offline transport policy (`pull_policy: never`,
`docker run --pull=never`, `dzn_compose up --pull never`).

For destructive scope the same test proves that `/`, `/tmp`, `/var`, `/var/tmp`,
`/etc`, `/opt`, `/Users`, the home directory, a directory containing this
checkout and the state directory itself are refused, records the cleanup command
stream to show that `bin/destroy.sh YES` targets only validated children and
removes the state directory itself with `rmdir` (never `rm -rf`), proves that a
state directory holding unrecognised content makes the command refuse without
deleting anything, and still removes a dedicated state directory end to end.

```sh
cd runtime
bin/check-cache.sh
bin/run-fresh-install.sh
bin/run-retained-migration.sh
bin/run-schema30-to-31-rehearsal.sh
bin/run-pure-tests.sh
```

The rehearsal creates Schema 30 from immutable commit
`86d57606cabcddba15d076edfe14fb4e7257e60f`, snapshots every pre-existing
table's schema and data (with only Migrator's two ledger options excluded),
switches the disposable plugin link to the Schema-31 worktree, and requires
the only additions to be exactly:

- `portal_lesson_capability_roots`
- `portal_public_capabilities`
- `portal_public_capability_events`
- `portal_public_capability_commands`
- `portal_public_action_events`
- `portal_access_denials`

It also verifies that `dzn_platform_portal_actions` remains absent.

`bin/run-regressions.sh` and `bin/run-concurrency.sh` are opt-in hooks for
the pre-existing R2, V, T, and U suites. Their immutable refs and historical
schemas are recorded in
[`manifests/historical-suites.json`](manifests/historical-suites.json).
Each starts a fresh database and its own historical worktree, so those suites
retain their own exact version semantics rather than being run against Schema
31. The historical runner files are unmodified; a private disposable Docker
shim injects `--pull=never` into their worker containers.

Run `bin/destroy.sh YES` to remove only the declared disposable state and
worktrees. Destructive cleanup is scoped twice over: the state directory must
satisfy the dedicated-directory rule above, and only the children this harness
itself creates (`candidate`, `schema30`, `historical-<phase>`, `mariadb`,
`wordpress`, `no-pull-bin`, `historical-source`, `schema30-before.json`,
`schema31-after.json`) are deleted, each after its physical path is
re-validated. The state directory itself is then removed with `rmdir` — never
`rm -rf` — so it disappears only once nothing else remains; an unrecognised
entry makes the command refuse and delete nothing. No Theme file, provider,
credential, network call, deploy, or business-code remediation is part of this
package.
