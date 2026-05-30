<?php
/**
 * Payment gateway registry and callback orchestration.
 *
 * @package WebtananBooking
 */

namespace Webtanan\Booking;

defined('ABSPATH') || exit;

final class Payment_Gateways {
    public static function available_gateways(): array {
        $gateways = array();
        $aqaye = new AqayePardakht_Gateway();

        if ($aqaye->is_enabled()) {
            $gateways[] = $aqaye->public_config();
        }

        return apply_filters('webtanan_booking_available_gateways', $gateways);
    }

    public static function get_gateway(string $gateway_id) {
        $gateway_id = sanitize_key($gateway_id);

        if (AqayePardakht_Gateway::ID === $gateway_id || '' === $gateway_id) {
            return new AqayePardakht_Gateway();
        }

        return apply_filters('webtanan_booking_gateway_adapter', null, $gateway_id);
    }

    public static function initiate_payment(int $appointment_id, string $lock_token, string $gateway_id = '') {
        global $wpdb;

        $appointment = Booking::get_appointment($appointment_id);
        if (!$appointment || 'locked' !== $appointment['appointment_status'] || !hash_equals((string) $appointment['lock_token'], $lock_token)) {
            return new \WP_Error('webtanan_invalid_lock', __('قفل نوبت معتبر نیست.', 'webtanan-booking'), array('status' => 409));
        }

        if (!self::appointment_lock_is_valid($appointment)) {
            $wpdb->update(
                DB::table('appointments'),
                array('appointment_status' => 'expired', 'updated_at' => DB::now()),
                array('id' => $appointment_id)
            );

            return new \WP_Error('webtanan_lock_expired', __('زمان قفل نوبت منقضی شده است.', 'webtanan-booking'), array('status' => 409));
        }

        $doctor = Booking::get_doctor((int) $appointment['doctor_id']);
        if (!$doctor || 1 !== (int) $doctor['allow_online_payment']) {
            return new \WP_Error('webtanan_online_payment_disabled', __('پرداخت آنلاین برای این پزشک فعال نیست.', 'webtanan-booking'), array('status' => 403));
        }

        $gateway_id = self::resolve_gateway_id($gateway_id);
        $gateway = self::get_gateway($gateway_id);
        if (!$gateway || !method_exists($gateway, 'is_enabled') || !$gateway->is_enabled()) {
            return new \WP_Error('webtanan_gateway_not_configured', __('درگاه پرداخت انتخاب‌شده فعال نیست.', 'webtanan-booking'), array('status' => 501));
        }

        $now = DB::now();
        $transaction_code = DB::code('TRX');
        $transaction_table = DB::table('transactions');
        $invoice_id = $transaction_code;
        $amount = Booking::appointment_charge_amount($appointment);

        $wpdb->insert(
            $transaction_table,
            array(
                'transaction_code' => $transaction_code,
                'user_id' => (int) $appointment['patient_user_id'],
                'doctor_id' => (int) $appointment['doctor_id'],
                'appointment_id' => $appointment_id,
                'gateway_name' => sanitize_key($gateway->id()),
                'invoice_id' => $invoice_id,
                'amount' => $amount,
                'status' => 'initiated',
                'request_payload' => wp_json_encode(array('appointment_id' => $appointment_id, 'gateway' => $gateway->id()), JSON_UNESCAPED_UNICODE),
                'created_at' => $now,
                'updated_at' => $now,
            )
        );

        $transaction_id = (int) $wpdb->insert_id;
        if ($transaction_id <= 0) {
            return new \WP_Error('webtanan_transaction_create_failed', __('ایجاد تراکنش پرداخت انجام نشد.', 'webtanan-booking'), array('status' => 500));
        }

        $wpdb->update(
            DB::table('appointments'),
            array(
                'transaction_id' => $transaction_id,
                'payment_method' => 'online',
                'updated_at' => $now,
            ),
            array('id' => $appointment_id)
        );

        $create = $gateway->create_payment(
            array(
                'amount' => $amount,
                'invoice_id' => $invoice_id,
                'mobile' => $appointment['patient_mobile'],
                'description' => self::payment_description($gateway, $appointment, $doctor, $transaction_code),
                'appointment' => $appointment,
                'doctor' => $doctor,
                'transaction_id' => $transaction_id,
            )
        );

        if (is_wp_error($create)) {
            $data = $create->get_error_data();
            $data = is_array($data) ? $data : array();
            $wpdb->update(
                $transaction_table,
                array(
                    'status' => 'create_failed',
                    'request_payload' => !empty($data['payload']) ? wp_json_encode(self::redact_payload($data['payload']), JSON_UNESCAPED_UNICODE) : null,
                    'create_response' => !empty($data['response']) ? wp_json_encode($data['response'], JSON_UNESCAPED_UNICODE) : null,
                    'error_code' => sanitize_text_field((string) ($data['error_code'] ?? $create->get_error_code())),
                    'error_message' => sanitize_textarea_field($create->get_error_message()),
                    'updated_at' => DB::now(),
                ),
                array('id' => $transaction_id)
            );

            return new \WP_Error(
                $create->get_error_code(),
                $create->get_error_message(),
                array('status' => $data['status'] ?? 502, 'transaction_id' => $transaction_id)
            );
        }

        $wpdb->update(
            $transaction_table,
            array(
                'status' => 'redirected',
                'gateway_transid' => sanitize_text_field((string) $create['transid']),
                'gateway_authority' => sanitize_text_field((string) $create['transid']),
                'request_payload' => wp_json_encode(self::redact_payload($create['payload']), JSON_UNESCAPED_UNICODE),
                'create_response' => wp_json_encode($create['response'], JSON_UNESCAPED_UNICODE),
                'updated_at' => DB::now(),
            ),
            array('id' => $transaction_id)
        );

        return array(
            'transaction_id' => $transaction_id,
            'transaction_code' => $transaction_code,
            'gateway' => $gateway->public_config(),
            'checkout_url' => esc_url_raw((string) $create['payment_url']),
            'payment_url' => esc_url_raw((string) $create['payment_url']),
        );
    }

