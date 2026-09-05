<?php
namespace Delnavazan\Platform\Public;

use Delnavazan\Platform\Core\Application\BookingRequestSubmissionService;

final class BookingRequestRestController {
    public static function register(): void { register_rest_route( 'delnavazan-platform/v1', '/booking-requests', array( 'methods' => 'POST', 'permission_callback' => '__return_true', 'callback' => array( __CLASS__, 'submit' ) ) ); }
    public static function submit( \WP_REST_Request $request ): \WP_REST_Response { try { $result = ( new BookingRequestSubmissionService() )->submitPublic( (array) $request->get_json_params() ); return new \WP_REST_Response( $result, 201 ); } catch ( \InvalidArgumentException ) { return new \WP_REST_Response( array( 'success' => false, 'code' => 'invalid_request' ), 400 ); } catch ( \Throwable ) { return new \WP_REST_Response( array( 'success' => false, 'code' => 'submission_unavailable' ), 500 ); } }
}
