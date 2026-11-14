<?php
namespace Wpswup;

defined( 'ABSPATH' ) || exit;

/**
 * Class Assets
 *
 * Enqueues Swup 4 + official themes/plugins and minified plugin assets.
 *
 * @package Wpswup
 */
class Assets {

	const SWUP_VERSION             = '4.10.0';
	const HEAD_PLUGIN_VERSION      = '2.3.1';
	const SCRIPTS_PLUGIN_VERSION   = '2.1.1';
	const SCROLL_PLUGIN_VERSION    = '3.2.0';
	const PRELOAD_PLUGIN_VERSION   = '3.2.12';
	const FORMS_PLUGIN_VERSION     = '2.1.0';
	const PROGRESS_PLUGIN_VERSION  = '3.2.0';
	const FADE_THEME_VERSION       = '2.0.2';
	const SLIDE_THEME_VERSION      = '2.0.2';
	const OVERLAY_THEME_VERSION    = '2.0.2';

	/** Handles that must never be re-evaluated by @swup/scripts-plugin. */
	private const IGNORE_SCRIPT_HANDLES = [
		'swup',
		'swup-head-plugin',
		'swup-scripts-plugin',
		'swup-progress-plugin',
		'swup-fade-theme',
		'swup-slide-theme',
		'swup-overlay-theme',
		'swup-scroll-plugin',
		'swup-preload-plugin',
		'swup-forms-plugin',
		'wpswup-main',
		'wpswup-woodmart-fix',
		'wpswup-compat',
		'wpswup-elementor',
		'jquery',
		'jquery-core',
		'jquery-migrate',
		'wp-hooks',
		'wp-i18n',
		'wp-polyfill',
		'admin-bar',
	];

	public function __construct() {
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_frontend' ] );
		add_filter( 'script_loader_tag', [ $this, 'tag_ignored_scripts' ], 10, 3 );
	}

	private function asset_url( string $relative ): string {
		$min = preg_replace( '/\.(js|css)$/', '.min.$1', $relative );
		$path_min = WPSWUP_DIR . $min;
		if ( file_exists( $path_min ) ) {
			return WPSWUP_URL . $min;
		}
		return WPSWUP_URL . $relative;
	}

	private function asset_ver( string $relative ): string {
		$min  = preg_replace( '/\.(js|css)$/', '.min.$1', $relative );
		$path = file_exists( WPSWUP_DIR . $min ) ? WPSWUP_DIR . $min : WPSWUP_DIR . $relative;
		return file_exists( $path ) ? (string) filemtime( $path ) : WPSWUP_VERSION;
	}

	/**
	 * Inject data-swup-ignore-script so ScriptsPlugin never re-runs persistent scripts.
	 */
	public function tag_ignored_scripts( string $tag, string $handle, string $src ): string {
		if ( in_array( $handle, self::IGNORE_SCRIPT_HANDLES, true ) ) {
			if ( false === strpos( $tag, 'data-swup-ignore-script' ) ) {
				$tag = str_replace( '<script ', '<script data-swup-ignore-script ', $tag );
			}
		}
		return $tag;
	}

