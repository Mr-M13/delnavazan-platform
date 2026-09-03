<?php
/**
 * Plugin Name: Delnavazan Platform
 * Description: Canonical Core foundation for Delnavazan. No Amelia or provider integration.
 * Version: 0.1.0
 * Requires at least: 6.4
 * Requires PHP: 8.1
 */
defined( 'ABSPATH' ) || exit;
define( 'DZN_PLATFORM_VERSION', '0.1.0' );
define( 'DZN_PLATFORM_SCHEMA_VERSION', '1' );
define( 'DZN_PLATFORM_FILE', __FILE__ );
define( 'DZN_PLATFORM_DIR', plugin_dir_path( __FILE__ ) );

spl_autoload_register( static function ( $class ) {
	$prefix = 'Delnavazan\\Platform\\';
	if ( ! str_starts_with( $class, $prefix ) ) { return; }
	$file = DZN_PLATFORM_DIR . 'src/' . str_replace( '\\', '/', substr( $class, strlen( $prefix ) ) ) . '.php';
	if ( is_readable( $file ) ) { require $file; }
} );

register_activation_hook( __FILE__, array( 'Delnavazan\\Platform\\Core\\Infrastructure\\Migration\\Migrator', 'activate' ) );
add_action( 'plugins_loaded', static function () {
	Delnavazan\Platform\Core\Infrastructure\Migration\Migrator::activate();
	Delnavazan\Platform\Admin\Controller\Menu::register();
} );
