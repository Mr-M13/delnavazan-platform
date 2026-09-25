# Delnavazan Platform — Product Decisions

## Purpose
Record decisions that constrain product behaviour. Implementation history does not belong here.

## Locked decisions
- Platform is the canonical operational/business authority; Theme is presentation-only.
- Teacher/student identities are stable canonical entities; WordPress accounts are access adapters, not the identity source of truth.
- Scheduling stores timezone-aware/frozen facts needed to reproduce decisions.
- Term/Lesson/enrolment transitions require explicit authority; they are not inferred from UI state.
- Introduction outcomes do not automatically create ongoing regular capacity without explicit continuation/slot authority.
- Commercial money is integer minor units plus currency.
- Offers/purchases/funding/renewal decisions preserve the policy/evidence snapshot used at the time.
- External providers execute authorised intents; they do not decide canonical payment, renewal, attendance or scheduling state.
- Provider-neutral domain records are preferred so Stripe/Google/Meet/etc. can be replaced without changing core authority.
- Privacy, idempotency and audit evidence are first-class requirements.
- Production deployment is a separate explicit decision/gate.

## Accepted Payment implementation boundary
- Phase T implements the existing provider-neutral payment, privacy, credential-isolation and fail-closed decisions; it introduces no new product or business-rule decision.
- Live provider provisioning/execution remains disabled. Selecting credentials, enabling a provider or production rollout requires explicit Hamed authorisation.
- Outstanding host runtime evidence is an acceptance requirement, not a product-policy choice.

## Decisions still requiring Hamed when encountered
Any new choice that changes:
- user-visible workflow/business rules;
- automatic vs manual behaviour;
- pricing/refund/renewal policy;
- notification timing/content policy;
- portal UX authority or editable fields;
- provider/cost/security trade-offs;
- production rollout/cutover behaviour.

Such a decision must be recorded here before implementation is treated as approved.

## Decision format
Each new material decision should state:
- decision;
- reason/business intent;
- affected authority/domain;
- date;
- whether reversible;
- superseded decision, if any.

## Staleness trigger
Update immediately after every material product/business-rule decision and before implementation of that decision begins. Maximum review interval: 30 days during active product development.
