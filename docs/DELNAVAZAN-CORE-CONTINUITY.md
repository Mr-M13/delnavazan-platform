# Delnavazan Core — Living Project Continuity Record

**Continuity snapshot:** 19 September 2026
**Purpose:** Durable handover for a new CD/ChatGPT session or implementation agent. Source, migrations and locked domain contracts remain authoritative; this record supplies the current state, boundaries and next action without relying on chat history.

## 1. Current state

| Item | Authoritative state |
|---|---|
| Repository | `Mr-M13/delnavazan-platform` |
| Pre-Phase 2A.2-M main | `c83c1f99557b2cd1f2f81aa82a05177887a468e6` |
| Phase 2A.2-M approved candidate | `f44b502509f5dcb9b5d0281cfda4329407abd3bf`; tree `85a8309bb6179a2061c86245ea1ad9a975c00c2e` |
| Phase 2A.2-M implementation merge | `ed11086ad8ddc65899c3b855248611b1eb9e09a4`; tree `85a8309bb6179a2061c86245ea1ad9a975c00c2e` |
| Platform | 0.1.0 |
| Current authoritative main | Phase 2A.2-Q implementation merge `815a301679fdebc5d058646749e5a206ab492629`; the docs-only closeout commit that follows is recorded in the task closeout because a commit cannot embed its own hash |
| Active Platform candidate | None |
| Schema | 24 |
| Migrations | 001–024; latest `024_post_intro_continuation_slot_reservation_authority` |
| Build identity | `phase2a2q-post-intro-continuation-slot-reservation-20260920.1` |
| Latest completed slice | Phase 2A.2-Q — Post-Intro Continuation & Slot Reservation Authority |
| Phase 2A.2-Q approved candidate | `815a301679fdebc5d058646749e5a206ab492629`; tree `13757b52dd84810d44e6dba19550ec7e668ad169` |
| Phase 2A.2-Q implementation merge | `815a301679fdebc5d058646749e5a206ab492629` (fast-forward; equals the approved candidate because no merge commit was required) |
| Phase 2A.2-P approved candidate | `30fe1f11c425ad7066408f20ca57e2ca084841d6`; tree `34c111c01932f14552ada366f911f9b404111692` |
| Phase 2A.2-P implementation merge | `30fe1f11c425ad7066408f20ca57e2ca084841d6` (fast-forward; equals the approved candidate because no merge commit was required) |
| Phase 2A.2-O approved candidate | `f5b43741f4b404fd102330aeb75d58ed8b3e2976`; tree `7182101fcaf9855f8834cd08bc2e7a4a0311791c` |
| Phase 2A.2-O implementation merge | `f5b43741f4b404fd102330aeb75d58ed8b3e2976` (fast-forward; the implementation-merge SHA equals the approved candidate because main required no merge commit) |
| Phase 2A.2-N merge state | Phase N independent re-review PASS; exact approved tree merged as `08138270f4bd32e5829ef0f5a18a316f6780607d` |
| Closeout baseline (pre-O) | `08138270f4bd32e5829ef0f5a18a316f6780607d`; the docs-only closeout commit is recorded in the task closeout because a commit cannot embed its own hash |
| Current 2A.2-M state | **COMPLETE / INDEPENDENTLY REVIEWED / MERGED / CLOSED** |
| Current 2A.2-N state | **COMPLETE / INDEPENDENTLY REVIEWED / MERGED / CLOSED** |
| Current 2A.2-O state | **COMPLETE / INDEPENDENTLY REVIEWED / MERGED / CLOSED** (final independent re-review PASS after correction rounds 1–3) |
| Current 2A.2-P state | **COMPLETE / INDEPENDENTLY REVIEWED / MERGED / CLOSED** (final independent re-review PASS after correction rounds 1–3). Provenance: original `6a6a4ce8249f79df0371753479587e5d9c1fcd27` failed (P-1…P-11); `eda3df2a24f5de67dcbba1dbe6e4ae863b325e13` is historical incomplete; `629310e835a4cad3bc25d5406280944c2b6e6145` failed; `0d6f0f3ab18fe7989367b78610a73beb0e3d7bd9` failed; `30fe1f11c425ad7066408f20ca57e2ca084841d6` passed and was fast-forward merged. Phase P is provider-neutral intake/evidence/assessment/review authority only; Phase O remains canonical delivery/attendance consequence authority and Lesson authority owns completion. No Google/provider credential, no production cutover, no deployment; Theme/NIU untouched. |
| Current 2A.2-Q state | **COMPLETE / INDEPENDENTLY REVIEWED / MERGED / CLOSED.** Provenance: original `33368f6a…` failed on Q-1/Q-2/Q-3; `c43a89b3…` failed on Q-R1-1/Q-R1-2/Q-R1-3; `815a301…` passed final independent re-review and was fast-forward merged. Phase Q owns exactly the post-introductory continuation decision and a bounded pre-payment hold on the single explicitly authorised first regular slot; Phase P remains complete/closed at Schema 23. No payment/Stripe, Term or Lesson materialisation, notification, provider/Google, Teacher/Student Portal or production activation occurred; Theme/NIU untouched. **Known follow-up:** the `expiry_vs_new_claim` concurrency mode's two-second window can invert its expected winner order under load while production behaviour stays correct (two consecutive full-matrix runs passed with a widened window); a bounded test-harness stability change should follow in a future correction round because the approved tree must not be altered by this merge. |
| Next Platform action | **Requires explicit product/architecture authorisation.** No next Platform slice is approved by this record; do not begin payment/Stripe, Term or Lesson materialisation, notifications, Google/provider integration, Teacher/Student Portal work, production continuation/attendance cutover or deployment on the strength of this document. |

The completed coordination chain is:

