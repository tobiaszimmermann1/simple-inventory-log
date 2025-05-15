<?php
/**
 * Plugin Name: Simple Inventory Log
 * Description: Logs stock changes (increase/decrease) for products.
 * Version: 1.0.0
 * Author: Tobias Zimmermann
 * License: GPL2+
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once plugin_dir_path( __FILE__ ) . 'includes/class-simple-inventory-log.php';

// Initialize the plugin
function run_simple_inventory_log() {
    $plugin = new Simple_Inventory_Log();
    $plugin->run();
}

register_activation_hook( __FILE__, [ 'Simple_Inventory_Log', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'Simple_Inventory_Log', 'deactivate' ] );

run_simple_inventory_log();
