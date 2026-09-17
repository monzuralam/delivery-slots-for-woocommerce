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
    }

    public function render_admin_page()
    {
        echo '<div id="dsw-app" class="dsw-app"></div>';
    }
}