	public function enqueue_frontend(): void {
		if ( is_admin() ) {
			return;
		}

		$opts = wpswup()->settings->all();
		$anim = (string) ( $opts['swup_animation'] ?? 'fade' );

		if ( ! empty( $opts['swup_enabled'] ) ) {
			wp_enqueue_script(
				'swup',
				'https://unpkg.com/swup@' . self::SWUP_VERSION . '/dist/Swup.umd.js',
				[],
				self::SWUP_VERSION,
				true
			);

			wp_enqueue_script(
				'swup-head-plugin',
				'https://unpkg.com/@swup/head-plugin@' . self::HEAD_PLUGIN_VERSION . '/dist/index.umd.js',
				[ 'swup' ],
				self::HEAD_PLUGIN_VERSION,
				true
			);

			if ( ! empty( $opts['swup_scripts_plugin'] ) ) {
				wp_enqueue_script(
					'swup-scripts-plugin',
					'https://unpkg.com/@swup/scripts-plugin@' . self::SCRIPTS_PLUGIN_VERSION . '/dist/index.umd.js',
					[ 'swup' ],
					self::SCRIPTS_PLUGIN_VERSION,
					true
				);
			}

			wp_enqueue_script(
				'swup-progress-plugin',
				'https://unpkg.com/@swup/progress-plugin@' . self::PROGRESS_PLUGIN_VERSION . '/dist/index.umd.js',
				[ 'swup' ],
				self::PROGRESS_PLUGIN_VERSION,
				true
			);

			if ( $anim === 'fade' ) {
				wp_enqueue_script(
					'swup-fade-theme',
					'https://unpkg.com/@swup/fade-theme@' . self::FADE_THEME_VERSION . '/dist/index.umd.js',
					[ 'swup' ],
					self::FADE_THEME_VERSION,
					true
				);
			} elseif ( in_array( $anim, [ 'slide', 'slide_reversed' ], true ) ) {
				wp_enqueue_script(
					'swup-slide-theme',
					'https://unpkg.com/@swup/slide-theme@' . self::SLIDE_THEME_VERSION . '/dist/index.umd.js',
					[ 'swup' ],
					self::SLIDE_THEME_VERSION,
					true
				);
			} elseif ( str_starts_with( $anim, 'overlay' ) ) {
				wp_enqueue_script(
					'swup-overlay-theme',
					'https://unpkg.com/@swup/overlay-theme@' . self::OVERLAY_THEME_VERSION . '/dist/index.umd.js',
					[ 'swup' ],
					self::OVERLAY_THEME_VERSION,
					true
				);
			}

			if ( ! empty( $opts['swup_scroll_plugin'] ) ) {
				wp_enqueue_script(
					'swup-scroll-plugin',
					'https://unpkg.com/@swup/scroll-plugin@' . self::SCROLL_PLUGIN_VERSION . '/dist/index.umd.js',
					[ 'swup' ],
					self::SCROLL_PLUGIN_VERSION,
					true
				);
			}

			// Preload plugin requires Swup cache; enqueue only when both are on.
			if ( ! empty( $opts['swup_preload'] ) && ! empty( $opts['swup_cache'] ) ) {
				wp_enqueue_script(
					'swup-preload-plugin',
					'https://unpkg.com/@swup/preload-plugin@' . self::PRELOAD_PLUGIN_VERSION . '/dist/index.umd.js',
					[ 'swup' ],
					self::PRELOAD_PLUGIN_VERSION,
					true
				);
			}

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

		wp_enqueue_style(
			'wpswup-progress',
			$this->asset_url( 'assets/css/wpswup-progress.css' ),
			[],
			$this->asset_ver( 'assets/css/wpswup-progress.css' )
		);

		$deps = ! empty( $opts['swup_enabled'] ) ? [ 'swup' ] : [];
		wp_enqueue_script(
			'wpswup-main',
			$this->asset_url( 'assets/js/wpswup-main.js' ),
			$deps,
			$this->asset_ver( 'assets/js/wpswup-main.js' ),
			true
		);

		wp_enqueue_script(
			'wpswup-woodmart-fix',
			$this->asset_url( 'assets/js/wpswup-woodmart-fix.js' ),
			[ 'wpswup-main' ],
			$this->asset_ver( 'assets/js/wpswup-woodmart-fix.js' ),
			true
		);

		wp_enqueue_script(
			'wpswup-compat',
			$this->asset_url( 'assets/js/wpswup-compat.js' ),
			[ 'wpswup-main' ],
			$this->asset_ver( 'assets/js/wpswup-compat.js' ),
			true
		);

		if ( defined( 'ELEMENTOR_VERSION' ) ) {
			wp_enqueue_script(
				'wpswup-elementor',
				$this->asset_url( 'assets/js/wpswup-elementor.js' ),
				[ 'wpswup-main' ],
				$this->asset_ver( 'assets/js/wpswup-elementor.js' ),
				true
			);
		}

		wp_localize_script( 'wpswup-main', 'wpswupConfig', $this->js_config() );
	}

	private function js_config(): array {
		$opts = wpswup()->settings->all();

		return apply_filters( 'wpswup_js_config', [
			'swupEnabled'       => (bool) $opts['swup_enabled'],
			'animation'         => $opts['swup_animation'],
			'duration'          => (int) $opts['swup_duration'],
			'cache'             => (bool) $opts['swup_cache'],
			'containers'        => array_values( array_filter( array_map( 'trim', explode( ',', (string) ( $opts['swup_containers'] ?? '' ) ) ) ) ),
			'containersAuto'    => empty( trim( (string) ( $opts['swup_containers'] ?? '' ) ) ),
			'linkSelector'      => $opts['swup_link_selector'],
			'scrollReset'       => (bool) $opts['swup_scroll_reset'],
			'scrollPlugin'      => (bool) ( $opts['swup_scroll_plugin'] ?? false ),
			'preload'           => (bool) $opts['swup_preload'],
			'forms'             => (bool) ( $opts['swup_forms'] ?? false ),
			'animationScope'    => $opts['swup_animation_scope'] ?? 'html',
			'native'            => (bool) ( $opts['swup_native'] ?? false ),
			'scriptsPlugin'     => (bool) ( $opts['swup_scripts_plugin'] ?? true ),
			'awaitMedia'        => (bool) ( $opts['swup_await_media'] ?? true ),
			'assetTimeout'      => max( 1000, (int) ( $opts['swup_asset_timeout'] ?? 8000 ) ),
			'debug'             => (bool) $opts['debug_mode'],
			'compatWoo'         => class_exists( 'WooCommerce' ),
			'compatElementor'   => defined( 'ELEMENTOR_VERSION' ),
		] );
	}
}
