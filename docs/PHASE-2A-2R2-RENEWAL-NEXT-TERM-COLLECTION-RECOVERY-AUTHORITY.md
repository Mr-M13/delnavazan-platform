# Phase 2A.2-R2 — Renewal, Next-Term, Recurring Enrolment/Collection, Recovery, Lapse & Refund Authority

**Status:** candidate (implementation). Awaiting independent review; not merged, not deployed.
**Schema:** 26 / `026_renewal_recurring_enrolment_authority`
**Build:** `phase2a2r2-renewal-next-term-collection-recovery-20260923.1`
**Base:** `main` @ `f9df3bfb0fda79fba7dee916c4687464ee67d480` (Schema 25 / R1 merged, closed)
**Governing contract:** [PHASE-2A-2R2-IMPLEMENTATION-CONTRACT.md](PHASE-2A-2R2-IMPLEMENTATION-CONTRACT.md)

## Authority

R2 is an orchestration and cross-Term authority layer above R1. It owns:

- **Recurring Enrolment** — frozen currency/region, mutable audited `manual`/`automatic` collection
  mode, and an `active/suspended/closed` lifecycle.
- **Renewal Cycle** — progression-derived next-Term boundary, manual same-slot guarantee
  (`n = 4` weeks), payment requirement, collection confirmation, next-Term binding, lapse/cancel.
- **Collection Intent** — provider-neutral `manual_payment_required`/`automatic_charge` states only.
- **Recovery** — provider-neutral representation; no automatic lapse while the recovery policy is
  unset.
- **Refund/Reversal review** — record and route for human review; `academic_consequence` stays NULL.
- **Continuous protection** — links a renewal cycle to an R1 protected-capacity claim.

It never reimplements R1 pricing, acceptance, funding derivation, protected claims or Term binding.
Next-Term creation delegates to `CommercialTermFundingService` → Phase-L
`CanonicalTermAuthorityService`; protected-capacity release delegates to `CommercialCapacityService`.

## Authority preservation

Every next-Term purchase uses the existing R1 chain (offer → accepted payment evidence → purchase →
bound entitlement → protected-capacity handoff → Phase-L Term → funding plan). R2 records only the
cross-Term orchestration outcome, and every R2 mutation is serialised on the R1
`commercial_account_roots` row for the owning beneficiary Student, so the fixed R1 lock order
(commercial account root → canonical Enrolment → ascending Teacher → Assignment → per-Teacher
scheduling root) is never inverted. R2 owns no independent lock, no provider column, and no
Term/Lesson/schedule writer.

## Boundary derivation

`RenewalCycleService` derives the next-Term boundary as the first R1 Regular recurring-pattern
occurrence strictly after the current Term's last applicable scheduled interval. Occurrences are
resolved through the canonical pattern authority and the Phase-Q wall-clock rule, so a DST transition
shifts the UTC instant without changing the agreed local class time. The boundary is never
`intro + 7 days`, never inferred from provider data, and fails closed with `boundary_facts_required`
when the current Term schedule or the authorised pattern is unavailable.

The manual guarantee window is the locked `n = 4` weeks resolved in the pattern timezone and recorded
on the cycle as `guarantee_deadline_at`; the advance automatic-charge instant is derived only when
`AUTOMATIC_RENEWAL_CHARGE_LEAD_TIME` is recorded.

## Correction round 1 — derived-fact ownership and terminal-cycle integrity

The first candidate hardened five boundaries that a reader of the contract can check directly. None of
them adds product policy, a provider column, a lock, a Term/Lesson/schedule writer or a delivery path.

1. **Source-Term authority.** `open_cycle` derives the boundary from the recurring enrolment's *own*
   canonical Term history only. An unknown Term, another Enrolment's Term, a legacy Term, an archived
   Term or a cancelled Term fails closed with `canonical_source_term_required` before any fact — or the
   cycle row — exists.
