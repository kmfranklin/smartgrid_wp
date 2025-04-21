<?php

/**
 * SmartGrid grid-item.php
 * 
 * Available variables:
 *  - $post         : The WP_Post object
 *  - $grid_id      : ID of the current grid config
 *  - $item_meta    : array of meta fields selected in the grid config
 *  - $item_terms   : array of taxonomy terms selected in the grid config
 */

global $post;

if (! $post instanceof WP_Post) {
    return;
}
setup_postdata($post);
?>
<div class="smartgrid-item-card">
    <a href="<?php the_permalink(); ?>" class="smartgrid-item-link">
        <?php if (has_post_thumbnail($post)) : ?>
            <div class="smartgrid-item-image">
                <?php echo get_the_post_thumbnail($post, 'medium'); ?>
            </div>
        <?php endif; ?>

        <h3 class="smartgrid-item-title"><?php echo get_the_title($post); ?></h3>

        <?php if (!empty($item_meta)) : ?>
            <div class="smartgrid-item-meta">
                <?php foreach ($item_meta as $meta_key) :
                    $value = get_post_meta($post->ID, $meta_key, true);
                    if ('' === $value) continue; ?>
                    <span class="meta-<?php echo esc_attr($meta_key); ?>">
                        <?php echo esc_html(ucfirst(str_replace('_', ' ', $meta_key)) . ": " . $value); ?>
                    </span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </a>
</div>
<?php
wp_reset_postdata();
