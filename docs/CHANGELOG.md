# Changelog

All notable changes to the Delnavazan Platform repository are documented here.
Platform phase numbers are independent of Hamnavaz phase numbers.

## Phase 2A.2-Q — Post-Intro Continuation & Slot Reservation Authority — merged and closed — 2026-09-20

- Final independently approved candidate `815a301679fdebc5d058646749e5a206ab492629`, tree `13757b52dd84810d44e6dba19550ec7e668ad169`, was **fast-forward merged into `main`** from authoritative pre-merge main `b7378665af1bc3cce935cfae27d346326a1ddc10`; the implementation-merge SHA equals the approved candidate because no merge commit was required. Schema 24 / migration `024_post_intro_continuation_slot_reservation_authority` / build `phase2a2q-post-intro-continuation-slot-reservation-20260920.1` are authoritative. **No deployment occurred**; production, Theme/NIU, payment/Stripe, provider and Portal surfaces were untouched, and no production continuation workflow was activated.
- Phase Q is authoritative for exactly one interval of the Student journey: the post-introductory continuation decision and a bounded pre-payment hold on the single explicitly authorised first regular class slot. The introduction remains free and separate; the hold is never a Lesson, schedule, Term, Assent, Assignment, payment or entitlement; the first-regular-slot record is the only source that may justify capacity (no `+7 days` recurrence inference exists); a delayed slot record converges an existing continuing decision without duplicating consent and resolves the missing-slot requirement append-preservingly; a Phase-N Lesson schedule and a Phase-Q hold remain distinct authorities with bidirectional Teacher-capacity arbitration; accepted-arrangement lineage ambiguity fails closed; and the documented lock order matches the implementation.
- Review history retained: original candidate `33368f6a` failed on Q-1/Q-2/Q-3; Correction-Round-1 candidate `c43a89b3` failed on Q-R1-1/Q-R1-2/Q-R1-3; Correction Round 2 passed final independent re-review. Phase P remains complete and closed.
- **Known follow-up (test hygiene only):** the `expiry_vs_new_claim` concurrency mode's two-second expiry window can invert its expected winner order under load while production behaviour stays correct; widening the window made two consecutive full-matrix runs pass. This should be corrected in a future bounded test-harness change, not inside this merge.

## Phase 2A.2-Q — Post-Intro Continuation & Slot Reservation Authority — Correction Round 2 candidate, unmerged — 2026-09-20

- **Correction Round 2 (this candidate)** corrects the independent-review findings on Correction-Round-1 candidate `c43a89b3aeecd74d4335b2a1fabd70c4e0ad263e` (tree `0cdf92de266f70d9e22e887e6a384934082ec51a`), which **failed review** on Q-R1-1/Q-R1-2/Q-R1-3. All changes descend from `c43a89b3`. Schema remains 24; migration and build unchanged. **Not merged, not deployed.**
- **Q-R1-1 (HIGH)** — recording the authoritative first regular slot now converges an **already-recorded** `continue_with_teacher` decision into the hold in the same transaction: durable case hydration and revalidation, original decision preserved (the reservation references it), Teacher scheduling root acquired, Phase-N schedules and other holds rechecked, exactly one reservation created with the frozen expiry, the `first_regular_slot_authority_required` requirement resolved append-preservingly (`state='resolved'` plus resolution actor/instant), and durable command evidence that returns the same authority/case/reservation on exact replay. No second Student decision is created and no consent is duplicated. Any failure — including a capacity conflict — rolls the whole convergence back, so no false `continue + slot + no hold` success can be observed; cases that are no longer eligible keep correct history and are never reactivated.
- **Q-R1-2 (MEDIUM)** — the documented lock order now states the **implemented** order (introductory Lesson → Phase-F principal/guardian authority row → continuation case → per-Teacher scheduling root → Phase-N versions) with a deadlock audit showing Phase-F mutation paths never acquire Lesson, Phase-Q or Teacher-root locks, so no reverse edge or cycle exists.
- **Q-R1-3 (LOW)** — the dead concurrency-setup closure that still referenced the removed slot derivation was deleted; every fixture now uses an explicit slot authority, and the Phase-Q contract continues to reject any reintroduction of `+7 days` derived authority.
- Validation (owner-executed): Phase-Q contract, authority runtime (delayed convergence, original-decision preservation, intervention resolution, capacity-conflict rollback, expiry boundaries, DST rejection), 34-case corruption runtime, **10-boundary** failure injection, 10-case migration runtime and the **16-mode** concurrency runner including three delayed-slot races. Phase-P/O/M/M0/L and Migrator regressions re-run.

## Phase 2A.2-Q — Post-Intro Continuation & Slot Reservation Authority — Correction Round 1 candidate, unmerged — 2026-09-20

- **Correction Round 1 (this candidate)** corrects the independent-review findings on candidate `33368f6a37c128b476419542e507462952909702` (tree `a20c48d8c8f5aaf61688d029c5b5084daa113327`), which **failed review** on Q-1, Q-2 and Q-3. All corrections are descendants of `33368f6a`; no commit was amended, rebased or rewritten. Schema remains 24 / `024_post_intro_continuation_slot_reservation_authority`; build unchanged. **Not merged, not deployed.**
- **Q-1 (HIGH)** — the invented `introduction time + 7 days` future-slot rule is removed entirely. Audit confirmed no existing authority carries a recurring future class (`proposal_versions` / `accepted_service_arrangements` hold only `frequency_per_week`, `schedule_constraints`, a commencement window and a timezone), so Phase Q now owns the narrowest truthful fact: an immutable `dzn_canonical_continuation_slot_authorities` record created by the administrator-only `recordFirstRegularSlot()` command, binding the exact introductory occurrence, Student, Teacher, Course, timezone, local wall clock, resolved UTC interval, authority basis, evidence provenance and rule version. A hold may exist only when bound to that record; continuing before a slot is authorised records the decision, holds nothing and raises `first_regular_slot_authority_required`. Nonexistent/ambiguous wall clocks, a non-future slot and a duplicate slot all fail closed. The six-day expiry policy is unchanged and its first operand is now genuine authority.
- **Q-3 (MEDIUM)** — accepted-arrangement lineage is no longer chosen with `ORDER BY id LIMIT 1`: exactly one candidate is authoritative, several raise `continuation_arrangement_ambiguous`, and a bound arrangement must remain an intact accepted record.
- **Q-2 (HIGH)** — the four omitted cross-authority races are now process-proven with real connections and gated locks: Phase-Q hold vs Phase-N Lesson scheduling in **both** commit orders, hold-expiry vs new capacity claim across the frozen boundary, guardian decision vs guardian revocation, and Student principal change vs decision (both orders). The runner now starts every race from a freshly prepared fixture so the matrix is order-independent, and each race proves the contender genuinely waits, revalidates after the wait, and yields exactly one winner with no double occupancy, no orphan hold and no partial schedule. Lock order documented: Phase-F authority rows → Phase-Q lesson/slot → Phase-Q case → per-Teacher root → Phase-N versions.
- Validation (owner-executed): Phase-Q contract, authority runtime (including four explicit expiry-boundary cases, DST gap/ambiguity rejection, missing-slot-authority handling and a proof that the hold is never a `+7 days` derivation), **34-case** corruption runtime, **six-boundary** failure injection, migration runtime (10 malformed-storage cases), and the **13-mode** concurrency runner. Phase-P/O/N/M/M0/L regressions re-run.

## Phase 2A.2-Q — Post-Intro Continuation & Slot Reservation Authority — implementation candidate, unmerged — 2026-09-20

