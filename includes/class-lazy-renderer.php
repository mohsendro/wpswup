<?php
namespace SLL;

defined( 'ABSPATH' ) || exit;

/**
 * Class Lazy_Renderer
 *
 * Resolves `data-render="section-name"` attributes and serves rendered
 * HTML via AJAX on scroll.
 *
 * Template resolution order (mirrors WooCommerce override pattern):
 *   1. SLL_TEMPLATE_LAZY_PATH / {name}/   (theme / wp-config define)
 *   2. Plugin's own bundled fallback (SLL_DIR/template-lazy/{name}/)
 *
 * Required files per section directory:
 *   skeleton.html  – placeholder shown while content loads
 *   render.php     – actual section markup (has access to $args)
 *   app.js         – section-specific JS (optional, auto-enqueued on AJAX)
 *   app.css        – section-specific CSS (optional, auto-enqueued on AJAX)
 *
 * HOW TO USE IN TEMPLATES (no output buffer needed):
 *   <?php sll_section( 'hero' ); ?>
 *   <?php sll_section( 'features', ['limit' => 6] ); ?>
 *   OR via shortcode:
 *   [sll_section name="hero"]
 *   [sll_section name="features" limit="6"]
 *
 * @package SLL
 */
class Lazy_Renderer {

    /** Resolved template directories cache. @var array<string,string> */
    private array $resolved = [];

    /** Sections enqueued on current page. @var string[] */
    private array $enqueued_sections = [];

    public function __construct() {
        // AJAX handlers – both logged-in and guest
        add_action( 'wp_ajax_sll_render_section',        [ $this, 'ajax_render_section' ] );
        add_action( 'wp_ajax_nopriv_sll_render_section', [ $this, 'ajax_render_section' ] );

        // Shortcode: [sll_section name="hero"]
        add_shortcode( 'sll_section', [ $this, 'shortcode_section' ] );

        // REST API endpoint (alternative to admin-ajax)
        add_action( 'rest_api_init', [ $this, 'register_rest_route' ] );
    }

    // ── Public Template Tag ───────────────────────────────────────────────────

    /**
     * Output a lazy section wrapper with skeleton placeholder.
     * Call this inside your theme templates.
     *
     * @param string $name  Section name (folder name in template-lazy/)
     * @param array  $args  Optional args passed to render.php
     * @param string $tag   Wrapper HTML tag (default: section)
     * @param string $class Extra CSS classes for the wrapper
     */
    public function render_placeholder(
        string $name,
        array  $args  = [],
        string $tag   = 'section',
        string $class = ''
    ): void {
        if ( ! sll()->option( 'lazy_enabled' ) ) {
            // Lazy disabled: render immediately server-side
            $this->render_inline( $name, $args );
            return;
        }

        $path = $this->resolve_template_path( $name );
        if ( $path ) {
            sll()->assets->enqueue_section_assets( $name, $path );
            $this->enqueued_sections[] = $name;
        }

        $skeleton    = $this->get_skeleton( $name );
        $args_attr   = ! empty( $args ) ? ' data-render-args=\'' . esc_attr( wp_json_encode( $args ) ) . '\'' : '';
        $class_attr  = $class ? ' ' . esc_attr( $class ) : '';
        $tag         = tag_escape( $tag );

        echo "<{$tag} data-render=\"" . esc_attr( $name ) . "\"{$args_attr} class=\"sll-lazy-section{$class_attr}\">";
        echo $skeleton; // skeleton.html is trusted theme developer output
        echo "</{$tag}>";
    }

    /**
     * Render section immediately (used when lazy is disabled or as fallback).
     */
    public function render_inline( string $name, array $args = [] ): void {
        $path = $this->resolve_template_path( $name );
        if ( ! $path || ! file_exists( $path . '/render.php' ) ) {
            if ( sll()->option( 'debug_mode' ) ) {
                echo "<!-- [SLL] Section '{$name}' not found -->";
            }
            return;
        }
        sll()->assets->enqueue_section_assets( $name, $path );
        $args = apply_filters( 'sll_render_args', $args, $name );
        do_action( "sll_before_render_{$name}", $args );
        include $path . '/render.php';
        do_action( "sll_after_render_{$name}", $args );
    }

    // ── Shortcode ─────────────────────────────────────────────────────────────

    /**
     * [sll_section name="hero" image_id="42"]
     */
    public function shortcode_section( array $atts ): string {
        $atts = shortcode_atts( [ 'name' => '', 'tag' => 'section', 'class' => '' ], $atts, 'sll_section' );
        if ( ! $atts['name'] ) {
            return '<!-- [SLL] shortcode missing name attribute -->';
        }
        $name     = sanitize_key( $atts['name'] );
        $reserved = [ 'name', 'tag', 'class' ];
        $args     = array_diff_key( $atts, array_flip( $reserved ) );

        ob_start();
        $this->render_placeholder( $name, $args, $atts['tag'], $atts['class'] );
        return ob_get_clean();
    }

    // ── REST API ──────────────────────────────────────────────────────────────

