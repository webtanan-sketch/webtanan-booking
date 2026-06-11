<?php
/**
 * Elementor doctor card widget.
 *
 * @package WebtananBooking
 */

namespace Webtanan\Booking\Elementor;

defined('ABSPATH') || exit;

final class Doctor_Card_Widget extends \Elementor\Widget_Base {
    public function get_name(): string {
        return 'webtanan_doctor_card';
    }

    public function get_title(): string {
        return __('کارت پزشک وب‌تنان', 'webtanan-booking');
    }

    public function get_icon(): string {
        return 'eicon-person';
    }

    public function get_categories(): array {
        return array('general');
    }

    public function get_style_depends(): array {
        return array('webtanan-booking-frontend');
    }

    public function get_script_depends(): array {
        return array('webtanan-booking-frontend');
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
        echo do_shortcode('[webtanan_booking_doctor_card doctor_id="' . absint($settings['doctor_id'] ?? 0) . '"]');
    }
}
