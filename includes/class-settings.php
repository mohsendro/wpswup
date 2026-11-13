<?php
namespace SLL;

defined( 'ABSPATH' ) || exit;

/**
 * Class Settings
 *
 * Manages plugin options with sane defaults for Swup 4.
 *
 * @package SLL
 */
class Settings {

	const OPTION_KEY = 'sll_options';

	/** @var array<string,mixed> */
	private array $options;

	/** @var array<string,mixed> */
	private array $defaults = [
		// Swup core
		'swup_enabled'          => true,
		'swup_animation'        => 'fade',
		'swup_duration'         => 300,
		'swup_cache'            => true,
		// Empty = auto-detect (recommended for ready-made themes)
		'swup_containers'       => '',
		'swup_link_selector'    => 'a[href]:not([data-no-swup]):not([target="_blank"]):not([href^="#"]):not([href^="mailto:"]):not([href^="tel:"])',
		'swup_scroll_reset'     => true,
		'swup_scroll_plugin'    => false,
		'swup_preload'          => true,
		'swup_forms'            => true,
		'swup_animation_scope'  => 'html',
		'swup_native'           => false,

		// Lazy Renderer
		'lazy_enabled'          => true,
		'lazy_threshold'        => 0.1,
		'lazy_rootMargin'       => '0px 0px 200px 0px',

		// Compatibility
		'compat_cache_plugins'  => true,
		'compat_page_builders'  => true,

		// Developer
		'debug_mode'            => false,
	];

	public function __construct() {
		$saved         = get_option( self::OPTION_KEY, [] );
		$this->options = wp_parse_args( is_array( $saved ) ? $saved : [], $this->defaults );
	}

	public function get( string $key, $default = null ) {
		return $this->options[ $key ] ?? ( $default ?? $this->defaults[ $key ] ?? null );
	}

	public function set( string $key, $value ): void {
		$this->options[ $key ] = $value;
	}

	public function save(): bool {
		return update_option( self::OPTION_KEY, $this->options );
	}

	public function all(): array {
		return $this->options;
	}

	public function defaults(): array {
		return $this->defaults;
	}
}
