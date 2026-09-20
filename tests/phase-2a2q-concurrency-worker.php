<?php
/** One gated Phase-Q continuation race worker; worker 1 holds its locks until the runner releases the gate. */
if(getenv('DZN_PHASE_2A2Q_RUNTIME_TEST')!=='concurrency'||!defined('WP_CLI')||!WP_CLI){fwrite(STDERR,"Phase 2A.2-Q concurrency worker refused.\n");exit(1);}
use Delnavazan\Platform\Core\Application\{CanonicalContinuationService,CanonicalContinuationValidator};

global $wpdb;
$state=get_option('dzn_phase_2a2q_concurrency_state');
$worker=(string)getenv('DZN_PHASE_2A2Q_WORKER');
$gate=(string)getenv('DZN_PHASE_2A2Q_GATE_DIR');
if(!is_array($state)||!in_array($worker,array('w1','w2'),true)||$gate==='')throw new RuntimeException('Phase 2A.2-Q concurrency state required');
$entry=(array)($state['actions'][$worker]??array());
$action=(string)($entry['action']??'');
$mark=static fn(string $name,string $value='')=>file_put_contents($gate.'/'.$name,$value);
$hook=(string)($state['hooks'][$worker]??'');
if($worker==='w1'&&$hook!==''){
    add_action($hook,static function() use($mark,$gate,$worker,$hook):void{
        $mark($worker.'.locked',$hook);
        for($i=0;$i<1200&&!is_file($gate.'/release');$i++)usleep(100000);
        if(!is_file($gate.'/release'))throw new RuntimeException('gate timeout');
    },10,3);
}
$mark($worker.'.connection',(string)$GLOBALS['wpdb']->get_var('SELECT CONNECTION_ID()'));
$mark($worker.'.started');
$occurrence=(array)($state[$entry['target']??'first']??$state['first']);
$key=(string)($state['keys'][$worker]??wp_generate_uuid4());
if($key==='')$key=(string)$state['keys']['w1'];
$reference=(string)($state['references'][$worker]??('race-'.$worker));
$at=(string)$state['at'];
try{
    $svc=new CanonicalContinuationService();
    if($action==='teacher_exception')wp_set_current_user((int)$state['teacher_user']);
    else wp_set_current_user((int)$occurrence['principal']);
    $evidence=array('evidence_channel'=>'authenticated_platform','evidence_reference'=>'race-'.$action.'-'.$reference,'evidence_at'=>$at);
    $result=match($action){
        'continue'=>$svc->continueWithTeacher((int)$occurrence['lesson_id'],$evidence,$key),
        'contact'=>$svc->requestContact((int)$occurrence['lesson_id'],$evidence,$key),
        'teacher_exception'=>$svc->markMatchNeedsAdmin((int)$occurrence['lesson_id'],$evidence,$key),
        default=>throw new RuntimeException('Unknown Phase-Q race action: '.$action),
    };
    $mark($worker.'.result',wp_json_encode(array('ok'=>true,'action'=>$action,'result'=>is_array($result)?$result:array('value'=>$result)))."\n");
}catch(Throwable$exception){
    $mark($worker.'.result',wp_json_encode(array('ok'=>false,'action'=>$action,'class'=>$exception::class,'message'=>$exception->getMessage()))."\n");
}
$mark($worker.'.finished');
