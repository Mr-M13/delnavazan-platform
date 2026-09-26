<?php
namespace Delnavazan\Platform\Admin\Controller;
use Delnavazan\Platform\Portals\PortalCapabilityService;
final class PortalCapabilityController {
    public const CAPABILITY='dzn_view_portal_capabilities'; public const MENU_SLUG='dzn-portal-capabilities';
    public static function register():void { add_action('admin_menu',array(__CLASS__,'menu')); }
    public static function menu():void { if(function_exists('add_submenu_page'))add_submenu_page('dzn-platform','Portal capabilities','Portal capabilities',self::CAPABILITY,self::MENU_SLUG,array(__CLASS__,'render')); }
    public static function render():void { if(!current_user_can(self::CAPABILITY))return; global $wpdb; $table=$wpdb->prefix.'dzn_portal_public_capabilities'; $rows=$wpdb->get_results("SELECT id,lesson_id,purpose,generation,state,expires_at,revocation_reason_code FROM {$table} ORDER BY id DESC LIMIT 100"); echo '<div class="wrap"><h1>Portal capabilities</h1><p>Access artefacts only. Public actions remain disabled by default.</p><table class="widefat"><thead><tr><th>ID</th><th>Lesson</th><th>Purpose</th><th>Generation</th><th>State</th><th>Expiry</th><th>Reason</th></tr></thead><tbody>'; foreach((array)$rows as $row)echo '<tr><td>'.esc_html($row->id).'</td><td>'.esc_html($row->lesson_id).'</td><td>'.esc_html($row->purpose).'</td><td>'.esc_html($row->generation).'</td><td>'.esc_html($row->state).'</td><td>'.esc_html($row->expires_at).'</td><td>'.esc_html((string)$row->revocation_reason_code).'</td></tr>'; echo '</tbody></table></div>'; }
}
