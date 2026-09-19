# Phase 2A.2-P — Canonical Attendance Intake & Review Authority

Status: **CORRECTION ROUND 1 COMPLETE CANDIDATE — implemented and owner-verified, NOT merged, NOT
deployed, NOT independently re-reviewed.**

Schema 23 (candidate only) / migration `023_canonical_attendance_intake_authority` / build
`phase2a2p-canonical-attendance-intake-20260919.1`, from authoritative base
`7b9aea68fddd651cd614f279f77e78b104885c4d` (Phase O merged and closed at Schema 22).

Phase P owns **intake, evidence and review**. It never owns canonical delivery truth: Phase O owns
delivery/attendance truth and Lesson authority owns completion. Nothing in Phase P writes Phase-O,
lifecycle, obligation or replacement storage directly — every canonical consequence is delegated
through the established application services.

## Review history (bounded, append-only)

| Candidate | Tree | Outcome |
| --- | --- | --- |
| `6a6a4ce8249f79df0371753479587e5d9c1fcd27` | `f17ef71c989ae8449108bb4ccd279b2daec4026a` | Original Phase-P candidate — **independent review FAIL (P-1…P-11)**. |
| `eda3df2a24f5de67dcbba1dbe6e4ae863b325e13` | `53af4c29cfb63dd21aad9fe99b2ef0e1d9d768aa` | Partial correction — fixed P-9 (cutover replay transaction leak), P-11 (continuity contradiction) and only part of P-1 (removed `resolved`/`verified` defaults). **Historical provenance only; not reviewable.** |
| Correction Round 1 (this candidate) | see continuity | Completes every remaining finding. |

No prior commit was amended, rebased, squashed or rewritten. Every correction is a descendant of
`eda3df2a24f5de67dcbba1dbe6e4ae863b325e13`.

## Locked owner policy implemented

| Lock | Implementation |
| --- | --- |
| Ordinary delivery (P-D1) | Ordinary successful delivery is **not** routinely declared by a Teacher, Student or administrator. It settles automatically only from trusted provider evidence that satisfies every condition below; automatic success records explicit Phase-O `delivered` truth. |
| Automatic completion (P-D2) | A settled occurrence converges to Phase-O outcome `delivered` **and** Lesson lifecycle `completed`, through the staged convergence coordinator (below). |
| Temporal window (P-D3) | Qualifying time is `[scheduled start, scheduled end + 900s)`. Pre-class grace is exactly **0**; post-class grace is exactly **15 minutes**. Participation before the start contributes zero. |
| Participant identity (P-D4) | Automatic success requires a **durable, provider-neutral identity mapping** to the canonical Teacher and Student of the exact occurrence. Display names, emails, fuzzy matches and unverified aliases never qualify, and **a caller may never assert its own resolution or verification state**. |
| Multiple devices (P-D5) | Multiple independently verified provider accounts for one canonical participant are unioned; ambiguous, unverified, revoked or mismatched accounts are never unioned and never double-count. |
| Open intervals (P-D6) | Missing leave times never qualify and are never inferred from the scheduled end, grace end, meeting end or current time. |
| Late evidence (P-D7) | After Term closure, valid provider evidence is still persisted, flagged `late_evidence` for administrative review, and never mutates canonical truth automatically. |
| review_required (P-D8) | Phase-P anomaly/review state is separate from Phase-O truth. Phase O `review_required` is published **only** through explicit administrative adjudication; failed assessment never auto-publishes it. |
| Term closure (P-D9) | Unresolved Phase-P cases never block Term closure and survive it for administrative review. No new Term-closure guard was added. |
| Cutover (P-D10) | Phase P is **prospective only**: a cutover instant must still be in the future when it is activated, policies are immutable, and every admitted case stores the exact policy row that admitted it. No historical import, no legacy dual-read, no reinterpretation, no production cutover. |
| Raw evidence (P-D11) | Canonical storage keeps normalized facts, keyed digests and provenance digests only; no raw provider payload is stored. |
| Provider finality (P-D12) | As soon as ≥1200 seconds of trusted qualifying overlap is irreversibly established, settlement may proceed without waiting for meeting finalisation. Later events never retract proven minutes; payload/identity/event-key/canonical conflicts open review, leave a durable conflict receipt and use append-only correction instead of silent rewrite. |

