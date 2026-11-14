<?php
/**
 * Plugin Name: WP Swup
 * Plugin URI:  https://github.com/mohsendro/wpswup
 * Description: SPA-like page transitions with Swup.js 4 for WordPress. Compatible with WooCommerce, Elementor, and WoodMart.
 * Version:     1.5.0
 * Author:      Mohsen Derogar
 * Author URI:  https://github.com/mohsendro
 * License:     GPL-2.0+
 * Text Domain: wpswup
 * Domain Path: /languages
 * Requires at least: 5.9
 * Requires PHP: 8.0
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'WPSWUP_VERSION' ) ) {
	define( 'WPSWUP_VERSION', '1.5.0' );
}
if ( ! defined( 'WPSWUP_FILE' ) ) {
	define( 'WPSWUP_FILE', __FILE__ );
}
if ( ! defined( 'WPSWUP_DIR' ) ) {
	define( 'WPSWUP_DIR', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'WPSWUP_URL' ) ) {
	define( 'WPSWUP_URL', plugin_dir_url( __FILE__ ) );
}
if ( ! defined( 'WPSWUP_SLUG' ) ) {
	define( 'WPSWUP_SLUG', 'wpswup' );
}

spl_autoload_register( function ( $class ) {
	$prefix = 'Wpswup\\';
	$base   = WPSWUP_DIR . 'includes/';

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

/**
 * Main plugin bootstrap.
 *
 * @return \Wpswup\Plugin
 */
function wpswup() {
	return \Wpswup\Plugin::instance();
}

add_action( 'plugins_loaded', function () {
	load_plugin_textdomain( 'wpswup', false, dirname( plugin_basename( WPSWUP_FILE ) ) . '/languages' );
	wpswup();
}, 5 );
