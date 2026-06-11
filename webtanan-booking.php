<?php
/**
 * Plugin Name: Webtanan Booking
 * Plugin URI: https://webtanan.com/
 * Description: SaaS doctor appointment booking for WordPress with CPT doctor profiles and operational custom tables.
 * Version: 1.2.4
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Webtanan
 * Text Domain: webtanan-booking
 * Domain Path: /languages
 *
 * @package WebtananBooking
 */

defined('ABSPATH') || exit;

define('WEBTANAN_BOOKING_VERSION', '1.2.4');
define('WEBTANAN_BOOKING_FILE', __FILE__);
define('WEBTANAN_BOOKING_PATH', plugin_dir_path(__FILE__));
define('WEBTANAN_BOOKING_URL', plugin_dir_url(__FILE__));

require_once WEBTANAN_BOOKING_PATH . 'includes/class-db.php';
require_once WEBTANAN_BOOKING_PATH . 'includes/class-roles.php';
require_once WEBTANAN_BOOKING_PATH . 'includes/class-activator.php';
require_once WEBTANAN_BOOKING_PATH . 'includes/class-plugin.php';
require_once WEBTANAN_BOOKING_PATH . 'includes/class-post-types.php';
require_once WEBTANAN_BOOKING_PATH . 'includes/class-admin.php';
require_once WEBTANAN_BOOKING_PATH . 'includes/class-aqayepardakht-gateway.php';
require_once WEBTANAN_BOOKING_PATH . 'includes/class-payment-gateways.php';
require_once WEBTANAN_BOOKING_PATH . 'includes/class-booking.php';
require_once WEBTANAN_BOOKING_PATH . 'includes/class-wallet.php';
require_once WEBTANAN_BOOKING_PATH . 'includes/class-otp.php';
require_once WEBTANAN_BOOKING_PATH . 'includes/class-ippanel-sms-service.php';
require_once WEBTANAN_BOOKING_PATH . 'includes/class-sms.php';
require_once WEBTANAN_BOOKING_PATH . 'includes/class-rest.php';
require_once WEBTANAN_BOOKING_PATH . 'includes/class-frontend.php';
require_once WEBTANAN_BOOKING_PATH . 'includes/class-cron.php';
require_once WEBTANAN_BOOKING_PATH . 'includes/class-seeder.php';
require_once WEBTANAN_BOOKING_PATH . 'includes/Elementor/class-elementor-integration.php';

register_activation_hook(__FILE__, array('\Webtanan\Booking\Activator', 'activate'));
register_deactivation_hook(__FILE__, array('\Webtanan\Booking\Activator', 'deactivate'));

add_action(
    'plugins_loaded',
    static function () {
        \Webtanan\Booking\Plugin::instance()->boot();
    }
);