## P-1 — durable provider-neutral participant identity authority

`CanonicalAttendanceIdentityService` is the **only** authority allowed to decide which canonical
Teacher or Student one provider account belongs to. `dzn_canonical_attendance_participant_mappings`
stores `provider_code` + keyed `provider_account_digest` + `participant_role` + `participant_id` +
`state` (`verified` / `unverified` / `revoked`) + provenance/evidence digests + `verified_at` /
`revoked_at` + mapping version. Recording and revoking require
`dzn_manage_canonical_attendance_identity` (administrator only).

Resolution is deterministic and registry-only:

| Registry state for the exact account + role | Result |
| --- | --- |
| exactly one `verified` mapping | resolved to that canonical participant |
| no row at all | `unknown` (`provider_identity_unmapped`) |
| rows but none verified (unverified/revoked) | `unknown` (`provider_identity_unmapped`) |
| more than one distinct `verified` target | `ambiguous` (`participant_ambiguous`) |
| verified target ≠ the occurrence's canonical participant | recorded as a fact (`participant_identity_mismatch`) but never qualifies |

Evidence rows store the derived `participant_identity_state`, `verification_state` and resolved
canonical id; the validator requires the durable registry to justify them. A changed or revoked
registry entry can therefore never silently reinterpret stored evidence — the aggregate fails closed.
The complete aggregate, **including the row just inserted**, is re-validated before any assessment or
canonical settlement.

## P-2 / P-6 — durable case, schedule and version authority

`CanonicalAttendanceSettlementService::settleDelivered()` accepts a **durable case ID**, never a
caller object, and refuses any case that is not already in `settlement_pending`/`settled`
(`attendance_settlement_not_pending`) so a direct call cannot manufacture canonical truth.
`adjudicate()` and `reassess()` hydrate the durable case by ID, re-validate the complete aggregate and
the exact schedule version, and refuse a superseded version with `schedule_version_conflict` before
any canonical consequence. `adjudicate()` additionally requires caller-supplied
`expected_case_version` (from the protected read the administrator reviewed) and fails
`stale_case_version` before any consequence; the expected version is bound into the command identity.

## P-3 / P-4 — context-bound provider replay and durable conflicts

A repeated provider event key must prove full immutable context equality: provider code, account
digest, event key, payload digest, case, Lesson, schedule version, participant role, join/leave
instants, observation instant and provenance digest. An exact duplicate reuses the recorded receipt
and never double-counts; any difference is classified (`changed_payload`, `cross_lesson`,
cross-schedule-version, `cross_context`), durably recorded in
`dzn_canonical_attendance_conflicts`, surfaced as the `duplicate_event_conflict` anomaly in protected
review, and refused with `Idempotency conflict`. The original evidence row is never overwritten and no
canonical truth is touched — including when the conflict arrives after settlement.

## P-5 — exact ingest replay convergence

An exact replay of the **original** ingest command resumes forward settlement whenever durable state
says `settlement_pending`. The convergence path is idempotent: Phase-O `delivered` truth and Lesson
completion each use deterministic keys, the final Phase-P settlement result is recorded exactly once,
and a replay of an already settled occurrence is a pure idempotent replay that appends nothing.
Conflicting canonical truth during recovery fails closed with `canonical_truth_conflict`.
Failure-injection boundaries A (pending, no Phase-O result), B (delivered, Lesson incomplete),
C (completed, final result missing), D (full completion then replay) and E (conflicting truth during
recovery) are all covered by executable runtime tests.

## P-7 — prospective cutover authority

`recordCutoverPolicy()` refuses a backdated instant (`cutover_instant_not_prospective`). Applicability
is deterministic from the occurrence instant: the applicable policy is the most recently activated
policy whose cutover has already passed for that occurrence (never "highest database ID"), and every
admitted case freezes the exact `cutover_policy_id` (with `rule_version` = `canonical_attendance_overlap_v1`,
threshold 1200, pre-grace 0, post-grace 900). A later policy can never reinterpret an earlier admitted
or settled case, and an occurrence before every activated cutover is refused
(`occurrence_before_cutover`). No production cutover was performed.

