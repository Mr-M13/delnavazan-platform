<?php
use Delnavazan\Platform\Portals\{CanonicalAttendancePortalReadPort,CanonicalLessonDeliveryPortalReadPort,CanonicalLessonSchedulePortalReadPort,PortalCapabilityOwnerPort,PortalOwnerPorts,PortalPublicActionRefusalRecorded,PublicCapabilityReadSubject,TeacherAssignmentPortalReadPort};
use Delnavazan\Platform\Portals\PortalPublicActionController;
use Delnavazan\Platform\Portals\PortalPublicActionService;
use Delnavazan\Platform\Portals\PortalRule;

/**
 * Phase-W §15.6 refusal-versus-failure split — behavioural coverage.
 *
 * Pure source-level behaviour: no WordPress, no database, no request. WordPress is stubbed only where the
 * public controller, the refusal transaction and the capability service touch it, so this file runs anywhere
 * PHP does — including the implementation environment, where the disposable WordPress + MariaDB runtime the
 * §18 authority suites need does not exist. `tests/phase-2a2w-contract.php` embeds it, so the contract guard
 * runs these probes and the static seams together.
 *
 * It proves the declared split of §15.6:
 *  1. a declared business refusal still commits exactly its refusal evidence — the refused action row and
 *     its denial row, appended in the §15.2 order inside the one transaction, then committed once;
 *  2. a persistence failure of *either* evidence insert, or of the commit itself, rolls both rows back and
 *     re-raises `portal_action_evidence_persistence_failed`: no refused action row, no denial row and no
 *     audit row survive, so the durable evidence of a refused command never exists without its command;
 *  3. a capability/action evidence write that fails inside the invoked service propagates unchanged out of
 *     both registered public callbacks (`join` and `absence`) and appends no *subsequent* refusal record —
 *     the controller opens no second transaction, writes no refusal row and writes no denial of its own;
 *  4. the post-delegation owner handoff carries the same split: a declared owner refusal is recorded as the
 *     refused outcome with the owner's own reason and its denial row in that one transaction, while an owner
 *     persistence/infrastructure failure propagates unchanged and records no refused outcome and no denial,
 *     leaving only the claim and lease the §15.8 replay converges;
 *  5. the classification itself: only a declared business refusal (`PortalRule::refusalReason()`) is a
 *     refusal; the declared persistence codes, an unexpected error and an unrelated throwable are not.
 *
 * The administrative surface (`PortalCapabilityController::command()`) ends in `exit()` and cannot be driven
 * in-process, so its identical split is proved by the source seam asserted at the end of this file and by
 * `tests/phase-2a2w-blocking-findings-contract.sh`.
 */
if ( class_exists( '\WP_REST_Response' ) ) {
	if ( ! defined( 'DZN_2A2W_PERSISTENCE_UNIT_EMBEDDED' ) ) { echo "phase-2a2w-persistence-failure-unit: skipped (a WordPress runtime is loaded; this proof needs its own stubs)\n"; }
	return;
}

$root = dirname( __DIR__ );

if ( ! function_exists( 'wp_salt' ) ) {
	function wp_salt( string $scheme = 'auth' ):string { return 'phase-2a2w-' . $scheme . '-0123456789abcdef'; }
}
if ( ! function_exists( 'wp_generate_uuid4' ) ) { function wp_generate_uuid4():string { return '11111111-2222-4333-8444-555555555555'; } }
if ( ! function_exists( 'wp_json_encode' ) ) { function wp_json_encode( $value ) { return json_encode( $value ); } }
if ( ! function_exists( '__return_true' ) ) { function __return_true():bool { return true; } }
if ( ! function_exists( 'get_current_user_id' ) ) { function get_current_user_id():int { return 0; } }
if ( ! function_exists( 'get_option' ) ) {
	function get_option( string $name, $default = false ) {
		global $dzn_2a2w_options;
		return array_key_exists( $name, $dzn_2a2w_options ) ? $dzn_2a2w_options[ $name ] : $default;
	}
}
if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( string $hook, $value, ...$args ) { return $value; }
}
$dzn_2a2w_options = array();

