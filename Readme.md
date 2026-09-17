# Delivery Slots for WooCommerce

Let customers pick a priced, capacity-limited delivery date and time at checkout, and give store admins a dashboard to manage those slots and see which orders belong to each one.

## Features

- 📅 **Two-step delivery picker at checkout** — pick a date, then a time slot for that date — supporting both the classic (shortcode) checkout and the block-based Checkout, with live availability and pricing.
- 💰 **Per-slot pricing** — each slot can add its own delivery fee to the order total, updated live as the customer picks a slot.
- 🔒 **Overselling-safe capacity** — slot bookings are reserved with a real database transaction (row locking), so two customers can never both book the last space in a slot.
- ♻️ **Automatic capacity release** — a cancelled, failed, refunded, or abandoned (unpaid for 6+ hours) order automatically frees up its slot again.
- 🛠️ **Admin dashboard** — a React-based screen in wp-admin to create, edit, search, and filter delivery slots (date, time, capacity, price, status).
- 📋 **Order ↔ slot visibility** — see every order booked into a slot from the dashboard, and see each order's delivery slot right in the WooCommerce Orders list (works with both HPOS and legacy order storage) and on the order details/emails.
- ⚛️ Admin dashboard and checkout UI are all React under the hood.

## Requirements

- WordPress 6.0+
- WooCommerce (active)
- PHP 7.4+

## Project Structure

```
├── 📁 assets/
│   ├── build/            (webpack output: admin.js/css — run `npm run build` first)
│   ├── checkout/         (hand-written checkout JS/CSS — classic + block checkout pickers)
│   └── vendor/           (self-hosted SweetAlert2, used by the admin dashboard)
├── 📁 includes/                    (PHP, namespace DSW\)
│   ├── 📁 Admin/                   (admin menu page)
│   ├── 📁 Frontend/                (checkout integration, order hooks, shortcode, blocks integration)
│   ├── 📁 Rest/                    (REST API: admin slot CRUD + public availability endpoint)
│   ├── 🐘 SlotManager.php          (the concurrency-safe reserve/release logic)
│   ├── 🐘 Install.php              (creates the DB tables on activation)
│   ├── 🐘 Cron.php                 (releases stale unpaid holds)
│   └── 🐘 Enqueue.php / Hooks.php
├── 📁 src/                         (JS/React source, built by webpack)
│   └── 📁 Admin/                   (the admin dashboard React app)
├── 📄 composer.json
├── 📄 package.json
└── 🐘 delivery-slots-for-woocommerce.php   (plugin entry point)
```

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
