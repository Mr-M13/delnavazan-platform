<?php
if (getenv('DZN_PHASE_2A2J_RUNTIME_TEST') !== 'concurrency' || !defined('WP_CLI') || !WP_CLI) { fwrite(STDERR, "Phase 2A.2-J verifier refused.\n"); exit(1); }
global $wpdb; $p = $wpdb->prefix . 'dzn_'; $state = get_option('dzn_phase_2a2j_race_state'); $enrolment = (int) $state['enrolment_id'];
$assignments = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$p}teacher_assignments WHERE enrolment_id=%d", $enrolment));
$current = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$p}teacher_assignments WHERE enrolment_id=%d AND applicable_slot=1", $enrolment));
$events = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$p}teacher_assignment_lifecycle_events e INNER JOIN {$p}teacher_assignments a ON a.id=e.assignment_id WHERE a.enrolment_id=%d", $enrolment));
$commands = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$p}teacher_assignment_commands WHERE enrolment_id=%d", $enrolment));
if (in_array($state['mode'], array('initial', 'replay'), true) && ($assignments !== 1 || !$current || $events !== 1 || $commands !== 1)) throw new RuntimeException('Initial/replay race final state invalid');
if ($state['mode'] === 'replacement' && ($assignments !== 2 || !$current || (int) $current->teacher_id !== (int) $state['teacher_one'] || $events !== 3 || $commands !== 2)) throw new RuntimeException('Replacement race final state invalid');
echo "race={$state['mode']} verifier=pass assignments={$assignments} events={$events} commands={$commands}\n";
