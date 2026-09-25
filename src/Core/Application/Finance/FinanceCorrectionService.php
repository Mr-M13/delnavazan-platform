<?php
namespace Delnavazan\Platform\Core\Application\Finance;

use Delnavazan\Platform\Core\Application\Finance\Integrity\{FinancePayabilityIntegrity,FinanceSnapshotIntegrity};
use Delnavazan\Platform\Core\Infrastructure\Repository\{FinanceSnapshotRepository,FinanceStatementRepository,TeacherRateRepository};

/**
 * Audited corrections (contract §12).
 *
 * A correction is the only way an already-recorded Finance fact may acquire a different meaning. Every
 * correction is append-only, names its exact target row and that row's digest, and leaves the target
 * untouched: the snapshot keeps its row, the derivation keeps its evaluation and the issued statement
 * keeps its lines. Nothing here is batch-applied — each correction is one capability-gated, evidenced
 * administrator command.
 */
final class FinanceCorrectionService {
    private const CAPABILITY_STATEMENT='dzn_manage_finance_statements';

    public function __construct(
        private ?FinanceSnapshotRepository $snapshots=null,
        private ?TeacherRateRepository $rates=null,
        private ?FinanceStatementRepository $statements=null,
        private ?LessonPayabilityService $payability=null,
        private ?TeacherStatementService $statementService=null
    ){
        $this->snapshots??=new FinanceSnapshotRepository();
        $this->rates??=new TeacherRateRepository();
        $this->statements??=new FinanceStatementRepository();
        $this->payability??=new LessonPayabilityService();
        $this->statementService??=new TeacherStatementService();
    }

