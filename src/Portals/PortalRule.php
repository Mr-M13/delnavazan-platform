<?php
namespace Delnavazan\Platform\Portals;

final class PortalRule {
    public const JOIN='lesson_join';
    public const ABSENCE='lesson_absence';
    public const PROVIDER_CALLS=0;
    public const PLATFORM_OUTBOX_WRITES=0;
    public const THEME_WRITES=0;
    public const CAPABILITY_BINDING_VERSION='portal_capability_binding_v1';
    public const HANDLE_ENTROPY_BYTES=32;
    public const MAX_CAPABILITY_TTL_SECONDS=2592000;
    public const PUBLIC_ACTION_OPTION='dzn_platform_portal_actions';
    public const PUBLIC_ACTION_ENABLED_VALUE='enabled';
    public const SAFE_JOIN_HOSTS=array('meet.google.com','zoom.us','teams.microsoft.com');
    public const EXCEPTION_REASON_CODES=array('portal_vocabulary_member_not_allowed','portal_parent_not_declared','portal_parent_not_live','portal_principal_required','portal_principal_unresolved','portal_principal_ambiguous','portal_principal_kind_not_permitted','portal_object_not_found','portal_object_not_owned','portal_object_not_portal_visible','portal_upstream_aggregate_invalid','portal_capability_unknown','portal_capability_handle_malformed','portal_capability_signature_invalid','portal_capability_expired','portal_capability_revoked','portal_capability_superseded','portal_capability_consumed','portal_capability_purpose_mismatch','portal_capability_stale_schedule','portal_capability_expiry_missing','portal_capability_ttl_not_allowed','portal_capability_binding_mismatch','portal_capability_generation_conflict','portal_join_target_not_allowlisted','portal_join_target_unavailable','portal_join_target_not_declared','portal_absence_window_closed','portal_absence_outcome_final','portal_absence_late_evidence','portal_confirmation_required','portal_confirmation_invalid','command_replay_conflict','portal_rate_limited','portal_route_disabled');
    public const OPERATOR_REASON_CODES=array('phase_w_declared_default','operator_decision','operator_recorded_error','operator_suspected_leak','operator_reschedule_rotation','operator_cancellation_rotation','operator_archive_rotation');
    public const REASON_CODES=array('phase_w_declared_default','operator_decision','operator_recorded_error','operator_suspected_leak','operator_reschedule_rotation','operator_cancellation_rotation','operator_archive_rotation','portal_vocabulary_member_not_allowed','portal_parent_not_declared','portal_parent_not_live','portal_principal_required','portal_principal_unresolved','portal_principal_ambiguous','portal_principal_kind_not_permitted','portal_object_not_found','portal_object_not_owned','portal_object_not_portal_visible','portal_upstream_aggregate_invalid','portal_capability_unknown','portal_capability_handle_malformed','portal_capability_signature_invalid','portal_capability_expired','portal_capability_revoked','portal_capability_superseded','portal_capability_consumed','portal_capability_purpose_mismatch','portal_capability_stale_schedule','portal_capability_expiry_missing','portal_capability_ttl_not_allowed','portal_capability_binding_mismatch','portal_capability_generation_conflict','portal_join_target_not_allowlisted','portal_join_target_unavailable','portal_join_target_not_declared','portal_absence_window_closed','portal_absence_outcome_final','portal_absence_late_evidence','portal_confirmation_required','portal_confirmation_invalid','command_replay_conflict','portal_rate_limited','portal_route_disabled');
    public static function reason(string $reason):string { if(!in_array($reason,self::REASON_CODES,true)) throw new \InvalidArgumentException('portal_vocabulary_member_not_allowed'); return $reason; }
    public static function exceptionReason(string $reason):string { if(!in_array($reason,self::EXCEPTION_REASON_CODES,true)) throw new \InvalidArgumentException('portal_vocabulary_member_not_allowed'); return $reason; }
    public static function operatorReason(string $reason):string { if(!in_array($reason,self::OPERATOR_REASON_CODES,true)) throw new \InvalidArgumentException('portal_vocabulary_member_not_allowed'); return $reason; }
}
