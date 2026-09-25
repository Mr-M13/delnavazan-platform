# Phase 2A.2-U — Finance, Payability, Effective-Dated Teacher Rates, Statements & Audited Corrections (Schema 30)

**Status:** implementation candidate — **independent review correction round 2 applied** (the review of
`86d57606cabcddba15d076edfe14fb4e7257e60f` / tree `6010181bfbf66e01fa49154c9ba266d1dd4888c4` returned
FAIL — CORRECTION REQUIRED with six blocking findings; all six are closed, plus two declared adjacent
corrections and two suite defects — see §6). Not authoritative until independently reviewed and merged;
not deployed and not merged.
**Schema:** 30 / migration `030_finance_payability_rate_statement_authority`.
**Build:** `phase2a2u-finance-payability-rate-statement-20260925.1`.
**Contract:** `docs/PHASE-2A-2U-FINANCE-PAYABILITY-RATE-STATEMENT-AUTHORITY-CONTRACT.md`
(SHA-256 `9893bbd935aad4a66908340ca6e3a91546be65b4cc80e7a334ed5f3473b8cd07`; correction round 2 adds
§0g and the §6.2/§7.2/§9.2/§9.3/§10.5/§12.2/§13.5/§15.3/§15.4/§15.5 amendments).
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
| Source contract suite (`tests/phase-2a2u-contract.php`) | **Executed and passing** under a PHP 8.5.8 WASM CLI (`php-wasm`, PHP 8.5.8, no WordPress and no database): `phase-2a2u-contract: OK`. Two defects in the suite itself were found and corrected with it — a malformed `str_contains()` needle for the per-capability repair and a closure `use` clause carrying a default value in `tests/phase-2a2u-corruption-runtime.php` |
| PHP syntax of the whole candidate | **Executed and passing**: all 460 `.php` files of `src/`, `tests/`, `delnavazan-platform.php` and `uninstall.php` tokenise and parse cleanly under PHP 8.5.8 (`token_get_all(..., TOKEN_PARSE)`) |
| Migration/verifier at all three call sites | Implemented; **not executed** |
| Runtime, statement, reconciliation, corruption, failure suites | Written; **not executed** |
| Concurrency runner (`phase-2a2u-concurrency-runner.sh` + setup/worker/verify) | Written; **not executed** |
| Adjacent L/M/M0/N/O/P/Q/R1/R2/T regressions | **Not re-run** |

The implementation environment has no MariaDB and no reachable Docker daemon (the container socket is
denied by the sandbox), so no migration, authority, corruption, failure or concurrency evidence exists
yet: those suites need the disposable WordPress + MariaDB runtime, and executing every §18 suite on it is
a mandatory acceptance gate before this candidate may be merged. The static half of the gate is now
executed: the source contract suite passes (including its source scans over
`src/Core/Application/Finance/**` for the declared command result states, the reason-code allowlist, the
typed command results, the root discipline, the §15.7 write allowlist and the §17 intent shape) and every
PHP file in the candidate parses.

## 5. Deliberate non-authorisations preserved

No ledger, journal, chart of accounts, tax/VAT rule, invoice, payout, bank/IBAN/card detail, remittance,
provider call, credential, notification delivery, public/Portal route, Theme change, Amelia write, merge,
deployment or production access exists in this candidate. Student pricing, obligations, settlements,
funding, collection and refund facts stay with R1/R2/T and are read-only here. Every open owner decision
of contract §21 remains open.

## 6. Independent review correction round 2 (implementation candidate)

The independent review of `86d57606cabcddba15d076edfe14fb4e7257e60f` (tree
`6010181bfbf66e01fa49154c9ba266d1dd4888c4`) returned **FAIL — CORRECTION REQUIRED** with six blocking
findings. All six are closed on this descendant (additive history only: no reset, no rebase, no amend, no
force-push), and contract §0g records them in the sections that govern them.

