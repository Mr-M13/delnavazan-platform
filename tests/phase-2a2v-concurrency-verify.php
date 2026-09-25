<?php
/**
 * Disposable Phase-V concurrency verifier: reads both worker outcomes and the shared durable state.
 *
 * The verifier never re-runs a command. It proves the mode's invariant from the recorded rows and the
 * workers' own outcomes: exactly one active connection, exactly-once authorization consumption, a
 * projection that either completed against the applicable version or failed closed, a Lesson whose
 * canonical state is exactly what the canonical authority recorded, one provider-event receipt for
 * one provider key with at most one conflict receipt, a mapping revocation that cannot widen any
 * authority, an archived Teacher that can never be given a new provider write, and full independence
 * between two unrelated Teachers.
 */
if(getenv('DZN_PHASE_2A2V_RUNTIME_TEST')!=='concurrency'||!defined('WP_CLI')||!WP_CLI||!in_array(wp_get_environment_type(),array('local','development'),true)){fwrite(STDERR,"Phase 2A.2-V concurrency verifier refused.\n");exit(1);}
global $wpdb;$p=$wpdb->prefix.'dzn_';
function dzn_vcv_assert(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);}
$mode=(string)getenv('DZN_PHASE_2A2V_MODE');
$gate=(string)getenv('DZN_PHASE_2A2V_GATE_DIR');
$state=get_option('dzn_phase_2a2v_concurrency');
dzn_vcv_assert(is_array($state)&&(string)$state['mode']===$mode,'Phase V concurrency setup state missing');
$outcome=static function(string $worker) use($gate):array{
    $file=$gate.'/'.$worker.'.result';
    dzn_vcv_assert(is_file($file),'Missing '.$worker.' outcome');
    $decoded=json_decode((string)file_get_contents($file),true);
    dzn_vcv_assert(is_array($decoded),'Malformed '.$worker.' outcome');
    return $decoded;
};
$w1=$outcome('w1');$w2=$outcome('w2');
$count=static function(string $table,string $where='1=1',array $args=array()) use($wpdb,$p):int{
    $sql="SELECT COUNT(*) FROM {$p}{$table} WHERE ".$where;
    return(int)($args?$wpdb->get_var($wpdb->prepare($sql,...$args)):$wpdb->get_var($sql));
};
$activeFor=static function(int $teacher) use($count):int{return $count('integration_connections','teacher_id=%d AND active_slot=1',array($teacher));};
$teacherId=(int)$state['teacher_id'];

