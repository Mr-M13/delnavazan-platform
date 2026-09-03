<?php
namespace Delnavazan\Platform\Core\Infrastructure\Repository;

final class TermRepository extends BaseRepository {
    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'dzn_terms';
    }

    public function sequenceExists(int $enrolment, int $sequence): bool {
        global $wpdb;
        return (bool) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$this->table} WHERE enrolment_id = %d AND sequence_number = %d",
            $enrolment,
            $sequence
        ));
    }

    public function belongsToUsableEnrolment(int $termId, int $enrolmentId): bool {
        global $wpdb;
        return (bool) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$this->table} WHERE id = %d AND enrolment_id = %d AND archived_at IS NULL AND status <> 'archived'",
            $termId,
            $enrolmentId
        ));
    }

    public function hasOperationalLessons(int $termId): bool {
        global $wpdb;
        $lessons = $wpdb->prefix . 'dzn_lessons';
        return (bool) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$lessons} WHERE term_id = %d AND archived_at IS NULL AND status NOT IN ('archived', 'completed', 'cancelled') LIMIT 1",
            $termId
        ));
    }
}
