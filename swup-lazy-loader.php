<?php
/**
 * Plugin Name: Swup Lazy Loader
 * Plugin URI:  https://github.com/your-repo/swup-lazy-loader
 * Description: SPA-like page transitions with Swup.js 4 + scroll-based lazy section rendering for WordPress. Compatible with WooCommerce and Elementor.
 * Version:     1.1.0
 * Author:      Your Name
 * License:     GPL-2.0+
 * Text Domain: swup-lazy-loader
 * Domain Path: /languages
 * Requires at least: 5.9
 * Requires PHP: 8.0
 */

defined( 'ABSPATH' ) || exit;

// ─── Constants ────────────────────────────────────────────────────────────────
define( 'SLL_VERSION', '1.1.0' );
define( 'SLL_FILE',    __FILE__ );
define( 'SLL_DIR',     plugin_dir_path( __FILE__ ) );
define( 'SLL_URL',     plugin_dir_url( __FILE__ ) );
define( 'SLL_SLUG',    'swup-lazy-loader' );

// ─── Autoloader ───────────────────────────────────────────────────────────────
spl_autoload_register( function ( $class ) {
	$prefix = 'SLL\\';
	$base   = SLL_DIR . 'includes/';

	if ( strncmp( $prefix, $class, strlen( $prefix ) ) !== 0 ) {
		return;
	}

	$relative = str_replace( '\\', '/', substr( $class, strlen( $prefix ) ) );
	$file = $base . 'class-' . strtolower( str_replace( [ '_', '\\' ], [ '-', '/' ], $relative ) ) . '.php';

	// Fallback for Admin namespace: includes/admin/class-*.php
	if ( ! file_exists( $file ) && str_contains( $relative, 'Admin/' ) ) {
		$file = $base . 'admin/class-' . strtolower( str_replace( '_', '-', basename( $relative ) ) ) . '.php';
	}

	if ( file_exists( $file ) ) {
		require $file;
	}
} );

// ─── Bootstrap ────────────────────────────────────────────────────────────────
add_action( 'plugins_loaded', function () {
	SLL\Plugin::instance();
} );

/**
 * Global helper – returns the Plugin singleton.
 *
 * @return SLL\Plugin
 */
function sll() {
	return SLL\Plugin::instance();
}

/**
 * Output a lazy-loaded section placeholder in your theme templates.
 *
 * Usage:
 *   <?php sll_section( 'hero' ); ?>
 *   <?php sll_section( 'features', [ 'limit' => 6 ], 'div', 'my-class' ); ?>
 *
 * @param string $name  Section folder name inside template-lazy/
 * @param array  $args  Args passed to render.php
 * @param string $tag   Wrapper HTML tag (default: section)
 * @param string $class Extra CSS class on the wrapper
 */
function sll_section( string $name, array $args = [], string $tag = 'section', string $class = '' ): void {
	if ( function_exists( 'sll' ) ) {
		sll()->lazy->render_placeholder( $name, $args, $tag, $class );
	}
}