- Branch `phase-2a2q-post-intro-continuation-slot-reservation` from authoritative base `b7378665af1bc3cce935cfae27d346326a1ddc10` (Phase P merged and closed). Schema 24 / migration `024_post_intro_continuation_slot_reservation_authority` / build `phase2a2q-post-intro-continuation-slot-reservation-20260920.1`. **Not merged and not deployed.**
- Phase Q owns exactly one narrow interval of the Student journey — the post-introductory continuation decision and, for a continuing decision only, a bounded temporary hold on the single expected first regular class slot — and stops before payment. It creates no payment intent, Stripe object, invoice, receipt, `payment_state`, Term, standard/replacement Lesson, academy obligation, delivery/attendance outcome, notification or provider/calendar/Google authority.
- Source-graph audit: an introductory Lesson in this repository is a `legacy_phase1` Lesson with `lesson_type='introductory'` and no Enrolment/Term, scheduled through the legacy current `lesson_schedule_versions` occurrence. Phase Q binds that source plus the Phase-F Student/guardian authority, the Phase-J Teacher principal, optional canonical Enrolment/Assignment/accepted-arrangement lineage and Phase-N Teacher capacity. It never forces the introduction into a paid Term and never fabricates a delivery fact: the post-intro gate proves only that the scheduled introductory occurrence window has ended, which permits a decision to be recorded.
- Locked owner decisions Q-D1…Q-D12 are implemented: the introduction is free and separate; four controlled Student dispositions; continuing creates a bounded pre-payment hold (not a Lesson/schedule/Term/Assent/Assignment/payment); only the first expected post-intro occurrence is held; expiry is frozen as `min(expected slot start, introductory occurrence end + 6 days)`; expiry preserves all history; a different-Teacher request, a contact request and a Teacher match-unsuitable report each record a controlled administrator intervention (the Teacher report also releases the hold and suppresses ordinary continuation); not-continuing closes the immediate path with optional controlled feedback only; payment is deferred; administration is exception handling.
- Decision authority is explicit and self-enforcing: Student commands require Phase-F adult principal or guardian-representative authority matched to the Student's capacity classification; the Teacher command requires its own capability plus the authoritative Teacher principal for the bound introduction and grants no other authority; integrity flagging is administrator-only. Every command is digest-bound, exact-replay idempotent, changed-context fail-closed (no nullable expected digest), and validates the complete aggregate before and after mutation.
- Real capacity, no fake flag: the hold participates in canonical Teacher-capacity arbitration through one narrow seam consulted by Phase-N scheduling, and Phase Q consults Phase-N schedules before inserting a hold; both take the same per-Teacher scheduling root. Phase-N Lesson schedules and Phase-Q pre-payment holds remain distinct authorities. Expiry is deterministic and lazy (frozen `expires_at`; capacity-effective only while active and unexpired).
- Validation (owner-executed, disposable WordPress/MariaDB harness): Phase-Q contract, authority runtime, 24-case corruption runtime with repair/recovery, five-boundary failure injection with retry convergence, migration runtime (fresh Schema 24, 23→24 rehearsal, repeat, partial capability repair, no backfill, no payment/Term creation, provider-neutral storage, 8 malformed-storage cases, retained-024 fail-closed) and a six-mode deterministic gated concurrency runner. Phase-P, Phase-O, Phase-N, Phase-M, Phase-M0 and Phase-L regressions re-run unchanged.
- Deferred and explicitly not owned: payment/Stripe, Term and Lesson materialisation, notification delivery, Student/Teacher Portal integration, Google/Meet/OAuth/webhook/credential work, calendar, Amelia, remediation, rescheduling and deployment.

## Phase 2A.2-P — Canonical Attendance Intake & Review Authority — merged and closed — 2026-09-20

- Final independently approved candidate `30fe1f11c425ad7066408f20ca57e2ca084841d6`, tree `34c111c01932f14552ada366f911f9b404111692`, was **fast-forward merged into `main`** from authoritative pre-merge main `7b9aea68fddd651cd614f279f77e78b104885c4d`. The implementation-merge SHA equals the approved candidate because no merge commit was required. Schema 23 / migration `023_canonical_attendance_intake_authority` / build `phase2a2p-canonical-attendance-intake-20260919.1` are now authoritative on `main`. **No deployment occurred**; production, Theme/NIU, Google/provider credentials, Stripe/payment, notification and portal surfaces were untouched.
- Phase P passed final independent re-review after three correction rounds (original `6a6a4ce` failed; `eda3df2` is historical incomplete; `629310e` failed; `0d6f0f3` failed; `30fe1f1` passed). No production attendance cutover was activated and no provider integration was implemented.
- Phase P is provider-neutral attendance intake/evidence/assessment/review authority only: canonical delivery/attendance truth remains with Phase O, and Lesson completion remains with Lesson authority. No Google API/OAuth/webhook/credential, calendar, Amelia, payment, notification, Teacher Portal or Student Portal integration was introduced.

## Phase 2A.2-P — Canonical Attendance Intake & Review Authority — Correction Round 3 candidate, unmerged — 2026-09-20

- **Correction Round 3 (this candidate)** corrects the three final independent re-review findings on Round-2 candidate `0d6f0f3ab18fe7989367b78610a73beb0e3d7bd9` (tree `a554f8ac3cc026770ea55a4edc2ebb2dce7697c1`). All changes are descendants of `0d6f0f3`; no commit was amended, rebased or rewritten. Schema remains 23 / `023_canonical_attendance_intake_authority`; build remains `phase2a2p-canonical-attendance-intake-20260919.1`. **Not merged, not deployed.**
- **R3-1 (HIGH)** — `recordCutoverPolicy()` now resolves the canonical command digest against the durable command authority before applying wall-clock prospective validation. An exact completed cutover command stays idempotently replayable after its cutover instant passes (same policy id, no second row, no canonical mutation), while a genuinely new command at a passed/backdated instant still fails `cutover_instant_not_prospective` and a changed payload under the same command key still fails `Idempotency conflict`. Regression crosses the wall-clock boundary with a short 2-second future instant.
- **R3-2 (MEDIUM)** — `replayCommand()` now takes `string $payload` (non-nullable) and compares the expected digest unconditionally. No nullable or sentinel bypass remains, and every caller supplies the canonical expected digest.
- **R3-3 (LOW)** — the `command_key_exact` concurrency verify now proves exactly one non-idempotent creation, exactly one `idempotent=true` response, the same attendance case, the same durable evidence identifier and exactly one evidence row, while the six changed-context modes keep failing `Idempotency conflict`.
- Validation (owner-executed): PHP lint, shell syntax, 30/30 `tests/*contract*.php`, overlap runtime, authority runtime (with post-cutover exact replay), 30-case corruption runtime, failure runtime, migration runtime (9 malformed-storage cases), 13-mode gated concurrency runner, and Phase-O/N/M/M0/L plus Migrator `RuntimeException` regressions. The Phase-M0 protected-read runtime's six integrity cases pass; its legacy-bootstrap step remains a pre-existing empty-`uid` harness defect on a reused disposable DB, unrelated to Phase-P.

## Phase 2A.2-P — Canonical Attendance Intake & Review Authority — Correction Round 2 candidate, unmerged — 2026-09-20

- **Correction Round 2 (this candidate)** corrects the three authority/integrity defects found in the final independent re-review of Correction Round 1 (`629310e835a4cad3bc25d5406280944c2b6e6145`, tree `49b93a71afefbe0471fe57dafc8961cef4de6f31`). All Round-2 corrections are descendants of `629310e`; no commit was amended, rebased or rewritten. Schema remains 23 / `023_canonical_attendance_intake_authority`; build remains `phase2a2p-canonical-attendance-intake-20260919.1`. **Not merged, not deployed.**
- **R1 / P-8 (HIGH)** — the concurrent ingest duplicate-command exception recovery path now reconstructs the complete incoming command facts and computes the same canonical payload/context digest as the normal ingest path, then compares it against `winner.command_payload_digest` through `replayCommand()`. No helper passes a null expected digest; the claim recovery path is hardened identically. A same-Lesson/schedule command key with a changed provider payload, event, account, join/leave interval, observation instant or provenance now fails `Idempotency conflict` and can never be acknowledged as an exact replay. Source/contract coverage now rejects any null expected digest in ingest/claim recovery rather than relying on a surface string match.
- **R2 / P-7 (HIGH)** — cutover applicability can never fall back to the database insertion id. Migration 023 now declares `UNIQUE KEY cutover_instant(cutover_utc)` and the verifier requires it; `recordCutoverPolicy()` rejects a second distinct policy at the same instant (`duplicate_cutover_instant`) while exact replay stays idempotent; and `applicablePolicy()` fails closed (`cutover_policy_ambiguous`) if corrupted/legacy data contains several policies at the same newest cutover instant.
- **R3 (HIGH)** — the protected current-attendance read resolves the authoritative current schedule version and refuses a case bound to a superseded version with `schedule_version_conflict`, matching reassessment, adjudication and settlement. Historical evidence is retained but never presented as current truth, and the read remains read-only (no mutation, no write transaction).
- Validation (owner-executed): PHP lint, shell syntax, 30/30 `tests/*contract*.php`, overlap runtime, authority runtime (with duplicate-instant rejection, cutover-ambiguity fail-closed and same-occurrence duplicate-command context matrix), 30-case corruption runtime, failure runtime, migration runtime (9 malformed-storage cases including non-unique cutover instant), and a **13-mode** gated concurrency runner including seven same-Lesson/schedule command-key races. Phase-O/N/M/M0/L and Migrator `RuntimeException` regressions re-run. The Phase-M0 protected-read runtime's six integrity cases pass; its legacy-bootstrap compatibility step fails only on a reused disposable DB because the pre-existing `insertLegacyBootstrap` helper persists an empty `uid`, which is unrelated to this correction.

## Phase 2A.2-P — Canonical Attendance Intake & Review Authority — Correction Round 1 complete candidate, unmerged — 2026-09-19

