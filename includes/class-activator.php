<?php
/**
 * Plugin activation and deactivation.
 *
 * @package WebtananBooking
 */

namespace Webtanan\Booking;

defined('ABSPATH') || exit;

final class Activator {
    public static function activate(): void {
        DB::create_tables();
        Roles::add_roles();
        self::seed_specialties();

        if (!get_option(DB::OPTION_SETTINGS)) {
            add_option(DB::OPTION_SETTINGS, DB::default_settings());
        }

        if (!wp_next_scheduled('webtanan_booking_hourly_cron')) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'hourly', 'webtanan_booking_hourly_cron');
        }

        flush_rewrite_rules();
    }

    public static function deactivate(): void {
        wp_clear_scheduled_hook('webtanan_booking_hourly_cron');
        flush_rewrite_rules();
    }

    private static function seed_specialties(): void {
        global $wpdb;

        $table = DB::table('specialties');
        $exists = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table");
        if ($exists > 0) {
            return;
        }

        $now = DB::now();
        $items = array(
            'قلب و عروق',
            'داخلی',
            'پوست و مو',
            'زنان و زایمان',
            'اطفال',
            'ارتوپدی',
            'دندانپزشکی',
            'روانپزشکی',
        );

        foreach ($items as $index => $name) {
            $wpdb->insert(
                $table,
                array(
                    'name' => $name,
                    'slug' => sanitize_title($name),
                    'parent_id' => 0,
                    'is_active' => 1,
                    'sort_order' => $index + 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ),
                array('%s', '%s', '%d', '%d', '%d', '%s', '%s')
            );
        }
    }
}
