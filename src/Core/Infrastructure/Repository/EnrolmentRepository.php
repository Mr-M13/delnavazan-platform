<?php
namespace Delnavazan\Platform\Core\Infrastructure\Repository;

final class EnrolmentRepository extends BaseRepository {
    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'dzn_enrolments';
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
