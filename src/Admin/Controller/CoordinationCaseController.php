<?php
namespace Delnavazan\Platform\Admin\Controller;

use Delnavazan\Platform\Core\Application\CoordinationCaseService;
use Delnavazan\Platform\Core\Infrastructure\Repository\CoordinationCaseRepository;

/** Protected internal operating surface; deliberately contains no proposal or assignment controls. */
final class CoordinationCaseController {
    private const CAPABILITY = 'dzn_manage_booking_request_coordination';
    public static function handlePost(): void {
        if ( ( $_SERVER['REQUEST_METHOD'] ?? '' ) !== 'POST' ) return;
        if ( ! current_user_can( self::CAPABILITY ) || ! current_user_can( 'dzn_view_booking_requests' ) ) wp_die( 'Access denied.', 'Delnavazan', array( 'response' => 403 ) );
        $action = self::string( $_POST['dzn_coordination_action'] ?? null ); if ( $action === '' ) return;
        try {
            $service = new CoordinationCaseService();
            if ( $action === 'open' ) { $request = self::id( $_POST['booking_request_id'] ?? null ); check_admin_referer( 'dzn_coordination_open' ); $service->open( $request, self::string( $_POST['reason'] ?? null ) ); }
            elseif ( $action === 'add_candidate' ) { $case = self::id( $_POST['case_id'] ?? null ); check_admin_referer( 'dzn_coordination_candidate_' . $case ); $service->addCandidate( $case, self::id( $_POST['teacher_id'] ?? null ), self::string( $_POST['source'] ?? null ), self::string( $_POST['reason'] ?? null ) ); }
            elseif ( $action === 'transition_case' ) { $case = self::id( $_POST['case_id'] ?? null ); check_admin_referer( 'dzn_coordination_case_state_' . $case ); $service->transition( $case, self::id( $_POST['version'] ?? null ), self::string( $_POST['state'] ?? null ), self::string( $_POST['reason'] ?? null ) ); }
            elseif ( $action === 'transition_candidate' ) { $candidate = self::id( $_POST['candidate_id'] ?? null ); check_admin_referer( 'dzn_coordination_candidate_state_' . $candidate ); $service->transitionCandidate( $candidate, self::id( $_POST['version'] ?? null ), self::string( $_POST['status'] ?? null ), self::string( $_POST['reason'] ?? null ) ); }
            else throw new \InvalidArgumentException( 'Unsupported coordination action' );
            self::redirect( false );
        } catch ( \Throwable ) { self::redirect( true ); }
    }
    public static function screen(): void {
        if ( ! current_user_can( self::CAPABILITY ) || ! current_user_can( 'dzn_view_booking_requests' ) ) { echo '<div class="wrap"><p>Access denied.</p></div>'; return; }
        $repo = new CoordinationCaseRepository(); $caseId = self::idOrZero( $_GET['case_id'] ?? null );
        echo '<div class="wrap"><h1>Booking Request Coordination</h1>';
        if ( $caseId > 0 && ( $case = $repo->caseForRead( $caseId ) ) ) { self::detail( $repo, $case ); echo '</div>'; return; }
        echo '<p>Internal advisory coordination only. It does not assign, reserve, contact, assent, propose, accept, or convert.</p><h2>Open coordination</h2><form method="post">'; wp_nonce_field( 'dzn_coordination_open' ); echo '<input type="hidden" name="dzn_coordination_action" value="open"><p><label>Booking Request ID<br><input name="booking_request_id" type="number" min="1" required></label></p><p><label>Reason<br><select name="reason"><option value="admin_opened">admin_opened</option><option value="manual_review">manual_review</option></select></label></p><button class="button button-primary">Open coordination</button></form>';
        echo '<h2>Recent cases</h2><table class="widefat striped"><thead><tr><th>Reference</th><th>Booking Request</th><th>State</th><th>Version</th><th>Updated</th></tr></thead><tbody>';
        foreach ( $repo->rows() as $row ) { $url = add_query_arg( array( 'page' => 'dzn-booking-request-coordination', 'case_id' => $row->id ), admin_url( 'admin.php' ) ); echo '<tr><td><a href="' . esc_url( $url ) . '">' . esc_html( (string) $row->reference_code ) . '</a></td><td>' . esc_html( (string) $row->booking_request_id ) . '</td><td>' . esc_html( (string) $row->state ) . '</td><td>' . esc_html( (string) $row->version ) . '</td><td>' . esc_html( (string) $row->updated_at ) . '</td></tr>'; }
        echo '</tbody></table></div>';
    }
    private static function detail( CoordinationCaseRepository $repo, object $case ): void {
        echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=dzn-booking-request-coordination' ) ) . '">← Back</a></p><h2>' . esc_html( (string) $case->reference_code ) . '</h2><p>Booking Request #' . esc_html( (string) $case->booking_request_id ) . ' · State ' . esc_html( (string) $case->state ) . ' · Version ' . esc_html( (string) $case->version ) . '</p>';
        echo '<h3>Change advisory case state</h3><form method="post">'; wp_nonce_field( 'dzn_coordination_case_state_' . $case->id ); echo '<input type="hidden" name="dzn_coordination_action" value="transition_case"><input type="hidden" name="case_id" value="' . esc_attr( (string) $case->id ) . '"><input type="hidden" name="version" value="' . esc_attr( (string) $case->version ) . '"><select name="state">'; foreach ( array( 'candidate_search', 'manual_search', 'waiting_for_availability', 'withdrawn', 'declined', 'unable_to_arrange', 'abandoned' ) as $state ) echo '<option value="' . esc_attr( $state ) . '">' . esc_html( $state ) . '</option>'; echo '</select> <select name="reason"><option value="manual_review">manual_review</option><option value="unable_to_arrange">unable_to_arrange</option><option value="abandoned">abandoned</option><option value="withdrawn">withdrawn</option><option value="declined">declined</option></select><button class="button">Save state</button></form>';
        echo '<h3>Add advisory candidate</h3><form method="post">'; wp_nonce_field( 'dzn_coordination_candidate_' . $case->id ); echo '<input type="hidden" name="dzn_coordination_action" value="add_candidate"><input type="hidden" name="case_id" value="' . esc_attr( (string) $case->id ) . '"><input name="teacher_id" type="number" min="1" required> <select name="source">'; foreach ( array( 'advisory_match', 'manual_search', 'student_or_guardian_preference', 'teacher_referral', 'operational_referral', 'approved_exception' ) as $source ) echo '<option value="' . esc_attr( $source ) . '">' . esc_html( $source ) . '</option>'; echo '</select> <select name="reason"><option value="advisory_match">advisory_match</option><option value="manual_review">manual_review</option><option value="approved_exception">approved_exception</option></select><button class="button">Add candidate</button></form>';
        echo '<h3>Candidate considerations</h3><table class="widefat striped"><thead><tr><th>ID</th><th>Teacher</th><th>Source</th><th>Advisory status</th><th>Version</th><th>Update</th></tr></thead><tbody>';
        foreach ( $repo->candidates( (int) $case->id ) as $candidate ) { echo '<tr><td>' . esc_html( (string) $candidate->id ) . '</td><td>' . esc_html( (string) $candidate->teacher_id ) . '</td><td>' . esc_html( (string) $candidate->source ) . '</td><td>' . esc_html( (string) $candidate->status ) . '</td><td>' . esc_html( (string) $candidate->version ) . '</td><td><form method="post">'; wp_nonce_field( 'dzn_coordination_candidate_state_' . $candidate->id ); echo '<input type="hidden" name="dzn_coordination_action" value="transition_candidate"><input type="hidden" name="candidate_id" value="' . esc_attr( (string) $candidate->id ) . '"><input type="hidden" name="version" value="' . esc_attr( (string) $candidate->version ) . '"><select name="status">'; foreach ( array( 'under_discussion', 'not_available', 'not_suitable', 'withdrawn_from_consideration', 'superseded', 'closed' ) as $status ) echo '<option value="' . esc_attr( $status ) . '">' . esc_html( $status ) . '</option>'; echo '</select> <select name="reason"><option value="candidate_review">candidate_review</option><option value="not_available">not_available</option><option value="not_suitable">not_suitable</option><option value="withdrawn">withdrawn</option></select><button class="button">Save</button></form></td></tr>'; }
        echo '</tbody></table>';
    }
    private static function string( mixed $value ): string { return is_string( $value ) ? sanitize_key( wp_unslash( $value ) ) : ''; }
    private static function id( mixed $value ): int { if ( ! is_string( $value ) || ! ctype_digit( $value ) || (int) $value < 1 ) throw new \InvalidArgumentException( 'Invalid identifier' ); return (int) $value; }
    private static function idOrZero( mixed $value ): int { return is_string( $value ) && ctype_digit( $value ) ? (int) $value : 0; }
    private static function redirect( bool $error ): never { $url = add_query_arg( array( 'page' => 'dzn-booking-request-coordination', 'dzn_notice' => $error ? 'Operation failed; no change was saved.' : 'Saved', 'dzn_error' => $error ? '1' : '0' ), admin_url( 'admin.php' ) ); wp_safe_redirect( $url ); exit; }
}