    public function register_rest_route(): void {
        register_rest_route( 'sll/v1', '/render/(?P<template>[a-z0-9_-]+)', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'rest_render_section' ],
            'permission_callback' => '__return_true',
            'args'                => [
                'template' => [ 'sanitize_callback' => 'sanitize_key' ],
            ],
        ] );
    }

    public function rest_render_section( \WP_REST_Request $request ): \WP_REST_Response {
        $template = $request->get_param( 'template' );
        $args     = (array) $request->get_query_params();
        unset( $args['template'] );

        $path = $this->resolve_template_path( $template );
        if ( ! $path || ! file_exists( $path . '/render.php' ) ) {
            return new \WP_REST_Response( [ 'success' => false, 'message' => "Template '{$template}' not found." ], 404 );
        }

        $args = apply_filters( 'sll_render_args', $args, $template );

        ob_start();
        do_action( "sll_before_render_{$template}", $args );
        include $path . '/render.php';
        do_action( "sll_after_render_{$template}", $args );
        $html = ob_get_clean();

        return new \WP_REST_Response( apply_filters( 'sll_render_response', [
            'success'  => true,
            'template' => $template,
            'html'     => $html,
        ], $template, $args ) );
    }

    // ── Legacy AJAX (kept for compatibility) ─────────────────────────────────

    /**
     * AJAX endpoint – renders a section's render.php and returns JSON.
     * POST: { template: 'section-name', args: {...}, nonce: '...' }
     */
    public function ajax_render_section(): void {
        check_ajax_referer( 'sll_lazy_render', 'nonce' );

        $template_name = sanitize_key( $_POST['template'] ?? '' );
        $args          = isset( $_POST['args'] ) ? (array) json_decode( wp_unslash( $_POST['args'] ), true ) : [];

        if ( ! $template_name ) {
            wp_send_json_error( [ 'message' => 'Missing template name.' ], 400 );
        }

        $path = $this->resolve_template_path( $template_name );

        if ( ! $path ) {
            wp_send_json_error( [ 'message' => "Template '{$template_name}' not found." ], 404 );
        }

        $render_file = $path . '/render.php';
        if ( ! file_exists( $render_file ) ) {
            wp_send_json_error( [ 'message' => "render.php missing for '{$template_name}'." ], 404 );
        }

        $args = apply_filters( 'sll_render_args', $args, $template_name );

        ob_start();
        do_action( "sll_before_render_{$template_name}", $args );
        include $render_file;
        do_action( "sll_after_render_{$template_name}", $args );
        $html = ob_get_clean();

        wp_send_json_success( apply_filters( 'sll_render_response', [
            'template' => $template_name,
            'html'     => $html,
        ], $template_name, $args ) );
    }

    // ── Template Resolution ───────────────────────────────────────────────────

    /**
     * Resolve the filesystem path for a named template-lazy section.
     *
     * @param  string $name  Folder name (e.g. 'hero', 'features')
     * @return string|null   Absolute path to the section directory, or null
     */
    public function resolve_template_path( string $name ): ?string {
        if ( isset( $this->resolved[ $name ] ) ) {
            return $this->resolved[ $name ] ?: null;
        }

        $candidates = apply_filters( 'sll_template_candidates', [
            // 1. Theme / user-defined override path
            rtrim( SLL_TEMPLATE_LAZY_PATH, '/' ) . '/' . $name,
            // 2. Plugin's own bundled examples
            SLL_DIR . 'template-lazy/' . $name,
        ], $name );

        foreach ( $candidates as $candidate ) {
            if ( is_dir( $candidate ) && file_exists( $candidate . '/render.php' ) ) {
                $this->resolved[ $name ] = $candidate;
                return $candidate;
            }
        }

        $this->resolved[ $name ] = '';
        return null;
    }

    /**
     * Return the skeleton HTML for a section.
     * Falls back to a generic pulse skeleton if skeleton.html is missing.
     */
    public function get_skeleton( string $name ): string {
        $path = $this->resolve_template_path( $name );

        if ( $path && file_exists( $path . '/skeleton.html' ) ) {
            $skeleton = file_get_contents( $path . '/skeleton.html' ); // phpcs:ignore
            return apply_filters( 'sll_skeleton_html', $skeleton, $name );
        }

        $fallback = '<div class="sll-skeleton sll-skeleton--generic" aria-hidden="true">'
            . '<div class="sll-skeleton__line" style="width:60%"></div>'
            . '<div class="sll-skeleton__line" style="width:80%"></div>'
            . '<div class="sll-skeleton__line" style="width:40%"></div>'
            . '</div>';

        return apply_filters( 'sll_skeleton_html', $fallback, $name );
    }

    /**
     * List all registered template-lazy sections found on disk.
     *
     * @return string[]
     */
    public function discover_sections(): array {
        $sections    = [];
        $search_dirs = [
            SLL_TEMPLATE_LAZY_PATH,
            SLL_DIR . 'template-lazy',
        ];

        foreach ( $search_dirs as $dir ) {
            if ( ! is_dir( $dir ) ) {
                continue;
            }
            foreach ( (array) glob( $dir . '/*', GLOB_ONLYDIR ) as $entry ) {
                $name = basename( $entry );
                if ( ! in_array( $name, $sections, true ) ) {
                    $sections[] = $name;
                }
            }
        }

        return apply_filters( 'sll_discovered_sections', $sections );
    }
}
