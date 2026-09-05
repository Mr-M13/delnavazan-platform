<?php
namespace Delnavazan\Platform\Core\Application;

use Delnavazan\Platform\Core\Infrastructure\Repository\BookingRequestRepository;

/** Validates intake-only facts; it deliberately performs neither matching nor routing. */
final class BookingRequestValidationService {
    public function __construct( private ?BookingRequestRepository $repo = null ) { $this->repo ??= new BookingRequestRepository(); }
    public function validate(array $input, bool $public = true): array {
        if ( $public ) {
            $allowed = array( 'requested_instrument_id', 'selected_intro_course_id', 'full_name', 'email', 'mobile', 'country', 'city', 'timezone', 'communication_language', 'whatsapp_same_as_mobile', 'whatsapp_number', 'privacy_notice_accepted', 'privacy_notice_version', 'requested_times' );
            if ( array_diff( array_keys( $input ), $allowed ) ) throw new \InvalidArgumentException( 'Unsupported public intake field' );
        }
        $instrument = Normalizer::id( $input['requested_instrument_id'] ?? null ); $instrumentRow = $this->repo->instrument( $instrument );
        if ( ! $instrumentRow || $instrumentRow->status !== 'active' || $instrumentRow->archived_at !== null ) throw new \InvalidArgumentException( 'Active Instrument required' );
        $course = Normalizer::id( $input['selected_intro_course_id'] ?? null, false ); $duration = 30; $buffer = 15;
        if ( $course ) { $courseRow = $this->repo->course( $course ); if ( ! $courseRow || $courseRow->status !== 'active' || $courseRow->archived_at !== null || $courseRow->course_type !== 'introductory' || (int) $courseRow->instrument_id !== $instrument ) throw new \InvalidArgumentException( 'Valid active Intro Course for Instrument required' ); $duration = (int) $courseRow->default_duration_minutes; $buffer = (int) $courseRow->default_buffer_minutes; }
        $full = Normalizer::text( $input['full_name'] ?? null, 191, true ); $email = Normalizer::email( $input['email'] ?? null ); $mobile = Normalizer::phone( $input['mobile'] ?? null ); $country = Normalizer::country( $input['country'] ?? null ); $city = Normalizer::text( $input['city'] ?? null, 191, true ); $timezone = Normalizer::timezone( $input['timezone'] ?? null ); $language = Normalizer::one( $input['communication_language'] ?? null, array( 'fa', 'en' ), 'communication language' );
        if ( ! $email || ! $this->phone( $mobile ) || ! $country || ! $city || ! $timezone ) throw new \InvalidArgumentException( 'Valid contact, country, city, and IANA timezone required' ); $same = filter_var( $input['whatsapp_same_as_mobile'] ?? null, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE ); if ( $same === null ) throw new \InvalidArgumentException( 'WhatsApp mobile relationship required' ); $whatsapp = $same ? $mobile : Normalizer::phone( $input['whatsapp_number'] ?? null ); if ( ! $this->phone( $whatsapp ) ) throw new \InvalidArgumentException( 'Valid WhatsApp number required' );
        if ( ($input['privacy_notice_accepted'] ?? null) !== true ) throw new \InvalidArgumentException( 'Privacy notice acknowledgement required' ); if ( isset( $input['privacy_notice_version'] ) && (string) $input['privacy_notice_version'] !== '2026-09-05' ) throw new \InvalidArgumentException( 'Unsupported privacy notice version' );
        $times = $input['requested_times'] ?? null; if ( ! is_array( $times ) || ! $times ) throw new \InvalidArgumentException( 'At least one requested time required' ); $normalized = array(); $seen = array(); foreach ( $times as $time ) { if ( ! is_array( $time ) || array_diff( array_keys( $time ), array( 'local_date', 'local_start_time', 'timezone' ) ) ) throw new \InvalidArgumentException( 'Malformed requested time' ); $item = RequestedTimeNormalizer::normalize( $time, $duration, $buffer ); $key = implode( '|', array( $item['local_date'], $item['local_start_time'], $item['timezone'], $duration, $buffer ) ); if ( isset( $seen[$key] ) ) throw new \InvalidArgumentException( 'Duplicate requested time' ); $seen[$key] = true; $normalized[] = $item; } usort( $normalized, static fn( array $a, array $b ): int => array( $a['starts_at_utc'], $a['local_date'], $a['local_start_time'], $a['timezone'] ) <=> array( $b['starts_at_utc'], $b['local_date'], $b['local_start_time'], $b['timezone'] ) );
        return array( 'instrument_id' => $instrument, 'course_id' => $course, 'contact' => array( 'full_name' => $full, 'email' => $email, 'mobile' => $mobile, 'country' => $country, 'city' => $city, 'timezone' => $timezone, 'communication_language' => $language, 'whatsapp_same_as_mobile' => $same ? 1 : 0, 'whatsapp_number' => $whatsapp ), 'times' => $normalized );
    }
    private function phone(?string $value): bool { return is_string( $value ) && preg_match( '/^\\+?[0-9][0-9 () .-]{5,31}$/', $value ) === 1; }
}
