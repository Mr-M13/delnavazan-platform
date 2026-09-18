<?php
/** Disposable Phase-O corruption regressions: every material delivery fact must fail closed. */
if(getenv('DZN_PHASE_2A2O_RUNTIME_TEST')!=='corruption'||!defined('WP_CLI')||!WP_CLI||!in_array(wp_get_environment_type(),array('local','development'),true)){fwrite(STDERR,"Phase 2A.2-O corruption runtime refused.\n");exit(1);}
use Delnavazan\Platform\Core\Application\{CanonicalEnrolmentLifecycleService,CanonicalLessonAuthorityService,CanonicalLessonDeliveryGuard,CanonicalLessonDeliveryReadService,CanonicalLessonDeliveryService,CanonicalLessonScheduleService,CanonicalTermAuthorityService,TeacherAcceptingStateService,TeacherAssignmentService,TeacherAvailabilityService,TeacherService,TeachingEligibilityService};

global $wpdb; $p = $wpdb->prefix . 'dzn_';
function dzn_oc_assert(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
function dzn_oc_key(string $label): string { return 'dzn-2a2oc-' . $label . '-' . wp_generate_uuid4(); }
function dzn_oc_evidence(string $reference): array { return array('evidence_channel' => 'staff_record', 'evidence_reference' => $reference, 'evidence_at' => gmdate('Y-m-d H:i:s')); }
$fixture = get_option('dzn_phase_2a2j_fixture');
dzn_oc_assert(is_array($fixture) && count($fixture['sources'] ?? array()) >= 1, 'Phase-J production fixture required');
$lessonService = new CanonicalLessonAuthorityService(); $enrolmentService = new CanonicalEnrolmentLifecycleService();
$termService = new CanonicalTermAuthorityService(); $assignmentService = new TeacherAssignmentService();
$scheduleService = new CanonicalLessonScheduleService(); $delivery = new CanonicalLessonDeliveryService();
$read = new CanonicalLessonDeliveryReadService(); $guard = new CanonicalLessonDeliveryGuard();
$availabilityService = new TeacherAvailabilityService(); $acceptingService = new TeacherAcceptingStateService();
$sources = array();
foreach ($fixture['sources'] as $candidate) {
    $candidateId = (int) $candidate['enrolment_id'];
    $state = (string) $wpdb->get_var($wpdb->prepare("SELECT lifecycle_state FROM {$p}enrolments WHERE id=%d", $candidateId));
    $terms = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$p}terms WHERE enrolment_id=%d AND record_model='canonical_enrolment_term_v1'", $candidateId));
    if ($state === 'authorised' && $terms === 0) $sources[] = $candidateId;
}
dzn_oc_assert(count($sources) >= 2, 'no_available_source');
$sourceCursor = 0;
/** Every chain owns a dedicated provisioned Teacher and serves at most eight Lessons. */
$makeChain = function () use (&$sourceCursor, $sources, $enrolmentService, $termService, $assignmentService, $acceptingService, $availabilityService, $wpdb, $p): array {
    dzn_oc_assert(isset($sources[$sourceCursor]), 'no_available_source');
    $enrolmentId = (int) $sources[$sourceCursor++];
    $enrolmentService->activate($enrolmentId, 'authorised', dzn_oc_evidence('activate'), dzn_oc_key('activate'));
    $term = $termService->create($enrolmentId, null, null, dzn_oc_evidence('term'), dzn_oc_key('term'));
    $termService->activate((int) $term['term_id'], 'authorised', dzn_oc_evidence('term-active'), dzn_oc_key('term-active'));
    $assignment = $assignmentService->assignInitial($enrolmentId, dzn_oc_key('assignment'));
    $courseId = (int) $wpdb->get_var($wpdb->prepare("SELECT course_id FROM {$p}enrolments WHERE id=%d", $enrolmentId));
    $teacherId = (int) (new TeacherService())->create(array('display_name' => 'Synthetic OC Teacher', 'email' => 'oc-' . wp_generate_uuid4() . '@phase-2a2o.invalid'));
    $acceptingService->set(array('teacher_id' => $teacherId, 'state' => 'accepting', 'reason_code' => 'synthetic_corruption'));
    $availabilityService->setProfile(array('teacher_id' => $teacherId, 'timezone' => 'UTC', 'status' => 'active', 'reason_code' => 'synthetic_corruption'));
    for ($weekday = 1; $weekday <= 7; $weekday++) {
        $availabilityService->setRecurringRule(array('teacher_id' => $teacherId, 'timezone' => 'UTC', 'weekday' => $weekday, 'local_start_time' => '00:00:00', 'local_end_time' => '23:59:59', 'state' => 'requestable', 'status' => 'active', 'reason_code' => 'synthetic_corruption'));
    }
    (new TeachingEligibilityService())->setEligibility(array('teacher_id' => $teacherId, 'course_id' => $courseId, 'status' => 'active', 'reason_code' => 'synthetic_corruption'));
    $moved = $assignmentService->replace($enrolmentId, $teacherId, array('expected_assignment_id' => (int) $assignment['assignment_id'], 'route' => 'staff_attestation', 'evidence_channel' => 'phone', 'evidence_reference' => 'isolated-corruption-' . $teacherId, 'evidence_at' => gmdate('Y-m-d H:i:s')), dzn_oc_key('isolate'));
    return array('term_id' => (int) $term['term_id'], 'assignment_id' => (int) $moved['assignment_id'], 'used' => 0);
};
$chain = null;
$occurrence = function (string $label, int $durationMinutes = 1) use (&$chain, $makeChain, $lessonService, $scheduleService, $wpdb, $p): array {
    if ($chain === null || $chain['used'] >= 8) $chain = $makeChain();
    $chain['used']++;
    $termId = (int) $chain['term_id']; $assignmentId = (int) $chain['assignment_id'];
    $lessonId = (int) $lessonService->createStandard($termId, $assignmentId, dzn_oc_evidence($label), dzn_oc_key($label))['lesson_id'];
    $wall = gmdate('Y-m-d H:i:s', strtotime('+2 seconds'));
    $scheduled = $scheduleService->schedule($lessonId, $assignmentId, array('schedule_timezone' => 'UTC', 'local_wall_date' => substr($wall, 0, 10), 'local_wall_time' => substr($wall, 11), 'duration_minutes' => $durationMinutes, 'reason_code' => 'synthetic_schedule') + dzn_oc_evidence('schedule-' . $label), dzn_oc_key('schedule-' . $label));
    $version = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$p}canonical_lesson_schedule_versions WHERE id=%d", (int) $scheduled['schedule_version_id']));
    $scheduleService->release($lessonId, array('expected_schedule_version_id' => (int) $version->id, 'reason_code' => 'synthetic_schedule_release') + dzn_oc_evidence('release-' . $label), dzn_oc_key('release-' . $label));
    return array('lesson_id' => $lessonId, 'ends_at_utc' => (string) $version->ends_at_utc);
};
/** Create every synthetic occurrence first, then wait once for all of them to end. */
$occurrences = array();
foreach (array('lesson','delivery_state','attendance_state','remedy_class','sequence','anchor','lineage','provider','recorded_by','reason_code','evidence_channel','evidence_reference_digest','evidence_at','supersession','command') as $label) {
    $occurrences[$label] = $occurrence('corrupt-' . $label);
}
$latest = 0;
foreach ($occurrences as $item) $latest = max($latest, strtotime((string) $item['ends_at_utc'] . ' UTC'));
while (time() < $latest + 2) sleep(1);
$settled = static function (string $label) use ($occurrences): int {
    if (!isset($occurrences[$label])) throw new RuntimeException('Synthetic occurrence unavailable: ' . $label);
    return (int) $occurrences[$label]['lesson_id'];
};
$recorded = function (int $lessonId, string $code, string $label, ?string $key = null): array {
    $useKey = $key ?? dzn_oc_key('outcome-' . $label);
    $result = (new CanonicalLessonDeliveryService())->record($lessonId, 'authorised', array('outcome_code' => $code, 'reason_code' => 'synthetic_outcome') + dzn_oc_evidence('outcome-' . $label), $useKey);
    return array('lesson_id' => $lessonId, 'outcome_id' => (int) $result['outcome_id'], 'key' => $useKey);
};
$lessonOf = static function (int $outcomeId) use ($wpdb, $p): int {
    return (int) $wpdb->get_var($wpdb->prepare("SELECT lesson_id FROM {$p}canonical_lesson_delivery_outcomes WHERE id=%d", $outcomeId));
};
$cases = 0;
/** Damage one material fact, prove read/guard/completion fail closed, then restore it exactly. */
$failClosed = function (string $label, int $outcomeId, string $damage, string $repair) use ($wpdb, $read, $guard, $lessonService, $lessonOf): void {
    $lessonId = $lessonOf($outcomeId);
    dzn_oc_assert($lessonId > 0, 'Delivery outcome relationship unavailable before damage: ' . $label);
    dzn_oc_assert($wpdb->query($damage) !== false, 'Failed to damage delivery storage: ' . $label);
    $readRejected = false;
    try { $read->forLesson($lessonId); } catch (InvalidArgumentException $exception) { $readRejected = $exception->getMessage() === 'canonical_delivery_integrity_conflict'; }
    dzn_oc_assert($readRejected, 'Corrupted delivery aggregate was served by the protected read: ' . $label);
    $guardRejected = false;
    try { $guard->hasOutcome($lessonId); } catch (InvalidArgumentException $exception) { $guardRejected = $exception->getMessage() === 'canonical_delivery_integrity_conflict'; }
    dzn_oc_assert($guardRejected, 'Corrupted delivery aggregate was accepted by the fail-closed guard: ' . $label);
    $completionRejected = false;
    try { $lessonService->complete($lessonId, 'authorised', dzn_oc_evidence('corrupt-complete'), dzn_oc_key('corrupt-complete')); }
    catch (Throwable $exception) { $completionRejected = $exception->getMessage() === 'canonical_delivery_integrity_conflict'; }
    dzn_oc_assert($completionRejected, 'Corrupted delivery aggregate did not block Lesson completion: ' . $label);
    dzn_oc_assert($wpdb->query($repair) !== false, 'Failed to repair delivery storage: ' . $label);
    dzn_oc_assert($read->forLesson($lessonId)['state'] === 'recorded', 'Repaired delivery aggregate is still unreadable: ' . $label);
};

