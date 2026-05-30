<?php
/**
 * SMS logging and IPPanel pattern dispatch.
 *
 * @package WebtananBooking
 */

namespace Webtanan\Booking;

defined('ABSPATH') || exit;

final class SMS {
    public static function init(): void {
        add_action('webtanan_booking_send_reminders', array(__CLASS__, 'send_24h_reminders'));
    }

    public static function message_types(): array {
        return array(
            'otp' => __('ورود با کد تایید', 'webtanan-booking'),
            'appointment_confirmed' => __('تایید نوبت - بیمار', 'webtanan-booking'),
            'staff_appointment_confirmed' => __('تایید نوبت - پزشک/منشی', 'webtanan-booking'),
            'appointment_cancelled' => __('لغو نوبت - بیمار', 'webtanan-booking'),
            'staff_appointment_cancelled' => __('لغو نوبت - پزشک/منشی', 'webtanan-booking'),
            'wallet_charged' => __('شارژ کیف پول', 'webtanan-booking'),
            'late_payment_wallet_charged' => __('پرداخت دیرهنگام به کیف پول منتقل شد', 'webtanan-booking'),
            'reminder_24h' => __('یادآوری نوبت', 'webtanan-booking'),
            'payment_failed' => __('پرداخت ناموفق', 'webtanan-booking'),
            'settlement_requested' => __('درخواست تسویه ثبت شد', 'webtanan-booking'),
            'settlement_paid' => __('تسویه پرداخت شد', 'webtanan-booking'),
            'settlement_status' => __('وضعیت تسویه', 'webtanan-booking'),
        );
    }

    public static function send_pattern(string $mobile, string $message_type, array $variables = array(), int $appointment_id = 0) {
        $settings = self::settings();
        $message_type = sanitize_key($message_type);
        $mobile = IPPanel_SMS_Service::normalize_mobile_e164($mobile);
        $pattern = self::pattern_config($message_type);
        $pattern_code = $pattern['code'];
        $variables = self::variables_for_message($message_type, $variables, $appointment_id);

        if (empty($settings['log_enabled'])) {
            return self::send_without_log($mobile, $message_type, $pattern_code, $variables, $settings, $pattern);
        }

        if (empty($settings['enabled'])) {
            return self::log_and_return($mobile, $pattern_code, $message_type, $variables, array('message' => 'SMS module is disabled.'), 'disabled', $appointment_id);
        }

        if (!self::message_type_allowed($message_type, $settings)) {
            return self::log_and_return($mobile, $pattern_code, $message_type, $variables, array('message' => 'This SMS target is disabled in settings.'), 'disabled', $appointment_id);
        }

        if (empty($pattern['enabled'])) {
            return self::log_and_return($mobile, $pattern_code, $message_type, $variables, array('message' => 'Pattern is disabled.'), 'disabled', $appointment_id);
        }

        if (!$pattern_code) {
            return self::log_and_return($mobile, '', $message_type, $variables, array('message' => 'Pattern code is empty.'), 'failed', $appointment_id);
        }

        if (!$mobile) {
            return self::log_and_return($mobile, $pattern_code, $message_type, $variables, array('message' => 'Recipient mobile is empty.'), 'failed', $appointment_id);
        }

        if (self::recent_duplicate_exists($mobile, $message_type, $appointment_id)) {
            return self::log_and_return($mobile, $pattern_code, $message_type, $variables, array('message' => 'Duplicate SMS blocked for 40 seconds.'), 'duplicate_blocked', $appointment_id);
        }

        $result = self::dispatch($mobile, $pattern_code, $message_type, $variables, $settings);
        $status = self::status_from_result($result);

        return self::log_and_return($mobile, $pattern_code, $message_type, $variables, $result, $status, $appointment_id);
    }

    public static function send_24h_reminders(): int {
        global $wpdb;

        $settings = self::settings();
        if (empty($settings['enabled']) || empty($settings['reminder_enabled'])) {
            return 0;
        }

        $appointments = $wpdb->get_results(
            "SELECT * FROM " . DB::table('appointments') . " WHERE appointment_status = 'confirmed' AND appointment_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 2 DAY) LIMIT 200",
            ARRAY_A
        );

        $sent = 0;
        $now = current_time('timestamp');
        $hours = max(1, (int) ($settings['reminder_time_hours'] ?? 24));

        foreach ($appointments as $appointment) {
            $appointment_ts = strtotime($appointment['appointment_date'] . ' ' . $appointment['start_time']);
            if ($appointment_ts < $now + (($hours - 1) * HOUR_IN_SECONDS) || $appointment_ts > $now + ($hours * HOUR_IN_SECONDS)) {
                continue;
            }

            $already_sent = (int) $wpdb->get_var(
                $wpdb->prepare(
                    'SELECT COUNT(*) FROM ' . DB::table('sms_logs') . ' WHERE related_appointment_id = %d AND message_type = %s AND status IN ("sent","test_mode")',
                    (int) $appointment['id'],
                    'reminder_24h'
                )
            );

            if ($already_sent > 0) {
                continue;
            }

            $result = self::send_pattern(
                $appointment['patient_mobile'],
                'reminder_24h',
                array(
                    'date' => $appointment['appointment_date'],
                    'time' => substr($appointment['start_time'], 0, 5),
                    'appointment_code' => $appointment['appointment_code'],
                ),
                (int) $appointment['id']
            );

            if (in_array($result['status'], array('sent', 'test_mode'), true)) {
                $sent++;
            }
        }

        return $sent;
    }