## P-8 / P-9 — duplicate command recovery and transaction state

Every duplicate-key recovery path recomputes and compares the expected payload and context; no path
passes a null payload. Reusing one command key with a changed payload, another Lesson, another
operation or another expected case version fails closed with `Idempotency conflict`. An exact
cutover-policy replay commits and **closes** its transaction (verified through `@@in_transaction`),
leaves no stale lock, and a later unrelated rollback does not undo the committed policy.

## P-10 — case rule version and policy binding

The validator checks `canonical_attendance_cases.rule_version` against the locked rule version and
validates the case's cutover-policy binding: the policy row must exist, must carry the locked rule
version and frozen constants, and its cutover instant must not be after the occurrence start. The
protected read additionally requires the stored assessment decision to agree with the stored
evidence, so a corrupt or divergent case can never be presented as trusted. Corruption and repair are
covered for case rule version, missing policy, policy threshold/pre-grace/post-grace/rule version,
policy/case applicability, the identity registry, and the decision chain.

## Capabilities (least privilege, per-capability repair)

| Capability | Administrator | `dzn_teacher` |
| --- | --- | --- |
| `dzn_ingest_canonical_attendance_evidence` | yes | no |
| `dzn_submit_own_attendance_claim` | yes | no |
| `dzn_submit_own_delivery_claim` | yes | **yes** |
| `dzn_manage_canonical_attendance_review` | yes | no |
| `dzn_view_canonical_attendance_review` | yes | no |
| `dzn_manage_canonical_attendance_identity` | yes | no |

Capability installation is per capability, not gated on a single grant, so a partially installed
state is repaired deterministically; reserved review/adjudication/identity authority is actively
withheld from the Teacher role. Student self-service remains deferred. Self-claim authority never
implies settlement authority.

## Persistence model (additive, no backfill)

| Table | Purpose |
| --- | --- |
| `dzn_canonical_attendance_cutover_policies` | Immutable prospective cutover boundary plus the frozen rule version and threshold/grace values. |
| `dzn_canonical_attendance_participant_mappings` | Durable provider-neutral provider-account → canonical-participant identity registry (mutable state/version). |
| `dzn_canonical_attendance_cases` | One review aggregate per Lesson + exact canonical schedule version, bound to its exact cutover policy (mutable state/case version). Never delivery truth. |
| `dzn_canonical_attendance_evidence` | Append-only provider intervals and human claims, with keyed event/payload/reference digests and no raw payload. |
| `dzn_canonical_attendance_decisions` | Append-only assessments, settlements, adjudications and closure evidence, including the optional Phase-O result link. |
| `dzn_canonical_attendance_case_anomalies` | Append-only controlled anomaly codes. |
| `dzn_canonical_attendance_conflicts` | Append-only durable conflict receipts for refused provider events. |
| `dzn_canonical_attendance_commands` | Digest-only command evidence for every intake/adjudication/identity command. |

The verifier runs after migration 023, during current-schema verification and unconditionally before
Schema 23 may be activated (including the retained-023/stale-version path), and it rejects any
provider-specific storage, missing registry/conflict indexes, a nullable cutover-policy binding and
any mutable column on an append-only table.

## Assessment rule

`CanonicalAttendanceRule` (`canonical_attendance_overlap_v1`, threshold **1200 seconds**) validates each
interval (rejecting impossible `leave <= join` and open intervals), clips it to the qualifying window,
unions intervals per participant, intersects Teacher and Student unions, merges the intersection and
sums unique simultaneous seconds. Exactly 1200 passes; 1199 does not. Total Teacher duration or total
Student duration alone never qualifies. Failure to prove success identifies no responsibility: it
produces anomaly codes (`teacher_participation_unproven`, `student_participation_unproven`,
`overlap_below_threshold`, `provider_evidence_missing`, `provider_identity_unmapped`,
`participant_ambiguous`, `participant_identity_mismatch`, `duplicate_event_conflict`,
`impossible_interval`, `open_interval`, `late_evidence`, `lesson_cancelled`,
`canonical_outcome_exists`, …) and a `ready_for_review` case — never `student_no_show`,
`teacher_non_delivery`, cancellation, entitlement or debt.

