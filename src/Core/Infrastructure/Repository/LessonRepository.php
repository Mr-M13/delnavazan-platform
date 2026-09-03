<?php
namespace Delnavazan\Platform\Core\Infrastructure\Repository;
final class LessonRepository extends BaseRepository { public function __construct(){ global $wpdb; $this->table=$wpdb->prefix . 'dzn_lessons'; } public function setCurrentSchedule(int $lesson,int $schedule,string $now,?int $actor):void{global $wpdb;if(false===$wpdb->update($this->table,['current_schedule_version_id'=>$schedule,'status'=>'scheduled','updated_at'=>$now,'updated_by'=>$actor],['id'=>$lesson]))throw new \RuntimeException($wpdb->last_error);} }
