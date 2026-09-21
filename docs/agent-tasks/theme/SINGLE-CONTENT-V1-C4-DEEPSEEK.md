# DELNAVAZAN THEME — SINGLE CONTENT PAGE V1 — CORRECTION ROUND 4

ROUTING
Agent: DeepSeek / Codex Desktop
Role: Theme implementation owner
Repository: Mr-M13/delnavazan-theme
Branch: feat/single-content-v1-reconstruct-local
Starting candidate: eb778142a021ba8b71eba2d2659687dde8559328
Starting tree: 0eb2b795abdf60a9e684bfa44357c97fe67b8ab9
Authoritative main: 88398f2dd847c320dfd83f3db736d1525cfe3484
Status: C3 independent re-review FAIL — malformed-opening-tag recovery defect.
Do not merge. Do not deploy. No history rewrite.

VERIFY FIRST
Fetch origin and verify exact repo, branch, clean worktree, HEAD/tree above, C3/main ancestry and unchanged origin/main. Stop on mismatch.

PERSISTENCE
Additive descendant only; normal fast-forward pushes. No amend/rebase/squash/reset/force push.

FORMAL C3 REVIEW FINDING
All owner suites and the required C3 crossing/nesting/recovery/Persian/ID/idempotence scenarios passed.
One new blocking parser-recovery defect was independently reproduced:
After rejecting a malformed heading opening tag, the tokenizer can interpret heading-like text INSIDE that malformed tag or its unterminated quoted attribute as genuine markup. The reviewer reproduced three inputs where malformed markup was mutated and erroneous anchors/outline entries were generated.
The review workspace commit containing the literal probes was not published, so do NOT guess-patch only a known string. Reconstruct and test the defect class generically.

REQUIRED CORRECTION
The tokenizer must have a safe recovery boundary after any malformed opening tag / unterminated tag or quoted attribute:
- bytes that belong to a malformed tag lexeme, including heading-looking substrings inside its attribute/text before a trustworthy tag boundary, must never be re-tokenized as markup;
- malformed input must remain byte-stable and produce no anchors/outline entries from pseudo-tags embedded inside the malformed lexeme;
- recovery may resume only at a defensible structural boundary;
- after recovery, genuinely separate valid neighbouring headings must still be recognized;
- never greedily consume unrelated later valid markup;
- preserve C3 ordered structural pairing behavior.

INDEPENDENT DEFECT RECONSTRUCTION
Before editing, create adversarial probes that demonstrate the reported class against exact C3. Include multiple distinct shapes, at minimum:
1. unterminated double-quoted H2 attribute containing literal <h3>...</h3>-looking text;
2. unterminated single-quoted H3 attribute containing literal <h2>...</h2>-looking text;
3. malformed H2 opening tag containing a nested '<' outside quotes followed by heading-looking bytes;
4. malformed opening tag followed later by a genuinely separate valid H2/H3; malformed pseudo-heading must be ignored but valid neighbour must recover where structurally defensible;
5. multiple malformed regions separated by valid headings;
6. > and < inside correctly terminated quoted attributes remain valid;
7. opposite quote cases from C2 remain valid;
8. C3 reverse-crossing/mismatched/nested/stray-close/unclosed matrix remains passing;
9. mixed-case H2/H3 tags;
10. Persian valid neighbours after malformed regions;
11. idempotence and final rendered ID uniqueness.

Do not simply expand a regex blacklist. Define/document the lexical recovery rule and why pseudo-tags inside malformed lexemes cannot escape into the token stream.

PRESERVE ALL ACCEPTED WORK
No regressions to dynamic/final-document ID reservation, CSS utilities, print predicate, pagination, scoped content mutation, RTL tables, Article/Policy/General modes, TOCs/43rem measure, Portal isolation, version 0.7.0. C4 should remain parser/tests/docs only unless strictly necessary.

VALIDATION
Run complete theme/static/render/Portal/PHP/JS suites, new adversarial probes, git diff --check, disposable WordPress runtime if available, package build, and fresh-clone rerun. Actively search for additional malformed-tag recovery bypasses beyond the minimum matrix.

DOCUMENTATION
Record C3 FAIL and C4 correction accurately. Do not claim independent PASS.

RETURN
A starting identity
B reproduced defect probes on C3
C lexical recovery design
D exact correction
E adversarial results
F regression results
G files changed
H commit/push verification
I fresh-clone verification
J new candidate SHA/tree
K exact parent C3 candidate

END EXACTLY:
SINGLE CONTENT PAGE V1 CORRECTION ROUND 4 IMPLEMENTED
CANDIDATE AWAITING INDEPENDENT RE-REVIEW
DO NOT MERGE
DO NOT DEPLOY
