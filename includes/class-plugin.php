<?php
namespace SLL;

defined( 'ABSPATH' ) || exit;

/**
 * Class Plugin
 *
 * Central singleton that boots all sub-systems.
 * Developers can hook into 'sll_loaded' after the plugin is ready.
 *
 * @package SLL
 */
final class Plugin {

	/** @var Plugin|null */
	private static ?Plugin $instance = null;

	/** @var Assets|null */
	public ?Assets $assets = null;

	/** @var Swup|null */
	public ?Swup $swup = null;

	/** @var Lazy_Renderer|null */
	public ?Lazy_Renderer $lazy = null;

	/** @var Settings|null */
	public ?Settings $settings = null;

	/**
	 * Get the singleton. Safe to call during construction of components.
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			// Assign BEFORE running the constructor body so nested
			// sll() / Plugin::instance() calls do not recurse.
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

		$this->define_template_path();
		$this->load_textdomain();
		$this->init_components();

		$booting = false;
		do_action( 'sll_loaded', $this );
	}

	/**
	 * Allow theme / wp-config.php to override the template-lazy base path.
	 *
	 *   define( 'SLL_TEMPLATE_LAZY_PATH', get_stylesheet_directory() . '/my-lazy' );
	 */
	private function define_template_path(): void {
		if ( ! defined( 'SLL_TEMPLATE_LAZY_PATH' ) ) {
			// get_stylesheet_directory() is safe after plugins_loaded / after_setup_theme.
			// On very early calls fall back to plugin path.
			$theme_path = function_exists( 'get_stylesheet_directory' )
				? get_stylesheet_directory() . '/template-lazy'
				: SLL_DIR . 'template-lazy';
			define( 'SLL_TEMPLATE_LAZY_PATH', $theme_path );
		}
		if ( ! defined( 'SLL_TEMPLATE_LAZY_URL' ) ) {
			$theme_url = function_exists( 'get_stylesheet_directory_uri' )
				? get_stylesheet_directory_uri() . '/template-lazy'
				: SLL_URL . 'template-lazy';
			define( 'SLL_TEMPLATE_LAZY_URL', $theme_url );
		}
	}

	private function load_textdomain(): void {
		// Ensure binary .mo exists (written once from embedded base64 if missing).
		$install = SLL_DIR . 'languages/install-mo.php';
		if ( is_readable( $install ) ) {
			require_once $install;
		}
		load_plugin_textdomain( SLL_SLUG, false, dirname( plugin_basename( SLL_FILE ) ) . '/languages' );
	}

	private function init_components(): void {
		// Order matters: Settings first (no hooks that call sll()),
		// then the rest which may call sll()->option().
		$this->settings = new Settings();
		$this->assets   = new Assets();
		$this->swup     = new Swup();
		$this->lazy     = new Lazy_Renderer();

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