/**
 * The refusal transaction's storage. `query()` and `insert()` are recorded, rows are buffered and only
 * published on `COMMIT`, so a `ROLLBACK` discards exactly the rows the failed transaction appended — the
 * statement stream is the evidence. It fails on request, so the two §15.6 failure classes can be driven
 * without a database.
 */
final class DznPhase2A2WPersistenceStore {
	public string $prefix = 'wp_';
	public int $insert_id = 0;
	public array $statements = array();
	public array $pending_events = array();
	public array $pending_denials = array();
	/** The redemption claim's supporting capability event/command rows (not refusal evidence). */
	public array $pending_support = array();
	public array $committed_events = array();
	public array $committed_denials = array();
	/** A `get_row()` query containing `handle_digest` resolves to this row when set. */
	public $hint = null;
	/** The Lesson root row and the capability row a rooted write re-reads, when set. */
	public $root_row = null;
	public $capability_row = null;
	/** '' | 'action' | 'denial' — which evidence insert fails. */
	public string $fail_insert = '';
	/** A statement containing this substring returns false. */
	public string $fail_statement = '';
	public function prepare( string $query, ...$args ):string { return $query; }
	public function get_row( string $query ) {
		if ( null !== $this->hint && false !== strpos( $query, 'SELECT id,lesson_id' ) ) { return $this->hint; }
		if ( null !== $this->root_row && false !== strpos( $query, 'portal_lesson_capability_roots' ) ) { return $this->root_row; }
		if ( null !== $this->capability_row && false !== strpos( $query, 'FROM ' . $this->prefix . 'dzn_portal_public_capabilities' ) ) { return $this->capability_row; }
		return null;
	}
	public function get_var( string $query ) { return 1; }
	public function query( string $query ) {
		$this->statements[] = $query;
		if ( '' !== $this->fail_statement && false !== strpos( $query, $this->fail_statement ) ) { return false; }
		if ( 'ROLLBACK' === $query ) { $this->pending_events = array(); $this->pending_denials = array(); $this->pending_support = array(); }
		if ( 'COMMIT' === $query ) {
			$this->committed_events = array_merge( $this->committed_events, $this->pending_events );
			$this->committed_denials = array_merge( $this->committed_denials, $this->pending_denials );
			$this->pending_events = array(); $this->pending_denials = array(); $this->pending_support = array();
		}
		// The real `$wpdb->query()` returns the affected-row count for a data-change statement, and the
		// consume claim requires exactly one updated row.
		if ( str_starts_with( $query, 'UPDATE ' ) ) { return 1; }
		return true;
	}
	public function insert( string $table, array $row ) {
		$this->statements[] = 'insert:' . $table;
		if ( $table === $this->prefix . 'dzn_portal_public_action_events' ) {
			if ( 'action' === $this->fail_insert ) { return false; }
			$this->pending_events[] = $row;
			return 1;
		}
		if ( $table === $this->prefix . 'dzn_portal_access_denials' ) {
			if ( 'denial' === $this->fail_insert ) { return false; }
			$this->pending_denials[] = $row;
			return 1;
		}
		if ( $table === $this->prefix . 'dzn_portal_public_capability_events' || $table === $this->prefix . 'dzn_portal_public_capability_commands' ) {
			$this->pending_support[] = $table;
			return 1;
		}
		throw new RuntimeException( 'unexpected portal write: ' . $table );
	}
}
$wpdb = new DznPhase2A2WPersistenceStore();

