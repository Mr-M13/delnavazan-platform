# DELNAVAZAN PLATFORM — PHASE 2A.2-R1 — CORRECTION ROUND 4

## Routing

- Agent: DeepSeek / Codex Desktop
- Role: Platform implementation owner
- Repository: `Mr-M13/delnavazan-platform`
- Branch: `phase-2a2r1-commercial-purchase-funding-authority`
- Parallel: NO
- Starting candidate: `2ff3d6e3a81ede8ebbf44f3144f1afc801b93531`
- Starting tree: `ab0a568a3ed73bf0471ce76c2a572b2b04812d01`
- Authoritative main: `1b9d7aaef0ca21fdb861ccc9da1d15634cb5d843`
- Status: C3 independent re-review FAIL; Correction Round 4 required.

All C4 work must be additive descendants of the exact C3 candidate. No amend, rebase, squash, reset, history rewrite or force push. Do not merge or deploy.

## First: verify identity

Before mutation, fetch origin and verify exact repo/remote/branch, HEAD=`2ff3d6e3a81ede8ebbf44f3144f1afc801b93531`, tree=`ab0a568a3ed73bf0471ce76c2a572b2b04812d01`, clean worktree, ancestry still contains `3aaf3081...`, `186fc501...`, `10fe4061...`, and `origin/main` remains `1b9d7aaef0ca21fdb861ccc9da1d15634cb5d843`. Stop on mismatch.

## Independent C3 review result

C3 correctly introduced `CommercialCommitmentValidator` and closed purchase/entitlement scalar ownership, but independent review returned `FAIL — CORRECTION REQUIRED` on three MAJOR findings. Fix exactly these without reopening independently passed R1 findings unless a concrete regression is discovered.

### NEW-C3-001 — Acceptance-evidence / settlement / payment-fact ownership

`purchase.first_evidence_id` must be proven to be the exact successful evidence that minted the accepted purchase, not merely an intrinsically valid accepted row for the same offer/obligation.

Extend the canonical commitment validation so, before capacity or Term truth, it proves at minimum:

1. `first_evidence_id` exists and identifies an intrinsically valid evidence row.
2. `evidence_kind === 'success'`.
3. `processing_state === 'accepted'`.
4. Evidence belongs to the exact offer and exact obligation used by the purchase.
5. Evidence amount equals authoritative obligation amount.
6. Evidence currency equals authoritative obligation currency and purchase/offer currency.
7. Provider occurrence time is valid and exactly agrees with the purchase acceptance instant according to the existing acceptance semantics.
8. The exact settlement exists for this evidence + obligation and belongs to this purchase/offer context.
9. Settlement amount/currency/occurrence agree with evidence and obligation.
10. The exact payment fact exists for the purchase/evidence/obligation relationship and its amount/currency/occurrence agree with the accepted evidence and purchase.
11. Any immutable IDs/digests already persisted by the R1 schema that establish this chain are checked.

Do not invent redundant storage if the existing schema already represents these facts. Reuse canonical repository/validator semantics. No silent repair.

Add corruption tests including at minimum:

- repoint `first_evidence_id` to another accepted non-success evidence row for the same otherwise-valid offer/obligation;
- evidence amount mismatch;
- evidence currency mismatch;
- evidence occurrence / purchase `accepted_at` mismatch;
- settlement evidence/obligation/purchase ownership mismatch;
- settlement economic mismatch;
- payment-fact purchase/evidence/obligation ownership mismatch;
- payment-fact economic/occurrence mismatch.

Each applicable corruption must be rejected independently at capacity handoff and Term binding before downstream truth, with durable zero-mutation assertions and no silent repair. Restore and prove normal convergence where practical.

### NEW-C3-002 — Existing-claim idempotent handoff integrity

The existing-claim path currently proves scalar ownership but can accept an invalid successor claim.

Before reporting idempotent existing-claim success, require:

