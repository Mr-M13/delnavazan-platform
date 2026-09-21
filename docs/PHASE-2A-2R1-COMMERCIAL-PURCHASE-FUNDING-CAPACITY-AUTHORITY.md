# Phase 2A.2-R1 — Commercial Purchase, Funding & Current-Term Capacity Authority

**Status:** implementation candidate on branch `phase-2a2r1-commercial-purchase-funding-authority`,
from authoritative base `1b9d7aaef0ca21fdb861ccc9da1d15634cb5d843`. **Not merged, not deployed, no
production cutover.** Schema 25 / migration `025_commercial_purchase_funding_authority` / build
`phase2a2r1-commercial-purchase-funding-authority-20260920.1`. The immutable candidate commit and
tree SHAs are recorded in the task closeout, because a commit cannot embed its own hash.

Correction round 2 is the current candidate: additive descendants of the reviewed correction-round-1
commit `186fc5012fe294ea3d91b85aeefdd738448471a0` that close the four remaining integrity findings
plus behavioural coverage and one documentation item (§0b). Independent re-review of correction
round 2 has **not** occurred, so this state is `CORRECTION ROUND 2 CANDIDATE — AWAITING INDEPENDENT
RE-REVIEW`: not passed, not merged, not deployed.

## 0. Independent review correction round 1

The first independent review of candidate `10fe40618af3de76f2af37a093e611af10cc6ccc` failed on eight
findings. All eight are corrected on descendants of that commit (never rewritten):

| Finding | Correction |
| --- | --- |
| R1-BLOCK-001 promotion redemption + adjustment consumption | Accepted purchase convergence is now the only consumption boundary: the promotion row and the exact snapshotted adjustment are locked and revalidated, both promotion limits are enforced under that lock, exactly one redemption and one `granted → consumed` transition with its append-only event are written, replay converges, and a second purchase can never reuse the adjustment. Offer issuance still consumes nothing. |
| R1-BLOCK-002 exact protected interval → Phase-N occupancy | A protected interval authorises an occupancy only when it IS that interval: Teacher, Term, canonical session, exact UTC bounds, buffered occupied end, schedule timezone and local wall clock. Zero, multiple, time, Teacher, Term, sequence or timezone/wall-clock mismatches fail closed, the exact interval is locked inside the scheduling transaction, and only that one interval is excluded from arbitration. |
| R1-BLOCK-003 historical capacity intervals | Only `claim.state = active` AND `interval.state = protected` blocks. `satisfied` and `released` rows are history; a legitimately released claim is reusable and no longer reported as corruption, while genuinely impossible aggregates still fail closed. |
| R1-BLOCK-004 Course identity continuity | The continuation case, authorised slot, hold, product, offer, recurring pattern, claim and Term Enrolment must agree on one Course. R1 selects or substitutes no Course; every mismatch fails closed before any commercial truth, capacity mutation or Term creation. |
| R1-MAJOR-005 exact provider-evidence convergence | Each evidence row stores a canonical immutable-fact digest (provider key, reference, kind, exact nullable amount and currency, obligation reference, occurrence instant, account digest, attributed offer/obligation). Identical facts converge idempotently; a material difference preserves the original row, never manufactures settlement truth and routes `conflicting_payment_evidence` / `ambiguous_obligation_attribution`, sequentially and concurrently. |
| R1-MAJOR-006 Teacher serialisation for claim release | `releaseClaim()` acquires the canonical per-Teacher scheduling root before reading or mutating the claim intervals, preserving the established global lock order and adding no inverse edge. |
| R1-MAJOR-007 migration + structural integrity | The repository migration policy deliberately avoids foreign keys and CHECK constraints, so equivalent durable enforcement is implemented instead: class-B policy allowlisting with a fail-closed read for a malformed or structural stored key, funding-plan/entitlement/purchase/offer ownership validation, offer↔product↔case Course and Student ownership validation, claim/interval Teacher and aggregate validation, and a corruption suite that exercises every listed cross-authority reference. |
| R1-MAJOR-008 critical test proof | Behavioural runtime, corruption and concurrency coverage asserts durable database state and authority ownership for every corrected invariant (see §5). |

## 0b. Independent review correction round 2

