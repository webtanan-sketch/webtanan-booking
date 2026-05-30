<?php
/**
 * Elementor doctor search widget.
 *
 * @package WebtananBooking
 */

namespace Webtanan\Booking\Elementor;

defined('ABSPATH') || exit;

final class Doctor_Search_Widget extends \Elementor\Widget_Base {
    public function get_name(): string {
        return 'webtanan_doctor_search';
    }

    public function get_title(): string {
        return __('جستجوی پزشکان وب‌تنان', 'webtanan-booking');
    }

    public function get_icon(): string {
        return 'eicon-search';
    }

    public function get_categories(): array {
        return array('general');
    }

    protected function render(): void {
        echo do_shortcode('[webtanan_booking_doctor_search]');
    }
}
