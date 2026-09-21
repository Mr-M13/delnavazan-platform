# DELNAVAZAN THEME — SINGLE CONTENT PAGE V1 — CORRECTION ROUND 5

ROUTING
Agent: DeepSeek / Codex local runner
Role: Theme implementation owner
Repository: Mr-M13/delnavazan-theme
Branch: feat/single-content-v1-reconstruct-local
Starting candidate: 4f89109fef9f6cf571b6b5213e736853a0bb1e18
Starting tree: 436df575eb3067bf2f844513799888fab3010fce
Authoritative main: 88398f2dd847c320dfd83f3db736d1525cfe3484
Status: C4 independent review FAIL — ONE blocking regression.
Do not merge. Do not deploy. No history rewrite.

VERIFY FIRST
Fetch origin and verify exact repo, branch, clean worktree, HEAD/tree above, C4/C3/main ancestry, and unchanged origin/main. Stop on mismatch.

BLOCKING DEFECT
C4 removed the previously established 2 KB fail-closed opening-tag limit.

Independent reviewer finding:
- dzn_theme_content_page_tag_scan() now scans unbounded tag lexemes.
- A >2 KB opening heading tag can therefore be accepted and rewritten instead of remaining byte-stable.
- This violates the preserved C1/C2 malformed-tag contract.

REQUIRED CORRECTION
Restore a bounded fail-closed scan while preserving the C4 lexical recovery design.

Requirements:
1. Any opening tag lexeme exceeding the established 2048-byte maximum before a trustworthy closing boundary must be classified malformed and left byte-stable.
2. No anchor or outline entry may be produced from an oversized tag or pseudo-heading bytes inside it.
3. Recovery after an oversized malformed lexeme must follow the same defensible-boundary rules as C4; do not reintroduce pseudo-tag leakage.
4. A genuinely separate valid neighbour after the malformed region must still recover when structurally defensible.
5. Preserve all valid C4 cases: quoted >/<, opposite quotes, nested-< malformed recovery, Persian neighbours, mixed-case tags, C3 crossing/nesting/mismatch matrix, idempotence, and final-ID uniqueness.
6. Do not broaden scope beyond parser/tests/docs unless strictly necessary.

MANDATORY TESTS
Add independent-style tests for:
- opening H2 attribute/tag >2048 bytes with a valid closing > after the limit;
- opening H3 tag >2048 bytes;
- oversized malformed tag containing literal H2/H3-looking pseudo-markup;
- oversized malformed tag followed by valid H2/H3 neighbour;
- just-under-limit valid tag remains valid;
- exact boundary behavior documented and tested;
- repeated oversized regions;
- all C4 adversarial cases remain green.

VALIDATION
Run full Theme static/render/Portal/PHP/JS suites, git diff --check, disposable WordPress runtime if available, package build/version, and fresh-clone rerun. Push additive corrections normally.

DOCUMENTATION
Record C4 independent FAIL and C5 correction. Do not claim independent PASS.

RETURN
A starting identity
B exact bounded-scan fix
C boundary semantics
D tests added
E regression results
F files changed
G commit/push verification
H fresh-clone verification
I new candidate SHA/tree
J exact parent C4 candidate

END EXACTLY:
SINGLE CONTENT PAGE V1 CORRECTION ROUND 5 IMPLEMENTED
CANDIDATE AWAITING INDEPENDENT RE-REVIEW
DO NOT MERGE
DO NOT DEPLOY