2. **Collection-obligation ownership.** A `manual_payment_required`/`automatic_charge` intent may only
   name an R1 obligation issued to the same beneficiary Student and Course in the cycle's frozen
   currency; anything else fails closed with `collection_obligation_ownership_conflict`, so another
   Student's settled obligation can never be presented as this cycle's collection.
3. **Authoritative first obligation.** `confirm_collection` consumes the cycle's earliest
   non-cancelled collection intent. A later obligation — tranche 2 may settle early — can never collect
   the cycle on its own, and a cancelled intent can never stand in for one.
4. **Terminal protection precondition (§5.6).** A cycle may only lapse or be cancelled once every
   continuous protection it owns has been released through the protected `release_protection` command,
   which delegates to the R1 capacity authority under the same per-Teacher scheduling root. A lapse or
   cancellation with an active protection fails closed with `recurring_protection_release_required`
   instead of releasing R1 capacity as a silent side effect or orphaning a live claim behind a terminal
   cycle. This is the same "release active protection first" rule the contract states for closing a
   recurring enrolment.
5. **Closure guard completeness (§5.1).** Closing a recurring enrolment is blocked by an open
   `open`/`review_required` refund/reversal review on its own canonical Enrolment, in addition to an
   active protection or an open recovery case (`recurring_enrolment_not_closable`).

The Schema 26 verifier now also rejects any table smuggled in beside the phase storage: a table that
claims an R2-owned prefix without being one of the eighteen declared tables fails migration
verification closed (`unexpected renewal storage`).

## Correction round 2 — the successor Term position, cross-commitment ownership and accepted evidence

The second pass corrected one orchestration defect that would have blocked the documented renewal
path and closed three cross-commitment gaps in the same family as correction round 1. It adds no
product policy, no provider column, no lock, no Term/Lesson/schedule writer and no delivery path.

1. **The Phase-L aggregate position is forwarded, never omitted.** Phase L (`CanonicalTermAuthorityService::create`)
   refuses to create a successor Term unless the caller proves the aggregate position it replaces —
   the latest canonical Term of the Enrolment and its terminal state (`closed`/`cancelled`) — and a
   real renewal always has a current Term, so the previous `bind_next_term` shape could never have
   completed. `bind_next_term` now takes the operator's authorised
   `expected_latest_term_id`/`expected_latest_state`, proves from stored facts that the Term is the
   latest non-archived canonical Term of the cycle's *own* Enrolment and that its lifecycle state is
   that terminal state (`renewal_aggregate_position_required`, `renewal_aggregate_position_mismatch`),
   and forwards the pair to R1. R2 still never closes, cancels or guesses a Term: the position is an
   authorised fact supplied to the command, and Phase L remains the sole authority that acts on it.
2. **Next-Term entitlement ownership.** Before delegating, `bind_next_term` proves the entitlement's
   purchase beneficiary Student, Course and currency are the cycle's own
   (`renewal_entitlement_ownership_conflict`), so another commitment's purchase chain can never be
   recorded as this cycle's next Term. After the delegated binding, the created Term's funding plan
   must additionally belong to a canonical Enrolment of the same Student and Course
   (`renewal_term_binding_conflict`).
3. **Continuous-protection claim ownership.** `establish_protection` now re-proves the claim inside
   the serialised transaction (a provisional read is no longer trusted): the adopted claim must be
   `active` and belong to the cycle's own beneficiary Student and Course
   (`recurring_protection_claim_conflict`), and a claim may not be adopted by a second cycle
   (`recurring_protection_already_exists`).
4. **Accepted-evidence settlement (§5.3).** A collection intent — and the cycle it collects — may
   only be confirmed by *accepted* R1 payment evidence for the exact obligation. `RecurringSupport::settlementReason()`
   is the single seam both consumers use: no settlement at all stays `obligation_not_settled`, and a
   settlement recorded against evidence R1 never accepted (`rejected`/`unmatched`) now fails closed
   with `accepted_payment_evidence_required`. This gives the declared
   `CONFIRM_REQUIRES_ACCEPTED_EVIDENCE` rule an actual enforcement point.
