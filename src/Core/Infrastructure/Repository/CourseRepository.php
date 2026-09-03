<?php
namespace Delnavazan\Platform\Core\Infrastructure\Repository;

final class CourseRepository extends BaseRepository {
    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'dzn_courses';
    }

    public function hasOperationalEnrolments(int $courseId): bool {
        global $wpdb;
        $enrolments = $wpdb->prefix . 'dzn_enrolments';
        return (bool) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$enrolments} WHERE course_id = %d AND archived_at IS NULL AND status NOT IN ('archived', 'completed', 'cancelled') LIMIT 1",
            $courseId
        ));
    }
}
