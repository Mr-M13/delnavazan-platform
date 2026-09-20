<?php
/** Prepare one deterministic gated Phase-Q continuation/slot race on synthetic production-path data. */
if(getenv('DZN_PHASE_2A2Q_RUNTIME_TEST')!=='concurrency'||!defined('WP_CLI')||!WP_CLI){fwrite(STDERR,"Phase 2A.2-Q concurrency setup refused.\n");exit(1);}
use Delnavazan\Platform\Core\Application\{CanonicalEnrolmentLifecycleService,CanonicalLessonAuthorityService,CanonicalLessonScheduleService,CanonicalTermAuthorityService,LessonScheduleService,LessonService,TeacherAcceptingStateService,TeacherAssignmentService,TeacherAvailabilityService,TeachingEligibilityService};

global $wpdb;$p=$wpdb->prefix.'dzn_';
$mode=(string)getenv('DZN_PHASE_2A2Q_MODE');
$fixture=get_option('dzn_phase_2a2j_fixture');
if(!is_array($fixture)||count($fixture['sources']??array())<3)throw new RuntimeException('Phase-J fixture required');
$sources=$fixture['sources'];
$evidence=static fn(string $reference):array=>array('evidence_channel'=>'staff_record','evidence_reference'=>$reference,'evidence_at'=>gmdate('Y-m-d H:i:s'));
$key=static fn(string $label):string=>'dzn-2a2q-race-'.$label.'-'.wp_generate_uuid4();
$admin=get_current_user_id();
$introOf=function(int $index,int $sequence,string $label) use($sources,$wpdb,$p):array{
    $src=$sources[$index%count($sources)];
    $lessonId=(int)(new LessonService())->create(array('student_id'=>(int)$src['student_id'],'teacher_id'=>(int)$src['teacher_id'],'course_id'=>(int)$src['course_id'],'lesson_type'=>'introductory','status'=>'draft'));
    $wall=gmdate('Y-m-d H:i:s',strtotime('-3 days')-($sequence*3600));
    (new LessonScheduleService())->initial($lessonId,array('schedule_timezone'=>'UTC','local_wall_date'=>substr($wall,0,10),'local_wall_time'=>substr($wall,11,8),'reason'=>$label));
    $occurrence=$wpdb->get_row($wpdb->prepare("SELECT v.* FROM {$p}lesson_schedule_versions v INNER JOIN {$p}lessons l ON l.id=v.lesson_id AND l.current_schedule_version_id=v.id WHERE v.lesson_id=%d AND v.superseded_at IS NULL",$lessonId));
    $principal=(int)$wpdb->get_var($wpdb->prepare("SELECT wordpress_user_id FROM {$p}student_principal_links WHERE student_id=%d AND status='active' AND active_slot=1 LIMIT 1",(int)$src['student_id']));
    return array('lesson_id'=>$lessonId,'version_id'=>(int)$occurrence->id,'start'=>(string)$occurrence->starts_at_utc,'end'=>(string)$occurrence->ends_at_utc,'student_id'=>(int)$src['student_id'],'teacher_id'=>(int)$src['teacher_id'],'course_id'=>(int)$src['course_id'],'principal'=>$principal);
};
$first=$introOf(0,1,'race-one');$second=$introOf(1,2,'race-two');
// Disposable harness: clear Phase-Q storage and give the baseline scenarios distinct Teachers so
// only the deliberately conflicting mode shares a Teacher and slot.
foreach(array('canonical_continuation_commands','canonical_continuation_interventions','canonical_continuation_reservations','canonical_continuation_decisions','canonical_continuation_cases')as$qTable)$wpdb->query("DELETE FROM {$p}{$qTable}");
$teacherUser=(int)$wpdb->get_var($wpdb->prepare("SELECT wordpress_user_id FROM {$p}teacher_principal_links WHERE teacher_id=%d AND status='active' AND revoked_at IS NULL LIMIT 1",(int)$first['teacher_id']));
if($teacherUser>0)(new \WP_User($teacherUser))->add_cap('dzn_submit_own_continuation_match_exception');
if($mode!=='competing_hold_same_slot'&&(int)$first['teacher_id']===(int)$second['teacher_id']){
    $alternative=(int)($fixture['teachers'][2]??0);
    if($alternative<1||$alternative===(int)$first['teacher_id'])throw new RuntimeException('A distinct synthetic Teacher is required for the Phase-Q race fixture');
    $wpdb->update($p.'lessons',array('teacher_id'=>$alternative),array('id'=>(int)$second['lesson_id']));
    $second['teacher_id']=$alternative;
}
$state=array('mode'=>$mode,'at'=>gmdate('Y-m-d H:i:s'),'first'=>$first,'second'=>$second,'teacher_user'=>$teacherUser,
    'keys'=>array('w1'=>$key('w1'),'w2'=>$key('w2')),'references'=>array('w1'=>'race-w1','w2'=>'race-w2'));
