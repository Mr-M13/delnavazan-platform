# Phase 2A.2-O — Canonical Lesson Delivery & Attendance Outcome Authority

Status: **IMPLEMENTATION CANDIDATE — owner verification complete, awaiting independent review**

Schema 22 / migration `022_canonical_lesson_delivery_attendance_authority` / build
`phase2a2o-canonical-lesson-delivery-attendance-authority-20260918.1`.

Phase O is provider-neutral. It records what actually happened to a canonical Lesson occurrence
without becoming a provider integration, without changing Lesson lifecycle, scheduling, payment,
notification or any external system, and without requiring a manual attendance record for ordinary
delivery.

## Locked product decisions implemented

| Decision | Implementation |
| --- | --- |
| **O-D1 — `completed` means delivered** | Completion is guarded by the delivery authority: a Lesson with an effective `teacher_non_delivery` or `review_required` outcome cannot be completed (`lesson_not_delivered`, `lesson_delivery_review_required`). No new Lesson lifecycle state was added. |
| **O-D2 — exception-based attendance** | Ordinary delivery is the *absence* of an outcome row. Only material or exceptional outcomes are recorded, so no administrator entry is required for a normal Lesson. The evidence boundary is provider-neutral, so a later meeting-evidence source can be recorded without domain coupling. |
| **O-D3 — student no-show** | `student_no_show` is a distinct canonical fact (`delivery_state = delivered`, `attendance_state = student_absent`, `remedy_class = none`). The Lesson is consumed, completion is still permitted, and no replacement entitlement is created. |
| **O-D4 — Teacher/academy non-delivery** | `teacher_non_delivery` records `delivery_state = not_delivered` with `remedy_class = academy_obligation`. Completion is refused, and the resulting replacement is classified `academy_obligation`, which never consumes the Student's two-per-Term allowance. |
| **O-D5 — cancelled vs not delivered** | Cancellation stays a pre-occurrence scheduling/lifecycle fact: the Phase-M replacement-eligible cancellation reason is refused once the recorded occurrence start has elapsed (`occurrence_already_started_use_delivery_outcome`) and refused whenever an outcome already exists (`delivery_outcome_exists`). Post-occurrence non-delivery is a delivery fact, never a cancellation reason. |
| **O-D6 — corrections** | Correction appends a superseding outcome, records actor, reason, evidence, prior outcome and successor, and never mutates the historical row, never reopens Lesson lifecycle and never moves money. A correction that would contradict a completed Lesson fails closed and must use the explicit O-D8 reconciliation command instead (`lesson_completed_reconciliation_required`). |
| **O-D7 — authorised absence is immediate** | Recording is capability-protected administrator authority (`dzn_manage_canonical_lesson_delivery`), takes effect immediately, is idempotent and needs no second approval state. Student/teacher self-service is explicitly not authorised by Phase O. |
| **O-D8 — reconciliation of a completed occurrence** | An explicit authorised append-only command (`reconcile_completion_non_delivery`) invalidates the *effective delivery meaning* of a historical `completed` Lesson when later evidence establishes Teacher/academy non-delivery. The immutable completion lifecycle event is never deleted, rewritten or mutated; the corrected effective canonical truth becomes non-delivery; the academy obligation is established with lineage naming the exact historical completion event; and the Lesson is never reopened, rescheduled or replaced automatically. No Finance, payment or payroll effect is introduced. |
| **O-D9 — advance Teacher/academy cancellation** | A pre-occurrence cancellation recorded with the controlled reason `academy_unavailable` (mapped to lifecycle reason `canonical_lesson_cancelled_academy_unavailable`) establishes an academy obligation without consuming the Student's allowance. A Student-requested cancellation uses the generic reason and never creates an obligation merely because the Lesson was cancelled. No broader cancellation/refund policy is invented. |

## Authority introduced

- one applicable canonical delivery/attendance outcome per canonical Lesson, with append-only
  supersession history;
- immutable evidence provenance: controlled channel, keyed reference digest, observed time,
  recorded time and actor;
- digest-only durable command evidence with complete replay revalidation;
- a protected integrity-checked read seam;
- fail-closed cross-phase guards consumed by Lesson completion, Lesson cancellation reconciliation,
  replacement issuance and canonical scheduling.

## Outcome vocabulary

