# DELNAVAZAN THEME — SINGLE CONTENT PAGE V1 — CORRECTION ROUND 4 FINAL INDEPENDENT RE-REVIEW

Role: independent reviewer using the separate OpenAI Codex review profile. READ ONLY.
Do not implement, commit, push, merge, deploy, amend, rebase, squash, reset, or modify repository history.

Repository: Mr-M13/delnavazan-theme
Authoritative main: 88398f2dd847c320dfd83f3db736d1525cfe3484
C3 reviewed candidate: eb778142a021ba8b71eba2d2659687dde8559328
C4 candidate: 4f89109fef9f6cf571b6b5213e736853a0bb1e18
Expected tree: 436df575eb3067bf2f844513799888fab3010fce
Branch: feat/single-content-v1-reconstruct-local

VERIFY FIRST
- exact repository, remote, branch, SHA/tree;
- clean isolated checkout;
- C4 is additive descendant of exact C3 and authoritative main;
- origin/main remains unchanged.

C3 formal review left one blocker:
after rejecting a malformed heading opening tag, the tokenizer could re-tokenize heading-looking bytes inside the malformed tag / unterminated attribute as genuine markup.

C4 claims to fix the lexical recovery boundary. Independently verify:

1. Unterminated double-quoted H2 attribute containing literal H3-looking markup produces no pseudo-heading anchor/outline mutation.
2. Unterminated single-quoted H3 attribute containing literal H2-looking markup likewise fails safe.
3. Malformed opening tag containing nested '<' outside quotes cannot leak the nested heading-looking bytes into tokenization.
4. Malformed region followed by a genuinely separate valid neighbour: malformed bytes remain untouched; valid neighbour still anchors/outlines when structurally defensible.
5. Multiple malformed regions separated by valid headings.
6. Correctly terminated quoted attributes containing >/< remain valid.
7. C2 opposite-quote cases remain valid.
8. C3 reverse-crossing / mismatched / nested / stray-close / unclosed matrix remains passing.
9. Mixed-case heading tags.
10. Persian valid neighbours after malformed regions.
11. Idempotence.
12. Final rendered ID uniqueness and TOC/aria target correctness.

Actively search beyond owner tests for lexical-recovery bypasses:
- malformed tag starts with multiple '<' bytes;
- unterminated attributes containing both H2 and H3 lookalikes;
- malformed attributes followed by comments, entities, inline tags, H4-H6, or script/style-like text;
- recovery boundary too broad (swallows later valid markup) or too narrow (lets pseudo-tags escape);
- malformed EOF cases.

Revalidate no regression in all previously accepted feature areas:
- document-wide/dynamic wrapper ID reservation;
- restored global accessibility/compatibility CSS;
- print response predicate;
- pagination;
- scoped content mutation;
- RTL tables;
- Article / Policy / General behavior;
- TOCs and 43rem measure;
- Portal isolation;
- Theme 0.7.0/package consistency.

Run all independently available static/render/PHP/JS tests and adversarial probes. If Docker/WordPress/browser/staging is unavailable in the reviewer environment, mark UNAVAILABLE rather than assuming PASS. Owner claims are not independent PASS.

FINAL VERDICT exactly one:
PASS — MERGE PLANNING MAY PROCEED
or
FAIL — CORRECTION REQUIRED

If PASS, approve exactly:
Candidate 4f89109fef9f6cf571b6b5213e736853a0bb1e18
Tree 436df575eb3067bf2f844513799888fab3010fce

Do not merge or deploy.