- **Correction Round 1 (this candidate)** completes every outstanding independent-review finding on branch `phase-2a2p-canonical-attendance-intake`, as a descendant of partial correction `eda3df2a24f5de67dcbba1dbe6e4ae863b325e13` (tree `53af4c29cfb63dd21aad9fe99b2ef0e1d9d768aa`); the original reviewed candidate `6a6a4ce8249f79df0371753479587e5d9c1fcd27` (tree `f17ef71c989ae8449108bb4ccd279b2daec4026a`) failed independent review on P-1…P-11. No prior commit was amended, rebased, squashed or rewritten. Schema remains 23 / `023_canonical_attendance_intake_authority`; build remains `phase2a2p-canonical-attendance-intake-20260919.1`. **Not merged, not deployed, not yet independently re-reviewed.**
- **P-1 (HIGH)** — a durable, provider-neutral participant identity authority now decides which canonical Teacher or Student a provider account belongs to. New `dzn_canonical_attendance_participant_mappings` registry (provider code + keyed account digest + role + canonical participant + `verified`/`unverified`/`revoked` state + provenance/evidence digests + verification/revocation instants + mapping version) behind a new administrator capability `dzn_manage_canonical_attendance_identity`. Intake no longer accepts any caller-supplied identity or verification assertion; a missing, unverified, revoked, ambiguous, wrong-role or wrong-participant mapping is retained as evidence, never qualifies, and raises an explicit anomaly. The complete aggregate including the just-inserted row is re-validated before any assessment or canonical settlement.
- **P-2 (HIGH)** — `settleDelivered()` accepts a durable case ID (never a caller object), refuses any case without a durable settlement intent (`attendance_settlement_not_pending`), and re-validates the case aggregate, cutover-policy binding and exact schedule version before any Phase-O or Lesson-authority mutation. `adjudicate()` and `reassess()` hydrate the durable case, validate the aggregate and refuse a superseded schedule version (`schedule_version_conflict`).
- **P-3 (HIGH)** — provider replay proves full immutable context equality (provider, account digest, event key, payload digest, case, Lesson, schedule version, role, join/leave, observation, provenance). A cross-context replay is a classified conflict, never another case's evidence row.
- **P-4 (HIGH)** — a refused conflicting provider event leaves a durable receipt in the new append-only `dzn_canonical_attendance_conflicts` table plus a `duplicate_event_conflict` anomaly, surfaces through protected review, never overwrites the original evidence row and never touches canonical truth — including after settlement.
- **P-5 (HIGH)** — an exact replay of the original ingest command resumes forward settlement from durable `settlement_pending`; boundaries A–E are covered by executable tests and recovery never duplicates Phase-O truth, Lesson completion or the final Phase-P result.
- **P-6 (HIGH)** — `adjudicate()` requires caller-supplied `expected_case_version`, fails `stale_case_version` before any canonical consequence, and binds the expected version into the command identity. The concurrency matrix now proves **exactly one** winner for competing adjudications.
- **P-7 (HIGH)** — cutover activation must be prospective (`cutover_instant_not_prospective`); applicability is deterministic from the occurrence instant (never "highest database ID"); each admitted case stores the exact immutable policy row and its frozen rule version/threshold/graces; a later policy cannot reinterpret an earlier case; an occurrence before every activated cutover is refused. No production cutover was performed.
- **P-8/P-9** — every duplicate-key recovery path recomputes and compares the expected payload and context (no null-payload replay), and an exact cutover-policy replay is proven to close its transaction (`@@in_transaction`), retain no stale lock and survive a later unrelated rollback.
- **P-10 (MEDIUM)** — the validator and protected read validate `canonical_attendance_cases.rule_version`, the cutover-policy existence/constants/applicability, the identity registry justification of every resolved row, and that the stored assessment decision agrees with the stored evidence; corruption and repair are covered for all of them.
- **Capability repair** — Phase-P grants are installed per capability rather than gated on one grant, a partially installed state is repaired deterministically, and reserved review/adjudication/identity authority is actively withheld from the `dzn_teacher` role.
- **Lock-order correction** — identity-registry and cutover-policy rows are shared authority and are now read without row locks; locking the policy row inside `lockOccurrence` serialised unrelated occurrences and could surface a lock-wait timeout as `occurrence_before_cutover`. Per-occurrence write serialisation is unchanged.
- Validation (owner-executed, disposable WordPress/MariaDB harness): PHP lint (267 files), shell syntax (10 harnesses), **30/30** `tests/*contract*.php`, overlap runtime, authority runtime (including an **8-case identity matrix**, cross-context replay, 5 settlement-convergence boundaries, cutover-policy authority, duplicate-command recovery, case/schedule validation, capability repair, no-policy fail-closed and protected read), a **30-case** corruption runtime, failure injection (3 boundaries + replay convergence + durable payload conflict), migration runtime (fresh Schema 23, 22→23 rehearsal, repeat, partial capability repair, no backfill, no production cutover, provider-neutral storage, 8 malformed-storage cases, retained-023 fail-closed) and a **6-mode** deterministic gated concurrency runner. Adjacent regressions re-run: Phase-O authority/corruption/failure/migration, Phase-N authority/corruption/failure/migration, Phase-M authority/corruption/failure, Phase-M0 authority and protected read, Phase-L authority, and the Migrator `RuntimeException` regression.

- **Original implementation (superseded by the correction above).** Branch `phase-2a2p-canonical-attendance-intake` from authoritative base `7b9aea68fddd651cd614f279f77e78b104885c4d`, Schema 23 / migration `023_canonical_attendance_intake_authority` / build `phase2a2p-canonical-attendance-intake-20260919.1`. Phase P owns provider-neutral attendance evidence intake, human absence/attendance claims, deterministic overlap assessment, anomaly/review authority and administrative adjudication. It does **not** own canonical delivery truth: Phase O remains authoritative and Lesson authority owns completion. Nothing in Phase P writes Phase-O, lifecycle, obligation or replacement storage directly.
- Owner locks implemented: ordinary delivery settles automatically only from trusted provider evidence with ≥1200 seconds of qualifying simultaneous overlap inside `[scheduled start, scheduled end + 15 minutes)`; pre-class grace is zero; open intervals never qualify; a failed assessment identifies no responsibility and produces only anomaly/review state; Student "Notify Absence" is an advance claim only; human claims are evidence and never settle; conflicting evidence requires administrative adjudication; `review_required` is published to Phase O only by explicit adjudication; unresolved cases never block Term closure and survive it; late evidence is retained and flagged but never auto-settles; the slice is prospective only with a durable cutover policy and no historical import; only normalized facts and keyed digests are stored. Automatic settlement converges in stages (durable intent → Phase-O `delivered` → Lesson completion → verification → result) because the existing services own their transactions; no nested transaction is simulated.
- **Deferred and explicitly not owned:** Google/Meet API, OAuth, webhooks or credentials; calendar, WhatsApp, notifications, payment/Stripe, Finance, payroll, renewal; remedial Lesson materialisation or automatic rescheduling; Theme/portal changes; public attendance routes; production cutover and historical attendance import. Student self-service and the Student Portal contract remain deferred.

## Post-Phase-O maintenance — malformed Migrator RuntimeException construction — merged — 2026-09-19

- Bounded maintenance merged into `main` as `08d0d26a94b6f33f20000ca64726f732bf9fa46d` (tree `002ade4586bbb431f44f09891210a36df9da0334`), closing the residual defect reported in the Phase 2A.2-N entry below: the malformed `throw new\RuntimeException(...)` construction in `Migrator` is corrected to `throw new \RuntimeException(...)` at all eleven remaining failure-path sites (`install_enrolment_conversion_authority`, `install_teacher_assignment_foundation`, `verify_enrolment_conversion_schema`, `verify_teacher_assignment_schema`), which previously produced a fatal PHP `Error` instead of the intended controlled rejection.
- Regression coverage: `tests/schema-contract.php` now rejects any malformed construction in the migrator and pins the corrected failure-path counts (1/1/4/5); `tests/migrator-runtimeexception-runtime.php` proves representative install and verify failure paths throw `\RuntimeException`. Schema remains 22, latest migration remains `022_canonical_lesson_delivery_attendance_authority`, build identity is unchanged, no migration 023 exists, and no Platform authority, business policy or Phase-M/N/O semantics changed.

## Phase 2A.2-O — Canonical Lesson Delivery & Attendance Outcome Authority — merged and closed — 2026-09-19

- Final independently approved candidate `f5b43741f4b404fd102330aeb75d58ed8b3e2976`, tree `7182101fcaf9855f8834cd08bc2e7a4a0311791c`, from authoritative pre-merge main `b0687fec98748144f96e3fc56f7e4fb53e01f673`, was **fast-forward merged into `main`**; the implementation-merge SHA equals the approved candidate because no merge commit was required. Schema 22 / migration `022_canonical_lesson_delivery_attendance_authority` / build `phase2a2o-canonical-lesson-delivery-attendance-authority-20260918.1` are authoritative. **No deployment occurred**; production, NIU, Theme, Amelia, Google/calendar, Meta and Stripe were untouched.
- The slice was independently reviewed three times: the original candidate failed on O-1/O-2/O-3, the round-1 correction failed on the O-D8 completion-event lineage plus corruption-coverage and replay findings, and the round-2 correction failed because obligation authority still established canonical completion locally. Correction round 3 replaced that local approximation with direct consumption of `CanonicalLessonAuthorityValidator`, added referenced-lifecycle-event corruption coverage, and received the final independent re-review PASS.
- Locked product decisions O-D1…O-D9 are authoritative: completed means delivered; ordinary delivery needs no routine outcome row; student no-show may coexist with completion and creates no automatic remedy; Teacher/academy non-delivery creates an academy obligation distinct from the Phase-M replacement; advance cancellation stays distinct from post-occurrence non-delivery; corrections remain append-only; Phase-O mutation requires the Phase-O administrator capability; a historical completion may be reconciled to non-delivery through an explicit append-only command while the completion event stays immutable; and controlled advance academy cancellation may establish academy debt while generic Student cancellation does not.
- Phase-M replacement authority is unchanged: two-per-Term allowance, origin uniqueness, `replacement` Lesson materialisation and historical `attested_non_delivery` semantics. Academy obligation remains a distinct immutable debt authority that is never a `replacement` Lesson, never consumes the allowance, is not automatically materialised, scheduled or reopened, and is never erased by Term closure.
- Provider neutrality is preserved: no Google Meet, calendar, Amelia, WhatsApp, payment, payroll, notification or webhook authority, no provider credentials and no remedial-Lesson materialisation.
- Evidence provenance: final independent review independently reproduced Git identity/ancestry, `git diff --check`, 29/29 `tests/*contract*.php`, PHP lint, shell syntax, Schema/migration identity and the Round-3 commit-scope inspection. The Phase-O authority runtime, 44-case corruption runtime, five-boundary failure injection, migration runtime, ten-mode concurrency matrix and the Phase N/M/L/M0 runtime regressions are implementation-owner evidence on a disposable MariaDB/WordPress harness and were not re-executed by the independent reviewer.
- Next Platform action: **none authorised**. The next slice requires an explicit product/architecture decision.