The independent re-review of correction round 1 candidate `186fc5012fe294ea3d91b85aeefdd738448471a0`
(tree `40fc2643eacc6811882b552785f55e7db5c04e30`) failed on four substantive integrity areas plus
behavioural coverage and one documentation item. All six are corrected on additive descendants of that
commit (never rewritten, never rebased, never squashed):

| Finding | Correction |
| --- | --- |
| R1-BLOCK-001 account-adjustment source ↔ immutable snapshot | `CommercialLineageValidator` now locks the authoritative adjustment source and proves it still corresponds exactly to the immutable `commercial_offer_adjustments` row captured at issuance: source id/type, kind, percentage basis points or fixed minor units, currency, application order, the recomputed discount against the **post-promotion running amount**, the offer's recorded contribution and the re-derived canonical snapshot digest (single-sourced with the issuance write). Any mismatch fails closed before redemption, adjustment consumption, evidence acceptance, settlement, purchase, entitlement, funding, capacity or Term truth, and the historical snapshot is never repaired or rewritten. |
| R1-BLOCK-004 authoritative offer lineage | One canonical, transaction-aware `CommercialLineageValidator` proves the stored chain `continuation case → authorised first regular slot → pre-payment hold → product → price → offer → Student → Teacher → Course (canonical Enrolment)`. It is reusable from a caller's transaction, operates on authoritative stored rows, fails closed on missing/mismatched/replaced relationships, locks the aggregate rows where the caller's serialization requires it, and is invoked by the offer read seam, payment acceptance, capacity handoff and Term binding. |
| R1-MAJOR-005 / R1-C1-NEW-001 concurrent initially-unattributed evidence | `recordUnattributed()` now recovers through the one canonical duplicate-evidence boundary: the winner is reloaded and revalidated, the incoming immutable facts are canonicalised with the same `evidence_fact_digest` algorithm, identical facts converge idempotently, and a materially different fact set preserves the winner unchanged and durably routes `conflicting_payment_evidence`. The conflicting fact set is never stored, never attributed as equivalent, and never manufactures settlement truth. |
| R1-MAJOR-007 integrity at owning mutation boundaries | Every owning mutation authority invokes the one canonical aggregate validator immediately before its mutation — payment acceptance, promotion/redemption consumption, account-adjustment consumption, capacity handoff, Term binding and evidence duplicate recovery — instead of relying on request validation, a read endpoint, a later verifier or post-purchase Term creation. No database foreign key or CHECK constraint is added; the established application/verifier integrity architecture is preserved. |
| R1-MAJOR-008 behavioural coverage | The corruption runtime now proves the at-rest account-adjustment mutation and rewritten snapshot, and proves that the same stored Course/ownership corruption is independently rejected by payment acceptance, capacity handoff and Term binding (each exercised directly, each asserting zero downstream mutation and authoritative predecessor state). A new deterministic concurrency mode covers initially-unattributed evidence with conflicting immutable facts, plus its identical-facts convergence counterpart, and the failure runtime injects a failure at the unattributed evidence write boundary. |
| R1-C1-NEW-002 documentation synchronisation | This document's testing matrix now lists the concurrency modes that are actually executed, the continuity record and changelog record correction round 2, and the new initially-unattributed conflicting-facts mode is listed here rather than claimed without execution evidence. |

R1-BLOCK-002 (exact protected interval → Phase-N occupancy), R1-BLOCK-003 (historical commercial
capacity lifecycle) and R1-MAJOR-006 (Teacher-root serialization for claim release) passed the
independent re-review and are deliberately unchanged.

## 1. What this phase owns

R1 establishes the canonical commercial authority that lets a paid Term exist without a payment
provider ever becoming business authority:

- sellable product identity for an Academy Course, with region/currency prices as data;
- bounded promotional discount authority and a distinct account-specific adjustment authority;
- an immutable purchase offer and whole-Term pricing snapshot with a deterministic calculation
  order (base → one promotion → one account adjustment → future credit seam);
- full payment and the V1 two-instalment plan, decomposed into ordered obligations;
- provider-neutral payment evidence, exact obligation settlement and derived academic effectiveness;
- purchase acceptance, a bounded 12-session entitlement, and Term binding through the *existing*
  Phase-L canonical Term authority;
