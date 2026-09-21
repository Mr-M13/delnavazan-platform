# DELNAVAZAN THEME — SINGLE CONTENT PAGE V1 — CORRECTION ROUND 1 FINAL INDEPENDENT RE-REVIEW

Role: Hamed Cloud independent reviewer. Read-only. Do not implement, merge, deploy, amend, rebase, squash, or rewrite history.

Repository: Mr-M13/delnavazan-theme
Authoritative main: 88398f2dd847c320dfd83f3db736d1525cfe3484
Reviewed parent candidate: 8e0af6253ff9c0243ff25000bf15a681efcf376b
Correction Round 1 candidate: 060f2cb2f864ec7e3f64b691f59eec36ee2fd8f1
Expected tree: 7be1b59bde35ea13cd1908d3d6940bde0a237651
Branch: feat/single-content-v1-reconstruct-local

Verify exact repo/remote/branch/SHA/tree, clean isolated checkout, additive ancestry from reviewed parent and exact main, and unchanged origin/main. Stop on mismatch.

The previous formal review found two blockers; Ina parallel precheck found three additional majors plus one minor. Independently verify all six corrections.

1. Heading parser:
- valid attributes containing > or < inside quoted values must not corrupt opening-tag parsing;
- single/double quotes, entities, NBSP, nested inline markup and Persian/mixed content must preserve correct visible heading text and deterministic anchors;
- malformed/unbalanced tags must fail safely without corrupting output.

2. Document-wide ID collision avoidance:
- all IDs already in rendered document must be reserved before heading assignment;
- theme-owned IDs (desktop/mobile TOC labels, related title, etc.) must be reserved;
- authored/generated/non-heading/theme-owned cross-collisions resolve deterministically with no duplicate IDs;
- TOC href and aria-labelledby target final unique IDs;
- idempotence preserved.

3. Pagination:
- paginated document renders wp_link_pages() after content;
- accessible localized labels/navigation;
- no generated anchors/TOC on paginated content unless explicitly changed and justified;
- independently test links/current-page semantics.

4. Scoped mutation:
- no global unintended the_content anchor mutation;
- front page, non-singular/archive loop, secondary/plugin-like content, feed/REST-like contexts and Portal surfaces remain untouched;
- no duplicate IDs from multiple content items on one response.

5. Persian tables:
- RTL/inherited direction by default;
- horizontal overflow preserved;
- LTR only opt-in via dir=ltr or explicit class;
- no global table-direction regression.

6. Print scoping:
- document-specific print rules must be scoped to document pages only;
- homepage and Student/Teacher Portal print behavior not altered by this feature.

Preserve previous positives:
- Article/Policy/General mode separation;
- 43rem measure;
- sticky desktop TOC + native mobile details/summary;
- Article metadata/media/related/prev-next;
- restrained Policy mode and print hint;
- General neutrality;
- portal isolation;
- version 0.7.0;
- no page builder/persistence.

Run all independently available static/render/runtime checks. Clearly distinguish independent PASS/FAIL/UNAVAILABLE. Actively search for new regressions or bypasses around parser edge cases, DOM IDs, filters, pagination, CSS/print bleed, accessibility, query/reset, malformed HTML and portal isolation.

Final verdict exactly one:
PASS — MERGE PLANNING MAY PROCEED
or
FAIL — CORRECTION REQUIRED

If PASS, approve exactly:
Candidate 060f2cb2f864ec7e3f64b691f59eec36ee2fd8f1
Tree 7be1b59bde35ea13cd1908d3d6940bde0a237651

Do not merge or deploy.
