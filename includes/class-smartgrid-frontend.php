<?php
defined('ABSPATH') || exit;

/**
 * SmartGrid_Frontend
 * 
 * Registers the [smartgrid] shortcode and enqueues front-end assets.
 * Handles AJAX fetching with unified meta-query logic and per-page settings.
 *
 * @package SmartGrid
 */
class SmartGrid_Frontend
{
  public function __construct()
  {
    add_shortcode('smartgrid', [$this, 'shortcode_callback']);
    add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
    add_action('wp_ajax_smartgrid_fetch', [$this, 'fetch_grid_items']);
    add_action('wp_ajax_nopriv_smartgrid_fetch', [$this, 'fetch_grid_items']);
  }

  public function shortcode_callback($atts)
  {
    $atts    = shortcode_atts(['id' => 0], $atts, 'smartgrid');
    $grid_id = absint($atts['id']);
    if (! $grid_id) {
      return '<!-- SmartGrid: missing or invalid id -->';
    }

    // 1) Figure out layout
    $layout = get_post_meta($grid_id, 'smartgrid_filter_layout', true) ?: 'above';

    // 2) Open outer wrapper
    $output  = sprintf(
      '<div class="smartgrid-wrapper sg-layout-%1$s">',
      esc_attr($layout)
    );

    // 3) Render filters
    $output .= $this->render_filters($grid_id);

    // 4) Open the “main” column (holds both grid & load‑more)
    $output .= sprintf(
      '<div class="smartgrid-main" data-grid-id="%1$d">',
      $grid_id
    );

    // 5) Render the actual grid container, with the ID & loading placeholder
    $output .= sprintf(
      '<div class="smartgrid-container" data-grid-id="%1$d" data-page="1">'
        . '<div class="smartgrid-loading">%2$s</div>'
        . '</div>',
      $grid_id,
      esc_html__('Loading grid...', 'smartgrid')
    );

    // 6) Render the load-more wrapper
    $output .= '<div class="smartgrid-load-more-wrap"></div>';

    // 7) Close the .smartgrid-main and .smartgrid-wrapper
    $output .= '</div>'; // .smartgrid-main
    $output .= '</div>'; // .smartgrid-wrapper

    return $output;
  }

  public function enqueue_assets()
  {
    wp_enqueue_style('smartgrid-frontend-style', SMARTGRID_URL . 'assets/css/frontend.css', [], SMARTGRID_VERSION);
    wp_enqueue_style('nouislider', 'https://cdn.jsdelivr.net/npm/nouislider@15.7.0/dist/nouislider.min.css', [], '15.7.0');
    wp_enqueue_script('nouislider', 'https://cdn.jsdelivr.net/npm/nouislider@15.7.0/dist/nouislider.min.js', [], '15.7.0', true);
    wp_enqueue_script('smartgrid-frontend', SMARTGRID_URL . 'assets/js/frontend.js', ['jquery', 'nouislider'], SMARTGRID_VERSION, true);
    wp_localize_script('smartgrid-frontend', 'smartgridVars', ['ajax_url' => admin_url('admin-ajax.php')]);
  }

  protected function get_grid_meta_fields($grid_id)
  {
    $meta = get_post_meta($grid_id, 'smartgrid_meta_fields', true);
    return is_array($meta) ? $meta : [];
  }

  protected function get_grid_taxonomy_filters($grid_id)
  {
    $tax = get_post_meta($grid_id, 'smartgrid_taxonomies', true);
    return is_array($tax) ? $tax : [];
  }