- the canonical Regular recurring pattern derived only from explicit authorised facts;
- current-Term protected capacity with the mandatory succession
  Phase-Q hold → R1 protected claim → Phase-N schedule;
- commercial exceptions/reconciliation and the versioned runtime commercial policy registry.

## 2. Locked decisions (R1-D1 … R1-D12)

| Decision | Locked meaning |
| --- | --- |
| R1-D1 one commitment | A standard paid Term is always one 12-session commitment. Two instalments never create two Terms. |
| R1-D2 funding ≠ commitment | The Term allocation stays 12; commercial funding decides how much of it is currently materialisable. |
| R1-D3 exact money | Money is an integer number of minor units plus an explicit currency. No floats, no conversion, no per-charge discount. |
| R1-D4 immutable snapshot | The payable amount exists only in the Platform-issued offer; the browser, a redirect or a success page can never establish it. |
| R1-D5 whole-Term pricing | Discounts apply to the whole Term exactly once, then the plan divides the authorised amount deterministically. |
| R1-D6 settlement ≠ effectiveness | An obligation may be financially settled while its tranche is academically ineffective until every lower-sequence obligation is settled. |
| R1-D7 no fake funding | Funded sessions are derived only from accepted obligations; no mutable funded-session counter exists. |
| R1-D8 no fake Lessons | An unfunded occurrence is never a Lesson; capacity protection and Lesson existence are separate authorities. |
| R1-D9 explicit recurrence | A Regular pattern comes only from the explicit administrator-authorised first regular slot; no `intro + 7 days` inference exists. |
| R1-D10 succession | An existing capacity authority is never released until its successor is durable under the same per-Teacher scheduling root. |
| R1-D11 no invented policy | The instalment due instant and every class-B policy may remain unset; nothing lapses and no advance charge date exists until policy is configured. |
| R1-D12 provider neutrality | Stripe (or any provider) supplies evidence only; no provider column, SDK, webhook, credential or subscription object exists. |

## 3. Integration with closed authorities (additive only)

| Phase | Additive change | Why it is minimal |
| --- | --- | --- |
| L (Term authority) | `SESSION_ALLOCATION` / `REPLACEMENT_ALLOWANCE` constants; an optional caller-owned transaction and caller capability on `create()`; a protected-capacity guard before Term close/cancel | Term creation, its lifecycle and its command evidence remain Phase-L code; R1 never inserts a Term itself |
| M (Lesson authority) | Standard issuance caps at `min(recorded allocation, funded allowance)` with the distinct `standard_funding_exhausted` reason; replacement issuance reads the recorded Term change allowance | The ≤12 cap and every Phase-M invariant are preserved; a Term with no commercial funding plan behaves exactly as before |
| N (scheduling) | `assertCapacity()` also consults protected claims and the exact authorising interval is satisfied in the same transaction; a canonical schedule release returns the interval to protection | Scheduling stays Phase-N authority; no parallel schedule writer exists |
| Q (continuation) | The pre-payment hold path also refuses an interval already protected by a paid commitment | Phase-Q states, rules, tables and vocabulary are unchanged; the release still uses the existing version-checked seam |

## 4. Capacity succession

1. A verified, accepted payment creates the purchase, its entitlement and the obligation settlement.
2. Under the per-Teacher scheduling root, R1 validates the Phase-Q predecessor hold, resolves the
   committed intervals (Regular: the pattern's intervals; Flexible: only the explicitly authorised
   interval), arbitrates against existing schedules, other holds and other claims, then inserts the
   successor claim **and only then** marks the predecessor hold `released` — in one transaction.
3. Term binding consumes the entitlement and creates the Term through Phase L, records the funding
   plan and binds the claim to the Term. A Term cannot be bound before its successor capacity is
   durable (`commercial_capacity_handoff_required`).
4. Each Phase-N schedule satisfies the interval that authorised it; a released schedule restores
   protection. No interval is ever unowned and no interval is counted twice.

## 5. Testing evidence (owner-executed, disposable WordPress 6.8.3 + MariaDB 11.4.13)

