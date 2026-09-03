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
foreach (['Requires PHP: 8.1', 'register_activation_hook', 'spl_autoload_register', 'DZN_PLATFORM_PHASE_1F_NONCE_DIAGNOSTICS', 'NonceLifecycleDiagnostic::register()'] as $fragment) {
    if (!str_contains($bootstrap, $fragment)) throw new RuntimeException("Phase 1F bootstrap rule missing: {$fragment}");
}

$screen = file_get_contents("{$root}/src/Admin/Controller/ScreenController.php");
foreach ([
    'logNonceDiagnostic($action, $post)',
    'wp_verify_nonce($post[\'_wpnonce\'], self::nonceAction($action))',
    'check_admin_referer(self::nonceAction($action))',
    'wp_nonce_field(self::nonceAction($action))',
    "'nonce_present'", "'nonce_scalar'", "'nonce_verify'", "'user_id'",
    "'capability_allowed'", "'page'", "'method'",
] as $fragment) {
    if (!str_contains($screen, $fragment)) throw new RuntimeException("Phase 1F nonce diagnostic missing: {$fragment}");
}
foreach (['$_COOKIE', 'HTTP_AUTHORIZATION', 'AUTH_SALT', "'nonce_value'"] as $forbidden) {
    if (str_contains($screen, $forbidden)) throw new RuntimeException("Phase 1F nonce diagnostic exposes forbidden data: {$forbidden}");
}

$lifecycle = file_get_contents("{$root}/src/Admin/Diagnostic/NonceLifecycleDiagnostic.php");
foreach ([
    'plugin_bootstrap', 'plugins_loaded', 'admin_init_early', 'admin_menu_early',
    'current_screen', 'admin_head', 'load_page', 'submenu_callback',
    'watchLoadHook',
    "'stage'", "'action'", "'nonce_present'", "'nonce_scalar'", "'nonce_verify'",
    "'user_id'", "'capability_allowed'", "'page'", "'method'",
    "'referer_field_present'", "'core_action_present'", "'core_action2_present'",
] as $fragment) {
    if (!str_contains($lifecycle . $screen, $fragment)) throw new RuntimeException("Phase 1F lifecycle diagnostic missing: {$fragment}");
}
foreach (['$_COOKIE', 'HTTP_AUTHORIZATION', 'AUTH_SALT', "'nonce_value'", "'post_body'", "'password'", "'credential'"] as $forbidden) {
    if (str_contains($lifecycle, $forbidden)) throw new RuntimeException("Phase 1F lifecycle diagnostic exposes forbidden data: {$forbidden}");
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

$runtimeFiles = [
    "{$root}/delnavazan-platform.php",
    "{$root}/uninstall.php",
];
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator("{$root}/src")) as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $runtimeFiles[] = $file->getPathname();
    }
}

// These are source-code contexts, not string literals: an autoloader prefix
// such as 'Delnavazan\\Platform\\' is intentional and must remain untouched.
$doubledNamespaceContexts = [
    'new \\\\',
    'extends \\\\',
    'implements \\\\',
    'instanceof \\\\',
    'catch (\\\\',
    'use \\\\',
];
foreach ($runtimeFiles as $runtimeFile) {
    $source = file_get_contents($runtimeFile);
    foreach ($doubledNamespaceContexts as $needle) {
        if (str_contains($source, $needle)) {
            throw new RuntimeException('Phase 1F malformed doubled namespace separator: ' . $runtimeFile);
        }
    }
    foreach (['\\\\RuntimeException', '\\\\InvalidArgumentException'] as $needle) {
        if (str_contains($source, $needle)) {
            throw new RuntimeException('Phase 1F malformed global exception reference: ' . $runtimeFile);
        }
    }
}

echo "Phase 1F source/package-readiness contract passed (not runtime evidence)\n";
