<?php
/** Phase 2A.2-G static final-acceptance, immutability, privacy and lock guard. */
$root=dirname(__DIR__);
$service=file_get_contents($root.'/src/Core/Application/FinalAcceptanceService.php');
$repo=file_get_contents($root.'/src/Core/Infrastructure/Repository/AcceptedServiceArrangementRepository.php');
$proposal=file_get_contents($root.'/src/Core/Application/ProposalService.php');
$proposalRepo=file_get_contents($root.'/src/Core/Infrastructure/Repository/ProposalRepository.php');
$authorityRepo=file_get_contents($root.'/src/Core/Infrastructure/Repository/StudentIdentityAuthorityRepository.php');
$migration=file_get_contents($root.'/src/Core/Infrastructure/Migration/Migrator.php');
$controller=file_get_contents($root.'/src/Admin/Controller/CoordinationCaseController.php');
$plugin=file_get_contents($root.'/delnavazan-platform.php');
$raceSetup=file_get_contents($root.'/tests/phase-2a2g-concurrency-setup.php');
$raceWorker=file_get_contents($root.'/tests/phase-2a2g-concurrency-worker.php');
$raceVerify=file_get_contents($root.'/tests/phase-2a2g-concurrency-verify.php');
$raceRunner=file_get_contents($root.'/tests/phase-2a2g-concurrency-runner.sh');
function phase_2a2g_method_body(string $source,string $name):string{
    $tokens=token_get_all($source);$named=false;$waiting=false;$depth=0;$body='';
    foreach($tokens as$i=>$token){
        if(is_array($token)&&$token[0]===T_FUNCTION){$named=false;$waiting=true;continue;}
        if($waiting&&is_array($token)&&$token[0]===T_STRING){$named=$token[1]===$name;$waiting=false;continue;}
        if(!$named)continue;$text=is_array($token)?$token[1]:$token;
        if($text==='{'){$depth++;if($depth===1)continue;}
        if($depth>0)$body.=$text;
        if($text==='}'&&--$depth===0)return substr($body,0,-1);
    }
    return '';
}
foreach(array("DZN_PLATFORM_SCHEMA_VERSION', '13'",'phase2a2g-final-acceptance-arrangement-20260910.1','013_final_acceptance_arrangement_foundation','accepted_service_arrangements','proposal_option_outcome_events','verify_final_acceptance_arrangement_schema','dzn_finalize_service_arrangements','ENGINE=InnoDB')as$n)if(!str_contains($migration.$plugin,$n))throw new RuntimeException('Missing Phase 2A.2-G foundation: '.$n);
foreach(array('affirmed','accepted_pending_conditions','authority_unresolved','adult_self','guardian_representative','service_acceptance','arrangementForCommand','command_payload_digest','IdempotencyConflictException','ProposalFamilyAlreadyAcceptedException','closed_competing','dzn_phase_2a2g_proposal_locks_held','dzn_phase_2a2g_authority_locks_held')as$n)if(!str_contains($service.$repo,$n))throw new RuntimeException('Missing final-acceptance invariant: '.$n);
foreach(array('requestForUpdate','caseForUpdate','familyForCaseForUpdate','familyOptionsForUpdate','currentVersionsForOptionsForUpdate','provisionalForUidForUpdate','studentForUpdate','resolutionByIdForUpdate','capacityByIdForUpdate','wordpressUserForUpdate','lockPrincipalsForAuthority','lockGrantsForAuthority')as$n)if(!str_contains($service.$authorityRepo,$n))throw new RuntimeException('Missing canonical authority lock: '.$n);
foreach(array('proposal_family_id','proposal_option_id','proposal_version_id','provisional_acceptance_event_id','identity_resolution_event_id','capacity_classification_id','authority_route','confirmation_evidence_reference','arrangement_fingerprint','version_fingerprint')as$n)if(!str_contains($migration,$n))throw new RuntimeException('Missing immutable arrangement evidence: '.$n);
$schema=substr($migration,strpos($migration,'CREATE TABLE {$p}accepted_service_arrangements'));
$schema=substr($schema,0,strpos($schema,'private static function private_digest'));
foreach(array('first_name','last_name','display_name','email','mobile','whatsapp','phone','address','payer','submitter')as$n)if(stripos($schema,$n)!==false)throw new RuntimeException('Accepted arrangement stores prohibited PII: '.$n);
if(stripos($repo,'raw idempotency')!==false||preg_match('/function\s+(?:update|delete).*Arrangement/i',$repo))throw new RuntimeException('Arrangement persistence is not immutable or digest-only');
foreach(array('createEnrolment','createLesson','amelia_','wp_amelia','platform_payments','platform_outbox','register_rest_route')as$n)if(stripos($service.$repo,$n)!==false)throw new RuntimeException('Final acceptance exceeded bounded authority: '.$n);
if(!str_contains($proposal,'acceptedArrangementForFamily')||str_contains($proposal,'acceptedArrangementForFamilyForUpdate'))throw new RuntimeException('Later Proposal issuance finality check is missing or locking an absence range');
$finalityRead=phase_2a2g_method_body($proposalRepo,'acceptedArrangementForFamily');
if($finalityRead===''||!str_contains($finalityRead,'accepted_service_arrangements')||stripos($finalityRead,'FOR UPDATE')!==false)throw new RuntimeException('Proposal issuance finality lookup must be non-locking under the Proposal Family lock');
foreach(array('versionForCommand','versionForOptionNumber','versionForOptionFingerprint')as$method){$body=phase_2a2g_method_body($proposalRepo,$method);if($body===''||stripos($body,'FOR UPDATE')!==false)throw new RuntimeException('Proposal absence lookup must not take a gap lock: '.$method);}
foreach(array(array($proposalRepo,'familyForCaseForUpdate'),array($proposalRepo,'optionForFamilyCandidateForUpdate'),array($proposalRepo,'optionForFamilyTeacherForUpdate'),array($proposalRepo,'currentVersionForOption'),array($repo,'familyOptionsForUpdate'),array($repo,'currentVersionsForOptionsForUpdate'),array($repo,'provisionalForUidForUpdate'))as[$source,$method]){
    $body=phase_2a2g_method_body($source,$method);if($body===''||stripos($body,'FOR UPDATE')===false)throw new RuntimeException('Required concrete-row lock missing: '.$method);
    if(preg_match_all('/"([^"]*FOR UPDATE[^"]*)"/i',$body,$queries))foreach($queries[1]as$query)if(stripos($query,'WHERE id=%d')===false)throw new RuntimeException('FOR UPDATE must lock only a concrete primary-key row: '.$method);
}
if(str_contains($service,'arrangementForFamilyForUpdate')||str_contains($service,'outcomesForFamilyForUpdate')||str_contains($service,'arrangementForCommandForUpdate'))throw new RuntimeException('Final acceptance reacquired an empty child-table gap lock');
if(!str_contains($controller,'finalize_service_arrangement')||!str_contains($controller,"check_admin_referer( 'dzn_final_acceptance' )")||!str_contains($controller,'requireFinalAcceptanceCapability'))throw new RuntimeException('Protected internal final-acceptance surface missing');
foreach(array("array('a', 'o', 'p', 'e', 'c', 'pr', 'g', 'x', 'u')",'phase-2a2g.invalid','dzn_phase_2a2g_proposal_locks_held','dzn_phase_2a2g_family_finality_checked','dzn_phase_2a2g_authority_locks_held','SELECT CONNECTION_ID()','w1.locked','w2.started','w2.finished','release','outcome=idempotency_conflict','closed_competing','proposal_option_outcome_events')as$n)if(!str_contains($raceSetup.$raceWorker.$raceVerify.$raceRunner.$service,$n))throw new RuntimeException('Missing deterministic final-acceptance race invariant: '.$n);
echo "Phase 2A.2-G source contract passed\n";
