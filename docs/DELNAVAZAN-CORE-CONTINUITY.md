# Delnavazan Core — Living Project Continuity Record

**Continuity snapshot:** 6 September 2026  
**Purpose:** Durable handover for Delnavazan Core. Update this record before moving to a new ChatGPT thread so the next session can recover architecture, decisions, completed work, current implementation state, validation, blockers and the exact next action without reconstructing the entire chat.

> GitHub Issue #4 remains the authoritative implementation contract for the active Core phase. Source code and migrations remain authoritative for implementation. This file is the continuity/handover layer.

## 1. Maintenance rules

- Update this file after each material development slice.
- Record Current State, Decision Register, Phase/PR Register, Validation, Open Risks and Next Action.
- Before a ChatGPT thread becomes unwieldy, update this file and start the next thread from it plus the latest Issue #4/PR state.
- Do not silently rewrite history. Record changed decisions with date/reason.
- Never record cookies, OAuth tokens, nonces, passwords, API secrets, salts or authentication headers.

## 2. Strategic direction

Delnavazan is progressively moving away from architectural dependence on Amelia toward an internally controlled modular education-management platform. The objective is not to clone Amelia feature-for-feature, but to implement Delnavazan's actual business requirements while preserving useful existing work.

The central domain model is Delnavazan-owned **Teacher + Student + Term + Lesson**. Google, Attendance, WhatsApp, Payments, Booking and future portals attach to those entities through defined services rather than independently reconstructing business state.

Existing useful work — including Google OAuth/Meet attendance, WhatsApp/Meta integration, attendance tooling, site enhancements and Hamnavaz — should be migrated/adapted where appropriate rather than discarded without reason.

## 3. Permanent engineering rules

- Architectural migration, not opportunistic rewrite.
- No new Amelia dependencies; isolate temporary legacy dependencies behind adapters where practical.
- New internal relationships use Delnavazan IDs and Delnavazan-owned authority.
- One source of truth for Teacher, Student, Term and Lesson.
- Google, Meta/WhatsApp and Stripe sit behind service boundaries.
- Store authoritative scheduling timestamps in UTC; convert for presentation/business scheduling.
- Version every schema migration and preserve rollback/recovery paths.
- Security and permissions are server-side; secrets never enter issues/logs/test reports.
- Business-critical behaviour requires focused automated/runtime tests, including concurrency where relevant.
- Keep Codex tasks narrow: no later-phase functionality, cosmetic refactors or unrelated cleanup unless approved.
- Codex does not deploy or merge unless explicitly instructed; source review precedes beta/runtime gates.

## 4. Current platform checkpoint

| Item | Current known state |
|---|---|
| Platform candidate | 0.1.0 |
| Validated Phase 2A.1-C build | `phase2a1c-booking-request-validation-20260905.3` |
| Schema | 6 |
| Migrations | 001–006, including `006_booking_request_core` |
| Repository | `Mr-M13/delnavazan-platform` |
| Authoritative implementation contract | GitHub Issue #4 |
| Phase 2A.1-C PR | PR #8 — Booking Request Core |
| Current workstream | Phase 2A.1-D — Booking Request safety/privacy layer |
| Deployment discipline | No unapproved beta/EasyWP deployment; no merge before review/gates |

## 5. Phase 2A.1-C — Booking Request Core

Phase 2A.1-C established the public Booking Request aggregate and read-only administrative inspection surface. The public submission surface is REST, not a visible front-end form.

- Public endpoint: `POST /wp-json/delnavazan-platform/v1/booking-requests`.
- Successful creation: HTTP 201 with `success` plus an opaque request reference.
- Invalid request/allowlist violations: safe HTTP 400 `invalid_request`; unexpected failure uses safe `submission_unavailable` behaviour.
- No public GET/PUT/PATCH/DELETE authority for Booking Requests.
- Mandatory immutable contact snapshot includes approved contact/location fields including country and city.
- Contact-snapshot retention due date: 24 months.
- Requested-time children bounded to maximum 8 entries.
- No valid Intro Course: approved 30-minute duration / 15-minute buffer fallback.
- Valid Intro Course: authoritative Course duration/buffer snapshotted coherently.
- Authoritative Instrument/Course validation occurs within the aggregate transaction with approved lock ordering so stale lifecycle/relationship authority cannot commit.
- Teacher availability/eligibility is not an intake prerequisite.
- Booking Request remains `submitted/unresolved`.
- This phase does not create Students, Lessons, routing, offers, reservations, payments, notifications, Amelia or calendar integration.

