# Delnavazan Platform

Delnavazan Platform is the authoritative operational/business layer. The Theme presents Platform-owned data and actions; it does not own business authority.

## Current accepted baseline
- Accepted repository baseline for this documentation pass: `559b1736621c9ed32e41dd2b785dd0f040dcb647`.
- Accepted schema authority: Phase 2A.2-R2 / Schema 26.
- In-flight work is intentionally not treated as accepted architecture. See the local `CD-LIVE-STATE.md` for current queue/candidate state.

## Canonical documents
- `docs/DELNAVAZAN-CORE-CONTINUITY.md` — current accepted state, active roadmap and recovery orientation.
- `docs/ARCHITECTURE.md` — ownership boundaries and system structure.
- `docs/DATA-MODEL.md` — current canonical domain model and schema authority.
- `docs/PRODUCT-DECISIONS.md` — accepted product/business rules and open decisions.
- `docs/SECURITY.md` — security invariants and verification gates.
- `docs/MIGRATION-STRATEGY.md` — migration rules, schema progression and rollback principles.

Historical phase records live under `docs/archive/phase-history/` and Git history. They are evidence, not current operational truth.

## Documentation rule
Canonical documents must be updated when their declared trigger fires. The cross-project trigger policy lives in the local Control Plane `DOCUMENTATION-POLICY.md`.
