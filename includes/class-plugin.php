<?php
namespace Wpswup;

defined( 'ABSPATH' ) || exit;

/**
 * Class Plugin
 *
 * Central singleton that boots all sub-systems.
 * Developers can hook into 'wpswup_loaded' after the plugin is ready.
 *
 * @package Wpswup
 */
final class Plugin {

	/** @var Plugin|null */
	private static ?Plugin $instance = null;

	/** @var Assets|null */
	public ?Assets $assets = null;

	/** @var Swup|null */
	public ?Swup $swup = null;

	/** @var Settings|null */
	public ?Settings $settings = null;

	/**
	 * Get the singleton. Safe to call during construction of components.
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			// Assign BEFORE running the constructor body so nested
			// wpswup() / Plugin::instance() calls do not recurse.
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		// Prevent re-entry if something calls instance() again mid-boot.
		static $booting = false;
		if ( $booting ) {
			return;
		}
		$booting = true;

		$this->load_textdomain();
		$this->init_components();

		$booting = false;
		do_action( 'wpswup_loaded', $this );
	}

	private function load_textdomain(): void {
		load_plugin_textdomain( WPSWUP_SLUG, false, dirname( plugin_basename( WPSWUP_FILE ) ) . '/languages' );
	}

	private function init_components(): void {
		// Order matters: Settings first (no hooks that call wpswup()),
		// then the rest which may call wpswup()->option().
		$this->settings = new Settings();
		$this->assets   = new Assets();
		$this->swup     = new Swup();

		if ( is_admin() ) {
			new Admin\Settings_Page();
		}
	}

	/**
	 * Retrieve a plugin option with a merged default.
	 *
	 * @param  string $key
	 * @param  mixed  $default
	 * @return mixed
	 */
	public function option( string $key, $default = null ) {
		if ( ! $this->settings ) {
			return $default;
		}
		return $this->settings->get( $key, $default );
	}
}
