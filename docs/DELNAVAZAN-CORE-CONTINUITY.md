# Delnavazan Core — Living Project Continuity Record

**Continuity snapshot:** 17 September 2026
**Purpose:** Durable handover for a new CD/ChatGPT session or implementation agent. Source, migrations and locked domain contracts remain authoritative; this record supplies the current state, boundaries and next action without relying on chat history.

## 1. Current state

| Item | Authoritative state |
|---|---|
| Repository | `Mr-M13/delnavazan-platform` |
| Pre-Phase 2A.2-M main | `c83c1f99557b2cd1f2f81aa82a05177887a468e6` |
| Phase 2A.2-M approved candidate | `f44b502509f5dcb9b5d0281cfda4329407abd3bf`; tree `85a8309bb6179a2061c86245ea1ad9a975c00c2e` |
| Phase 2A.2-M implementation merge | `ed11086ad8ddc65899c3b855248611b1eb9e09a4`; tree `85a8309bb6179a2061c86245ea1ad9a975c00c2e` |
| Platform | 0.1.0 |
| Schema | 21 |
| Migrations | 001–021; latest `021_canonical_lesson_schedule_authority` |
| Build identity | `phase2a2n-canonical-lesson-schedule-authority-20260917.1` |
| Latest completed slice | Phase 2A.2-N — Canonical Lesson Scheduling & Teacher Capacity Authority |
| Merge state | Phase N independent re-review PASS; exact approved tree merged as `08138270f4bd32e5829ef0f5a18a316f6780607d` |
| Closeout baseline | `08138270f4bd32e5829ef0f5a18a316f6780607d`; the docs-only closeout commit is recorded in the task closeout because a commit cannot embed its own hash |
| Current 2A.2-M state | **COMPLETE / INDEPENDENTLY REVIEWED / MERGED / CLOSED** |
| Current 2A.2-N state | **COMPLETE / INDEPENDENTLY REVIEWED / MERGED / CLOSED** |
| Next Platform action after N merge | Requires separate authorisation; attendance, delivery, payment, renewal, notification and provider integration remain outside Platform authority |

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

Main is authoritative at Schema 21 / Phase N. Canonical Lesson scheduling and Teacher capacity authority are now authoritative alongside the completed Enrolment, Term, Assignment and Lesson authority chain. Attendance, delivery, payment, renewal, notification, communication-provider, calendar/Meet, Amelia and payroll authority remain outside Platform scope.

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

Phases 2A.2-A through K are complete. The next planned boundary is **Phase 2A.2-L — Canonical Term Creation & Lifecycle Authority**. Phase L is not implemented or authorised by this record.

Its expected subject area is explicit canonical Term creation and lifecycle command authority, a dedicated capability boundary, idempotency, locking/concurrency and authoritative creation evidence. Those subjects require a separately locked implementation contract; this continuity record does not design them. Canonical Lesson foundation/authority follows later and must not be pulled into Phase L implicitly.

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

### Phase 2A.2-L candidate
Phase L is an unmerged candidate adding explicit canonical Term creation and bounded lifecycle authority with durable idempotency. Canonical Enrolment remains Student + Course; Teacher Assignment remains the sole current-Teacher authority. Term closure/cancellation does not mutate Enrolment, and no Lesson, payment, scheduling or integration authority follows from a Term command.

## Phase 2A.2-M0 implementation candidate

The unmerged M0 candidate advances its package identity to Schema 19 / `019_canonical_enrolment_lifecycle_authority` and build `phase2a2m0-enrolment-lifecycle-authority-20260917.1`. It makes the explicit Enrolment lifecycle graph operational and leaves canonical Lesson authority non-authoritative. The frozen pre-M0 Lesson candidate remains historical evidence and must be resumed separately as Schema 20 after M0 review/merge.