if ( ! class_exists( 'WP_REST_Request' ) ) {
	class WP_REST_Request {
		private array $params = array();
		private string $method;
		public function __construct( string $method = 'GET', string $route = '' ) { $this->method = $method; }
		public function set_param( string $key, $value ):void { $this->params[ $key ] = $value; }
		public function get_param( string $key ) { return $this->params[ $key ] ?? null; }
		public function get_method():string { return $this->method; }
	}
}
if ( ! class_exists( 'WP_REST_Response' ) ) {
	class WP_REST_Response {
		private $data;
		private int $status;
		private array $headers = array();
		public function __construct( $data = null, int $status = 200, array $headers = array() ) { $this->data = $data; $this->status = $status; foreach ( $headers as $name => $value ) { $this->headers[ $name ] = $value; } }
		public function get_data() { return $this->data; }
		public function get_status():int { return $this->status; }
		public function header( string $name, $value ):void { $this->headers[ $name ] = $value; }
		public function get_headers():array { return $this->headers; }
	}
}

require_once $root . '/src/Portals/PortalRule.php';
require_once $root . '/src/Portals/PortalCapabilityService.php';
require_once $root . '/src/Portals/PortalPublicActionService.php';
require_once $root . '/src/Portals/PortalPublicActionRefusalRecorded.php';
require_once $root . '/src/Portals/PortalPublicActionController.php';
require_once $root . '/src/Portals/PortalOwnerPorts.php';

/** The capability owner the absence handoff re-proves through. */
final class DznPhase2A2WPersistenceOwner implements PortalCapabilityOwnerPort {
	public function binding( int $lessonId, int $scheduleVersionId, string $purpose, ?int $studentId, bool $requirePrincipal = true ):array {
		return array( 'lesson_id' => $lessonId, 'schedule_version_id' => $scheduleVersionId, 'purpose' => $purpose, 'student_id' => $studentId ?? 11, 'lesson_uid' => 'lesson-uid-1', 'schedule_version_uid' => 'schedule-uid-1' );
	}
}
/** The attendance owner handoff: a declared refusal, a persistence failure or a recorded claim. */
final class DznPhase2A2WPersistenceAttendance implements CanonicalAttendancePortalReadPort {
	public static string $mode = 'submitted';
	public function summaryForSubject( $subject, int $lessonId, int $scheduleVersionId ):array { throw new RuntimeException( 'unused' ); }
	public function assertCapabilityClaimAdmissible( PublicCapabilityReadSubject $subject ):void {}
	public function submitCapabilityClaim( PublicCapabilityReadSubject $subject, string $redemptionReference ):array {
		if ( 'declared_refusal' === self::$mode ) { throw new InvalidArgumentException( 'portal_absence_window_closed' ); }
		if ( 'infrastructure' === self::$mode ) { throw new RuntimeException( 'phase_persistence_unavailable' ); }
		return array( 'case_id' => 55, 'evidence_id' => 900 );
	}
}
PortalOwnerPorts::configureCapability( new DznPhase2A2WPersistenceOwner() );
PortalOwnerPorts::configureReadPorts(
	new class implements TeacherAssignmentPortalReadPort {
		public function forSubject( $subject, int $enrolmentId ):array { throw new RuntimeException( 'unused' ); }
		public function pageForSubject( $subject, ?string $cursor, int $limit ):array { throw new RuntimeException( 'unused' ); }
	},
	new class implements CanonicalLessonSchedulePortalReadPort {
		public function forSubject( $subject, int $lessonId ):array { throw new RuntimeException( 'unused' ); }
		public function pageForSubject( $subject, ?string $cursor, int $limit ):array { throw new RuntimeException( 'unused' ); }
	},
	new class implements CanonicalLessonDeliveryPortalReadPort {
		public function summaryForSubject( $subject, int $lessonId ):array { throw new RuntimeException( 'unused' ); }
	},
	new DznPhase2A2WPersistenceAttendance()
);

