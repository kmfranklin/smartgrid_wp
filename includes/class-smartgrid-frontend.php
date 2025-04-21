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
        '</div>',
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
}
