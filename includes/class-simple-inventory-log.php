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
                product_name VARCHAR(500) NOT NULL,
                sku VARCHAR(500) NOT NULL,
                date DATETIME DEFAULT CURRENT_TIMESTAMP,
                stock_change FLOAT NOT NULL,
                stock FLOAT NOT NULL,
                action VARCHAR(500) NOT NULL,
                relation VARCHAR(500) NOT NULL,
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
        add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );    
        self::hook_into_stock_changes();
        add_action( 'admin_post_export_inventory_log', [ $this, 'export_inventory_log' ] );
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

        // Set up sorting parameters
        $order_by = isset($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : 'date'; // Default to sorting by date
        $order = isset($_GET['order']) && $_GET['order'] === 'asc' ? 'ASC' : 'DESC'; // Default to descending order

        // Set up pagination parameters
        $per_page = 20;
        $current_page = isset($_GET['paged']) ? absint($_GET['paged']) : 1;
        $offset = ($current_page - 1) * $per_page;

        // Get the total number of records
        $total_records = $wpdb->get_var("SELECT COUNT(*) FROM $table");
        $total_pages = ceil($total_records / $per_page);

        // Query with sorting and pagination
        $logs = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM $table ORDER BY {$order_by} {$order} LIMIT %d OFFSET %d",
            $per_page, 
            $offset
        ), ARRAY_A );

        echo '<div class="wrap"><h1>Simple Inventory Log</h1>';
        
        echo '<div class="tablenav top">';
        echo '<div class="alignleft actions">';
        echo '<a href="' . esc_url(admin_url('admin-post.php?action=export_inventory_log')) . '" class="button button-primary">Export to Excel</a>';
        echo '</div>';
        echo '<div class="clear"></div>';
        echo '</div>';

        if ( empty( $logs ) ) {
            echo '<p>No logs found.</p>';
        } else {
            echo '<table class="widefat fixed striped">';
            echo '<thead><tr>';

            // Toggle order for sorting columns
            $toggle_order = ($order === 'ASC') ? 'desc' : 'asc';

            echo '<th><a href="' . esc_url(add_query_arg(['orderby' => 'id', 'order' => $toggle_order], 'admin.php?page=simple-inventory-log')) . '">ID</a></th>';
            echo '<th><a href="' . esc_url(add_query_arg(['orderby' => 'product_id', 'order' => $toggle_order], 'admin.php?page=simple-inventory-log')) . '">Product ID</a></th>';
            echo '<th><a href="' . esc_url(add_query_arg(['orderby' => 'product_name', 'order' => $toggle_order], 'admin.php?page=simple-inventory-log')) . '">Product Name</a></th>';
            echo '<th><a href="' . esc_url(add_query_arg(['orderby' => 'sku', 'order' => $toggle_order], 'admin.php?page=simple-inventory-log')) . '">SKU</a></th>';
            echo '<th><a href="' . esc_url(add_query_arg(['orderby' => 'date', 'order' => $toggle_order], 'admin.php?page=simple-inventory-log')) . '">Date</a></th>';
            echo '<th>Change</th><th>Stock</th><th>Action</th><th>User Name</th><th>User ID</th><th>Note</th>';
            echo '</tr></thead><tbody>';

            foreach ( $logs as $log ) {
                echo '<tr>';
                foreach ( $log as $value ) {
                    echo '<td>' . esc_html( $value ) . '</td>';
                }
                echo '</tr>';
            }

            echo '</tbody></table>';

            $this->render_pagination($current_page, $total_pages);
        }

        echo '</div>';
    }

    public function render_pagination($current_page, $total_pages) {
        if ($total_pages > 1) {
            $base_url = admin_url('admin.php?page=simple-inventory-log');
            $pagination = '<div class="tablenav"><div class="tablenav-pages">';
            
            // Previous page link
            if ($current_page > 1) {
                $pagination .= '<a class="first-page" href="' . esc_url(add_query_arg('paged', 1, $base_url)) . '">&laquo; First</a>';
                $pagination .= '<a class="prev-page" href="' . esc_url(add_query_arg('paged', $current_page - 1, $base_url)) . '">Prev</a>';
            }

            // Page numbers
            for ($i = 1; $i <= $total_pages; $i++) {
                $pagination .= '<a class="page-numbers" href="' . esc_url(add_query_arg('paged', $i, $base_url)) . '">' . $i . '</a>';
            }

            // Next page link
            if ($current_page < $total_pages) {
                $pagination .= '<a class="next-page" href="' . esc_url(add_query_arg('paged', $current_page + 1, $base_url)) . '">Next</a>';
                $pagination .= '<a class="last-page" href="' . esc_url(add_query_arg('paged', $total_pages, $base_url)) . '">Last &raquo;</a>';
            }

            $pagination .= '</div></div>';
            echo $pagination;
        }
    }

    public static function get_table_name() {
        return self::$table_name;
    }


    // Log stock changes
    public static function log_stock_change( $product_id, $change, $new_stock, $action = '', $note = '' ) {
        global $wpdb;

        // product data
        $product = wc_get_product( $product_id );
        $product_name = $product ? $product->get_name() : '';
        $sku = $product ? $product->get_sku() : '';

        // user data
        $current_user_id = get_current_user_id();
        if ($current_user_id) {
            $user = get_userdata( $current_user_id );
            if ( $user ) {
                $name  = $user->display_name;
                $email = $user->user_email;
                $user_name = $name . ' (' . $email . ')';
            } 
        } else {
            $user_name = 'System';
            $current_user_id = '';
        }       

        $wpdb->insert(
            self::$table_name,
            [
                'product_id'    => $product_id,
                'product_name'    => $product_name,
                'sku'    => $sku,
                'stock_change'  => $change,
                'stock'         => $new_stock,
                'action'        => $action,
                'relation'      => $user_name,
                'user_id'       => $current_user_id,
                'note'          => $note,
                'date'          => current_time( 'mysql' ),
            ],
            [
                '%d', // product_id (integer)
                '%s', // product_name (string)
                '%s', // sku (string)
                '%f', // stock_change (float)
                '%f', // stock (float)
                '%s', // action (string)
                '%s', // relation (string)
                '%d', // user_id (integer)
                '%s', // note (string)
                '%s', // date (string)
            ]
        );
    }

    // Handle manual stock changes (admin ui or programmatically)
    //// woocommerce_product_before_set_stock to save previous stock in a transient
    //// woocommerce_product_set_stock to log the new stock

    public static function hook_into_stock_changes() {
        add_action( 'woocommerce_product_set_stock', [ __CLASS__, 'handle_stock_change' ], 10, 1 );
        add_action( 'update_post_metadata', [ __CLASS__, 'handle_stock_change_meta_transient' ], 10, 5 );
        add_action( 'updated_post_meta', [ __CLASS__, 'handle_stock_change_meta' ], 10, 4 );
        add_action( 'woocommerce_product_before_set_stock', [ __CLASS__, 'before_handle_stock_change' ], 10, 1 );
        add_action( 'woocommerce_checkout_order_created', [ __CLASS__, 'handle_stock_change_action_order' ], 10, 1 );
        add_action( 'woocommerce_order_refunded', [ __CLASS__, 'handle_stock_change_action_refund' ], 10, 1 );
    }

    public static function handle_stock_change( $product ) {
        $product_id = $product->get_id();
        $user_id = get_current_user_id();

        $new_stock = $product->get_stock_quantity();

        $old_stock = get_transient( "sil_old_stock_{$product_id}_{$user_id}" );
        delete_transient( "sil_old_stock_{$product_id}_{$user_id}" );

        $action = 'manual';
        $action_transient_key = "sil_stock_action_queue_{$product_id}";
        $queue = get_transient( $action_transient_key );

        if ( is_array( $queue ) && count( $queue ) > 0 ) {
            $action = array_shift( $queue ); // FIFO: get first action
            set_transient( $action_transient_key, $queue, 10 * MINUTE_IN_SECONDS );
        }

        if ( $old_stock === false ) {
            $change = 0;
        } else {
            $change = $new_stock - (float) $old_stock;
        }

        if ( $change !== 0 ) {
            Simple_Inventory_Log::log_stock_change(
                $product_id,
                $change,
                $new_stock,
                $action,
                ''
            );
        }
    }

    public static function handle_stock_change_meta_transient( $check, $object_id, $meta_key, $meta_value, $prev_value ) {
        if ( $meta_key === '_stock' ) {
            $product = wc_get_product( $object_id );
            $product_id = $product->get_id();
            $user_id = get_current_user_id();

            $old_stock = floatval(get_post_meta( $object_id, '_stock', true ));
            $stock_change_transient = set_transient( "sil_old_stock_{$product_id}_{$user_id}", $old_stock, 5 * MINUTE_IN_SECONDS );
        }
    }

    public static function handle_stock_change_meta(  $meta_id, $object_id, $meta_key, $meta_value ) {
        if ( $meta_key === '_stock' ) {
            $product = wc_get_product( $object_id );
            self::handle_stock_change( $product );
        }
    }

    public static function before_handle_stock_change( $product ) {
        $product_id = $product->get_id();
        $user_id = get_current_user_id();
        $old_stock = floatval(get_post_meta( $product_id, '_stock', true ));

        $stock_change_transient = set_transient( "sil_old_stock_{$product_id}_{$user_id}", $old_stock, 5 * MINUTE_IN_SECONDS );
    }

    // Handle stock changes from orders created through the checkout process
    public static function handle_stock_change_action_order( $order ) {
        foreach ( $order->get_items() as $item ) {
            $product = $item->get_product();
            if ( ! $product ) continue;

            $product_id = $product->get_id();
            $order_id = $order->get_id();
            $transient_key = "sil_stock_action_queue_{$product_id}";

            $queue = get_transient( $transient_key );
            if ( ! is_array( $queue ) ) {
                $queue = [];
            }

            $queue[] = "order_{$order_id}";

            set_transient( $transient_key, $queue, 10 * MINUTE_IN_SECONDS );
        }
    }

    public function export_inventory_log() {
        // Check user capabilities
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
        }

        // get data
        global $wpdb;
        $table = self::$table_name;
        $logs = $wpdb->get_results( "SELECT * FROM $table ORDER BY date DESC", ARRAY_A );

        if ( empty( $logs ) ) {
            // Redirect back to the log page with a message
            wp_redirect( admin_url( 'admin.php?page=simple-inventory-log&export_status=empty' ) );
            exit;
        }
        
        // Set the headers for the CSV download
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="inventory_log.csv"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');

        $data = array();

        // set header row
        $header = array(
            'ID',
            'Product ID',
            'Product Name',
            'SKU',
            'Date',
            'Stock Change',
            'Stock',
            'Action',
            'User Name',
            'User ID',
            'Note'
        );
        array_push($data, $header);

        // set data rows
        foreach ( $logs as $log ) {
            array_push($data, array(
                $log['id'],
                $log['product_id'],
                $log['product_name'],
                $log['sku'],
                $log['date'],
                $log['stock_change'],
                $log['stock'],
                $log['action'],
                $log['relation'],
                $log['user_id'],
                $log['note']
            ));
        }

        // Populate the CSV with data
        foreach ($data as $item) {
            fputcsv($output, $item);
        }

        // Close the output stream
        fclose($output);
        exit;
    }
}