### Phase 2A.2-O correction round 3 — independent re-review findings on round 2 — 2026-09-19

- Independent re-review of round-2 candidate `5b18f20d576e5446cee6cecc0b21d010fb62ffa1` (tree `af035208b7096dad12e60b768e928372fb094778`) returned **FAIL — CORRECTION ROUND 3 REQUIRED**. The round-3 correction is a descendant of that candidate; Schema 22, migration `022_canonical_lesson_delivery_attendance_authority`, the build identity, O-D1…O-D9 and the round-1/round-2 corrections are unchanged.
- **HIGH — canonical lifecycle authority, not a local approximation:** `CanonicalAcademyObligationValidator` no longer establishes canonical completion by scanning raw lifecycle rows (event id, lesson id, `to_state`, maximum id). It now consumes `CanonicalLessonAuthorityValidator::valid()` before accepting obligation lineage, and the delivery validator does the same whenever a reconciliation pointer exists; the delivery service's reconciliation command also consumes it instead of scanning for a maximum completed event id. Completion is bound to the canonical chain's terminal event, which is a consequence of the validated legal progression. `CanonicalLessonAuthorityValidator` references no Phase-O authority, so the reuse is one-way with no cycle, no recursive validation and no lock-order change. The obligation stays subordinate to canonical Lesson/outcome/reconciliation facts.
- **MEDIUM — referenced-event corruption coverage:** added `from_state` corruption, `event_sequence` corruption and an injected structurally noncanonical completed lifecycle row (with an explicit assertion that Lesson authority rejects it), each exercised through `outstandingForTerm()`, `outstandingCountForTerm()` and `outstandingForEnrolment()` with repair proving recovery. The round-2 claim that the referenced event was already proven to be the canonical completed event was an overclaim and is corrected in the contract document and continuity record.
- **Sequencing fix found during verification:** the append-only lifecycle event and (for academy cancellations) the academy obligation are now written before the advance-cancellation guards, inside the same transaction, so the guards validate the exact state the transaction will commit; the guard is also invoked at the service seam before any mutation. Rejections still roll back the event, obligation and lifecycle update together and keep their precise reasons.
- Correction validation: Phase-O contract, authority runtime, 44-case corruption runtime (18 delivery + 26 aggregate obligation classes), five failure-injection boundaries, migration runtime and the 10-mode concurrency matrix pass; Phase F–N regression suites re-run on a fresh empty-schema disposable runtime.

### Phase 2A.2-O correction round 2 — independent re-review findings on round 1 — 2026-09-19

- Independent re-review of round-1 candidate `c63c1327047bd76f3155788874ec6ea6ae7a38e0` (tree `7fa33f0d406e25a403e0f2b5c7d4668923faaaca`) returned **FAIL — CORRECTION ROUND 2 REQUIRED**. The round-2 correction is a descendant of that candidate; Schema 22, migration `022_canonical_lesson_delivery_attendance_authority`, the build identity and O-D1…O-D9 are unchanged, and the round-1 corrections (O-1 capability enforcement, O-2 fail-closed aggregate reads, O-3 corrected counts) are preserved.
- **HIGH — O-D8 completion-event lineage:** `CanonicalAcademyObligationValidator` now validates the obligation's `source_event_id` against the effective outcome's canonical reconciliation lineage. An ordinary non-reconciled non-delivery must carry no completion-event lineage (a stored identifier is an integrity conflict); an O-D8 reconciled completion must name exactly the event the effective outcome supersedes, which must exist in the Lesson's canonical lifecycle, belong to the same Lesson, be a `completed` event, still be that Lesson's latest canonical completed event, and coexist with a `completed` Lesson state. The obligation stays subordinate to the canonical reconciliation truth.
- **MEDIUM — corruption coverage:** added Teacher Assignment identity to the aggregate identity matrix and four O-D8 completion-lineage cases (wrong identifier, cross-Lesson event, missing lineage, wrong lifecycle-event type) plus the ordinary non-reconciled spurious-lineage case, each exercised through `outstandingForTerm()`, `outstandingCountForTerm()` and `outstandingForEnrolment()` with repair proving recovery. The round-1 documentation overclaim that aggregate corruption coverage already included complete source event lineage and Teacher Assignment is corrected.
- **LOW — idempotent replay:** `owe()` no longer returns an existing same-kind obligation before validating the supplied authority. Exact legitimate replay (same canonical source identifiers and identical immutable evidence) remains idempotent with no duplicate row, while conflicting replay intent now fails closed with `obligation_replay_conflict`; the existing obligation's canonical aggregate is revalidated before an idempotent success is reported. `UNIQUE(source_lesson_id)` and concurrency semantics are unchanged.
- Correction validation: Phase-O contract, authority runtime, 41-case corruption runtime (18 delivery + 23 aggregate obligation classes), five failure-injection boundaries, migration runtime and the 10-mode concurrency matrix pass; Phase F–N regression suites re-run on a fresh empty-schema disposable runtime.

### Phase 2A.2-O correction round 1 — independent-review findings O-1/O-2/O-3 — 2026-09-19

- Final independent review of candidate `4f8af9067aa1ed463916d86b32a04f4193f44d23` (tree `8a79719e39496476debef4c67971648ab3447d57`) returned **FAIL — CORRECTION REQUIRED**. The correction is a descendant of that reviewed candidate; Schema 22, migration `022_canonical_lesson_delivery_attendance_authority`, the build identity and every locked product decision are unchanged.
- **O-1 (HIGH)**: `CanonicalAcademyObligationService::owe()` is the public state-mutating academy-obligation seam and now enforces `dzn_manage_canonical_lesson_delivery` itself as its first statement, before any source lookup, validation or mutation. An unauthorized principal fails closed with `Unauthorized`, creates no obligation row and leaves no partial authority; the authorized delivery and advance-cancellation paths are unchanged and remain atomic inside their caller's transaction. No capability was broadened and no new public authority was added.
- **O-2 (HIGH)**: `outstandingForTerm()`, `outstandingCountForTerm()` and `outstandingForEnrolment()` now hydrate every selected obligation against its canonical source Lesson and validate it through `CanonicalAcademyObligationValidator`, which additionally requires the stored Term/Enrolment/Student/Course/Teacher/Assignment selectors to agree with the source authority, validates source outcome/event lineage, schedule-version and occurrence anchors, actor, classification and evidence, and requires the obligation evidence to be identical to the evidence of the source fact. Any invalid row fails the whole aggregate closed; nothing is silently omitted, and the Term count is derived from the same validated aggregate rather than a raw SQL count (the raw count seam was removed). Term and Enrolment selectors select the union of rows claiming the selector and rows whose source Lesson belongs to it, so a corrupted stored selector is validated instead of disappearing.
- **O-3 (LOW)**: the "36 static/source contract tests" claim was a miscount. Corrected to 29 files matching `tests/*contract*.php`, with `tests/static.php` PHP lint and `sh -n` shell syntax checks reported separately as syntax checks.
- Owner-suite robustness: the Phase-O runtime, corruption, failure and concurrency harnesses now choose synthetic occurrence leads that keep each interval inside one UTC day (the provisioned full-day availability rules meet at midnight, where a genuine one-second coverage gap exists) and settle pooled occurrences with one shared wait, making the suites independent of the wall-clock time they are run at.
- Correction validation: Phase-O contract, authority runtime (including the O-1 negative authorization test that invokes the obligation write seam directly as a subscriber), 35-case corruption runtime (18 delivery + 17 aggregate obligation classes), five failure-injection boundaries, migration runtime and the 10-mode concurrency matrix all pass; Phase F–N regression suites re-run unchanged on a fresh empty-schema disposable runtime.

## Phase 2A.2-O — Canonical Lesson Delivery & Attendance Outcome Authority — implementation candidate, unmerged — 2026-09-18

