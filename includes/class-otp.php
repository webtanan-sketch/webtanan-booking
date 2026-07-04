<?php
/**
 * OTP authentication.
 *
 * @package WebtananBooking
 */

namespace Webtanan\Booking;

defined('ABSPATH') || exit;

final class OTP {
    private const COMPLETION_TOKEN_TTL = 10 * MINUTE_IN_SECONDS;
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
        $now = current_datetime();
        $since = $now->modify('-' . $window_minutes . ' minutes')->format('Y-m-d H:i:s');
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
        $expiration_minutes = max(1, (int) ($settings['otp_expiration_minutes'] ?? 3));
        $expires_at = $now->modify('+' . $expiration_minutes . ' minutes')->format('Y-m-d H:i:s');

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

        $otp_id = (int) $wpdb->insert_id;
        $sms_result = SMS::send_pattern($mobile, 'otp', array('otp' => $code), 0);
        $sms_status = sanitize_key((string) ($sms_result['status'] ?? 'failed'));
        if (!in_array($sms_status, array('sent', 'queued', 'test_mode'), true)) {
            if ($otp_id > 0) {
                $wpdb->delete(DB::table('otp_logs'), array('id' => $otp_id), array('%d'));
            }

            return new \WP_Error(
                'webtanan_otp_sms_failed',
                __('ارسال پیامک کد ورود انجام نشد. تنظیمات پنل پیامک و کد پترن را بررسی کنید.', 'webtanan-booking'),
                array(
                    'status' => 502,
                    'sms_status' => $sms_status,
                )
            );
        }

        $response = array(
            'sent' => true,
            'expires_at' => $expires_at,
            'expires_in' => $expiration_minutes * MINUTE_IN_SECONDS,
        );

        return $response;
    }

    public static function verify(string $mobile, string $code, string $purpose = 'login') {
        global $wpdb;

        $mobile = self::normalize_mobile($mobile);
        $purpose = sanitize_key($purpose ?: 'login');
        $code = preg_replace('/\D/', '', self::latin_digits($code));

        if (!self::is_valid_mobile($mobile) || '' === $code) {
            return new \WP_Error('webtanan_invalid_otp_input', __('کد ورود معتبر نیست.', 'webtanan-booking'), array('status' => 400));
        }

        $settings = DB::get_settings();
        $table = DB::table('otp_logs');
        $otps = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table WHERE mobile = %s AND purpose = %s AND is_used = 0 AND expires_at >= %s ORDER BY id DESC LIMIT 5",
                $mobile,
                $purpose,
                DB::now()
            ),
            ARRAY_A
        );

        if (!$otps) {
            return new \WP_Error('webtanan_otp_not_found', __('کد ورود منقضی شده یا وجود ندارد.', 'webtanan-booking'), array('status' => 404));
        }

        $max_attempts = max(1, (int) ($settings['otp_max_attempts'] ?? 3));
        $otp = null;
        foreach ($otps as $candidate) {
            if ((int) $candidate['attempt_count'] < $max_attempts && wp_check_password($code, $candidate['otp_code_hash'])) {
                $otp = $candidate;
                break;
            }
        }

        if (!$otp && count(array_filter($otps, static function (array $candidate) use ($max_attempts): bool { return (int) $candidate['attempt_count'] < $max_attempts; })) === 0) {
            return new \WP_Error('webtanan_otp_attempts_exceeded', __('تعداد تلاش برای این کد ورود تمام شده است.', 'webtanan-booking'), array('status' => 429));
        }

        if (!$otp) {
            $latest = $otps[0];
            $wpdb->update($table, array('attempt_count' => min($max_attempts, (int) $latest['attempt_count'] + 1)), array('id' => (int) $latest['id']));

            return new \WP_Error('webtanan_otp_invalid', __('کد ورود اشتباه است.', 'webtanan-booking'), array('status' => 401));
        }

        $wpdb->query(
            $wpdb->prepare(
                "UPDATE $table SET is_used = 1, used_at = %s WHERE mobile = %s AND purpose = %s AND is_used = 0",
                DB::now(),
                $mobile,
                $purpose
            )
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
            'completion_token' => self::issue_completion_token((int) $user_id),
        );
    }

    public static function validate_completion_token(string $token): int {
        $token = sanitize_text_field($token);
        if ('' === $token || strlen($token) < 32) {
            return 0;
        }

        $payload = get_transient('webtanan_profile_token_' . hash('sha256', $token));
        if (!is_array($payload) || empty($payload['user_id']) || empty($payload['expires_at'])) {
            return 0;
        }

        if ((int) $payload['expires_at'] < time()) {
            delete_transient('webtanan_profile_token_' . hash('sha256', $token));
            return 0;
        }

        return (int) $payload['user_id'];
    }

    public static function consume_completion_token(string $token): void {
        $token = sanitize_text_field($token);
        if ('' !== $token) {
            delete_transient('webtanan_profile_token_' . hash('sha256', $token));
        }
    }

    private static function issue_completion_token(int $user_id): string {
        $token = wp_generate_password(48, false, false);
        set_transient(
            'webtanan_profile_token_' . hash('sha256', $token),
            array(
                'user_id' => $user_id,
                'expires_at' => time() + self::COMPLETION_TOKEN_TTL,
            ),
            self::COMPLETION_TOKEN_TTL
        );

        return $token;
    }

    public static function normalize_mobile(string $mobile): string {
        $mobile = trim(self::latin_digits($mobile));
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

    private static function latin_digits(string $value): string {
        return strtr(
            $value,
            array(
                '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
                '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
                '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
                '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            )
        );
    }

    private static function is_valid_mobile(string $mobile): bool {
        return (bool) preg_match('/^\+?[0-9]{10,15}$/', $mobile);
    }

    private static function find_or_create_patient(string $mobile) {
        $mobile_digits = preg_replace('/\D/', '', $mobile);
        $local_mobile = '98' === substr($mobile_digits, 0, 2) ? '0' . substr($mobile_digits, 2) : $mobile_digits;
        $mobile_variants = array_values(array_unique(array_filter(array($mobile, $mobile_digits, $local_mobile, '+' . $mobile_digits))));
        $mobile_meta_query = array(
            'relation' => 'OR',
            array('key' => 'webtanan_mobile', 'value' => $mobile_variants, 'compare' => 'IN'),
            array('key' => 'billing_phone', 'value' => $mobile_variants, 'compare' => 'IN'),
            array('key' => 'mobile', 'value' => $mobile_variants, 'compare' => 'IN'),
            array('key' => 'phone', 'value' => $mobile_variants, 'compare' => 'IN'),
        );
        $users = get_users(
            array(
                'number' => 1,
                'fields' => 'ID',
                'role__in' => array('administrator', 'webtanan_doctor', 'webtanan_secretary'),
                'meta_query' => $mobile_meta_query,
            )
        );
        if (!$users) {
            $users = get_users(
                array(
                    'number' => 1,
                    'fields' => 'ID',
                    'meta_query' => $mobile_meta_query,
                )
            );
        }

        if ($users) {
            return (int) $users[0];
        }

        $login_candidates = array_unique(
            array_filter(
                array(
                    $mobile_digits,
                    '0' . preg_replace('/^98/', '', $mobile_digits),
                    '+' . $mobile_digits,
                )
            )
        );
        foreach ($login_candidates as $candidate) {
            $existing = get_user_by('login', $candidate);
            if ($existing instanceof \WP_User) {
                update_user_meta((int) $existing->ID, 'webtanan_mobile', $mobile);
                return (int) $existing->ID;
            }
        }

        $login_base = 'patient_' . $mobile_digits;
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
