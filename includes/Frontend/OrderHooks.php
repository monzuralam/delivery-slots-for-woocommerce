<?php

namespace DSW\Frontend;

use DSW\SlotManager;

if (! defined('ABSPATH')) exit;

/**
 * Keeps slot capacity in sync with an order's lifecycle, and shows the
 * booked slot to both the customer (order-received page, emails) and the
 * store admin (order edit screen).
 */
class OrderHooks
{
    public function __construct()
    {
        // Status-change lifecycle — covers cancelled, failed, refunded,
        // processing, completed, on-hold transitions in both directions.
        add_action('woocommerce_order_status_changed', [$this, 'handle_status_changed'], 10, 4);

        // Trash / restore / permanent delete.
        add_action('woocommerce_trash_order', [$this, 'handle_trashed']);
        add_action('woocommerce_untrash_order', [$this, 'handle_restored']);
        add_action('woocommerce_before_delete_order', [$this, 'handle_hard_deleted']);

        // Customer-facing display.
        add_action('woocommerce_order_details_after_order_table', [$this, 'display_slot']);
        add_action('woocommerce_email_after_order_table', [$this, 'display_slot']);

        // Admin-facing display (read-only) on the order edit screen.
        add_action('woocommerce_admin_order_data_after_order_details', [$this, 'display_slot_admin']);

        // Admin orders-list "Delivery Slot" column (HPOS- and legacy-aware).
        // Deferred to admin_menu: wc_get_page_screen_id() isn't guaranteed to
        // be defined yet during plugins_loaded (depends on plugin load order
        // relative to WooCommerce's own plugins_loaded handler).
        add_action('admin_menu', [$this, 'register_order_list_column']);
    }

    /* -----------------------------------------------------------------
     * Admin orders-list column
     * ------------------------------------------------------------- */

    private function is_hpos_enabled()
    {
        $class = '\Automattic\WooCommerce\Utilities\OrderUtil';

        return class_exists($class)
            && method_exists($class, 'custom_orders_table_usage_is_enabled')
            && $class::custom_orders_table_usage_is_enabled();
    }

    public function register_order_list_column()
    {
        if ($this->is_hpos_enabled()) {
            $screen = wc_get_page_screen_id('shop-order');
            add_filter("manage_{$screen}_columns", [$this, 'add_order_list_column']);
            add_action("manage_{$screen}_custom_column", [$this, 'render_order_list_column_hpos'], 10, 2);

            return;
        }

        add_filter('manage_edit-shop_order_columns', [$this, 'add_order_list_column']);
        add_action('manage_shop_order_posts_custom_column', [$this, 'render_order_list_column_legacy'], 10, 2);
    }

    public function add_order_list_column($columns)
    {
        $new = [];

        foreach ($columns as $key => $label) {
            $new[$key] = $label;

            if ('order_status' === $key) {
                $new['dsw_slot'] = __('Delivery Slot', 'delivery-slots-for-woocommerce');
            }
        }

        if (! isset($new['dsw_slot'])) {
            $new['dsw_slot'] = __('Delivery Slot', 'delivery-slots-for-woocommerce');
        }

        return $new;
    }

    public function render_order_list_column_hpos($column, $order)
    {
        if ('dsw_slot' === $column) {
            $this->echo_slot_summary($order);
        }
    }

    public function render_order_list_column_legacy($column, $post_id)
    {
        if ('dsw_slot' === $column) {
            $this->echo_slot_summary(wc_get_order($post_id));
        }
    }

    private function echo_slot_summary($order)
    {
        $slot = $this->get_order_slot($order);

        if (! $slot) {
            echo '&mdash;';

            return;
        }

        printf(
            '%1$s<br><small>%2$s&ndash;%3$s</small>',
            esc_html(date_i18n(get_option('date_format'), strtotime($slot->slot_date))),
            esc_html(substr($slot->start_time, 0, 5)),
            esc_html(substr($slot->end_time, 0, 5))
        );
    }

    public function handle_status_changed($order_id, $from, $to)
    {
        $was_holding = SlotManager::is_holding_status($from);
        $now_holding = SlotManager::is_holding_status($to);

        if ($was_holding && ! $now_holding) {
            SlotManager::release_slot($order_id);

            return;
        }

        if (! $was_holding && $now_holding) {
            $order   = wc_get_order($order_id);
            $booking = SlotManager::get_booking_by_order($order_id);
            $slot_id = $booking ? (int) $booking->slot_id : absint($order ? $order->get_meta(Checkout::ORDER_META) : 0);

            if (! $slot_id || ! $order) {
                return;
            }

            $result = SlotManager::reserve_slot($slot_id, $order_id);

            if (is_wp_error($result)) {
                $order->add_order_note(
                    sprintf(
                        /* translators: %s: error message */
                        __('Delivery Slots: could not re-book this order\'s delivery slot (%s). Please assign a new slot.', 'delivery-slots-for-woocommerce'),
                        $result->get_error_message()
                    )
                );
            } else {
                $order->add_order_note(__('Delivery Slots: delivery slot re-booked.', 'delivery-slots-for-woocommerce'));
            }
        }
    }

    public function handle_trashed($order_id)
    {
        SlotManager::release_slot($order_id);
    }

    public function handle_restored($order_id)
    {
        $order = wc_get_order($order_id);

        if (! $order || ! SlotManager::is_holding_status($order->get_status())) {
            return;
        }

        $booking = SlotManager::get_booking_by_order($order_id);
        $slot_id = $booking ? (int) $booking->slot_id : absint($order->get_meta(Checkout::ORDER_META));

        if (! $slot_id) {
            return;
        }

        $result = SlotManager::reserve_slot($slot_id, $order_id);

        if (is_wp_error($result)) {
            $order->add_order_note(
                sprintf(
                    /* translators: %s: error message */
                    __('Delivery Slots: could not re-book the delivery slot after restoring this order (%s).', 'delivery-slots-for-woocommerce'),
                    $result->get_error_message()
                )
            );
        } else {
            $order->add_order_note(__('Delivery Slots: delivery slot re-booked after order was restored.', 'delivery-slots-for-woocommerce'));
        }
    }

    public function handle_hard_deleted($order_id)
    {
        SlotManager::release_slot($order_id);
    }

    /* -----------------------------------------------------------------
     * Display
     * ------------------------------------------------------------- */

    public function display_slot($order)
    {
        $slot = $this->get_order_slot($order);

        if (! $slot) {
            return;
        }

        echo '<h2>' . esc_html__('Delivery Slot', 'delivery-slots-for-woocommerce') . '</h2>';
        echo '<p>' . esc_html($this->format_slot_label($slot)) . '</p>';
    }

    public function display_slot_admin($order)
    {
        $slot = $this->get_order_slot($order);

        if (! $slot) {
            return;
        }

        echo '<p class="form-field form-field-wide dsw-order-slot"><strong>' .
            esc_html__('Delivery Slot:', 'delivery-slots-for-woocommerce') .
            '</strong> ' . esc_html($this->format_slot_label($slot)) . '</p>';
    }

    private function get_order_slot($order)
    {
        if (! $order instanceof \WC_Order) {
            return null;
        }

        $booking = SlotManager::get_booking_by_order($order->get_id());

        if (! $booking || 'reserved' !== $booking->status) {
            return null;
        }

        return SlotManager::get_slot($booking->slot_id);
    }

    private function format_slot_label($slot)
    {
        return date_i18n(get_option('date_format'), strtotime($slot->slot_date)) . ', ' .
            substr($slot->start_time, 0, 5) . ' - ' . substr($slot->end_time, 0, 5);
    }
}
