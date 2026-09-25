# Phase 2A.2-T — Provider-Neutral Payment Execution Seam & Stripe Adapter (implementation record)

**Status:** candidate — awaiting independent review. Not merged, not deployed, not production-authorised.
**Schema:** 029 / migrations `028_payment_execution_seam_provider_adapter` and `029_payment_event_decision_claim_authority`
**Build:** `phase2a2t-payment-execution-seam-stripe-adapter-20260925.10`
**Base:** `main` at the Phase-V candidate tree (Schema 27), strictly additive on top of R1 (Schema 25, authoritative) and R2 (Schema 26, candidate).

This record documents the implementation of the contract in
[PHASE-2A-2T-PAYMENT-EXECUTION-SEAM-STRIPE-ADAPTER-CONTRACT.md](PHASE-2A-2T-PAYMENT-EXECUTION-SEAM-STRIPE-ADAPTER-CONTRACT.md)
(correction round 10, SHA-256 `5c60d7baec801045217919016aa097712e2e215e9d755bb87cd3faaf4e71b343`). The contract
is normative; this record states what was built, what could be executed in this environment, and what
remains deliberately unresolved.

## 1. What this phase owns

1. **Provider-neutral execution seam** — `src/Core/Application/PaymentExecution/`: the port
   (`PaymentExecutionPort`), the durable request/outcome value objects, the sealed dispatch descriptor,
   the one-use dispatch capability, the descriptor preflight verdict, the reference claims, the event
   envelope/translator boundary and the controlled provider registry.
2. **Provider account and object mapping registry** — `PaymentProviderAccountService`,
   `PaymentProviderObjectService`, both digest-only and authority-free.
3. **Execution command authority** — `PaymentExecutionService` plus the immutable command, the
   append-only attempt and result rows and the single mutable dispatch claim.
4. **Stripe adapter** — `src/Integrations/Payment/Stripe/`: protocol translation, normalisation,
   redaction, exact-raw-body signature verification and the single public webhook route.
5. **Webhook intake** — `PaymentEventIntakeService`: durable receipt, verification, event identity,
   the durable per-event decision claim, translation and the bounded ordered R2 consequence.
6. **Secret isolation** — `PaymentSecretVault` plus its own repository; no provider secret is writable in
   this build.
7. **Schema 028/029** — the fifteen additive seam tables with the fail-closed verifier
   `verify_payment_execution_schema()`, plus [C10-1] the one-table decision-claim aggregate of migration
   `029_payment_event_decision_claim_authority` with its own fail-closed verifier
   `verify_payment_event_decision_claim_schema()`.

## 2. Structural properties the candidate must satisfy

- The **first outbound-capable boundary** on the Platform: a provider key, a provider reference and a
  provider secret may exist only as a controlled vocabulary member, a keyed digest and authenticated
  ciphertext. Core holds no open API for a sealed envelope; only an adapter seals or opens one.
- The **first unauthenticated request path that can reach commercial authority** (the webhook). It can
  never change commercial truth directly: translation submits provider-neutral evidence to the existing
  R1 `CommercialPaymentService::ingest()` boundary, which alone decides acceptance and settlement.
- **No command is ever unowned between the two transactions.** Transaction 1 commits the immutable
  command row together with the durable dispatch claim (the sealed descriptor included) before the
  account-root lock is released; the provider call happens outside any transaction, and transaction 2
  writes the single attempt and its terminal result row only through a settlement fenced by the claim's
  generation and token.
- **The envelope is opened exactly once**, in the non-mutating pre-call preflight, and its `ok` verdict
  mints the one-use capability the single invocation consumes. `submit`/`cancel`/`reconcile` never reopen
  a descriptor, so no second open can fail after the claim leaves `claimed`.
- **A descriptor failure precedes any call.** A failing preflight, or a failing Core binding comparison
  under the claim lock, ends a `claimed` claim `released` with a durable `refused`/
  `dispatch_descriptor_unavailable` result — the one claim/result pairing §8.2 rule 5 permits. A
  generation-1 `in_flight` claim whose capability cannot be consumed takes the provably call-free no-call
  abort; a takeover generation never takes it and keeps the ordinary `in_flight` pending behaviour.
