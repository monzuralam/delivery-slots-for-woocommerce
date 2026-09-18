<?php

namespace DSW\Admin;

use DSW\SlotManager;

if (! defined('ABSPATH')) exit;

/**
 * AJAX handlers for the Settings page (General save + Tools export/import).
 */
class Ajax
{
    public function __construct()
    {
        add_action('wp_ajax_dsw_save_settings', [$this, 'save_settings']);
        add_action('wp_ajax_dsw_export_settings', [$this, 'export_settings']);
        add_action('wp_ajax_dsw_import_settings', [$this, 'import_settings']);
    }

    private function check_access()
    {
        if (! current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('You are not allowed to do this.', 'delivery-slots-for-woocommerce')], 403);
        }

        check_ajax_referer('dsw_settings', 'nonce');
    }

    public function save_settings()
    {
        $this->check_access();

        $data = [
            'enable_dsw' => ! empty($_POST['enable_dsw']),
            'auto_save'  => ! empty($_POST['auto_save']),
        ];

        if (isset($_POST['default_delivery_slot_title'])) {
            $data['default_delivery_slot_title'] = wp_unslash($_POST['default_delivery_slot_title']);
        }

        if (isset($_POST['delivery_date_title'])) {
            $data['delivery_date_title'] = wp_unslash($_POST['delivery_date_title']);
        }

        if (isset($_POST['delivery_time_title'])) {
            $data['delivery_time_title'] = wp_unslash($_POST['delivery_time_title']);
        }

        if (isset($_POST['stale_hold_hours'])) {
            $data['stale_hold_hours'] = $_POST['stale_hold_hours'];
        }

        $saved = $this->persist_settings($data);

        wp_send_json_success($saved);
    }

    /**
     * Sanitize and persist settings, merged over the current values so a
     * partial save doesn't wipe out fields it didn't touch.
     */
    private function persist_settings($data)
    {
        $current = dsw_get_settings();

        $sanitized = [
            'enable_dsw'                  => array_key_exists('enable_dsw', $data) ? (bool) $data['enable_dsw'] : $current['enable_dsw'],
            'default_delivery_slot_title' => array_key_exists('default_delivery_slot_title', $data)
                ? sanitize_text_field($data['default_delivery_slot_title'])
                : $current['default_delivery_slot_title'],
            'delivery_date_title' => array_key_exists('delivery_date_title', $data)
                ? sanitize_text_field($data['delivery_date_title'])
                : $current['delivery_date_title'],
            'delivery_time_title' => array_key_exists('delivery_time_title', $data)
                ? sanitize_text_field($data['delivery_time_title'])
                : $current['delivery_time_title'],
            'stale_hold_hours' => array_key_exists('stale_hold_hours', $data)
                ? max(1, absint($data['stale_hold_hours']))
                : $current['stale_hold_hours'],
            'auto_save' => array_key_exists('auto_save', $data) ? (bool) $data['auto_save'] : $current['auto_save'],
        ];

        update_option('dsw_settings', $sanitized);

        return $sanitized;
    }

    public function export_settings()
    {
        $this->check_access();

        global $wpdb;

        $scope = isset($_GET['scope']) ? sanitize_key($_GET['scope']) : 'everything';

        if (! in_array($scope, ['everything', 'settings', 'slots', 'bookings'], true)) {
            $scope = 'everything';
        }

        $payload = [
            'meta' => [
                'plugin'      => 'delivery-slots-for-woocommerce',
                'version'     => defined('DSW_VERSION') ? DSW_VERSION : '',
                'exported_at' => current_time('mysql'),
                'scope'       => $scope,
            ],
        ];

        if (in_array($scope, ['everything', 'settings'], true)) {
            $payload['settings'] = dsw_get_settings();
        }

        if (in_array($scope, ['everything', 'slots'], true)) {
            $payload['slots'] = $wpdb->get_results(
                'SELECT slot_date, start_time, end_time, capacity, price, status FROM ' . SlotManager::slots_table(),
                ARRAY_A
            ) ?: [];
        }

        if (in_array($scope, ['everything', 'bookings'], true)) {
            // Read-only: order_id links are specific to this site's WooCommerce
            // orders and are never restored by import (see import_settings()).
            $payload['bookings'] = $wpdb->get_results(
                'SELECT slot_id, order_id, status, created_at FROM ' . SlotManager::bookings_table(),
                ARRAY_A
            ) ?: [];
        }

        nocache_headers();
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="delivery-slots-export-' . $scope . '-' . gmdate('Y-m-d') . '.json"');

        echo wp_json_encode($payload);

        exit;
    }

    public function import_settings()
    {
        $this->check_access();

        $raw  = isset($_POST['payload']) ? wp_unslash($_POST['payload']) : '';
        $data = json_decode($raw, true);

        if (! is_array($data)) {
            wp_send_json_error(['message' => __('That file is not a valid Delivery Slots export.', 'delivery-slots-for-woocommerce')], 400);
        }

        $result = ['imported' => 0, 'skipped_with_bookings' => 0];

        if (! empty($data['settings']) && is_array($data['settings'])) {
            $this->persist_settings($data['settings']);
        }

        if (! empty($data['slots']) && is_array($data['slots'])) {
            $result = SlotManager::replace_slots($data['slots']);
        }

        wp_send_json_success($result);
    }
}
