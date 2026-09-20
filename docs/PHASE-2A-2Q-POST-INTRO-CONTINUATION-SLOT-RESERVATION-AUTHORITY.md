# Phase 2A.2-Q — Post-Intro Continuation & Slot Reservation Authority

Status: **IMPLEMENTATION CANDIDATE — owner-verified, NOT merged, NOT deployed, awaiting independent review.**

Schema 24 (candidate only) / migration `024_post_intro_continuation_slot_reservation_authority` / build
`phase2a2q-post-intro-continuation-slot-reservation-20260920.1`, from authoritative base
`b7378665af1bc3cce935cfae27d346326a1ddc10` (Phase P merged and closed at Schema 23).

Phase Q owns one narrow interval of the Student journey:

```
FREE INTRODUCTORY LESSON → POST-INTRO CONTINUATION DECISION → TEMPORARY HOLD OF THE EXPECTED FIRST REGULAR CLASS SLOT → LATER PAYMENT AUTHORITY
```

It stops before payment. It answers only: does the Student want to continue with this Teacher; if yes,
which exact first regular slot is temporarily held and when does that hold expire; has the Student
asked for another Teacher, asked to be contacted, or chosen not to continue; and has the Teacher
declared that the match needs administrator handling. It never answers whether the Student has paid,
whether a Term or the twelve paid Lessons should exist, or whether the introduction was delivered.

## Source-graph audit (what actually exists in this repository)

| Concept | Authoritative source found |
| --- | --- |
| Introductory Lesson | a `legacy_phase1` Lesson with `lesson_type='introductory'`, Student/Teacher/Course set and **no** Enrolment or Term (created by `LessonService::create`). It is deliberately *not* a canonical Term Lesson. |
| Introductory occurrence | the single current (non-superseded) `lesson_schedule_versions` row that the Lesson's `current_schedule_version_id` points at, with UTC anchors plus `schedule_timezone`, `local_wall_date`, `local_wall_time`. |
| Student authority | Phase-F `student_principal_links` (adult) and `student_acceptance_authority_grants` with `authority_type='guardian_representative'` (minor), scoped by the Student's current acceptance capacity classification. |
| Teacher authority | Phase-J `teacher_principal_links`. |
| Optional canonical lineage | the applicable canonical Enrolment for the same Student/Teacher/Course (recorded only when it already exists), its applicable Teacher Assignment, and any `accepted_service_arrangements` row for the same Student/Teacher/Course. |
| Teacher capacity | Phase-N `canonical_lesson_schedule_versions` plus the per-Teacher `teacher_schedule_roots` serialization device. |

Phase Q invents none of these. It never forces an introductory Lesson into a paid Term, and it never
fabricates a delivery/attendance fact.

## Post-intro eligibility gate

Phase Q binds exactly one authorised introductory occurrence and refuses any decision before that
occurrence's scheduled window has ended (`introductory_occurrence_not_ended`). This proves **only**
that "a post-intro decision may now be recorded" — it does **not** assert that the introductory
Lesson was delivered, attended or completed. No competing attendance authority is created; delivery
and attendance truth remain Phase O's.

## Locked owner decisions Q-D1…Q-D12

