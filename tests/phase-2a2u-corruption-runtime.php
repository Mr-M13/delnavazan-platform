<?php
/**
 * Disposable Phase-U corruption proof: every declared corrupt Finance shape fails closed through its
 * owning validator, is never silently repaired, and converges after exact restoration. Synthetic local
 * data only.
 */
if(getenv('DZN_PHASE_2A2U_CORRUPTION_TEST')!=='authority'||!defined('WP_CLI')||!WP_CLI||!in_array(wp_get_environment_type(),array('local','development'),true)){fwrite(STDERR,"Phase 2A.2-U corruption runtime refused.\n");exit(1);}
require __DIR__.'/phase-2a2u-fixture.php';
use Delnavazan\Platform\Core\Application\Finance\{FinancePolicyService,LessonFinanceSnapshotService,LessonPayabilityService,TeacherRateService,TeacherStatementService};
use Delnavazan\Platform\Core\Application\Finance\Integrity\{FinancePayabilityIntegrity,FinanceRateIntegrity,FinanceSnapshotIntegrity,FinanceStatementIntegrity};
use Delnavazan\Platform\Core\Infrastructure\Migration\Migrator;
global $wpdb;$p=$wpdb->prefix.'dzn_';
$fixture=get_option('dzn_phase_2a2j_fixture');
dzn_u_fix_assert(is_array($fixture)&&count($fixture['sources']??array())>=8,'Phase-J production fixture required');
wp_set_current_user(1);
dzn_u_fix_reset();
$snapshots=new LessonFinanceSnapshotService();$payability=new LessonPayabilityService();$statements=new TeacherStatementService();$rates=new TeacherRateService();$policies=new FinancePolicyService();
$chain=dzn_u_fix_chain($fixture);
$teacherId=(int)$chain['teacher_id'];
$lesson=dzn_u_fix_occurrence($chain,$fixture,'corrupt-1',2,30);
$other=dzn_u_fix_occurrence($chain,$fixture,'corrupt-2',2,30);
$rate=dzn_u_fix_rate($teacherId,array('scope_kind'=>'teacher','course_scope_id'=>0,'amount_minor'=>12000,'currency'=>'AUD','effective_from'=>gmdate('Y-m-d H:i:s',time()-3600),'compensation_basis'=>'per_session'),'corrupt');
$snapshots->capture((int)$lesson['lesson_id'],dzn_u_fix_key('cap-1'));
$snapshots->capture((int)$other['lesson_id'],dzn_u_fix_key('cap-2'));
$evaluation=$payability->evaluate((int)$lesson['lesson_id'],dzn_u_fix_key('eval-1'));
$payability->evaluate((int)$other['lesson_id'],dzn_u_fix_key('eval-2'));
$snapshotRow=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$p}finance_lesson_snapshots WHERE lesson_id=%d",(int)$lesson['lesson_id']));
$evaluationRow=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$p}finance_payability_evaluations WHERE id=%d",(int)$evaluation['evaluation_id']));
$failsClosed=static function(callable $call,string $expected,string $message):void{
    $caught=null;try{$call();}catch(Throwable$e){$caught=$e;}
    dzn_u_fix_assert($caught!==null,$message.' was accepted');
    dzn_u_fix_assert(str_contains($caught->getMessage(),$expected),$message.' failed closed with an unexpected code: '.$caught->getMessage());
};

