# Phase 2A.2-R1 — Commercial Purchase, Funding & Current-Term Capacity Authority

**Status:** implementation candidate on branch `phase-2a2r1-commercial-purchase-funding-authority`,
from authoritative base `1b9d7aaef0ca21fdb861ccc9da1d15634cb5d843`. **Not merged, not deployed, no
production cutover.** Schema 25 / migration `025_commercial_purchase_funding_authority` / build
`phase2a2r1-commercial-purchase-funding-authority-20260920.1`. The immutable candidate commit and
tree SHAs are recorded in the task closeout, because a commit cannot embed its own hash.

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
| `tests/phase-2a2r1-failure-runtime.php` | pass (9 injected write boundaries, each fully rolled back, each retry converging) |
| `tests/phase-2a2r1-corruption-runtime.php` | pass (offer, settlement, protected interval, entitlement and evidence corruption all fail closed) |
| `tests/phase-2a2r1-concurrency-runner.sh` (`duplicate_evidence`, `handoff_vs_schedule`, `settlement_vs_lesson_seven`, `unrelated_commitments`) | pass |
| `tests/phase-2a2m-runtime.php` (regression) | pass |
| `tests/phase-2a2q-runtime.php` (regression) | **cannot run green on a freshly built disposable runtime, and fails identically on the untouched base `1b9d7aae`** (`assignment_changed` in its own assignment-replacement scenario). Pre-existing environment/fixture-order dependency, not an R1 regression |

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
