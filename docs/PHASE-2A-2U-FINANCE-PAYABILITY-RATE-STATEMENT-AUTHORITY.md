# Phase 2A.2-U — Finance, Payability, Effective-Dated Teacher Rates, Statements & Audited Corrections (Schema 30)

**Status:** implementation candidate. Not authoritative until independently reviewed and merged; not
deployed, not merged and not exercised against a runtime in the implementation environment.
**Schema:** 30 / migration `030_finance_payability_rate_statement_authority`.
**Build:** `phase2a2u-finance-payability-rate-statement-20260925.1`.
**Contract:** `docs/PHASE-2A-2U-FINANCE-PAYABILITY-RATE-STATEMENT-AUTHORITY-CONTRACT.md`
(SHA-256 `bacd87afc1f0435716163d64d1b3ed9401ef529201bdde78cc974df7fbbe4900`).
**Base:** `b36561dc6bb6e87fd142a28ae67fbc4f2fdc9279` — the materialised Phase 2A.2-T candidate tree at
Schema 29. Phase T (and R2, V) remain unmerged candidates; this candidate is therefore **explicitly
scoped to that recorded base**, exactly as contract §20 prerequisite 2 allows, and it consumes no
R2/T-only selector.

## 1. What this candidate implements

| Contract section | Surface |
| --- | --- |
| §6 policy authority | `src/Core/Application/Finance/FinancePolicyService.php`, `FinancePolicyRepository`, `finance_policies`, `finance_policy_commands`, the seeded singleton `finance_policy_roots` |
| §7 effective-dated rates | `TeacherRateService`, `TeacherRateRepository`, `finance_teacher_rates`, `finance_teacher_rate_events`, `finance_teacher_rate_commands`, `FinanceRateIntegrity` |
| §8 per-Lesson snapshots | `LessonFinanceSnapshotService`, `FinanceSnapshotRepository`, `finance_lesson_snapshots`, `finance_snapshot_corrections`, `finance_snapshot_commands`, `FinanceSnapshotIntegrity` |
| §9 payability | `LessonPayabilityService`, `FinancePayabilityRepository`, `finance_payability_evaluations`, `finance_payability_overrides`, `finance_payability_commands`, `FinancePayabilityIntegrity` |
| §10 statements | `TeacherStatementService`, `FinanceStatementRepository`, `finance_statements`, `finance_statement_lines`, `finance_statement_events`, `finance_statement_commands`, `FinanceStatementIntegrity` |
| §11 reconciliation | `FinanceReconciliationService`, `FinanceReconciliationRepository`, `finance_reconciliation_runs`, `finance_reconciliation_findings`, `finance_reconciliation_commands`, `FinanceReconciliationIntegrity` |
| §12 audited corrections | `FinanceCorrectionService` (snapshot correction, payability override, statement supersession) |
| §5/§16 vocabulary and upstream facts | `FinanceRule`, `FinanceIdempotency`, `FinanceSupport`, `FinanceFacts` |
| §14 reads and screens | `Read/TeacherRateReadService`, `Read/LessonFinanceReadService`, `Read/TeacherStatementReadService`, `Read/FinanceReconciliationReadService`, five administrator-only controllers under the existing Platform menu |
| §13/§13.5 storage | `Migrator::install_finance_payability_rate_statement_authority()` — twenty-one `dbDelta` tables, the single `finance_policy_roots` row and the three declared default policy versions |
| §13.4 verifier | `Migrator::verify_finance_payability_rate_statement_schema()` at all three declared call sites |
| §14.2 capabilities | `dzn_manage_finance_policies`, `dzn_manage_teacher_rates`, `dzn_manage_lesson_payability`, `dzn_manage_finance_statements`, `dzn_view_finance_authority` — repaired per capability and removed from `dzn_teacher` |
| §17 intents | `TEACHER_STATEMENT_ISSUED`, `TEACHER_STATEMENT_SUPERSEDED`, `FINANCE_RECONCILIATION_EXCEPTION_RAISED`, written identity-only through the unchanged `platform_outbox` seam |
| §18 suites | `tests/phase-2a2u-contract.php`, `-migration-runtime.php`, `-runtime.php`, `-statement-runtime.php`, `-reconciliation-runtime.php`, `-corruption-runtime.php`, `-failure-runtime.php`, `-concurrency-runner.sh` (+ setup/worker/verify), `-fixture.php` |

## 2. How the locked decisions are expressed

- **U-D4 locked instant.** `FinanceFacts::context()` resolves the Lesson's effective Phase-O outcome
  anchors and falls back to the applicable Phase-N schedule version (canonical) or the current
  `lesson_schedule_versions` row (a legacy introductory Lesson). The recorded
  `snapshot_instant_utc`/`snapshot_boundary` is taken verbatim from that anchor and never recomputed.
- **U-D6/U-D19 registries.** Both effective-dated registries carry the same guard: a rate whose
  `effective_from` is not strictly later than the Teacher's greatest snapshot instant is refused
  `rate_effective_from_precedes_snapshot`, and a policy version whose `effective_from` is not strictly
  later than its key's recorded consumption maximum is refused
  `policy_effective_from_precedes_recorded_consumption`. The consumption maximum is a bounded read of
  the declared rows through their declared indexes (`FinancePolicyRepository::consumptionMaximum()`), and
  the verifier proves the equivalent coverage invariant over the recorded pairs and triples.