### Phase 2A.1-C beta REST/security gate

- Valid public REST submission: **PASS**. Exactly one Booking Request aggregate, one contact snapshot and one requested-time snapshot; no Student binding; coherent Brisbane local/UTC; 30/15 duration/buffer; 24-month retention.
- Mass-assignment using `student_id`: **PASS** — HTTP 400, zero mutation.
- Malformed `communication_language` array: **PASS** — HTTP 400, no private diagnostics, zero mutation.
- Public GET/PUT/PATCH/DELETE: **PASS** — no route/authority, zero mutation.
- Opaque request reference provided no public read/update/cancel/delete/claim authority: **PASS**.
- Admin inspection read-only: **PASS**. Escaping-injection and lower-privilege subchecks were blocked because no safe test surface/session existed; non-critical blockers.
- Final integrity: only expected Booking Request/contact/requested-time rows changed; Student/Lesson/Enrolment/Term and teacher eligibility/availability unchanged.
- Overall: **PASS WITH BLOCKED NON-CRITICAL TEST**.

## 6. Locked product rule — zero teacher matches

Teacher availability must **never** determine whether a student can submit interest in an active Instrument. If no teacher currently matches, Delnavazan keeps the lead. Zero matches is a later operational/manual-matching exception, not a rejected Booking Request.

Future routing may use a `needs_manual_matching` equivalent and notify administration while giving the student a friendly acknowledgement. That workflow is not part of 2A.1-C or the current 2A.1-D safety slice unless Issue #4 explicitly changes scope.

## 7. Phase 2A.1-D — approved design decisions

This slice is the safety/privacy layer around Booking Request intake.

### Idempotency

- `Idempotency-Key` required for public Booking Request creation.
- 24-hour protection window.
- Never persist raw key; store only approved HMAC/keyed digest.
- Same key + same normalized request returns original result without another aggregate.
- Same key + different normalized request rejects safely.
- Requested-time ordering remains semantically meaningful.
- Concurrency must not create duplicate Booking Requests for one idempotency operation.

### Duplicate detection

- Exact normalized matches only for email, mobile and WhatsApp.
- No fuzzy name/location matching.
- Multiple signals may coexist.
- Duplicate indication never blocks valid intake.
- Never automatically merge/bind people or create a Student.
- Review remains human-controlled using approved review outcomes/states.

### Privacy erasure

- Dedicated high-trust erasure permission.
- An unconverted `submitted/unresolved` Booking Request may be erased.
- First terminate/close workflow authority belonging to the request; then remove identifying contact/personal information.
- Preserve only operational facts permitted by the contract and the minimum non-identifying tombstone/audit.
- No automatic 24-month cleanup execution in this phase.

### Abuse protection

- Lightweight server-side rate limiter for anonymous Booking Request intake.
- Use only approved keyed digest of client signal; never persist raw IP/User-Agent.
- Rate limiting is mitigation, not business authority.
- If transient/rate-limit storage is unavailable, fail open so legitimate intake is not blocked.
- Validation and idempotency remain integrity controls.

### Explicit 2A.1-D exclusions

No Teacher routing/reservation, offers/matching workflow, Student creation/binding, Lesson creation, payments, notifications, Amelia integration, Google Calendar/Meet integration or later-phase workflow.

## 8. Current next action

Codex should implement only the approved Phase 2A.1-D contract after re-reading the latest Issue #4 and confirming a clean branch based on synchronized post-2A.1-C `main`.

It should:

1. Add focused schema/migration changes only where required.
2. Preserve transactional atomicity/global lock-order rules.
3. Add source/runtime/concurrency tests for all new behaviour and failure paths.
4. Commit and push the implementation branch.
5. Create/update the appropriate PR.
6. Post `CODEX REPORT — PHASE 2A.1-D` to Issue #4 with branch/head SHA, changed files, migrations/capabilities/API behaviour, idempotency, duplicate detection, erasure, rate limiter, tests/concurrency, deviations and proposed beta validation.
7. **STOP. Do not deploy or merge.**

Ina is currently unavailable. This is not a blocker. CD/ChatGPT performs the first independent source review of the GitHub diff/PR, followed by Hamed running the approved beta/runtime gate. A separate adversarial Codex pass may be commissioned if useful but remains logically separate from implementation.

## 9. Broader architecture / future modules