1. `predecessor_reservation_id` is NON-NULL for R1 Q→R1 successor claims.
2. It exactly equals the offer / Phase-Q reservation authority.
3. Claim state is the appropriate active state for an existing successor claim.
4. Claim version and immutable identity/source/pattern facts are valid.
5. Load the locked claim intervals and validate the complete claim/interval aggregate using the canonical capacity/claim validator (`CommercialValidator::claimValid()` or the repository's single authoritative equivalent).
6. Required protected intervals exist and match the claim; missing/extra/corrupt intervals fail closed.
7. A released, expired, satisfied, malformed or interval-corrupt claim must NOT be returned as idempotent handoff success.

Add corruption tests for at minimum:

- predecessor reservation set to NULL;
- predecessor reservation mismatch;
- claim state released/expired/non-active;
- invalid claim version/source/pattern where represented;
- missing protected interval;
- corrupted interval aggregate.

Assert no false successful handoff, no Q release caused by the corrupted replay/convergence path, and no silent repair.

### NEW-C3-003 — Command replay after at-rest corruption

Capacity handoff and Term binding currently check command replay before full commitment validation. Their replay validators do not re-prove the complete commitment/result aggregate, allowing an old command winner to report idempotent success after at-rest corruption.

Correct replay semantics so an existing successful command may be returned only after the authoritative current stored aggregate is revalidated.

For capacity-handoff replay, re-prove at minimum:

- full immutable commitment chain (`entitlement → purchase → accepted evidence/settlement/payment fact → offer → upstream lineage`);
- resulting claim belongs to that exact commitment;
- exact mandatory predecessor reservation;
- complete current claim/interval aggregate;
- result state is appropriate for replay.

For Term-binding replay, re-prove at minimum:

- full immutable commitment chain;
- required claim ownership and complete valid claim aggregate;
- exact Term/result aggregate and funding-plan relationship already expected by the operation;
- no corrupted relationship can be reported as successful replay.

Preserve idempotency: valid unchanged state must still replay successfully. Corrupted at-rest state must fail closed, not create another result and not rewrite corruption.

Add replay-after-corruption tests for both operations. At minimum corrupt representative purchase/evidence/claim/result relationships after a successful command, replay the same command key, and prove explicit failure plus zero new downstream mutation. Restore and prove normal replay succeeds again where practical.

## Preserve all earlier independent passes

Do not regress: R1-BLOCK-001 adjustment snapshot integrity; BLOCK-002 exact protected interval succession; BLOCK-003 historical non-blocking capacity; BLOCK-004 upstream offer/Course/Student/Teacher lineage; MAJOR-005 evidence exact convergence; MAJOR-006 Teacher-root serialization; C1-NEW-001 unattributed evidence race; C1-NEW-002 documentation matrix. Preserve the locked 12-session / 6+6 funding model, settlement-vs-effectiveness distinction, Phase L/M/N/Q ownership, explicit Q first regular slot, current-Term-only capacity, provider neutrality and legacy-Term behavior.

## Locking / transaction requirements

Maintain the established order. Do not create a Teacher-root → commercial-account reverse edge. Replay revalidation must be transaction-aware and must not bypass the canonical account/commitment/Teacher serialization model. If replay needs additional locked rows, place them consistently with current global order and explain the final order in the report.

## Out of scope

No recurring enrolment, renewal execution, payment recovery automation, Stripe SDK/webhook authority, notifications, Theme/UI, gift cards, invoice/tax/accounting, refund academic consequences or deployment.

## Validation

Run: identity/provenance; PHP lint; shell syntax; `git diff --check`; R1 contract/static; migration runtime; authority runtime; corruption runtime including all C4 probes; failure runtime; full R1 concurrency matrix; focused replay-after-corruption tests; adjacent Phase L/M0/M/N/O regressions; previous C2/C3 corruption/failure/concurrency coverage. Classify known Phase-P/Q harness failures accurately and never hide candidate-caused failures.

After implementation: self-review the complete diff, commit additively, normal-push only, create a genuinely fresh clone, verify exact new SHA/tree/ancestry/main, and rerun critical C4 suites from that clone.

## Documentation

Update R1 authority docs, changelog and candidate continuity on the R1 branch to record the C3 FAIL, NEW-C3-001/002/003, C4 changes and actually executed validation. Do not claim PASS/merge/deployment.

## Required return

Return one complete report with: starting identity; exact fixes for NEW-C3-001/002/003; files changed; canonical evidence/settlement/fact invariants; claim aggregate/replay invariants; transaction/lock review; tests added; exact validation commands/results; known pre-existing failures; regression status; additive commit history; normal-push confirmation; fresh-clone evidence; new immutable candidate SHA/tree.

Final status must be exactly:

`PHASE 2A.2-R1 CORRECTION ROUND 4 IMPLEMENTED`
`CANDIDATE AWAITING INDEPENDENT RE-REVIEW`
`DO NOT MERGE`
`DO NOT DEPLOY`