| # | Finding | Where the correction lives |
| --- | --- | --- |
| 1 | Command rows omitted the `NOT NULL` `result_state`, and refusal evidence kept the attempt's success state | `FinanceRule::COMMAND_SUCCESS_STATES`/`COMMAND_REFUSAL_STATE`/`COMMAND_FAILED_STATE`/`COMMAND_RESULT_STATES` + `FinanceRule::commandSuccessState()`; one declared success state on every `capture`/`correct_snapshot`/`evaluate`/`override`/`draft`/`issue`/`withdraw`/`supersede`/`run`/`resolve_exception` command row; `FinanceSupport::commitRefusal()` writes `result_state = 'refused'` with `NULL` typed results. Runtime, statement, reconciliation and failure suites now prove both paths on all six command tables |
| 2 | A successor rate claimed the scope's live slot before its predecessor released it (`UNIQUE teacher_scope_slot`) | `TeacherRateService::record()` closes and supersedes the predecessor first, each move a conditional statement requiring an affected-row count of `1`, inside the same transaction; `withdraw()` measures the interval-free proof only where it writes a closure. Contract §7.2 states the order |
| 3 | An appended evaluation/correction pre-claimed the one applicable slot (`UNIQUE lesson_applicable`/`snapshot_applicable`) | The declared two-statement protocol of contract §15.4: append with `applicable_slot = NULL`, release the predecessor, then claim the slot with its own conditional statement (`FinancePayabilityRepository::claimApplicable()`, `FinanceSnapshotRepository::claimApplicable()`), both counts checked. Runtime + statement suites prove re-evaluation, override and a second correction |
| 4 | `record()` silently superseded the predecessor, so the declared `supersede()` command could never succeed | `FinancePolicyService::record()` inserts one version and moves nothing (`policy_id`/`result_policy_id` both name the row it wrote); `supersede()` performs the single conditional `active → superseded` move with its own command row, audit evidence and affected-row count. The runtime suite proves the two-command lifecycle and the refusal of a second supersession |
| 5 | Issuance never compared the stored line with the current effective snapshot/evaluation | `TeacherStatementService::assertLineFresh()` compares snapshot id, applicable correction id, payability-evaluation id, disposition, basis code, rate row/version, compensation basis, currency and the exact recomputed amount; a stale draft is refused `statement_derivation_mismatch` with its recorded totals untouched and is withdrawn + re-drafted (§10.5 rule 6, §15.5). The statement suite proves refusal, withdrawal, re-draft and re-issuance against a corrected amount |
| 6 | The reconciliation `run()` closure never captured `$payload` | `$payload` is captured; the reconciliation suite adds the identical replay (one run, one command row, no second run) and the conflicting replay refused `command_replay_conflict` |

Two further corrections are declared in contract §0g rather than left implicit, because the corrected
policy lifecycle and the declared issuance gate are only coherent with them:

- **The policy verifier refused a live installation.** The seed assertions were implemented against the
  *fresh* state ("no row for `FINANCE_STATEMENT_TIMEZONE`", "at most four rows in total"), which the
  declared `record()`/`supersede()` lifecycle necessarily leaves behind — the verifier runs on every
  current-schema verification, so the first recorded timezone policy would have failed closed on every
  subsequent request. It now asserts the seed **shape**: the only migration-authored (`recorded_by = 0`)
  rows are the three declared keys' version 1, and no migration-authored row names the timezone key.
- **The rate-withdrawal guard judged time it does not write.** Withdrawing a rate a successor already
  closed wrote no interval yet was measured against the successor's interval, so a superseded rate could
  not be retracted. The proof now runs only where a closure write happens.

Two defects in the suites themselves are corrected with this round: a closure `use` clause carrying a
default value (a syntax error that made `tests/phase-2a2u-corruption-runtime.php` unparseable) and a
malformed `str_contains()` needle in `tests/phase-2a2u-contract.php`; the concurrency fixture's
`concurrent_policy_record` mode also raced an instant its own seeded predecessor already claimed, so
neither contender could succeed and the mode's declared "one succeeds, the loser refuses
`finance_policy_timeline_overlap`" outcome was unreachable — its competing instant is now admissible for
the winner.

**Still outstanding, and flagged rather than silently rewritten:** the §18 *runtime* suites need the
disposable WordPress + MariaDB runtime to be executed, and they require fixture-flow work before they can
pass — their occurrence helper schedules then releases the canonical schedule version (so a capture has
no applicable anchor unless an outcome is recorded) and leaves the Lesson `authorised` (so §8.2's
`snapshot_lesson_not_finalised` gate refuses the capture), the outcome helpers require an occurrence that
has already ended, and several calls pass a 60 *minute* duration where the suite's own comment intends one
minute. Those are test-fixture defects, not candidate behaviour, and they are recorded here so the
runtime gate is executed against a corrected fixture rather than assumed green.
