# Core-dataset technical prerequisites

Schema 33 adds bounded operational evidence storage only. It creates no Enrolment, Teacher Assignment, Term or Lesson rows, performs no backfill, selects no canonical-versus-legacy policy, calls no provider, and does not deploy or cut over.

The existing authorities remain the writers:

- Enrolment conversion: `EnrolmentConversionService::convert()`.
- Initial Teacher Assignment: `TeacherAssignmentService::assignInitial()`.
- Canonical Lesson issuance: `CanonicalLessonAuthorityService::createStandard()` / `createReplacement()`.

The readiness service provides a read-only, deterministic expected-vs-actual projection for each bounded scope (`enrolments`, `teacher_assignments`, `canonical_lessons`). The digest is SHA-256 over the ordered projection; a mismatch is reported and never repaired. Operator evidence records the actor, operation, target, reason and evidence-reference digest. Corrections are append-only records containing prior and corrected digests; they do not mutate a domain aggregate.

Durable actor/provenance is recorded only for future evidence. Existing rows remain untouched, including nullable historical actor fields. No value is inferred for a missing actor or source.

## Rehearsal

1. Use a disposable Schema-31/32 database snapshot and record its tree/SHA.
2. Apply the additive readiness slice and verify only the five `core_dataset_*` tables were added; assert all pre-existing table schemas and rows are byte-for-byte unchanged.
3. Run reconciliation in read-only mode for all three scopes with operator-supplied expected count/digest. Preserve both results, including mismatches.
4. In a disposable transaction, exercise each existing authority with a controlled fixture or stop at its validation boundary; do not commit a real domain row. Separately exercise the evidence recorder with non-production test identities.
5. Reconcile again, confirm no unexpected Core rows, and verify evidence rows carry the acting operator and digests.
6. Roll back by disabling the readiness menu/entrypoint and restoring the pre-rehearsal database snapshot. Do not delete evidence or domain rows in production; production correction is forward-only through the owning authority and an append-only correction record.

Acceptance is the preserved pre-existing tree, a recorded reconciliation digest, and a rehearsed rollback—not a cutover decision.
