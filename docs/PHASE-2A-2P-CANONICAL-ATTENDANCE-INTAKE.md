# Phase 2A.2-P — Canonical Attendance Intake & Review Authority

Status: **REVIEW CANDIDATE — implemented and owner-verified, NOT merged, NOT deployed**

Schema 23 / migration `023_canonical_attendance_intake_authority` / build
`phase2a2p-canonical-attendance-intake-20260919.1`, from authoritative base
`7b9aea68fddd651cd614f279f77e78b104885c4d` (Phase O merged and closed).

Phase P owns **intake, evidence and review**. It never owns canonical delivery truth: Phase O owns
delivery/attendance truth and Lesson authority owns completion. Nothing in Phase P writes Phase-O,
lifecycle, obligation or replacement storage directly — every canonical consequence is delegated
through the established application services.

## Locked owner policy implemented

| Lock | Implementation |
| --- | --- |
| Ordinary delivery (P-D1) | Ordinary successful delivery is **not** routinely declared by a Teacher, Student or administrator. It settles automatically only from trusted provider evidence that satisfies every condition below; automatic success records explicit Phase-O `delivered` truth. |
| Automatic completion (P-D2) | A settled occurrence converges to Phase-O outcome `delivered` **and** Lesson lifecycle `completed`, through a staged convergence coordinator (below). |
| Temporal window (P-D3) | Qualifying time is `[scheduled start, scheduled end + 900s)`. Pre-class grace is exactly **0**; post-class grace is exactly **15 minutes**. Participation before the start contributes zero. |
| Participant identity (P-D4) | Automatic success requires pre-verified provider-account mapping to the canonical Teacher and Student of the exact occurrence. Display names, emails, fuzzy matches and unverified aliases never qualify. |
| Multiple devices (P-D5) | Multiple verified intervals for one canonical participant are unioned; unresolved or heuristic identities are never unioned and never double-count. |
| Open intervals (P-D6) | Missing leave times never qualify and are never inferred from the scheduled end, grace end, meeting end or current time. |
| Late evidence (P-D7) | After Term closure, valid provider evidence is still persisted, flagged `late_evidence` for administrative review, and never mutates canonical truth automatically. |
| review_required (P-D8) | Phase-P anomaly/review state is separate from Phase-O truth. Phase O `review_required` is published **only** through explicit administrative adjudication; failed assessment never auto-publishes it. |
| Term closure (P-D9) | Unresolved Phase-P cases never block Term closure and survive it for administrative review. No new Term-closure guard was added. |
| Cutover (P-D10) | Phase P is prospective only, using a durable persisted cutover instant (`canonical_attendance_cutover_policies`). No historical import, no legacy dual-read, no reinterpretation. No production cutover was performed. |
| Raw evidence (P-D11) | Canonical storage keeps normalized facts, keyed digests and provenance digests only; no raw provider payload is stored. |
| Provider finality (P-D12) | As soon as ≥1200 seconds of trusted qualifying overlap is irreversibly established, settlement may proceed without waiting for meeting finalisation. Later events never retract proven minutes; payload/identity/event-key/canonical conflicts open review and use append-only correction instead of silent rewrite. |

## Assessment rule

`CanonicalAttendanceRule` (`canonical_attendance_overlap_v1`, threshold **1200 seconds**) validates each
interval (rejecting impossible `leave <= join` and open intervals), clips it to the qualifying window,
unions intervals per participant, intersects Teacher and Student unions, merges the intersection and
sums unique simultaneous seconds. Exactly 1200 passes; 1199 does not. Total Teacher duration or total
Student duration alone never qualifies. Failure to prove success identifies no responsibility: it
produces anomaly codes (`teacher_participation_unproven`, `student_participation_unproven`,
`overlap_below_threshold`, `provider_evidence_missing`, `participant_ambiguous`, `impossible_interval`,
`open_interval`, `late_evidence`, `lesson_cancelled`, `canonical_outcome_exists`, …) and a
`ready_for_review` case — never `student_no_show`, `teacher_non_delivery`, cancellation, entitlement or debt.

