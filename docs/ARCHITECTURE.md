# Delnavazan Platform Architecture

## Purpose
Describe the current ownership model and system boundaries. Detailed table/field shapes belong in code/migrations and `DATA-MODEL.md`.

## Architectural rule
Platform owns canonical business authority. WordPress/UI surfaces, Theme and external providers consume Platform decisions through explicit interfaces; they do not infer or replace authority.

## Layers
- **Core/domain** — canonical identity, enrolment, term, lesson, scheduling, attendance, commercial and related authority.
- **Application services** — commands, validation, idempotency, lifecycle transitions and read models.
- **Infrastructure** — persistence, migration/verifier, WordPress integration, outbox/provider adapters.
- **Admin/Public adapters** — authenticated operational surfaces and controlled public capabilities.
- **Theme** — presentation layer only.

## Domain ownership
Platform owns:
- Teacher/student identity and authority links.
- Booking/intake, coordination, proposal and acceptance evidence.
- Canonical enrolment, Term and Lesson lifecycles.
- Teacher assignment, scheduling/capacity and delivery/attendance facts.
- Post-intro continuation and slot reservation authority.
- Commercial product/price/offer/purchase/funding/capacity authority.
- Renewal/recurring enrolment, collection intent, recovery and refund-review authority.
- Provider-neutral payment accounts/object mappings, execution commands/attempts/results, dispatch claims, provider-event evidence/decisions and decision claims.

Provider adapters may execute an authorised intent but must not create domain truth independently. The accepted Stripe adapter owns only Stripe protocol, signature and transport concerns; Core owns the provider-neutral contract and reconciliation authority.

## Internal workflow pattern
- Commands are idempotent and explicitly authorised.
- Aggregate mutations are bounded and versioned.
- Evidence/events/commands that are intended to be immutable remain append-only.
- External work is emitted via outbox/intents, then executed by adapters.
- Provider responses are ingested as evidence/facts and reconciled back into Platform state.
- Payment dispatch and provider-event decisions use durable, lease/fencing-aware claims so retries cannot silently duplicate authority.
- Provider secrets remain encrypted and adapter-scoped; live execution/provisioning requires an explicit allowlist and is currently disabled.

## Module boundaries
Cross-domain writes must go through the owning application service. Direct table coupling that bypasses authority is prohibited. Shared identifiers do not grant write ownership.

## Presentation boundary
Theme may format, localise and compose Platform data. It may not:
- calculate authoritative payment, renewal, attendance or scheduling state;
- silently invent lifecycle transitions;
- store a second canonical copy of Platform authority.

## Deployment boundary
Local/test/packaging work is distinct from production deployment. Production deployment remains explicitly gated.

## Staleness trigger
Update after an accepted change to domain ownership, module boundaries, external-integration architecture, side-effect model, or platform/theme authority split. Maximum review interval: 30 days while active development continues.
