<?php
/**
 * WordPress admin screens.
 *
 * @package WebtananBooking
 */

namespace Webtanan\Booking;

defined('ABSPATH') || exit;

final class Admin {
    private const NOTICE_TRANSIENT = 'webtanan_booking_admin_notice';

    public static function init(): void {
        add_action('admin_menu', array(__CLASS__, 'register_menu'));
        add_action('admin_menu', array(__CLASS__, 'register_survey_menu'), 11);
        add_action('admin_init', array(__CLASS__, 'register_settings'));
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_admin_assets'));
        add_action('wp_ajax_webtanan_booking_user_search', array(__CLASS__, 'ajax_user_search'));
        add_filter('upload_mimes', array(__CLASS__, 'allow_font_upload_mimes'));

        add_action('admin_post_webtanan_booking_save_doctor', array(__CLASS__, 'handle_save_doctor'));
        add_action('admin_post_webtanan_booking_save_secretary_assignment', array(__CLASS__, 'handle_save_secretary_assignment'));
        add_action('admin_post_webtanan_booking_save_specialty', array(__CLASS__, 'handle_save_specialty'));
        add_action('admin_post_webtanan_booking_save_schedule', array(__CLASS__, 'handle_save_schedule'));
        add_action('admin_post_webtanan_booking_save_exception', array(__CLASS__, 'handle_save_exception'));
        add_action('admin_post_webtanan_booking_create_admin_appointment', array(__CLASS__, 'handle_create_admin_appointment'));
        add_action('admin_post_webtanan_booking_update_admin_appointment', array(__CLASS__, 'handle_update_admin_appointment'));
        add_action('admin_post_webtanan_booking_bulk_appointment_action', array(__CLASS__, 'handle_bulk_appointment_action'));
        add_action('admin_post_webtanan_booking_update_survey', array(__CLASS__, 'handle_update_survey'));
        add_action('admin_post_webtanan_booking_save_settlement', array(__CLASS__, 'handle_save_settlement'));
        add_action('admin_post_webtanan_booking_update_settlement', array(__CLASS__, 'handle_update_settlement'));
        add_action('admin_post_webtanan_booking_wallet_adjustment', array(__CLASS__, 'handle_wallet_adjustment'));
        add_action('admin_post_webtanan_booking_test_sms', array(__CLASS__, 'handle_test_sms'));
        add_action('admin_post_webtanan_booking_send_normal_sms', array(__CLASS__, 'handle_send_normal_sms'));
        add_action('admin_post_webtanan_booking_check_ippanel_patterns', array(__CLASS__, 'handle_check_ippanel_patterns'));
    }

    public static function register_menu(): void {
        add_menu_page(
            __('مدیریت نوبت‌دهی', 'webtanan-booking'),
            __('نوبت‌دهی وب‌تنان', 'webtanan-booking'),
            'webtanan_manage_booking',
            'webtanan-booking',
            array(__CLASS__, 'render_dashboard'),
            'dashicons-calendar-alt',
            56
        );

        add_submenu_page('webtanan-booking', __('داشبورد', 'webtanan-booking'), __('داشبورد', 'webtanan-booking'), 'webtanan_manage_booking', 'webtanan-booking', array(__CLASS__, 'render_dashboard'));
        add_submenu_page('webtanan-booking', __('پزشکان', 'webtanan-booking'), __('پزشکان', 'webtanan-booking'), 'webtanan_manage_doctors', 'webtanan-booking-doctors', array(__CLASS__, 'render_doctors'));
        add_submenu_page('webtanan-booking', __('منشی‌ها', 'webtanan-booking'), __('منشی‌ها', 'webtanan-booking'), 'webtanan_manage_doctors', 'webtanan-booking-secretaries', array(__CLASS__, 'render_secretaries'));
        add_submenu_page('webtanan-booking', __('تخصص‌ها', 'webtanan-booking'), __('تخصص‌ها', 'webtanan-booking'), 'webtanan_manage_doctors', 'webtanan-booking-specialties', array(__CLASS__, 'render_specialties'));
        add_submenu_page('webtanan-booking', __('برنامه نوبت‌دهی و روزهای خاص', 'webtanan-booking'), __('برنامه نوبت‌دهی و روزهای خاص', 'webtanan-booking'), 'webtanan_manage_doctors', 'webtanan-booking-schedules', array(__CLASS__, 'render_schedules'));
        add_submenu_page('webtanan-booking', __('نوبت‌ها', 'webtanan-booking'), __('نوبت‌ها', 'webtanan-booking'), 'webtanan_manage_booking', 'webtanan-booking-appointments', array(__CLASS__, 'render_appointments'));
        add_submenu_page('webtanan-booking', __('بیماران', 'webtanan-booking'), __('بیماران', 'webtanan-booking'), 'webtanan_manage_booking', 'webtanan-booking-patients', array(__CLASS__, 'render_patients'));
        add_submenu_page('webtanan-booking', __('تراکنش‌ها', 'webtanan-booking'), __('تراکنش‌ها', 'webtanan-booking'), 'webtanan_manage_finance', 'webtanan-booking-transactions', array(__CLASS__, 'render_transactions'));
        add_submenu_page('webtanan-booking', __('دفتر کل کیف پول', 'webtanan-booking'), __('دفتر کل کیف پول', 'webtanan-booking'), 'webtanan_manage_finance', 'webtanan-booking-wallet', array(__CLASS__, 'render_wallet'));
        add_submenu_page('webtanan-booking', __('گزارش‌های مالی', 'webtanan-booking'), __('گزارش‌های مالی', 'webtanan-booking'), 'webtanan_manage_finance', 'webtanan-booking-financial-reports', array(__CLASS__, 'render_financial_reports'));
        add_submenu_page('webtanan-booking', __('تسویه پزشکان', 'webtanan-booking'), __('تسویه پزشکان', 'webtanan-booking'), 'webtanan_manage_finance', 'webtanan-booking-settlements', array(__CLASS__, 'render_settlements'));
        add_submenu_page('webtanan-booking', __('لاگ پیامک/OTP', 'webtanan-booking'), __('لاگ پیامک/OTP', 'webtanan-booking'), 'webtanan_manage_settings', 'webtanan-booking-logs', array(__CLASS__, 'render_logs_overview'));
        add_submenu_page('webtanan-booking', __('لاگ OTP', 'webtanan-booking'), __('لاگ OTP', 'webtanan-booking'), 'webtanan_manage_settings', 'webtanan-booking-otp-logs', array(__CLASS__, 'render_otp_logs'));
        add_submenu_page('webtanan-booking', __('لاگ پیامک', 'webtanan-booking'), __('لاگ پیامک', 'webtanan-booking'), 'webtanan_manage_settings', 'webtanan-booking-sms-logs', array(__CLASS__, 'render_sms_logs'));
        add_submenu_page('webtanan-booking', __('تنظیمات', 'webtanan-booking'), __('تنظیمات', 'webtanan-booking'), 'webtanan_manage_settings', 'webtanan-booking-settings', array(__CLASS__, 'render_settings'));
        add_submenu_page('webtanan-booking', __('شورت‌کدها', 'webtanan-booking'), __('شورت‌کدها', 'webtanan-booking'), 'webtanan_manage_booking', 'webtanan-booking-shortcodes', array(__CLASS__, 'render_shortcodes'));
        add_submenu_page('webtanan-booking', __('مستندات', 'webtanan-booking'), __('مستندات', 'webtanan-booking'), 'webtanan_manage_booking', 'webtanan-booking-docs', array(__CLASS__, 'render_docs'));
    }

    public static function register_survey_menu(): void {
        add_submenu_page(
            'webtanan-booking',
            __('نظرسنجی‌ها', 'webtanan-booking'),
            __('نظرسنجی‌ها', 'webtanan-booking'),
            'webtanan_manage_booking',
            'webtanan-booking-surveys',
            array(__CLASS__, 'render_surveys')
        );
    }

    public static function register_settings(): void {
        register_setting(
            'webtanan_booking_settings',
            DB::OPTION_SETTINGS,
            array(
                'type' => 'array',
                'sanitize_callback' => array(__CLASS__, 'sanitize_settings'),
                'default' => DB::default_settings(),
            )
        );
    }

    public static function sanitize_settings($input): array {
        $input = is_array($input) ? $input : array();

        $sms_input = isset($input['sms_provider_settings']) && is_array($input['sms_provider_settings']) ? $input['sms_provider_settings'] : array();
        $patterns = array();
        foreach (SMS::message_types() as $type => $label) {
            $pattern = isset($sms_input['patterns'][$type]) && is_array($sms_input['patterns'][$type]) ? $sms_input['patterns'][$type] : array();
            $patterns[$type] = array(
                'enabled' => isset($pattern['enabled']),
                'code' => isset($pattern['code']) ? sanitize_text_field($pattern['code']) : '',
            );
        }

        $from_number = isset($sms_input['from_number']) ? sanitize_text_field($sms_input['from_number']) : (isset($sms_input['originator']) ? sanitize_text_field($sms_input['originator']) : '');
        $gateway_input = isset($input['gateway_settings']) && is_array($input['gateway_settings']) ? $input['gateway_settings'] : array();
        $aqaye_input = isset($gateway_input['aqayepardakht']) && is_array($gateway_input['aqayepardakht']) ? $gateway_input['aqayepardakht'] : array();
        $cancellation_input = isset($input['cancellation_policy']) && is_array($input['cancellation_policy']) ? $input['cancellation_policy'] : array();
        $callback_method = isset($aqaye_input['callback_method']) ? strtoupper(sanitize_text_field($aqaye_input['callback_method'])) : 'GET';
        if (!in_array($callback_method, array('GET', 'POST'), true)) {
            $callback_method = 'GET';
        }

        return array_replace_recursive(
            DB::get_settings(),
            array(
                'default_commission_type' => (isset($input['default_commission_type']) && 'fixed' === $input['default_commission_type']) ? 'fixed' : 'percent',
                'default_commission_value' => isset($input['default_commission_value']) ? (float) $input['default_commission_value'] : 0,
                'lock_duration_minutes' => isset($input['lock_duration_minutes']) ? max(1, absint($input['lock_duration_minutes'])) : 15,
                'otp_expiration_minutes' => isset($input['otp_expiration_minutes']) ? max(1, absint($input['otp_expiration_minutes'])) : 3,
                'otp_rate_limit_max_sends' => isset($input['otp_rate_limit_max_sends']) ? max(1, absint($input['otp_rate_limit_max_sends'])) : 3,
                'otp_rate_limit_window_minutes' => isset($input['otp_rate_limit_window_minutes']) ? max(1, absint($input['otp_rate_limit_window_minutes'])) : 15,
                'platform_wallet_user_id' => isset($input['platform_wallet_user_id']) ? absint($input['platform_wallet_user_id']) : 0,
                'wallet_topup_min_amount' => isset($input['wallet_topup_min_amount']) ? max(1000, absint($input['wallet_topup_min_amount'])) : 10000,
                'wallet_topup_max_amount' => isset($input['wallet_topup_max_amount']) ? max(1000, absint($input['wallet_topup_max_amount'])) : 50000000,
                'ui_font_family' => isset($input['ui_font_family']) ? sanitize_text_field($input['ui_font_family']) : '',
                'ui_font_attachment_id' => isset($input['ui_font_attachment_id']) ? absint($input['ui_font_attachment_id']) : 0,
                'ui_font_url' => isset($input['ui_font_url']) ? esc_url_raw($input['ui_font_url']) : '',
                'gateway_settings' => array(
                    'active_gateway' => isset($gateway_input['active_gateway']) ? sanitize_key($gateway_input['active_gateway']) : 'aqayepardakht',
                    'merchant_id' => isset($gateway_input['merchant_id']) ? sanitize_text_field($gateway_input['merchant_id']) : '',
                    'callback_url' => isset($gateway_input['callback_url']) ? esc_url_raw($gateway_input['callback_url']) : '',
                    'aqayepardakht' => array(
                        'enabled' => isset($aqaye_input['enabled']),
                        'sandbox' => isset($aqaye_input['sandbox']),
                        'pin' => isset($aqaye_input['pin']) ? sanitize_text_field($aqaye_input['pin']) : '',
                        'callback_method' => $callback_method,
                        'min_amount' => isset($aqaye_input['min_amount']) ? max(0, absint($aqaye_input['min_amount'])) : 1000,
                        'max_amount' => isset($aqaye_input['max_amount']) ? max(1000, absint($aqaye_input['max_amount'])) : 400000000,
                        'description_template' => isset($aqaye_input['description_template']) ? sanitize_text_field($aqaye_input['description_template']) : 'Appointment payment {appointment_code}',
                        'create_url' => isset($aqaye_input['create_url']) ? esc_url_raw($aqaye_input['create_url']) : 'https://panel.aqayepardakht.ir/api/v2/create',
                        'verify_url' => isset($aqaye_input['verify_url']) ? esc_url_raw($aqaye_input['verify_url']) : 'https://panel.aqayepardakht.ir/api/v2/verify',
                        'startpay_url' => isset($aqaye_input['startpay_url']) ? esc_url_raw($aqaye_input['startpay_url']) : 'https://panel.aqayepardakht.ir/startpay',
                    ),
                ),
                'sms_provider_settings' => array(
                    'enabled' => isset($sms_input['enabled']),
                    'provider' => isset($sms_input['provider']) ? sanitize_key($sms_input['provider']) : 'ippanel',
                    'base_url' => isset($sms_input['base_url']) ? esc_url_raw($sms_input['base_url']) : 'https://edge.ippanel.com/v1',
                    'api_key' => isset($sms_input['api_key']) ? sanitize_text_field($sms_input['api_key']) : '',
                    'originator' => $from_number,
                    'from_number' => $from_number,
                    'test_mode' => isset($sms_input['test_mode']),
                    'log_enabled' => isset($sms_input['log_enabled']),
                    'send_to_patient' => isset($sms_input['send_to_patient']),
                    'send_to_doctor' => isset($sms_input['send_to_doctor']),
                    'send_to_secretary' => isset($sms_input['send_to_secretary']),
                    'reminder_enabled' => isset($sms_input['reminder_enabled']),
                    'patterns' => $patterns,
                ),
                'cancellation_policy' => array(
                    'patient_cancel_until_hours' => isset($cancellation_input['patient_cancel_until_hours']) ? max(0, absint($cancellation_input['patient_cancel_until_hours'])) : 0,
                    'full_refund_hours' => isset($cancellation_input['full_refund_hours']) ? max(0, absint($cancellation_input['full_refund_hours'])) : 24,
                    'full_refund_percent' => isset($cancellation_input['full_refund_percent']) ? min(100, max(0, (float) $cancellation_input['full_refund_percent'])) : 100,
                    'partial_refund_hours' => isset($cancellation_input['partial_refund_hours']) ? max(0, absint($cancellation_input['partial_refund_hours'])) : 6,
                    'partial_refund_percent' => isset($cancellation_input['partial_refund_percent']) ? min(100, max(0, (float) $cancellation_input['partial_refund_percent'])) : 50,
                    'late_refund_percent' => isset($cancellation_input['late_refund_percent']) ? min(100, max(0, (float) $cancellation_input['late_refund_percent'])) : 0,
                    'doctor_admin_full_refund' => isset($cancellation_input['doctor_admin_full_refund']),
                ),
            )
        );
    }

