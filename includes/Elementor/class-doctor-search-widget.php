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
        return __('جستجوی پیشرفته پزشکان وب‌تنان', 'webtanan-booking');
    }

    public function get_icon(): string {
        return 'eicon-search';
    }

    public function get_categories(): array {
        return array('general');
    }

    public function get_keywords(): array {
        return array('doctor', 'booking', 'appointment', 'webtanan', 'search');
    }

    public function get_style_depends(): array {
        return array('webtanan-booking-frontend');
    }

    public function get_script_depends(): array {
        return array('webtanan-booking-frontend');
    }

    protected function register_controls(): void {
        $this->start_controls_section(
            'content',
            array('label' => __('محتوا و مقدارهای پیش‌فرض', 'webtanan-booking'))
        );

        $this->add_control(
            'per_page',
            array(
                'label' => __('تعداد نتیجه', 'webtanan-booking'),
                'type' => \Elementor\Controls_Manager::NUMBER,
                'default' => 50,
                'min' => 1,
                'max' => 50,
            )
        );

        $this->add_control(
            'default_search',
            array(
                'label' => __('متن جستجوی پیش‌فرض', 'webtanan-booking'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => '',
            )
        );

        $this->add_control(
            'search_placeholder',
            array(
                'label' => __('متن راهنمای جستجو', 'webtanan-booking'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('نام پزشک، تخصص یا آدرس مطب', 'webtanan-booking'),
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
                'label' => __('شناسه استان پیش‌فرض', 'webtanan-booking'),
                'type' => \Elementor\Controls_Manager::NUMBER,
                'default' => 0,
                'min' => 0,
            )
        );

        $this->add_control(
            'city_id',
            array(
                'label' => __('شناسه شهر پیش‌فرض', 'webtanan-booking'),
                'type' => \Elementor\Controls_Manager::NUMBER,
                'default' => 0,
                'min' => 0,
            )
        );

        $this->add_control(
            'payment_filter',
            array(
                'label' => __('روش پرداخت پیش‌فرض', 'webtanan-booking'),
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
            'show_filters',
            array(
                'label' => __('نمایش فیلترها', 'webtanan-booking'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'return_value' => 'yes',
                'default' => 'yes',
            )
        );

        $this->add_control(
            'show_sort',
            array(
                'label' => __('نمایش مرتب‌سازی', 'webtanan-booking'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'return_value' => 'yes',
                'default' => 'yes',
            )
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'layout',
            array('label' => __('چیدمان', 'webtanan-booking'))
        );

        $this->add_control(
            'layout_mode',
            array(
                'label' => __('حالت نمایش نتیجه‌ها', 'webtanan-booking'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'default' => 'grid',
                'options' => array(
                    'grid' => __('شبکه‌ای', 'webtanan-booking'),
                    'list' => __('لیستی', 'webtanan-booking'),
                ),
            )
        );

        $this->add_control(
            'sort',
            array(
                'label' => __('مرتب‌سازی پیش‌فرض', 'webtanan-booking'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'default' => 'first_available',
                'options' => array(
                    'first_available' => __('نزدیک‌ترین نوبت آزاد', 'webtanan-booking'),
                    '' => __('پیش‌فرض وردپرس', 'webtanan-booking'),
                ),
            )
        );

        $this->end_controls_section();
    }

    protected function render(): void {
        $settings = $this->get_settings_for_display();

        echo do_shortcode(
            '[webtanan_booking_doctor_search per_page="' . absint($settings['per_page'] ?? 50) .
            '" default_search="' . esc_attr($settings['default_search'] ?? '') .
            '" search_placeholder="' . esc_attr($settings['search_placeholder'] ?? '') .
            '" specialty_id="' . absint($settings['specialty_id'] ?? 0) .
            '" province_id="' . absint($settings['province_id'] ?? 0) .
            '" city_id="' . absint($settings['city_id'] ?? 0) .
            '" payment_filter="' . esc_attr($settings['payment_filter'] ?? '') .
            '" sort="' . esc_attr($settings['sort'] ?? 'first_available') .
            '" layout="' . esc_attr($settings['layout_mode'] ?? 'grid') .
            '" show_filters="' . esc_attr($settings['show_filters'] ?? 'yes') .
            '" show_sort="' . esc_attr($settings['show_sort'] ?? 'yes') . '"]'
        );
    }
}
