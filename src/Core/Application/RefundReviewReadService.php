<?php
namespace Delnavazan\Platform\Core\Application;

/** Capability-protected, PII-minimised read seam for the Refund/Reversal review aggregate. */
final class RefundReviewReadService {
    private const CAPABILITY='dzn_view_recurring_authority';
    public function one(int $id):array{
        RecurringSupport::requireCapability(self::CAPABILITY);
        global $wpdb;$p=$wpdb->prefix.'dzn_';
        $row=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$p}refund_review_cases WHERE id=%d",$id));
        if(!$row)throw new \InvalidArgumentException('refund_review_not_found');
        // The academic consequence is an unresolved product decision: R2 never records it, so a
        // stored value is corruption rather than configuration, and the read fails closed. While it
        // is unset the read model reports the explicit `unresolved` state and no clawback exists.
        RecurringSupport::integrity(
            RecurringRule::validState((string)$row->state,RecurringRule::REFUND_REVIEW_STATES)
            &&in_array((string)$row->kind,RecurringRule::REFUND_REVIEW_KINDS,true)
            &&(int)$row->purchase_id>0
            &&(int)$row->obligation_id>0
            &&(int)$row->evidence_id>0
            &&preg_match('/^\d{1,15}$/D',(string)$row->amount_minor)===1
            &&RecurringSupport::supportedCurrency((string)$row->currency)
            &&(int)$row->refund_review_version>0
            &&$row->academic_consequence===null,
            'refund_review_integrity_conflict'
        );
        return array('refund_review_id'=>(int)$row->id,'purchase_id'=>(int)$row->purchase_id,'obligation_id'=>(int)$row->obligation_id,'evidence_id'=>(int)$row->evidence_id,'kind'=>(string)$row->kind,'amount_minor'=>(int)$row->amount_minor,'currency'=>(string)$row->currency,'state'=>(string)$row->state,'academic_consequence'=>null,'academic_consequence_state'=>'unresolved','resolution_note'=>$row->resolution_note===null?null:(string)$row->resolution_note);
    }
    public function events(int $id):array{
        RecurringSupport::requireCapability(self::CAPABILITY);
        global $wpdb;$p=$wpdb->prefix.'dzn_';
        $rows=$wpdb->get_results($wpdb->prepare("SELECT * FROM {$p}refund_review_events WHERE refund_review_id=%d ORDER BY event_sequence",$id))?:array();
        return array_map(static fn($e)=>array('event_sequence'=>(int)$e->event_sequence,'event_type'=>(string)$e->event_type,'from_state'=>$e->from_state===null?null:(string)$e->from_state,'to_state'=>(string)$e->to_state,'occurred_at'=>(string)$e->occurred_at),$rows);
    }
}
