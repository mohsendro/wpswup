<?php
namespace SLL;

defined( 'ABSPATH' ) || exit;

/**
 * Class Assets
 *
 * Enqueues Swup 4 + official themes/plugins and minified plugin assets.
 *
 * @package SLL
 */
class Assets {

	const SWUP_VERSION             = '4.10.0';
	const HEAD_PLUGIN_VERSION      = '2.3.1';
	const SCROLL_PLUGIN_VERSION    = '3.2.0';
	const PRELOAD_PLUGIN_VERSION   = '3.2.12';
	const FORMS_PLUGIN_VERSION     = '2.1.0';
	const PROGRESS_PLUGIN_VERSION  = '3.2.0';
	const FADE_THEME_VERSION       = '2.0.2';
	const SLIDE_THEME_VERSION      = '2.0.2';
	const OVERLAY_THEME_VERSION    = '2.0.2';

	public function __construct() {
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_frontend' ] );
	}

	private function asset_url( string $relative ): string {
		$min = preg_replace( '/\.(js|css)$/', '.min.$1', $relative );
		$path_min = SLL_DIR . $min;
		if ( file_exists( $path_min ) ) {
			return SLL_URL . $min;
		}
		return SLL_URL . $relative;
	}

	private function asset_ver( string $relative ): string {
		$min  = preg_replace( '/\.(js|css)$/', '.min.$1', $relative );
		$path = file_exists( SLL_DIR . $min ) ? SLL_DIR . $min : SLL_DIR . $relative;
		return file_exists( $path ) ? (string) filemtime( $path ) : SLL_VERSION;
	}

	public function enqueue_frontend(): void {
		if ( is_admin() ) {
			return;
		}

		$opts = sll()->settings->all();
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

			if ( ! empty( $opts['swup_preload'] ) ) {
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
			'sll-progress',
			$this->asset_url( 'assets/css/sll-progress.css' ),
			[],
			$this->asset_ver( 'assets/css/sll-progress.css' )
		);

		$deps = ! empty( $opts['swup_enabled'] ) ? [ 'swup' ] : [];
		wp_enqueue_script(
			'sll-main',
			$this->asset_url( 'assets/js/sll-main.js' ),
			$deps,
			$this->asset_ver( 'assets/js/sll-main.js' ),
			true
		);

		wp_enqueue_script(
			'sll-woodmart-fix',
			$this->asset_url( 'assets/js/sll-woodmart-fix.js' ),
			[ 'sll-main' ],
			$this->asset_ver( 'assets/js/sll-woodmart-fix.js' ),
			true
		);

		wp_enqueue_script(
			'sll-compat',
			$this->asset_url( 'assets/js/sll-compat.js' ),
			[ 'sll-main' ],
			$this->asset_ver( 'assets/js/sll-compat.js' ),
			true
		);

		if ( defined( 'ELEMENTOR_VERSION' ) ) {
			wp_enqueue_script(
				'sll-elementor',
				$this->asset_url( 'assets/js/sll-elementor.js' ),
				[ 'sll-main' ],
				$this->asset_ver( 'assets/js/sll-elementor.js' ),
				true
			);
		}

		wp_localize_script( 'sll-main', 'sllConfig', $this->js_config() );
	}

	private function js_config(): array {
		$opts = sll()->settings->all();

		return apply_filters( 'sll_js_config', [
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
			'debug'             => (bool) $opts['debug_mode'],
			'compatWoo'         => class_exists( 'WooCommerce' ),
			'compatElementor'   => defined( 'ELEMENTOR_VERSION' ),
		] );
	}
}