    public static function list_ippanel_patterns(int $page = 1, int $per_page = 100): array {
        return (new IPPanel_SMS_Service())->list_patterns($page, $per_page);
    }

    public static function send_staff_appointment_notification(int $appointment_id, string $message_type): array {
        $appointment = class_exists(__NAMESPACE__ . '\Booking') ? Booking::get_appointment($appointment_id) : null;
        if (!$appointment) {
            return array();
        }

        $settings = self::settings();
        $doctor = Booking::get_doctor((int) $appointment['doctor_id']);
        $sent = array();

        if ($doctor && !empty($settings['send_to_doctor']) && (int) $doctor['user_id'] > 0) {
            $mobile = self::mobile_for_user((int) $doctor['user_id']);
            if ($mobile) {
                $sent[] = self::send_pattern($mobile, $message_type, array(), $appointment_id);
            }
        }

        if (!empty($settings['send_to_secretary'])) {
            foreach (self::secretary_user_ids_for_doctor((int) $appointment['doctor_id']) as $secretary_user_id) {
                $mobile = self::mobile_for_user($secretary_user_id);
                if ($mobile) {
                    $sent[] = self::send_pattern($mobile, $message_type, array(), $appointment_id);
                }
            }
        }

        return $sent;
    }

    public static function send_doctor_notification(int $doctor_id, string $message_type, array $variables = array()): array {
        $doctor = class_exists(__NAMESPACE__ . '\Booking') ? Booking::get_doctor($doctor_id) : null;
        if (!$doctor || (int) $doctor['user_id'] <= 0) {
            return array();
        }

        $mobile = self::mobile_for_user((int) $doctor['user_id']);
        if (!$mobile) {
            return array();
        }

        return self::send_pattern($mobile, $message_type, $variables, 0);
    }

    private static function send_without_log(string $mobile, string $message_type, string $pattern_code, array $variables, array $settings, array $pattern): array {
        if (empty($settings['enabled'])) {
            return array('status' => 'disabled', 'provider_response' => array('message' => 'SMS module is disabled.'));
        }

        if (empty($pattern['enabled']) || !$pattern_code || !$mobile) {
            return array('status' => 'failed', 'provider_response' => array('message' => 'SMS pattern, mobile or status is invalid.'));
        }

        $result = self::dispatch($mobile, $pattern_code, $message_type, $variables, $settings);

        return array('status' => self::status_from_result($result), 'provider_response' => $result);
    }

    private static function dispatch(string $mobile, string $pattern_code, string $message_type, array $variables, array $settings): array {
        $override = apply_filters('webtanan_booking_sms_send', null, $mobile, $pattern_code, $message_type, $variables);
        if (null !== $override) {
            if (is_wp_error($override)) {
                return array('success' => false, 'status' => 'failed', 'message' => $override->get_error_message());
            }

            if (true === $override) {
                return array('success' => true, 'status' => 'sent', 'message' => 'Provider accepted message.');
            }

            return is_array($override) ? $override : array('success' => false, 'status' => 'failed', 'message' => 'Invalid SMS filter response.');
        }

        if ('ippanel' !== ($settings['provider'] ?? 'ippanel')) {
            return array('success' => false, 'status' => 'failed', 'message' => 'Unsupported SMS provider.');
        }

        return (new IPPanel_SMS_Service())->send_pattern($mobile, $pattern_code, $variables);
    }

    private static function log_and_return(string $mobile, string $pattern_code, string $message_type, array $variables, array $provider_response, string $status, int $appointment_id): array {
        global $wpdb;

        $wpdb->insert(
            DB::table('sms_logs'),
            array(
                'mobile' => $mobile,
                'pattern_code' => $pattern_code,
                'message_type' => $message_type,
                'variables' => wp_json_encode($variables, JSON_UNESCAPED_UNICODE),
                'provider_response' => wp_json_encode($provider_response, JSON_UNESCAPED_UNICODE),
                'status' => sanitize_key($status),
                'related_appointment_id' => absint($appointment_id),
                'created_at' => DB::now(),
            )
        );

        return array('status' => $status, 'provider_response' => $provider_response);
    }

