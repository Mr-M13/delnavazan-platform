<?php
// Source/package-readiness contract only. Runtime evidence belongs in the
// controlled WordPress/PHP/MySQL matrix, not in this local check.
$root = dirname(__DIR__);
$runtime = file_get_contents("{$root}/docs/PHASE-1F-RUNTIME-VALIDATION.md");
foreach ([
    'Package, prerequisites, and rollback', 'all ten tables are ready',
    '001_initial_core_schema', 'Controlled exception harness',
    'PASS/FAIL/BLOCKED', 'Do not uninstall',
] as $fragment) {
    if (!str_contains($runtime, $fragment)) throw new RuntimeException("Phase 1F runbook missing: {$fragment}");
}

$bootstrap = file_get_contents("{$root}/delnavazan-platform.php");
foreach (['Requires PHP: 8.1', 'register_activation_hook', 'spl_autoload_register'] as $fragment) {
    if (!str_contains($bootstrap, $fragment)) throw new RuntimeException("Phase 1F bootstrap rule missing: {$fragment}");
}

$uninstall = file_get_contents("{$root}/uninstall.php");
if (!str_contains($uninstall, 'preserved on uninstall') || str_contains($uninstall, 'DROP TABLE')) {
    throw new RuntimeException('Phase 1F uninstall preservation rule missing');
}

foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator("{$root}/src")) as $file) {
    if (!$file->isFile() || $file->getExtension() !== 'php') continue;
    $source = file_get_contents($file->getPathname());
    if (stripos($source, 'amelia') !== false || str_contains($source, 'add_rest_route') || str_contains($source, 'wp_ajax_')) {
        throw new RuntimeException('Phase 1F Core source has forbidden runtime dependency: ' . $file->getFilename());
    }
}

echo "Phase 1F source/package-readiness contract passed (not runtime evidence)\n";
