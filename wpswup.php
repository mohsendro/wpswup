<?php
/**
 * Plugin Name: WP Swup
 * Plugin URI:  https://github.com/mohsendro/wpswup
 * Description: SPA-like page transitions with Swup.js 4 + scroll-based lazy section rendering for WordPress. Compatible with WooCommerce, Elementor, and WoodMart.
 * Version:     1.2.0
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
	define( 'SLL_VERSION', '1.2.0' );
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

	$relative = str_replace( '\\', '/', substr( $class, strlen( $prefix ) ) );
	$file     = $base . 'class-' . strtolower( str_replace( [ '_', '\\' ], [ '-', '/' ], $relative ) ) . '.php';

	if ( ! file_exists( $file ) && str_contains( $relative, 'Admin/' ) ) {
		$file = $base . 'admin/class-' . strtolower( str_replace( '_', '-', basename( $relative ) ) ) . '.php';
	}

	if ( file_exists( $file ) ) {
		require $file;
	}
} );

if ( ! function_exists( 'sll' ) ) {
	/**
	 * Main plugin instance.
	 *
	 * @return \SLL\Plugin
	 */
	function sll() {
		return \SLL\Plugin::instance();
	}
}

if ( ! function_exists( 'sll_section' ) ) {
	/**
	 * Render a lazy section placeholder.
	 *
	 * @param string $name Template folder under template-lazy/.
	 * @param array  $args Optional args passed to render.php.
	 */
	function sll_section( string $name, array $args = [] ): void {
		if ( function_exists( 'sll' ) && sll()->lazy_renderer ) {
			sll()->lazy_renderer->render_placeholder( $name, $args );
		}
	}
}

add_action( 'init', static function () {
	$install_mo = SLL_DIR . 'languages/install-mo.php';
	if ( file_exists( $install_mo ) ) {
		require_once $install_mo;
	}
	load_plugin_textdomain( 'wpswup', false, dirname( plugin_basename( SLL_FILE ) ) . '/languages' );
}, 0 );

// Boot only once (prevents recursion / double-load).
add_action( 'plugins_loaded', static function () {
	\SLL\Plugin::instance();
}, 5 );
