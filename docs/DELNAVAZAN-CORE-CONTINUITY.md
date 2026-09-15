# Delnavazan Core — Living Project Continuity Record

**Continuity snapshot:** 15 September 2026
**Purpose:** Durable handover for a new CD/ChatGPT session or implementation agent. Source, migrations and locked domain contracts remain authoritative; this record supplies the current state, boundaries and next action without relying on chat history.

## 1. Current state

| Item | Authoritative state |
|---|---|
| Repository | `Mr-M13/delnavazan-platform` |
| Pre-Phase 2A.2-I main | `f855103c6449ad466ff267c7a9740b56c9ffed66` |
| Platform merge main (Phase 2A.2-I) | `d0bfbe1b808e60e8bcffae0da6c16a4fda1dc928` |
| Platform | 0.1.0 |
| Schema candidate | 16 |
| Migrations candidate | 001–016; latest `016_teacher_assignment_foundation` |
| Build identity candidate | `phase2a2j-teacher-assignment-foundation-20260915.1` |
| Latest completed slice | Phase 2A.2-I — Enrolment Conversion Authority; Phase J is an unmerged candidate |
| Phase 2A.2-I approved candidate | `4e40c5fa7665a8f96fcbf19def2cac719e7bc38b` |
| Merge state | Final independent re-review PASS; merged as `d0bfbe1b808e60e8bcffae0da6c16a4fda1dc928` |
| Final main after continuity update | The commit containing this record (`origin/main`); its exact SHA is recorded in the merge completion report because a Git commit cannot embed its own hash |
| Next Platform action | Independent review of `phase-2a2j-teacher-assignment-foundation`; do not merge or deploy from this handoff |
| Current 2A.2-I state | **COMPLETE / MERGED / CLOSED** |

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
10. **2A.2-J candidate** — Teacher Assignment Foundation: separate current-Teacher authority, retained initial provenance, assignment-specific replacement evidence, atomic lifecycle and protected reads/offboarding.

Main remains authoritative at Schema 15 / Phase I. The unmerged Schema 16 candidate adds Teacher Assignment without creating Terms or Lessons or establishing scheduling, capacity, payment, notification, communication, calendar or Amelia authority.

## Phase 2A.2-J implementation candidate — Teacher Assignment Foundation

The candidate branch starts from authoritative main `7bd4430737f460fdb995bbe05dea272b55641294`. Its exact candidate SHA is reported after commit because a commit cannot contain its own identity. It establishes a first-class Assignment aggregate and explicitly preserves `enrolments.teacher_id` as historical Accepted Service Arrangement context.

Zero Assignment is valid and migration 016 performs no backfill. Initial Assignment is limited to canonical Enrolments in `authorised`, `current`, or `paused` and must use the exact retained final-arrangement Teacher. Historical Availability Assent provenance remains usable even after time passes or the source record later transitions. A different Teacher requires new assignment-specific authenticated-Teacher acceptance or authorised staff attestation; availability, eligibility, proposal history, the historical Enrolment Teacher, and administrator preference do not substitute.

Replacement atomically terminates the predecessor and creates its successor; no future replacement can be staged. Expected-current identity arbitrates stale commands, nullable-slot uniqueness is final database arbitration, and same-key replay/different-key identical-authority behavior is explicit. Lifecycle evidence is append-only and command/evidence references are digest-only. The protected read seam returns only stable identifiers, sequence, state, and assigned time.

Assignment commands lock Enrolment → Teachers with shared locks in ascending ID order → concrete Assignments under `READ COMMITTED`. Teacher archival takes an exclusive Teacher lock and rechecks applicable Assignment state transactionally. J-5 keeps principal/onboarding offboarding independent: it does not archive the Teacher, invalidate existing Assignment authority, or prevent retained-provenance initial and authorised staff-attested replacement authority, but it prevents authenticated-Teacher replacement after principal authority is revoked. Review correction round 1 restricts duplicate arbitration to named indexes plus complete operation-specific authority proof; terminal failures cannot converge from a merely current Assignment. Exhaustive failure/corruption and twenty-mode bidirectional process-concurrency coverage, including six real principal-offboarding races, is committed. The candidate has no Enrolment lifecycle, Term, Lesson, schedule, capacity, payment, notification, calendar, Amelia, Hamnavaz, CRM, Theme, NIU, or production authority.

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
→ later Teacher Assignment
```

These distinctions are deliberate and must not be collapsed:

- Teacher Availability Assent is not Teacher Assignment, capacity reservation or Lesson booking.
- A Proposal creates offer authority only; it is not acceptance or arrangement authority.
- Acceptance is not conversion authority.
- Conversion readiness is neither conversion authority nor a successful conversion.
- A successful conversion creates exactly one Enrolment authorised by the accepted arrangement. It does not create Teacher Assignment.
- Teacher Assignment is later, separate, effective-dated authority.

Any subsequent work must preserve this graph and must not silently advance to Teacher Assignment, scheduling, payment, notification, calendar or Amelia work.

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

Phase 2A.2-H is **COMPLETE / INDEPENDENTLY REVIEWED / MERGED** at Schema 14 / migration `014_canonical_enrolment_foundation`, build `phase2a2h-canonical-enrolment-foundation-20260913.1`.

The next Platform work begins after H. Conversion readiness and explicit conversion authority remain separate future increments: readiness is not authority, and authority is not successful conversion. No canonical Enrolment creation, Teacher Assignment, Term, Lesson, scheduling, payment, notification, calendar or Amelia authority may be inferred from the H foundation.

### Phase 2A.2-I recovery candidate (unmerged)

From authoritative main `f855103c6449ad466ff267c7a9740b56c9ffed66`, branch `phase-2a2i-enrolment-conversion-authority-recovery-2` prepares Schema 15 / `015_enrolment_conversion_authority`, build `phase2a2i-enrolment-conversion-authority-20260914.1`, for independent review. It adds non-bearer readiness, explicit atomic conversion, concrete Student + Course serialization, digest-only idempotency, initial lifecycle evidence and privacy-safe final-arrangement conversion. This candidate is not authoritative until independently reviewed and merged; no deployment or external-system change is implied.

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
| 0.4.2 branch | `codex/increment-0.4.2-art-direction` |
| 0.4.2 candidate | `a4dfc4acd614f049c725f599a4d856ea40f6d517` |
| 0.4.2 status | Source candidate, ready for runtime/visual validation; not merged or deployed |

Theme 0.4.2 implements a contemporary Persian cultural-institution direction: an asymmetric replaceable hero, restrained Persian typography, Custom Logo header/footer, corrected SVG hamburger, six-item instrument folio, human coordination reassurance, presentation-only regional pricing, FAQ before articles, homepage date removal, and responsive/accessibility refinements.

Final Academy-owned or licensed hero/instrument imagery remains required before visual sign-off. The Theme remains presentation only: it has no Platform, pricing-authority, payment, enrolment, Teacher, scheduling, notification, calendar or Amelia business authority.

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

CD is the architecture/orchestration authority: source review, security/privacy/concurrency review, merge readiness, sequencing, dependencies and collision control.

Hamed and Ina are dynamic execution-agent identities, each with Work and Codex available. Do not use obsolete permanent labels such as “Hamed Theme”, “Ina Platform” or equivalent. For substantial work CD specifies Agent → Environment → Model → Reasoning → Parallel yes/no.

Work and Codex share the same usage/credit pool. Avoid duplicate expensive reconnaissance. Prefer Codex when a real runtime/development environment is uniquely useful; prefer Work when source/repository/architecture evidence is sufficient.

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
