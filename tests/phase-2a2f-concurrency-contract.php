<?php
/** Static lock-order guard for the deterministic local race harness plan. */
$root=dirname(__DIR__);
$identity=file_get_contents($root.'/src/Core/Application/StudentIdentityResolutionService.php');
$authority=file_get_contents($root.'/src/Core/Application/StudentAcceptanceAuthorityService.php');
$privacy=file_get_contents($root.'/src/Core/Application/BookingRequestPrivacyService.php');
$repo=file_get_contents($root.'/src/Core/Infrastructure/Repository/StudentIdentityAuthorityRepository.php');
foreach(array('requestForUpdate','currentResolutionForUpdate','privacyResolutionConsistent','studentForUpdate','currentCapacityForUpdate','activePrincipalForStudentForUpdate','activePrincipalForUserForUpdate','activeGuardianForUserForUpdate','activeGrantForUpdate','wordpressUserForUpdate','version=%d','active_slot=1','dzn_phase_2a2f_identity_resolution_locks_held','dzn_phase_2a2f_capacity_locks_held','dzn_phase_2a2f_authority_locks_held')as$needle)if(strpos($identity.$authority.$privacy.$repo,$needle)===false)throw new RuntimeException('Phase 2A.2-F race lock or CAS invariant missing: '.$needle);
if(strpos($identity,'privacy_erased_at!==null')===false||strpos($privacy,"dzn_phase_2a2e_privacy_locks_held")===false)throw new RuntimeException('Resolution/erasure controlled-gate invariant missing');
echo "Phase 2A.2-F concurrency source contract passed\n";