    public static function handle_aqayepardakht_callback(\WP_REST_Request $request) {
        global $wpdb;

        $params = self::sanitize_callback_params($request->get_params());
        $transid = $params['transid'];
        $invoice_id = $params['invoice_id'];

        if ('' === $transid && '' === $invoice_id) {
            return new \WP_Error('webtanan_aqayepardakht_callback_missing_id', __('شناسه‌های تراکنش در callback پرداخت وجود ندارد.', 'webtanan-booking'), array('status' => 400));
        }

        DB::start_transaction();
        $transaction = self::find_aqaye_transaction($transid, $invoice_id, true);
        if (!$transaction) {
            DB::rollback();

            return new \WP_Error('webtanan_aqayepardakht_transaction_not_found', __('تراکنش پرداخت پیدا نشد.', 'webtanan-booking'), array('status' => 404));
        }

        if (self::is_final_transaction_status((string) $transaction['status'])) {
            DB::commit();

            return rest_ensure_response(self::callback_result($transaction, 'already_processed'));
        }

        if ('verifying' === $transaction['status'] && self::is_recently_verifying($transaction)) {
            DB::commit();

            return rest_ensure_response(self::callback_result($transaction, 'verification_in_progress'));
        }

        $callback_status = $params['status'];
        $transaction_table = DB::table('transactions');
        $update = array(
            'callback_payload' => wp_json_encode($params, JSON_UNESCAPED_UNICODE),
            'callback_status' => $callback_status,
            'gateway_ref_id' => $params['tracking_number'],
            'gateway_tracking_number' => $params['tracking_number'],
            'gateway_card_number' => $params['cardnumber'],
            'gateway_bank' => $params['bank'],
            'status' => '1' === $callback_status ? 'verifying' : 'callback_failed',
            'updated_at' => DB::now(),
        );

        if ('' !== $transid) {
            $update['gateway_transid'] = $transid;
            $update['gateway_authority'] = $transid;
        }

        $wpdb->update($transaction_table, $update, array('id' => (int) $transaction['id']));
        DB::commit();

        if ('1' !== $callback_status) {
            self::mark_failed_payment_lock((int) $transaction['appointment_id'], (int) $transaction['id'], $params['tracking_number']);

            return rest_ensure_response(
                array(
                    'success' => false,
                    'status' => 'callback_failed',
                    'transaction_id' => (int) $transaction['id'],
                    'appointment_id' => (int) $transaction['appointment_id'],
                )
            );
        }

        $gateway = new AqayePardakht_Gateway();
        $verify = $gateway->verify_payment($transid ?: (string) $transaction['gateway_transid'], (float) $transaction['amount']);

        if (is_wp_error($verify)) {
            $data = $verify->get_error_data();
            $data = is_array($data) ? $data : array();
            $wpdb->update(
                $transaction_table,
                array(
                    'status' => 'verify_failed',
                    'verify_payload' => !empty($data['payload']) ? wp_json_encode(self::redact_payload($data['payload']), JSON_UNESCAPED_UNICODE) : null,
                    'error_code' => $verify->get_error_code(),
                    'error_message' => sanitize_textarea_field($verify->get_error_message()),
                    'updated_at' => DB::now(),
                ),
                array('id' => (int) $transaction['id'])
            );

            return $verify;
        }

        $wpdb->update(
            $transaction_table,
            array(
                'verify_payload' => wp_json_encode(self::redact_payload($verify['payload']), JSON_UNESCAPED_UNICODE),
                'verify_response' => wp_json_encode($verify['response'], JSON_UNESCAPED_UNICODE),
                'error_code' => $verify['success'] ? '' : sanitize_text_field((string) ($verify['code'] ?? '')),
                'error_message' => $verify['success'] ? '' : sanitize_textarea_field((string) ($verify['message'] ?? '')),
                'status' => $verify['success'] ? 'verifying' : 'verify_failed',
                'verified_at' => $verify['success'] ? DB::now() : null,
                'updated_at' => DB::now(),
            ),
            array('id' => (int) $transaction['id'])
        );

        if (empty($verify['success'])) {
            self::mark_failed_payment_lock((int) $transaction['appointment_id'], (int) $transaction['id'], $params['tracking_number']);

            return rest_ensure_response(
                array(
                    'success' => false,
                    'status' => 'verify_failed',
                    'transaction_id' => (int) $transaction['id'],
                    'appointment_id' => (int) $transaction['appointment_id'],
                    'message' => $verify['message'] ?? '',
                )
            );
        }

        $confirmed = Booking::confirm_appointment_after_payment((int) $transaction['appointment_id'], (int) $transaction['id']);
        if (is_wp_error($confirmed)) {
            $wpdb->update(
                $transaction_table,
                array(
                    'status' => 'confirmation_failed',
                    'error_code' => $confirmed->get_error_code(),
                    'error_message' => sanitize_textarea_field($confirmed->get_error_message()),
                    'updated_at' => DB::now(),
                ),
                array('id' => (int) $transaction['id'])
            );

            return $confirmed;
        }

        if (isset($confirmed['status']) && 'refunded_to_wallet' === $confirmed['status']) {
            $wpdb->update(
                $transaction_table,
                array('status' => 'expired_lock_wallet_charged', 'updated_at' => DB::now()),
                array('id' => (int) $transaction['id'])
            );
        }

        return rest_ensure_response(
            array(
                'success' => true,
                'status' => $confirmed['status'] ?? 'confirmed',
                'transaction_id' => (int) $transaction['id'],
                'appointment_id' => (int) $transaction['appointment_id'],
                'already_verified' => !empty($verify['already_verified']),
                'suggested_slots' => $confirmed['suggested_slots'] ?? array(),
            )
        );
    }

