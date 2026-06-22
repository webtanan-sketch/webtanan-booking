<?php
/**
 * Role-aware sample sidebar renderer.
 *
 * @package WebtananBooking
 */

namespace Webtanan\Booking;

defined('ABSPATH') || exit;

final class Sidebar_Menu {
    public static function context_for_current_user(): string {
        if (current_user_can('manage_options') || current_user_can('webtanan_manage_booking')) {
            return 'admin';
        }

        $user = wp_get_current_user();
        $roles = (array) ($user->roles ?? array());

        if (in_array('webtanan_secretary', $roles, true)) {
            return 'secretary';
        }

        if (in_array('webtanan_doctor', $roles, true)) {
            return 'doctor';
        }

        return 'patient';
    }

    public static function render(string $context = '', string $active = '', array $args = array()): string {
        $context = $context ? sanitize_key($context) : self::context_for_current_user();
        $active = $active ? sanitize_key($active) : self::default_active($context);
        $items = self::items($context);
        $badges = isset($args['badges']) && is_array($args['badges']) ? $args['badges'] : array();
        $id = !empty($args['id']) ? sanitize_html_class((string) $args['id']) : 'sidebar';

        ob_start();
        ?>
        <aside class="sidebar wb-sidebar wb-nav" id="<?php echo esc_attr($id); ?>">
            <?php foreach ($items as $item) : ?>
                <?php if (($item['type'] ?? 'item') === 'divider') : ?>
                    <div class="sidebar-divider"></div>
                    <?php if (!empty($item['label'])) : ?>
                        <div class="sidebar-label"><?php echo esc_html($item['label']); ?></div>
                    <?php endif; ?>
                    <?php continue; ?>
                <?php endif; ?>

                <?php
                if (!self::item_allowed($item)) {
                    continue;
                }

                $key = sanitize_key((string) ($item['key'] ?? ''));
                $view = sanitize_key((string) ($item['view'] ?? $key));
                $is_active = $active === $key || $active === $view;
                $classes = array('nav-item', 'wb-nav-item');
                if ($is_active) {
                    $classes[] = 'active';
                    $classes[] = 'is-active';
                }
                if (!empty($item['logout'])) {
                    $classes[] = 'wb-logout';
                }
                $badge_value = $badges[$key] ?? ($item['badge'] ?? '');
                $badge_tone = sanitize_html_class((string) ($item['badge_tone'] ?? 'info'));
                $url = !empty($item['url']) ? esc_url($item['url']) : '';
                $tag = $url ? 'a' : 'div';
                ?>
                <<?php echo tag_escape($tag); ?>
                    class="<?php echo esc_attr(implode(' ', $classes)); ?>"
                    <?php if ($url) : ?>href="<?php echo esc_url($url); ?>"<?php endif; ?>
                    <?php if (!$url) : ?>role="button" tabindex="0"<?php endif; ?>
                    data-wb-view="<?php echo esc_attr($view); ?>"
                    data-menu-key="<?php echo esc_attr($key); ?>">
                    <i class="<?php echo esc_attr((string) ($item['icon'] ?? 'fas fa-circle')); ?>" aria-hidden="true"></i>
                    <?php echo esc_html((string) ($item['label'] ?? '')); ?>
                    <?php if ('' !== (string) $badge_value) : ?>
                        <span class="badge badge-<?php echo esc_attr($badge_tone); ?>"><?php echo esc_html((string) $badge_value); ?></span>
                    <?php endif; ?>
                </<?php echo tag_escape($tag); ?>>
            <?php endforeach; ?>
        </aside>
        <?php

        return trim((string) ob_get_clean());
    }

