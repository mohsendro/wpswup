<?php
namespace SLL;

defined( 'ABSPATH' ) || exit;

/**
 * Class Swup
 *
 * Server-side helpers for Swup 4 SPA transitions:
 *  - Animation CSS (compatible with Swup 4 class names)
 *  - Cache-busting / Vary headers for caching plugins
 *  - Elementor + WooCommerce compatibility hooks
 *
 * @package SLL
 */
class Swup {

	public function __construct() {
		add_action( 'wp_head',      [ $this, 'output_animation_styles' ], 10 );
		add_action( 'send_headers', [ $this, 'maybe_add_cache_headers' ] );
		add_filter( 'body_class',   [ $this, 'add_swup_body_class' ] );

		// Defer page-builder compat until wp so options are fully available
		add_action( 'wp', [ $this, 'pagebuilder_compat' ] );
	}

	/**
	 * Body class for theme CSS scoping.
	 */
	public function add_swup_body_class( array $classes ): array {
		if ( $this->is_swup_enabled() ) {
			$classes[] = 'swup-enabled';
			$classes[] = 'sll-swup';
		}
		return $classes;
	}

	/**
	 * Inject animation CSS that matches Swup 4 lifecycle classes.
	 */
	public function output_animation_styles(): void {
		if ( ! $this->is_swup_enabled() ) {
			return;
		}

		$animation = $this->opt( 'swup_animation', 'fade' );
		$duration  = (int) $this->opt( 'swup_duration', 300 );
		$scope     = $this->opt( 'swup_animation_scope', 'html' );

		echo '<style id="sll-swup-anim">';
		echo ':root{--sll-duration:' . esc_attr( (string) $duration ) . 'ms}';
		echo '.transition-swup{transition-property:opacity,transform;transition-duration:var(--sll-duration);transition-timing-function:ease}';

		if ( $animation === 'none' ) {
			echo '</style>';
			do_action( 'sll_after_animation_styles', $animation, $duration );
			return;
		}

		$prefix = ( $scope === 'containers' ) ? '.transition-swup' : 'html';

		switch ( $animation ) {
			case 'slide':
				echo "
				{$prefix}.is-changing .transition-swup{transition-property:opacity,transform}
				{$prefix}.is-animating.is-leaving .transition-swup{opacity:0;transform:translateY(-12px)}
				{$prefix}.is-animating.is-rendering .transition-swup{opacity:0;transform:translateY(12px)}
				";
				break;

			default: // fade
				echo "
				{$prefix}.is-changing .transition-swup{transition-property:opacity}
				{$prefix}.is-animating.is-leaving .transition-swup{opacity:0}
				{$prefix}.is-animating.is-rendering .transition-swup{opacity:0}
				";
				break;
		}

		echo '</style>';
		do_action( 'sll_after_animation_styles', $animation, $duration );
	}

	/**
	 * Cache plugin compatibility.
	 */
	public function maybe_add_cache_headers(): void {
		if ( ! $this->opt( 'compat_cache_plugins', true ) ) {
			return;
		}

		$is_swup = (
			( isset( $_SERVER['HTTP_X_REQUESTED_WITH'] ) && strtolower( (string) $_SERVER['HTTP_X_REQUESTED_WITH'] ) === 'swup' )
			|| isset( $_SERVER['HTTP_X_SWUP_REQUEST'] )
		);

		if ( $is_swup ) {
			header( 'Vary: X-Requested-With' );
			header( 'X-Rocket-No-Cache: 1' );
			header( 'Cache-Control: no-store, no-cache, must-revalidate' );
			do_action( 'sll_swup_request_headers' );
		}
	}

	/**
	 * Elementor + WooCommerce front-end compatibility.
	 */
	public function pagebuilder_compat(): void {
		if ( ! $this->opt( 'compat_page_builders', true ) ) {
			return;
		}

		if ( defined( 'ELEMENTOR_VERSION' ) && class_exists( '\Elementor\Plugin' ) ) {
			add_filter( 'elementor/frontend/print_google_fonts', '__return_true' );
		}

		do_action( 'sll_pagebuilder_compat' );
	}

	/** Safe option read without risking recursion. */
	private function opt( string $key, $default = null ) {
		$plugin = Plugin::instance();
		if ( ! $plugin->settings ) {
			return $default;
		}
		return $plugin->settings->get( $key, $default );
	}

	private function is_swup_enabled(): bool {
		return (bool) $this->opt( 'swup_enabled', true );
	}
}
