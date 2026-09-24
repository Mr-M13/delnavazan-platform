<?php
namespace Delnavazan\Platform\Core\Application;

/**
 * Locked Phase 2A.2-R2 recurring-enrolment rule (owner decisions recorded in the R2 contract).
 *
 * R2 is an orchestration and cross-Term authority layer above R1. It records recurring
 * enrolment, next-Term renewal cycles, provider-neutral collection intents, recovery/lapse
 * representation, provider-neutral refund/reversal review and continuous cross-Term protection.
 * It never reimplements R1 pricing, acceptance, funding derivation, protected claims or Term
 * binding, performs no live charge, delivers no notification and invents no unresolved policy.
 */
final class RecurringRule {
    public const RULE_VERSION='recurring_enrolment_v1';
    public const DOMAIN='recurring_v1';
    public const EVIDENCE_CHANNELS=array('staff_record','authenticated_platform','document_reference','provider_evidence');

    /** Recurring Enrolment aggregate states. */
    public const RECURRING_STATES=array('active','suspended','closed');
    public const COLLECTION_MODES=array('manual','automatic');
    public const RECURRING_EVENT_TYPES=array('established','collection_mode_changed','suspended','resumed','closed');

    /** Renewal Cycle states. */
    public const CYCLE_STATES=array('pending','guarantee_protected','payment_required','collected','term_bound','closed','lapsed','cancelled');
    /** The renewal-cycle states that may still carry new cross-Term activity; `lapsed`, `cancelled`
     * and `closed` are terminal and are never reopened by a later recurring record. */
    public const CYCLE_LIVE_STATES=array('pending','guarantee_protected','payment_required','collected','term_bound');
    public const CYCLE_EVENT_TYPES=array('opened','guarantee_protected','payment_required','collected','term_bound','closed','lapsed','cancelled');

    /** Collection Intent states and kinds. */
    public const COLLECTION_INTENT_STATES=array('pending','submitted','confirmed','failed','recovered','cancelled');
    public const COLLECTION_KINDS=array('manual_payment_required','automatic_charge');
    public const COLLECTION_INTENT_EVENT_TYPES=array('opened','submitted','confirmed','failed','recovered','cancelled');

    /** Recovery Case states. */
    public const RECOVERY_STATES=array('open','recovering','recovered','lapsed');
    public const RECOVERY_EVENT_TYPES=array('opened','attempt_recorded','recovered','lapsed');

    /** Refund/Reversal review states and kinds. */
    public const REFUND_REVIEW_STATES=array('open','review_required','resolved','dismissed');
    public const REFUND_REVIEW_KINDS=array('refund','reversal');
    public const REFUND_REVIEW_EVENT_TYPES=array('opened','review_required','resolved','dismissed');

    /** Continuous cross-Term protection states. */
    public const PROTECTION_STATES=array('active','released','lapsed');
    public const PROTECTION_EVENT_TYPES=array('established','extended','released','lapsed');

    /**
     * Channel-neutral notification intents consumed by Phase S. These are intent names only,
     * never templates and never delivery records.
     */
    public const NOTIFICATION_INTENTS=array(
        'AUTOMATIC_RENEWAL_UPCOMING','AUTOMATIC_RENEWAL_CHARGED','AUTOMATIC_RENEWAL_FAILED',
        'MANUAL_RENEWAL_PAYMENT_REQUIRED','GUARANTEE_DEADLINE_APPROACHING','GUARANTEE_EXPIRED',
        'PAYMENT_FAILED','PAYMENT_RECOVERED','TERM_LAPSED','REFUND_REVIEW_REQUIRED','REFUND_RESOLVED',
    );

    /**
     * The manual same-slot guarantee expression is fixed at n weeks; the value is a locked owner
     * decision, not a configurable policy. `MANUAL_RENEWAL_SLOT_GUARANTEE_WEEKS` remains a class-B
     * R1 policy only to bound future review; R2 uses the locked default below when it is unset.
     */
    public const MANUAL_RENEWAL_SLOT_GUARANTEE_WEEKS=4;

    /** A collection confirmation may only follow accepted R1 payment evidence. */
    public const CONFIRM_REQUIRES_ACCEPTED_EVIDENCE=true;

    public static function validState(string $state,array $allowed):bool{return in_array($state,$allowed,true);}
    public static function collectionMode(string $mode):?string{return in_array($mode,self::COLLECTION_MODES,true)?$mode:null;}
    public static function notificationIntent(string $intent):bool{return in_array($intent,self::NOTIFICATION_INTENTS,true);}
}
