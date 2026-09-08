# Phase 2A.2-C — Teacher Availability Assent

Phase 2A.2-C adds the smallest authoritative record that a named Teacher is
willing and available in principle for an exact, frozen pre-issue arrangement.
It is deliberately before Proposal Family, Option, and Version implementation.

## Authority and exclusions

An assent is time-bounded evidence of willingness only. It is not Student
identity, guardian authority, capacity reservation, Teacher Assignment, a
booking, a proposal, acceptance, Accepted Service Arrangement, Enrolment,
Term, Lesson, payment, compensation, notification, calendar action, or Amelia
authority. No public REST route exists for assents.

## Frozen pre-issue arrangement snapshot

`teacher_availability_assent_snapshots` is append-only. It binds one candidate
Teacher context to an opaque prospective-subject reference, either an active
Course or an exact unresolved Course specification, delivery mode, location
scope, weekly frequency, expected duration, structured schedule constraints,
commencement window, timezone, and controlled conditions. It stores a SHA-256
arrangement fingerprint over those facts. A future issued version must prove
its frozen facts match this fingerprint; this increment creates no proposal
tables or proposal authority.

The snapshot uses controlled codes and opaque references. It does not store
names, contact details, addresses, full message bodies, raw client signals,
calendar data, or Amelia data. Booking Request contact facts remain neither
identity nor authority.

## Attribution and validity

`teacher_availability_assents` records either:

- an authenticated Teacher principal linked to the exact active Teacher; or
- an authorised administrator attesting attributable evidence, with controlled
  attribution basis, evidence channel/reference/time, and optional uncertainty
  code.

External communications are evidence only, never the transition itself. Each
record has explicit `valid_until` and/or `review_by`; currentness is derived at
read time and requires that neither present threshold has passed. Validity is
stored per record and is not a hard-coded duration. Currentness also requires
the source request/case/candidate and Teacher status/readiness/accepting state
to remain usable, and Course eligibility when the snapshot has a Course.

## Lifecycle, concurrency, and audit

The minimal lifecycle is `recorded`, `withdrawn`, `invalidated`, or
`superseded`. Historical records and immutable snapshots are never edited.
Renewal/replacement creates a new record and explicitly supersedes the prior
record with optimistic version checks. A duplicate current assent for the same
snapshot is rejected instead of overwritten.

Consequential operations lock in the established order: Booking Request,
Coordination Case, Candidate Teacher, Teacher, snapshot, then assent. Candidate
and assent expected versions protect against stale administrators. Every
recording, supersession, withdrawal, invalidation, and privacy-erasure
invalidation writes a privacy-minimised audit event containing controlled
identifiers, state, version, and evidence reference only.

## Privacy erasure

Booking Request privacy erasure runs in the existing source transaction. It
first closes the advisory Case/Candidates, then invalidates every live assent
dependent on that request before PII is removed. The remaining snapshot and
audit facts are opaque/minimised historical provenance and can no longer be
used as live assent authority.

## Internal surface

Authorised coordination staff can record administrator-attested assents and
inspect validity/review timestamps from the protected coordination screen.
They can withdraw, invalidate, or record a replacement using the prior assent
ID and version. The authenticated-Teacher service path uses the existing
Teacher principal-link authority and is intentionally not exposed as a new
public endpoint in this increment.