$failures = 0;
/** Diagnostics must work under both the CLI SAPI (where `STDERR` is defined) and the embedded runtime. */
function dzn_2a2w_persistence_note( string $message ):void {
	if ( defined( 'STDERR' ) ) { fwrite( STDERR, $message ); return; }
	@file_put_contents( 'php://stderr', $message );
}
function dzn_2a2w_persistence( bool $condition, string $message ):void {
	global $failures;
	if ( ! $condition ) { $failures++; dzn_2a2w_persistence_note( 'Phase-W persistence failure: FAIL - ' . $message . "\n" ); }
}
/** A pristine store in the same shape the public callbacks meet. */
function dzn_2a2w_persistence_store( string $fail_insert = '', string $fail_statement = '' ):DznPhase2A2WPersistenceStore {
	global $wpdb;
	$wpdb = new DznPhase2A2WPersistenceStore();
	$wpdb->fail_insert = $fail_insert;
	$wpdb->fail_statement = $fail_statement;
	return $wpdb;
}

$_SERVER['REMOTE_ADDR'] = '203.0.113.77';
$_REQUEST               = array();

// 1. A declared business refusal still commits exactly its refused action row and its denial row, appended
//    in the §15.2 order inside one transaction, then committed once.
$store = dzn_2a2w_persistence_store();
$dzn_2a2w_options = array();
$request = new WP_REST_Request( 'GET', '/delnavazan-platform/v1/portal/join' );
$request->set_param( 'handle', 'handle-business-refusal' );
$response = PortalPublicActionController::join( $request );
dzn_2a2w_persistence( 404 === $response->get_status() && array( 'code' => 'portal_action_unavailable' ) === $response->get_data(), 'a declared business refusal keeps the uniform non-enumerating 404' );
dzn_2a2w_persistence( 1 === count( $store->committed_events ) && 1 === count( $store->committed_denials ), 'one declared refusal commits exactly one action row and one denial row' );
dzn_2a2w_persistence( 'portal_route_disabled' === ( $store->committed_events[0]['outcome_reason_code'] ?? null ), 'the committed action row records the declared refusal reason' );
dzn_2a2w_persistence( $store->statements === array(
	'START TRANSACTION',
	'insert:wp_dzn_portal_public_action_events',
	'insert:wp_dzn_portal_access_denials',
	'COMMIT',
), 'one declared refusal is one transaction: action row, denial row, then exactly one commit' );

// 2. A failed refused-action write rolls the whole refusal evidence back and re-raises the declared
//    persistence failure.
$store = dzn_2a2w_persistence_store( 'action' );
$caught = null;
try { ( new PortalPublicActionService() )->recordRefusal( 'lesson_join', 'handle-evidence-1', 'portal_capability_unknown' ); } catch ( Throwable $e ) { $caught = $e; }
dzn_2a2w_persistence( $caught instanceof RuntimeException && 'portal_action_evidence_persistence_failed' === $caught->getMessage(), 'a failed refused-action insert must re-raise portal_action_evidence_persistence_failed' );
dzn_2a2w_persistence( array() === $store->committed_events && array() === $store->committed_denials, 'a failed refused-action insert must leave no committed action or denial row' );
dzn_2a2w_persistence( array() === $store->pending_events && array() === $store->pending_denials, 'a failed refused-action insert must roll its own row back' );
dzn_2a2w_persistence( in_array( 'ROLLBACK', $store->statements, true ) && ! in_array( 'COMMIT', $store->statements, true ), 'a failed refused-action insert must roll back instead of committing' );

// 3. A failed denial write rolls the refused action row back with it: the two rows are one piece of
//    evidence, never split across two transactions.
$store = dzn_2a2w_persistence_store( 'denial' );
$caught = null;
try { ( new PortalPublicActionService() )->recordRefusal( 'lesson_absence', 'handle-evidence-2', 'portal_capability_unknown' ); } catch ( Throwable $e ) { $caught = $e; }
dzn_2a2w_persistence( $caught instanceof RuntimeException && 'portal_action_evidence_persistence_failed' === $caught->getMessage(), 'a failed denial insert must re-raise portal_action_evidence_persistence_failed' );
dzn_2a2w_persistence( array() === $store->committed_events && array() === $store->committed_denials, 'a failed denial insert must leave no committed action or denial row' );
dzn_2a2w_persistence( $store->statements === array(
	'START TRANSACTION',
	'insert:wp_dzn_portal_public_action_events',
	'insert:wp_dzn_portal_access_denials',
	'ROLLBACK',
), 'the refused action row is appended before its denial row and both roll back together' );

