# Operational-readiness integration candidate — conflict-resolution manifest

This is a single, local, independently reviewable integration candidate. It was built from the
GitHub-facing `main` reference the bridge prepared — `origin/main` = `main` =
`6673e7bb6b0dc267d455b61220c3da77eb1b4356` — and combined four reviewed candidates with four
explicit **non-fast-forward** merge commits. No cherry-pick and no rebase was used, so every reviewed
candidate tip below remains an ancestor of the integration tip.

Nothing was pushed, no authoritative `main` was moved, nothing was deployed, and no credential,
provider call, external send or cutover was used. The combined tree requires independent review.

## 1. Base and candidate ancestry

| candidate | reviewed tip | merge commit into the integration |
|---|---|---|
| Reviewed W operational-readiness correction (`PLATFORM-W-OPREADINESS-CORRECTION-V2`) | `eb55768e1eff32103b7357cde10b01aa65917d1f` | `a4013ad7ba44fcfa64ca84dff867d9f214b6ded8` |
| Finalized runtime correction (`PLATFORM-OPREADINESS-RUNTIME-HARNESS-CORRECTION-V2`) | `77d8adf55b8a1ccf0d1456f875b55fc1e07a3777` | `f32730484145db34f6b9a35170af2f0ba5d42d7b` |
| Docs/portal correction candidate (`PLATFORM-OPREADINESS-DOC-REGISTRY-CORRECTION-V2`) | `d768e3614cc1fc749a7e4a001da939377c2efa24` | `380457da6f027dfc0bab2cd78fb989653332998a` |
| Final Schema-32 Notifications S re-land (`PLATFORM-S-NOTIFICATIONS-SCHEMA32-RELAND`) | `56cd831ae09b89baf5c1ca396a68c05411439213` | `69b5cb35f2e044fa69543b61e2fcaa03882903fc` |

Each merge commit is a true two-parent merge whose parents are (previous integration tip, reviewed
candidate tip): `6673e7b → a4013ad → f327304 → 380457d → 69b5cb3` (trees `1eaf1a4`, `ce5dbe6`,
`a67f3ab`, `a1a524c`). The integration merge tip is `69b5cb35f2e044fa69543b61e2fcaa03882903fc`
(tree `a1a524c03a0abba98a0fa0a5620a9c4078d98e45`); the reviewable candidate is that tip plus this
record (see §4). The candidate commits were fetched from their own managed workspaces as local
remotes — no push, no credentials, no provider.

## 2. Conflicts and how they were resolved

Overlapping paths were resolved deliberately. Nothing was resolved by discarding a candidate's
content wholesale, and no candidate-only path was dropped.

