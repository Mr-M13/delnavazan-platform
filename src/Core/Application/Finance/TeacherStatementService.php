<?php
namespace Delnavazan\Platform\Core\Application\Finance;

use Delnavazan\Platform\Core\Application\Finance\Integrity\{FinancePayabilityIntegrity,FinanceSnapshotIntegrity,FinanceStatementIntegrity};
use Delnavazan\Platform\Core\Infrastructure\Repository\{FinancePayabilityRepository,FinanceSnapshotRepository,FinanceStatementRepository};
use Delnavazan\Platform\Core\Support\Identifier;

/**
 * Teacher compensation statement authority (contract §10).
 *
 * A statement is identified by Teacher + half-open period + version, its lines are immutable from the
 * moment they are written, its totals are recomputed and compared exactly, and its issuance gate is
 * fail-closed. `draft`/`issue` hold the global policy serialisation root and then the Teacher root in the
 * fixed order of §15.2, so a policy mutation and a draft are totally ordered and a draft can never
 * record a mixture of two policy states.
 */
final class TeacherStatementService {
    private const CAPABILITY='dzn_manage_finance_statements';

    public function __construct(
        private ?FinanceStatementRepository $statements=null,
        private ?FinanceSnapshotRepository $snapshots=null,
        private ?FinancePayabilityRepository $evaluations=null,
        private ?FinanceFacts $facts=null,
        private ?FinancePolicyService $policies=null
    ){
        $this->statements??=new FinanceStatementRepository();
        $this->snapshots??=new FinanceSnapshotRepository();
        $this->evaluations??=new FinancePayabilityRepository();
        $this->facts??=new FinanceFacts();
        $this->policies??=new FinancePolicyService();
    }

    /** §10.2 `draft`: one immutable line per Lesson, recorded totals and the timezone triple. */
    public function draft(int $teacherId,string $periodStartUtc,string $periodEndUtc,string $rawKey):array{
        FinanceSupport::requireCapability(self::CAPABILITY);
        $actor=FinanceSupport::actor();$now=FinanceSupport::now();$digest=FinanceSupport::key($rawKey);
        $payload=FinanceSupport::payload(array('teacher_id'=>$teacherId,'start'=>$periodStartUtc,'end'=>$periodEndUtc,'operation'=>'draft'));
        $command=array('command_domain'=>FinanceRule::DOMAIN,'operation'=>'draft','command_key_digest'=>$digest,'command_payload_digest'=>$payload,'teacher_id'=>$teacherId,'statement_id'=>null,'result_statement_id'=>null,'reason_code'=>null,'created_at'=>$now,'created_by'=>$actor);
        $lock=static fn()=>FinanceSupport::lockPolicyRootThenTeacher($teacherId,$actor);
        return FinanceSupport::runCommand($lock,'finance_statement_commands',$command,function()use($teacherId,$periodStartUtc,$periodEndUtc,$actor,$now,$digest,$payload,&$command){
            if($existing=$this->statements->command($digest))return $this->replay($existing,$payload,'draft');
            if(!FinanceRule::periodValid($periodStartUtc,$periodEndUtc))throw new FinanceRefusalException('statement_period_too_long','A statement period is a half-open UTC range of at most '.FinanceRule::MAX_STATEMENT_PERIOD_DAYS.' days',array('teacher_id'=>$teacherId));
            $built=$this->buildDraft($teacherId,$periodStartUtc,$periodEndUtc,null,$actor,$now,$digest);
            $command['statement_id']=$built['statement_id'];$command['result_statement_id']=$built['statement_id'];
            $commandId=$this->statements->insertCommand($command);
            return $built+array('command_id'=>$commandId);
        });
    }

    /** §10.3: recompute and compare every total and the derivation digest from the stored lines. */
    public function assertTotals(int $statementId):array{
        FinanceSupport::requireCapability(self::CAPABILITY);
        return FinanceStatementIntegrity::assertTotals($statementId,$this->statements,$this->snapshots,$this->evaluations);
    }

