# DELNAVAZAN THEME — SINGLE CONTENT PAGE V1 — CORRECTION ROUND 2 FINAL INDEPENDENT RE-REVIEW

Role: Hamed Cloud independent reviewer. Read-only. Do not implement, merge, deploy, commit, amend, rebase, squash, reset or rewrite history.

Repository: Mr-M13/delnavazan-theme
Authoritative main: 88398f2dd847c320dfd83f3db736d1525cfe3484
C1 reviewed candidate: 060f2cb2f864ec7e3f64b691f59eec36ee2fd8f1
C2 candidate: 642501f7106697af5e65ecef4373a82c9d429bf1
Expected tree: 736cdd303bb40f1b2ec4bc80b6f498f7aff93133
Branch: feat/single-content-v1-reconstruct-local

Verify exact remote/repo/branch/SHA/tree, clean isolated checkout, additive ancestry from C1 and exact main, and unchanged origin/main. Stop on mismatch.

Re-review all previous findings, with special focus on C2:

1. Valid quoted attributes:
- opposite quote inside active quoted value, e.g. title="don't > stop";
- >/< inside quoted values;
- entities, Persian/mixed text;
- no invalid global quote-count assumption.

2. Malformed/nested/overlapping headings:
- H2/H3 nesting both directions, same-level nesting, crossing/overlap, missing close/open;
- malformed ambiguous clusters must fail safe and never corrupt/duplicate output or create misleading TOC entries;
- neighbouring valid headings still work.

3. Final-document ID uniqueness:
- reserve dynamic wrapper IDs including main-content and post-{ID};
- feature-owned IDs reserved;
- authored/generated/non-heading/wrapper cross-collisions deterministic;
- independently inspect a full rendered document for duplicate IDs and TOC/aria targets.

4. CSS preservation:
- verify accessibility/compatibility utilities removed in C1 are restored relative to reviewed parent;
- screen-reader-text, focus, [hidden], branding/menu compatibility, nav/footer link compatibility, owned-media-slot sizing;
- actively inspect C2 diff for unrelated CSS regressions.

5. Print response predicate:
- marker only on actual document responses;
- positive: single post, default General page, Policy template;
- negative: front page, archive/non-singular, Student Portal, Teacher Portal, custom/plugin page templates outside document system.

Also revalidate all previously accepted items:
- pagination;
- no global the_content mutation;
- Persian tables RTL by default / LTR opt-in;
- Article/Policy/General separation;
- 43rem measure;
- desktop/mobile TOC behavior;
- query/reset hygiene;
- Portal isolation;
- Theme 0.7.0/package consistency.

Run all independently available checks and add adversarial probes beyond owner tests. Treat owner runtime claims only as claims unless independently rerun. Clearly mark browser/staging checks UNAVAILABLE if unavailable.

Final verdict exactly one:
PASS — MERGE PLANNING MAY PROCEED
or
FAIL — CORRECTION REQUIRED

If PASS, approve exactly:
Candidate 642501f7106697af5e65ecef4373a82c9d429bf1
Tree 736cdd303bb40f1b2ec4bc80b6f498f7aff93133

Do not merge or deploy.
