<?php
namespace Delnavazan\Platform\Core\Application;

use Delnavazan\Platform\Core\Infrastructure\Repository\TeacherAvailabilityAssentRepository;
use Delnavazan\Platform\Core\Support\Identifier;

/** Bounded authority: records only a Teacher's time-bounded willingness for frozen pre-issue facts. */
final class TeacherAvailabilityAssentService {
    private const CLOSED_CASES = array( 'withdrawn', 'declined', 'unable_to_arrange', 'abandoned' );
    public function __construct( private ?TeacherAvailabilityAssentRepository $repo = null ) { $this->repo ??= new TeacherAvailabilityAssentRepository(); }

    /** Authorised Delnavazan actor attests evidence attributable to the named Teacher. */
    public function recordAdministratorAttestation( array $input ): array { $this->admin(); return $this->record( $input, 'administrator', $this->actor() ); }
    /** Authenticated Teacher principal records only their own authoritative statement. */
    public function recordAuthenticatedTeacher( array $input ): array { $this->teacherCapability(); return $this->record( $input, 'teacher', $this->actor() ); }

    public function withdraw( int $assentId, int $expectedVersion, string $reason ): int {
        $assentId = Normalizer::id( $assentId ); $expectedVersion = Normalizer::count( $expectedVersion, 1, PHP_INT_MAX ); $reason = $this->reason( $reason ); $actor = $this->actor();
        $read = $this->repo->assentForRead( $assentId ); if ( ! $read ) throw new \InvalidArgumentException( 'Teacher assent not found' );
        $this->repo->begin(); try {
            $this->contextForUpdate( (int) $read->candidate_id, null ); $assent = $this->repo->assentForUpdate( $assentId );
            if ( ! $assent || (int) $assent->version !== $expectedVersion || $assent->state !== 'recorded' ) throw new \RuntimeException( 'Teacher assent changed concurrently' );
            if ( ! current_user_can( 'dzn_manage_teacher_availability_assent' ) && ( ! current_user_can( 'dzn_record_own_availability_assent' ) || ! $this->repo->teacherPrincipalHasAuthority( $actor, (int) $assent->teacher_id ) ) ) throw new \RuntimeException( 'Unauthorized' );
            $now = gmdate( 'Y-m-d H:i:s' ); $version = $this->repo->transitionAssent( $assent, 'withdrawn', $reason, $actor, $now );
            $this->repo->audit( 'teacher_availability_assent', $assentId, 'teacher_availability_assent.withdrawn', $actor, $reason, 'state=withdrawn;version=' . $version, $this->key( 'withdraw:' . $assentId . ':' . $version ), $now );
            $this->repo->commit(); return $version;
        } catch ( \Throwable $e ) { $this->repo->rollback(); throw $e; }
    }
    public function invalidate( int $assentId, int $expectedVersion, string $reason ): int {
        $this->admin(); $assentId = Normalizer::id( $assentId ); $expectedVersion = Normalizer::count( $expectedVersion, 1, PHP_INT_MAX ); $reason = $this->reason( $reason ); $actor = $this->actor();
        $read = $this->repo->assentForRead( $assentId ); if ( ! $read ) throw new \InvalidArgumentException( 'Teacher assent not found' );
        $this->repo->begin(); try {
            $this->contextForUpdate( (int) $read->candidate_id, null ); $assent = $this->repo->assentForUpdate( $assentId );
            if ( ! $assent || (int) $assent->version !== $expectedVersion || $assent->state !== 'recorded' ) throw new \RuntimeException( 'Teacher assent changed concurrently' );
            $now = gmdate( 'Y-m-d H:i:s' ); $version = $this->repo->transitionAssent( $assent, 'invalidated', $reason, $actor, $now );
            $this->repo->audit( 'teacher_availability_assent', $assentId, 'teacher_availability_assent.invalidated', $actor, $reason, 'state=invalidated;version=' . $version, $this->key( 'invalidate:' . $assentId . ':' . $version ), $now );
            $this->repo->commit(); return $version;
        } catch ( \Throwable $e ) { $this->repo->rollback(); throw $e; }
    }
    /** Currentness is derived and never turns an unexpired record into a reservation. */
    public function current( int $candidateId, string $fingerprint ): ?object {
        $candidateId = Normalizer::id( $candidateId ); $fingerprint = $this->fingerprint( $fingerprint ); $now = gmdate( 'Y-m-d H:i:s' );
        $assent = $this->repo->currentForFingerprint( $candidateId, $fingerprint, $now );
        return $assent && $this->repo->currentSourceReliance( (int) $assent->id ) && $this->repo->currentTeacherReliance( (int) $assent->teacher_id, $assent->course_id === null ? null : (int) $assent->course_id, $now ) ? $assent : null;
    }