// 4. A failed commit rolls both appended rows back.
$store = dzn_2a2w_persistence_store( '', 'COMMIT' );
$caught = null;
try { ( new PortalPublicActionService() )->recordRefusal( 'lesson_join', 'handle-evidence-3', 'portal_capability_unknown' ); } catch ( Throwable $e ) { $caught = $e; }
dzn_2a2w_persistence( $caught instanceof RuntimeException && 'portal_action_evidence_persistence_failed' === $caught->getMessage(), 'a failed commit must re-raise portal_action_evidence_persistence_failed' );
dzn_2a2w_persistence( array() === $store->committed_events && array() === $store->committed_denials, 'a failed commit must leave no committed action or denial row' );
dzn_2a2w_persistence( in_array( 'ROLLBACK', $store->statements, true ), 'a failed commit must roll back the appended evidence' );

// 5. A Join request whose own capability evidence write fails propagates that failure unchanged and
//    appends no subsequent refusal record: the controller opens no second transaction of its own.
$store = dzn_2a2w_persistence_store( '', 'INSERT IGNORE' );
$store->hint = (object) array( 'id' => 7, 'lesson_id' => 3 );
$dzn_2a2w_options = array( PortalRule::PUBLIC_ACTION_OPTION => PortalRule::PUBLIC_ACTION_ENABLED_VALUE );
$request = new WP_REST_Request( 'GET', '/delnavazan-platform/v1/portal/join' );
$request->set_param( 'handle', str_repeat( 'a', 64 ) );
$request->set_param( 'token', str_repeat( 'b', 71 ) );
$caught = null;
try { PortalPublicActionController::join( $request ); } catch ( Throwable $e ) { $caught = $e; }
dzn_2a2w_persistence( $caught instanceof RuntimeException && 'portal_capability_persistence_failed' === $caught->getMessage(), 'a failed capability evidence write must propagate unchanged out of the Join callback' );
dzn_2a2w_persistence( 0 === count( array_filter( $store->statements, static function ( string $statement ):bool { return str_starts_with( $statement, 'insert:' ); } ) ), 'a failed capability evidence write must append no refusal action row or denial row afterwards' );
dzn_2a2w_persistence( array() === $store->committed_events && array() === $store->committed_denials, 'a failed capability evidence write must commit no refusal evidence' );
dzn_2a2w_persistence( $store->statements === array(
	'START TRANSACTION',
	'INSERT IGNORE INTO wp_dzn_portal_lesson_capability_roots(lesson_id,created_at,created_by) VALUES(%d,%s,%d)',
	'ROLLBACK',
), 'the controller opens no second transaction after the rolled-back capability write' );

// 6. The same for the absence-confirmation callback: a missing Lesson root is a persistence failure, so the
//    refusal record the controller would otherwise write is never written.
$store = dzn_2a2w_persistence_store();
$store->hint = (object) array( 'id' => 9, 'lesson_id' => 4 );
$dzn_2a2w_options = array( PortalRule::PUBLIC_ACTION_OPTION => PortalRule::PUBLIC_ACTION_ENABLED_VALUE );
$request = new WP_REST_Request( 'POST', '/delnavazan-platform/v1/portal/absence/confirm' );
$request->set_param( 'handle', str_repeat( 'c', 64 ) );
$request->set_param( 'token', str_repeat( 'd', 71 ) );
$request->set_param( 'confirmation', str_repeat( 'e', 128 ) );
$caught = null;
try { PortalPublicActionController::absence( $request ); } catch ( Throwable $e ) { $caught = $e; }
dzn_2a2w_persistence( $caught instanceof RuntimeException && 'portal_action_evidence_persistence_failed' === $caught->getMessage(), 'a missing Lesson root must propagate unchanged out of the absence callback' );
dzn_2a2w_persistence( 0 === count( array_filter( $store->statements, static function ( string $statement ):bool { return str_starts_with( $statement, 'insert:' ); } ) ), 'a failed absence-evidence write must append no refusal action row or denial row afterwards' );
dzn_2a2w_persistence( array() === $store->committed_events && array() === $store->committed_denials, 'a failed absence-evidence write must commit no refusal evidence' );

