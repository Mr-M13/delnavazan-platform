<?php
if (getenv('DZN_PHASE_2A2J_RUNTIME_TEST') !== 'concurrency' || !defined('WP_CLI') || !WP_CLI || !in_array(wp_get_environment_type(), array('local', 'development'), true)) { fwrite(STDERR, "Phase 2A.2-J concurrency setup refused.\n"); exit(1); }
use Delnavazan\Platform\Core\Application\TeacherAssignmentService;
global $wpdb; $p = $wpdb->prefix . 'dzn_'; $fixture = get_option('dzn_phase_2a2j_fixture'); $mode = (string) getenv('DZN_PHASE_2A2J_MODE');
if (!is_array($fixture) || !in_array($mode, array('initial', 'replay', 'replacement'), true)) throw new RuntimeException('Phase J race state unavailable');
$source = $fixture['sources'][1]; $enrolmentId = (int) $source['enrolment_id'];
$assignmentIds = array_map('intval', $wpdb->get_col($wpdb->prepare("SELECT id FROM {$p}teacher_assignments WHERE enrolment_id=%d", $enrolmentId)) ?: array());
if ($assignmentIds) { $marks = implode(',', array_fill(0, count($assignmentIds), '%d')); $wpdb->query($wpdb->prepare("DELETE FROM {$p}teacher_assignment_commands WHERE result_assignment_id IN ({$marks})", ...$assignmentIds)); $wpdb->query($wpdb->prepare("DELETE FROM {$p}teacher_assignment_lifecycle_events WHERE assignment_id IN ({$marks})", ...$assignmentIds)); $wpdb->query($wpdb->prepare("DELETE FROM {$p}teacher_assignments WHERE id IN ({$marks})", ...$assignmentIds)); }
$wpdb->update($p . 'enrolments', array('lifecycle_state' => 'authorised', 'applicable_slot' => 1), array('id' => $enrolmentId));
$state = array('mode' => $mode, 'enrolment_id' => $enrolmentId, 'teacher_one' => (int) $fixture['teachers'][1], 'teacher_two' => (int) $fixture['teachers'][2], 'key_one' => dzn_2a2j_race_key($mode . '-one'), 'key_two' => dzn_2a2j_race_key($mode . '-two'));
if ($mode === 'replay') $state['key_two'] = $state['key_one'];
if ($mode === 'replacement') { $initial = (new TeacherAssignmentService())->assignInitial($enrolmentId, dzn_2a2j_race_key('replacement-base')); $state['expected_assignment_id'] = (int) $initial['assignment_id']; }
update_option('dzn_phase_2a2j_race_state', $state, false);
echo "Phase 2A.2-J {$mode} concurrency setup passed\n";
function dzn_2a2j_race_key(string $label): string { return 'dzn-2a2j-race-' . $label . '-' . substr(hash('sha256', $label . wp_generate_uuid4()), 0, 32); }
