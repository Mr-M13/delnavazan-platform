# DELNAVAZAN THEME — SINGLE CONTENT PAGE V1 — FINAL INDEPENDENT REVIEW

Role: Hamed Cloud independent reviewer. Read-only. Do not implement, merge, deploy, amend, or rewrite history.

Repository: Mr-M13/delnavazan-theme
Authoritative main: 88398f2dd847c320dfd83f3db736d1525cfe3484
Candidate branch: feat/single-content-v1-reconstruct-local
Candidate SHA: 8e0af6253ff9c0243ff25000bf15a681efcf376b
Expected tree: 9bae2f8351e667173b1d350829d77018b0af9462

Verify exact remote/repo/branch/SHA/tree, clean isolated checkout, candidate ancestry from exact main, and unchanged origin/main.

Scope: independently review the reconstructed reusable Article / Policy / General content-page system.

Review critically:
1. WordPress-native architecture and no page-builder/custom-field dependency.
2. Mode selection: posts=Article, pages=General/Help, Policy template=Policy.
3. Deterministic H2/H3 anchors: authored IDs preserved, duplicates stable, empty headings fallback, idempotence, outline generated from same rendered content.
4. TOC behavior: sticky desktop; accessible native mobile disclosure; 3-section gate; no JS dependency; correct heading links.
5. Article treatment: categories, date, reading time, featured image, related and previous/next behavior.
6. Policy treatment: restrained metadata, no promotional Article elements, print behavior.
7. General/Help neutrality.
8. RTL/Persian typography and design-system reuse; 43rem document measure.
9. Responsive/accessibility/focus semantics as far as static/runtime environment permits.
10. Portal isolation: Student and Teacher Portal not regressed.
11. Paginated content carve-out and any undesirable edge case it introduces.
12. Security/escaping/output safety and WordPress query/reset hygiene.
13. Version/package consistency at Theme 0.7.0.
14. Tests: static, render, WordPress runtime, portal regressions. Distinguish independently executed PASS from owner claims.
15. Actively search for new defects: anchor collisions, malformed heading parsing, nested markup, duplicate IDs, TOC mismatch, query pollution, mode misclassification, empty-content behavior, accessibility regressions, CSS bleed, print regressions, or portal bleed.

Run all independently available checks. If runtime/browser tooling is unavailable, state UNAVAILABLE rather than assuming PASS.

Final verdict exactly one:
PASS — MERGE PLANNING MAY PROCEED
or
FAIL — CORRECTION REQUIRED

If PASS, approve exactly:
Candidate 8e0af6253ff9c0243ff25000bf15a681efcf376b
Tree 9bae2f8351e667173b1d350829d77018b0af9462

Do not merge or deploy.