<?php
namespace Delnavazan\Platform\Admin\Controller;
use Delnavazan\Platform\Portals\PortalCapabilityService;
final class PortalCapabilityController {
    public const CAPABILITY='dzn_view_portal_capabilities'; public const MENU_SLUG='dzn-portal-capabilities';
    public static function register():void { add_action('admin_menu',array(__CLASS__,'menu')); }
    public static function menu():void { if(function_exists('add_submenu_page'))add_submenu_page('dzn-platform','Portal capabilities','Portal capabilities',self::CAPABILITY,self::MENU_SLUG,array(__CLASS__,'render')); }
    public static function render():void { if(!current_user_can(self::CAPABILITY))return; echo '<div class="wrap"><h1>Portal capabilities</h1><p>Phase-W access artefacts only. Public actions remain disabled by default.</p></div>'; }
}
