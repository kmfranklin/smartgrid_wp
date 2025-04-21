<?php

/**
 * Plugin Name: SmartGrid
 * Description: A visual grid builder for custom post types, with AJAX filters and shortcode support.
 * Version: 0.1.0
 * Author: Kevin Franklin | Nomadic Software
 * License: GPL2+
 */

defined('ABSPATH') || exit;

define('SMARTGRID_VERSION', '0.1.0');
define('SMARTGRID_PATH', plugin_dir_path(__FILE__));
define('SMARTGRID_URL', plugin_dir_url(__FILE__));

require_once SMARTGRID_PATH . 'includes/class-smartgrid-loader.php';

add_action('plugins_loaded', function () {
    $loader = new SmartGrid_Loader();
    $loader->run();
});
