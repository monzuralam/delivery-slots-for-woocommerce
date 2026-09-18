<?php

namespace DSW\Frontend;

use DSW\SlotManager;

if (! defined('ABSPATH')) exit;

/**
 * Everything that happens on the checkout page:
 *  - rendering the slot picker (classic shortcode checkout + block checkout)
 *  - live pricing (a cart fee equal to the selected slot's price)
 *  - the AJAX endpoint the JS uses to record "which slot is currently selected"
 *  - the single, authoritative place capacity is actually reserved
 *    (woocommerce_checkout_order_processed) via SlotManager::reserve_slot().
 */
class Checkout
{
    const SESSION_KEY  = 'dsw_selected_slot_id';
    const POST_FIELD   = 'dsw_delivery_slot_id';
    const NONCE_ACTION = 'dsw_select_slot';
    const ORDER_META   = '_dsw_delivery_slot_id';

    public function __construct()
    {
        // Classic (shortcode) checkout field.
        add_action('woocommerce_after_order_notes', [$this, 'render_classic_field']);
        add_action('woocommerce_checkout_process', [$this, 'validate_classic_field']);

        // Pricing: apply the selected slot's price as a cart fee (works for
        // both classic and block checkout, since both use WC_Cart).
        add_action('woocommerce_cart_calculate_fees', [$this, 'apply_slot_fee']);

        // AJAX: record the customer's live selection (for pricing + surviving
        // an update_checkout refresh). Capacity is NOT touched here.
        add_action('wp_ajax_dsw_select_slot', [$this, 'ajax_select_slot']);
        add_action('wp_ajax_nopriv_dsw_select_slot', [$this, 'ajax_select_slot']);

        // Assets.
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);

        // Block checkout integration (progressive enhancement).
        add_action('woocommerce_blocks_loaded', [$this, 'register_blocks_integration']);
        add_action('woocommerce_blocks_loaded', [$this, 'register_cart_update_callback']);
        add_action('woocommerce_store_api_checkout_update_order_from_request', [$this, 'save_slot_from_store_api_request'], 10, 2);