- Candidate branch `phase-2a2o-canonical-lesson-delivery-attendance-authority` from authoritative post-N main `b0687fec98748144f96e3fc56f7e4fb53e01f673`. Schema 22 / migration `022_canonical_lesson_delivery_attendance_authority` / build `phase2a2o-canonical-lesson-delivery-attendance-authority-20260918.1`. **Not merged and not deployed.**
- Locked product decisions: O-D1 `completed` means delivered; O-D2 exception-based recording (ordinary delivery needs no attendance row); O-D3 student no-show consumes the Lesson with no entitlement; O-D4 Teacher/academy non-delivery owes an academy-funded occurrence that never consumes the Student's allowance; O-D5 cancellation stays pre-occurrence while post-occurrence non-delivery is a delivery fact; O-D6 corrections supersede append-only history without reopening lifecycle; O-D7 administrator recording is immediate, idempotent and needs no second approval; O-D8 an explicit authorised append-only command reconciles a historical completion into Teacher/academy non-delivery while the immutable completion event is preserved; O-D9 an advance Teacher/academy cancellation establishes an academy obligation while a Student-requested cancellation never does.
- Adds `dzn_canonical_lesson_delivery_outcomes` (append-only outcome history, one applicable outcome per Lesson, immutable evidence provenance bound to the exact canonical schedule version, occurrence start/end and any reconciled completion event), `dzn_canonical_lesson_delivery_commands` (digest-only command evidence) and `dzn_canonical_academy_obligations` (immutable academy-owed occurrences, `UNIQUE(source_lesson_id)`, independent of Term lifecycle).
- Outcome vocabulary: `delivered`, `student_no_show`, `interruption`, `review_required` and `teacher_non_delivery`; delivery/attendance/remedy classifications are derived from the outcome code and validated on every read, so a caller cannot choose a favourable classification. Only `teacher_non_delivery` and `review_required` block completion.
- Temporal rule: a final delivery/no-show/non-delivery fact becomes recordable only after the governing occurrence has ended; the Teacher capacity buffer is not the waiting period.
- Cross-phase: Lesson completion refuses a known non-delivery or unresolved occurrence; the Phase-M replacement-eligible cancellation reason and the Phase-O `academy_unavailable` reason are both refused after the recorded occurrence start or when an outcome already exists; canonical scheduling refuses a Lesson with a recorded outcome. The Phase-M replacement cap, eligibility rule and Lesson classification are unchanged, historical `attested_non_delivery` records are never reinterpreted, and academy debt is never materialised as a `replacement` Lesson.
- Provider-neutral: evidence is a controlled channel plus an opaque keyed digest with observed time and actor; no provider call, webhook, credential, Google/Amelia coupling or finance consequence is introduced. Deferred: Google Meet evidence ingestion, signed public links, portals, notifications, payability, partial delivery and retention.
- Validation on disposable MariaDB 11.4.13: 29 static/source contract tests plus PHP lint and shell syntax checks, authority runtime, 18-class corruption runtime, five failure-injection boundaries, migration runtime (fresh Schema 22, exact 21→22 rehearsal, repeat safety, capability repair, legacy preservation, no backfill, six malformed-storage cases, retained-022 fail-closed) and a 10-mode deterministic gated concurrency matrix. Phase F–N regression suites re-run. No deployment, production, Theme/NIU, Amelia or external-system change occurred.

## Phase 2A.2-N — Canonical Lesson Scheduling & Teacher Capacity Authority — merged and closed — 2026-09-18

- Candidate branch `phase-2a2n-canonical-lesson-schedule-authority` from authoritative post-M main `f90c41e6d9e129d7d9e1a243941d4655fadc6b8e`, tree `15c257aedf6d2d18afc5c1ba4991d7c468f1195c`. Schema 21 / migration `021_canonical_lesson_schedule_authority` / build `phase2a2n-canonical-lesson-schedule-authority-20260917.1`.
- A scheduled canonical Lesson is a canonical Lesson in `authorised` state plus exactly one applicable canonical schedule version. Phase N adds no `scheduled` Lesson lifecycle state and does not mutate Phase-M Lesson lifecycle as a side effect.
- Adds `dzn_teacher_schedule_roots` (serialization anchor only), `dzn_canonical_lesson_schedule_versions` (immutable interval assertions with one applicable version per Lesson), `dzn_canonical_lesson_schedule_events` (append-only `scheduled`/`rescheduled`/`released` history) and `dzn_canonical_lesson_schedule_commands` (digest-only command evidence).
- Teacher capacity is one concurrent canonical Lesson per Teacher over the half-open occupied interval `[start, end + buffer)`. Occupancy is derived from applicable versions; there is no mutable reservation projection and no capacity counter. Duration and buffer are frozen on every version from the canonical Course policy, with an audited duration override only.
- Scheduling requires a future start, an explicit IANA timezone, a current canonical Enrolment and Term, and the Lesson's recorded Assignment still applicable. Release is permitted while the Enrolment is current, paused or closed. Availability is consumed as an upstream constraint with a capability-controlled, fully audited administrative override that bypasses availability only.
- Serialization uses a per-Teacher root acquired after the canonical Enrolment/Lesson/Assignment context and before the capacity decision; `READ COMMITTED` disables gap locking, so the root is required to prevent first-ever overlapping reservations. No phase may acquire a Teacher scheduling root and then an earlier Enrolment identity-root chain.
- Cross-phase guards: Lesson completion/cancellation, Enrolment closure, Term close/cancel, Teacher Assignment replacement and Teacher archival all reject `active_future_schedule_exists` rather than cascading or silently releasing authority.
- Legacy isolation: `LessonScheduleService` and `lesson_schedule_versions` remain legacy-only, there is no backfill or dual-read, and the canonical validator fails closed on legacy scheduling contamination of a canonical Lesson.
- Validation: 30 static/source contract tests; Phase-N authority, corruption (30 version/event, 29 command, released-state), failure-injection (11 boundaries), migration (fresh, rehearsed 20→21, repeat, capability repair, root backfill) and a 26-mode deterministic gated concurrency matrix on disposable MariaDB 11.4.13. No deployment, production, Theme/NIU or Amelia change occurred.

### Phase 2A.2-N correction round 1 — independent-review HIGH finding — 2026-09-18

- Correction branch `phase-2a2n-migration-verification-correction1`, a descendant of the reviewed candidate `503a96fb4bf14964117b5e2bb2c292dda848e912` (tree `0bf581ce40bb42c765085e818d789d74249f87e8`). Schema 21 / migration `021_canonical_lesson_schedule_authority` / build `phase2a2n-canonical-lesson-schedule-authority-20260917.1` and the locked Phase-N product and authority model are unchanged. The correction is uncommitted and unreviewed.
- HIGH finding: `Migrator::verify_canonical_lesson_schedule_authority_schema()` existed but was never invoked, so Schema 21 could activate — and stay current — without verifying its own concurrency-critical storage. The verifier is now wired into the two paths every earlier phase already uses: the per-migration loop records `021_canonical_lesson_schedule_authority` only after the Phase-N verifier passes, and `verify_current_schema()` (the path taken whenever the schema option already reads 21) now ends with the same verifier.
- Defect exposed by that wiring: the Phase-N verifier's rejection paths were written `throw new\RuntimeException(...)`, which PHP lexes as the qualified name `new\RuntimeException` and executes as a call to an undefined function, so corrupt Phase-N storage produced a fatal `Error` instead of a controlled rejection. The Phase-N verifier now throws `new \RuntimeException(...)` and fails closed. The same form remains in earlier verifiers (`Migrator.php` lines 167, 173, 204, 209, 210 and 211); it is deliberately left unchanged here and reported for a separate bounded correction.
- Regression coverage: `tests/phase-2a2n-migration-runtime.php` gains a malformed-storage section that damages authoritative Phase-N storage six ways (dropped `lesson_applicable` index, dropped `teacher_occupancy` index, dropped `teacher` root index, an added mutable `updated_at` on append-only evidence, a nullable `command_key_digest`, and a non-transactional storage engine) and proves each damaged state is rejected by `Migrator::maybe_upgrade()` and that repaired storage returns to Schema 21. `tests/phase-2a2n-contract.php` now asserts both call sites plus the `RuntimeException` failure form, so the wiring and the fail-closed error class cannot silently regress.
- Validation: 28/28 static/source contract tests; Phase-N contract, authority, corruption (30 version/event, 29 command, released-state), failure-injection and migration suites on disposable WordPress 6.8.3 / PHP 8.3 / MariaDB 11.4.13; Phase M0, M and L runtimes as adjacent-phase regression. Negative control: with the wiring removed, the new malformed-storage section fails with `Malformed canonical scheduling storage was accepted: dropped applicable-slot index` and the strengthened contract fails. No main, production, NIU, Theme, Amelia, Hamnavaz or external-system change occurred; nothing was merged, deployed, committed or pushed.

### Phase 2A.2-N correction round 2 — independent-review HIGH finding — 2026-09-18

