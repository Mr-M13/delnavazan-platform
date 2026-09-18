# Changelog

All notable changes to the Delnavazan Platform repository are documented here.
Platform phase numbers are independent of Hamnavaz phase numbers.

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
