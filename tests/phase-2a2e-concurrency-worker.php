<?php
/** Separate WP-CLI acceptance worker with a deterministic post-lock barrier. */
if(getenv('DZN_PHASE_2A2E_RUNTIME_TEST')!=='isolated'||!defined('WP_CLI')||!WP_CLI){fwrite(STDERR,"Phase 2A.2-E worker refused.\n");exit(1);}
use Delnavazan\Platform\Core\Application\ProposalAcceptanceService;
use Delnavazan\Platform\Core\Application\IdempotencyConflictException;
$state=get_option('dzn_phase_2a2e_race_state');if(!is_array($state))throw new RuntimeException('Acceptance race state unavailable');$name=(string)getenv('DZN_PHASE_2A2E_WORKER');$mode=(string)getenv('DZN_PHASE_2A2E_MODE');if(!preg_match('/^[ab][12]$/',$name)||!in_array($mode,array('a','b'),true))throw new RuntimeException('Worker identity unavailable');
$gate='/gate';file_put_contents($gate.'/'.$name.'.started',getmypid()."\n");
if(($state['holder']??'')===$name)add_action('dzn_phase_2a2e_acceptance_locks_held',static function()use($gate,$name){file_put_contents($gate.'/'.$name.'.locked',microtime(true)."\n");for($i=0;$i<300&&!file_exists($gate.'/release');$i++)usleep(100000);if(!file_exists($gate.'/release'))throw new RuntimeException('Barrier release unavailable');});
$payload=$state[$name]??null;if(!is_array($payload))throw new RuntimeException('Worker payload unavailable');
try{$out=(new ProposalAcceptanceService())->record($state['family_uid'],$state['option_uid'],(int)$state['version_number'],$state['prospective_subject_ref'],$payload['channel'],$state['evidence_at'],$state['key']);echo 'outcome=success event='.(int)$out['event_id'].' idempotent='.($out['idempotent']?'1':'0')."\n";}catch(IdempotencyConflictException){echo "outcome=conflict\n";}catch(Throwable $e){echo 'outcome=rejected class='.get_class($e)."\n";}