    private static function resolve_gateway_id(string $gateway_id): string {
        $gateway_id = sanitize_key($gateway_id);
        if ('' !== $gateway_id) {
            return $gateway_id;
        }

        $settings = DB::get_settings();
        $active = sanitize_key((string) ($settings['gateway_settings']['active_gateway'] ?? ''));

        return $active ?: AqayePardakht_Gateway::ID;
    }

    private static function payment_description($gateway, array $appointment, array $doctor, string $transaction_code): string {
        $settings = method_exists($gateway, 'settings') ? $gateway->settings() : array();
        $template = (string) ($settings['description_template'] ?? 'Appointment payment {appointment_code}');
        $doctor_name = !empty($doctor['post_id']) ? get_the_title((int) $doctor['post_id']) : ($doctor['clinic_name'] ?? '');

        return strtr(
            $template,
            array(
                '{appointment_code}' => (string) $appointment['appointment_code'],
                '{transaction_code}' => $transaction_code,
                '{doctor_name}' => (string) $doctor_name,
                '{patient_name}' => trim((string) $appointment['patient_first_name'] . ' ' . (string) $appointment['patient_last_name']),
                '{amount}' => (string) round(Booking::appointment_charge_amount($appointment)),
            )
        );
    }

    private static function appointment_lock_is_valid(array $appointment): bool {
        return !empty($appointment['locked_until']) && strtotime((string) $appointment['locked_until']) >= current_time('timestamp');
    }