        // The critical enforcement point. Classic (shortcode) checkout and
        // block checkout (Store API) fire two different "order processed"
        // hooks — woocommerce_store_api_checkout_order_processed is NOT a
        // backwards-compat alias of woocommerce_checkout_order_processed in
        // current WooCommerce, so both must be hooked or block-checkout
        // orders would skip capacity enforcement entirely. Throwing aborts
        // the order with an inline error and leaves no capacity taken.
        add_action('woocommerce_checkout_order_processed', [$this, 'reserve_on_order_processed'], 10, 3);
        add_action('woocommerce_store_api_checkout_order_processed', [$this, 'reserve_on_store_api_order_processed']);
    }

    /**
     * Whether the feature is turned on (Settings > General > Enable delivery slots).
     */
    private function is_enabled()
    {
        return (bool) dsw_get_settings('enable_dsw', true);
    }

    /* -----------------------------------------------------------------
     * Rendering
     * ------------------------------------------------------------- */

    public function render_classic_field($checkout)
    {
        if (! $this->is_enabled()) {
            return;
        }

        echo '<div id="dsw-delivery-slot" class="dsw-delivery-slot">';
        echo '<h3>' . esc_html(dsw_get_settings('default_delivery_slot_title', __('Delivery date & time', 'delivery-slots-for-woocommerce'))) . '</h3>';
        echo '<div class="dsw-slot-fields">';

        echo '<p class="form-row dsw-slot-field">';
        echo '<label for="dsw_delivery_date">' . esc_html(dsw_get_settings('delivery_date_title', __('Delivery date', 'delivery-slots-for-woocommerce'))) . ' <span class="required">*</span></label>';
        echo '<select id="dsw_delivery_date" class="dsw-slot-select" required>';
        echo '<option value="">' . esc_html__('Loading available dates…', 'delivery-slots-for-woocommerce') . '</option>';
        echo '</select>';
        echo '</p>';

        echo '<p class="form-row dsw-slot-field">';
        echo '<label for="dsw_delivery_slot_id">' . esc_html(dsw_get_settings('delivery_time_title', __('Delivery time', 'delivery-slots-for-woocommerce'))) . ' <span class="required">*</span></label>';
        echo '<select name="' . esc_attr(self::POST_FIELD) . '" id="dsw_delivery_slot_id" class="dsw-slot-select" required disabled>';
        echo '<option value="">' . esc_html__('Select a date first', 'delivery-slots-for-woocommerce') . '</option>';
        echo '</select>';
        echo '</p>';

        echo '</div>';
        echo '<span class="dsw-slot-status" aria-live="polite"></span>';
        echo '</div>';
    }

    public function validate_classic_field()
    {
        if (! $this->is_enabled()) {
            return;
        }

        if (! isset($_POST['woocommerce_checkout_place_order']) && ! isset($_POST[self::POST_FIELD])) {
            return;
        }

        $slot_id = isset($_POST[self::POST_FIELD]) ? absint(wp_unslash($_POST[self::POST_FIELD])) : 0;

        if (! $slot_id) {
            wc_add_notice(__('Please select a delivery slot before placing your order.', 'delivery-slots-for-woocommerce'), 'error');
        }
    }

    /* -----------------------------------------------------------------
     * Pricing
     * ------------------------------------------------------------- */

    public function apply_slot_fee($cart)
    {
        if (! $this->is_enabled()) {
            return;
        }

        if (is_admin() && ! defined('DOING_AJAX')) {
            return;
        }

        if (! WC()->session) {
            return;
        }

        $slot_id = absint(WC()->session->get(self::SESSION_KEY));

        if (! $slot_id) {
            return;
        }

        $slot = SlotManager::get_slot($slot_id);

        if (! $slot || 'active' !== $slot->status) {
            return;
        }

        if ((float) $slot->price <= 0) {
            return;
        }

        $cart->add_fee(
            sprintf(
                /* translators: %s: delivery slot label */
                __('Delivery (%s)', 'delivery-slots-for-woocommerce'),
                date_i18n(get_option('date_format'), strtotime($slot->slot_date)) . ', ' . substr($slot->start_time, 0, 5) . '-' . substr($slot->end_time, 0, 5)
            ),
            (float) $slot->price,
            false
        );
    }

    /**
     * AJAX handler used by the JS whenever the customer changes their slot
     * selection. Stores the choice in the WC session so apply_slot_fee() can
     * price it correctly and it survives an update_checkout refresh.
     */
    public function ajax_select_slot()
    {
        check_ajax_referer(self::NONCE_ACTION, 'nonce');

        if (! $this->is_enabled()) {
            wp_send_json_error(['message' => __('Delivery slots are currently disabled.', 'delivery-slots-for-woocommerce')]);
        }

        $slot_id = isset($_POST['slot_id']) ? absint(wp_unslash($_POST['slot_id'])) : 0;

        // A falsy slot id means "clear the current selection" (e.g. the
        // customer changed the delivery date, invalidating their previous
        // time choice) — drop any priced fee rather than treating it as an error.
        if (! $slot_id) {
            if (WC()->session) {
                WC()->session->set(self::SESSION_KEY, null);
            }

            wp_send_json_success(['cleared' => true]);
        }

        $slot = SlotManager::get_slot($slot_id);

        if (! $slot || 'active' !== $slot->status) {
            wp_send_json_error(['message' => __('That slot is not available.', 'delivery-slots-for-woocommerce')]);
        }

        WC()->session->set(self::SESSION_KEY, $slot_id);

        if (SlotManager::get_remaining_capacity($slot) < 1) {
            wp_send_json_success([
                'sold_out' => true,
                'message'  => __('That slot just sold out. Please pick another one.', 'delivery-slots-for-woocommerce'),
            ]);
        }

        wp_send_json_success([
            'sold_out'  => false,
            'remaining' => SlotManager::get_remaining_capacity($slot),
        ]);
    }

    /* -----------------------------------------------------------------
     * Assets
     * ------------------------------------------------------------- */

    public function enqueue_assets()
    {
        if (! $this->is_enabled()) {
            return;
        }

        if (! function_exists('is_checkout') || ! is_checkout()) {
            return;
        }

        wp_enqueue_style('dsw-checkout', DSW_URL . '/assets/checkout/checkout.css', [], DSW_VERSION);

        wp_enqueue_script('dsw-checkout', DSW_URL . '/assets/checkout/checkout.js', ['jquery'], DSW_VERSION, true);

        wp_localize_script('dsw-checkout', 'DSW_Checkout', [
            'restUrl'      => esc_url_raw(rest_url('dsw/v1/slots/available')),
            'ajaxUrl'      => admin_url('admin-ajax.php'),
            'nonce'        => wp_create_nonce(self::NONCE_ACTION),
            'selectedSlot' => WC()->session ? absint(WC()->session->get(self::SESSION_KEY)) : 0,
            'i18n'         => [
                'chooseDate'     => __('Select a delivery date…', 'delivery-slots-for-woocommerce'),
                'chooseDateFirst' => __('Select a date first', 'delivery-slots-for-woocommerce'),
                'choose'         => __('Select a delivery time…', 'delivery-slots-for-woocommerce'),
                'soldOut'        => __('Sold out', 'delivery-slots-for-woocommerce'),
                'left'           => __('left', 'delivery-slots-for-woocommerce'),
                'error'          => __('Could not load delivery slots. Please refresh the page.', 'delivery-slots-for-woocommerce'),
            ],
            'pollSeconds'  => 20,
        ]);
    }

    /* -----------------------------------------------------------------
     * Block checkout (progressive enhancement)
     * ------------------------------------------------------------- */

    public function register_blocks_integration()
    {
        if (! $this->is_enabled()) {
            return;
        }

        if (! interface_exists(\Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface::class)) {
            return;
        }

        add_action('woocommerce_blocks_checkout_block_registration', function ($integration_registry) {
            $integration_registry->register(new BlocksIntegration());
        });
    }

    /**
     * Registers the server-side handler for the client's extensionCartUpdate()
     * calls (see assets/checkout/blocks-checkout.js). Without this, a slot
     * chosen in block checkout is never written to WC()->session, so
     * apply_slot_fee() would have nothing to price until the order is
     * actually placed — the total would never update live.
     */
    public function register_cart_update_callback()
    {
        if (! $this->is_enabled()) {
            return;
        }

        if (! function_exists('woocommerce_store_api_register_update_callback')) {
            return;
        }

        woocommerce_store_api_register_update_callback([
            'namespace' => 'dsw',
            'callback'  => function ($data) {
                if (! WC()->session) {
                    return;
                }

                $slot_id = isset($data['slot_id']) ? absint($data['slot_id']) : 0;

                if (! $slot_id) {
                    WC()->session->set(self::SESSION_KEY, null);

                    return;
                }

                $slot = SlotManager::get_slot($slot_id);

                if ($slot && 'active' === $slot->status) {
                    WC()->session->set(self::SESSION_KEY, $slot_id);
                }
            },
        ]);
    }

    /**
     * Persist the slot chosen in the block checkout onto the order as meta,
     * before woocommerce_checkout_order_processed does the actual atomic
     * reservation.
     */
    public function save_slot_from_store_api_request($order, $request)
    {
        $extensions = $request->get_param('extensions');

        if (empty($extensions['dsw']['slot_id'])) {
            return;
        }

        $slot_id = absint($extensions['dsw']['slot_id']);

        if ($slot_id) {
            $order->update_meta_data(self::ORDER_META, $slot_id);
            $order->save_meta_data();
        }
    }

    /* -----------------------------------------------------------------
     * The critical enforcement point
     * ------------------------------------------------------------- */

    public function reserve_on_order_processed($order_id, $posted_data, $order)
    {
        if (! $this->is_enabled()) {
            return;
        }

        if (! $order instanceof \WC_Order) {
            $order = wc_get_order($order_id);
        }

        if (! $order) {
            return;
        }

        $slot_id = absint($order->get_meta(self::ORDER_META));

        if (! $slot_id && isset($_POST[self::POST_FIELD])) {
            $slot_id = absint(wp_unslash($_POST[self::POST_FIELD]));
        }

        $this->reserve_slot_for_order($order, $slot_id);
    }

    /**
     * Same enforcement as reserve_on_order_processed(), for the block
     * checkout / Store API path. Prefers the slot id saved to order meta
     * (set in save_slot_from_store_api_request(), from the checkout
     * request's extension data), falling back to the WC session value set
     * by register_cart_update_callback() in case a given WooCommerce
     * version doesn't carry cart extension data through to the final
     * checkout request's extensions payload.
     */
    public function reserve_on_store_api_order_processed($order)
    {
        if (! $this->is_enabled()) {
            return;
        }

        if (! $order instanceof \WC_Order) {
            return;
        }

        $slot_id = absint($order->get_meta(self::ORDER_META));

        if (! $slot_id && WC()->session) {
            $slot_id = absint(WC()->session->get(self::SESSION_KEY));
        }

        $this->reserve_slot_for_order($order, $slot_id);
    }

    private function reserve_slot_for_order($order, $slot_id)
    {
        if (! $slot_id) {
            throw new \WC_Data_Exception('dsw_slot_required', __('Please select a delivery slot before placing your order.', 'delivery-slots-for-woocommerce'));
        }

        $result = SlotManager::reserve_slot($slot_id, $order->get_id());

        if (is_wp_error($result)) {
            throw new \WC_Data_Exception('dsw_slot_reserve_failed', $result->get_error_message());
        }

        $slot = SlotManager::get_slot($slot_id);

        $order->update_meta_data(self::ORDER_META, $slot_id);

        if ($slot) {
            $order->add_order_note(
                sprintf(
                    /* translators: %s: slot label */
                    __('Delivery slot booked: %s', 'delivery-slots-for-woocommerce'),
                    date_i18n(get_option('date_format'), strtotime($slot->slot_date)) . ' ' . substr($slot->start_time, 0, 5) . '-' . substr($slot->end_time, 0, 5)
                )
            );
        }

        $order->save();

        if (WC()->session) {
            WC()->session->set(self::SESSION_KEY, null);
        }
    }
}
