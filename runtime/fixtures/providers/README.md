# Provider fixtures and mocks (future-phase contract)

This directory is the **local-only** home for provider-adapter test doubles for
the future Stripe, Google and Meta phases. Nothing here is used by the current
platform code: the Platform is deliberately provider-neutral today, and the
migration verifiers reject any provider-specific column, table or authority.

## Rules (non-negotiable)

1. **No live credentials.** Never commit an API key, secret, webhook signing
   secret, access token, client id or account number. Use clearly-labelled
   synthetic placeholders (`sk_test_...`-shaped strings are still treated as
   secrets and must not be committed).
2. **No live traffic.** Every fixture is a static sample or a deterministic
   local responder. No test may reach a real provider endpoint.
3. **Provider identity is a mapping, not business identity.** Fixtures model a
   provider payload and its digest mapping; they never own canonical Teacher,
   Student, Enrolment, Term, Lesson or payment authority.
4. **Digests over raw values.** Where the platform records a provider fact it
   stores a `*_digest` (`char(64)`), never the raw secret/token/reference.

## Layout

```text
providers/
  stripe/   payment-intent / webhook / account fixtures + adapter contract
  google/   calendar / meet fixtures + adapter contract
  meta/     WhatsApp message fixtures + adapter contract
```

Each provider folder contains a `README.md` (the seam contract) and one or more
sample payload files. When an adapter phase is authorised, the adapter is
expected to consume these fixtures through a `TransportInterface`-style seam and
to be swappable in the disposable runtime via an environment variable (for
example `DZN_PROVIDER_TRANSPORT=mock`) so tests never perform an external send.
