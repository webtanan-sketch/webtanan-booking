<?php
/**
 * Uninstall handler.
 *
 * Financial and appointment data is preserved by default. To drop plugin data on
 * uninstall, set option webtanan_booking_delete_data_on_uninstall to yes before
 * uninstalling.
 *
 * @package WebtananBooking
 */

defined('WP_UNINSTALL_PLUGIN') || exit;

global $wpdb;

$delete_data = get_option('webtanan_booking_delete_data_on_uninstall', 'no');

delete_option('webtanan_booking_settings');
delete_option('webtanan_booking_db_version');

if ('yes' !== $delete_data) {
    return;
}

$tables = array(
    'saas_sms_logs',
    'saas_otp_logs',
    'saas_settlement_requests',
    'saas_wallets_ledger',
    'saas_transactions',
    'saas_appointments',
    'saas_schedule_exceptions',
    'saas_schedules',
    'saas_specialties',
    'saas_doctors',
);

foreach ($tables as $table) {
    $wpdb->query('DROP TABLE IF EXISTS ' . $wpdb->prefix . $table);
}
