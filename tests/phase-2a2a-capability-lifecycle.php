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
function get_option(string $key, mixed $default = false): mixed { global $phase2a2aOptions; return $phase2a2aOptions[$key] ?? $default; }
function update_option(string $key, mixed $value, mixed $autoload = null): bool { global $phase2a2aOptions; $phase2a2aOptions[$key] = $value; return true; }
function get_role(string $name): ?Phase2A2ARole { global $phase2a2aAdmin; return $name === 'administrator' ? $phase2a2aAdmin : null; }
function add_role(string $name, string $display, array $caps): void {}
require dirname(__DIR__) . '/src/Core/Infrastructure/Migration/Migrator.php';
$method = new ReflectionMethod('Delnavazan\\Platform\\Core\\Infrastructure\\Migration\\Migrator', 'ensure_capabilities');
$method->setAccessible(true);

// Absent marker installs the capability and records the marker.
$method->invoke(null);
if (!$phase2a2aAdmin->has_cap('dzn_prepare_booking_request_matches') || get_option('dzn_platform_capability_version') !== '2a2a') throw new RuntimeException('Absent capability marker was not installed');
$adds = $phase2a2aAdmin->adds;
// Current marker plus present capability is a harmless no-op.
$method->invoke(null);
if ($phase2a2aAdmin->adds !== $adds) throw new RuntimeException('Current capability marker was not a harmless no-op');
// A damaged current marker cannot suppress reconciliation.
unset($phase2a2aAdmin->caps['dzn_prepare_booking_request_matches']);
$method->invoke(null);
if (!$phase2a2aAdmin->has_cap('dzn_prepare_booking_request_matches')) throw new RuntimeException('Missing current capability was not restored');
echo "Phase 2A.2-A capability lifecycle passed\n";
