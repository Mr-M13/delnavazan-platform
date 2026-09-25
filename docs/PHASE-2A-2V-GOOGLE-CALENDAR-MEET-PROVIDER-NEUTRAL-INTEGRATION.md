# Phase 2A.2-V — Provider-Neutral Google Calendar & Meet Integration (implementation record)

Status: **RECONSTRUCTED IMPLEMENTATION CANDIDATE — NOT INDEPENDENTLY REVIEWED / NOT MERGED / NOT
DEPLOYED.** No live credential, no provider traffic, no production data, no Theme change, no
deployment and no public cutover is authorised by this document or by the code it describes.

> **Why this record exists.** The previously recorded Phase-V candidate
> `3724edb3c36959f3657e6b495ab96f26d347c476` / tree `5402cf890e2db898bccb15ba8ffc97176ced6457` is no
> longer present in any workspace, backup object store, reflog or remote branch. This candidate is a
> reconstruction of the *same approved contract* from the completed
> `PLATFORM-V-GOOGLE-CONTRACT-PREFLIGHT` contract, the authoritative current Platform code/docs and
> the preserved prior Phase-V run evidence. No new product behaviour was invented: every rule below
> is traceable to the preflight contract (`docs/PHASE-2A-2V-PROVIDER-NEUTRAL-GOOGLE-CALENDAR-MEET-INTEGRATION.md`
> in the contract-preflight workspace) or to the preserved Phase-V run records.

## 1. Identity

| Item | Value |
| --- | --- |
| Authoritative base | manager checkout `559b1736621c9ed32e41dd2b785dd0f040dcb647` (tree `4e7fb1736b4a30de323cd25fe5dd4b6ceaad65a1`) |
| Candidate schema | **27** |
| Candidate migration | `027_google_calendar_meet_provider_integration` |
| Candidate build | `phase2a2v-provider-neutral-google-calendar-meet-20260925.1` |
| Candidate SHA / tree | recorded by the host materialisation; a commit cannot embed its own hash |

The preflight contract named `26` / `026_google_calendar_meet_provider_integration` as *placeholders
to be fixed by the approving cut*. The authoritative base already carries Schema 26
(`026_renewal_recurring_enrolment_authority`), so this candidate takes the next free number and stays
strictly additive: no prior migration is renamed, renumbered or replayed, and no existing table,
column or capability is modified.

## 2. Authority boundary (unchanged from the contract)

| Authority | Owner | Phase-V relationship |
| --- | --- | --- |
| Canonical Lesson identity/lifecycle | Phase M | Read-only. V never issues, completes, cancels or reclassifies a Lesson. |
| Canonical scheduling & Teacher capacity | Phase N | Read-only. V may project an *already-applicable* schedule version; it never creates, revises or releases one. |
| Canonical delivery/attendance outcome | Phase O | Read-only. V records no outcome and corrects none. |
| Attendance intake, identity, review, settlement | Phase P | V is a trusted evidence **source** that submits normalised facts to the Phase-P intake seam. Participant identity authority stays Phase P. |

The candidate contains no `UPDATE`/`INSERT` of canonical Lesson, schedule, delivery, attendance,
payment or notification storage. Its only writes are to its own nine integration tables and to the
Phase-P intake seam through `CanonicalAttendanceIntakeService::ingestProviderEvidence`.

## 3. Storage

Nine provider-neutral tables (verifier: `verify_google_calendar_meet_provider_integration_schema`,
run after the migration, on current-schema verification and unconditionally before schema
activation, including the retained-migration/stale-version path):

| Table | Purpose |
| --- | --- |
| `dzn_integration_connections` | One active Teacher+provider connection with append-preserving lifecycle generations. |
| `dzn_integration_credentials` | Sealed renewable credential: `ciphertext` + `nonce` + `key_version` + `cipher_version` only. |
| `dzn_integration_oauth_authorizations` | One-time consent intent: state digest, verifier digest, client/scope/redirect/principal binding, expiry, consumed outcome. |
| `dzn_provider_identity_mappings` | Provider subject → Core Teacher connection identity, versioned with supersession. |
| `dzn_provider_calendar_event_mappings` | Provider calendar event → exact Lesson + canonical schedule version. |
| `dzn_provider_meeting_mappings` | Provider conference/join reference → exact Lesson + canonical schedule version. |
| `dzn_provider_ingest_events` | Append-only normalised provider-event receipts with separate provider/local instants. |
| `dzn_provider_event_conflicts` | Append-only durable conflict receipts for a changed provider-event context. |
| `dzn_provider_integration_commands` | Append-only digest-only command evidence. |

The verifier rejects a provider-specific column, a plaintext credential column, a missing
credential key/cipher version, a mutable column on the three append-only tables, a malformed digest,
a missing unique index (`active_connection`, `provider_subject`, `lesson_version`,
`provider_event`/`provider_conference`, `conflict_identity`, `command_key_digest`), a
non-transactional engine, an unexpected table claiming a Phase-V prefix, and any attempt to smuggle
academic, attendance, delivery, notification or payment storage into the phase.