- Core services/configuration/migrations/permissions/audit/diagnostics.
- Identity: Teachers, Students, administrators.
- Lessons and Terms as central business entities.
- Scheduling, availability and booking.
- Stripe payments, Term automation and future subscriptions.
- Google Calendar/Meet behind a Google service.
- Attendance attached to Delnavazan Lessons.
- WhatsApp/Meta as a Notification channel, not business-logic owner.
- Teacher and Student portals.
- Hamnavaz independent-teacher directory/network.
- Reporting, accounting/payouts and future modules as needed.

## 10. Hamnavaz boundary

Hamnavaz connects students with independent Iranian/Persian music teachers, especially for in-person tuition by location and instrument. It is structurally compatible with the Delnavazan ecosystem but is not the Academy Booking Request workflow.

- Initial role: directory/network; student contacts independent teacher directly.
- No Delnavazan lesson booking, payment collection, commission or teacher calendar management in initial Hamnavaz V1.
- Mobile-first location + instrument search, structured profiles, moderation and verification lifecycle.
- Premium listings, Stripe subscriptions, maps, real-time calendars and AI recommendations remain prepared-for/future features unless separately approved.
- Longer-term Delnavazan may include an Iran physical academy branch.

## 11. Historical architecture note

An August handover documented an earlier Amelia-heavy enrolment architecture and Site Enhancements consolidation. It remains useful history, but later Core decisions supersede it where conflicting. Current direction is Delnavazan-owned identity/lesson/term authority and progressive Amelia exit, not expansion of Amelia dependency.

## 12. Known operational/backlog items

- Attendance historically did not show every appointment reliably; future Core attendance should show every Lesson with explicit attendance/pending/error states.
- Attendance UI historically required horizontal scrolling/inconsistent actions; future UI should use reusable Delnavazan components.
- Google connection state historically sometimes needed refresh before Employee Panel reflected correct state; investigate/migrate without deepening Amelia coupling.
- Payments/Terms target: verified Stripe webhook -> payment authority -> Term activation/generation -> remaining Lessons automatically created.
- Preserve existing Meta Cloud API work, but business modules call a Notification service rather than Meta directly.
- Hamnavaz premium subscription/payment automation remains future/backlog unless reprioritized.

## 13. Continuity update template

Append/update this information whenever a meaningful slice closes:

| Field | Entry |
|---|---|
| Date / phase | `[YYYY-MM-DD | phase]` |
| Authoritative issue/PR | `[Issue # / PR # / branch / head SHA]` |
| What changed | `[short factual summary]` |
| Validation | `[source tests / runtime tests / beta gate / result]` |
| Decisions locked | `[new or changed product/architecture decisions]` |
| Blocked/deferred | `[anything intentionally not completed]` |
| Production/beta state | `[deployed? build/schema/migrations?]` |
| Next exact action | `[single next task and owner]` |

## 14. Thread rollover procedure

1. When the current ChatGPT thread becomes very long, stop starting new implementation slices.
2. Ask ChatGPT to update this continuity record from the current thread plus latest GitHub Issue/PR state.
3. Commit the revised Markdown copy here and optionally retain/export the DOCX snapshot.
4. Start the new ChatGPT thread and reference/upload this record first.
5. Tell the new thread to treat Issue #4/source as authoritative for implementation and this file as the continuity map.
6. Continue from **Current next action**.

## 15. Change register

| Date | Change | Reason | Effect |
|---|---|---|---|
| 16 Aug 2026 | Initial Delnavazan workspace handover | Workspace continuity | Captured earlier Amelia/site-enhancement architecture. |
| Early Sep 2026 | Strategic pivot to Delnavazan Core / progressive Amelia exit | Custom platform preferable to working around Amelia | Teacher/Student/Term/Lesson become Delnavazan-owned architecture. |
| 5 Sep 2026 | Phase 2A.1-C Booking Request Core validated | Establish safe public intake aggregate | REST intake passed beta/security gate with non-critical blocked subchecks. |
| 6 Sep 2026 | Phase 2A.1-D safety/privacy design locked | Protect intake against retries/duplicates/abuse and support controlled erasure | Ready for bounded Codex implementation and independent review. |

## 16. Provenance

This snapshot was compiled from the recovered long Delnavazan/Core chat history supplied on 6 September 2026, the Delnavazan Platform Development and Amelia Exit Plan, the earlier Delnavazan Project Handover, and the confirmed Phase 2A.1-C/2A.1-D checkpoint. Where older documents conflict with later confirmed Core decisions, the later confirmed Core decisions take precedence.
