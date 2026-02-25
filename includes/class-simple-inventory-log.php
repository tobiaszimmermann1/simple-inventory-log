<?php

if ( ! defined( 'ABSPATH' ) ) {
exit;
}

class Simple_Inventory_Log {
private static $table_name;

/** Columns shown in the admin table, in display order. */
private static $columns = [
'id'           => 'ID',
'product_id'   => 'Product ID',
'product_name' => 'Product Name',
'sku'          => 'SKU',
'date'         => 'Date',
'stock_change' => 'Change',
'stock'        => 'Stock',
'action'       => 'Action',
'relation'     => 'User',
'user_id'      => 'User ID',
'note'         => 'Note',
];

/** Columns that support ORDER BY. */
private static $sortable_columns = [
'id', 'product_id', 'product_name', 'sku', 'date', 'stock_change', 'stock', 'action',
];

public function __construct() {
global $wpdb;
self::$table_name = $wpdb->prefix . 'inventory_log';
}

// ── Activation / deactivation ────────────────────────────────────────────

public static function activate() {
global $wpdb;

$table_name      = $wpdb->prefix . 'inventory_log';
$charset_collate = $wpdb->get_charset_collate();

if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) !== $table_name ) {
require_once ABSPATH . 'wp-admin/includes/upgrade.php';

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
// Keep data on deactivation; cleanup happens on uninstall.
}

// ── Bootstrap ────────────────────────────────────────────────────────────

public function run() {
add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
self::hook_into_stock_changes();
add_action( 'admin_post_export_inventory_log', [ $this, 'export_inventory_log' ] );
}

public function add_admin_menu() {
add_menu_page(
__( 'Inventory Log', 'simple-inventory-log' ),
__( 'Inventory Log', 'simple-inventory-log' ),
'manage_options',
'simple-inventory-log',
[ $this, 'render_admin_page' ],
'dashicons-clipboard',
26
);
}

public function enqueue_admin_assets( $hook ) {
if ( 'toplevel_page_simple-inventory-log' !== $hook ) {
return;
}
wp_enqueue_style(
'simple-inventory-log',
SIL_PLUGIN_URL . 'assets/css/admin.css',
[],
SIL_VERSION
);
}

// ── Admin page ───────────────────────────────────────────────────────────

public function render_admin_page() {
if ( ! current_user_can( 'manage_options' ) ) {
wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'simple-inventory-log' ) );
}

global $wpdb;
$table = self::$table_name;

// ── Sorting ──────────────────────────────────────────────────────────
$order_by = ( isset( $_GET['orderby'] ) && in_array( $_GET['orderby'], self::$sortable_columns, true ) )
? sanitize_text_field( $_GET['orderby'] )
: 'date';
$order    = ( isset( $_GET['order'] ) && strtolower( $_GET['order'] ) === 'asc' ) ? 'ASC' : 'DESC';

// ── Pagination ───────────────────────────────────────────────────────
$per_page     = 20;
$current_page = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
$offset       = ( $current_page - 1 ) * $per_page;

// ── Filters ──────────────────────────────────────────────────────────
$product_search = isset( $_GET['product_search'] ) ? sanitize_text_field( $_GET['product_search'] ) : '';
$action_filter  = isset( $_GET['action_filter'] )  ? sanitize_text_field( $_GET['action_filter'] )  : '';
$date_from      = isset( $_GET['date_from'] )      ? sanitize_text_field( $_GET['date_from'] )      : '';
$date_to        = isset( $_GET['date_to'] )        ? sanitize_text_field( $_GET['date_to'] )        : '';

// ── Build WHERE clause ───────────────────────────────────────────────
$where  = '1=1';
$params = [];

if ( $product_search !== '' ) {
$like     = '%' . $wpdb->esc_like( $product_search ) . '%';
$where   .= ' AND (product_name LIKE %s OR sku LIKE %s)';
$params[] = $like;
$params[] = $like;
}

if ( $action_filter !== '' ) {
if ( $action_filter === 'manual' ) {
$where .= " AND action = 'manual'";
} else {
$where   .= ' AND action LIKE %s';
$params[] = $wpdb->esc_like( $action_filter ) . '_%';
}
}

if ( $date_from !== '' ) {
$where   .= ' AND date >= %s';
$params[] = $date_from . ' 00:00:00';
}

if ( $date_to !== '' ) {
$where   .= ' AND date <= %s';
$params[] = $date_to . ' 23:59:59';
}

