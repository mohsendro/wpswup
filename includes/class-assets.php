<?php
namespace SLL;

defined( 'ABSPATH' ) || exit;

/**
 * Class Assets
 *
 * Enqueues Swup.js (via CDN), official plugins, the lazy-renderer runtime,
 * and per-section CSS/JS files discovered from template-lazy folders.
 *
 * Compatible with Swup 4.x (hooks system, visit object, built-in scroll).
 *
 * @package SLL
 */
class Assets {

	/** Pinned Swup core version (CDN). */
	const SWUP_VERSION = '4.10.0';

	/** Official plugin versions compatible with Swup 4. */
	const HEAD_PLUGIN_VERSION    = '2.3.1';
	const SCROLL_PLUGIN_VERSION  = '3.2.0';
	const PRELOAD_PLUGIN_VERSION = '3.2.12';
	const FORMS_PLUGIN_VERSION   = '2.1.0';

	public function __construct() {
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_frontend' ] );
	}

	/**
	 * Enqueue frontend assets.
	 */
	public function enqueue_frontend(): void {
		if ( is_admin() ) {
			return;
		}

		$opts = sll()->settings->all();

		if ( $opts['swup_enabled'] ) {
			// Core – official package is "swup", not "@swup/swup"
			wp_enqueue_script(
				'swup',
				'https://unpkg.com/swup@' . self::SWUP_VERSION . '/dist/Swup.umd.js',
				[],
				self::SWUP_VERSION,
				true
			);

			// Head Plugin – keeps <title>, meta, stylesheets in sync
			wp_enqueue_script(
				'swup-head-plugin',
				'https://unpkg.com/@swup/head-plugin@' . self::HEAD_PLUGIN_VERSION . '/dist/index.umd.js',
				[ 'swup' ],
				self::HEAD_PLUGIN_VERSION,
				true
			);

			// Scroll Plugin – only when user wants animated / custom scroll.
			// Swup 4 already resets scroll + handles #anchors natively.
			if ( ! empty( $opts['swup_scroll_plugin'] ) ) {
				wp_enqueue_script(
					'swup-scroll-plugin',
					'https://unpkg.com/@swup/scroll-plugin@' . self::SCROLL_PLUGIN_VERSION . '/dist/index.umd.js',
					[ 'swup' ],
					self::SCROLL_PLUGIN_VERSION,
					true
				);
			}

			// Preload Plugin
			if ( ! empty( $opts['swup_preload'] ) ) {
				wp_enqueue_script(
					'swup-preload-plugin',
					'https://unpkg.com/@swup/preload-plugin@' . self::PRELOAD_PLUGIN_VERSION . '/dist/index.umd.js',
					[ 'swup' ],
					self::PRELOAD_PLUGIN_VERSION,
					true
				);
			}

			// Forms Plugin – useful for WooCommerce cart / checkout forms
			if ( ! empty( $opts['swup_forms'] ) ) {
				wp_enqueue_script(
					'swup-forms-plugin',
					'https://unpkg.com/@swup/forms-plugin@' . self::FORMS_PLUGIN_VERSION . '/dist/index.umd.js',
					[ 'swup' ],
					self::FORMS_PLUGIN_VERSION,
					true
				);
			}
		}

		// Lazy renderer CSS
		wp_enqueue_style(
			'sll-lazy',
			SLL_URL . 'assets/css/sll-lazy.css',
			[],
			SLL_VERSION
		);

		// Main runtime (depends on Swup only when enabled)
		$deps = $opts['swup_enabled'] ? [ 'swup' ] : [];
		wp_enqueue_script(
			'sll-main',
			SLL_URL . 'assets/js/sll-main.js',
			$deps,
			SLL_VERSION,
			true
		);

		wp_localize_script( 'sll-main', 'sllConfig', $this->js_config() );
	}

	/**
	 * Build the localized config object passed to JS.
	 */
	private function js_config(): array {
		$opts = sll()->settings->all();

		return apply_filters( 'sll_js_config', [
			'swupEnabled'       => (bool) $opts['swup_enabled'],
			'animation'         => $opts['swup_animation'],
			'duration'          => (int) $opts['swup_duration'],
			'cache'             => (bool) $opts['swup_cache'],
			'containers'        => array_values( array_filter( array_map( 'trim', explode( ',', (string) ( $opts['swup_containers'] ?? '' ) ) ) ) ),
			'containersAuto'     => empty( trim( (string) ( $opts['swup_containers'] ?? '' ) ) ),
			'linkSelector'      => $opts['swup_link_selector'],
			'scrollReset'       => (bool) $opts['swup_scroll_reset'],
			'scrollPlugin'      => (bool) ( $opts['swup_scroll_plugin'] ?? false ),
			'preload'           => (bool) $opts['swup_preload'],
			'forms'             => (bool) ( $opts['swup_forms'] ?? false ),
			'animationScope'    => $opts['swup_animation_scope'] ?? 'html',
			'native'            => (bool) ( $opts['swup_native'] ?? false ),
			'lazyEnabled'       => (bool) $opts['lazy_enabled'],
			'lazyThreshold'     => (float) $opts['lazy_threshold'],
			'lazyRootMargin'    => $opts['lazy_rootMargin'],
			'ajaxUrl'           => admin_url( 'admin-ajax.php' ),
			'nonce'             => wp_create_nonce( 'sll_lazy_render' ),
			'restUrl'           => rest_url( 'sll/v1/render/' ),
			'restNonce'         => wp_create_nonce( 'wp_rest' ),
			'debug'             => (bool) $opts['debug_mode'],
			// Compat flags for JS-side re-inits
			'compatWoo'         => class_exists( 'WooCommerce' ),
			'compatElementor'   => defined( 'ELEMENTOR_VERSION' ),
		] );
	}

	/**
	 * Enqueue a section's app.js / app.css.
	 */
	public function enqueue_section_assets( string $template_name, string $template_path ): void {
		$js       = $template_path . '/app.js';
		$css      = $template_path . '/app.css';
		$url_base = $this->path_to_url( $template_path );

		if ( file_exists( $css ) ) {
			wp_enqueue_style(
				"sll-section-{$template_name}",
				$url_base . '/app.css',
				[],
				filemtime( $css )
			);
		}

		if ( file_exists( $js ) ) {
			wp_enqueue_script(
				"sll-section-{$template_name}",
				$url_base . '/app.js',
				[ 'sll-main' ],
				filemtime( $js ),
				true
			);
		}
	}

	/**
	 * Convert absolute path under template-lazy to public URL.
	 */
	private function path_to_url( string $path ): string {
		$base_path = rtrim( SLL_TEMPLATE_LAZY_PATH, '/' );
		$base_url  = rtrim( SLL_TEMPLATE_LAZY_URL, '/' );

		if ( str_starts_with( $path, $base_path ) ) {
			return str_replace( $base_path, $base_url, $path );
		}

		// Plugin fallback
		$plugin_base = rtrim( SLL_DIR . 'template-lazy', '/' );
		$plugin_url  = rtrim( SLL_URL . 'template-lazy', '/' );
		return str_replace( $plugin_base, $plugin_url, $path );
	}
}
