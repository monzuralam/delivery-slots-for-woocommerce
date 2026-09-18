<?php

if (! defined('ABSPATH')) exit;

/**
 * Get a Delivery Slots setting value, or the full settings array.
 *
 * Merged over defaults so a field added after a site's `dsw_settings` option
 * was first saved still gets a value.
 *
 * @param string|null $key     Setting key (e.g. 'stale_hold_hours'), or null for the full array.
 * @param mixed       $default Fallback if the key doesn't exist. Ignored when $key is null.
 * @return mixed
 */
function dsw_get_settings($key = null, $default = null) {
    $defaults = [
        'enable_dsw'                  => true,
        'default_delivery_slot_title' => 'Delivery date & time',
        'delivery_date_title'         => 'Delivery date',
        'delivery_time_title'         => 'Delivery time',
        'stale_hold_hours'            => 6,
        'auto_save'                   => false,
        'delete_data_on_uninstall'    => false,
    ];

    $stored = get_option('dsw_settings', []);

    if (! is_array($stored)) {
        $stored = [];
    }

    $settings = wp_parse_args($stored, $defaults);

    if (null === $key) {
        return $settings;
    }

    return array_key_exists($key, $settings) ? $settings[$key] : $default;
}
