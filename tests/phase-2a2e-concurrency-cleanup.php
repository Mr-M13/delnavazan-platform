<?php
/** Removes the per-run state; DZN_PHASE_2A2E_PURGE_FIXTURE=1 removes the fixture after all modes. */
if(getenv('DZN_PHASE_2A2E_RUNTIME_TEST')!=='isolated'||!defined('WP_CLI')||!WP_CLI||!in_array(wp_get_environment_type(),array('local','development'),true)){fwrite(STDERR,"Phase 2A.2-E concurrency cleanup refused.\n");exit(1);}
delete_option('dzn_phase_2a2e_race_state');if(getenv('DZN_PHASE_2A2E_PURGE_FIXTURE')==='1')delete_option('dzn_phase_2a2e_concurrency_fixture');echo "Phase 2A.2-E concurrency state removed\n";
