# Core-dataset technical prerequisites

Schema 33 adds bounded operational evidence storage only. It creates no Enrolment, Teacher Assignment, Term or Lesson rows, performs no backfill, selects no canonical-versus-legacy policy, calls no provider, and does not deploy or cut over.

The existing authorities remain the writers:

- Enrolment conversion: `EnrolmentConversionService::convert()`.
- Initial Teacher Assignment: `TeacherAssignmentService::assignInitial()`.
- Canonical Lesson issuance: `CanonicalLessonAuthorityService::createStandard()` / `createReplacement()`.

The readiness service provides a read-only-over-domain, deterministic expected-vs-actual projection for each bounded scope (`enrolments`, `teacher_assignments`, `canonical_lessons`). The digest is SHA-256 over the ordered projection; a mismatch is reported and never repaired. Each projection is then persisted as an append-only `core_dataset_reconciliation_runs` row carrying the acting operator, the expected and actual counts and digests and the match state, with deterministic `core_dataset_reconciliation_findings` rows when a run mismatches — so the recorded reconciliation digest outlives the request that produced it, while no Core domain row is created, changed or repaired. The run row and every finding it requires are written in one transaction, so a mismatched run is never durable without its findings. Operator evidence records the actor, operation, target, reason and evidence-reference digest, and the idempotency payload binds that same evidence-reference digest, so replaying a command key against different operator evidence is a conflicting payload rather than an exact replay: an exact replay of a completed readiness operation converges on its recorded row and a same-key/different-payload replay is refused. Corrections are append-only records containing prior and corrected digests; they do not mutate a domain aggregate.

Durable actor/provenance is recorded only for future evidence. Existing rows remain untouched, including nullable historical actor fields. No value is inferred for a missing actor or source.

## Rehearsal

1. Use a disposable Schema-31/32 database snapshot and record its tree/SHA.
2. Apply the additive readiness slice and verify only the five `core_dataset_*` tables were added; assert all pre-existing table schemas and rows are byte-for-byte unchanged.
3. Run reconciliation for all three scopes with operator-supplied expected count/digest, using an operator identity. Each run records its own evidence row — actor, expected/actual counts and digests, match state and, on a mismatch, deterministic findings — while touching no Core domain row. Preserve both results, including mismatches.
4. In a disposable transaction, exercise each existing authority with a controlled fixture or stop at its validation boundary; do not commit a real domain row. Separately exercise the evidence recorder with non-production test identities.
5. Reconcile again, confirm no unexpected Core rows, and verify evidence rows carry the acting operator and digests.
6. Roll back by disabling the readiness menu/entrypoint and restoring the pre-rehearsal database snapshot. Do not delete evidence or domain rows in production; production correction is forward-only through the owning authority and an append-only correction record.

Acceptance is the preserved pre-existing tree, a recorded reconciliation digest, and a rehearsed rollback—not a cutover decision.

## Correction round 1

Applied on top of the reviewed-and-failed candidate `650e30ea77c5e8ae568af4663cb9a035df7aa566` / tree `20209a09b7fe7fe645caf0a1dc64ed5600611c40`, whose review found three blocking defects. The candidate is additive: the reviewed commit stays an ancestor and nothing was reset, rebased, amended or force-pushed.

1. **Schema-033 migration lock.** `033_core_dataset_technical_prerequisites` is now a member of the locked migration map in `Migrator::maybe_upgrade()` — installed, verified with `verify_core_dataset_technical_prerequisites_schema()` before it is recorded as complete and before `dzn_platform_schema_version` may advance — and it is required by `verify_current_schema()`, which also runs its verifier. The separate post-upgrade `ensure_core_dataset_technical_prerequisites()` path (activation hook and `plugins_loaded` call) is gone, so no database can publish Schema 33 without the five readiness tables or the ledger record.
2. **Recorded reconciliation.** `CoreDatasetReadinessService::reconcile()` still writes no Core row, but it now persists the run it just computed: an append-only `core_dataset_reconciliation_runs` row with the acting operator, expected/actual counts and digests and the match state, plus deterministic `core_dataset_reconciliation_findings` rows (`scope_count_mismatch`, `scope_digest_mismatch`) when a run mismatches. The returned result carries the new `run_id`.
3. **Idempotent operator evidence.** `CoreDatasetReadinessService::recordOperation()` now loads the row already carrying the command-key digest and returns its record id when the recomputed payload digest matches — including when the insert loses a duplicate-key race — and fails closed with `Core operator evidence replay conflict` only for a same-key/different-payload replay. A retry of a completed readiness operation therefore converges instead of failing on the unique key.

The bounded runtime harness was completed at the schema this package declares: `bin/verify-schema32.sh` (historical name) asserts Schema 33, the Schema-033 ledger entry and the five readiness tables; `bin/run-retained-migration.sh` pins the complete `33|33` ledger; both upgrade rehearsals declare the five Schema-033 tables; and `tests/runtime-path-guards.sh` re-asserts that same declared schema.

## Correction round 3

Applied on top of the reviewed-and-failed candidate `4942b5a330c61053dee20ccd8198da2e83bedd25` / tree `7de134e46dacd575d98be6ed1fdd1c4a9670fdfb`, whose review found two blocking defects. The candidate is additive: the reviewed commit stays an ancestor and nothing was reset, rebased, amended or force-pushed.

1. **Evidence-reference binding.** `CoreDatasetReadinessService::recordOperation()` digested the operator evidence reference only on the inserted row, so the command-key idempotency payload did not cover it and a same-key replay carrying a different `evidenceReference` was accepted as an exact replay and returned the prior row. The service now computes `hash('sha256', $evidenceReference)` once, binds that digest into the payload that `command_payload_digest` is taken over, and stores the same value. A replay whose evidence reference differs is therefore a conflicting payload and fails closed with `Core operator evidence replay conflict`; a genuine retry of the same evidence still converges on the recorded row.
2. **Transactional run and findings.** `CoreDatasetReadinessService::reconcile()` inserted the `core_dataset_reconciliation_runs` row and only then wrote each required finding, so a failed finding insert left a mismatched run durable with no findings. The run insert and every required finding now run inside one transaction that opens before the run row is written, commits only after every finding has persisted, and `ROLLBACK`s (re-throwing the original failure) when the `START TRANSACTION`, the run insert, a finding insert or the `COMMIT` itself fails — matching the declared transaction idiom of `PortalCapabilityService`. A matched run writes no finding and still commits its single run row.

`tests/phase-opreadiness-core-dataset-contract.php` was extended with §4 and §5 to re-assert both corrections from the tree: the evidence-reference digest is bound into the payload before the payload digest is computed and is the same value the row stores, and the reconciliation transaction opens before the run insert, holds across `recordFindings()`, and commits only after it, with the `ROLLBACK`/rethrow on failure. The suite remains WordPress-free and reads the tree only.