    private static function sanitize_callback_params(array $params): array {
        return array(
            'transid' => sanitize_text_field((string) ($params['transid'] ?? '')),
            'cardnumber' => sanitize_text_field((string) ($params['cardnumber'] ?? '')),
            'tracking_number' => sanitize_text_field((string) ($params['tracking_number'] ?? '')),
            'invoice_id' => sanitize_text_field((string) ($params['invoice_id'] ?? '')),
            'bank' => sanitize_text_field((string) ($params['bank'] ?? '')),
            'status' => sanitize_text_field((string) ($params['status'] ?? '0')),
        );
    }

    private static function find_aqaye_transaction(string $transid, string $invoice_id, bool $for_update = false): ?array {
        global $wpdb;

        $where = array();
        $params = array(AqayePardakht_Gateway::ID);

        if ('' !== $transid) {
            $where[] = 'gateway_transid = %s';
            $params[] = $transid;
            $where[] = 'gateway_authority = %s';
            $params[] = $transid;
        }

        if ('' !== $invoice_id) {
            $where[] = 'invoice_id = %s';
            $params[] = $invoice_id;
            $where[] = 'transaction_code = %s';
            $params[] = $invoice_id;
        }

        if (!$where) {
            return null;
        }

        $sql = 'SELECT * FROM ' . DB::table('transactions') . ' WHERE gateway_name = %s AND (' . implode(' OR ', $where) . ') ORDER BY id DESC LIMIT 1';
        if ($for_update) {
            $sql .= ' FOR UPDATE';
        }

        $row = $wpdb->get_row($wpdb->prepare($sql, $params), ARRAY_A);

        return is_array($row) ? $row : null;
    }

    private static function is_final_transaction_status(string $status): bool {
        return in_array($status, array('verified', 'refunded_to_wallet', 'expired_lock_wallet_charged'), true);
    }

    private static function is_recently_verifying(array $transaction): bool {
        $updated_at = strtotime((string) ($transaction['updated_at'] ?? ''));
        if (!$updated_at) {
            return false;
        }

        return $updated_at >= current_time('timestamp') - (5 * MINUTE_IN_SECONDS);
    }

    private static function redact_payload(array $payload): array {
        foreach ($payload as $key => $value) {
            $normalized_key = strtolower((string) $key);
            if (in_array($normalized_key, array('pin', 'api_key', 'password', 'token', 'secret'), true)) {
                $payload[$key] = '***redacted***';
            } elseif (is_array($value)) {
                $payload[$key] = self::redact_payload($value);
            }
        }

        return $payload;
    }

    private static function callback_result(array $transaction, string $reason): array {
        return array(
            'success' => true,
            'status' => (string) $transaction['status'],
            'reason' => $reason,
            'transaction_id' => (int) $transaction['id'],
            'appointment_id' => (int) $transaction['appointment_id'],
        );
    }

    private static function mark_failed_payment_lock(int $appointment_id, int $transaction_id, string $tracking_number = ''): void {
        global $wpdb;

        $appointment = Booking::get_appointment($appointment_id);
        if (!$appointment) {
            return;
        }

        if ('locked' === $appointment['appointment_status'] && (int) $appointment['transaction_id'] === $transaction_id) {
            $wpdb->update(
                DB::table('appointments'),
                array(
                    'payment_status' => 'failed',
                    'appointment_status' => 'expired',
                    'updated_at' => DB::now(),
                ),
                array('id' => $appointment_id)
            );
        }

        SMS::send_pattern(
            (string) $appointment['patient_mobile'],
            'payment_failed',
            array(
                'appointment_code' => (string) $appointment['appointment_code'],
                'tracking_code' => $tracking_number,
            ),
            $appointment_id
        );
    }
}