    private function record( array $input, string $attribution, int $actor ): array {
        $candidateId = Normalizer::id( $input['candidate_id'] ?? null ); $candidateVersion = Normalizer::count( $input['candidate_version'] ?? null, 1, PHP_INT_MAX ); $facts = $this->facts( $input ); $validity = $this->validity( $input );
        $replaces = Normalizer::id( $input['supersede_assent_id'] ?? null, false ); $replaceVersion = $replaces ? Normalizer::count( $input['supersede_assent_version'] ?? null, 1, PHP_INT_MAX ) : null;
        $read = $this->repo->candidateForRead( $candidateId ); if ( ! $read ) throw new \InvalidArgumentException( 'Candidate Teacher not found' );
        $this->repo->begin(); try {
            $context = $this->contextForUpdate( $candidateId, $candidateVersion );
            if ( (int) $context['teacher']->id !== $facts['teacher_id'] ) throw new \InvalidArgumentException( 'Frozen arrangement Teacher must match Candidate Teacher' );
            if ( $attribution === 'teacher' && ! $this->repo->teacherPrincipalHasAuthority( $actor, $facts['teacher_id'] ) ) throw new \RuntimeException( 'Authenticated Teacher authority is not established' );
            if ( $facts['course_id'] !== null && ( ! $this->repo->courseForRead( $facts['course_id'] ) || ! $this->repo->teacherEligibleForCourse( $facts['teacher_id'], $facts['course_id'], $context['now'] ) ) ) throw new \InvalidArgumentException( 'Teacher is not currently eligible for the intended Course' );
            $facts['booking_request_id'] = (int) $context['request']->id; $facts['coordination_case_id'] = (int) $context['case']->id; $facts['candidate_id'] = $candidateId; $facts['created_at'] = $context['now']; $facts['created_by'] = $actor;
            $snapshot = $this->repo->snapshotForCandidateFingerprintForUpdate( $candidateId, $facts['arrangement_fingerprint'] );
            if ( ! $snapshot ) { $facts['uid'] = Identifier::uid(); $snapshotId = $this->repo->createSnapshot( $facts ); $snapshot = $this->repo->snapshotForCandidateFingerprintForUpdate( $candidateId, $facts['arrangement_fingerprint'] ); if ( ! $snapshot || (int) $snapshot->id !== $snapshotId ) throw new \RuntimeException( 'Assent snapshot changed concurrently' ); }
            $existing = $this->repo->currentForSnapshotForUpdate( (int) $snapshot->id, $context['now'] );
            if ( $existing && ( $replaces === null || (int) $existing->id !== $replaces ) ) throw new \InvalidArgumentException( 'A current Teacher assent already exists for this exact arrangement' );
            if ( $replaces !== null ) { $prior = $this->repo->assentForUpdate( $replaces ); if ( ! $prior || (int) $prior->candidate_id !== $candidateId || (int) $prior->version !== $replaceVersion || $prior->state !== 'recorded' ) throw new \RuntimeException( 'Teacher assent changed concurrently' ); }
            $id = $this->repo->createAssent( array( 'uid' => Identifier::uid(), 'snapshot_id' => (int) $snapshot->id, 'booking_request_id' => (int) $context['request']->id, 'coordination_case_id' => (int) $context['case']->id, 'candidate_id' => $candidateId, 'teacher_id' => $facts['teacher_id'], 'state' => 'recorded', 'version' => 1, 'valid_until' => $validity['valid_until'], 'review_by' => $validity['review_by'], 'recorded_at' => $context['now'], 'attribution_type' => $attribution, 'teacher_actor_user_id' => $attribution === 'teacher' ? $actor : null, 'attested_by' => $attribution === 'administrator' ? $actor : null, 'attribution_basis' => $this->code( $input['attribution_basis'] ?? null, 64, 'Attribution basis' ), 'evidence_channel' => $this->one( $input['evidence_channel'] ?? null, array( 'in_person', 'phone', 'email_reference', 'message_reference', 'other_reference' ), 'Evidence channel' ), 'evidence_reference' => $this->code( $input['evidence_reference'] ?? null, 128, 'Evidence reference' ), 'evidence_at' => $this->utc( $input['evidence_at'] ?? null, 'Evidence timestamp' ), 'uncertainty_code' => $this->optionalCode( $input['uncertainty_code'] ?? null, 128 ), 'withdrawn_at' => null, 'withdrawn_by' => null, 'invalidated_at' => null, 'invalidated_by' => null, 'state_reason_code' => 'recorded', 'superseded_by_assent_id' => null, 'created_at' => $context['now'], 'updated_at' => $context['now'], 'created_by' => $actor, 'updated_by' => $actor ) );
            if ( $replaces !== null ) { $prior = $this->repo->assentForUpdate( $replaces ); $this->repo->transitionAssent( $prior, 'superseded', 'renewed_or_replaced', $actor, $context['now'], $id ); $this->repo->audit( 'teacher_availability_assent', $replaces, 'teacher_availability_assent.superseded', $actor, 'renewed_or_replaced', 'superseded_by=' . $id, $this->key( 'supersede:' . $replaces . ':' . $id ), $context['now'] ); }
            $this->repo->audit( 'teacher_availability_assent', $id, 'teacher_availability_assent.recorded', $actor, 'recorded', 'attribution=' . $attribution . ';snapshot=' . $snapshot->id . ';fingerprint=' . $facts['arrangement_fingerprint'], $this->key( 'record:' . $id ), $context['now'] );
            $this->repo->commit(); return array( 'assent_id' => $id, 'snapshot_id' => (int) $snapshot->id, 'fingerprint' => $facts['arrangement_fingerprint'], 'version' => 1, 'created' => true );
        } catch ( \Throwable $e ) { $this->repo->rollback(); throw $e; }
    }
    /** Locks in established order: Booking Request, Case, Candidate, Teacher, then Snapshot/Assent. */
    private function contextForUpdate( int $candidateId, ?int $expectedCandidateVersion ): array {
        $read = $this->repo->candidateForRead( $candidateId ); if ( ! $read ) throw new \InvalidArgumentException( 'Candidate Teacher not found' );
        $caseRead = $this->repo->caseForRead( (int) $read->coordination_case_id ); if ( ! $caseRead ) throw new \InvalidArgumentException( 'Coordination Case not found' );
        $caseReadId = (int) $caseRead->id; $request = $this->repo->requestForUpdate( (int) $caseRead->booking_request_id );
        $case = $this->repo->caseForUpdate( $caseReadId ); if ( ! $case || ! $request || (int) $case->booking_request_id !== (int) $request->id || in_array( $case->state, self::CLOSED_CASES, true ) || $request->student_id !== null || $request->lifecycle_status !== 'submitted' || $request->resolution_state !== 'unresolved' || $request->privacy_erased_at !== null ) throw new \InvalidArgumentException( 'Booking Request is not available for Teacher assent' );
        $candidate = $this->repo->candidateForUpdate( $candidateId ); if ( ! $candidate || (int) $candidate->coordination_case_id !== (int) $case->id || ( $expectedCandidateVersion !== null && (int) $candidate->version !== $expectedCandidateVersion ) ) throw new \RuntimeException( 'Candidate Teacher changed concurrently' );
        if ( in_array( $candidate->status, array( 'not_available', 'not_suitable', 'withdrawn_from_consideration', 'superseded', 'closed' ), true ) ) throw new \InvalidArgumentException( 'Candidate Teacher is not available for assent' );
        $teacher = $this->repo->teacherForAssentForUpdate( (int) $candidate->teacher_id ); if ( ! $teacher ) throw new \InvalidArgumentException( 'Candidate Teacher is not currently eligible for assent' );
        return array( 'request' => $request, 'case' => $case, 'candidate' => $candidate, 'teacher' => $teacher, 'now' => gmdate( 'Y-m-d H:i:s' ) );
    }
    private function facts( array $input ): array {
        $course = Normalizer::id( $input['course_id'] ?? null, false ); $spec = $this->optionalCode( $input['unresolved_course_spec'] ?? null, 128 ); if ( ( $course === null ) === ( $spec === null ) ) throw new \InvalidArgumentException( 'Exactly one intended Course or unresolved Course specification is required' );
        $facts = array( 'teacher_id' => Normalizer::id( $input['teacher_id'] ?? null ), 'prospective_subject_ref' => $this->code( $input['prospective_subject_ref'] ?? null, 64, 'Prospective subject reference' ), 'course_id' => $course, 'unresolved_course_spec' => $spec, 'delivery_mode' => $this->one( $input['delivery_mode'] ?? null, array( 'online', 'in_person', 'hybrid' ), 'Delivery mode' ), 'location_scope' => $this->code( $input['location_scope'] ?? null, 64, 'Location scope' ), 'frequency_per_week' => Normalizer::count( $input['frequency_per_week'] ?? null, 1, 14 ), 'expected_duration_minutes' => Normalizer::count( $input['expected_duration_minutes'] ?? null, 15, 480 ), 'schedule_constraints' => $this->code( $input['schedule_constraints'] ?? null, 255, 'Schedule constraints' ), 'commencement_window_start' => $this->utc( $input['commencement_window_start'] ?? null, 'Commencement start' ), 'commencement_window_end' => $this->utc( $input['commencement_window_end'] ?? null, 'Commencement end' ), 'timezone' => $this->timezone( $input['timezone'] ?? null ), 'conditions_code' => $this->optionalCode( $input['conditions_code'] ?? null, 128 ) );
        if ( $facts['commencement_window_end'] <= $facts['commencement_window_start'] ) throw new \InvalidArgumentException( 'Commencement window end must follow start' );
        $facts['arrangement_fingerprint'] = hash( 'sha256', implode( "\n", array_map( static fn( $value ): string => $value === null ? '' : (string) $value, $facts ) ) ); return $facts;
    }
    private function validity( array $input ): array { $valid = $this->utcOptional( $input['valid_until'] ?? null, 'Validity end' ); $review = $this->utcOptional( $input['review_by'] ?? null, 'Review-by' ); $now = gmdate( 'Y-m-d H:i:s' ); if ( $valid === null && $review === null ) throw new \InvalidArgumentException( 'Validity end or review-by timestamp is required' ); if ( ( $valid !== null && $valid <= $now ) || ( $review !== null && $review <= $now ) ) throw new \InvalidArgumentException( 'Validity and review-by must be future timestamps' ); return array( 'valid_until' => $valid, 'review_by' => $review ); }
    private function admin(): void { if ( ! current_user_can( 'dzn_manage_teacher_availability_assent' ) ) throw new \RuntimeException( 'Unauthorized' ); }
    private function teacherCapability(): void { if ( ! current_user_can( 'dzn_record_own_availability_assent' ) ) throw new \RuntimeException( 'Unauthorized' ); }
    private function actor(): int { $actor = get_current_user_id(); if ( $actor < 1 ) throw new \RuntimeException( 'Authenticated actor required' ); return $actor; }
    private function reason( string $value ): string { return $this->one( $value, array( 'teacher_withdrew', 'operational_unavailable', 'eligibility_changed', 'attribution_unreliable', 'material_facts_changed', 'privacy_erased', 'renewed_or_replaced' ), 'Assent reason' ); }
    private function one( mixed $value, array $allowed, string $label ): string { return Normalizer::one( $value, $allowed, $label ); }
    private function code( mixed $value, int $max, string $label ): string { $value = Normalizer::text( $value, $max, true ); if ( ! preg_match( '/^[A-Za-z0-9][A-Za-z0-9:._,;|+\\/-]*$/D', $value ) ) throw new \InvalidArgumentException( $label . ' must be a privacy-safe controlled code' ); return $value; }
    private function optionalCode( mixed $value, int $max ): ?string { if ( $value === null || $value === '' ) return null; return $this->code( $value, $max, 'Controlled code' ); }
    private function timezone( mixed $value ): string { $timezone = Normalizer::timezone( $value ); if ( ! $timezone ) throw new \InvalidArgumentException( 'IANA timezone required' ); return $timezone; }
    private function utc( mixed $value, string $label ): string { $value = (string) $value; $date = \DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', $value, new \DateTimeZone( 'UTC' ) ); if ( ! $date || $date->format( 'Y-m-d H:i:s' ) !== $value ) throw new \InvalidArgumentException( $label . ' must be canonical UTC datetime' ); return $value; }
    private function utcOptional( mixed $value, string $label ): ?string { return $value === null || $value === '' ? null : $this->utc( $value, $label ); }
    private function fingerprint( string $value ): string { if ( ! preg_match( '/^[a-f0-9]{64}$/D', $value ) ) throw new \InvalidArgumentException( 'Arrangement fingerprint required' ); return $value; }
    private function key( string $value ): string { return hash_hmac( 'sha256', 'teacher-assent:' . $value . ':' . Identifier::uid(), wp_salt( 'dzn_teacher_assent_audit' ) ); }
}