- **§15.1 one root, chosen from the target's scope.** `FinanceSupport::lockPolicyRootThenTeacher()` is
  the only ordering used by a policy consumer; `lockPolicyRoot(true)` appears only in the three mutating
  policy commands; a period-wide reconciliation `run()` and a `resolveException()` on a teacher-less
  exception take the global policy root shared, and a Teacher-scoped `run()`/`resolveException()` takes
  that Teacher's root and no policy root.
- **§15.8 two outcome classes.** `FinanceSupport::runCommand()` runs the attempt inside its own
  transaction under the selected root, rolls back on a `FinanceRefusalException` and then commits exactly
  the refusal evidence (refused command row, matching `finance_exceptions` row, digest-only audit rows)
  in the declared refusal-evidence transaction. A fail-closed validator whose message is a declared
  exception reason code is converted into that refusal; any other failure rolls the whole command back.
- **§15.7 allowlist.** Every Finance write goes through `FinanceSupport::insertRow()` /
  `compareAndSwap()` (declared-table guard) or the shared boundary's audit/outbox writes; no Finance code
  path names any other table.

## 3. Implementation notes a reviewer should read before the runtime gate

These are the places where the contract states an outcome without fixing every mechanical detail. Each
is implemented conservatively and is flagged here rather than left implicit.

1. **Legacy, non-introductory Lessons.** A canonical Lesson is `record_model='canonical_term_lesson_v1'`
   with kind `standard`/`replacement`; an `introductory` Lesson is the legacy-classified Lesson with no
   Term that Phase Q already treats as authoritative. Any other Lesson row encountered inside a
   statement/reconciliation period (for example a legacy `standard` row) is not a Finance fact and fails
   the derivation closed with `finance_lesson_kind_not_allowed`, exactly as contract §8.5 declares —
   Finance never derives from a non-canonical Lesson. Historical capture of pre-Schema-030 Lessons stays
   the open decision of contract §21.
2. **Snapshot corrections asserting a rate that does not cover the snapshot instant.** Contract §12.2
   admits such a correction only with explicit historical-rate evidence. This candidate records the
   correction **and** an informational `snapshot_derivation_mismatch` exception carrying the evidence
   (so the difference appears in reconciliation and is never hidden), rather than refusing the operator's
   evidenced assertion. The correction still has to be complete (`snapshot_correction_incomplete`) and
   still has to be paired with any affected issued statement's supersession
   (`statement_supersession_required`).
3. **A correction that would change an issued statement** is refused `statement_supersession_required`
   until the operator has run the statement supersession; the pairing is therefore a two-command
   authorisation, never a silent rewrite.
4. **A period with no finance-relevant Lesson** has no derivable single currency, so drafting it is
   refused `statement_derivation_mismatch` rather than inventing a currency for an empty statement.
5. **Two policy keys are read but only the applied one is recorded.** `evaluate()` and `capture()`
   resolve the three payability keys at the snapshot instant and record only the version the applied
   derivation row actually consumed; an unset key that governs the fact blocks the fact
   (`finance_policy_unset`), an unset key that governs nothing does not.
6. **Reconciliation inputs.** `legacy_flag_differs` is emitted from the controlled comparison input
   (`legacy_comparison`) of a run, and `provider_evidence_unmatched` from recorded, provider-neutral
   R1 evidence whose processing state is not `accepted` — both as visibility, never as authority, exactly
   as §11.1/§11.3 require. A run that cannot hydrate a Lesson records the run `failed` with
   `upstream_aggregate_invalid` and appends no unproven finding.
7. **Confirmed Schema 030 (contract §20 prerequisite 1).** Owner brief said "029"; 029 is owned by the
   Phase-T candidate, so this candidate targets the next free identifier, `030_finance_payability_rate_statement_authority`,
   and re-assigns nothing. Confirming 030 remains the first acceptance-gate item.

## 4. Evidence status — read this before accepting the candidate

| Evidence | Status |
| --- | --- |
| Source contract suite (`tests/phase-2a2u-contract.php`) | **Written, not executed** — PHP is absent in the implementation environment (`php: command not found`), so no PHP file in this candidate has been linted or run |
| Migration/verifier at all three call sites | Implemented; **not executed** |
| Runtime, statement, reconciliation, corruption, failure suites | Written; **not executed** |
| Concurrency runner (`phase-2a2u-concurrency-runner.sh` + setup/worker/verify) | Written; **not executed** |
| Adjacent L/M/M0/N/O/P/Q/R1/R2/T regressions | **Not re-run** |

The implementation environment has no PHP interpreter, no MariaDB and no reachable Docker daemon (the
container socket is denied by the sandbox), so no migration, authority, corruption, failure or
concurrency evidence exists yet. The static checks that *were* run are the source-level proofs the suite
encodes (declared table set, vocabulary and reason-code literals, digest-only/append-only discipline,
parent and index contract, root discipline, allowlisted writes, intents, docs). Executing every §18
suite on the disposable WordPress + MariaDB runtime is a mandatory acceptance gate before this candidate
may be merged.

## 5. Deliberate non-authorisations preserved

No ledger, journal, chart of accounts, tax/VAT rule, invoice, payout, bank/IBAN/card detail, remittance,
provider call, credential, notification delivery, public/Portal route, Theme change, Amelia write, merge,
deployment or production access exists in this candidate. Student pricing, obligations, settlements,
funding, collection and refund facts stay with R1/R2/T and are read-only here. Every open owner decision
of contract §21 remains open.