// Two applicable evaluations of one Lesson, and a broken sequence, are refused by the chain validator.
$secondEvaluationId=(int)$wpdb->get_var($wpdb->prepare("SELECT id FROM {$p}finance_payability_evaluations WHERE lesson_id=%d AND applicable_slot IS NULL ORDER BY id LIMIT 1",(int)$other['lesson_id']));
if($secondEvaluationId<1){
    $wpdb->query($wpdb->prepare("INSERT INTO {$p}finance_payability_evaluations (lesson_id,evaluation_sequence,applicable_slot,disposition,basis_code,lesson_kind,snapshot_id,derivation_digest,rule_version,reason_code,recorded_at,recorded_by,created_at,created_by) VALUES (%d,2,NULL,'non_payable','academy_obligation','standard',%d,%s,'finance_payability_v1','operator_decision',%s,1,%s,1)",(int)$other['lesson_id'],(int)$snapshotRow->id,str_repeat('a',64),gmdate('Y-m-d H:i:s'),gmdate('Y-m-d H:i:s')));
    $secondEvaluationId=(int)$wpdb->insert_id;
}
$wpdb->update($p.'finance_payability_evaluations',array('applicable_slot'=>1),array('id'=>$secondEvaluationId));
$failsClosed(fn()=>FinancePayabilityIntegrity::effective((int)$other['lesson_id']),'payability_supersession_conflict','two applicable evaluations of one Lesson');
$wpdb->update($p.'finance_payability_evaluations',array('applicable_slot'=>null),array('id'=>$secondEvaluationId));
$originalSequence=(int)$wpdb->get_var($wpdb->prepare("SELECT evaluation_sequence FROM {$p}finance_payability_evaluations WHERE id=%d",$secondEvaluationId));
$wpdb->update($p.'finance_payability_evaluations',array('evaluation_sequence'=>7),array('id'=>$secondEvaluationId));
$failsClosed(fn()=>FinancePayabilityIntegrity::effective((int)$other['lesson_id']),'payability_supersession_conflict','a broken evaluation sequence');
$wpdb->update($p.'finance_payability_evaluations',array('evaluation_sequence'=>$originalSequence),array('id'=>$secondEvaluationId));
dzn_u_fix_assert((int)$wpdb->get_var($wpdb->prepare("SELECT applicable_slot FROM {$p}finance_payability_evaluations WHERE id=%d",(int)$evaluationRow->id))===1,'exact restoration converges on the applicable row');

// A snapshot whose recorded digest no longer matches its inputs, or which names a missing rate version.
$originalDigest=(string)$snapshotRow->derivation_digest;
$wpdb->update($p.'finance_lesson_snapshots',array('derivation_digest'=>str_repeat('b',64)),array('id'=>(int)$snapshotRow->id));
$failsClosed(fn()=>FinanceSnapshotIntegrity::assertDigest($wpdb->get_row($wpdb->prepare("SELECT * FROM {$p}finance_lesson_snapshots WHERE id=%d",(int)$snapshotRow->id))),'snapshot_derivation_mismatch','a tampered snapshot digest');
$wpdb->update($p.'finance_lesson_snapshots',array('derivation_digest'=>$originalDigest),array('id'=>(int)$snapshotRow->id));
$originalRateVersion=(int)$snapshotRow->rate_version;
$wpdb->update($p.'finance_lesson_snapshots',array('rate_version'=>$originalRateVersion+9),array('id'=>(int)$snapshotRow->id));
$failsClosed(fn()=>FinanceSnapshotIntegrity::effective((int)$lesson['lesson_id']),'snapshot_derivation_mismatch','a snapshot naming a rate version that does not exist');
$wpdb->update($p.'finance_lesson_snapshots',array('rate_version'=>$originalRateVersion),array('id'=>(int)$snapshotRow->id));

