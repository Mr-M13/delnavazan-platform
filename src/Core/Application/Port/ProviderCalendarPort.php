<?php
namespace Delnavazan\Platform\Core\Application\Port;

/**
 * Calendar projection boundary.
 *
 * A projection mirrors one exact applicable canonical schedule version. The port may create, update
 * or delete a provider calendar event for an already-approved command, and may return a normalised
 * provider reference — it may never create, revise, release or reschedule canonical schedule
 * authority, and it never decides capacity.
 */
interface ProviderCalendarPort {
    /**
     * @param array{provider_code:string,operation:string,lesson_id:int,schedule_version_id:int,starts_at_utc:string,ends_at_utc:string,schedule_timezone:string,local_wall_date:string,local_wall_time:string,teacher_subject_reference:?string,projection_reference:?string} $command
     * @return array{provider_object_reference:string,provider_occurred_at_utc:string}
     */
    public function project(array $command):array;
}
