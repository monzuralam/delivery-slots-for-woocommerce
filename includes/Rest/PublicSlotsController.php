<?php

namespace DSW\Rest;

use DSW\SlotManager;

if (! defined('ABSPATH')) exit;

/**
 * A small, read-only, public REST endpoint the checkout page polls to
 * render/refresh the list of available delivery slots without a page
 * reload. Final capacity enforcement always happens server-side in
 * SlotManager::reserve_slot() at order-processing time — nothing this
 * endpoint returns is trusted for the actual booking decision.
 */
class PublicSlotsController
{
    const NAMESPACE_ = 'dsw/v1';

    public function __construct()
    {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes()
    {
        register_rest_route(self::NAMESPACE_, '/slots/available', [
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [$this, 'get_slots'],
                'permission_callback' => '__return_true',
                'args'                => [
                    'from' => [
                        'type'              => 'string',
                        'required'          => false,
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                    'days' => [
                        'type'              => 'integer',
                        'required'          => false,
                        'default'           => 14,
                        'sanitize_callback' => 'absint',
                    ],
                ],
            ],
        ]);
    }

    /**
     * Returns active, future slots (available or not — sold-out slots are
     * included but flagged, not hidden, so the list doesn't jump around
     * while the customer is browsing).
     */
    public function get_slots(\WP_REST_Request $request)
    {
        $from = $request->get_param('from');
        $days = min(60, max(1, (int) $request->get_param('days')));

        if (! $from || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
            $from = current_time('Y-m-d');
        }

        global $wpdb;

        $table = SlotManager::slots_table();
        $to    = gmdate('Y-m-d', strtotime($from . " + {$days} days"));

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, slot_date, start_time, end_time, capacity, booked, price
             FROM {$table}
             WHERE status = 'active' AND slot_date BETWEEN %s AND %s
             ORDER BY slot_date ASC, start_time ASC",
            $from,
            $to
        ));

        $data = [];

        foreach ($rows as $row) {
            $remaining = max(0, (int) $row->capacity - (int) $row->booked);

            $data[] = [
                'id'         => (int) $row->id,
                'date'       => $row->slot_date,
                'start_time' => substr($row->start_time, 0, 5),
                'end_time'   => substr($row->end_time, 0, 5),
                'price'      => wc_format_decimal($row->price, wc_get_price_decimals()),
                'price_html' => wc_price($row->price),
                'remaining'  => $remaining,
                'available'  => $remaining > 0,
            ];
        }

        return new \WP_REST_Response(['slots' => $data], 200);
    }
}
