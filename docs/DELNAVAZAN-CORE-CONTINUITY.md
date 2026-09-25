# Delnavazan Platform — Current Continuity

## Purpose
This is the short re-entry document for the Platform repository. It records accepted state only. In-flight tasks, blockers and candidate commits belong in the local `CD-LIVE-STATE.md`.

## Accepted baseline
- Repository baseline: `559b1736621c9ed32e41dd2b785dd0f040dcb647`.
- Phase 2A.2-R2 / Schema 26 is the accepted architectural baseline for this documentation pass.
- Earlier accepted authority includes identity, teacher availability, booking/intake, coordination, proposal/acceptance, enrolment, term, lesson, scheduling, delivery/attendance, continuation/reservation, commercial purchase/funding/current-Term capacity and renewal/recurring/recovery/refund-review authority.
- Provider-specific transport, production deployment and presentation remain outside canonical Platform authority unless explicitly added by an accepted contract.

## Current in-flight boundary
- Phase S notifications work is not considered accepted merely because a candidate exists.
- The local Control Plane currently owns exact task/blocker/candidate truth.
- Architecture/data docs are updated only after an implementation has passed review and become the accepted baseline.

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
The current queue after R2 proceeds through Notifications (S), Payment Execution (T), Finance/Reporting (U), Google integration (V), Portals (W), then functional-completeness audit. Exact sequencing and status are authoritative only in `state/project-queue.json` / `CD-LIVE-STATE.md`.

## Recovery orientation
When resuming after a gap:
1. Read local `CD-LIVE-STATE.md`.
2. Verify live queue/scheduler/orchestrator state.
3. Confirm accepted repository baseline and candidate identity.
4. Read only the canonical docs relevant to the task.
5. Use archived phase docs only for forensic detail.

## Staleness trigger
Update this document after any accepted roadmap milestone, accepted schema change, major authority-boundary change, or when three accepted Platform tasks have completed since its last update.