  public function fetch_grid_items()
  {
    $grid_id = isset($_REQUEST['grid_id']) ? absint($_REQUEST['grid_id']) : 0;
    $paged   = isset($_REQUEST['paged'])   ? absint($_REQUEST['paged'])   : 1;
    if (!$grid_id) {
      wp_send_json_error('Invalid grid ID.');
    }

    $post_type = get_post_meta($grid_id, 'smartgrid_post_type', true);
    if (!$post_type) {
      wp_send_json_error('No post type configured.');
    }

    // Prepare tax and meta query arrays
    $taxonomies = $this->get_grid_taxonomy_filters($grid_id);
    $tax_query  = [];
    $meta_query = ['relation' => 'AND'];

    // -- Process taxonomy filters --
    if (!empty($_REQUEST['tax']) && is_array($_REQUEST['tax'])) {
      foreach ($taxonomies as $tax) {
        if (!empty($_REQUEST['tax'][$tax])) {
          $terms = array_map('sanitize_text_field', (array) $_REQUEST['tax'][$tax]);
          $tax_query[] = [
            'taxonomy' => $tax,
            'field'    => 'slug',
            'terms'    => $terms,
            'operator' => 'IN',
          ];
        }
      }
    }

    // -- Unified meta filter loop (ranges, checkboxes, scalars) --
    if (!empty($_REQUEST['meta']) && is_array($_REQUEST['meta'])) {
      foreach ($_REQUEST['meta'] as $mkey => $mval) {
        // Range slider: expects ['min'=>x,'max'=>y]
        if (is_array($mval) && isset($mval['min'], $mval['max'])) {
          $min = floatval($mval['min']);
          $max = floatval($mval['max']);
          $meta_query[] = [
            'key'     => sanitize_key($mkey),
            'value'   => [$min, $max],
            'type'    => 'NUMERIC',
            'compare' => 'BETWEEN',
          ];
        }
        // Checkbox array filter
        elseif (is_array($mval) && $mval) {
          $values = array_map('sanitize_text_field', $mval);
          $meta_query[] = [
            'key'     => sanitize_key($mkey),
            'value'   => $values,
            'type'    => 'NUMERIC',
            'compare' => 'IN',
          ];
        }
        // Scalar filter
        elseif ($mval !== '') {
          $clean = sanitize_text_field(wp_unslash($mval));
          $meta_query[] = [
            'key'     => sanitize_key($mkey),
            'value'   => $clean,
            'compare' => is_numeric($clean) ? '=' : 'LIKE',
          ];
        }
      }
    }

    // Use search term if provided
    if (!empty($_REQUEST['s'])) {
      $search = sanitize_text_field(wp_unslash($_REQUEST['s']));
      $args['s'] = $search;
    }

    // Determine per-page (grid admin setting or fallback)
    // If the user explicitly entered “0”, show all posts (=> -1)
    $raw_pp = get_post_meta($grid_id, 'smartgrid_posts_per_page', true);
    if (intval($raw_pp) === 0 && $raw_pp !== '') {
      $per_page = -1;
    } else {
      // positive number or blank
      $per_page = absint($raw_pp);
      if (! $per_page) {
        $per_page = get_option('posts_per_page') ?: 10;
      }
    }

    // Build WP_Query args
    $args = [
      'post_type'      => $post_type,
      'posts_per_page' => $per_page,
      'paged'          => $paged,
      's'              => !empty($_REQUEST['s'])
        ? sanitize_text_field(wp_unslash($_REQUEST['s']))
        : '',
    ];
    if ($tax_query) {
      $args['tax_query'] = array_merge(['relation' => 'AND'], $tax_query);
    }
    if (count($meta_query) > 1) {
      $args['meta_query'] = $meta_query;
    }

    $query = new WP_Query($args);

    // Render items
    ob_start();
    if ($query->have_posts()) {
      echo '<div class="smartgrid-items">';
      while ($query->have_posts()) {
        $query->the_post();
        $template = locate_template('smartgrid/grid-item.php') ?: SMARTGRID_PATH . 'templates/grid-item.php';
        set_query_var('post',       get_post());
        set_query_var('grid_id',    $grid_id);
        set_query_var('item_meta',  $this->get_grid_meta_fields($grid_id));
        set_query_var('item_terms', $this->get_grid_taxonomy_filters($grid_id));
        include $template;
      }
      echo '</div>';
    } else {
      echo '<p>' . esc_html__('No items found.', 'smartgrid') . '</p>';
    }
    wp_reset_postdata();
    $html = ob_get_clean();

    wp_send_json_success([
      'html'      => $html,
      'more'      => $paged < $query->max_num_pages,
      'next_page' => $paged + 1,
    ]);
  }

