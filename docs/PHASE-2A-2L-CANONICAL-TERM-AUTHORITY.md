# Phase 2A.2-L — Canonical Term creation and lifecycle authority

Status: candidate for independent review. Schema 18, migration `018_canonical_term_authority`, build `phase2a2l-canonical-term-authority-20260916.1`.

Phase L adds an administrator-only `dzn_manage_canonical_terms` command boundary. Creation is subordinate to an applicable, structurally valid canonical Student + Course Enrolment. The server owns Term sequence, `authorised` origin, applicable slot, the fixed 12-standard/2-replacement allocation origin, canonical identifiers and digest-only evidence. A later Term may be explicitly created after valid `closed` or `cancelled` history when the caller names that exact latest terminal position.

Allowed transitions are `authorised -> current`, `authorised -> cancelled`, `current -> closed`, and `current -> cancelled`. There is no reopening, generic canonical archive/restore, automatic successor, or Enrolment mutation. Every command atomically changes the projection where applicable, appends one lifecycle event, and records an immutable HMAC-keyed command. Exact replay validates the complete result; conflicting or corrupted evidence fails closed.

Lock order is canonical Enrolment first, then its Terms by sequence/id, then lifecycle evidence. Database uniqueness remains final arbitration. No Teacher, Teacher Assignment, Lesson, payment, scheduling, allocation consumption, notification, calendar, Amelia, Hamnavaz, CRM, WhatsApp, portal or external-side-effect authority is introduced.
