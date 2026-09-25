# Delnavazan Platform — Current Continuity

## Purpose
This is the short re-entry document for the Platform repository. It records accepted state only. In-flight tasks, blockers and candidate commits belong in the local `CD-LIVE-STATE.md`.

## Accepted baseline
- Repository baseline: `b36561dc6bb6e87fd142a28ae67fbc4f2fdc9279`.
- Phase V / Schema 27 and Phase T / Schema 29 are accepted on Platform `main`.
- Accepted authority includes identity, teaching, commercial/renewal, provider-neutral Google integration, and a provider-neutral payment-execution seam with a reviewed Stripe adapter.
- Payment execution records commands, attempts, results, dispatch claims, provider events/decisions and encrypted adapter-scoped secrets without making provider state canonical business truth.
- Production deployment and live provider activation remain outside this accepted documentation state.

## Current in-flight boundary
- Runtime acceptance for the Payment execution migration, webhook, secret-handling, failure and concurrency suites remains outstanding until a host with PHP and disposable WordPress/MariaDB is available.
- Live execution/provisioning provider allowlists are empty; no provider credential or live-charge path is enabled.
- Finance contract preflight is active in the local Control Plane. The Notifications candidate remains unmerged and must be reconciled and renumbered against current `main`.
- The local Control Plane owns exact task, blocker, candidate and worker truth.

## Non-negotiable boundaries
- Platform owns business authority and canonical records.
- Theme is presentation-only.
- Provider integrations are adapters; they do not become domain authority.
- External side effects must be represented through controlled intents/outbox/provider adapters.
- Money uses integer minor units plus explicit currency.
- Mutable aggregate state and append-only evidence/event/command records remain distinct.
- Secrets/raw provider credentials do not belong in canonical domain rows or documentation.
- Idempotency, auditability, privacy erasure boundaries and fail-closed validation are required.

## Roadmap orientation
Google integration (V) and Payment Execution (T) are accepted. Finance/Reporting (U) contract preflight is active; Notifications (S) remains parked and unmerged pending reconciliation. Exact sequencing and status are authoritative only in `state/project-queue.json` / `CD-LIVE-STATE.md`.

## Recovery orientation
When resuming after a gap:
1. Read local `CD-LIVE-STATE.md`.
2. Verify live queue/scheduler/orchestrator state.
3. Confirm accepted repository baseline and candidate identity.
4. Read only the canonical docs relevant to the task.
5. Use archived phase docs only for forensic detail.

## Staleness trigger
Update this document after any accepted roadmap milestone, accepted schema change, major authority-boundary change, or when three accepted Platform tasks have completed since its last update.
