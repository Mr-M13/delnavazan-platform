# Stripe provider fixture contract (future phase)

The current Platform has **no Stripe adapter**. `CommercialPaymentService` models
payment evidence through `CommercialIdempotency` digests and settles canonical
obligations; the `provider_key` field is an opaque mapping, not a live account.
The migration verifier rejects a `stripe_payment_intent_id` column or any other
provider-specific storage.

When a Stripe adapter is authorised it must:

- implement a single `provider_key` (for example `stripe`) without leaking it
  into canonical storage;
- accept the `CommercialPaymentService::ingest()` shape unchanged, producing
  `evidence_reference_digest` / `evidence_fact_digest` rather than raw ids;
- be testable with the sample webhook below through a mock transport, so no
  `api.stripe.com` call is ever made in the disposable runtime.

See [sample-webhook.json](sample-webhook.json) for a synthetic
`payment_intent.succeeded` event. It is **not** a real event and carries no
live key or account.