5. **Refund-review evidence ownership.** `record_refund_evidence` proves the reviewed purchase
   carries the recorded currency, that the obligation belongs to that purchase, and that the evidence
   is that purchase's and obligation's *accepted* payment evidence
   (`refund_review_evidence_conflict`, `accepted_payment_evidence_required`).

The Schema 26 verifier additionally rejects a raw key or raw reference column on any append-only
phase table (`renewal evidence must stay digest-only`), so the digest-only storage rule of the
contract is enforced by the verifier and not only by application habit.

The shared R2 fixture was also corrected: a funded Enrolment now carries its authoritative
occupancy — the funded Term is activated, receives a Teacher Assignment, one standard canonical
Lesson and that Lesson's first schedule version — because the boundary derivation reads the current
Term's applicable schedule versions. A fixture without those facts could only ever have proved
`boundary_facts_required`, never the derivation itself.

## Pre-implementation prerequisites closed with this candidate

§13 of the governing contract lists prerequisites that gate *execution* of R2. Each is closed here,
and each closure is additive to the corresponding authority rather than a change of it.

1. **Concurrency runner executability (§13.1).** The disposable-runtime host validation of
   `f9df3bf` failed every R1 concurrency mode with `/tests/phase-2a2r1-concurrency-runner.sh:
   Permission denied`, because the runner was committed without its executable bit. Every
   `tests/*-concurrency-runner.sh` — the six that were mode `100644` (`2a2g`, `2a2i`, `2a2o`,
   `2a2p`, `2a2r1` and the new `2a2r2`) and the seven already `100755` — is now committed as
   `100755`, so the documented `phase-2a2r1-concurrency-runner.sh MODE` / `phase-2a2r2-concurrency-runner.sh MODE`
   invocations are executable in a fresh clone.
2. **Phase-P/Phase-Q fixture-order runtime failure (§13.2).** The same host validation failed the
   *R1 failure runtime* with `InvalidArgumentException: teacher_slot_conflict` raised at
   `CanonicalContinuationService::holdFirstRegularSlot()` while the shared fixture built its second
   scenario. The cause is fixture order, not authority: the disposable runtime keeps one database per
   suite, the shared helper `tests/phase-2a2r1-fixture.php` derived every scenario's authorised first
   regular slot from the same fixed formula, and an earlier suite legitimately left *applicable
   canonical Lesson schedules* for the same Teacher (the fixture uses one Teacher throughout) at that
   exact interval — so the next suite's Phase-Q hold collided with a real, correct occupancy. The
   helper now authorises the first whole-week candidate whose interval is free of applicable
   canonical Lesson schedules, effective Phase-Q holds and active protected R1 capacity intervals,
   and fails loudly if none exists. The slot is still an explicitly administrator-authorised record
   (`administrator_attestation`), so no authority is weakened and no suite asserts the slot's offset
   from the introduction.
3. **Stale-documentation debt (§13.3).** `ARCHITECTURE.md`, `MODULE-BOUNDARIES.md`,
   `MIGRATION-STRATEGY.md`, `COMMERCIAL-POLICY-REGISTRY.md`, `DATA-MODEL.md`, `README.md`,
   `CHANGELOG.md`, `DELNAVAZAN-CORE-CONTINUITY.md` and the Phase-R1 phase document no longer describe
   R1 as an unmerged candidate: they record R1 merged and closed at Schema 25 on `main` and the
   Schema 26 R2 candidate above it, with the three renewal/collection class-B policies recorded.
4. **Product sign-off (§13.4)** remains a product-owner decision and is *not* invented here: the four
   deferred decisions stay behind the safe defaults in the table below, and each still gates its own
   later sub-slice.

## Correction round 4 — recovery-state enforcement (§5.4)

