<?php
namespace Wpswup;

defined( 'ABSPATH' ) || exit;

/**
 * Class Swup
 *
 * Server-side helpers for Swup 4 SPA transitions.
 *
 * @package Wpswup
 */
class Swup {

	public function __construct() {
		add_action( 'wp_head',      [ $this, 'output_animation_styles' ], 10 );
		add_action( 'send_headers', [ $this, 'maybe_add_cache_headers' ] );
		add_filter( 'body_class',   [ $this, 'add_swup_body_class' ] );
		add_action( 'wp', [ $this, 'pagebuilder_compat' ] );
	}

	public function add_swup_body_class( array $classes ): array {
		if ( $this->is_swup_enabled() ) {
			$classes[] = 'swup-enabled';
			$classes[] = 'wpswup-swup';
			$anim = (string) $this->opt( 'swup_animation', 'fade' );
			$classes[] = 'wpswup-theme-' . sanitize_html_class( $anim );
		}
		return $classes;
	}

	public function output_animation_styles(): void {
		if ( ! $this->is_swup_enabled() ) {
			return;
		}

		$animation = (string) $this->opt( 'swup_animation', 'fade' );
		$duration  = (int) $this->opt( 'swup_duration', 400 );
		$sec       = max( 0.05, $duration / 1000 );

		echo '<style id="wpswup-swup-anim">';
		echo ':root{--wpswup-duration:' . esc_attr( (string) $duration ) . 'ms}';
		echo 'body{';
		echo '--swup-fade-theme-duration:' . esc_attr( (string) $sec ) . 's;';
		echo '--swup-slide-theme-duration-fade:' . esc_attr( (string) $sec ) . 's;';
		echo '--swup-slide-theme-duration-slide:' . esc_attr( (string) ( $sec * 1.15 ) ) . 's;';
		echo '--swup-overlay-theme-duration:' . esc_attr( (string) max( 0.35, $sec * 1.2 ) ) . 's;';
		echo '}';

		if ( $animation === 'none' ) {
			echo '.transition-swup{transition:none!important}';
			echo 'html.is-changing .transition-swup,html.is-animating .transition-swup{opacity:1!important;transform:none!important}';
		}

		echo '</style>';
		do_action( 'wpswup_after_animation_styles', $animation, $duration );
	}

	public function maybe_add_cache_headers(): void {
		if ( ! $this->opt( 'compat_cache_plugins', true ) ) {
			return;
		}

		$is_swup = (
			( isset( $_SERVER['HTTP_X_REQUESTED_WITH'] ) && strtolower( (string) $_SERVER['HTTP_X_REQUESTED_WITH'] ) === 'swup' )
			|| isset( $_SERVER['HTTP_X_SWUP_REQUEST'] )
		);

		if ( ! $is_swup ) {
			return;
		}

		header( 'Vary: X-Requested-With, Accept-Encoding', false );

		$bypass = is_user_logged_in();
		if ( ! $bypass && ! empty( $_COOKIE ) ) {
			foreach ( array_keys( $_COOKIE ) as $ck ) {
				$ck = (string) $ck;
				if (
					str_starts_with( $ck, 'wordpress_logged_in_' )
					|| str_starts_with( $ck, 'wp_woocommerce_session_' )
					|| str_starts_with( $ck, 'woocommerce_cart_hash' )
					|| str_starts_with( $ck, 'woocommerce_items_in_cart' )
				) {
					$bypass = true;
					break;
				}
			}
		}

		if ( $bypass ) {
			header( 'X-Rocket-No-Cache: 1' );
			header( 'X-LiteSpeed-Cache-Control: no-cache' );
			header( 'Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0' );
			header( 'Pragma: no-cache' );
		} else {
			header( 'Cache-Control: public, max-age=300, s-maxage=600, stale-while-revalidate=60' );
		}

		do_action( 'wpswup_swup_request_headers', $bypass );
	}

	public function pagebuilder_compat(): void {
		if ( ! $this->opt( 'compat_page_builders', true ) ) {
			return;
		}

		if ( defined( 'ELEMENTOR_VERSION' ) && class_exists( '\\Elementor\\Plugin' ) ) {
			add_filter( 'elementor/frontend/print_google_fonts', '__return_true' );
		}

		do_action( 'wpswup_pagebuilder_compat' );
	}

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
