# DELNAVAZAN PLATFORM — PHASE 2A.2-R1 — CORRECTION ROUND 6

ROUTING
Agent: DeepSeek / Codex Desktop
Role: Platform implementation owner
Parallel mutation: NO

REPOSITORY
Mr-M13/delnavazan-platform

BRANCH
phase-2a2r1-commercial-purchase-funding-authority

AUTHORITATIVE MAIN
1b9d7aaef0ca21fdb861ccc9da1d15634cb5d843

STARTING CANDIDATE
C5 SHA: 2af26260d1ba711a18f9fc73c15923531cab69cd
C5 TREE: 46c712744ad545d7a7cb49ec03defddc97080f1a
Parent C4: 6de25b8c32a21d060c27e0f98a8a05a4d1a7bfaa

STATUS
C5 FINAL INDEPENDENT REVIEW: FAIL — CORRECTION REQUIRED
DO NOT MERGE. DO NOT DEPLOY.

Before mutation fetch origin and verify exact repository, branch, clean state, HEAD/tree, parent ancestry, and unchanged origin/main. Stop on mismatch.

HISTORY RULE
Correction must be additive descendant of exact C5. No amend, rebase, squash, reset, force push, merge, or history rewrite.

## C6-MAJOR-001 — complete operation-specific commercial_commands selector shape

Independent review found that C5 validates explicitly populated result/ownership fields but still permits contamination of selectors that should be NULL for a given operation.

The commercial_commands schema has nullable selectors including teacher_id, obligation_id, term_id, offer_id and others. Replay must validate the COMPLETE operation-specific persisted command shape, not merely populated fields.

Required rule:
1. Every selector/result/ownership field applicable to the operation must exactly equal the revalidated authoritative aggregate.
2. Every selector that is inapplicable to that operation must remain exactly NULL.
3. No foreign-but-valid selector may be ignored.
4. Same-key replay against a contaminated command must fail closed.
5. Do not silently repair the command.
6. Do not create a duplicate command or downstream truth.
7. Exact restoration must permit the normal idempotent replay.

At minimum close the independently demonstrated gaps:
- Term binding: teacher_id and obligation_id currently unchecked. Determine the authoritative operation-specific shape: if intentionally unused they must be exactly NULL; if semantically owned and persisted by design they must exactly match authoritative identity. Do not invent new redundant persistence merely to satisfy the test.
- Capacity handoff: term_id and obligation_id must have their intended exact shape (currently expected NULL unless the existing schema/write contract proves otherwise).
- Claim release: term_id and obligation_id must have their intended exact shape (currently expected NULL); preserve the C5 rule that release offer_id is NULL.
- Audit ALL remaining commercial_commands columns for binding, handoff, and release. Do not stop at the examples above. Encode an explicit complete-shape validator/check for each operation so another nullable selector cannot remain an unvalidated bypass.

Add same-key contamination probes for every newly enforced selector. Each probe must prove rejection, preserved corruption/no repair, no duplicate command/downstream truth, exact restoration, then successful idempotent replay.

Preserve all C5 independent passes:
- settlement occurrence authority = evidence ingested_at under existing acceptance write semantics;
- settlement currency and payment-fact currency integrity;
- release replay requires exact released lifecycle, controlled reason/released_at, no protected intervals;
- populated command result/ownership validation;
- NEW-C3-002 and all prior R1 BLOCKER/MAJOR passes;
- CommercialLineageValidator unchanged unless strictly necessary and justified;
- existing lock/transaction ordering;
- schema 25, migration/build identity, locked commercial model.

Run full owner validation:
- provenance/identity
- git diff --check
- all PHP lint/static/contract suites
- R1 migration/authority/corruption/failure runtimes
- focused C6 command contamination probes
- full 9-mode concurrency matrix
- adjacent Phase L/M0/M/N/O regressions
- classify known P/Q base failures only if reproduced on untouched authoritative main
- fresh-clone verification of pushed candidate

Update R1 authority docs, changelog and candidate continuity on implementation branch.

Publish by normal fast-forward push only.

Return:
- exact starting identity
- exact C6 defect resolution and complete selector-shape matrix by operation
- files changed
- tests added
- validation results
- regression/locking assessment
- commit history
- push/fresh-clone verification
- NEW CANDIDATE SHA
- NEW TREE SHA
- exact parent C5 SHA

End exactly:
PHASE 2A.2-R1 CORRECTION ROUND 6 IMPLEMENTED
CANDIDATE AWAITING INDEPENDENT RE-REVIEW
DO NOT MERGE
DO NOT DEPLOY
