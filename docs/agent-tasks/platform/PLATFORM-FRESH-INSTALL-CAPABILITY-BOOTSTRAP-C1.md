# DELNAVAZAN PLATFORM — FRESH-INSTALL CAPABILITY BOOTSTRAP CORRECTION

STATUS
Post-R1 defect discovered by the new local disposable runtime.
Bounded corrective implementation. No deployment.

REPOSITORY
Mr-M13/delnavazan-platform

AUTHORITATIVE BASE
main @ 993532f1365644589b8a6134990cc0a1306456e9
tree a5e234c8d4ba4903f64d393f393d429a6b739e34

DEFECT
A completely fresh WordPress installation can fail Platform activation in Migrator::ensure_capabilities() with:
"Canonical attendance intake capability installation failed: Teacher claim grant".

Root cause observed on real local WordPress + MariaDB:
- ensure_capabilities() reads $teacher / $teacherRole = get_role('dzn_teacher') before the base role is guaranteed to exist;
- Phase-P and Phase-Q Teacher-specific repair/grant logic therefore skips on a truly fresh install;
- the base dzn_teacher role is created later in the same method;
- final verification then sees the newly-created Teacher role without dzn_submit_own_delivery_claim and fails closed.
This is an ordering/bootstrap defect, not a policy change.

GOAL
Make capability installation converge correctly on:
1. a brand-new WordPress install with no dzn_teacher role;
2. an existing install with a complete role;
3. a partially installed/corrupted role requiring capability repair.

REQUIREMENTS
- Preserve all existing capability names and least-privilege policy.
- Teachers must receive only the already-authorised Teacher-owned capabilities.
- Teachers must never receive Phase-P review/adjudication/identity/ingest authority, Phase-Q admin authority, or R1 commercial authority.
- Administrator grants remain unchanged.
- Existing installs must converge idempotently.
- Do not weaken final verification.
- Do not introduce deployment/provider/theme work.
- Keep the fix minimal and local to capability bootstrap/verification plus tests.

IMPLEMENTATION DIRECTION
Ensure the base dzn_teacher role exists before any Phase-P/Phase-Q Teacher-specific grant/repair logic that depends on it, or implement an equivalent ordering-safe repair that proves the same invariant. Avoid duplicate/conflicting role creation logic.

TESTS
Add focused regression evidence for:
- no dzn_teacher role -> capability install succeeds and expected Teacher grants exist;
- complete dzn_teacher role -> idempotent;
- partially missing Teacher Phase-P/Phase-Q grants -> repaired;
- reserved admin/review/commercial capabilities are absent from Teacher after repair;
- existing capability/version-marker contracts remain green.
Run all available static/contract tests.
If Docker is unavailable inside the agent sandbox, explicitly state that host fresh-install runtime validation is still required; do not claim runtime PASS.

CANDIDATE
Commit the correction. Do not amend/rebase/squash reviewed history.
Return exact candidate SHA/tree.
If repository network access is available, publish the branch normally; otherwise return the local commit identity and state that publication remains required.

END EXACTLY:
NEW CANDIDATE SHA / TREE: <sha> / <tree>
CANDIDATE AWAITING INDEPENDENT REVIEW
