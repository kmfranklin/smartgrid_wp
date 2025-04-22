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

    // Single sprintf: %1$d = grid ID, %2$s = escaped loading text
    return sprintf(
      '<div class="smartgrid-container" data-grid-id="%1$d">' .
        '<div class="smartgrid-loading">%2$s</div>' .
        '</div>' .
        '<div class=smartgrid-load-more-wrap></div>',
      $grid_id,
      esc_html__('Loading grid…', 'smartgrid')
    );
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

    // 3) Build a WP_Query
    $per_page = 10;
    $query = new WP_Query([
      'post_type'     => $post_type,
      'posts_per_page' => $per_page,
      'paged'         => $paged,
    ]);

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
}