## 4. Behaviour locked by the contract

- **V-D2 lifecycle.** `disconnected → authorizing → connected`, with `refresh_failed`, `revoking`,
  `revoke_failed`, `revoked`; a revoked connection is terminal, and a reconnect always starts a new
  consent lifecycle. A failed provider revoke is recorded and retryable and never leaves the
  connection usable locally (the credential is quarantined *before* the provider is asked).
- **V-D3 initiation.** State and PKCE verifier are generated server-side, returned once, and stored
  only as digests. The intent binds the principal, exact Core Teacher, client, ordered scope set and
  exact HTTPS redirect target, carries an absolute expiry, and is consumed exactly once. Scope
  mismatch, identity mismatch, unknown/expired/replayed state and a redirect mismatch all fail
  closed. Teacher self-connect revalidates the Phase-J `teacher_principal_links` row inside the
  transaction.
- **V-D4 credentials.** `sodium_crypto_secretbox` (AES-256-GCM fallback) with a unique nonce per row,
  a key derived from the WordPress salts with `dzn_provider_integration` domain separation, a stored
  key/cipher version, and an envelope bound to exactly one connection + provider code. Decrypt fails
  closed on an unknown version, a malformed nonce, a truncated ciphertext or a failed authentication;
  rotation is explicit; nothing is ever logged, exported or placed in a notice.
- **V-D5 mappings.** A provider subject/account/calendar/event/conference value is stored only as a
  keyed digest and never re-identifies a Teacher or Lesson. Supersession/revocation is
  append-preserving.
- **V-D6/V-D7 projections.** A calendar/meet projection copies one exact applicable canonical
  schedule version's UTC interval and wall-clock provenance, and is refused when the version is
  stale, unusable or the Lesson's canonical aggregate does not validate. A retraction quarantines the
  local reference only: it never cancels, releases or reschedules the Lesson.
- **V-D8 evidence.** Provider facts reach Phase P only through
  `CanonicalAttendanceIntakeService::ingestProviderEvidence`; the integration layer never resolves a
  participant, never asserts verification, never writes Phase-O/P storage, and holds no integration
  row across the Phase-P transaction.
- **V-D9/V-D10 idempotency.** A provider event key is deduplicated against full immutable context
  equality: an exact duplicate converges, a changed context is durably refused as one of
  `changed_payload`, `cross_lesson`, `cross_schedule_version`, `cross_context`, `cross_participant`,
  `cross_interval`, and the original receipt is never overwritten. Commands persist
  `command_key_digest` + `command_payload_digest`; replay reconstructs the complete facts through the
  same canonical digest path and compares the payload digest unconditionally.
- **V-D11 no raw payload.** Only normalised facts and keyed digests are stored.
- **V-D12/V-D14 no I/O.** No migration, activation or verification path reads a credential,
  configuration option or network resource; the phase contains no HTTP/OAuth/webhook client.
- **V-D13 least privilege.** Administrator: all five capabilities. `dzn_teacher`: only
  `dzn_connect_own_provider_calendar` and `dzn_view_provider_integrations`, and the Teacher role is
  actively stripped of the management, revocation and ingestion grants. Background ingestion is a
  named system actor with the bounded `dzn_ingest_provider_events` grant; participant identity and
  delivery outcomes are never delegated to that actor.

## 5. Modules

| Artefact | Responsibility |
| --- | --- |
| `src/Core/Application/ProviderIntegrationRule.php` | Controlled vocabulary, lifecycle transitions, exact redirect/scope comparison, projection-fact extraction from a canonical schedule version. |
| `src/Core/Application/ProviderIntegrationIdempotency.php` | Domain-separated key/payload/evidence/subject/provider-event digests. |
| `src/Core/Application/IntegrationSecretService.php` | Seal/open/rotate the integration secret class; fail closed; redacted reporting. |
| `src/Core/Application/ProviderIntegrationValidator.php` | Fail-closed row shapes plus the composed canonical Lesson/schedule/delivery validity a projection requires. |
| `src/Core/Application/ProviderIntegrationService.php` | Connection lifecycle, identity mappings, projections and retractions, digest-only command evidence and replay. |
| `src/Core/Application/ProviderEventIngestService.php` | Provider-event verification, dedup/conflict receipts, delegation to the Phase-P seam. |
| `src/Core/Application/ProviderIntegrationReadService.php` | Capability-gated, object-level-authorised reads; credentials reported redacted only. |
| `src/Core/Application/Port/*.php` | `ProviderOAuthPort`, `ProviderCalendarPort`, `ProviderMeetingPort`, `ProviderEventNormalizer`. |
| `src/Integrations/GoogleCalendarMeetAdapter.php` | The only Google-specific translation seam: pure, no HTTP client, no OAuth library, no credential, no webhook. |
| `src/Integrations/ContractProviderAdapters.php` | Deterministic no-I/O adapters implementing all four ports for the evidence runs; credential material is never recorded. |
| `src/Core/Infrastructure/Repository/ProviderIntegrationRepository.php` | All nine tables plus the Phase-V lock order (`Student–Course identity root → Enrolment → Term → canonical Lesson`). |