    /** §10.5 `issue`: the fail-closed issuance gate, then the one conditional `draft → issued` move. */
    public function issue(int $statementId,string $rawKey):array{
        FinanceSupport::requireCapability(self::CAPABILITY);
        $actor=FinanceSupport::actor();$now=FinanceSupport::now();$digest=FinanceSupport::key($rawKey);
        $hint=$this->statements->byId($statementId);
        if(!$hint)throw new FinanceRefusalException('finance_parent_not_live','The statement does not exist');
        $teacherId=(int)$hint->teacher_id;
        $payload=FinanceSupport::payload(array('statement_id'=>$statementId,'operation'=>'issue'));
        $command=array('command_domain'=>FinanceRule::DOMAIN,'operation'=>'issue','command_key_digest'=>$digest,'command_payload_digest'=>$payload,'teacher_id'=>$teacherId,'statement_id'=>$statementId,'result_statement_id'=>$statementId,'reason_code'=>null,'created_at'=>$now,'created_by'=>$actor);
        $lock=static fn()=>FinanceSupport::lockPolicyRootThenTeacher($teacherId,$actor);
        return FinanceSupport::runCommand($lock,'finance_statement_commands',$command,function()use($statementId,$actor,$now,$payload,$digest,&$command){
            if($existing=$this->statements->command($digest))return $this->replay($existing,$payload,'issue');
            $statement=$this->statements->byId($statementId,true);
            if(!$statement)throw new FinanceRefusalException('finance_parent_not_live','The statement does not exist');
            $scope=array('teacher_id'=>(int)$statement->teacher_id,'statement_id'=>$statementId);
            // Gate 1–2: the statement is a draft and its recorded totals still equal a fresh derivation.
            if((string)$statement->state!=='draft')throw new FinanceRefusalException('statement_state_transition_conflict','Only a draft statement can be issued (current state: '.(string)$statement->state.')',$scope);
            FinanceStatementIntegrity::assertTotals($statementId,$this->statements,$this->snapshots,$this->evaluations);
            // Gate 3: the period has elapsed.
            if((string)$statement->period_end_utc>$now)throw new FinanceRefusalException('statement_period_not_elapsed','A statement covers an interval that has already happened',$scope);
            // Gate 8: the recorded timezone triple is fully recorded and still in force.
            $triple=FinanceStatementIntegrity::assertTimezoneTriple($statement);
            if($triple==='unset')throw new FinanceRefusalException('policy_unset_for_statement_period','The statement timezone policy was not recorded at drafting time',$scope);
            $recorded=$this->policies->versionRow('FINANCE_STATEMENT_TIMEZONE',(int)$statement->timezone_policy_version);
            if(!$recorded||(string)$recorded->policy_value!==(string)$statement->period_timezone||!in_array((string)$recorded->status,array('active','superseded'),true))throw new FinanceRefusalException('policy_unset_for_statement_period','The recorded timezone version was retracted or no longer resolves',$scope);
            // Gate 4–6: every non-archived member of the period's set is finalised, snapshotted and stated.
            $set=$this->periodLessons((int)$statement->teacher_id,(string)$statement->period_start_utc,(string)$statement->period_end_utc);
            $lines=$this->statements->lines($statementId,true);
            $stated=array();
            foreach($lines as $line)$stated[(int)$line->lesson_id]=true;
            if((int)$statement->excluded_archived_count!==count($set['archived']))throw new FinanceRefusalException('statement_derivation_mismatch','The recorded archive exclusion count no longer matches the period',$scope);
            foreach(array_keys($set['active']) as $lessonId){
                if(!isset($stated[$lessonId]))throw new FinanceRefusalException('snapshot_missing_for_lesson','Every Lesson of the period must be stated or counted as an archive exclusion',$scope);
                $this->assertLineFresh($lessonId,$scope);
            }
            // Gate 5: no pending line.
            if((int)$statement->pending_line_count>0)throw new FinanceRefusalException('payability_pending','A pending payability blocks statement issuance',$scope);
            // Gate 9: no open blocking exception for this Teacher and period.
            foreach($this->statements->blockingExceptions((int)$statement->teacher_id,(string)$statement->period_start_utc,(string)$statement->period_end_utc) as $exception)throw new FinanceRefusalException((string)$exception->reason_code,'An open blocking exception blocks statement issuance',$scope);
            // Gates 10–11: no other live statement overlaps the period or states one of its Lessons.
            if($this->statements->liveInPeriod((int)$statement->teacher_id,(string)$statement->period_start_utc,(string)$statement->period_end_utc,$statementId,true)!==array())throw new FinanceRefusalException('statement_period_overlap','Two live statements of one Teacher may not overlap in time',$scope);
            if($this->statements->statedElsewhere((int)$statement->teacher_id,array_keys($stated),$statementId)!==array())throw new FinanceRefusalException('lesson_stated_twice','A live statement of this Teacher already states one of these Lessons',$scope);
            if($this->statements->issue($statementId,$actor,$now)!==1)throw new FinanceRefusalException('statement_state_transition_conflict','The issuance lost its compare-and-swap against the current state',$scope);
            FinanceSupport::audit('finance_statements',$statementId,'issue',$actor,$digest,null,$now,null);
            $this->statements->insertEvent(array('statement_id'=>$statementId,'event_sequence'=>$this->statements->nextEventSequence($statementId),'event_type'=>'issued','from_state'=>'draft','to_state'=>'issued','superseded_by_statement_id'=>null,'reason_code'=>'operator_decision','evidence_channel'=>'authenticated_platform','evidence_reference_digest'=>FinanceIdempotency::evidence('finance-issue-'.$digest),'evidence_at'=>$now,'occurred_at'=>$now,'recorded_at'=>$now,'recorded_by'=>$actor,'created_at'=>$now,'created_by'=>$actor));
            FinanceSupport::outboxIntent('finance_statements',$statementId,'TEACHER_STATEMENT_ISSUED',$now);
            $commandId=$this->statements->insertCommand($command);
            return array('statement_id'=>$statementId,'state'=>'issued','issued_at'=>$now,'issued_by'=>$actor,'command_id'=>$commandId);
        });
    }