## Persistence model (additive, no backfill)

| Table | Purpose |
| --- | --- |
| `dzn_canonical_attendance_cutover_policies` | Durable prospective cutover boundary plus the frozen rule version and threshold/grace values. |
| `dzn_canonical_attendance_cases` | One review aggregate per Lesson + exact canonical schedule version (the only mutable table: state and case version). Never delivery truth. |
| `dzn_canonical_attendance_evidence` | Append-only provider intervals and human claims, with keyed event/payload/reference digests and no raw payload. |
| `dzn_canonical_attendance_decisions` | Append-only assessments, settlements, adjudications and closure evidence, including the optional Phase-O result link. |
| `dzn_canonical_attendance_case_anomalies` | Append-only controlled anomaly codes. |
| `dzn_canonical_attendance_commands` | Digest-only command evidence for every intake/adjudication command. |

The verifier runs after migration 023, during current-schema verification and unconditionally before
Schema 23 may be activated (including the retained-023/stale-version path), and it rejects any
provider-specific storage.

## Capabilities

`dzn_ingest_canonical_attendance_evidence` (provider intake), `dzn_submit_own_attendance_claim` and
`dzn_submit_own_delivery_claim` (own-claim submission; the Teacher claim capability is granted to the
`dzn_teacher` role, the Student claim capability is not granted to any self-service role), and
`dzn_manage_canonical_attendance_review` / `dzn_view_canonical_attendance_review` (administrative
adjudication and protected read). Self-claim authority never implies settlement authority.

## Settlement convergence (chosen architecture)

Existing Phase-O and Lesson-authority services each own their own transaction, so Phase P does **not**
attempt a nested transaction. Instead:

1. the intake command durably records the assessment decision (state `settlement_pending`) in Phase-P
   storage and commits;
2. `CanonicalAttendanceSettlementService` records canonical `delivered` truth through
   `CanonicalLessonDeliveryService` using a deterministic key derived from the case UID;
3. it then completes the Lesson through `CanonicalLessonAuthorityService` using a second
   deterministic key;
4. it re-reads both canonical facts and only then reports success;
5. the Phase-P settlement result is recorded in a final transaction. If that final write fails, the
   durable `settlement_pending` intent lets a retry detect the already-established canonical truth
   and converge without duplication.

Phase P never reports success unless canonical truth is established, and no Phase-P row itself creates
entitlement. Administrative adjudication may delegate `review_required` or `teacher_non_delivery` to
Phase O (which alone creates any academy obligation) and may settle `delivered` for an occurrence that
did not prove automatic success; it never creates a Phase-M replacement, refund, remedial Lesson or
reschedule.

## Explicit non-authority / deferrals

No Google/Meet API, OAuth, webhook or credential; no calendar, WhatsApp, notification, payment,
Stripe, Finance, payroll or renewal authority; no remedial Lesson materialisation or automatic
rescheduling; no Theme or portal change; no public attendance route; no production cutover and no
historical attendance import. Student self-service and the Student Portal contract remain deferred —
Phase P exposes the authority seam they will later use.

## Validation

Contract (`tests/phase-2a2p-contract.php`), overlap rule (`tests/phase-2a2p-overlap-runtime.php`,
including the worked 20-minute example, 19:59 failure, split/duplicate intervals, pre-start exclusion
and post-grace exclusion), authority runtime, 15-case corruption runtime, failure injection with
convergence replay, migration runtime (fresh Schema 23, 22→23 rehearsal, repeat, retained-023
recovery, five malformed-storage cases, provider-neutrality) and a five-mode gated concurrency runner
(identical provider event, changed payload, claim vs adjudication, adjudication vs adjudication,
unrelated Lessons). All run on a disposable MariaDB/WordPress harness; Phase O and Phase N migration
runtimes and all 30 contract files were re-run on the Phase-P tree.
