<?php

namespace DSW;

/**
 * Manager Class
 */
class Install
{
	/**
	 * Activate plugin
	 * @since 1.0.0
	 */
    public static function activate()
    {
        self::create_tables();
        self::create_default_data();

        if (! wp_next_scheduled('dsw_cleanup_stale_holds')) {
            wp_schedule_event(time() + 300, 'hourly', 'dsw_cleanup_stale_holds');
        }
    }

    /**
     * Deactivate plugin
     * @since 1.0.0
     */
    public static function deactivate()
    {
        $timestamp = wp_next_scheduled('dsw_cleanup_stale_holds');

        if ($timestamp) {
            wp_unschedule_event($timestamp, 'dsw_cleanup_stale_holds');
        }
    }

    /**
     * Creates the slots/bookings tables directly with ENGINE=InnoDB.
     *
     * dbDelta() does not reliably honour ENGINE clauses across MySQL/MariaDB
     * versions, and InnoDB is required for the transactional row locking
     * (SELECT ... FOR UPDATE) that SlotManager::reserve_slot() depends on to
     * stay overselling-safe under concurrent checkouts.
     */
    private static function create_tables()
    {
        global $wpdb;

        $wpdb->hide_errors();

		$slots_table 	= $wpdb->prefix . 'dsw_slots';
		$bookings_table = $wpdb->prefix . 'dsw_bookings';

        $charset_collate = $wpdb->get_charset_collate();

        $tables = [
			"CREATE TABLE IF NOT EXISTS {$slots_table} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				slot_date DATE NOT NULL,
				start_time TIME NOT NULL,
				end_time TIME NOT NULL,
				capacity INT UNSIGNED NOT NULL DEFAULT 0,
				booked INT UNSIGNED NOT NULL DEFAULT 0,
				price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
				status VARCHAR(20) NOT NULL DEFAULT 'active',
				created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				PRIMARY KEY  (id),
				KEY slot_date (slot_date),
				KEY status (status)
			) ENGINE=InnoDB {$charset_collate};",

			"CREATE TABLE IF NOT EXISTS {$bookings_table} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				slot_id BIGINT UNSIGNED NOT NULL,
				order_id BIGINT UNSIGNED NOT NULL,
				status VARCHAR(20) NOT NULL DEFAULT 'reserved',
				created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				PRIMARY KEY  (id),
				UNIQUE KEY order_id (order_id),
				KEY slot_id (slot_id),
				KEY status (status)
			) ENGINE=InnoDB {$charset_collate};",
        ];

        foreach ($tables as $table) {
            $wpdb->query($table);
        }
    }

    /**
     * Create plugin settings default data
     *
     * @since 1.0.0
     */
    private static function create_default_data()
    {
        if (! get_option('dsw_version')) {
            update_option('dsw_version', DSW_VERSION);
        }

        if (! get_option('dsw_db_version')) {
            update_option('dsw_db_version', DSW_DB_VERSION);
        }

        if (! get_option('dsw_install_time')) {
            update_option('dsw_install_time', current_time('mysql'));
        }
    }
}