1. **2A.2-A** — protected Booking Request advisory assessment.
2. **2A.2-B** — Coordination Case and Candidate Teacher foundation.
3. **2A.2-C** — Teacher Availability Assent.
4. **2A.2-D** — Proposal Family, Teacher-specific Option and immutable Proposal Version.
5. **2A.2-E** — Provisional Acceptance Evidence: immutable, exact Proposal-Version evidence only.
6. **2A.2-F** — Student Identity & Acceptance Authority Foundation: reviewed identity resolution, Student capacity, provenanced principal links, guardian representative grants, and informational eligibility reads.
7. **2A.2-G** — Final Acceptance + Accepted Service Arrangement Foundation: current authority revalidation, immutable accepted arrangement facts, sibling Option outcomes, and deterministic finality/idempotency evidence.
8. **2A.2-H** — Canonical Enrolment Foundation: legacy-compatible Student + Course identity, reserved lifecycle/lineage, conflict inspection and protected history without creation or conversion authority.
9. **2A.2-I** — Enrolment Conversion Authority: informational readiness, explicit capability-protected atomic/idempotent conversion, authoritative source revalidation and canonical lifecycle-origin evidence.
10. **2A.2-J** — Teacher Assignment Foundation: separate current-Teacher authority, retained initial provenance, assignment-specific replacement evidence, atomic lifecycle and protected reads/offboarding.
11. **2A.2-K** — Canonical Term Foundation: legacy-compatible Enrolment + sequence identity, canonical lifecycle/applicability storage, append-only history and protected reads without creation or lifecycle mutation authority.
12. **2A.2-L** — Canonical Term Creation & Lifecycle Authority: explicit capability-protected creation, bounded transitions, durable idempotency and Enrolment-first concurrency control.
13. **2A.2-M0** — Canonical Enrolment Lifecycle Authority: explicit activate/pause/resume/close commands, durable idempotency, protected integrity reads and subordinate closure guards.
14. **2A.2-M** — Canonical Lesson Authority: bounded standard/replacement issuance and terminal completion/cancellation, immutable Term/Enrolment/Assignment/Teacher provenance, append-only lifecycle evidence and idempotent command evidence.
15. **2A.2-O** — Canonical Lesson Delivery & Attendance Outcome Authority: provider-neutral exceptional delivery/attendance outcomes, append-only correction, explicit historical-completion reconciliation (O-D8), controlled advance academy-cancellation debt (O-D9) and a distinct immutable academy-obligation authority that never consumes the Phase-M replacement allowance.
16. **2A.2-P** — Canonical Attendance Intake & Review Authority: durable provider-neutral provider-account → canonical-participant identity registry, prospective immutable cutover policy (one policy per cutover instant), per-occurrence intake cases, append-only evidence/decisions/anomalies/conflicts, digest-only command evidence, controlled provider-evidence overlap assessment and administrative adjudication; every canonical consequence is delegated to Phase O and Lesson authority.
17. **2A.2-Q** — Post-Intro Continuation & Slot Reservation Authority: the post-introductory continuation disposition (continue / different Teacher / contact me / not continuing, plus the Teacher's match-exception report), an explicit administrator-authorised first regular slot record, and a bounded pre-payment capacity hold that converges a delayed slot record into the original continuing decision; administrator interventions for alternative Teacher, contact requests and unsuitable matches; every canonical consequence (delivery, completion, Term, Lesson, obligation, payment, notification) remains outside Phase Q.

Main is authoritative at Schema 24 / Phase Q. Canonical Lesson identity and lifecycle, canonical scheduling and Teacher capacity (Phase N), Phase-O delivery/attendance outcome authority, Phase-P provider-neutral intake/evidence/assessment/review authority, and Phase-Q post-intro continuation and pre-payment slot reservation authority are now authoritative alongside the completed Enrolment, Term, Assignment and Lesson authority chain. Production continuation/attendance cutover, payment/Stripe, Term or Lesson materialisation, provider/Meet/OAuth/webhook/credential integration, renewal, notification, calendar, Amelia, payroll, Teacher Portal and Student Portal integration remain outside Platform scope.

## Completed Phase 2A.2-P — Canonical Attendance Intake & Review Authority

**COMPLETE / INDEPENDENTLY REVIEWED / MERGED / CLOSED.** Authoritative pre-merge main was `7b9aea68fddd651cd614f279f77e78b104885c4d`. The final independently approved candidate `30fe1f11c425ad7066408f20ca57e2ca084841d6`, tree `34c111c01932f14552ada366f911f9b404111692`, was fast-forward merged into `main` (implementation-merge SHA equals the approved candidate because no merge commit was required). Schema 23 / `023_canonical_attendance_intake_authority` / build `phase2a2p-canonical-attendance-intake-20260919.1` are authoritative. No deployment occurred; production, Theme/NIU, Google/provider credentials, Stripe/payment, notification and portal surfaces were untouched, and no production attendance cutover was activated.

Phase P owns provider-neutral attendance intake, evidence and review only: a durable participant identity registry, prospective immutable cutover policy, per-occurrence cases and append-only evidence/decisions/conflicts. It does not own canonical delivery/attendance truth (Phase O) or completion (Lesson authority), and it introduces no Google/Amelia/calendar/payment/notification authority. Locked owner decisions P-D1 … P-D12 are authoritative (threshold 1200, pre-grace 0, post-grace 900; no responsibility inference; human claims never settle; no raw provider payload; duplicate cutover-instant rejection and ambiguity fail-closed; protected read fails closed on superseded schedules; full-context duplicate-command recovery).

## Completed Phase 2A.2-N — Canonical Lesson Scheduling & Teacher Capacity Authority

Phase N is **COMPLETE / INDEPENDENTLY REVIEWED / MERGED / CLOSED**. The independently approved final candidate `9f92ada6ba82d809c1515566fb426c90a5a68e57`, tree `ec29baabf2d737d0eecd72d857a152b8eba4b163`, was merged without squash or rebase as `08138270f4bd32e5829ef0f5a18a316f6780607d`, whose merge tree exactly matches the approved tree. Schema 21 / `021_canonical_lesson_schedule_authority` / build `phase2a2n-canonical-lesson-schedule-authority-20260917.1` are authoritative.

The final correction ensures Phase-N storage verification runs after migration 021, during current-schema verification, and unconditionally before Schema 21 activation, including the retained-021/stale-schema-version recovery path. The focused independent re-review returned no findings. No deployment occurred; production, Theme/NIU, Amelia and external integrations were untouched.

## Completed Phase 2A.2-M — Canonical Lesson Authority

Phase M uses additive Schema 20 / `020_canonical_lesson_authority` and build `phase2a2m-canonical-lesson-authority-20260917.1`. It preserves legacy Lesson rows, requires a current canonical Enrolment, current canonical Term and current Teacher Assignment, and derives Teacher provenance from that Assignment. It adds immutable command/lifecycle evidence, permanent 12-standard/2-replacement allocation and no scheduling or delivery authority. M0 closure rejects an authorised canonical Lesson without cascade; terminal Lessons do not block closure, and malformed Lesson evidence fails closed. It is **COMPLETE / INDEPENDENTLY REVIEWED / MERGED / CLOSED**.

### Correction round 1 — 2026-09-17

The reviewed candidate `1d723e0d7b5ef73db7bc6c24683a73c62684432c` (tree `cb577b7ece33d8533b2e47dacd4b3894566376ec`) failed independent review. Correction branch `phase-2a2m-canonical-lesson-authority-correction1` addresses the four findings without changing the locked Phase-M product or authority model:

1. `CanonicalLessonAuthorityValidator::valid()` is now the single canonical aggregate hydration and integrity gate: Lesson↔Term enrolment, Lesson↔Enrolment Student and Course identity, the recorded Teacher Assignment's structural belonging with its immutable Teacher, and complete replacement-origin lineage including controlled replacement-eligible non-delivery evidence. Historical Lessons are never required to keep the historical Assignment current.
2. Replay revalidates every operation-specific command intent against the operation facts, the result aggregate through the canonical validator, and the durable lifecycle evidence the command recorded; a corrupted command, result or history may not replay as success.
3. `tests/phase-2a2m-concurrency-runner.sh` commits a deterministic process-level runner that coordinates setup, gated worker start, lock-wait observation, controlled release, worker completion and final database verification, consumes and asserts worker artefacts, and fails non-zero on any violated race invariant.
4. Failure injection spans standard creation, replacement creation, completion, cancellation, lifecycle/history evidence writes and command evidence writes, proving complete rollback with no false replay.

Verification for this round: all 29 static/source contract tests pass; the Phase F, G, I, J, L, M0 and M runtime suites pass on disposable MariaDB 11.4.13 baselines, including a genuine Phase-H Schema-14 fixture database upgraded to Schema 20; the 16-mode gated concurrency matrix passes deterministically. The stale finite schema/build enumerations in `tests/phase-2a2h-contract.php` and `tests/phase-2a2l-runtime.php` were replaced with the repository's established minimum-schema plus canonical-build-identity pattern; both were failing on the reviewed candidate itself. Independently approved candidate `f44b502509f5dcb9b5d0281cfda4329407abd3bf`, tree `85a8309bb6179a2061c86245ea1ad9a975c00c2e`, was merged as `ed11086ad8ddc65899c3b855248611b1eb9e09a4`, whose tree exactly matches the approved tree. No deployment occurred.

## Completed Canonical Enrolment Lifecycle Authority — Phase 2A.2-M0

From authoritative pre-M0 main `8490d712d18116b3ca606d6c2e3dbb8560d2ce54`, independently approved candidate `66ba811c47a3494f12f47dbed03775ca7c4e5ba0`, tree `6a9631505a86893abf1fcefa89a78675e7ed3901`, was merged without squash or rebase as `c316a5153c7a56b810732495b3785786771c695a`. The implementation merge tree exactly equals the approved candidate tree. Schema 19 / `019_canonical_enrolment_lifecycle_authority` and build `phase2a2m0-enrolment-lifecycle-authority-20260917.1` are authoritative.

M0 makes the explicit canonical Enrolment graph operational, adds immutable digest-only command evidence, preserves pause applicability, validates protected lifecycle reads, and blocks closure while applicable canonical Term or Teacher Assignment authority exists. It adds no canonical Lesson storage or authority and performs no cascading subordinate mutation.

Phase 2A.2-M0 is **COMPLETE / INDEPENDENTLY REVIEWED / MERGED / CLOSED**. No deployment occurred.

## Completed Canonical Term Authority — Phase 2A.2-L

From authoritative pre-L main `788bf0989f4365607ba41322471e50fca75a5e81`, independently approved candidate `26beab6147df545fed70949f06b83d83042184cd`, tree `b283c319b9a3af3267cf43ad9591e30d93085986`, was merged without squash or rebase as `36e1d6b754079efcf6fdff02ed2a029099071455`. The merge tree exactly equals the approved candidate tree. Schema 18 / `018_canonical_term_authority` and build `phase2a2l-canonical-term-authority-20260916.1` are authoritative.

Phase L adds administrator-only canonical Term creation plus `authorised → current`, `authorised → cancelled`, `current → closed`, and `current → cancelled`. Durable digest-only command evidence, exact replay/conflict handling, pre-lock aggregate-position binding and Enrolment-first process concurrency preserve stale-command safety. It adds no Lesson consumption, Teacher authority, payment, scheduling, external integration or cross-aggregate mutation.

Phase 2A.2-L is **COMPLETE / INDEPENDENTLY REVIEWED / MERGED / CLOSED**. No deployment occurred.

## Completed Canonical Term Foundation — Phase 2A.2-K

From authoritative pre-K main `cf222d20e4ab0d08c992e7ec38bcece8b7625d9e`, independently approved candidate `a6491ccc1cf624be205d1ea022421a5f7903eb2b`, tree `9a59062c2a6aa10c95e88e4d24909c6e770dd27d`, was merged as `25e213c69d7f299c9ed5330eed7cd9ba4051c022`. Schema 17 / `017_canonical_term_foundation` and build `phase2a2k-canonical-term-foundation-20260916.1` are authoritative.

The candidate preserves all existing Terms as `legacy_phase1` without translating status, payment, allocation, dates or archive state. It reserves `canonical_enrolment_term_v1` for a Teacher-neutral Term identified by canonical Enrolment plus immutable sequence; adds `authorised`, `current`, `closed`, and `cancelled` lifecycle storage, one-applicable-row arbitration and append-only digest-only history; and provides protected integrity classification and privacy-minimised reads.

The independent correction closed the merge-blocking cross-aggregate defect: a validly closed canonical Enrolment with an `authorised` or `current` applicable Term now fails closed as `data_integrity_conflict`, while terminal Term history remains valid. Phase K intentionally contains no ordinary canonical Term creator, lifecycle mutation command, command-idempotency table, Lesson creation/consumption, scheduling, payment, renewal or external authority. The existing legacy Term/Lesson path remains available only through explicit legacy record-model guards. Generic archive/restore cannot mutate canonical lifecycle.

Phase 2A.2-K is **COMPLETE / INDEPENDENTLY REVIEWED / MERGED / CLOSED**. No deployment occurred.

## Completed Teacher Assignment Foundation — Phase 2A.2-J

Starting from pre-J main `7bd4430737f460fdb995bbe05dea272b55641294`, approved candidate `b1fd5aebf47a0bce33f74ab56b53c5334d719bf4`, tree `5766e126a5abd7e9160e6c00dc96b6837f49b05e`, was merged as `763a9fa9f792cd45114e10978a1bda0a53662c21`. Schema 16 / `016_teacher_assignment_foundation`, build `phase2a2j-teacher-assignment-foundation-20260915.1`, establishes a first-class Assignment aggregate and explicitly preserves `enrolments.teacher_id` as historical Accepted Service Arrangement context.

Zero Assignment is valid and migration 016 performs no backfill. Initial Assignment is limited to canonical Enrolments in `authorised`, `current`, or `paused` and must use the exact retained final-arrangement Teacher. Historical Availability Assent provenance remains usable even after time passes or the source record later transitions. A different Teacher requires new assignment-specific authenticated-Teacher acceptance or authorised staff attestation; availability, eligibility, proposal history, the historical Enrolment Teacher, and administrator preference do not substitute.

Replacement atomically terminates the predecessor and creates its successor; no future replacement can be staged. Expected-current identity arbitrates stale commands, nullable-slot uniqueness is final database arbitration, and same-key replay/different-key identical-authority behavior is explicit. Lifecycle evidence is append-only and command/evidence references are digest-only. The protected read seam returns only stable identifiers, sequence, state, and assigned time.

Assignment commands lock Enrolment → Teachers with shared locks in ascending ID order → concrete Assignments under `READ COMMITTED`. Teacher archival takes an exclusive Teacher lock and rechecks applicable Assignment state transactionally. J-5 keeps principal/onboarding offboarding independent: it does not archive the Teacher, invalidate existing Assignment authority, or prevent retained-provenance initial and authorised staff-attested replacement authority, but it prevents authenticated-Teacher replacement after principal authority is revoked. Applicable Assignment authority blocks incompatible Teacher archival. Review correction round 1 restricts duplicate arbitration to named indexes plus complete operation-specific authority proof; terminal failures cannot converge from a merely current Assignment. Exhaustive failure/corruption and twenty-mode bidirectional process-concurrency coverage, including six real principal-offboarding races, is committed.

The original independent review found one HIGH and three MEDIUM findings. Correction round 1 closed the HIGH finding plus failure/corruption and regression gaps. The remaining offboarding concurrency question exposed a contract ambiguity; J-5 clarified that archival and principal offboarding are distinct authority boundaries. The final candidate added deterministic offboarding coverage without production-source changes, and the final independent check passed. The MariaDB reviewer environment lacked MySQL-specific `data_locks` / `data_lock_waits` attribution tables; this limitation was explicitly documented and no runtime success was inferred from Ina's execution.

Phase 2A.2-J is **COMPLETE / INDEPENDENTLY REVIEWED / MERGED / CLOSED**. No Enrolment lifecycle, Term, Lesson, schedule, capacity, payment, notification, calendar, Amelia, Hamnavaz, CRM, Theme, NIU, or production authority was introduced. No deployment occurred.

## 2. Locked coordination domain contract

The authoritative hierarchy is:

```text
Booking Request
→ Coordination Case
→ Candidate Teacher Consideration
→ Teacher Availability Assent
→ Proposal Family
→ Teacher-specific Proposal Option
→ immutable Proposal Version
→ provisional/final acceptance
→ Accepted Service Arrangement
→ conversion readiness
→ explicit conversion authority
→ idempotent Enrolment conversion
→ explicit Teacher Assignment authority
→ canonical Term foundation (storage/read only)
→ future explicit Term creation/lifecycle authority
→ later canonical Lesson authority
```

These distinctions are deliberate and must not be collapsed:

- Teacher Availability Assent is not Teacher Assignment, capacity reservation or Lesson booking.
- A Proposal creates offer authority only; it is not acceptance or arrangement authority.
- Acceptance is not conversion authority.
- Conversion readiness is neither conversion authority nor a successful conversion.
- A successful conversion creates exactly one Enrolment authorised by the accepted arrangement. It does not create Teacher Assignment.
- Teacher Assignment is separate, effective-dated authority.

Any subsequent work must preserve this graph. Phase K does not authorise Term creation or lifecycle mutation, and no work may silently advance to Lesson, scheduling, capacity, payment, notification, calendar or Amelia authority.

## Completed Enrolment Conversion Authority — Phase 2A.2-I

Starting from pre-I main `f855103c6449ad466ff267c7a9740b56c9ffed66`, approved candidate `4e40c5fa7665a8f96fcbf19def2cac719e7bc38b` was merged as `d0bfbe1b808e60e8bcffae0da6c16a4fda1dc928`. Schema 15 / `015_enrolment_conversion_authority`, build `phase2a2i-enrolment-conversion-authority-20260914.1`, establishes:

- informational, read-only conversion readiness distinct from explicit capability-protected conversion authority;
- locked revalidation of the authoritative Accepted Service Arrangement source graph, including exact frozen identity-resolution and capacity-classification evidence;
- retention of PII-free final arrangement authority after Booking Request privacy erasure, without reconstructing or copying erased contact data;
- canonical Student + Course identity serialization through a concrete identity root, while the frozen Teacher remains historical/service context only;
- atomic, digest-only idempotent conversion to exactly one canonical Enrolment beginning in `authorised` state;
- contamination-safe replay and already-converted results that remain valid through legitimate later canonical lifecycle progression;
- return-after-closure predecessor selection only from complete authoritative lifecycle history, with malformed or ambiguous evidence rejected.

Final independent re-review result: **PASS — MERGE READY**. Phase 2A.2-I is **COMPLETE / MERGED / CLOSED**.

Teacher Assignment remains future work and was not started. Conversion creates no Teacher Assignment, Term or Lesson authority and no payment, calendar, Amelia, notification or communication authority. No deployment occurred; Theme, NIU, Amelia, Hamnavaz, CRM and production were untouched.

## Completed Canonical Enrolment Foundation — Phase 2A.2-H

Starting from pre-H main `aa626e89c66a393977caf002355589990aec03df`, approved candidate `6ae121d0a96b8bd034663992f4ee5ee0812a9893` was merged as `672e5334fe9f37fff5f872cf3fdad18af5ab50d5`. Schema 14 / `014_canonical_enrolment_foundation`, build `phase2a2h-canonical-enrolment-foundation-20260913.1`, establishes:

- canonical Enrolment structural identity as Student + Course, with record models `legacy_phase1` and `canonical_student_course_v1`;
- the reserved canonical lifecycle `authorised → current → paused → closed`;
- immutable Accepted Service Arrangement provenance, one applicable canonical Enrolment per Student + Course, and append-only lifecycle-history foundations;
- protected applicability/conflict inspection and internal read/history surfaces;
- exact preservation of legacy status and historical `teacher_id`, plus a constrained capability-protected legacy/bootstrap compatibility seam;
- closed generic/manual canonical creation and repository-level protection against legacy archive/restore mutation of canonical records.

Independent-review findings HIGH-1, HIGH-2, HIGH-3 and MEDIUM-1 were corrected. Final independent re-review result: **PASS — MERGE READY**. Phase 2A.2-H is complete, independently reviewed and merged.

No Accepted Service Arrangement → Enrolment conversion was implemented. There is no Teacher Assignment authority and no canonical Term or Lesson authority. Conversion readiness and explicit conversion authority remain distinct future work and must not be silently conflated with Phase 2A.2-H. No deployment occurred; Theme, NIU, Amelia, Hamnavaz and production were untouched.

## Completed Final Acceptance + Accepted Service Arrangement Foundation — Phase 2A.2-G

Starting from pre-G main `0607be0ce6f2dcd32dc60a4d8ff6c76d0f4012c8`, independently reviewed candidate `701f62595327ba464d81299b1832ba7825eddc4e` was merged as `3a33bafd5943b15b139bbe40cb198cbb201cc943`. Schema 13 / `013_final_acceptance_arrangement_foundation` establishes:

- exact final acceptance of the current immutable Proposal Version against current Booking Request, Student identity, capacity and adult-self or guardian authority;
- immutable, PII-safe Accepted Service Arrangement facts and atomic sibling Proposal Option outcomes;
- HMAC-digested, replay-safe idempotency and one final arrangement per Proposal Family;
- Proposal finality after acceptance, without conversion or operational authority;
- protected internal administration only;
- deterministic concurrency evidence for competing acceptance, Proposal replacement, privacy erasure, capacity and authority invalidation, shared idempotency keys and unrelated Families;
- attributed principal-first and guardian-first contention on the concrete Student authority serialization row, with stale authority rejected after release.

Production authority-lock source candidate `d96378d7f16a2ff1c90ec1fc99273361f3630808` passed independent source review. Candidate `701f62595327ba464d81299b1832ba7825eddc4e` added evidence and semantic contract hardening only. Final independent review result: **PASS — MERGE READY**.

No deployment occurred. Deliberate exclusions remain: no Booking Request conversion, Enrolment creation, Teacher Assignment, Term, Lesson, payment, notification, scheduling, calendar or Amelia authority.

## Completed Student Identity & Acceptance Authority Foundation — Phase 2A.2-F

PR #15 merged approved candidate `f7b8066ce6f45c6bee461cdb13cc2614283988bd` as merge commit `c578f137ed537276524a460a9bb4771ec6fbcc4c`. Schema 12 / `012_student_identity_acceptance_authority` establishes:

- explicit human-reviewed, append-only Booking Request-to-Student identity resolution, with no automatic identity matching;
- append-only `adult`, `minor` and `unknown` Student acceptance-capacity classifications;
- authoritative, versioned and provenanced Student-to-WordPress principal links with atomic supersession lineage;
- effective and revocable `guardian_representative` authority scoped to `service_acceptance`, with strict intervals and append-preserving lazy expiry;
- derived adult-self authority; adult delegation remains unavailable in V1;
- protected internal administration and an informational, non-bearer eligibility read service;
- the Booking Request privacy-erasure boundary, including rejection of new authority after erasure;
- a deterministic common authority lock order and attributed R1–R11 concurrency harness;
- real Schema 11 → 12 migration and repeat-upgrade evidence.

Deliberate exclusions remain: no automatic PII matching, public identity workflow, adult delegation, final acceptance, Accepted Service Arrangement, Booking Request conversion, Enrolment, Teacher Assignment, Term, Lesson, payment, notification, calendar or Amelia authority.

> This prerequisite gate was resolved before Phase 2A.2-G implementation; the historical Phase 2A.2-F boundary remains recorded here.

## 3. Completed Proposal Foundation — Phase 2A.2-D

Schema 10 / `010_proposal_foundation` implements the minimum proposal lineage:

- one canonical Proposal Family per Booking Request / Coordination Case;
- Teacher-specific Proposal Options, with unique Family/Candidate and Family/Teacher constraints;
- append-only immutable Proposal Versions, each belonging to exactly one Option;
- A2 supersedes A1 only inside Option A; a Teacher B Option remains independent;
- exact Family + Option + Version retrieval and a guarded current-Version pointer;
- authoritative Assent consumption before issuance, not a duplicate currentness check;
- Proposal-scoped HMAC idempotency with no raw client-key persistence;
- controlled historical facts and provenance without unnecessary Booking Request contact PII;
- capability `dzn_issue_booking_request_proposals`;
- protected internal coordination surface only, with no public Proposal REST route.

It does not create Student, Enrolment, Teacher Assignment, Lesson, payment, notification, calendar, Amelia or public Proposal authority.

### Validation record

The completed 2A.2-D validation included:

- Schema 9 → 10 and repeated-migration behaviour;
- capability repair;
- Proposal lineage and immutability;
- Assent currentness/rejection;
- idempotency and privacy/authority boundaries;
- simultaneous initial issuance and simultaneous replacement;
- issuance racing Assent invalidation and eligibility invalidation.

Final validation was **PASS WITH NON-BLOCKING LIMITATIONS — MERGE READY**, then PR #13 merged.

The following failures are historical regressions on the exact base, not 2A.2-D regressions:

- `phase-1c-executable.php`
- `phase-1d-contract.php`
- `phase-1f-contract.php`
- `phase-2a0-independent-review-contract.php`

## 4. Next Platform action

**No next Platform slice is authorised.** Phase 2A.2-Q is merged and closed at Schema 24; the next bounded slice requires an explicit product/architecture decision from the owner before reconnaissance, a branch or implementation begins. Phase Q merged only the post-intro continuation decision and a bounded pre-payment hold; it does not activate a production continuation workflow, create a payment/Term/Lesson consequence or implement provider/Portal integration.

This record does not authorise a continuation or delivery/attendance cutover, provider integration, Google/Meet/OAuth/webhook/credential work, Amelia/calendar work, payment/Stripe/Finance work, Term or Lesson materialisation, notification authority, Teacher Portal, Student Portal integration, a public route or any later phase.

Historical roadmap note (superseded): this section previously recorded Phase 2A.2-L, the Phase 2A.2-M0/M boundaries, the Phase-O candidate, the Phase-P candidate and the Phase-Q correction rounds as the next action. Those slices are complete and merged; the earlier wording is retained only as provenance and is **not** the current next action.

## Completed Phase 2A.2-O — Canonical Lesson Delivery & Attendance Outcome Authority

**COMPLETE / INDEPENDENTLY REVIEWED / MERGED / CLOSED.** Authoritative pre-merge base was post-Phase-N `main` at `b0687fec98748144f96e3fc56f7e4fb53e01f673`. The final independently approved candidate `f5b43741f4b404fd102330aeb75d58ed8b3e2976`, tree `7182101fcaf9855f8834cd08bc2e7a4a0311791c`, was fast-forward merged into `main` (the implementation-merge SHA equals the approved candidate because no merge commit was required), so Schema 22 / migration `022_canonical_lesson_delivery_attendance_authority` / build `phase2a2o-canonical-lesson-delivery-attendance-authority-20260918.1` are authoritative. No deployment occurred; production, Theme/NIU, Amelia, Google/calendar, Meta and Stripe were untouched.

Phase O is **provider-neutral**. It records what actually happened to a canonical Lesson occurrence with no provider coupling, no payment or payroll effect, no notification authority and no public surface.

### Locked product decisions O-D1 … O-D9

| Decision | Locked meaning |
| --- | --- |
| O-D1 | Canonical Lesson `completed` means the occurrence was actually delivered. No new Lesson lifecycle state was added. |
| O-D2 | Exception-based recording: ordinary delivery is the absence of an outcome row, so no routine attendance record is required per Lesson. |
| O-D3 | A student no-show may coexist with delivered/completed — it is a distinct attendance fact, the Lesson is consumed, and no automatic academy remedy is created. |
| O-D4 | Teacher/academy non-delivery creates an academy obligation that is distinct from the Phase-M replacement. |
| O-D5 | Advance cancellation remains distinct from post-occurrence non-delivery and can never describe the same event. |
| O-D6 | Corrections remain append-only: they supersede history, never rewrite or reopen Lesson lifecycle, and never move money. |
| O-D7 | Canonical Phase-O mutation authority requires the Phase-O administrator capability (`dzn_manage_canonical_lesson_delivery`), enforced inside the public write seam before any lookup or mutation. |
| O-D8 | The historical completion event is preserved immutably while the effective delivery truth may be explicitly reconciled to Teacher/academy non-delivery; the academy obligation is bound to canonical completion lineage. Nothing reopens, reschedules, materialises or pays automatically. |
| O-D9 | A controlled advance Teacher/academy cancellation (`academy_unavailable`) may establish academy debt; a generic Student cancellation does not. |

### Canonical lifecycle authority (Round-3 architecture)

Academy-obligation authority does **not** define Lesson completion. `CanonicalAcademyObligationValidator` consumes `CanonicalLessonAuthorityValidator::valid()` before accepting any obligation lineage; the delivery validator consumes it whenever a reconciliation pointer exists; and the reconciliation command consumes it instead of scanning raw lifecycle rows. Completion is bound to the canonical chain's terminal event, which follows from the validated legal progression rather than from selected row fields or a latest-completed-ID scan. The call is one-way (Lesson authority references no Phase-O authority), so there is no recursion, no competing lifecycle authority and no lock-order change; a malformed source Lesson lifecycle causes obligation protected reads to fail closed.

### Security / integrity closeout

- The public obligation write seam (`CanonicalAcademyObligationService::owe()`) self-enforces `dzn_manage_canonical_lesson_delivery` before any lookup, validation or mutation; an unauthorized principal fails closed with no obligation row and no partial authority.
- Same-kind replay validates the supplied source and immutable evidence: exact legitimate replay is idempotent with a single row, while conflicting evidence or source identifiers fail closed (`obligation_replay_conflict`) and a conflicting source kind fails closed (`obligation_source_conflict`).
- Aggregate Term/Enrolment obligation reads validate every selected obligation through canonical authority, discover rows by the union of the stored selector and the source-Lesson relationship so a corrupted selector cannot disappear silently, and fail the whole aggregate closed on any invalid row; the Term count derives from the same validated authority and no raw SQL count seam exists.
- Round-3 sequencing keeps guards and reads honest inside the Phase-M transaction: the append-only lifecycle event and any academy obligation are written before the advance-cancellation guards, so guards validate the exact state the transaction will commit, and a rejection rolls everything back together with its precise reason.

### Historical correction records (superseded by the final merge)

The three sub-sections below record correction rounds that are now complete. They are historical provenance: the candidate SHAs they name are superseded by the merged candidate `f5b43741f4b404fd102330aeb75d58ed8b3e2976`, and no active work remains from them.

#### Correction round 3 (independent re-review of round 2 failed — HIGH/MEDIUM)

Round-2 candidate `5b18f20d576e5446cee6cecc0b21d010fb62ffa1` (tree `af035208b7096dad12e60b768e928372fb094778`) failed independent re-review. The round-3 correction is a descendant of that candidate and remains validation, test and documentation work; Schema 22, the migration, the build identity, O-D1…O-D9 and the round-1/2 fixes are preserved.

- **HIGH — no competing lifecycle authority:** canonical completion is no longer decided inside academy-obligation authority. `CanonicalAcademyObligationValidator` consumes `CanonicalLessonAuthorityValidator::valid()` (which references no Phase-O authority, so the reuse is one-way with no cycle, recursion or lock-order change) before accepting obligation lineage, the delivery validator consumes it whenever a reconciliation pointer exists, and the delivery service's reconciliation command consumes it instead of scanning for a maximum completed event id. Completion is bound to the canonical chain's terminal event as a consequence of the validated legal progression. The obligation remains subordinate and never decides whether a malformed Lesson lifecycle is acceptable.
- **MEDIUM — referenced-event corruption coverage:** the referenced completion event itself is now corrupted by tests (`from_state`, `event_sequence`, and an injected structurally noncanonical completed row that Lesson authority rejects), each exercised through all three public aggregate seams with repair proving recovery. The earlier claim that the referenced event was already proven canonical was an overclaim and is corrected.
- **Sequencing:** the append-only lifecycle event and any academy obligation are written before the advance-cancellation guards inside the caller's transaction, so the guards validate the exact committed state; the guard also runs at the service seam before mutation. Rejections roll back the event, obligation and lifecycle update together and retain their precise reasons (`delivery_outcome_exists`, `occurrence_already_started_use_delivery_outcome`).

#### Correction round 2 (independent re-review of round 1 failed — HIGH/MEDIUM/LOW)

Round-1 candidate `c63c1327047bd76f3155788874ec6ea6ae7a38e0` (tree `7fa33f0d406e25a403e0f2b5c7d4668923faaaca`) failed independent re-review. The round-2 correction is a descendant of that candidate and remains validation, test and documentation work; Schema 22, the migration, the build identity, O-D1…O-D9 and the round-1 fixes are all preserved.

- **HIGH — O-D8 completion-event lineage:** an academy obligation raised by a reconciled completion now has its `source_event_id` validated against the effective outcome's canonical reconciliation lineage: an ordinary non-reconciled non-delivery must carry **no** completion-event lineage (a stored identifier is an integrity conflict), while a reconciled one must name exactly the event the effective outcome supersedes, and that event must exist in the Lesson's canonical lifecycle, belong to the same Lesson, be the `completed` event and still be the Lesson's latest completed event with the Lesson in `completed` state. The obligation remains subordinate to the canonical Lesson/outcome/reconciliation facts and never becomes the authority over completion.
- **MEDIUM — corruption coverage:** Teacher Assignment identity was added to the aggregate identity matrix, and four O-D8 completion-lineage corruption cases (wrong identifier, completion event belonging to another Lesson, missing lineage where reconciliation requires it, wrong lifecycle-event type) plus a spurious-lineage case on an ordinary non-reconciled non-delivery are now exercised through all three public aggregate seams with repair proving recovery. The round-1 claim that aggregate corruption coverage already included complete source event lineage and Teacher Assignment was an overclaim and is corrected.
- **LOW — idempotent replay:** `owe()` validates the supplied source and the existing obligation's binding and immutable evidence before reporting an idempotent success; exact legitimate replay stays idempotent with no duplicate row, and conflicting replay intent fails closed with `obligation_replay_conflict`. Uniqueness and concurrency semantics are unchanged.

#### Correction round 1 (independent review failed O-1/O-2/O-3)

The first owner-verified candidate `4f8af9067aa1ed463916d86b32a04f4193f44d23` (tree `8a79719e39496476debef4c67971648ab3447d57`) failed final independent review. The correction is a descendant of that reviewed candidate and changes only application-service validation, capability enforcement, tests and documentation — Schema 22, the migration, the build identity and O-D1…O-D9 are unchanged.

- **O-1 boundary:** `CanonicalAcademyObligationService::owe()` — the single public academy-obligation write seam — enforces `dzn_manage_canonical_lesson_delivery` as its first statement, before any source lookup, validation or mutation. An unauthorized principal fails closed with `Unauthorized`, no obligation row is created and no partial authority survives. Its two legitimate callers (the Phase-O non-delivery path and the Phase-M advance-cancellation reconciliation) already run behind their own capability check inside a transaction, so authorized behaviour and atomicity are unchanged; an advance cancellation by a principal without the Phase-O capability now fails closed and rolls back completely. This is the only path by which academy debt can be created.
- **O-2 aggregate fail-closed validation:** `outstandingForTerm()`, `outstandingCountForTerm()` and `outstandingForEnrolment()` hydrate each selected obligation against its canonical source Lesson and validate it through `CanonicalAcademyObligationValidator`, which now requires the stored Term, Enrolment, Student, Course, Teacher and Teacher Assignment selectors to agree with the source authority, checks source outcome/cancellation-event lineage, schedule-version and occurrence anchors, actor, classification and evidence, and requires the obligation's evidence to be identical to the evidence of the source fact. One invalid row fails the whole aggregate closed (`canonical_obligation_integrity_conflict`); no partial apparently-valid aggregate is returned, and the Term count is derived from the same validated aggregate rather than a raw SQL count. Term and Enrolment selectors use the union of stored-selector and source-Lesson membership, so a corrupted stored selector is validated instead of disappearing.
- **O-3 test-count evidence (corrected):** at Phase-O closeout, 29 files matched `tests/*contract*.php` and all passed. PHP lint (`tests/static.php`) and `sh -n` shell syntax checks are separate syntax checks, not contract tests. The earlier "36 contract tests" figure was a miscount and must not be quoted. **Current repository count is 30** `tests/*contract*.php` files on authoritative `main` because the merged Phase-P authority adds `tests/phase-2a2p-contract.php`.

Phase O is a **provider-neutral** canonical authority over what actually happened to a canonical Lesson occurrence. It adds no provider integration, no Google/Amelia/calendar/notification coupling, no payment or payroll effect and no public surface.

### Locked product decisions O-D1 … O-D9

| Decision | Locked meaning |
| --- | --- |
| O-D1 | Canonical Lesson `completed` means the occurrence was delivered. No new Lesson lifecycle state is added. |
| O-D2 | Exception-based recording: ordinary delivery is the absence of an outcome row, so no manual attendance record is required per Lesson. |
| O-D3 | A student no-show is a distinct fact: the Lesson is consumed, completion is still permitted, and no entitlement is created. |
| O-D4 | Teacher/academy non-delivery blocks completion and creates an academy-owed occurrence. |
| O-D5 | Cancellation stays a pre-occurrence scheduling/lifecycle fact; post-occurrence non-delivery is a delivery fact. The two can never describe the same event. |
| O-D6 | Corrections supersede append-only history, never mutate it, never reopen the Lesson and never move money. |
| O-D7 | Authorised administrator recording is immediate, idempotent and needs no second approval. Student/Teacher self-service is not authorised. |
| O-D8 | A completed Lesson may later be reconciled by an explicit authorised append-only command into Teacher/academy non-delivery. The historical completion event stays immutable; the effective delivery truth becomes non-delivery; the academy obligation is established; nothing reopens, reschedules, replaces or pays. |
| O-D9 | An advance Teacher/academy cancellation (`academy_unavailable`) establishes an academy obligation; a Student-requested cancellation never does. |

### Provider-neutral evidence boundary

Evidence is a controlled channel (`staff_record`, `authenticated_platform`, `document_reference`) plus an opaque keyed digest, an observed time and an actor. Phase O makes no provider call and never lets provider output become authority merely because it was emitted. Roadmap Phase 4 (direct Google integration) can ingest meeting evidence (teacher/student join, join/leave interval, overlap, duration) through this same boundary without changing canonical identity or adding Google coupling to the domain. No Google-specific schema exists.

### Phase-M replacement allowance vs Phase-O academy-owed occurrence

These are deliberately different authorities and must not be merged:

- the **Phase-M replacement allowance** is the Student's own bounded make-up allowance — at most two per Term, one per eligible origin, created only by a pre-occurrence cancellation carrying the controlled `attested_non_delivery` attestation, materialised as a `replacement` Lesson, and expiring with the Term;
- the **Phase-O academy-owed occurrence** is a teaching occurrence the academy owes because it failed to deliver. It is recorded in `{prefix}dzn_canonical_academy_obligations`, bounded to one immutable row per source occurrence, never counted against the two-per-Term allowance, never materialised as a `replacement` Lesson, and never removed or deactivated by Term closure. Materialising it into a delivered future occurrence remains a later explicit command/phase.

Historical `canonical_lesson_cancelled_replacement_eligible` and `attested_non_delivery` records keep their historical Phase-M meaning; there is no semantic backfill.

### Current exclusions

Attendance cutover, Google Meet/Calendar evidence ingestion, signed public join/absence links, Student/Teacher portals, notifications/WhatsApp, payment/Stripe, Finance, payability, teacher payroll, renewal automation, partial-delivery proration, legacy attendance import, retention policy, Theme/NIU work and any materialisation or scheduling of an owed occurrence remain later authority. The Phase O candidate itself creates none of them.

Full contract, validation record and residual notes: [PHASE-2A-2O-CANONICAL-LESSON-DELIVERY-ATTENDANCE-AUTHORITY.md](PHASE-2A-2O-CANONICAL-LESSON-DELIVERY-ATTENDANCE-AUTHORITY.md).

## 5. Persistent architectural boundaries

Delnavazan Platform is an incremental authority migration away from architectural dependence on Amelia. Core owns stable business identity and canonical state; integrations reference that state through explicit boundaries.

- Do not add new Amelia dependencies or write to Amelia merely because Platform source exists.
- Teachers, Students, Enrolments, Terms and Lessons remain distinct concepts; Lesson is the later operational centre.
- Provider mappings are not business identity. Do not automatically merge identities.
- Migrations are versioned, ordered, retry-safe and separate from authority cutover.
- Security, privacy, capability checks, provenance, idempotency and concurrency testing are first-class requirements.
- No deployment, merge, public endpoint, external communication or payment activity is implied by source completion.

Historical Phase 0/Phase 1 planning documents remain useful where they do not conflict with this newer coordination/Proposal/Acceptance contract.

## 6. Theme status and boundaries

| Item | State |
|---|---|
| Theme repository | `Mr-M13/delnavazan-theme` |
| 0.4.1 source baseline | `e8f6da4cc365b61aaf1e0356f11100c24449730d` |
| 0.4.1 validated package | `delnavazan-production-theme-0.4.1.zip` |
| 0.4.1 package SHA-256 | `4f90bea5c58b0426d6ae81b16c676d0d477d97fa6c3047918f2c90f276a435b3` |
| NIU 0.4.1 state | Manually installed; no production deployment |
| Historical 0.4.2 branch | `codex/increment-0.4.2-art-direction` |
| Historical 0.4.2 candidate | `a4dfc4acd614f049c725f599a4d856ea40f6d517` |

Theme 0.4.2 implements a contemporary Persian cultural-institution direction: an asymmetric replaceable hero, restrained Persian typography, Custom Logo header/footer, corrected SVG hamburger, six-item instrument folio, human coordination reassurance, presentation-only regional pricing, FAQ before articles, homepage date removal, and responsive/accessibility refinements.

Final Academy-owned or licensed hero/instrument imagery remains required before visual sign-off. The Theme remains presentation only: it has no Platform, pricing-authority, payment, enrolment, Teacher, scheduling, notification, calendar or Amelia business authority.

### Current header and homepage continuity

The public Theme remains Persian-first (`fa-IR`), RTL, refined/editorial/warm/human, with warm ivory, Delnavazan turquoise, restrained pomegranate, PHP templates, `theme.json`, Gutenberg and minimal JavaScript. Desktop navigation order is `خانه`, `ورود هنرجویان`, `ورود اساتید`, `مقالات`, `ثبت نام`. Ordinary links use deep Delnavazan turquoise; only `ثبت نام` receives CTA treatment. Preserve Custom Logo, the warm-ivory header and mobile-menu behaviour.

Current homepage work includes refined responsive styling, corrected mobile/full-width sections, the green pricing/finale section, and reuse of `BG-Ornoments.webp`. The artwork already carries reduced opacity, so CSS renders it at `opacity: 1`. Footer intent is logo right/top, actions centred/top, legal left/bottom and secondary navigation right/bottom.

The homepage shows three randomly selected published articles. Gutenberg Query Loop remains presentation markup. The identifying class `dzn-home-random-posts` belongs on the inner `core/post-template` block; `query_loop_block_query_vars` then targets that query with `posts_per_page = 3`, `orderby = rand` and `ignore_sticky_posts = true`. Putting the class only on the outer `core/query` block does not target the query-vars filter. Authoritative Theme source must contain no temporary forced `post__in`, post ID or footer diagnostic comment.

### Theme staging

NIU (`https://niu-nailhouse.com`) is disposable, sanitised Theme staging. Preserve the MU staging guard and keep ordinary plugins inactive. Do not casually re-run sanitisation. NIU itself is not globally unavailable: product-owner normal browser access works, while Cloud Browser currently receives 502/connection-refused responses. Do not repeatedly spend agent quota retrying that browser path.

## 7. Locked commercial and public-contact facts

### Payment journey

Free introductory lesson → learner decides whether to continue → term tuition paid → paid 12-session term begins → educational Lesson 1 of 12.

Canonical public wording is **one term / 12 weekly private lessons**. Do not call the canonical term “3 months”.

### Regional set pricing

This is configured regional pricing, not live FX conversion:

| Region | Currency | Amount |
|---|---|---:|
| Australia | AUD | 250 |
| New Zealand | NZD | 250 |
| United States | USD | 250 |
| Canada | CAD | 250 |
| Euro pricing region | EUR | 150 |
| United Kingdom | GBP | 150 |

UAE/AED, Kuwait/KWD, Turkey/TRY and other Gulf regions are future candidates only, not active offers.

Theme 0.4.2 may suggest a supported region from a location signal, accepts an explicit manual selection and stores that UI preference locally. It remains neutral for unsupported/failed detection and never silently defaults to the United States. It has no FX, payment or entitlement authority.

The future authority flow is: location signal → suggested region → visitor selection → UI preference → enrolment context → Platform authoritative price revalidation → later payment/Stripe authority.

### Public contact

- Customer phone: **0413 413 004**
- Email: **delnavazan@mail.com**
- Instagram: **@insta.delnavazan**
- `+61 431 364 200` is separate WhatsApp notification infrastructure and must not be exposed as customer-facing contact.

## 8. Execution posture

CD / ChatGPT is the architect, orchestrator, dependency manager and handoff coordinator: it controls sequencing, dependencies, collision avoidance and review routing.

Ina / Codex Local and Hamed / Codex Cloud operate under a dual-owner, reciprocal-review model. Either may own a complete bounded slice and perform reconnaissance, implementation, tests and self-review. Ownership alternates according to dependency, quota/capacity, task shape, context and efficiency; neither identity is permanently restricted to developer or reviewer work.

For consequential migrations, authority transitions, concurrency/idempotency, identity/privacy, payments, external side effects, destructive operations and consequential merge candidates, the non-owner performs independent cross-review. Routine deterministic/documentation work does not automatically require independent review. After an independent PASS, the implementation owner may perform deterministic merge/closeout only when the exact reviewed candidate is preserved.

Parallelise genuinely dependency-independent work. Do not parallelise sequential authority work where that would create speculative implementation, collisions or rework. Avoid duplicate expensive reconnaissance and runtime work.

Delivery cadence should reuse established patterns rather than automatically splitting every capability into tiny phases. Prefer a larger bounded increment when authority boundaries are established, rollback is clear, validation remains tractable, concurrency risk is controlled and independent review can inspect one coherent candidate. Retain smaller increments when migration or authority risk genuinely requires them.

Delivery posture: **CONTROLLED MOMENTUM**. For bounded, reversible work: IMPLEMENT → TEST → INSPECT → CORRECT. Remain strict around production, real-user/private data, security/privacy, destructive migrations, payments, external communications, Amelia/calendar, identity/guardian authority, acceptance/conversion authority and consequential deployment.

## 9. New-session checklist

Before starting another slice, establish:

1. the exact repository, branch and SHA;
2. whether the work is merged, a candidate, or reconnaissance only;
3. the relevant locked domain boundary and explicit exclusions;
4. whether runtime/deployment authority exists;
5. which agent/environment is already working on adjacent scope;
6. the single next authorised action.

Never treat this document as authority to deploy, merge, access production, change NIU, activate plugins, send communications, or extend a later phase.

### Historical candidate records (superseded — provenance only)

The two paragraphs below described Phase 2A.2-L and Phase 2A.2-M0 while they were still unmerged candidates. Both slices have since been independently reviewed, merged and closed (Phase L at Schema 18, Phase M0 at Schema 19), so they are historical provenance and must not be read as current state.

> **Historical Phase 2A.2-L record:** Phase L was an unmerged candidate adding explicit canonical Term creation and bounded lifecycle authority with durable idempotency. Canonical Enrolment remains Student + Course; Teacher Assignment remains the sole current-Teacher authority. Term closure/cancellation does not mutate Enrolment, and no Lesson, payment, scheduling or integration authority follows from a Term command.

> **Historical Phase 2A.2-M0 record:** the formerly unmerged M0 candidate advanced its package identity to Schema 19 / `019_canonical_enrolment_lifecycle_authority` and build `phase2a2m0-enrolment-lifecycle-authority-20260917.1`, made the explicit Enrolment lifecycle graph operational and left canonical Lesson authority non-authoritative. The frozen pre-M0 Lesson candidate was subsequently resumed as Schema 20 / Phase M.


## 10. Continuity handover — 21 September 2026

This section supersedes any older “next action”, agent-role, review-state, Theme-candidate, or orchestration wording elsewhere in this document when they conflict. Historical implementation records remain valid provenance; this section is the current operational handover.

### Current checkpoint

- Authoritative Platform repository: `Mr-M13/delnavazan-platform`.
- Authoritative Platform main remains `1b9d7aaef0ca21fdb861ccc9da1d15634cb5d843` at Schema 24 / Phase Q. Do not advance main merely to publish this continuity handover while Phase R1 is still under review.
- Phase R1 is the current critical path. It is **NOT MERGE READY**.
- Original R1 candidate `10fe40618af3de76f2af37a093e611af10cc6ccc`, tree `394b52d3286a9683e2e714bb7794729b7ebf5210`, failed final independent review.
- Correction Round 1 candidate `186fc5012fe294ea3d91b85aeefdd738448471a0`, tree `40fc2643eacc6811882b552785f55e7db5c04e30`, is a published additive descendant of the failed candidate and also failed final independent re-review.
- Next authorised Platform action: **Correction Round 2 from `186fc501...` only, additive descendant, same branch; no amend/rebase/squash/force-push.**
- After owner implementation and publication, CD critically reviews the exact candidate identity, then Hamed Cloud independently re-reviews that immutable SHA/tree. Only an independent PASS may lead to merge planning.
- Theme Single Content Page V1 remains parked until Platform R1 reaches a safe checkpoint.

### Phase R1 — Commercial Purchase & Funding Authority

R1 uses Schema 25 / migration `025_commercial_purchase_funding_authority` / build `phase2a2r1-commercial-purchase-funding-authority-20260920.1`. The branch is `phase-2a2r1-commercial-purchase-funding-authority`. The reviewed history is immutable.

The commercial model that remains locked through correction work is:

- financial settlement is distinct from academic effectiveness;
- an obligation is financially settled only from verified exact amount/currency evidence;
- academic effectiveness additionally requires all lower-sequence obligations to be settled;
- V1 instalments cover 6+6 sessions, with the second instalment allowed to settle early but remaining prerequisite-pending until the first settles;
- effective funded-session allowance is derived, never a mutable counter;
- canonical Term count remains 12 and replacement allowance remains 2;
- Q’s explicit first regular slot is the anchor; never infer “intro + 7 days”;
- current-Term protected capacity claims preserve authority before N concrete occupancy;
- existing capacity authority is never released until a durable successor exists under the same Teacher serialisation;
- Flexible mode protects only the single explicit Q interval if that is the only authoritative slot;
- no live Stripe/provider SDK, recurring renewal, recovery execution, Theme/UI, notification, tax/invoice/accounting, or deployment belongs to R1.

#### Final independent re-review status after Correction Round 1

The independently verified corrected candidate is `186fc5012fe294ea3d91b85aeefdd738448471a0`, tree `40fc2643eacc6811882b552785f55e7db5c04e30`, descendant of original `10fe406...`, with `origin/main` still `1b9d7aa...`.

Confirmed fixed and must not regress:
- R1-BLOCK-002 exact protected-interval succession;
- R1-BLOCK-003 historical satisfied/released intervals no longer block;
- R1-MAJOR-006 claim release uses the Teacher scheduling root.

Still merge-blocking for Correction Round 2:

1. **Account-adjustment acceptance integrity.** Purchase acceptance must load the exact immutable offer-adjustment snapshot and verify source identity/type/kind, percentage or fixed amount, currency, ordering, applied amount and snapshot digest; recompute from the post-promotion running amount; and require recomputed amount = recorded offer amount = immutable snapshot amount before consumption. Add at-rest corruption coverage proving acceptance causes no downstream commercial mutation when the source adjustment is corrupted after offer issuance.
2. **Course/ownership lineage at mutation boundaries.** Build a reusable transaction-aware offer-lineage validator and invoke it at offer reads, payment acceptance before benefit/settlement, capacity handoff before capacity mutation, and Term binding before Term creation. It must lock and validate case/slot/reservation/product/offer plus Student/Teacher/Course continuity. Add corruption tests proving the same malformed offer is independently rejected by payment acceptance, handoff and Term binding with no downstream mutation.
3. **Initially-unattributed provider-evidence race.** In the concurrent unique-insert recovery path, compare the winning row’s `evidence_fact_digest` with the incoming fact. Equal means idempotent; unequal must create/record conflict or ambiguity after rollback and fail explicitly while leaving the original unchanged. Add a deterministic two-worker same-reference/different-facts concurrency test.
4. **Cross-authority enforcement/tests.** Centralise the validators above at all owning mutation paths, add the missing corruption/concurrency proofs, and synchronise R1 documentation/testing tables with the actual expanded coverage.

Reviewer runtime limitation remains non-authoritative: Hamed Cloud lacked a full WP/WP-CLI/DB runtime and therefore performed source/static/contract inspection only where runtime was unavailable. Owner runtime claims are not independent approval.

### Agent board

| Agent | Role / environment | Last completed work | Current status | Next expected task |
|---|---|---|---|---|
| CD / ChatGPT | Architect, sequencer, reviewer, continuity owner | Consolidated current state and identified R1 C2 path | Active through handover, then new CD takes over | Maintain this file; prepare complete C2 handoff; review returned candidate |
| Hamed | Human project owner / messenger | Routed reviews and implementation results | No product decision pending | Paste complete CD instructions to named agent and return complete result |
| DeepSeek / Codex Desktop | Platform implementation owner | R1 original + Correction Round 1, published `186fc501...` | **Start a fresh conversation for C2**; old chat is too context-heavy | Implement/test/publish Correction Round 2 as additive descendant of `186fc501...` |
| Hamed Cloud | Independent Platform reviewer | Final independent re-review of C1: FAIL | Idle | Review exact immutable C2 SHA/tree only after CD pre-review |
| Ina / Ina Cloud | Theme implementation/correction owner | Student Portal V1 and Teacher Portal V1 complete; content-page candidate recovery attempt blocked | Parked | Reconstruct Single Content Page V1 later from authoritative Theme main if original object remains unavailable |

Operational model is manual:
`Hamed ↔ CD → CD prepares one complete copy/paste instruction → Hamed sends it to the specified agent → Hamed returns the complete result → CD critically reviews → CD prepares the next instruction`.
Do not rely on autonomous background orchestration, live-log monitoring, or automatic handoffs.

### Theme current state

Authoritative Theme repository: `Mr-M13/delnavazan-theme`. Authoritative Theme main is `88398f2dd847c320dfd83f3db736d1525cfe3484`.

Completed/closed:
- Homepage and header direction are locked.
- Student Portal V1 candidate `bc891ae401aed0a58c1dea2c5d7587b3046b6773`, tree `810c855...`, Theme 0.5.0, merged/closed.
- Teacher Portal V1 candidate `6ce6718c3038faf892542d033b73d021800d28ee`, tree `8c27144abd3a84d9e8037f3cf92abb7f962742f0`, Theme 0.6.0, independently reviewed/merged/closed.

Single Content Page V1 original implementation was created on branch `feature/single-content-page-v1` from `88398f...` with candidate `1190359e186111df6d50a5bd8266baf148e3e2a2`, tree `d2ebcfbb6a2ed4803aaa0810148749d8181ea4f6`, Theme 0.7.0, but the candidate was never published and the cloud filesystem disappeared before independent review. This was **not a code-review failure**; the immutable candidate became unavailable. A later exhaustive cloud recovery attempt found no usable object/worktree.

Locked reconstruction target remains:
- reusable Article / Policy / General content system;
- existing Delnavazan header/footer and Persian-first RTL visual language;
- readable ~43rem document column;
- H2/H3 structure, lists/tables/quotes/images;
- sticky desktop TOC and collapsible mobile TOC with deterministic stable anchors;
- policy effective/updated metadata, stable anchors, print behaviour, no promotional treatment;
- richer Article hero/category/reading time/related or previous-next treatment;
- simpler General/Help variant;
- standard WordPress authoring, no proprietary page-builder dependency;
- portals excluded;
- responsive/accessibility validation.

Do not waste quota repeatedly searching the old cloud filesystem. Once Platform R1 is safe, reconstruct as a **new** candidate from authoritative Theme main, publish immediately, verify by fresh clone, independently review, correct additively if needed, then package the final merged Theme 0.7.0 as a genuinely downloadable artefact. Staging currently remains on Theme 0.4.6; no production deployment.

### Environment and repository lessons

- Always verify `git remote -v`, expected repository, branch, HEAD, status and `origin/main` before mutation.
- Platform and Theme are separate repositories; never repoint or mix histories.
- Important review candidates must be published before relying on ephemeral cloud filesystems.
- Reviewed failed candidates are immutable evidence; corrections are additive descendants only.
- Owner test reports do not equal independent approval.
- Reviewer runtime unavailability is a limitation, not a pass or fail by itself.
- Do not claim an artefact is downloadable unless an actually accessible file/link exists.
- The local Platform checkout at `/Users/hamed/Desktop/delnavazan/delnavazan-platform` was found on the old Phase-M branch with substantial uncommitted Phase-M work; do not use or clean that checkout for continuity housekeeping.
- This continuity consolidation was therefore prepared in isolated worktree `/Users/hamed/Desktop/delnavazan/continuity-handover-20260921` on branch `docs/continuity-handover-20260921` from `origin/main` `1b9d7aa...`, specifically to avoid contaminating reviewed or historical implementation work.

### Exact next actions

1. Incoming CD reads this entire continuity document, then verifies this Current Checkpoint, Agent Board, authoritative identities and exact next action.
2. Incoming CD prepares one self-contained fenced-code instruction for a **fresh DeepSeek/Codex conversation**.
3. DeepSeek verifies Platform repo and starts from published C1 candidate `186fc501...` on the existing R1 branch; no rewrite.
4. DeepSeek fixes exactly the four remaining C2 areas, preserves the already-passed findings and locked commercial model, runs focused + regression + deterministic concurrency/corruption tests, normal-pushes the new descendant and verifies it from a fresh clone.
5. Hamed returns the complete DeepSeek result to CD.
6. CD critically checks identity, ancestry, scope, tests and claimed fixes before any independent review.
7. CD sends the exact immutable C2 SHA/tree to Hamed Cloud for independent final re-review.
8. Repeat additive correction rounds if required. Only independent PASS permits merge planning.
9. After R1 is safely merged/closed, return to Theme Single Content Page V1 reconstruction with Ina.

**HAMED ACTION REQUIRED:** none at handover time unless CD presents a concrete copy/paste instruction or a genuine product/business-policy choice.

## 11. Continuity Maintenance Protocol

This file is the sole authoritative Delnavazan continuity/handover record. Do not create a competing CD continuity document.

### Immediate updates after durable state changes

Update this document as soon as any of the following occurs:
- independent review PASS or FAIL;
- creation/publication of a new immutable candidate SHA/tree;
- a correction round;
- merge or closeout;
- an architecture or product decision;
- a new blocker, resolved blocker, or accepted deferral;
- deployment or staging state change;
- an agent-role/environment change;
- discovery that invalidates existing continuity.

Do not record every transient debugging step. Capture durable project state, decisions, evidence and reasoning needed to resume safely.

### Checkpoint consolidation

Perform a deliberate consolidation at each natural junction:
- completion of a phase;
- completion of a review/correction cycle;
- before switching the critical path between Platform and Theme;
- before handing CD responsibility to a new chat/session.

At a consolidation checkpoint, reconcile stale wording, remove superseded “current” instructions, refresh the Current Checkpoint and Agent Board, verify authoritative SHAs/trees, blockers/deferrals and exact next action, and preserve useful historical provenance without letting it masquerade as current state.

### Context-health consolidation

Do not wait until the CD chat is nearly full. When the CD conversation becomes long or operationally complex, proactively verify and update this file while the accumulated context is still strong. The goal is for the next CD to inherit a recently consolidated project record rather than reconstructing history from chat fragments.

### Mandatory outgoing-CD handover

Before any CD handover, the outgoing CD must make a final consolidation that explicitly leaves current:
- authoritative repositories and main SHAs;
- active candidate SHA/tree and review status;
- Agent Board;
- blockers and accepted deferrals;
- any Hamed decisions genuinely pending;
- critical-path ordering;
- exact next action.

### Incoming-CD protocol

The incoming CD must:
1. read the entire authoritative continuity document before directing any agent;
2. verify Current Checkpoint, Agent Board, repository identities, candidate identities and Exact Next Action;
3. not reconstruct historical state from assumptions or stale chat snippets;
4. continue maintaining this same document during its tenure;
5. perform its own final consolidation before the next handover.

The intended chain is self-maintaining: CD1 maintains → final consolidation → CD2 reads and verifies → CD2 maintains → next final consolidation.

A sufficient future bootstrap is:
> Read `docs/DELNAVAZAN-CORE-CONTINUITY.md` as the authoritative continuity handover. Resume from **Current checkpoint**, **Agent board** and **Exact next actions**. Do not reconstruct state from assumptions.
