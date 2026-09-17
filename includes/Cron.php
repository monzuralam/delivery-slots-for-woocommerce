<?php

namespace DSW;

if (! defined('ABSPATH')) exit;

/**
 * A pending order (created but never paid, e.g. customer abandoned an
 * offsite payment gateway) still "holds" its slot per
 * SlotManager::HOLDING_STATUSES. Without cleanup, an abandoned checkout
 * could permanently occupy the last space in a slot. We release capacity
 * for very old pending orders on an hourly cron, and also react immediately
 * to WooCommerce's own "cancel unpaid orders" housekeeping when the store
 * has that enabled.
 */
class Cron
{
    /** Release a pending order's slot after this many hours with no payment. */
    const STALE_HOURS = 6;

    public function __construct()
    {
        add_action('dsw_cleanup_stale_holds', [$this, 'release_stale_pending_orders']);
        add_action('woocommerce_cancel_unpaid_order', [SlotManager::class, 'release_slot']);
    }

    public function release_stale_pending_orders()
    {
        $cutoff = gmdate('Y-m-d H:i:s', time() - (self::STALE_HOURS * HOUR_IN_SECONDS));

        $order_ids = wc_get_orders([
            'status'       => ['pending'],
            'date_created' => '<' . strtotime($cutoff),
            'limit'        => 100,
            'return'       => 'ids',
        ]);

        foreach ($order_ids as $order_id) {
            $booking = SlotManager::get_booking_by_order($order_id);

            if ($booking && 'reserved' === $booking->status) {
                SlotManager::release_slot($order_id);

                $order = wc_get_order($order_id);

                if ($order) {
                    $order->add_order_note(__('Delivery Slots: slot released automatically because this order has been unpaid for too long.', 'delivery-slots-for-woocommerce'));
                }
            }
        }
    }
}
