<?php

/**
 * Core loader for SmartGrid
 */

defined('ABSPATH') || exit;

class SmartGrid_Loader
{

  public function run()
  {
    add_action('init', [$this, 'register_grid_config']);
    add_action('admin_menu', [$this, 'admin_menu']);
  }

  public function register_grid_config()
  {
    register_post_type('smartgrid', [
      'labels' => [
        'name' => 'SmartGrids',
        'singular_name' => 'SmartGrid',
        'add_new' => 'Add New Grid',
        'edit_item' => 'Edit Grid',
      ],
      'public' => false,
      'show_ui' => true,
      'menu_icon' => 'dashicons-screenoptions',
      'supports' => ['title'],
    ]);
  }

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

  public function render_settings_page()
  {
    echo '<div class="wrap"><h1>SmartGrid Settings</h1><p>Settings placeholder</p></div>';
  }
}
