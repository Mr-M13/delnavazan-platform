<?php
use Delnavazan\Platform\Core\Application\Finance\{FinanceRefusalException,FinanceRule,FinanceSupport};

/**
 * Phase 2A.2-U §15.3 replay re-verification unit proof.
 *
 * Pure source-level behaviour of the shared replay arbitration: no WordPress, no database, no request.
 * It loads the four real classes the helpers live in and stubs only `wp_salt()` (the digest key), so it
 * runs anywhere PHP does — including the implementation environment, where the disposable
 * WordPress + MariaDB runtime the §18 authority suites need does not exist.
 *
 * It proves the two declared answers a replay may give and the one it may never give: a refusal
 * converges on its refusal, a recorded success converges only when its typed result row is present and
 * still names the command's own aggregate, and an absent, mismatched or non-reproducing result fails
 * closed with `command_replay_conflict` instead of returning a recorded result id.
 */
$root = dirname( __DIR__ );
if ( ! function_exists( 'wp_salt' ) ) { function wp_salt( string $scheme = '' ):string { return 'phase-2a2u-replay-unit-' . $scheme; } }
require $root . '/src/Core/Application/Finance/FinanceRefusalException.php';
require $root . '/src/Core/Application/Finance/FinanceIdempotency.php';
require $root . '/src/Core/Application/Finance/FinanceRule.php';
require $root . '/src/Core/Application/Finance/FinanceSupport.php';

$failures = 0;
function dzn_u_replay( bool $condition, string $message ):void {
	global $failures;
	if ( ! $condition ) { $failures++; fwrite( STDERR, 'Phase 2A.2-U replay unit: FAIL — ' . $message . "\n" ); }
}
function dzn_u_replay_refusal( callable $call, string $expected, string $message ):void {
	$caught = null;
	try { $call(); } catch ( Throwable $e ) { $caught = $e; }
	dzn_u_replay(
		$caught instanceof FinanceRefusalException && $caught->getMessage() === $expected,
		$message . ' (got ' . ( $caught === null ? 'no refusal' : get_class( $caught ) . ': ' . $caught->getMessage() ) . ')'
	);
}

// §15.3: the declared non-refusal outcome states of one operation. Only a reconciliation `run` carries
// the declared `failed` state beside its success state; every other operation has exactly one.
dzn_u_replay( FinanceRule::commandOutcomeStates( 'run' ) === array( 'completed', 'failed' ), 'a run declares `completed` and `failed`' );
dzn_u_replay( FinanceRule::commandOutcomeStates( 'capture' ) === array( 'recorded' ), 'a capture declares only `recorded`' );
dzn_u_replay( FinanceRule::commandOutcomeStates( 'close' ) === array( 'closed' ), 'a rate closure declares only `closed`' );
dzn_u_replay( FinanceRule::commandOutcomeStates( 'issue' ) === array( 'issued' ), 'a statement issuance declares only `issued`' );

// A refusal still converges on its refusal — the original §15.8 behaviour the re-verification keeps.
dzn_u_replay_refusal(
	static fn() => FinanceSupport::assertReplayState( (object) array( 'result_state' => 'refused', 'reason_code' => 'snapshot_derivation_mismatch' ), 'capture' ),
	'snapshot_derivation_mismatch',
	'a replayed refusal converges on its own reason code'
);
// A recorded state that is not the operation's declared outcome can never replay as that operation.
dzn_u_replay_refusal(
	static fn() => FinanceSupport::assertReplayState( (object) array( 'result_state' => 'failed', 'reason_code' => null ), 'capture' ),
	'command_replay_conflict',
	'a `failed` run row can never replay as a capture'
);
dzn_u_replay_refusal(
	static fn() => FinanceSupport::assertReplayState( (object) array(), 'record' ),
	'command_replay_conflict',
	'a command row with no declared state can never replay'
);
FinanceSupport::assertReplayState( (object) array( 'result_state' => 'completed' ), 'run' );
FinanceSupport::assertReplayState( (object) array( 'result_state' => 'failed' ), 'run' );
dzn_u_replay( true, 'a run replays `completed` or `failed`' );

// The typed result row must be re-loaded and must still name the command's own aggregate.
$row = (object) array( 'id' => 7, 'lesson_id' => 3, 'teacher_id' => 5 );
dzn_u_replay_refusal( static fn() => FinanceSupport::replayResultRow( 0, static fn( int $id ) => $row, array(), 'aggregate' ), 'command_replay_conflict', 'an absent typed result id fails closed' );
dzn_u_replay_refusal( static fn() => FinanceSupport::replayResultRow( 7, static fn( int $id ) => null, array(), 'aggregate' ), 'command_replay_conflict', 'a deleted typed result row fails closed' );
dzn_u_replay_refusal( static fn() => FinanceSupport::replayResultRow( 7, static fn( int $id ) => $row, array( 'lesson_id' => 4 ), 'aggregate' ), 'command_replay_conflict', 'a typed result naming another Lesson fails closed' );
dzn_u_replay_refusal( static fn() => FinanceSupport::replayResultRow( 7, static fn( int $id ) => $row, array( 'teacher_id' => null ), 'aggregate' ), 'command_replay_conflict', 'a typed result that is not the command\'s own scope fails closed' );
$found = FinanceSupport::replayResultRow( 7, static fn( int $id ) => $row, array( 'lesson_id' => 3, 'teacher_id' => 5 ), 'aggregate' );
dzn_u_replay( $found === $row, 'a matching typed result row is returned to the replay' );

// The recorded result must still reproduce the command's own payload.
$facts = array( 'key' => 'INTRO_PAYABILITY_POLICY', 'value' => 'non_payable', 'value_type' => 'policy_reference', 'effective_from' => '2026-09-25 00:00:00' );
$digest = FinanceSupport::payload( $facts );
FinanceSupport::assertReplayPayload( $digest, $facts, 'aggregate' );
dzn_u_replay( true, 'the recorded payload reproduces itself' );
dzn_u_replay_refusal(
	static fn() => FinanceSupport::assertReplayPayload( $digest, array( 'key' => 'INTRO_PAYABILITY_POLICY', 'value' => 'payable', 'value_type' => 'policy_reference', 'effective_from' => '2026-09-25 00:00:00' ), 'aggregate' ),
	'command_replay_conflict',
	'a recorded result whose fact has moved fails closed'
);

if ( $failures > 0 ) { fwrite( STDERR, 'phase-2a2u-replay-unit: FAIL (' . $failures . ")\n" ); exit( 1 ); }
echo "phase-2a2u-replay-unit: OK (shared §15.3 replay re-verification: refusal convergence, absent/mismatched fail-closed, exact convergence)\n";
