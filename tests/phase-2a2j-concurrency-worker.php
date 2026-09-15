<?php
if (getenv('DZN_PHASE_2A2J_RUNTIME_TEST') !== 'concurrency' || !defined('WP_CLI') || !WP_CLI) { fwrite(STDERR, "Phase 2A.2-J concurrency worker refused.\n"); exit(1); }
use Delnavazan\Platform\Core\Application\TeacherAssignmentService;
global $wpdb; $state = get_option('dzn_phase_2a2j_race_state'); $worker = (string) getenv('DZN_PHASE_2A2J_WORKER'); $gate = (string) getenv('DZN_PHASE_2A2J_GATE_DIR');
if (!is_array($state) || !in_array($worker, array('w1', 'w2'), true) || !is_dir($gate)) throw new RuntimeException('Invalid Phase J worker state');
$touch = static function(string $name, string $value = '') use ($gate): void { if (file_put_contents($gate . '/' . $name, $value) === false) throw new RuntimeException('Gate write failed'); };
$touch($worker . '.connection', (string) $wpdb->get_var('SELECT CONNECTION_ID()')); $touch($worker . '.started');
$wait = static function(string $name) use ($gate): void { for ($i = 0; $i < 600 && !is_file($gate . '/' . $name); $i++) usleep(100000); if (!is_file($gate . '/' . $name)) throw new RuntimeException('Release gate timeout'); };
if ($worker === 'w1') add_action('dzn_phase_2a2j_assignment_locks_held', static function() use ($touch, $wait, $worker): void { $touch($worker . '.locked'); $wait('release'); });
$service = new TeacherAssignmentService();
try {
    if (in_array($state['mode'], array('initial', 'replay'), true)) {
        $result = $service->assignInitial((int) $state['enrolment_id'], (string) $state[$worker === 'w1' ? 'key_one' : 'key_two']);
    } else {
        $teacher = (int) $state[$worker === 'w1' ? 'teacher_one' : 'teacher_two'];
        $evidence = array('expected_assignment_id' => (int) $state['expected_assignment_id'], 'route' => 'staff_attestation', 'evidence_channel' => 'phone', 'evidence_reference' => 'race-' . $worker . '-agreement', 'evidence_at' => gmdate('Y-m-d H:i:s', strtotime('-1 second')));
        $result = $service->replace((int) $state['enrolment_id'], $teacher, $evidence, (string) $state[$worker === 'w1' ? 'key_one' : 'key_two']);
    }
    $outcome = $result['created'] ? 'created' : ($result['idempotent'] ? 'replay' : ($result['already_applied'] ? 'already_applied' : 'unknown'));
    echo "outcome={$outcome} assignment={$result['assignment_id']}\n";
} catch (InvalidArgumentException $exception) { echo 'outcome=' . $exception->getMessage() . ' class=' . $exception::class . "\n"; }
finally { $touch($worker . '.finished'); }