    /** §10.6: the terminal `draft → withdrawn` move; lines are retained and never re-used. */
    public function withdraw(int $statementId,array $input,string $rawKey):array{
        FinanceSupport::requireCapability(self::CAPABILITY);
        $actor=FinanceSupport::actor();$now=FinanceSupport::now();$digest=FinanceSupport::key($rawKey);
        $hint=$this->statements->byId($statementId);
        if(!$hint)throw new FinanceRefusalException('finance_parent_not_live','The statement does not exist');
        $teacherId=(int)$hint->teacher_id;
        $reason=FinanceSupport::operatorReason($input);
        $payload=FinanceSupport::payload(array('statement_id'=>$statementId,'reason'=>$reason,'operation'=>'withdraw'));
        $command=array('command_domain'=>FinanceRule::DOMAIN,'operation'=>'withdraw','command_key_digest'=>$digest,'command_payload_digest'=>$payload,'teacher_id'=>$teacherId,'statement_id'=>$statementId,'result_statement_id'=>$statementId,'reason_code'=>$reason,'created_at'=>$now,'created_by'=>$actor);
        $lock=static fn()=>FinanceSupport::lockTeacherRoot($teacherId,$actor);
        return FinanceSupport::runCommand($lock,'finance_statement_commands',$command,function()use($statementId,$actor,$now,$digest,$payload,$reason,&$command){
            if($existing=$this->statements->command($digest))return $this->replay($existing,$payload,'withdraw');
            $statement=$this->statements->byId($statementId,true);
            if(!$statement)throw new FinanceRefusalException('finance_parent_not_live','The statement does not exist');
            $scope=array('teacher_id'=>(int)$statement->teacher_id,'statement_id'=>$statementId);
            if((string)$statement->state!=='draft')throw new FinanceRefusalException('statement_state_transition_conflict','Only a draft statement can be withdrawn (current state: '.(string)$statement->state.')',$scope);
            $proof=FinanceSupport::evidence($input);
            if($this->statements->withdraw($statementId,$actor,$now)!==1)throw new FinanceRefusalException('statement_state_transition_conflict','The withdrawal lost its compare-and-swap against the current state',$scope);
            $this->statements->insertEvent(array('statement_id'=>$statementId,'event_sequence'=>$this->statements->nextEventSequence($statementId),'event_type'=>'withdrawn','from_state'=>'draft','to_state'=>'withdrawn','superseded_by_statement_id'=>null,'reason_code'=>$reason,'evidence_channel'=>$proof['channel'],'evidence_reference_digest'=>$proof['digest'],'evidence_at'=>$proof['at'],'occurred_at'=>$now,'recorded_at'=>$now,'recorded_by'=>$actor,'created_at'=>$now,'created_by'=>$actor));
            FinanceSupport::audit('finance_statements',$statementId,'withdraw',$actor,$digest,$reason,$now,null);
            $commandId=$this->statements->insertCommand($command);
            return array('statement_id'=>$statementId,'state'=>'withdrawn','command_id'=>$commandId);
        });
    }

