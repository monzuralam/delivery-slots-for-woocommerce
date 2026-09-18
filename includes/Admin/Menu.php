<?php
namespace DSW\Admin;

if (! defined('ABSPATH') ) { exit;
}


class Menu
{

    public function __construct()
    {
        add_action('admin_menu', array( $this, 'add_menu_page' ));
    }

    public function add_menu_page()
    {
        add_menu_page(
            __('Delivery Slots for WooCommerce', 'delivery-slots-for-woocommerce'),
            __('Delivery Slots for WooCommerce', 'delivery-slots-for-woocommerce'),
            'manage_options',
            'delivery-slots-for-woocommerce',
            array(
				$this,
				'render_admin_page'
            )
        );

        add_submenu_page(
            'delivery-slots-for-woocommerce',
            __('Delivery Slots for WooCommerce', 'delivery-slots-for-woocommerce'),
            __('Dashboard', 'delivery-slots-for-woocommerce'),
            'manage_options',
            'delivery-slots-for-woocommerce',
            array(
                $this,
                'render_admin_page'
            )
        );

        add_submenu_page(
            'delivery-slots-for-woocommerce',
            __('Settings', 'delivery-slots-for-woocommerce'),
            __('Settings', 'delivery-slots-for-woocommerce'),
            'manage_options',
            'delivery-slots-for-woocommerce-settings',
            array(
                $this,
                'render_settings_page'
            )
        );

        add_submenu_page(
            'delivery-slots-for-woocommerce',
            __('Getting Started', 'delivery-slots-for-woocommerce'),
            __('Getting Started', 'delivery-slots-for-woocommerce'),
            'manage_options',
            'delivery-slots-for-woocommerce-getting-started',
            array(
                $this,
                'render_getting_started_page'
            )
        );
    }

    public function render_admin_page()
    {
        echo '<div id="dsw-app" class="dsw-app"></div>';
    }

    public function render_settings_page()
    {
        echo '<div id="dsw-settings" class="dsw-app"></div>';
    }

    public function render_getting_started_page()
    {
        echo '<div id="dsw-getting-started" class="dsw-app"></div>';
    }
}
