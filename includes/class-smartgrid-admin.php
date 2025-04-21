<?php
defined('ABSPATH') || exit;

/**
 * SmartGrid_Admin
 * 
 * Adds meta-boxes and handles saving of grid configuration
 * for the SmartGrid custom post type.
 * 
 * @package SmartGrid
 */
class SmartGrid_Admin
{

    /**
     * Constructor.
     * 
     * Hooks into WordPress to register meta-boxes and save handlers.
     */
    public function __construct()
    {
        add_action('add_meta_boxes', [$this, 'add_grid_meta_boxes']);
        add_action('add_meta_boxes', [$this, 'add_shortcode_metabox'], 20);
        add_action('save_post_smartgrid', [$this, 'save_grid_meta'], 10, 2);
    }


    /**
     * Register the "Grid Settings" meta-box on the SmartGrid CPT edit screen.
     * 
     * @return void
     */
    public function add_grid_meta_boxes()
    {
        add_meta_box(
            'smartgrid_settings',
            __('Grid Settings', 'smartgrid'),
            [$this, 'render_settings_box'],
            'smartgrid',
            'normal',
            'default'
        );
    }

    /**
     * Render contents of the Grid Settings meta-box.
     * 
     * @param WP_Post $post The current post object.
     * @return void
     */
    public function render_settings_box($post)
    {
        // Nonce for security
        wp_nonce_field('smartgrid_save', 'smartgrid_nonce');

        // Retrieve existing value or default
        $selected = get_post_meta($post->ID, 'smartgrid_post_type', true) ?: '';

        // Fetch public CPTs
        $types = get_post_types(['public' => true], 'objects');

        echo '<p><label for="smartgrid_post_type">' . __('Post Type', 'smartgrid') . '</label></p>';
        echo '<select id="smartgrid_post_type" name="smartgrid_post_type">';
        echo '<option value="">' . __('— Select —', 'smartgrid') . '</option>';
        foreach ($types as $type) {
            printf(
                '<option value="%1$s"%2$s>%3$s</option>',
                esc_attr($type->name),
                selected($selected, $type->name, false),
                esc_html($type->label)
            );
        }
        echo '</select></p>';
    }

    /**
     * Save the Grid Settings when the post is saved.
     * 
     * @param int     $post_id Post ID.
     * @param WP_Post $post    Post object.
     * @return void
     */
    public function save_grid_meta($post_id, $post)
    {
        if (
            ! isset($_POST['smartgrid_nonce']) ||
            ! wp_verify_nonce($_POST['smartgrid_nonce'], 'smartgrid_save')
        ) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if ($post->post_type !== 'smartgrid') return;
        if (! current_user_can('edit_post', $post_id)) return;

        // Sanitize and save
        $pt = sanitize_text_field($_POST['smartgrid_post_type'] ?? '');
        update_post_meta($post_id, 'smartgrid_post_type', $pt);
    }

    /**
     * Register a metabox to display the copy-able shortcode.
     */
    public function add_shortcode_metabox()
    {
        add_meta_box(
            'smartgrid_shortcode',
            __('Shortcode', 'smartgrid'),
            [$this, 'render_shortcode_box'],
            'smartgrid',
            'side',
            'high'
        );
    }

    /**
     * Render the shortcode input.
     * 
     * @param WP_Post $post
     */
    public function render_shortcode_box($post)
    {
        $shortcode = sprintf('[smartgrid id="%d"]', $post->ID);
        echo '<input type="text" readonly style="width:100%;font-family:monospace;" value="'
            . esc_attr($shortcode)
            . '" onclick="this.select();" />';
    }
}