**Package identity — `delnavazan-platform.php` (W correction vs main's Schema-32 S re-land).**
The W slice is stamped `phase2a2w-portal-facing-services-20260927.2` at Schema `31`; main already
carries the additive Schema-32 Notifications S re-land. The integration keeps
`DZN_PLATFORM_SCHEMA_VERSION = '32'` and the S build stamp
`phase2a2s-notification-communications-authority-20260924.1`, because the S migration is additive on
top of Schema 031 and because `tests/phase-2a2s-migration-runtime.php` pins exactly schema `32` with
the S build prefix. The W slice's own stamp is recorded as the W slice's stamp in its contract and
README rows, not as the package's; no W test pins the W build identity. Nothing else in the file
conflicted — the file is byte-identical to `main`'s.

**Migration registry — `src/Core/Infrastructure/Migration/Migrator.php` (W vs main).**
Both sides edited this file; the three-way merge was automatic and resolved by union of distinct
regions, and the result was checked item by item: `031_portal_facing_services_principal_authorization`
and its verifier call sites come from the W correction, `032_notification_communications_authority`
and `verify_notification_communications_schema()` come from main's S re-land, and both appear exactly
once in the ordered migration map and the verifier chain. The W correction's Schema-031 table shape
(the nullable `capability_id`/`lesson_id` on `portal_public_action_events`) is retained.

**Public action controller — `src/Portals/PortalPublicActionController.php` (W vs docs/portal).**
The merged controller keeps W's implementation truth: a refusal's evidence — the refused
`portal_public_action_events` row and its `portal_access_denials` row — is written by
`PortalPublicActionService::recordRefusal()` inside the one root-serialised transaction, the
post-delegation outcome passes `$alreadyRecorded`, the reason vocabulary is
`PortalRule::EXCEPTION_REASON_CODES`, and the controller never writes denial evidence of its own. It
adds the docs candidate's truth: fixed per-route surface constants (`SURFACE_JOIN` / `SURFACE_ABSENCE`)
so no request text can select a bucket or audit row, admission executed before the option gate, and a
declared **fail-open** admission whose only refusal is a completed `allow()` that returns `false`.
The callbacks now call `self::admit(self::SURFACE_*)` and `self::refused($e, PortalRule::JOIN|ABSENCE,
$handle, $e instanceof PortalPublicActionRefusalRecorded)`. W's client-visible `429` for
`portal_rate_limited` was **not** retained: the docs registry records one uniform non-enumerating
`404 {"code":"portal_action_unavailable"}` with `Cache-Control: no-store` for every public refusal,
and no test depends on the 429.

**Controller contract guard — `tests/phase-2a2w-contract.php` (W vs docs/portal).**
Union of both reviewed assertion sets: W's root-serialised single-delegator, refusal-evidence and
consumed-replay assertions, plus the docs candidate's fixed-surface, admission-before-gate and
fail-open assertions, plus the merged truth that the controller contains `recordRefusal` and does not
contain `portal_access_denials`. Two assertion shapes are adapted to the merged class and recorded
here because they cannot hold verbatim: the `admit()` body is delimited by the following method (the
merged class declares `refused()` before `admit()`, W's order), and the callback assertions match the
merged call shapes `self::admit(self::SURFACE_JOIN,$handle)` /
`self::refused($e,PortalRule::JOIN,$handle,`.

**Rate-limit behavioural proof — `tests/phase-2a2w-public-rate-limit-unit.php` (docs/portal, adapted).**
This candidate-only file is the one candidate-only path that is not byte-identical to its candidate
(see §3). The docs candidate's probes are retained but now drive the merged seam: the real
`PortalPublicActionService::recordRefusal()` over an in-memory store, the W limiter's owner-supplied
`dzn_portal_rate_limit_budget` (an absent budget fails open; bucket state is an array), and the fixed
per-route surfaces. A tenth probe proves that one refusal is one transaction: the service opens it,
appends the refused action row and then its denial row in the §15.2 order, and commits once — a
property the docs candidate's version could not observe because it stubbed the controller's own
denial write, which W's correction removed.

**Revocation-replay behavioural proof — `tests/phase-2a2w-replay-runtime.php` (docs/portal, adapted).**
The docs candidate's proof is retained and now drives W's real absence flow: the GET renderer is
non-mutating and emits the 128-hex signed confirmation (`CONFIRMATION_TOKEN_BYTES` nonce plus
signature) instead of the base service's 64-hex token plus `confirmation_rendered` row. It still
proves the same property end to end — a consumed confirmation, the revoked Student principal link so
the ordinary path refuses `portal_principal_required`, and the exact replay converging on the
recorded `submitted` outcome with `replayed=true` and no second claim, event or command. Its in-memory
store now honours the real `action_state IN (…)` list and the `delegating` lease lookup instead of the
base service's list.

**README and the Phase-2A-2W contract (W vs docs/portal).**
The docs registry text and structure are kept (registry link, current-source reconciliation,
`PORTAL-AUTHORIZATION-REGISTRY.md`, §18 evidence split), W implementation-truth rows are kept
(principal identity storage, `delegation`/`submitted` action-state vocabulary, the 36-member exception
vocabulary, the eleven concurrency modes, denial-rollback and lease-takeover coverage), the W build
stamp is recorded as the W slice's own stamp, and the recorded package identity is updated to this
tree's integrated Schema-32 identity. The historical provenance tables in §0.1 are retained as
provenance, not rewritten, and the changelog's dated "Documentation reconciliation — current
Schema-31 source state" entry is likewise retained as the record of that round's own base.

**Changelog — `docs/CHANGELOG.md` (docs/portal vs main's S section, then S).**
Both reviewed section sets are retained: the W/docs round entries first, then the S section, then the
pre-existing Phase-U entry. The S section's round-11 correction entry merged additively and without
conflict, and the S-topology note records that this integration merges the reviewed S re-land
candidate itself, while the recorded recovery branch remains unrelated to `main`.

**Schema-32 S re-land — additive.**
`032_notification_communications_authority` is declared exactly once, keeps its verifier wiring and
marker, and no S-owned file was replaced by another candidate; the S merge conflicted on nothing and
its product-code corrections (`NotificationEligibility`, `NotificationSchedule`, `NotificationSupport`)
are byte-identical to the reviewed candidate.

## 3. Verification record

Environment: no host PHP, no WordPress, no MariaDB and no reachable Docker daemon. PHP was executed
through a local PHP WebAssembly CLI at 8.3.32 and 8.5 against a read-only mount of this tree. Every
result below was reproduced on the integration tree and compared with the same file on `main` and on
each candidate tip.

- **Candidate ancestry — PASS.** All four reviewed tips are ancestors of the integration tip.
- **Non-fast-forward shape — PASS.** Each candidate was merged through an explicit two-parent merge
  commit; no cherry-pick and no rebase.
- **Candidate-only path retention — PASS.** Every path in every candidate tree is present in the
  integration tree (W 17, runtime 22, docs 2, S 0 candidate-only paths; 0 missing). All candidate-only
  files are byte-identical to their candidate except `tests/phase-2a2w-public-rate-limit-unit.php`,
  the deliberate adaptation recorded above. No path present in `main` disappeared (591 → 632 files).
- **Static source guards and PHP-level behavioural proofs — PASS** under both PHP 8.3 and 8.5:
  `tests/phase-2a2w-contract.php`, `tests/phase-2a2w-public-rate-limit-unit.php` (adapted),
  `tests/phase-2a2w-replay-runtime.php` (adapted), `tests/phase-2a2w-remediation-contract.php`,
  `tests/phase-2a2w-theme-isolation-contract.php` and `tests/phase-2a2s-contract.php`.
- **Combined contract sweep — PASS with no regression.** The 40 `*contract*.php` guards plus the two
  W behavioural proofs were run on `main`, on each candidate tip and on the integration tree. No file
  that passes on `main` or on any candidate fails on the integration tree; the integration tree adds
  the three W-only guards as passes.
- **Shell guards — PASS.** `tests/runtime-path-guards.sh` (finalized runtime hardening, also pass on
  the runtime candidate) and `tests/phase-2a2w-blocking-findings-contract.sh` (W refusal, delegation
  and lock-order contract).
- **PHP parse sweep — PASS.** All 538 `src/**/*.php`, `tests/*.php`, `delnavazan-platform.php` and
  `uninstall.php` parse under `token_get_all(…, TOKEN_PARSE)` on PHP 8.3 and 8.5. This substitutes for
  `tests/static.php`, whose `php -l` loop needs a spawning CLI this environment does not provide.
- **`git diff --check` — clean**; no conflict markers remain anywhere in the tree.

Pre-existing failures, identical on `main` and on the integration tree (not caused by this
integration): `tests/phase-2a2n-contract.php`, `tests/phase-2a2t-contract.php`,
`tests/phase-2a2u-contract.php`, `tests/phase-2a2v-contract.php` (each pins a different phase's build
identity in the single `DZN_PLATFORM_BUILD_ID` constant, so they cannot all hold once a later phase
stamps its own), and `tests/schema-contract.php` (a Phase-1 guard that forbids `finance` in the
schema, stale since Schema 30 added the finance migration).

Not executed: the WordPress/MariaDB runtime, migration, concurrency, corruption, failure, webhook,
secret and browser suites, and the `runtime/` harness itself (no cached images, no reachable Docker
daemon, no network). `tests/phase-2a2s-schedule-derivation-runtime.php` fails identically on `main`
and here because it requires that runtime. They remain required acceptance gates before any merge,
deployment or cutover.

## 4. Auditability

The merge graph (`refs/candidates/*` plus the four merge commits and the integration tip) lives in
the local working clone created for this candidate (during this run at `/private/tmp/dzn-opreadiness-integration-A3b.Hq9aCn/repo`), which was made by cloning the managed checkout and
fetching the four candidates from their own managed workspaces without pushing. The managed
checkout's `.git` directory is read-only to this worker, so the merge-commit graph cannot be created
inside it; this manifest and the synchronised working tree are the audit trail the managed checkout
can carry. Every reviewed candidate tip, the four merge-commit SHAs and every resolution above are
reproducible from the four managed workspaces and this base. The resolution content was additionally
cross-checked byte-for-byte against the earlier locally built integration graph (itself never
reviewed — its review runs were blocked before a verdict), and the two builds agree on the merged
tree.

The combined tree requires independent review before any authoritative merge, deployment, provider
activation or cutover.
