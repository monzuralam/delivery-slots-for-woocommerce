<?php

namespace DSW;

if (! defined('ABSPATH')) exit;

class Enqueue {

    /**
     * Constructor
     */
    public function __construct() {
        // Frontend scripts
        add_action('wp_enqueue_scripts', array($this, 'frontend_scripts'));

        // Admin scripts
        add_action('admin_enqueue_scripts', array($this, 'admin_scripts'));
    }

    /**
     * Frontend Assets
     * @since 1.0.0
     */
    public function frontend_scripts() {
        wp_register_script('dsw-frontend', DSW_URL . '/assets/build/frontend.js', array('jquery'), DSW_VERSION, true);
    }

    /**
     * Admin Assets
     * @since 1.0.0
     */
    public function admin_scripts($hook) {
        if ('toplevel_page_delivery-slots-for-woocommerce' === $hook) {
            $this->dashboard_scripts($hook);

            return;
        }

        if ('delivery-slots-for-woocommerce_page_delivery-slots-for-woocommerce-settings' === $hook) {
            $this->settings_scripts($hook);

            return;
        }

        if ('delivery-slots-for-woocommerce_page_delivery-slots-for-woocommerce-getting-started' === $hook) {
            $this->getting_started_scripts($hook);

            return;
        }
    }

    /**
     * Assets for the main Delivery Slots dashboard page
     */
    private function dashboard_scripts($hook) {
        $asset_file = DSW_PATH . '/assets/build/dashboard.asset.php';

        if (! file_exists($asset_file)) {
            return;
        }

        $asset = require $asset_file;

		wp_enqueue_style('dsw-sweetalert2', DSW_URL . '/assets/vendor/sweetalert2/sweetalert2.min.css', [], '11.26.25');

		wp_enqueue_script('dsw-sweetalert2', DSW_URL . '/assets/vendor/sweetalert2/sweetalert2.min.js', ['jquery'], '11.26.25', true);

        wp_enqueue_script(
            'dsw-dashboard',
            DSW_URL . '/assets/build/dashboard.js',
            array_merge($asset['dependencies'], ['dsw-sweetalert2'], ['wp-i18n']),
            $asset['version'],
            true
        );

        if (file_exists(DSW_PATH . '/assets/build/dashboard.css')) {
            wp_enqueue_style('dsw-dashboard', DSW_URL . '/assets/build/dashboard.css', [], $asset['version']);
        }

        wp_localize_script('dsw-dashboard', 'dsw', $this->get_localize_data($hook));
    }

    /**
     * Assets for the "Getting Started" page
     */
    private function getting_started_scripts($hook) {
        $asset_file = DSW_PATH . '/assets/build/getting-started.asset.php';

        if (! file_exists($asset_file)) {
            return;
        }

        $asset = require $asset_file;

        wp_enqueue_script(
            'dsw-getting-started',
            DSW_URL . '/assets/build/getting-started.js',
            $asset['dependencies'],
            $asset['version'],
            true
        );

        if (file_exists(DSW_PATH . '/assets/build/dashboard.css')) {
            wp_enqueue_style('dsw-getting-started', DSW_URL . '/assets/build/dashboard.css', [], $asset['version']);
        }

        wp_localize_script('dsw-getting-started', 'dsw', $this->get_localize_data($hook));
    }

    /**
     * Assets for the Settings page
     */
    private function settings_scripts($hook) {
        $asset_file = DSW_PATH . '/assets/build/settings.asset.php';

        if (! file_exists($asset_file)) {
            return;
        }

        $asset = require $asset_file;

        wp_enqueue_style('dsw-sweetalert2', DSW_URL . '/assets/vendor/sweetalert2/sweetalert2.min.css', [], '11.26.25');

        wp_enqueue_script('dsw-sweetalert2', DSW_URL . '/assets/vendor/sweetalert2/sweetalert2.min.js', ['jquery'], '11.26.25', true);

        wp_enqueue_script(
            'dsw-settings',
            DSW_URL . '/assets/build/settings.js',
            array_merge($asset['dependencies'], ['dsw-sweetalert2']),
            $asset['version'],
            true
        );

        if (file_exists(DSW_PATH . '/assets/build/settings.css')) {
            wp_enqueue_style('dsw-settings', DSW_URL . '/assets/build/settings.css', [], $asset['version']);
        }

        wp_localize_script('dsw-settings', 'dsw', $this->get_localize_data($hook));
    }

    /**
     * Data localized to `window.dsw`, built per admin page (`$hook`).
     */
    private function get_localize_data($hook) {
        $data = [
            'root'    => esc_url_raw(rest_url('dsw/v1/')),
            'nonce'   => wp_create_nonce('wp_rest'),
            'version' => DSW_VERSION,
        ];

        if ('toplevel_page_delivery-slots-for-woocommerce' === $hook) {
            return $data;
        }

        if ('delivery-slots-for-woocommerce_page_delivery-slots-for-woocommerce-settings' === $hook) {
            $data['settings']  = dsw_get_settings();
            $data['ajaxUrl']   = admin_url('admin-ajax.php');
            $data['ajaxNonce'] = wp_create_nonce('dsw_settings');

            return $data;
        }

        if ('delivery-slots-for-woocommerce_page_delivery-slots-for-woocommerce-getting-started' === $hook) {
            $data['slotCount']    = $this->get_slot_count();
            $data['checkoutType'] = $this->get_checkout_type();

            return $data;
        }

        return $data;
    }

    /**
     * Number of configured delivery slots.
     */
    private function get_slot_count() {
        global $wpdb;

        return (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . SlotManager::slots_table());
    }

    /**
     * Detected checkout type: "block", "classic", or "none".
     */
    private function get_checkout_type() {
        $checkout_page = get_post(wc_get_page_id('checkout'));

        if ($checkout_page && function_exists('has_block') && has_block('woocommerce/checkout', $checkout_page)) {
            return 'block';
        }

        if ($checkout_page && has_shortcode($checkout_page->post_content, 'woocommerce_checkout')) {
            return 'classic';
        }

        return 'none';
    }
}
