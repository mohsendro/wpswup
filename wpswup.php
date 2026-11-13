<?php
/**
 * Plugin Name: WP Swup
 * Plugin URI:  https://github.com/mohsendro/wpswup
 * Description: SPA-like page transitions with Swup.js 4 + scroll-based lazy section rendering for WordPress. Compatible with WooCommerce, Elementor, and WoodMart.
 * Version:     1.1.2
 * Author:      Mohsen Derogar
 * Author URI:  https://github.com/mohsendro
 * License:     GPL-2.0+
 * Text Domain: wpswup
 * Domain Path: /languages
 * Requires at least: 5.9
 * Requires PHP: 8.0
 */

defined( 'ABSPATH' ) || exit;

// ─── Constants ────────────────────────────────────────────────────────────────
if ( ! defined( 'SLL_VERSION' ) ) {
	define( 'SLL_VERSION', '1.1.2' );
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

// ─── Autoloader ───────────────────────────────────────────────────────────────
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

// ─── Bootstrap ────────────────────────────────────────────────────────────────
add_action( 'plugins_loaded', function () {
	\SLL\Plugin::instance();
} );

/**
 * Global helper – returns the Plugin singleton.
 *
 * @return \SLL\Plugin
 */
if ( ! function_exists( 'sll' ) ) {
	function sll() {
		return \SLL\Plugin::instance();
	}
}

/**
 * Output a lazy-loaded section placeholder in theme templates.
 *
 * @param string $name  Section folder name inside template-lazy/
 * @param array  $args  Args passed to render.php
 * @param string $tag   Wrapper HTML tag (default: section)
 * @param string $class Extra CSS class on the wrapper
 */
if ( ! function_exists( 'sll_section' ) ) {
	function sll_section( string $name, array $args = [], string $tag = 'section', string $class = '' ): void {
		sll()->lazy->render_placeholder( $name, $args, $tag, $class );
	}
}
