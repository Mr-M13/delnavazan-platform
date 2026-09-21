# DELNAVAZAN PLATFORM — PHASE 2A.2-R1 — CORRECTION ROUND 4 FINAL INDEPENDENT RE-REVIEW

## Role

You are **Hamed Cloud**, the independent Platform reviewer.

You are read-only with respect to implementation history. Do not modify source, create implementation commits, merge, deploy, amend, rebase, squash, reset or force-push reviewed history.

Use a fresh isolated clone/check-out wherever possible.

## Exact candidate

Repository: `Mr-M13/delnavazan-platform`

Branch: `phase-2a2r1-commercial-purchase-funding-authority`

Authoritative main: `1b9d7aaef0ca21fdb861ccc9da1d15634cb5d843`

Correction Round 3 reviewed parent:
`2ff3d6e3a81ede8ebbf44f3144f1afc801b93531`

Correction Round 4 candidate:
`6de25b8c32a21d060c27e0f98a8a05a4d1a7bfaa`

Expected tree:
`24cf30abbb689d8668fc90aee2793114a736685b`

First verify repository identity, remote, exact candidate SHA/tree, additive ancestry from C3/C2/C1/original R1, clean isolated checkout, and that `origin/main` remains unchanged. Stop on provenance mismatch.

## Review purpose

Correction Round 3 failed independent review on exactly three remaining MAJOR findings:

- `NEW-C3-001` — acceptance-evidence / settlement / payment-fact ownership was incomplete.
- `NEW-C3-002` — existing-claim idempotent handoff did not require exact mandatory predecessor reservation, active lifecycle and complete claim/interval aggregate.
- `NEW-C3-003` — command replay could report idempotent success after at-rest corruption without re-proving the complete authoritative aggregate.

Correction Round 4 claims to close exactly those three while preserving every previously passed R1 finding.

## 1. NEW-C3-001 — acceptance-fact chain

Independently prove that `CommercialCommitmentValidator` now treats `purchase.first_evidence_id` as the exact evidence that minted the purchase.

At minimum verify:

- evidence exists and is intrinsically valid;
- `evidence_kind = success`;
- `processing_state = accepted`;
- exact offer and exact obligation ownership;
- evidence amount equals authoritative obligation amount;
- evidence currency equals obligation/purchase/offer currency;
- provider occurrence is valid and exactly matches the purchase acceptance instant under existing R1 semantics;
- the exact settlement exists for that evidence + obligation;
- settlement amount/currency/occurrence agree with the evidence/obligation;
- the exact payment fact binds purchase + evidence + obligation;
- payment-fact amount/currency/occurrence agree with the accepted evidence and purchase;
- no silent repair or alternate-row substitution is accepted.

Inspect the C4 corruption probes for:
- non-success accepted evidence substitution;
- evidence amount/currency/occurrence corruption;
- settlement ownership/economic corruption;
- payment-fact ownership/economic/occurrence corruption;
- purchase acceptance-time corruption.

Each relevant path must fail before capacity or Term/funding truth.

## 2. NEW-C3-002 — existing-claim aggregate integrity

Verify an existing R1 successor claim is accepted only if:

- `predecessor_reservation_id` is non-null;
- it exactly matches the authoritative Phase-Q reservation/hold;
- claim state is the correct active successor state;
- claim version is valid;
- source/pattern identity is coherent;
- locked intervals form a complete valid aggregate;
- declared interval count matches;
- required protected intervals exist;
- no released, expired, malformed, missing-interval or interval-corrupt claim is returned as idempotent handoff success.

Confirm the implementation uses the canonical claim validator rather than duplicated weaker logic.

Inspect corruption tests for null/foreign predecessor, released/expired state, invalid version, pattern/source corruption, declared-count mismatch, missing interval and corrupt interval aggregate.

## 3. NEW-C3-003 — replay after at-rest corruption

This is critical.

Inspect capacity handoff replay, claim-release replay and Term-binding replay.

A successful command winner may be replayed only after current authoritative stored state is revalidated with locks.

For capacity handoff replay require:
- full immutable commitment chain;
- exact claim ownership/predecessor;
- complete valid claim/interval aggregate;
- recorded result state remains valid.

For Term-binding replay require:
- full commitment chain;
- bound claim ownership and complete valid claim aggregate;
- exact Term identity/Enrolment relationship;
- exact funding-plan relationship to purchase/offer/Term/commitment.

For claim-release replay require:
- released result remains the exact authorised claim aggregate;
- corruption cannot be reported as successful replay.

Verify the post-rollback duplicate/replay path runs inside an appropriate transaction instead of loose autocommit reads.

