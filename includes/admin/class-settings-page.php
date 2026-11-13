<?php
namespace Wpswup\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Settings page – WP Swup
 *
 * @package Wpswup\Admin
 */
class Settings_Page {

	public function __construct() {
		add_action( 'admin_menu', [ $this, 'register_menu' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	public function register_menu(): void {
		add_options_page(
			__( 'WP Swup', 'wpswup' ),
			__( 'WP Swup', 'wpswup' ),
			'manage_options',
			'wpswup',
			[ $this, 'render_page' ]
		);
	}

	public function enqueue_assets( string $hook ): void {
		if ( 'settings_page_wpswup' !== $hook ) {
			return;
		}
		$admin_css = file_exists( WPSWUP_DIR . 'assets/css/wpswup-admin.min.css' )
			? WPSWUP_URL . 'assets/css/wpswup-admin.min.css'
			: WPSWUP_URL . 'assets/css/wpswup-admin.css';
		wp_enqueue_style( 'wpswup-admin', $admin_css, [], WPSWUP_VERSION );
	}

	public function register_settings(): void {
		register_setting(
			'wpswup_options_group',
			\Wpswup\Settings::OPTION_KEY,
			[ 'sanitize_callback' => [ $this, 'sanitize' ] ]
		);
	}

	public function sanitize( array $input ): array {
		$defaults = wpswup()->settings->defaults();
		$clean    = [];

		$clean['swup_enabled']         = ! empty( $input['swup_enabled'] );
		$allowed_anim = \Wpswup\Settings::THEMES;
		$clean['swup_animation']       = in_array( $input['swup_animation'] ?? '', $allowed_anim, true )
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
		$clean['compat_cache_plugins'] = ! empty( $input['compat_cache_plugins'] );
		$clean['compat_page_builders'] = ! empty( $input['compat_page_builders'] );
		$clean['debug_mode']           = ! empty( $input['debug_mode'] );

		return apply_filters( 'wpswup_sanitize_options', $clean, $input );
	}

	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$opts = wpswup()->settings->all();
		?>
		<div class="wrap wpswup-admin-wrap">
			<div class="wpswup-admin-header">
				<div class="wpswup-admin-header__logo">
					<svg viewBox="0 0 32 32" width="32" height="32" xmlns="http://www.w3.org/2000/svg">
						<rect width="32" height="32" rx="8" fill="#2563EB"/>
						<path d="M8 16C8 11.58 11.58 8 16 8s8 3.58 8 8-3.58 8-8 8" stroke="#fff" stroke-width="2.5" stroke-linecap="round"/>
						<circle cx="16" cy="16" r="3" fill="#fff"/>
					</svg>
				</div>
				<div>
					<h1 class="wpswup-admin-header__title"><?php esc_html_e( 'WP Swup', 'wpswup' ); ?></h1>
					<p class="wpswup-admin-header__sub"><?php esc_html_e( 'SPA-like page transitions powered by Swup.js 4', 'wpswup' ); ?></p>
				</div>
			</div>

			<form method="post" action="options.php">
				<?php settings_fields( 'wpswup_options_group' ); ?>

				<div class="wpswup-admin-tabs" id="wpswup-tabs">
					<nav class="wpswup-admin-tabs__nav">
						<button type="button" class="wpswup-tab active" data-tab="swup"><?php esc_html_e( 'Swup / SPA', 'wpswup' ); ?></button>
						<button type="button" class="wpswup-tab" data-tab="compat"><?php esc_html_e( 'Compatibility', 'wpswup' ); ?></button>
						<button type="button" class="wpswup-tab" data-tab="info"><?php esc_html_e( 'Info', 'wpswup' ); ?></button>
					</nav>

					<div class="wpswup-tab-panel active" id="tab-swup">
						<div class="wpswup-card">
							<h2><?php esc_html_e( 'Page Transition Settings', 'wpswup' ); ?></h2>
							<table class="form-table wpswup-form-table" role="presentation">
								<tr>
									<th><?php esc_html_e( 'Enable Swup', 'wpswup' ); ?></th>
									<td>
										<label class="wpswup-toggle">
											<input type="checkbox" name="wpswup_options[swup_enabled]" value="1" <?php checked( $opts['swup_enabled'] ); ?>>
											<span class="wpswup-toggle__slider"></span>
										</label>
									</td>
								</tr>
								<tr>
									<th><?php esc_html_e( 'Animation Theme', 'wpswup' ); ?></th>
									<td>
										<select name="wpswup_options[swup_animation]" class="wpswup-select">
											<?php
											$themes = [
												'fade'            => __( 'Fade (official)', 'wpswup' ),
												'slide'           => __( 'Slide (official)', 'wpswup' ),
												'slide_reversed'  => __( 'Slide reversed', 'wpswup' ),
												'overlay'         => __( 'Overlay → right', 'wpswup' ),
												'overlay_left'    => __( 'Overlay → left', 'wpswup' ),
												'overlay_top'     => __( 'Overlay → top', 'wpswup' ),
												'overlay_bottom'  => __( 'Overlay → bottom', 'wpswup' ),
												'none'            => __( 'None', 'wpswup' ),
											];
											foreach ( $themes as $val => $label ) :
												?>
												<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $opts['swup_animation'], $val ); ?>><?php echo esc_html( $label ); ?></option>
											<?php endforeach; ?>
										</select>
										<p class="description"><?php esc_html_e( 'Official Swup themes: Fade, Slide, Overlay. See swup.js.org/themes', 'wpswup' ); ?></p>
									</td>
								</tr>
								<tr>
									<th><?php esc_html_e( 'Duration (ms)', 'wpswup' ); ?></th>
									<td><input type="number" name="wpswup_options[swup_duration]" value="<?php echo esc_attr( $opts['swup_duration'] ); ?>" min="50" max="2000" step="50" class="small-text"></td>
								</tr>
								<tr>
									<th><?php esc_html_e( 'Cache Pages', 'wpswup' ); ?></th>
									<td>
										<label class="wpswup-toggle">
											<input type="checkbox" name="wpswup_options[swup_cache]" value="1" <?php checked( $opts['swup_cache'] ); ?>>
											<span class="wpswup-toggle__slider"></span>
										</label>
									</td>
								</tr>
								<tr>
									<th><?php esc_html_e( 'Swap Containers', 'wpswup' ); ?></th>
									<td>
										<input type="text" name="wpswup_options[swup_containers]" value="<?php echo esc_attr( $opts['swup_containers'] ); ?>" class="regular-text">
										<p class="description"><?php esc_html_e( 'Leave empty to auto-detect (WoodMart, Elementor, …). Example: #main, .main-page-wrapper', 'wpswup' ); ?></p>
									</td>
								</tr>
								<tr>
									<th><?php esc_html_e( 'Link Selector', 'wpswup' ); ?></th>
									<td><textarea name="wpswup_options[swup_link_selector]" rows="3" class="large-text code"><?php echo esc_textarea( $opts['swup_link_selector'] ); ?></textarea></td>
								</tr>
								<tr>
									<th><?php esc_html_e( 'Reset Scroll', 'wpswup' ); ?></th>
									<td>
										<label class="wpswup-toggle">
											<input type="checkbox" name="wpswup_options[swup_scroll_reset]" value="1" <?php checked( $opts['swup_scroll_reset'] ?? true ); ?>>
											<span class="wpswup-toggle__slider"></span>
										</label>
									</td>
								</tr>
								<tr>
									<th><?php esc_html_e( 'Animated Scroll Plugin', 'wpswup' ); ?></th>
									<td>
										<label class="wpswup-toggle">
											<input type="checkbox" name="wpswup_options[swup_scroll_plugin]" value="1" <?php checked( $opts['swup_scroll_plugin'] ?? false ); ?>>
											<span class="wpswup-toggle__slider"></span>
										</label>
									</td>
								</tr>
								<tr>
									<th><?php esc_html_e( 'Preload on Hover', 'wpswup' ); ?></th>
									<td>
										<label class="wpswup-toggle">
											<input type="checkbox" name="wpswup_options[swup_preload]" value="1" <?php checked( $opts['swup_preload'] ); ?>>
											<span class="wpswup-toggle__slider"></span>
										</label>
									</td>
								</tr>
								<tr>
									<th><?php esc_html_e( 'Forms Plugin', 'wpswup' ); ?></th>
									<td>
										<label class="wpswup-toggle">
											<input type="checkbox" name="wpswup_options[swup_forms]" value="1" <?php checked( $opts['swup_forms'] ?? true ); ?>>
											<span class="wpswup-toggle__slider"></span>
										</label>
									</td>
								</tr>
								<tr>
									<th><?php esc_html_e( 'Animation Scope', 'wpswup' ); ?></th>
									<td>
										<select name="wpswup_options[swup_animation_scope]" class="wpswup-select">
											<option value="html" <?php selected( $opts['swup_animation_scope'] ?? 'html', 'html' ); ?>>html</option>
											<option value="containers" <?php selected( $opts['swup_animation_scope'] ?? 'html', 'containers' ); ?>>containers</option>
										</select>
									</td>
								</tr>
								<tr>
									<th><?php esc_html_e( 'Native View Transitions', 'wpswup' ); ?></th>
									<td>
										<label class="wpswup-toggle">
											<input type="checkbox" name="wpswup_options[swup_native]" value="1" <?php checked( $opts['swup_native'] ?? false ); ?>>
											<span class="wpswup-toggle__slider"></span>
										</label>
									</td>
								</tr>
							</table>
						</div>
					</div>

					<div class="wpswup-tab-panel" id="tab-compat">
						<div class="wpswup-card">
							<h2><?php esc_html_e( 'Compatibility', 'wpswup' ); ?></h2>
							<table class="form-table wpswup-form-table" role="presentation">
								<tr>
									<th><?php esc_html_e( 'Cache Plugin Support', 'wpswup' ); ?></th>
									<td>
										<label class="wpswup-toggle">
											<input type="checkbox" name="wpswup_options[compat_cache_plugins]" value="1" <?php checked( $opts['compat_cache_plugins'] ); ?>>
											<span class="wpswup-toggle__slider"></span>
										</label>
									</td>
								</tr>
								<tr>
									<th><?php esc_html_e( 'Page Builder Support', 'wpswup' ); ?></th>
									<td>
										<label class="wpswup-toggle">
											<input type="checkbox" name="wpswup_options[compat_page_builders]" value="1" <?php checked( $opts['compat_page_builders'] ); ?>>
											<span class="wpswup-toggle__slider"></span>
										</label>
										<p class="description"><?php esc_html_e( 'Elementor, WooCommerce, WoodMart re-init after transitions.', 'wpswup' ); ?></p>
									</td>
								</tr>
								<tr>
									<th><?php esc_html_e( 'Debug Mode', 'wpswup' ); ?></th>
									<td>
										<label class="wpswup-toggle">
											<input type="checkbox" name="wpswup_options[debug_mode]" value="1" <?php checked( $opts['debug_mode'] ); ?>>
											<span class="wpswup-toggle__slider"></span>
										</label>
									</td>
								</tr>
							</table>
						</div>
					</div>

					<div class="wpswup-tab-panel" id="tab-info">
						<div class="wpswup-card">
							<h2><?php esc_html_e( 'About', 'wpswup' ); ?></h2>
							<p><?php esc_html_e( 'WP Swup provides SPA-like page transitions using Swup.js 4. Compatible with WooCommerce, Elementor, and WoodMart.', 'wpswup' ); ?></p>
							<p class="description"><?php esc_html_e( 'Version', 'wpswup' ); ?>: <?php echo esc_html( WPSWUP_VERSION ); ?></p>
						</div>
					</div>
				</div>

				<?php submit_button( __( 'Save Settings', 'wpswup' ) ); ?>
			</form>
		</div>
		<script>
		(function(){
			var tabs = document.querySelectorAll('#wpswup-tabs .wpswup-tab');
			var panels = document.querySelectorAll('#wpswup-tabs .wpswup-tab-panel');
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
