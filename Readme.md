# Delivery Slots for WooCommerce

Let customers pick a priced, capacity-limited delivery date and time at checkout, and give store admins a dashboard to manage those slots and see which orders belong to each one.

## Features

- 📅 **Two-step delivery picker at checkout** — pick a date, then a time slot for that date — supporting both the classic (shortcode) checkout and the block-based Checkout, with live availability and pricing. The whole feature can be turned off from Settings without touching code.
- 💰 **Per-slot pricing** — each slot can add its own delivery fee to the order total, updated live as the customer picks a slot.
- 🔒 **Overselling-safe capacity** — slot bookings are reserved with a real database transaction (row locking), so two customers can never both book the last space in a slot. See [Technical decision: preventing overbooked slots](#technical-decision-preventing-overbooked-slots).
- ♻️ **Automatic capacity release** — a cancelled, failed, refunded, or abandoned order automatically frees up its slot again; how long an unpaid order can hold a slot before that happens is configurable.
- 🛠️ **Admin dashboard** — a React-based screen in wp-admin to create, edit, search, and filter delivery slots (date, time, capacity, price, status).
- ⚙️ **Settings page** — General tab for plugin behavior (enable/disable, field label, stale-hold hours) and a Tools tab to export/import settings and slot definitions as JSON.
- 📋 **Order ↔ slot visibility** — see every order booked into a slot from the dashboard, and see each order's delivery slot right in the WooCommerce Orders list (works with both HPOS and legacy order storage) and on the order details/emails.
- ⚛️ Admin dashboard, Settings, and checkout UI are all React under the hood.

## Requirements

- WordPress 6.0+
- WooCommerce (active)
- PHP 7.4+

## Project Structure

```
├── 📁 assets/
│   ├── build/            (webpack output: dashboard.js/css, getting-started.js, settings.js/css — run `npm run build` first)
│   ├── checkout/         (hand-written checkout JS/CSS — classic + block checkout pickers)
│   └── vendor/           (self-hosted SweetAlert2, used by the admin screens)
├── 📁 includes/                    (PHP, namespace DSW\)
│   ├── 📁 Admin/                   (Menu.php — admin menu pages; Ajax.php — Settings save/export/import)
│   ├── 📁 Frontend/                (checkout integration, order hooks, blocks integration)
│   ├── 📁 Rest/                    (REST API: admin slot CRUD + public availability endpoint)
│   ├── 🐘 SlotManager.php          (the concurrency-safe reserve/release logic; owns wp_dsw_slots/wp_dsw_bookings)
│   ├── 🐘 Install.php              (creates the DB tables + default options on activation)
│   ├── 🐘 Cron.php                 (releases stale unpaid holds)
│   ├── 🐘 functions.php            (global helpers, e.g. `dsw_get_settings()`; files-autoloaded)
│   └── 🐘 Enqueue.php / Hooks.php
├── 📁 src/                         (JS/React source, built by webpack)
│   ├── 📁 js/Dashboard/            (the slots dashboard React app)
│   ├── 📁 js/GettingStarted/       (the Getting Started React app)
│   ├── 📁 js/Settings/             (the Settings React app — Context API for settings state)
│   └── 📁 css/                     (dashboard.scss, settings.scss — each compiles to its own build/*.css)
├── 📄 composer.json
├── 📄 package.json
└── 🐘 delivery-slots-for-woocommerce.php   (plugin entry point)
```

## Architecture

- **Data layer — `SlotManager`**: the only code path that reads or writes `wp_dsw_slots` and `wp_dsw_bookings`. Both tables are created with `ENGINE=InnoDB` directly in `Install.php` (not `dbDelta()`, which doesn't reliably honor `ENGINE=`), because capacity reservation depends on real transactional row locking.
- **Settings** live in a single `dsw_settings` option, read anywhere via the global `dsw_get_settings($key = null, $default = null)` helper (`includes/functions.php`) — call it with no arguments for the full settings array, or a key for one value with a fallback. Settings are written only from `includes/Admin/Ajax.php` (the Settings page's save/import handlers), so there's one place that ever sanitizes and persists them.
- **REST API** (`dsw/v1` namespace, `includes/Rest/`): `SlotsController` is the `manage_options`-gated admin CRUD the Dashboard app calls; `PublicSlotsController` is the public, read-only endpoint the checkout picker polls for availability. Neither is trusted for the actual booking decision — that's always `SlotManager::reserve_slot()`, enforced server-side when the order is placed.
- **Settings AJAX** (`includes/Admin/Ajax.php`, classic `admin-ajax.php` actions rather than REST): saving settings, and exporting/importing settings + slot definitions as a JSON file. Export uses a real server-driven file download (`Content-Disposition: attachment`) instead of a REST response, and import always treats slots with active bookings as untouchable — see [Technical decision: preventing overbooked slots](#technical-decision-preventing-overbooked-slots) for why booking rows are never part of import.
- **Checkout integration** (`includes/Frontend/Checkout.php` + `BlocksIntegration.php`): supports both the classic (shortcode) checkout and the block-based Checkout, because a store can use either. Both paths funnel into the same `SlotManager::reserve_slot()` call when an order is placed. The entire feature is gated behind `dsw_get_settings('enable_dsw', true)` — when disabled, the picker isn't rendered on either checkout, its assets aren't enqueued, and the order-processed hooks skip capacity enforcement entirely so checkout keeps working normally without a slot.
- **Admin screens** (`includes/Admin/Menu.php`, three React apps under `src/js/`): **Dashboard** (slot CRUD, SweetAlert2 forms), **Settings** (General + Tools), and **Getting Started** (onboarding). Each has its own webpack entry and its own `window.dsw` payload localized per-page from `includes/Enqueue.php`.

### Technical decision: preventing overbooked slots

Two customers can hit "Place order" for the same last-available slot at the same instant. Naively checking "is there room?" then inserting a booking has a race: both requests can read "1 space left" before either one writes, and both proceed.

`SlotManager::reserve_slot()` closes that window with a single InnoDB transaction:

```sql
START TRANSACTION;
SELECT booked, capacity FROM wp_dsw_slots WHERE id = ? FOR UPDATE;
-- if booked >= capacity: ROLLBACK, return an error
UPDATE wp_dsw_slots SET booked = booked + 1 WHERE id = ? AND booked < capacity;
INSERT INTO wp_dsw_bookings (slot_id, order_id, status='reserved');
COMMIT;
```

`SELECT ... FOR UPDATE` takes an exclusive lock on that slot's row, so a second request's own `SELECT ... FOR UPDATE` blocks until the first transaction commits or rolls back — there's no window where both requests can see "1 space left" and both book it. The `UPDATE ... WHERE booked < capacity` guard is a second, belt-and-braces check against the same race. This is also why the tables must be `InnoDB` (row-level locking) and created directly rather than via `dbDelta()`.

Because classic and block checkout fire different "order processed" hooks (`woocommerce_checkout_order_processed` vs. `woocommerce_store_api_checkout_order_processed` — confirmed by reading WooCommerce's own source, not assumed), both are wired to the same `reserve_slot_for_order()` in `Checkout.php`; missing either one would let that checkout path skip capacity enforcement entirely. Reservation failure throws `WC_Data_Exception`, which WooCommerce surfaces as an inline checkout error and leaves no capacity taken.

Capacity is released the same way it's taken — through `SlotManager::release_slot()` — on cancellation, failure, refund, trash/delete, or an hourly cron sweep for orders left unpaid past the configurable stale-hold window (Settings > General).

Restoring a trashed order (`OrderHooks::handle_restored()`, hooked to `woocommerce_untrash_order`) attempts to re-reserve the same slot via `SlotManager::reserve_slot()` if the order comes back into a holding status. This can fail if the slot filled up (or was closed) while the order sat in the trash — in that case the restore itself still succeeds, but an order note is added ("please assign a new slot") instead of silently leaving the order without one.

## Database Structure

Created directly in `Install.php::create_tables()` on activation (`ENGINE=InnoDB`, not `dbDelta()` — see [above](#technical-decision-preventing-overbooked-slots)). All reads/writes go through `SlotManager`.

**`wp_dsw_slots`** — one row per bookable slot:

| Column       | Type                | Notes                                    |
|--------------|---------------------|-------------------------------------------|
| `id`         | `BIGINT UNSIGNED`   | Primary key, auto-increment                |
| `slot_date`  | `DATE`              | Indexed                                    |
| `start_time` | `TIME`              |                                             |
| `end_time`   | `TIME`              |                                             |
| `capacity`   | `INT UNSIGNED`      | Default `0`                                |
| `booked`     | `INT UNSIGNED`      | Default `0`; never exceeds `capacity`      |
| `price`      | `DECIMAL(10,2)`     | Default `0.00`                             |
| `status`     | `VARCHAR(20)`       | `active` / `inactive` / `full` / `closed`; indexed |
| `created_at` | `DATETIME`          | `CURRENT_TIMESTAMP`                        |
| `updated_at` | `DATETIME`          | `CURRENT_TIMESTAMP ON UPDATE`              |

**`wp_dsw_bookings`** — one row per order↔slot link:

| Column      | Type              | Notes                                          |
|-------------|-------------------|--------------------------------------------------|
| `id`        | `BIGINT UNSIGNED` | Primary key, auto-increment                       |
| `slot_id`   | `BIGINT UNSIGNED` | Indexed; no FK constraint (enforced in code)      |
| `order_id`  | `BIGINT UNSIGNED` | **Unique** — one booking per order                |
| `status`    | `VARCHAR(20)`     | `reserved` / `released`; indexed                  |
| `created_at`| `DATETIME`        | `CURRENT_TIMESTAMP`                               |
| `updated_at`| `DATETIME`        | `CURRENT_TIMESTAMP ON UPDATE`                     |

The `order_id` unique key is what makes `SlotManager::reserve_slot()` idempotent for a given order (re-processing the same order updates its existing booking row instead of creating a duplicate).

**Plugin settings** live outside these tables, in a single `dsw_settings` option (`wp_options`), read via `dsw_get_settings()` (`includes/functions.php`) — see [Architecture](#architecture).

## Security

- **Capability checks** — every admin-only entry point (the `SlotsController` REST routes and the Settings `Ajax` handlers) requires `current_user_can('manage_options')`. The one public REST route, `GET /dsw/v1/slots/available`, is intentionally read-only and returns nothing beyond slot date/time/price/remaining-capacity — no order, customer, or booking data.
- **Nonces, scoped per purpose** — the admin REST calls use the standard `wp_rest` nonce; the Settings page's save/export/import AJAX actions use their own `dsw_settings` nonce; the checkout picker's AJAX selection endpoint uses its own `dsw_select_slot` nonce. None of these are interchangeable — a nonce for one purpose is rejected by the others.
- **Input handling** — REST route args declare `sanitize_callback`/`validate_callback` (e.g. slot dates are regex-validated, capacity/price are cast with `absint()`/`(float)`), and `$_POST`/`$_GET` values are run through `wp_unslash()` + `sanitize_text_field()`/`sanitize_key()`/`absint()` before use.
- **SQL** — every query that includes a user-supplied value uses `$wpdb->prepare()` with placeholders (slot CRUD, the availability endpoint, the duplicate-slot conflict check). The few queries built with a dynamic table name (from `SlotManager::slots_table()`/`bookings_table()`, never user input) either escape it with `esc_sql()` plus backticks (`uninstall.php`) or are marked `// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared` with the reason, since identifiers can't go through a `%s`/`%d` placeholder.
- **Output escaping** — `esc_html()`/`esc_html__()`/`esc_attr()`/`esc_url_raw()` wrap dynamic values wherever they're echoed into HTML or attributes, including error messages passed into thrown exceptions (`esc_html($result->get_error_message())` in `Checkout.php`).
- **No trusted client input for the actual booking decision** — the public availability endpoint and the client-side pickers are purely informational; capacity is only ever reserved through `SlotManager::reserve_slot()`, re-verified server-side at order-processing time regardless of what the client last displayed.
- **Direct file access is blocked** — every PHP file starts with `if (! defined('ABSPATH')) exit;`, and `uninstall.php` additionally requires `defined('WP_UNINSTALL_PLUGIN')`.
- **Uninstall is non-destructive by default** — dropping the plugin's tables and options only happens if "Delete data on uninstall" (Settings > Tools) has been explicitly turned on; the default is off.

## Getting Started

### Prerequisites

- WordPress with WooCommerce active (local or remote)
- Node.js & npm
- Composer

### Installation

1. Clone (or copy) this plugin into your WordPress `wp-content/plugins/` directory.

2. Install dependencies:
    ```bash
    cd delivery-slots-for-woocommerce
    npm install
    composer install
    ```

3. Build the admin dashboard assets:
    ```bash
    npm run build
    ```
    Use `npm start` instead for a dev build with watch mode. The `assets/checkout/*.js` files are plain, hand-written scripts and don't need a build step.

4. Activate the plugin from the WordPress admin dashboard (this creates the plugin's database tables).

5. Go to **Delivery Slots for WooCommerce** in the wp-admin menu to create your first delivery slot, then visit checkout to see the delivery date/time picker.

## Contributing

1. Fork the repository and create a feature branch.
2. Make your changes, then run `npm run build` and `php -l` on any touched files.
3. Commit with a clear message and open a Pull Request.
