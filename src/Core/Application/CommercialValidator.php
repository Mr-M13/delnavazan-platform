<?php
namespace Delnavazan\Platform\Core\Application;

/**
 * Fail-closed integrity gate for Phase 2A.2-R1 commercial aggregates.
 *
 * Every consumer (capacity arbitration, funding derivation, Term binding, read seams) hydrates and
 * validates the aggregate through this class before using it, so a corrupted offer, obligation,
 * settlement or capacity claim blocks the consuming authority instead of silently approving a false
 * commercial truth. Nothing here mutates state.
 */
final class CommercialValidator {
    public static function utc(?string $value):bool{
        return is_string($value)&&preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/D',$value)===1&&strtotime($value.' UTC')!==false;
    }
    public static function digest(?string $value):bool{
        return is_string($value)&&preg_match('/^[0-9a-f]{64}$/D',$value)===1;
    }
    private static function key(string $value):bool{
        return preg_match('/^[a-z0-9_]+$/D',$value)===1;
    }

    /**
     * A commercial offer is a coherent immutable pricing snapshot only when its plan decomposition
     * exactly explains the amount due: contiguous ordered obligations covering the whole Term
     * commitment, tranche amounts summing to the amount due, and a discount total that accounts for
     * every recorded adjustment.
     */
    public static function offerValid(object $offer,array $obligations,array $adjustments=array()):bool{
        if((string)$offer->state!=='issued'&&!in_array((string)$offer->state,CommercialRule::OFFER_STATES,true))return false;
        if(!self::key((string)$offer->plan_kind)||(int)$offer->committed_sessions<1)return false;
        $currency=CommercialRule::currency((string)$offer->currency);
        if($currency===null||$currency!==(string)$offer->currency)return false;
        $base=(int)$offer->base_amount_minor;$discount=(int)$offer->discount_total_minor;$due=(int)$offer->amount_due_minor;
        if($base<1||$discount<0||$discount>$base||$due!==$base-$discount)return false;
        $recorded=(int)($offer->promotion_amount_minor??0)+(int)($offer->account_adjustment_amount_minor??0);
        if($recorded!==$discount)return false;
        if((int)($offer->promotion_amount_minor??0)>0&&$offer->promotion_id===null)return false;
        if($offer->promotion_id===null&&(int)($offer->promotion_amount_minor??0)!==0)return false;
        if((int)($offer->account_adjustment_amount_minor??0)>0&&$offer->account_adjustment_id===null)return false;
        if($offer->account_adjustment_id===null&&(int)($offer->account_adjustment_amount_minor??0)!==0)return false;
        if(!self::utc((string)$offer->issued_at)||!self::digest((string)$offer->evidence_reference_digest))return false;
        if((int)$offer->offer_version<1)return false;
        foreach($adjustments as $adjustment){
            $order=(int)$adjustment->application_order;
            if($order<1||(string)$adjustment->currency!==$currency)return false;
            if(!in_array((string)$adjustment->source_type,CommercialRule::ADJUSTMENT_SOURCE_TYPES,true))return false;
            if((int)$adjustment->applied_amount_minor<0||!self::digest((string)$adjustment->snapshot_digest))return false;
            if(!self::utc((string)$adjustment->created_at))return false;
        }
        return self::obligationsValid($obligations,(int)$offer->committed_sessions,(string)$offer->plan_kind,$currency,$due);
    }

    /** Ordered, contiguous, whole-commitment plan decomposition whose tranches sum to the amount due. */
    public static function obligationsValid(array $obligations,int $committedSessions,string $planKind,string $currency,int $amountDue):bool{
        $expected=CommercialRule::obligationSequences($planKind);
        if($expected===null||count($obligations)!==$expected)return false;
        $covered=0;$sum=0;$nextSequence=1;$nextSession=1;
        foreach($obligations as $obligation){
            $sequence=(int)$obligation->obligation_sequence;
            if($sequence!==$nextSequence)return false;
            $nextSequence++;
            $from=(int)$obligation->sessions_from;$to=(int)$obligation->sessions_to;$sessions=(int)$obligation->sessions_covered;
            if($from!==$nextSession||$to<$from||$sessions!==$to-$from+1)return false;
            if($to>$committedSessions)return false;
            if((string)$obligation->currency!==$currency)return false;
            if((int)$obligation->amount_minor<0)return false;
            if(!self::digest((string)$obligation->obligation_reference_digest))return false;
            $nextSession=$to+1;
            $covered+=$sessions;
            $sum+=(int)$obligation->amount_minor;
        }
        if($covered!==$committedSessions)return false;
        return $sum===$amountDue;
    }

