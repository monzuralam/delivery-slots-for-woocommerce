<?php

namespace DSW\Frontend;

if (! defined('ABSPATH')) exit;

use Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface;

/**
 * Registers our checkout script with WooCommerce Blocks so the delivery-slot
 * picker also appears in the block-based Checkout block, in addition to the
 * classic shortcode checkout handled by Checkout::render_classic_field().
 *
 * Deliberately "progressive enhancement": if a store uses the classic
 * [woocommerce_checkout] shortcode, none of this code path runs and that
 * flow works fully server-rendered.
 */
class BlocksIntegration implements IntegrationInterface
{
    public function get_name()
    {
        return 'dsw';
    }

    public function initialize()
    {
        wp_register_script(
            'dsw-blocks-checkout',
            DSW_URL . '/assets/checkout/blocks-checkout.js',
            ['wc-blocks-checkout', 'wp-element', 'wp-html-entities', 'wp-i18n', 'wp-data', 'wp-plugins'],
            DSW_VERSION,
            true
        );

        wp_set_script_translations('dsw-blocks-checkout', 'delivery-slots-for-woocommerce');

        wp_localize_script('dsw-blocks-checkout', 'DSW_BlocksCheckout', [
            'restUrl'      => esc_url_raw(rest_url('dsw/v1/slots/available')),
            'namespace'    => 'dsw',
            'pollSeconds'  => 20,
            'selectedSlot' => WC()->session ? absint(WC()->session->get(Checkout::SESSION_KEY)) : 0,
            'title'        => dsw_get_settings('default_delivery_slot_title', 'Delivery date & time'),
            'dateTitle'    => dsw_get_settings('delivery_date_title', 'Delivery date'),
            'timeTitle'    => dsw_get_settings('delivery_time_title', 'Delivery time'),
        ]);
    }

    public function get_script_handles()
    {
        return ['dsw-blocks-checkout'];
    }

    public function get_editor_script_handles()
    {
        return [];
    }

    public function get_script_data()
    {
        return [];
    }
}
