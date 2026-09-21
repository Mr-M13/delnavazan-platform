# DELNAVAZAN THEME — SINGLE CONTENT PAGE V1 — PERSISTENT RECONSTRUCTION

ROUTING
Agent: DeepSeek / Codex Desktop
Role: Theme implementation owner
Repository: Mr-M13/delnavazan-theme
Authoritative base: main at 88398f2dd847c320dfd83f3db736d1525cfe3484
Environment: local/persistent only
Parallel mutation: NO
Do not merge. Do not deploy.

WHY THIS ROUND EXISTS
The prior Ina Cloud reconstruction was implemented and locally validated as commit 9a986adc18c09906c23b6cbdbd0d3e14f51fbfe5 / tree dc83559bcdc06de5d0c0d6e989b50f7adf769da2 but could not be pushed because the cloud environment lacked GitHub credentials. That workspace then expired and the Git object became unrecoverable. Do NOT attempt recovery. Reconstruct cleanly from authoritative Theme main.

CRITICAL PERSISTENCE RULE
1. Use a NEW isolated local worktree from exact authoritative Theme main.
2. Create branch feat/single-content-v1-reconstruct-local.
3. PUSH THE EMPTY BRANCH TO ORIGIN IMMEDIATELY before implementation.
4. Commit and push in coherent increments as you work. Never allow the only copy of a material implementation state to exist solely in /tmp or an ephemeral environment.
5. Normal fast-forward pushes only. No force push, amend, rebase, squash, reset, merge, or deployment.

LOCKED PRODUCT TARGET
Reusable Persian RTL Article / Policy / General content-page system using the existing Delnavazan header/footer and visual language.

Required:
- preserve Persian-first RTL, typography, Turquoise & Pomegranate palette, current header/footer;
- readable document column around 43rem;
- standard WordPress authoring for H2/H3, paragraphs, lists, tables, blockquotes, images;
- deterministic heading anchors, preserving authored IDs where present and resolving duplicates predictably;
- sticky desktop table of contents;
- accessible native/keyboard-friendly collapsible mobile table of contents;
- Article mode: categories, publication metadata, reading time, featured image, related/previous-next content where existing WP data supports it;
- Policy mode: restrained policy metadata, no promotional treatment, print support;
- General/Help mode: simpler neutral presentation;
- responsive and accessibility-conscious;
- Student Portal and Teacher Portal must not be altered;
- no page builder or bespoke authoring workflow;
- target Theme version 0.7.0.

IMPLEMENTATION GUIDANCE
First inspect existing Theme conventions/components and reuse them. Keep the system WordPress-native and presentation-only. Prefer the simplest reusable architecture. Routine reversible product decisions are delegated to CD/implementation owner; record material decisions instead of blocking.

VALIDATION
Run all repository-provided Theme checks, PHP lint, render-contract tests, portal regressions, git diff --check, package build, and any available accessibility/static checks. If a browser/WordPress runtime is unavailable, state that honestly.

PUBLICATION
- push each coherent commit normally;
- final candidate must be remotely visible;
- fresh-clone origin branch;
- verify exact HEAD/tree/parent and clean state;
- confirm origin/main remains 88398f2dd847c320dfd83f3db736d1525cfe3484.

RETURN
Provide:
A. starting identity and remote
B. branch creation and early-push proof
C. architecture/product decisions
D. files changed
E. validation results
F. commit history
G. push verification
H. fresh-clone verification
I. NEW CANDIDATE SHA
J. NEW TREE SHA
K. exact parent main SHA

End exactly:
SINGLE CONTENT PAGE V1 RECONSTRUCTED
CANDIDATE AWAITING INDEPENDENT REVIEW
DO NOT MERGE
DO NOT DEPLOY
