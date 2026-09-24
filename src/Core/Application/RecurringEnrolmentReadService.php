<?php
namespace Delnavazan\Platform\Core\Application;

/** Capability-protected, PII-minimised read seam for the Recurring Enrolment aggregate. */
final class RecurringEnrolmentReadService {
    private const CAPABILITY='dzn_view_recurring_authority';
    public function one(int $id):array{
        RecurringSupport::requireCapability(self::CAPABILITY);
        global $wpdb;$p=$wpdb->prefix.'dzn_';
        $row=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$p}recurring_enrolments WHERE id=%d",$id));
        if(!$row)throw new \InvalidArgumentException('recurring_enrolment_not_found');
        // §7.3: the linked ownership facts are part of the aggregate. The recurring enrolment must sit on
        // a canonical Enrolment of the same Student and Course, and its frozen currency must be one the
        // platform supports — a row that no longer describes its own Enrolment is never returned.
        $enrolment=$wpdb->get_row($wpdb->prepare("SELECT id,student_id,course_id,archived_at FROM {$p}enrolments WHERE id=%d",(int)$row->enrolment_id));
        RecurringSupport::integrity(
            RecurringRule::validState((string)$row->state,RecurringRule::RECURRING_STATES)
            &&in_array((string)$row->collection_mode,RecurringRule::COLLECTION_MODES,true)
            &&RecurringSupport::supportedCurrency((string)$row->currency)
            &&(int)$row->enrolment_id>0
            &&(int)$row->recurring_enrolment_version>0
            &&(string)$row->rule_version===RecurringRule::RULE_VERSION
            &&$enrolment!==null&&$enrolment->archived_at===null
            &&(int)$enrolment->student_id===(int)$row->student_id
            &&(int)$enrolment->course_id===(int)$row->course_id,
            'recurring_enrolment_integrity_conflict'
        );
        // The current row is the projection of its own append-only history: contiguity, legal
        // transitions, the final event and the recorded version must all agree with the row.
        RecurringIntegrity::aggregate('recurring_enrolment',$row,$this->history($id),'recurring_enrolment_integrity_conflict');
        return $this->shape($row);
    }
    public function events(int $id):array{
        RecurringSupport::requireCapability(self::CAPABILITY);
        return array_map(static fn($e)=>array('event_sequence'=>(int)$e->event_sequence,'event_type'=>(string)$e->event_type,'from_state'=>$e->from_state===null?null:(string)$e->from_state,'to_state'=>(string)$e->to_state,'from_collection_mode'=>$e->from_collection_mode===null?null:(string)$e->from_collection_mode,'to_collection_mode'=>(string)$e->to_collection_mode,'occurred_at'=>(string)$e->occurred_at),$this->history($id));
    }
    /** The stored append-only history of the aggregate, in event order. */
    private function history(int $id):array{
        global $wpdb;$p=$wpdb->prefix.'dzn_';
        return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$p}recurring_enrolment_events WHERE recurring_enrolment_id=%d ORDER BY event_sequence",$id))?:array();
    }
    private function shape(object $row):array{
        return array('recurring_enrolment_id'=>(int)$row->id,'enrolment_id'=>(int)$row->enrolment_id,'student_id'=>(int)$row->student_id,'course_id'=>(int)$row->course_id,'currency'=>(string)$row->currency,'region_code'=>(string)$row->region_code,'collection_mode'=>(string)$row->collection_mode,'state'=>(string)$row->state);
    }
}
