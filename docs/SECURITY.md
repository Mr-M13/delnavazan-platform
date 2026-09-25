# Delnavazan Platform — Security Invariants

## Purpose
Record enduring security boundaries. Detailed test cases belong in automated tests.

## Trust boundaries
- Browser/public requests are untrusted.
- WordPress authentication grants identity/context only; application services still enforce domain authority.
- Provider callbacks/webhooks are untrusted until authenticated, normalised and reconciled.
- Theme templates must not create privileged domain transitions.
- Local automation/control-plane authority is separate from production application authority.

## Required controls
- CSRF protection for state-changing browser/admin actions.
- Capability/role/ownership checks at application-service boundaries.
- Input validation and escaped output appropriate to context.
- Secrets in environment/keychain/provider configuration, never committed or returned through diagnostics.
- OAuth/provider tokens isolated behind integration adapters.
- Idempotency for retryable commands and callback ingestion.
- Append-only audit/evidence where history must be preserved.
- Digest/safe-reference storage for sensitive external identifiers where raw values are unnecessary.
- Privacy erasure/retention rules that preserve only justified non-identifying integrity evidence.
- Fail-closed migration/schema verification.

## External side effects
No provider action should occur merely because a domain row exists. Platform emits an authorised intent/outbox item; an adapter performs the side effect and records outcome evidence.

## Accepted Payment execution controls
- Stripe webhook authentication uses the exact raw request body and supported `v1` signatures; malformed, unsupported or unverifiable callbacks fail closed.
- Proxy-reported HTTPS is trusted only through explicit allowlisted configuration, never from an arbitrary client header.
- Receipt, normalised event and initial decision persistence is atomic/recoverable; dispatch and decision retries use durable lease/generation/token fencing.
- Provider secrets use authenticated encryption, remain adapter-scoped and are never stored in canonical commercial rows.
- Live execution and provisioning provider allowlists are empty until explicitly authorised.

## Operational safety
- Never log raw credentials, tokens or unnecessary personal data.
- Diagnostic output must be safe to expose to an authorised operator.
- Production deployment requires explicit Hamed authorisation.

## Verification gate
Security-sensitive changes require relevant automated tests plus independent review before acceptance.

## Staleness trigger
Update after any accepted authentication, authorisation, secret-handling, OAuth/webhook, privacy/retention, provider-boundary or production-security change. Maximum review interval: 45 days.
