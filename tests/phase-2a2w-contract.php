<?php
/** Static contract guard for Phase-W's security and migration seams. */
$root=dirname(__DIR__);
$cap=file_get_contents($root.'/src/Portals/PortalCapabilityService.php');
$public=file_get_contents($root.'/src/Portals/PortalPublicActionController.php');
$migration=file_get_contents($root.'/src/Core/Infrastructure/Migration/Migrator.php');
foreach(array('hash_equals','aes-256-gcm','FOR UPDATE','superseded_by_capability_id','portal_public_capability_commands','portal_public_capability_events') as $needle)if(strpos($cap,$needle)===false)throw new RuntimeException('Phase-W capability contract missing '.$needle);
if(strpos($public,"(string)get_option(PortalRule::PUBLIC_ACTION_OPTION,'')!==PortalRule::PUBLIC_ACTION_ENABLED_VALUE")===false)throw new RuntimeException('Public action option is not strict');
foreach(array('verify_portal_facing_services_schema','portal_lesson_capability_roots','portal_public_action_events','digest shape','orphan portal action') as $needle)if(strpos($migration,$needle)===false)throw new RuntimeException('Phase-W migration verifier missing '.$needle);
echo "Phase-W contract passed\n";