    /** Provider-neutral evidence: a digest-only reference, an explicit exact amount/currency pair. */
    public static function evidenceValid(object $evidence):bool{
        if(trim((string)$evidence->provider_key)==='')return false;
        if(!self::digest((string)$evidence->evidence_reference_digest))return false;
        if(!in_array((string)$evidence->evidence_kind,CommercialRule::EVIDENCE_KINDS,true))return false;
        if(!in_array((string)$evidence->processing_state,CommercialRule::EVIDENCE_STATES,true))return false;
        $amount=$evidence->amount_minor;$currency=$evidence->currency;
        if(($amount===null)!==($currency===null))return false;
        if($amount!==null&&(int)$amount<1)return false;
        if($currency!==null&&CommercialRule::currency((string)$currency)!==(string)$currency)return false;
        if($evidence->obligation_reference_digest!==null&&!self::digest((string)$evidence->obligation_reference_digest))return false;
        if(!self::digest((string)($evidence->evidence_fact_digest??'')))return false;
        return self::utc((string)$evidence->ingested_at);
    }

    /**
     * Exact protected-interval ↔ canonical-occupancy identity.
     *
     * A commercial protected interval authorises a Phase-N schedule only when the authoritative
     * occupancy is the same interval: same Teacher, same UTC bounds (including the buffered
     * occupied end), same schedule timezone and same local wall clock. Matching only Term plus
     * canonical session sequence is deliberately insufficient.
     */
    public static function intervalMatchesSchedule(object $interval,array $schedule):bool{
        foreach(array('teacher_id','starts_at_utc','ends_at_utc','occupied_ends_at_utc','schedule_timezone','local_wall_date','local_wall_time') as $field){
            if(!isset($schedule[$field]))return false;
            if((string)$interval->{$field}!==(string)$schedule[$field])return false;
        }
        return true;
    }
    /** Course identity must be continuous across every authority that represents it. */
    public static function courseConsistent(array $courses):bool{
        $expected=null;
        foreach($courses as $courseId){
            if($courseId===null)continue;
            $courseId=(int)$courseId;
            if($courseId<1)return false;
            if($expected===null){$expected=$courseId;continue;}
            if($courseId!==$expected)return false;
        }
        return $expected!==null;
    }
    /** Whether recorded evidence already carries the incoming (nullable) attribution. */
    public static function evidenceAttributionMatches(object $evidence,?object $offer,?object $obligation):bool{
        $recordedOffer=$evidence->offer_id===null?null:(int)$evidence->offer_id;
        $recordedObligation=$evidence->obligation_id===null?null:(int)$evidence->obligation_id;
        $incomingOffer=$offer===null?null:(int)$offer->id;
        $incomingObligation=$obligation===null?null:(int)$obligation->id;
        return $recordedOffer===$incomingOffer&&$recordedObligation===$incomingObligation;
    }

    /** A settlement must be the exact obligation amount in the obligation currency. */
    public static function settlementValid(object $settlement,object $obligation):bool{
        if((int)$settlement->obligation_id!==(int)$obligation->id)return false;
        if((int)$settlement->amount_minor!==(int)$obligation->amount_minor)return false;
        if((string)$settlement->currency!==(string)$obligation->currency)return false;
        return self::utc((string)$settlement->settled_at);
    }

    /** Purchase and its bounded entitlement: one entitlement, one Term binding at most. */
    public static function entitlementValid(object $entitlement):bool{
        if(!in_array((string)$entitlement->state,CommercialRule::ENTITLEMENT_STATES,true))return false;
        if((int)$entitlement->session_count<1)return false;
        if((string)$entitlement->state==='term_bound'&&((int)($entitlement->term_id??0)<1||!self::utc((string)($entitlement->bound_at??null))))return false;
        if((string)$entitlement->state==='issued'&&$entitlement->term_id!==null)return false;
        return (int)$entitlement->entitlement_version>=1;
    }