| `outcome_code` | `delivery_state` | `attendance_state` | `remedy_class` | `occurrence_attempted` | Blocks completion |
| --- | --- | --- | --- | --- | --- |
| `delivered` | delivered | attended | none | 1 | no |
| `student_no_show` | delivered | student_absent | none | 1 | no |
| `teacher_non_delivery` | not_delivered | unknown | academy_obligation | 1 | **yes** |
| `interruption` | delivered | unknown | none | 1 | no |
| `review_required` | unknown | unknown | none | 0 | **yes** |

The profile columns are derived from `outcome_code` and are validated on every read, so a caller can
never choose a favourable delivery, attendance or remedy classification.

## Academy-owed occurrence authority (distinct from the Phase-M replacement)

`{prefix}dzn_canonical_academy_obligations` is its own canonical authority. It is deliberately **not**
a Phase-M `replacement` Lesson:

| | Phase-M replacement (`replacement` Lesson) | Phase-O academy obligation |
| --- | --- | --- |
| Meaning | the Student's bounded make-up allowance | a teaching occurrence the academy owes because it failed to deliver |
| Trigger | pre-occurrence cancellation with the controlled `attested_non_delivery` attestation | effective `teacher_non_delivery` outcome, explicit completion reconciliation (O-D8), or advance `academy_unavailable` cancellation (O-D9) |
| Bounded by | maximum two per Term, one per origin, never restored | one immutable obligation per source occurrence, never capped by the two-per-Term allowance |
| Effect | issues a `replacement` Lesson on an explicit command | records the debt only; no Lesson, no schedule, no notification, no money |
| Term closure | allowance expires when the Term closes | the obligation survives Term closure and remains readable |

The Phase-M replacement cap, eligibility rule and Lesson classification are unchanged by Phase O, and
historical `canonical_lesson_cancelled_replacement_eligible` / `attested_non_delivery` records are never
reinterpreted as Phase-O non-delivery or academy obligations (no semantic backfill).

## Explicit non-authority

No Lesson lifecycle mutation or automatic completion/cancellation; no `scheduled`, `delivered` or
`attended` Lesson lifecycle state; no scheduling creation, revision or release; no automatic
replacement entitlement; no Term allocation change beyond the O-D4 distinction; no payability,
rate, statement or payment logic; no notification, Google/Meta/Amelia/calendar/provider call,
webhook or credential; no public or signed link; no portal; no legacy attendance import or
dual-read; no Amelia dependency.

## Replacement / entitlement reconciliation

Two remedies now exist and are never merged:

1. **Student allowance (`student_allowance`)** — the pre-existing Phase-M path: a standard Lesson
   cancelled before the occurrence with the controlled `attested_non_delivery` reason. It remains
   capped at two per Term and is recorded on the issued replacement Lesson.
2. **Academy obligation (`academy_obligation`)** — a Lesson whose effective outcome is
   `teacher_non_delivery`. It is bounded to one replacement per non-delivered origin by the existing
   origin uniqueness, does not consume the two-per-Term allowance, and is issued only after the
   non-delivered occurrence is terminalised.

The allowance cap counts only `student_allowance` replacements, so a Teacher failure can never
reduce the Student's normal allowance. No Finance consequence, no automatic scheduling and no
generic unlimited replacement mechanism is introduced.

## Temporal honesty

An outcome may only be recorded for a Lesson that was genuinely scheduled and whose recorded
occurrence start has already elapsed. Unscheduled occurrences (`schedule_required_for_delivery_outcome`)
and future occurrences (`occurrence_not_started`) are refused, and a Lesson with a recorded outcome
can no longer be scheduled or rescheduled (`delivery_outcome_exists`). Outcomes are anchored to the
frozen UTC occurrence start taken from the canonical schedule history, and wall-clock/DST behaviour
is inherited from the Phase-N scheduling authority.

## Locking

```text
Student–Course identity root → Enrolment → Term → canonical Lesson → Lesson lifecycle evidence
→ schedule versions → schedule events → delivery outcomes → delivery commands (innermost)
```

The Phase O authority never acquires the per-Teacher scheduling root, so the Phase-N contract rule
("no phase may acquire a Teacher scheduling root and then an earlier Enrolment identity-root chain")
remains satisfied.

## Data model

