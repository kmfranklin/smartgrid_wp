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
    add_action('add_meta_boxes', [$this, 'add_filters_metabox'], 15);
    add_action('add_meta_boxes', [$this, 'add_shortcode_metabox'], 20);
    add_action('save_post_smartgrid', [$this, 'save_grid_meta'], 10, 2);
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

    // Retrieve existing values
    $selected_pt = get_post_meta($post->ID, 'smartgrid_post_type', true) ?: '';
    $per_page = get_post_meta($post->ID, 'smartgrid_posts_per_page', true);
    if ($per_page === '') {
      $per_page = 10;
    }

    // Post type selector
    echo '<p>';
    echo '<label for="smartgrid_post_type">' . __('Post Type', 'smartgrid') . '</label><br>';
    echo '<select id="smartgrid_post_type" name="smartgrid_post_type">';
    echo '<option value="">' . __('— Select —', 'smartgrid') . '</option>';
    $types = get_post_types(['public' => true], 'objects');
    foreach ($types as $type) {
      printf(
        '<option value="%1$s" %2$s>%3$s</option>',
        esc_attr($type->name),
        selected($selected_pt, $type->name, false),
        esc_html($type->label)
      );
    }
    echo '</select>';
    echo '</p>';

    // Posts per page input
    echo '<p>';
    echo '<label for="smartgrid_posts_per_page">' . __('Posts per page', 'smartgrid') . '</label><br>';
    echo sprintf(
      '<input type="number" id="smartgrid_posts_per_page" name="smartgrid_posts_per_page" value="%s" min="0">',
      esc_attr($per_page)
    );
    echo '<br><em>' . esc_html__('Enter 0 to show all posts', 'smartgrid') . '</em>';
    echo '</p>';
  }

  /**
   * Register a metabox to choose filterable taxonomies & meta fields.
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
  public function render_filters_box(WP_Post $post)
  {
    wp_nonce_field('smartgrid_filters_save', 'smartgrid_filters_nonce');

    // 1) Load grid settings & saved filters
    $post_type = get_post_meta($post->ID, 'smartgrid_post_type', true);
    $filters   = get_post_meta($post->ID, 'smartgrid_filters', true);
    $tax_cfg   = $filters['taxonomies'] ?? [];
    $meta_cfg  = $filters['meta']       ?? [];

    // 2) Taxonomy filters
    echo '<h4>' . esc_html__('Taxonomy Filters', 'smartgrid') . '</h4>';
    echo '<div class="filters-grid">';
    foreach (get_taxonomies(['public' => true], 'objects') as $tax) {
      $enabled = !empty($tax_cfg[$tax->name]['enabled']);
      $type    = $tax_cfg[$tax->name]['type'] ?? '';
      echo '<div class="filter-row">';
      echo '<label><input type="checkbox" name="smartgrid_filters[taxonomies][' . esc_attr($tax->name) . '][enabled]" ' . checked($enabled, true, false) . '> ' . esc_html($tax->label) . '</label>';
      echo '<select name="smartgrid_filters[taxonomies][' . esc_attr($tax->name) . '][type]">';
      echo '<option value="">' . esc_html__('Select type…', 'smartgrid') . '</option>';
      echo '<option value="dropdown" ' . selected($type, 'dropdown', false) . '>' . esc_html__('Dropdown', 'smartgrid') . '</option>';
      echo '<option value="checkboxes" ' . selected($type, 'checkboxes', false) . '>' . esc_html__('Checkboxes', 'smartgrid') . '</option>';
      echo '</select>';
      echo '</div>';
    }
    echo '</div>';

    // 3) Meta filters — require post type
    echo '<h4>' . esc_html__('Meta Field Filters', 'smartgrid') . '</h4>';
    if (!$post_type) {
      echo '<p><em>' . esc_html__('Please select and save a Post Type above.', 'smartgrid') . '</em></p>';
      return;
    }

    global $wpdb;
    $all_keys = $wpdb->get_col($wpdb->prepare(
      "SELECT DISTINCT pm.meta_key
             FROM {$wpdb->postmeta} pm
             JOIN {$wpdb->posts} p ON pm.post_id = p.ID
             WHERE p.post_type = %s
               AND pm.meta_key NOT LIKE '\_%'
             ORDER BY pm.meta_key",
      $post_type
    ));

    // 4) JS repeater template
?>
    <script type="text/html" id="tmpl-smartgrid-meta-row">
      <div class="smartgrid-meta-row">
        <select class="smartgrid-meta-field">
          <option value=""><?php esc_html_e('Select field…', 'smartgrid') ?></option>
          <?php foreach ($all_keys as $field_key): ?>
            <option value="<?php echo esc_attr($field_key) ?>">
              <?php echo esc_html(ucwords(str_replace('_', ' ', $field_key))) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <select class="smartgrid-meta-type">
          <option value="text"><?php esc_html_e('Text', 'smartgrid') ?></option>
          <option value="dropdown"><?php esc_html_e('Dropdown', 'smartgrid') ?></option>
          <option value="checkboxes"><?php esc_html_e('Checkboxes', 'smartgrid') ?></option>
          <option value="slider"><?php esc_html_e('Slider', 'smartgrid') ?></option>
        </select>
        <button class="button smartgrid-meta-remove">x</button>
        <input type="hidden" name="smartgrid_filters[meta][{{ data.key }}][enabled]" value="1">
        <input type="hidden" name="smartgrid_filters[meta][{{ data.key }}][type]" value="{{ data.type }}">
      </div>
    </script>

    <div id="smartgrid-meta-repeater">
      <?php foreach ($meta_cfg as $key => $cfg): ?>
        <div class="smartgrid-meta-row">
          <select class="smartgrid-meta-field">
            <option value=""><?php esc_html_e('Select field…', 'smartgrid') ?></option>
            <?php foreach ($all_keys as $k): ?>
              <option value="<?php echo esc_attr($k) ?>" <?php selected($key, $k) ?>>
                <?php echo esc_html(ucwords(str_replace('_', ' ', $k))) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <select class="smartgrid-meta-type">
            <option value="text" <?php selected($cfg['type'], 'text') ?>><?php esc_html_e('Text', 'smartgrid') ?></option>
            <option value="dropdown" <?php selected($cfg['type'], 'dropdown') ?>><?php esc_html_e('Dropdown', 'smartgrid') ?></option>
            <option value="checkboxes" <?php selected($cfg['type'], 'checkboxes') ?>><?php esc_html_e('Checkboxes', 'smartgrid') ?></option>
            <option value="slider" <?php selected($cfg['type'], 'slider') ?>><?php esc_html_e('Slider', 'smartgrid') ?></option>
          </select>
          <button class="button smartgrid-meta-remove">×</button>
          <input type="hidden" name="smartgrid_filters[meta][<?php echo esc_attr($key) ?>][enabled]" value="1">
          <input type="hidden" name="smartgrid_filters[meta][<?php echo esc_attr($key) ?>][type]" value="<?php echo esc_attr($cfg['type']) ?>">
        </div>
      <?php endforeach; ?>

      <button type="button" id="smartgrid-meta-add" class="button">
        <?php esc_html_e('Add Meta Filter', 'smartgrid') ?>
      </button>
    </div>
<?php

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
    // Verify nonce and permissions
    if (!isset($_POST['smartgrid_nonce']) || !wp_verify_nonce($_POST['smartgrid_nonce'], 'smartgrid_save')) {
      return;
    }
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if ($post->post_type !== 'smartgrid') return;
    if (!current_user_can('edit_post', $post_id)) return;

    // Save post type
    $pt = sanitize_text_field($_POST['smartgrid_post_type'] ?? '');
    update_post_meta($post_id, 'smartgrid_post_type', $pt);

    // Save posts per page
    if (isset($_POST['smartgrid_posts_per_page'])) {
      update_post_meta(
        $post_id,
        'smartgrid_posts_per_page',
        absint($_POST['smartgrid_posts_per_page'])
      );
    }
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
    // existing save_filters_meta implementation
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
    echo '<input type="text" readonly style="width:100%;font-family:monospace;" value="' . esc_attr($shortcode) . '" onclick="this.select();">';
  }
}
