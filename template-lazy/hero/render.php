<?php
/**
 * template-lazy/hero/render.php
 */

defined( 'ABSPATH' ) || exit;

$title   = isset( $args['title'] ) ? esc_html( $args['title'] ) : get_the_title();
$tagline = isset( $args['tagline'] ) ? esc_html( $args['tagline'] ) : '';
$img_id  = isset( $args['image_id'] ) ? absint( $args['image_id'] ) : 0;

$img = $img_id ? wp_get_attachment_image( $img_id, 'large', false, [ 'class' => 'sll-hero__img' ] ) : '';
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
            <?php esc_html_e( 'Read the Blog', 'wpswup' ); ?>
        </a>
    </div>
</div>