    public static function echo(string $context = '', string $active = '', array $args = array()): void {
        echo self::render($context, $active, $args); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    public static function items(string $context): array {
        $context = sanitize_key($context);

        if ('admin' === $context) {
            return self::admin_items();
        }

        if ('doctor' === $context) {
            return self::doctor_items(false);
        }

        if ('secretary' === $context) {
            return self::doctor_items(true);
        }

        return self::patient_items();
    }

    private static function default_active(string $context): string {
        if ('doctor' === $context || 'secretary' === $context || 'admin' === $context) {
            return 'today';
        }

        return 'patient-overview';
    }

    private static function patient_items(): array {
        return array(
            array('key' => 'patient-overview', 'view' => 'patient-overview', 'icon' => 'fas fa-home', 'label' => __('داشبورد', 'webtanan-booking')),
            array('key' => 'doctor-search', 'view' => 'doctor-search', 'icon' => 'fas fa-user-md', 'label' => __('پزشکان', 'webtanan-booking'), 'url' => self::doctors_archive_url()),
            array('key' => 'patient-appointments', 'view' => 'patient-appointments', 'icon' => 'fas fa-calendar-check', 'label' => __('نوبت‌های من', 'webtanan-booking'), 'badge_tone' => 'success'),
            array('key' => 'patient-history', 'view' => 'patient-history', 'icon' => 'fas fa-history', 'label' => __('تاریخچه', 'webtanan-booking')),
            array('key' => 'patient-records', 'view' => 'patient-records', 'icon' => 'fas fa-prescription', 'label' => __('نسخه‌ها و پرونده', 'webtanan-booking')),
            array('type' => 'divider', 'label' => __('حساب کاربری', 'webtanan-booking')),
            array('key' => 'patient-wallet', 'view' => 'patient-wallet', 'icon' => 'fas fa-wallet', 'label' => __('کیف پول', 'webtanan-booking')),
            array('key' => 'patient-favorites', 'view' => 'patient-favorites', 'icon' => 'fas fa-heart', 'label' => __('پزشکان منتخب', 'webtanan-booking')),
            array('key' => 'patient-profile', 'view' => 'patient-profile', 'icon' => 'fas fa-user-circle', 'label' => __('پروفایل', 'webtanan-booking')),
            array('key' => 'help', 'view' => 'help', 'icon' => 'fas fa-question-circle', 'label' => __('راهنما', 'webtanan-booking')),
            array('key' => 'logout', 'view' => 'logout', 'icon' => 'fas fa-sign-out-alt', 'label' => __('خروج', 'webtanan-booking'), 'logout' => true),
        );
    }

    private static function doctor_items(bool $secretary = false): array {
        $items = array(
            array('key' => 'today', 'view' => 'today', 'icon' => 'fas fa-chart-pie', 'label' => __('داشبورد', 'webtanan-booking')),
            array('key' => 'appointments', 'view' => 'calendar', 'icon' => 'fas fa-calendar-check', 'label' => __('نوبت‌ها', 'webtanan-booking'), 'badge_tone' => 'success'),
            array('key' => 'patients', 'view' => 'patients', 'icon' => 'fas fa-users', 'label' => __('بیماران', 'webtanan-booking')),
            array('key' => 'records', 'view' => 'records', 'icon' => 'fas fa-file-prescription', 'label' => __('پرونده بیماران', 'webtanan-booking')),
            array('key' => 'calendar', 'view' => 'calendar', 'icon' => 'fas fa-clock', 'label' => __('زمان‌بندی', 'webtanan-booking')),
            array('key' => 'schedule', 'view' => 'schedule', 'icon' => 'fas fa-calendar-alt', 'label' => __('برنامه نوبت‌دهی', 'webtanan-booking')),
            array('key' => 'exceptions', 'view' => 'exceptions', 'icon' => 'fas fa-calendar-plus', 'label' => __('روزهای خاص', 'webtanan-booking')),
            array('type' => 'divider', 'label' => __('مدیریت', 'webtanan-booking')),
            array('key' => 'profile', 'view' => 'profile', 'icon' => 'fas fa-user-md', 'label' => __('پروفایل', 'webtanan-booking')),
            array('key' => 'wallet', 'view' => 'wallet', 'icon' => 'fas fa-file-invoice', 'label' => __('صورتحساب‌ها', 'webtanan-booking'), 'finance' => true),
            array('key' => 'settlements', 'view' => 'wallet', 'icon' => 'fas fa-money-check-alt', 'label' => __('تسویه حساب', 'webtanan-booking'), 'finance' => true),
            array('key' => 'settings', 'view' => 'settings', 'icon' => 'fas fa-cog', 'label' => __('تنظیمات', 'webtanan-booking'), 'doctor_only' => true),
            array('key' => 'logout', 'view' => 'logout', 'icon' => 'fas fa-sign-out-alt', 'label' => __('خروج', 'webtanan-booking'), 'logout' => true),
        );

        if ($secretary) {
            $items = array_values(
                array_filter(
                    $items,
                    static function (array $item): bool {
                        if (!empty($item['doctor_only'])) {
                            return false;
                        }

                        if (!empty($item['finance']) && !self::secretary_can_view_finance()) {
                            return false;
                        }

                        return true;
                    }
                )
            );
        }

        return $items;
    }

    private static function admin_items(): array {
        return array_merge(
            self::doctor_items(false),
            array(
                array('type' => 'divider', 'label' => __('مدیریت کل', 'webtanan-booking')),
                array('key' => 'admin-doctors', 'view' => 'admin-doctors', 'icon' => 'fas fa-user-md', 'label' => __('مدیریت پزشکان', 'webtanan-booking'), 'url' => admin_url('admin.php?page=webtanan-booking-doctors')),
                array('key' => 'admin-appointments', 'view' => 'admin-appointments', 'icon' => 'fas fa-calendar-check', 'label' => __('همه نوبت‌ها', 'webtanan-booking'), 'url' => admin_url('admin.php?page=webtanan-booking-appointments')),
                array('key' => 'admin-finance', 'view' => 'admin-finance', 'icon' => 'fas fa-chart-line', 'label' => __('گزارش مالی', 'webtanan-booking'), 'url' => admin_url('admin.php?page=webtanan-booking-financial-reports')),
            )
        );
    }

    private static function item_allowed(array $item): bool {
        if (!empty($item['capability']) && !current_user_can((string) $item['capability'])) {
            return false;
        }

        return true;
    }

    private static function secretary_can_view_finance(): bool {
        $user_id = get_current_user_id();
        if (!$user_id) {
            return false;
        }

        return (bool) get_user_meta($user_id, 'webtanan_secretary_can_view_finance', true);
    }

    private static function doctors_archive_url(): string {
        return get_post_type_archive_link('saas_doctors') ?: add_query_arg('post_type', 'saas_doctors', home_url('/'));
    }
}
