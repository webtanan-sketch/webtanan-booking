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
        add_action('webtanan_booking_send_waiting_list_messages', array(__CLASS__, 'send_waiting_list_30m_messages'));
        add_action('webtanan_booking_send_survey_requests', array(__CLASS__, 'send_survey_requests'));
        add_action('webtanan_booking_retry_failed_sms', array(__CLASS__, 'retry_failed_messages'));
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
            'appointment_survey' => __('نظرسنجی بعد از نوبت', 'webtanan-booking'),
            'waiting_list_30m' => __('جایگاه صف انتظار نیم ساعت قبل', 'webtanan-booking'),
            'bulk_appointment_cancelled' => __('لغو گروهی نوبت‌ها', 'webtanan-booking'),
            'manual_sms' => __('پیامک معمولی مدیریت', 'webtanan-booking'),
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

        if ('otp' !== $message_type && self::recent_duplicate_exists($mobile, $message_type, $appointment_id)) {
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

    public static function send_waiting_list_30m_messages(): int {
        global $wpdb;

        $settings = self::settings();
        if (empty($settings['enabled']) || empty($settings['reminder_enabled'])) {
            return 0;
        }

        $now = current_time('timestamp');
        $from = date('Y-m-d H:i:s', $now + (25 * MINUTE_IN_SECONDS));
        $to = date('Y-m-d H:i:s', $now + (35 * MINUTE_IN_SECONDS));
        $appointments = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM " . DB::table('appointments') . "
                WHERE appointment_status IN ('confirmed','pay_at_clinic')
                    AND patient_mobile <> ''
                    AND CONCAT(appointment_date, ' ', start_time) BETWEEN %s AND %s
                ORDER BY appointment_date ASC, start_time ASC
                LIMIT 200",
                $from,
                $to
            ),
            ARRAY_A
        );

        $sent = 0;
        foreach ($appointments as $appointment) {
            if (self::sms_already_sent((int) $appointment['id'], 'waiting_list_30m')) {
                continue;
            }

            $snapshot = class_exists(__NAMESPACE__ . '\Booking') ? Booking::waiting_list_snapshot($appointment) : array();
            $result = self::send_pattern(
                $appointment['patient_mobile'],
                'waiting_list_30m',
                array(
                    'queue_position' => (string) ($snapshot['queue_position'] ?? 1),
                    'ahead_count' => (string) ($snapshot['ahead_count'] ?? 0),
                    'waiting_list_url' => self::waiting_list_url($appointment),
                ),
                (int) $appointment['id']
            );

            if (in_array($result['status'], array('sent', 'test_mode'), true)) {
                $sent++;
            }
        }

        return $sent;
    }

    public static function send_survey_requests(): int {
        global $wpdb;

        $settings = self::settings();
        if (empty($settings['enabled'])) {
            return 0;
        }

        $now = current_time('timestamp');
        $from = date('Y-m-d H:i:s', $now - (2 * HOUR_IN_SECONDS));
        $to = date('Y-m-d H:i:s', $now - HOUR_IN_SECONDS);
        $appointments = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM " . DB::table('appointments') . "
                WHERE appointment_status IN ('confirmed','pay_at_clinic','completed')
                    AND patient_mobile <> ''
                    AND CONCAT(appointment_date, ' ', start_time) BETWEEN %s AND %s
                ORDER BY appointment_date ASC, start_time ASC
                LIMIT 200",
                $from,
                $to
            ),
            ARRAY_A
        );

        $sent = 0;
        foreach ($appointments as $appointment) {
            if (self::sms_already_sent((int) $appointment['id'], 'appointment_survey')) {
                continue;
            }

            $result = self::send_pattern(
                $appointment['patient_mobile'],
                'appointment_survey',
                array('survey_url' => self::survey_url($appointment)),
                (int) $appointment['id']
            );

            if (in_array($result['status'], array('sent', 'test_mode'), true)) {
                $sent++;
            }
        }

        return $sent;
    }

    public static function retry_failed_messages(): int {
        global $wpdb;

        if (get_transient('webtanan_booking_sms_retry_lock')) {
            return 0;
        }
        set_transient('webtanan_booking_sms_retry_lock', 1, 10 * MINUTE_IN_SECONDS);

        $table = DB::table('sms_logs');
        $since = wp_date('Y-m-d H:i:s', current_time('timestamp') - (6 * HOUR_IN_SECONDS), wp_timezone());
        $before = wp_date('Y-m-d H:i:s', current_time('timestamp') - (2 * MINUTE_IN_SECONDS), wp_timezone());
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table
                WHERE status = 'failed'
                    AND created_at BETWEEN %s AND %s
                    AND message_type NOT IN ('otp','manual_sms')
                ORDER BY id ASC
                LIMIT 50",
                $since,
                $before
            ),
            ARRAY_A
        );

        $sent = 0;
        foreach ((array) $rows as $row) {
            $appointment_id = (int) ($row['related_appointment_id'] ?? 0);
            $message_type = sanitize_key((string) ($row['message_type'] ?? ''));
            $mobile = sanitize_text_field((string) ($row['mobile'] ?? ''));
            if ('' === $message_type || '' === $mobile || self::sms_already_sent_to($appointment_id, $message_type, $mobile)) {
                continue;
            }

            $provider = json_decode((string) ($row['provider_response'] ?? ''), true);
            if (!self::is_transient_provider_failure(is_array($provider) ? $provider : array())) {
                continue;
            }

            $attempts = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM $table
                    WHERE mobile = %s AND message_type = %s AND related_appointment_id = %d
                        AND status = 'failed' AND created_at >= %s",
                    $mobile,
                    $message_type,
                    $appointment_id,
                    $since
                )
            );
            if ($attempts >= 3) {
                continue;
            }

            $variables = json_decode((string) ($row['variables'] ?? ''), true);
            $result = self::send_pattern($mobile, $message_type, is_array($variables) ? $variables : array(), $appointment_id);
            if (in_array((string) ($result['status'] ?? ''), array('sent', 'queued', 'test_mode'), true)) {
                $sent++;
            }
        }

        delete_transient('webtanan_booking_sms_retry_lock');

        return $sent;
    }

    public static function list_ippanel_patterns(int $page = 1, int $per_page = 100): array {
        return (new IPPanel_SMS_Service())->list_patterns($page, $per_page);
    }

    public static function send_normal(array $mobiles, string $message, array $variables = array(), int $appointment_id = 0): array {
        $settings = self::settings();
        $message = trim(wp_strip_all_tags($message));
        $mobiles = array_values(
            array_unique(
                array_filter(
                    array_map(
                        static function ($mobile): string {
                            return IPPanel_SMS_Service::normalize_mobile_e164((string) $mobile);
                        },
                        $mobiles
                    )
                )
            )
        );

        if (!$mobiles || '' === $message) {
            return array('status' => 'failed', 'provider_response' => array('message' => __('گیرنده یا متن پیامک خالی است.', 'webtanan-booking')));
        }

        if (empty($settings['enabled'])) {
            $result = array('success' => false, 'status' => 'disabled', 'message' => 'SMS module is disabled.');
            foreach ($mobiles as $mobile) {
                self::log_and_return($mobile, '', 'manual_sms', array_merge($variables, array('message' => $message)), $result, 'disabled', $appointment_id);
            }

            return array('status' => 'disabled', 'provider_response' => $result, 'recipients' => count($mobiles));
        }

        $result = (new IPPanel_SMS_Service())->send_normal($mobiles, $message);
        $status = self::status_from_result($result);

        foreach ($mobiles as $mobile) {
            self::log_and_return($mobile, '', 'manual_sms', array_merge($variables, array('message' => $message)), $result, $status, $appointment_id);
        }

        return array('status' => $status, 'provider_response' => $result, 'recipients' => count($mobiles));
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

        if ('failed' === $status && !in_array($message_type, array('otp', 'manual_sms'), true) && self::is_transient_provider_failure($provider_response)) {
            $next_retry = wp_next_scheduled('webtanan_booking_retry_failed_sms');
            if (!$next_retry || $next_retry > time() + (3 * MINUTE_IN_SECONDS)) {
                wp_schedule_single_event(time() + (2 * MINUTE_IN_SECONDS), 'webtanan_booking_retry_failed_sms');
            }
        }

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
        if (in_array($message_type, array('appointment_confirmed', 'appointment_cancelled', 'wallet_charged', 'late_payment_wallet_charged', 'reminder_24h', 'payment_failed', 'otp', 'appointment_survey', 'waiting_list_30m'), true)) {
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

    private static function sms_already_sent(int $appointment_id, string $message_type): bool {
        global $wpdb;

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                'SELECT COUNT(*) FROM ' . DB::table('sms_logs') . ' WHERE related_appointment_id = %d AND message_type = %s AND status IN ("sent","test_mode","queued")',
                $appointment_id,
                $message_type
            )
        ) > 0;
    }

    private static function sms_already_sent_to(int $appointment_id, string $message_type, string $mobile): bool {
        global $wpdb;

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                'SELECT COUNT(*) FROM ' . DB::table('sms_logs') . ' WHERE related_appointment_id = %d AND message_type = %s AND mobile = %s AND status IN ("sent","test_mode","queued")',
                $appointment_id,
                $message_type,
                $mobile
            )
        ) > 0;
    }

    private static function is_transient_provider_failure(array $provider): bool {
        $error_code = sanitize_key((string) ($provider['error_code'] ?? ''));
        $http_code = absint($provider['http_code'] ?? 0);
        $message = strtolower((string) ($provider['message'] ?? ''));

        if ('http_error' === $error_code) {
            return (bool) preg_match('/timeout|timed out|resolve|resolving|temporary|connection|curl error (6|7|28)/i', $message);
        }

        return 429 === $http_code || $http_code >= 500;
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
                        'clinic_name' => $doctor['clinic_name'] ?? '',
                        'clinic_address' => $doctor['clinic_address'] ?? '',
                        'waiting_list_url' => self::waiting_list_url($appointment),
                        'survey_url' => self::survey_url($appointment),
                    ),
                    $variables
                );
            }
        }

        if ('otp' === $message_type) {
            $settings = self::settings();
            $parameter_name = sanitize_key((string) ($settings['otp_parameter_name'] ?? 'verifyotp'));
            if ('' === $parameter_name) {
                $parameter_name = 'verifyotp';
            }
            $otp_value = '';
            if (isset($variables['otp'])) {
                $otp_value = (string) $variables['otp'];
            }

            if (isset($variables['code'])) {
                $otp_value = (string) $variables['code'];
            }

            if (isset($variables['verifyotp'])) {
                $otp_value = (string) $variables['verifyotp'];
            }

            if (isset($variables[$parameter_name])) {
                $otp_value = (string) $variables[$parameter_name];
            }

            return array($parameter_name => preg_replace('/\D/', '', $otp_value));
        }

        $allowed_by_type = array(
            'appointment_confirmed' => array('doctor_name', 'patient_name', 'date', 'time', 'appointment_code', 'clinic_name', 'clinic_address', 'amount', 'tracking_code'),
            'appointment_cancelled' => array('doctor_name', 'patient_name', 'date', 'time', 'appointment_code', 'reason', 'refund_status', 'amount'),
            'bulk_appointment_cancelled' => array('doctor_name', 'date', 'reason', 'status', 'amount'),
            'reminder_24h' => array('doctor_name', 'patient_name', 'date', 'time', 'appointment_code', 'clinic_name', 'clinic_address'),
            'waiting_list_30m' => array('doctor_name', 'patient_name', 'date', 'time', 'appointment_code', 'queue_position', 'ahead_count', 'waiting_list_url'),
            'appointment_survey' => array('doctor_name', 'patient_name', 'date', 'time', 'appointment_code', 'survey_url'),
            'wallet_charged' => array('patient_name', 'appointment_code', 'amount', 'status'),
            'late_payment_wallet_charged' => array('patient_name', 'doctor_name', 'appointment_code', 'amount', 'status'),
            'payment_failed' => array('patient_name', 'doctor_name', 'appointment_code', 'amount', 'reason'),
            'settlement_requested' => array('doctor_name', 'amount', 'status'),
            'settlement_paid' => array('doctor_name', 'amount', 'status', 'tracking_code'),
            'settlement_status' => array('doctor_name', 'amount', 'status', 'reason', 'tracking_code'),
        );
        if (isset($allowed_by_type[$message_type])) {
            $variables = array_intersect_key($variables, array_flip($allowed_by_type[$message_type]));
        }

        $variables = apply_filters('webtanan_booking_sms_pattern_variables', $variables, $message_type, $appointment_id);

        return IPPanel_SMS_Service::sanitize_params(is_array($variables) ? $variables : array());
    }

    private static function waiting_list_url(array $appointment): string {
        return add_query_arg(
            array(
                'webtanan_waiting_list' => 1,
                'appointment_code' => (string) $appointment['appointment_code'],
                'token' => self::appointment_token($appointment, 'waiting-list'),
            ),
            home_url('/')
        );
    }

    private static function survey_url(array $appointment): string {
        return add_query_arg(
            array(
                'webtanan_survey' => 1,
                'appointment_code' => (string) $appointment['appointment_code'],
                'token' => self::appointment_token($appointment, 'survey'),
            ),
            home_url('/')
        );
    }

    public static function public_survey_url(array $appointment): string {
        return self::survey_url($appointment);
    }

    public static function appointment_token(array $appointment, string $purpose): string {
        $payload = implode('|', array(
            sanitize_key($purpose),
            (int) ($appointment['id'] ?? 0),
            (string) ($appointment['appointment_code'] ?? ''),
            (string) ($appointment['patient_mobile'] ?? ''),
        ));

        return hash_hmac('sha256', $payload, wp_salt('nonce'));
    }

    public static function verify_appointment_token(array $appointment, string $purpose, string $token): bool {
        $expected = self::appointment_token($appointment, $purpose);

        return '' !== $token && hash_equals($expected, sanitize_text_field($token));
    }
}
