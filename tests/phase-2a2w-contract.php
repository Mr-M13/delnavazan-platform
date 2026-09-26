<?php
/** Static contract guard for Phase-W's security and migration seams. */
$root=dirname(__DIR__);
$cap=file_get_contents($root.'/src/Portals/PortalCapabilityService.php');
$public=file_get_contents($root.'/src/Portals/PortalPublicActionController.php');
$action=file_get_contents($root.'/src/Portals/PortalPublicActionService.php');
$attendance=file_get_contents($root.'/src/Core/Application/CanonicalAttendanceIntakeService.php');
$migration=file_get_contents($root.'/src/Core/Infrastructure/Migration/Migrator.php');
$replay=file_get_contents($root.'/tests/phase-2a2w-replay-runtime.php');
foreach(array('hash_equals','aes-256-gcm','FOR UPDATE','superseded_by_capability_id','portal_public_capability_commands','portal_public_capability_events') as $needle)if(strpos($cap,$needle)===false)throw new RuntimeException('Phase-W capability contract missing '.$needle);
if(strpos($public,"(string)get_option(PortalRule::PUBLIC_ACTION_OPTION,'')!==PortalRule::PUBLIC_ACTION_ENABLED_VALUE")===false)throw new RuntimeException('Public action option is not strict');
foreach(array('verify_portal_facing_services_schema','portal_lesson_capability_roots','portal_public_action_events','digest shape','orphan portal action') as $needle)if(strpos($migration,$needle)===false)throw new RuntimeException('Phase-W migration verifier missing '.$needle);
foreach(array('verifyConsumed','confirmed_submitting','consumed_action_event_id') as $needle)if(strpos($action,$needle)===false&&strpos($cap,$needle)===false)throw new RuntimeException('Phase-W replay convergence seam missing '.$needle);
foreach(array('bool $requirePrincipal=true','binding($l,$s,$p,$student,$requirePrincipal)') as $needle)if(strpos($cap,$needle)===false)throw new RuntimeException('Phase-W consumed replay principal bypass seam missing '.$needle);
foreach(array('revoked_at','requirePrincipal','same consumed confirmation') as $needle)if(strpos($replay,$needle)===false)throw new RuntimeException('Phase-W revoked-principal replay coverage missing '.$needle);
foreach(array('PUBLIC_CAPABILITY_AUDIT_ACTOR','public_capability_on_behalf','command($digest)') as $needle)if(strpos($attendance,$needle)===false)throw new RuntimeException('Anonymous capability handoff seam missing '.$needle);
echo "Phase-W contract passed\n";
