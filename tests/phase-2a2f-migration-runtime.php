<?php
/** Schema 11 -> 12 migration assertion; only runs in explicitly disposable local CLI environments. */
if(getenv('DZN_PHASE_2A2F_RUNTIME_TEST')!=='migration'||!defined('WP_CLI')||!WP_CLI||!in_array(wp_get_environment_type(),array('local','development'),true)){fwrite(STDERR,"Phase 2A.2-F migration runtime refused.\n");exit(1);}
global $wpdb;$p=$wpdb->prefix.'dzn_';
foreach(array('booking_request_identity_resolution_events','student_acceptance_capacity_classifications','student_acceptance_authority_grants')as$table)if($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$p.$table))!==$p.$table)throw new RuntimeException('Missing migrated table '.$table);
foreach(array(array('booking_requests','current_identity_resolution_id'),array('students','current_acceptance_capacity_classification_id'),array('student_principal_links','link_sequence'),array('student_principal_links','verification_basis'))as[$table,$column])if(!$wpdb->get_row("SHOW COLUMNS FROM {$p}{$table} LIKE '{$column}'"))throw new RuntimeException('Missing migrated column '.$table.'.'.$column);
echo "Phase 2A.2-F migration runtime passed\n";