| Table | Purpose |
| --- | --- |
| `{prefix}dzn_canonical_lesson_delivery_outcomes` | Append-only outcome history; one applicable outcome per Lesson; only `applicable_slot`, `superseded_at` and `superseded_by_outcome_id` may mutate. |
| `{prefix}dzn_canonical_lesson_delivery_commands` | Digest-only durable command evidence with complete intent and result identity. |
| `{prefix}dzn_canonical_academy_obligations` | Immutable academy-owed occurrences, `UNIQUE(source_lesson_id)`, independent of Term lifecycle. |

Every outcome binds the exact canonical schedule version that governed it (`schedule_version_id`), the
frozen occurrence start and the frozen occurrence end, and — where applicable — the immutable
completion event it reconciles (`reconciles_completion_event_id`). The migration is additive: it
creates the three tables only. There is no legacy attendance backfill, no dual-read and no
reinterpretation of legacy `legacy_phase1` Lessons.

## Temporal rule

A final delivery/no-show/non-delivery fact may only become effective **after the governing occurrence
has ended** (`occurrence_not_ended`); the Teacher capacity buffer is explicitly not the waiting
period, so a one-minute occurrence is recordable one minute later rather than sixteen. An occurrence
that has not started is refused (`occurrence_not_started`), an occurrence that was never scheduled is
refused (`schedule_required_for_delivery_outcome`), and a Lesson with a recorded outcome can no longer
be scheduled or rescheduled.

## Provider-neutral evidence boundary (Google Meet future verification)

Evidence is a controlled channel (`staff_record`, `authenticated_platform`, `document_reference`)
plus an opaque keyed digest, an observed time and an actor. Phase O never calls a provider and never
lets provider output become authority merely because it was emitted: a provider-shaped assertion
must travel through the same authorised recording command and the same canonical aggregate gate.
When roadmap Phase 4 adds meeting evidence (teacher joined, student joined, join/leave interval,
overlap, duration), it can be ingested as evidence against the same boundary and reconciled into an
outcome without changing canonical identity or introducing Google coupling into the domain.

## Validation performed (disposable MariaDB 11.4 runtime; no production, NIU, Theme, Amelia or external system contacted)

- 36 static/source contract tests pass, including the Phase O contract; PHP lint and shell syntax checks pass.
- Authority runtime: ordinary delivery without any attendance row, student no-show, exact replay,
  conflicting assertion, correction and supersession lineage, Teacher non-delivery blocking
  completion, academy-funded remedy after the allowance cap is exhausted, advance-cancellation
  versus post-occurrence non-delivery, temporal refusals, review-required resolution, capability
  denial and digest-only evidence.
- Corruption runtime: 18 material fact classes (Lesson relationship, outcome sequence, occurrence
  start and end anchors, schedule-version binding, profile columns, actor, reason, channel, reference
  digest, observed time, applicable relationship, supersession target, provider-shaped evidence,
  command intent and command result) fail closed through the protected read, the guard and Lesson
  completion, then recover.
- Failure injection: outcome insert, supersession, obligation establishment, command evidence and
  lock-boundary injections all roll back completely with no orphan evidence, no half-superseded
  authority, no phantom entitlement and no falsely replayable command.
- Migration runtime: fresh Schema 22, exact Schema 21 → 22 rehearsal, repeat safety, capability
  repair, legacy preservation, no delivery/obligation backfill, six malformed-storage fail-closed
  cases and the retained-022/stale-schema-version activation path.
- Concurrency: 10-mode deterministic gated matrix (outcome vs completion in both orders, outcome vs
  cancellation, schedule release, schedule revision, Enrolment closure, Term closure, correction vs
  correction, duplicate identical assertion, unrelated Lesson independence).
- Phase F–N regression suites re-run as part of the owner matrix.

Per-mode database lock attribution is recorded as `privilege_required` in this environment because
the least-privilege test user cannot read `information_schema.INNODB_TRX`; determinism is enforced by
the committed gate, worker artefacts and verifier instead, exactly as recorded for Phase N.

## Residual and deferred work

Deferred by decision: Google Meet/Calendar ingestion and reconciliation, signed public join/absence
links, Student/Teacher self-service, payability and Finance consequences, notifications, partial
delivery/proration, legacy attendance migration and cutover, retention durations, and any automated
materialisation or scheduling of an owed occurrence. `review_required` outcomes block completion
until an authorised correction resolves them. Materialising an academy obligation into a delivered
future occurrence remains a later explicit command/phase; Phase O records the entitlement only and
never creates or schedules a Lesson for it.