    /** §10.6 `supersede`: a new draft version plus one conditional `issued → superseded` move. */
    public function supersede(int $statementId,array $input,string $rawKey):array{
        FinanceSupport::requireCapability(self::CAPABILITY);
        $actor=FinanceSupport::actor();$now=FinanceSupport::now();$digest=FinanceSupport::key($rawKey);
        $hint=$this->statements->byId($statementId);
        if(!$hint)throw new FinanceRefusalException('finance_parent_not_live','The statement does not exist');
        $teacherId=(int)$hint->teacher_id;
        $reason=FinanceSupport::operatorReason($input);
        $payload=FinanceSupport::payload(array('statement_id'=>$statementId,'reason'=>$reason,'operation'=>'supersede'));
        $command=array('command_domain'=>FinanceRule::DOMAIN,'operation'=>'supersede','command_key_digest'=>$digest,'command_payload_digest'=>$payload,'teacher_id'=>$teacherId,'statement_id'=>$statementId,'result_statement_id'=>null,'reason_code'=>$reason,'created_at'=>$now,'created_by'=>$actor);
        $lock=static fn()=>FinanceSupport::lockPolicyRootThenTeacher($teacherId,$actor);
        return FinanceSupport::runCommand($lock,'finance_statement_commands',$command,function()use($statementId,$actor,$now,$digest,$payload,$reason,&$command){
            if($existing=$this->statements->command($digest))return $this->replay($existing,$payload,'supersede');
            $predecessor=$this->statements->byId($statementId,true);
            if(!$predecessor)throw new FinanceRefusalException('finance_parent_not_live','The statement does not exist');
            $scope=array('teacher_id'=>(int)$predecessor->teacher_id,'statement_id'=>$statementId);
            if((string)$predecessor->state!=='issued')throw new FinanceRefusalException('statement_state_transition_conflict','Only an issued statement can be superseded (current state: '.(string)$predecessor->state.')',$scope);
            $built=$this->buildDraft((int)$predecessor->teacher_id,(string)$predecessor->period_start_utc,(string)$predecessor->period_end_utc,$statementId,$actor,$now,$digest);
            if($this->statements->supersede($statementId,(int)$built['statement_id'],$actor,$now)!==1)throw new FinanceRefusalException('statement_state_transition_conflict','The supersession lost its compare-and-swap against the current state',$scope);
            $this->statements->insertEvent(array('statement_id'=>$statementId,'event_sequence'=>$this->statements->nextEventSequence($statementId),'event_type'=>'superseded','from_state'=>'issued','to_state'=>'superseded','superseded_by_statement_id'=>(int)$built['statement_id'],'reason_code'=>$reason,'evidence_channel'=>'authenticated_platform','evidence_reference_digest'=>FinanceIdempotency::evidence('finance-supersede-'.$digest),'evidence_at'=>$now,'occurred_at'=>$now,'recorded_at'=>$now,'recorded_by'=>$actor,'created_at'=>$now,'created_by'=>$actor));
            FinanceSupport::audit('finance_statements',$statementId,'supersede',$actor,$digest,$reason,$now,null);
            FinanceSupport::outboxIntent('finance_statements',$statementId,'TEACHER_STATEMENT_SUPERSEDED',$now);
            $command['result_statement_id']=(int)$built['statement_id'];
            $commandId=$this->statements->insertCommand($command);
            return $built+array('superseded_statement_id'=>$statementId,'command_id'=>$commandId);
        });
    }

