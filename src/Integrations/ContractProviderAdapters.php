<?php
namespace Delnavazan\Platform\Integrations;

use Delnavazan\Platform\Core\Application\ProviderIntegrationIdempotency;
use Delnavazan\Platform\Core\Application\Port\{ProviderCalendarPort,ProviderEventNormalizer,ProviderMeetingPort,ProviderOAuthPort};

/**
 * Deterministic no-I/O provider adapters used by the Phase-V evidence runs.
 *
 * Every method is a pure function of its input: no HTTP, no socket, no filesystem, no clock other
 * than the caller's explicit instant, and no credential. The adapters record the calls they received
 * in memory so the runtime suites can assert exactly what the authority asked for, and they return
 * synthetic provider references derived from the canonical facts, which is what a real provider would
 * return as an opaque reference.
 *
 * A test or a local rehearsal may therefore execute the whole integration path without contacting a
 * provider, without a live credential and without production data.
 */
final class ContractProviderAdapters implements ProviderOAuthPort,ProviderCalendarPort,ProviderMeetingPort,ProviderEventNormalizer {
    /** @var array<int,array{port:string,call:array<string,mixed>}> */
    private array $calls=array();
    private array $validatedTokens=array();

    /** @param array<string,string> $validatedTokens map of credential material => raw provider subject reference */
    public function __construct(array $validatedTokens=array()){
        $this->validatedTokens=$validatedTokens;
    }

    /** @return array<int,array{port:string,call:array<string,mixed>}> */
    public function calls():array{return $this->calls;}

    public function count(string $port):int{
        $total=0;foreach($this->calls as $call)if($call['port']===$port)$total++;
        return $total;
    }

    public function authorizationUri(array $request):string{
        $this->calls[]=array('port'=>'oauth','call'=>array('operation'=>'authorization_uri','request'=>$this->safe($request)));
        return 'https://accounts.example.invalid/o/oauth2/v2/auth?state='.rawurlencode((string)($request['state']??''));
    }

    public function exchange(array $request):array{
        $this->calls[]=array('port'=>'oauth','call'=>array('operation'=>'exchange','request'=>$this->safe($request)));
        $subject='subject-'.substr(ProviderIntegrationIdempotency::evidence((string)($request['code']??'')),0,16);
        $material='material-'.substr(ProviderIntegrationIdempotency::providerPayload((string)($request['code']??'')),0,24);
        return array('granted_scope_snapshot'=>(string)($request['scope_snapshot']??''),'provider_subject_reference'=>$subject,'credential_material'=>$material,'consent_version'=>'consent-v1');
    }

    public function refresh(array $connection):array{
        $this->calls[]=array('port'=>'oauth','call'=>array('operation'=>'refresh','request'=>$this->safe($connection)));
        return array('credential_material'=>(string)($connection['credential_material']??''),'access_valid_until_utc'=>(string)($connection['now_utc']??''));
    }

    public function revoke(array $connection):array{
        $this->calls[]=array('port'=>'oauth','call'=>array('operation'=>'revoke','request'=>$this->safe($connection)));
        return array('revoked'=>true,'failure_reason_code'=>null);
    }

    public function inspect(array $connection):array{
        $this->calls[]=array('port'=>'oauth','call'=>array('operation'=>'inspect','request'=>$this->safe($connection)));
        $material=(string)($connection['credential_material']??'');
        if(!isset($this->validatedTokens[$material]))return array('usable'=>false,'provider_subject_reference'=>null,'granted_scope_snapshot'=>null,'failure_reason_code'=>'credential_unusable');
        return array('usable'=>true,'provider_subject_reference'=>$this->validatedTokens[$material],'granted_scope_snapshot'=>(string)($connection['scope_snapshot']??''),'failure_reason_code'=>null);
    }

    public function project(array $command):array{
        $this->calls[]=array('port'=>(string)($command['provider_code']??''),'call'=>array('operation'=>(string)($command['operation']??'project'),'request'=>$this->safe($command)));
        $reference='ref-'.substr(ProviderIntegrationIdempotency::payload(array('lesson_id'=>(int)($command['lesson_id']??0),'schedule_version_id'=>(int)($command['schedule_version_id']??0),'operation'=>(string)($command['operation']??'project'))),0,24);
        return array('provider_object_reference'=>$reference,'join_uri_reference'=>$reference.'-join','provider_occurred_at_utc'=>(string)($command['now_utc']??'')?:gmdate('Y-m-d H:i:s'));
    }

    public function verify(array $delivery):bool{
        return isset($delivery['verified'])&&$delivery['verified']===true;
    }

    public function normalise(array $delivery):array{
        $facts=$delivery['facts']??array();
        if(!is_array($facts))throw new \InvalidArgumentException('Provider event body required');
        return $facts;
    }

    /** Never record credential material in the call log. */
    private function safe(array $value):array{
        foreach($value as $key=>$item){
            if(str_contains((string)$key,'material')||str_contains((string)$key,'secret')||str_contains((string)$key,'token'))$value[$key]='[redacted]';
            elseif(is_array($item))$value[$key]=$this->safe($item);
        }
        return $value;
    }
}
