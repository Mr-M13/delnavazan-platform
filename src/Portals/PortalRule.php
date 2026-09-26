<?php
namespace Delnavazan\Platform\Portals;

final class PortalRule {
    public const JOIN='lesson_join';
    public const ABSENCE='lesson_absence';
    public const PROVIDER_CALLS=0;
    public const PLATFORM_OUTBOX_WRITES=0;
    public const THEME_WRITES=0;
    public const MAX_CAPABILITY_TTL_SECONDS=86400;
    public const SAFE_JOIN_HOSTS=array('meet.google.com','zoom.us','teams.microsoft.com');
    public const REASON_CODES=array('operator_issue','suspected_leak','operator_reschedule_rotation','operator_archive_rotation','operator_cancel_rotation','portal_parent_not_live','portal_capability_unknown','portal_capability_expired','portal_capability_revoked','portal_capability_superseded','portal_capability_stale_schedule','portal_object_not_portal_visible','portal_join_target_not_allowlisted','portal_actions_disabled','portal_principal_required','portal_principal_unresolved','portal_principal_ambiguous','portal_principal_kind_not_permitted','portal_upstream_aggregate_invalid','command_replay_conflict');
    public static function reason(string $reason):string { if(!in_array($reason,self::REASON_CODES,true)) throw new \InvalidArgumentException('portal_reason_code_not_allowed'); return $reason; }
}