  /**
   * Return all distinct values for a given meta key on the selected post type.
   *
   * @param int    $grid_id  Grid post ID
   * @param string $meta_key The meta_key to pull values for.
   * @return string[]        List of unique meta_values.
   */
  protected function get_unique_meta_values($grid_id, $meta_key)
  {
    global $wpdb;
    // 1) figure out which post type this grid is configured for
    $post_type = get_post_meta($grid_id, 'smartgrid_post_type', true);
    if (! $post_type) {
      return [];
    }

    // 2) query distinct meta_values
    $sql = $wpdb->prepare(
      "SELECT DISTINCT pm.meta_value
         FROM {$wpdb->postmeta} pm
         JOIN {$wpdb->posts} p
           ON pm.post_id = p.ID
        WHERE p.post_type  = %s
          AND pm.meta_key   = %s
          AND pm.meta_value <> ''
        ORDER BY pm.meta_value+0 ASC, pm.meta_value ASC",
      $post_type,
      $meta_key
    );

    return (array) $wpdb->get_col($sql);
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

    // Show search if admin-enabled
    if (get_post_meta($grid_id, 'smartgrid_include_search', true)) {
      echo '<div class="filter-group filter-search">';
      echo '<input type="search" name="s" class="smartgrid-search-input" placeholder="'
        . esc_attr__('Search...', 'smartgrid') . '">';
      echo '</div>';
    }

    // Taxonomy filters
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

    // Meta field filters
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
          $values = $this->get_unique_meta_values($grid_id, $key);
          foreach ($values as $val) {
            printf(
              '<label><input type="checkbox" name="meta[%1$s][]" value="%2$s"> %2$s</label><br>',
              esc_attr($key),
              esc_attr($val)
            );
          }
          break;

        case 'slider':
          list($min, $max) = $this->get_meta_range($grid_id, $key);
?>
          <div class="smartgrid-slider-wrapper">
            <div class="smartgrid-slider"
              data-key="<?php echo esc_attr($key); ?>"
              data-min="<?php echo esc_attr($min); ?>"
              data-max="<?php echo esc_attr($max); ?>"></div>

            <div class="smartgrid-slider-controls">
              <span class="smartgrid-slider-range">
                <?php echo number_format_i18n($min); ?> &ndash; <?php echo number_format_i18n($max); ?>
              </span>
              <button type="button" class="smartgrid-slider-clear">
                <?php esc_html_e('Clear', 'smartgrid'); ?>
              </button>
            </div>

            <input type="hidden" name="meta[<?php echo esc_attr($key); ?>][min]" value="<?php echo esc_attr($min); ?>" />
            <input type="hidden" name="meta[<?php echo esc_attr($key); ?>][max]" value="<?php echo esc_attr($max); ?>" />
          </div>
<?php
          break;

        default: // text
          echo '<input type="text" name="meta[' . esc_attr($key) . ']" placeholder="'
            . esc_attr__('Enter value…', 'smartgrid') . '">';
      }

      echo '</div>';
    }

    // Submit button
    echo '<button type="submit" class="smartgrid-filter-submit">'
      . esc_html__('Apply Filters', 'smartgrid')
      . '</button>';

    echo '</form>';
    return ob_get_clean();
  }

  /**
   * Get the numeric min/max for a given meta_key on this grid's post type.
   * 
   * @param int     $grid_id The grid post ID.
   * @param string  $meta_key The ACF/meta field key.
   * @return array  [min, max] (both floats), or [0, 0] if none.
   */
  protected function get_meta_range($grid_id, $meta_key)
  {
    global $wpdb;
    $post_type = get_post_meta($grid_id, 'smartgrid_post_type', true);
    if (!$post_type) {
      return [0, 0];
    }
    $sql = $wpdb->prepare(
      "SELECT
        MIN(CAST(pm.meta_value AS DECIMAL(10,2))) as min_val,
        MAX(CAST(pm.meta_value AS DECIMAL(10,2))) as max_val
      FROM {$wpdb->postmeta} pm
      INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
      WHERE p.post_type = %s
        AND pm.meta_key = %s",
      $post_type,
      $meta_key
    );
    $row = $wpdb->get_row($sql);
    return [
      floatval($row->min_val ?? 0),
      floatval($row->max_val ?? 0),
    ];
  }
}
