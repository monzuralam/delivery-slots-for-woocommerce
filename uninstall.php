<?php

// If uninstall is not called from WordPress, exit.
if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

require_once __DIR__ . '/vendor/autoload.php';

// Respect the "Delete data on uninstall" setting (Settings > Tools) — off by
// default, so uninstalling the plugin never destroys data unless the store
// owner explicitly opted in.
if (! dsw_get_settings('delete_data_on_uninstall', false)) {
    return;
}

global $wpdb;

$dsw_tables = ['dsw_slots', 'dsw_bookings'];

foreach ($dsw_tables as $table) {
    $wpdb->query('DROP TABLE IF EXISTS `' . $wpdb->prefix . esc_sql($table) . '`');
}

$dsw_options = [
	'dsw_settings',
	'dsw_version',
	'dsw_db_version',
	'dsw_install_time',
];

foreach ($dsw_options as $option) {
	delete_option($option);
}

wp_clear_scheduled_hook('dsw_cleanup_stale_holds');
