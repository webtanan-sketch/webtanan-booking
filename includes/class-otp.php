<?php
/**
 * OTP authentication.
 *
 * @package WebtananBooking
 */

namespace Webtanan\Booking;

defined('ABSPATH') || exit;

final class OTP {
    private const DEFAULT_RATE_LIMIT_MAX_SENDS = 3;
    private const DEFAULT_RATE_LIMIT_WINDOW_MINUTES = 15;

    public static function send(string $mobile, string $purpose = 'login') {
        global $wpdb;

        $mobile = self::normalize_mobile($mobile);
        $purpose = sanitize_key($purpose ?: 'login');

        if (!self::is_valid_mobile($mobile)) {
            return new \WP_Error('webtanan_invalid_mobile', __('شماره موبایل معتبر نیست.', 'webtanan-booking'), array('status' => 400));
        }

        $settings = DB::get_settings();
        $max_sends = max(1, (int) ($settings['otp_rate_limit_max_sends'] ?? self::DEFAULT_RATE_LIMIT_MAX_SENDS));
        $window_minutes = max(1, (int) ($settings['otp_rate_limit_window_minutes'] ?? self::DEFAULT_RATE_LIMIT_WINDOW_MINUTES));
        $since = gmdate('Y-m-d H:i:s', current_time('timestamp') - ($window_minutes * MINUTE_IN_SECONDS));
        $recent = (int) $wpdb->get_var(
            $wpdb->prepare(
                'SELECT COUNT(*) FROM ' . DB::table('otp_logs') . ' WHERE mobile = %s AND purpose = %s AND created_at >= %s',
                $mobile,
                $purpose,
                $since
            )
        );

        if ($recent >= $max_sends) {
            return new \WP_Error(
                'webtanan_otp_rate_limited',
                sprintf(
                    /* translators: %d: OTP rate limit window in minutes. */
                    __('تعداد درخواست‌های شما بیش از حد مجاز است. لطفاً %d دقیقه دیگر تلاش کنید.', 'webtanan-booking'),
                    $window_minutes
                ),
                array('status' => 429)
            );
        }

        $code = (string) random_int(100000, 999999);
        $expires_at = gmdate('Y-m-d H:i:s', current_time('timestamp') + ((int) $settings['otp_expiration_minutes'] * MINUTE_IN_SECONDS));

        $inserted = $wpdb->insert(
            DB::table('otp_logs'),
            array(
                'mobile' => $mobile,
                'otp_code_hash' => wp_hash_password($code),
                'purpose' => $purpose,
                'expires_at' => $expires_at,
                'attempt_count' => 0,
                'is_used' => 0,
                'ip_address' => self::ip_address(),
                'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '',
                'created_at' => DB::now(),
            )
        );

        if (!$inserted) {
            return new \WP_Error('webtanan_otp_insert_failed', __('ایجاد کد ورود انجام نشد.', 'webtanan-booking'), array('status' => 500));
        }

        SMS::send_pattern($mobile, 'otp', array('code' => $code), 0);

        $response = array(
            'sent' => true,
            'expires_at' => $expires_at,
        );

        if (defined('WP_DEBUG') && WP_DEBUG) {
            $response['debug_otp'] = $code;
        }

        return $response;
    }

    public static function verify(string $mobile, string $code, string $purpose = 'login') {
        global $wpdb;

        $mobile = self::normalize_mobile($mobile);
        $purpose = sanitize_key($purpose ?: 'login');
        $code = preg_replace('/\D/', '', $code);

        if (!self::is_valid_mobile($mobile) || '' === $code) {
            return new \WP_Error('webtanan_invalid_otp_input', __('کد ورود معتبر نیست.', 'webtanan-booking'), array('status' => 400));
        }

        $settings = DB::get_settings();
        $table = DB::table('otp_logs');
        $otp = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $table WHERE mobile = %s AND purpose = %s AND is_used = 0 AND expires_at >= %s ORDER BY id DESC LIMIT 1",
                $mobile,
                $purpose,
                DB::now()
            ),
            ARRAY_A
        );

        if (!$otp) {
            return new \WP_Error('webtanan_otp_not_found', __('کد ورود منقضی شده یا وجود ندارد.', 'webtanan-booking'), array('status' => 404));
        }

        if ((int) $otp['attempt_count'] >= (int) $settings['otp_max_attempts']) {
            return new \WP_Error('webtanan_otp_attempts_exceeded', __('تعداد تلاش برای این کد ورود تمام شده است.', 'webtanan-booking'), array('status' => 429));
        }

        if (!wp_check_password($code, $otp['otp_code_hash'])) {
            $wpdb->update($table, array('attempt_count' => (int) $otp['attempt_count'] + 1), array('id' => (int) $otp['id']));

            return new \WP_Error('webtanan_otp_invalid', __('کد ورود اشتباه است.', 'webtanan-booking'), array('status' => 401));
        }

        $wpdb->update(
            $table,
            array('is_used' => 1, 'used_at' => DB::now()),
            array('id' => (int) $otp['id'])
        );

        $user_id = self::find_or_create_patient($mobile);
        if (is_wp_error($user_id)) {
            return $user_id;
        }

        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id, true);

        return array(
            'verified' => true,
            'user_id' => $user_id,
            'nonce' => wp_create_nonce('wp_rest'),
        );
    }

    public static function normalize_mobile(string $mobile): string {
        $mobile = trim($mobile);
        $mobile = str_replace(array(' ', '-', '(', ')'), '', $mobile);
        $mobile = preg_replace('/[^0-9+]/', '', $mobile);

        if (0 === strpos($mobile, '0098')) {
            $mobile = '+98' . substr($mobile, 4);
        } elseif (0 === strpos($mobile, '98')) {
            $mobile = '+98' . substr($mobile, 2);
        } elseif (0 === strpos($mobile, '09')) {
            $mobile = '+98' . substr($mobile, 1);
        } elseif (0 === strpos($mobile, '9')) {
            $mobile = '+98' . $mobile;
        }

        return $mobile;
    }

    private static function is_valid_mobile(string $mobile): bool {
        return (bool) preg_match('/^\+?[0-9]{10,15}$/', $mobile);
    }

    private static function find_or_create_patient(string $mobile) {
        $users = get_users(
            array(
                'meta_key' => 'webtanan_mobile',
                'meta_value' => $mobile,
                'number' => 1,
                'fields' => 'ID',
            )
        );

        if ($users) {
            return (int) $users[0];
        }

        $login_base = 'patient_' . preg_replace('/\D/', '', $mobile);
        $login = $login_base;
        $suffix = 1;
        while (username_exists($login)) {
            $login = $login_base . '_' . $suffix;
            $suffix++;
        }

        $user_id = wp_insert_user(
            array(
                'user_login' => $login,
                'user_pass' => wp_generate_password(32, true, true),
                'role' => 'webtanan_patient',
                'display_name' => $mobile,
            )
        );

        if (is_wp_error($user_id)) {
            return $user_id;
        }

        update_user_meta((int) $user_id, 'webtanan_mobile', $mobile);

        return (int) $user_id;
    }

    private static function ip_address(): string {
        foreach (array('HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR') as $key) {
            if (!empty($_SERVER[$key])) {
                $value = sanitize_text_field(wp_unslash($_SERVER[$key]));
                $parts = explode(',', $value);

                return trim($parts[0]);
            }
        }

        return '';
    }
}
