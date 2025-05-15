<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Simple_Inventory_Log {
    private static $table_name;

    public function __construct() {
        global $wpdb;
        self::$table_name = $wpdb->prefix . 'inventory_log';
    }

    public static function activate() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'inventory_log';
        $charset_collate = $wpdb->get_charset_collate();

        // Check if table exists
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" ) != $table_name ) {
            require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

            $sql = "CREATE TABLE $table_name (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                product_id BIGINT UNSIGNED NOT NULL,
                date DATETIME DEFAULT CURRENT_TIMESTAMP,
                stock_change INT NOT NULL,
                stock INT NOT NULL,
                action VARCHAR(20) NOT NULL,
                relation VARCHAR(100) DEFAULT NULL,
                user_id BIGINT UNSIGNED DEFAULT NULL,
                note TEXT DEFAULT NULL,
                PRIMARY KEY (id),
                INDEX (product_id),
                INDEX (date)
            ) $charset_collate;";

            dbDelta( $sql );
        }
    }

    public static function deactivate() {
        // No action needed; keeping data on deactivation
    }

    public function run() {
        // Hook into WordPress (e.g. add menu, REST routes, etc. if needed)
    }

    public function add_admin_menu() {
      add_menu_page(
          'Inventory Log',
          'Inventory Log',
          'manage_options',
          'simple-inventory-log',
          [ $this, 'render_admin_page' ],
          'dashicons-clipboard',
          26
      );
    }

    public function render_admin_page() {
      global $wpdb;
      $table = self::$table_name;
      $logs = $wpdb->get_results( "SELECT * FROM $table ORDER BY date DESC LIMIT 100", ARRAY_A );

      echo '<div class="wrap"><h1>Simple Inventory Log</h1>';
      if ( empty( $logs ) ) {
          echo '<p>No logs found.</p>';
      } else {
          echo '<table class="widefat fixed striped">';
          echo '<thead><tr><th>ID</th><th>Product ID</th><th>Date</th><th>Change</th><th>Stock</th><th>Action</th><th>Relation</th><th>User ID</th><th>Note</th></tr></thead><tbody>';
          foreach ( $logs as $log ) {
              echo '<tr>';
              foreach ( $log as $value ) {
                  echo '<td>' . esc_html( $value ) . '</td>';
              }
              echo '</tr>';
          }
          echo '</tbody></table>';
      }
      echo '</div>';
    }

    public static function get_table_name() {
        return self::$table_name;
    }
}
