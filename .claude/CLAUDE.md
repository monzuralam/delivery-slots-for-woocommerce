# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project overview

WordPress plugin (`delivery-slots-for-woocommerce`) that lets customers pick a priced, capacity-limited delivery date/time at WooCommerce checkout, and lets store admins manage those slots — and see which orders belong to each — from a custom wp-admin screen. It started from a generic "WP React Starter" boilerplate (see `Readme.md`, which still describes the old generic starter, not this plugin) and has since grown a full admin dashboard, a checkout integration (classic + block checkout), and order-lifecycle capacity tracking.

Namespace: all PHP classes live under `DSW\` (PSR-4, see `composer.json`), autoloaded from `includes/`.

## Commands

JS/build tooling is `@wordpress/scripts` (wp-scripts), configured via `webpack.config.js`, which auto-picks up any lowercase-named `src/*.js` file as a webpack entry point:

- `npm start` — dev build with watch mode.
- `npm run build` — production build; outputs to `assets/build/` (`admin.js`/`admin.css`/`admin.asset.php`, `frontend.js`/`frontend.asset.php`). **Always rebuild after editing anything under `src/`** — PHP enqueues the built files, not the source.
- `npm run lint:js` / `npm run lint:css` / `npm run format` — currently `lint:js` is broken in this environment (`@typescript-eslint`/`ts-api-utils` version mismatch in `node_modules`), unrelated to any plugin code.
- `npm run plugin-zip` — package the plugin into a distributable zip.

No JS or PHP test suite is configured. PHP dependencies are managed by Composer; `composer.json` autoloads `includes/` (PSR-4) plus `includes/functions.php` (a files-autoload entry, currently empty).

Verification during development has relied on `php -l` per touched file, WP-CLI `eval` against the real site DB (this repo lives inside a live WordPress install at `D:/server/www/qcw`, reachable at `http://qcw.local`, with WP-CLI at `/c/wp-cli/wp-cli.phar`) to exercise REST routes / DB logic directly, and Claude-in-Chrome to click through the actual checkout/admin pages.

## Architecture

**Entry point** (`delivery-slots-for-woocommerce.php`): singleton `Delivery_Slots_For_WooCommerce` (`dsw()`). Declares HPOS + cart/checkout-blocks compatibility on `before_woocommerce_init`, registers activate/deactivate hooks (`DSW\Install`), and on `plugins_loaded` (gated on WooCommerce being active) instantiates, unconditionally: `DSW\Enqueue`, `DSW\Hooks`, `DSW\Rest\SlotsController`, `DSW\Rest\PublicSlotsController`, `DSW\Frontend\Checkout`, `DSW\Frontend\OrderHooks`, `DSW\Cron` — plus `DSW\Admin` when `is_admin()` and `DSW\Frontend\Shortcode` otherwise. **Note:** don't call WooCommerce helper functions like `wc_get_page_screen_id()` directly from a constructor run during `plugins_loaded` — WooCommerce's own `plugins_loaded` handler that defines them isn't guaranteed to have run first depending on plugin load order (alphabetical). Defer such calls to a later hook (`admin_menu` is used for this in `OrderHooks`).

**Data layer — `DSW\SlotManager`** (`includes/SlotManager.php`): all reads/writes to the two custom tables go through this class.
- `wp_dsw_slots` — one row per bookable slot (`slot_date`, `start_time`, `end_time`, `capacity`, `booked`, `price`, `status`: active/inactive/full/closed).
- `wp_dsw_bookings` — order↔slot links (`slot_id`, `order_id`, `status`: reserved/released), unique on `order_id`.
- Both tables are created directly with `ENGINE=InnoDB` in `Install::create_tables()` (**not** `dbDelta()`, which doesn't reliably honor `ENGINE=`) because `SlotManager::reserve_slot()` depends on real transactional row locking.
- `reserve_slot($slot_id, $order_id)` / `release_slot($order_id)`: the concurrency-safe core of the whole feature — `START TRANSACTION` + `SELECT ... FOR UPDATE` + a conditional `UPDATE ... WHERE booked < capacity`, so two simultaneous checkouts for the last space in a slot can't both succeed. Read the method's own doc comment before touching it.

**REST API** (namespace `dsw/v1`):
- `includes/Rest/SlotsController.php` — admin CRUD (`manage_options`-gated): `GET/POST /slots`, `GET /slots/stats`, `PUT/DELETE /slots/{id}`, `GET /slots/{id}/orders` (orders booked into a slot, for the admin dashboard's "View orders" action). Update/delete block on existing bookings (can't shrink capacity below `booked`, can't delete a slot that has bookings).
- `includes/Rest/PublicSlotsController.php` — public, read-only `GET /slots/available` that the checkout-page JS polls. Sold-out slots are included but flagged `available: false`, not hidden, so the list doesn't jump around while browsing. Nothing this endpoint returns is trusted for the actual booking decision — that's always `SlotManager::reserve_slot()` server-side.

**Checkout integration** (`includes/Frontend/`): the store's live checkout uses **block checkout** (confirmed via WP-CLI against the actual `checkout` page content), so both paths matter and are exercised, not just implemented speculatively.
- `Checkout.php` — classic-checkout field rendering/validation (`woocommerce_after_order_notes` etc.), the cart-fee hook (`apply_slot_fee`, reads the selected slot from `WC()->session`), the `wp_ajax_dsw_select_slot` handler classic checkout's JS calls on change, and `register_cart_update_callback()` (registers a `woocommerce_store_api_register_update_callback` handler for namespace `dsw` — **this is what makes block-checkout pricing update live**; without it, `extensionCartUpdate()` calls from the client never reach `WC()->session`). The capacity-reservation enforcement point is split in two because **classic and block checkout fire different "order processed" hooks** (`woocommerce_checkout_order_processed` vs `woocommerce_store_api_checkout_order_processed` — confirmed by reading WooCommerce's own source, not assumed) — both are hooked, both funnel into the same private `reserve_slot_for_order()`, which throws `WC_Data_Exception` on failure (WooCommerce catches this and shows an inline checkout error, leaving no capacity taken).
- `BlocksIntegration.php` — `IntegrationInterface` implementation registering `assets/checkout/blocks-checkout.js` for the block checkout.
- `OrderHooks.php` — capacity lifecycle on order status change/trash/restore/delete (via `SlotManager::HOLDING_STATUSES`), customer-facing slot display (order-received page, emails), and admin-facing display: a line on the single-order screen plus a "Delivery Slot" column on the wp-admin Orders list. The column is HPOS-aware (`OrderUtil::custom_orders_table_usage_is_enabled()` — note the current method name; an older-sounding name doesn't exist in recent WooCommerce) with a legacy post-based fallback.
- `Cron.php` — hourly `dsw_cleanup_stale_holds` releases a `pending` order's slot after 6h unpaid, so an abandoned checkout can't permanently occupy the last space.
- **Frontend assets are static, not webpack-built**: `assets/checkout/checkout.js` (classic, jQuery) and `assets/checkout/blocks-checkout.js` (block, built on `wp.element`/`wp.plugins`/`wc.blocksCheckout` globals) are hand-written, not run through the `src/` webpack pipeline, because `@woocommerce/blocks-checkout` isn't an installed npm package and wp-scripts' dependency-extraction plugin has no external mapping for `@woocommerce/*` (confirmed by checking `node_modules/@wordpress/dependency-extraction-webpack-plugin`). Both feature-detect their globals and no-op safely if unavailable.
- Both checkout UIs are **two dependent fields** (pick a date, then a time from that date's slots), not one combined dropdown. The block-checkout picker renders via `ExperimentalOrderMeta` (the only WooCommerce Blocks slot for injecting an arbitrary component) but is then relocated with a React portal (`wp.element.createPortal`) into an anchor inserted before the Payment section in the main column — `ExperimentalOrderMeta` itself only ever renders inside the Order Summary sidebar, which is the wrong place for a required input (confirmed by inspecting the live DOM, not assumed).

**Admin dashboard** (`src/Admin/`, mounted by `includes/Admin/Menu.php` into `#dsw-app`): a hand-built React app (plain `react`/`react-dom`, not `@wordpress/element`) — stats cards, search/filter, paginated table, add/edit via a SweetAlert2-driven form (`swal.js`; SweetAlert2 is self-hosted at `assets/vendor/sweetalert2/` and enqueued only on this admin page), delete confirmation, and a "View orders" action per slot showing bookings in a SweetAlert2 modal. `includes/Admin/Ajax.php` exists as an empty, unused stub class (not instantiated anywhere) — presumably scaffolding for a future AJAX handler.

**Known gaps / pre-existing oddities** (not touched — out of scope so far):
- `src/frontend.js` mounts into `#wrs-frontend-app`, but `includes/Frontend/Shortcode.php`'s `[dsw]` shortcode renders `#dsw-frontend-app` — the id mismatch means that shortcode's React app (`src/Frontend/App.js`, still a placeholder) never actually mounts. Unrelated to the checkout/admin features above, which use their own mount points.
- `Readme.md` still describes the generic starter template.
- `lint:js` doesn't currently run (see Commands).