// ── Counts & data ────────────────────────────────────────────────────
// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
$count_sql     = "SELECT COUNT(*) FROM {$table} WHERE {$where}";
$total_records = empty( $params )
? $wpdb->get_var( $count_sql ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
: $wpdb->get_var( $wpdb->prepare( $count_sql, ...$params ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

$total_pages = (int) ceil( $total_records / $per_page );

// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
$data_sql    = "SELECT * FROM {$table} WHERE {$where} ORDER BY {$order_by} {$order} LIMIT %d OFFSET %d";
$data_params = array_merge( $params, [ $per_page, $offset ] );
$logs        = $wpdb->get_results( $wpdb->prepare( $data_sql, ...$data_params ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

// Extra args threaded through sort / pagination links.
$extra_args = array_filter( [
'product_search' => $product_search,
'action_filter'  => $action_filter,
'date_from'      => $date_from,
'date_to'        => $date_to,
] );

// ── Render ───────────────────────────────────────────────────────────
echo '<div class="wrap">';
echo '<h1>' . esc_html__( 'Simple Inventory Log', 'simple-inventory-log' ) . '</h1>';

$this->render_filters( $product_search, $action_filter, $date_from, $date_to );

$export_url = wp_nonce_url(
admin_url( 'admin-post.php?action=export_inventory_log' ),
'export_inventory_log'
);
echo '<div class="tablenav top">';
echo '<div class="alignleft actions">';
echo '<a href="' . esc_url( $export_url ) . '" class="button button-primary">' . esc_html__( 'Export to CSV', 'simple-inventory-log' ) . '</a>';
echo '</div><div class="clear"></div></div>';

if ( empty( $logs ) ) {
echo '<p>' . esc_html__( 'No logs found.', 'simple-inventory-log' ) . '</p>';
} else {
$toggle_order = ( $order === 'ASC' ) ? 'desc' : 'asc';

echo '<table class="widefat fixed striped sil-table">';
echo '<thead><tr>';

foreach ( self::$columns as $col_key => $col_label ) {
if ( in_array( $col_key, self::$sortable_columns, true ) ) {
$is_active = ( $order_by === $col_key );
$col_order = $is_active ? $toggle_order : 'desc';
$th_class  = $is_active ? 'sorted sorted-' . strtolower( $order ) : 'sortable';
$sort_url  = esc_url( add_query_arg(
array_merge(
$extra_args,
[ 'page' => 'simple-inventory-log', 'orderby' => $col_key, 'order' => $col_order ]
),
admin_url( 'admin.php' )
) );
echo '<th class="' . esc_attr( $th_class ) . '"><a href="' . $sort_url . '">' . esc_html( $col_label ) . '</a></th>';
} else {
echo '<th>' . esc_html( $col_label ) . '</th>';
}
}

echo '</tr></thead><tbody>';

foreach ( $logs as $log ) {
echo '<tr>';
foreach ( self::$columns as $col_key => $col_label ) {
$value = isset( $log[ $col_key ] ) ? $log[ $col_key ] : '';
if ( $col_key === 'stock_change' ) {
$css     = ( $value > 0 ) ? 'sil-stock-positive' : ( ( $value < 0 ) ? 'sil-stock-negative' : '' );
$display = ( $value > 0 ) ? '+' . $value : $value;
echo '<td class="' . esc_attr( $css ) . '">' . esc_html( $display ) . '</td>';
} else {
echo '<td>' . esc_html( $value ) . '</td>';
}
}
echo '</tr>';
}

echo '</tbody></table>';

$this->render_pagination( $current_page, $total_pages, $extra_args );
}

echo '</div>'; // .wrap
}

// ── Filter form ──────────────────────────────────────────────────────────

private function render_filters( $product_search, $action_filter, $date_from, $date_to ) {
$has_filters = ( $product_search !== '' || $action_filter !== '' || $date_from !== '' || $date_to !== '' );
?>
<form method="get" action="" class="sil-filters">
<input type="hidden" name="page" value="simple-inventory-log" />

<label>
<?php esc_html_e( 'Product', 'simple-inventory-log' ); ?>
<input type="text" name="product_search"
value="<?php echo esc_attr( $product_search ); ?>"
placeholder="<?php esc_attr_e( 'Name or SKU', 'simple-inventory-log' ); ?>" />
</label>

<label>
<?php esc_html_e( 'Action', 'simple-inventory-log' ); ?>
<select name="action_filter">
<option value=""><?php esc_html_e( 'All actions', 'simple-inventory-log' ); ?></option>
<option value="manual"    <?php selected( $action_filter, 'manual' ); ?>><?php esc_html_e( 'Manual', 'simple-inventory-log' ); ?></option>
<option value="order"     <?php selected( $action_filter, 'order' ); ?>><?php esc_html_e( 'Order', 'simple-inventory-log' ); ?></option>
<option value="refund"    <?php selected( $action_filter, 'refund' ); ?>><?php esc_html_e( 'Refund', 'simple-inventory-log' ); ?></option>
<option value="cancelled" <?php selected( $action_filter, 'cancelled' ); ?>><?php esc_html_e( 'Cancelled', 'simple-inventory-log' ); ?></option>
</select>
</label>

<label>
<?php esc_html_e( 'From', 'simple-inventory-log' ); ?>
<input type="date" name="date_from" value="<?php echo esc_attr( $date_from ); ?>" />
</label>

<label>
<?php esc_html_e( 'To', 'simple-inventory-log' ); ?>
<input type="date" name="date_to" value="<?php echo esc_attr( $date_to ); ?>" />
</label>

<button type="submit" class="button"><?php esc_html_e( 'Filter', 'simple-inventory-log' ); ?></button>

<?php if ( $has_filters ) : ?>
<a href="<?php echo esc_url( admin_url( 'admin.php?page=simple-inventory-log' ) ); ?>" class="button">
<?php esc_html_e( 'Reset', 'simple-inventory-log' ); ?>
</a>
<?php endif; ?>
</form>
<?php
}

// ── Pagination ───────────────────────────────────────────────────────────

public function render_pagination( $current_page, $total_pages, $extra_args = [] ) {
if ( $total_pages <= 1 ) {
return;
}

$base_url = add_query_arg(
array_merge( $extra_args, [ 'page' => 'simple-inventory-log' ] ),
admin_url( 'admin.php' )
);

echo '<div class="tablenav"><div class="sil-pagination">';

if ( $current_page > 1 ) {
echo '<a class="button" href="' . esc_url( add_query_arg( 'paged', 1, $base_url ) ) . '">&laquo; ' . esc_html__( 'First', 'simple-inventory-log' ) . '</a>';
echo '<a class="button" href="' . esc_url( add_query_arg( 'paged', $current_page - 1, $base_url ) ) . '">' . esc_html__( 'Prev', 'simple-inventory-log' ) . '</a>';
}

$max_pages  = 10;
$half_range = (int) floor( $max_pages / 2 );
$start_page = max( 1, $current_page - $half_range );
$end_page   = min( $total_pages, $start_page + $max_pages - 1 );

if ( $end_page - $start_page + 1 < $max_pages ) {
$start_page = max( 1, $end_page - $max_pages + 1 );
}

if ( $start_page > 1 ) {
echo '<span class="ellipsis">&hellip;</span>';
}

for ( $i = $start_page; $i <= $end_page; $i++ ) {
$btn_class = ( $i === $current_page ) ? 'button button-primary' : 'button';
echo '<a class="' . esc_attr( $btn_class ) . '" href="' . esc_url( add_query_arg( 'paged', $i, $base_url ) ) . '">' . esc_html( $i ) . '</a>';
}

if ( $end_page < $total_pages ) {
echo '<span class="ellipsis">&hellip;</span>';
}

if ( $current_page < $total_pages ) {
echo '<a class="button" href="' . esc_url( add_query_arg( 'paged', $current_page + 1, $base_url ) ) . '">' . esc_html__( 'Next', 'simple-inventory-log' ) . '</a>';
echo '<a class="button" href="' . esc_url( add_query_arg( 'paged', $total_pages, $base_url ) ) . '">' . esc_html__( 'Last', 'simple-inventory-log' ) . ' &raquo;</a>';
}

echo '</div></div>';
}

// ── Table name helper ─────────────────────────────────────────────────────

public static function get_table_name() {
return self::$table_name;
}

// ── Stock-change logging ──────────────────────────────────────────────────

public static function log_stock_change( $product_id, $change, $new_stock, $action = '', $note = '' ) {
global $wpdb;

$product      = wc_get_product( $product_id );
$product_name = $product ? $product->get_name() : '';
$sku          = $product ? $product->get_sku()  : '';

$current_user_id = get_current_user_id();
if ( $current_user_id ) {
$user      = get_userdata( $current_user_id );
$user_name = $user ? $user->display_name . ' (' . $user->user_email . ')' : '';
} else {
$user_name       = 'System';
$current_user_id = null;
}

$wpdb->insert(
self::$table_name,
[
'product_id'   => $product_id,
'product_name' => $product_name,
'sku'          => $sku,
'stock_change' => $change,
'stock'        => $new_stock,
'action'       => $action,
'relation'     => $user_name,
'user_id'      => $current_user_id,
'note'         => $note,
'date'         => current_time( 'mysql' ),
],
[ '%d', '%s', '%s', '%f', '%f', '%s', '%s', '%d', '%s', '%s' ]
);
}

// ── Hooks into WooCommerce stock changes ──────────────────────────────────

public static function hook_into_stock_changes() {
// Simple products
add_action( 'woocommerce_product_before_set_stock', [ __CLASS__, 'before_handle_stock_change' ], 10, 1 );
add_action( 'woocommerce_product_set_stock',        [ __CLASS__, 'handle_stock_change' ],        10, 1 );

// Variation products
add_action( 'woocommerce_variation_before_set_stock', [ __CLASS__, 'before_handle_stock_change' ], 10, 1 );
add_action( 'woocommerce_variation_set_stock',        [ __CLASS__, 'handle_stock_change' ],        10, 1 );

// Fallback: direct _stock meta updates (e.g. quick-edit, bulk-edit, imports, REST API)
add_action( 'update_post_metadata', [ __CLASS__, 'handle_stock_change_meta_transient' ], 10, 5 );
add_action( 'updated_post_meta',    [ __CLASS__, 'handle_stock_change_meta' ],           10, 4 );

// Action-type tagging
add_action( 'woocommerce_checkout_order_created', [ __CLASS__, 'handle_stock_change_action_order' ],     10, 1 );
add_filter( 'woocommerce_create_refund',          [ __CLASS__, 'handle_stock_change_action_refund' ],    10, 2 );
add_action( 'woocommerce_order_status_cancelled', [ __CLASS__, 'handle_stock_change_action_cancelled' ], 9,  1 );
}

// ── Stock change handlers ─────────────────────────────────────────────────

/**
 * Cache the current stock before a WooCommerce-triggered change so we can
 * compute the delta afterwards.
 */
public static function before_handle_stock_change( $product ) {
$product_id = $product->get_id();
$user_id    = get_current_user_id();
$old_stock  = floatval( get_post_meta( $product_id, '_stock', true ) );

set_transient( "sil_old_stock_{$product_id}_{$user_id}", $old_stock, 5 * MINUTE_IN_SECONDS );
}

/**
 * Called after WooCommerce has updated the stock value.
 */
public static function handle_stock_change( $product ) {
$product_id = $product->get_id();
$user_id    = get_current_user_id();
$new_stock  = $product->get_stock_quantity();

$old_stock = get_transient( "sil_old_stock_{$product_id}_{$user_id}" );
delete_transient( "sil_old_stock_{$product_id}_{$user_id}" );

// Consume the action from the per-product FIFO queue.
$action    = 'manual';
$queue_key = "sil_stock_action_queue_{$product_id}";
$queue     = get_transient( $queue_key );

if ( is_array( $queue ) && count( $queue ) > 0 ) {
$action = array_shift( $queue );
set_transient( $queue_key, $queue, 10 * MINUTE_IN_SECONDS );
}

if ( false === $old_stock ) {
// No baseline recorded – skip to avoid duplicate / spurious logging.
return;
}

$change = $new_stock - (float) $old_stock;

if ( 0.0 !== $change ) {
self::log_stock_change( $product_id, $change, $new_stock, $action );
}
}

/**
 * Fallback: capture old stock before a direct _stock meta update.
 * Fires on the `update_post_metadata` filter (before the write).
 */
public static function handle_stock_change_meta_transient( $check, $object_id, $meta_key, $meta_value, $prev_value ) {
if ( '_stock' !== $meta_key ) {
return $check;
}

$product = wc_get_product( $object_id );
if ( ! $product ) {
return $check;
}

$user_id   = get_current_user_id();
$old_stock = floatval( get_post_meta( $object_id, '_stock', true ) );
set_transient( "sil_old_stock_{$object_id}_{$user_id}", $old_stock, 5 * MINUTE_IN_SECONDS );

return $check;
}

/**
 * Fallback: log after a direct _stock meta update.
 * Fires on the `updated_post_meta` action (after the write).
 */
public static function handle_stock_change_meta( $meta_id, $object_id, $meta_key, $meta_value ) {
if ( '_stock' !== $meta_key ) {
return;
}

$product = wc_get_product( $object_id );
if ( ! $product ) {
return;
}

self::handle_stock_change( $product );
}

// ── Action-type tagging ───────────────────────────────────────────────────

/**
 * Tag upcoming stock reductions with the order ID so logs show "order_XXX".
 */
public static function handle_stock_change_action_order( $order ) {
foreach ( $order->get_items() as $item ) {
$product = $item->get_product();
if ( ! $product ) {
continue;
}

$product_id = $product->get_id();
$order_id   = $order->get_id();
$queue_key  = "sil_stock_action_queue_{$product_id}";
$queue      = get_transient( $queue_key );

if ( ! is_array( $queue ) ) {
$queue = [];
}

$queue[] = "order_{$order_id}";
set_transient( $queue_key, $queue, 10 * MINUTE_IN_SECONDS );
}
}

/**
 * Tag upcoming stock restorations with the refund ID so logs show "refund_XXX".
 *
 * Hooked as a filter on `woocommerce_create_refund`, which fires inside
 * wc_create_refund() BEFORE wc_increase_stock_levels() is called.
 *
 * @param WC_Order_Refund|WP_Error $refund Refund object (may not have an ID yet).
 * @param array                    $args   Arguments passed to wc_create_refund().
 * @return WC_Order_Refund|WP_Error Unmodified $refund (required by filter).
 */
public static function handle_stock_change_action_refund( $refund, $args ) {
if ( empty( $args['restock_items'] ) || empty( $args['line_items'] ) ) {
return $refund;
}

$order_id = ! empty( $args['order_id'] ) ? (int) $args['order_id'] : 0;
$order    = $order_id ? wc_get_order( $order_id ) : null;

if ( ! $order ) {
return $refund;
}

$refund_id = ( is_object( $refund ) && is_callable( [ $refund, 'get_id' ] ) ) ? (int) $refund->get_id() : 0;
$tag       = 'refund_' . ( $refund_id ?: $order_id );

foreach ( $args['line_items'] as $item_id => $item_data ) {
$item = $order->get_item( $item_id );
if ( ! $item || ! is_callable( [ $item, 'get_product' ] ) ) {
continue;
}

$product = $item->get_product();
if ( ! $product ) {
continue;
}

$product_id = $product->get_id();
$queue_key  = "sil_stock_action_queue_{$product_id}";
$queue      = get_transient( $queue_key );

if ( ! is_array( $queue ) ) {
$queue = [];
}

$queue[] = $tag;
set_transient( $queue_key, $queue, 10 * MINUTE_IN_SECONDS );
}

return $refund;
}

/**
 * Tag upcoming stock restorations so logs show "cancelled_XXX".
 *
 * Runs at priority 9, before WooCommerce restores stock at priority 10.
 */
public static function handle_stock_change_action_cancelled( $order_id ) {
$order = wc_get_order( $order_id );
if ( ! $order ) {
return;
}

foreach ( $order->get_items() as $item ) {
if ( ! is_callable( [ $item, 'get_product' ] ) ) {
continue;
}

$product = $item->get_product();
if ( ! $product || ! $product->managing_stock() ) {
continue;
}

$product_id = $product->get_id();
$queue_key  = "sil_stock_action_queue_{$product_id}";
$queue      = get_transient( $queue_key );

if ( ! is_array( $queue ) ) {
$queue = [];
}

$queue[] = "cancelled_{$order_id}";
set_transient( $queue_key, $queue, 10 * MINUTE_IN_SECONDS );
}
}

// ── CSV export ────────────────────────────────────────────────────────────

public function export_inventory_log() {
if ( ! current_user_can( 'manage_options' ) ) {
wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'simple-inventory-log' ) );
}

check_admin_referer( 'export_inventory_log' );

global $wpdb;
$table = self::$table_name;
// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
$logs = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY date DESC", ARRAY_A );

if ( empty( $logs ) ) {
wp_redirect( admin_url( 'admin.php?page=simple-inventory-log&export_status=empty' ) );
exit;
}

header( 'Content-Type: text/csv; charset=utf-8' );
header( 'Content-Disposition: attachment; filename="inventory_log_' . gmdate( 'Y-m-d' ) . '.csv"' );
header( 'Pragma: no-cache' );
header( 'Expires: 0' );

$output = fopen( 'php://output', 'w' );

fputcsv( $output, [ 'ID', 'Product ID', 'Product Name', 'SKU', 'Date', 'Stock Change', 'Stock', 'Action', 'User', 'User ID', 'Note' ] );

foreach ( $logs as $log ) {
fputcsv( $output, [
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
$log['note'],
] );
}

fclose( $output );
exit;
}
}
