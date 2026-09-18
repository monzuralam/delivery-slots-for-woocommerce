<?php

namespace DSW\Rest;

use DSW\SlotManager;

if (! defined('ABSPATH')) exit;

/**
 * REST API controller for delivery slots CRUD.
 */
class SlotsController
{
    const NAMESPACE_ = 'dsw/v1';
    const STATUSES   = ['active', 'inactive', 'full', 'closed'];

    /**
     * Constructor
     */
    public function __construct()
    {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    /**
     * Register REST routes
     */
    public function register_routes()
    {
        register_rest_route(self::NAMESPACE_, '/slots/stats', [
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [$this, 'get_stats'],
                'permission_callback' => [$this, 'check_permission'],
            ],
        ]);

        register_rest_route(self::NAMESPACE_, '/slots/check-conflict', [
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [$this, 'check_conflict'],
                'permission_callback' => [$this, 'check_permission'],
                'args'                => [
                    'slot_date'  => ['type' => 'string', 'required' => true],
                    'start_time' => ['type' => 'string', 'required' => true],
                    'exclude_id' => ['type' => 'integer', 'required' => false],
                ],
            ],
        ]);

        register_rest_route(self::NAMESPACE_, '/slots', [
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [$this, 'get_items'],
                'permission_callback' => [$this, 'check_permission'],
                'args'                => [
                    'search'   => ['type' => 'string', 'required' => false],
                    'status'   => ['type' => 'string', 'required' => false],
                    'page'     => ['type' => 'integer', 'default' => 1],
                    'per_page' => ['type' => 'integer', 'default' => 10],
                ],
            ],
            [
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'create_item'],
                'permission_callback' => [$this, 'check_permission'],
                'args'                => $this->get_item_args(),
            ],
        ]);

        register_rest_route(self::NAMESPACE_, '/slots/(?P<id>\d+)', [
            [
                'methods'             => \WP_REST_Server::EDITABLE,
                'callback'            => [$this, 'update_item'],
                'permission_callback' => [$this, 'check_permission'],
                'args'                => $this->get_item_args(false),
            ],
            [
                'methods'             => \WP_REST_Server::DELETABLE,
                'callback'            => [$this, 'delete_item'],
                'permission_callback' => [$this, 'check_permission'],
            ],
        ]);

        register_rest_route(self::NAMESPACE_, '/slots/(?P<id>\d+)/orders', [
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [$this, 'get_slot_orders'],
                'permission_callback' => [$this, 'check_permission'],
            ],
        ]);
    }

    /**
     * Shared permission check
     */
    public function check_permission()
    {
        return current_user_can('manage_options');
    }

    /**
     * Argument schema for create/update
     */
    private function get_item_args($required = true)
    {
        return [
            'slot_date' => [
                'required'          => $required,
                'validate_callback' => function ($value) {
                    return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $value);
                },
            ],
            'start_time' => [
                'required'          => $required,
                'validate_callback' => function ($value) {
                    return (bool) preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $value);
                },
            ],
            'end_time' => [
                'required'          => $required,
                'validate_callback' => function ($value) {
                    return (bool) preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $value);
                },
            ],
            'capacity' => [
                'required'          => $required,
                'sanitize_callback' => 'absint',
            ],
            'price' => [
                'required'          => $required,
                'sanitize_callback' => function ($value) {
                    return (float) $value;
                },
            ],
            'status' => [
                'required'          => $required,
                'validate_callback' => function ($value) {
                    return in_array($value, self::STATUSES, true);
                },
            ],
        ];
    }

    /**
     * Table name helper
     */
    private function table()
    {
        return SlotManager::slots_table();
    }

    /**
     * GET /slots/stats
     */
    public function get_stats()
    {
        global $wpdb;

        $table = $this->table();

        $row = $wpdb->get_row(
            "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) AS active,
                COALESCE(SUM(capacity), 0) AS total_capacity,
                COALESCE(SUM(booked), 0) AS total_booked
            FROM {$table}",
            ARRAY_A
        );

        return rest_ensure_response([
            'total'          => (int) ($row['total'] ?? 0),
            'active'         => (int) ($row['active'] ?? 0),
            'total_capacity' => (int) ($row['total_capacity'] ?? 0),
            'total_booked'   => (int) ($row['total_booked'] ?? 0),
        ]);
    }

    /**
     * GET /slots/check-conflict — whether a slot already exists for this
     * exact date + start time (used before creating/editing a slot).
     */
    public function check_conflict(\WP_REST_Request $request)
    {
        global $wpdb;

        $table      = $this->table();
        $slot_date  = $request->get_param('slot_date');
        $start_time = $request->get_param('start_time');
        $exclude_id = (int) $request->get_param('exclude_id');

        $sql    = "SELECT COUNT(*) FROM {$table} WHERE slot_date = %s AND start_time = %s";
        $values = [$slot_date, $start_time];

        if ($exclude_id) {
            $sql     .= ' AND id != %d';
            $values[] = $exclude_id;
        }

        $count = (int) $wpdb->get_var($wpdb->prepare($sql, $values));

        return rest_ensure_response(['conflict' => $count > 0]);
    }

    /**
     * GET /slots
     */
    public function get_items(\WP_REST_Request $request)
    {
        global $wpdb;

        $table    = $this->table();
        $search   = $request->get_param('search');
        $status   = $request->get_param('status');
        $page     = max(1, (int) $request->get_param('page'));
        $per_page = min(100, max(1, (int) $request->get_param('per_page')));
        $offset   = ($page - 1) * $per_page;

        $where  = ['1=1'];
        $values = [];

        if (! empty($search)) {
            $where[]  = 'slot_date LIKE %s';
            $values[] = '%' . $wpdb->esc_like($search) . '%';
        }

        if (! empty($status) && in_array($status, self::STATUSES, true)) {
            $where[]  = 'status = %s';
            $values[] = $status;
        }

        $where_sql = implode(' AND ', $where);

        $total_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
        $total     = (int) ($values ? $wpdb->get_var($wpdb->prepare($total_sql, $values)) : $wpdb->get_var($total_sql));

        $list_sql = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY slot_date DESC, start_time ASC LIMIT %d OFFSET %d";
        $items    = $wpdb->get_results($wpdb->prepare($list_sql, array_merge($values, [$per_page, $offset])), ARRAY_A);

        return rest_ensure_response([
            'items'       => array_map([$this, 'prepare_item'], $items ?: []),
            'total'       => $total,
            'total_pages' => (int) ceil($total / $per_page),
        ]);
    }

    /**
     * Normalize a DB row for the API response
     */
    private function prepare_item($row)
    {
        return [
            'id'         => (int) $row['id'],
            'slot_date'  => $row['slot_date'],
            'start_time' => substr($row['start_time'], 0, 5),
            'end_time'   => substr($row['end_time'], 0, 5),
            'capacity'   => (int) $row['capacity'],
            'booked'     => (int) $row['booked'],
            'price'      => (float) $row['price'],
            'status'     => $row['status'],
        ];
    }

    /**
     * POST /slots
     */
    public function create_item(\WP_REST_Request $request)
    {
        global $wpdb;

        $data = [
            'slot_date'  => $request->get_param('slot_date'),
            'start_time' => $request->get_param('start_time'),
            'end_time'   => $request->get_param('end_time'),
            'capacity'   => absint($request->get_param('capacity')),
            'booked'     => 0,
            'price'      => (float) $request->get_param('price'),
            'status'     => $request->get_param('status'),
        ];

        $inserted = $wpdb->insert($this->table(), $data);

        if (false === $inserted) {
            return new \WP_Error('dsw_insert_failed', __('Could not create the slot.', 'delivery-slots-for-woocommerce'), ['status' => 500]);
        }

        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table()} WHERE id = %d", $wpdb->insert_id), ARRAY_A);

        return rest_ensure_response($this->prepare_item($row));
    }

    /**
     * PUT /slots/{id}
     */
    public function update_item(\WP_REST_Request $request)
    {
        global $wpdb;

        $id    = (int) $request->get_param('id');
        $table = $this->table();

        $existing = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id), ARRAY_A);

        if (! $existing) {
            return new \WP_Error('dsw_not_found', __('Slot not found.', 'delivery-slots-for-woocommerce'), ['status' => 404]);
        }

        $capacity = absint($request->get_param('capacity'));

        if ($capacity < (int) $existing['booked']) {
            return new \WP_Error(
                'dsw_capacity_too_low',
                sprintf(
                    /* translators: %d: number of existing bookings */
                    __('Capacity cannot be lower than the %d order(s) already booked into this slot.', 'delivery-slots-for-woocommerce'),
                    (int) $existing['booked']
                ),
                ['status' => 400]
            );
        }

        $data = [
            'slot_date'  => $request->get_param('slot_date'),
            'start_time' => $request->get_param('start_time'),
            'end_time'   => $request->get_param('end_time'),
            'capacity'   => $capacity,
            'price'      => (float) $request->get_param('price'),
            'status'     => $request->get_param('status'),
        ];

        $updated = $wpdb->update($table, $data, ['id' => $id]);

        if (false === $updated) {
            return new \WP_Error('dsw_update_failed', __('Could not update the slot.', 'delivery-slots-for-woocommerce'), ['status' => 500]);
        }

        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id), ARRAY_A);

        return rest_ensure_response($this->prepare_item($row));
    }

    /**
     * DELETE /slots/{id}
     */
    public function delete_item(\WP_REST_Request $request)
    {
        global $wpdb;

        $id = (int) $request->get_param('id');

        $existing = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table()} WHERE id = %d", $id), ARRAY_A);

        if (! $existing) {
            return new \WP_Error('dsw_not_found', __('Slot not found.', 'delivery-slots-for-woocommerce'), ['status' => 404]);
        }

        if ((int) $existing['booked'] > 0) {
            return new \WP_Error(
                'dsw_has_bookings',
                __('This slot has active bookings and cannot be deleted. Set it to inactive or closed instead.', 'delivery-slots-for-woocommerce'),
                ['status' => 400]
            );
        }

        $wpdb->delete($this->table(), ['id' => $id]);

        return rest_ensure_response(['deleted' => true, 'id' => $id]);
    }

    /**
     * GET /slots/{id}/orders
     */
    public function get_slot_orders(\WP_REST_Request $request)
    {
        $slot_id  = (int) $request->get_param('id');
        $bookings = SlotManager::get_orders_for_slot($slot_id);

        $items = [];

        foreach ($bookings as $booking) {
            $order = wc_get_order($booking->order_id);

            if (! $order) {
                continue;
            }

            $customer = trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name());

            if (! $customer) {
                $customer = $order->get_billing_email();
            }

            $items[] = [
                'order_id'     => $order->get_id(),
                'order_number' => $order->get_order_number(),
                'status'       => $order->get_status(),
                'status_label' => wc_get_order_status_name($order->get_status()),
                'customer'     => $customer,
                'total'        => $order->get_formatted_order_total(),
                'date_created' => $order->get_date_created() ? $order->get_date_created()->date('Y-m-d H:i') : '',
                'edit_url'     => $order->get_edit_order_url(),
            ];
        }

        return rest_ensure_response(['items' => $items]);
    }
}
