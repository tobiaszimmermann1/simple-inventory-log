<?php
/**
 * Plugin Name: Simple Inventory Log
 * Plugin URI:  https://github.com/tobiaszimmermann1/simple-inventory-log
 * Description: Logs every WooCommerce stock change (manual edits, orders, refunds, cancellations) in a simple, filterable admin list.
 * Version:     1.0.0
 * Author:      Tobias Zimmermann
 * Author URI:  https://github.com/tobiaszimmermann1
 * License:     GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: simple-inventory-log
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Tested up to:      6.7
 * WC requires at least: 6.0
 * WC tested up to:      9.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'SIL_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SIL_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SIL_VERSION', '1.0.0' );

if ( file_exists( plugin_dir_path( __FILE__ ) . 'vendor/autoload.php' ) ) {
    require_once plugin_dir_path( __FILE__ ) . 'vendor/autoload.php';
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
