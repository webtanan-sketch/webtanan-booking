<?php
/**
 * Elementor next available appointment widget.
 *
 * @package WebtananBooking
 */

namespace Webtanan\Booking\Elementor;

defined('ABSPATH') || exit;

final class Next_Available_Widget extends \Elementor\Widget_Base {
    public function get_name(): string {
        return 'webtanan_next_available';
    }

    public function get_title(): string {
        return __('اولین نوبت آزاد وب‌تنان', 'webtanan-booking');
    }

    public function get_icon(): string {
        return 'eicon-clock-o';
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
        echo do_shortcode('[webtanan_booking_next_available doctor_id="' . absint($settings['doctor_id'] ?? 0) . '"]');
    }
}