Verify valid unchanged state still replays idempotently.

Inspect replay-after-corruption tests using the SAME command key after corrupting representative purchase/evidence/claim/funding-plan/result state.

## 4. Locking / transaction analysis

Independently inspect the final ordering.

Pay particular attention to the implementation-owner claim that:

- forward handoff remains account root → commitment rows → Phase-Q hold → Teacher root → claim → intervals;
- binding remains account root → commitment rows → claim → intervals → Enrolment → Phase L;
- replay follows compatible commitment → claim → intervals → Term ordering;
- no Teacher-root → account-root reverse edge was introduced;
- duplicate/replay recovery now revalidates inside a transaction;
- any plan-row locking does not create a hidden inversion.

If any reverse edge, stale-read gap or lockless replay authority remains, classify it.

## 5. Regression checks

Do not reopen prior findings without concrete regression evidence, but verify C4 did not regress:

- R1-BLOCK-001 adjustment source ↔ immutable snapshot;
- R1-BLOCK-002 exact protected interval succession;
- R1-BLOCK-003 historical non-blocking capacity;
- R1-BLOCK-004 canonical upstream offer/Course/Student/Teacher lineage;
- R1-MAJOR-005 provider-evidence exact convergence;
- R1-MAJOR-006 Teacher-root serialization;
- R1-MAJOR-008 required corruption/failure/concurrency coverage;
- R1-C1-NEW-001 initially-unattributed evidence race;
- R1-C1-NEW-002 documentation/executed matrix;
- C3 purchase/entitlement scalar ownership.

Confirm `CommercialLineageValidator` remains unchanged unless there is a documented reason.

## 6. Locked commercial model / out-of-scope

Confirm C4 does not alter the locked R1 model:
integer minor units, explicit currency, immutable whole-Term pricing, free intro separate, one 12-session Term, full payment or 6+6 instalments, settlement distinct from academic effectiveness, derived funded allowance, Phase L/M/N/Q ownership, explicit Q first regular slot, current-Term-only capacity, predecessor not released before durable successor, provider neutrality, legacy-Term compatibility.

Confirm no recurring enrolment, renewal execution, recovery automation, Stripe/webhook business authority, notifications, Theme/UI, gift cards, tax/accounting, refund academic consequences or deployment.

## 7. Validation

Run everything independently available.

At minimum where supported:

- identity/provenance;
- `git diff --check`;
- PHP lint;
- shell syntax;
- contract/static tests;
- R1 migration runtime;
- R1 authority runtime;
- R1 corruption runtime;
- R1 failure runtime;
- C4 replay/corruption matrix;
- R1 concurrency suite or focused relevant modes;
- adjacent regressions.

Classify each as PASS / FAIL / UNAVAILABLE.

Do not convert implementation-owner claims into independent PASS.

Known owner claims:
- 32/32 contract PASS;
- migration/authority/corruption/failure runtimes PASS;
- full 9-mode concurrency PASS;
- Phase L/M0/M/N/O PASS;
- Phase-P/Q known pre-existing failures only;
- fresh-clone critical rerun PASS.

## 8. New defect search

Actively look for bypasses not explicitly listed above, especially:
- alternate valid evidence/settlement/fact substitution;
- missing-row vs corrupt-row behavior;
- replay result contamination;
- lock-order inversion;
- stale claim/funding/Term aggregate;
- command replay returning success after current-state corruption;
- corruption disappearing from selector lookup instead of being positively validated.

Classify any new finding BLOCKER / MAJOR / MINOR.

## 9. Required final report

Return:

A. Identity/provenance

B. Findings for:
- NEW-C3-001
- NEW-C3-002
- NEW-C3-003
- R1-MAJOR-007
- R1-MAJOR-008
- all prior regression checks above

C. CommercialCommitmentValidator analysis

D. Claim aggregate / capacity handoff analysis

E. Term-binding analysis

F. Replay/idempotency analysis

G. Transaction/locking analysis

H. Corruption/test analysis

I. Validation executed, clearly separated into independently executed PASS / FAIL / UNAVAILABLE / owner claims not independently reproduced

J. Newly discovered defects

K. Final verdict using exactly one:

`PASS — MERGE PLANNING MAY PROCEED`

or

`FAIL — CORRECTION REQUIRED`

If PASS, approve exactly:
Candidate `6de25b8c32a21d060c27e0f98a8a05a4d1a7bfaa`
Tree `24cf30abbb689d8668fc90aee2793114a736685b`

Do not merge or deploy.

If FAIL, state exact correction requirements and do not implement them.