Independent review of the round-3 candidate found one correctness gap in the Recovery Case authority:
the aggregate recorded the *representation* of a recovery but did not enforce the two states the
contract fixes for it. Both are now enforced from stored facts, inside the serialised transaction and
after the owning Student's R1 commercial account root is held, so neither can be defeated by a stale
pre-state read or by a racing transition. No product policy, provider column, lock,
Term/Lesson/schedule writer or delivery path was added.

1. **`open` records a failed collection intent.** A pending, submitted, confirmed, recovered or
   cancelled intent can never seed a recovery case, and a cycle that has already reached a terminal
   state (`lapsed`, `cancelled`, `closed`) is never reopened by a recovery record. Both are re-read
   under the lock and fail closed with `collection_intent_not_failed` / `invalid_renewal_cycle_state`,
   so a recovery case can no longer be attached to a collection that never failed or to a cycle whose
   cross-Term authority has already ended. The live-cycle vocabulary is one locked constant
   (`RecurringRule::CYCLE_LIVE_STATES`) shared with the continuous-protection guard.
2. **`recovered` records the exact R1 evidence that settled the obligation.** `mark_recovered` now
   re-proves *accepted* R1 settlement for the case's own collection intent through the same
   `RecurringSupport::settlementReason()` seam the collection commands consume: an obligation with no
   settlement fails closed with `obligation_not_settled`, and a settlement recorded against evidence
   R1 never accepted (`rejected`/`unmatched`) fails closed with `accepted_payment_evidence_required`.
   R2 still writes no settlement, reversal or clawback of its own. The proof runs after the aggregate
   is locked and before the state row is written, so a recovery can never be recorded ahead of — or on
   the strength of — evidence R1 did not accept. A recovery may be recorded from `open` or
   `recovering`: the contract orders the recovery *activity* but never requires an attempt event
   before the settling evidence.

The previous `recovered` source set (`recovering` only) was also wrong in the other direction: it made
the concurrency mode `recovery_vs_satisfaction` impossible, because its pre-state opens the case and
its holder then records the settling evidence — the assertion `the recovering worker must record the
recovery` could never hold. A recovery may now be recorded from `open` or `recovering`, which is what
§5.4 describes: the contract orders the recovery *activity* but never requires an attempt event before
the settling evidence.

`tests/phase-2a2r2-runtime.php` proves both branches — a pending and a submitted intent are refused, a
failed one seeds the case, an unsettled obligation and non-accepted evidence are both refused with
their exact shared reason, an accepted settlement records the recovery and its `PAYMENT_RECOVERED`
intent, and a failed intent of a cancelled cycle is refused without reopening the cycle — while
`tests/phase-2a2r2-contract.php` asserts the enforcement points and the single live-cycle vocabulary.
The failure-injection suite opens the recovery case while its intent is still failed, matching the
enforced order.

## Delegation is convergent, never nested

R2 never opens a transaction around a delegating R1 command. `bind_next_term` and
`release_protection` let R1 own its own transaction (Term + funding plan + entitlement/claim binding,
or the protected-interval release under the same per-Teacher root), then re-open R2's serialised
transaction, verify the durable R1 outcome and record it. A write-boundary failure between the two
halves leaves the R1 fact durable; the retry adopts it rather than duplicating a Term, funding plan,
entitlement or release. `bind_next_term` forwards the caller's authorised aggregate position into the
delegated R1 command, so the convergent retry keeps the same position and converges on the same Term.
`tests/phase-2a2r2-failure-runtime.php` proves both halves of that contract.

## Channel-neutral notification intents

The finalised intents are published through the existing `platform_outbox` seam with a keyed digest
identity: `AUTOMATIC_RENEWAL_UPCOMING`, `AUTOMATIC_RENEWAL_CHARGED`, `AUTOMATIC_RENEWAL_FAILED`,
`MANUAL_RENEWAL_PAYMENT_REQUIRED`, `GUARANTEE_DEADLINE_APPROACHING`, `GUARANTEE_EXPIRED`,
`PAYMENT_FAILED`, `PAYMENT_RECOVERED`, `TERM_LAPSED`, `REFUND_REVIEW_REQUIRED`, `REFUND_RESOLVED`.
These are intent names only: no template, no recipient and no delivery record exists. Delivery belongs
to Phase S; the charge adapter to Phase T.

