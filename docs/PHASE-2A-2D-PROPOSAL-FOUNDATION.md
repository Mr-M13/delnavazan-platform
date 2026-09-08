# Phase 2A.2-D — Proposal foundation

This increment introduces a Proposal Family, Teacher-specific Proposal Option, and immutable issued Proposal Version. A Family groups alternatives and is never an acceptance target. Teacher A and Teacher B are separate Options; a revision of A is a new Version of A and never supersedes B.

Issuance consumes the current Teacher Availability Assent transactionally, then copies privacy-safe canonical arrangement facts and the supporting Assent reference into an immutable Version. Later Assent expiry or withdrawal cannot rewrite historical Version content, but cannot support a new issue.

The lock order is existing Assent context (Booking Request, Case, Candidate, Teacher, Course, eligibility) followed by Family, Option, Version, and issuance-key rows. Option locking serializes version numbers and makes supersession option-scoped; this is the concurrency boundary for simultaneous revisions. A digest-only idempotency key returns the original Family/Option/Version result for an authorised retry.

No acceptance, Student identity, Accepted Service Arrangement, Enrolment, Teacher Assignment, reservation, payment, calendar, Amelia, or notification authority is created. Privacy erasure invalidates still-issued Versions without copying Booking Request contact data.