| Suite | Result |
| --- | --- |
| `tests/phase-2a2r1-contract.php` and the full `tests/*contract*.php` set (32 files) | pass |
| `tests/phase-2a2r1-migration-runtime.php` | pass (Schema 25 identity, repeat safety, verifier refuses provider columns and mutable append-only columns) |
| `tests/phase-2a2r1-runtime.php` | pass (full-payment, two-instalment, early tranche 2, duplicate/mismatched/unattributed/refund evidence, succession, flexible, policy registry, exceptions, deferment allowance, Term-close guard) |
| `tests/phase-2a2r1-failure-runtime.php` | pass (10 injected write boundaries — including the initially-unattributed evidence boundary — each fully rolled back, each retry converging) |
| `tests/phase-2a2r1-corruption-runtime.php` | pass (offer, settlement, protected interval, entitlement and evidence corruption all fail closed, plus the correction-round-2 matrix: an account-adjustment source mutated after its snapshot, a rewritten immutable snapshot, and one stored Course/ownership corruption independently rejected by payment acceptance, capacity handoff and Term binding) |
| `tests/phase-2a2r1-concurrency-runner.sh` (`duplicate_evidence`, `handoff_vs_schedule`, `settlement_vs_lesson_seven`, `unrelated_commitments`, `promotion_global_limit`, `conflicting_evidence_replay`, `release_vs_satisfaction`, `unattributed_conflict`, `unattributed_convergence`) | pass (nine executed modes; the last two are the correction-round-2 initially-unattributed evidence races with conflicting and with identical immutable facts) |
| `tests/phase-2a2l-runtime.php`, `tests/phase-2a2m0-runtime.php`, `tests/phase-2a2m-runtime.php`, `tests/phase-2a2n-runtime.php`, `tests/phase-2a2o-runtime.php` (adjacent regressions for the authorities R1 integrates with) | pass |
| `tests/phase-2a2p-runtime.php` (regression) | **cannot run green on a freshly built disposable runtime, and fails identically on the untouched base `1b9d7aae`** (`no_available_source` at its own fixture step). Pre-existing environment/fixture dependency, not an R1 regression |
| `tests/phase-2a2q-runtime.php` (regression) | **cannot run green on a freshly built disposable runtime, and fails identically on the untouched base `1b9d7aae`** (`no_available_source`, and `assignment_changed` in its own assignment-replacement scenario, depending on fixture order). Pre-existing environment/fixture-order dependency, not an R1 regression |

PHP lint (311 files under `src/` and `tests/` plus the plugin file) and shell syntax for the
concurrency runner are clean, `git diff --check` reports nothing, and the whole matrix above was
re-executed from a genuinely fresh clone of `origin` at the pushed candidate commit.

## 6. Exclusions

No Stripe API, Checkout, webhook endpoint, credential or live provider call; no automatic recurring
charge execution; no cross-Term recurring-enrolment authority, renewal guarantee, recovery or lapse;
no renewal-date movement; no notification delivery; no Theme, Student/Teacher/Admin portal or
wp-admin screen work; no gift cards or stored-value ledger; no refund academic consequences; no
Teacher payout, accounting, tax or invoice work; no deployment or production cutover; no change to
any Phase J–Q accepted product decision.

## 7. Deferred to Phase R2

Recurring enrolment (frozen currency, mode, state), renewal cycles with a progression-derived
next-Term boundary, automatic and manual collection modes, the four-week manual same-slot guarantee,
continuous automatic protection, recovery representation, cancellation/lapse and capacity release,
the refund/reversal review trajectory, and the channel-neutral notification intents listed in the
policy registry.

## 8. Known limitations / follow-up

- The sellable product references the Academy Course that the canonical Enrolment is bound to; the
  paid relationship currently inherits the Course identity of the accepted service arrangement.
  Selecting a distinct paid-Term Course is future authority, not an R1 decision.
- Protected-capacity claims are Task-scoped to the current Term. Cross-Term protection is Phase R2.
- `INSTALMENT_DUE_DATE_POLICY` is registered but not interpreted: R1 records an explicit authorised
  due instant when one is supplied and never invents a number.
- Phase-Q `expiry_vs_new_claim` harness hygiene patch (`/tmp/phase2a2q-test-harness-stability.patch`)
  remains outstanding and deliberately untouched by this candidate.