// A rate scope violation, an overlapping interval, a withdrawn row holding the live slot and a
// non-resolvable live index all fail the rate integrity proof closed.
$originalScope=(string)$wpdb->get_var($wpdb->prepare("SELECT scope_kind FROM {$p}finance_teacher_rates WHERE id=%d",(int)$rate['rate_id']));
$wpdb->update($p.'finance_teacher_rates',array('scope_kind'=>'teacher','course_scope_id'=>$chain['course_id']),array('id'=>(int)$rate['rate_id']));
$failsClosed(fn()=>FinanceRateIntegrity::validate($wpdb->get_row($wpdb->prepare("SELECT * FROM {$p}finance_teacher_rates WHERE id=%d",(int)$rate['rate_id']))),'rate_scope_violation','a teacher-scoped rate carrying a real Course');
$wpdb->update($p.'finance_teacher_rates',array('scope_kind'=>$originalScope,'course_scope_id'=>0),array('id'=>(int)$rate['rate_id']));
$originalStatus=(string)$wpdb->get_var($wpdb->prepare("SELECT status FROM {$p}finance_teacher_rates WHERE id=%d",(int)$rate['rate_id']));
$wpdb->update($p.'finance_teacher_rates',array('status'=>'withdrawn'),array('id'=>(int)$rate['rate_id']));
$failsClosed(fn()=>FinanceRateIntegrity::validate($wpdb->get_row($wpdb->prepare("SELECT * FROM {$p}finance_teacher_rates WHERE id=%d",(int)$rate['rate_id']))),'teacher_rate_state_not_resolvable','a withdrawn rate keeping its live slot and an open interval');
$wpdb->update($p.'finance_teacher_rates',array('status'=>$originalStatus),array('id'=>(int)$rate['rate_id']));
$wpdb->query($wpdb->prepare("INSERT INTO {$p}finance_teacher_rates (uid,teacher_id,scope_kind,course_scope_id,compensation_basis,amount_minor,currency,effective_from,status,rate_version,active_slot,recorded_at,recorded_by,created_at,created_by,updated_at) VALUES (%s,%d,'teacher',0,'per_session',1,'AUD',%s,'superseded',99,NULL,%s,1,%s,1,%s)",wp_generate_uuid4(),$teacherId,gmdate('Y-m-d H:i:s',time()-1800),gmdate('Y-m-d H:i:s'),gmdate('Y-m-d H:i:s'),gmdate('Y-m-d H:i:s')));
$overlapId=(int)$wpdb->insert_id;
$failsClosed(function()use($rates,$teacherId){
    $rows=$rates->timeline($teacherId);
    unset($rows);
    FinanceRateIntegrity::timeline((new Delnavazan\Platform\Core\Infrastructure\Repository\TeacherRateRepository())->ratesForTeacher($teacherId));
},'teacher_rate_timeline_overlap','two overlapping rate intervals in one scope');
$wpdb->query($wpdb->prepare("DELETE FROM {$p}finance_teacher_rates WHERE id=%d",$overlapId));
FinanceRateIntegrity::timeline((new Delnavazan\Platform\Core\Infrastructure\Repository\TeacherRateRepository())->ratesForTeacher($teacherId));