    private static function recent_duplicate_exists(string $mobile, string $message_type, int $appointment_id): bool {
        global $wpdb;

        $since = date('Y-m-d H:i:s', current_time('timestamp') - 40);
        $table = DB::table('sms_logs');

        $count = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM $table WHERE mobile = %s AND message_type = %s AND related_appointment_id = %d AND created_at >= %s AND status IN ('sent','test_mode','queued')",
                $mobile,
                $message_type,
                $appointment_id,
                $since
            )
        );

        return $count > 0;
    }

    private static function status_from_result(array $result): string {
        if (!empty($result['status']) && in_array($result['status'], array('sent', 'failed', 'test_mode', 'queued'), true)) {
            return sanitize_key($result['status']);
        }

        return !empty($result['success']) ? 'sent' : 'failed';
    }

    private static function settings(): array {
        $settings = DB::get_settings();
        $sms = $settings['sms_provider_settings'] ?? array();
        $defaults = DB::default_settings()['sms_provider_settings'];
        $sms = array_replace_recursive($defaults, is_array($sms) ? $sms : array());
        $sms['from_number'] = $sms['from_number'] ?: ($sms['originator'] ?? '');
        $sms['reminder_time_hours'] = (int) ($settings['reminder_time_hours'] ?? 24);

        return $sms;
    }

    private static function pattern_config(string $message_type): array {
        $settings = self::settings();
        $patterns = $settings['patterns'] ?? array();
        $pattern = $patterns[$message_type] ?? array();

        if (is_string($pattern)) {
            return array('enabled' => true, 'code' => sanitize_text_field($pattern));
        }

        if (!is_array($pattern)) {
            $pattern = array();
        }

        return array(
            'enabled' => isset($pattern['enabled']) ? (bool) $pattern['enabled'] : true,
            'code' => isset($pattern['code']) ? sanitize_text_field($pattern['code']) : '',
        );
    }

    private static function message_type_allowed(string $message_type, array $settings): bool {
        if (in_array($message_type, array('appointment_confirmed', 'appointment_cancelled', 'wallet_charged', 'late_payment_wallet_charged', 'reminder_24h', 'payment_failed', 'otp'), true)) {
            return !empty($settings['send_to_patient']);
        }

        if (0 === strpos($message_type, 'staff_')) {
            return !empty($settings['send_to_doctor']) || !empty($settings['send_to_secretary']);
        }

        if (in_array($message_type, array('settlement_requested', 'settlement_paid', 'settlement_status'), true)) {
            return !empty($settings['send_to_doctor']);
        }

        return true;
    }

    private static function mobile_for_user(int $user_id): string {
        foreach (array('webtanan_mobile', 'billing_phone', 'mobile', 'phone') as $key) {
            $mobile = get_user_meta($user_id, $key, true);
            if (is_string($mobile) && '' !== trim($mobile)) {
                return $mobile;
            }
        }

        $user = get_userdata($user_id);
        if ($user && preg_match('/^\+?[0-9]{10,15}$/', $user->user_login)) {
            return $user->user_login;
        }

        return '';
    }

    private static function secretary_user_ids_for_doctor(int $doctor_id): array {
        $users = get_users(
            array(
                'role' => 'webtanan_secretary',
                'fields' => 'ID',
                'number' => 300,
            )
        );

        $matched = array();
        foreach ($users as $user_id) {
            $assigned = get_user_meta((int) $user_id, 'webtanan_assigned_doctor_ids', true);
            if (is_string($assigned)) {
                $assigned = array_filter(array_map('absint', explode(',', $assigned)));
            }
            if (!is_array($assigned)) {
                $assigned = array();
            }

            if (in_array($doctor_id, array_map('absint', $assigned), true)) {
                $matched[] = (int) $user_id;
            }
        }

        return array_values(array_unique($matched));
    }

    private static function variables_for_message(string $message_type, array $variables, int $appointment_id): array {
        if ($appointment_id > 0 && class_exists(__NAMESPACE__ . '\Booking')) {
            $appointment = Booking::get_appointment($appointment_id);
            if ($appointment) {
                $doctor = Booking::get_doctor((int) $appointment['doctor_id']);
                $doctor_name = '';
                if ($doctor) {
                    $doctor_name = (int) $doctor['post_id'] > 0 ? get_the_title((int) $doctor['post_id']) : ($doctor['clinic_name'] ?? '');
                }

                $variables = array_merge(
                    array(
                        'doctor_name' => $doctor_name,
                        'patient_name' => trim($appointment['patient_first_name'] . ' ' . $appointment['patient_last_name']),
                        'date' => $appointment['appointment_date'],
                        'time' => substr((string) $appointment['start_time'], 0, 5),
                        'appointment_code' => $appointment['appointment_code'],
                        'code' => $appointment['appointment_code'],
                        'refund_status' => __('استرداد کیف پول طبق قانون لغو محاسبه می‌شود.', 'webtanan-booking'),
                    ),
                    $variables
                );
            }
        }

        if ('otp' === $message_type && isset($variables['otp'])) {
            $variables['code'] = $variables['otp'];
            unset($variables['otp']);
        }

        return IPPanel_SMS_Service::sanitize_params($variables);
    }
}
