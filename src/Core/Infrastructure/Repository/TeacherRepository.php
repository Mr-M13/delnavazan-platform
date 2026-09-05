<?php
namespace Delnavazan\Platform\Core\Infrastructure\Repository;

final class TeacherRepository extends BaseRepository {
    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'dzn_teachers';
    }

    public function hasOperationalEnrolments(int $teacherId): bool {
        global $wpdb;
        $enrolments = $wpdb->prefix . 'dzn_enrolments';
        return (bool) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$enrolments} WHERE teacher_id = %d AND archived_at IS NULL AND status NOT IN ('archived', 'completed', 'cancelled') LIMIT 1",
            $teacherId
        ));
    }

    /** Archival is blocked until Teacher-domain authority is explicitly offboarded. */
    public function hasActivePrincipalAuthority(int $teacherId): bool {
        global $wpdb;
        $links = $wpdb->prefix . 'dzn_teacher_principal_links';
        return (bool) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$links} WHERE teacher_id = %d AND status = 'active' LIMIT 1",
            $teacherId
        ));
    }
}
