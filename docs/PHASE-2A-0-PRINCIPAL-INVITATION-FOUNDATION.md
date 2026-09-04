# Phase 2A.0 — Principal and invitation foundation

This slice establishes Core-owned Teacher and Student principal-link foundations.
It deliberately does not create booking requests, routing, availability,
Teacher response, Intro conversion, timers, payments, portals, or providers.

## Authority boundary

WordPress accounts authenticate people; they do not confer Delnavazan domain
authority. A Teacher-domain action must require both a minimal WordPress role
or capability and an active Teacher principal-link row plus active Teacher
onboarding state. Teacher and Student principal links are separate domains, so
one legitimate WordPress account can later hold one of each without leaking
Teacher authority into Student access.

## Invitation and secret handling

An administrator can issue, reissue, or revoke an invitation for an active,
unarchived Core Teacher. Reissue supersedes an unclaimed active generation.
Each generation is recipient-bound, single-use, revocable, and expiry-bound.

The raw secret is generated in memory and returned only to the calling service
for a future delivery adapter. Only a keyed digest is stored. Audit and outbox
tables contain invitation/generation references and idempotency keys only;
there is no provider integration in this slice. The minimal admin surface
creates delivery intent but never renders a secret or places one in a URL.

## Claim and WordPress provisioning recovery

Existing-account claim requires the authenticated WordPress user to own the
recipient email; email equality alone is never authority. A new WordPress user
is provisioned outside the Core SQL transaction and is recorded by an
untrusted-to-domain, random attempt marker. A crash or uncertain external
outcome transitions the attempt to recovery_required; it must be reconciled,
not retried by blindly creating another account or deleting one.

Core finalisation is a transaction: it locks the invitation/generation and
Teacher, rechecks current eligibility and generation, consumes the generation,
writes the unique Teacher link, updates onboarding state, and writes audit
facts. Reissue/revoke makes a stale attempt non-finalisable. Repeating the
same command returns its existing durable attempt; competing claims are
rejected by the unique generation owner and unique Teacher/User link slots.

## Offboarding

Offboarding revokes active Teacher invitations and Teacher principal authority
without deleting the Core Teacher, lessons, history, WordPress account, or a
separate Student-domain link. Future high-trust relink policy remains outside
this slice.

## Required isolated runtime validation

Before production use, run fresh-install/rerun migration verification and the
invitation, claim, collision, recovery, capability/nonce, and raw-secret
non-persistence cases in isolated WordPress + MySQL. No beta Teacher/Student
account or provider delivery should be used for this foundation.