- Correction branch `phase-2a2n-migration-verification-correction2`, a descendant of the correction-round-1 candidate `4a1741af1af622936e45b1f1d6f7419fcfcc3b43` (tree `e067496bd5ce8dca64573c794dd0cf8dc5ddaf8d`). Schema 21 / migration `021_canonical_lesson_schedule_authority` / build `phase2a2n-canonical-lesson-schedule-authority-20260917.1` and the locked Phase-N product and authority model are unchanged. The correction is uncommitted and unreviewed.
- HIGH finding: when the completed-migration ledger already recorded `021_canonical_lesson_schedule_authority` but the schema option still read 20 (021 recorded, then execution stopped before the option advanced), `Migrator::maybe_upgrade()` skipped 021 and advanced the schema option to 21 without invoking `verify_canonical_lesson_schedule_authority_schema()`. `maybe_upgrade()` now invokes the Phase-N verifier unconditionally immediately before it writes the schema option, so a retained-021/stale-schema-version activation is verified fail-closed. The verifier call after migration 021 and the `verify_current_schema()` call are unchanged.
- Regression coverage: `tests/phase-2a2n-migration-runtime.php` gains a retained-021 case that keeps the completed marker, sets the schema option to 20, drops the `lesson_applicable` index, proves `Migrator::maybe_upgrade()` rejects with `Migration verification failed` and leaves the schema option at 20, then repairs the index and proves recovery to Schema 21 exactly once. `tests/phase-2a2n-contract.php` now asserts the pre-activation verifier call site is adjacent to the schema-option write, so it cannot silently disappear.
- Negative control: with the pre-activation verifier call removed, the new retained-021 case fails with `Retained-021/stale-schema-version activation accepted damaged Phase-N storage` and the strengthened contract fails with `Phase N storage must be verified before the schema option is advanced to Schema 21`.
- Validation: 27/27 static/source contract tests; Phase-N authority, corruption (30 version/event, 29 command, released-state), failure-injection (11 boundaries) and migration (fresh Schema 21, rehearsed 20→21, repeat, capability repair, Teacher-root backfill, six malformed-storage states, retained-021 pre-activation) suites on disposable WordPress 6.8.3 / PHP 8.3 / MariaDB 11.4.13; Phase M0, M and L runtimes as adjacent-phase Migrator regression. Concurrency was not re-run: the correction touches migration verification only and does not change scheduling, capacity, lifecycle or authority semantics. No main, production, NIU, Theme, Amelia, Hamnavaz or external-system change occurred; nothing was merged, deployed, committed or pushed.

### Phase 2A.2-N final review and merge closeout — 2026-09-18

- Final independently approved candidate `9f92ada6ba82d809c1515566fb426c90a5a68e57`, tree `ec29baabf2d737d0eecd72d857a152b8eba4b163`. Focused independent re-review Round 2 returned no findings and PASS — MERGE READY.
- Merged without squash or rebase from pre-N main `f90c41e6d9e129d7d9e1a243941d4655fadc6b8e` as `08138270f4bd32e5829ef0f5a18a316f6780607d`; the merge tree exactly matches the approved candidate tree.
- Schema 21 / migration `021_canonical_lesson_schedule_authority` / build `phase2a2n-canonical-lesson-schedule-authority-20260917.1` are authoritative. No deployment, production, Theme/NIU, Amelia or external-system changes occurred.

## Phase 2A.2-M — Canonical Lesson Authority — merged and closed — 2026-09-17

- Correction branch `phase-2a2m-canonical-lesson-authority-correction1` created directly from the failed reviewed candidate `1d723e0d7b5ef73db7bc6c24683a73c62684432c`, tree `cb577b7ece33d8533b2e47dacd4b3894566376ec`. Schema 20 / migration `020_canonical_lesson_authority` / build `phase2a2m-canonical-lesson-authority-20260917.1` are unchanged; this round corrects review findings without changing the locked Phase-M product or authority model.
- Finding 1: `CanonicalLessonAuthorityValidator::valid()` is now the single canonical aggregate hydration and integrity gate. It validates the Lesson↔Term enrolment link, the Lesson↔Enrolment Student and Course identity, that the recorded Teacher Assignment structurally belongs to the Lesson enrolment with its immutable Teacher, and complete replacement-origin lineage, including controlled replacement-eligible non-delivery evidence. A historical Lesson is never required to keep a current Teacher Assignment.
- Finding 2: idempotent replay revalidates every recorded command intent (domain, operation, enrolment, Term, expected Teacher Assignment, expected Lesson, expected from-state, replacement origin, result Lesson, result state) against the operation's facts, revalidates the result aggregate through the canonical validator, and requires the durable lifecycle evidence the command recorded. A corrupted command, result or history can no longer replay as success.
- Finding 3: a committed deterministic process-level runner (`tests/phase-2a2m-concurrency-runner.sh`) now coordinates setup, gated worker start, lock-wait observation, controlled release, worker completion and final database verification, and consumes and asserts every worker artefact. It covers same-key standard issuance, the final standard allocation boundary, same-key replacement, same-origin replacement, the final replacement cap, Lesson versus Enrolment pause and close in both orders (with a close-first fixture that genuinely permits closure), Lesson versus Term close and cancel in both orders, Lesson versus Teacher Assignment replacement in both orders, and unrelated-root independence proved by completing one worker while the other stays gated.
- Finding 4: failure injection now spans standard creation, replacement creation (including origin claim release), completion, cancellation, lifecycle/history evidence writes and command evidence writes, with table-driven proof that each rollback leaves no partial aggregate, no orphan evidence and no falsely replayable command.
- Residual defect corrected: the stale finite schema/build enumerations in `tests/phase-2a2h-contract.php` and `tests/phase-2a2l-runtime.php` were replaced with the repository's established minimum-schema plus canonical-build-identity pattern used by the earlier Phase C–F contracts; both were failing on the reviewed candidate itself.
- Validation: all 29 static/source contract tests pass; Phase F, G, I, J, L, M0 and M runtime suites pass on disposable MariaDB 11.4.13 baselines, including a genuine Phase-H Schema-14 fixture database upgraded to Schema 20; the 16-mode gated concurrency matrix passes deterministically.
- Independently approved candidate `f44b502509f5dcb9b5d0281cfda4329407abd3bf`, tree `85a8309bb6179a2061c86245ea1ad9a975c00c2e`, was merged without squash or rebase from pre-M main `c83c1f99557b2cd1f2f81aa82a05177887a468e6` as `ed11086ad8ddc65899c3b855248611b1eb9e09a4`; its implementation merge tree exactly matches the approved tree. Schema 20 / `020_canonical_lesson_authority` and build `phase2a2m-canonical-lesson-authority-20260917.1` are authoritative.
- Phase M is **COMPLETE / INDEPENDENTLY REVIEWED / MERGED / CLOSED**. No deployment, production access, Theme/NIU, Amelia or external-system change occurred. Phase N has not started.

## Phase 2A.2-M0 — Canonical Enrolment Lifecycle Authority — merged and closed — 2026-09-17

- Merged independently approved candidate `66ba811c47a3494f12f47dbed03775ca7c4e5ba0`, tree `6a9631505a86893abf1fcefa89a78675e7ed3901`, from pre-M0 main `8490d712d18116b3ca606d6c2e3dbb8560d2ce54` as merge commit `c316a5153c7a56b810732495b3785786771c695a`; the implementation merge tree exactly matches the approved tree.
- Schema 19 / migration `019_canonical_enrolment_lifecycle_authority` and build `phase2a2m0-enrolment-lifecycle-authority-20260917.1` are authoritative.
- Independent review passed explicit lifecycle, replay/conflict, protected-read integrity, subordinate closure guards and concurrency boundaries after two focused correction rounds.
- Canonical Lesson authority remains non-authoritative. No deployment, production, Theme/NIU or Amelia change occurred.

## Phase 2A.2-L — Canonical Term Creation & Lifecycle Authority — merged and closed — 2026-09-16

- Merged independently approved candidate `26beab6147df545fed70949f06b83d83042184cd`, tree `b283c319b9a3af3267cf43ad9591e30d93085986`, from pre-L main `788bf0989f4365607ba41322471e50fca75a5e81` as merge commit `36e1d6b754079efcf6fdff02ed2a029099071455`; merge tree exactly matches the approved tree.
- Schema 18 / migration `018_canonical_term_authority` and build `phase2a2l-canonical-term-authority-20260916.1` are authoritative.
- Independent review passed creation, lifecycle, replay/conflict/corruption, failure injection and complete process-level concurrency validation, including stale close/create and cancel/create arbitration.
- No Lesson, Teacher, payment, scheduling, Amelia, Theme, NIU, deployment or production authority changed.

## Post-Phase 2A.2-K continuity normalisation — 2026-09-16

- Reconciled current-state architecture, data-model, migration, product-decision, module-boundary and continuity guidance with authoritative Schema 17 while preserving phase-specific historical provenance.
- Recorded the dual-owner/reciprocal-review execution model, bounded delivery cadence, Phase-L boundary, and current Theme homepage/random-article handoff facts.
- Documentation only: no application source, migration, schema, build, Theme runtime, deployment or production authority changed.

## Phase 2A.2-K — Canonical Term Foundation — merged and closed — 2026-09-16