## Safe defaults (undecided product policy)

| Decision | Seam |
| --- | --- |
| Refund academic consequences | `refund_review_cases.academic_consequence` is never written; reads report `academic_consequence_state = unresolved`; no funded-session clawback or settlement reversal |
| Automatic renewal opt-in/lead time | `AUTOMATIC_RENEWAL_CHARGE_LEAD_TIME` unset means no advance charge instant and no `AUTOMATIC_RENEWAL_UPCOMING`; `collection_mode` is recorded only |
| Recovery/lapse thresholds | `PAYMENT_RECOVERY_POLICY` unset means no automatic lapse and capacity stays protected; an explicit evidenced administrator command is the only lapse path |
| Provider-specific confirmation | Provider-neutral only; confirmation arrives through R1 `commercial_payment_evidence` |

## Out of scope

Stripe SDK/webhook/credentials/live charge, notification delivery/templates, refund academic
consequences, automatic-charge execution, Teacher payout/accounting/tax, portals/public routes,
Theme, deployment and production access.

## Test matrix

| Suite | Required proof |
| --- | --- |
| `tests/phase-2a2r2-contract.php` | Schema 26 identity, build shape, migration/verifier call sites, rule constants, intent set, capability boundaries, no provider column, no FK/CHECK, immutable and digest-only append-only storage, exactly-eighteen-table installer set, the R1 serialization root, the outbox seam, delegation is not nested, the forwardable Phase-L aggregate position, the correction-round-2 ownership guards, and the seven concurrency modes |
| `tests/phase-2a2r2-migration-runtime.php` | Fresh Schema 26; additive 25→26 ledger proof; no backfill/inferred renewal; repeat safety against a retained R1 sentinel; provider/mutable/digest/index/academic/raw-key/raw-reference malformed-storage rejection with exact restoration; a smuggled-table probe; retained-026 stale-version fail-closed; partial capability repair |
| `tests/phase-2a2r2-runtime.php` | `funding_plan_required`; establishment and digest-only replay; audited collection-mode change; derived boundary and price snapshot; manual guarantee; intent lifecycle; the forwarded aggregate position and its fail-closed refusals; next-Term R1 offer/acceptance/binding orchestration; continuous protection and its delegated release; refund review trajectory; recovery/lapse with the policy unset and with explicit evidence; the §5.4 recovery-state enforcement (failed source intent, live cycle, accepted R1 settlement); terminal lifecycle constraints; accepted-evidence settlement; cross-commitment ownership refusals; intent-set conformance |
| `tests/phase-2a2r2-corruption-runtime.php` | Frozen currency/mode, boundary/guarantee, price snapshot, recovery/lapse, cross-Term protection ownership, refund evidence and digest-only command-row corruption all fail closed, are never silently repaired, and converge after exact restoration |
| `tests/phase-2a2r2-failure-runtime.php` | An injected write boundary at every owning mutation rolls back completely; the identical retry converges; both delegating commands keep the durable R1 half and never duplicate it; a duplicate event sequence and a conflicting replay fail closed |
| `tests/phase-2a2r2-concurrency-runner.sh` | `renewal_vs_schedule`, `guarantee_vs_close`, `recovery_vs_satisfaction`, `release_vs_succession`, `mode_change_vs_cycle`, `refund_vs_settlement`, `unrelated_recurring_enrolments`, each with isolated DB state, a gated holder and a coherent-aggregate verifier |
| Adjacent regressions | Phase L/M/N/O/Q/R1 contract and pure suites, `static.php` lint, the schema contract, the fresh-install capability bootstrap and the migration-exception runtime |

