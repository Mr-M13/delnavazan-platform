<?php
namespace Delnavazan\Platform\Integrations\Payment\Stripe;

use Delnavazan\Platform\Core\Application\PaymentExecution\{PaymentEventIntakeService,PaymentExecutionRule,PaymentExecutionSupport};

/**
 * The only public entry point of Phase 2A.2-T (contract §9.1, §9.2, §9.8).
 *
 * It performs no WordPress authentication: the caller is a provider, not a principal, and the entire
 * trust decision is deferred to signature verification. The account segment is resolved *before* the
 * body is read, because verification needs the matching account, mode and signing secret. Every inbound
 * request is receipted, including a refusal decided before parsing; rate limiting is deliberately not
 * applied — signature verification is the control, and a rate limit would discard legitimately retried
 * provider traffic.
 */
final class StripeWebhookController {
    public static function register():void{
        register_rest_route('delnavazan-platform/v1','/payment-provider-events/(?P<provider>[a-z0-9_]{1,32})/(?P<account>[A-Za-z0-9_-]{1,32})',array(
            'methods'=>'POST','permission_callback'=>'__return_true','callback'=>array(__CLASS__,'handle'),
        ));
        // The bare provider route exists so every inbound request is receipted and never falls back to
        // a default account.
        register_rest_route('delnavazan-platform/v1','/payment-provider-events/(?P<provider>[a-z0-9_]{1,32})',array(
            'methods'=>'POST','permission_callback'=>'__return_true','callback'=>array(__CLASS__,'handleProviderOnly'),
        ));
    }

    public static function handle(\WP_REST_Request $request){
        return self::process((string)$request->get_param('provider'),(string)$request->get_param('account'),$request);
    }
    public static function handleProviderOnly(\WP_REST_Request $request){
        return self::process((string)$request->get_param('provider'),'',$request);
    }

    private static function process(string $providerKey,string $accountSelector,\WP_REST_Request $request){
        $intake=new PaymentEventIntakeService();
        $rawBody=(string)$request->get_body();
        $headers=array();
        foreach((array)$request->get_headers() as $name=>$value)$headers[strtolower((string)$name)]=is_array($value)?(string)reset($value):(string)$value;
        $meta=array('received_at'=>PaymentExecutionSupport::now(),'source'=>(string)$request->get_header('x-forwarded-for'));
        $reason=self::precheck($request,$rawBody);
        if($reason!==null)$meta['precheck_refusal']=$reason;
        $result=$intake->receive($providerKey,$accountSelector,($reason===null?$rawBody:''),$headers,$meta);
        $response=new \WP_REST_Response(array('status'=>$result['verification_state']),self::status((int)$result['status']));
        $response->header('Cache-Control','no-store');
        return $response;
    }

    /** §9.2: method, transport, content type and body-size requirements, decided before parsing. */
    private static function precheck(\WP_REST_Request $request,string $rawBody):?string{
        if(strtoupper((string)$request->get_method())!=='POST')return 'method_not_allowed';
        if(!self::isHttps())return 'https_required';
        $contentType=$request->get_content_type();
        if(!is_array($contentType)||!str_contains(strtolower((string)($contentType['value']??'')),'application/json'))return 'unsupported_content_type';
        if(strlen($rawBody)>PaymentExecutionRule::MAX_WEBHOOK_BYTES)return 'payload_too_large';
        if(trim($rawBody)==='')return 'empty_payload';
        if((array)$request->get_query_params()!==array())return 'unexpected_request_shape';
        if(count((array)$request->get_url_params())>2)return 'unexpected_request_shape';
        return null;
    }
    private static function isHttps():bool{
        if(function_exists('is_ssl')&&is_ssl())return true;
        return function_exists('wp_get_environment_type')&&wp_get_environment_type()==='local';
    }
    private static function status(int $status):int{return in_array($status,array(200,400,401,404,405,413,500,503),true)?$status:200;}
}
