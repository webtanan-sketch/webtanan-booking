<?php
/**
 * Elementor booking calendar widget.
 *
 * @package WebtananBooking
 */

namespace Webtanan\Booking\Elementor;

defined('ABSPATH') || exit;

final class Booking_Calendar_Widget extends \Elementor\Widget_Base {
    public function get_name(): string {
        return 'webtanan_booking_calendar';
    }

    public function get_title(): string {
        return __('تقویم نوبت‌دهی وب‌تنان', 'webtanan-booking');
    }

    public function get_icon(): string {
        return 'eicon-calendar';
    }

    public function get_categories(): array {
        return array('general');
    }

    protected function register_controls(): void {
        $this->start_controls_section('content', array('label' => __('محتوا', 'webtanan-booking')));
        $this->add_control(
            'doctor_id',
            array(
                'label' => __('شناسه پزشک', 'webtanan-booking'),
                'type' => \Elementor\Controls_Manager::NUMBER,
                'default' => 0,
                'min' => 0,
            )
        );
        $this->end_controls_section();
    }

    protected function render(): void {
        $settings = $this->get_settings_for_display();
        echo do_shortcode('[webtanan_booking_calendar doctor_id="' . absint($settings['doctor_id'] ?? 0) . '"]');
    }
}
