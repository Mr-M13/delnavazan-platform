<?php
namespace Delnavazan\Platform\Core\Infrastructure\Repository;
final class CourseRepository extends BaseRepository { public function __construct(){ global $wpdb; $this->table=$wpdb->prefix . 'dzn_courses'; } }
