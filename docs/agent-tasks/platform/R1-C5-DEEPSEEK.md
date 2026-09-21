# DELNAVAZAN PLATFORM — PHASE 2A.2-R1 — CORRECTION ROUND 5

Agent: DeepSeek / Codex Desktop
Repository: Mr-M13/delnavazan-platform
Branch: phase-2a2r1-commercial-purchase-funding-authority
Starting candidate: 6de25b8c32a21d060c27e0f98a8a05a4d1a7bfaa
Starting tree: 24cf30abbb689d8668fc90aee2793114a736685b
Authoritative main: 1b9d7aaef0ca21fdb861ccc9da1d15634cb5d843

Status: C4 independent review FAIL. Correction Round 5 required.
All work must be additive descendants of the exact C4 candidate. No amend, rebase, squash, reset, force-push, merge, or deploy.

## Verify identity first
Fetch origin and verify exact repo/remote/branch, HEAD and tree above, clean worktree, ancestry includes 2ff3d6e3, 3aaf3081, 186fc501, 10fe4061, and origin/main is unchanged. Stop on mismatch.

## C5-MAJOR-001 — release replay lifecycle/result integrity
The independent reviewer found that release replay can accept a current active claim for a recorded released result.
Required correction:
- release replay must require the current claim state to be exactly released;
- current intervals must reflect the authorised released lifecycle;
- validate any persisted release metadata/result fields against the recorded release;
- a coherent active aggregate must never satisfy replay of a recorded release;
- no silent repair.
Required test: perform a successful release; mutate the released claim and relevant intervals back into an otherwise valid active aggregate; replay the SAME command key; require fail-closed behavior, zero duplicate result/mutation, no repair; restore exactly and prove valid idempotent replay succeeds.

## C5-MAJOR-002 — settlement occurrence/currency and payment-fact currency integrity
C4 does not bind settlement.settled_at to the authoritative acceptance/evidence/payment timeline.
Required correction:
- enforce the existing R1 settlement occurrence invariant using existing stored facts and semantics; do not invent new business policy or redundant persistence;
- positively validate settlement currency against authoritative obligation/evidence/purchase/offer currency where not already guaranteed;
- positively validate payment-fact currency against the accepted evidence/purchase/obligation chain;
- preserve settlement-vs-academic-effectiveness distinction;
- no alternate-row substitution or silent repair.
Required corruption probes: settlement settled_at, settlement currency, payment-fact currency. Each must fail before capacity or Term/funding truth, with no repair; restore and prove convergence.

## C5-MAJOR-003 — full Term replay command-result validation
Term replay revalidates much of the aggregate but not the stored command result completely.
Required correction:
- require command result_state exactly term_bound;
- require result_id to identify the exact revalidated result aggregate expected by this operation;
- require recorded term_id, entitlement_id, purchase_id, and offer_id fields, where present, to agree exactly with the revalidated Term/funding/commitment;
- positively validate any other persisted command result/ownership fields;
- contaminated command rows must fail closed before idempotent success;
- no duplicate result and no silent repair.
Required same-key probes: result_state, term_id, entitlement_id, purchase_id, offer_id, and result_id contamination where independently mutable. Each must fail closed, preserve corruption, restore exactly, then replay successfully.

## Preserve prior independent passes
Do not regress NEW-C3-002, R1-MAJOR-007, R1-BLOCK-001/002/003/004, R1-MAJOR-005/006, R1-C1-NEW-001/002, C3 scalar ownership, and the C4 areas that independently passed. CommercialLineageValidator should remain unchanged unless a concrete regression requires otherwise.

## Locking / transactions
Preserve global order and avoid any Teacher-root -> account-root reverse edge. Replay validation must use appropriate locks/transactions and must not create a new plan/Term/claim lock inversion. Explain final ordering.

## Validation
Run identity/provenance, PHP lint, shell syntax, git diff --check, R1 contract/static, migration runtime, authority runtime, corruption runtime including all C5 probes, failure runtime, full R1 concurrency matrix, focused release-replay and Term-command contamination tests, adjacent Phase L/M0/M/N/O regressions, and previous C2/C3/C4 coverage. Classify known Phase-P/Q harness failures accurately.

After implementation: self-review complete diff, commit additively, normal-push only, fresh clone, verify exact new SHA/tree/ancestry/main, and rerun critical C5 suites from the fresh clone.

Update the R1 authority doc, changelog, and candidate continuity on the R1 branch to record the C4 independent FAIL, C5 corrections, and actual validation. Do not claim independent PASS, merge, or deployment.

Required final status exactly:
PHASE 2A.2-R1 CORRECTION ROUND 5 IMPLEMENTED
CANDIDATE AWAITING INDEPENDENT RE-REVIEW
DO NOT MERGE
DO NOT DEPLOY