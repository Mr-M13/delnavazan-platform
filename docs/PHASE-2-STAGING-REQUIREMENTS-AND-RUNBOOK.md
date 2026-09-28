# Phase 2 — Staging Requirements and Provisioning Runbook

Status: **READY FOR HOST SELECTION — provisioning not yet authorised**

## Goal

Create a reproducible, non-production WordPress staging environment for integrated Platform + Theme verification, seed/operator rehearsal, backup/restore testing and cutover preparation.

## Hosting requirements matrix

| Requirement | Required | Notes |
|---|---:|---|
| Full SSH access | Yes | Needed for controlled Git/WP-CLI/runtime diagnostics. |
| WP-CLI | Yes | Must be available directly or installable. |
| Supported PHP with required extensions | Yes | Pin exact version after host selection and compatibility check. |
| MariaDB/MySQL with database/admin access | Yes | Must support Schema 33 migrations and backup/restore rehearsal. |
| HTTPS/TLS | Yes | Automatic renewal preferred. |
| Cron / scheduled jobs | Yes | Real cron preferred over traffic-only WP-Cron for staging tests. |
| Filesystem control | Yes | Plugin/theme deployment, logs and controlled permissions. |
| Off-host backup destination | Yes | Backups must survive loss of the staging server. |
| Snapshot/restore capability | Preferred | Useful before migration and destructive rehearsal steps. |
| Server/application logs | Yes | Web, PHP and WordPress logs accessible to operator. |
| Staging-only secrets | Yes | No production provider credentials. |
| Outbound-network control | Preferred | Helps prove accidental provider sends cannot occur. |
| Australian region | Preferred | Lower latency and simpler operational context. |
| Root/sudo access | Preferred | Strongly favours VPS/managed VPS over restrictive WP hosting. |
## Architecture target

Preferred candidate until host comparison is complete:

`DNS/Cloudflare -> HTTPS web server -> PHP-FPM -> WordPress + Delnavazan Platform/Theme -> MariaDB`

A managed VPS is preferred if it preserves SSH/root-equivalent control while reducing OS patching/backup burden. Self-managed VPS remains acceptable. Managed WordPress hosting is acceptable only if it satisfies the control requirements above without blocking WP-CLI, cron, logs, migration testing or backup/restore rehearsal.

## Provisioning sequence

### 1. Host baseline

- Create a fresh staging server/account.
- Apply OS/provider security updates.
- Configure firewall to expose only required services.
- Create a non-root deployment/operator account where applicable.
- Enable SSH key authentication; disable password SSH where supported.
- Confirm system time/timezone handling and NTP.

### 2. Web/database baseline

- Install/configure supported web server + PHP-FPM or equivalent managed stack.
- Install/configure MariaDB/MySQL.
- Create a dedicated staging database/user with least required privileges.
- Configure HTTPS and renewal.
- Configure staging hostname; keep production DNS untouched.
### 3. WordPress baseline

- Install fresh WordPress.
- Disable search indexing for staging.
- Use staging-only salts/secrets.
- Configure real cron if available and disable traffic-dependent WP-Cron only after replacement is verified.
- Enable controlled debug/error logging without exposing logs publicly.
- Do not install unrelated plugins.

### 4. Delnavazan artefacts

- Deploy reviewed Platform artefact/source from authoritative Git.
- Deploy reviewed Theme artefact/source from authoritative Git.
- Record exact Git SHAs/package hashes used.
- Run Platform activation/migrations only on staging.
- Verify Schema 33.
- Confirm Theme and Platform coexist without business-authority leakage into Theme.

### 5. Safety controls before integration testing

- No production payment credentials.
- No live Meta/WhatsApp/email/SMS/Google credentials.
- No production webhooks.
- No Amelia writes.
- No real external messaging.
- Prefer explicit stub/disabled provider configuration.
- Confirm staging cannot accidentally send customer communications.
### 6. Phase 2 staging acceptance sequence

Run and retain evidence for:

1. fresh install;
2. retained migration path;
3. Schema 33 integrity;
4. Core operator entry/reconcile workflow;
5. seed idempotency and conflict refusal;
6. admin/portal capability boundaries;
7. cron/background execution;
8. Theme/Platform coexistence;
9. backup creation;
10. full restore into a disposable target;
11. post-restore integrity checks;
12. proof that providers/production dependencies were not contacted.

## Backup/restore minimum

A staging backup is acceptable only if it includes database dump, required uploads, a configuration inventory without secrets, exact Platform/Theme revisions, restore instructions and verification checksums. At least one restore must be performed into a fresh/disposable target before Phase 2 completion.

## Evidence convention

Safe evidence may be committed under `docs/evidence/phase-2/<date>/<check-name>.md`. Private operational evidence stays in the controlled operator location; only safe references/digests belong in Git.

## Hamed approval gates

Explicit approval is still required before purchasing/provisioning paid hosting, changing production DNS, entering live provider credentials, using real student/teacher private data on staging, production deployment/authority cutover, or Amelia retirement.
