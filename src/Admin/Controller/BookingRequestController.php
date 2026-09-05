<?php
namespace Delnavazan\Platform\Admin\Controller;

use Delnavazan\Platform\Core\Infrastructure\Repository\BookingRequestRepository;

/** Read-only, capability-gated inspection of sensitive intake data. */
final class BookingRequestController {
    public static function screen(): void { if ( ! current_user_can( 'dzn_view_booking_requests' ) ) { echo '<div class="wrap"><p>Access denied.</p></div>'; return; } $repo = new BookingRequestRepository(); $id = filter_var( $_GET['request_id'] ?? null, FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 1 ) ) ); echo '<div class="wrap"><h1>Booking Requests</h1>'; if ( $id && ( $row = $repo->details( (int) $id ) ) ) { foreach ( get_object_vars( $row ) as $field => $value ) echo '<p><strong>' . esc_html( $field ) . ':</strong> ' . esc_html( (string) $value ) . '</p>'; echo '<h2>Requested times</h2>'; foreach ( $repo->times( (int) $id ) as $time ) echo '<p>' . esc_html( wp_json_encode( $time ) ) . '</p>'; echo '</div>'; return; } echo '<table class="widefat striped"><thead><tr><th>Reference</th><th>Instrument</th><th>Lifecycle</th><th>Resolution</th><th>Created</th></tr></thead><tbody>'; foreach ( $repo->rows() as $row ) echo '<tr><td><a href="' . esc_url( add_query_arg( array( 'page' => 'dzn-booking-requests', 'request_id' => $row->id ), admin_url( 'admin.php' ) ) ) . '">' . esc_html( (string) $row->reference_code ) . '</a></td><td>' . esc_html( (string) $row->requested_instrument_id ) . '</td><td>' . esc_html( (string) $row->lifecycle_status ) . '</td><td>' . esc_html( (string) $row->resolution_state ) . '</td><td>' . esc_html( (string) $row->created_at ) . '</td></tr>'; echo '</tbody></table></div>'; }
}