- Merged independently approved candidate `a6491ccc1cf624be205d1ea022421a5f7903eb2b`, tree `9a59062c2a6aa10c95e88e4d24909c6e770dd27d`, from pre-K main `cf222d20e4ab0d08c992e7ec38bcece8b7625d9e` as merge commit `25e213c69d7f299c9ed5330eed7cd9ba4051c022`.
- Prepared Schema 17 / migration `017_canonical_term_foundation` and build `phase2a2k-canonical-term-foundation-20260916.1` from authoritative Schema 16 main `cf222d20e4ab0d08c992e7ec38bcece8b7625d9e`.
- Additively classifies existing Terms as `legacy_phase1` without translating status, payment, allocation, dates or archive state, and reserves `canonical_enrolment_term_v1` for the canonical foundation.
- Adds canonical `authorised`, `current`, `closed`, and `cancelled` lifecycle storage, one-applicable-Term-per-Enrolment database arbitration, and append-only digest-only lifecycle evidence.
- Adds capability-protected integrity classification and privacy-minimised canonical Term/history reads. Canonical Terms contain no Teacher identity and do not treat the legacy `payment_state` as authority.
- Preserves the legacy Term/Enrolment/Lesson path while closing generic canonical insertion and legacy archive/restore mutation of canonical Terms at both service and repository boundaries.
- Adds source, migration, legacy-preservation, history-integrity, uniqueness, capability-repair and write-boundary coverage. No ordinary canonical Term creator, lifecycle command, idempotency command table, Lesson, scheduling, payment or external-system authority is included.
- Independent correction ensures a closed canonical Enrolment cannot expose an authorised/current applicable Term while preserving valid terminal history. Independent re-review passed.
- Phase 2A.2-K is **COMPLETE / INDEPENDENTLY REVIEWED / MERGED / CLOSED**. No deployment occurred.

## Phase 2A.2-J — Teacher Assignment Foundation — merged and closed — 2026-09-16

- Merged independently reviewed candidate `b1fd5aebf47a0bce33f74ab56b53c5334d719bf4`, tree `5766e126a5abd7e9160e6c00dc96b6837f49b05e`, from pre-J main `7bd4430737f460fdb995bbe05dea272b55641294` as merge commit `763a9fa9f792cd45114e10978a1bda0a53662c21`.
- Review correction round 1 replaces broad duplicate-text recovery with named-index arbitration and complete operation-specific result proof. Terminal rollback cannot report success unless exact terminal state and evidence already exist through the ordinary locked convergence path.
- Added exhaustive write-boundary rollback and persisted-corruption runtime suites, plus the full competing-operation process matrix with lock attribution. Shared ascending Teacher locks retain archival exclusion and principal-offboarding serialization without serializing unrelated Enrolments that share a Teacher.
- Locked J-5 principal offboarding independence and added six deterministic real-service race modes covering initial, staff-attested replacement and authenticated-Teacher replacement in both commit orders. Principal offboarding revokes login/onboarding authority without becoming Teacher archival or invalidating otherwise valid teaching Assignment authority.
- Updated backward-compatible Phase C–F and earlier historical contracts to use minimum-schema and semantic invariants instead of stale build strings, finite schema enumerations, whitespace, or explanatory prose matches.
- Added Schema 16 / migration `016_teacher_assignment_foundation` and build `phase2a2j-teacher-assignment-foundation-20260915.1`.
- Added a first-class Teacher Assignment aggregate, one-applicable-row database invariant, ordered replacement lineage, append-only lifecycle evidence, and immutable digest-only command evidence.
- Preserved `enrolments.teacher_id` as historical final-arrangement context. Zero Assignment is valid; there is no backfill and no Enrolment lifecycle mutation.
- Initial Assignment revalidates exact retained final-arrangement/Availability Assent provenance. Replacement requires new assignment-specific authenticated-Teacher acceptance or authorised staff attestation and an expected-current Assignment ID.
- Added privacy-minimised readiness/current reads, atomic initial/replacement/end/cancel commands, replay/conflict/already-applied handling, deterministic Enrolment → ascending Teacher → Assignment locking, database arbitration, and transactional Teacher offboarding protection.
- Added source, Schema 15 → 16 migration, isolated runtime, rollback, uniqueness, capability lifecycle, and attributed process-level concurrency coverage.
- Independent review originally found one HIGH and three MEDIUM findings. Correction round 1 closed the HIGH plus failure/corruption and regression gaps; J-5 then clarified the remaining principal-offboarding concurrency ambiguity.
- The final candidate added deterministic offboarding coverage without production-source changes and passed the final independent check. MariaDB lacked MySQL-specific `data_locks` / `data_lock_waits` attribution tables; this was documented and no runtime success was inferred from Ina's execution.
- Phase 2A.2-J is **COMPLETE / INDEPENDENTLY REVIEWED / MERGED / CLOSED**. No deployment occurred and no Term, Lesson, schedule, capacity, payment, notification, calendar, Amelia, Hamnavaz, CRM, Theme, NIU, or production authority was introduced.

## Phase 2A.2-I — Enrolment Conversion Authority — merged and closed — 2026-09-15

- Merged final independently reviewed candidate `4e40c5fa7665a8f96fcbf19def2cac719e7bc38b` from authoritative pre-I main `f855103c6449ad466ff267c7a9740b56c9ffed66` as merge commit `d0bfbe1b808e60e8bcffae0da6c16a4fda1dc928`.
- Added Schema 15 / migration `015_enrolment_conversion_authority` and build `phase2a2i-enrolment-conversion-authority-20260914.1`.
- Added distinct capability-protected read-only readiness and explicit conversion command boundaries, a concrete Student + Course serialization root, digest-only idempotency evidence, a dedicated canonical writer, initial lifecycle evidence, and unambiguous closure-event predecessor selection.
- Conversion retains the final PII-free Accepted Service Arrangement after privacy erasure, copies the frozen Teacher only as historical context, and stops at an `authorised` canonical Enrolment without downstream operational authority.
- Added clean Schema 14 → 15, isolated rollback/uniqueness, and deterministic held-lock concurrency evidence. No Theme, NIU, Amelia, Hamnavaz, CRM or production system was touched.
- Independent-review corrections add frozen identity/capacity evidence revalidation, complete lifecycle-chain validation, contamination-safe replay, all nine readiness outcomes, explicit stale-readiness rejection, dual-source Race A convergence gates, and direct Phase I capability removal/repair coverage.
- Replay and already-converted semantics remain valid through legitimate `authorised`, `current`, `paused` and `closed` lifecycle progression while malformed history or state/applicability combinations fail closed.
- Phase 2A.2-I is **COMPLETE / MERGED / CLOSED**. Teacher Assignment remains future work and was not started. No deployment occurred; Theme, NIU, Amelia, Hamnavaz, CRM and production were untouched.

## Phase 2A.2-H — Canonical Enrolment Foundation — 2026-09-14

- Merged independently reviewed candidate `6ae121d0a96b8bd034663992f4ee5ee0812a9893` from authoritative pre-H main `aa626e89c66a393977caf002355589990aec03df` as merge commit `672e5334fe9f37fff5f872cf3fdad18af5ab50d5`.
- Added Schema 14 / migration `014_canonical_enrolment_foundation` and build `phase2a2h-canonical-enrolment-foundation-20260913.1`.
- Preserved Phase 1 Enrolments as explicit legacy history and added Student + Course canonical identity, immutable Accepted Service Arrangement provenance, applicable-row uniqueness, reserved lifecycle/lineage structure, and append-only lifecycle evidence.
- Record models are `legacy_phase1` and `canonical_student_course_v1`; the reserved canonical lifecycle is `authorised → current → paused → closed`.
- Added protected six-outcome applicability inspection, preserved constrained legacy/bootstrap compatibility, and closed generic/manual canonical creation, legacy archive/restore, and Phase 1 Term/Lesson/Teacher authority paths for canonical records.
- Independent-review findings HIGH-1, HIGH-2, HIGH-3 and MEDIUM-1 were corrected; final independent re-review result: **PASS — MERGE READY**. Phase 2A.2-H is complete, independently reviewed and merged.
- No Accepted Service Arrangement → Enrolment conversion, Teacher Assignment, canonical Term/Lesson authority, deployment or external-system authority was added. Conversion readiness and explicit conversion authority remain separate future work.
- No deployment occurred; Theme, NIU, Amelia, Hamnavaz and production were untouched.

## Phase 2A.2-G — Final Acceptance + Accepted Service Arrangement Foundation — 2026-09-13

- Merged reviewed candidate `701f62595327ba464d81299b1832ba7825eddc4e` from pre-G main `0607be0ce6f2dcd32dc60a4d8ff6c76d0f4012c8` as merge commit `3a33bafd5943b15b139bbe40cb198cbb201cc943`.
- Added Schema 13 / migration `013_final_acceptance_arrangement_foundation` and build `phase2a2g-final-acceptance-arrangement-20260910.1`.
- Added authority-revalidated final acceptance, immutable PII-safe Accepted Service Arrangements, atomic sibling Option outcomes, Proposal finality and digest-only idempotency.
- Production authority-lock candidate `d96378d7f16a2ff1c90ec1fc99273361f3630808` passed source review; `701f62595327ba464d81299b1832ba7825eddc4e` added deterministic contention evidence and semantic contract hardening only.
- Independent review result: **PASS — MERGE READY**. Deterministic authority contention evidence was accepted.
- No deployment occurred. Conversion, Enrolment creation, Teacher Assignment, Term/Lesson generation, payment, notification, scheduling, calendar and Amelia authority remain excluded. Phase 2A.2-H Enrolment foundation is next.

## 0.1.0 — 2026-09-09

Phase 2A.2-F merge main: `c578f137ed537276524a460a9bb4771ec6fbcc4c`

Schema 12 / migrations 001–012. Build identity: `phase2a2f-student-identity-acceptance-authority-20260909.1`.

### Phase 2A.2-F — Student Identity & Acceptance Authority Foundation

