<?php

namespace DSW;

if (! defined('ABSPATH')) exit;

/**
 * All reads/writes to the delivery-slot tables go through this class.
 *
 * reserve_slot() / release_slot() are the only places capacity is changed,
 * and they are written to be safe under concurrent requests via a real
 * InnoDB transaction with SELECT ... FOR UPDATE row locking.
 */
class SlotManager
{
    /**
     * Order statuses that should "hold" a slot's capacity.
     * Everything else (cancelled, failed, refunded, trash) releases it.
     */
    const HOLDING_STATUSES = ['pending', 'on-hold', 'processing', 'completed'];

    public static function slots_table()
    {
        global $wpdb;

        return $wpdb->prefix . 'dsw_slots';
    }

    public static function bookings_table()
    {
        global $wpdb;

        return $wpdb->prefix . 'dsw_bookings';
    }

    public static function get_slot($slot_id)
    {
        global $wpdb;

        $table = self::slots_table();

        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $slot_id));
    }

    public static function get_remaining_capacity($slot)
    {
        if (is_numeric($slot)) {
            $slot = self::get_slot($slot);
        }

        if (! $slot) {
            return 0;
        }

        return max(0, (int) $slot->capacity - (int) $slot->booked);
    }

    public static function is_holding_status($status)
    {
        $status = str_replace('wc-', '', $status);

        return in_array($status, self::HOLDING_STATUSES, true);
    }

    public static function get_booking_by_order($order_id)
    {
        global $wpdb;

        $table = self::bookings_table();

        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE order_id = %d", $order_id));
    }

    /**
     * Orders currently booked (reserved) into a given slot, newest first.
     */
    public static function get_orders_for_slot($slot_id)
    {
        global $wpdb;

        $table = self::bookings_table();

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE slot_id = %d AND status = 'reserved' ORDER BY created_at DESC",
            $slot_id
        ));
    }

    /* -----------------------------------------------------------------
     * Booking lifecycle (the concurrency-critical part)
     * ------------------------------------------------------------- */

    /**
     * Atomically reserve one unit of capacity on a slot for an order.
     *
     * Two customers hitting "Place order" for the same last-available slot
     * at the same moment must not both succeed. We push the decision into
     * the database using a real transaction with a locking read:
     *
     *   START TRANSACTION;
     *   SELECT booked, capacity FROM slots WHERE id = ? FOR UPDATE;
     *   -- if booked >= capacity: ROLLBACK, return an error
     *   UPDATE slots SET booked = booked + 1 WHERE id = ? AND booked < capacity;
     *   INSERT INTO bookings (slot_id, order_id, status='reserved');
     *   COMMIT;
     *
     * SELECT ... FOR UPDATE takes an exclusive row lock on that slot's row,
     * so a concurrent request's own SELECT ... FOR UPDATE blocks until this
     * transaction commits or rolls back — there is no window where both
     * requests can see "1 space left" and both proceed to book it.
     *
     * @return true|\WP_Error
     */
    public static function reserve_slot($slot_id, $order_id)
    {
        global $wpdb;

        $slots_table    = self::slots_table();
        $bookings_table = self::bookings_table();

        $existing = self::get_booking_by_order($order_id);

        if ($existing && 'reserved' === $existing->status) {
            if ((int) $existing->slot_id === (int) $slot_id) {
                return true;
            }

            return new \WP_Error('dsw_already_booked', __('This order already has a different delivery slot reserved.', 'delivery-slots-for-woocommerce'));
        }

        $wpdb->query('START TRANSACTION');

        $slot = $wpdb->get_row(
            $wpdb->prepare("SELECT id, capacity, booked, status FROM {$slots_table} WHERE id = %d FOR UPDATE", $slot_id)
        );

        if (! $slot) {
            $wpdb->query('ROLLBACK');

            return new \WP_Error('dsw_slot_missing', __('The selected delivery slot no longer exists.', 'delivery-slots-for-woocommerce'));
        }

        if ('active' !== $slot->status) {
            $wpdb->query('ROLLBACK');

            return new \WP_Error('dsw_slot_inactive', __('The selected delivery slot is no longer available.', 'delivery-slots-for-woocommerce'));
        }

        if ((int) $slot->booked >= (int) $slot->capacity) {
            $wpdb->query('ROLLBACK');

            return new \WP_Error('dsw_slot_full', __('Sorry, that delivery slot just sold out. Please choose another.', 'delivery-slots-for-woocommerce'));
        }

        $updated = $wpdb->query(
            $wpdb->prepare("UPDATE {$slots_table} SET booked = booked + 1 WHERE id = %d AND booked < capacity", $slot_id)
        );

        if (! $updated) {
            $wpdb->query('ROLLBACK');

            return new \WP_Error('dsw_slot_full', __('Sorry, that delivery slot just sold out. Please choose another.', 'delivery-slots-for-woocommerce'));
        }

        if ($existing) {
            $wpdb->update(
                $bookings_table,
                ['slot_id' => $slot_id, 'status' => 'reserved'],
                ['order_id' => $order_id],
                ['%d', '%s'],
                ['%d']
            );
        } else {
            $inserted = $wpdb->insert(
                $bookings_table,
                ['slot_id' => $slot_id, 'order_id' => $order_id, 'status' => 'reserved'],
                ['%d', '%d', '%s']
            );

            if (false === $inserted) {
                $wpdb->query('ROLLBACK');

                return new \WP_Error('dsw_db_error', __('Could not save the delivery slot booking. Please try again.', 'delivery-slots-for-woocommerce'));
            }
        }

        $wpdb->query('COMMIT');

        return true;
    }

    /**
     * Release the capacity held by an order's booking. Idempotent: releasing
     * an already-released or non-existent booking is a safe no-op.
     *
     * @return true
     */
    public static function release_slot($order_id)
    {
        global $wpdb;

        $slots_table    = self::slots_table();
        $bookings_table = self::bookings_table();

        $booking = self::get_booking_by_order($order_id);

        if (! $booking || 'reserved' !== $booking->status) {
            return true;
        }

        $wpdb->query('START TRANSACTION');

        $wpdb->get_row($wpdb->prepare("SELECT id FROM {$slots_table} WHERE id = %d FOR UPDATE", $booking->slot_id));

        $wpdb->query(
            $wpdb->prepare("UPDATE {$slots_table} SET booked = GREATEST(0, booked - 1) WHERE id = %d", $booking->slot_id)
        );

        $wpdb->update(
            $bookings_table,
            ['status' => 'released'],
            ['order_id' => $order_id],
            ['%s'],
            ['%d']
        );

        $wpdb->query('COMMIT');

        return true;
    }

    /**
     * Release the old slot and reserve the new one atomically (best effort):
     * rolls back to the original slot if the new one has no room.
     *
     * @return true|\WP_Error
     */
    public static function change_order_slot($order_id, $new_slot_id)
    {
        $booking = self::get_booking_by_order($order_id);

        if ($booking && 'reserved' === $booking->status && (int) $booking->slot_id === (int) $new_slot_id) {
            return true;
        }

        if ($booking && 'reserved' === $booking->status) {
            self::release_slot($order_id);
        }

        $result = self::reserve_slot($new_slot_id, $order_id);

        if (is_wp_error($result) && $booking && 'reserved' === $booking->status) {
            self::reserve_slot($booking->slot_id, $order_id);
        }

        return $result;
    }
}