// 1. Lesson relationship, outcome identity and occurrence anchor.
$c1 = $recorded($settled('lesson'), 'student_no_show', 'c1');
$failClosed('lesson relationship', (int) $c1['outcome_id'],
    "UPDATE {$p}canonical_lesson_delivery_outcomes SET lesson_id=lesson_id+100000 WHERE id=" . (int) $c1['outcome_id'],
    "UPDATE {$p}canonical_lesson_delivery_outcomes SET lesson_id=lesson_id-100000 WHERE id=" . (int) $c1['outcome_id']);
$cases++;
$failClosed('outcome sequence', (int) $c1['outcome_id'],
    "UPDATE {$p}canonical_lesson_delivery_outcomes SET outcome_sequence=outcome_sequence+9 WHERE id=" . (int) $c1['outcome_id'],
    "UPDATE {$p}canonical_lesson_delivery_outcomes SET outcome_sequence=outcome_sequence-9 WHERE id=" . (int) $c1['outcome_id']);
$cases++;
$failClosed('occurrence anchor', (int) $c1['outcome_id'],
    "UPDATE {$p}canonical_lesson_delivery_outcomes SET occurrence_starts_at_utc=occurrence_starts_at_utc - INTERVAL 1 DAY WHERE id=" . (int) $c1['outcome_id'],
    "UPDATE {$p}canonical_lesson_delivery_outcomes SET occurrence_starts_at_utc=occurrence_starts_at_utc + INTERVAL 1 DAY WHERE id=" . (int) $c1['outcome_id']);
