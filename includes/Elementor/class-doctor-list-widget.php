<?php
/**
 * Elementor doctor list widget.
 *
 * @package WebtananBooking
 */

namespace Webtanan\Booking\Elementor;

defined('ABSPATH') || exit;

final class Doctor_List_Widget extends \Elementor\Widget_Base {
    public function get_name(): string {
        return 'webtanan_doctor_list';
    }

    public function get_title(): string {
        return __('لیست پزشکان وب‌تنان', 'webtanan-booking');
    }

    public function get_icon(): string {
        return 'eicon-post-list';
    }

    public function get_categories(): array {
        return array('general');
    }

    protected function register_controls(): void {
        $this->start_controls_section('content', array('label' => __('محتوا', 'webtanan-booking')));
        $this->add_control(
            'per_page',
            array(
                'label' => __('تعداد پزشک در هر صفحه', 'webtanan-booking'),
                'type' => \Elementor\Controls_Manager::NUMBER,
                'default' => 12,
                'min' => 1,
                'max' => 50,
            )
        );
        $this->add_control(
            'specialty_id',
            array(
                'label' => __('شناسه تخصص', 'webtanan-booking'),
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
            'online',
            array(
                'label' => __('فقط پرداخت آنلاین', 'webtanan-booking'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'return_value' => '1',
                'default' => '',
            )
        );
        $this->add_control(
            'pay_at_clinic',
            array(
                'label' => __('فقط پرداخت در مطب', 'webtanan-booking'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'return_value' => '1',
                'default' => '',
            )
        );
        $this->end_controls_section();
    }

    protected function render(): void {
        $settings = $this->get_settings_for_display();
        echo do_shortcode(
            '[webtanan_booking_doctor_list per_page="' . absint($settings['per_page'] ?? 12) .
            '" specialty_id="' . absint($settings['specialty_id'] ?? 0) .
            '" province_id="' . absint($settings['province_id'] ?? 0) .
            '" city_id="' . absint($settings['city_id'] ?? 0) .
            '" payment_filter="' . esc_attr($settings['payment_filter'] ?? '') .
            '" sort="' . esc_attr($settings['sort'] ?? '') .
            '" online="' . esc_attr($settings['online'] ?? '') .
            '" pay_at_clinic="' . esc_attr($settings['pay_at_clinic'] ?? '') . '"]'
        );
    }
}