    /**
     * §10.2/§10.6: build one draft statement for a period inside the caller's transaction.
     *
     * The period's finance-relevant Lesson set comes from canonical occurrences; archived members are
     * excluded with a counted exclusion; every remaining member must be finalised and snapshotted, and one
     * immutable line is appended per Lesson in the deterministic order (`occurrence_starts_at_utc`, then
     * `lesson_id`).
     */
    private function buildDraft(int $teacherId,string $startUtc,string $endUtc,?int $predecessorId,int $actor,string $now,string $commandDigest):array{
        $this->facts->teacher($teacherId,true);
        if(!FinanceRule::periodValid($startUtc,$endUtc))throw new FinanceRefusalException('statement_period_too_long','A statement period is a half-open UTC range of at most '.FinanceRule::MAX_STATEMENT_PERIOD_DAYS.' days',array('teacher_id'=>$teacherId));
        if($endUtc>$now)throw new FinanceRefusalException('statement_period_not_elapsed','A statement covers an interval that has already happened',array('teacher_id'=>$teacherId));
        if($this->statements->liveInPeriod($teacherId,$startUtc,$endUtc,$predecessorId??0,true)!==array())throw new FinanceRefusalException('statement_period_overlap','Two live statements of one Teacher may not overlap in time',array('teacher_id'=>$teacherId));
        $timezone=$this->policies->resolve(FinanceRule::UNSET_POLICY_KEY,$now);
        $periodZone=$timezone['set']?(string)$timezone['value']:null;
        $periodLabel=$periodZone===null?null:$this->renderPeriodLabel($startUtc,$endUtc,$periodZone);
        $timezoneVersion=$timezone['set']?(int)$timezone['version']:null;
        $set=$this->periodLessons($teacherId,$startUtc,$endUtc);
        $lines=array();
        foreach($set['active'] as $lessonId=>$context){
            if(!$context['finalised'])throw new FinanceRefusalException('lesson_not_finalised_in_period','A Lesson of the period is not finalised',array('teacher_id'=>$teacherId,'lesson_id'=>(int)$lessonId));
            $effective=FinanceSnapshotIntegrity::effective($lessonId,$this->snapshots);
            $evaluation=FinancePayabilityIntegrity::effective($lessonId,$this->evaluations,$this->snapshots);
            if(!$evaluation)throw new FinanceRefusalException('payability_pending','A Lesson of the period has no effective payability evaluation yet',array('teacher_id'=>$teacherId,'lesson_id'=>$lessonId));
            $disposition=(string)$evaluation->disposition;
            $lineAmount=$disposition==='payable'?$effective['amount_minor']:0;
            $lineValues=array('lesson_id'=>$lessonId,'disposition'=>$disposition,'basis_code'=>(string)$evaluation->basis_code,'snapshot_id'=>(int)$effective['snapshot']->id,'snapshot_correction_id'=>$effective['correction']===null?null:(int)$effective['correction']->id,'payability_evaluation_id'=>(int)$evaluation->id,'rate_id'=>$effective['rate_id'],'rate_version'=>$effective['rate_version'],'line_amount_minor'=>$lineAmount,'currency'=>(string)$effective['currency']);
            $lines[]=array('values'=>$lineValues,'digest'=>FinanceStatementIntegrity::lineDigest((object)array_merge($lineValues,array())),'context'=>$context,'effective'=>$effective,'evaluation'=>$evaluation,'amount'=>$lineAmount,'disposition'=>$disposition);
        }
        usort($lines,static function(array $a,array $b):int{
            $left=(string)$a['context']['occurrence_starts_at_utc'];$right=(string)$b['context']['occurrence_starts_at_utc'];
            return $left===$right?((int)$a['values']['lesson_id']<=>(int)$b['values']['lesson_id']):strcmp($left,$right);
        });
        $currencies=array();
        foreach($lines as $index=>$line){
            $currencies[(string)$line['values']['currency']]=true;
            $lines[$index]['values']['currency']=(string)$line['values']['currency'];
        }
        if(count($currencies)>1)throw new FinanceRefusalException('currency_mismatch_for_statement','A statement is single-currency; no conversion exists in this phase',array('teacher_id'=>$teacherId));
        if($currencies===array())throw new FinanceRefusalException('statement_derivation_mismatch','A period with no finance-relevant Lesson carries no derivable statement currency',array('teacher_id'=>$teacherId));
        $currency=(string)array_key_first($currencies);
        $payableAmount=0;$pending=0;$nonPayable=0;$payable=0;
        foreach($lines as $index=>$line){
            $line['values']['line_sequence']=$index+1;
            if($line['disposition']==='payable'){$payable++;$payableAmount+=(int)$line['amount'];}
            elseif($line['disposition']==='pending')$pending++;
            else $nonPayable++;
            $lines[$index]['values']=$line['values'];
        }
        $totals=array('total_line_count'=>count($lines),'payable_line_count'=>$payable,'payable_amount_minor'=>$payableAmount,'non_payable_line_count'=>$nonPayable,'pending_line_count'=>$pending,'excluded_archived_count'=>count($set['archived']));
        $version=$this->statements->nextVersion($teacherId,$startUtc,$endUtc);
        $statementRow=array(
            'uid'=>Identifier::uid(),'reference_code'=>null,'teacher_id'=>$teacherId,'period_start_utc'=>$startUtc,'period_end_utc'=>$endUtc,
            'period_timezone'=>$periodZone,'period_label'=>$periodLabel,'currency'=>$currency,'state'=>'draft','statement_version'=>$version,
            'superseded_statement_id'=>$predecessorId,'superseded_at'=>null,'superseded_by_statement_id'=>null,
            'timezone_policy_version'=>$timezoneVersion,'rule_version'=>FinanceRule::STATEMENT_RULE_VERSION,
            'total_line_count'=>$totals['total_line_count'],'payable_line_count'=>$payable,'payable_amount_minor'=>$payableAmount,
            'non_payable_line_count'=>$nonPayable,'pending_line_count'=>$pending,'excluded_archived_count'=>$totals['excluded_archived_count'],
            'issued_at'=>null,'issued_by'=>null,'created_at'=>$now,'created_by'=>$actor,'updated_at'=>$now,'updated_by'=>$actor,
        );
        $statementRow['derivation_digest']=$this->statementDigest($statementRow,$lines);
        $statementId=$this->statements->insertStatement($statementRow);
        $this->statements->assignPublicHandle($statementId);
        FinanceSupport::audit('finance_statements',$statementId,$predecessorId===null?'draft':'supersede',$actor,$commandDigest,null,$now,null);
        foreach($lines as $line){
            $lineId=$this->statements->insertLine(array(
                'statement_id'=>$statementId,'line_sequence'=>(int)$line['values']['line_sequence'],'lesson_id'=>(int)$line['values']['lesson_id'],
                'snapshot_id'=>(int)$line['values']['snapshot_id'],'snapshot_correction_id'=>$line['values']['snapshot_correction_id'],
                'payability_evaluation_id'=>(int)$line['values']['payability_evaluation_id'],'teacher_id'=>(int)$line['context']['teacher_id'],
                'course_id'=>(int)$line['context']['course_id'],'lesson_kind'=>(string)$line['context']['kind'],
                'occurrence_starts_at_utc'=>(string)$line['context']['occurrence_starts_at_utc'],'occurrence_ends_at_utc'=>(string)$line['context']['occurrence_ends_at_utc'],
                'duration_minutes'=>(int)$line['context']['duration_minutes'],
                'delivery_state'=>$line['evaluation']->delivery_state===null?null:(string)$line['evaluation']->delivery_state,
                'attendance_state'=>$line['evaluation']->attendance_state===null?null:(string)$line['evaluation']->attendance_state,
                'remedy_class'=>$line['evaluation']->remedy_class===null?null:(string)$line['evaluation']->remedy_class,
                'disposition'=>$line['disposition'],'basis_code'=>(string)$line['evaluation']->basis_code,
                'rate_id'=>(int)$line['values']['rate_id'],'rate_version'=>(int)$line['values']['rate_version'],
                'compensation_basis'=>(string)$line['effective']['compensation_basis'],'rate_amount_minor'=>(int)$line['effective']['snapshot']->rate_amount_minor,
                'currency'=>(string)$line['values']['currency'],'line_amount_minor'=>(int)$line['amount'],
                'derivation_digest'=>(string)$line['digest'],'created_at'=>$now,'created_by'=>$actor,
            ));
            FinanceSupport::audit('finance_statement_lines',$lineId,'drafted',$actor,$commandDigest,null,$now,null);
        }
        $this->statements->insertEvent(array('statement_id'=>$statementId,'event_sequence'=>1,'event_type'=>'drafted','from_state'=>null,'to_state'=>'draft','superseded_by_statement_id'=>null,'reason_code'=>'operator_decision','evidence_channel'=>'authenticated_platform','evidence_reference_digest'=>FinanceIdempotency::evidence('finance-draft-'.$commandDigest),'evidence_at'=>$now,'occurred_at'=>$now,'recorded_at'=>$now,'recorded_by'=>$actor,'created_at'=>$now,'created_by'=>$actor));
        return array('statement_id'=>$statementId,'teacher_id'=>$teacherId,'statement_version'=>$version,'currency'=>$currency,'totals'=>$totals,'timezone_recorded'=>$periodZone!==null,'state'=>'draft');
    }