| Lock | Implementation |
| --- | --- |
| Q-D1 free/separate intro | The intro Lesson is a legacy `introductory` Lesson with no Term. Phase Q consumes no standard Lesson allocation, no replacement allowance and no academy obligation, and creates none. |
| Q-D2 continuation choices | Exactly four Student dispositions: `continue_with_teacher`, `different_teacher`, `contact_me`, `not_continuing`. |
| Q-D3 continue holds a slot | `continue_with_teacher` establishes a bounded pre-payment capacity hold. It is not a Lesson, schedule, Term, Assent, Assignment, payment, attendance, delivery or enrolment conversion. |
| Q-D4 only the first slot | Exactly one reservation per continuation case (`UNIQUE(continuation_case_id)`), holding only the first expected post-intro occurrence. No block, series or future Lesson materialisation. |
| Q-D5 expiry rule | `expires_at = min(expected_first_regular_start, introductory_occurrence_end + 6 days)`, both exact UTC instants; the six-day bound is a precise duration from the authoritative introductory occurrence boundary. |
| Q-D6 expiry keeps history | Expiry never deletes or rewrites Booking Request, Coordination, Proposal, acceptance, arrangement, identity, Enrolment, Assignment, introductory Lesson history or the decision itself. |
| Q-D7 different Teacher → admin | Records a `student_requested_different_teacher` administrator intervention. No automatic rerouting, proposal or second intro. |
| Q-D8 Teacher unsuitable → admin | The Teacher's own principal records `teacher_match_unsuitable`, releases the hold and suppresses ordinary continuation/payment-ready state (`teacher_match_suppressed`) until a later authorised admin workflow resolves it. No Student rejection, no rereoute, no Student-facing wording. |
| Q-D9 contact me → admin | Records a `student_requested_contact` intervention. No payment authority, no automatic routing. |
| Q-D10 not continuing | Closes the immediate continuation path (`continuation_closed`) and releases any hold. Optional feedback is a controlled reason code only (`teacher_fit`, `schedule`, `price`, `changed_mind`, `technical_experience`, `other`, `prefer_not_to_say`); it is never mandatory and no free text, CRM or analytics surface is created. |
| Q-D11 payment deferred | No payment intent, Stripe object, invoice, receipt, `payment_state`, Term or standard Lesson is created. A later payment authority consumes this state. |
| Q-D12 admin = exception handling | The normal path is intro → continue → first-slot hold → later payment; administrators handle alternative Teacher, contact requests, unsuitable matches and integrity conflicts. |

## Continuation aggregate and decision authority

`dzn_canonical_continuation_cases` is the narrow aggregate: it binds the source introductory Lesson and
its exact occurrence anchors/wall-clock provenance, Student, Teacher, Course, optional Enrolment /
Assignment / accepted-arrangement lineage, the locked rule version, the current decision and an
append-only decision chain. `dzn_canonical_continuation_decisions` is append-only; a case's decision
may legitimately be revised (a Student may change their mind) but `not_continuing` is terminal and a
Teacher suppression blocks ordinary continuation until admin resolution.

Commands are explicit seams rather than generic writes:

| Command | Authority required |
| --- | --- |
| `continueWithTeacher`, `requestDifferentTeacher`, `requestContact`, `stopContinuation` | Student principal authority (adult) or guardian representative grant (minor) — revalidated from Phase F, never inferred from payload, email or display name. |
| `markMatchNeedsAdmin` | `dzn_submit_own_continuation_match_exception` **and** the authoritative Teacher principal link for the Teacher bound to the intro. It grants no Term, Lesson, payment, rejection, reassignment or admin-review authority. |
| `flagIntegrityConflict` | `dzn_manage_canonical_continuation` (administrator only). |

Every command hydrates and validates the complete source graph, locks in canonical order, records
durable digest-only command evidence, and is idempotent: an exact replay converges on the recorded
result, while the same command key with a changed context fails `Idempotency conflict`. The replay
path requires a non-null expected digest and compares unconditionally.

## Slot derivation, hold lifecycle and expiry

The expected first regular slot is derived from the introductory occurrence's own wall-clock
provenance: the same local weekday and time one week later, in the same timezone, using the Course's
authoritative duration and buffer for the occupied interval. The derivation is DST-safe (a wall time
that does not exist in the target zone fails closed as `continuation_slot_not_derivable`).

The reservation freezes `starts_at_utc`, `ends_at_utc`, `occupied_ends_at_utc`, timezone/wall-clock
provenance, `reserved_at`, `expires_at`, rule version and reservation version. Lifecycle is deliberately
minimal: `active` → `expired` → `released`. The later payment slice may consume the state; Phase Q
reserves that vocabulary without implementing the later transition.

Expiry is deterministic and lazy: `expires_at` is frozen at creation and never recomputed from "now";
a reservation is capacity-effective only while `state='active'` **and** `expires_at > now`. A hold
whose expiry has already passed is created `expired` and blocks nothing.