$derived=static function(array $occurrence) use($wpdb,$p):array{
    $course=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$p}courses WHERE id=%d",(int)$occurrence['course_id']));
    return Delnavazan\Platform\Core\Application\CanonicalContinuationRule::expectedFirstRegularSlot($occurrence,$course);
};
switch($mode){
    case 'continue_exact_replay':
        $sharedExact=$key('shared');
        $state['actions']=array('w1'=>array('action'=>'continue','target'=>'first'),'w2'=>array('action'=>'continue','target'=>'first'));
        $state['keys']=array('w1'=>$sharedExact,'w2'=>$sharedExact);
        $state['references']=array('w1'=>'race-shared','w2'=>'race-shared');
        $state['hooks']=array('w1'=>'dzn_phase_2a2q_after_decision_insert','w2'=>null);
        break;
    case 'command_key_changed_decision':
        $shared=$key('shared');
        $state['actions']=array('w1'=>array('action'=>'continue','target'=>'first'),'w2'=>array('action'=>'contact','target'=>'first'));
        $state['keys']=array('w1'=>$shared,'w2'=>$shared);
        $state['references']=array('w1'=>'race-shared','w2'=>'race-shared');
        $state['hooks']=array('w1'=>'dzn_phase_2a2q_after_decision_insert','w2'=>null);
        break;
    case 'two_student_decisions':
        $state['actions']=array('w1'=>array('action'=>'continue','target'=>'first'),'w2'=>array('action'=>'contact','target'=>'first'));
        $state['hooks']=array('w1'=>'dzn_phase_2a2q_after_reservation_insert','w2'=>null);
        break;
    case 'continue_vs_teacher_exception':
        $state['actions']=array('w1'=>array('action'=>'continue','target'=>'first'),'w2'=>array('action'=>'teacher_exception','target'=>'first'));
        $state['hooks']=array('w1'=>'dzn_phase_2a2q_after_reservation_insert','w2'=>null);
        break;
    case 'competing_hold_same_slot':
        // Both Students expect the SAME Teacher and the SAME first regular slot.
        $wpdb->update($p.'lessons',array('teacher_id'=>(int)$first['teacher_id']),array('id'=>(int)$second['lesson_id']));
        $wpdb->update($p.'lesson_schedule_versions',array('starts_at_utc'=>$first['start'],'ends_at_utc'=>$first['end'],'local_wall_date'=>$wpdb->get_var($wpdb->prepare("SELECT local_wall_date FROM {$p}lesson_schedule_versions WHERE lesson_id=%d",(int)$first['lesson_id'])),'local_wall_time'=>$wpdb->get_var($wpdb->prepare("SELECT local_wall_time FROM {$p}lesson_schedule_versions WHERE lesson_id=%d",(int)$first['lesson_id']))),array('lesson_id'=>(int)$second['lesson_id']));
        $state['actions']=array('w1'=>array('action'=>'continue','target'=>'first'),'w2'=>array('action'=>'continue','target'=>'second'));
        $state['hooks']=array('w1'=>'dzn_phase_2a2q_teacher_root_held','w2'=>null);
        break;
    case 'unrelated_teachers':
        $state['actions']=array('w1'=>array('action'=>'continue','target'=>'first'),'w2'=>array('action'=>'continue','target'=>'second'));
        $state['hooks']=array('w1'=>'dzn_phase_2a2q_after_reservation_insert','w2'=>null);
        break;
    default:
        throw new RuntimeException('Unknown Phase-Q race mode: '.$mode);
}
update_option('dzn_phase_2a2q_concurrency_state',$state,false);
echo 'prepared='.$mode."\n";
