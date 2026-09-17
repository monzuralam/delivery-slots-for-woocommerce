<?php

/**
 * Plugin Name: Delivery Slots for WooCommerce
 * Plugin URI: https://github.com/monzuralam/delivery-slots-for-woocommerce
 * Description: A simple WordPress React Starter Plugin for WordPress.
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Version: 1.0.0
 * Author: Monzur Alam
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 * Text Domain: delivery-slots-for-woocommerce
 */

defined('ABSPATH') || exit;

require __DIR__ . '/vendor/autoload.php';

/**
 * Declare compatibility with WooCommerce features (HPOS, cart/checkout
 * blocks) before WooCommerce boots.
 */
add_action('before_woocommerce_init', function () {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
    }
});

/**
 * Delivery_Slots_For_WooCommerce Class
 */
final class Delivery_Slots_For_WooCommerce
{

    /**
     * Plugin version
     *
     * @var string
     */
    private $version = '1.0.0';

    /**
     * Instances array
     *
     * @var array
     */
    private $instances = [];

    /**
     * Initialize
     */
    public function __construct()
    {
        // define constants
        $this->define_constants();

        // run the installer
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);

        // localization setup
        add_action('init', [$this, 'localization_setup']);

        // load the plugin
        add_action('plugins_loaded', [$this, 'init_plugin']);
    }

    private function check_environment()
    {
        $environment = true;

        if (! class_exists('WooCommerce')) {
            $environment = false;

            if (is_admin()) {
                add_action('admin_notices', [$this, 'missing_plugin_notice']);
            }
        }

        return $environment;
    }

    public function is_woocommerce_installed($basename)
    {
        if (! function_exists('get_plugins')) {
            include_once ABSPATH . '/wp-admin/includes/plugin.php';
        }

        $installed_plugins = get_plugins();

        return isset($installed_plugins[$basename]);
    }

    public function missing_plugin_notice()
    {

        if (! current_user_can('activate_plugins')) {
            return;
        }

        $woocommerce = 'woocommerce/woocommerce.php';

        if ($this->is_woocommerce_installed($woocommerce)) {
            $activation_url = wp_nonce_url('plugins.php?action=activate&amp;plugin=' . $woocommerce . '&amp;plugin_status=all&amp;paged=1&amp;s', 'activate-plugin_' . $woocommerce);

            $message = '<strong>Delivery Slots for WooCommerce</strong> requires <strong>WooCommerce</strong> plugin to be active. Please activate WooCommerce to continue.';

            $button_text = 'Activate WooCommerce';
        } else {

            $activation_url = wp_nonce_url(self_admin_url('update.php?action=install-plugin&plugin=woocommerce'), 'install-plugin_woocommerce');

            $message     = '<strong>Delivery Slots for WooCommerce</strong> requires <strong>WooCommerce</strong> plugin to be installed and activated. Please install WooCommerce to continue.';
            $button_text = 'Install WooCommerce';
        }

        $button = '<p><a href="' . esc_url($activation_url) . '" class="button-primary">' . esc_html($button_text) . '</a></p>';

        printf(
            '<div class="error"><p>%1$s</p>%2$s</div>',
            wp_kses_post($message),
            wp_kses_post($button)
        );
    }

    /**
     * Define constants
     *
     * @return void
     */
    private function define_constants()
    {
        define('DSW_VERSION', $this->version);
        define('DSW_DB_VERSION', '1.0.0');
        define('DSW_FILE', __FILE__);
        define('DSW_PATH', dirname(DSW_FILE));
        define('DSW_INCLUDES', DSW_PATH . '/includes');
        define('DSW_URL', plugins_url('', DSW_FILE));
        define('DSW_ASSETS', DSW_URL . '/assets');
    }

    /**
     * Run the installer
     *
     * @return void
     */
    public function activate()
    {
        DSW\Install::activate();
    }

    /**
     * Run the deactivator
     *
     * @return void
     */
    public function deactivate()
    {
        DSW\Install::deactivate();
    }

    /**
     * Initialize plugin for localization
     *
     * @return void
     * @since  1.0.0
     */
    public function localization_setup()
    {
        load_plugin_textdomain('delivery-slots-for-woocommerce', false, dirname(plugin_basename(DSW_FILE)) . '/languages/');
    }

    /**
     * Initialize the plugin
     *
     * @return void
     */
    public function init_plugin()
    {
        if (! $this->check_environment()) {
            return;
        }

        if (is_admin()) {
            new DSW\Admin();
        }

        if(! is_admin() ) {
            new DSW\Frontend\Shortcode();
        }

        new DSW\Enqueue();
        new DSW\Hooks();
        new DSW\Rest\SlotsController();
        new DSW\Rest\PublicSlotsController();
        new DSW\Frontend\Checkout();
        new DSW\Frontend\OrderHooks();
        new DSW\Cron();
    }

    /**
     * Initializes the Delivery_Slots_For_WooCommerce class
     *
     * Checks for an existing instance
     * and if it doesn't find one, creates it.
     */
    public static function instance()
    {
        static $instance = false;

        if (! $instance) {
            $instance = new self();
        }

        return $instance;
    }
}

/**
 * Return the instance
 *
 * @return \Delivery_Slots_For_WooCommerce
 */
function dsw()
{
    return Delivery_Slots_For_WooCommerce::instance();
}

// take off
dsw();