    /** The statement's derivation digest over its recorded period, currency, version and ordered lines. */
    private function statementDigest(array $statementRow,array $lines):string{
        $ordered=array();
        foreach($lines as $line)$ordered[]=array('line_sequence'=>(int)$line['values']['line_sequence'],'lesson_id'=>(int)$line['values']['lesson_id'],'line_digest'=>(string)$line['digest']);
        return FinanceSupport::digest(array('period_start_utc','period_end_utc','currency','statement_version','rule_version','line_count','payable_amount_minor','excluded_archived_count','lines'),array(
            'period_start_utc'=>(string)$statementRow['period_start_utc'],'period_end_utc'=>(string)$statementRow['period_end_utc'],
            'currency'=>(string)$statementRow['currency'],'statement_version'=>(int)$statementRow['statement_version'],
            'rule_version'=>(string)$statementRow['rule_version'],'line_count'=>(int)$statementRow['total_line_count'],
            'payable_amount_minor'=>(int)$statementRow['payable_amount_minor'],'excluded_archived_count'=>(int)$statementRow['excluded_archived_count'],
            'lines'=>$ordered,
        ));
    }
    /** The period's finance-relevant Lesson set, split into the stated set and the counted archive exclusions. */
    public function periodLessons(int $teacherId,string $startUtc,string $endUtc):array{
        $active=array();$archived=array();
        foreach($this->statements->candidateLessons($teacherId) as $candidate){
            $anchor=$candidate->outcome_starts??($candidate->canonical_starts??$candidate->legacy_starts);
            if($anchor===null||(string)$anchor<$startUtc||(string)$anchor>=$endUtc)continue;
            $lessonId=(int)$candidate->lesson_id;
            // §10.4: an archived member is excluded from a *new* draft and counted, never silently removed —
            // and an archived, never-captured Lesson is simply counted out, so it is not even hydrated.
            if($candidate->archived_at!==null){$archived[]=$lessonId;continue;}
            $context=$this->hydrate($lessonId);
            if($context['archived'])$archived[]=$lessonId;else $active[$lessonId]=$context;
        }
        ksort($active,SORT_NUMERIC);sort($archived,SORT_NUMERIC);
        return array('active'=>$active,'archived'=>$archived);
    }
    /** Hydrate one Lesson context and require it to be finalised (a drafting/issuance requirement). */
    private function contextOf(int $lessonId):array{
        $context=$this->hydrate($lessonId);
        if(!$context['finalised'])throw new FinanceRefusalException('lesson_not_finalised_in_period','A Lesson of the period is not finalised',array('teacher_id'=>(int)$context['teacher_id'],'lesson_id'=>$lessonId));
        return $context;
    }
    /** Hydrate one Lesson context, converting a fail-closed upstream defect into its durable refusal. */
    private function hydrate(int $lessonId):array{
        try{
            $context=$this->facts->context($lessonId,true);
        }catch(\Throwable$e){
            throw FinanceSupport::refusalFrom($e);
        }
        return $context;
    }
    /** §10.5 rule 6: a stated line's effective snapshot must re-verify and its amount must be reproducible. */
    private function assertLineFresh(int $lessonId,array $scope):void{
        $effective=FinanceSnapshotIntegrity::effective($lessonId,$this->snapshots);
        $evaluation=FinancePayabilityIntegrity::effective($lessonId,$this->evaluations,$this->snapshots);
        if(!$evaluation)throw new FinanceRefusalException('payability_pending','A Lesson of the period has no effective payability evaluation',$scope);
        if((string)$evaluation->disposition==='pending')throw new FinanceRefusalException('payability_pending','A pending payability blocks statement issuance',$scope);
        if($effective['amount_minor']<0)throw new FinanceRefusalException('finance_amount_not_exact','A statement line amount must be an exact integer',$scope);
    }
    /** §10.1: the human-facing period label rendered in the recorded IANA zone. */
    private function renderPeriodLabel(string $startUtc,string $endUtc,string $timezone):string{
        $zone=new \DateTimeZone($timezone);
        $start=(new \DateTimeImmutable($startUtc,new \DateTimeZone('UTC')))->setTimezone($zone);
        $end=(new \DateTimeImmutable($endUtc,new \DateTimeZone('UTC')))->setTimezone($zone);
        return $start->format('Y-m-d H:i').'–'.$end->format('Y-m-d H:i').' '.$timezone;
    }
    private function replay(object $row,string $payload,string $operation):array{
        if(!hash_equals((string)$row->command_payload_digest,$payload)||(string)$row->operation!==$operation)throw new FinanceRefusalException('command_replay_conflict','A materially different replay is refused and the original record is preserved');
        if((string)$row->result_state==='refused')throw new FinanceRefusalException((string)$row->reason_code,'A refused command replay converges on its refusal');
        return array('statement_id'=>$row->result_statement_id===null?null:(int)$row->result_statement_id,'recorded'=>true,'idempotent'=>true,'command_id'=>(int)$row->id);
    }
}