    /**
     * §12.2 `snapshot_correction`: append a complete, evidenced correction and make it the effective
     * snapshot for every later derivation, without editing the snapshot it corrects.
     */
    public function correctSnapshot(int $lessonId,array $input,string $rawKey):array{
        FinanceSupport::requireCapability(self::CAPABILITY_STATEMENT);
        $actor=FinanceSupport::actor();$now=FinanceSupport::now();$digest=FinanceSupport::key($rawKey);
        $snapshot=$this->snapshots->byLesson($lessonId);
        if(!$snapshot)throw new FinanceRefusalException('snapshot_missing_for_lesson','A correction names the exact prior snapshot it corrects');
        $teacherId=(int)$snapshot->teacher_id;
        $reason=FinanceSupport::operatorReason($input);
        $payload=FinanceSupport::payload(array('lesson_id'=>$lessonId,'snapshot_id'=>(int)$snapshot->id,'rate_id'=>(int)($input['corrected_rate_id']??0),'version'=>(int)($input['corrected_rate_version']??0),'amount'=>$input['corrected_derived_amount_minor']??null,'currency'=>(string)($input['corrected_currency']??''),'reason'=>$reason,'operation'=>'correct_snapshot'));
        $command=array('command_domain'=>FinanceRule::DOMAIN,'operation'=>'correct_snapshot','command_key_digest'=>$digest,'command_payload_digest'=>$payload,'teacher_id'=>$teacherId,'lesson_id'=>$lessonId,'snapshot_id'=>(int)$snapshot->id,'correction_id'=>null,'result_snapshot_id'=>(int)$snapshot->id,'result_correction_id'=>null,'reason_code'=>$reason,'created_at'=>$now,'created_by'=>$actor);
        $lock=static fn()=>FinanceSupport::lockTeacherRoot($teacherId,$actor);
        return FinanceSupport::runCommand($lock,'finance_snapshot_commands',$command,function()use($lessonId,$snapshot,$input,$actor,$now,$digest,$payload,$reason,&$command){
            if($existing=$this->snapshots->command($digest))return $this->replay($existing,$payload,'correct_snapshot');
            $scope=array('teacher_id'=>(int)$snapshot->teacher_id,'lesson_id'=>$lessonId,'snapshot_id'=>(int)$snapshot->id);
            $rateId=(int)($input['corrected_rate_id']??0);
            $rateVersion=(int)($input['corrected_rate_version']??0);
            $rateAmount=array_key_exists('corrected_rate_amount_minor',$input)?FinanceSupport::amount($input['corrected_rate_amount_minor'],'Exact integer amount required'):-1;
            $currency=array_key_exists('corrected_currency',$input)?strtoupper((string)$input['corrected_currency']):'';
            $derivedAmount=array_key_exists('corrected_derived_amount_minor',$input)?FinanceSupport::amount($input['corrected_derived_amount_minor'],'Exact integer amount required'):-1;
            if($rateId<1||$rateVersion<1||$rateAmount<0||preg_match('/^[A-Z]{3}$/D',$currency)!==1||$derivedAmount<0)throw new FinanceRefusalException('snapshot_correction_incomplete','A correction names its rate row and version, the corrected amount, the currency and the derived amount',$scope);
            $rate=$this->rates->byId($rateId);
            if(!$rate||(int)$rate->rate_version!==$rateVersion)throw new FinanceRefusalException('snapshot_correction_incomplete','The corrected rate row and version must exist',$scope);
            // §12.2: the restated intro pair is the correction's own immutable restatement of the snapshot's.
            $introKey=$input['intro_policy_key']??null;$introVersion=array_key_exists('intro_policy_version',$input)&&$input['intro_policy_version']!==null?(int)$input['intro_policy_version']:null;
            if((string)$snapshot->lesson_kind==='introductory'){if($introKey!=='INTRO_PAYABILITY_POLICY'||$introVersion===null)throw new FinanceRefusalException('snapshot_correction_incomplete','An introductory correction restates both halves of the intro policy pair',$scope);}
            elseif($introKey!==null||$introVersion!==null)throw new FinanceRefusalException('snapshot_correction_incomplete','A non-introductory correction records no intro policy pair',$scope);
            FinanceSnapshotIntegrity::assertDigest($snapshot);
            $proof=FinanceSupport::evidence($input);
            $sequence=$this->snapshots->nextCorrectionSequence((int)$snapshot->id);
            $applicable=$this->snapshots->applicableCorrection((int)$snapshot->id,true);
            if($applicable&&!hash_equals((string)$applicable->derivation_digest,(string)$input['prior_correction_digest']??'')&&array_key_exists('prior_correction_digest',$input))throw new FinanceRefusalException('command_replay_conflict','The named prior correction does not match the applicable one',$scope);
            $values=array('snapshot_id'=>(int)$snapshot->id,'lesson_id'=>$lessonId,'corrected_rate_id'=>$rateId,'corrected_rate_version'=>$rateVersion,'corrected_rate_amount_minor'=>$rateAmount,'corrected_currency'=>$currency,'corrected_derived_amount_minor'=>$derivedAmount,'intro_policy_key'=>$introKey,'intro_policy_version'=>$introVersion,'prior_snapshot_digest'=>(string)$snapshot->derivation_digest);
            $correctionId=$this->snapshots->insertCorrection(array(
                'snapshot_id'=>(int)$snapshot->id,'lesson_id'=>$lessonId,'correction_sequence'=>$sequence,'applicable_slot'=>1,
                'corrected_rate_id'=>$rateId,'corrected_rate_version'=>$rateVersion,'corrected_rate_amount_minor'=>$rateAmount,
                'corrected_currency'=>$currency,'corrected_derived_amount_minor'=>$derivedAmount,
                'intro_policy_key'=>$introKey,'intro_policy_version'=>$introVersion,
                'prior_snapshot_digest'=>(string)$snapshot->derivation_digest,'derivation_digest'=>FinanceSupport::digest(FinanceRule::CORRECTION_DIGEST_FIELDS,$values),
                'reason_code'=>$reason,'note'=>($input['note']??null)===null?null:substr((string)$input['note'],0,190),
                'evidence_channel'=>$proof['channel'],'evidence_reference_digest'=>$proof['digest'],'evidence_at'=>$proof['at'],
                'superseded_at'=>null,'superseded_by_correction_id'=>null,'recorded_at'=>$now,'recorded_by'=>$actor,'created_at'=>$now,'created_by'=>$actor,
            ));
            if($applicable&&$this->snapshots->supersedeCorrection((int)$applicable->id,$correctionId,$now)!==1)throw new FinanceRefusalException('snapshot_correction_incomplete','The previous applicable correction was already replaced',$scope);
            FinanceSupport::audit('finance_snapshot_corrections',$correctionId,'correct_snapshot',$actor,$digest,$reason,$now,null);
            if($applicable)FinanceSupport::audit('finance_snapshot_corrections',(int)$applicable->id,'supersede',$actor,$digest,null,$now,null);
            // §12.2: an asserted rate that does not cover the snapshot instant is admitted only with explicit
            // historical-rate evidence and is reported as an informational difference rather than hidden.
            if((string)$rate->effective_from>(string)$snapshot->snapshot_instant_utc||($rate->effective_until!==null&&(string)$rate->effective_until<=(string)$snapshot->snapshot_instant_utc))FinanceSupport::exception('snapshot_derivation_mismatch','The corrected rate interval does not contain the snapshot instant; historical-rate evidence is recorded',$scope,$actor,$now,null);
            // §12.2: a correction that would change the totals of an issued statement must be paired with its
            // supersession, so a difference can never exist between an issued statement and its authority.
            foreach($this->statements->statementsFor((int)$snapshot->teacher_id) as $statement){
                if((string)$statement->state!=='issued')continue;
                foreach($this->statements->lines((int)$statement->id) as $line)if((int)$line->lesson_id===$lessonId)throw new FinanceRefusalException('statement_supersession_required','A correction that would change an issued statement must be paired with its supersession',array('teacher_id'=>(int)$snapshot->teacher_id,'statement_id'=>(int)$statement->id,'lesson_id'=>$lessonId));
            }
            $command['correction_id']=$correctionId;$command['result_correction_id']=$correctionId;
            $commandId=$this->snapshots->insertCommand($command);
            return array('correction_id'=>$correctionId,'snapshot_id'=>(int)$snapshot->id,'lesson_id'=>$lessonId,'corrected_derived_amount_minor'=>$derivedAmount,'command_id'=>$commandId);
        });
    }