    /**
     * A protected capacity claim is coherent only when its interval set is contiguous, ordered,
     * arithmetically valid and consistent with its own interval states.
     */
    public static function claimValid(object $claim,array $intervals):bool{
        if(!in_array((string)$claim->state,CommercialRule::CLAIM_STATES,true))return false;
        if(!in_array((string)$claim->source_kind,CommercialRule::CLAIM_SOURCE_KINDS,true))return false;
        $committed=(int)$claim->committed_sessions;
        if($committed<1||(int)$claim->interval_count!==count($intervals)||count($intervals)<1)return false;
        if((string)$claim->state==='released'&&($claim->released_at===null||trim((string)$claim->release_reason_code)===''))return false;
        if((int)$claim->claim_version<1||!self::utc((string)$claim->established_at))return false;
        if(!self::digest((string)$claim->evidence_reference_digest))return false;
        $nextSequence=1;
        foreach($intervals as $interval){
            if((int)$interval->interval_sequence!==$nextSequence)return false;
            $nextSequence++;
            if((int)$interval->teacher_id!==(int)$claim->teacher_id)return false;
            $expected=(int)$interval->expected_session;
            if($expected<1||$expected>$committed)return false;
            $starts=(string)$interval->starts_at_utc;$ends=(string)$interval->ends_at_utc;$occupied=(string)$interval->occupied_ends_at_utc;
            if(!self::utc($starts)||!self::utc($ends)||!self::utc($occupied))return false;
            if(!($starts<$ends&&$ends<=$occupied))return false;
            if((int)$interval->duration_minutes<5||(int)$interval->buffer_minutes<0)return false;
            if(!self::utc((string)$interval->local_wall_date.' 00:00:00'))return false;
            $state=(string)$interval->state;
            if(!in_array($state,CommercialRule::CLAIM_INTERVAL_STATES,true))return false;
            if($state==='satisfied'&&((int)($interval->satisfied_lesson_id??0)<1||(int)($interval->satisfied_schedule_version_id??0)<1||!self::utc((string)($interval->satisfied_at??null))))return false;
            if($state==='protected'&&($interval->satisfied_lesson_id!==null||$interval->satisfied_at!==null))return false;
            if($state==='released'&&!self::utc((string)($interval->released_at??null)))return false;
            if((int)$interval->interval_version<1)return false;
        }
        // An active claim protects at least one interval and never more than the whole commitment:
        // a Regular commitment claims every expected interval, a Flexible commitment claims only the
        // single interval that was explicitly authorised.
        if((string)$claim->state==='active'&&(int)$claim->interval_count>$committed)return false;
        // A non-active claim may never still hold protected capacity: that pair is corruption.
        if((string)$claim->state!=='active')foreach($intervals as $interval)if((string)$interval->state==='protected')return false;
        return true;
    }

    /** Runtime policies are class-B only; a structural invariant may never be stored here. */
    public static function policyValid(object $policy):bool{
        if(!in_array((string)$policy->policy_key,CommercialRule::POLICY_KEYS,true))return false;
        if((int)$policy->policy_version<1)return false;
        if((string)$policy->status!=='active')return false;
        if($policy->value_type!==null&&!in_array((string)$policy->value_type,CommercialRule::POLICY_VALUE_TYPES,true))return false;
        if($policy->policy_value!==null&&trim((string)$policy->policy_value)==='')return false;
        if(!self::utc((string)$policy->recorded_at)||(int)$policy->recorded_by<1)return false;
        return self::digest((string)$policy->evidence_reference_digest);
    }
    /** Money must be an exact non-negative integer minor-unit string; this guards against float drift. */
    public static function minorUnits(mixed $value):?int{
        if(is_int($value))return $value>=0?$value:null;
        if(is_string($value)&&preg_match('/^\d{1,18}$/D',$value)===1)return (int)$value;
        return null;
    }
}
