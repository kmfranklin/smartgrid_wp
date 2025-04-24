<?php
defined('ABSPATH') || exit;

/**
 * SmartGrid_Frontend
 * 
 * Registers the [smartgrid] shortcode and enqueues front-end assets.
 * 
 * @package SmartGrid
 */
class SmartGrid_Frontend
{

  /**
   * Constructor.
   */
  public function __construct()
  {
    add_shortcode('smartgrid', [$this, 'shortcode_callback']);
    add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);

    // AJAX endpoints for both logged-in and public users
    add_action('wp_ajax_smartgrid_fetch', [$this, 'fetch_grid_items']);
    add_action('wp_ajax_nopriv_smartgrid_fetch', [$this, 'fetch_grid_items']);
  }

  /**
   * Shortcode callback for [smartgrid id="123"].
   *
   * @param array $atts Shortcode attributes.
   * @return string     Container markup for JS to hook into.
   */
  public function shortcode_callback($atts)
  {
    $atts = shortcode_atts(
      ['id' => ''],
      $atts,
      'smartgrid'
    );

    $grid_id = absint($atts['id']);
    if (! $grid_id) {
      return '<!-- SmartGrid: missing or invalid id -->';
    }

    // 1) Render the filters form
    $output = $this->render_filters($grid_id);

    // 2) Render the grid container with page tracking
    $output .= sprintf(
      '<div class="smartgrid-container" data-grid-id="%1$d" data-page="1">'
        . '<div class="smartgrid-loading">%2$s</div>'
        . '</div>'
        // 3) Load more wrapper
        . '<div class="smartgrid-load-more-wrap"></div>',
      $grid_id,
      esc_html__('Loading grid...', 'smartgrid')
    );

    return $output;
  }

  /**
   * Enqueue front-end CSS and JS.
   * 
   * @return void
   */
  public function enqueue_assets()
  {
    // CSS
    wp_enqueue_style(
      'smartgrid-frontend-style',
      SMARTGRID_URL . 'assets/css/frontend.css',
      [],
      SMARTGRID_VERSION
    );

    // JS
    wp_enqueue_script(
      'smartgrid-frontend',
      SMARTGRID_URL . 'assets/js/frontend.js',
      ['jquery'],
      SMARTGRID_VERSION,
      true
    );

    // Pass AJAX URL
    wp_localize_script(
      'smartgrid-frontend',
      'smartgridVars',
      ['ajax_url' => admin_url('admin-ajax.php')]
    );
  }

  /**
   * Retrieve the list of meta fields configured for this grid.
   *
   * @param int $grid_id
   * @return string[]  Array of meta field keys (or empty array).
   */
  protected function get_grid_meta_fields($grid_id)
  {
    $meta = get_post_meta($grid_id, 'smartgrid_meta_fields', true);
    return is_array($meta) ? $meta : [];
  }

  /**
   * Retrieve the list of taxonomies configured for this grid.
   *
   * @param int $grid_id
   * @return string[]  Array of taxonomy slugs (or empty array).
   */
  protected function get_grid_taxonomy_filters($grid_id)
  {
    $tax = get_post_meta($grid_id, 'smartgrid_taxonomies', true);
    return is_array($tax) ? $tax : [];
  }

  /**
   * AJAX callback: query posts and return rendered HTML.
   */
  public function fetch_grid_items()
  {
    // 1) Sanitize & get grid ID
    $grid_id = isset($_REQUEST['grid_id']) ? absint($_REQUEST['grid_id']) : 0;
    $paged = isset($_REQUEST['paged']) ? absint($_REQUEST['paged']) : 1;
    if (! $grid_id) {
      wp_send_json_error('Invalid grid ID.');
    }

    // 2) Load the saved post type from meta
    $post_type = get_post_meta($grid_id, 'smartgrid_post_type', true);
    if (! $post_type) {
      wp_send_json_error('No post type configured.');
    }

    // 2a) Get configured filters for this grid
    $taxonomies = $this->get_grid_taxonomy_filters($grid_id);
    $meta_keys = $this->get_grid_meta_fields($grid_id);

    // 2b) Initialize tax_query & meta_query arrays
    $tax_query = [];
    $meta_query = ['relation' => 'AND'];

    // 2c) Process incoming form values from $_REQUEST
    // Taxonomies: expect $_REQUEST['tax'][$taxonomy] as an array of slugs
    if (!empty($_REQUEST['tax']) && is_array($_REQUEST['tax'])) {
      foreach ($taxonomies as $tax) {
        if (!empty($_REQUEST['tax'][$tax])) {
          $tax_query[] = [
            'taxonomy'    => $tax,
            'field'       => 'slug',
            'terms'       => array_map('sanitize_text_field', (array) $_REQUEST['tax'][$tax]),
            'operator'    => 'IN',
          ];
        }
      }
    }

    // Meta fields: expect $_REQUEST['meta'][$key] as a scalar or range
    if (!empty($_REQUEST['meta']) && is_array($_REQUEST['meta'])) {
      foreach ($meta_keys as $key) {
        if (isset($_REQUEST['meta'][$key]) && $_REQUEST['meta'][$key] !== '') {
          $value = sanitize_text_field(wp_unslash($_REQUEST['meta'][$key]));
          $meta_query[] = [
            'key'     => $key,
            'value'   => $value,
            'compare' => is_numeric($value) ? '=' : 'LIKE',
          ];
        }
      }
    }

    // 3) Build a WP_Query
    $query_args = [
      'post_type'     => $post_type,
      'posts_per_page' => $per_page,
      'paged'         => $paged,
    ];

    if (!empty($tax_query)) {
      $query_args['tax_query'] = ['relation' => 'AND'] + $tax_query;
    }

    if (count($meta_query) > 1) {
      $query_args['meta_query'] = $meta_query;
    }

    $query = new WP_Query($query_args);

    // 4) Render each item using a template part
    ob_start();
    if ($query->have_posts()) {
      echo '<div class="smartgrid-items">';
      while ($query->have_posts()) {
        $query->the_post();

        // Determine template hierarchy
        $template = locate_template('smartgrid/grid-item.php');
        if (! $template) {
          $template = SMARTGRID_PATH . 'templates/grid-item.php';
        }

        // Prepare variables for the template
        set_query_var('post',       get_post());
        set_query_var('grid_id',    $grid_id);
        set_query_var('item_meta',  $this->get_grid_meta_fields($grid_id));
        set_query_var('item_terms', $this->get_grid_taxonomy_filters($grid_id));

        // Load the template
        include $template;
      }

      echo '</div>';
    } else {
      echo '<p>' . esc_html__('No items found.', 'smartgrid') . '</p>';
    }
    wp_reset_postdata();

    $html = ob_get_clean();

    // Determine if there are more pages
    $has_more = $paged < $query->max_num_pages;
    $next_page = $paged + 1;

    wp_send_json_success([
      'html'      => $html,
      'more'      => $has_more,
      'next_page' => $next_page,
    ]);
  }

  /**
   * Render the filter form for a given grid config.
   * 
   * @param int $grid_id ID of the grid.
   * @return string      HTML of the filters form.
   */
  protected function render_filters($grid_id)
  {
    // 1) Load saved filter configuration
    $filters  = get_post_meta($grid_id, 'smartgrid_filters', true);
    $tax_cfg  = $filters['taxonomies'] ?? [];
    $meta_cfg = $filters['meta']       ?? [];

    ob_start();
    echo '<form class="smartgrid-filters" data-grid-id="' . esc_attr($grid_id) . '">';

    // 2) Taxonomy filters
    foreach ($tax_cfg as $tax => $cfg) {
      if (!$cfg['enabled']) {
        continue;
      }
      $terms = get_terms(['taxonomy' => $tax, 'hide_empty' => true]);
      if (empty($terms)) {
        continue;
      }

      echo '<div class="filter-group filter-taxonomy-filter-' . esc_attr($cfg['type']) . '">';
      echo '<h5>' . esc_html(get_taxonomy($tax)->label) . '</h5>';

      if ($cfg['type'] === 'dropdown') {
        echo '<select name="tax[' . esc_attr($tax) . ']">';
        echo '<option value="">' . esc_html__('All', 'smartgrid') . '</option>';
        foreach ($terms as $t) {
          printf(
            '<option value="%1$s">%2$s</option>',
            esc_attr($t->slug),
            esc_html($t->name)
          );
        }
        echo '</select>';
      } else { // checkboxes
        foreach ($terms as $t) {
          printf(
            '<label><input type="checkbox" name="tax[%1$s][]" value="%2$s"> %3$s</label>',
            esc_attr($tax),
            esc_attr($t->slug),
            esc_html($t->name)
          );
        }
      }

      echo '</div>';
    }

    // 3) Meta field filters
    foreach ($meta_cfg as $key => $cfg) {
      if (!$cfg['enabled']) {
        continue;
      }

      echo '<div class="filter-group filter-meta filter-' . esc_attr($cfg['type']) . '">';
      echo '<h5>' . esc_html(ucwords(str_replace('_', ' ', $key))) . '</h5>';

      switch ($cfg['type']) {
        case 'dropdown':
          // TODO: fetch unique values for this field
          echo '<select name="meta[' . esc_attr($key) . ']">';
          echo '<option value="">' . esc_html__('Any', 'smartgrid') . '</option>';
          echo '</select>';
          break;

        case 'checkboxes':
          // TODO: fetch unique values and loop them
          break;

        case 'slider':
          // TODO: output your slider + hidden inputs
          echo '<div class="smartgrid-slider" data-key="' . esc_attr($key) . '"></div>';
          echo '<input type="hidden" name="meta[' . esc_attr($key) . '][min]" value="">';
          echo '<input type="hidden" name="meta[' . esc_attr($key) . '][max]" value="">';
          break;

        default: // text
          echo '<input type="text" name="meta[' . esc_attr($key) . ']" placeholder="'
            . esc_attr__('Enter value…', 'smartgrid') . '">';
      }

      echo '</div>';
    }

    // 4) Submit button
    echo '<button type="submit" class="smartgrid-filter-submit">'
      . esc_html__('Apply Filters', 'smartgrid')
      . '</button>';

    echo '</form>';
    return ob_get_clean();
  }
}