    /** §12.1 `payability_override`: the audited override of §9.3, with the payability capability. */
    public function overridePayability(int $lessonId,string $disposition,string $reasonCode,array $input,string $rawKey):array{
        return $this->payability->override($lessonId,$disposition,$reasonCode,$input,$rawKey);
    }
    /** §12.1 `statement_supersession`: the append-only successor version of §10.6. */
    public function supersedeStatement(int $statementId,array $input,string $rawKey):array{
        return $this->statementService->supersede($statementId,$input,$rawKey);
    }
    /** The effective payability of one Lesson, read through the chain validator. */
    public function effectivePayability(int $lessonId):?object{
        return FinancePayabilityIntegrity::effective($lessonId);
    }
    private function replay(object $row,string $payload,string $operation):array{
        if(!hash_equals((string)$row->command_payload_digest,$payload)||(string)$row->operation!==$operation)throw new FinanceRefusalException('command_replay_conflict','A materially different replay is refused and the original record is preserved');
        if((string)$row->result_state==='refused')throw new FinanceRefusalException((string)$row->reason_code,'A refused command replay converges on its refusal');
        return array('correction_id'=>$row->result_correction_id===null?null:(int)$row->result_correction_id,'snapshot_id'=>$row->result_snapshot_id===null?null:(int)$row->result_snapshot_id,'recorded'=>true,'idempotent'=>true,'command_id'=>(int)$row->id);
    }
}
