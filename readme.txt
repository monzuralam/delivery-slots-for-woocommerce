=== Delivery Slots for WooCommerce ===
Contributors: monzuralam
Tags: woocommerce, delivery, delivery slots, checkout, booking
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/old-licenses/gpl-2.0.html

Let customers pick a priced, capacity-limited delivery date and time at WooCommerce checkout, and manage those slots from wp-admin.

== Description ==

Delivery Slots for WooCommerce adds a delivery date & time picker to your WooCommerce checkout, and gives you a dashboard to manage the available slots — their capacity, pricing, and status — along with visibility into which orders belong to each slot.

= Key features =

* **Two-step delivery picker at checkout** — customers pick a date, then a time slot for that date. Works on both the classic (shortcode) checkout and the block-based Checkout, with live availability and pricing, and no setup required.
* **Per-slot pricing** — each slot can add its own delivery fee to the order total, applied the moment a slot is selected.
* **Overselling-safe capacity** — slot bookings are reserved with a real database transaction (row locking), so two customers can never both book the last space in a slot.
* **Automatic capacity release** — a cancelled, failed, refunded, or abandoned order automatically frees up its slot again. How long an unpaid order can hold a slot before that happens is configurable.
* **Admin dashboard** — a dashboard in wp-admin to create, edit, search, and filter delivery slots (date, time, capacity, price, status).
* **Settings page** — enable/disable the picker, customize the checkout field labels, configure the stale-hold window, and export/import your settings and slot definitions as JSON.
* **Order ↔ slot visibility** — see every order booked into a slot from the dashboard, and see each order's delivery slot right in the WooCommerce Orders list (works with both HPOS and legacy order storage) and on the order details/emails.
* **Optional cleanup on uninstall** — choose whether removing the plugin also deletes its slots, bookings, and settings (off by default).

= Requirements =

* WordPress 6.0 or newer
* WooCommerce, active
* PHP 7.4 or newer

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/delivery-slots-for-woocommerce`, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the "Plugins" screen in WordPress.
3. Go to **Delivery Slots for WooCommerce** in the wp-admin menu to create your first delivery slot.
4. Visit your store's checkout page to see the delivery date/time picker.
5. Visit **Delivery Slots for WooCommerce > Settings** to enable/disable the picker, customize checkout labels, and adjust the stale-hold window.

== Frequently Asked Questions ==

= What happens when a delivery slot is full? =

The slot is still shown at checkout but marked "Sold out" and can't be selected until capacity frees up.

= What happens if an order with a booked slot is cancelled or refunded? =

The slot's booked count is automatically decreased, freeing up the space for other customers.

= What if a customer never completes payment? =

An hourly background task releases the slot automatically once the order has been unpaid for longer than the configurable stale-hold window (default: 6 hours).

= Can I lower a slot's capacity below the number of orders already booked into it? =

No — the Dashboard blocks this to prevent overbooking. Cancel or reassign the existing orders first.

= Can I delete a slot that already has bookings? =

No — slots with active bookings can't be deleted. Set the slot's status to "Inactive" or "Closed" instead.

= Does this work with both the classic and block checkout? =

Yes — the delivery date/time picker supports both, and pricing/capacity enforcement works identically either way.

= Does this support WooCommerce's High-Performance Order Storage (HPOS)? =

Yes — order lookups and the Orders list column work whether HPOS is enabled or not.

= Does uninstalling the plugin delete my slots and settings? =

Only if you turn on "Delete data on uninstall" in Settings > Tools. It's off by default, so removing the plugin never destroys your data unless you explicitly opt in.

== Screenshots ==

1. The delivery date & time picker at checkout.
2. The admin dashboard for managing delivery slots.
3. The Settings page.

== Changelog ==

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.0 =
Initial release.
