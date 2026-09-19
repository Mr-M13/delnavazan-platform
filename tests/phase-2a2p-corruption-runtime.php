<?php
/** Disposable Phase-P corruption regressions: corrupted intake authority must fail closed. */
if(getenv('DZN_PHASE_2A2P_RUNTIME_TEST')!=='corruption'||!defined('WP_CLI')||!WP_CLI||!in_array(wp_get_environment_type(),array('local','development'),true)){fwrite(STDERR,"Phase 2A.2-P corruption runtime refused.\n");exit(1);}
use Delnavazan\Platform\Core\Application\{CanonicalAttendanceIntakeService,CanonicalAttendanceReadService,CanonicalEnrolmentLifecycleService,CanonicalLessonAuthorityService,CanonicalLessonScheduleService,CanonicalTermAuthorityService,TeacherAcceptingStateService,TeacherAssignmentService,TeacherAvailabilityService,TeacherService,TeachingEligibilityService};

global $wpdb;$p=$wpdb->prefix.'dzn_';
function dzn_pc_assert(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);}
function dzn_pc_key(string $label):string{return 'dzn-2a2pc-'.$label.'-'.wp_generate_uuid4();}
function dzn_pc_evidence(string $reference):array{return array('evidence_channel'=>'staff_record','evidence_reference'=>$reference,'evidence_at'=>gmdate('Y-m-d H:i:s'));}
$fixture=get_option('dzn_phase_2a2j_fixture');
dzn_pc_assert(is_array($fixture)&&count($fixture['sources']??array())>=1,'Phase-J production fixture required');
$lessons=new CanonicalLessonAuthorityService();$enrolments=new CanonicalEnrolmentLifecycleService();$terms=new CanonicalTermAuthorityService();
$assignments=new TeacherAssignmentService();$schedules=new CanonicalLessonScheduleService();$availability=new TeacherAvailabilityService();$accepting=new TeacherAcceptingStateService();
$intake=new CanonicalAttendanceIntakeService();$read=new CanonicalAttendanceReadService();
$source=$fixture['sources'][0];$enrolmentId=(int)$source['enrolment_id'];
$enrolments->activate($enrolmentId,'authorised',dzn_pc_evidence('activate'),dzn_pc_key('activate'));
$term=$terms->create($enrolmentId,null,null,dzn_pc_evidence('term'),dzn_pc_key('term'));
$terms->activate((int)$term['term_id'],'authorised',dzn_pc_evidence('term-active'),dzn_pc_key('term-active'));
$assignment=$assignments->assignInitial($enrolmentId,dzn_pc_key('assignment'));
$course=(int)$wpdb->get_var($wpdb->prepare("SELECT course_id FROM {$p}enrolments WHERE id=%d",$enrolmentId));
$teacher=(int)(new TeacherService())->create(array('display_name'=>'Synthetic PC Teacher','email'=>'pc-'.wp_generate_uuid4().'@phase-2a2p.invalid'));
$accepting->set(array('teacher_id'=>$teacher,'state'=>'accepting','reason_code'=>'synthetic_provision'));
$availability->setProfile(array('teacher_id'=>$teacher,'timezone'=>'UTC','status'=>'active','reason_code'=>'synthetic_provision'));
for($weekday=1;$weekday<=7;$weekday++)$availability->setRecurringRule(array('teacher_id'=>$teacher,'timezone'=>'UTC','weekday'=>$weekday,'local_start_time'=>'00:00:00','local_end_time'=>'23:59:59','state'=>'requestable','status'=>'active','reason_code'=>'synthetic_provision'));
(new TeachingEligibilityService())->setEligibility(array('teacher_id'=>$teacher,'course_id'=>$course,'status'=>'active','reason_code'=>'synthetic_provision'));
$moved=$assignments->replace($enrolmentId,$teacher,array('expected_assignment_id'=>(int)$assignment['assignment_id'],'route'=>'staff_attestation','evidence_channel'=>'phone','evidence_reference'=>'isolated-'.$teacher,'evidence_at'=>gmdate('Y-m-d H:i:s')),dzn_pc_key('isolate'));
$assignmentId=(int)$moved['assignment_id'];
$intake->recordCutoverPolicy(gmdate('Y-m-d H:i:s',strtotime('-1 day')),dzn_pc_key('cutover'));
$occurrence=function(string $label,int $duration) use($lessons,$schedules,$wpdb,$p,$term,$assignmentId):array{
    $lessonId=(int)$lessons->createStandard((int)$term['term_id'],$assignmentId,dzn_pc_evidence($label),dzn_pc_key($label))['lesson_id'];
    $wall=gmdate('Y-m-d H:i:s',strtotime('+3 seconds'));
    $scheduled=$schedules->schedule($lessonId,$assignmentId,array('schedule_timezone'=>'UTC','local_wall_date'=>substr($wall,0,10),'local_wall_time'=>substr($wall,11),'duration_minutes'=>$duration,'reason_code'=>'synthetic_schedule')+dzn_pc_evidence('s-'.$label),dzn_pc_key('s-'.$label));
    $version=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$p}canonical_lesson_schedule_versions WHERE id=%d",(int)$scheduled['schedule_version_id']));
    $schedules->release($lessonId,array('expected_schedule_version_id'=>(int)$version->id,'reason_code'=>'synthetic_release')+dzn_pc_evidence('r-'.$label),dzn_pc_key('r-'.$label));
    $row=$wpdb->get_row($wpdb->prepare("SELECT student_id,teacher_id FROM {$p}lessons WHERE id=%d",$lessonId));
    return array('lesson_id'=>$lessonId,'version_id'=>(int)$version->id,'start'=>(string)$version->starts_at_utc,'end'=>(string)$version->ends_at_utc,'student_id'=>(int)$row->student_id,'teacher_id'=>(int)$row->teacher_id);
};
$occurrences=array();
foreach(array('case','evidence','decision','link')as$i=>$label)$occurrences[$label]=$occurrence('corrupt-'.$label,1);
$latest=0;foreach($occurrences as$o)$latest=max($latest,strtotime($o['end'].' UTC'));
while(time()<$latest+3)sleep(1);
$settledOutcomeId=0;
$runSuffix=substr(str_replace('-','',wp_generate_uuid4()),0,10);
foreach($occurrences as$label=>$o){
    $intake->ingestProviderEvidence((int)$o['lesson_id'],(int)$o['version_id'],array('provider_code'=>'google_meet','provider_event_key'=>'evt-'.$label.'-'.$runSuffix,'provider_payload_key'=>'pay-'.$label.'-'.$runSuffix,'participant_role'=>'teacher','participant_identity_state'=>'resolved','verification_state'=>'verified','resolved_teacher_id'=>(int)$o['teacher_id'],'join_at_utc'=>$o['start'],'leave_at_utc'=>$o['end'],'observed_at'=>$o['end'],'provenance_reference'=>'prov-'.$label.'-'.$runSuffix,'evidence_reference'=>'ref-'.$label.'-'.$runSuffix),dzn_pc_key('evt-'.$label));
}
$caseOf=static function(int $lessonId,int $versionId) use($wpdb,$p):object{$row=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$p}canonical_attendance_cases WHERE lesson_id=%d AND schedule_version_id=%d",$lessonId,$versionId));if(!$row)throw new RuntimeException('Phase P case fixture missing');return $row;};
$cases=array();foreach($occurrences as$label=>$o)$cases[$label]=$caseOf((int)$o['lesson_id'],(int)$o['version_id']);
$evidenceId=static function(int $caseId) use($wpdb,$p):int{return(int)$wpdb->get_var($wpdb->prepare("SELECT id FROM {$p}canonical_attendance_evidence WHERE case_id=%d ORDER BY id LIMIT 1",$caseId));};
$decisionId=static function(int $caseId) use($wpdb,$p):int{return(int)$wpdb->get_var($wpdb->prepare("SELECT id FROM {$p}canonical_attendance_decisions WHERE case_id=%d ORDER BY decision_sequence LIMIT 1",$caseId));};
$casesRun=0;
/** Damage one material fact, prove the protected read fails closed, then repair and prove recovery. */
$failClosed=function(string $label,object $case,string $damage,string $repair,array $expected) use($wpdb,$read):void{
    dzn_pc_assert($wpdb->query($damage)!==false,'Failed to damage Phase P authority: '.$label);
    $observed=null;
    try{$read->forOccurrence((int)$case->lesson_id,(int)$case->schedule_version_id);}catch(Throwable$e){$observed=$e->getMessage();}
    dzn_pc_assert(in_array($observed,$expected,true),'Corrupted Phase P authority ('.$label.') was not rejected (observed: '.var_export($observed,true).')');
    dzn_pc_assert($wpdb->query($repair)!==false,'Failed to repair Phase P authority: '.$label);
    $restored=$read->forOccurrence((int)$case->lesson_id,(int)$case->schedule_version_id);
    dzn_pc_assert((string)$restored['case']['case_id']===(string)$case->id,'Repaired Phase P authority is still unreadable: '.$label);
};
/** A corrupted selector makes the occurrence undiscoverable; both outcomes fail closed. */
$selectorFail=array('canonical_attendance_integrity_conflict','canonical_attendance_case_required');
$integrityFail=array('canonical_attendance_integrity_conflict');