- Merged Schema 12 / migration `012_student_identity_acceptance_authority` from approved candidate `f7b8066ce6f45c6bee461cdb13cc2614283988bd` through PR #15 as merge commit `c578f137ed537276524a460a9bb4771ec6fbcc4c`.
- Added human-reviewed, append-only Booking Request identity resolution and Student capacity classification records, versioned Student principal-link provenance, and effective/revocable guardian representative grants.
- Added protected internal administration and an informational authority-read service. No final acceptance, conversion, public workflow, calendar, payment, notification, or Amelia authority was added.
- Added atomic Student-principal supersession, a common authority lock order, strict guardian intervals with append-preserving expiry, real Schema 11 → 12 migration evidence, and an executable deterministic race harness.
- Confirmed derived adult-self authority and `guardian_representative` / `service_acceptance` authority; adult delegation remains unavailable in V1, and eligibility reads are informational non-bearer results.
- Preserved the Booking Request privacy-erasure boundary and recorded attributed R1–R11 concurrency evidence.
- Completed foundation chain: 2A.2-A Booking Request assessment; 2A.2-B Coordination Case/Candidate Teacher; 2A.2-C Teacher Availability Assent; 2A.2-D Proposal Foundation; 2A.2-E Provisional Acceptance Evidence; 2A.2-F Student Identity & Acceptance Authority Foundation.
- Deliberate exclusions remain: no automatic PII matching, public identity workflow, adult delegation, final acceptance, Accepted Service Arrangement, Booking Request conversion, Enrolment, Teacher Assignment, Term, Lesson, payment, notification, calendar or Amelia authority.
- **BEFORE PHASE 2A.2-G / FINAL ACCEPTANCE-CONVERSION WORK: HAMNAVAZ DOMAIN / PLATFORM RECONCILIATION REQUIRED.**

### Phase 2A.2-E — Provisional Acceptance Evidence

- Merged Schema 11 / migration `011_provisional_acceptance_evidence` from reviewed candidate `740a29dbe3fce081ba0fd19d6b6259cb4d293134` through PR #14.
- Added append-only `accepted_pending_conditions` evidence with `authority_unresolved`; no final acceptance, Student/guardian authority, Accepted Service Arrangement, conversion or downstream operational authority.

### Phase 2A.2-D — Proposal Foundation

- Added Schema 10 / migration 010 for canonical Proposal Families, Teacher-specific Options, and immutable Versions.
- Added Assent-authorised initial and replacement issuance with Proposal-scoped idempotency and guarded pointer advancement.
- Added a dedicated protected coordination capability and internal issuance surface; no public endpoint or acceptance authority.
- Added source, migration, isolated-runtime, revision-race, and Assent-invalidation-race test coverage.
- Validated candidate `5b67442218baeba94b68d988f31f51e09ae1a58b` passed with non-blocking limitations and was merged through PR #13.

## Historical development record

### Phase 2A.0 — Principal invitation runtime-validation preparation

- Added an isolated-only, reversible WordPress/MySQL validation matrix and a
  WP-CLI delivery-preparation assertion helper. The helper is not packaged,
  creates no accounts, emits no raw secret, and requires an explicit
  non-production database marker.
- Stamped the clean runtime-validation package identity for the approved
  schema-version-3 Phase 2A.0 head. No deployment, provider send, Amelia
  change, or authority cutover is included.

### Phase 1 — Core Foundation & Controlled Runtime Validation

- Implemented the Phase 1 canonical schema, migration verification/locking,
  capabilities, Core identities, relationship validation, Lesson schedule
  history, archive safeguards, Operational Exceptions, and minimal protected
  engineering admin surfaces.
- Added a controlled Phase 1F beta validation runbook and clean-package
  preparation. Runtime validation remains pending; no business authority,
  Amelia dependency, provider integration, or Phase 2 work has begun.

### Product decision reconciliation — 3 September 2026

- Confirmed permanent Core Teacher and Student identities; contact details and
  provider IDs are attributes/mappings, and suspected duplicates never
  auto-merge.
- Confirmed separate first-class Instrument, Course, Enrolment, Term, and Lesson
  concepts, with Enrolment as the continuing relationship and Term as its
  bounded allocation/payment/renewal cycle.
- Confirmed that normal rescheduling preserves Lesson identity and audited
  schedule history; genuine replacement/make-up occurrences may be separately
  linked Lessons.
- Confirmed UTC canonical Lesson instants, explicit IANA timezones, retained
  recurring wall-clock intent, and separate Gregorian/Persian calendar
  presentation.
- Confirmed numeric internal keys plus immutable opaque ULID-style UIDs,
  approved `DZN-*` reference prefixes, and independent public-action
  capabilities.
- Confirmed explicit, administrator-authorized, audited, one-to-one optional
  Core Teacher ↔ Hamnavaz Profile links.
- Confirmed soft archive/default retention, separately authorized deletion or
  anonymization, and purpose-limited provider provenance.
- Confirmed effective-dated teacher rates and a teacher-rate/currency snapshot
  per Lesson, independent from Student pricing, as Platform Phase 9 Finance
  architecture rather than Phase 1 persistence.
- Confirmed the approved initial lifecycle vocabulary, Phase 1 Course fields,
  ULID-style UIDs/reference prefixes, custom-table direction, append-only Lesson
  Schedule Versions, introductory-Lesson relationship rules, Term replacement
  defaults, timezone onboarding, and Operational Exception framework.
- Corrected the initial Lesson vocabulary to `introductory`, `standard`, and
  `replacement`, and confirmed direct Lesson Student/Teacher/Course identity
  snapshots with creation-time Enrolment consistency validation.
- Removed premature Finance persistence from the proposed Phase 1 foundation;
  Finance tables, rate snapshots, and audited corrections remain Phase 9 work.
- Rejected the Phase 0 proposal for an automated Amelia importer, shadow
  synchronizer, parity engine, and repeatable mapping/checkpoint pipeline. The
  small active dataset will be recreated manually; historical Amelia data may
  be retained outside operational Core as a read-only archive.
- Renamed canonical Platform Phase 2 to **Core Data Setup & Cutover
  Preparation** without changing Phase 0–10 numbering.
- Preserved the runtime strangler strategy, authority ledger, bounded cutovers,
  rollback, and exit gates while prohibiting new Amelia data-model dependencies
  in Platform Core.

### Phase 0 — Existing System Audit & Architecture

- Established the initial canonical architecture for Core-owned Teacher,
  Student, Term/Enrolment, and Lesson identity/state; the combined
  Term/Enrolment question was resolved by the 3 September 2026 decision above.
- Defined Lesson as the operational centre while keeping attendance,
  notifications, scheduling, finance, reporting, and provider integrations in
  separate module boundaries.
- Documented the complete Delnavazan Enhancements migration map using KEEP,
  EXTRACT, REFACTOR, ADAPT, REPLACE, and RETIRE classifications.
- Defined the conceptual data model, `LegacyReference`, Google connection model,
  Hamnavaz/Core Teacher relationship, and state-separation rules.
- Defined the minimum security architecture for secrets, OAuth, webhooks, public
  lesson actions, authorization, logs, retention, and provider isolation.
- Considered a no-big-bang Amelia migration strategy with read-only imports and
  shadow comparison. The automated data-migration elements are superseded by the
  3 September 2026 decision above; per-capability authority cutovers, rollback,
  and exit gates remain current.
- Restored the canonical Platform Phase 0–10 roadmap and documented those
  migration mechanisms as cross-phase techniques rather than substitute phases.
- Scoped the proposed Platform Phase 1 Core foundation without implementing it.
- Added repository hygiene rules for local, secret-bearing, generated, export,
  and backup files.

### Current status

- Platform Phase 0 — Existing System Audit & Architecture is complete.
- Platform Phase 1 — Core Foundation & Canonical Data Model is implemented on
  its review branch and awaiting controlled Phase 1F beta runtime validation.
- Amelia remains installed, operational, authoritative, and readable.
- Hamnavaz Phase 4 remains intentionally paused.
## Phase 2A.2-G recovery candidate history

- Adds Schema 13 / migration 013 for immutable, PII-free Accepted Service Arrangements and sibling Proposal Option outcomes.
- Adds lock-revalidated adult-self/minor-guardian final acceptance, HMAC idempotency, privacy-safe replay, and a protected internal admin form.
- Freezes further Proposal issuance after a Family has a final arrangement. No deployment or downstream service conversion is included.

## Phase 2A.2-L candidate — 2026-09-16
- Advances the candidate to Schema 18 with migration `018_canonical_term_authority` and immutable digest-only canonical-Term command evidence.
- Adds explicit administrator-only canonical Term creation and the four bounded lifecycle transitions with Enrolment-first locking, fail-closed replay, and process-level race harnesses.
- Preserves legacy Term/Lesson behavior and adds no Lesson, Teacher, payment, scheduling, allocation-consumption, notification, or external-integration authority.

## Phase 2A.2-M0 candidate

- Advances the candidate to Schema 19 / `019_canonical_enrolment_lifecycle_authority` and build `phase2a2m0-enrolment-lifecycle-authority-20260917.1`.
- Adds explicit, idempotent canonical Enrolment activate, pause, resume, and guarded close commands plus minimal administrator invocation.
- Hardens shared Enrolment history validation to reject illegal lifecycle edges. Canonical Lesson authority remains non-authoritative.