## Real capacity integration (Phase-N separation preserved)

The hold is real Teacher capacity but is never a Lesson schedule. Both authorities take the same
per-Teacher scheduling root, so they serialize on one device in one lock order:

* Phase Q consults Phase-N `canonical_lesson_schedule_versions` before inserting a hold;
* Phase N consults the active Phase-Q hold set through the single narrow seam
  `CanonicalContinuationCapacityAuthority::assertNoActiveHold()`.

A corrupt hold fails closed rather than silently blocking or silently releasing capacity.
`Teacher Availability Assent`, `Teacher Assignment`, Phase-N Lesson schedules and Phase-Q holds remain
four distinct concepts and are not interchangeable.

## Administrator intervention

`dzn_canonical_continuation_interventions` records the controlled, attributable requirement, linked to
the exact continuation case, its owning decision, Student, Teacher, introductory Lesson and reason
classification. A decision that requires human handling must carry its intervention fact: losing or
misattributing it makes the aggregate fail closed. No generic ticketing system is introduced and no
Student-facing wording belongs to this authority.

## Protected read

`CanonicalContinuationReadService` exposes only stable references, the current decision, whether admin
action is required, the single expected slot, its frozen expiry and whether the hold is currently
capacity-effective. It validates the complete aggregate, fails closed on corrupted lineage or
impossible reservation state, returns no guardian evidence internals and no unnecessary PII, and never
mutates state or opens a write transaction (capability `dzn_view_canonical_continuation`).

## Absolute boundaries

Phase Q creates **no** payment intent, Stripe customer/payment object, invoice, receipt,
`payment_state`, Term, standard or replacement Lesson, academy obligation, delivery/attendance
outcome, Phase-P settlement, notification, calendar, Google/Meet/OAuth/webhook/credential, Theme or
Portal change, public REST route or deployment. Provider-neutral only: no external call is made from
any Phase-Q path or from migration 024.

## Migration / schema

`024_post_intro_continuation_slot_reservation_authority` is additive only: it creates the five Phase-Q
tables, performs no backfill, invents no continuation decision or reservation, creates no Term or
Lesson and preserves all existing legacy/canonical data. Only `canonical_continuation_cases` (decision
state) and `canonical_continuation_reservations` (lifecycle state) are mutable; everything else is
append-only. The verifier runs after migration 024, on current-schema verification and unconditionally
before Schema 24 activation (including the retained-024/stale-version path), and rejects
provider/payment-specific columns, missing indexes and any mutable column on an append-only table.
Capability repair is per capability and the Teacher role is explicitly denied administrative
continuation authority.

## Validation (owner-executed on a disposable WordPress/MariaDB harness)

| Evidence category | Result |
| --- | --- |
| Phase-Q contract | pass |
| Authority runtime | pass (continue + real hold, slot derivation, frozen expiry, exact replay and changed-context conflict, admin interventions, not-continuing closure with optional feedback, Teacher match exception, Student/guardian authority, capacity arbitration against both a second hold and canonical Lesson scheduling, expired-hold non-blocking, absolute boundaries) |
| Corruption runtime | **24 fail-closed cases with repair/recovery** |
| Failure-injection runtime | **5 write boundaries** (case, decision, reservation, intervention, command evidence) with complete rollback, no false admin item, no capacity leak and retry convergence |
| Migration runtime | fresh Schema 24, 23→24 rehearsal, repeat, partial capability repair, no backfill, no payment/Term creation, provider-neutral storage, 8 malformed-storage cases, retained-024 fail-closed |
| Concurrency runner | **6 deterministic gated modes**: exact continuation replay, same key + changed decision, two Student decisions, continue vs Teacher exception, competing hold on the same Teacher slot, unrelated Teachers |

Phase-P, Phase-O, Phase-N, Phase-M, Phase-M0 and Phase-L regressions were re-run unchanged. No
deployment, production, Theme/NIU, Google/provider or payment work occurred.
