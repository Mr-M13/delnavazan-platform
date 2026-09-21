<?php
namespace Delnavazan\Platform\Core\Application;

use Delnavazan\Platform\Core\Infrastructure\Repository\{CanonicalContinuationRepository,CommercialAuthorityRepository,CommercialPaymentRepository};

/**
 * The single canonical validator for the downstream Phase 2A.2-R1 commercial commitment.
 *
 * `CommercialLineageValidator` proves the upstream offer aggregate. That is necessary but not
 * sufficient: the commitment layer created *after* acceptance — the purchase and its bounded
 * entitlement — is what capacity handoff and Term binding actually consume, and following
 * `entitlement.purchase_id → purchase.offer_id → offer` proves only that the *selected offer* is
 * valid. It does not prove that the selected purchase and entitlement still belong to that exact
 * offer and still carry the immutable accepted commitment.
 *
 * The authoritative chain proved here is therefore:
 *
 *   entitlement → purchase → offer → canonical upstream offer lineage
 *
 * and, where a mutation consumes an existing protected-capacity claim, that the claim belongs to the
 * same authoritative context (entitlement, purchase, Student, Teacher, Course, commitment).
 *
 * Every value crossing the boundary is compared row-to-row against the immutable accepted offer (the
 * purchase's accepted snapshot and the entitlement's bounded quantity), so a purchase repointed at
 * another otherwise-valid offer, a re-scoped product or currency, a rewritten accepted amount or plan,
 * a re-pointed beneficiary, a resized entitlement and a foreign capacity claim all fail closed at the
 * mutation owner that would otherwise create capacity or Term truth.
 *
 * The validator is usable from a transaction that already holds locks and never acquires the
 * commercial account root or the Teacher scheduling root, so callers keep their serialization device
 * and the repository lock order.
 */
final class CommercialCommitmentValidator {
    /** Entitlement states that may still hand over capacity: the purchase exists, the Term may not. */
    public const STATES_PRE_CAPACITY=array('issued','term_bound');
    /** Entitlement states that may be bound to a canonical Term: not yet bound. */
    public const STATES_PRE_TERM=array('issued');

    /**
     * Prove the complete commitment chain that owns one entitlement.
     *
     * @param array<int,string> $allowedEntitlementStates states appropriate to the requested mutation
     * @return array{entitlement:object,purchase:object,offer:object}
     */
    public static function assertCommitment(int $entitlementId,array $allowedEntitlementStates,bool $lock=false,?CommercialAuthorityRepository $authority=null,?CanonicalContinuationRepository $continuations=null,?CommercialPaymentRepository $payments=null):array{
        $authority??=new CommercialAuthorityRepository();
        $entitlement=$authority->entitlement($entitlementId,$lock);
        if(!$entitlement)throw new \InvalidArgumentException('commercial_entitlement_required');
        return self::assertForEntitlement($entitlement,$allowedEntitlementStates,$lock,$authority,$continuations,$payments);
    }

    /**
     * Prove the complete commitment chain for an already-hydrated (and, where required, locked)
     * entitlement.
     *
     * @param array<int,string> $allowedEntitlementStates states appropriate to the requested mutation
     * @return array{entitlement:object,purchase:object,offer:object}
     */
    public static function assertForEntitlement(object $entitlement,array $allowedEntitlementStates,bool $lock=false,?CommercialAuthorityRepository $authority=null,?CanonicalContinuationRepository $continuations=null,?CommercialPaymentRepository $payments=null):array{
        $authority??=new CommercialAuthorityRepository();
        $continuations??=new CanonicalContinuationRepository();
        $payments??=new CommercialPaymentRepository();
        $conflict='commercial_commitment_integrity_conflict';
        if(!CommercialValidator::entitlementValid($entitlement))throw new \InvalidArgumentException($conflict);
        if(!in_array((string)$entitlement->state,$allowedEntitlementStates,true))throw new \InvalidArgumentException($conflict);
        // A. entitlement → purchase: the exact purchase this entitlement belongs to.
        $purchaseId=(int)$entitlement->purchase_id;
        if($purchaseId<1)throw new \InvalidArgumentException($conflict);
        $purchase=$authority->purchase($purchaseId,$lock);
        if(!$purchase||(int)$purchase->id!==$purchaseId)throw new \InvalidArgumentException($conflict);
        if(!in_array((string)$purchase->state,CommercialRule::PURCHASE_STATES,true))throw new \InvalidArgumentException($conflict);
        if(!in_array((string)$purchase->reconciliation_state,CommercialRule::RECONCILIATION_STATES,true))throw new \InvalidArgumentException($conflict);
        if(!CommercialValidator::utc((string)$purchase->accepted_at))throw new \InvalidArgumentException($conflict);
        if((int)$purchase->purchase_version<1)throw new \InvalidArgumentException($conflict);
        // B. purchase → offer: the exact accepted offer the purchase was created for.
        $offerId=(int)$purchase->offer_id;
        if($offerId<1)throw new \InvalidArgumentException($conflict);
        $offer=$authority->offer($offerId,$lock);
        if(!$offer||(int)$offer->id!==$offerId)throw new \InvalidArgumentException($conflict);
        // The selected purchase must be that offer's own purchase: one accepted offer can only ever
        // own one purchase, so a purchase repointed at another otherwise-valid offer fails here even
        // when every economic value happens to match.
        $owner=$authority->purchaseByOffer($offerId,$lock);
        if(!$owner||(int)$owner->id!==$purchaseId)throw new \InvalidArgumentException($conflict);
        // Every persisted immutable purchase fact that represents accepted offer truth must still equal
        // that offer. A purchase repointed at another otherwise-valid offer fails here on ownership.
        if((int)$purchase->beneficiary_student_id!==(int)$offer->beneficiary_student_id)throw new \InvalidArgumentException($conflict);
        if((int)$purchase->beneficiary_student_id!==(int)$offer->student_id)throw new \InvalidArgumentException($conflict);
        if((int)$purchase->product_id!==(int)$offer->product_id)throw new \InvalidArgumentException($conflict);
        if((string)$purchase->currency!==(string)$offer->currency)throw new \InvalidArgumentException($conflict);
        if((int)$purchase->amount_minor!==(int)$offer->amount_due_minor)throw new \InvalidArgumentException($conflict);
        if((string)$purchase->plan_kind!==(string)$offer->plan_kind)throw new \InvalidArgumentException($conflict);
        // C. entitlement → offer: proved through the purchase relationship, not assumed transitively.
        if((int)$entitlement->beneficiary_student_id!==(int)$purchase->beneficiary_student_id)throw new \InvalidArgumentException($conflict);
        if((int)$entitlement->beneficiary_student_id!==(int)$offer->beneficiary_student_id)throw new \InvalidArgumentException($conflict);
        if((int)$entitlement->session_count!==(int)$offer->committed_sessions)throw new \InvalidArgumentException($conflict);
        if($entitlement->term_id!==null&&(int)$entitlement->term_id<1)throw new \InvalidArgumentException($conflict);
        // The acceptance evidence that minted this purchase must still belong to this commitment.
        self::assertAcceptanceEvidence($purchase,$offer,$authority,$payments,$lock,$conflict);
        // D. The upstream offer authority: the full canonical lineage, re-proved from the offer.
        CommercialLineageValidator::assertForOffer($offer,$lock,$authority,$continuations);
        return array('entitlement'=>$entitlement,'purchase'=>$purchase,'offer'=>$offer);
    }

