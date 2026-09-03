# Platform Phase 1 — Core Foundation

## 1. Status

**Not started. Requires explicit product-owner approval.**

This document defines the proposed boundary and acceptance criteria for a future
implementation phase. It does not authorize PHP, plugin bootstrap code, database
tables, deployment, production data access, or changes to Amelia.

## 2. Objective

Create the smallest reliable platform foundation on which later read-only Amelia
import and shadow comparison can be built. Phase 1 establishes canonical Core
identity/state contracts, migrations, and module boundaries without changing any
live business authority.

## 3. Proposed deliverables

After approval, Phase 1 may deliver:

1. a valid WordPress plugin lifecycle/bootstrap with explicit module loading;
2. application version and independent schema/data version tracking;
3. a retry-safe migration runner with lock, audit result, and failure recovery;
4. minimum canonical Teacher, Student, Term/Enrolment, Lesson, and
   `LegacyReference` persistence needed for Phase 2 import;
5. stable internal identifier generation and audit/archive conventions;
6. application-service interfaces for creating/resolving identities and source
   mappings;
7. a versioned domain event envelope plus durable idempotency/outbox boundary;
8. capability-protected administrator diagnostics showing version, schema,
   migration health, and documentation links;
9. automated tests for migration retry, mapping uniqueness, state separation,
   authorization, and direct-file safety;
10. build/package validation and a documented rollback plan.

These deliverables remain proposals until a separately reviewed Phase 1 brief
approves the physical schema and implementation choices.

## 4. Required design decisions before implementation

Phase 1 must not begin until the product owner has resolved or explicitly
deferred:

- Teacher and Student duplicate-resolution/merge policy;
- whether Term and Enrolment are separate first-class records in the initial
  schema or one aggregate with explicit allocation semantics;
- initial lifecycle vocabularies and transition authorities;
- course/instrument catalogue ownership;
- reschedule/version history shape;
- timezone source and override policy;
- minimum data retention defaults;
- Core Teacher/Hamnavaz Profile linking workflow;
- identifier strategy and whether any identifier is safe for public exposure;
- scope of the first Phase 2 Amelia import.

## 5. Scope boundaries

### In scope after approval

- Core infrastructure and domain contracts only;
- schema creation/migration for approved minimum entities;
- administration visible only to appropriately capable users;
- synthetic/anonymised fixtures and non-production automated tests;
- no-op or test-only domain event publication needed to validate the boundary;
- documentation of every schema field and migration.

### Explicitly out of scope

- Amelia data import or table queries;
- any write to Amelia;
- changes to Amelia configuration, hooks, portals, or production records;
- teacher/student self-service portals;
- scheduling UI or scheduling authority;
- attendance reconciliation or signed public actions;
- Google OAuth/Meet provider calls;
- Meta/WhatsApp sends or webhook cutover;
- Stripe/payment processing;
- teacher payment statements;
- Hamnavaz Phase 4, public directory search, or profile changes;
- production migration, cutover, deactivation, or deletion;
- copying production personal data into tests, docs, commits, or PRs.

## 6. Minimum entity contract

The Phase 1 schema must encode the invariants in
[DATA-MODEL.md](DATA-MODEL.md), not merely mirror Amelia tables.

At minimum:

- Teacher and Student receive stable Core IDs independent of mutable contact data;
- Term/Enrolment links one Teacher and one Student in an academy context;
- Lesson references an approved Term/Enrolment and stores UTC schedule fields
  separately from attendance, delivery, and payability;
- LegacyReference maps scoped external IDs to Core records and enforces
  deterministic uniqueness/conflict handling;
- every mutable aggregate has created/updated provenance and reversible archive
  metadata;
- no provider secret or arbitrary raw payload is stored in Core entity rows.

Physical table names, keys, indexes, and constraints require design review in the
approved implementation PR. Custom tables are expected only where they are
justified by transactional/query needs; WordPress-native APIs remain preferred
for configuration and capability integration.

## 7. Migration framework contract

- Application and schema versions are separate.
- Each migration has a unique ordered identifier and an auditable result.
- A lock prevents concurrent execution.
- Re-running after completion is a safe no-op; re-running after partial failure
  either resumes from a checkpoint or repairs forward deterministically.