// 7. The post-delegation owner handoff keeps the same split: a declared owner refusal is recorded as the
//    refused outcome with the owner's own reason (and its denial row in that one transaction), while an
//    owner persistence failure propagates unchanged and records no refused outcome and no denial at all.
$dzn_2a2w_absence_handle = str_repeat( 'a', 64 );
$dzn_2a2w_absence_expiry = gmdate( 'Y-m-d H:i:s', time() + 3600 );
$dzn_2a2w_absence_binding = PortalRule::CAPABILITY_BINDING_VERSION . '|lesson_absence|lesson-uid-1|schedule-uid-1|1|' . $dzn_2a2w_absence_expiry;
$dzn_2a2w_absence_token = $dzn_2a2w_absence_handle . hash_hmac( 'sha256', $dzn_2a2w_absence_binding, wp_salt( 'dzn_portal_capability_public' ) );
$dzn_2a2w_absence_capability = (object) array(
	'id' => 7,
	'uid' => 'capability-uid-1',
	'lesson_id' => 3,
	'schedule_version_id' => 5,
	'purpose' => 'lesson_absence',
	'generation' => 1,
	'expires_at' => $dzn_2a2w_absence_expiry,
	'state' => 'active',
	'subject_student_id' => 11,
	'token_digest' => hash_hmac( 'sha256', $dzn_2a2w_absence_token, wp_salt( 'dzn_portal_capability_public' ) ),
);
$dzn_2a2w_absence_nonce = str_repeat( 'b', PortalRule::CONFIRMATION_TOKEN_BYTES * 2 );
$dzn_2a2w_absence_confirmation = $dzn_2a2w_absence_nonce . hash_hmac( 'sha256', 'portal_absence_confirmation_v1|' . $dzn_2a2w_absence_handle . '|' . $dzn_2a2w_absence_nonce . '|' . $dzn_2a2w_absence_capability->id . '|' . $dzn_2a2w_absence_capability->generation . '|' . $dzn_2a2w_absence_capability->expires_at, wp_salt( 'dzn_portal_confirmation' ) );
$dzn_2a2w_options = array( PortalRule::PUBLIC_ACTION_OPTION => PortalRule::PUBLIC_ACTION_ENABLED_VALUE );
foreach ( array( 'declared_refusal', 'infrastructure' ) as $mode ) {
	$store = dzn_2a2w_persistence_store();
	$store->hint = (object) array( 'id' => 7, 'lesson_id' => 3 );
	$store->root_row = (object) array( 'id' => 21 );
	$store->capability_row = $dzn_2a2w_absence_capability;
	DznPhase2A2WPersistenceAttendance::$mode = $mode;
	$caught = null;
	try { ( new PortalPublicActionService() )->confirmAbsence( $dzn_2a2w_absence_handle, $dzn_2a2w_absence_token, $dzn_2a2w_absence_confirmation ); } catch ( Throwable $e ) { $caught = $e; }
	if ( 'declared_refusal' === $mode ) {
		dzn_2a2w_persistence( $caught instanceof PortalPublicActionRefusalRecorded && 'portal_absence_window_closed' === $caught->getMessage(), 'a declared owner refusal is reported as the refused outcome with the owner reason' );
		$last = $store->committed_events[ count( $store->committed_events ) - 1 ] ?? array();
		dzn_2a2w_persistence( 'refused' === ( $last['action_state'] ?? null ) && 'portal_absence_window_closed' === ( $last['outcome_reason_code'] ?? null ), 'the refused owner outcome is committed with the owner reason' );
		dzn_2a2w_persistence( 1 === count( $store->committed_denials ), 'a declared owner refusal commits its denial row in the same transaction as its outcome row' );
	} else {
		dzn_2a2w_persistence( $caught instanceof RuntimeException && 'phase_persistence_unavailable' === $caught->getMessage(), 'an owner persistence failure propagates unchanged out of the absence handoff' );
		dzn_2a2w_persistence( 0 === count( $store->committed_denials ), 'an owner persistence failure records no denial row' );
		dzn_2a2w_persistence( 0 === count( array_filter( $store->committed_events, static fn( array $row ):bool => 'refused' === ( $row['action_state'] ?? null ) ) ), 'an owner persistence failure records no refused outcome' );
		dzn_2a2w_persistence( 2 === count( $store->committed_events ), 'an owner persistence failure leaves only the committed redemption claim and delegation lease' );
	}
}