    public static function enqueue_admin_assets(string $hook): void {
        if (false === strpos($hook, 'webtanan-booking') && 'saas_doctors' !== get_post_type()) {
            return;
        }

        wp_enqueue_style('webtanan-booking-admin', WEBTANAN_BOOKING_URL . 'assets/css/admin.css', array(), WEBTANAN_BOOKING_VERSION);
        wp_enqueue_media();
        wp_enqueue_script('webtanan-booking-jalali', WEBTANAN_BOOKING_URL . 'assets/js/jalali-calendar.js', array(), WEBTANAN_BOOKING_VERSION, true);
        wp_enqueue_script('webtanan-booking-admin', WEBTANAN_BOOKING_URL . 'assets/js/admin.js', array('jquery', 'webtanan-booking-jalali'), WEBTANAN_BOOKING_VERSION, true);
        wp_localize_script(
            'webtanan-booking-admin',
            'WebtananBookingAdmin',
            array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('webtanan_booking_admin_ajax'),
            )
        );
        self::add_custom_font_inline_style();
    }

    private static function add_custom_font_inline_style(): void {
        $settings = DB::get_settings();
        $font_family = sanitize_text_field((string) ($settings['ui_font_family'] ?? ''));
        $font_url = esc_url_raw((string) ($settings['ui_font_url'] ?? ''));
        $font_attachment_id = absint($settings['ui_font_attachment_id'] ?? 0);
        if ('' === $font_url && $font_attachment_id > 0) {
            $font_url = esc_url_raw((string) wp_get_attachment_url($font_attachment_id));
        }

        if ('' === $font_family || '' === $font_url) {
            return;
        }

        $format = preg_match('/\.woff2(\?|$)/i', $font_url) ? 'woff2' : (preg_match('/\.woff(\?|$)/i', $font_url) ? 'woff' : 'truetype');
        $css = '@font-face{font-family:"' . esc_attr($font_family) . '";src:url("' . esc_url($font_url) . '") format("' . esc_attr($format) . '");font-display:swap;}';
        $css .= '.webtanan-admin-wrap,.webtanan-admin-wrap *{font-family:"' . esc_attr($font_family) . '",inherit;}';
        wp_add_inline_style('webtanan-booking-admin', $css);
    }

    public static function allow_font_upload_mimes(array $mimes): array {
        $mimes['woff'] = 'font/woff';
        $mimes['woff2'] = 'font/woff2';
        $mimes['ttf'] = 'font/ttf';

        return $mimes;
    }

    public static function ajax_user_search(): void {
        if (!current_user_can('webtanan_manage_finance') && !current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('دسترسی غیرمجاز است.', 'webtanan-booking')), 403);
        }

        check_ajax_referer('webtanan_booking_admin_ajax', 'nonce');
        $term = sanitize_text_field((string) ($_GET['q'] ?? ''));
        if (strlen($term) < 2 && !ctype_digit($term)) {
            wp_send_json_success(array());
        }

        $ids = array();
        if (ctype_digit($term)) {
            $ids[] = absint($term);
        }

        $users = get_users(
            array(
                'number' => 20,
                'search' => '*' . $term . '*',
                'search_columns' => array('user_login', 'user_email', 'display_name'),
                'fields' => array('ID', 'display_name', 'user_email', 'user_login'),
            )
        );

        $meta_users = get_users(
            array(
                'number' => 20,
                'meta_query' => array(
                    'relation' => 'OR',
                    array('key' => 'webtanan_mobile', 'value' => $term, 'compare' => 'LIKE'),
                    array('key' => 'mobile', 'value' => $term, 'compare' => 'LIKE'),
                    array('key' => 'billing_phone', 'value' => $term, 'compare' => 'LIKE'),
                ),
                'fields' => array('ID', 'display_name', 'user_email', 'user_login'),
            )
        );

        $items = array();
        foreach (array_merge($users, $meta_users) as $user) {
            if (isset($items[$user->ID])) {
                continue;
            }
            $items[$user->ID] = array(
                'id' => (int) $user->ID,
                'text' => sprintf('%s (#%d) - %s', $user->display_name ?: $user->user_login, (int) $user->ID, $user->user_email),
            );
        }

        foreach ($ids as $id) {
            $user = get_userdata($id);
            if ($user && !isset($items[$id])) {
                $items[$id] = array(
                    'id' => $id,
                    'text' => sprintf('%s (#%d) - %s', $user->display_name ?: $user->user_login, $id, $user->user_email),
                );
            }
        }

        wp_send_json_success(array_values($items));
    }

    public static function render_dashboard(): void {
        global $wpdb;

        $appointments = DB::table('appointments');
        $transactions = DB::table('transactions');
        $today = current_time('Y-m-d');
        $counts = array(
            __('پزشکان', 'webtanan-booking') => (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . DB::table('doctors')),
            __('نوبت‌های امروز', 'webtanan-booking') => (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $appointments WHERE appointment_date = %s", $today)),
            __('نوبت‌های تاییدشده', 'webtanan-booking') => (int) $wpdb->get_var("SELECT COUNT(*) FROM $appointments WHERE appointment_status = 'confirmed'"),
            __('تراکنش‌های موفق', 'webtanan-booking') => (int) $wpdb->get_var("SELECT COUNT(*) FROM $transactions WHERE status IN ('verified','expired_lock_wallet_charged')"),
            __('درخواست‌های تسویه باز', 'webtanan-booking') => (int) $wpdb->get_var("SELECT COUNT(*) FROM " . DB::table('settlement_requests') . " WHERE status = 'pending'"),
        );

        $income = (float) $wpdb->get_var("SELECT SUM(amount) FROM " . DB::table('wallets_ledger') . " WHERE user_type = 'platform' AND entry_type = 'commission'");

        ?>
        <div class="wrap webtanan-admin" dir="rtl">
            <h1><?php esc_html_e('داشبورد مدیریت نوبت‌دهی', 'webtanan-booking'); ?></h1>
            <?php self::render_notice(); ?>
            <div class="webtanan-admin-cards">
                <?php foreach ($counts as $label => $count) : ?>
                    <div class="webtanan-admin-card">
                        <strong><?php echo esc_html(number_format_i18n($count)); ?></strong>
                        <span><?php echo esc_html($label); ?></span>
                    </div>
                <?php endforeach; ?>
                <div class="webtanan-admin-card">
                    <strong><?php echo esc_html(number_format_i18n($income)); ?></strong>
                    <span><?php esc_html_e('درآمد ثبت‌شده پلتفرم', 'webtanan-booking'); ?></span>
                </div>
            </div>
            <div class="webtanan-admin-panel">
                <h2><?php esc_html_e('دسترسی سریع', 'webtanan-booking'); ?></h2>
                <p>
                    <a class="button button-primary" href="<?php echo esc_url(self::page_url('webtanan-booking-doctors')); ?>"><?php esc_html_e('افزودن پزشک', 'webtanan-booking'); ?></a>
                    <a class="button" href="<?php echo esc_url(self::page_url('webtanan-booking-appointments')); ?>"><?php esc_html_e('ثبت نوبت حضوری', 'webtanan-booking'); ?></a>
                    <a class="button" href="<?php echo esc_url(self::page_url('webtanan-booking-transactions')); ?>"><?php esc_html_e('بررسی تراکنش‌ها', 'webtanan-booking'); ?></a>
                    <a class="button" href="<?php echo esc_url(self::page_url('webtanan-booking-settings')); ?>"><?php esc_html_e('تنظیمات درگاه و پیامک', 'webtanan-booking'); ?></a>
                </p>
            </div>
        </div>
        <?php
    }

    public static function render_doctors(): void {
        global $wpdb;

        $edit_id = absint($_GET['doctor_id'] ?? 0);
        $edit = $edit_id ? self::doctor_row($edit_id) : null;
        $rows = self::filtered_doctors();
        $specialties = self::specialty_options();

        ?>
        <div class="wrap webtanan-admin" dir="rtl">
            <h1><?php esc_html_e('مدیریت پزشکان', 'webtanan-booking'); ?></h1>
            <?php self::render_notice(); ?>
            <div class="webtanan-admin-layout">
                <div class="webtanan-admin-main">
                    <form method="get" class="webtanan-filter-bar">
                        <input type="hidden" name="page" value="webtanan-booking-doctors">
                        <input type="search" name="s" value="<?php echo esc_attr(self::request_text('s')); ?>" placeholder="<?php esc_attr_e('جستجو نام، کد، شماره نظام پزشکی', 'webtanan-booking'); ?>">
                        <select name="specialty_id">
                            <option value="0"><?php esc_html_e('همه تخصص‌ها', 'webtanan-booking'); ?></option>
                            <?php foreach ($specialties as $specialty) : ?>
                                <option value="<?php echo esc_attr((string) $specialty['id']); ?>" <?php selected(absint($_GET['specialty_id'] ?? 0), (int) $specialty['id']); ?>><?php echo esc_html($specialty['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select name="status">
                            <option value=""><?php esc_html_e('همه وضعیت‌ها', 'webtanan-booking'); ?></option>
                            <option value="active" <?php selected(self::request_text('status'), 'active'); ?>><?php esc_html_e('فعال', 'webtanan-booking'); ?></option>
                            <option value="inactive" <?php selected(self::request_text('status'), 'inactive'); ?>><?php esc_html_e('غیرفعال', 'webtanan-booking'); ?></option>
                            <option value="verified" <?php selected(self::request_text('status'), 'verified'); ?>><?php esc_html_e('تاییدشده', 'webtanan-booking'); ?></option>
                            <option value="unverified" <?php selected(self::request_text('status'), 'unverified'); ?>><?php esc_html_e('تاییدنشده', 'webtanan-booking'); ?></option>
                        </select>
                        <?php submit_button(__('فیلتر', 'webtanan-booking'), 'secondary', '', false); ?>
                    </form>
                    <table class="widefat striped">
                        <thead><tr>
                            <th><?php esc_html_e('پزشک', 'webtanan-booking'); ?></th>
                            <th><?php esc_html_e('کاربر', 'webtanan-booking'); ?></th>
                            <th><?php esc_html_e('تخصص', 'webtanan-booking'); ?></th>
                            <th><?php esc_html_e('هزینه‌ها', 'webtanan-booking'); ?></th>
                            <th><?php esc_html_e('وضعیت', 'webtanan-booking'); ?></th>
                            <th><?php esc_html_e('عملیات', 'webtanan-booking'); ?></th>
                        </tr></thead>
                        <tbody>
                        <?php if (!$rows) : ?>
                            <tr><td colspan="6"><?php esc_html_e('پزشکی یافت نشد.', 'webtanan-booking'); ?></td></tr>
                        <?php endif; ?>
                        <?php foreach ($rows as $row) : ?>
                            <tr>
                                <td><strong><?php echo esc_html($row['post_title'] ?: $row['clinic_name']); ?></strong><br><code><?php echo esc_html($row['doctor_code']); ?></code></td>
                                <td><?php echo esc_html($row['display_name'] ?: '-'); ?></td>
                                <td><?php echo esc_html($row['specialty_name'] ?: '-'); ?></td>
                                <td>
                                    <strong><?php esc_html_e('نوبت‌دهی:', 'webtanan-booking'); ?></strong>
                                    <?php echo esc_html(number_format_i18n(Booking::doctor_booking_fee($row))); ?>
                                    <br>
                                    <span class="description">
                                        <?php esc_html_e('ویزیت نمایشی:', 'webtanan-booking'); ?>
                                        <?php echo esc_html(number_format_i18n((float) $row['visit_price'])); ?>
                                    </span>
                                </td>
                                <td><?php echo wp_kses_post(self::status_badge((int) $row['is_active'] ? __('فعال', 'webtanan-booking') : __('غیرفعال', 'webtanan-booking'), (int) $row['is_active'] ? 'success' : 'muted')); ?> <?php echo wp_kses_post(self::status_badge((int) $row['is_verified'] ? __('تاییدشده', 'webtanan-booking') : __('تاییدنشده', 'webtanan-booking'), (int) $row['is_verified'] ? 'success' : 'warning')); ?></td>
                                <td>
                                    <a class="button button-small" href="<?php echo esc_url(self::page_url('webtanan-booking-doctors', array('doctor_id' => (int) $row['id']))); ?>"><?php esc_html_e('ویرایش', 'webtanan-booking'); ?></a>
                                    <?php if (!empty($row['post_id'])) : ?>
                                        <a class="button button-small" href="<?php echo esc_url(get_permalink((int) $row['post_id'])); ?>" target="_blank" rel="noreferrer"><?php esc_html_e('نمایش', 'webtanan-booking'); ?></a>
                                        <a class="button button-small" href="<?php echo esc_url(get_edit_post_link((int) $row['post_id'], '')); ?>"><?php esc_html_e('ویرایش محتوای پروفایل', 'webtanan-booking'); ?></a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="webtanan-admin-side">
                    <?php self::render_doctor_form($edit); ?>
                </div>
            </div>
        </div>
        <?php
    }

    public static function render_secretaries(): void {
        global $wpdb;

        $secretary_id = absint($_GET['secretary_user_id'] ?? 0);
        $secretaries = get_users(array('role' => 'webtanan_secretary', 'fields' => array('ID', 'display_name', 'user_email')));
        if (!$secretary_id && $secretaries) {
            $secretary_id = (int) $secretaries[0]->ID;
        }

        $assigned = $secretary_id ? get_user_meta($secretary_id, 'webtanan_assigned_doctor_ids', true) : array();
        if (is_string($assigned)) {
            $assigned = array_filter(array_map('absint', explode(',', $assigned)));
        }
        if (!is_array($assigned)) {
            $assigned = array();
        }

        $can_view_finance = $secretary_id ? ('yes' === get_user_meta($secretary_id, 'webtanan_secretary_can_view_finance', true)) : false;
        $doctors = $wpdb->get_results('SELECT d.id, d.clinic_name, p.post_title FROM ' . DB::table('doctors') . ' d LEFT JOIN ' . $wpdb->posts . ' p ON p.ID = d.post_id ORDER BY p.post_title ASC, d.id ASC LIMIT 500', ARRAY_A);

        ?>
        <div class="wrap webtanan-admin" dir="rtl">
            <h1><?php esc_html_e('مدیریت دسترسی منشی‌ها', 'webtanan-booking'); ?></h1>
            <?php self::render_notice(); ?>
            <div class="webtanan-admin-layout">
                <div class="webtanan-admin-main">
                    <div class="webtanan-admin-panel">
                        <h2><?php esc_html_e('منشی‌های ثبت‌شده', 'webtanan-booking'); ?></h2>
                        <table class="widefat striped">
                            <thead><tr><th><?php esc_html_e('منشی', 'webtanan-booking'); ?></th><th><?php esc_html_e('پزشکان مجاز', 'webtanan-booking'); ?></th><th><?php esc_html_e('دسترسی مالی', 'webtanan-booking'); ?></th><th><?php esc_html_e('عملیات', 'webtanan-booking'); ?></th></tr></thead>
                            <tbody>
                            <?php foreach ($secretaries as $secretary) : ?>
                                <?php
                                $ids = get_user_meta((int) $secretary->ID, 'webtanan_assigned_doctor_ids', true);
                                if (is_string($ids)) {
                                    $ids = array_filter(array_map('absint', explode(',', $ids)));
                                }
                                $ids = is_array($ids) ? array_map('absint', $ids) : array();
                                ?>
                                <tr>
                                    <td><strong><?php echo esc_html($secretary->display_name); ?></strong><br><code><?php echo esc_html($secretary->user_email); ?></code></td>
                                    <td><?php echo esc_html(number_format_i18n(count($ids))); ?></td>
                                    <td><?php echo esc_html('yes' === get_user_meta((int) $secretary->ID, 'webtanan_secretary_can_view_finance', true) ? __('دارد', 'webtanan-booking') : __('ندارد', 'webtanan-booking')); ?></td>
                                    <td><a class="button button-small" href="<?php echo esc_url(self::page_url('webtanan-booking-secretaries', array('secretary_user_id' => (int) $secretary->ID))); ?>"><?php esc_html_e('تنظیم دسترسی', 'webtanan-booking'); ?></a></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (!$secretaries) : ?><tr><td colspan="4"><?php esc_html_e('هنوز کاربری با نقش منشی وب‌تنان وجود ندارد.', 'webtanan-booking'); ?></td></tr><?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="webtanan-admin-side">
                    <div class="webtanan-admin-panel">
                        <h2><?php esc_html_e('اتصال منشی به پزشک', 'webtanan-booking'); ?></h2>
                        <form method="get" class="webtanan-filter-bar">
                            <input type="hidden" name="page" value="webtanan-booking-secretaries">
                            <select name="secretary_user_id">
                                <?php foreach ($secretaries as $secretary) : ?>
                                    <option value="<?php echo esc_attr((string) $secretary->ID); ?>" <?php selected($secretary_id, (int) $secretary->ID); ?>><?php echo esc_html($secretary->display_name); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php submit_button(__('انتخاب', 'webtanan-booking'), 'secondary', '', false); ?>
                        </form>
                        <?php if ($secretary_id) : ?>
                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                <?php wp_nonce_field('webtanan_booking_save_secretary_assignment'); ?>
                                <input type="hidden" name="action" value="webtanan_booking_save_secretary_assignment">
                                <input type="hidden" name="secretary_user_id" value="<?php echo esc_attr((string) $secretary_id); ?>">
                                <p><label><?php esc_html_e('پزشک‌های مجاز', 'webtanan-booking'); ?></label>
                                    <select name="doctor_ids[]" class="widefat" multiple size="10">
                                        <?php foreach ($doctors as $doctor) : ?>
                                            <option value="<?php echo esc_attr((string) $doctor['id']); ?>" <?php selected(in_array((int) $doctor['id'], $assigned, true)); ?>><?php echo esc_html(($doctor['post_title'] ?: $doctor['clinic_name']) . ' #' . $doctor['id']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </p>
                                <p><label><input type="checkbox" name="can_view_finance" value="1" <?php checked($can_view_finance); ?>> <?php esc_html_e('منشی اجازه مشاهده کیف پول، درآمد و تسویه این پزشک‌ها را داشته باشد', 'webtanan-booking'); ?></label></p>
                                <?php submit_button(__('ذخیره دسترسی منشی', 'webtanan-booking')); ?>
                            </form>
                        <?php else : ?>
                            <p><?php esc_html_e('برای تنظیم دسترسی، ابتدا یک کاربر با نقش منشی وب‌تنان بسازید.', 'webtanan-booking'); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    public static function render_specialties(): void {
        global $wpdb;

        $edit_id = absint($_GET['specialty_id'] ?? 0);
        $edit = $edit_id ? $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . DB::table('specialties') . ' WHERE id = %d', $edit_id), ARRAY_A) : null;
        $where = '1=1';
        $params = array();
        $search = self::request_text('s');
        if ($search) {
            $where .= ' AND (name LIKE %s OR slug LIKE %s)';
            $like = '%' . $wpdb->esc_like($search) . '%';
            $params[] = $like;
            $params[] = $like;
        }
        $status = self::request_text('status');
        if ('active' === $status || 'inactive' === $status) {
            $where .= ' AND is_active = %d';
            $params[] = 'active' === $status ? 1 : 0;
        }

        $sql = 'SELECT * FROM ' . DB::table('specialties') . " WHERE $where ORDER BY sort_order ASC, name ASC LIMIT 200";
        $rows = $params ? $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A) : $wpdb->get_results($sql, ARRAY_A);

        ?>
        <div class="wrap webtanan-admin" dir="rtl">
            <h1><?php esc_html_e('مدیریت تخصص‌ها', 'webtanan-booking'); ?></h1>
            <?php self::render_notice(); ?>
            <div class="webtanan-admin-layout">
                <div class="webtanan-admin-main">
                    <form method="get" class="webtanan-filter-bar">
                        <input type="hidden" name="page" value="webtanan-booking-specialties">
                        <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('جستجوی تخصص', 'webtanan-booking'); ?>">
                        <select name="status">
                            <option value=""><?php esc_html_e('همه وضعیت‌ها', 'webtanan-booking'); ?></option>
                            <option value="active" <?php selected($status, 'active'); ?>><?php esc_html_e('فعال', 'webtanan-booking'); ?></option>
                            <option value="inactive" <?php selected($status, 'inactive'); ?>><?php esc_html_e('غیرفعال', 'webtanan-booking'); ?></option>
                        </select>
                        <?php submit_button(__('فیلتر', 'webtanan-booking'), 'secondary', '', false); ?>
                    </form>
                    <table class="widefat striped">
                        <thead><tr><th><?php esc_html_e('نام', 'webtanan-booking'); ?></th><th><?php esc_html_e('اسلاگ', 'webtanan-booking'); ?></th><th><?php esc_html_e('ترتیب', 'webtanan-booking'); ?></th><th><?php esc_html_e('وضعیت', 'webtanan-booking'); ?></th><th><?php esc_html_e('عملیات', 'webtanan-booking'); ?></th></tr></thead>
                        <tbody>
                        <?php foreach ($rows as $row) : ?>
                            <tr>
                                <td><?php echo esc_html($row['name']); ?></td>
                                <td><code><?php echo esc_html($row['slug']); ?></code></td>
                                <td><?php echo esc_html((string) $row['sort_order']); ?></td>
                                <td><?php echo wp_kses_post(self::status_badge((int) $row['is_active'] ? __('فعال', 'webtanan-booking') : __('غیرفعال', 'webtanan-booking'), (int) $row['is_active'] ? 'success' : 'muted')); ?></td>
                                <td>
                                    <a class="button button-small" href="<?php echo esc_url(self::page_url('webtanan-booking-specialties', array('specialty_id' => (int) $row['id']))); ?>"><?php esc_html_e('ویرایش', 'webtanan-booking'); ?></a>
                                    <a class="button button-small" href="<?php echo esc_url(self::public_specialty_url((int) $row['id'])); ?>" target="_blank" rel="noreferrer"><?php esc_html_e('نمایش', 'webtanan-booking'); ?></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$rows) : ?><tr><td colspan="5"><?php esc_html_e('تخصصی یافت نشد.', 'webtanan-booking'); ?></td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="webtanan-admin-side">
                    <?php self::render_specialty_form($edit); ?>
                </div>
            </div>
        </div>
        <?php
    }

    public static function render_schedules(): void {
        global $wpdb;

        $doctor_id = absint($_GET['doctor_id'] ?? 0);
        $schedule_id = absint($_GET['schedule_id'] ?? 0);
        $exception_id = absint($_GET['exception_id'] ?? 0);
        $schedule = $schedule_id ? $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . DB::table('schedules') . ' WHERE id = %d', $schedule_id), ARRAY_A) : null;
        $exception = $exception_id ? $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . DB::table('schedule_exceptions') . ' WHERE id = %d', $exception_id), ARRAY_A) : null;
        $doctor_id = $doctor_id ?: absint($schedule['doctor_id'] ?? $exception['doctor_id'] ?? 0);

        $schedules = array();
        $exceptions = array();
        if ($doctor_id) {
            $schedules = $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . DB::table('schedules') . ' WHERE doctor_id = %d ORDER BY FIELD(weekday, "saturday","sunday","monday","tuesday","wednesday","thursday","friday"), start_time ASC', $doctor_id), ARRAY_A);
            $exceptions = $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . DB::table('schedule_exceptions') . ' WHERE doctor_id = %d ORDER BY exception_date DESC, start_time ASC LIMIT 100', $doctor_id), ARRAY_A);
        }

        ?>
        <div class="wrap webtanan-admin" dir="rtl">
            <h1><?php esc_html_e('برنامه نوبت‌دهی و روزهای خاص', 'webtanan-booking'); ?></h1>
            <?php self::render_notice(); ?>
            <form method="get" class="webtanan-filter-bar">
                <input type="hidden" name="page" value="webtanan-booking-schedules">
                <?php self::doctor_select('doctor_id', $doctor_id, __('انتخاب پزشک', 'webtanan-booking')); ?>
                <?php submit_button(__('نمایش', 'webtanan-booking'), 'secondary', '', false); ?>
            </form>
            <?php if (!$doctor_id) : ?>
                <div class="notice notice-info"><p><?php esc_html_e('برای مدیریت برنامه کاری ابتدا یک پزشک را انتخاب کنید.', 'webtanan-booking'); ?></p></div>
            <?php else : ?>
                <div class="webtanan-admin-layout">
                    <div class="webtanan-admin-main">
                        <h2><?php esc_html_e('شیفت‌های هفتگی', 'webtanan-booking'); ?></h2>
                        <table class="widefat striped">
                            <thead><tr><th><?php esc_html_e('روز', 'webtanan-booking'); ?></th><th><?php esc_html_e('شروع', 'webtanan-booking'); ?></th><th><?php esc_html_e('پایان', 'webtanan-booking'); ?></th><th><?php esc_html_e('مدت', 'webtanan-booking'); ?></th><th><?php esc_html_e('ظرفیت', 'webtanan-booking'); ?></th><th><?php esc_html_e('وضعیت', 'webtanan-booking'); ?></th><th><?php esc_html_e('عملیات', 'webtanan-booking'); ?></th></tr></thead>
                            <tbody>
                            <?php foreach ($schedules as $row) : ?>
                                <tr><td><?php echo esc_html(self::weekday_label($row['weekday'])); ?></td><td><?php echo esc_html(substr($row['start_time'], 0, 5)); ?></td><td><?php echo esc_html(substr($row['end_time'], 0, 5)); ?></td><td><?php echo esc_html((string) $row['slot_duration']); ?></td><td><?php echo esc_html((string) $row['capacity_per_slot']); ?></td><td><?php echo wp_kses_post(self::status_badge((int) $row['is_active'] ? __('فعال', 'webtanan-booking') : __('غیرفعال', 'webtanan-booking'), (int) $row['is_active'] ? 'success' : 'muted')); ?></td><td><a class="button button-small" href="<?php echo esc_url(self::page_url('webtanan-booking-schedules', array('doctor_id' => $doctor_id, 'schedule_id' => (int) $row['id']))); ?>"><?php esc_html_e('ویرایش', 'webtanan-booking'); ?></a></td></tr>
                            <?php endforeach; ?>
                            <?php if (!$schedules) : ?><tr><td colspan="7"><?php esc_html_e('برنامه‌ای ثبت نشده است.', 'webtanan-booking'); ?></td></tr><?php endif; ?>
                            </tbody>
                        </table>
                        <h2><?php esc_html_e('برنامه تاریخ خاص', 'webtanan-booking'); ?></h2>
                        <table class="widefat striped">
                            <thead><tr><th><?php esc_html_e('تاریخ', 'webtanan-booking'); ?></th><th><?php esc_html_e('نوع', 'webtanan-booking'); ?></th><th><?php esc_html_e('شروع', 'webtanan-booking'); ?></th><th><?php esc_html_e('پایان', 'webtanan-booking'); ?></th><th><?php esc_html_e('دلیل', 'webtanan-booking'); ?></th><th><?php esc_html_e('عملیات', 'webtanan-booking'); ?></th></tr></thead>
                            <tbody>
                            <?php foreach ($exceptions as $row) : ?>
                                <tr><td><?php echo esc_html($row['exception_date']); ?></td><td><?php echo esc_html($row['type']); ?></td><td><?php echo esc_html(substr((string) $row['start_time'], 0, 5)); ?></td><td><?php echo esc_html(substr((string) $row['end_time'], 0, 5)); ?></td><td><?php echo esc_html($row['reason']); ?></td><td><a class="button button-small" href="<?php echo esc_url(self::page_url('webtanan-booking-schedules', array('doctor_id' => $doctor_id, 'exception_id' => (int) $row['id']))); ?>"><?php esc_html_e('ویرایش', 'webtanan-booking'); ?></a></td></tr>
                            <?php endforeach; ?>
                            <?php if (!$exceptions) : ?><tr><td colspan="6"><?php esc_html_e('استثنایی ثبت نشده است.', 'webtanan-booking'); ?></td></tr><?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="webtanan-admin-side">
                        <?php self::render_schedule_form($doctor_id, $schedule); ?>
                        <?php self::render_exception_form($doctor_id, $exception); ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    public static function render_appointments(): void {
        global $wpdb;

        $where = '1=1';
        $params = array();
        $doctor_id = absint($_GET['doctor_id'] ?? 0);
        if ($doctor_id) {
            $where .= ' AND a.doctor_id = %d';
            $params[] = $doctor_id;
        }
        foreach (array('appointment_status', 'payment_status') as $field) {
            $value = self::request_key($field);
            if ($value) {
                $where .= " AND a.$field = %s";
                $params[] = $value;
            }
        }
        $from = self::request_text('date_from');
        $to = self::request_text('date_to');
        if ($from) {
            $where .= ' AND a.appointment_date >= %s';
            $params[] = $from;
        }
        if ($to) {
            $where .= ' AND a.appointment_date <= %s';
            $params[] = $to;
        }
        $search = self::request_text('s');
        if ($search) {
            $where .= ' AND (a.appointment_code LIKE %s OR a.patient_first_name LIKE %s OR a.patient_last_name LIKE %s OR a.patient_mobile LIKE %s OR a.patient_national_code LIKE %s)';
            $like = '%' . $wpdb->esc_like($search) . '%';
            array_push($params, $like, $like, $like, $like, $like);
        }

        $sql = 'SELECT a.*, p.post_title AS doctor_title FROM ' . DB::table('appointments') . ' a LEFT JOIN ' . DB::table('doctors') . ' d ON d.id = a.doctor_id LEFT JOIN ' . $wpdb->posts . " p ON p.ID = d.post_id WHERE $where ORDER BY a.appointment_date DESC, a.start_time DESC LIMIT 100";
        $rows = $params ? $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A) : $wpdb->get_results($sql, ARRAY_A);

        ?>
        <div class="wrap webtanan-admin" dir="rtl">
            <h1><?php esc_html_e('مدیریت نوبت‌ها', 'webtanan-booking'); ?></h1>
            <?php self::render_notice(); ?>
            <div class="webtanan-admin-layout">
                <div class="webtanan-admin-main">
                    <form method="get" class="webtanan-filter-bar">
                        <input type="hidden" name="page" value="webtanan-booking-appointments">
                        <?php self::doctor_select('doctor_id', $doctor_id, __('همه پزشکان', 'webtanan-booking')); ?>
                        <input type="date" class="webtanan-jalali-input" name="date_from" value="<?php echo esc_attr($from); ?>" aria-label="<?php esc_attr_e('از تاریخ', 'webtanan-booking'); ?>">
                        <input type="date" class="webtanan-jalali-input" name="date_to" value="<?php echo esc_attr($to); ?>" aria-label="<?php esc_attr_e('تا تاریخ', 'webtanan-booking'); ?>">
                        <span class="description"><?php esc_html_e('تاریخ‌ها در رابط ادمین شمسی نمایش داده می‌شوند؛ مقدار ذخیره‌شده برای کوئری Gregorian است.', 'webtanan-booking'); ?></span>
                        <?php self::select_from_map('appointment_status', self::appointment_statuses(), self::request_key('appointment_status'), __('همه وضعیت نوبت‌ها', 'webtanan-booking')); ?>
                        <?php self::select_from_map('payment_status', self::payment_statuses(), self::request_key('payment_status'), __('همه پرداخت‌ها', 'webtanan-booking')); ?>
                        <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('نام، موبایل، کد ملی یا کد نوبت', 'webtanan-booking'); ?>">
                        <?php submit_button(__('فیلتر', 'webtanan-booking'), 'secondary', '', false); ?>
                    </form>
                    <table class="widefat striped webtanan-admin-table">
                        <thead><tr><th><?php esc_html_e('کد/زمان', 'webtanan-booking'); ?></th><th><?php esc_html_e('پزشک', 'webtanan-booking'); ?></th><th><?php esc_html_e('بیمار', 'webtanan-booking'); ?></th><th><?php esc_html_e('مبلغ خدمات', 'webtanan-booking'); ?></th><th><?php esc_html_e('پرداخت', 'webtanan-booking'); ?></th><th><?php esc_html_e('وضعیت', 'webtanan-booking'); ?></th><th><?php esc_html_e('عملیات', 'webtanan-booking'); ?></th></tr></thead>
                        <tbody>
                        <?php foreach ($rows as $row) : ?>
                            <tr>
                                <td><code><?php echo esc_html($row['appointment_code']); ?></code><br><?php echo esc_html($row['appointment_date'] . ' ' . substr($row['start_time'], 0, 5)); ?></td>
                                <td><?php echo esc_html($row['doctor_title'] ?: '#' . $row['doctor_id']); ?></td>
                                <td><?php echo esc_html(trim($row['patient_first_name'] . ' ' . $row['patient_last_name'])); ?><br><span dir="ltr"><?php echo esc_html($row['patient_mobile']); ?></span></td>
                                <td><?php echo esc_html(number_format_i18n(Booking::appointment_charge_amount($row))); ?></td>
                                <td><?php echo esc_html(self::payment_statuses()[$row['payment_status']] ?? $row['payment_status']); ?></td>
                                <td><?php echo esc_html(self::appointment_statuses()[$row['appointment_status']] ?? $row['appointment_status']); ?></td>
                                <td><?php self::render_appointment_actions($row); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$rows) : ?><tr><td colspan="7"><?php esc_html_e('نوبتی یافت نشد.', 'webtanan-booking'); ?></td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="webtanan-admin-side">
                    <?php self::render_admin_appointment_form(); ?>
                    <?php self::render_bulk_appointment_form($doctor_id, $from ?: current_time('Y-m-d')); ?>
                </div>
            </div>
        </div>
        <?php
    }

    public static function render_patients(): void {
        global $wpdb;

        $where = "patient_mobile <> ''";
        $params = array();
        $search = self::request_text('s');
        if ($search) {
            $where .= ' AND (patient_first_name LIKE %s OR patient_last_name LIKE %s OR patient_mobile LIKE %s OR patient_national_code LIKE %s)';
            $like = '%' . $wpdb->esc_like($search) . '%';
            array_push($params, $like, $like, $like, $like);
        }
        $doctor_id = absint($_GET['doctor_id'] ?? 0);
        if ($doctor_id) {
            $where .= ' AND doctor_id = %d';
            $params[] = $doctor_id;
        }

        $sql = 'SELECT patient_user_id, patient_first_name, patient_last_name, patient_national_code, patient_mobile, COUNT(*) AS appointment_count, MAX(appointment_date) AS last_appointment FROM ' . DB::table('appointments') . " WHERE $where GROUP BY patient_user_id, patient_first_name, patient_last_name, patient_national_code, patient_mobile ORDER BY last_appointment DESC LIMIT 150";
        $rows = $params ? $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A) : $wpdb->get_results($sql, ARRAY_A);

        ?>
        <div class="wrap webtanan-admin" dir="rtl">
            <h1><?php esc_html_e('مدیریت بیماران', 'webtanan-booking'); ?></h1>
            <?php self::render_notice(); ?>
            <form method="get" class="webtanan-filter-bar">
                <input type="hidden" name="page" value="webtanan-booking-patients">
                <?php self::doctor_select('doctor_id', $doctor_id, __('همه پزشکان', 'webtanan-booking')); ?>
                <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('نام، موبایل یا کد ملی', 'webtanan-booking'); ?>">
                <?php submit_button(__('فیلتر', 'webtanan-booking'), 'secondary', '', false); ?>
            </form>
            <table class="widefat striped">
                <thead><tr><th><?php esc_html_e('بیمار', 'webtanan-booking'); ?></th><th><?php esc_html_e('موبایل', 'webtanan-booking'); ?></th><th><?php esc_html_e('کد ملی', 'webtanan-booking'); ?></th><th><?php esc_html_e('کاربر', 'webtanan-booking'); ?></th><th><?php esc_html_e('تعداد نوبت', 'webtanan-booking'); ?></th><th><?php esc_html_e('آخرین نوبت', 'webtanan-booking'); ?></th><th><?php esc_html_e('کیف پول', 'webtanan-booking'); ?></th></tr></thead>
                <tbody>
                <?php foreach ($rows as $row) : ?>
                    <tr><td><?php echo esc_html(trim($row['patient_first_name'] . ' ' . $row['patient_last_name'])); ?></td><td dir="ltr"><?php echo esc_html($row['patient_mobile']); ?></td><td><?php echo esc_html($row['patient_national_code']); ?></td><td><?php echo esc_html((string) $row['patient_user_id']); ?></td><td><?php echo esc_html(number_format_i18n((int) $row['appointment_count'])); ?></td><td><?php echo esc_html($row['last_appointment']); ?></td><td><?php echo esc_html((int) $row['patient_user_id'] > 0 ? number_format_i18n(Wallet::balance((int) $row['patient_user_id'], 'patient')) : '-'); ?></td></tr>
                <?php endforeach; ?>
                <?php if (!$rows) : ?><tr><td colspan="7"><?php esc_html_e('بیماری یافت نشد.', 'webtanan-booking'); ?></td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public static function render_transactions(): void {
        global $wpdb;

        $where = '1=1';
        $params = array();
        foreach (array('gateway_name', 'status') as $field) {
            $value = self::request_key($field);
            if ($value) {
                $where .= " AND $field = %s";
                $params[] = $value;
            }
        }
        $doctor_id = absint($_GET['doctor_id'] ?? 0);
        if ($doctor_id) {
            $where .= ' AND doctor_id = %d';
            $params[] = $doctor_id;
        }
        $search = self::request_text('s');
        if ($search) {
            $where .= ' AND (transaction_code LIKE %s OR gateway_transid LIKE %s OR gateway_tracking_number LIKE %s OR invoice_id LIKE %s)';
            $like = '%' . $wpdb->esc_like($search) . '%';
            array_push($params, $like, $like, $like, $like);
        }
        $sql = 'SELECT * FROM ' . DB::table('transactions') . " WHERE $where ORDER BY id DESC LIMIT 100";
        $rows = $params ? $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A) : $wpdb->get_results($sql, ARRAY_A);

        ?>
        <div class="wrap webtanan-admin" dir="rtl">
            <h1><?php esc_html_e('تراکنش‌ها', 'webtanan-booking'); ?></h1>
            <?php self::render_notice(); ?>
            <form method="get" class="webtanan-filter-bar">
                <input type="hidden" name="page" value="webtanan-booking-transactions">
                <?php self::doctor_select('doctor_id', $doctor_id, __('همه پزشکان', 'webtanan-booking')); ?>
                <input type="text" name="gateway_name" value="<?php echo esc_attr(self::request_key('gateway_name')); ?>" placeholder="<?php esc_attr_e('درگاه', 'webtanan-booking'); ?>">
                <input type="text" name="status" value="<?php echo esc_attr(self::request_key('status')); ?>" placeholder="<?php esc_attr_e('وضعیت تراکنش', 'webtanan-booking'); ?>">
                <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('کد تراکنش، transid، پیگیری', 'webtanan-booking'); ?>">
                <?php submit_button(__('فیلتر', 'webtanan-booking'), 'secondary', '', false); ?>
            </form>
            <table class="widefat striped">
                <thead><tr><th><?php esc_html_e('کد', 'webtanan-booking'); ?></th><th><?php esc_html_e('درگاه', 'webtanan-booking'); ?></th><th><?php esc_html_e('TransID', 'webtanan-booking'); ?></th><th><?php esc_html_e('پیگیری', 'webtanan-booking'); ?></th><th><?php esc_html_e('نوبت', 'webtanan-booking'); ?></th><th><?php esc_html_e('مبلغ', 'webtanan-booking'); ?></th><th><?php esc_html_e('وضعیت', 'webtanan-booking'); ?></th><th><?php esc_html_e('خطا', 'webtanan-booking'); ?></th><th><?php esc_html_e('تاریخ', 'webtanan-booking'); ?></th></tr></thead>
                <tbody>
                <?php foreach ($rows as $row) : ?>
                    <tr><td><code><?php echo esc_html($row['transaction_code']); ?></code></td><td><?php echo esc_html($row['gateway_name']); ?></td><td dir="ltr"><?php echo esc_html($row['gateway_transid']); ?></td><td dir="ltr"><?php echo esc_html($row['gateway_tracking_number']); ?></td><td><?php echo esc_html((string) $row['appointment_id']); ?></td><td><?php echo esc_html(number_format_i18n((float) $row['amount'])); ?></td><td><?php echo esc_html($row['status']); ?></td><td><?php echo esc_html(wp_trim_words((string) $row['error_message'], 10)); ?></td><td><?php echo esc_html($row['created_at']); ?></td></tr>
                <?php endforeach; ?>
                <?php if (!$rows) : ?><tr><td colspan="9"><?php esc_html_e('تراکنشی یافت نشد.', 'webtanan-booking'); ?></td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public static function render_wallet(): void {
        global $wpdb;

        $where = '1=1';
        $params = array();
        $user_type = self::request_key('user_type');
        $user_id = absint($_GET['user_id'] ?? 0);
        $entry_type = self::request_key('entry_type');
        if ($user_type) {
            $where .= ' AND user_type = %s';
            $params[] = $user_type;
        }
        if ($user_id) {
            $where .= ' AND user_id = %d';
            $params[] = $user_id;
        }
        if ($entry_type) {
            $where .= ' AND entry_type = %s';
            $params[] = $entry_type;
        }
        $sql = 'SELECT * FROM ' . DB::table('wallets_ledger') . " WHERE $where ORDER BY id DESC LIMIT 150";
        $rows = $params ? $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A) : $wpdb->get_results($sql, ARRAY_A);

        ?>
        <div class="wrap webtanan-admin" dir="rtl">
            <h1><?php esc_html_e('کیف پول و دفتر کل', 'webtanan-booking'); ?></h1>
            <?php self::render_notice(); ?>
            <div class="webtanan-admin-layout">
                <div class="webtanan-admin-main">
                    <form method="get" class="webtanan-filter-bar">
                        <input type="hidden" name="page" value="webtanan-booking-wallet">
                        <?php self::select_from_map('user_type', self::user_types(), $user_type, __('همه نوع کاربران', 'webtanan-booking')); ?>
                        <input type="number" name="user_id" min="0" value="<?php echo esc_attr((string) $user_id); ?>" placeholder="<?php esc_attr_e('شناسه کاربر', 'webtanan-booking'); ?>">
                        <input type="text" name="entry_type" value="<?php echo esc_attr($entry_type); ?>" placeholder="<?php esc_attr_e('نوع رکورد', 'webtanan-booking'); ?>">
                        <?php submit_button(__('فیلتر', 'webtanan-booking'), 'secondary', '', false); ?>
                    </form>
                    <table class="widefat striped">
                        <thead><tr><th>ID</th><th><?php esc_html_e('کاربر', 'webtanan-booking'); ?></th><th><?php esc_html_e('نوع کاربر', 'webtanan-booking'); ?></th><th><?php esc_html_e('نوع رکورد', 'webtanan-booking'); ?></th><th><?php esc_html_e('مبلغ', 'webtanan-booking'); ?></th><th><?php esc_html_e('مانده بعد', 'webtanan-booking'); ?></th><th><?php esc_html_e('نوبت/تراکنش', 'webtanan-booking'); ?></th><th><?php esc_html_e('توضیح', 'webtanan-booking'); ?></th><th><?php esc_html_e('تاریخ', 'webtanan-booking'); ?></th></tr></thead>
                        <tbody>
                        <?php foreach ($rows as $row) : ?>
                            <tr><td><?php echo esc_html((string) $row['id']); ?></td><td><?php echo esc_html((string) $row['user_id']); ?></td><td><?php echo esc_html($row['user_type']); ?></td><td><?php echo esc_html($row['entry_type']); ?></td><td><?php echo esc_html(number_format_i18n((float) $row['amount'])); ?></td><td><?php echo esc_html(number_format_i18n((float) $row['balance_after'])); ?></td><td><?php echo esc_html($row['related_appointment_id'] . ' / ' . $row['related_transaction_id']); ?></td><td><?php echo esc_html(wp_trim_words((string) $row['description'], 12)); ?></td><td><?php echo esc_html($row['created_at']); ?></td></tr>
                        <?php endforeach; ?>
                        <?php if (!$rows) : ?><tr><td colspan="9"><?php esc_html_e('رکوردی یافت نشد.', 'webtanan-booking'); ?></td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="webtanan-admin-side">
                    <?php self::render_wallet_adjustment_form(); ?>
                </div>
            </div>
        </div>
        <?php
    }

    public static function render_surveys(): void {
        global $wpdb;

        $surveys = DB::table('survey_responses');
        $appointments = DB::table('appointments');
        $doctors = DB::table('doctors');

        $doctor_id = absint($_GET['doctor_id'] ?? 0);
        $status = self::request_key('status');
        $rating = absint($_GET['rating'] ?? 0);
        $search = self::request_text('search');
        $where = '1=1';
        $params = array();

        if ($doctor_id) {
            $where .= ' AND s.doctor_id = %d';
            $params[] = $doctor_id;
        }
        if ($status) {
            $where .= ' AND s.status = %s';
            $params[] = $status;
        }
        if ($rating) {
            $where .= ' AND s.rating = %d';
            $params[] = $rating;
        }
        if ($search) {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where .= ' AND (a.appointment_code LIKE %s OR a.patient_first_name LIKE %s OR a.patient_last_name LIKE %s OR a.patient_mobile LIKE %s OR s.feedback LIKE %s)';
            array_push($params, $like, $like, $like, $like, $like);
        }

        $stats_sql = "SELECT COUNT(*) AS total_count, COALESCE(AVG(rating), 0) AS avg_rating, SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending_count, SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) AS approved_count FROM $surveys s LEFT JOIN $appointments a ON a.id = s.appointment_id WHERE $where";
        $stats = $params ? $wpdb->get_row($wpdb->prepare($stats_sql, $params), ARRAY_A) : $wpdb->get_row($stats_sql, ARRAY_A);

        $sql = "SELECT s.*, a.appointment_code, a.patient_first_name, a.patient_last_name, a.patient_mobile, a.appointment_date, a.start_time, p.post_title AS doctor_title
            FROM $surveys s
            LEFT JOIN $appointments a ON a.id = s.appointment_id
            LEFT JOIN $doctors d ON d.id = s.doctor_id
            LEFT JOIN $wpdb->posts p ON p.ID = d.post_id
            WHERE $where
            ORDER BY s.id DESC
            LIMIT 150";
        $rows = $params ? $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A) : $wpdb->get_results($sql, ARRAY_A);

        ?>
        <div class="wrap webtanan-admin" dir="rtl">
            <h1><?php esc_html_e('نظرسنجی‌های نوبت', 'webtanan-booking'); ?></h1>
            <?php self::render_notice(); ?>
            <div class="webtanan-admin-cards">
                <div class="webtanan-admin-card"><strong><?php echo esc_html(number_format_i18n((int) ($stats['total_count'] ?? 0))); ?></strong><span><?php esc_html_e('کل نظرها', 'webtanan-booking'); ?></span></div>
                <div class="webtanan-admin-card"><strong><?php echo esc_html(number_format_i18n((float) ($stats['avg_rating'] ?? 0), 1)); ?></strong><span><?php esc_html_e('میانگین امتیاز', 'webtanan-booking'); ?></span></div>
                <div class="webtanan-admin-card"><strong><?php echo esc_html(number_format_i18n((int) ($stats['pending_count'] ?? 0))); ?></strong><span><?php esc_html_e('در انتظار بررسی', 'webtanan-booking'); ?></span></div>
                <div class="webtanan-admin-card"><strong><?php echo esc_html(number_format_i18n((int) ($stats['approved_count'] ?? 0))); ?></strong><span><?php esc_html_e('منتشر شده', 'webtanan-booking'); ?></span></div>
            </div>

            <form method="get" class="webtanan-filter-bar">
                <input type="hidden" name="page" value="webtanan-booking-surveys">
                <?php self::doctor_select('doctor_id', $doctor_id, __('همه پزشکان', 'webtanan-booking')); ?>
                <?php self::select_from_map('status', self::survey_statuses(), $status, __('همه وضعیت‌ها', 'webtanan-booking')); ?>
                <select name="rating">
                    <option value="0"><?php esc_html_e('همه امتیازها', 'webtanan-booking'); ?></option>
                    <?php for ($i = 5; $i >= 1; $i--) : ?>
                        <option value="<?php echo esc_attr((string) $i); ?>" <?php selected($rating, $i); ?>><?php echo esc_html(sprintf(__('%d ستاره', 'webtanan-booking'), $i)); ?></option>
                    <?php endfor; ?>
                </select>
                <input type="search" name="search" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('جستجوی بیمار، موبایل، کد نوبت یا متن نظر', 'webtanan-booking'); ?>">
                <?php submit_button(__('فیلتر', 'webtanan-booking'), 'secondary', '', false); ?>
            </form>

            <table class="widefat striped webtanan-admin-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e('نوبت', 'webtanan-booking'); ?></th>
                        <th><?php esc_html_e('پزشک', 'webtanan-booking'); ?></th>
                        <th><?php esc_html_e('بیمار', 'webtanan-booking'); ?></th>
                        <th><?php esc_html_e('امتیاز', 'webtanan-booking'); ?></th>
                        <th><?php esc_html_e('وضعیت', 'webtanan-booking'); ?></th>
                        <th><?php esc_html_e('متن نظر', 'webtanan-booking'); ?></th>
                        <th><?php esc_html_e('تاریخ ثبت', 'webtanan-booking'); ?></th>
                        <th><?php esc_html_e('عملیات', 'webtanan-booking'); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $row) : ?>
                    <tr>
                        <td><code><?php echo esc_html((string) $row['appointment_code']); ?></code><br><span class="description"><?php echo esc_html(($row['appointment_date'] ?: '-') . ' ' . substr((string) $row['start_time'], 0, 5)); ?></span></td>
                        <td><?php echo esc_html($row['doctor_title'] ?: '#' . $row['doctor_id']); ?></td>
                        <td><?php echo esc_html(trim((string) $row['patient_first_name'] . ' ' . (string) $row['patient_last_name']) ?: '-'); ?><br><span dir="ltr"><?php echo esc_html((string) $row['patient_mobile']); ?></span></td>
                        <td><strong><?php echo esc_html(str_repeat('★', max(1, min(5, (int) $row['rating'])))); ?></strong></td>
                        <td><?php echo esc_html(self::survey_statuses()[$row['status']] ?? $row['status']); ?></td>
                        <td><?php echo esc_html(wp_trim_words((string) $row['feedback'], 20)); ?></td>
                        <td><?php echo esc_html((string) $row['created_at']); ?></td>
                        <td><?php self::render_survey_actions($row); ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$rows) : ?><tr><td colspan="8"><?php esc_html_e('نظری پیدا نشد.', 'webtanan-booking'); ?></td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public static function render_financial_reports(): void {
        global $wpdb;

        $ledger = DB::table('wallets_ledger');
        $transactions = DB::table('transactions');
        $appointments = DB::table('appointments');
        $doctors = DB::table('doctors');

        $platform_commission = (float) $wpdb->get_var("SELECT COALESCE(SUM(amount), 0) FROM $ledger WHERE user_type = 'platform' AND entry_type = 'commission' AND amount > 0");
        $doctor_withdrawable = (float) $wpdb->get_var(
            "SELECT COALESCE(SUM(CASE WHEN l.balance_after > 0 THEN l.balance_after ELSE 0 END), 0)
            FROM $ledger l
            INNER JOIN (
                SELECT user_id, user_type, MAX(id) AS max_id
                FROM $ledger
                WHERE user_type IN ('doctor','secretary')
                GROUP BY user_id, user_type
            ) latest ON latest.max_id = l.id"
        );
        $pay_at_clinic_debt = (float) $wpdb->get_var(
            "SELECT COALESCE(SUM(CASE WHEN l.balance_after < 0 THEN ABS(l.balance_after) ELSE 0 END), 0)
            FROM $ledger l
            INNER JOIN (
                SELECT user_id, user_type, MAX(id) AS max_id
                FROM $ledger
                WHERE user_type IN ('doctor','secretary')
                GROUP BY user_id, user_type
            ) latest ON latest.max_id = l.id"
        );
        $patient_wallets = (float) $wpdb->get_var(
            "SELECT COALESCE(SUM(CASE WHEN l.balance_after > 0 THEN l.balance_after ELSE 0 END), 0)
            FROM $ledger l
            INNER JOIN (
                SELECT user_id, user_type, MAX(id) AS max_id
                FROM $ledger
                WHERE user_type = 'patient'
                GROUP BY user_id, user_type
            ) latest ON latest.max_id = l.id"
        );

        $recent_success = $wpdb->get_results(
            "SELECT t.*, a.appointment_code, p.post_title AS doctor_title
            FROM $transactions t
            LEFT JOIN $appointments a ON a.id = t.appointment_id
            LEFT JOIN $doctors d ON d.id = t.doctor_id
            LEFT JOIN $wpdb->posts p ON p.ID = d.post_id
            WHERE t.status IN ('verified','paid')
            ORDER BY COALESCE(t.verified_at, t.updated_at, t.created_at) DESC, t.id DESC
            LIMIT 20",
            ARRAY_A
        );
        $late_wallet_credits = $wpdb->get_results(
            "SELECT t.*, a.appointment_code, l.balance_after
            FROM $transactions t
            LEFT JOIN $appointments a ON a.id = t.appointment_id
            LEFT JOIN $ledger l ON l.related_transaction_id = t.id AND l.user_type = 'patient' AND l.entry_type = 'credit'
            WHERE t.status = 'expired_lock_wallet_charged'
            ORDER BY t.id DESC
            LIMIT 20",
            ARRAY_A
        );

        ?>
        <div class="wrap webtanan-admin" dir="rtl">
            <h1><?php esc_html_e('گزارش‌های مالی پلتفرم', 'webtanan-booking'); ?></h1>
            <?php self::render_notice(); ?>
            <div class="webtanan-admin-cards">
                <div class="webtanan-admin-card"><strong><?php echo esc_html(number_format_i18n($platform_commission)); ?></strong><span><?php esc_html_e('کل کارمزد ثبت‌شده پلتفرم', 'webtanan-booking'); ?></span></div>
                <div class="webtanan-admin-card"><strong><?php echo esc_html(number_format_i18n($doctor_withdrawable)); ?></strong><span><?php esc_html_e('درآمد قابل برداشت پزشکان/منشی‌ها', 'webtanan-booking'); ?></span></div>
                <div class="webtanan-admin-card"><strong><?php echo esc_html(number_format_i18n($pay_at_clinic_debt)); ?></strong><span><?php esc_html_e('بدهی کمیسیون پرداخت در مطب', 'webtanan-booking'); ?></span></div>
                <div class="webtanan-admin-card"><strong><?php echo esc_html(number_format_i18n($patient_wallets)); ?></strong><span><?php esc_html_e('موجودی کیف پول بیماران', 'webtanan-booking'); ?></span></div>
            </div>

            <div class="webtanan-admin-panel">
                <h2><?php esc_html_e('پرداخت‌های موفق اخیر', 'webtanan-booking'); ?></h2>
                <table class="widefat striped">
                    <thead><tr><th><?php esc_html_e('کد تراکنش', 'webtanan-booking'); ?></th><th><?php esc_html_e('نوبت', 'webtanan-booking'); ?></th><th><?php esc_html_e('پزشک', 'webtanan-booking'); ?></th><th><?php esc_html_e('درگاه', 'webtanan-booking'); ?></th><th><?php esc_html_e('مبلغ', 'webtanan-booking'); ?></th><th><?php esc_html_e('پیگیری', 'webtanan-booking'); ?></th><th><?php esc_html_e('تاریخ تایید', 'webtanan-booking'); ?></th></tr></thead>
                    <tbody>
                    <?php foreach ($recent_success as $row) : ?>
                        <tr>
                            <td><code><?php echo esc_html($row['transaction_code']); ?></code></td>
                            <td><?php echo esc_html($row['appointment_code'] ?: (string) $row['appointment_id']); ?></td>
                            <td><?php echo esc_html($row['doctor_title'] ?: '-'); ?></td>
                            <td><?php echo esc_html($row['gateway_name']); ?></td>
                            <td><?php echo esc_html(number_format_i18n((float) $row['amount'])); ?></td>
                            <td dir="ltr"><?php echo esc_html($row['gateway_tracking_number'] ?: $row['gateway_ref_id']); ?></td>
                            <td><?php echo esc_html($row['verified_at'] ?: $row['updated_at']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$recent_success) : ?><tr><td colspan="7"><?php esc_html_e('پرداخت موفقی ثبت نشده است.', 'webtanan-booking'); ?></td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="webtanan-admin-panel">
                <h2><?php esc_html_e('شارژ کیف پول بابت بازگشت دیرهنگام', 'webtanan-booking'); ?></h2>
                <table class="widefat striped">
                    <thead><tr><th><?php esc_html_e('کد تراکنش', 'webtanan-booking'); ?></th><th><?php esc_html_e('کاربر', 'webtanan-booking'); ?></th><th><?php esc_html_e('نوبت', 'webtanan-booking'); ?></th><th><?php esc_html_e('مبلغ', 'webtanan-booking'); ?></th><th><?php esc_html_e('مانده بعد', 'webtanan-booking'); ?></th><th><?php esc_html_e('تاریخ', 'webtanan-booking'); ?></th></tr></thead>
                    <tbody>
                    <?php foreach ($late_wallet_credits as $row) : ?>
                        <tr>
                            <td><code><?php echo esc_html($row['transaction_code']); ?></code></td>
                            <td><?php echo esc_html((string) $row['user_id']); ?></td>
                            <td><?php echo esc_html($row['appointment_code'] ?: (string) $row['appointment_id']); ?></td>
                            <td><?php echo esc_html(number_format_i18n((float) $row['amount'])); ?></td>
                            <td><?php echo esc_html(null !== $row['balance_after'] ? number_format_i18n((float) $row['balance_after']) : '-'); ?></td>
                            <td><?php echo esc_html($row['updated_at']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$late_wallet_credits) : ?><tr><td colspan="6"><?php esc_html_e('بازگشت دیرهنگام شارژشده‌ای ثبت نشده است.', 'webtanan-booking'); ?></td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }

    public static function render_settlements(): void {
        global $wpdb;

        $where = '1=1';
        $params = array();
        $doctor_id = absint($_GET['doctor_id'] ?? 0);
        $status = self::request_key('status');
        if ($doctor_id) {
            $where .= ' AND s.doctor_id = %d';
            $params[] = $doctor_id;
        }
        if ($status) {
            $where .= ' AND s.status = %s';
            $params[] = $status;
        }
        $sql = 'SELECT s.*, p.post_title AS doctor_title, d.user_id FROM ' . DB::table('settlement_requests') . ' s LEFT JOIN ' . DB::table('doctors') . ' d ON d.id = s.doctor_id LEFT JOIN ' . $wpdb->posts . " p ON p.ID = d.post_id WHERE $where ORDER BY s.id DESC LIMIT 100";
        $rows = $params ? $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A) : $wpdb->get_results($sql, ARRAY_A);

        ?>
        <div class="wrap webtanan-admin" dir="rtl">
            <h1><?php esc_html_e('تسویه پزشکان', 'webtanan-booking'); ?></h1>
            <?php self::render_notice(); ?>
            <div class="webtanan-admin-layout">
                <div class="webtanan-admin-main">
                    <form method="get" class="webtanan-filter-bar">
                        <input type="hidden" name="page" value="webtanan-booking-settlements">
                        <?php self::doctor_select('doctor_id', $doctor_id, __('همه پزشکان', 'webtanan-booking')); ?>
                        <?php self::select_from_map('status', self::settlement_statuses(), $status, __('همه وضعیت‌ها', 'webtanan-booking')); ?>
                        <?php submit_button(__('فیلتر', 'webtanan-booking'), 'secondary', '', false); ?>
                    </form>
                    <?php if ($doctor_id) : ?>
                        <?php $summary = Booking::settlement_summary($doctor_id); ?>
                        <div class="webtanan-admin-cards">
                            <div class="webtanan-admin-card"><strong><?php echo esc_html(number_format_i18n((float) $summary['total_balance'])); ?></strong><span><?php esc_html_e('موجودی کل', 'webtanan-booking'); ?></span></div>
                            <div class="webtanan-admin-card"><strong><?php echo esc_html(number_format_i18n((float) $summary['available_balance'])); ?></strong><span><?php esc_html_e('قابل برداشت', 'webtanan-booking'); ?></span></div>
                            <div class="webtanan-admin-card"><strong><?php echo esc_html(number_format_i18n((float) $summary['pending_settlement'])); ?></strong><span><?php esc_html_e('تسویه در انتظار', 'webtanan-booking'); ?></span></div>
                            <div class="webtanan-admin-card"><strong><?php echo esc_html(number_format_i18n((float) $summary['commission_debt'])); ?></strong><span><?php esc_html_e('بدهی کمیسیون حضوری', 'webtanan-booking'); ?></span></div>
                        </div>
                    <?php endif; ?>
                    <table class="widefat striped">
                        <thead><tr><th><?php esc_html_e('پزشک', 'webtanan-booking'); ?></th><th><?php esc_html_e('مبلغ', 'webtanan-booking'); ?></th><th><?php esc_html_e('شبا', 'webtanan-booking'); ?></th><th><?php esc_html_e('وضعیت', 'webtanan-booking'); ?></th><th><?php esc_html_e('پیگیری بانکی', 'webtanan-booking'); ?></th><th><?php esc_html_e('درخواست/پردازش', 'webtanan-booking'); ?></th><th><?php esc_html_e('یادداشت', 'webtanan-booking'); ?></th><th><?php esc_html_e('عملیات', 'webtanan-booking'); ?></th></tr></thead>
                        <tbody>
                        <?php foreach ($rows as $row) : ?>
                            <tr><td><?php echo esc_html($row['doctor_title'] ?: '#' . $row['doctor_id']); ?></td><td><?php echo esc_html(number_format_i18n((float) $row['amount'])); ?></td><td dir="ltr"><?php echo esc_html($row['iban']); ?></td><td><?php echo esc_html(self::settlement_statuses()[$row['status']] ?? $row['status']); ?></td><td dir="ltr"><?php echo esc_html($row['bank_tracking_number'] ?? '-'); ?></td><td><?php echo esc_html(($row['requested_at'] ?: '-') . ' / ' . ($row['processed_at'] ?: '-')); ?></td><td><?php echo esc_html(wp_trim_words((string) $row['admin_note'], 12)); ?></td><td><?php self::render_settlement_actions($row); ?></td></tr>
                        <?php endforeach; ?>
                        <?php if (!$rows) : ?><tr><td colspan="8"><?php esc_html_e('درخواست تسویه‌ای یافت نشد.', 'webtanan-booking'); ?></td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="webtanan-admin-side">
                    <?php self::render_settlement_form(); ?>
                </div>
            </div>
        </div>
        <?php
    }

    public static function render_logs_overview(): void {
        global $wpdb;

        $otp_rows = $wpdb->get_results(
            'SELECT id, mobile, purpose, attempt_count, is_used, created_at, used_at FROM ' . DB::table('otp_logs') . ' ORDER BY id DESC LIMIT 30',
            ARRAY_A
        );
        $sms_rows = $wpdb->get_results(
            'SELECT id, mobile, message_type, pattern_code, status, related_appointment_id, created_at FROM ' . DB::table('sms_logs') . ' ORDER BY id DESC LIMIT 30',
            ARRAY_A
        );

        ?>
        <div class="wrap webtanan-admin" dir="rtl">
            <h1><?php esc_html_e('لاگ پیامک و OTP', 'webtanan-booking'); ?></h1>
            <?php self::render_notice(); ?>
            <div class="webtanan-admin-cards">
                <div class="webtanan-admin-card">
                    <strong><?php echo esc_html(number_format_i18n((int) $wpdb->get_var('SELECT COUNT(*) FROM ' . DB::table('otp_logs')))); ?></strong>
                    <span><?php esc_html_e('کل OTPها', 'webtanan-booking'); ?></span>
                </div>
                <div class="webtanan-admin-card">
                    <strong><?php echo esc_html(number_format_i18n((int) $wpdb->get_var('SELECT COUNT(*) FROM ' . DB::table('sms_logs')))); ?></strong>
                    <span><?php esc_html_e('کل پیامک‌ها', 'webtanan-booking'); ?></span>
                </div>
                <div class="webtanan-admin-card">
                    <strong><?php echo esc_html(number_format_i18n((int) $wpdb->get_var('SELECT COUNT(*) FROM ' . DB::table('sms_logs') . " WHERE status IN ('failed','error')"))); ?></strong>
                    <span><?php esc_html_e('پیامک‌های ناموفق', 'webtanan-booking'); ?></span>
                </div>
            </div>
            <div class="webtanan-admin-layout">
                <div class="webtanan-admin-main">
                    <div class="webtanan-admin-panel">
                        <h2><?php esc_html_e('آخرین پیامک‌ها', 'webtanan-booking'); ?></h2>
                        <p>
                            <a class="button" href="<?php echo esc_url(self::page_url('webtanan-booking-sms-logs')); ?>"><?php esc_html_e('فیلتر کامل لاگ پیامک', 'webtanan-booking'); ?></a>
                        </p>
                        <?php self::render_simple_table($sms_rows, array('id', 'mobile', 'message_type', 'pattern_code', 'status', 'related_appointment_id', 'created_at'), __('لاگ پیامکی یافت نشد.', 'webtanan-booking')); ?>
                    </div>
                </div>
                <div class="webtanan-admin-side">
                    <div class="webtanan-admin-panel">
                        <h2><?php esc_html_e('آخرین OTPها', 'webtanan-booking'); ?></h2>
                        <p>
                            <a class="button" href="<?php echo esc_url(self::page_url('webtanan-booking-otp-logs')); ?>"><?php esc_html_e('فیلتر کامل لاگ OTP', 'webtanan-booking'); ?></a>
                        </p>
                        <?php self::render_simple_table($otp_rows, array('id', 'mobile', 'purpose', 'attempt_count', 'is_used', 'created_at', 'used_at'), __('لاگ OTP یافت نشد.', 'webtanan-booking')); ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    public static function render_otp_logs(): void {
        global $wpdb;

        $where = '1=1';
        $params = array();
        $mobile = self::request_text('mobile');
        $purpose = self::request_key('purpose');
        $used = self::request_text('used');
        if ($mobile) {
            $where .= ' AND mobile LIKE %s';
            $params[] = '%' . $wpdb->esc_like($mobile) . '%';
        }
        if ($purpose) {
            $where .= ' AND purpose = %s';
            $params[] = $purpose;
        }
        if ('' !== $used) {
            $where .= ' AND is_used = %d';
            $params[] = absint($used);
        }
        $sql = 'SELECT id, mobile, purpose, expires_at, attempt_count, is_used, ip_address, created_at, used_at FROM ' . DB::table('otp_logs') . " WHERE $where ORDER BY id DESC LIMIT 150";
        $rows = $params ? $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A) : $wpdb->get_results($sql, ARRAY_A);

        ?>
        <div class="wrap webtanan-admin" dir="rtl">
            <h1><?php esc_html_e('لاگ OTP', 'webtanan-booking'); ?></h1>
            <?php self::render_notice(); ?>
            <form method="get" class="webtanan-filter-bar">
                <input type="hidden" name="page" value="webtanan-booking-otp-logs">
                <input type="text" name="mobile" value="<?php echo esc_attr($mobile); ?>" placeholder="<?php esc_attr_e('موبایل', 'webtanan-booking'); ?>">
                <input type="text" name="purpose" value="<?php echo esc_attr($purpose); ?>" placeholder="<?php esc_attr_e('هدف کد ورود', 'webtanan-booking'); ?>">
                <select name="used"><option value=""><?php esc_html_e('همه', 'webtanan-booking'); ?></option><option value="1" <?php selected($used, '1'); ?>><?php esc_html_e('استفاده‌شده', 'webtanan-booking'); ?></option><option value="0" <?php selected($used, '0'); ?>><?php esc_html_e('استفاده‌نشده', 'webtanan-booking'); ?></option></select>
                <?php submit_button(__('فیلتر', 'webtanan-booking'), 'secondary', '', false); ?>
            </form>
            <?php self::render_simple_table($rows, array('id', 'mobile', 'purpose', 'expires_at', 'attempt_count', 'is_used', 'ip_address', 'created_at', 'used_at'), __('لاگی یافت نشد.', 'webtanan-booking')); ?>
        </div>
        <?php
    }

    public static function render_sms_logs(): void {
        global $wpdb;

        $where = '1=1';
        $params = array();
        foreach (array('mobile', 'message_type', 'status') as $field) {
            $value = 'mobile' === $field ? self::request_text($field) : self::request_key($field);
            if ($value) {
                $where .= " AND $field " . ('mobile' === $field ? 'LIKE %s' : '= %s');
                $params[] = 'mobile' === $field ? '%' . $wpdb->esc_like($value) . '%' : $value;
            }
        }
        $appointment_id = absint($_GET['appointment_id'] ?? 0);
        if ($appointment_id) {
            $where .= ' AND related_appointment_id = %d';
            $params[] = $appointment_id;
        }
        $sql = 'SELECT * FROM ' . DB::table('sms_logs') . " WHERE $where ORDER BY id DESC LIMIT 150";
        $rows = $params ? $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A) : $wpdb->get_results($sql, ARRAY_A);

        ?>
        <div class="wrap webtanan-admin" dir="rtl">
            <h1><?php esc_html_e('لاگ پیامک', 'webtanan-booking'); ?></h1>
            <?php self::render_notice(); ?>
            <form method="get" class="webtanan-filter-bar">
                <input type="hidden" name="page" value="webtanan-booking-sms-logs">
                <input type="text" name="mobile" value="<?php echo esc_attr(self::request_text('mobile')); ?>" placeholder="<?php esc_attr_e('موبایل', 'webtanan-booking'); ?>">
                <select name="message_type"><option value=""><?php esc_html_e('همه نوع پیامک‌ها', 'webtanan-booking'); ?></option><?php foreach (SMS::message_types() as $type => $label) : ?><option value="<?php echo esc_attr($type); ?>" <?php selected(self::request_key('message_type'), $type); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></select>
                <input type="text" name="status" value="<?php echo esc_attr(self::request_key('status')); ?>" placeholder="<?php esc_attr_e('وضعیت', 'webtanan-booking'); ?>">
                <input type="number" name="appointment_id" min="0" value="<?php echo esc_attr((string) $appointment_id); ?>" placeholder="<?php esc_attr_e('شناسه نوبت', 'webtanan-booking'); ?>">
                <?php submit_button(__('فیلتر', 'webtanan-booking'), 'secondary', '', false); ?>
            </form>
            <table class="widefat striped">
                <thead><tr><th><?php esc_html_e('تاریخ', 'webtanan-booking'); ?></th><th><?php esc_html_e('موبایل', 'webtanan-booking'); ?></th><th><?php esc_html_e('نوع', 'webtanan-booking'); ?></th><th><?php esc_html_e('پترن', 'webtanan-booking'); ?></th><th><?php esc_html_e('وضعیت', 'webtanan-booking'); ?></th><th><?php esc_html_e('نوبت', 'webtanan-booking'); ?></th><th><?php esc_html_e('پاسخ سرویس‌دهنده', 'webtanan-booking'); ?></th></tr></thead>
                <tbody>
                <?php foreach ($rows as $row) : ?>
                    <tr><td><?php echo esc_html($row['created_at']); ?></td><td dir="ltr"><?php echo esc_html($row['mobile']); ?></td><td><?php echo esc_html($row['message_type']); ?></td><td><?php echo esc_html($row['pattern_code']); ?></td><td><?php echo esc_html($row['status']); ?></td><td><?php echo esc_html((string) $row['related_appointment_id']); ?></td><td><code><?php echo esc_html(wp_trim_words((string) $row['provider_response'], 25)); ?></code></td></tr>
                <?php endforeach; ?>
                <?php if (!$rows) : ?><tr><td colspan="7"><?php esc_html_e('لاگ پیامکی یافت نشد.', 'webtanan-booking'); ?></td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public static function render_settings(): void {
        $settings = DB::get_settings();

        ?>
        <div class="wrap webtanan-admin" dir="rtl">
            <h1><?php esc_html_e('تنظیمات افزونه', 'webtanan-booking'); ?></h1>
            <?php self::render_notice(); ?>
            <form method="post" action="options.php">
                <?php settings_fields('webtanan_booking_settings'); ?>
                <h2><?php esc_html_e('تنظیمات عمومی', 'webtanan-booking'); ?></h2>
                <table class="form-table" role="presentation">
                    <tr><th scope="row"><?php esc_html_e('نوع کارمزد پیش‌فرض', 'webtanan-booking'); ?></th><td><select name="<?php echo esc_attr(DB::OPTION_SETTINGS); ?>[default_commission_type]"><option value="percent" <?php selected($settings['default_commission_type'], 'percent'); ?>><?php esc_html_e('درصدی', 'webtanan-booking'); ?></option><option value="fixed" <?php selected($settings['default_commission_type'], 'fixed'); ?>><?php esc_html_e('مبلغ ثابت', 'webtanan-booking'); ?></option></select></td></tr>
                    <tr><th scope="row"><?php esc_html_e('مقدار کارمزد پیش‌فرض', 'webtanan-booking'); ?></th><td><input type="number" min="0" step="1000" name="<?php echo esc_attr(DB::OPTION_SETTINGS); ?>[default_commission_value]" value="<?php echo esc_attr((string) $settings['default_commission_value']); ?>"></td></tr>
                    <tr><th scope="row"><?php esc_html_e('مدت قفل نوبت به دقیقه', 'webtanan-booking'); ?></th><td><input type="number" min="1" name="<?php echo esc_attr(DB::OPTION_SETTINGS); ?>[lock_duration_minutes]" value="<?php echo esc_attr((string) $settings['lock_duration_minutes']); ?>"></td></tr>
                    <tr><th scope="row"><?php esc_html_e('انقضای OTP به دقیقه', 'webtanan-booking'); ?></th><td><input type="number" min="1" name="<?php echo esc_attr(DB::OPTION_SETTINGS); ?>[otp_expiration_minutes]" value="<?php echo esc_attr((string) $settings['otp_expiration_minutes']); ?>"></td></tr>
                    <tr><th scope="row"><?php esc_html_e('حداکثر ارسال OTP در بازه', 'webtanan-booking'); ?></th><td><input type="number" min="1" name="<?php echo esc_attr(DB::OPTION_SETTINGS); ?>[otp_rate_limit_max_sends]" value="<?php echo esc_attr((string) ($settings['otp_rate_limit_max_sends'] ?? 3)); ?>"> <span class="description"><?php esc_html_e('پیش‌فرض: ۳ بار', 'webtanan-booking'); ?></span></td></tr>
                    <tr><th scope="row"><?php esc_html_e('بازه محدودیت ارسال OTP به دقیقه', 'webtanan-booking'); ?></th><td><input type="number" min="1" name="<?php echo esc_attr(DB::OPTION_SETTINGS); ?>[otp_rate_limit_window_minutes]" value="<?php echo esc_attr((string) ($settings['otp_rate_limit_window_minutes'] ?? 15)); ?>"> <span class="description"><?php esc_html_e('پیش‌فرض: ۱۵ دقیقه', 'webtanan-booking'); ?></span></td></tr>
                    <tr><th scope="row"><?php esc_html_e('شناسه کاربر کیف پول پلتفرم', 'webtanan-booking'); ?></th><td><input type="number" min="0" name="<?php echo esc_attr(DB::OPTION_SETTINGS); ?>[platform_wallet_user_id]" value="<?php echo esc_attr((string) $settings['platform_wallet_user_id']); ?>"></td></tr>
                    <tr><th scope="row"><?php esc_html_e('محدوده شارژ کیف پول', 'webtanan-booking'); ?></th><td><input type="number" min="1000" name="<?php echo esc_attr(DB::OPTION_SETTINGS); ?>[wallet_topup_min_amount]" value="<?php echo esc_attr((string) ($settings['wallet_topup_min_amount'] ?? 10000)); ?>"> <input type="number" min="1000" name="<?php echo esc_attr(DB::OPTION_SETTINGS); ?>[wallet_topup_max_amount]" value="<?php echo esc_attr((string) ($settings['wallet_topup_max_amount'] ?? 50000000)); ?>"> <span class="description"><?php esc_html_e('حداقل و حداکثر شارژ آنلاین کیف پول بیمار.', 'webtanan-booking'); ?></span></td></tr>
                    <?php self::render_font_settings_fields($settings); ?>
                </table>
                <?php self::render_cancellation_settings_fields($settings); ?>
                <?php self::render_gateway_settings_fields($settings); ?>
                <?php self::render_sms_settings_fields($settings); ?>
                <?php submit_button(__('ذخیره تنظیمات', 'webtanan-booking')); ?>
            </form>
            <?php self::render_sms_test_forms(); ?>
        </div>
        <?php
    }

    public static function render_docs(): void {
        ?>
        <div class="wrap webtanan-admin" dir="rtl">
            <h1><?php esc_html_e('مستندات', 'webtanan-booking'); ?></h1>
            <p><?php esc_html_e('مستندات توسعه در پوشه docs افزونه قرار دارد. از فایل docs/00-index.md شروع کنید.', 'webtanan-booking'); ?></p>
            <p><a class="button button-primary" href="<?php echo esc_url(WEBTANAN_BOOKING_URL . 'docs/00-index.md'); ?>" target="_blank" rel="noreferrer"><?php esc_html_e('باز کردن فهرست مستندات', 'webtanan-booking'); ?></a></p>
        </div>
        <?php
    }

    public static function render_shortcodes(): void {
        $rows = self::shortcode_rows();
        ?>
        <div class="wrap webtanan-admin" dir="rtl">
            <h1><?php esc_html_e('شورت‌کدهای راه‌اندازی سیستم', 'webtanan-booking'); ?></h1>
            <p><?php esc_html_e('برای ساخت صفحات اصلی سایت، این شورت‌کدها را در برگه‌های وردپرس یا ویجت‌های المنتور قرار دهید.', 'webtanan-booking'); ?></p>
            <table class="widefat striped webtanan-admin-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e('کاربرد', 'webtanan-booking'); ?></th>
                        <th><?php esc_html_e('شورت‌کد', 'webtanan-booking'); ?></th>
                        <th><?php esc_html_e('پیشنهاد صفحه', 'webtanan-booking'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row) : ?>
                        <tr>
                            <td><strong><?php echo esc_html($row['title']); ?></strong><br><span class="description"><?php echo esc_html($row['description']); ?></span></td>
                            <td><code class="webtanan-shortcode-code"><?php echo esc_html($row['shortcode']); ?></code></td>
                            <td><?php echo esc_html($row['page']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div class="webtanan-admin-panel">
                <h2><?php esc_html_e('لینک‌های عمومی خودکار', 'webtanan-booking'); ?></h2>
                <p><?php esc_html_e('پروفایل هر پزشک از لینک خود پست‌تایپ پزشک نمایش داده می‌شود. آرشیو پزشکان نیز از مسیر آرشیو saas_doctors در دسترس است.', 'webtanan-booking'); ?></p>
                <p><a class="button" href="<?php echo esc_url(self::public_doctors_archive_url()); ?>" target="_blank" rel="noreferrer"><?php esc_html_e('نمایش آرشیو پزشکان', 'webtanan-booking'); ?></a></p>
            </div>
        </div>
        <?php
    }

    public static function handle_update_survey(): void {
        global $wpdb;

        self::require_capability('webtanan_manage_booking');
        check_admin_referer('webtanan_booking_update_survey');

        $survey_id = absint($_POST['survey_id'] ?? 0);
        $status = sanitize_key($_POST['status'] ?? '');
        if (!$survey_id || !array_key_exists($status, self::survey_statuses())) {
            self::set_notice('error', __('درخواست مدیریت نظر معتبر نیست.', 'webtanan-booking'));
            self::redirect('webtanan-booking-surveys');
        }

        $table = DB::table('survey_responses');
        $survey = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d LIMIT 1", $survey_id), ARRAY_A);
        if (!$survey) {
            self::set_notice('error', __('نظر پیدا نشد.', 'webtanan-booking'));
            self::redirect('webtanan-booking-surveys');
        }

        $wpdb->update($table, array('status' => $status, 'updated_at' => DB::now()), array('id' => $survey_id), array('%s', '%s'), array('%d'));

        $comment_id = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT comment_id FROM $wpdb->commentmeta WHERE meta_key = %s AND meta_value = %d LIMIT 1",
                '_webtanan_survey_appointment_id',
                (int) $survey['appointment_id']
            )
        );
        if ($comment_id > 0) {
            if ('approved' === $status && !empty($survey['public_consent'])) {
                wp_set_comment_status($comment_id, 'approve');
            } else {
                wp_set_comment_status($comment_id, 'hold');
            }
        }

        self::set_notice('success', __('وضعیت نظر به‌روزرسانی شد.', 'webtanan-booking'));
        wp_safe_redirect(wp_get_referer() ?: self::page_url('webtanan-booking-surveys'));
        exit;
    }

    public static function handle_save_doctor(): void {
        self::require_capability('webtanan_manage_doctors');
        check_admin_referer('webtanan_booking_save_doctor');

        $raw = isset($_POST['doctor']) && is_array($_POST['doctor']) ? wp_unslash($_POST['doctor']) : array();
        $title = sanitize_text_field($raw['title'] ?? '');
        if ('' === $title) {
            self::set_notice('error', __('نام پزشک الزامی است.', 'webtanan-booking'));
            self::redirect('webtanan-booking-doctors');
        }

        $post_id = absint($raw['post_id'] ?? 0);
        $user_id = absint($raw['user_id'] ?? 0);
        $postarr = array(
            'ID' => $post_id,
            'post_type' => 'saas_doctors',
            'post_title' => $title,
            'post_content' => wp_kses_post($raw['profile_content'] ?? ''),
            'post_excerpt' => sanitize_textarea_field($raw['profile_excerpt'] ?? ''),
            'post_status' => in_array(($raw['post_status'] ?? 'publish'), array('publish', 'draft', 'pending'), true) ? $raw['post_status'] : 'publish',
            'post_author' => $user_id ?: get_current_user_id(),
        );

        $result = $post_id ? wp_update_post($postarr, true) : wp_insert_post($postarr, true);
        if (is_wp_error($result)) {
            self::set_notice('error', $result->get_error_message());
            self::redirect('webtanan-booking-doctors');
        }

        $featured_image_id = absint($raw['featured_image_id'] ?? 0);
        if ($featured_image_id > 0) {
            set_post_thumbnail((int) $result, $featured_image_id);
        } else {
            delete_post_thumbnail((int) $result);
        }

        $gallery_ids = self::sanitize_id_list($raw['gallery_image_ids'] ?? '');
        update_post_meta((int) $result, '_webtanan_doctor_gallery_ids', implode(',', $gallery_ids));

        $doctor_id = self::upsert_doctor((int) $result, $raw);
        self::set_notice('success', __('پزشک ذخیره شد.', 'webtanan-booking'));
        self::redirect('webtanan-booking-doctors', array('doctor_id' => $doctor_id));
    }

    public static function handle_save_secretary_assignment(): void {
        self::require_capability('webtanan_manage_doctors');
        check_admin_referer('webtanan_booking_save_secretary_assignment');

        $secretary_id = absint($_POST['secretary_user_id'] ?? 0);
        $user = $secretary_id ? get_user_by('id', $secretary_id) : false;
        if (!$user || !in_array('webtanan_secretary', (array) $user->roles, true)) {
            self::set_notice('error', __('کاربر منشی معتبر نیست.', 'webtanan-booking'));
            self::redirect('webtanan-booking-secretaries');
        }

        $doctor_ids = isset($_POST['doctor_ids']) && is_array($_POST['doctor_ids']) ? array_map('absint', wp_unslash($_POST['doctor_ids'])) : array();
        $doctor_ids = array_values(array_unique(array_filter($doctor_ids)));
        update_user_meta($secretary_id, 'webtanan_assigned_doctor_ids', $doctor_ids);
        update_user_meta($secretary_id, 'webtanan_secretary_can_view_finance', isset($_POST['can_view_finance']) ? 'yes' : 'no');

        self::set_notice('success', __('دسترسی منشی ذخیره شد.', 'webtanan-booking'));
        self::redirect('webtanan-booking-secretaries', array('secretary_user_id' => $secretary_id));
    }

    public static function handle_save_specialty(): void {
        global $wpdb;

        self::require_capability('webtanan_manage_doctors');
        check_admin_referer('webtanan_booking_save_specialty');

        $id = absint($_POST['specialty_id'] ?? 0);
        $name = sanitize_text_field(wp_unslash($_POST['name'] ?? ''));
        if ('' === $name) {
            self::set_notice('error', __('نام تخصص الزامی است.', 'webtanan-booking'));
            self::redirect('webtanan-booking-specialties');
        }

        $now = DB::now();
        $data = array(
            'name' => $name,
            'slug' => sanitize_title(wp_unslash($_POST['slug'] ?? $name)),
            'parent_id' => absint($_POST['parent_id'] ?? 0),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
            'sort_order' => (int) ($_POST['sort_order'] ?? 0),
            'updated_at' => $now,
        );

        if ($id) {
            $wpdb->update(DB::table('specialties'), $data, array('id' => $id));
        } else {
            $data['created_at'] = $now;
            $wpdb->insert(DB::table('specialties'), $data);
            $id = (int) $wpdb->insert_id;
        }

        self::set_notice('success', __('تخصص ذخیره شد.', 'webtanan-booking'));
        self::redirect('webtanan-booking-specialties', array('specialty_id' => $id));
    }

    public static function handle_save_schedule(): void {
        global $wpdb;

        self::require_capability('webtanan_manage_doctors');
        check_admin_referer('webtanan_booking_save_schedule');

        $id = absint($_POST['schedule_id'] ?? 0);
        $doctor_id = absint($_POST['doctor_id'] ?? 0);
        $now = DB::now();
        $data = array(
            'doctor_id' => $doctor_id,
            'weekday' => self::valid_weekday(sanitize_key($_POST['weekday'] ?? 'saturday')),
            'start_time' => self::normalize_time($_POST['start_time'] ?? ''),
            'end_time' => self::normalize_time($_POST['end_time'] ?? ''),
            'slot_duration' => max(1, absint($_POST['slot_duration'] ?? 15)),
            'capacity_per_slot' => max(1, absint($_POST['capacity_per_slot'] ?? 1)),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
            'updated_at' => $now,
        );

        if (!$doctor_id || !$data['start_time'] || !$data['end_time']) {
            self::set_notice('error', __('اطلاعات برنامه کاری کامل نیست.', 'webtanan-booking'));
            self::redirect('webtanan-booking-schedules', array('doctor_id' => $doctor_id));
        }

        if ($id) {
            $wpdb->update(DB::table('schedules'), $data, array('id' => $id));
        } else {
            $data['created_at'] = $now;
            $wpdb->insert(DB::table('schedules'), $data);
        }

        self::set_notice('success', __('برنامه کاری ذخیره شد.', 'webtanan-booking'));
        self::redirect('webtanan-booking-schedules', array('doctor_id' => $doctor_id));
    }

    public static function handle_save_exception(): void {
        global $wpdb;

        self::require_capability('webtanan_manage_doctors');
        check_admin_referer('webtanan_booking_save_exception');

        $id = absint($_POST['exception_id'] ?? 0);
        $doctor_id = absint($_POST['doctor_id'] ?? 0);
        $type = sanitize_key($_POST['type'] ?? 'day_off');
        if (!in_array($type, array('day_off', 'custom_shift', 'reduced_shift', 'extra_shift'), true)) {
            $type = 'day_off';
        }
        $now = DB::now();
        $data = array(
            'doctor_id' => $doctor_id,
            'exception_date' => sanitize_text_field(wp_unslash($_POST['exception_date'] ?? '')),
            'type' => $type,
            'start_time' => 'day_off' === $type ? null : self::normalize_time($_POST['start_time'] ?? ''),
            'end_time' => 'day_off' === $type ? null : self::normalize_time($_POST['end_time'] ?? ''),
            'slot_duration' => max(1, absint($_POST['slot_duration'] ?? 15)),
            'capacity_per_slot' => max(1, absint($_POST['capacity_per_slot'] ?? 1)),
            'reason' => sanitize_textarea_field(wp_unslash($_POST['reason'] ?? '')),
            'updated_at' => $now,
        );

        if (!$doctor_id || !$data['exception_date']) {
            self::set_notice('error', __('اطلاعات استثنا کامل نیست.', 'webtanan-booking'));
            self::redirect('webtanan-booking-schedules', array('doctor_id' => $doctor_id));
        }

        if ($id) {
            $wpdb->update(DB::table('schedule_exceptions'), $data, array('id' => $id));
        } else {
            $data['created_at'] = $now;
            $wpdb->insert(DB::table('schedule_exceptions'), $data);
        }

        self::set_notice('success', __('استثنا ذخیره شد.', 'webtanan-booking'));
        self::redirect('webtanan-booking-schedules', array('doctor_id' => $doctor_id));
    }

    public static function handle_create_admin_appointment(): void {
        self::require_capability('webtanan_manage_booking');
        check_admin_referer('webtanan_booking_create_admin_appointment');

        $raw = isset($_POST['appointment']) && is_array($_POST['appointment']) ? wp_unslash($_POST['appointment']) : array();
        $result = Booking::create_staff_appointment($raw);
        self::notice_from_result($result, __('نوبت ثبت شد.', 'webtanan-booking'));
        self::redirect('webtanan-booking-appointments', array('doctor_id' => absint($raw['doctor_id'] ?? 0)));
    }

    public static function handle_update_admin_appointment(): void {
        self::require_capability('webtanan_manage_booking');
        check_admin_referer('webtanan_booking_update_admin_appointment');

        $appointment_id = absint($_POST['appointment_id'] ?? 0);
        $operation = sanitize_key($_POST['operation'] ?? '');
        if ('payment' === $operation) {
            $result = Booking::update_clinic_payment_status($appointment_id, sanitize_key($_POST['payment_status'] ?? 'unpaid'));
        } elseif ('attendance' === $operation) {
            $result = Booking::update_attendance_status($appointment_id, sanitize_key($_POST['appointment_status'] ?? 'confirmed'));
        } elseif ('cancel' === $operation) {
            $result = Booking::cancel_appointment($appointment_id, 'admin', sanitize_textarea_field(wp_unslash($_POST['reason'] ?? '')));
        } else {
            $result = new \WP_Error('webtanan_invalid_admin_operation', __('عملیات نامعتبر است.', 'webtanan-booking'));
        }

        self::notice_from_result($result, __('نوبت به‌روزرسانی شد.', 'webtanan-booking'));
        self::redirect('webtanan-booking-appointments');
    }

    public static function handle_bulk_appointment_action(): void {
        global $wpdb;

        self::require_capability('webtanan_manage_booking');
        check_admin_referer('webtanan_booking_bulk_appointment_action');

        $operation = sanitize_key($_POST['operation'] ?? '');
        $doctor_id = absint($_POST['doctor_id'] ?? 0);
        $date = sanitize_text_field(wp_unslash($_POST['appointment_date'] ?? ''));
        $reason = sanitize_textarea_field(wp_unslash($_POST['reason'] ?? ''));

        if ('cancel_day' !== $operation || !$doctor_id || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            self::set_notice('error', __('برای لغو گروهی، پزشک و تاریخ معتبر انتخاب کنید.', 'webtanan-booking'));
            self::redirect('webtanan-booking-appointments');
        }

        $ids = array_map(
            'absint',
            $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT id FROM " . DB::table('appointments') . "
                    WHERE doctor_id = %d
                        AND appointment_date = %s
                        AND appointment_status IN ('locked','confirmed','pay_at_clinic')
                    ORDER BY start_time ASC",
                    $doctor_id,
                    $date
                )
            )
        );

        if (!$ids) {
            self::set_notice('error', __('برای این پزشک و تاریخ، نوبت قابل لغوی پیدا نشد.', 'webtanan-booking'));
            self::redirect('webtanan-booking-appointments', array('doctor_id' => $doctor_id, 'date_from' => $date, 'date_to' => $date));
        }

        $summary = Booking::bulk_cancel_appointments($ids, 'admin', $reason);
        SMS::send_doctor_notification(
            $doctor_id,
            'bulk_appointment_cancelled',
            array(
                'date' => $date,
                'reason' => $reason,
                'amount' => (string) ($summary['refund_total'] ?? 0),
                'status' => 'cancelled',
            )
        );

        self::set_notice(
            'success',
            sprintf(
                __('لغو گروهی انجام شد: %1$d نوبت لغو شد، %2$d از قبل لغو شده بود، %3$d ناموفق. مجموع استرداد: %4$s تومان', 'webtanan-booking'),
                (int) $summary['cancelled'],
                (int) $summary['already_cancelled'],
                (int) $summary['failed'],
                number_format_i18n((float) $summary['refund_total'])
            ),
            $summary
        );
        self::redirect('webtanan-booking-appointments', array('doctor_id' => $doctor_id, 'date_from' => $date, 'date_to' => $date));
    }

    public static function handle_save_settlement(): void {
        self::require_capability('webtanan_manage_finance');
        check_admin_referer('webtanan_booking_save_settlement');

        $doctor_id = absint($_POST['doctor_id'] ?? 0);
        $amount = abs((float) ($_POST['amount'] ?? 0));
        if (!$doctor_id || $amount <= 0) {
            self::set_notice('error', __('پزشک و مبلغ تسویه الزامی است.', 'webtanan-booking'));
            self::redirect('webtanan-booking-settlements');
        }

        $result = Booking::create_settlement_request(
            $doctor_id,
            $amount,
            sanitize_text_field(wp_unslash($_POST['iban'] ?? '')),
            sanitize_textarea_field(wp_unslash($_POST['admin_note'] ?? ''))
        );

        self::notice_from_result($result, __('درخواست تسویه دستی ثبت شد.', 'webtanan-booking'));
        self::redirect('webtanan-booking-settlements', array('doctor_id' => $doctor_id));
    }

    public static function handle_update_settlement(): void {
        self::require_capability('webtanan_manage_finance');
        check_admin_referer('webtanan_booking_update_settlement');

        $id = absint($_POST['settlement_id'] ?? 0);
        $status = sanitize_key($_POST['status'] ?? '');
        if (!$id || !isset(self::settlement_statuses()[$status])) {
            self::set_notice('error', __('وضعیت تسویه نامعتبر است.', 'webtanan-booking'));
            self::redirect('webtanan-booking-settlements');
        }

        $result = Booking::update_settlement_request(
            $id,
            $status,
            sanitize_textarea_field(wp_unslash($_POST['admin_note'] ?? '')),
            sanitize_text_field(wp_unslash($_POST['bank_tracking_number'] ?? ''))
        );

        if (!is_wp_error($result) && 'paid' === $status && 'paid' !== ($result['old_status'] ?? '')) {
            SMS::send_doctor_notification((int) $result['doctor_id'], 'settlement_paid', array('amount' => (string) $result['amount'], 'status' => $status));
        } elseif (!is_wp_error($result)) {
            SMS::send_doctor_notification((int) $result['doctor_id'], 'settlement_status', array('amount' => (string) $result['amount'], 'status' => $status));
        } else {
            self::notice_from_result($result, __('وضعیت تسویه به‌روزرسانی شد.', 'webtanan-booking'));
            self::redirect('webtanan-booking-settlements');
        }

        self::set_notice('success', __('وضعیت تسویه به‌روزرسانی شد.', 'webtanan-booking'));
        self::redirect('webtanan-booking-settlements', array('doctor_id' => (int) $result['doctor_id']));
    }

    public static function handle_wallet_adjustment(): void {
        self::require_capability('webtanan_manage_finance');
        check_admin_referer('webtanan_booking_wallet_adjustment');

        $result = Wallet::add_entry(
            array(
                'user_id' => absint($_POST['user_id'] ?? 0),
                'user_type' => sanitize_key($_POST['user_type'] ?? 'patient'),
                'entry_type' => sanitize_key($_POST['entry_type'] ?? 'credit'),
                'amount' => abs((float) ($_POST['amount'] ?? 0)),
                'description' => sanitize_textarea_field(wp_unslash($_POST['description'] ?? __('اصلاح دستی مدیر', 'webtanan-booking'))),
            )
        );

        self::notice_from_result($result, __('اصلاح کیف پول ثبت شد.', 'webtanan-booking'));
        self::redirect('webtanan-booking-wallet', array('user_id' => absint($_POST['user_id'] ?? 0), 'user_type' => sanitize_key($_POST['user_type'] ?? 'patient')));
    }

    public static function handle_test_sms(): void {
        self::require_capability('webtanan_manage_settings');
        check_admin_referer('webtanan_booking_test_sms');

        $mobile = isset($_POST['mobile']) ? sanitize_text_field(wp_unslash($_POST['mobile'])) : '';
        $message_type = isset($_POST['message_type']) ? sanitize_key(wp_unslash($_POST['message_type'])) : 'otp';
        $result = SMS::send_pattern($mobile, $message_type, self::sample_sms_variables($message_type), 0);
        self::set_notice(in_array($result['status'], array('sent', 'test_mode'), true) ? 'success' : 'error', sprintf(__('نتیجه تست پیامک: %s', 'webtanan-booking'), $result['status']), $result['provider_response'] ?? array());
        self::redirect('webtanan-booking-settings');
    }

    public static function handle_send_normal_sms(): void {
        self::require_capability('webtanan_manage_settings');
        check_admin_referer('webtanan_booking_send_normal_sms');

        $raw_mobiles = sanitize_textarea_field(wp_unslash($_POST['mobiles'] ?? ''));
        $message = sanitize_textarea_field(wp_unslash($_POST['message'] ?? ''));
        $mobiles = preg_split('/[\s,،]+/u', $raw_mobiles);
        $result = SMS::send_normal(is_array($mobiles) ? $mobiles : array(), $message, array('source' => 'admin'));
        self::set_notice(
            in_array($result['status'], array('sent', 'test_mode'), true) ? 'success' : 'error',
            sprintf(__('نتیجه ارسال پیامک معمولی: %1$s برای %2$d گیرنده', 'webtanan-booking'), $result['status'], (int) ($result['recipients'] ?? 0)),
            $result['provider_response'] ?? array()
        );
        self::redirect('webtanan-booking-settings');
    }

    public static function handle_check_ippanel_patterns(): void {
        self::require_capability('webtanan_manage_settings');
        check_admin_referer('webtanan_booking_check_ippanel_patterns');

        $result = SMS::list_ippanel_patterns(1, 100);
        self::set_notice(!empty($result['success']) ? 'success' : 'error', !empty($result['success']) ? __('اتصال به IPPanel موفق بود.', 'webtanan-booking') : __('اتصال به IPPanel ناموفق بود.', 'webtanan-booking'), $result);
        self::redirect('webtanan-booking-settings');
    }

    private static function render_doctor_form(?array $doctor): void {
        $doctor = $doctor ?: array();
        $post = !empty($doctor['post_id']) ? get_post((int) $doctor['post_id']) : null;
        $settings = DB::get_settings();
        $post_id = $post ? (int) $post->ID : 0;
        $featured_image_id = $post_id ? (int) get_post_thumbnail_id($post_id) : 0;
        $featured_image_url = $featured_image_id ? wp_get_attachment_image_url($featured_image_id, 'thumbnail') : '';
        $gallery_ids = $post_id ? self::sanitize_id_list(get_post_meta($post_id, '_webtanan_doctor_gallery_ids', true)) : array();

        ?>
        <div class="webtanan-admin-panel">
            <h2><?php echo esc_html($doctor ? __('ویرایش پزشک', 'webtanan-booking') : __('افزودن پزشک', 'webtanan-booking')); ?></h2>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('webtanan_booking_save_doctor'); ?>
                <input type="hidden" name="action" value="webtanan_booking_save_doctor">
                <input type="hidden" name="doctor[post_id]" value="<?php echo esc_attr((string) ($doctor['post_id'] ?? 0)); ?>">
                <input type="hidden" name="doctor[id]" value="<?php echo esc_attr((string) ($doctor['id'] ?? 0)); ?>">
                <p><label><?php esc_html_e('نام پزشک', 'webtanan-booking'); ?></label><input type="text" class="widefat" name="doctor[title]" value="<?php echo esc_attr($post ? $post->post_title : ''); ?>" required></p>
                <p><label><?php esc_html_e('وضعیت انتشار پروفایل', 'webtanan-booking'); ?></label><select class="widefat" name="doctor[post_status]"><option value="publish" <?php selected($post ? $post->post_status : 'publish', 'publish'); ?>><?php esc_html_e('منتشر شده', 'webtanan-booking'); ?></option><option value="draft" <?php selected($post ? $post->post_status : '', 'draft'); ?>><?php esc_html_e('پیش‌نویس', 'webtanan-booking'); ?></option><option value="pending" <?php selected($post ? $post->post_status : '', 'pending'); ?>><?php esc_html_e('در انتظار بررسی', 'webtanan-booking'); ?></option></select></p>
                <p><label><?php esc_html_e('کاربر وردپرس پزشک', 'webtanan-booking'); ?></label><?php wp_dropdown_users(array('name' => 'doctor[user_id]', 'selected' => (int) ($doctor['user_id'] ?? 0), 'show_option_none' => __('انتخاب کاربر', 'webtanan-booking'), 'class' => 'widefat')); ?></p>
                <p><label><?php esc_html_e('منشی دریافت‌کننده سهم نوبت‌دهی', 'webtanan-booking'); ?></label><?php wp_dropdown_users(array('name' => 'doctor[secretary_user_id]', 'selected' => (int) ($doctor['secretary_user_id'] ?? 0), 'show_option_none' => __('بدون منشی؛ سهم به پزشک ثبت شود', 'webtanan-booking'), 'class' => 'widefat')); ?></p>
                <p><label><?php esc_html_e('کد پزشک', 'webtanan-booking'); ?></label><input type="text" class="widefat" name="doctor[doctor_code]" value="<?php echo esc_attr($doctor['doctor_code'] ?? ''); ?>"></p>
                <p><label><?php esc_html_e('شماره نظام پزشکی', 'webtanan-booking'); ?></label><input type="text" class="widefat" name="doctor[medical_system_number]" value="<?php echo esc_attr($doctor['medical_system_number'] ?? ''); ?>"></p>
                <p><label><?php esc_html_e('تخصص', 'webtanan-booking'); ?></label><?php self::specialty_select('doctor[specialty_id]', (int) ($doctor['specialty_id'] ?? 0)); ?></p>
                <p><label><?php esc_html_e('شناسه استان', 'webtanan-booking'); ?></label><input type="number" min="0" class="widefat" name="doctor[province_id]" value="<?php echo esc_attr((string) ($doctor['province_id'] ?? 0)); ?>"><span class="description"><?php esc_html_e('برای فیلتر عمومی و ویجت المنتور استفاده می‌شود.', 'webtanan-booking'); ?></span></p>
                <p><label><?php esc_html_e('شناسه شهر', 'webtanan-booking'); ?></label><input type="number" min="0" class="widefat" name="doctor[city_id]" value="<?php echo esc_attr((string) ($doctor['city_id'] ?? 0)); ?>"><span class="description"><?php esc_html_e('در نسخه‌های بعد می‌تواند به جدول شهرها متصل شود.', 'webtanan-booking'); ?></span></p>
                <p><label><?php esc_html_e('نام مطب', 'webtanan-booking'); ?></label><input type="text" class="widefat" name="doctor[clinic_name]" value="<?php echo esc_attr($doctor['clinic_name'] ?? ''); ?>"></p>
                <p><label><?php esc_html_e('تلفن مطب', 'webtanan-booking'); ?></label><input type="text" class="widefat" name="doctor[clinic_phone]" value="<?php echo esc_attr($doctor['clinic_phone'] ?? ''); ?>"></p>
                <p><label><?php esc_html_e('آدرس مطب', 'webtanan-booking'); ?></label><textarea class="widefat" rows="3" name="doctor[clinic_address]"><?php echo esc_textarea($doctor['clinic_address'] ?? ''); ?></textarea></p>
                <p><label><?php esc_html_e('هزینه خدمات نوبت‌دهی', 'webtanan-booking'); ?></label><input type="number" min="0" step="1000" class="widefat" name="doctor[booking_fee]" value="<?php echo esc_attr((string) ($doctor['booking_fee'] ?? 0)); ?>"><span class="description"><?php esc_html_e('این مبلغ از بیمار در سایت دریافت می‌شود.', 'webtanan-booking'); ?></span></p>
                <p><label><?php esc_html_e('نوع سهم پزشک/منشی از خدمات نوبت‌دهی', 'webtanan-booking'); ?></label><select class="widefat" name="doctor[booking_fee_share_type]"><option value="percent" <?php selected($doctor['booking_fee_share_type'] ?? 'percent', 'percent'); ?>><?php esc_html_e('درصدی', 'webtanan-booking'); ?></option><option value="fixed" <?php selected($doctor['booking_fee_share_type'] ?? '', 'fixed'); ?>><?php esc_html_e('مبلغ ثابت', 'webtanan-booking'); ?></option></select></p>
                <p><label><?php esc_html_e('مقدار سهم پزشک/منشی', 'webtanan-booking'); ?></label><input type="number" min="0" step="1000" class="widefat" name="doctor[booking_fee_share_value]" value="<?php echo esc_attr((string) ($doctor['booking_fee_share_value'] ?? 0)); ?>"><span class="description"><?php esc_html_e('باقی‌مانده هزینه نوبت‌دهی به عنوان درآمد پلتفرم ثبت می‌شود.', 'webtanan-booking'); ?></span></p>
                <p><label><?php esc_html_e('تعرفه ویزیت نمایشی (اختیاری)', 'webtanan-booking'); ?></label><input type="number" min="0" step="1000" class="widefat" name="doctor[visit_price]" value="<?php echo esc_attr((string) ($doctor['visit_price'] ?? 0)); ?>"><span class="description"><?php esc_html_e('این مبلغ فقط در پروفایل نمایش داده می‌شود و توسط سایت دریافت نمی‌شود.', 'webtanan-booking'); ?></span></p>
                <p><label><?php esc_html_e('نوع کارمزد پلتفرم', 'webtanan-booking'); ?></label><select class="widefat" name="doctor[platform_commission_type]"><option value="percent" <?php selected($doctor['platform_commission_type'] ?? $settings['default_commission_type'], 'percent'); ?>><?php esc_html_e('درصدی', 'webtanan-booking'); ?></option><option value="fixed" <?php selected($doctor['platform_commission_type'] ?? $settings['default_commission_type'], 'fixed'); ?>><?php esc_html_e('مبلغ ثابت', 'webtanan-booking'); ?></option></select></p>
                <p><label><?php esc_html_e('مقدار کارمزد پلتفرم', 'webtanan-booking'); ?></label><input type="number" min="0" step="1000" class="widefat" name="doctor[platform_commission_value]" value="<?php echo esc_attr((string) ($doctor['platform_commission_value'] ?? $settings['default_commission_value'])); ?>"><span class="description"><?php esc_html_e('برای گزارش مالی و بدهی پرداخت حضوری استفاده می‌شود. در حالت درصدی، مقدار را به درصد وارد کنید.', 'webtanan-booking'); ?></span></p>
                <p><label><?php esc_html_e('شبا', 'webtanan-booking'); ?></label><input type="text" class="widefat" dir="ltr" name="doctor[iban]" value="<?php echo esc_attr($doctor['iban'] ?? ''); ?>"></p>
                <p><label><?php esc_html_e('صاحب حساب', 'webtanan-booking'); ?></label><input type="text" class="widefat" name="doctor[bank_account_owner]" value="<?php echo esc_attr($doctor['bank_account_owner'] ?? ''); ?>"></p>
                <p><label><?php esc_html_e('خلاصه پروفایل', 'webtanan-booking'); ?></label><textarea class="widefat" rows="2" name="doctor[profile_excerpt]"><?php echo esc_textarea($post ? $post->post_excerpt : ''); ?></textarea></p>
                <p><label><?php esc_html_e('متن پروفایل', 'webtanan-booking'); ?></label><textarea class="widefat" rows="4" name="doctor[profile_content]"><?php echo esc_textarea($post ? $post->post_content : ''); ?></textarea></p>
                <div class="webtanan-admin-media-field">
                    <label><?php esc_html_e('تصویر پزشک', 'webtanan-booking'); ?></label>
                    <input type="hidden" name="doctor[featured_image_id]" id="webtanan_doctor_featured_image_id" value="<?php echo esc_attr((string) $featured_image_id); ?>">
                    <div class="webtanan-media-preview" id="webtanan_doctor_featured_preview">
                        <?php if ($featured_image_url) : ?><img src="<?php echo esc_url($featured_image_url); ?>" alt=""><?php endif; ?>
                    </div>
                    <button type="button" class="button webtanan-media-upload" data-target="#webtanan_doctor_featured_image_id" data-preview="#webtanan_doctor_featured_preview"><?php esc_html_e('انتخاب تصویر', 'webtanan-booking'); ?></button>
                    <button type="button" class="button webtanan-media-clear" data-target="#webtanan_doctor_featured_image_id" data-preview="#webtanan_doctor_featured_preview"><?php esc_html_e('حذف تصویر', 'webtanan-booking'); ?></button>
                </div>
                <div class="webtanan-admin-media-field">
                    <label><?php esc_html_e('گالری مطب', 'webtanan-booking'); ?></label>
                    <input type="hidden" name="doctor[gallery_image_ids]" id="webtanan_doctor_gallery_image_ids" value="<?php echo esc_attr(implode(',', $gallery_ids)); ?>">
                    <div class="webtanan-gallery-preview" id="webtanan_doctor_gallery_preview">
                        <?php foreach ($gallery_ids as $gallery_id) : $gallery_url = wp_get_attachment_image_url((int) $gallery_id, 'thumbnail'); ?>
                            <?php if ($gallery_url) : ?><img src="<?php echo esc_url($gallery_url); ?>" alt="" data-id="<?php echo esc_attr((string) $gallery_id); ?>"><?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="button webtanan-gallery-upload" data-target="#webtanan_doctor_gallery_image_ids" data-preview="#webtanan_doctor_gallery_preview"><?php esc_html_e('انتخاب تصاویر گالری', 'webtanan-booking'); ?></button>
                    <button type="button" class="button webtanan-media-clear" data-target="#webtanan_doctor_gallery_image_ids" data-preview="#webtanan_doctor_gallery_preview"><?php esc_html_e('حذف گالری', 'webtanan-booking'); ?></button>
                </div>
                <p><label><input type="checkbox" name="doctor[is_active]" value="1" <?php checked((int) ($doctor['is_active'] ?? 1), 1); ?>> <?php esc_html_e('فعال', 'webtanan-booking'); ?></label></p>
                <p><label><input type="checkbox" name="doctor[is_verified]" value="1" <?php checked((int) ($doctor['is_verified'] ?? 0), 1); ?>> <?php esc_html_e('تایید شده', 'webtanan-booking'); ?></label></p>
                <p><label><input type="checkbox" name="doctor[allow_online_payment]" value="1" <?php checked((int) ($doctor['allow_online_payment'] ?? 1), 1); ?>> <?php esc_html_e('پرداخت آنلاین', 'webtanan-booking'); ?></label></p>
                <p><label><input type="checkbox" name="doctor[allow_pay_at_clinic]" value="1" <?php checked((int) ($doctor['allow_pay_at_clinic'] ?? 0), 1); ?>> <?php esc_html_e('پرداخت در مطب', 'webtanan-booking'); ?></label></p>
                <?php submit_button($doctor ? __('ذخیره پزشک', 'webtanan-booking') : __('افزودن پزشک', 'webtanan-booking')); ?>
            </form>
        </div>
        <?php
    }

    private static function render_specialty_form(?array $specialty): void {
        $specialty = $specialty ?: array();
        ?>
        <div class="webtanan-admin-panel">
            <h2><?php echo esc_html($specialty ? __('ویرایش تخصص', 'webtanan-booking') : __('افزودن تخصص', 'webtanan-booking')); ?></h2>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('webtanan_booking_save_specialty'); ?>
                <input type="hidden" name="action" value="webtanan_booking_save_specialty">
                <input type="hidden" name="specialty_id" value="<?php echo esc_attr((string) ($specialty['id'] ?? 0)); ?>">
                <p><label><?php esc_html_e('نام تخصص', 'webtanan-booking'); ?></label><input type="text" class="widefat" name="name" value="<?php echo esc_attr($specialty['name'] ?? ''); ?>" required></p>
                <p><label><?php esc_html_e('اسلاگ', 'webtanan-booking'); ?></label><input type="text" class="widefat" dir="ltr" name="slug" value="<?php echo esc_attr($specialty['slug'] ?? ''); ?>"></p>
                <p><label><?php esc_html_e('والد', 'webtanan-booking'); ?></label><?php self::specialty_select('parent_id', (int) ($specialty['parent_id'] ?? 0), __('بدون والد', 'webtanan-booking')); ?></p>
                <p><label><?php esc_html_e('ترتیب نمایش', 'webtanan-booking'); ?></label><input type="number" class="widefat" name="sort_order" value="<?php echo esc_attr((string) ($specialty['sort_order'] ?? 0)); ?>"></p>
                <p><label><input type="checkbox" name="is_active" value="1" <?php checked((int) ($specialty['is_active'] ?? 1), 1); ?>> <?php esc_html_e('فعال', 'webtanan-booking'); ?></label></p>
                <?php submit_button(__('ذخیره تخصص', 'webtanan-booking')); ?>
            </form>
        </div>
        <?php
    }

    private static function render_schedule_form(int $doctor_id, ?array $schedule): void {
        $schedule = $schedule ?: array();
        ?>
        <div class="webtanan-admin-panel">
            <h2><?php esc_html_e('ثبت شیفت هفتگی', 'webtanan-booking'); ?></h2>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('webtanan_booking_save_schedule'); ?>
                <input type="hidden" name="action" value="webtanan_booking_save_schedule">
                <input type="hidden" name="doctor_id" value="<?php echo esc_attr((string) $doctor_id); ?>">
                <input type="hidden" name="schedule_id" value="<?php echo esc_attr((string) ($schedule['id'] ?? 0)); ?>">
                <p><label><?php esc_html_e('روز هفته', 'webtanan-booking'); ?></label><?php self::weekday_select('weekday', $schedule['weekday'] ?? 'saturday'); ?></p>
                <p><label><?php esc_html_e('شروع', 'webtanan-booking'); ?></label><input type="time" class="widefat" name="start_time" value="<?php echo esc_attr(substr((string) ($schedule['start_time'] ?? '09:00'), 0, 5)); ?>"></p>
                <p><label><?php esc_html_e('پایان', 'webtanan-booking'); ?></label><input type="time" class="widefat" name="end_time" value="<?php echo esc_attr(substr((string) ($schedule['end_time'] ?? '13:00'), 0, 5)); ?>"></p>
                <p><label><?php esc_html_e('مدت هر نوبت', 'webtanan-booking'); ?></label><input type="number" min="1" class="widefat" name="slot_duration" value="<?php echo esc_attr((string) ($schedule['slot_duration'] ?? 15)); ?>"></p>
                <p><label><?php esc_html_e('ظرفیت هر اسلات', 'webtanan-booking'); ?></label><input type="number" min="1" class="widefat" name="capacity_per_slot" value="<?php echo esc_attr((string) ($schedule['capacity_per_slot'] ?? 1)); ?>"></p>
                <p><label><input type="checkbox" name="is_active" value="1" <?php checked((int) ($schedule['is_active'] ?? 1), 1); ?>> <?php esc_html_e('فعال', 'webtanan-booking'); ?></label></p>
                <?php submit_button(__('ذخیره شیفت', 'webtanan-booking')); ?>
            </form>
        </div>
        <?php
    }

    private static function render_exception_form(int $doctor_id, ?array $exception): void {
        $exception = $exception ?: array();
        ?>
        <div class="webtanan-admin-panel">
            <h2><?php esc_html_e('ثبت برنامه تاریخ خاص', 'webtanan-booking'); ?></h2>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('webtanan_booking_save_exception'); ?>
                <input type="hidden" name="action" value="webtanan_booking_save_exception">
                <input type="hidden" name="doctor_id" value="<?php echo esc_attr((string) $doctor_id); ?>">
                <input type="hidden" name="exception_id" value="<?php echo esc_attr((string) ($exception['id'] ?? 0)); ?>">
                <p><label><?php esc_html_e('تاریخ', 'webtanan-booking'); ?></label><input type="date" class="widefat" name="exception_date" value="<?php echo esc_attr($exception['exception_date'] ?? current_time('Y-m-d')); ?>"></p>
                <p><label><?php esc_html_e('نوع', 'webtanan-booking'); ?></label><select class="widefat" name="type"><option value="day_off" <?php selected($exception['type'] ?? 'day_off', 'day_off'); ?>><?php esc_html_e('تعطیلی کامل', 'webtanan-booking'); ?></option><option value="custom_shift" <?php selected($exception['type'] ?? '', 'custom_shift'); ?>><?php esc_html_e('شیفت جایگزین', 'webtanan-booking'); ?></option><option value="reduced_shift" <?php selected($exception['type'] ?? '', 'reduced_shift'); ?>><?php esc_html_e('شیفت کوتاه', 'webtanan-booking'); ?></option><option value="extra_shift" <?php selected($exception['type'] ?? '', 'extra_shift'); ?>><?php esc_html_e('شیفت اضافه', 'webtanan-booking'); ?></option></select></p>
                <p><label><?php esc_html_e('شروع', 'webtanan-booking'); ?></label><input type="time" class="widefat" name="start_time" value="<?php echo esc_attr(substr((string) ($exception['start_time'] ?? ''), 0, 5)); ?>"></p>
                <p><label><?php esc_html_e('پایان', 'webtanan-booking'); ?></label><input type="time" class="widefat" name="end_time" value="<?php echo esc_attr(substr((string) ($exception['end_time'] ?? ''), 0, 5)); ?>"></p>
                <p><label><?php esc_html_e('مدت نوبت', 'webtanan-booking'); ?></label><input type="number" min="1" class="widefat" name="slot_duration" value="<?php echo esc_attr((string) ($exception['slot_duration'] ?? 15)); ?>"></p>
                <p><label><?php esc_html_e('ظرفیت', 'webtanan-booking'); ?></label><input type="number" min="1" class="widefat" name="capacity_per_slot" value="<?php echo esc_attr((string) ($exception['capacity_per_slot'] ?? 1)); ?>"></p>
                <p><label><?php esc_html_e('دلیل', 'webtanan-booking'); ?></label><textarea class="widefat" rows="2" name="reason"><?php echo esc_textarea($exception['reason'] ?? ''); ?></textarea></p>
                <?php submit_button(__('ذخیره استثنا', 'webtanan-booking')); ?>
            </form>
        </div>
        <?php
    }

    private static function render_admin_appointment_form(): void {
        ?>
        <div class="webtanan-admin-panel">
            <h2><?php esc_html_e('ثبت نوبت حضوری', 'webtanan-booking'); ?></h2>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('webtanan_booking_create_admin_appointment'); ?>
                <input type="hidden" name="action" value="webtanan_booking_create_admin_appointment">
                <p><label><?php esc_html_e('پزشک', 'webtanan-booking'); ?></label><?php self::doctor_select('appointment[doctor_id]', 0, __('انتخاب پزشک', 'webtanan-booking')); ?></p>
                <p><label><?php esc_html_e('تاریخ', 'webtanan-booking'); ?></label><input type="date" class="widefat" name="appointment[appointment_date]" value="<?php echo esc_attr(current_time('Y-m-d')); ?>"></p>
                <p><label><?php esc_html_e('ساعت شروع', 'webtanan-booking'); ?></label><input type="time" class="widefat" name="appointment[start_time]" required></p>
                <p><label><?php esc_html_e('نام', 'webtanan-booking'); ?></label><input type="text" class="widefat" name="appointment[patient_first_name]" required></p>
                <p><label><?php esc_html_e('نام خانوادگی', 'webtanan-booking'); ?></label><input type="text" class="widefat" name="appointment[patient_last_name]" required></p>
                <p><label><?php esc_html_e('کد ملی', 'webtanan-booking'); ?></label><input type="text" class="widefat" name="appointment[patient_national_code]"></p>
                <p><label><?php esc_html_e('موبایل', 'webtanan-booking'); ?></label><input type="tel" class="widefat" name="appointment[patient_mobile]"></p>
                <p><label><?php esc_html_e('وضعیت پرداخت', 'webtanan-booking'); ?></label><select class="widefat" name="appointment[payment_status]"><option value="cash_at_clinic"><?php esc_html_e('نقدی در مطب', 'webtanan-booking'); ?></option><option value="pos_at_clinic"><?php esc_html_e('کارت‌خوان', 'webtanan-booking'); ?></option><option value="unpaid"><?php esc_html_e('پرداخت‌نشده', 'webtanan-booking'); ?></option></select></p>
                <?php submit_button(__('ثبت نوبت', 'webtanan-booking')); ?>
            </form>
        </div>
        <?php
    }

    private static function render_bulk_appointment_form(int $doctor_id = 0, string $date = ''): void {
        ?>
        <div class="webtanan-admin-panel webtanan-danger-panel">
            <h2><?php esc_html_e('لغو گروهی نوبت‌ها', 'webtanan-booking'); ?></h2>
            <p class="description"><?php esc_html_e('برای روزهایی که برنامه پزشک تغییر می‌کند، همه نوبت‌های فعال همان روز را لغو و مبلغ پرداخت‌شده را به کیف پول بیماران برگردانید.', 'webtanan-booking'); ?></p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('webtanan_booking_bulk_appointment_action'); ?>
                <input type="hidden" name="action" value="webtanan_booking_bulk_appointment_action">
                <input type="hidden" name="operation" value="cancel_day">
                <p><label><?php esc_html_e('پزشک', 'webtanan-booking'); ?></label><?php self::doctor_select('doctor_id', $doctor_id, __('انتخاب پزشک', 'webtanan-booking')); ?></p>
                <p><label><?php esc_html_e('تاریخ', 'webtanan-booking'); ?></label><input type="date" class="widefat" name="appointment_date" value="<?php echo esc_attr($date ?: current_time('Y-m-d')); ?>" required></p>
                <p><label><?php esc_html_e('دلیل لغو', 'webtanan-booking'); ?></label><textarea class="widefat" rows="3" name="reason" placeholder="<?php esc_attr_e('مثلاً تغییر برنامه پزشک در این روز', 'webtanan-booking'); ?>"></textarea></p>
                <?php submit_button(__('لغو همه نوبت‌های فعال این روز', 'webtanan-booking'), 'delete'); ?>
            </form>
        </div>
        <?php
    }

    private static function render_appointment_actions(array $row): void {
        ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="webtanan-row-actions">
            <?php wp_nonce_field('webtanan_booking_update_admin_appointment'); ?>
            <input type="hidden" name="action" value="webtanan_booking_update_admin_appointment">
            <input type="hidden" name="appointment_id" value="<?php echo esc_attr((string) $row['id']); ?>">
            <select name="operation"><option value="payment"><?php esc_html_e('پرداخت', 'webtanan-booking'); ?></option><option value="attendance"><?php esc_html_e('حضور', 'webtanan-booking'); ?></option><option value="cancel"><?php esc_html_e('لغو', 'webtanan-booking'); ?></option></select>
            <select name="payment_status"><option value="cash_at_clinic"><?php esc_html_e('نقدی', 'webtanan-booking'); ?></option><option value="pos_at_clinic"><?php esc_html_e('کارت‌خوان', 'webtanan-booking'); ?></option><option value="unpaid"><?php esc_html_e('پرداخت‌نشده', 'webtanan-booking'); ?></option></select>
            <select name="appointment_status"><option value="confirmed"><?php esc_html_e('تاییدشده', 'webtanan-booking'); ?></option><option value="completed"><?php esc_html_e('مراجعه کرد', 'webtanan-booking'); ?></option><option value="no_show"><?php esc_html_e('مراجعه نکرد', 'webtanan-booking'); ?></option></select>
            <input type="text" name="reason" placeholder="<?php esc_attr_e('دلیل لغو', 'webtanan-booking'); ?>">
            <?php submit_button(__('اعمال', 'webtanan-booking'), 'secondary small', '', false); ?>
        </form>
        <?php
    }

    private static function render_wallet_adjustment_form(): void {
        ?>
        <div class="webtanan-admin-panel">
            <h2><?php esc_html_e('اصلاح دستی کیف پول', 'webtanan-booking'); ?></h2>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('webtanan_booking_wallet_adjustment'); ?>
                <input type="hidden" name="action" value="webtanan_booking_wallet_adjustment">
                <p><label><?php esc_html_e('شناسه کاربر', 'webtanan-booking'); ?></label><input type="number" class="widefat" name="user_id" min="0" required></p>
                <p><label><?php esc_html_e('نوع کاربر', 'webtanan-booking'); ?></label><?php self::select_from_map('user_type', self::user_types(), 'patient'); ?></p>
                <p><label><?php esc_html_e('نوع رکورد', 'webtanan-booking'); ?></label><select class="widefat" name="entry_type"><option value="credit"><?php esc_html_e('افزایش اعتبار', 'webtanan-booking'); ?></option><option value="debit"><?php esc_html_e('کاهش اعتبار', 'webtanan-booking'); ?></option><option value="manual_adjustment"><?php esc_html_e('اصلاح دستی مثبت', 'webtanan-booking'); ?></option></select></p>
                <p><label><?php esc_html_e('مبلغ', 'webtanan-booking'); ?></label><input type="number" class="widefat" min="1" step="1000" name="amount" required></p>
                <p><label><?php esc_html_e('توضیح', 'webtanan-booking'); ?></label><textarea class="widefat" rows="2" name="description"></textarea></p>
                <?php submit_button(__('ثبت اصلاح', 'webtanan-booking')); ?>
            </form>
        </div>
        <?php
    }

    private static function render_settlement_form(): void {
        ?>
        <div class="webtanan-admin-panel">
            <h2><?php esc_html_e('ثبت درخواست تسویه دستی', 'webtanan-booking'); ?></h2>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('webtanan_booking_save_settlement'); ?>
                <input type="hidden" name="action" value="webtanan_booking_save_settlement">
                <p><label><?php esc_html_e('پزشک', 'webtanan-booking'); ?></label><?php self::doctor_select('doctor_id', 0, __('انتخاب پزشک', 'webtanan-booking')); ?></p>
                <p><label><?php esc_html_e('مبلغ', 'webtanan-booking'); ?></label><input type="number" min="1" step="1000" class="widefat" name="amount" required></p>
                <p><label><?php esc_html_e('شبا', 'webtanan-booking'); ?></label><input type="text" class="widefat" dir="ltr" name="iban"></p>
                <p><label><?php esc_html_e('یادداشت مدیر', 'webtanan-booking'); ?></label><textarea class="widefat" rows="2" name="admin_note"></textarea></p>
                <?php submit_button(__('ثبت درخواست', 'webtanan-booking')); ?>
            </form>
        </div>
        <?php
    }

    private static function render_survey_actions(array $row): void {
        ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="webtanan-row-actions webtanan-survey-actions">
            <?php wp_nonce_field('webtanan_booking_update_survey'); ?>
            <input type="hidden" name="action" value="webtanan_booking_update_survey">
            <input type="hidden" name="survey_id" value="<?php echo esc_attr((string) $row['id']); ?>">
            <?php self::select_from_map('status', self::survey_statuses(), (string) $row['status']); ?>
            <?php submit_button(__('ذخیره', 'webtanan-booking'), 'secondary small', '', false); ?>
        </form>
        <?php
    }

    private static function render_settlement_actions(array $row): void {
        ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="webtanan-row-actions webtanan-settlement-actions" data-current-status="<?php echo esc_attr((string) $row['status']); ?>">
            <?php wp_nonce_field('webtanan_booking_update_settlement'); ?>
            <input type="hidden" name="action" value="webtanan_booking_update_settlement">
            <input type="hidden" name="settlement_id" value="<?php echo esc_attr((string) $row['id']); ?>">
            <?php self::select_from_map('status', self::settlement_statuses(), $row['status']); ?>
            <input type="text" name="bank_tracking_number" value="<?php echo esc_attr($row['bank_tracking_number'] ?? ''); ?>" placeholder="<?php esc_attr_e('شماره پیگیری بانکی', 'webtanan-booking'); ?>">
            <input type="text" name="admin_note" value="<?php echo esc_attr($row['admin_note']); ?>" placeholder="<?php esc_attr_e('یادداشت', 'webtanan-booking'); ?>">
            <?php submit_button(__('ذخیره', 'webtanan-booking'), 'secondary small', '', false); ?>
            <span class="description"><?php esc_html_e('ثبت paid فقط با شماره پیگیری معتبر انجام می‌شود و ledger تسویه فقط یک بار ساخته می‌شود.', 'webtanan-booking'); ?></span>
        </form>
        <?php
    }

    private static function render_font_settings_fields(array $settings): void {
        $font_attachment_id = absint($settings['ui_font_attachment_id'] ?? 0);
        $font_url = esc_url_raw((string) ($settings['ui_font_url'] ?? ''));
        if ('' === $font_url && $font_attachment_id > 0) {
            $font_url = esc_url_raw((string) wp_get_attachment_url($font_attachment_id));
        }
        ?>
        <tr>
            <th scope="row"><?php esc_html_e('فونت رابط افزونه', 'webtanan-booking'); ?></th>
            <td>
                <input type="text" class="regular-text" name="<?php echo esc_attr(DB::OPTION_SETTINGS); ?>[ui_font_family]" value="<?php echo esc_attr((string) ($settings['ui_font_family'] ?? '')); ?>" placeholder="WebtananFont">
                <input type="hidden" id="webtanan-ui-font-attachment-id" name="<?php echo esc_attr(DB::OPTION_SETTINGS); ?>[ui_font_attachment_id]" value="<?php echo esc_attr((string) $font_attachment_id); ?>">
                <input type="url" id="webtanan-ui-font-url" class="large-text" dir="ltr" name="<?php echo esc_attr(DB::OPTION_SETTINGS); ?>[ui_font_url]" value="<?php echo esc_attr($font_url); ?>" placeholder="https://example.com/font.woff2">
                <p>
                    <button type="button" class="button webtanan-font-upload" data-target="#webtanan-ui-font-attachment-id" data-url-target="#webtanan-ui-font-url"><?php esc_html_e('آپلود/انتخاب فونت', 'webtanan-booking'); ?></button>
                </p>
                <p class="description"><?php esc_html_e('فایل‌های مجاز: woff, woff2, ttf. اگر خالی باشد فونت قالب سایت استفاده می‌شود.', 'webtanan-booking'); ?></p>
            </td>
        </tr>
        <?php
    }

    private static function render_cancellation_settings_fields(array $settings): void {
        $policy = $settings['cancellation_policy'] ?? array();
        ?>
        <h2><?php esc_html_e('قوانین لغو و استرداد', 'webtanan-booking'); ?></h2>
        <table class="form-table" role="presentation">
            <tr><th scope="row"><?php esc_html_e('آخرین مهلت لغو توسط بیمار', 'webtanan-booking'); ?></th><td><input type="number" min="0" name="<?php echo esc_attr(DB::OPTION_SETTINGS); ?>[cancellation_policy][patient_cancel_until_hours]" value="<?php echo esc_attr((string) ($policy['patient_cancel_until_hours'] ?? 0)); ?>"> <span><?php esc_html_e('ساعت قبل از نوبت؛ مقدار صفر یعنی بیمار هر زمان می‌تواند لغو کند.', 'webtanan-booking'); ?></span></td></tr>
            <tr><th scope="row"><?php esc_html_e('استرداد کامل بیمار تا', 'webtanan-booking'); ?></th><td><input type="number" min="0" name="<?php echo esc_attr(DB::OPTION_SETTINGS); ?>[cancellation_policy][full_refund_hours]" value="<?php echo esc_attr((string) ($policy['full_refund_hours'] ?? 24)); ?>"> <span><?php esc_html_e('ساعت قبل از نوبت', 'webtanan-booking'); ?></span></td></tr>
            <tr><th scope="row"><?php esc_html_e('درصد استرداد کامل', 'webtanan-booking'); ?></th><td><input type="number" min="0" max="100" step="0.01" name="<?php echo esc_attr(DB::OPTION_SETTINGS); ?>[cancellation_policy][full_refund_percent]" value="<?php echo esc_attr((string) ($policy['full_refund_percent'] ?? 100)); ?>">%</td></tr>
            <tr><th scope="row"><?php esc_html_e('استرداد درصدی بیمار تا', 'webtanan-booking'); ?></th><td><input type="number" min="0" name="<?php echo esc_attr(DB::OPTION_SETTINGS); ?>[cancellation_policy][partial_refund_hours]" value="<?php echo esc_attr((string) ($policy['partial_refund_hours'] ?? 6)); ?>"> <span><?php esc_html_e('ساعت قبل از نوبت', 'webtanan-booking'); ?></span></td></tr>
            <tr><th scope="row"><?php esc_html_e('درصد استرداد میانی', 'webtanan-booking'); ?></th><td><input type="number" min="0" max="100" step="0.01" name="<?php echo esc_attr(DB::OPTION_SETTINGS); ?>[cancellation_policy][partial_refund_percent]" value="<?php echo esc_attr((string) ($policy['partial_refund_percent'] ?? 50)); ?>">%</td></tr>
            <tr><th scope="row"><?php esc_html_e('درصد استرداد دیرهنگام', 'webtanan-booking'); ?></th><td><input type="number" min="0" max="100" step="0.01" name="<?php echo esc_attr(DB::OPTION_SETTINGS); ?>[cancellation_policy][late_refund_percent]" value="<?php echo esc_attr((string) ($policy['late_refund_percent'] ?? 0)); ?>">%</td></tr>
            <tr><th scope="row"><?php esc_html_e('لغو توسط مطب یا مدیر', 'webtanan-booking'); ?></th><td><label><input type="checkbox" name="<?php echo esc_attr(DB::OPTION_SETTINGS); ?>[cancellation_policy][doctor_admin_full_refund]" value="1" <?php checked(!empty($policy['doctor_admin_full_refund'])); ?>> <?php esc_html_e('با استرداد کامل به کیف پول بیمار انجام شود', 'webtanan-booking'); ?></label></td></tr>
        </table>
        <?php
    }

    private static function render_gateway_settings_fields(array $settings): void {
        $gateway = $settings['gateway_settings'];
        $aqaye = $gateway['aqayepardakht'] ?? array();
        $callback_url = rest_url('saas/v1/payment/aqayepardakht/callback');
        ?>
        <h2><?php esc_html_e('تنظیمات درگاه پرداخت', 'webtanan-booking'); ?></h2>
        <table class="form-table" role="presentation">
            <tr><th scope="row"><?php esc_html_e('درگاه فعال', 'webtanan-booking'); ?></th><td><select name="<?php echo esc_attr(DB::OPTION_SETTINGS); ?>[gateway_settings][active_gateway]"><option value="aqayepardakht" <?php selected($gateway['active_gateway'] ?? '', 'aqayepardakht'); ?>><?php esc_html_e('آقای پرداخت', 'webtanan-booking'); ?></option></select></td></tr>
            <tr><th scope="row"><?php esc_html_e('فعال‌سازی آقای پرداخت', 'webtanan-booking'); ?></th><td><label><input type="checkbox" name="<?php echo esc_attr(DB::OPTION_SETTINGS); ?>[gateway_settings][aqayepardakht][enabled]" value="1" <?php checked(!empty($aqaye['enabled'])); ?>> <?php esc_html_e('پرداخت آنلاین با آقای پرداخت فعال باشد', 'webtanan-booking'); ?></label></td></tr>
            <tr><th scope="row"><?php esc_html_e('حالت سندباکس', 'webtanan-booking'); ?></th><td><label><input type="checkbox" name="<?php echo esc_attr(DB::OPTION_SETTINGS); ?>[gateway_settings][aqayepardakht][sandbox]" value="1" <?php checked(!empty($aqaye['sandbox'])); ?>> <?php esc_html_e('مسیر سندباکس استفاده شود', 'webtanan-booking'); ?></label></td></tr>
            <tr><th scope="row"><?php esc_html_e('پین درگاه', 'webtanan-booking'); ?></th><td><input type="password" class="regular-text" dir="ltr" name="<?php echo esc_attr(DB::OPTION_SETTINGS); ?>[gateway_settings][aqayepardakht][pin]" value="<?php echo esc_attr($aqaye['pin'] ?? ''); ?>" placeholder="sandbox"></td></tr>
            <tr><th scope="row"><?php esc_html_e('آدرس بازگشت پرداخت', 'webtanan-booking'); ?></th><td><input type="text" class="large-text" dir="ltr" readonly value="<?php echo esc_attr($callback_url); ?>"></td></tr>
            <tr><th scope="row"><?php esc_html_e('روش بازگشت پرداخت', 'webtanan-booking'); ?></th><td><select name="<?php echo esc_attr(DB::OPTION_SETTINGS); ?>[gateway_settings][aqayepardakht][callback_method]"><option value="GET" <?php selected($aqaye['callback_method'] ?? 'GET', 'GET'); ?>>GET</option><option value="POST" <?php selected($aqaye['callback_method'] ?? 'GET', 'POST'); ?>>POST</option></select></td></tr>
            <tr><th scope="row"><?php esc_html_e('محدوده مبلغ', 'webtanan-booking'); ?></th><td><input type="number" min="0" name="<?php echo esc_attr(DB::OPTION_SETTINGS); ?>[gateway_settings][aqayepardakht][min_amount]" value="<?php echo esc_attr((string) ($aqaye['min_amount'] ?? 1000)); ?>"> <input type="number" min="1000" name="<?php echo esc_attr(DB::OPTION_SETTINGS); ?>[gateway_settings][aqayepardakht][max_amount]" value="<?php echo esc_attr((string) ($aqaye['max_amount'] ?? 400000000)); ?>"></td></tr>
            <tr><th scope="row"><?php esc_html_e('قالب توضیحات', 'webtanan-booking'); ?></th><td><input type="text" class="large-text" name="<?php echo esc_attr(DB::OPTION_SETTINGS); ?>[gateway_settings][aqayepardakht][description_template]" value="<?php echo esc_attr($aqaye['description_template'] ?? 'Appointment payment {appointment_code}'); ?>"><p class="description"><code>{appointment_code}</code> <code>{transaction_code}</code> <code>{doctor_name}</code> <code>{patient_name}</code> <code>{amount}</code></p></td></tr>
            <tr><th scope="row"><?php esc_html_e('API URLها', 'webtanan-booking'); ?></th><td><input type="url" class="large-text" dir="ltr" name="<?php echo esc_attr(DB::OPTION_SETTINGS); ?>[gateway_settings][aqayepardakht][create_url]" value="<?php echo esc_attr($aqaye['create_url'] ?? 'https://panel.aqayepardakht.ir/api/v2/create'); ?>"><br><input type="url" class="large-text" dir="ltr" name="<?php echo esc_attr(DB::OPTION_SETTINGS); ?>[gateway_settings][aqayepardakht][verify_url]" value="<?php echo esc_attr($aqaye['verify_url'] ?? 'https://panel.aqayepardakht.ir/api/v2/verify'); ?>"><br><input type="url" class="large-text" dir="ltr" name="<?php echo esc_attr(DB::OPTION_SETTINGS); ?>[gateway_settings][aqayepardakht][startpay_url]" value="<?php echo esc_attr($aqaye['startpay_url'] ?? 'https://panel.aqayepardakht.ir/startpay'); ?>"></td></tr>
        </table>
        <?php
    }

    private static function render_sms_settings_fields(array $settings): void {
        $sms = $settings['sms_provider_settings'];
        ?>
        <h2><?php esc_html_e('تنظیمات پیامک IPPanel', 'webtanan-booking'); ?></h2>
        <table class="form-table" role="presentation">
            <tr><th scope="row"><?php esc_html_e('فعال‌سازی پیامک', 'webtanan-booking'); ?></th><td><label><input type="checkbox" name="<?php echo esc_attr(DB::OPTION_SETTINGS); ?>[sms_provider_settings][enabled]" value="1" <?php checked(!empty($sms['enabled'])); ?>> <?php esc_html_e('ارسال پیامک از طریق IPPanel', 'webtanan-booking'); ?></label></td></tr>
            <tr><th scope="row"><?php esc_html_e('آدرس پایه API', 'webtanan-booking'); ?></th><td><input type="url" class="regular-text" dir="ltr" name="<?php echo esc_attr(DB::OPTION_SETTINGS); ?>[sms_provider_settings][base_url]" value="<?php echo esc_attr($sms['base_url'] ?? 'https://edge.ippanel.com/v1'); ?>"></td></tr>
            <tr><th scope="row"><?php esc_html_e('کلید API', 'webtanan-booking'); ?></th><td><input type="password" class="regular-text" dir="ltr" name="<?php echo esc_attr(DB::OPTION_SETTINGS); ?>[sms_provider_settings][api_key]" value="<?php echo esc_attr($sms['api_key'] ?? ''); ?>"></td></tr>
            <tr><th scope="row"><?php esc_html_e('شماره فرستنده', 'webtanan-booking'); ?></th><td><input type="text" class="regular-text" dir="ltr" name="<?php echo esc_attr(DB::OPTION_SETTINGS); ?>[sms_provider_settings][from_number]" value="<?php echo esc_attr($sms['from_number'] ?: ($sms['originator'] ?? '')); ?>" placeholder="+983000505"></td></tr>
            <tr><th scope="row"><?php esc_html_e('رفتار پیامک', 'webtanan-booking'); ?></th><td><label><input type="checkbox" name="<?php echo esc_attr(DB::OPTION_SETTINGS); ?>[sms_provider_settings][test_mode]" value="1" <?php checked(!empty($sms['test_mode'])); ?>> <?php esc_html_e('حالت تست', 'webtanan-booking'); ?></label><br><label><input type="checkbox" name="<?php echo esc_attr(DB::OPTION_SETTINGS); ?>[sms_provider_settings][log_enabled]" value="1" <?php checked(!empty($sms['log_enabled'])); ?>> <?php esc_html_e('ذخیره لاگ', 'webtanan-booking'); ?></label><br><label><input type="checkbox" name="<?php echo esc_attr(DB::OPTION_SETTINGS); ?>[sms_provider_settings][send_to_patient]" value="1" <?php checked(!empty($sms['send_to_patient'])); ?>> <?php esc_html_e('ارسال به بیمار', 'webtanan-booking'); ?></label><br><label><input type="checkbox" name="<?php echo esc_attr(DB::OPTION_SETTINGS); ?>[sms_provider_settings][send_to_doctor]" value="1" <?php checked(!empty($sms['send_to_doctor'])); ?>> <?php esc_html_e('ارسال به پزشک', 'webtanan-booking'); ?></label><br><label><input type="checkbox" name="<?php echo esc_attr(DB::OPTION_SETTINGS); ?>[sms_provider_settings][send_to_secretary]" value="1" <?php checked(!empty($sms['send_to_secretary'])); ?>> <?php esc_html_e('ارسال به منشی', 'webtanan-booking'); ?></label><br><label><input type="checkbox" name="<?php echo esc_attr(DB::OPTION_SETTINGS); ?>[sms_provider_settings][reminder_enabled]" value="1" <?php checked(!empty($sms['reminder_enabled'])); ?>> <?php esc_html_e('یادآوری نوبت', 'webtanan-booking'); ?></label></td></tr>
        </table>
        <h2><?php esc_html_e('کد پترن‌ها', 'webtanan-booking'); ?></h2>
        <div class="webtanan-admin-help-card">
            <strong><?php esc_html_e('متغیرهای قابل استفاده در پترن‌ها', 'webtanan-booking'); ?></strong>
            <p>
                <?php foreach (self::sms_pattern_variables() as $variable) : ?>
                    <code>{<?php echo esc_html($variable); ?>}</code>
                <?php endforeach; ?>
            </p>
        </div>
        <table class="widefat striped webtanan-pattern-table"><thead><tr><th><?php esc_html_e('نوع پیامک', 'webtanan-booking'); ?></th><th><?php esc_html_e('فعال', 'webtanan-booking'); ?></th><th><?php esc_html_e('کد پترن', 'webtanan-booking'); ?></th><th><?php esc_html_e('نمونه کد', 'webtanan-booking'); ?></th><th><?php esc_html_e('نمونه متن', 'webtanan-booking'); ?></th></tr></thead><tbody>
            <?php foreach (SMS::message_types() as $type => $label) : $pattern = self::sms_pattern_for_settings($sms, $type); $example = self::sms_pattern_examples($type); ?>
                <tr>
                    <td><code><?php echo esc_html($type); ?></code><br><?php echo esc_html($label); ?></td>
                    <td><input type="checkbox" name="<?php echo esc_attr(DB::OPTION_SETTINGS); ?>[sms_provider_settings][patterns][<?php echo esc_attr($type); ?>][enabled]" value="1" <?php checked($pattern['enabled']); ?>></td>
                    <td><input type="text" class="regular-text" dir="ltr" name="<?php echo esc_attr(DB::OPTION_SETTINGS); ?>[sms_provider_settings][patterns][<?php echo esc_attr($type); ?>][code]" value="<?php echo esc_attr($pattern['code']); ?>"></td>
                    <td><code><?php echo esc_html($example['code']); ?></code></td>
                    <td><?php echo esc_html($example['text']); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody></table>
        <?php
    }

    private static function render_sms_test_forms(): void {
        ?>
        <hr>
        <h2><?php esc_html_e('ابزار تست پیامک', 'webtanan-booking'); ?></h2>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="webtanan-filter-bar">
            <?php wp_nonce_field('webtanan_booking_test_sms'); ?>
            <input type="hidden" name="action" value="webtanan_booking_test_sms">
            <input type="text" name="mobile" dir="ltr" placeholder="+989121234567" required>
            <select name="message_type"><?php foreach (SMS::message_types() as $type => $label) : ?><option value="<?php echo esc_attr($type); ?>"><?php echo esc_html($label); ?></option><?php endforeach; ?></select>
            <?php submit_button(__('ارسال پیامک تست', 'webtanan-booking'), 'secondary', 'submit', false); ?>
        </form>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php wp_nonce_field('webtanan_booking_check_ippanel_patterns'); ?>
            <input type="hidden" name="action" value="webtanan_booking_check_ippanel_patterns">
            <?php submit_button(__('بررسی اتصال IPPanel', 'webtanan-booking'), 'secondary', 'submit', false); ?>
        </form>
        <h2><?php esc_html_e('ارسال پیامک معمولی', 'webtanan-booking'); ?></h2>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="webtanan-admin-sms-form">
            <?php wp_nonce_field('webtanan_booking_send_normal_sms'); ?>
            <input type="hidden" name="action" value="webtanan_booking_send_normal_sms">
            <p>
                <label><?php esc_html_e('شماره‌ها', 'webtanan-booking'); ?></label>
                <textarea name="mobiles" rows="3" dir="ltr" class="large-text" placeholder="+989121234567&#10;+989351234567" required></textarea>
                <span class="description"><?php esc_html_e('هر شماره را در یک خط بنویسید یا با ویرگول جدا کنید.', 'webtanan-booking'); ?></span>
            </p>
            <p>
                <label><?php esc_html_e('متن پیامک', 'webtanan-booking'); ?></label>
                <textarea name="message" rows="4" class="large-text" maxlength="900" required></textarea>
            </p>
            <?php submit_button(__('ارسال پیامک معمولی', 'webtanan-booking'), 'primary', 'submit', false); ?>
        </form>
        <?php
    }

    private static function upsert_doctor(int $post_id, array $raw): int {
        global $wpdb;

        $now = DB::now();
        $doctor_id = absint($raw['id'] ?? 0);
        $data = array(
            'post_id' => $post_id,
            'user_id' => absint($raw['user_id'] ?? 0),
            'secretary_user_id' => absint($raw['secretary_user_id'] ?? 0),
            'doctor_code' => sanitize_text_field($raw['doctor_code'] ?? '') ?: DB::code('DOC'),
            'medical_system_number' => sanitize_text_field($raw['medical_system_number'] ?? ''),
            'specialty_id' => absint($raw['specialty_id'] ?? 0),
            'city_id' => absint($raw['city_id'] ?? 0),
            'province_id' => absint($raw['province_id'] ?? 0),
            'clinic_name' => sanitize_text_field($raw['clinic_name'] ?? ''),
            'clinic_address' => sanitize_textarea_field($raw['clinic_address'] ?? ''),
            'clinic_phone' => sanitize_text_field($raw['clinic_phone'] ?? ''),
            'visit_price' => (float) ($raw['visit_price'] ?? 0),
            'booking_fee' => (float) ($raw['booking_fee'] ?? 0),
            'booking_fee_share_type' => (isset($raw['booking_fee_share_type']) && 'fixed' === $raw['booking_fee_share_type']) ? 'fixed' : 'percent',
            'booking_fee_share_value' => (float) ($raw['booking_fee_share_value'] ?? 0),
            'platform_commission_type' => (isset($raw['platform_commission_type']) && 'fixed' === $raw['platform_commission_type']) ? 'fixed' : 'percent',
            'platform_commission_value' => (float) ($raw['platform_commission_value'] ?? 0),
            'iban' => sanitize_text_field($raw['iban'] ?? ''),
            'bank_account_owner' => sanitize_text_field($raw['bank_account_owner'] ?? ''),
            'is_active' => isset($raw['is_active']) ? 1 : 0,
            'is_verified' => isset($raw['is_verified']) ? 1 : 0,
            'allow_online_payment' => isset($raw['allow_online_payment']) ? 1 : 0,
            'allow_pay_at_clinic' => isset($raw['allow_pay_at_clinic']) ? 1 : 0,
            'updated_at' => $now,
        );

        if (!$doctor_id) {
            $doctor_id = (int) $wpdb->get_var($wpdb->prepare('SELECT id FROM ' . DB::table('doctors') . ' WHERE post_id = %d', $post_id));
        }
        if ($doctor_id) {
            $wpdb->update(DB::table('doctors'), $data, array('id' => $doctor_id));
            return $doctor_id;
        }

        $data['created_at'] = $now;
        $wpdb->insert(DB::table('doctors'), $data);

        return (int) $wpdb->insert_id;
    }

    private static function filtered_doctors(): array {
        global $wpdb;

        $where = '1=1';
        $params = array();
        $search = self::request_text('s');
        if ($search) {
            $where .= ' AND (p.post_title LIKE %s OR d.doctor_code LIKE %s OR d.medical_system_number LIKE %s OR d.clinic_name LIKE %s)';
            $like = '%' . $wpdb->esc_like($search) . '%';
            array_push($params, $like, $like, $like, $like);
        }
        $specialty_id = absint($_GET['specialty_id'] ?? 0);
        if ($specialty_id) {
            $where .= ' AND d.specialty_id = %d';
            $params[] = $specialty_id;
        }
        $status = self::request_text('status');
        if ('active' === $status || 'inactive' === $status) {
            $where .= ' AND d.is_active = %d';
            $params[] = 'active' === $status ? 1 : 0;
        } elseif ('verified' === $status || 'unverified' === $status) {
            $where .= ' AND d.is_verified = %d';
            $params[] = 'verified' === $status ? 1 : 0;
        }

        $sql = 'SELECT d.*, p.post_title, p.post_status, s.name AS specialty_name, u.display_name FROM ' . DB::table('doctors') . ' d LEFT JOIN ' . $wpdb->posts . ' p ON p.ID = d.post_id LEFT JOIN ' . DB::table('specialties') . ' s ON s.id = d.specialty_id LEFT JOIN ' . $wpdb->users . " u ON u.ID = d.user_id WHERE $where ORDER BY d.id DESC LIMIT 100";

        return $params ? $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A) : $wpdb->get_results($sql, ARRAY_A);
    }

    private static function doctor_row(int $doctor_id): ?array {
        global $wpdb;

        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . DB::table('doctors') . ' WHERE id = %d', $doctor_id), ARRAY_A);

        return is_array($row) ? $row : null;
    }

    private static function doctor_select(string $name, int $selected = 0, string $placeholder = ''): void {
        global $wpdb;

        $rows = $wpdb->get_results('SELECT d.id, d.clinic_name, p.post_title FROM ' . DB::table('doctors') . ' d LEFT JOIN ' . $wpdb->posts . ' p ON p.ID = d.post_id ORDER BY p.post_title ASC, d.id DESC LIMIT 300', ARRAY_A);
        echo '<select class="widefat" name="' . esc_attr($name) . '">';
        echo '<option value="0">' . esc_html($placeholder ?: __('همه پزشکان', 'webtanan-booking')) . '</option>';
        foreach ($rows as $row) {
            echo '<option value="' . esc_attr((string) $row['id']) . '" ' . selected($selected, (int) $row['id'], false) . '>' . esc_html($row['post_title'] ?: $row['clinic_name'] ?: '#' . $row['id']) . '</option>';
        }
        echo '</select>';
    }

    private static function specialty_options(): array {
        global $wpdb;

        return $wpdb->get_results('SELECT id, name FROM ' . DB::table('specialties') . ' ORDER BY sort_order ASC, name ASC LIMIT 300', ARRAY_A);
    }

    private static function specialty_select(string $name, int $selected = 0, string $placeholder = ''): void {
        echo '<select class="widefat" name="' . esc_attr($name) . '">';
        echo '<option value="0">' . esc_html($placeholder ?: __('انتخاب تخصص', 'webtanan-booking')) . '</option>';
        foreach (self::specialty_options() as $specialty) {
            echo '<option value="' . esc_attr((string) $specialty['id']) . '" ' . selected($selected, (int) $specialty['id'], false) . '>' . esc_html($specialty['name']) . '</option>';
        }
        echo '</select>';
    }

    private static function weekday_select(string $name, string $selected): void {
        self::select_from_map($name, self::weekdays(), $selected);
    }

    private static function select_from_map(string $name, array $items, string $selected = '', string $placeholder = ''): void {
        echo '<select class="widefat" name="' . esc_attr($name) . '">';
        if ($placeholder) {
            echo '<option value="">' . esc_html($placeholder) . '</option>';
        }
        foreach ($items as $value => $label) {
            echo '<option value="' . esc_attr((string) $value) . '" ' . selected($selected, (string) $value, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
    }

    private static function render_simple_table(array $rows, array $columns, string $empty): void {
        echo '<table class="widefat striped"><thead><tr>';
        foreach ($columns as $column) {
            echo '<th>' . esc_html($column) . '</th>';
        }
        echo '</tr></thead><tbody>';
        foreach ($rows as $row) {
            echo '<tr>';
            foreach ($columns as $column) {
                echo '<td>' . esc_html((string) ($row[$column] ?? '')) . '</td>';
            }
            echo '</tr>';
        }
        if (!$rows) {
            echo '<tr><td colspan="' . esc_attr((string) count($columns)) . '">' . esc_html($empty) . '</td></tr>';
        }
        echo '</tbody></table>';
    }

    private static function render_notice(): void {
        $notice = get_transient(self::NOTICE_TRANSIENT);
        if ($notice) {
            delete_transient(self::NOTICE_TRANSIENT);
        }
        if (!is_array($notice)) {
            return;
        }

        echo '<div class="notice notice-' . esc_attr($notice['type'] ?? 'info') . ' is-dismissible"><p>' . esc_html($notice['message'] ?? '') . '</p></div>';
        if (!empty($notice['details'])) {
            echo '<pre class="webtanan-admin-debug">' . esc_html(wp_json_encode($notice['details'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . '</pre>';
        }
    }

    private static function set_notice(string $type, string $message, $details = null): void {
        set_transient(self::NOTICE_TRANSIENT, array('type' => $type, 'message' => $message, 'details' => $details), 90);
    }

    private static function notice_from_result($result, string $success_message): void {
        if (is_wp_error($result)) {
            self::set_notice('error', $result->get_error_message(), $result->get_error_data());
            return;
        }

        self::set_notice('success', $success_message);
    }

    private static function redirect(string $page, array $args = array()): void {
        wp_safe_redirect(self::page_url($page, $args));
        exit;
    }

    private static function page_url(string $page, array $args = array()): string {
        return add_query_arg(array_merge(array('page' => $page), $args), admin_url('admin.php'));
    }

    private static function public_doctors_archive_url(array $args = array()): string {
        $url = get_post_type_archive_link('saas_doctors');
        if (!$url) {
            $url = add_query_arg('post_type', 'saas_doctors', home_url('/'));
        }

        return $args ? add_query_arg($args, $url) : $url;
    }

    private static function public_specialty_url(int $specialty_id): string {
        return self::public_doctors_archive_url(array('specialty_id' => $specialty_id));
    }

    private static function shortcode_rows(): array {
        return array(
            array(
                'title' => __('ورود بیمار با OTP', 'webtanan-booking'),
                'description' => __('فرم ورود/ثبت‌نام بیماران با شماره موبایل و کد پیامکی.', 'webtanan-booking'),
                'shortcode' => '[webtanan_booking_auth]',
                'page' => __('ورود / حساب بیمار', 'webtanan-booking'),
            ),
            array(
                'title' => __('آرشیو کامل پزشکان', 'webtanan-booking'),
                'description' => __('لیست تخصص‌ها، جستجوی پزشک و کارت پزشکان برای صفحه اصلی نوبت‌دهی.', 'webtanan-booking'),
                'shortcode' => '[webtanan_booking_doctors_archive per_page="12"]',
                'page' => __('نوبت‌دهی پزشکان', 'webtanan-booking'),
            ),
            array(
                'title' => __('جستجوی پزشکان', 'webtanan-booking'),
                'description' => __('جستجوی Ajax/REST بر اساس نام، تخصص، آدرس، شهر، استان، روش پرداخت و نزدیک‌ترین نوبت آزاد.', 'webtanan-booking'),
                'shortcode' => '[webtanan_booking_doctor_search per_page="12" specialty_id="0" province_id="0" city_id="0" payment_filter="" sort=""]',
                'page' => __('جستجوی پزشک', 'webtanan-booking'),
            ),
            array(
                'title' => __('لیست پزشکان', 'webtanan-booking'),
                'description' => __('نمایش کارت پزشکان؛ قابل محدود کردن بر اساس تخصص، شهر، استان و روش پرداخت.', 'webtanan-booking'),
                'shortcode' => '[webtanan_booking_doctor_list per_page="12" specialty_id="0" province_id="0" city_id="0" payment_filter="" sort=""]',
                'page' => __('لیست پزشکان', 'webtanan-booking'),
            ),
            array(
                'title' => __('کارت پزشک', 'webtanan-booking'),
                'description' => __('کارت یک پزشک با تصویر، تخصص، هزینه نوبت‌دهی و اولین نوبت آزاد از REST.', 'webtanan-booking'),
                'shortcode' => '[webtanan_booking_doctor_card doctor_id="1"]',
                'page' => __('کارت پزشک', 'webtanan-booking'),
            ),
            array(
                'title' => __('لیست تخصص‌ها', 'webtanan-booking'),
                'description' => __('نمایش تخصص‌ها با لینک به آرشیو فیلترشده همان تخصص.', 'webtanan-booking'),
                'shortcode' => '[webtanan_booking_specialty_list show_count="yes"]',
                'page' => __('تخصص‌ها', 'webtanan-booking'),
            ),
            array(
                'title' => __('تقویم نوبت‌دهی پزشک', 'webtanan-booking'),
                'description' => __('برای صفحه پزشک یا صفحه اختصاصی رزرو یک پزشک.', 'webtanan-booking'),
                'shortcode' => '[webtanan_booking_calendar doctor_id="1"]',
                'page' => __('دریافت نوبت', 'webtanan-booking'),
            ),
            array(
                'title' => __('اولین نوبت آزاد', 'webtanan-booking'),
                'description' => __('اطلاعات حساس به زمان از REST لود می‌شود و کش HTML را دور می‌زند.', 'webtanan-booking'),
                'shortcode' => '[webtanan_booking_next_available doctor_id="1"]',
                'page' => __('کارت یا پروفایل پزشک', 'webtanan-booking'),
            ),
            array(
                'title' => __('پنل بیمار', 'webtanan-booking'),
                'description' => __('مشاهده نوبت‌های آینده، سوابق، رسید و کیف پول بیمار.', 'webtanan-booking'),
                'shortcode' => '[webtanan_booking_patient_panel]',
                'page' => __('پنل بیمار', 'webtanan-booking'),
            ),
            array(
                'title' => __('داشبورد پزشک و منشی', 'webtanan-booking'),
                'description' => __('پنل فرانت‌اند مطب برای پزشک و منشی‌های مجاز.', 'webtanan-booking'),
                'shortcode' => '[webtanan_booking_doctor_dashboard]',
                'page' => __('پنل مطب', 'webtanan-booking'),
            ),
        );
    }

    private static function require_capability(string $capability): void {
        if (!current_user_can($capability) && !current_user_can('manage_options')) {
            wp_die(esc_html__('دسترسی غیرمجاز است.', 'webtanan-booking'));
        }
    }

    private static function request_text(string $key): string {
        return isset($_GET[$key]) ? sanitize_text_field(wp_unslash($_GET[$key])) : '';
    }

    private static function request_key(string $key): string {
        return isset($_GET[$key]) ? sanitize_key(wp_unslash($_GET[$key])) : '';
    }

    private static function sanitize_id_list($value): array {
        if (is_array($value)) {
            $ids = $value;
        } else {
            $ids = explode(',', (string) $value);
        }

        return array_values(array_unique(array_filter(array_map('absint', $ids))));
    }

    private static function normalize_time($time): string {
        $time = sanitize_text_field(wp_unslash($time));
        if (preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time)) {
            return $time . ':00';
        }
        if (preg_match('/^([01]\d|2[0-3]):[0-5]\d:[0-5]\d$/', $time)) {
            return substr($time, 0, 5) . ':00';
        }

        return '';
    }

    private static function valid_weekday(string $weekday): string {
        return isset(self::weekdays()[$weekday]) ? $weekday : 'saturday';
    }

    private static function weekday_label(string $weekday): string {
        return self::weekdays()[$weekday] ?? $weekday;
    }

    private static function weekdays(): array {
        return array(
            'saturday' => __('شنبه', 'webtanan-booking'),
            'sunday' => __('یکشنبه', 'webtanan-booking'),
            'monday' => __('دوشنبه', 'webtanan-booking'),
            'tuesday' => __('سه‌شنبه', 'webtanan-booking'),
            'wednesday' => __('چهارشنبه', 'webtanan-booking'),
            'thursday' => __('پنجشنبه', 'webtanan-booking'),
            'friday' => __('جمعه', 'webtanan-booking'),
        );
    }

    private static function appointment_statuses(): array {
        return array('pending' => __('در انتظار', 'webtanan-booking'), 'locked' => __('در حال رزرو', 'webtanan-booking'), 'confirmed' => __('تاییدشده', 'webtanan-booking'), 'cancelled' => __('لغوشده', 'webtanan-booking'), 'expired' => __('منقضی', 'webtanan-booking'), 'completed' => __('مراجعه کرد', 'webtanan-booking'), 'no_show' => __('مراجعه نکرد', 'webtanan-booking'), 'pay_at_clinic' => __('پرداخت در مطب', 'webtanan-booking'));
    }

    private static function payment_statuses(): array {
        return array('unpaid' => __('پرداخت‌نشده', 'webtanan-booking'), 'paid' => __('پرداخت آنلاین', 'webtanan-booking'), 'failed' => __('ناموفق', 'webtanan-booking'), 'refunded_to_wallet' => __('برگشت به کیف پول', 'webtanan-booking'), 'cash_at_clinic' => __('نقدی در مطب', 'webtanan-booking'), 'pos_at_clinic' => __('کارت‌خوان', 'webtanan-booking'), 'wallet_paid' => __('پرداخت از کیف پول', 'webtanan-booking'));
    }

    private static function settlement_statuses(): array {
        return array('pending' => __('در انتظار', 'webtanan-booking'), 'approved' => __('تایید شده', 'webtanan-booking'), 'rejected' => __('رد شده', 'webtanan-booking'), 'paid' => __('پرداخت شده', 'webtanan-booking'), 'cancelled' => __('لغو شده', 'webtanan-booking'));
    }

    private static function survey_statuses(): array {
        return array(
            'pending' => __('در انتظار بررسی', 'webtanan-booking'),
            'approved' => __('منتشر شده', 'webtanan-booking'),
            'private' => __('خصوصی', 'webtanan-booking'),
            'rejected' => __('رد شده', 'webtanan-booking'),
        );
    }

    private static function user_types(): array {
        return array('patient' => __('بیمار', 'webtanan-booking'), 'doctor' => __('پزشک', 'webtanan-booking'), 'secretary' => __('منشی', 'webtanan-booking'), 'platform' => __('پلتفرم', 'webtanan-booking'));
    }

    private static function status_badge(string $label, string $type): string {
        return '<span class="webtanan-admin-badge webtanan-admin-badge-' . esc_attr($type) . '">' . esc_html($label) . '</span>';
    }

    private static function sms_pattern_for_settings(array $sms, string $type): array {
        $pattern = $sms['patterns'][$type] ?? array();
        if (is_string($pattern)) {
            return array('enabled' => true, 'code' => $pattern);
        }
        if (!is_array($pattern)) {
            $pattern = array();
        }

        return array('enabled' => isset($pattern['enabled']) ? (bool) $pattern['enabled'] : true, 'code' => isset($pattern['code']) ? (string) $pattern['code'] : '');
    }

    private static function sms_pattern_variables(): array {
        return array(
            'code',
            'doctor_name',
            'patient_name',
            'date',
            'time',
            'appointment_code',
            'amount',
            'reason',
            'refund_status',
            'tracking_code',
            'status',
            'clinic_name',
            'clinic_address',
            'queue_position',
            'ahead_count',
            'waiting_list_url',
            'survey_url',
        );
    }

    private static function sms_pattern_examples(string $type): array {
        $examples = array(
            'otp' => array('code' => 'otp_login_001', 'text' => 'کد ورود شما: {code}'),
            'appointment_confirmed' => array('code' => 'appointment_confirmed_001', 'text' => '{patient_name} عزیز، نوبت شما با {doctor_name} در تاریخ {date} ساعت {time} قطعی شد. کد نوبت: {appointment_code}'),
            'staff_appointment_confirmed' => array('code' => 'staff_appointment_confirmed_001', 'text' => 'نوبت جدید برای {doctor_name}: {patient_name} در {date} ساعت {time}. کد: {appointment_code}'),
            'appointment_cancelled' => array('code' => 'appointment_cancelled_001', 'text' => '{patient_name} عزیز، نوبت {appointment_code} لغو شد. وضعیت استرداد: {refund_status}'),
            'staff_appointment_cancelled' => array('code' => 'staff_appointment_cancelled_001', 'text' => 'نوبت {appointment_code} برای {patient_name} لغو شد. دلیل: {reason}'),
            'wallet_charged' => array('code' => 'wallet_charged_001', 'text' => 'کیف پول شما به مبلغ {amount} تومان شارژ شد.'),
            'late_payment_wallet_charged' => array('code' => 'late_wallet_001', 'text' => 'پرداخت انجام شد اما ساعت نوبت از دست رفت. مبلغ {amount} تومان به کیف پول شما برگشت.'),
            'reminder_24h' => array('code' => 'reminder_24h_001', 'text' => 'یادآوری نوبت: {doctor_name}، {date} ساعت {time}. کد: {appointment_code}'),
            'payment_failed' => array('code' => 'payment_failed_001', 'text' => 'پرداخت نوبت {appointment_code} ناموفق بود. می‌توانید دوباره تلاش کنید.'),
            'settlement_requested' => array('code' => 'settlement_requested_001', 'text' => 'درخواست تسویه به مبلغ {amount} تومان ثبت شد.'),
            'settlement_paid' => array('code' => 'settlement_paid_001', 'text' => 'تسویه شما با کد پیگیری {tracking_code} پرداخت شد. مبلغ: {amount} تومان'),
            'settlement_status' => array('code' => 'settlement_status_001', 'text' => 'وضعیت درخواست تسویه شما: {status}'),
            'appointment_survey' => array('code' => 'appointment_survey_001', 'text' => '{patient_name} عزیز، لطفاً تجربه مراجعه به {doctor_name} را ثبت کنید: {survey_url}'),
            'waiting_list_30m' => array('code' => 'waiting_list_30m_001', 'text' => '{patient_name} عزیز، تا نوبت شما {ahead_count} نفر جلوتر هستند. جایگاه شما: {queue_position}. مشاهده زنده: {waiting_list_url}'),
            'bulk_appointment_cancelled' => array('code' => 'bulk_cancel_001', 'text' => 'نوبت‌های تاریخ {date} لغو شدند. دلیل: {reason}'),
            'manual_sms' => array('code' => 'manual_sms_not_pattern', 'text' => 'این نوع پیامک از پترن استفاده نمی‌کند و با متن آزاد مدیریت ارسال می‌شود.'),
        );

        return $examples[$type] ?? array('code' => $type . '_001', 'text' => 'نمونه متن پیامک با متغیرهای بالا');
    }

    private static function sample_sms_variables(string $message_type): array {
        return array(
            'code' => '458921',
            'doctor_name' => 'دکتر احمدی',
            'patient_name' => 'علی رضایی',
            'date' => current_time('Y-m-d'),
            'time' => current_time('H:i'),
            'appointment_code' => 'APT-TEST',
            'amount' => '100000',
            'reason' => 'تست سیستم',
            'refund_status' => 'شارژ کیف پول',
            'tracking_code' => 'TRK-TEST',
            'status' => 'pending',
            'clinic_name' => 'مطب مرکزی',
            'clinic_address' => 'تهران، خیابان نمونه، پلاک ۱۰',
            'queue_position' => '3',
            'ahead_count' => '2',
            'waiting_list_url' => home_url('/waiting-list-test'),
            'survey_url' => home_url('/survey-test'),
        );
    }
}
