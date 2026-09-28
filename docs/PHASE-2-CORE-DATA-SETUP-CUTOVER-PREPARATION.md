# Phase 2 — Core Data Setup & Cutover Preparation

Status: **STARTED — planning and bounded implementation only**  
Authoritative base: `db596698a5d7b398692aeec425efd7abf544cb6a`  
Foundation gate: **FOUNDATION ACCEPTED**  
Production deployment/cutover: **NOT AUTHORISED**

## Objective

Prepare the accepted Delnavazan Platform foundation for real operational use without reintroducing an Amelia importer or creating a big-bang migration. Establish the small canonical starting dataset, prove the required operator workflows against it, prepare a clean staging environment, and define explicit cutover/rollback gates before any production authority moves.

## Locked approach

- Use the canonical Platform as the future business authority.
- Do **not** build a bulk Amelia importer or synchroniser.
- Teachers create fresh accounts.
- Seed only the small set of Instruments/Courses and active Students/Enrolments/Terms/Lessons actually required to start operations.
- Preserve legacy Amelia data as read-only historical evidence until retirement gates pass.
- Hosting/staging is supporting infrastructure, not authority cutover.
- No production traffic, live-provider activation, real payment execution, external messaging, or Amelia retirement occurs in this phase without a separate Hamed gate.

## Workstreams

### P2-A — Authoritative seed inventory

Define the minimum initial catalogue and active operational records required for launch:

1. Instruments / Courses.
2. Teacher accounts and canonical Teacher identities.
3. Active Students.
4. Active Enrolments.
5. Current Terms.
6. Required future Lessons / schedules needed for continuity of service.

Output: a human-reviewable seed manifest with provenance and no private production data committed to Git.

### P2-B — Operator entry and validation workflow

Use the accepted Core operator surfaces to create/reconcile the small starting dataset. Prove:

- capability and nonce boundaries;
- idempotent create/reconcile behaviour;
- provenance/audit receipts;
- duplicate/conflict refusal;
- rollback/re-entry procedure;
- no Amelia write dependency.

### P2-C — Staging environment

Prepare a fresh WordPress staging environment suitable for the Platform and Theme:

- current supported PHP/MariaDB versions;
- HTTPS;
- WP-CLI and controlled deployment tooling;
- off-host backups;
- logs/monitoring;
- staging-only secrets/configuration;
- Platform + Theme installed from reviewed Git artefacts;
- no live provider credentials or production traffic.

Hosting selection/provisioning remains a Hamed approval/cost gate. A managed VPS is the preferred architecture candidate unless comparison shows a material reason to use managed WordPress hosting.

### P2-D — Staging acceptance

On staging, prove the foundation as an integrated WordPress installation:

- fresh install and retained migration path;
- Schema 33 integrity;
- Core dataset operator workflows;
- portal/admin capability boundaries;
- Theme/Platform coexistence;
- cron/background execution;
- backup/restore rehearsal;
- no accidental provider sends or production dependencies.

### P2-E — Cutover preparation only

Produce, but do not execute, the controlled cutover plan:

- authority ledger by capability;
- pre-cutover checklist;
- rollback checkpoints;
- observation period;
- Amelia dependency/exit checklist;
- exact human gates for each authority transfer.

## Completion gate

Phase 2 is complete only when:

1. the minimum canonical seed manifest is approved;
2. staging is healthy and reproducible;
3. required seed/operator workflows pass with auditable evidence;
4. backup/restore is proven;
5. the cutover plan and rollback gates are explicit;
6. no unresolved hidden Amelia dependency exists in the capabilities proposed for the next phase.

Completion of Phase 2 does **not** itself authorise production cutover or Amelia retirement.

## Immediate next actions

1. Freeze Navazan V1 as historical orchestration infrastructure; preserve state/evidence, do not delete it.
2. Build the minimum seed-inventory template from current Platform entities and required fields. **DONE — see `PHASE-2-SEED-INVENTORY-TEMPLATE.md`.**
3. Produce a hosting/staging requirements matrix and compare self-managed VPS, managed VPS and managed WordPress hosting before purchase.
4. Prepare the staging installation/runbook so provisioning can start immediately after Hamed selects hosting. **DONE — see `PHASE-2-STAGING-REQUIREMENTS-AND-RUNBOOK.md`.**
