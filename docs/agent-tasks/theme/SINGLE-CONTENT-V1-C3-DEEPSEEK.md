# DELNAVAZAN THEME — SINGLE CONTENT PAGE V1 — CORRECTION ROUND 3

ROUTING
Agent: DeepSeek / Codex Desktop
Role: Theme implementation owner
Repository: Mr-M13/delnavazan-theme
Branch: feat/single-content-v1-reconstruct-local
Starting candidate: 642501f7106697af5e65ecef4373a82c9d429bf1
Starting tree: 736cdd303bb40f1b2ec4bc80b6f498f7aff93133
Authoritative main: 88398f2dd847c320dfd83f3db736d1525cfe3484
Status: C2 independent re-review FAIL — ONE parser correction required.
Do not merge. Do not deploy. No history rewrite.

FIRST VERIFY
Fetch origin. Verify exact repo/branch/HEAD/tree, clean worktree, C2 and main ancestry, unchanged origin/main. Stop on mismatch.

PERSISTENCE
Additive descendant only. Normal fast-forward push. No amend/rebase/squash/reset/force push.

ONLY BLOCKING DEFECT
Malformed reverse-crossing/mismatched heading closures are not fully rejected before pairing.

Reproduced:
<h2>First</h3><h3>Second</h2>
C2 currently mutates this into an anchored H2 and creates a misleading TOC entry.

Related:
<h2>Broken</h3><h2>Valid neighbour</h2>
The malformed first heading greedily consumes the later valid H2 closing tag, causing the valid neighbouring heading to be discarded.

REQUIRED CORRECTION
Implement a heading tokenizer/parser strategy that respects tag order rather than independently searching each opening heading for the next same-level close.

At minimum:
- tokenize H2/H3 opening and closing tags in document order using the existing quote-aware tag-end logic;
- maintain enough structural state to identify well-formed non-nested heading pairs;
- any mismatched close, nested heading, crossing structure, unclosed open, stray close, or ambiguous cluster must fail safe for the malformed region;
- malformed markup must remain byte-stable and must produce no misleading outline entry;
- recovery must resume after the malformed region so a subsequent structurally valid neighbouring H2/H3 is still anchored and appears in the outline;
- do not let a malformed opening greedily consume a later valid heading's closing tag;
- preserve all valid heading behavior, authored IDs, final-document reservations, deterministic collisions and idempotence.

MANDATORY ADVERSARIAL TEST MATRIX
Include at least:
1. <h2>First</h3><h3>Second</h2>
2. <h2>Broken</h3><h2>Valid neighbour</h2> — malformed first untouched; valid neighbour anchored/outlines.
3. <h3>Broken</h2><h3>Valid neighbour</h3>
4. <h2>Outer <h3>Inner</h3> tail</h2>
5. <h3>Outer <h2>Inner</h2> tail</h3>
6. same-level nested H2/H2 and H3/H3.
7. stray closing tags before and between valid headings.
8. unclosed heading followed by a valid heading.
9. malformed region followed by multiple valid Persian headings.
10. valid headings containing nested inline non-heading markup remain supported.
11. valid opposite-quote / >/< attribute cases from C2 remain passing.
12. rerun idempotence and final-ID uniqueness.

PRESERVE ALL C2 ACCEPTED AREAS
Do not alter or regress:
- quote delimiter fix;
- document-wide/dynamic wrapper ID reservation;
- restored global utility/compatibility CSS;
- print response predicate;
- pagination;
- scoped content mutation;
- RTL tables;
- Article/Policy/General behavior;
- TOCs/43rem measure;
- Portal isolation;
- Theme 0.7.0.

VALIDATION
Run complete Theme suite, PHP lint, static checks, Portal suites, content render suite, new adversarial matrix, git diff --check, real disposable WordPress rendering if available, package build, fresh clone and rerun critical tests. Audit C3 diff to ensure it is parser/tests/docs only unless another file is strictly necessary.

DOCUMENTATION
Record C2 FAIL and C3 correction. Do not claim independent PASS.

RETURN
A. starting identity
B. exact parser correction
C. malformed-region recovery strategy
D. adversarial matrix results
E. regression results
F. files changed
G. commit/push verification
H. fresh-clone verification
I. new candidate SHA/tree
J. exact parent C2 candidate

END EXACTLY:
SINGLE CONTENT PAGE V1 CORRECTION ROUND 3 IMPLEMENTED
CANDIDATE AWAITING INDEPENDENT RE-REVIEW
DO NOT MERGE
DO NOT DEPLOY
