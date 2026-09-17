<?php

namespace DSW\Frontend;

/**
 * Frontend Class
 */
class Shortcode {

    /**
     * Initialize
     */
    public function __construct() {
        add_shortcode('dsw', [$this, 'render_shortcode']);
    }

    /**
     * Shortcode
     */
    public function render_shortcode($atts, $content){
        wp_enqueue_script('dsw-frontend');
        return '<div id="dsw-frontend-app"></div>';
    }
}
