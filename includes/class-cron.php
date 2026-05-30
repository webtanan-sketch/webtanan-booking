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
        add_action('webtanan_booking_hourly_cron', array(__CLASS__, 'hourly'));
    }

    public static function hourly(): void {
        Booking::expire_locks();
        do_action('webtanan_booking_send_reminders');
    }
}
