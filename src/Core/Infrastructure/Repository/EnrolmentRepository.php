<?php
namespace Delnavazan\Platform\Core\Infrastructure\Repository;

final class EnrolmentRepository extends BaseRepository {
    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'dzn_enrolments';
    }

    /** Phase 1 downstream services may consume legacy Enrolments only. */
    public function usable(int $id): ?object {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE id = %d AND record_model = 'legacy_phase1' AND archived_at IS NULL AND status <> 'archived'",
            $id
        ));
    }

    /** A later conversion phase must add a dedicated, invariant-safe writer. */
    public function insert(array $data): int {
        throw new \RuntimeException('Generic Enrolment repository insertion is disabled');
    }

    public function forStudentCourse(int $studentId, int $courseId): array {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE student_id = %d AND course_id = %d ORDER BY id ASC",
            $studentId,
            $courseId
        )) ?: [];
    }

    public function forAcceptedArrangement(int $arrangementId): array {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE accepted_service_arrangement_id = %d ORDER BY id ASC",
            $arrangementId
        )) ?: [];
    }

    public function lifecycleHistory(int $enrolmentId): array {
        global $wpdb;
        $events = $wpdb->prefix . 'dzn_enrolment_lifecycle_events';
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$events} WHERE enrolment_id = %d ORDER BY event_sequence ASC",
            $enrolmentId
        )) ?: [];
    }

    public function hasOperationalTerms(int $enrolmentId): bool {
        global $wpdb;
        $terms = $wpdb->prefix . 'dzn_terms';
        return (bool) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$terms} WHERE enrolment_id = %d AND archived_at IS NULL AND status NOT IN ('archived', 'completed', 'cancelled') LIMIT 1",
            $enrolmentId
        ));
    }

    public function hasOperationalLessons(int $enrolmentId): bool {
        global $wpdb;
        $lessons = $wpdb->prefix . 'dzn_lessons';
        return (bool) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$lessons} WHERE enrolment_id = %d AND archived_at IS NULL AND status NOT IN ('archived', 'completed', 'cancelled') LIMIT 1",
            $enrolmentId
        ));
    }
}
