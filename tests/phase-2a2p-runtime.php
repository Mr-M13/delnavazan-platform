<?php
/** Disposable production-path Phase-P attendance intake/review proof; synthetic local data only. */
if(getenv('DZN_PHASE_2A2P_RUNTIME_TEST')!=='authority'||!defined('WP_CLI')||!WP_CLI||!in_array(wp_get_environment_type(),array('local','development'),true)){fwrite(STDERR,"Phase 2A.2-P runtime refused.\n");exit(1);}
use Delnavazan\Platform\Core\Application\{CanonicalAcademyObligationService,CanonicalAttendanceIntakeService,CanonicalAttendanceReadService,CanonicalAttendanceSettlementService,CanonicalEnrolmentLifecycleService,CanonicalLessonAuthorityService,CanonicalLessonScheduleService,CanonicalTermAuthorityService,TeacherAcceptingStateService,TeacherAssignmentService,TeacherAvailabilityService,TeacherService,TeachingEligibilityService};

global $wpdb; $p=$wpdb->prefix.'dzn_';
function dzn_p_assert(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);}
function dzn_p_key(string $label):string{return 'dzn-2a2p-'.$label.'-'.wp_generate_uuid4();}
function dzn_p_evidence(string $reference):array{return array('evidence_channel'=>'staff_record','evidence_reference'=>$reference,'evidence_at'=>gmdate('Y-m-d H:i:s'));}
function dzn_p_rejected(callable $call,string $expected,string $message):void{$caught=null;try{$call();}catch(Throwable$e){$caught=$e;}dzn_p_assert($caught!==null,$message.' was accepted');dzn_p_assert($caught->getMessage()===$expected,$message.' rejected with an unexpected error: '.$caught->getMessage());}
$fixture=get_option('dzn_phase_2a2j_fixture');
dzn_p_assert(is_array($fixture)&&count($fixture['sources']??array())>=8,'Phase-J production fixture required');
$lessons=new CanonicalLessonAuthorityService();$enrolments=new CanonicalEnrolmentLifecycleService();
$terms=new CanonicalTermAuthorityService();$assignments=new TeacherAssignmentService();$schedules=new CanonicalLessonScheduleService();
$availability=new TeacherAvailabilityService();$accepting=new TeacherAcceptingStateService();
$intake=new CanonicalAttendanceIntakeService();$settlement=new CanonicalAttendanceSettlementService();$read=new CanonicalAttendanceReadService();
$allocate=static function() use($fixture,$wpdb,$p):int{foreach($fixture['sources'] as$source){$id=(int)$source['enrolment_id'];$state=(string)$wpdb->get_var($wpdb->prepare("SELECT lifecycle_state FROM {$p}enrolments WHERE id=%d",$id));$count=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$p}terms WHERE enrolment_id=%d AND record_model='canonical_enrolment_term_v1'",$id));if($state==='authorised'&&$count===0)return$id;}throw new RuntimeException('no_available_source');};
$chain=function() use($allocate,$enrolments,$terms,$assignments,$availability,$accepting,$wpdb,$p):array{
    $id=$allocate();
    $enrolments->activate($id,'authorised',dzn_p_evidence('activate'),dzn_p_key('activate'));
    $term=$terms->create($id,null,null,dzn_p_evidence('term'),dzn_p_key('term'));
    $terms->activate((int)$term['term_id'],'authorised',dzn_p_evidence('term-active'),dzn_p_key('term-active'));
    $assignment=$assignments->assignInitial($id,dzn_p_key('assignment'));
    $course=(int)$wpdb->get_var($wpdb->prepare("SELECT course_id FROM {$p}enrolments WHERE id=%d",$id));
    $teacher=(int)(new TeacherService())->create(array('display_name'=>'Synthetic P Teacher','email'=>'p-'.wp_generate_uuid4().'@phase-2a2p.invalid'));
    $accepting->set(array('teacher_id'=>$teacher,'state'=>'accepting','reason_code'=>'synthetic_provision'));
    $availability->setProfile(array('teacher_id'=>$teacher,'timezone'=>'UTC','status'=>'active','reason_code'=>'synthetic_provision'));
    for($weekday=1;$weekday<=7;$weekday++)$availability->setRecurringRule(array('teacher_id'=>$teacher,'timezone'=>'UTC','weekday'=>$weekday,'local_start_time'=>'00:00:00','local_end_time'=>'23:59:59','state'=>'requestable','status'=>'active','reason_code'=>'synthetic_provision'));
    (new TeachingEligibilityService())->setEligibility(array('teacher_id'=>$teacher,'course_id'=>$course,'status'=>'active','reason_code'=>'synthetic_provision'));
    $moved=$assignments->replace($id,$teacher,array('expected_assignment_id'=>(int)$assignment['assignment_id'],'route'=>'staff_attestation','evidence_channel'=>'phone','evidence_reference'=>'isolated-'.$teacher,'evidence_at'=>gmdate('Y-m-d H:i:s')),dzn_p_key('isolate'));
    return array('enrolment_id'=>$id,'term_id'=>(int)$term['term_id'],'assignment_id'=>(int)$moved['assignment_id']);
};
$occurrence=function(array $chain,string $label,int $durationMinutes) use($lessons,$schedules,$wpdb,$p):array{
    $lessonId=(int)$lessons->createStandard((int)$chain['term_id'],(int)$chain['assignment_id'],dzn_p_evidence($label),dzn_p_key($label))['lesson_id'];
    $wall=gmdate('Y-m-d H:i:s',strtotime('+3 seconds'));
    $scheduled=$schedules->schedule($lessonId,(int)$chain['assignment_id'],array('schedule_timezone'=>'UTC','local_wall_date'=>substr($wall,0,10),'local_wall_time'=>substr($wall,11),'duration_minutes'=>$durationMinutes,'reason_code'=>'synthetic_schedule')+dzn_p_evidence('schedule-'.$label),dzn_p_key('schedule-'.$label));
    $version=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$p}canonical_lesson_schedule_versions WHERE id=%d",(int)$scheduled['schedule_version_id']));
    $schedules->release($lessonId,array('expected_schedule_version_id'=>(int)$version->id,'reason_code'=>'synthetic_release')+dzn_p_evidence('release-'.$label),dzn_p_key('release-'.$label));
    return array('lesson_id'=>$lessonId,'version_id'=>(int)$version->id,'start'=>(string)$version->starts_at_utc,'end'=>(string)$version->ends_at_utc);
};
$windowEnd=static fn(array $o):string=>gmdate('Y-m-d H:i:s',strtotime($o['end'].' UTC')+900);
/** Provider event keys must be unique per run: they are durable globally unique receipts. */
$runSuffix=substr(str_replace('-','',wp_generate_uuid4()),0,10);
$plus=static fn(string $utc,int $seconds):string=>gmdate('Y-m-d H:i:s',strtotime($utc.' UTC')+$seconds);
$provider=function(array $o,string $role,int $joinOffset,int $leaveOffset,string $label) use($intake,$plus,$runSuffix):array{
    return $intake->ingestProviderEvidence((int)$o['lesson_id'],(int)$o['version_id'],array(
        'provider_code'=>'google_meet','provider_event_key'=>'event-'.$label.'-'.$runSuffix,'provider_payload_key'=>'payload-'.$label.'-'.$runSuffix,
        'participant_role'=>$role,'participant_identity_state'=>'resolved','verification_state'=>'verified',
        'resolved_student_id'=>$role==='student'?(int)$o['student_id']:null,'resolved_teacher_id'=>$role==='teacher'?(int)$o['teacher_id']:null,
        'join_at_utc'=>$plus($o['start'],$joinOffset),'leave_at_utc'=>$plus($o['start'],$leaveOffset),'observed_at'=>$o['end'],
        'provenance_reference'=>'prov-'.$label,'evidence_reference'=>'ref-'.$label,
    ),dzn_p_key('provider-'.$label));
};
$caseOf=static function(int $lessonId,int $versionId) use($wpdb,$p):?object{return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$p}canonical_attendance_cases WHERE lesson_id=%d AND schedule_version_id=%d",$lessonId,$versionId));};
$evidenceCount=static function(int $caseId) use($wpdb,$p):int{return(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$p}canonical_attendance_evidence WHERE case_id=%d",$caseId));};
$lessonState=static function(int $lessonId) use($wpdb,$p):string{return(string)$wpdb->get_var($wpdb->prepare("SELECT lifecycle_state FROM {$p}lessons WHERE id=%d",$lessonId));};
$outcome=static function(int $lessonId) use($wpdb,$p){return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$p}canonical_lesson_delivery_outcomes WHERE lesson_id=%d AND applicable_slot=1",$lessonId));};
$obligationCount=static function(int $lessonId) use($wpdb,$p):int{return(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$p}canonical_academy_obligations WHERE source_lesson_id=%d",$lessonId));};

// ---------------------------------------------------------------------------
// Fixture: cutover policy, seven chains, one shared wait for the longest occurrence.
// ---------------------------------------------------------------------------
$intake->recordCutoverPolicy(gmdate('Y-m-d H:i:s',strtotime('-1 day')),dzn_p_key('cutover'));
$chains=array();$occurrences=array();
foreach(array('settle','below','review','teacher_nd','forced','no_change','late')as$label)$chains[$label]=$chain();
$occurrences['settle']=$occurrence($chains['settle'],'settle',5);
$occurrences['below']=$occurrence($chains['below'],'below',5);
foreach(array('review','teacher_nd','forced','no_change','late')as$label)$occurrences[$label]=$occurrence($chains[$label],$label,1);
foreach($occurrences as$label=>$o){
    $row=$wpdb->get_row($wpdb->prepare("SELECT student_id,teacher_id FROM {$p}lessons WHERE id=%d",(int)$o['lesson_id']));
    $occurrences[$label]['student_id']=(int)$row->student_id;$occurrences[$label]['teacher_id']=(int)$row->teacher_id;
}
$latestEnd=0;foreach($occurrences as$o)$latestEnd=max($latestEnd,strtotime($o['end'].' UTC'));
while(time()<$latestEnd+3)sleep(1);

// ---------------------------------------------------------------------------
// 1. Automatic settlement: exactly 1200 seconds of trusted, identity-resolved overlap.
// ---------------------------------------------------------------------------
$settle=$occurrences['settle'];
$provider($settle,'teacher',0,1200,'settle-teacher');
$provider($settle,'student',0,1200,'settle-student');
$case=$caseOf((int)$settle['lesson_id'],(int)$settle['version_id']);
dzn_p_assert($case&&(string)$case->state==='settled','automatic settlement did not settle the case');
$settledOutcome=$outcome((int)$settle['lesson_id']);
dzn_p_assert($settledOutcome&&(string)$settledOutcome->outcome_code==='delivered','automatic settlement must create canonical delivered truth');
dzn_p_assert($lessonState((int)$settle['lesson_id'])==='completed','automatic settlement must converge Lesson completion');
dzn_p_assert($obligationCount((int)$settle['lesson_id'])===0,'ordinary success must not create academy debt');
dzn_p_assert((string)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}dzn_lessons WHERE canonical_replacement_origin_lesson_id=%d",(int)$settle['lesson_id']))==='0','ordinary success must not create a replacement Lesson');
$readModel=$read->forOccurrence((int)$settle['lesson_id'],(int)$settle['version_id']);
dzn_p_assert($readModel['assessment']['qualifying_overlap_seconds']===1200&&$readModel['assessment']['threshold_seconds']===1200,'read model must expose the locked threshold and measured overlap');
dzn_p_assert($readModel['canonical_truth']['effective_outcome']==='delivered'&&$readModel['case']['state']==='settled','read model must compose canonical truth and case state');
dzn_p_assert($readModel['assessment']['pre_grace_seconds']===0&&$readModel['assessment']['post_grace_seconds']===900,'read model must expose the locked temporal policy');

// ---------------------------------------------------------------------------
// 2. 1199 seconds is not success: anomaly only, no canonical side effect.
// ---------------------------------------------------------------------------
$below=$occurrences['below'];
$provider($below,'teacher',0,1200,'below-teacher');
$provider($below,'student',0,1199,'below-student');
$belowCase=$caseOf((int)$below['lesson_id'],(int)$below['version_id']);
dzn_p_assert($belowCase&&(string)$belowCase->state==='ready_for_review','below-threshold evidence must open review');
dzn_p_assert($outcome((int)$below['lesson_id'])===null,'below-threshold evidence must not create canonical truth');
dzn_p_assert($lessonState((int)$below['lesson_id'])==='authorised','below-threshold evidence must not complete the Lesson');
dzn_p_assert($obligationCount((int)$below['lesson_id'])===0,'insufficient evidence must never identify responsibility');
$belowRead=$read->forOccurrence((int)$below['lesson_id'],(int)$below['version_id']);
dzn_p_assert($belowRead['assessment']['qualifying_overlap_seconds']===1199,'read model must report the measured 1199 seconds');
dzn_p_assert(in_array('overlap_below_threshold',$belowRead['assessment']['anomaly_codes'],true),'overlap_below_threshold anomaly must be recorded');
dzn_p_assert(in_array('teacher_participation_unproven',$belowRead['assessment']['anomaly_codes'],true)===false,'proven teacher participation must not be reported unproven');

// ---------------------------------------------------------------------------
// 3. Human claims: evidence only, never settlement, and they coexist append-only.
// ---------------------------------------------------------------------------
$claimBefore=$evidenceCount((int)$belowCase->id);
$claim=$intake->submitClaim((int)$below['lesson_id'],(int)$below['version_id'],array('claim_kind'=>'advance_absence_claim','reason_code'=>'student_travel','observed_at'=>gmdate('Y-m-d H:i:s'),'evidence_reference'=>'absence-claim'),dzn_p_key('claim'));
dzn_p_assert((int)$claim['case_id']===(int)$belowCase->id,'claim must bind to the existing occurrence case');
dzn_p_assert($evidenceCount((int)$belowCase->id)===$claimBefore+1,'claim must be persisted as append-only evidence');
dzn_p_assert($outcome((int)$below['lesson_id'])===null&&$lessonState((int)$below['lesson_id'])==='authorised','a Student absence claim must not change canonical truth');
dzn_p_assert($obligationCount((int)$below['lesson_id'])===0,'a Student absence claim must not create academy debt');
$claimRead=$read->forOccurrence((int)$below['lesson_id'],(int)$below['version_id']);
dzn_p_assert(count($claimRead['claims'])===1&&$claimRead['claims'][0]['evidence_kind']==='advance_absence_claim','read model must expose the advance absence claim');
$intake->submitClaim((int)$below['lesson_id'],(int)$below['version_id'],array('claim_kind'=>'delivery_claim','reason_code'=>'teacher_statement','observed_at'=>gmdate('Y-m-d H:i:s'),'evidence_reference'=>'delivery-claim'),dzn_p_key('claim-delivery'));
$claimRead=$read->forOccurrence((int)$below['lesson_id'],(int)$below['version_id']);
dzn_p_assert(count($claimRead['claims'])===2,'conflicting human claims must coexist append-only');
dzn_p_assert($outcome((int)$below['lesson_id'])===null,'a Teacher claim alone must not settle delivery');

// ---------------------------------------------------------------------------
// 4. Administrative adjudication is the only Phase-P path that may publish truth.
// ---------------------------------------------------------------------------
$review=$occurrences['review'];
$intake->submitClaim((int)$review['lesson_id'],(int)$review['version_id'],array('claim_kind'=>'review_request','reason_code'=>'dispute','observed_at'=>gmdate('Y-m-d H:i:s'),'evidence_reference'=>'review-request'),dzn_p_key('review-request'));
$reviewCase=$caseOf((int)$review['lesson_id'],(int)$review['version_id']);
$adjudicated=$intake->adjudicate((int)$reviewCase->id,array('adjudication'=>'review_required'),dzn_p_key('adjudicate-review'));
dzn_p_assert((string)$adjudicated['state']==='adjudicated','administrative adjudication did not close the case');
$reviewOutcome=$outcome((int)$review['lesson_id']);
dzn_p_assert($reviewOutcome&&(string)$reviewOutcome->outcome_code==='review_required','explicit adjudication must delegate review_required to Phase O');
dzn_p_rejected(fn()=>$settlement->settleDelivered($reviewCase,true),'canonical_truth_conflict','settlement over an existing conflicting canonical outcome');
$teacherNd=$occurrences['teacher_nd'];
$intake->submitClaim((int)$teacherNd['lesson_id'],(int)$teacherNd['version_id'],array('claim_kind'=>'review_request','reason_code'=>'attendance_dispute','observed_at'=>gmdate('Y-m-d H:i:s'),'evidence_reference'=>'teacher-nd-claim'),dzn_p_key('teacher-nd-claim'));
$teacherNdCase=$caseOf((int)$teacherNd['lesson_id'],(int)$teacherNd['version_id']);
$intake->adjudicate((int)$teacherNdCase->id,array('adjudication'=>'teacher_non_delivery'),dzn_p_key('adjudicate-nd'));
$ndOutcome=$outcome((int)$teacherNd['lesson_id']);
dzn_p_assert($ndOutcome&&(string)$ndOutcome->outcome_code==='teacher_non_delivery','adjudication must delegate non-delivery truth to Phase O');
dzn_p_assert($obligationCount((int)$teacherNd['lesson_id'])===1,'Phase O must own the academy obligation created by non-delivery');
dzn_p_assert((string)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}dzn_lessons WHERE canonical_replacement_origin_lesson_id=%d",(int)$teacherNd['lesson_id']))==='0','Phase P must never create a Phase-M replacement');
$forced=$occurrences['forced'];
$intake->submitClaim((int)$forced['lesson_id'],(int)$forced['version_id'],array('claim_kind'=>'review_request','reason_code'=>'forced_settlement','observed_at'=>gmdate('Y-m-d H:i:s'),'evidence_reference'=>'forced'),dzn_p_key('forced-claim'));
$forcedCase=$caseOf((int)$forced['lesson_id'],(int)$forced['version_id']);
$forcedResult=$intake->adjudicate((int)$forcedCase->id,array('adjudication'=>'settle_delivered'),dzn_p_key('adjudicate-forced'));
dzn_p_assert((string)$forcedResult['state']==='settled','forced administrative settlement did not settle');
dzn_p_assert($outcome((int)$forced['lesson_id'])&&$lessonState((int)$forced['lesson_id'])==='completed','forced settlement must reach delivered + completed');
$noChange=$occurrences['no_change'];
$intake->submitClaim((int)$noChange['lesson_id'],(int)$noChange['version_id'],array('claim_kind'=>'review_request','reason_code'=>'no_change','observed_at'=>gmdate('Y-m-d H:i:s'),'evidence_reference'=>'no-change'),dzn_p_key('no-change-claim'));
$noChangeCase=$caseOf((int)$noChange['lesson_id'],(int)$noChange['version_id']);
$intake->adjudicate((int)$noChangeCase->id,array('adjudication'=>'record_no_change'),dzn_p_key('adjudicate-no-change'));
dzn_p_assert($outcome((int)$noChange['lesson_id'])===null,'record_no_change must not publish canonical truth');
dzn_p_assert((string)$caseOf((int)$noChange['lesson_id'],(int)$noChange['version_id'])->state==='closed_no_change','record_no_change must close the case without change');

// ---------------------------------------------------------------------------
// 5. Term closure and late evidence.
// ---------------------------------------------------------------------------
$late=$occurrences['late'];
$intake->submitClaim((int)$late['lesson_id'],(int)$late['version_id'],array('claim_kind'=>'advance_absence_claim','reason_code'=>'planned_absence','observed_at'=>gmdate('Y-m-d H:i:s'),'evidence_reference'=>'late-claim'),dzn_p_key('late-claim'));
$lessons->cancel((int)$late['lesson_id'],'authorised',dzn_p_evidence('cancel-late'),dzn_p_key('cancel-late'));
$terms->close((int)$chains['late']['term_id'],'current',dzn_p_evidence('term-close'),dzn_p_key('term-close'));
dzn_p_assert((string)$wpdb->get_var($wpdb->prepare("SELECT lifecycle_state FROM {$wpdb->prefix}dzn_terms WHERE id=%d",(int)$chains['late']['term_id']))==='closed','Term must close with an unresolved attendance case');
$lateProvider=$intake->ingestProviderEvidence((int)$late['lesson_id'],(int)$late['version_id'],array('provider_code'=>'google_meet','provider_event_key'=>'late-event-'.$runSuffix,'provider_payload_key'=>'late-payload-'.$runSuffix,'participant_role'=>'teacher','participant_identity_state'=>'resolved','verification_state'=>'verified','resolved_teacher_id'=>(int)$late['teacher_id'],'join_at_utc'=>$late['start'],'leave_at_utc'=>$late['end'],'observed_at'=>$late['end'],'provenance_reference'=>'late-prov-'.$runSuffix,'evidence_reference'=>'late-ref-'.$runSuffix),dzn_p_key('late-provider'));
dzn_p_assert($lateProvider['settlement']===null,'late evidence after Term closure must not settle automatically');
$lateRead=$read->forOccurrence((int)$late['lesson_id'],(int)$late['version_id']);
dzn_p_assert($lateRead['review']['term_closed']===true&&$lateRead['review']['late_evidence']===true,'late evidence must be flagged for administrative review');
dzn_p_assert($lateRead['evidence']['counts']['provider_interval']>=1,'late provider receipt must be retained');
dzn_p_assert($outcome((int)$late['lesson_id'])===null,'late evidence must not mutate canonical truth automatically');
// An administrator may still act after Term closure, but the claim is flagged late and never settles.
$lateClaim=$intake->submitClaim((int)$late['lesson_id'],(int)$late['version_id'],array('claim_kind'=>'advance_absence_claim','reason_code'=>'late_claim','observed_at'=>gmdate('Y-m-d H:i:s'),'evidence_reference'=>'late-2'),dzn_p_key('late-2'));
dzn_p_assert(empty($lateClaim['settlement']),'a post-closure claim must never settle automatically');
$lateReadAfter=$read->forOccurrence((int)$late['lesson_id'],(int)$late['version_id']);
dzn_p_assert($lateReadAfter['review']['late_evidence']===true&&$lateReadAfter['case']['state']==='ready_for_review','a post-closure claim must be flagged late and left for administrative review');
dzn_p_assert($outcome((int)$late['lesson_id'])===null,'a post-closure claim must not change canonical truth');

// ---------------------------------------------------------------------------
// 6. Idempotency and capability boundaries.
// ---------------------------------------------------------------------------
$replayIntent=array('provider_code'=>'google_meet','provider_event_key'=>'event-settle-teacher-'.$runSuffix,'provider_payload_key'=>'payload-settle-teacher-'.$runSuffix,'participant_role'=>'teacher','participant_identity_state'=>'resolved','verification_state'=>'verified','resolved_teacher_id'=>(int)$settle['teacher_id'],'join_at_utc'=>$settle['start'],'leave_at_utc'=>$plus($settle['start'],1200),'observed_at'=>$settle['end'],'provenance_reference'=>'prov-settle-teacher-'.$runSuffix,'evidence_reference'=>'ref-settle-teacher-'.$runSuffix);
$replay=$intake->ingestProviderEvidence((int)$settle['lesson_id'],(int)$settle['version_id'],$replayIntent,dzn_p_key('replay-1'));
dzn_p_assert(!empty($replay['idempotent']),'a fresh key for an already-received provider event must not duplicate contribution');
$conflictReplay=false;
try{$intake->ingestProviderEvidence((int)$settle['lesson_id'],(int)$settle['version_id'],array_merge($replayIntent,array('provider_payload_key'=>'payload-settle-teacher-changed-'.$runSuffix,'leave_at_utc'=>$plus($settle['start'],1260))),dzn_p_key('replay-2'));}catch(\Throwable$e){$conflictReplay=$e->getMessage()==='Idempotency conflict';}
dzn_p_assert($conflictReplay,'a changed payload for an existing provider event key must fail closed');
$subscriber=wp_insert_user(array('user_login'=>'dzn-2a2p-sub-'.wp_generate_uuid4(),'user_pass'=>wp_generate_password(32,true,true),'user_email'=>'sub-'.wp_generate_uuid4().'@phase-2a2p.invalid','role'=>'subscriber'));
$previous=get_current_user_id();wp_set_current_user((int)$subscriber);
try{
    dzn_p_rejected(fn()=>$intake->recordCutoverPolicy(gmdate('Y-m-d H:i:s'),dzn_p_key('sub-cutover')),'Unauthorized','non-administrator cutover policy');
    dzn_p_rejected(fn()=>$intake->adjudicate((int)$belowCase->id,array('adjudication'=>'record_no_change'),dzn_p_key('sub-adjudicate')),'Unauthorized','non-administrator adjudication');
    $deniedRead=false;try{$read->forOccurrence((int)$settle['lesson_id'],(int)$settle['version_id']);}catch(RuntimeException$e){$deniedRead=$e->getMessage()==='Unauthorized';}
    dzn_p_assert($deniedRead,'non-administrator protected attendance review read');
}finally{wp_set_current_user($previous);}
$firstEvidence=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}dzn_canonical_attendance_evidence WHERE provider_event_key_digest=%s",hash_hmac('sha256','canonical_attendance_event_key:event-settle-teacher',wp_salt('dzn_canonical_attendance'))));
dzn_p_assert($firstEvidence&&preg_match('/^[a-f0-9]{64}$/D',(string)$firstEvidence->provider_event_key_digest)===1,'provider event keys must persist only as keyed digests');
dzn_p_assert((string)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}dzn_canonical_attendance_evidence WHERE provider_event_key_digest=%s",'event-settle-teacher'))==='0','raw provider event keys must never persist');

echo "automatic_settlement=pass\nbelow_threshold_review=pass\nhuman_claims=pass\nadministrative_adjudication=pass\nterm_closure_and_late_evidence=pass\nidempotency_and_capability=pass\nprotected_read=pass\nPhase 2A.2-P authority runtime passed\n";