- **An event decision is owned by exactly one claim.** [C9-1]/[C9-2] Every decision — the first decision
  of a new event, the first decision of an event that was recorded and then left owing one, the terminal
  consequence of a `pending` decision and a `drain()` — is appended only by the worker that owns the
  event's live decision claim. The claim is taken before any translation or R1/R2 consequence runs, its
  generation and token fence the `claimed → settled` transition inside the transaction that inserts the
  decision (so the next `decision_sequence` is allocated under the claim row's lock), and a delivery that
  cannot own the claim performs no work at all: it re-reads the owner's decision, waits a bounded
  structural window and converges. An expired claim is taken over by exactly one generation, so a worker
  that died holding one never strands the event.
- **Unresolved policy stays unresolved.** `provider_recurring_semantics_unresolved` is recorded and acted
  upon by nothing; there is no advance charge instant, no lapse, no recovery threshold and no refund
  consequence; `subscription` mappings are inert; the refund path records and routes for review with
  `academic_consequence` NULL.

## 3. Deliberate implementation notes

- **The drain re-supplies the delivery.** `payment_provider_events` stores only digests by design, so a
  received event whose translation refused (for example an unset worker principal) is completed by
  `PaymentEventIntakeService::drain($eventId, $rawBody, $headers)`: the body is verified against the
  recorded account's single active signing secret before the event identity is matched, and [C8-4] the
  **full** recorded `event_fact_digest` is then recomputed and required to match, so a drain can never
  manufacture a fact, never translate a changed payload for a recorded event id, and converges on the
  recorded identity exactly once.
- [C8-1] **The route is method-complete and the receipt is byte-exact.** Both webhook routes are
  registered for every HTTP method through one locked method set, so a `GET`/`PUT`/`PATCH`/`DELETE`
  delivery reaches the controlled handler and is receipted as `method_not_allowed` instead of being
  dropped by WordPress routing; and the controller always hands the exact raw bytes it received to the
  receipt, whatever precheck refused the request, so no refusal records a zero-byte or rewritten digest.
- [C8-2] **Attribution is exact.** The event's own provider object must carry exactly one *active*
  mapping (`active_slot = 1`, `state = linked`), and the canonical obligation that mapping owns —
  `canonical_id` for an `obligation` mapping, the mapped collection intent's `obligation_id` for a
  `collection_intent` mapping — must equal the obligation the event resolved. A historical mapping is
  never authority; a mismatch is refused `ambiguous_obligation_attribution` and submits no evidence.
- [C8-3] **A lost insert race converges.** Insert-or-resolve reports whether this worker created the
  event: a worker that meets `UNIQUE provider_event` adopts the winner's row and enters the same
  fact-digest convergence path a read duplicate enters, so identical deliveries converge on one decision
  and materially different facts append the controlled conflict decision instead of a second translation.
- **Operator exception reason.** §8.3 requires an operator-visible exception when an `in_flight` claim
  cannot be reconciled. Phase T may not widen R1's locked `CommercialRule::EXCEPTION_REASONS`, so the
  candidate records that exception under the existing controlled reason `conflicting_payment_evidence`
  (`severity = warning`, `safe_detail = payment_execution_in_flight_pending:<reason>`), and the same fact
  is surfaced directly by `PaymentExecutionReadService::outstandingDispatches()`. No new R1 vocabulary
  was added.
- **Webhook transport.** The controller performs the §9.2 request checks (method, HTTPS, content type,
  body size, request shape) and passes a controlled `precheck_refusal` reason to the intake service, so a
  refusal decided before parsing is still durably receipted and the body is never read to resolve the
  account. [C9-3] The HTTPS check is the complete §9.2 rule: direct TLS, the local development
  environment, or a proxy header the operator has configured this site to trust
  (`PaymentExecutionRule::TRUSTED_PROXY_OPTION`) and that is a member of the locked
  `HTTPS_PROXY_HEADERS` allowlist. The option names headers, never authority — an unset, empty,
  malformed or non-member configuration trusts nothing — so a client can never satisfy the requirement
  with its own `X-Forwarded-Proto`, and only a leftmost `https` in the header's scheme chain counts.
- **Every registered method is receipted, `OPTIONS` included.** [C9-4] WordPress answers `OPTIONS` in
  `rest_handle_options_request()`, a `rest_pre_dispatch` filter, so no route registration can deliver one
  to a callback. `StripeWebhookController::register()` therefore also registers the endpoint's own
  `rest_pre_dispatch` interception ahead of that handler; it answers only an `OPTIONS` delivery to this
  endpoint's two route shapes and runs the same controlled `process()` path a routed
  `GET`/`PUT`/`PATCH`/`DELETE` takes, so an `OPTIONS` delivery is receipted
  `refused`/`method_not_allowed` with its exact raw body and never becomes an event.
- **The event row stays immutable.** [C9-1]/[C9-2] The decision claim is its own aggregate
  (`payment_provider_event_decision_claims`), never a column on `payment_provider_events`, so the
  recorded event identity is still write-once and the claim's lifecycle is never mixed into it. A
  `conflicting_provider_event` row is likewise not the event's decision: whether an event still owes a
  decision is asked of its non-conflict decision timeline.
- **No scheduler.** `drain()` and `redrive()` are explicit, idempotent entry points for a later scheduler
  owned by another phase; no `cron` or `wp_schedule_*` call exists anywhere in `src/`.

## 4. Correction round 8 (independent review of `91bf288` / tree `dec38b70`)

The independent review of the round-7 candidate returned **FAIL — CORRECTION REQUIRED** on four blocking
findings. Each is closed additively in this candidate; no authority, table, capability, policy or provider
call is added, and no previous commit is rewritten.

| # | Blocking finding | Correction in this candidate |
| --- | --- | --- |
| C8-1 | A non-`POST` delivery never reached the controller (WordPress matched the route method first), so it could not be receipted; and every precheck-refused request was receipted against `''` instead of the bytes that arrived, recording a zero-byte/different digest for oversized, invalid-content-type and other refusals. | Both routes declare one locked **all-method** set (`GET, POST, PUT, PATCH, DELETE, HEAD, OPTIONS`), so §9.2's method requirement is decided inside the controlled handler and receipted as `method_not_allowed`; the controller always passes the **exact raw body** to `receive()`, so a refused request records its true `request_digest` and `body_bytes`. |
| C8-2 | Attribution resolved the obligation from metadata alone while `objectIsMapped()` accepted *any* stored mapping of the event object, regardless of canonical kind/id or active state, so a signed event could submit evidence for an obligation the mapped object did not own. | The intake resolves **exactly one active mapping** for the event object (`active_slot = 1`, `state = linked`; historical rows are never authority) and requires the canonical obligation that mapping owns (`obligation` → `canonical_id`; `collection_intent` → its `obligation_id`) to equal the obligation the event resolved. Absent/historical ⇒ `unmapped_provider_object`; multiple candidates or a mismatch ⇒ `ambiguous_obligation_attribution`; no evidence is submitted. |
| C8-3 | Two workers could both observe no event; the loser of `insertEvent()` received the winner's id after the unique-key collision and then unconditionally called `decide()`, appending a second decision and running a second R1/R2 consequence. | Insert-or-resolve now returns `created`; a worker that lost `UNIQUE provider_event` adopts the winner's event and is routed through the **one shared** convergence path (identical facts converge; a `pending` R2 consequence may be completed; materially different facts append the controlled conflict decision). |
| C8-4 | `drain()` validated only the event-reference digest, so a changed but validly signed payload with the same event id bypassed the duplicate fact-digest conflict path and was translated as the recorded event. | `drain()` recomputes the **full** `event_fact_digest` before appending any decision; a mismatch preserves the recorded event unchanged, appends the controlled `conflicting_provider_event` decision with the R1 exception, and submits no evidence. |

`tests/phase-2a2t-contract.php` asserts all four source contracts; `tests/phase-2a2t-webhook-runtime.php`
proves the routing/receipt, attribution and drain behaviour; `tests/phase-2a2t-concurrency-runner.sh` makes
`duplicate_webhook` a real duplicate-delivery race and adds `conflicting_duplicate_webhook` (seventeen
modes total, up from sixteen).

## 5. Correction round 9 (independent review of `5b477b7` / tree `a45d56fb`)

The independent review of the round-8 candidate returned **FAIL — CORRECTION REQUIRED** on four blocking
findings. Each is closed additively in this candidate; no authority, capability, policy or provider call
is added, and no previous commit is rewritten.

| # | Blocking finding | Correction in this candidate |
| --- | --- | --- |
| C9-1 | `convergeExisting()` returned `recorded` when the recorded event had no decision row at all, so an event that was inserted and then left owing its decision (a crash between the event insert and its first decision) was never completed by a redelivery. | The one serialised decision path treats "no decision at all" as an owed decision: an identical redelivery takes the event's decision claim and appends the first decision exactly once. An event owes a decision while its **non-conflict** decision timeline is empty or still `pending`, so `converged = recorded` can no longer mean "undecided forever". |
| C9-2 | Pending-decision retries were not concurrency-safe: two deliveries could both read the same `pending` decision, both run `decide()` and the R1/R2 consequence, and both allocate the same next `decision_sequence`, so one failed on the unique index after performing work. | A durable per-event **decision claim** (`payment_provider_event_decision_claims`) with `UNIQUE event_claim (provider_event_id, active_claim_slot)` is taken before any decision work runs. The winner alone may translate and run the consequence; the claim's generation and token fence the settlement inside the same transaction that inserts the decision; the loser performs no work and converges on what the owner recorded; an expired claim is taken over by exactly one new generation. |
| C9-3 | The HTTPS precheck ignored the declared `PaymentExecutionRule::HTTPS_PROXY_HEADERS` and had no configured-proxy trust path, so a TLS-terminated deployment relying on a trusted `X-Forwarded-Proto: https` had every webhook refused as `https_required`. | The §9.2 transport rule is now complete and input-only: direct TLS, the local environment, or a proxy header that the operator has configured this site to trust and that is a member of the locked allowlist. An unconfigured, empty, malformed or non-allowlisted configuration trusts nothing, so a client can never mark its own delivery secure, and only a leftmost `https` in the scheme chain passes. |
| C9-4 | The route declared `OPTIONS`, but registering a method does not deliver that method to a callback: WordPress answers `OPTIONS` in `rest_handle_options_request()`, a `rest_pre_dispatch` filter that runs before normal route dispatch, so an `OPTIONS` delivery was answered with no receipt at all — breaking the invariant that every registered method is durably receipted. | The endpoint now registers its own `rest_pre_dispatch` interception at priority `1`, ahead of the core handler's `10`. It answers only an `OPTIONS` delivery to this endpoint's two registered route shapes (case-insensitively, with an optional trailing separator) and returns every other filter input untouched, so no other route is affected. A matched delivery runs the same controlled `process()` path as a routed `GET`/`PUT`/`PATCH`/`DELETE`: `refused`/`method_not_allowed`, `405`, the exact raw body digest and byte count, exactly one receipt and no event. |

`tests/phase-2a2t-contract.php` asserts all four source contracts; `tests/phase-2a2t-webhook-runtime.php`
proves the recorded-event recovery, the claim's own shape, the takeover of an abandoned claim, the
transport matrix and the `OPTIONS` interception (registered ahead of the core handler, receipted with the
exact raw body and never answered without one); `tests/phase-2a2t-failure-runtime.php` proves the claim
fence's atomicity; `tests/phase-2a2t-corruption-runtime.php` proves the claim aggregate fails closed; and
`tests/phase-2a2t-concurrency-runner.sh` adds `pending_decision_retry` and `undecided_event_recovery`
(nineteen modes total, up from seventeen).

The same round corrected one disposable-fixture defect the `OPTIONS` proof exposed: the webhook suite's
account selector was 41 characters long, but the route declares the account segment as
`[A-Za-z0-9_-]{1,32}`, so every REST-level delivery in that suite (`GET`, the proxy `POST` and the new
`OPTIONS`) would have been answered `rest_no_route` and receipted nothing — the routing proofs were
testing the fixture, not the endpoint. The selector now fits the declared segment, and the suite asserts
that it does. **Known observation, deliberately not corrected in this bounded round:**
`PaymentProviderAccountService::register()` does not bound `reference_code` to that 32-character segment,
so an operator could register a selector whose webhook URL can never match the route. That is a
registration-validation gap in the account surface rather than a method-receipt defect, and closing it
would add a refusal to the registration command; it is recorded for the owner rather than widened into
this round.

## 5A. Correction round 10 (independent review of `5d8c379` / tree `47f9062e`)

The independent review of the round-9 candidate returned **FAIL — CORRECTION REQUIRED** on two blocking
findings. Each is closed additively in this candidate; no authority, capability, policy or provider call
is added, no table beyond the one aggregate the phase already required is added, and no previous commit
is rewritten.

| # | Blocking finding | Correction in this candidate |
| --- | --- | --- |
| C10-1 | `Migrator.php` / `delnavazan-platform.php`: the new decision-claim table was added by the already-completed migration `028` while the schema identity stayed `28`. An installation that had previously completed `028` skipped its installer and then failed the strengthened verifier, because `payment_provider_event_decision_claims` did not exist and no subsequent migration was scheduled to create it — an unrecoverable state for a database the ledger could have repaired. | The claim aggregate is now migration `029_payment_event_decision_claim_authority`'s own storage and the plugin declares Schema `29`. Migration `028` is restored to its fifteen-table set; its verifier neither requires nor validates the claim table (it tolerates the scheduled sibling instead of failing closed on a repairable state); `verify_payment_event_decision_claim_schema()` runs after `029`, on current-schema verification and unconditionally before the schema option advances to `29`; and `029` is scheduled in the ledger and on the retained/current-schema paths exactly like every other phase migration. A completed-`028` database is repaired by one additive `dbDelta` of the claim table, with no backfill, no inferred claim and nothing settled. |
| C10-2 | `PaymentEventIntakeService.php` / `PaymentProviderRepository.php` / `PaymentExecutionRule.php`: the 120-second claim lease could expire while the original worker was still executing `decide()` and its R1/R2 mutations. A successor could take the claim over and complete the decision, and the original worker then resumed and executed the same R1/R2 work before its fence failure was discovered only during the later append — the token fenced the decision row, not the work it was meant to serialise. | Ownership now covers the whole decision operation. The lease is the **bounded window the owner works inside**: the intake opens it before any decision work runs, and immediately before every work unit — the R1 evidence submission and every R2 command, each one local transaction and never a provider call — it re-proves and renews the window with one fenced conditional statement that requires its own `claimed` generation, token and live slot **and** an unexpired lease, aborting the worker with `DecisionClaimWindowClosed` when the statement affects no row. A renewal can never resurrect an expired window, so a generation whose window closed (its lease lapsed, or exactly one successor generation took the claim over) performs no further decision or R1/R2 consequence work, appends nothing and converges on the current owner's decision, and the append stays fenced by the same generation and token. |

`tests/phase-2a2t-contract.php` asserts both source contracts,
`tests/phase-2a2t-migration-runtime.php` proves the completed-`028` repair rehearsal (028 completed, 029
unrecorded, no claim table → scheduled repair creates the table, records 029 and reaches Schema 29), the
completed-`029`/missing-table fail-closed case and its ledger-owned repair, and
`tests/phase-2a2t-concurrency-runner.sh` adds `stale_owner_after_lease_expiry` (twenty modes): the first
worker takes the claim for an owed decision and lets its own lease lapse while it still owns it, the
second worker takes the claim over (settled generation-2 claim) and completes the decision, and the
resumed first worker is stopped by the gate before its first R1/R2 unit. The suite records every
worker's arrival at the inherited R1/R2 work hooks, so it proves the stale generation reached no work
boundary at all, appended nothing, and left exactly one R1 settlement/evidence, one confirmed intent,
one collected cycle, one decision and one settled generation-2 claim.

The same round corrected three pre-existing self-inconsistencies in the phase's own static contract
suite, which were present in the reviewed tree and would have made `tests/phase-2a2t-contract.php` fail
regardless of this phase's behaviour: the `[C6-1]` source-order assertion compared the preflight and the
lease acquisition in the inverted direction (the code already satisfies its declared order), the "the
dispatch seal must not be the credential vault" scan matched the seal's own docblock mention of the
vault, and the sealing-boundary scan treated the inherited Phase-V `IntegrationSecretService` as an
unexpected boundary. The first is corrected to its declared meaning, the second by wording the docblock
without the class name, and the third by naming that inherited boundary explicitly in the scan. No
assertion was weakened: each still fails on the condition it names.

## 6. Evidence executed in this environment

| Check | Result |
| --- | --- |
| `git diff --check` | pass (no whitespace or conflict-marker defects) |
| Shell syntax of the concurrency runner (`sh -n`) | pass |
| Git object integrity (`git fsck --no-dangling`, `git status`) | pass |
| Source scans: [C10-1] migration `028` creates exactly the fifteen declared seam tables once each, migration `029` creates exactly the one decision-claim table, the two sets are disjoint and their union is the declared sixteen-table set, and `028`'s verifier neither requires nor validates the claim aggregate; [C10-2] the renewal statement requires the owner's own generation, token, live slot and an unexpired lease and can never renew an expired window, and both the R1 submission and every R2 command are gated on it; exact vocabulary strings; no forbidden column pattern; no `wp_schedule_*`/`curl_*`/`wp_remote_*` in Core; `wp_set_current_user` only inside `PaymentExecutionWorkerContext`; only an adapter seals or opens an envelope; the controller names no proxy header outside the locked allowlist | pass |
| Re-emulation of `tests/phase-2a2t-contract.php`'s static assertions in this environment (all 203 assertion sites on this tree: 177 `str_contains` with their polarity and OR-pairs, 7 `substr_count`, 11 `strpos`/ordering assertions, the schema/build identity regexes, the declared-suite file globs, and the two `CREATE TABLE` sets) | pass — no assertion in the suite fails on this tree (PHP cannot run here, so this is a faithful re-implementation of its string and ordering predicates, not the suite itself) |
| Source review of the changed sources (every changed hunk read) | pass — the endpoint's `rest_pre_dispatch` interception is its only participation on that hook, it is registered ahead of the core `OPTIONS` handler, it matches only the endpoint's two route shapes, and it re-uses the one precheck/receipt path instead of adding a second refusal implementation |
| Source scans re-run this round (the two Phase-T migrations together declare exactly the sixteen tables once each — fifteen in `028`, one in `029`; no `wp_schedule_*`/`curl_*`/`wp_remote_*` in Core; `wp_set_current_user` only inside `PaymentExecutionWorkerContext`; no proxy-header literal outside the locked allowlist; exactly one `rest_pre_dispatch` registration in the plugin) | pass |
| Delimiter/quote balance of every changed PHP file (comments and strings stripped, then `()`/`{}`/`[]` balance) | pass (a delimiter sanity check only — **not** `php -l`, which this environment cannot run) |
| `tests/phase-2a2t-contract.php` | **not executed — PHP is unavailable in this environment** |
| `tests/phase-2a2t-migration-runtime.php`, `-runtime.php`, `-webhook-runtime.php`, `-secret-runtime.php`, `-corruption-runtime.php`, `-failure-runtime.php` | **not executed — PHP and the disposable WordPress + MariaDB runtime are unavailable in this environment** |
| `tests/phase-2a2t-concurrency-runner.sh` (all twenty modes) | **not executed — the disposable container runtime is unreachable in this environment** |

The runtime suites are written and wired exactly as the contract's §17 requires, and they were
**not** run here. Running them on the disposable runtime (fresh install, 26→29 upgrade, migration,
webhook, secret, corruption, failure and the full concurrency matrix) is the mandatory acceptance gate
for this candidate and remains outstanding.

## 7. Explicit non-authorisation

No live Stripe API call, credential, webhook secret value, charge, refund, payout, provider dashboard
change, notification delivery, Theme/NIU change, Amelia write/removal, deployment, production access,
production cutover or merge occurred or is authorised by this record. `LIVE_EXECUTION_PROVIDERS` and
`PROVISIONABLE_PROVIDERS` are empty, so the Stripe adapter is provably incapable of an outbound call and
no code path can store a Stripe credential — not even through the capability-authorised vault surface.

## 8. Definition of done — outstanding items

1. Execute every §17 suite on the disposable runtime and record the results.
2. Confirm the Schema 027/028 ledger assumption is recorded either way (Phase S owning 027, or 027
   deliberately skipped) as the contract's §19 prerequisite requires.
3. Independent review of the corrected candidate commit and tree (correction round 9 closes the four
   blocking findings the review of `5b477b72d97a329e7bc02ce041be8958af85fe81` raised).