- Migration failure leaves the plugin in a safe, observable state and never
  triggers provider writes.
- Large data operations are deferred to Phase 2 and do not run synchronously on
  ordinary page requests.
- Deactivation preserves data and removes only ephemeral schedules/locks defined
  by the approved lifecycle.
- Uninstall preserves valuable Core data by default. Destructive removal requires
  explicit future policy and authorization.

## 8. Domain event foundation

Phase 1 should define an event envelope containing:

- event ID and version;
- event type;
- aggregate type/ID and aggregate version;
- occurred-at UTC time;
- actor/source;
- correlation and causation IDs;
- payload constrained to the documented event contract;
- publication/processing state and idempotency metadata.

An event is written atomically with the business change or through an equivalent
durable mechanism. External delivery is not performed inside a Core transaction.
Phase 1 need not implement production consumers.

## 9. LegacyReference foundation

Phase 1 establishes the mapping contract required by the next read-only import
phase:

- scoped provider + external type + external ID uniqueness;
- target Core type + Core ID;
- observed/import metadata and safe source fingerprint;
- active/superseded/conflicted/retired state;
- deterministic conflict reporting;
- no authorization based on the reference alone;
- no dependency from Core identity creation to Amelia availability.

No Amelia-specific SQL belongs in Core. It will live in the Phase 2 Legacy
Adapter.

## 10. Security requirements

- follow [SECURITY.md](SECURITY.md);
- capability and intent-specific nonce checks for every admin mutation;
- object-level authorization for every Core record;
- prepared queries and bounded inputs;
- private-by-default REST/public behavior;
- no secret-bearing settings unless the approved Phase 1 scope explicitly needs
  the encryption service for test coverage;
- redacted audit/diagnostic output;
- no predictable public Lesson actions or public record IDs;
- no theme, WordPress core, Amelia, or unrelated plugin file changes.

## 11. Compatibility and rollout

Phase 1 runs dark beside the current system:

- Delnavazan Enhancements remains active and unchanged;
- Amelia remains installed and authoritative;
- Hamnavaz remains independently operational at Phase 3;
- no production workflow reads Core records for decisions;
- no provider or user-facing side effect originates from Core;
- activation/deactivation is reversible and preserves existing site behaviour.

Deployment to a beta runtime requires a recoverable backup and a separate,
explicitly authorised validation brief.

## 12. Validation matrix

Before Phase 1 can be proposed for merge, validate:

1. repository branch/base/scope and documentation consistency;
2. plugin activation, deactivation, and reactivation without fatal/warning;
3. application/schema version separation;
4. clean install and every supported upgrade/retry path;
5. migration lock, partial-failure recovery, and idempotent rerun;
6. stable Core ID behavior independent of email/provider IDs;
7. LegacyReference uniqueness, repeat import simulation, and conflict handling;
8. Lesson schedule state remains separate from attendance, notification, and
   finance state;
9. archive/restore audit behavior;
10. capability, nonce, object-level authorization, SQL, sanitization, escaping,
    and direct-access protections;
11. private REST/public exposure baseline;
12. no Amelia queries/writes, provider calls, cron sends, or Hamnavaz changes;
13. no secrets or production personal data in repository/build artifacts;
14. static checks, tests, `git diff --check`, package contents/hash, and runtime
    smoke tests proportionate to the approved deliverable.

## 13. Acceptance criteria

Phase 1 is complete only when:

- approved physical schema and migrations implement the documented invariants;
- all validation passes with evidence;
- a new installation and retry-safe upgrade work in a representative WordPress
  environment;
- the working live system behaves exactly as before because no authority moved;
- the PR contains no Phase 2 import, integrations, portals, public routes,
  provider writes, or Hamnavaz Phase 4 work;
- independent review approves the boundary;
- the PR remains unmerged until the product owner explicitly authorizes merge.

## 14. Recommended first task after approval

Create a dedicated Phase 1 implementation branch from current `main`, finalize
the physical schema proposal against the open decisions, then build only the
foundation above. Do not combine Phase 1 with Amelia import or a workflow cutover.
