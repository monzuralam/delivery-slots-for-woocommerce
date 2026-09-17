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
        if ('toplevel_page_delivery-slots-for-woocommerce' !== $hook) {
            return;
        }

        $asset_file = DSW_PATH . '/assets/build/admin.asset.php';

        if (! file_exists($asset_file)) {
            return;
        }

        $asset = require $asset_file;

		wp_enqueue_style('dsw-sweetalert2', DSW_URL . '/assets/vendor/sweetalert2/sweetalert2.min.css', [], '11.26.25');

		wp_enqueue_script('dsw-sweetalert2', DSW_URL . '/assets/vendor/sweetalert2/sweetalert2.min.js', ['jquery'], '11.26.25', true);

        wp_enqueue_script(
            'dsw-admin',
            DSW_URL . '/assets/build/admin.js',
            array_merge($asset['dependencies'], ['dsw-sweetalert2']),
            $asset['version'],
            true
        );

        if (file_exists(DSW_PATH . '/assets/build/admin.css')) {
            wp_enqueue_style('dsw-admin', DSW_URL . '/assets/build/admin.css', [], $asset['version']);
        }

        wp_localize_script('dsw-admin', 'dswAdmin', [
            'root'  => esc_url_raw(rest_url('dsw/v1/')),
            'nonce' => wp_create_nonce('wp_rest'),
        ]);
    }
}