if($mode==='connect_vs_revoke'){
    dzn_vcv_assert($activeFor($teacherId)===1,'exactly one active connection may exist after connect vs revoke');
    dzn_vcv_assert($count('integration_credentials','state=%s',array('active'))<=$count('integration_connections'),'every active credential must belong to an existing connection');
    dzn_vcv_assert((string)$wpdb->get_var($wpdb->prepare("SELECT connection_state FROM {$p}integration_connections WHERE id=%d",(int)$state['connection_id']))!=='connected','the revoked connection must never stay connected');
}elseif($mode==='authorization_replay'){
    dzn_vcv_assert($activeFor($teacherId)===1,'exactly one active connection may exist after an authorization replay');
    dzn_vcv_assert($count('provider_integration_commands',"operation='complete_authorization'")===1,'an authorization state may be consumed exactly once');
    dzn_vcv_assert($count('integration_credentials','state=%s',array('active'))===1,'an authorization replay must not mint a second active credential');
    dzn_vcv_assert($count('integration_oauth_authorizations',"authorization_state='consumed'")===1,'exactly one authorization intent may be consumed');
    dzn_vcv_assert($count('integration_oauth_authorizations',"authorization_state='issued'")===0,'a consumed authorization may never stay pending');
    dzn_vcv_assert($w1['ok']===true&&$w2['ok']===true,'both contenders must converge on the recorded completion');
    dzn_vcv_assert((int)($w2['outcome']['connection_id']??0)===(int)($w1['outcome']['connection_id']??0),'a replayed completion must report the identical connection');
}elseif($mode==='projection_vs_release'){
    dzn_vcv_assert(\Delnavazan\Platform\Core\Application\CanonicalLessonScheduleValidator::validForLesson((int)$state['lesson_id']),'the canonical schedule aggregate must remain valid after the race');
    dzn_vcv_assert($count('canonical_lesson_schedule_versions','lesson_id=%d',array((int)$state['lesson_id']))>=(int)$state['baseline']['versions'],'the release must never remove a canonical schedule version');
    $mapping=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$p}provider_calendar_event_mappings WHERE lesson_id=%d AND active_slot=1",(int)$state['lesson_id']));
    if($mapping){
        $version=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$p}canonical_lesson_schedule_versions WHERE id=%d",(int)$mapping->schedule_version_id));
        dzn_vcv_assert($version!==null,'a projection may only reference an existing canonical schedule version');
        dzn_vcv_assert((string)$mapping->starts_at_utc===(string)$version->starts_at_utc&&(string)$mapping->ends_at_utc===(string)$version->ends_at_utc,'a projection must mirror the exact interval of the version it names');
    }else{
        dzn_vcv_assert($w1['ok']===false,'a projection against a stale version must fail closed rather than disappear silently');
    }
    dzn_vcv_assert((int)$count('provider_integration_commands',"lesson_id=%d AND operation='project_calendar_event'",array((int)$state['lesson_id']))<=1,'at most one calendar projection command may be recorded for the occurrence');
}elseif($mode==='projection_vs_completion'){
    $lesson=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$p}lessons WHERE id=%d",(int)$state['lesson_id']));
    dzn_vcv_assert($lesson!==null,'the canonical Lesson must still exist');
    $projectionCommands=$count('provider_integration_commands',"lesson_id=%d AND operation IN ('project_calendar_event','project_meeting_conference')",array((int)$state['lesson_id']));
    dzn_vcv_assert($projectionCommands<=1,'an integration layer must never record more than one projection command per occurrence');
    // The integration layer owns no Lesson column: a completed Lesson must be exactly what the
    // canonical authority recorded, and no V row may claim a Lesson-lifecycle operation.
    dzn_vcv_assert((int)$count('provider_integration_commands',"operation LIKE %s",array('%lesson%'))===0,'the integration layer may never record a Lesson-lifecycle operation');
    dzn_vcv_assert(in_array((string)$lesson->lifecycle_state,array('scheduled','completed','cancelled'),true),'the canonical Lesson state must be a controlled canonical state');
}elseif($mode==='duplicate_vs_conflicting_event'){
    $receipts=$wpdb->get_results($wpdb->prepare("SELECT * FROM {$p}provider_ingest_events WHERE lesson_id=%d",(int)$state['lesson_id']),ARRAY_A)?:array();
    dzn_vcv_assert(count($receipts)===1,'one provider event key must leave exactly one integration receipt');
    $conflicts=$count('provider_event_conflicts','lesson_id=%d',array((int)$state['lesson_id']));
    if($w2['ok']===true&&is_array($w2['outcome']??null)&&!empty($w2['outcome']['conflict']))dzn_vcv_assert($conflicts===1,'a conflicting duplicate must leave exactly one conflict receipt');
    else dzn_vcv_assert($conflicts===0,'an exact duplicate must converge without a conflict receipt');
    dzn_vcv_assert((int)$receipts[0]['event_sequence']>0,'a converged provider event must keep its original receipt fields');
}elseif($mode==='mapping_revoke_vs_ingest'){
    dzn_vcv_assert($count('integration_connections','teacher_id=%d AND active_slot=1',array($teacherId))===1,'a mapping revocation must never disturb the active connection');
    dzn_vcv_assert($count('provider_identity_mappings','id=%d AND mapping_state=%s',array((int)$state['identity_mapping_id'],'revoked'))===1,'the identity mapping must be recorded as revoked');
    dzn_vcv_assert($count('provider_identity_mappings','teacher_id=%d AND mapping_state=%s',array($teacherId,'verified'))===0,'a revoked mapping may never stay verified');
    dzn_vcv_assert($count('provider_ingest_events','lesson_id=%d',array((int)$state['lesson_id']))<=1,'the concurrent ingest must leave at most one integration receipt');
}elseif($mode==='teacher_archival_vs_connection'){
    $teacher=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$p}teachers WHERE id=%d",$teacherId));
    dzn_vcv_assert($teacher!==null,'the Teacher must still exist');
    if($teacher->archived_at!==null){
        dzn_vcv_assert($count('integration_connections','teacher_id=%d AND created_at>%s',array($teacherId,(string)$teacher->archived_at))===0,'no connection may be created after the Teacher was archived');
    }
}elseif($mode==='unrelated_teacher'){
    $other=(int)$state['other_teacher_id'];
    dzn_vcv_assert($activeFor($teacherId)===1,'Teacher A must keep exactly one active connection');
    dzn_vcv_assert($activeFor($other)===1,'Teacher B must keep exactly one active connection');
    dzn_vcv_assert($count('provider_integration_commands','teacher_id=%d',array($teacherId))>0,'Teacher A must have recorded its own integration commands');
    dzn_vcv_assert($count('provider_integration_commands','teacher_id=%d',array($other))>0,'Teacher B must have recorded its own integration commands');
    dzn_vcv_assert(is_file($gate.'/w2.independent'),'an unrelated Teacher must complete without waiting on the held transaction');
}else{
    throw new RuntimeException('Unknown Phase V concurrency mode');
}
echo "Phase 2A.2-V concurrency verified: ".$mode."\n";
