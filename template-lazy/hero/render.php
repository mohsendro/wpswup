<?php
/**
 * template-lazy/hero/render.php
 *
 * Example hero section rendered on demand.
 * $args is available and contains any data-render-args passed by the client.
 *
 * Theme override: copy this folder to your-theme/template-lazy/hero/
 */

defined( 'ABSPATH' ) || exit;

// Defaults – merge with anything passed via data-render-args
$args = wp_parse_args( $args ?? [], [
    'title'    => get_bloginfo( 'name' ),
    'tagline'  => get_bloginfo( 'description' ),
    'image_id' => 0,
] );

$title   = esc_html( $args['title'] );
$tagline = esc_html( $args['tagline'] );
$img     = $args['image_id']
           ? wp_get_attachment_image( (int) $args['image_id'], 'hero', false, [ 'loading' => 'eager' ] )
           : '';
?>
<div class="sll-hero">
    <?php if ( $img ) : ?>
        <div class="sll-hero__media"><?php echo $img; // already escaped by WP ?></div>
    <?php endif; ?>

    <div class="sll-hero__body">
        <h1 class="sll-hero__title"><?php echo $title; ?></h1>
        <?php if ( $tagline ) : ?>
            <p class="sll-hero__tagline"><?php echo $tagline; ?></p>
        <?php endif; ?>
        <a href="<?php echo esc_url( get_permalink( get_option( 'page_for_posts' ) ) ); ?>"
           class="sll-hero__cta">
            <?php esc_html_e( 'Read the Blog', 'swup-lazy-loader' ); ?>
        </a>
    </div>
</div>