// 8. The classification itself.
$dzn_2a2w_options = array();
foreach ( PortalRule::PERSISTENCE_FAILURE_CODES as $code ) {
	dzn_2a2w_persistence( null === PortalRule::refusalReason( new RuntimeException( $code ) ), 'a declared persistence failure code is never a business refusal: ' . $code );
	dzn_2a2w_persistence( ! in_array( $code, PortalRule::EXCEPTION_REASON_CODES, true ), 'a declared persistence failure code is not a refusal-reason vocabulary member: ' . $code );
}
dzn_2a2w_persistence( 'portal_capability_unknown' === PortalRule::refusalReason( new InvalidArgumentException( 'portal_capability_unknown' ) ), 'a declared refusal reason is a business refusal' );
dzn_2a2w_persistence( 'portal_upstream_aggregate_invalid' === PortalRule::refusalReason( new InvalidArgumentException( 'portal_upstream_aggregate_invalid' ) ), 'a declared aggregate refusal is a business refusal' );
dzn_2a2w_persistence( null === PortalRule::refusalReason( new RuntimeException( 'unexpected infrastructure failure' ) ), 'an unexpected throwable is never converted into a business refusal' );
dzn_2a2w_persistence( null === PortalRule::refusalReason( new TypeError( 'unexpected type' ) ), 'a PHP error is never converted into a business refusal' );

// 9. The administrative command surface carries the identical split: it classifies before it writes, so a
//    persistence/corruption failure is re-raised instead of being recorded as a refused command.
$admin = file_get_contents( $root . '/src/Admin/Controller/PortalCapabilityController.php' );
$public = file_get_contents( $root . '/src/Portals/PortalPublicActionController.php' );
foreach ( array( 'admin' => $admin, 'public' => $public ) as $label => $source ) {
	dzn_2a2w_persistence( false !== strpos( $source, 'PortalRule::refusalReason($e)' ) && false !== strpos( $source, 'if($reason===null)throw $e;' ), 'the ' . $label . ' controller must classify before it writes refusal evidence' );
	dzn_2a2w_persistence( false === strpos( $source, 'in_array($e->getMessage(),PortalRule::EXCEPTION_REASON_CODES,true)' ), 'the ' . $label . ' controller must not map an unrecognised throwable to a durable refusal' );
	dzn_2a2w_persistence( false === strpos( $source, "?'portal_upstream_aggregate_invalid'" ), 'the ' . $label . ' controller must not convert an unrecognised throwable into portal_upstream_aggregate_invalid' );
}

if ( $failures > 0 ) {
	dzn_2a2w_persistence_note( 'phase-2a2w-persistence-failure-unit: FAIL (' . $failures . ")\n" );
	if ( ! defined( 'DZN_2A2W_PERSISTENCE_UNIT_EMBEDDED' ) ) { exit( 1 ); }
	throw new RuntimeException( 'Phase-W persistence-failure behavioural coverage failed' );
}
if ( ! defined( 'DZN_2A2W_PERSISTENCE_UNIT_EMBEDDED' ) ) { echo "phase-2a2w-persistence-failure-unit: OK (§15.6 refusal evidence is all-or-nothing and only a declared business refusal writes it)\n"; }
