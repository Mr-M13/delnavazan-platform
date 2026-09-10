# Delnavazan Core — Living Project Continuity Record

**Continuity snapshot:** 11 September 2026
**Purpose:** Durable handover for a new CD/ChatGPT session or implementation agent. Source, migrations and locked domain contracts remain authoritative; this record supplies the current state, boundaries and next action without relying on chat history.

## 1. Current state

| Item | Authoritative state |
|---|---|
| Repository | `Mr-M13/delnavazan-platform` |
| Platform merge main (Phase 2A.2-F) | `c578f137ed537276524a460a9bb4771ec6fbcc4c` |
| Platform | 0.1.0 |
| Schema | 12 |
| Migrations | 001–012 |
| Build identity | `phase2a2f-student-identity-acceptance-authority-20260909.1` |
| Latest completed slice | Phase 2A.2-F — Student Identity & Acceptance Authority Foundation |
| Phase 2A.2-F approved candidate | `f7b8066ce6f45c6bee461cdb13cc2614283988bd` |
| Merge state | PR #15 merged as `c578f137ed537276524a460a9bb4771ec6fbcc4c` |
| Next Platform action | Hamnavaz domain / Platform reconciliation before Phase 2A.2-G or final acceptance/conversion work |
| Current 2A.2-F state | **COMPLETE / MERGED** |

The completed coordination chain is:

1. **2A.2-A** — protected Booking Request advisory assessment.
2. **2A.2-B** — Coordination Case and Candidate Teacher foundation.
3. **2A.2-C** — Teacher Availability Assent.
4. **2A.2-D** — Proposal Family, Teacher-specific Option and immutable Proposal Version.
5. **2A.2-E** — Provisional Acceptance Evidence: immutable, exact Proposal-Version evidence only.
6. **2A.2-F** — Student Identity & Acceptance Authority Foundation: reviewed identity resolution, Student capacity, provenanced principal links, guardian representative grants, and informational eligibility reads.

Schema 12 / `012_student_identity_acceptance_authority` adds bounded internal identity and authority evidence. It does not promote provisional acceptance, perform final acceptance, create an Accepted Service Arrangement, convert a Booking Request, create an Enrolment, assign a Teacher, create Terms or Lessons, or establish payment, notification, calendar or Amelia authority.

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

Any subsequent work must preserve this graph and must not silently advance to conversion, assignment, scheduling, payment, notification, calendar or Amelia work.

## Completed Student Identity & Acceptance Authority Foundation — Phase 2A.2-F

PR #15 merged approved candidate `f7b8066ce6f45c6bee461cdb13cc2614283988bd` as merge commit `c578f137ed537276524a460a9bb4771ec6fbcc4c`. Schema 12 / `012_student_identity_acceptance_authority` establishes:

- human-reviewed, append-only Booking Request-to-Student identity resolution;
- minimal append-only Student capacity classification;
- versioned, provenanced Student-to-WordPress principal links with atomic supersession;
- effective, revocable guardian representative grants with strict intervals and append-preserving expiry;
- a common authority lock order and deterministic attributed concurrency evidence;
- protected internal administration and an informational eligibility read service.

Deliberate exclusions remain: no automatic PII matching, public identity workflow, adult delegation, final acceptance, Accepted Service Arrangement, Booking Request conversion, Enrolment, Teacher Assignment, Term, Lesson, payment, notification, calendar or Amelia authority.

> **BEFORE PHASE 2A.2-G / FINAL ACCEPTANCE-CONVERSION WORK: HAMNAVAZ DOMAIN / PLATFORM RECONCILIATION REQUIRED.**

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

Phase 2A.2-F is **COMPLETE / MERGED** at Schema 12 / migration `012_student_identity_acceptance_authority`, build `phase2a2f-student-identity-acceptance-authority-20260909.1`.

Next dependency: **Hamnavaz domain / Platform reconciliation.** This reconciliation is required before Phase 2A.2-G or any final acceptance/conversion work. It must preserve the locked separation in section 2 and does not itself authorise conversion, Teacher Assignment, scheduling, capacity reservation, payment, notification, calendar or Amelia work.

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
