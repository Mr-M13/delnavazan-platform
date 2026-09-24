<?php
namespace Delnavazan\Platform\Core\Application;

/** Capability-protected, PII-minimised read seam for the Recovery Case aggregate. */
final class RecoveryReadService {
    private const CAPABILITY='dzn_view_recurring_authority';
    public function one(int $id):array{
        RecurringSupport::requireCapability(self::CAPABILITY);
        global $wpdb;$p=$wpdb->prefix.'dzn_';
        $row=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$p}recovery_cases WHERE id=%d",$id));
        if(!$row)throw new \InvalidArgumentException('recovery_case_not_found');
        // §7.3: a recovery case must still describe its own failed collection of its own cycle — the
        // referenced cycle must belong to the recorded recurring enrolment and the referenced collection
        // intent to that same cycle — so a detached case is malformed rather than authority.
        $cycle=$wpdb->get_row($wpdb->prepare("SELECT id,recurring_enrolment_id FROM {$p}renewal_cycles WHERE id=%d",(int)$row->renewal_cycle_id));
        $intent=$wpdb->get_row($wpdb->prepare("SELECT id,renewal_cycle_id FROM {$p}collection_intents WHERE id=%d",(int)$row->collection_intent_id));
        RecurringSupport::integrity(
            RecurringRule::validState((string)$row->state,RecurringRule::RECOVERY_STATES)
            &&(int)$row->recurring_enrolment_id>0
            &&(int)$row->renewal_cycle_id>0
            &&(int)$row->collection_intent_id>0
            &&(int)$row->recovery_case_version>0
            &&$cycle!==null&&$intent!==null
            &&(int)$cycle->recurring_enrolment_id===(int)$row->recurring_enrolment_id
            &&(int)$intent->renewal_cycle_id===(int)$row->renewal_cycle_id,
            'recovery_case_integrity_conflict'
        );
        RecurringIntegrity::aggregate('recovery_case',$row,$this->history($id),'recovery_case_integrity_conflict');
        return array('recovery_case_id'=>(int)$row->id,'recurring_enrolment_id'=>(int)$row->recurring_enrolment_id,'renewal_cycle_id'=>(int)$row->renewal_cycle_id,'collection_intent_id'=>(int)$row->collection_intent_id,'state'=>(string)$row->state);
    }
    public function events(int $id):array{
        RecurringSupport::requireCapability(self::CAPABILITY);
        return array_map(static fn($e)=>array('event_sequence'=>(int)$e->event_sequence,'event_type'=>(string)$e->event_type,'from_state'=>$e->from_state===null?null:(string)$e->from_state,'to_state'=>(string)$e->to_state,'occurred_at'=>(string)$e->occurred_at),$this->history($id));
    }
    /** The stored append-only history of the aggregate, in event order. */
    private function history(int $id):array{
        global $wpdb;$p=$wpdb->prefix.'dzn_';
        return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$p}recovery_case_events WHERE recovery_case_id=%d ORDER BY event_sequence",$id))?:array();
    }
}
