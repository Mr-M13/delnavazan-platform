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
foreach (['Requires PHP: 8.1', 'register_activation_hook', 'spl_autoload_register', 'DZN_PLATFORM_PHASE_1F_NONCE_DIAGNOSTICS', 'DZN_PLATFORM_BUILD_ID', "'phase1f-identity-gate-20260904.1'", 'NonceLifecycleDiagnostic::register()'] as $fragment) {
    if (!str_contains($bootstrap, $fragment)) throw new RuntimeException("Phase 1F bootstrap rule missing: {$fragment}");
}

$screen = file_get_contents("{$root}/src/Admin/Controller/ScreenController.php");
foreach ([
    'public static function handlePost(string $screen)',
    "NonceLifecycleDiagnostic::logStage('mutation_load_hook')",
    'logNonceDiagnostic($action, $post)',
    'wp_verify_nonce($post[\'_wpnonce\'], self::nonceAction($action))',
    'check_admin_referer(self::nonceAction($action))',
    'wp_nonce_field(self::nonceAction($action))',
    "NonceLifecycleDiagnostic::logStage('nonce_verified')",
    "NonceLifecycleDiagnostic::logStage('mutation_complete')",
    "NonceLifecycleDiagnostic::logStage('redirect_enter')",
    'if (wp_safe_redirect($url)) exit',
    "' · Build ' . esc_html(DZN_PLATFORM_BUILD_ID)",
    "'nonce_present'", "'nonce_scalar'", "'nonce_verify'", "'user_id'",
    "'capability_allowed'", "'page'", "'method'",
] as $fragment) {
    if (!str_contains($screen, $fragment)) throw new RuntimeException("Phase 1F nonce diagnostic missing: {$fragment}");
}
foreach (['$_COOKIE', 'HTTP_AUTHORIZATION', 'AUTH_SALT', "'nonce_value'"] as $forbidden) {
    if (str_contains($screen, $forbidden)) throw new RuntimeException("Phase 1F nonce diagnostic exposes forbidden data: {$forbidden}");
}
if (str_contains($screen, 'self::handleMutation(); self::renderMessages()')) {
    throw new RuntimeException('Phase 1F mutation handling must not run in the post-header render callback');
}

$menu = file_get_contents("{$root}/src/Admin/Controller/Menu.php");
foreach (["add_action('load-' . \$hook", 'ScreenController::handlePost($screen)', 'before admin-header.php'] as $fragment) {
    if (!str_contains($menu, $fragment)) throw new RuntimeException("Phase 1F pre-header mutation hook missing: {$fragment}");
}

$lifecycle = file_get_contents("{$root}/src/Admin/Diagnostic/NonceLifecycleDiagnostic.php");
foreach ([
    'plugin_bootstrap', 'plugins_loaded', 'admin_init_early', 'admin_menu_early',
    'current_screen', 'admin_head', 'load_page', 'mutation_load_hook',
    'nonce_verified', 'mutation_complete', 'redirect_enter', 'redirect_failed',
    'submenu_callback',
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
