# Phase 2A.2-I deterministic concurrency

The disposable runner uses two distinct WP-CLI/MySQL sessions, a real post-lock application hook, a second-worker start gate, and an explicit release file. Modes `a` and `b` prove same-root and same-source serialization; `u1` and `u2` prove unrelated identity roots can progress before release; `p1` and `p2` prove both privacy/conversion orderings through the Booking Request lock. Timing is not used as the success oracle: each run verifies worker outcomes and authoritative database state after both transactions finish.
