=== Simple Inventory Log ===
Contributors:      tobiaszimmermann1
Tags:              woocommerce, inventory, stock, log, audit
Requires at least: 5.8
Tested up to:      6.7
Requires PHP:      7.4
Stable tag:        1.0.0
License:           GPL v2 or later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html

Logs every WooCommerce stock change in a simple, filterable admin list.

== Description ==

Simple Inventory Log records every stock change for your WooCommerce products
and variations in one place.  Each entry captures:

* **What changed** – product name, SKU, and the numeric change (+/−)
* **When** – date and time of the change
* **Why** – the trigger: manual edit, a specific order, a refund, or a
  cancelled order
* **Who** – the WordPress user (or "System" for automated processes)

=== Features ===

* Tracks **all** stock changes: manual edits in the WooCommerce product screen
  (including quick-edit and bulk-edit), order placement, refund restocking,
  and order cancellation restocking.
* Works with both **simple products** and **product variations**.
* **Filter** the log by product name / SKU, action type (manual, order,
  refund, cancelled), and date range.
* **Sort** by any column.
* **Export** the full log to CSV.
* Cleans up its database table when the plugin is uninstalled.

== Installation ==

1. Upload the `simple-inventory-log` folder to `/wp-content/plugins/`.
2. Activate the plugin via the **Plugins** screen in WordPress.
3. Visit **Inventory Log** in the WordPress admin sidebar.

== Frequently Asked Questions ==

= Does it require WooCommerce? =

Yes – the plugin hooks into WooCommerce stock events and will not do anything
useful without WooCommerce active.

= Will my existing log data be lost when I deactivate the plugin? =

No. Data is only removed when you *uninstall* (delete) the plugin from the
Plugins screen.

= Can I export the log? =

Yes. Click **Export to CSV** at the top of the Inventory Log page.

== Screenshots ==

1. The Inventory Log admin page with filters and sortable columns.

== Changelog ==

= 1.0.0 =
* Initial public release.
* Added variation product tracking.
* Added refund and order-cancellation action tagging.
* Added filter form (product, action type, date range).
* Added sortable columns with direction indicators.
* Added colour-coded stock-change column.
* Added CSV export with nonce security.
* Added `uninstall.php` to clean up data on plugin deletion.
* Full WP coding-standards pass: escaping, sanitisation, capability checks,
  text-domain support.

== Upgrade Notice ==

= 1.0.0 =
First release – no upgrade steps required.
