<?php
namespace Delnavazan\Platform\Core\Application\PaymentExecution;

/**
 * [C10-2] The bounded decision window this worker was working inside has closed.
 *
 * Raised by the decision work-unit gate when the event's claim is no longer this generation's live,
 * unexpired claim: either its lease expired before the unit began — the owner stalled past the window it
 * was granted — or exactly one successor generation took the claim over. The window is deliberately not
 * renewable once it has closed, so a generation whose window is gone performs **no** further decision
 * work and **no** R1/R2 consequence work, and appends nothing.
 *
 * It is a controlled stop, never a failure of the delivery: the caller converges on whatever decision the
 * current owner publishes (or reports the event as durably still owing one, §9.5).
 */
final class DecisionClaimWindowClosed extends \RuntimeException {}
