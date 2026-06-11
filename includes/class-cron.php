<?php
/**
 * Cron jobs.
 *
 * @package WebtananBooking
 */

namespace Webtanan\Booking;

defined('ABSPATH') || exit;

final class Cron {
    public static function init(): void {
        add_filter('cron_schedules', array(__CLASS__, 'schedules'));
        add_action('webtanan_booking_hourly_cron', array(__CLASS__, 'hourly'));
        add_action('webtanan_booking_15m_cron', array(__CLASS__, 'every_15_minutes'));
        self::ensure_events();
    }

    public static function schedules(array $schedules): array {
        $schedules['webtanan_booking_15_minutes'] = array(
            'interval' => 15 * MINUTE_IN_SECONDS,
            'display' => __('هر ۱۵ دقیقه - وب‌تنان بوکینگ', 'webtanan-booking'),
        );

        return $schedules;
    }

    public static function ensure_events(): void {
        if (!wp_next_scheduled('webtanan_booking_hourly_cron')) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'hourly', 'webtanan_booking_hourly_cron');
        }

        if (!wp_next_scheduled('webtanan_booking_15m_cron')) {
            wp_schedule_event(time() + (15 * MINUTE_IN_SECONDS), 'webtanan_booking_15_minutes', 'webtanan_booking_15m_cron');
        }
    }

    public static function hourly(): void {
        Booking::expire_locks();
        do_action('webtanan_booking_send_reminders');
    }

    public static function every_15_minutes(): void {
        Booking::expire_locks();
        do_action('webtanan_booking_send_waiting_list_messages');
        do_action('webtanan_booking_send_survey_requests');
    }
}
