<?php
/**
 * Elementor specialty list widget.
 *
 * @package WebtananBooking
 */

namespace Webtanan\Booking\Elementor;

defined('ABSPATH') || exit;

final class Specialty_List_Widget extends \Elementor\Widget_Base {
    public function get_name(): string { return 'webtanan_specialty_list'; }
    public function get_title(): string { return __('تخصص‌های وب‌تنان', 'webtanan-booking'); }
    public function get_icon(): string { return 'eicon-tags'; }
    public function get_categories(): array { return array('general'); }
    public function get_style_depends(): array { return array('webtanan-booking-frontend'); }

    protected function register_controls(): void {
        $this->start_controls_section('content', array('label' => __('نمایش تخصص‌ها', 'webtanan-booking')));
        $this->add_control('limit', array('label' => __('تعداد', 'webtanan-booking'), 'type' => \Elementor\Controls_Manager::NUMBER, 'default' => 8, 'min' => 1, 'max' => 50));
        $this->add_control('layout', array('label' => __('جای تصویر', 'webtanan-booking'), 'type' => \Elementor\Controls_Manager::SELECT, 'default' => 'top', 'options' => array('top' => __('بالا', 'webtanan-booking'), 'right' => __('راست', 'webtanan-booking'), 'left' => __('چپ', 'webtanan-booking'))));
        $this->add_control('columns', array('label' => __('تعداد ستون', 'webtanan-booking'), 'type' => \Elementor\Controls_Manager::NUMBER, 'default' => 4, 'min' => 2, 'max' => 6));
        $this->add_control('show_icon', array('label' => __('نمایش تصویر', 'webtanan-booking'), 'type' => \Elementor\Controls_Manager::SWITCHER, 'return_value' => 'yes', 'default' => 'yes'));
        $this->add_control('show_count', array('label' => __('نمایش تعداد پزشکان', 'webtanan-booking'), 'type' => \Elementor\Controls_Manager::SWITCHER, 'return_value' => 'yes', 'default' => 'yes'));
        $this->end_controls_section();
    }

    protected function render(): void {
        $settings = $this->get_settings_for_display();
        echo do_shortcode('[webtanan_booking_specialty_list limit="' . absint($settings['limit'] ?? 8) . '" layout="' . esc_attr($settings['layout'] ?? 'top') . '" columns="' . absint($settings['columns'] ?? 4) . '" show_icon="' . esc_attr($settings['show_icon'] ?? 'yes') . '" show_count="' . esc_attr($settings['show_count'] ?? 'yes') . '"]');
    }
}