$cases++;
$failClosed('occurrence end anchor', (int) $c1['outcome_id'],
    "UPDATE {$p}canonical_lesson_delivery_outcomes SET occurrence_ends_at_utc=occurrence_ends_at_utc - INTERVAL 1 MINUTE WHERE id=" . (int) $c1['outcome_id'],
    "UPDATE {$p}canonical_lesson_delivery_outcomes SET occurrence_ends_at_utc=occurrence_ends_at_utc + INTERVAL 1 MINUTE WHERE id=" . (int) $c1['outcome_id']);
$cases++;
$boundVersion = (string) $wpdb->get_var($wpdb->prepare("SELECT schedule_version_id FROM {$p}canonical_lesson_delivery_outcomes WHERE id=%d", (int) $c1['outcome_id']));
$failClosed('schedule-version binding', (int) $c1['outcome_id'],
    "UPDATE {$p}canonical_lesson_delivery_outcomes SET schedule_version_id=schedule_version_id+100000 WHERE id=" . (int) $c1['outcome_id'],
    "UPDATE {$p}canonical_lesson_delivery_outcomes SET schedule_version_id=" . (int) $boundVersion . " WHERE id=" . (int) $c1['outcome_id']);
$cases++;
// 2. Outcome type versus its derived delivery/attendance/remedy profile.
foreach (array('delivery_state' => array('not_delivered', 'delivered'), 'attendance_state' => array('student_absent', 'attended'), 'remedy_class' => array('academy_obligation', 'none')) as $column => $values) {
    $case = $recorded($settled($column), 'delivered', 'x-' . $column);
    $failClosed($column, (int) $case['outcome_id'],
        "UPDATE {$p}canonical_lesson_delivery_outcomes SET {$column}='{$values[0]}' WHERE id=" . (int) $case['outcome_id'],
        "UPDATE {$p}canonical_lesson_delivery_outcomes SET {$column}='{$values[1]}' WHERE id=" . (int) $case['outcome_id']);
    $cases++;
}
// 3. Actor, reason, evidence channel, reference digest and observed time.
foreach (array('recorded_by', 'reason_code', 'evidence_channel', 'evidence_reference_digest', 'evidence_at') as $column) {
    $case = $recorded($settled($column), 'student_no_show', 'c-' . $column);
    $id = (int) $case['outcome_id'];
    $original = (string) $wpdb->get_var($wpdb->prepare("SELECT {$column} FROM {$p}canonical_lesson_delivery_outcomes WHERE id=%d", $id));
    $damage = match ($column) {
        'recorded_by' => "UPDATE {$p}canonical_lesson_delivery_outcomes SET recorded_by=0 WHERE id={$id}",
        'reason_code' => "UPDATE {$p}canonical_lesson_delivery_outcomes SET reason_code='' WHERE id={$id}",
        'evidence_channel' => "UPDATE {$p}canonical_lesson_delivery_outcomes SET evidence_channel='provider_guess' WHERE id={$id}",
        'evidence_reference_digest' => "UPDATE {$p}canonical_lesson_delivery_outcomes SET evidence_reference_digest=REPEAT('b',64) WHERE id={$id}",
        default => "UPDATE {$p}canonical_lesson_delivery_outcomes SET evidence_at=evidence_at - INTERVAL 2 DAY WHERE id={$id}",
    };
    $repair = "UPDATE {$p}canonical_lesson_delivery_outcomes SET {$column}='" . esc_sql($original) . "' WHERE id={$id}";
    $failClosed($column, $id, $damage, $repair);
    $cases++;
}
// 4. Applicable/supersession relationship: an effective outcome superseded without a successor.
$c4 = $recorded($settled('supersession'), 'student_no_show', 'c-supersede');
$failClosed('applicable/supersession relationship', (int) $c4['outcome_id'],
    "UPDATE {$p}canonical_lesson_delivery_outcomes SET applicable_slot=NULL,superseded_at='" . gmdate('Y-m-d H:i:s') . "' WHERE id=" . (int) $c4['outcome_id'],
    "UPDATE {$p}canonical_lesson_delivery_outcomes SET applicable_slot=1,superseded_at=NULL WHERE id=" . (int) $c4['outcome_id']);
