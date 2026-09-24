<?php
namespace Delnavazan\Platform\Core\Application;

/** Capability-protected, PII-minimised read seam for the Collection Intent aggregate. */
final class CollectionReadService {
    private const CAPABILITY='dzn_view_recurring_authority';
    public function one(int $id):array{
        RecurringSupport::requireCapability(self::CAPABILITY);
        global $wpdb;$p=$wpdb->prefix.'dzn_';
        $row=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$p}collection_intents WHERE id=%d",$id));
        if(!$row)throw new \InvalidArgumentException('collection_intent_not_found');
        RecurringSupport::integrity(
            RecurringRule::validState((string)$row->state,RecurringRule::COLLECTION_INTENT_STATES)
            &&in_array((string)$row->kind,RecurringRule::COLLECTION_KINDS,true)
            &&(int)$row->renewal_cycle_id>0
            &&(int)$row->obligation_id>0
            &&(int)$row->collection_intent_version>0
            &&RecurringSupport::utcOrNull($row->charge_at)
            &&RecurringSupport::controlledReasonOrNull($row->failure_reason_code),
            'collection_intent_integrity_conflict'
        );
        return array('collection_intent_id'=>(int)$row->id,'renewal_cycle_id'=>(int)$row->renewal_cycle_id,'obligation_id'=>(int)$row->obligation_id,'kind'=>(string)$row->kind,'state'=>(string)$row->state,'charge_at'=>$row->charge_at===null?null:(string)$row->charge_at,'failure_reason_code'=>$row->failure_reason_code===null?null:(string)$row->failure_reason_code);
    }
    public function events(int $id):array{
        RecurringSupport::requireCapability(self::CAPABILITY);
        global $wpdb;$p=$wpdb->prefix.'dzn_';
        $rows=$wpdb->get_results($wpdb->prepare("SELECT * FROM {$p}collection_intent_events WHERE collection_intent_id=%d ORDER BY event_sequence",$id))?:array();
        return array_map(static fn($e)=>array('event_sequence'=>(int)$e->event_sequence,'event_type'=>(string)$e->event_type,'from_state'=>$e->from_state===null?null:(string)$e->from_state,'to_state'=>(string)$e->to_state,'occurred_at'=>(string)$e->occurred_at),$rows);
    }
}
