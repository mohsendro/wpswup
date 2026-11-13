<?php
namespace Wpswup;

defined( 'ABSPATH' ) || exit;

/**
 * Class Settings
 *
 * Single serialized option row: wpswup_options (max 1 wp_options row for settings).
 *
 * @package Wpswup
 */
class Settings {

	/** Only option key used by this plugin for settings. */
	const OPTION_KEY = 'wpswup_options';

	/** Allowed official + built-in animation themes. */
	const THEMES = [
		'none',
		'fade',
		'slide',
		'slide_reversed',
		'overlay',
		'overlay_left',
		'overlay_top',
		'overlay_bottom',
	];

	/** @var array<string,mixed> */
	private array $options;

	/** @var array<string,mixed> */
	private array $defaults = [
		'swup_enabled'          => true,
		// Official Swup themes: fade | slide | slide_reversed | overlay* | none
		'swup_animation'        => 'fade',
		'swup_duration'         => 400,
		'swup_cache'            => true,
		'swup_containers'       => '',
		'swup_link_selector'    => 'a[href]:not([data-no-swup]):not([target="_blank"]):not([href^="#"]):not([href^="mailto:"]):not([href^="tel:"])',
		'swup_scroll_reset'     => true,
		'swup_scroll_plugin'    => false,
		'swup_preload'          => true,
		'swup_forms'            => true,
		'swup_animation_scope'  => 'html',
		'swup_native'           => false,
		'compat_cache_plugins'  => true,
		'compat_page_builders'  => true,
		'debug_mode'            => false,
	];

	public function __construct() {
		$saved = get_option( self::OPTION_KEY, null );
		// Migrate legacy option key from pre-1.4.0 (sll_options).
		if ( null === $saved || [] === $saved ) {
			$legacy = get_option( 'sll_options', null );
			if ( is_array( $legacy ) && $legacy ) {
				$saved = $legacy;
				update_option( self::OPTION_KEY, $legacy, true );
				delete_option( 'sll_options' );
			}
		}
		$this->options = wp_parse_args( is_array( $saved ) ? $saved : [], $this->defaults );
	}

	public function get( string $key, $default = null ) {
		return $this->options[ $key ] ?? ( $default ?? $this->defaults[ $key ] ?? null );
	}

	public function set( string $key, $value ): void {
		$this->options[ $key ] = $value;
	}

	public function save(): bool {
		// One serialized row only.
		return update_option( self::OPTION_KEY, $this->options, true );
	}

	public function all(): array {
		return $this->options;
	}

	public function defaults(): array {
		return $this->defaults;
	}

	/** Whether the selected animation uses an official Swup theme package. */
	public function uses_official_theme(): bool {
		$a = (string) $this->get( 'swup_animation', 'fade' );
		return in_array( $a, [ 'fade', 'slide', 'slide_reversed', 'overlay', 'overlay_left', 'overlay_top', 'overlay_bottom' ], true );
	}
}
