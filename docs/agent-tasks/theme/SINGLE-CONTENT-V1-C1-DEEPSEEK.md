# DELNAVAZAN THEME — SINGLE CONTENT PAGE V1 — CORRECTION ROUND 1

ROUTING
Agent: DeepSeek / Codex Desktop
Role: Theme implementation owner
Repository: Mr-M13/delnavazan-theme
Branch: feat/single-content-v1-reconstruct-local
Starting candidate: 8e0af6253ff9c0243ff25000bf15a681efcf376b
Starting tree: 9bae2f8351e667173b1d350829d77018b0af9462
Authoritative main: 88398f2dd847c320dfd83f3db736d1525cfe3484
Status: independent review FAIL — correction required
Do not merge. Do not deploy. No history rewrite.

FIRST VERIFY
Fetch origin. Verify exact repo/remote/branch, HEAD=8e0af6253ff9c0243ff25000bf15a681efcf376b, tree=9bae2f8351e667173b1d350829d77018b0af9462, clean worktree, exact main ancestor, origin/main unchanged. Stop on mismatch.

HISTORY / PERSISTENCE RULE
All corrections must be additive descendants of the exact reviewed candidate. Normal fast-forward pushes only. No amend/rebase/squash/reset/force push. Push coherent increments promptly so no material state exists only locally.

FORMAL INDEPENDENT REVIEW BLOCKERS

1. HEADING PARSER MUST HANDLE VALID ATTRIBUTES CONTAINING ">"
Current regex opening-tag parsing (#<(h[1-6])\b([^>]*)>...) misparses quoted attribute values containing >, corrupting both TOC text and generated anchor.
Required:
- replace the brittle opening-tag parsing with a parser that correctly handles valid HTML attributes and nested inline markup;
- preserve authored heading IDs where allowed;
- derive visible heading text from actual heading inner content, not broken attribute fragments;
- preserve deterministic idempotent behavior;
- add adversarial tests for title/data attributes containing >, <, quotes, entities, nested inline tags, Persian text, and mixed markup;
- ensure malformed HTML fails safely without corrupting output.

2. COMPLETE DOCUMENT-WIDE ID COLLISION AVOIDANCE
Current registry only sees H2/H3 IDs and misses non-heading IDs plus theme-owned IDs such as dzn-toc-title-desktop.
Required:
- collect/reserve IDs from the whole rendered document before assigning heading anchors;
- reserve all theme-owned IDs introduced by this feature (desktop/mobile TOC title IDs or equivalent);
- never emit duplicate IDs;
- if an authored heading ID collides with any existing element/theme-owned ID, deterministically resolve it rather than silently duplicating;
- TOC hrefs and aria-labelledby must target the final unique IDs;
- add tests for non-heading element collisions, theme-owned ID collisions, repeated authored IDs, generated/authored cross-collisions, and mixed Persian anchors.

PARALLEL PRECHECK MAJORS — FIX IN SAME ROUND

3. PAGINATED CONTENT MUST PROVIDE READER NAVIGATION
The candidate disables generated anchors/TOC for <!--nextpage--> but the template omits wp_link_pages(), so readers can be stranded on the first segment.
Required:
- render wp_link_pages() for document modes after the_content output;
- use localized accessible labels;
- add render/runtime coverage proving a paginated document exposes navigation to remaining pages;
- preserve the deliberate "no generated TOC/anchor rewrite for paginated content" carve-out unless a better core-compatible approach is justified.

4. SCOPE HEADING MUTATION TO DOCUMENT RENDERING ONLY
dzn_theme_content_page_filter_content() is globally registered on the_content without a sufficiently narrow document guard, affecting front page, widgets/plugins, feeds/REST/future portal contexts.
Required:
- remove global unintended mutation outside Article/Policy/General document rendering;
- prefer a narrowly bounded render-state guard or processing directly within the document template;
- at minimum require supported singular/main-query/template context;
- prove front page, non-singular loops, plugin-like secondary the_content calls, feeds/REST-style contexts, and Portal surfaces are not modified;
- avoid duplicate IDs across multiple content items on one response.

5. PERSIAN TABLES MUST REMAIN RTL BY DEFAULT
Shared prose styles currently force all tables direction:ltr and text-align:left.
Required:
- keep document tables RTL / inherited direction and alignment by default;
- retain horizontal overflow independently;
- allow explicit LTR behavior only through an intentional opt-in class or dir=ltr;
- add CSS/static/render coverage for Persian and mixed numeric/Latin tables.

6. PRINT RULES MUST NOT BLEED TO NON-DOCUMENT SURFACES
Current print rules hide global site chrome and alter body styling on every page.
Required:
- scope document-specific print behavior to document-mode pages via body/document selectors;
- do not change Portal or homepage print behavior as a side effect of this feature;
- add negative static checks for Portal/homepage print bleed.

PRESERVE EXISTING POSITIVES
Do not regress:
- Article / Policy / General mode separation;
- 43rem document measure;
- desktop sticky TOC + native mobile details/summary;
- JS-free TOC;
- Article metadata, featured image, related and previous/next content;
- restrained Policy mode and print hint;
- General neutrality;
- portal isolation;
- Theme version 0.7.0;
- package build;
- existing portal render regressions;
- WordPress-native authoring/no page builder;
- no new persistence.

VALIDATION
Run:
- exact provenance and git diff --check;
- node static theme validation;
- portal dialog tests and all JS syntax checks;
- PHP lint;
- content-page render tests including all new adversarial cases;
- Student Portal and Teacher Portal render regressions;
- real disposable WordPress rendering if available;
- paginated post/page runtime navigation proof;
- RTL/Persian table rendering/static proof;
- print CSS scoping proof;
- package build and version check;
- fresh clone of pushed branch and rerun critical checks.

DOCUMENTATION
Update Theme docs/changelog to record independent FAIL findings and correction round. Do not claim PASS.

RETURN
A. starting identity
B. exact fixes for six findings
C. parser/ID-collision design
D. files changed
E. tests added
F. validation results
G. commit history
H. push verification
I. fresh-clone verification
J. new candidate SHA/tree
K. exact parent reviewed candidate

END EXACTLY:
SINGLE CONTENT PAGE V1 CORRECTION ROUND 1 IMPLEMENTED
CANDIDATE AWAITING INDEPENDENT RE-REVIEW
DO NOT MERGE
DO NOT DEPLOY
