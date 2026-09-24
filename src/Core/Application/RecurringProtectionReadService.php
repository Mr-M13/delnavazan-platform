<?php
namespace Delnavazan\Platform\Core\Application;

/** Capability-protected, PII-minimised read seam for the continuous protection aggregate. */
final class RecurringProtectionReadService {
    private const CAPABILITY='dzn_view_recurring_authority';
    public function one(int $id):array{
        RecurringSupport::requireCapability(self::CAPABILITY);
        global $wpdb;$p=$wpdb->prefix.'dzn_';
        $row=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$p}recurring_protections WHERE id=%d",$id));
        if(!$row)throw new \InvalidArgumentException('recurring_protection_not_found');
        RecurringSupport::integrity(
            RecurringRule::validState((string)$row->state,RecurringRule::PROTECTION_STATES)
            &&(int)$row->renewal_cycle_id>0
            &&(int)$row->claim_id>0
            &&(int)$row->recurring_protection_version>0,
            'recurring_protection_integrity_conflict'
        );
        return array('recurring_protection_id'=>(int)$row->id,'renewal_cycle_id'=>(int)$row->renewal_cycle_id,'claim_id'=>(int)$row->claim_id,'state'=>(string)$row->state);
    }
    public function events(int $id):array{
        RecurringSupport::requireCapability(self::CAPABILITY);
        global $wpdb;$p=$wpdb->prefix.'dzn_';
        $rows=$wpdb->get_results($wpdb->prepare("SELECT * FROM {$p}recurring_protection_events WHERE recurring_protection_id=%d ORDER BY event_sequence",$id))?:array();
        return array_map(static fn($e)=>array('event_sequence'=>(int)$e->event_sequence,'event_type'=>(string)$e->event_type,'from_state'=>$e->from_state===null?null:(string)$e->from_state,'to_state'=>(string)$e->to_state,'occurred_at'=>(string)$e->occurred_at),$rows);
    }
}
