<?php
/** Verify the committed database state of one gated Phase-Q continuation race. */
if(getenv('DZN_PHASE_2A2Q_RUNTIME_TEST')!=='concurrency'||!defined('WP_CLI')||!WP_CLI){fwrite(STDERR,"Phase 2A.2-Q concurrency verifier refused.\n");exit(1);}
global $wpdb;$p=$wpdb->prefix.'dzn_';
$mode=(string)getenv('DZN_PHASE_2A2Q_MODE');
$state=get_option('dzn_phase_2a2q_concurrency_state');
if(!is_array($state)||$mode==='')throw new RuntimeException('Phase 2A.2-Q concurrency state required');
function dzn_qv_assert(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);}
$gate=(string)getenv('DZN_PHASE_2A2Q_GATE_DIR');
$workerResult=static function(string $worker) use($gate):array{
    $raw=trim((string)@file_get_contents($gate.'/'.$worker.'.result'));
    if($raw==='')throw new RuntimeException('Phase-Q race worker result unavailable: '.$worker);
    $decoded=json_decode($raw,true);
    if(!is_array($decoded))throw new RuntimeException('Phase-Q race worker result malformed: '.$worker);
    return $decoded;
};
$caseOf=static function(int $lessonId) use($wpdb,$p):?object{return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$p}canonical_continuation_cases WHERE intro_lesson_id=%d",$lessonId));};
$reservationOf=static function(int $caseId) use($wpdb,$p):?object{return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$p}canonical_continuation_reservations WHERE continuation_case_id=%d",$caseId));};
$decisionCount=static function(int $caseId) use($wpdb,$p):int{return(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$p}canonical_continuation_decisions WHERE continuation_case_id=%d",$caseId));};
$activeHoldCount=static function(int $teacherId) use($wpdb,$p):int{return(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$p}canonical_continuation_reservations WHERE teacher_id=%d AND state='active' AND expires_at>%s",$teacherId,gmdate('Y-m-d H:i:s')));};
$firstLesson=(int)$state['first']['lesson_id'];$secondLesson=(int)$state['second']['lesson_id'];
$firstTeacher=(int)$state['first']['teacher_id'];
switch($mode){
    case 'continue_exact_replay':
        $w1=$workerResult('w1');$w2=$workerResult('w2');
        dzn_qv_assert($w1['ok']===true&&$w2['ok']===true,'an exact concurrent continuation replay must be accepted by both workers');
        $case=$caseOf($firstLesson);
        dzn_qv_assert($case&&$decisionCount((int)$case->id)===1,'an exact concurrent replay must record exactly one decision');
        $reservation=$reservationOf((int)$case->id);
        dzn_qv_assert($reservation&&$activeHoldCount($firstTeacher)===1,'an exact concurrent replay must hold capacity exactly once');
        $reservationId=(int)$reservation->id;
        $ids=array();
        foreach(array($w1,$w2)as$payload){
            $value=$payload['result']['reservation_id']??$payload['result']['reservation']['reservation_id']??null;
            if($value!==null)$ids[]=(int)$value;
        }
        dzn_qv_assert(count($ids)===2,'both exact results must expose a reservation identifier');
        dzn_qv_assert($ids[0]===$ids[1]&&$ids[0]===$reservationId,'both exact concurrent responses must reference the same durable reservation');
        break;
    case 'command_key_changed_decision':
        $w1=$workerResult('w1');$w2=$workerResult('w2');
        dzn_qv_assert($w1['ok']===true&&$w2['ok']===false&&$w2['message']==='Idempotency conflict','a changed decision under one command key must fail idempotency conflict');
        $case=$caseOf($firstLesson);
        dzn_qv_assert($case&&$decisionCount((int)$case->id)===1,'a rejected changed-decision replay must leave exactly one decision');
        break;
    case 'two_student_decisions':
        $w1=$workerResult('w1');$w2=$workerResult('w2');
        dzn_qv_assert($w1['ok']===true&&$w2['ok']===true,'two Student decisions with distinct command keys must both be recorded');
        $case=$caseOf($firstLesson);
        dzn_qv_assert($case&&$decisionCount((int)$case->id)===2,'both Student decisions must be retained append-only');
        dzn_qv_assert((string)$case->current_decision==='contact_me','the later serialized decision must be the current continuation decision');
        $reservation=$reservationOf((int)$case->id);
        dzn_qv_assert($reservation&&(string)$reservation->state==='released','a later non-continuing decision must release the earlier hold');
        dzn_qv_assert($activeHoldCount($firstTeacher)===0,'a released hold must stop blocking capacity');
        break;
    case 'continue_vs_teacher_exception':
        $w1=$workerResult('w1');$w2=$workerResult('w2');
        dzn_qv_assert($w1['ok']===true,'the Student continuation must succeed');
        dzn_qv_assert($w2['ok']===true,'the Teacher match exception must succeed on its own principal');
        $case=$caseOf($firstLesson);
        dzn_qv_assert($case&&(string)$case->current_decision==='teacher_unsuitable','the Teacher exception must end the ordinary continuation state');
        $reservation=$reservationOf((int)$case->id);
        dzn_qv_assert($reservation&&(string)$reservation->state==='released','the Teacher exception must stop holding capacity');
        dzn_qv_assert($activeHoldCount($firstTeacher)===0,'no capacity may leak after a Teacher match exception');
        dzn_qv_assert((int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$p}canonical_continuation_interventions WHERE continuation_case_id=%d AND reason_code='teacher_match_unsuitable'",(int)$case->id))===1,'the Teacher exception must record exactly one administrator intervention');
        break;
    case 'competing_hold_same_slot':
        $ok=0;$losers=0;
        foreach(array('w1','w2')as$worker){$r=json_decode(trim((string)@file_get_contents($gate.'/'.$worker.'.result')),true);if(!is_array($r))continue;if($r['ok']===true)$ok++;elseif($r['message']==='teacher_slot_conflict')$losers++;}
        dzn_qv_assert($ok===1&&$losers===1,'exactly one Student may hold the same exclusive Teacher slot');
        dzn_qv_assert($activeHoldCount($firstTeacher)===1,'exactly one active hold may remain after the race');
        dzn_qv_assert((int)$wpdb->get_var("SELECT COUNT(*) FROM {$p}canonical_continuation_reservations")===1,'a losing hold attempt must leave no partial reservation');
        break;
    case 'unrelated_teachers':
        $w1=$workerResult('w1');$w2=$workerResult('w2');
        dzn_qv_assert($w1['ok']===true&&$w2['ok']===true,'unrelated Teachers must not block one another');
        $caseOne=$caseOf($firstLesson);$caseTwo=$caseOf($secondLesson);
        dzn_qv_assert($caseOne&&$caseTwo,'both unrelated continuation cases must exist');
        dzn_qv_assert($reservationOf((int)$caseOne->id)!==null&&$reservationOf((int)$caseTwo->id)!==null,'both unrelated holds must be recorded');
        break;
    default:
        throw new RuntimeException('Unknown Phase-Q race mode: '.$mode);
}
echo 'verified='.$mode."\n";
