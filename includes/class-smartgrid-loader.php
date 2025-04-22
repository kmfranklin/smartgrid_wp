<?php

/**
 * Core loader for SmartGrid
 */

defined('ABSPATH') || exit;
require_once SMARTGRID_PATH . 'includes/class-smartgrid-admin.php';
require_once SMARTGRID_PATH . 'includes/class-smartgrid-frontend.php';

/**
 * SmartGrid_Loader
 * 
 * Handles registration of the SmartGrid CPT, admin menus,
 * and bootstraps the plugin's core functionality.
 */
class SmartGrid_Loader
{

  /**
   * Run all loader actions.
   * 
   * Hooks into WordPress actions to register post types
   * and load admin features.
   * 
   * @return void
   */
  public function run()
  {
    add_action('init', [$this, 'register_grid_config']);
    add_action('admin_menu', [$this, 'admin_menu']);
    add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);

    // Load admin UI only when in WP-Admin
    if (is_admin()) {
      new SmartGrid_Admin();
    }
    new SmartGrid_Frontend();
  }

  /**
   * Enqueue admin-only styles for our Filters UI.
   * 
   * @param string $hook_suffix The current admin page hook.
   */
  public function enqueue_admin_assets($hook_suffix)
  {
    $screen = get_current_screen();

    // Only load on SmartGrid edit/add screens
    if (in_array($screen->post_type, ['smartgrid'], true)) {
      wp_enqueue_style(
        'smartgrid-admin',
        SMARTGRID_URL . 'assets/css/admin.css',
        [],
        SMARTGRID_VERSION
      );
    }
  }

  /**
   * Register the 'smartgrid' custom post type.
   * 
   * @return void
   */
  public function register_grid_config()
  {
    register_post_type('smartgrid', [
      'labels' => [
        'name'               => __('SmartGrids',         'smartgrid'),
        'singular_name'      => __('SmartGrid',          'smartgrid'),
        'menu_name'          => __('SmartGrids',         'smartgrid'),
        'name_admin_bar'     => __('SmartGrid',          'smartgrid'),
        'add_new'            => __('Add New',            'smartgrid'),
        'add_new_item'       => __('Add New Grid',       'smartgrid'),
        'edit_item'          => __('Edit Grid',          'smartgrid'),
        'new_item'           => __('New Grid',           'smartgrid'),
        'all_items'          => __('All Grids',          'smartgrid'),
        'view_item'          => __('View Grid',          'smartgrid'),
        'search_items'       => __('Search Grids',       'smartgrid'),
        'not_found'          => __('No grids found.',    'smartgrid'),
        'not_found_in_trash' => __('No grids in trash.', 'smartgrid'),
      ],
      'public'       => false,
      'show_ui'      => true,
      'show_in_menu' => true,
      'menu_icon'    => 'dashicons-screenoptions',
      'supports'     => ['title'],
    ]);
  }

  /**
   * Add a settings submenu under the SmartGrids CPT.
   * 
   * @return void
   */
  public function admin_menu()
  {
    add_submenu_page(
      'edit.php?post_type=smartgrid',
      'SmartGrid Settings',
      'Settings',
      'manage_options',
      'smartgrid-settings',
      [$this, 'render_settings_page']
    );
  }

  /**
   * Render the SmartGrid Settings admin page.
   * 
   * @return void
   */
  public function render_settings_page()
  {
    echo '<div class="wrap"><h1>SmartGrid Settings</h1><p>Settings placeholder</p></div>';
  }
}
