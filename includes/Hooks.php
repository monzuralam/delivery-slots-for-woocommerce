<?php

namespace DSW;

/**
 * Manager Class
 */
class Hooks {

    /**
     * Constructor
     */
    public function __construct() {
        add_filter('plugin_action_links_' . plugin_basename(DSW_FILE), [$this, 'plugin_action_links']);
    }

    /**
     * Action Links
     * @since 1.0.0
     */
    public function plugin_action_links($links) {
        $links[] = '<a href="' . admin_url('admin.php?page=delivery-slots-for-woocommerce') . '" >' . __('Settings', 'delivery-slots-for-woocommerce') . '</a>';

        return $links;
    }
}