    /**
     * Prove an existing protected-capacity claim belongs to the same authoritative commitment.
     *
     * A claim for another entitlement, purchase, Student, Teacher, Course or commitment size may never
     * satisfy this chain, so a corrupt claim selection fails closed instead of silently handing over
     * capacity that belongs to a different purchase.
     */
    public static function assertClaimBelongsToCommitment(object $claim,object $entitlement,object $purchase,object $offer):void{
        $conflict='commercial_capacity_integrity_conflict';
        if($claim->entitlement_id===null||(int)$claim->entitlement_id!==(int)$entitlement->id)throw new \InvalidArgumentException($conflict);
        if($claim->purchase_id===null||(int)$claim->purchase_id!==(int)$purchase->id)throw new \InvalidArgumentException($conflict);
        if((int)$claim->student_id!==(int)$purchase->beneficiary_student_id)throw new \InvalidArgumentException($conflict);
        if((int)$claim->student_id!==(int)$offer->beneficiary_student_id)throw new \InvalidArgumentException($conflict);
        if((int)$claim->teacher_id!==(int)$offer->teacher_id)throw new \InvalidArgumentException($conflict);
        if((int)$claim->course_id!==(int)$offer->course_id)throw new \InvalidArgumentException($conflict);
        if((int)$claim->committed_sessions!==(int)$offer->committed_sessions)throw new \InvalidArgumentException($conflict);
        if($claim->predecessor_reservation_id!==null&&(int)$claim->predecessor_reservation_id!==(int)($offer->reservation_id??0))throw new \InvalidArgumentException($conflict);
    }

    /**
     * The evidence that minted the purchase must still be an accepted evidence row for this exact offer
     * and obligation. (The first acceptance writes its evidence row before the purchase exists, so a
     * null purchase link is legitimate; any other purchase link is corruption.)
     */
    private static function assertAcceptanceEvidence(object $purchase,object $offer,CommercialAuthorityRepository $authority,CommercialPaymentRepository $payments,bool $lock,string $conflict):void{
        $evidenceId=(int)$purchase->first_evidence_id;
        if($evidenceId<1)throw new \InvalidArgumentException($conflict);
        $evidence=$payments->evidence($evidenceId,$lock);
        if(!$evidence||!CommercialValidator::evidenceValid($evidence))throw new \InvalidArgumentException($conflict);
        if((string)$evidence->processing_state!=='accepted')throw new \InvalidArgumentException($conflict);
        if($evidence->offer_id===null||(int)$evidence->offer_id!==(int)$offer->id)throw new \InvalidArgumentException($conflict);
        if($evidence->purchase_id!==null&&(int)$evidence->purchase_id!==(int)$purchase->id)throw new \InvalidArgumentException($conflict);
        if($evidence->obligation_id===null)throw new \InvalidArgumentException($conflict);
        $obligation=$authority->obligation((int)$evidence->obligation_id,$lock);
        if(!$obligation||(int)$obligation->offer_id!==(int)$offer->id)throw new \InvalidArgumentException($conflict);
    }
}
