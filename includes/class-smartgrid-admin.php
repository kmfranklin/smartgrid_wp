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
    add_action('add_meta_boxes', [$this, 'add_filters_metabox'], 15);
    add_action('save_post_smartgrid', [$this, 'save_filters_meta'], 20, 2);
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

    echo '<p>';
    echo '<label for="smartgrid_post_type">' . __('Post Type', 'smartgrid') . '</label><br />';
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
    echo '</select>';
    echo '</p>';
  }

  /**
   * Register a metabox to choose filterable taxonomies & meta fields.
   * 
   */
  public function add_filters_metabox()
  {
    add_meta_box(
      'smartgrid_filters',
      __('Filters Configuration', 'smartgrid'),
      [$this, 'render_filters_box'],
      'smartgrid',
      'normal',
      'high'
    );
  }

  /**
   * Outputs checkboxes for taxonomies & meta keys.
   */
  public function render_filters_box($post)
  {
    $post_type = get_post_meta($post->ID, 'smartgrid_post_type', true);

    wp_nonce_field('smartgrid_filters_save', 'smartgrid_filters_nonce');

    // Load saved values
    $filters = get_post_meta($post->ID, 'smartgrid_filters', true);
    $tax_cfg  = $filters['taxonomies'] ?? [];
    $meta_cfg = $filters['meta']       ?? [];

    // 1) Taxonomy filters
    echo '<h4>' . esc_html__('Taxonomy Filters', 'smartgrid') . '</h4>';
    $taxes = get_taxonomies(['public' => true], 'objects');
    foreach ($taxes as $tax) {
      $enabled = ! empty($tax_cfg[$tax->name]['enabled']);
      $type    = $tax_cfg[$tax->name]['type'] ?? '';

      printf(
        '<div class="filter-row">
         <label>
           <input type="checkbox"
                  name="smartgrid_filters[taxonomies][%1$s][enabled]"
                  %2$s
           />
           %3$s
         </label>
         <select name="smartgrid_filters[taxonomies][%1$s][type]">
           <option value="" disabled %4$s>%5$s</option>
           <option value="dropdown"   %6$s>%7$s</option>
           <option value="checkboxes" %8$s>%9$s</option>
         </select>
       </div>',
        esc_attr($tax->name),
        checked($enabled, true, false),
        esc_html($tax->label),
        selected($type, '',         false), // placeholder
        esc_html__('Select type…',  'smartgrid'),
        selected($type, 'dropdown',  false),
        esc_html__('Dropdown',      'smartgrid'),
        selected($type, 'checkboxes', false),
        esc_html__('Checkboxes',    'smartgrid')
      );
    }

    // 2) Meta field filters
    echo '<h4>' . esc_html__('Meta Field Filters', 'smartgrid') . '</h4>';

    $post_type = get_post_meta($post->ID, 'smartgrid_post_type', true);
    if ($post_type) {
      global $wpdb;
      $raw_keys = $wpdb->get_col($wpdb->prepare(
        "SELECT DISTINCT pm.meta_key
             FROM {$wpdb->postmeta} pm
             JOIN {$wpdb->posts} p ON pm.post_id = p.ID
             WHERE p.post_type = %s
               AND pm.meta_key NOT LIKE '\\_%'
             ORDER BY pm.meta_key",
        $post_type
      ));
    } else {
      $raw_keys = [];
    }

    if (empty($raw_keys)) {
      echo '<p>' . esc_html__('No custom fields found.', 'smartgrid') . '</p>';
    } else {
      foreach ($raw_keys as $key) {
        $enabled = ! empty($meta_cfg[$key]['enabled']);
        $type    = $meta_cfg[$key]['type'] ?? '';

        printf(
          '<div class="filter-row">
             <label>
               <input type="checkbox"
                      name="smartgrid_filters[meta][%1$s][enabled]"
                      %2$s
               />
               %3$s
             </label>
             <select name="smartgrid_filters[meta][%1$s][type]">
               <option value="" disabled %4$s>%5$s</option>
               <option value="text"       %6$s>%7$s</option>
               <option value="dropdown"   %8$s>%9$s</option>
               <option value="checkboxes" %10$s>%11$s</option>
               <option value="slider"     %12$s>%13$s</option>
             </select>
           </div>',
          esc_attr($key),
          checked($enabled, true, false),
          esc_html(ucwords(str_replace('_', ' ', $key))),
          selected($type, '',            false),
          esc_html__('Select type…',     'smartgrid'),
          selected($type, 'text',        false),
          esc_html__('Text',             'smartgrid'),
          selected($type, 'dropdown',    false),
          esc_html__('Dropdown',         'smartgrid'),
          selected($type, 'checkboxes',  false),
          esc_html__('Checkboxes',       'smartgrid'),
          selected($type, 'slider',      false),
          esc_html__('Slider',           'smartgrid')
        );
      }
    }
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
   * Save the configured filters when a Grid is saved.
   *
   * @param int     $post_id The post ID being saved.
   * @param WP_Post $post    The post object.
   * @return void
   */
  public function save_filters_meta($post_id, $post)
  {
    // Verify nonce
    if (
      empty($_POST['smartgrid_filters_nonce']) ||
      ! wp_verify_nonce($_POST['smartgrid_filters_nonce'], 'smartgrid_filters_save')
    ) {
      return;
    }

    // Bail on autosave, wrong post type, or insufficient permissions
    if (
      defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ||
      $post->post_type !== 'smartgrid' ||
      ! current_user_can('edit_post', $post_id)
    ) {
      return;
    }

    // Grab the raw filters array (may not exist yet)
    $raw = $_POST['smartgrid_filters'] ?? [];

    // Prepare a clean structure
    $clean = [
      'taxonomies' => [],
      'meta'       => [],
    ];

    // Process taxonomy filters
    if (! empty($raw['taxonomies']) && is_array($raw['taxonomies'])) {
      foreach ($raw['taxonomies'] as $tax => $cfg) {
        $enabled = ! empty($cfg['enabled']);
        $type    = sanitize_text_field($cfg['type'] ?? '');
        if ($enabled && in_array($type, ['dropdown', 'checkboxes'], true)) {
          $clean['taxonomies'][$tax] = [
            'enabled' => true,
            'type'    => $type,
          ];
        }
      }
    }

    // Process meta‑field filters
    if (! empty($raw['meta']) && is_array($raw['meta'])) {
      foreach ($raw['meta'] as $key => $cfg) {
        $enabled = ! empty($cfg['enabled']);
        $type    = sanitize_text_field($cfg['type'] ?? '');
        if ($enabled && in_array($type, ['text', 'dropdown', 'checkboxes', 'slider'], true)) {
          $clean['meta'][$key] = [
            'enabled' => true,
            'type'    => $type,
          ];
        }
      }
    }

    // Save the full filters config in one meta key
    update_post_meta($post_id, 'smartgrid_filters', wp_slash($clean));
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