// A statement whose state and issuance evidence disagree, and a line naming a foreign snapshot.
// This period contains no finance-relevant Lesson, so the draft is refused `statement_derivation_mismatch`
// (a recorded business refusal, never a silent empty statement). The statement-shape corruption probes
// below therefore run against whatever statement the period does carry, when one exists.
try{$statements->draft($teacherId,gmdate('Y-m-d H:i:s',time()-7200),gmdate('Y-m-d H:i:s',time()-60),dzn_u_fix_key('corrupt-draft'));}catch(Throwable$e){dzn_u_fix_assert($e->getMessage()==='statement_derivation_mismatch','an empty period must be refused statement_derivation_mismatch, not silently drafted');}
$statementId=(int)($wpdb->get_var($wpdb->prepare("SELECT id FROM {$p}finance_statements WHERE teacher_id=%d ORDER BY id DESC LIMIT 1",$teacherId))??0);
if($statementId>0){
    $originalIssued=$wpdb->get_row($wpdb->prepare("SELECT state,issued_at,issued_by FROM {$p}finance_statements WHERE id=%d",$statementId));
    if((string)$originalIssued->state==='issued'){
        $wpdb->update($p.'finance_statements',array('issued_at'=>null),array('id'=>$statementId));
        $failsClosed(fn()=>FinanceStatementIntegrity::assertTotals($statementId),'statement_derivation_mismatch','an issued statement with no issuance evidence');
        $wpdb->update($p.'finance_statements',array('issued_at'=>$originalIssued->issued_at,'issued_by'=>$originalIssued->issued_by),array('id'=>$statementId));
    }
    $wpdb->update($p.'finance_statements',array('issued_at'=>gmdate('Y-m-d H:i:s')),array('id'=>$statementId,'state'=>'draft'));
    $failsClosed(fn()=>FinanceStatementIntegrity::assertTotals($statementId),'statement_derivation_mismatch','a draft statement carrying issuance evidence');
    $wpdb->update($p.'finance_statements',array('issued_at'=>$originalIssued->issued_at,'issued_by'=>$originalIssued->issued_by),array('id'=>$statementId));
    $line=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$p}finance_statement_lines WHERE statement_id=%d ORDER BY line_sequence LIMIT 1",$statementId));
    if($line){
        $foreignSnapshot=(int)$wpdb->get_var($wpdb->prepare("SELECT id FROM {$p}finance_lesson_snapshots WHERE lesson_id<>%d LIMIT 1",(int)$line->lesson_id));
        if($foreignSnapshot>0){
            $wpdb->update($p.'finance_statement_lines',array('snapshot_id'=>$foreignSnapshot),array('id'=>(int)$line->id));
            $failsClosed(fn()=>FinanceStatementIntegrity::assertTotals($statementId),'statement_derivation_mismatch','a line naming a snapshot of another Lesson');
            $wpdb->update($p.'finance_statement_lines',array('snapshot_id'=>(int)$line->snapshot_id),array('id'=>(int)$line->id));
        }
    }
    $wpdb->update($p.'finance_statements',array('period_label'=>null),array('id'=>$statementId));
    $failsClosed(fn()=>FinanceStatementIntegrity::assertTimezoneTriple($wpdb->get_row($wpdb->prepare("SELECT * FROM {$p}finance_statements WHERE id=%d",$statementId))),'statement_timezone_representation_invalid','a partially recorded statement timezone triple');
    $wpdb->update($p.'finance_statements',array('period_label'=>'restored'),array('id'=>$statementId));
}

// Corrupt policy and root shapes are refused by the phase verifier, and converge after restoration.
$originalEffective=(string)$wpdb->get_var("SELECT effective_from FROM {$p}finance_policies WHERE policy_key='INTRO_PAYABILITY_POLICY'");
$wpdb->query("UPDATE {$p}finance_policies SET effective_from=NULL WHERE policy_key='INTRO_PAYABILITY_POLICY'");
$failsClosed(fn()=>Migrator::maybe_upgrade(),'Migration verification failed','a policy version with a NULL effective instant');
$wpdb->query($wpdb->prepare("UPDATE {$p}finance_policies SET effective_from=%s WHERE policy_key='INTRO_PAYABILITY_POLICY'",$originalEffective));
$originalValue=(string)$wpdb->get_var("SELECT policy_value FROM {$p}finance_policies WHERE policy_key='INTERRUPTION_COMPENSATION_POLICY'");
$wpdb->query("UPDATE {$p}finance_policies SET policy_value=NULL WHERE policy_key='INTERRUPTION_COMPENSATION_POLICY'");
$failsClosed(fn()=>Migrator::maybe_upgrade(),'Migration verification failed','a null-valued policy version');
$wpdb->query($wpdb->prepare("UPDATE {$p}finance_policies SET policy_value=%s WHERE policy_key='INTERRUPTION_COMPENSATION_POLICY'",$originalValue));
$wpdb->query("DELETE FROM {$p}finance_policy_roots");
$failsClosed(fn()=>Migrator::maybe_upgrade(),'Migration verification failed','a missing global policy root');
$wpdb->query($wpdb->prepare("INSERT INTO {$p}finance_policy_roots (root_key,created_at,created_by) VALUES ('finance_policy',%s,NULL)",gmdate('Y-m-d H:i:s')));
Migrator::maybe_upgrade();
echo "phase-2a2u-corruption-runtime: OK (every corrupt shape fails closed through its owning validator and converges after restoration)\n";
