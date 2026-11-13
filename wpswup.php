<?php
/**
 * Plugin Name: WP Swup
 * Plugin URI:  https://github.com/mohsendro/wpswup
 * Description: SPA-like page transitions with Swup.js 4 for WordPress. Compatible with WooCommerce, Elementor, and WoodMart.
 * Version:     1.3.0
 * Author:      Mohsen Derogar
 * Author URI:  https://github.com/mohsendro
 * License:     GPL-2.0+
 * Text Domain: wpswup
 * Domain Path: /languages
 * Requires at least: 5.9
 * Requires PHP: 8.0
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'SLL_VERSION' ) ) {
	define( 'SLL_VERSION', '1.3.0' );
}
if ( ! defined( 'SLL_FILE' ) ) {
	define( 'SLL_FILE', __FILE__ );
}
if ( ! defined( 'SLL_DIR' ) ) {
	define( 'SLL_DIR', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'SLL_URL' ) ) {
	define( 'SLL_URL', plugin_dir_url( __FILE__ ) );
}
if ( ! defined( 'SLL_SLUG' ) ) {
	define( 'SLL_SLUG', 'wpswup' );
}

spl_autoload_register( function ( $class ) {
	$prefix = 'SLL\\';
	$base   = SLL_DIR . 'includes/';

	if ( strncmp( $prefix, $class, strlen( $prefix ) ) !== 0 ) {
		return;
	}

	$relative = substr( $class, strlen( $prefix ) );
	$file     = $base . 'class-' . str_replace( '_', '-', strtolower( $relative ) ) . '.php';

	// Admin subfolder
	if ( str_starts_with( $relative, 'Admin\\' ) ) {
		$rel  = substr( $relative, 6 );
		$file = $base . 'admin/class-' . str_replace( '_', '-', strtolower( $rel ) ) . '.php';
	}

	if ( file_exists( $file ) ) {
		require_once $file;
	}
} );

// Bootstrap MO from PO when needed (pure-PHP, no gettext binary).
$install_mo = SLL_DIR . 'languages/install-mo.php';
if ( file_exists( $install_mo ) ) {
	require_once $install_mo;
}

/**
 * Main plugin bootstrap.
 *
 * @return \SLL\Plugin
 */
function sll() {
	return \SLL\Plugin::instance();
}

add_action( 'plugins_loaded', function () {
	load_plugin_textdomain( 'wpswup', false, dirname( plugin_basename( SLL_FILE ) ) . '/languages' );
	sll();
}, 5 );