$cases++;
// 5. Provider-shaped evidence must never become canonical truth merely by being stored.
$c4b = $recorded($settled('lineage'), 'student_no_show', 'c-lineage');
$lineage = $delivery->correct((int) $c4b['lesson_id'], (int) $c4b['outcome_id'], array('outcome_code' => 'interruption', 'reason_code' => 'synthetic_outcome') + dzn_oc_evidence('c-lineage-fix'), dzn_oc_key('c-lineage-fix'));
$failClosed('supersession target', (int) $c4b['outcome_id'],
    "UPDATE {$p}canonical_lesson_delivery_outcomes SET superseded_by_outcome_id=superseded_by_outcome_id+100000 WHERE id=" . (int) $c4b['outcome_id'],
    "UPDATE {$p}canonical_lesson_delivery_outcomes SET superseded_by_outcome_id=" . (int) $lineage['outcome_id'] . " WHERE id=" . (int) $c4b['outcome_id']);
$cases++;
$c5 = $recorded($settled('provider'), 'student_no_show', 'c-provider');
$c5digest = (string) $wpdb->get_var($wpdb->prepare("SELECT evidence_reference_digest FROM {$p}canonical_lesson_delivery_outcomes WHERE id=%d", (int) $c5['outcome_id']));
$failClosed('provider-shaped evidence', (int) $c5['outcome_id'],
    "UPDATE {$p}canonical_lesson_delivery_outcomes SET evidence_channel='authenticated_platform',evidence_reference_digest=REPEAT('b',64) WHERE id=" . (int) $c5['outcome_id'],
    "UPDATE {$p}canonical_lesson_delivery_outcomes SET evidence_channel='staff_record',evidence_reference_digest='" . esc_sql($c5digest) . "' WHERE id=" . (int) $c5['outcome_id']);
