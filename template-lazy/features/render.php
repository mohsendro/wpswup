<?php
/**
 * template-lazy/features/render.php
 *
 * Example features grid section.
 * Theme override: copy to your-theme/template-lazy/features/
 */

defined( 'ABSPATH' ) || exit;

$args = wp_parse_args( $args ?? [], [
    'title'    => __( 'Features', 'swup-lazy-loader' ),
    'items'    => [],
] );

// Fallback demo items if none provided
if ( empty( $args['items'] ) ) {
    $args['items'] = [
        [ 'icon' => '⚡', 'title' => 'Lightning Fast',  'desc' => 'SPA-like transitions with zero page reloads.' ],
        [ 'icon' => '🎨', 'title' => 'Theme Friendly',  'desc' => 'Works alongside any WordPress theme.' ],
        [ 'icon' => '🔧', 'title' => 'Extensible API',  'desc' => 'Hooks, filters, and JS events for developers.' ],
    ];
}
?>
<div class="sll-features">
    <h2 class="sll-features__title"><?php echo esc_html( $args['title'] ); ?></h2>
    <div class="sll-features__grid">
        <?php foreach ( $args['items'] as $item ) : ?>
            <div class="sll-feature-card">
                <div class="sll-feature-card__icon"><?php echo esc_html( $item['icon'] ); ?></div>
                <h3 class="sll-feature-card__title"><?php echo esc_html( $item['title'] ); ?></h3>
                <p class="sll-feature-card__desc"><?php echo esc_html( $item['desc'] ); ?></p>
            </div>
        <?php endforeach; ?>
    </div>
</div>
