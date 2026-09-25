# Phase 2A.2-T — Provider-Neutral Payment Execution Seam & Stripe Adapter (implementation record)

**Status:** candidate — awaiting independent review. Not merged, not deployed, not production-authorised.
**Schema:** 028 / migration `028_payment_execution_seam_provider_adapter`
**Build:** `phase2a2t-payment-execution-seam-stripe-adapter-20260924.8`
**Base:** `main` at the Phase-V candidate tree (Schema 27), strictly additive on top of R1 (Schema 25, authoritative) and R2 (Schema 26, candidate).

This record documents the implementation of the contract in
[PHASE-2A-2T-PAYMENT-EXECUTION-SEAM-STRIPE-ADAPTER-CONTRACT.md](PHASE-2A-2T-PAYMENT-EXECUTION-SEAM-STRIPE-ADAPTER-CONTRACT.md)
(correction round 8, SHA-256 `fb611d36627544ade01d5fa8935a4b98b11893cc1f465ddc5d1179d256b796ad`). The contract
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
   translation and the bounded ordered R2 consequence.
6. **Secret isolation** — `PaymentSecretVault` plus its own repository; no provider secret is writable in
   this build.
7. **Schema 028** — fifteen additive tables with the fail-closed verifier
   `verify_payment_execution_schema()`.

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
  account.
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

## 5. Evidence executed in this environment

| Check | Result |
| --- | --- |
| `git diff --check` | pass (no whitespace or conflict-marker defects) |
| Shell syntax of the concurrency runner (`sh -n`) | pass |
| Git object integrity (`git fsck --no-dangling`, `git status`) | pass |
| Source scans: exactly fifteen declared tables; exact vocabulary strings; no forbidden column pattern; no `wp_schedule_*`/`curl_*`/`wp_remote_*` in Core; `wp_set_current_user` only inside `PaymentExecutionWorkerContext`; only an adapter seals or opens an envelope | pass |
| `tests/phase-2a2t-contract.php` | **not executed — PHP is unavailable in this environment** |
| `tests/phase-2a2t-migration-runtime.php`, `-runtime.php`, `-webhook-runtime.php`, `-secret-runtime.php`, `-corruption-runtime.php`, `-failure-runtime.php` | **not executed — PHP and the disposable WordPress + MariaDB runtime are unavailable in this environment** |
| `tests/phase-2a2t-concurrency-runner.sh` (all seventeen modes) | **not executed — the disposable container runtime is unreachable in this environment** |

The runtime suites are written and wired exactly as the contract's §17 requires, and they were
**not** run here. Running them on the disposable runtime (fresh install, 26→28 upgrade, migration,
webhook, secret, corruption, failure and the full concurrency matrix) is the mandatory acceptance gate
for this candidate and remains outstanding.

## 6. Explicit non-authorisation

No live Stripe API call, credential, webhook secret value, charge, refund, payout, provider dashboard
change, notification delivery, Theme/NIU change, Amelia write/removal, deployment, production access,
production cutover or merge occurred or is authorised by this record. `LIVE_EXECUTION_PROVIDERS` and
`PROVISIONABLE_PROVIDERS` are empty, so the Stripe adapter is provably incapable of an outbound call and
no code path can store a Stripe credential — not even through the capability-authorised vault surface.

## 7. Definition of done — outstanding items

1. Execute every §17 suite on the disposable runtime and record the results.
2. Confirm the Schema 027/028 ledger assumption is recorded either way (Phase S owning 027, or 027
   deliberately skipped) as the contract's §19 prerequisite requires.
3. Independent review of the corrected candidate commit and tree (correction round 8 closes the four
   blocking findings the review of `91bf288cd1cf4d5c08e7c99cb310a4d0743113b1` raised).
