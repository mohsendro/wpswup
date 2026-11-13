<?php
namespace SLL\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Class Settings_Page
 *
 * Renders the professional settings panel under Settings > Swup Lazy Loader.
 *
 * @package SLL\Admin
 */
class Settings_Page {

    public function __construct() {
        add_action( 'admin_menu',          [ $this, 'register_menu' ] );
        add_action( 'admin_init',          [ $this, 'register_settings' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
    }

    public function register_menu(): void {
        add_options_page(
            __( 'Swup Lazy Loader', 'swup-lazy-loader' ),
            __( 'Swup Lazy Loader', 'swup-lazy-loader' ),
            'manage_options',
            'swup-lazy-loader',
            [ $this, 'render_page' ]
        );
    }

    public function enqueue_assets( string $hook ): void {
        if ( 'settings_page_swup-lazy-loader' !== $hook ) {
            return;
        }
        wp_enqueue_style(
            'sll-admin',
            \SLL_URL . 'assets/css/sll-admin.css',
            [],
            \SLL_VERSION
        );
    }

    public function register_settings(): void {
        register_setting(
            'sll_options_group',
            \SLL\Settings::OPTION_KEY,
            [ 'sanitize_callback' => [ $this, 'sanitize' ] ]
        );
    }

    public function sanitize( array $input ): array {
		$defaults = sll()->settings->defaults();
		$clean    = [];

		$clean['swup_enabled']         = ! empty( $input['swup_enabled'] );
		$clean['swup_animation']       = in_array( $input['swup_animation'] ?? '', [ 'fade', 'slide', 'none' ], true )
			? $input['swup_animation'] : $defaults['swup_animation'];
		$clean['swup_duration']        = absint( $input['swup_duration'] ?? $defaults['swup_duration'] );
		$clean['swup_cache']           = ! empty( $input['swup_cache'] );
		$clean['swup_containers']      = sanitize_text_field( $input['swup_containers'] ?? $defaults['swup_containers'] );
		$clean['swup_link_selector']   = sanitize_text_field( $input['swup_link_selector'] ?? $defaults['swup_link_selector'] );
		$clean['swup_scroll_reset']    = ! empty( $input['swup_scroll_reset'] );
		$clean['swup_scroll_plugin']   = ! empty( $input['swup_scroll_plugin'] );
		$clean['swup_preload']         = ! empty( $input['swup_preload'] );
		$clean['swup_forms']           = ! empty( $input['swup_forms'] );
		$clean['swup_animation_scope'] = in_array( $input['swup_animation_scope'] ?? '', [ 'html', 'containers' ], true )
			? $input['swup_animation_scope'] : $defaults['swup_animation_scope'];
		$clean['swup_native']          = ! empty( $input['swup_native'] );
		$clean['lazy_enabled']         = ! empty( $input['lazy_enabled'] );
		$clean['lazy_threshold']       = min( 1.0, max( 0.0, (float) ( $input['lazy_threshold'] ?? $defaults['lazy_threshold'] ) ) );
		$clean['lazy_rootMargin']      = sanitize_text_field( $input['lazy_rootMargin'] ?? $defaults['lazy_rootMargin'] );
		$clean['compat_cache_plugins'] = ! empty( $input['compat_cache_plugins'] );
		$clean['compat_page_builders'] = ! empty( $input['compat_page_builders'] );
		$clean['debug_mode']           = ! empty( $input['debug_mode'] );

		return apply_filters( 'sll_sanitize_options', $clean, $input );
	}

    public function render_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $opts     = sll()->settings->all();
        $sections = sll()->lazy->discover_sections();
        ?>
        <div class="wrap sll-admin-wrap">
            <div class="sll-admin-header">
                <div class="sll-admin-header__logo">
                    <svg viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg" width="32" height="32">
                        <rect width="32" height="32" rx="8" fill="#2563EB"/>
                        <path d="M8 16C8 11.58 11.58 8 16 8s8 3.58 8 8-3.58 8-8 8" stroke="#fff" stroke-width="2.5" stroke-linecap="round"/>
                        <circle cx="16" cy="16" r="3" fill="#fff"/>
                    </svg>
                </div>
                <div>
                    <h1 class="sll-admin-header__title"><?php esc_html_e( 'Swup Lazy Loader', 'swup-lazy-loader' ); ?></h1>
                    <p class="sll-admin-header__sub"><?php esc_html_e( 'SPA transitions + scroll-based lazy section rendering', 'swup-lazy-loader' ); ?></p>
                </div>
            </div>

            <form method="post" action="options.php">
                <?php settings_fields( 'sll_options_group' ); ?>

                <div class="sll-admin-tabs" id="sll-tabs">
                    <nav class="sll-admin-tabs__nav">
                        <button type="button" class="sll-tab active" data-tab="swup"><?php esc_html_e( 'Swup / SPA', 'swup-lazy-loader' ); ?></button>
                        <button type="button" class="sll-tab" data-tab="lazy"><?php esc_html_e( 'Lazy Renderer', 'swup-lazy-loader' ); ?></button>
                        <button type="button" class="sll-tab" data-tab="compat"><?php esc_html_e( 'Compatibility', 'swup-lazy-loader' ); ?></button>
                        <button type="button" class="sll-tab" data-tab="info"><?php esc_html_e( 'Sections & Info', 'swup-lazy-loader' ); ?></button>
                    </nav>

                    <!-- ── Swup Tab ── -->
                    <div class="sll-tab-panel active" id="tab-swup">
                        <div class="sll-card">
                            <h2><?php esc_html_e( 'Page Transition Settings', 'swup-lazy-loader' ); ?></h2>

                            <table class="form-table sll-form-table" role="presentation">
                                <tr>
                                    <th><?php esc_html_e( 'Enable Swup', 'swup-lazy-loader' ); ?></th>
                                    <td>
                                        <label class="sll-toggle">
                                            <input type="checkbox" name="sll_options[swup_enabled]" value="1" <?php checked( $opts['swup_enabled'] ); ?>>
                                            <span class="sll-toggle__slider"></span>
                                        </label>
                                        <p class="description"><?php esc_html_e( 'Enable SPA-like page transitions.', 'swup-lazy-loader' ); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Animation Style', 'swup-lazy-loader' ); ?></th>
                                    <td>
                                        <select name="sll_options[swup_animation]" class="sll-select">
                                            <?php foreach ( [ 'fade' => __( 'Fade', 'swup-lazy-loader' ), 'slide' => __( 'Slide Up/Down', 'swup-lazy-loader' ), 'none' => __( 'None', 'swup-lazy-loader' ) ] as $val => $label ) : ?>
                                                <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $opts['swup_animation'], $val ); ?>><?php echo esc_html( $label ); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Duration (ms)', 'swup-lazy-loader' ); ?></th>
                                    <td>
                                        <input type="number" name="sll_options[swup_duration]" value="<?php echo esc_attr( $opts['swup_duration'] ); ?>" min="50" max="2000" step="50" class="small-text">
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Cache Pages', 'swup-lazy-loader' ); ?></th>
                                    <td>
                                        <label class="sll-toggle">
                                            <input type="checkbox" name="sll_options[swup_cache]" value="1" <?php checked( $opts['swup_cache'] ); ?>>
                                            <span class="sll-toggle__slider"></span>
                                        </label>
                                        <p class="description"><?php esc_html_e( 'Cache visited pages in memory (faster back-navigation).', 'swup-lazy-loader' ); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Swap Containers', 'swup-lazy-loader' ); ?></th>
                                    <td>
                                        <input type="text" name="sll_options[swup_containers]" value="<?php echo esc_attr( $opts['swup_containers'] ); ?>" class="regular-text">
                                        <p class="description"><?php esc_html_e( 'Leave empty to auto-detect (recommended for WoodMart, Elementor, and ready-made themes). Or set unique selectors, e.g. #main, .main-page-wrapper', 'swup-lazy-loader' ); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Link Selector', 'swup-lazy-loader' ); ?></th>
                                    <td>
                                        <textarea name="sll_options[swup_link_selector]" rows="3" class="large-text code"><?php echo esc_textarea( $opts['swup_link_selector'] ); ?></textarea>
                                        <p class="description"><?php esc_html_e( 'CSS selector for links that Swup should intercept.', 'swup-lazy-loader' ); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Reset Scroll (built-in)', 'swup-lazy-loader' ); ?></th>
                                    <td>
                                        <label class="sll-toggle">
                                            <input type="checkbox" name="sll_options[swup_scroll_reset]" value="1" <?php checked( $opts['swup_scroll_reset'] ?? true ); ?>>
                                            <span class="sll-toggle__slider"></span>
                                        </label>
                                        <p class="description"><?php esc_html_e( 'Swup 4 resets scroll position and handles #anchors natively. Keep enabled.', 'swup-lazy-loader' ); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Animated Scroll Plugin', 'swup-lazy-loader' ); ?></th>
                                    <td>
                                        <label class="sll-toggle">
                                            <input type="checkbox" name="sll_options[swup_scroll_plugin]" value="1" <?php checked( $opts['swup_scroll_plugin'] ?? false ); ?>>
                                            <span class="sll-toggle__slider"></span>
                                        </label>
                                        <p class="description"><?php esc_html_e( 'Optional @swup/scroll-plugin for smooth animated scrolling (not required for basic reset).', 'swup-lazy-loader' ); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Preload on Hover', 'swup-lazy-loader' ); ?></th>
                                    <td>
                                        <label class="sll-toggle">
                                            <input type="checkbox" name="sll_options[swup_preload]" value="1" <?php checked( $opts['swup_preload'] ); ?>>
                                            <span class="sll-toggle__slider"></span>
                                        </label>
                                        <p class="description"><?php esc_html_e( 'Prefetch pages on link hover for instant navigation.', 'swup-lazy-loader' ); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Forms Plugin', 'swup-lazy-loader' ); ?></th>
                                    <td>
                                        <label class="sll-toggle">
                                            <input type="checkbox" name="sll_options[swup_forms]" value="1" <?php checked( $opts['swup_forms'] ?? true ); ?>>
                                            <span class="sll-toggle__slider"></span>
                                        </label>
                                        <p class="description"><?php esc_html_e( 'Handle form submissions (cart, search, filters) without full reload. Recommended for WooCommerce.', 'swup-lazy-loader' ); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Animation Scope', 'swup-lazy-loader' ); ?></th>
                                    <td>
                                        <select name="sll_options[swup_animation_scope]" class="sll-select">
                                            <option value="html" <?php selected( $opts['swup_animation_scope'] ?? 'html', 'html' ); ?>><?php esc_html_e( 'html (recommended)', 'swup-lazy-loader' ); ?></option>
                                            <option value="containers" <?php selected( $opts['swup_animation_scope'] ?? 'html', 'containers' ); ?>><?php esc_html_e( 'containers only', 'swup-lazy-loader' ); ?></option>
                                        </select>
                                        <p class="description"><?php esc_html_e( 'Where Swup 4 adds is-changing / is-animating classes.', 'swup-lazy-loader' ); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Native View Transitions', 'swup-lazy-loader' ); ?></th>
                                    <td>
                                        <label class="sll-toggle">
                                            <input type="checkbox" name="sll_options[swup_native]" value="1" <?php checked( $opts['swup_native'] ?? false ); ?>>
                                            <span class="sll-toggle__slider"></span>
                                        </label>
                                        <p class="description"><?php esc_html_e( 'Use the browser View Transitions API when available (Swup 4 native option).', 'swup-lazy-loader' ); ?></p>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <!-- ── Lazy Tab ── -->
                    <div class="sll-tab-panel" id="tab-lazy">
                        <div class="sll-card">
                            <h2><?php esc_html_e( 'Lazy Section Renderer', 'swup-lazy-loader' ); ?></h2>
                            <table class="form-table sll-form-table" role="presentation">
                                <tr>
                                    <th><?php esc_html_e( 'Enable Lazy Renderer', 'swup-lazy-loader' ); ?></th>
                                    <td>
                                        <label class="sll-toggle">
                                            <input type="checkbox" name="sll_options[lazy_enabled]" value="1" <?php checked( $opts['lazy_enabled'] ); ?>>
                                            <span class="sll-toggle__slider"></span>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Observer Threshold', 'swup-lazy-loader' ); ?></th>
                                    <td>
                                        <input type="number" name="sll_options[lazy_threshold]" value="<?php echo esc_attr( $opts['lazy_threshold'] ); ?>" min="0" max="1" step="0.05" class="small-text">
                                        <p class="description"><?php esc_html_e( '0 = trigger as soon as 1px is visible, 1 = fully visible. Default: 0.1', 'swup-lazy-loader' ); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Root Margin', 'swup-lazy-loader' ); ?></th>
                                    <td>
                                        <input type="text" name="sll_options[lazy_rootMargin]" value="<?php echo esc_attr( $opts['lazy_rootMargin'] ); ?>" class="regular-text">
                                        <p class="description"><?php esc_html_e( 'Pre-load 200px before the section enters viewport. CSS margin syntax.', 'swup-lazy-loader' ); ?></p>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <!-- ── Compat Tab ── -->
                    <div class="sll-tab-panel" id="tab-compat">
                        <div class="sll-card">
                            <h2><?php esc_html_e( 'Compatibility', 'swup-lazy-loader' ); ?></h2>
                            <table class="form-table sll-form-table" role="presentation">
                                <tr>
                                    <th><?php esc_html_e( 'Cache Plugin Support', 'swup-lazy-loader' ); ?></th>
                                    <td>
                                        <label class="sll-toggle">
                                            <input type="checkbox" name="sll_options[compat_cache_plugins]" value="1" <?php checked( $opts['compat_cache_plugins'] ); ?>>
                                            <span class="sll-toggle__slider"></span>
                                        </label>
                                        <p class="description"><?php esc_html_e( 'Adds Vary headers for WP Rocket, W3TC, LiteSpeed Cache, etc.', 'swup-lazy-loader' ); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Page Builder Support', 'swup-lazy-loader' ); ?></th>
                                    <td>
                                        <label class="sll-toggle">
                                            <input type="checkbox" name="sll_options[compat_page_builders]" value="1" <?php checked( $opts['compat_page_builders'] ); ?>>
                                            <span class="sll-toggle__slider"></span>
                                        </label>
                                        <p class="description"><?php esc_html_e( 'Improves compatibility with Elementor, Divi, Beaver Builder, WooCommerce.', 'swup-lazy-loader' ); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Debug Mode', 'swup-lazy-loader' ); ?></th>
                                    <td>
                                        <label class="sll-toggle">
                                            <input type="checkbox" name="sll_options[debug_mode]" value="1" <?php checked( $opts['debug_mode'] ); ?>>
                                            <span class="sll-toggle__slider"></span>
                                        </label>
                                        <p class="description"><?php esc_html_e( 'Logs Swup events and lazy load triggers to browser console.', 'swup-lazy-loader' ); ?></p>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <!-- ── Info Tab ── -->
                    <div class="sll-tab-panel" id="tab-info">
                        <div class="sll-card">
                            <h2><?php esc_html_e( 'Discovered Sections', 'swup-lazy-loader' ); ?></h2>
                            <?php if ( empty( $sections ) ) : ?>
                                <p class="sll-notice sll-notice--warn">
                                    <?php esc_html_e( 'No template-lazy sections found yet.', 'swup-lazy-loader' ); ?>
                                </p>
                            <?php else : ?>
                                <div class="sll-sections-grid">
                                    <?php foreach ( $sections as $s ) :
                                        $path     = sll()->lazy->resolve_template_path( $s );
                                        $has_skel = $path && file_exists( $path . '/skeleton.html' );
                                        $has_css  = $path && file_exists( $path . '/app.css' );
                                        $has_js   = $path && file_exists( $path . '/app.js' );
                                    ?>
                                        <div class="sll-section-card">
                                            <strong><?php echo esc_html( $s ); ?></strong>
                                            <ul>
                                                <li class="<?php echo $has_skel ? 'ok' : 'missing'; ?>"><?php esc_html_e( 'skeleton.html', 'swup-lazy-loader' ); ?></li>
                                                <li class="ok"><?php esc_html_e( 'render.php', 'swup-lazy-loader' ); ?></li>
                                                <li class="<?php echo $has_css ? 'ok' : 'optional'; ?>"><?php esc_html_e( 'app.css', 'swup-lazy-loader' ); ?></li>
                                                <li class="<?php echo $has_js ? 'ok' : 'optional'; ?>"><?php esc_html_e( 'app.js', 'swup-lazy-loader' ); ?></li>
                                            </ul>
                                            <code class="sll-path"><?php echo esc_html( str_replace( ABSPATH, '…/', $path ) ); ?></code>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <hr>
                            <h2><?php esc_html_e( 'Template Path', 'swup-lazy-loader' ); ?></h2>
                            <p>
                                <?php esc_html_e( 'Current path:', 'swup-lazy-loader' ); ?>
                                <code><?php echo esc_html( str_replace( ABSPATH, '…/', SLL_TEMPLATE_LAZY_PATH ) ); ?></code>
                            </p>
                            <div class="sll-notice sll-notice--info">
                                <strong><?php esc_html_e( 'Override in wp-config.php:', 'swup-lazy-loader' ); ?></strong>
                                <pre>define( 'SLL_TEMPLATE_LAZY_PATH', get_stylesheet_directory() . '/my-lazy' );</pre>
                            </div>

                            <h2><?php esc_html_e( 'Usage', 'swup-lazy-loader' ); ?></h2>
                            <div class="sll-notice sll-notice--info">
                                <strong><?php esc_html_e( 'In your theme template:', 'swup-lazy-loader' ); ?></strong>
                                <pre>&lt;section data-render="hero"&gt;&lt;/section&gt;
&lt;div data-render="features" data-render-args='{"limit":6}'&gt;&lt;/div&gt;</pre>
                            </div>
                        </div>
                    </div>
                </div>

                <?php submit_button( __( 'Save Settings', 'swup-lazy-loader' ) ); ?>
            </form>
        </div>

        <script>
        (function(){
            var tabs  = document.querySelectorAll('.sll-tab');
            var panels = document.querySelectorAll('.sll-tab-panel');
            tabs.forEach(function(tab){
                tab.addEventListener('click', function(){
                    tabs.forEach(function(t){ t.classList.remove('active'); });
                    panels.forEach(function(p){ p.classList.remove('active'); });
                    tab.classList.add('active');
                    document.getElementById('tab-' + tab.dataset.tab).classList.add('active');
                });
            });
        })();
        </script>
        <?php
    }
}
