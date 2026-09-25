<?php
/**
 * Disposable Phase-T concurrency worker. Synthetic local data only.
 *
 * The holder opens a transaction that locks the R1 commercial account root for its Student and gates on
 * the runner's release file; the contender then attempts the opposing operation. Exactly one of every
 * opposing pair may dispatch, the loser must be refused durably with `dispatch_in_flight`, and an
 * expired lease may be taken over exactly once.
 */
if(getenv('DZN_PHASE_2A2T_RUNTIME_TEST')!=='concurrency'||!defined('WP_CLI')||!WP_CLI||!in_array(wp_get_environment_type(),array('local','development'),true)){fwrite(STDERR,"Phase 2A.2-T concurrency worker refused.\n");exit(1);}
use Delnavazan\Platform\Core\Application\PaymentExecution\{PaymentExecutionDispatchSeal,PaymentExecutionService,PaymentExecutionSupport,ProviderReferenceClaims};
use Delnavazan\Platform\Integrations\Payment\ContractPaymentAdapter;
global $wpdb;$p=$wpdb->prefix.'dzn_';
function dzn_tcw_assert(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);}
$gate=(string)getenv('DZN_PHASE_2A2T_GATE_DIR');
$worker=(string)getenv('DZN_PHASE_2A2T_WORKER');
$fixture=get_option('dzn_phase_2a2t_concurrency_fixture');
dzn_tcw_assert(is_array($fixture)&&count($fixture['rows']??array())>=2,'the concurrency fixture must exist');
wp_set_current_user(1);
$row=$worker==='w1'?$fixture['rows'][0]:$fixture['rows'][1];
$adapter=new ContractPaymentAdapter();
\Delnavazan\Platform\Core\Application\PaymentExecution\PaymentProviderRegistry::registerExecutionPort($adapter);
$service=new PaymentExecutionService();
$claims=new ProviderReferenceClaims('acct-conc',array('student'=>$row['references']['student'],'obligation'=>$row['references']['obligation'],'collection_intent'=>$row['references']['collection_intent']));
$target=$worker==='w1'?$fixture['rows'][0]:$fixture['rows'][1];
$operation=((string)$fixture['mode']==='submit_vs_cancel'||(string)$fixture['mode']==='submit_vs_cancel_in_flight')&&$worker==='w2'?'cancel':'submit';
if($gate!==''&&is_dir($gate)){
    // Holder: take the account-root lock and hold it until the runner releases the gate.
    $repository=new \Delnavazan\Platform\Core\Infrastructure\Repository\PaymentExecutionRepository();
    $repository->begin();
    PaymentExecutionSupport::lockAccountRoot((int)$row['student_id'],1);
    file_put_contents($gate.'/'.$worker.'.started','1');
    $i=0;while(!file_exists($gate.'/release')&&$i<1200){usleep(100000);$i++;}
    $repository->commit();
}
$outcome=null;$caught=null;
try{
    $outcome=$operation==='cancel'
        ?$service->cancelCollection((int)$row['intent_id'],array('provider_account_id'=>(int)$fixture['account_id'],'obligation_id'=>(int)$row['obligation_id']),$claims,'dzn-2a2tc-'.$fixture['mode'].'-'.$worker)
        :$service->submitCollection(array('provider_account_id'=>(int)$fixture['account_id'],'obligation_id'=>(int)$row['obligation_id'],'collection_intent_id'=>(int)$row['intent_id']),$claims,'dzn-2a2tc-'.$fixture['mode'].'-'.$worker);
}catch(Throwable$e){$caught=$e;}
$record=array('worker'=>$worker,'operation'=>$operation,'outcome'=>$outcome,'error'=>$caught?$caught->getMessage():null,'mutating_calls'=>$adapter->mutatingCalls(),'reconcile_calls'=>$adapter->calls('reconcile'),'total_calls'=>$adapter->totalCalls(),'at'=>gmdate('Y-m-d H:i:s'));
if($gate!=='')file_put_contents($gate.'/'.$worker.'.json',(string)wp_json_encode($record));
if($caught!==null&&$caught->getMessage()!==\Delnavazan\Platform\Core\Application\PaymentExecution\PaymentExecutionRule::COMMAND_CONFLICT_REASON&&$caught->getMessage()!=='dispatch_in_flight')throw $caught;
echo "phase-2a2t-concurrency-worker ".$worker." done\n";
