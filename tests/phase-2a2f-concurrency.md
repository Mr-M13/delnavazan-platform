# Phase 2A.2-F deterministic concurrency harness

Run only against a disposable local/development WordPress database containing
synthetic `.invalid` data. The runner starts independent WP-CLI processes and
uses application-service post-lock hooks. It observes `w1.locked`, launches the
contender, observes `w2.started`, proves an InnoDB wait as `w2.blocked`, and
only then creates `release`; sleeps are never accepted as overlap evidence.
The disposable database user therefore needs read-only `SELECT` access to
`performance_schema.data_lock_waits` and `performance_schema.data_locks`.
Informational eligibility reads do not lock: they complete and write
`w2.observed` while the writer remains held, explicitly proving the permitted
old committed snapshot before the final post-commit projection is checked.

All authority mutations use one lock order:

1. Student aggregate;
2. involved WordPress-user rows in ascending numeric ID;
3. relevant Student principal-link rows by ID;
4. relevant guardian-grant rows by ID;
5. mutation/insertion.

Non-locking ID discovery is permitted before the transaction, but every
authoritative row and expected version is reread under these locks.

| Mode | Race | Expected committed result |
|---|---|---|
| `r1` | resolution vs resolution | first resolution wins; one event/current projection |
| `r2a` | resolution wins vs erasure | resolution commits, then erasure; one retained non-PII identity event |
| `r2b` | erasure wins vs resolution | erasure commits; resolver rejects and creates no Student/event |
| `r3` | capacity vs capacity | two append-only classifications; second is current |
| `r4` | principal establish vs establish, same Student | one active Student slot |
| `r5` | principal establish vs establish, same WP user | one active principal slot |
| `r6` | principal supersede vs competing establish | atomic supersession wins with exact lineage; contender rejects |
| `r7` | guardian grant vs grant | one current exact grant |
| `r8` | guardian grant vs principal establishment | guardian wins; principal rejects |
| `r9` | guardian revoke vs supersede | revoke wins; stale supersession rejects |
| `r10` | intersecting guardian supersessions | deterministic common-user locking; both permitted lineages commit without deadlock |
| `r11c` | capacity transition with informational read | read may observe old complete authority; final read uses new capacity |
| `r11pr` / `r11ps` | principal revoke/supersede with read | held read sees old complete state; final projection reflects mutation |
| `r11gr` / `r11gs` | guardian revoke/supersede with read | held read sees old complete state; final projection reflects mutation |
| `r11e` | elapsed guardian replacement with informational read | held read sees the elapsed grant as ineligible; replacement retires it as `expired` and becomes eligible |

For every mode the verifier checks row counts, active-slot uniqueness,
current pointers, versions, supersession lineage, and rollback integrity.
Generic database/deadlock errors fail the runner.

```sh
export DZN_PHASE_2A2F_RUNTIME_TEST=isolated
export DZN_PHASE_2A2F_WP_CLI=/absolute/path/to/wp
export DZN_PHASE_2A2F_WP_PATH=/absolute/path/to/disposable-wordpress
export DZN_PHASE_2A2F_WP_USER=123
export DZN_PHASE_2A2F_GATE_DIR=/absolute/path/to/empty/shared-gate
export DZN_PHASE_2A2F_MODE=r1
tests/phase-2a2f-concurrency-runner.sh
```