## Settlement convergence (chosen architecture)

Existing Phase-O and Lesson-authority services each own their own transaction, so Phase P does **not**
attempt a nested transaction. Instead:

1. the intake command durably records the assessment decision (state `settlement_pending`) in Phase-P
   storage and commits;
2. `CanonicalAttendanceSettlementService` hydrates the durable case, re-validates the complete
   aggregate, the exact schedule version and the cutover-policy binding, and then records canonical
   `delivered` truth through `CanonicalLessonDeliveryService` using a deterministic key derived from
   the case UID;
3. it completes the Lesson through `CanonicalLessonAuthorityService` using a second deterministic key;
4. it re-reads both canonical facts and only then reports success;
5. the Phase-P settlement result is recorded in a final transaction. If that final write fails, the
   durable `settlement_pending` intent lets the original command's exact replay detect the
   already-established canonical truth and converge without duplication.

Phase P never reports success unless canonical truth is established, and no Phase-P row itself creates
entitlement. Administrative adjudication may delegate `review_required` or `teacher_non_delivery` to
Phase O (which alone creates any academy obligation) and may settle `delivered` for an occurrence that
did not prove automatic success; it never creates a Phase-M replacement, refund, remedial Lesson or
reschedule.

## Lock-order note (correction finding)

Identity-registry and cutover-policy rows are shared by many occurrences and are therefore read
**without row locks**. An earlier draft locked the policy row inside `lockOccurrence`, which serialised
otherwise unrelated intake work and could surface a lock-wait timeout as `occurrence_before_cutover`.
Write serialisation remains per occurrence: enrolment root → Lesson → schedule version → case →
evidence → decisions.

## Explicit non-authority / deferrals

No Google/Meet API, OAuth, webhook or credential; no calendar, WhatsApp, notification, payment,
Stripe, Finance, payroll or renewal authority; no remedial Lesson materialisation or automatic
rescheduling; no Theme or portal change; no public attendance route; no production cutover and no
historical attendance import. Student self-service and the Student Portal contract remain deferred —
Phase P exposes the authority seam they will later use.

## Validation (owner-executed on a disposable WordPress/MariaDB harness)

| Evidence category | Result |
| --- | --- |
| PHP lint (267 PHP files) | pass |
| Shell syntax (`sh -n`, 10 shell harnesses) | pass |
| Source contracts (`tests/*contract*.php`) | **30/30 pass** (the earlier "29" figure predates the Phase-P contract file; the "36" figure was a miscount and must not be quoted) |
| Overlap rule runtime | pass (worked 20-minute example, 19:59 failure, split/duplicate intervals, pre-start and post-grace exclusion) |
| Authority runtime | pass (automatic settlement, below-threshold review, human claims, administrative adjudication, Term closure/late evidence, idempotency and capability, **8-case provider-identity matrix**, cross-context replay, **5 settlement-convergence boundaries**, cutover-policy authority, duplicate-command recovery, case/schedule validation, cutover transaction state, capability repair, no-policy fail-closed, protected read) |
| Corruption runtime | **30 fail-closed cases with repair/recovery** |
| Failure-injection runtime | 3 write boundaries + convergence replay + durable payload conflict |
| Migration runtime | fresh Schema 23, 22→23 rehearsal, repeat, partial capability repair, no backfill, no production cutover, provider-neutral storage, 8 malformed-storage cases, retained-023 fail-closed |
| Concurrency runner | **6 deterministic gated modes**: same event/same payload, same event/changed payload, claim vs adjudication, adjudication vs adjudication (exactly one winner, the other `stale_case_version`), one command key across two Lessons (exactly one winner), unrelated Lessons |
| Adjacent regressions | Phase-O authority/corruption/failure/migration, Phase-N authority/corruption/failure/migration, Phase-M authority/corruption/failure, Phase-M0 authority + protected read, Phase-L authority, Migrator RuntimeException regression |

No deployment, production, NIU, Theme, Amelia or external-system change occurred. Nothing was merged.