// 1. Lesson binding.
$failClosed('Lesson binding',$cases['case'],"UPDATE {$p}canonical_attendance_cases SET lesson_id=lesson_id+100000 WHERE id=".(int)$cases['case']->id,"UPDATE {$p}canonical_attendance_cases SET lesson_id=lesson_id-100000 WHERE id=".(int)$cases['case']->id,$selectorFail);$casesRun++;
// 2. Schedule-version binding.
$failClosed('schedule-version binding',$cases['case'],"UPDATE {$p}canonical_attendance_cases SET schedule_version_id=schedule_version_id+100000 WHERE id=".(int)$cases['case']->id,"UPDATE {$p}canonical_attendance_cases SET schedule_version_id=schedule_version_id-100000 WHERE id=".(int)$cases['case']->id,$selectorFail);$casesRun++;
// 3. Occurrence anchors (window must keep the locked 15-minute post-class grace).
$failClosed('occurrence window',$cases['case'],"UPDATE {$p}canonical_attendance_cases SET window_end_utc=DATE_ADD(window_end_utc, INTERVAL 60 SECOND) WHERE id=".(int)$cases['case']->id,"UPDATE {$p}canonical_attendance_cases SET window_end_utc=DATE_SUB(window_end_utc, INTERVAL 60 SECOND) WHERE id=".(int)$cases['case']->id,array('canonical_attendance_integrity_conflict'));$casesRun++;
// 4. Selectors: Term and Enrolment.
$failClosed('Term selector',$cases['case'],"UPDATE {$p}canonical_attendance_cases SET term_id=term_id+100000 WHERE id=".(int)$cases['case']->id,"UPDATE {$p}canonical_attendance_cases SET term_id=term_id-100000 WHERE id=".(int)$cases['case']->id,array('canonical_attendance_integrity_conflict'));$casesRun++;
$failClosed('Enrolment selector',$cases['case'],"UPDATE {$p}canonical_attendance_cases SET enrolment_id=enrolment_id+100000 WHERE id=".(int)$cases['case']->id,"UPDATE {$p}canonical_attendance_cases SET enrolment_id=enrolment_id-100000 WHERE id=".(int)$cases['case']->id,array('canonical_attendance_integrity_conflict'));$casesRun++;
// 5. Case state and version.
$failClosed('review state',$cases['case'],"UPDATE {$p}canonical_attendance_cases SET state='unknown_state' WHERE id=".(int)$cases['case']->id,"UPDATE {$p}canonical_attendance_cases SET state='ready_for_review' WHERE id=".(int)$cases['case']->id,array('canonical_attendance_integrity_conflict'));$casesRun++;
// 6. Provider event key and payload digest.
$eventKeyCase=$cases['evidence'];$eventKeyEvidence=$evidenceId((int)$eventKeyCase->id);
dzn_pc_assert($eventKeyEvidence>0,'Phase P evidence fixture missing');
$originalEventKey=(string)$wpdb->get_var($wpdb->prepare("SELECT provider_event_key_digest FROM {$p}canonical_attendance_evidence WHERE id=%d",$eventKeyEvidence));
$failClosed('provider event key',$eventKeyCase,"UPDATE {$p}canonical_attendance_evidence SET provider_event_key_digest='short' WHERE id={$eventKeyEvidence}","UPDATE {$p}canonical_attendance_evidence SET provider_event_key_digest='{$originalEventKey}' WHERE id={$eventKeyEvidence}",array('canonical_attendance_integrity_conflict'));$casesRun++;
$originalPayload=(string)$wpdb->get_var($wpdb->prepare("SELECT provider_payload_digest FROM {$p}canonical_attendance_evidence WHERE id=%d",$eventKeyEvidence));
$failClosed('payload digest',$eventKeyCase,"UPDATE {$p}canonical_attendance_evidence SET provider_payload_digest=NULL WHERE id={$eventKeyEvidence}","UPDATE {$p}canonical_attendance_evidence SET provider_payload_digest='{$originalPayload}' WHERE id={$eventKeyEvidence}",array('canonical_attendance_integrity_conflict'));$casesRun++;
// 7. Participant identity and interval endpoints.
$identityCase=$cases['evidence'];$identityEvidence=$evidenceId((int)$identityCase->id);
$originalRole=(string)$wpdb->get_var($wpdb->prepare("SELECT participant_role FROM {$p}canonical_attendance_evidence WHERE id=%d",$identityEvidence));
$failClosed('participant identity',$identityCase,"UPDATE {$p}canonical_attendance_evidence SET participant_role='observer' WHERE id={$identityEvidence}","UPDATE {$p}canonical_attendance_evidence SET participant_role='{$originalRole}' WHERE id={$identityEvidence}",array('canonical_attendance_integrity_conflict'));$casesRun++;
$failClosed('interval endpoints',$identityCase,"UPDATE {$p}canonical_attendance_evidence SET join_at_utc='not-a-timestamp' WHERE id={$identityEvidence}","UPDATE {$p}canonical_attendance_evidence SET join_at_utc=(SELECT s FROM (SELECT starts_at_utc s FROM {$p}canonical_lesson_schedule_versions WHERE lesson_id=(SELECT lesson_id FROM {$p}canonical_attendance_evidence WHERE id={$identityEvidence})) t) WHERE id={$identityEvidence}",array('canonical_attendance_integrity_conflict'));$casesRun++;
// 8. Evidence reference digest and verification state.
$digestEvidence=$evidenceId((int)$cases['evidence']->id);
$originalReference=(string)$wpdb->get_var($wpdb->prepare("SELECT evidence_reference_digest FROM {$p}canonical_attendance_evidence WHERE id=%d",$digestEvidence));
$failClosed('evidence reference digest',$cases['evidence'],"UPDATE {$p}canonical_attendance_evidence SET evidence_reference_digest='short' WHERE id={$digestEvidence}","UPDATE {$p}canonical_attendance_evidence SET evidence_reference_digest='{$originalReference}' WHERE id={$digestEvidence}",array('canonical_attendance_integrity_conflict'));$casesRun++;
$originalVerification=(string)$wpdb->get_var($wpdb->prepare("SELECT verification_state FROM {$p}canonical_attendance_evidence WHERE id=%d",$digestEvidence));
$failClosed('verification state',$cases['evidence'],"UPDATE {$p}canonical_attendance_evidence SET verification_state='trusted_guess' WHERE id={$digestEvidence}","UPDATE {$p}canonical_attendance_evidence SET verification_state='{$originalVerification}' WHERE id={$digestEvidence}",array('canonical_attendance_integrity_conflict'));$casesRun++;
// 9. Decision rule/version and Phase-O result link.
$decisionCase=$cases['decision'];$firstDecision=$decisionId((int)$decisionCase->id);
$originalRule=(string)$wpdb->get_var($wpdb->prepare("SELECT rule_version FROM {$p}canonical_attendance_decisions WHERE id=%d",$firstDecision));
$failClosed('rule version',$decisionCase,"UPDATE {$p}canonical_attendance_decisions SET rule_version='attendance_v0' WHERE id={$firstDecision}","UPDATE {$p}canonical_attendance_decisions SET rule_version='{$originalRule}' WHERE id={$firstDecision}",array('canonical_attendance_integrity_conflict'));$casesRun++;
$failClosed('Phase-O result link',$decisionCase,"UPDATE {$p}canonical_attendance_decisions SET result_outcome_id=999999 WHERE id={$firstDecision}","UPDATE {$p}canonical_attendance_decisions SET result_outcome_id=NULL WHERE id={$firstDecision}",array('canonical_attendance_integrity_conflict'));$casesRun++;
// 10. Anomaly classification.
$anomalyCase=$cases['decision'];$anomalyId=(int)$wpdb->get_var($wpdb->prepare("SELECT id FROM {$p}canonical_attendance_case_anomalies WHERE case_id=%d LIMIT 1",(int)$anomalyCase->id));
dzn_pc_assert($anomalyId>0,'Phase P anomaly fixture missing');
$failClosed('anomaly classification',$anomalyCase,"UPDATE {$p}canonical_attendance_case_anomalies SET code='free_form_reason' WHERE id={$anomalyId}","UPDATE {$p}canonical_attendance_case_anomalies SET code='provider_evidence_missing' WHERE id={$anomalyId}",array('canonical_attendance_integrity_conflict'));$casesRun++;

echo "corruption_cases=".$casesRun."\nintake_authority_fail_closed=pass\nrepair_recovery=pass\nPhase 2A.2-P corruption runtime passed\n";
