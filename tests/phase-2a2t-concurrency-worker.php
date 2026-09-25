<?php
/**
 * Disposable Phase-T concurrency worker. Synthetic local data only.
 *
 * Two families of race share one worker:
 *
 * - the execution race: the holder opens a transaction that locks the R1 commercial account root for its
 *   Student and gates on the runner's release file; the contender then attempts the opposing operation.
 *   Exactly one of every opposing pair may dispatch, the loser must be refused durably with
 *   `dispatch_in_flight`, and an expired lease may be taken over exactly once;
 * - [C8-3] the duplicate-delivery race (`duplicate_webhook`, `conflicting_duplicate_webhook`): both workers
 *   announce themselves in the gate directory, wait for each other, and then submit one provider event
 *   identity at the same moment. The unique `provider_event` index and the recorded immutable fact digest
 *   — never a read-then-insert — decide which worker owns the event and whether the other converges or
 *   records the controlled conflict.
 */
if(getenv('DZN_PHASE_2A2T_RUNTIME_TEST')!=='concurrency'||!defined('WP_CLI')||!WP_CLI||!in_array(wp_get_environment_type(),array('local','development'),true)){fwrite(STDERR,"Phase 2A.2-T concurrency worker refused.\n");exit(1);}
use Delnavazan\Platform\Core\Application\PaymentExecution\{PaymentEventIntakeService,PaymentExecutionDispatchSeal,PaymentExecutionService,PaymentExecutionSupport,PaymentProviderRegistry,ProviderReferenceClaims};
use Delnavazan\Platform\Integrations\Payment\ContractPaymentAdapter;
use Delnavazan\Platform\Integrations\Payment\Stripe\{StripeEventTranslator,StripeSignatureVerifier};
global $wpdb;$p=$wpdb->prefix.'dzn_';
function dzn_tcw_assert(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);}
$gate=(string)getenv('DZN_PHASE_2A2T_GATE_DIR');
$worker=(string)getenv('DZN_PHASE_2A2T_WORKER');
$fixture=get_option('dzn_phase_2a2t_concurrency_fixture');
dzn_tcw_assert(is_array($fixture)&&count($fixture['rows']??array())>=2,'the concurrency fixture must exist');
wp_set_current_user(1);
$mode=(string)$fixture['mode'];
if(in_array($mode,array('duplicate_webhook','conflicting_duplicate_webhook'),true)){
    if(!defined('DZN_PLATFORM_PAYMENT_TEST_VAULT'))define('DZN_PLATFORM_PAYMENT_TEST_VAULT',true);
    PaymentProviderRegistry::registerTranslator(new StripeEventTranslator(new StripeSignatureVerifier()));
    dzn_tcw_assert(isset($fixture['webhook']['selector'],$fixture['webhook']['body'],$fixture['webhook']['body_changed']),'the duplicate-webhook race fixture must exist');
    $webhook=$fixture['webhook'];
    // The two deliveries must be in flight together: announce this worker, then wait for its sibling.
    if($gate!==''&&is_dir($gate)){
        file_put_contents($gate.'/'.$worker.'.started','1');
        $waited=0;
        while((!file_exists($gate.'/w1.started')||!file_exists($gate.'/w2.started'))&&$waited<300){usleep(100000);$waited++;}
    }
    $body=(string)($mode==='conflicting_duplicate_webhook'&&$worker==='w2'?$webhook['body_changed']:$webhook['body']);
    $signature='t='.time().',v1='.hash_hmac('sha256',time().'.'.$body,(string)$webhook['secret']);
    $outcome=null;$caught=null;
    try{
        $outcome=(new PaymentEventIntakeService())->receive('stripe',(string)$webhook['selector'],$body,array('stripe-signature'=>$signature));
    }catch(Throwable$e){$caught=$e;}
    if($gate!=='')file_put_contents($gate.'/'.$worker.'.json',(string)wp_json_encode(array('worker'=>$worker,'operation'=>'webhook_delivery','outcome'=>$outcome,'error'=>$caught?$caught->getMessage():null,'mutating_calls'=>0,'reconcile_calls'=>0,'total_calls'=>0,'at'=>gmdate('Y-m-d H:i:s'))));
    if($caught!==null)throw $caught;
    echo "phase-2a2t-concurrency-worker ".$worker." done\n";
    return;
}
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
