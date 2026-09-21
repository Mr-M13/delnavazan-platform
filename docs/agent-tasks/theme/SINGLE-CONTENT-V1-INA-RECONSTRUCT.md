# DELNAVAZAN THEME — SINGLE CONTENT PAGE V1 RECONSTRUCTION

ROUTING
Agent: Ina Cloud
Role: Theme implementation owner
Repository: Mr-M13/delnavazan-theme
Authoritative base: main at 88398f2dd847c320dfd83f3db736d1525cfe3484
Mode: implementation on a NEW branch. Do not deploy. Do not merge.

BACKGROUND
The prior unpublished Single Content Page V1 candidate 1190359e186111df6d50a5bd8266baf148e3e2a2 / tree d2ebcfbb6a2ed4803aaa0810148749d8181ea4f6 disappeared with its cloud filesystem. Recovery was exhausted. It was NOT rejected by code review. Reconstruct cleanly from authoritative Theme main; do not search for or depend on the lost candidate.

LOCKED PRODUCT TARGET
Build a reusable Persian RTL Article / Policy / General content-page system using the existing Delnavazan header/footer and visual language.

Required:
- preserve existing Persian RTL, typography, Turquoise & Pomegranate palette and current header/footer;
- readable document column around 43rem;
- robust standard WordPress authoring for H2/H3, paragraphs, ordered/unordered lists, tables, blockquotes and images;
- deterministic heading anchors;
- sticky desktop table of contents;
- accessible collapsible mobile table of contents;
- Policy mode: restrained policy metadata, print support, no promotional treatment;
- Article mode: richer hero/category/reading-time treatment plus related/previous/next content where existing WP data supports it;
- General/Help mode: simpler neutral presentation;
- responsive and keyboard/accessibility-conscious;
- do not alter Student Portal or Teacher Portal;
- do not introduce a page builder or bespoke authoring workflow;
- Theme target version 0.7.0.

IMPLEMENTATION RULES
1. Fetch origin and verify exact authoritative main SHA before mutation. Stop on mismatch.
2. Create a NEW reconstruction branch from exact main; do not reuse the lost branch/candidate.
3. Inspect existing Theme conventions/components first and reuse them rather than duplicating header/footer/design primitives.
4. Keep scope limited to the reusable content system and necessary docs/version/tests.
5. No production/staging deployment and no merge.
6. Run all available PHP/static/theme checks, diff checks and any repository tests.
7. Publish the new branch immediately by normal push once coherent; do not leave the only candidate in a cloud filesystem.
8. Fresh-clone the pushed branch and verify exact candidate SHA/tree and clean state.
9. Return a concise implementation report with files changed, product decisions made, validation, new branch, candidate SHA/tree, parent main SHA and fresh-clone verification.

PRODUCT DECISION AUTHORITY
CD has delegated authority to make routine reversible product decisions. Where details are not locked above, choose the simplest reusable WordPress-native solution consistent with the existing Theme. Record material decisions; do not block waiting for Hamed unless an irreversible/external commitment is required.

END STATUS
SINGLE CONTENT PAGE V1 RECONSTRUCTED
CANDIDATE AWAITING INDEPENDENT REVIEW
DO NOT MERGE
DO NOT DEPLOY
