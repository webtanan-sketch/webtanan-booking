<?php
/**
 * Elementor integration loader.
 *
 * @package WebtananBooking
 */

namespace Webtanan\Booking;

defined('ABSPATH') || exit;

final class Elementor_Integration {
    public static function init(): void {
        if (!did_action('elementor/loaded')) {
            return;
        }

        add_action('elementor/widgets/register', array(__CLASS__, 'register_widgets'));
    }

    public static function register_widgets($widgets_manager): void {
        if (!class_exists('\Elementor\Widget_Base')) {
            return;
        }

        require_once WEBTANAN_BOOKING_PATH . 'includes/Elementor/class-doctor-search-widget.php';
        require_once WEBTANAN_BOOKING_PATH . 'includes/Elementor/class-doctor-list-widget.php';
        require_once WEBTANAN_BOOKING_PATH . 'includes/Elementor/class-doctor-card-widget.php';
        require_once WEBTANAN_BOOKING_PATH . 'includes/Elementor/class-booking-calendar-widget.php';
        require_once WEBTANAN_BOOKING_PATH . 'includes/Elementor/class-next-available-widget.php';

        $widgets = array(
            new \Webtanan\Booking\Elementor\Doctor_Search_Widget(),
            new \Webtanan\Booking\Elementor\Doctor_List_Widget(),
            new \Webtanan\Booking\Elementor\Doctor_Card_Widget(),
            new \Webtanan\Booking\Elementor\Booking_Calendar_Widget(),
            new \Webtanan\Booking\Elementor\Next_Available_Widget(),
        );

        foreach ($widgets as $widget) {
            if (method_exists($widgets_manager, 'register')) {
                $widgets_manager->register($widget);
            } elseif (method_exists($widgets_manager, 'register_widget_type')) {
                $widgets_manager->register_widget_type($widget);
            }
        }
    }
}