### Executed outside the disposable runtime

**No runtime evidence exists yet: none of the §11 suites has been executed against this candidate.**
The authoring sandbox for this round has **no PHP binary, no MySQL/MariaDB and no reachable Docker
daemon** (`docker ps` fails with `permission denied … /Users/hamed/.docker/run/docker.sock`; no
`php`, `mysql` or `mariadb` on the host `PATH`; no loopback port for the disposable stack), so the
WordPress + MariaDB suites are authored and environment-guarded but cannot be executed here. This is
an environment limit of the authoring sandbox, not a result: nothing below may be quoted as a pass.

Executed in that sandbox (reproducible, structural only):

- a lexical balance/lint pass over every PHP file (347 files, 0 unbalanced) that strips comments and
  both quoting styles;
- an installer↔verifier cross-check of migration 026: the 18 declared tables, every
  verifier-required column and every verifier-required index exist in the installer (0 missing);
- a service↔schema cross-check: every column written by the six R2 repositories and their services
  exists in the corresponding installed table (0 mismatches);
- an R2 class/file-name match (22 files) and a repository-wide reference-resolution pass over the R2
  classes and suites (3,579 `new`/`::` references, 0 unresolved repo-level symbols);
- a forbidden-surface scan of the R2 application layer (`stripe`, `wp_remote_`, `wp_mail`, `curl_`,
  `webhook`, `floatval`, `round(`, `INSERT INTO`, `UPDATE `, `DELETE FROM`, cron scheduling,
  parallel Term/Lesson/funding/claim writers: none present);
- `git diff --check` clean, `sh -n` on `tests/phase-2a2r2-concurrency-runner.sh`, and all thirteen
  `tests/*-concurrency-runner.sh` committed as mode `100755`;
- the delivered 403-file working tree hashed content-addressed into a single tree object; that tree
  hash is reported in the task handover rather than embedded here, because embedding it would change
  the tree it describes. The hashing method was validated against the previous round: it reproduces
  that round's host-materialised tree exactly, so the reported value is the tree the host will
  materialise into the candidate commit.

Still outstanding, and required before this candidate may be described as green — the migration,
authority, corruption, failure-injection and concurrency suites for Schema 26 plus the adjacent
Phase L/M/N/O/Q/R1 and pure regressions, all on the disposable local runtime:

```sh
# once per disposable database
DZN_PHASE_2A2J_RUNTIME_TEST=fixture wp eval-file tests/phase-2a2j-fixture.php --user=1
# Phase 2A.2-R2
DZN_PHASE_2A2R2_RUNTIME_TEST=migration   wp eval-file tests/phase-2a2r2-migration-runtime.php  --user=1
DZN_PHASE_2A2R2_RUNTIME_TEST=authority   wp eval-file tests/phase-2a2r2-runtime.php            --user=1
DZN_PHASE_2A2R2_RUNTIME_TEST=corruption  wp eval-file tests/phase-2a2r2-corruption-runtime.php --user=1
DZN_PHASE_2A2R2_RUNTIME_TEST=failure     wp eval-file tests/phase-2a2r2-failure-runtime.php    --user=1
# seven modes, each with isolated DB state
for mode in renewal_vs_schedule guarantee_vs_close recovery_vs_satisfaction release_vs_succession \
            mode_change_vs_cycle refund_vs_settlement unrelated_recurring_enrolments; do
  DZN_PHASE_2A2R2_REPO=<repo> DZN_PHASE_2A2R2_WP_DIR=<wpdir> DZN_PHASE_2A2R2_NET=<net> \
    sh tests/phase-2a2r2-concurrency-runner.sh "$mode"
done
```

The disposable harness under `runtime-r2-recovery/` currently invokes only the R1 suites, and its
`.env` `DZN_REPO_ROOT` still points at another workspace; both must be pointed at this checkout before
the R2 suites and modes can be run. That is owner/host work, not candidate code.
