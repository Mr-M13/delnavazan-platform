<?php
namespace Delnavazan\Platform\Core\Application;

use Delnavazan\Platform\Core\Infrastructure\Repository\BookingRequestRepository;
use Delnavazan\Platform\Core\Support\Identifier;

final class BookingRequestSubmissionService {
    public function __construct( private ?BookingRequestRepository $repo = null, private ?BookingRequestValidationService $validator = null ) { $this->repo ??= new BookingRequestRepository(); $this->validator ??= new BookingRequestValidationService( $this->repo ); }
    /** @return array{success:bool,request_reference:string} */
    public function submitPublic(array $input): array {
        $data = $this->validator->validate( $input, true ); $now = gmdate( 'Y-m-d H:i:s' ); $retention = gmdate( 'Y-m-d H:i:s', strtotime( '+180 days', strtotime( $now . ' UTC' ) ) );
        $uid = Identifier::uid(); $reference = 'REQ-' . $uid;
        $this->repo->begin(); try { $id = $this->repo->createRequest( array( 'uid' => $uid, 'reference_code' => null, 'student_id' => null, 'requested_instrument_id' => $data['instrument_id'], 'selected_intro_course_id' => $data['course_id'], 'lifecycle_status' => 'submitted', 'resolution_state' => 'unresolved', 'retention_due_at' => $retention, 'version' => 1, 'created_at' => $now, 'updated_at' => $now, 'created_by' => null, 'updated_by' => null ) ); $this->repo->assignReference( $id, $reference ); $this->repo->createSnapshot( $id, $data['contact'], $now ); foreach ( $data['times'] as $sequence => $time ) $this->repo->createTime( $id, $sequence + 1, $time, $now ); $this->repo->commit(); return array( 'success' => true, 'request_reference' => $reference ); } catch ( \Throwable $e ) { $this->repo->rollback(); throw $e; }
    }
}