$cases++;
// 6. Command intent corruption must never replay as success.
$key6 = dzn_oc_key('c-command');
$c6 = $recorded($settled('command'), 'student_no_show', 'c-command', $key6);
$commandId = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$p}canonical_lesson_delivery_commands WHERE result_outcome_id=%d", (int) $c6['outcome_id']));
$commandPayload = (string) $wpdb->get_var($wpdb->prepare("SELECT command_payload_digest FROM {$p}canonical_lesson_delivery_commands WHERE id=%d", $commandId));
$commandResult = (int) $wpdb->get_var($wpdb->prepare("SELECT result_outcome_id FROM {$p}canonical_lesson_delivery_commands WHERE id=%d", $commandId));
$intent = array('outcome_code' => 'student_no_show', 'reason_code' => 'synthetic_outcome') + dzn_oc_evidence('outcome-c-command');
dzn_oc_assert($wpdb->query("UPDATE {$p}canonical_lesson_delivery_commands SET command_payload_digest=REPEAT('a',64) WHERE id={$commandId}") !== false, 'Failed to damage delivery command intent');
$intentRejected = false;
try { $delivery->record((int) $c6['lesson_id'], 'authorised', $intent, $key6); }
catch (Throwable $exception) { $intentRejected = in_array($exception->getMessage(), array('Idempotency conflict', 'Contaminated canonical delivery command', 'Contaminated canonical delivery result', 'canonical_delivery_integrity_conflict'), true); }
dzn_oc_assert($intentRejected, 'Corrupted command intent replayed as success');
dzn_oc_assert($wpdb->query("UPDATE {$p}canonical_lesson_delivery_commands SET command_payload_digest='{$commandPayload}' WHERE id={$commandId}") !== false, 'Failed to repair delivery command intent');
$cases++;
// 7. Command result identity corruption must never replay as success.
dzn_oc_assert($wpdb->query("UPDATE {$p}canonical_lesson_delivery_commands SET result_outcome_id=result_outcome_id+100000 WHERE id={$commandId}") !== false, 'Failed to damage delivery command result');
$resultRejected = false;
try { $delivery->record((int) $c6['lesson_id'], 'authorised', $intent, $key6); }
catch (Throwable $exception) { $resultRejected = in_array($exception->getMessage(), array('Idempotency conflict', 'Contaminated canonical delivery command', 'Contaminated canonical delivery result', 'canonical_delivery_integrity_conflict'), true); }
dzn_oc_assert($resultRejected, 'Corrupted command result replayed as success');
dzn_oc_assert($wpdb->query("UPDATE {$p}canonical_lesson_delivery_commands SET result_outcome_id={$commandResult} WHERE id={$commandId}") !== false, 'Failed to repair delivery command result');
$cases++;

echo "corruption_cases=" . $cases . "\ncorrupted_delivery_fail_closed=pass\nprovider_evidence_not_authority=pass\nsupersession_lineage_enforced=pass\ncommand_evidence_enforced=pass\nPhase 2A.2-O corruption runtime passed\n";