Core never imports `Delnavazan\Platform\Integrations\*`; the adapters are injected through the ports.

## 6. Test surface and exact disposable-runtime commands

| Artefact | Coverage | Status |
| --- | --- | --- |
| `tests/phase-2a2v-contract.php` | Static source contract: migration/verifier wiring, nine tables, provider neutrality, digest-only storage, capability wiring, seam names, no external call. | **EXECUTED — PASS** |
| `tests/phase-2a2v-isolated-runtime.php` | Pure runtime proof of the rule, digest boundary, credential sealing (unique nonce/binding/fail-closed/rotation), Google translation seam, deterministic adapters and fail-closed shapes. 229 assertions. | **EXECUTED — PASS** |
| `tests/phase-2a2v-migration-runtime.php` | Fresh Schema 27, additive 26→27, no backfill, repeat safety, per-capability repair, retained-027 and stale-version verification, malformed-storage rejection for provider/plaintext/mutable/digest/index/extra-table probes. | NOT EXECUTED HERE (needs WP-CLI + MariaDB) |
| `tests/phase-2a2v-runtime.php` | Consent lifecycle, sealing, mappings, calendar/Meet projection against the exact canonical version, stale-version refusal, retraction without canonical mutation, Phase-P evidence seam, duplicate convergence, durable conflict, capability denial, object-level reads, digest-only persistence. | NOT EXECUTED HERE |
| `tests/phase-2a2v-corruption-runtime.php` | Corrupt connection state/identity digest, credential versions and sealed material, mapping shapes, provider-event context/state, conflict kind and command evidence; every path fails closed and recovers after repair. | NOT EXECUTED HERE |
| `tests/phase-2a2v-failure-runtime.php` | Injected write boundaries at the connection, projection and ingest gates: full rollback, no orphan provider reference, no falsely replayable command, no Phase-P fact from a rolled-back ingest. | NOT EXECUTED HERE |
| `tests/phase-2a2v-concurrency-runner.sh` (+ setup/worker/verify) | Deterministic process-level matrix of §12: `connect_vs_revoke`, `authorization_replay`, `projection_vs_release`, `projection_vs_completion`, `duplicate_vs_conflicting_event`, `mapping_revoke_vs_ingest`, `teacher_archival_vs_connection`, `unrelated_teacher`. | NOT EXECUTED HERE |

Runtime suites are gated on `DZN_PHASE_2A2V_RUNTIME_TEST` ∈
`migration|authority|corruption|failure|concurrency` and refuse to run outside WP-CLI on a
`local`/`development` environment. In the disposable WordPress/MariaDB harness:

```sh
php tests/phase-2a2v-contract.php
php tests/phase-2a2v-isolated-runtime.php
DZN_PHASE_2A2V_RUNTIME_TEST=migration  wp eval-file tests/phase-2a2v-migration-runtime.php --user=1 --allow-root
DZN_PHASE_2A2V_RUNTIME_TEST=authority  wp eval-file tests/phase-2a2v-runtime.php --user=1 --allow-root
DZN_PHASE_2A2V_RUNTIME_TEST=corruption wp eval-file tests/phase-2a2v-corruption-runtime.php --user=1 --allow-root
DZN_PHASE_2A2V_RUNTIME_TEST=failure    wp eval-file tests/phase-2a2v-failure-runtime.php --user=1 --allow-root
DZN_PHASE_2A2V_REPO=<repo> DZN_PHASE_2A2V_WP_DIR=<wp> DZN_PHASE_2A2V_NET=<net> \
  sh tests/phase-2a2v-concurrency-runner.sh <mode>
```

## 7. Adjacent regression and evidence executed for this candidate

- `php -l` over every Phase-V PHP file, `delnavazan-platform.php` and the modified `Migrator.php`: clean.
- `sh -n tests/phase-2a2v-concurrency-runner.sh`: clean.
- `git diff --check`: clean.
- No live credential, no provider traffic, no production data, no Theme change and no deployment were
  used or produced.

## 8. Explicit non-authority / deferrals

No Lesson issuance/lifecycle mutation; no scheduling, revision or release; no canonical delivery or
attendance outcome write; no attendance settlement or adjudication; no participant identity
resolution; no payment/Stripe, notification, WhatsApp/Meta, renewal, payroll or Finance authority; no
calendar import/availability authority; no public join action or signed capability; no portal or
Theme change; no Amelia dependency; no production OAuth/webhook activation; no production cutover and
no deployment.

The five preflight open product decisions remain open and un-implemented by design: the exact approved
Google scope set, the calendar projection create/update-versus-delete retraction policy, failed-revoke
credential retention, webhook push versus bounded polling, and whether Meet join/leave evidence
auto-settles ordinary delivery through Phase P. The candidate takes the fail-closed option for each:
the scope set is deployment configuration validated as an exact set, a retraction quarantines locally
and records a retryable outcome rather than assuming a delete, a revoked credential stays quarantined,
transport is a deployment concern behind the normaliser port, and settlement remains Phase P's own
decision.
