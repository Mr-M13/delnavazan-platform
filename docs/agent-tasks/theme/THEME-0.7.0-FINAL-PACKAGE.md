# DELNAVAZAN THEME — FINAL 0.7.0 PACKAGE

ROUTING
Agent: DeepSeek / local Codex runner
Role: packaging/verification owner
Repository: Mr-M13/delnavazan-theme
Authoritative main: e39f5b5c88b139163bd2a3906631e3d79325b1b5
Expected tree: 34355ca2a76a56933f6c7312ba38b46244e4c538
No merge. No deployment.

OBJECTIVE
Produce the final merged Theme 0.7.0 package from exact authoritative main and verify it is a real, reproducible downloadable artefact.

VERIFY FIRST
Fetch origin and verify:
- repo/remote identity;
- main HEAD exactly e39f5b5c88b139163bd2a3906631e3d79325b1b5;
- tree exactly 34355ca2a76a56933f6c7312ba38b46244e4c538;
- clean worktree;
- no divergence from origin/main.
Stop on mismatch.

PACKAGE
Run the repository's canonical package/build script from exact main.
Expected Theme version: 0.7.0.
Do not alter source unless packaging itself reveals a genuine defect; if it does, stop and report rather than silently fixing.

VERIFY PACKAGE
- archive exists and is non-empty;
- filename/version are correct;
- style.css inside archive reports Version: 0.7.0;
- package contains the merged Single Content Page files and existing portal/theme assets;
- no development-only junk, .git, temp files, local secrets or build workspace paths;
- checksum generated and independently recomputed;
- extract archive into a fresh temp directory;
- inspect extracted tree;
- run all package-compatible static/JS/PHP checks available against extracted package;
- compare packaged source files to exact main where appropriate;
- record archive byte size and SHA-256.

PUBLISHABLE OUTPUT
Copy the final verified ZIP and checksum into:
~/Library/Application Support/DelnavazanAgentBridge/artifacts/theme-0.7.0/
with stable names:
delnavazan-production-theme-0.7.0.zip
delnavazan-production-theme-0.7.0.sha256

Do not claim user-facing download publication; CD will handle saving the verified files to an accessible Library/artifact surface after this task.

RETURN
A source identity
B build command/result
C package path
D archive size
E SHA-256
F internal version verification
G extracted validation
H junk/secret audit
I final stable local artifact paths

END EXACTLY:
THEME 0.7.0 FINAL PACKAGE VERIFIED
READY FOR USER-FACING ARTEFACT PUBLICATION
DO NOT DEPLOY
