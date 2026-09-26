<?php
namespace Delnavazan\Platform\Portals;
final class PortalPublicActionController {
    public static function register():void { register_rest_route('delnavazan-platform/v1','/portal/join',array('methods'=>'GET','permission_callback'=>'__return_true','callback'=>array(__CLASS__,'join'))); register_rest_route('delnavazan-platform/v1','/portal/absence',array('methods'=>'GET','permission_callback'=>'__return_true','callback'=>array(__CLASS__,'absence'))); register_rest_route('delnavazan-platform/v1','/portal/absence/confirm',array('methods'=>'POST','permission_callback'=>'__return_true','callback'=>array(__CLASS__,'absence'))); }
    public static function join(\WP_REST_Request $request):\WP_REST_Response { try { if((bool)get_option('dzn_platform_portal_actions',false)!==true)throw new \InvalidArgumentException('portal_actions_disabled');$url=(new PortalCapabilityService())->join((string)$request->get_param('handle'),(string)$request->get_param('token'));return new \WP_REST_Response(null,302,array('Location'=>$url)); } catch(\Throwable $e){return new \WP_REST_Response(array('code'=>'portal_action_unavailable'),404);} }
    public static function absence(\WP_REST_Request $request):\WP_REST_Response { return new \WP_REST_Response(array('code'=>'portal_action_unavailable'),404); }
}
