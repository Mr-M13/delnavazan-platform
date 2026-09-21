# DELNAVAZAN THEME — SINGLE CONTENT PAGE V1 — CORRECTION ROUND 2

ROUTING
Agent: DeepSeek / Codex Desktop
Role: Theme implementation owner
Repository: Mr-M13/delnavazan-theme
Branch: feat/single-content-v1-reconstruct-local
Starting candidate: 060f2cb2f864ec7e3f64b691f59eec36ee2fd8f1
Starting tree: 7be1b59bde35ea13cd1908d3d6940bde0a237651
Reviewed parent: 8e0af6253ff9c0243ff25000bf15a681efcf376b
Authoritative main: 88398f2dd847c320dfd83f3db736d1525cfe3484
Status: C1 independent re-review FAIL — correction required.
Do not merge. Do not deploy. No history rewrite.

FIRST VERIFY
Fetch origin; verify exact repo/remote/branch, HEAD/tree above, clean worktree, reviewed parent and exact main are ancestors, origin/main unchanged. Stop on mismatch.

HISTORY / PERSISTENCE
Corrections must be additive descendants of exact C1 candidate. Normal fast-forward pushes only. No amend/rebase/squash/reset/force push. Push coherent increments promptly.

C1 ITEMS ALREADY ACCEPTED — MUST NOT REGRESS
- pagination via wp_link_pages with localized accessible navigation;
- no generated TOC/anchor rewrite for paginated content;
- no global the_content anchor mutation;
- Persian tables RTL/inherited by default with explicit LTR opt-in;
- document-mode separation, 43rem measure, TOCs, Article/Policy/General behavior, portal render suites, version 0.7.0.

CORRECTION ITEMS

1. VALID OPPOSITE QUOTE INSIDE QUOTED ATTRIBUTE
Reproduced valid HTML:
<h2 title="don't > stop">Valid heading</h2>
C1 scanner tracks active delimiter correctly, but a later "balanced" check independently requires even counts of both quote characters and rejects the heading.
Required:
- remove invalid global quote-count assumptions;
- validate according to the active quote delimiter only;
- correctly handle apostrophe inside double-quoted values and double quote characters/entities inside single-quoted values where valid;
- retain support for >/< inside quoted values;
- add adversarial tests combining opposite quote + >/< + entities + Persian/mixed text.

2. MALFORMED NESTED/OVERLAPPING HEADINGS MUST FAIL SAFE
Reproduced malformed input:
<h2>Outer <h3>Inner</h3> tail</h2>
C1 accepts overlapping heading ranges and reverse replacement corrupts output with duplicated trailing markup.
Required:
- detect nested/overlapping heading candidate ranges before mutation;
- malformed overlapping/nested heading structures must remain untouched (or be safely excluded) and must never corrupt/duplicate markup;
- no TOC entries should be generated from ambiguous malformed ranges;
- add tests for H2/H3 nesting both directions, same-level nesting, crossing/overlap shapes, missing close/open, and ensure byte-stable safe fallback where appropriate.

3. TRUE FINAL-DOCUMENT ID RESERVATION
C1 scans only filtered post-content plus fixed theme-owned IDs. It misses dynamic IDs emitted by surrounding template, e.g. article wrapper id="post-42" and page main id="main-content".
Required:
- reserve all IDs that the document template/wrappers will emit before heading anchor assignment, including dynamic post-{ID}, main-content, TOC IDs, related IDs and any other deterministic wrapper IDs;
- design one explicit function/contract for template-owned reserved IDs so future wrapper IDs are not silently omitted;
- heading authored/generated IDs must never collide with surrounding document IDs;
- add render/runtime test for authored <h2 id="post-42"> on post 42 and main-content/theme wrapper collisions;
- verify final rendered document ID uniqueness, not only content-fragment uniqueness.

4. RESTORE UNRELATED CSS REMOVED BY C1
Diff against reviewed parent shows C1 accidentally deleted global utility/compatibility sections:
.screen-reader-text
.screen-reader-text:focus
[hidden]
responsive .site-branding__description / .menu-toggle__label
primary-navigation link-color compatibility
footer link-color compatibility
owned-media-slot image sizing
Required:
- restore these exactly from reviewed parent unless a correction genuinely requires a narrowly documented change;
- audit the entire C1 CSS diff for any other unrelated deletion/regression;
- add static preservation checks for critical accessibility utilities and compatibility selectors;
- prove Portal/homepage/shared theme behavior is not changed by C2 except intended document feature rules.

5. PRINT MARKER MUST MATCH ACTUAL DOCUMENT RENDERING
C1 body class dzn-document-body is added to broad singular post/page responses, including custom/plugin page templates that may not render .dzn-document.
Required:
- apply document print marker only when the actual response uses this document system/template;
- Article single, normal General page and Policy template should receive it;
- front page, Portal templates, plugin/custom page templates outside this system, archives/non-singular should not;
- prefer one shared mode/template predicate used by both rendering and body-class logic to prevent drift;
- add static/render/runtime tests for all positive and negative cases.

6. REGRESSION / ADVERSARIAL AUDIT
Actively search beyond listed cases:
- quote-aware parser correctness, malformed HTML safety, overlapping ranges;
- final DOM duplicate IDs;
- TOC href/aria target correctness;
- pagination accepted behavior;
- no global filter leakage;
- query/reset hygiene;
- CSS deletions/bleed;
- print behavior;
- accessibility utilities;
- portal isolation.

VALIDATION
Run provenance, git diff --check, static theme checks, portal dialog tests, JS syntax, PHP lint, all render suites, new adversarial parser tests, final rendered-ID uniqueness tests, real disposable WordPress rendering if available, pagination proof, body-class template proof, CSS preservation proof, package build/version, fresh clone and rerun critical checks.

DOCUMENTATION
Record C1 FAIL and C2 corrections without claiming independent PASS.

RETURN
A. starting identity
B. fixes for five findings
C. parser malformed-markup strategy
D. final-document ID reservation contract
E. CSS preservation audit
F. tests added
G. validation
H. commit/push history
I. fresh-clone verification
J. new candidate SHA/tree
K. exact parent C1 candidate

END EXACTLY:
SINGLE CONTENT PAGE V1 CORRECTION ROUND 2 IMPLEMENTED
CANDIDATE AWAITING INDEPENDENT RE-REVIEW
DO NOT MERGE
DO NOT DEPLOY
