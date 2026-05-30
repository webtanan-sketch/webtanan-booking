<?php
/**
 * Main plugin bootstrapper.
 *
 * @package WebtananBooking
 */

namespace Webtanan\Booking;

defined('ABSPATH') || exit;

final class Plugin {
    /**
     * Singleton instance.
     *
     * @var Plugin|null
     */
    private static $instance = null;

    /**
     * Prevent duplicate boot.
     *
     * @var bool
     */
    private $booted = false;

    public static function instance(): Plugin {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function boot(): void {
        if ($this->booted) {
            return;
        }

        $this->booted = true;

        load_plugin_textdomain('webtanan-booking', false, dirname(plugin_basename(WEBTANAN_BOOKING_FILE)) . '/languages');

        if (get_option(DB::OPTION_DB_VERSION) !== WEBTANAN_BOOKING_VERSION) {
            DB::create_tables();
        }

        Post_Types::init();
        Admin::init();
        REST::init();
        Frontend::init();
        Cron::init();
        SMS::init();
        Elementor_Integration::init();
    }
}
