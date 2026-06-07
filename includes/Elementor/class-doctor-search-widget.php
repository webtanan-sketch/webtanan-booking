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

    protected function register_controls(): void {
        $this->start_controls_section('content', array('label' => __('محتوا', 'webtanan-booking')));
        $this->add_control(
            'per_page',
            array(
                'label' => __('تعداد نتیجه', 'webtanan-booking'),
                'type' => \Elementor\Controls_Manager::NUMBER,
                'default' => 12,
                'min' => 1,
                'max' => 50,
            )
        );
        $this->add_control(
            'specialty_id',
            array(
                'label' => __('شناسه تخصص پیش‌فرض', 'webtanan-booking'),
                'type' => \Elementor\Controls_Manager::NUMBER,
                'default' => 0,
                'min' => 0,
            )
        );
        $this->add_control(
            'province_id',
            array(
                'label' => __('شناسه استان', 'webtanan-booking'),
                'type' => \Elementor\Controls_Manager::NUMBER,
                'default' => 0,
                'min' => 0,
            )
        );
        $this->add_control(
            'city_id',
            array(
                'label' => __('شناسه شهر', 'webtanan-booking'),
                'type' => \Elementor\Controls_Manager::NUMBER,
                'default' => 0,
                'min' => 0,
            )
        );
        $this->add_control(
            'payment_filter',
            array(
                'label' => __('روش پرداخت', 'webtanan-booking'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'default' => '',
                'options' => array(
                    '' => __('همه روش‌ها', 'webtanan-booking'),
                    'online' => __('پرداخت آنلاین', 'webtanan-booking'),
                    'clinic' => __('پرداخت در مطب', 'webtanan-booking'),
                ),
            )
        );
        $this->add_control(
            'sort',
            array(
                'label' => __('مرتب‌سازی', 'webtanan-booking'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'default' => '',
                'options' => array(
                    '' => __('پیش‌فرض', 'webtanan-booking'),
                    'first_available' => __('نزدیک‌ترین نوبت آزاد', 'webtanan-booking'),
                ),
            )
        );
        $this->add_control(
            'show_filters',
            array(
                'label' => __('نمایش فیلترهای تخصص و پرداخت', 'webtanan-booking'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'return_value' => 'yes',
                'default' => 'yes',
            )
        );
        $this->end_controls_section();
    }

    protected function render(): void {
        $settings = $this->get_settings_for_display();
        echo do_shortcode(
            '[webtanan_booking_doctor_search per_page="' . absint($settings['per_page'] ?? 12) .
            '" specialty_id="' . absint($settings['specialty_id'] ?? 0) .
            '" province_id="' . absint($settings['province_id'] ?? 0) .
            '" city_id="' . absint($settings['city_id'] ?? 0) .
            '" payment_filter="' . esc_attr($settings['payment_filter'] ?? '') .
            '" sort="' . esc_attr($settings['sort'] ?? '') .
            '" show_filters="' . esc_attr($settings['show_filters'] ?? 'yes') . '"]'
        );
    }
}
