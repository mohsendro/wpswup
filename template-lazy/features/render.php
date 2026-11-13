<?php
/**
 * template-lazy/features/render.php
 *
 * Example features grid section.
 * Theme override: copy to your-theme/template-lazy/features/
 */

defined( 'ABSPATH' ) || exit;

$args = wp_parse_args( $args ?? [], [
    'title'    => __( 'Features', 'wpswup' ),
    'items'    => [],
] );

// Fallback demo items if none provided
if ( empty( $args['items'] ) ) {
    $args['items'] = [
        [ 'icon' => '⚡', 'title' => __( 'Lightning Fast', 'wpswup' ),  'desc' => __( 'SPA-like transitions with zero page reloads.', 'wpswup' ) ],
        [ 'icon' => '🎨', 'title' => __( 'Theme Friendly', 'wpswup' ),  'desc' => __( 'Works alongside any WordPress theme.', 'wpswup' ) ],
        [ 'icon' => '🔧', 'title' => __( 'Extensible API', 'wpswup' ),  'desc' => __( 'Hooks, filters, and JS events for developers.', 'wpswup' ) ],
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
