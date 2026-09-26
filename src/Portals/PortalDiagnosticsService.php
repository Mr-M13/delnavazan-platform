<?php
namespace Delnavazan\Platform\Portals;

final class PortalDiagnosticsService {
    public function summary():array { global $wpdb; $p=$wpdb->prefix.'dzn_'; $result=array(); foreach(array('portal_public_capabilities','portal_public_capability_events','portal_public_capability_commands','portal_public_action_events','portal_access_denials') as $table)$result[$table]=(int)$wpdb->get_var("SELECT COUNT(*) FROM {$p}{$table}"); return $result; }
}
