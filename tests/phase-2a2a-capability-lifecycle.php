<?php
/** Isolated executable check for the version-marker capability reconciliation. */
final class Phase2A2ARole {
    public array $caps = array();
    public int $adds = 0;
    public function add_cap(string $cap): void { $this->caps[$cap] = true; $this->adds++; }
    public function has_cap(string $cap): bool { return ! empty($this->caps[$cap]); }
}
$phase2a2aOptions = array();
$phase2a2aAdmin = new Phase2A2ARole();
$phase2a2aRoles = array( 'administrator' => $phase2a2aAdmin );
function get_option(string $key, mixed $default = false): mixed { global $phase2a2aOptions; return $phase2a2aOptions[$key] ?? $default; }
function update_option(string $key, mixed $value, mixed $autoload = null): bool { global $phase2a2aOptions; $phase2a2aOptions[$key] = $value; return true; }
function get_role(string $name): ?Phase2A2ARole { global $phase2a2aRoles; return $phase2a2aRoles[$name] ?? null; }
function add_role(string $name, string $display, array $caps): ?Phase2A2ARole {
    global $phase2a2aRoles;
    if ($name === '' || isset($phase2a2aRoles[$name])) return null;
    $role = new Phase2A2ARole();
    foreach ($caps as $cap => $grant) {
        if (is_int($cap)) { $role->add_cap((string)$grant); continue; }
        if ($grant) $role->add_cap((string)$cap);
    }
    $phase2a2aRoles[$name] = $role;
    return $role;
}
require dirname(__DIR__) . '/src/Core/Infrastructure/Migration/Migrator.php';
$method = new ReflectionMethod('Delnavazan\\Platform\\Core\\Infrastructure\\Migration\\Migrator', 'ensure_capabilities');
$method->setAccessible(true);

// Absent marker installs the current protected capability set and marker.
$method->invoke(null);
if (!$phase2a2aAdmin->has_cap('dzn_prepare_booking_request_matches') || !$phase2a2aAdmin->has_cap('dzn_manage_booking_request_coordination') || !$phase2a2aAdmin->has_cap('dzn_manage_teacher_availability_assent') || !$phase2a2aAdmin->has_cap('dzn_issue_booking_request_proposals') || !$phase2a2aAdmin->has_cap('dzn_record_booking_request_provisional_acceptance') || !$phase2a2aAdmin->has_cap('dzn_resolve_booking_request_student_identity') || !$phase2a2aAdmin->has_cap('dzn_manage_student_acceptance_authority') || !$phase2a2aAdmin->has_cap('dzn_view_student_acceptance_eligibility') || !$phase2a2aAdmin->has_cap('dzn_finalize_service_arrangements') || get_option('dzn_platform_capability_version') !== '2a2g') throw new RuntimeException('Absent capability marker was not installed');
$phase2a2aTeacher = get_role('dzn_teacher');
if (!$phase2a2aTeacher || !$phase2a2aTeacher->has_cap('read') || !$phase2a2aTeacher->has_cap('dzn_record_own_availability_assent')) throw new RuntimeException('Teacher assent capability was not installed');
$adds = $phase2a2aAdmin->adds;
// Current marker plus present capability is a harmless no-op.
$method->invoke(null);
if ($phase2a2aAdmin->adds !== $adds) throw new RuntimeException('Current capability marker was not a harmless no-op');
// A damaged current marker cannot suppress reconciliation.
unset($phase2a2aAdmin->caps['dzn_prepare_booking_request_matches'], $phase2a2aAdmin->caps['dzn_issue_booking_request_proposals'], $phase2a2aAdmin->caps['dzn_record_booking_request_provisional_acceptance'], $phase2a2aAdmin->caps['dzn_resolve_booking_request_student_identity'], $phase2a2aAdmin->caps['dzn_manage_student_acceptance_authority'], $phase2a2aAdmin->caps['dzn_view_student_acceptance_eligibility'], $phase2a2aAdmin->caps['dzn_finalize_service_arrangements']);
$method->invoke(null);
if (!$phase2a2aAdmin->has_cap('dzn_prepare_booking_request_matches') || !$phase2a2aAdmin->has_cap('dzn_manage_booking_request_coordination') || !$phase2a2aAdmin->has_cap('dzn_manage_teacher_availability_assent') || !$phase2a2aAdmin->has_cap('dzn_issue_booking_request_proposals') || !$phase2a2aAdmin->has_cap('dzn_record_booking_request_provisional_acceptance') || !$phase2a2aAdmin->has_cap('dzn_resolve_booking_request_student_identity') || !$phase2a2aAdmin->has_cap('dzn_manage_student_acceptance_authority') || !$phase2a2aAdmin->has_cap('dzn_view_student_acceptance_eligibility') || !$phase2a2aAdmin->has_cap('dzn_finalize_service_arrangements')) throw new RuntimeException('Missing current capability was not restored');
// A damaged Teacher role must be repaired even when the lifecycle marker is current.
unset($phase2a2aTeacher->caps['dzn_record_own_availability_assent']);
$method->invoke(null);
if (!$phase2a2aTeacher->has_cap('dzn_record_own_availability_assent')) throw new RuntimeException('Missing Teacher assent capability was not restored');
echo "Phase 2A.2-A capability lifecycle passed\n";
