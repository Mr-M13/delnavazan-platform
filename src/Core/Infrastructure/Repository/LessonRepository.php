<?php
namespace Delnavazan\Platform\Core\Infrastructure\Repository;

final class LessonRepository extends BaseRepository {
    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'dzn_lessons';
    }

    public function locked(int $id): ?object {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %d FOR UPDATE", $id));
    }

    public function setCurrentSchedule(int $lesson, ?int $expected, int $schedule, string $now, ?int $actor): void {
        global $wpdb;
        $result = $wpdb->update($this->table, ['current_schedule_version_id' => $schedule, 'status' => 'scheduled', 'updated_at' => $now, 'updated_by' => $actor], ['id' => $lesson, 'current_schedule_version_id' => $expected]);
        if (false === $result) throw new \RuntimeException($wpdb->last_error);
        if ($result !== 1) throw new \RuntimeException('Stale Lesson schedule pointer');
    }

    public function findNotArchived(int $id): ?object {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE id = %d AND archived_at IS NULL AND status <> 'archived'",
            $id
        ));
    }
}
