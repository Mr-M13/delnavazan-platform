<?php
/** Verify the committed database state of one gated Phase-P attendance intake race. */
if(getenv('DZN_PHASE_2A2P_RUNTIME_TEST')!=='concurrency'||!defined('WP_CLI')||!WP_CLI){fwrite(STDERR,"Phase 2A.2-P concurrency verifier refused.\n");exit(1);}
global $wpdb;$p=$wpdb->prefix.'dzn_';
$mode=(string)getenv('DZN_PHASE_2A2P_MODE');
$state=get_option('dzn_phase_2a2p_concurrency_state');
if(!is_array($state)||$mode==='')throw new RuntimeException('Phase 2A.2-P concurrency state required');
function dzn_pv_assert(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);}
$evidenceCount=static function(int $lessonId) use($wpdb,$p):int{return(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$p}canonical_attendance_evidence WHERE lesson_id=%d",$lessonId));};
$providerCount=static function(int $lessonId) use($wpdb,$p):int{return(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$p}canonical_attendance_evidence WHERE lesson_id=%d AND evidence_kind='provider_interval'",$lessonId));};
$caseFor=static function(int $lessonId) use($wpdb,$p):?object{return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$p}canonical_attendance_cases WHERE lesson_id=%d ORDER BY id LIMIT 1",$lessonId));};
$outcome=static function(int $lessonId) use($wpdb,$p):int{return(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$p}canonical_lesson_delivery_outcomes WHERE lesson_id=%d",$lessonId));};
$first=(int)$state['first']['lesson_id'];$second=(int)$state['second']['lesson_id'];
switch($mode){
    case 'same_event_same_payload':
        dzn_pv_assert($providerCount($first)===1,'identical provider event race must retain exactly one evidence row');
        dzn_pv_assert($outcome($first)===0,'a race on an unproven occurrence must not create canonical truth');
        break;
    case 'same_event_changed_payload':
        dzn_pv_assert($providerCount($first)===1,'changed-payload race must retain exactly the first provider event');
        break;
    case 'claim_vs_adjudication':
        $case=$caseFor($first);
        dzn_pv_assert($case&&in_array((string)$case->state,array('closed_no_change','adjudicated'),true),'claim/adjudication race must converge on an adjudicated case');
        dzn_pv_assert($evidenceCount($first)>=1,'claim evidence must be retained after adjudication');
        break;
    case 'adjudication_vs_adjudication':
        $case=$caseFor($first);
        dzn_pv_assert($case&&(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$p}canonical_attendance_decisions WHERE case_id=%d AND decision_kind='admin_adjudication'",(int)$case->id))>=1,'concurrent adjudication must record a decision');
        dzn_pv_assert($outcome($first)===0,'record_no_change adjudication must not create canonical truth');
        break;
    case 'unrelated_lessons':
        dzn_pv_assert($evidenceCount($first)===1&&$evidenceCount($second)===1,'unrelated Lessons must each record exactly one claim');
        break;
    default:
        throw new RuntimeException('Unknown Phase-P race mode: '.$mode);
}
echo 'verified='.$mode."\n";
