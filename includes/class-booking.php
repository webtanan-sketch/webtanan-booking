<?php
/**
 * Appointment booking domain logic.
 *
 * @package WebtananBooking
 */

namespace Webtanan\Booking;

defined('ABSPATH') || exit;

final class Booking {
    public static function get_doctor(int $doctor_id): ?array {
        global $wpdb;

        $doctor = $wpdb->get_row(
            $wpdb->prepare('SELECT * FROM ' . DB::table('doctors') . ' WHERE id = %d', $doctor_id),
            ARRAY_A
        );

        return is_array($doctor) ? $doctor : null;
    }

    public static function get_slots(int $doctor_id, string $date): array {
        global $wpdb;

        if (!self::is_valid_date($date)) {
            return array();
        }

        $doctor = self::get_doctor($doctor_id);
        if (!$doctor || 1 !== (int) $doctor['is_active'] || 1 !== (int) $doctor['is_verified']) {
            return array();
        }

        $segments = self::schedule_segments($doctor_id, $date);
        if (!$segments) {
            return array();
        }

        $appointments = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT * FROM ' . DB::table('appointments') . ' WHERE doctor_id = %d AND appointment_date = %s',
                $doctor_id,
                $date
            ),
            ARRAY_A
        );

        $appointment_map = array();
        foreach ($appointments as $appointment) {
            $appointment_map[self::normalize_time($appointment['start_time'])] = $appointment;
        }

        $now = current_time('timestamp');
        $slots = array();

        foreach ($segments as $segment) {
            $duration = max(1, (int) $segment['slot_duration']);
            $start = strtotime($date . ' ' . self::normalize_time($segment['start_time']));
            $end = strtotime($date . ' ' . self::normalize_time($segment['end_time']));

            while ($start && $end && $start + ($duration * MINUTE_IN_SECONDS) <= $end) {
                $start_time = date('H:i:s', $start);
                $end_time = date('H:i:s', $start + ($duration * MINUTE_IN_SECONDS));
                $status = 'available';
                $appointment_id = 0;
                $appointment_status = '';
                $payment_status = '';

                if (isset($appointment_map[$start_time])) {
                    $appointment = $appointment_map[$start_time];
                    $appointment_id = (int) $appointment['id'];
                    $status = self::public_slot_status($appointment, $now);
                    $appointment_status = (string) $appointment['appointment_status'];
                    $payment_status = (string) $appointment['payment_status'];
                }

                $slots[] = array(
                    'doctor_id' => $doctor_id,
                    'date' => $date,
                    'start_time' => substr($start_time, 0, 5),
                    'end_time' => substr($end_time, 0, 5),
                    'duration' => $duration,
                    'capacity_per_slot' => (int) $segment['capacity_per_slot'],
                    'status' => $status,
                    'appointment_status' => $appointment_status,
                    'payment_status' => $payment_status,
                    'appointment_id' => $appointment_id,
                );

                $start += $duration * MINUTE_IN_SECONDS;
            }
        }

        return $slots;
    }

    public static function next_available(int $doctor_id, int $limit = 5): array {
        $found = array();
        $date = current_time('Y-m-d');

        for ($i = 0; $i < 45 && count($found) < $limit; $i++) {
            $day = date('Y-m-d', strtotime($date . " +$i days"));
            foreach (self::get_slots($doctor_id, $day) as $slot) {
                if ('available' === $slot['status']) {
                    $found[] = $slot;
                    if (count($found) >= $limit) {
                        break;
                    }
                }
            }
        }

        return $found;
    }

    public static function lock_appointment(array $args) {
        global $wpdb;

        $doctor_id = absint($args['doctor_id'] ?? 0);
        $date = sanitize_text_field($args['appointment_date'] ?? '');
        $start_time = self::normalize_time((string) ($args['start_time'] ?? ''));
        $patient_user_id = absint($args['patient_user_id'] ?? get_current_user_id());
        $doctor = self::get_doctor($doctor_id);

        if (!$doctor || 1 !== (int) $doctor['is_active'] || 1 !== (int) $doctor['is_verified']) {
            return new \WP_Error('webtanan_doctor_unavailable', __('این پزشک برای نوبت‌دهی فعال نیست.', 'webtanan-booking'), array('status' => 404));
        }

        if (!self::is_valid_date($date) || !self::is_valid_time($start_time)) {
            return new \WP_Error('webtanan_invalid_slot', __('تاریخ یا ساعت نوبت معتبر نیست.', 'webtanan-booking'), array('status' => 400));
        }

        if (!self::slot_exists_in_schedule($doctor_id, $date, $start_time)) {
            return new \WP_Error('webtanan_slot_not_in_schedule', __('زمان انتخاب‌شده خارج از برنامه کاری پزشک است.', 'webtanan-booking'), array('status' => 400));
        }

        $settings = DB::get_settings();
        $duration = self::slot_duration($doctor_id, $date, $start_time);
        $end_time = date('H:i:s', strtotime($date . ' ' . $start_time) + ($duration * MINUTE_IN_SECONDS));
        $lock_token = wp_generate_uuid4();
        $locked_until = date('Y-m-d H:i:s', current_time('timestamp') + ((int) $settings['lock_duration_minutes'] * MINUTE_IN_SECONDS));
        $table = DB::table('appointments');
        $now = DB::now();

        DB::start_transaction();

        $existing = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $table WHERE doctor_id = %d AND appointment_date = %s AND start_time = %s FOR UPDATE",
                $doctor_id,
                $date,
                $start_time
            ),
            ARRAY_A
        );

        if ($existing && self::is_blocking_appointment($existing)) {
            DB::rollback();

            return new \WP_Error('webtanan_slot_taken', __('این زمان قبلاً رزرو یا قفل شده است.', 'webtanan-booking'), array('status' => 409));
        }

        $data = array(
            'doctor_id' => $doctor_id,
            'patient_user_id' => $patient_user_id,
            'patient_first_name' => sanitize_text_field($args['patient_first_name'] ?? ''),
            'patient_last_name' => sanitize_text_field($args['patient_last_name'] ?? ''),
            'patient_national_code' => sanitize_text_field($args['patient_national_code'] ?? ''),
            'patient_mobile' => OTP::normalize_mobile((string) ($args['patient_mobile'] ?? '')),
            'appointment_date' => $date,
            'start_time' => $start_time,
            'end_time' => $end_time,
            'visit_price' => (float) $doctor['visit_price'],
            'display_visit_price' => (float) $doctor['visit_price'],
            'booking_fee' => self::doctor_booking_fee($doctor),
            'payment_method' => sanitize_key($args['payment_method'] ?? 'online'),
            'payment_status' => 'unpaid',
            'appointment_status' => 'locked',
            'locked_until' => $locked_until,
            'lock_token' => $lock_token,
            'transaction_id' => 0,
            'cancelled_by' => '',
            'cancel_reason' => '',
            'confirmed_at' => null,
            'cancelled_at' => null,
            'updated_at' => $now,
        );

        if ($existing) {
            $data['appointment_code'] = DB::code('APT');
            $updated = $wpdb->update($table, $data, array('id' => (int) $existing['id']));
            $appointment_id = (int) $existing['id'];
            if (false === $updated) {
                DB::rollback();

                return new \WP_Error('webtanan_lock_failed', __('قفل کردن زمان نوبت انجام نشد.', 'webtanan-booking'), array('status' => 500));
            }
        } else {
            $data['appointment_code'] = DB::code('APT');
            $data['created_at'] = $now;
            $inserted = $wpdb->insert($table, $data);
            if (!$inserted) {
                DB::rollback();

                return new \WP_Error('webtanan_lock_collision', __('کاربر دیگری زودتر این زمان را انتخاب کرده است.', 'webtanan-booking'), array('status' => 409));
            }
            $appointment_id = (int) $wpdb->insert_id;
        }

        DB::commit();

        return array(
            'appointment_id' => $appointment_id,
            'appointment_code' => (string) $data['appointment_code'],
            'lock_token' => $lock_token,
            'locked_until' => $locked_until,
            'amount' => self::doctor_booking_fee($doctor),
            'booking_fee' => self::doctor_booking_fee($doctor),
            'visit_price' => (float) $doctor['visit_price'],
            'payment_method' => $data['payment_method'],
        );
    }

    public static function initiate_payment(int $appointment_id, string $lock_token, string $gateway_id = '') {
        return Payment_Gateways::initiate_payment($appointment_id, $lock_token, $gateway_id);
    }

    public static function renew_lock_for_resume(int $appointment_id, string $mobile) {
        global $wpdb;

        $appointment_id = absint($appointment_id);
        $mobile = OTP::normalize_mobile($mobile);
        if ($appointment_id <= 0 || '' === $mobile) {
            return new \WP_Error('webtanan_resume_invalid_input', __('اطلاعات ادامه پرداخت معتبر نیست.', 'webtanan-booking'), array('status' => 400));
        }

        $appointments = DB::table('appointments');
        $settings = DB::get_settings();
        $now = DB::now();

        DB::start_transaction();
        $appointment = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $appointments WHERE id = %d FOR UPDATE", $appointment_id),
            ARRAY_A
        );

        if (!$appointment || !hash_equals(OTP::normalize_mobile((string) $appointment['patient_mobile']), $mobile)) {
            DB::rollback();

            return new \WP_Error('webtanan_resume_appointment_not_found', __('نوبتی با این کد و شماره موبایل پیدا نشد.', 'webtanan-booking'), array('status' => 404));
        }

        if ('confirmed' === (string) $appointment['appointment_status']) {
            DB::commit();

            return array(
                'status' => 'already_confirmed',
                'appointment_id' => $appointment_id,
                'appointment_code' => (string) $appointment['appointment_code'],
            );
        }

        if (in_array((string) $appointment['appointment_status'], array('cancelled', 'completed', 'no_show'), true)) {
            DB::rollback();

            return new \WP_Error('webtanan_resume_not_payable', __('این نوبت دیگر قابل پرداخت نیست.', 'webtanan-booking'), array('status' => 409));
        }

        if (!self::slot_exists_in_schedule((int) $appointment['doctor_id'], (string) $appointment['appointment_date'], (string) $appointment['start_time'])) {
            DB::rollback();

            return new \WP_Error(
                'webtanan_resume_slot_unavailable',
                __('این ساعت دیگر در برنامه پزشک فعال نیست.', 'webtanan-booking'),
                array('status' => 409, 'suggested_slots' => self::next_available((int) $appointment['doctor_id'], 5))
            );
        }

        $slot_row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $appointments WHERE doctor_id = %d AND appointment_date = %s AND start_time = %s FOR UPDATE",
                (int) $appointment['doctor_id'],
                (string) $appointment['appointment_date'],
                self::normalize_time((string) $appointment['start_time'])
            ),
            ARRAY_A
        );

        if ($slot_row && (int) $slot_row['id'] !== $appointment_id && self::is_blocking_appointment($slot_row)) {
            DB::rollback();

            return new \WP_Error(
                'webtanan_resume_slot_taken',
                __('این ساعت در این فاصله پر شده است. می‌توانید یک نوبت جدید بگیرید.', 'webtanan-booking'),
                array('status' => 409, 'suggested_slots' => self::next_available((int) $appointment['doctor_id'], 5))
            );
        }

        if ('locked' === (string) $appointment['appointment_status'] && self::lock_is_valid($appointment)) {
            if (0 === (int) $appointment['patient_user_id'] && get_current_user_id() > 0) {
                $wpdb->update(
                    $appointments,
                    array('patient_user_id' => get_current_user_id(), 'updated_at' => $now),
                    array('id' => $appointment_id)
                );
            }
            DB::commit();

            return array(
                'status' => 'locked',
                'appointment_id' => $appointment_id,
                'appointment_code' => (string) $appointment['appointment_code'],
                'lock_token' => (string) $appointment['lock_token'],
                'locked_until' => (string) $appointment['locked_until'],
                'amount' => self::appointment_charge_amount($appointment),
                'booking_fee' => self::appointment_charge_amount($appointment),
                'visit_price' => (float) $appointment['visit_price'],
            );
        }

        $lock_token = wp_generate_uuid4();
        $locked_until = date('Y-m-d H:i:s', current_time('timestamp') + ((int) $settings['lock_duration_minutes'] * MINUTE_IN_SECONDS));
        $wpdb->update(
            $appointments,
            array(
                'appointment_status' => 'locked',
                'payment_status' => 'unpaid',
                'payment_method' => 'online',
                'patient_user_id' => 0 === (int) $appointment['patient_user_id'] && get_current_user_id() > 0 ? get_current_user_id() : (int) $appointment['patient_user_id'],
                'locked_until' => $locked_until,
                'lock_token' => $lock_token,
                'transaction_id' => 0,
                'updated_at' => $now,
            ),
            array('id' => $appointment_id)
        );

        DB::commit();

        return array(
            'status' => 'relocked',
            'appointment_id' => $appointment_id,
            'appointment_code' => (string) $appointment['appointment_code'],
            'lock_token' => $lock_token,
            'locked_until' => $locked_until,
            'amount' => self::appointment_charge_amount($appointment),
            'booking_fee' => self::appointment_charge_amount($appointment),
            'visit_price' => (float) $appointment['visit_price'],
        );
    }

    public static function confirm_appointment_after_payment(int $appointment_id, int $transaction_id, string $lock_token = '') {
        global $wpdb;

        $appointments = DB::table('appointments');
        $transactions = DB::table('transactions');
        $now = DB::now();

        DB::start_transaction();

        $appointment = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $appointments WHERE id = %d FOR UPDATE", $appointment_id),
            ARRAY_A
        );

        if (!$appointment) {
            DB::rollback();

            return new \WP_Error('webtanan_appointment_not_found', __('نوبت پیدا نشد.', 'webtanan-booking'), array('status' => 404));
        }

        if (($lock_token && !hash_equals((string) $appointment['lock_token'], $lock_token)) || ($transaction_id && (int) $appointment['transaction_id'] !== $transaction_id && 'confirmed' !== $appointment['appointment_status'])) {
            $refund = self::refund_transaction_to_wallet($transaction_id, __('پرداخت پس از رزرو شدن دوباره این زمان برگشت خورد.', 'webtanan-booking'));
            DB::commit();

            return $refund;
        }

        if ('confirmed' === $appointment['appointment_status']) {
            DB::commit();

            return array('status' => 'confirmed', 'appointment_id' => $appointment_id);
        }

        if (!self::lock_is_valid($appointment)) {
            $wpdb->update(
                $appointments,
                array(
                    'payment_status' => 'refunded_to_wallet',
                    'appointment_status' => 'expired',
                    'transaction_id' => $transaction_id,
                    'updated_at' => $now,
                ),
                array('id' => $appointment_id)
            );
            $wpdb->update(
                $transactions,
                array('status' => 'expired_lock_wallet_charged', 'verified_at' => $now, 'updated_at' => $now),
                array('id' => $transaction_id)
            );

            if ((int) $appointment['patient_user_id'] > 0) {
                Wallet::add_entry(
                    array(
                        'user_id' => (int) $appointment['patient_user_id'],
                        'user_type' => 'patient',
                        'related_appointment_id' => $appointment_id,
                        'related_transaction_id' => $transaction_id,
                        'entry_type' => 'refund',
                        'amount' => self::appointment_charge_amount($appointment),
                        'description' => __('برگشت دیرهنگام پرداخت: مبلغ به کیف پول بیمار منتقل شد.', 'webtanan-booking'),
                    )
                );
            }

            SMS::send_pattern(
                $appointment['patient_mobile'],
                'late_payment_wallet_charged',
                array('amount' => (string) self::appointment_charge_amount($appointment)),
                $appointment_id
            );

            DB::commit();

            return array(
                'status' => 'refunded_to_wallet',
                'appointment_id' => $appointment_id,
                'suggested_slots' => self::next_available((int) $appointment['doctor_id'], 5),
            );
        }

        $wpdb->update(
            $appointments,
            array(
                'payment_status' => 'paid',
                'appointment_status' => 'confirmed',
                'transaction_id' => $transaction_id,
                'confirmed_at' => $now,
                'updated_at' => $now,
            ),
            array('id' => $appointment_id)
        );
        $wpdb->update(
            $transactions,
            array('status' => 'verified', 'verified_at' => $now, 'updated_at' => $now),
            array('id' => $transaction_id)
        );

        self::record_revenue_split($appointment, $transaction_id);
        DB::commit();

        SMS::send_pattern($appointment['patient_mobile'], 'appointment_confirmed', array('code' => $appointment['appointment_code']), $appointment_id);
        SMS::send_staff_appointment_notification($appointment_id, 'staff_appointment_confirmed');

        return array('status' => 'confirmed', 'appointment_id' => $appointment_id);
    }

    public static function confirm_wallet_payment(int $appointment_id, string $lock_token) {
        global $wpdb;

        $appointments = DB::table('appointments');
        $now = DB::now();

        DB::start_transaction();

        $appointment = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $appointments WHERE id = %d FOR UPDATE", $appointment_id),
            ARRAY_A
        );

        if (!$appointment || 'locked' !== $appointment['appointment_status'] || !hash_equals((string) $appointment['lock_token'], $lock_token)) {
            DB::rollback();

            return new \WP_Error('webtanan_invalid_lock', __('قفل نوبت معتبر نیست.', 'webtanan-booking'), array('status' => 409));
        }

        if (!self::lock_is_valid($appointment)) {
            $wpdb->update($appointments, array('appointment_status' => 'expired', 'updated_at' => $now), array('id' => $appointment_id));
            DB::commit();

            return new \WP_Error('webtanan_lock_expired', __('زمان قفل نوبت منقضی شده است.', 'webtanan-booking'), array('status' => 409));
        }

        $patient_id = (int) $appointment['patient_user_id'];
        $amount = self::appointment_charge_amount($appointment);
        if ($patient_id <= 0 || Wallet::balance($patient_id, 'patient') < $amount) {
            DB::rollback();

            return new \WP_Error('webtanan_wallet_insufficient', __('موجودی کیف پول کافی نیست.', 'webtanan-booking'), array('status' => 402));
        }

        Wallet::add_entry(
            array(
                'user_id' => $patient_id,
                'user_type' => 'patient',
                'related_appointment_id' => $appointment_id,
                'entry_type' => 'wallet_payment',
                'amount' => $amount,
                'description' => __('پرداخت نوبت از کیف پول بیمار.', 'webtanan-booking'),
            )
        );

        $wpdb->update(
            $appointments,
            array(
                'payment_method' => 'wallet',
                'payment_status' => 'wallet_paid',
                'appointment_status' => 'confirmed',
                'confirmed_at' => $now,
                'updated_at' => $now,
            ),
            array('id' => $appointment_id)
        );

        self::record_revenue_split($appointment, 0);
        DB::commit();

        SMS::send_pattern($appointment['patient_mobile'], 'appointment_confirmed', array('code' => $appointment['appointment_code']), $appointment_id);
        SMS::send_staff_appointment_notification($appointment_id, 'staff_appointment_confirmed');

        return array('status' => 'confirmed', 'appointment_id' => $appointment_id);
    }

    public static function confirm_pay_at_clinic(int $appointment_id, string $lock_token) {
        global $wpdb;

        $appointments = DB::table('appointments');
        $now = DB::now();

        DB::start_transaction();

        $appointment = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $appointments WHERE id = %d FOR UPDATE", $appointment_id),
            ARRAY_A
        );

        if (!$appointment || 'locked' !== $appointment['appointment_status'] || !hash_equals((string) $appointment['lock_token'], $lock_token)) {
            DB::rollback();

            return new \WP_Error('webtanan_invalid_lock', __('قفل نوبت معتبر نیست.', 'webtanan-booking'), array('status' => 409));
        }

        if (!self::lock_is_valid($appointment)) {
            $wpdb->update($appointments, array('appointment_status' => 'expired', 'updated_at' => $now), array('id' => $appointment_id));
            DB::commit();

            return new \WP_Error('webtanan_lock_expired', __('زمان قفل نوبت منقضی شده است.', 'webtanan-booking'), array('status' => 409));
        }

        $doctor = self::get_doctor((int) $appointment['doctor_id']);
        if (!$doctor || 1 !== (int) $doctor['allow_pay_at_clinic']) {
            DB::rollback();

            return new \WP_Error('webtanan_pay_at_clinic_disabled', __('پرداخت در مطب برای این پزشک فعال نیست.', 'webtanan-booking'), array('status' => 403));
        }

        $wpdb->update(
            $appointments,
            array(
                'payment_method' => 'pay_at_clinic',
                'payment_status' => 'unpaid',
                'appointment_status' => 'pay_at_clinic',
                'confirmed_at' => $now,
                'updated_at' => $now,
            ),
            array('id' => $appointment_id)
        );

        self::record_pay_at_clinic_commission_debt($appointment);
        DB::commit();

        SMS::send_pattern($appointment['patient_mobile'], 'appointment_confirmed', array('code' => $appointment['appointment_code']), $appointment_id);
        SMS::send_staff_appointment_notification($appointment_id, 'staff_appointment_confirmed');

        return array('status' => 'pay_at_clinic', 'appointment_id' => $appointment_id);
    }

    public static function create_staff_appointment(array $args) {
        global $wpdb;

        $doctor_id = absint($args['doctor_id'] ?? 0);
        $date = sanitize_text_field($args['appointment_date'] ?? '');
        $start_time = self::normalize_time((string) ($args['start_time'] ?? ''));
        $doctor = self::get_doctor($doctor_id);

        if (!$doctor || 1 !== (int) $doctor['is_active'] || 1 !== (int) $doctor['is_verified']) {
            return new \WP_Error('webtanan_doctor_unavailable', __('این پزشک برای نوبت‌دهی فعال نیست.', 'webtanan-booking'), array('status' => 404));
        }

        if (!self::is_valid_date($date) || !self::is_valid_time($start_time)) {
            return new \WP_Error('webtanan_invalid_slot', __('تاریخ یا ساعت نوبت معتبر نیست.', 'webtanan-booking'), array('status' => 400));
        }

        if (!self::slot_exists_in_schedule($doctor_id, $date, $start_time)) {
            return new \WP_Error('webtanan_slot_not_in_schedule', __('زمان انتخاب‌شده خارج از برنامه کاری پزشک است.', 'webtanan-booking'), array('status' => 400));
        }

        $payment_status = sanitize_key((string) ($args['payment_status'] ?? 'cash_at_clinic'));
        if (!in_array($payment_status, array('unpaid', 'cash_at_clinic', 'pos_at_clinic'), true)) {
            $payment_status = 'cash_at_clinic';
        }

        $duration = self::slot_duration($doctor_id, $date, $start_time);
        $end_time = date('H:i:s', strtotime($date . ' ' . $start_time) + ($duration * MINUTE_IN_SECONDS));
        $appointment_status = 'unpaid' === $payment_status ? 'pay_at_clinic' : 'confirmed';
        $payment_method = 'unpaid' === $payment_status ? 'pay_at_clinic' : $payment_status;
        $table = DB::table('appointments');
        $now = DB::now();

        DB::start_transaction();

        $existing = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $table WHERE doctor_id = %d AND appointment_date = %s AND start_time = %s FOR UPDATE",
                $doctor_id,
                $date,
                $start_time
            ),
            ARRAY_A
        );

        if ($existing && self::is_blocking_appointment($existing)) {
            DB::rollback();

            return new \WP_Error('webtanan_slot_taken', __('این زمان قبلاً رزرو یا قفل شده است.', 'webtanan-booking'), array('status' => 409));
        }

        $data = array(
            'appointment_code' => DB::code('APT'),
            'doctor_id' => $doctor_id,
            'patient_user_id' => absint($args['patient_user_id'] ?? 0),
            'patient_first_name' => sanitize_text_field($args['patient_first_name'] ?? ''),
            'patient_last_name' => sanitize_text_field($args['patient_last_name'] ?? ''),
            'patient_national_code' => sanitize_text_field($args['patient_national_code'] ?? ''),
            'patient_mobile' => OTP::normalize_mobile((string) ($args['patient_mobile'] ?? '')),
            'appointment_date' => $date,
            'start_time' => $start_time,
            'end_time' => $end_time,
            'visit_price' => (float) $doctor['visit_price'],
            'display_visit_price' => (float) $doctor['visit_price'],
            'booking_fee' => self::doctor_booking_fee($doctor),
            'payment_method' => $payment_method,
            'payment_status' => $payment_status,
            'appointment_status' => $appointment_status,
            'locked_until' => null,
            'lock_token' => '',
            'transaction_id' => 0,
            'cancelled_by' => '',
            'cancel_reason' => '',
            'confirmed_at' => $now,
            'cancelled_at' => null,
            'updated_at' => $now,
        );

        if ($existing) {
            $updated = $wpdb->update($table, $data, array('id' => (int) $existing['id']));
            $appointment_id = (int) $existing['id'];
            if (false === $updated) {
                DB::rollback();

                return new \WP_Error('webtanan_staff_appointment_failed', __('ثبت نوبت توسط مطب انجام نشد.', 'webtanan-booking'), array('status' => 500));
            }
        } else {
            $data['created_at'] = $now;
            $inserted = $wpdb->insert($table, $data);
            if (!$inserted) {
                DB::rollback();

                return new \WP_Error('webtanan_staff_appointment_collision', __('کاربر دیگری زودتر این زمان را انتخاب کرده است.', 'webtanan-booking'), array('status' => 409));
            }
            $appointment_id = (int) $wpdb->insert_id;
        }

        $appointment = array_merge($data, array('id' => $appointment_id));
        self::record_pay_at_clinic_commission_debt($appointment);
        DB::commit();

        SMS::send_pattern($appointment['patient_mobile'], 'appointment_confirmed', array('code' => $appointment['appointment_code']), $appointment_id);
        SMS::send_staff_appointment_notification($appointment_id, 'staff_appointment_confirmed');

        return array('appointment_id' => $appointment_id, 'status' => $appointment_status, 'payment_status' => $payment_status);
    }

    public static function update_clinic_payment_status(int $appointment_id, string $payment_status) {
        global $wpdb;

        $payment_status = sanitize_key($payment_status);
        if (!in_array($payment_status, array('unpaid', 'cash_at_clinic', 'pos_at_clinic'), true)) {
            return new \WP_Error('webtanan_invalid_payment_status', __('وضعیت پرداخت معتبر نیست.', 'webtanan-booking'), array('status' => 400));
        }

        $appointments = DB::table('appointments');
        DB::start_transaction();

        $appointment = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $appointments WHERE id = %d FOR UPDATE", $appointment_id),
            ARRAY_A
        );

        if (!$appointment) {
            DB::rollback();

            return new \WP_Error('webtanan_appointment_not_found', __('نوبت پیدا نشد.', 'webtanan-booking'), array('status' => 404));
        }

        $clinic_payment = in_array($appointment['payment_status'], array('unpaid', 'cash_at_clinic', 'pos_at_clinic'), true) || 'pay_at_clinic' === $appointment['appointment_status'];
        if (!$clinic_payment) {
            DB::rollback();

            return new \WP_Error('webtanan_online_payment_immutable', __('وضعیت پرداخت آنلاین از داشبورد مطب قابل تغییر نیست.', 'webtanan-booking'), array('status' => 403));
        }

        $wpdb->update(
            $appointments,
            array(
                'payment_method' => 'unpaid' === $payment_status ? 'pay_at_clinic' : $payment_status,
                'payment_status' => $payment_status,
                'appointment_status' => 'unpaid' === $payment_status ? 'pay_at_clinic' : 'confirmed',
                'updated_at' => DB::now(),
            ),
            array('id' => $appointment_id)
        );

        DB::commit();

        return array('appointment_id' => $appointment_id, 'payment_status' => $payment_status);
    }

    public static function update_attendance_status(int $appointment_id, string $appointment_status) {
        global $wpdb;

        $appointment_status = sanitize_key($appointment_status);
        if (!in_array($appointment_status, array('confirmed', 'completed', 'no_show'), true)) {
            return new \WP_Error('webtanan_invalid_appointment_status', __('وضعیت نوبت معتبر نیست.', 'webtanan-booking'), array('status' => 400));
        }

        $appointments = DB::table('appointments');
        DB::start_transaction();

        $appointment = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $appointments WHERE id = %d FOR UPDATE", $appointment_id),
            ARRAY_A
        );

        if (!$appointment) {
            DB::rollback();

            return new \WP_Error('webtanan_appointment_not_found', __('نوبت پیدا نشد.', 'webtanan-booking'), array('status' => 404));
        }

        if (in_array($appointment['appointment_status'], array('cancelled', 'expired'), true)) {
            DB::rollback();

            return new \WP_Error('webtanan_appointment_status_locked', __('نوبت لغوشده یا منقضی‌شده قابل تغییر نیست.', 'webtanan-booking'), array('status' => 409));
        }

        $wpdb->update(
            $appointments,
            array(
                'appointment_status' => $appointment_status,
                'updated_at' => DB::now(),
            ),
            array('id' => $appointment_id)
        );

        DB::commit();

        return array('appointment_id' => $appointment_id, 'appointment_status' => $appointment_status);
    }

    public static function cancel_appointment(int $appointment_id, string $cancelled_by, string $reason = '') {
        global $wpdb;

        $appointments = DB::table('appointments');
        $now = DB::now();

        DB::start_transaction();

        $appointment = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $appointments WHERE id = %d FOR UPDATE", $appointment_id),
            ARRAY_A
        );

        if (!$appointment) {
            DB::rollback();

            return new \WP_Error('webtanan_appointment_not_found', __('نوبت پیدا نشد.', 'webtanan-booking'), array('status' => 404));
        }

        if ('cancelled' === $appointment['appointment_status']) {
            $existing_refund = self::existing_refund_amount($appointment_id);
            DB::rollback();

            return array(
                'status' => 'cancelled',
                'refund_amount' => $existing_refund,
                'already_cancelled' => true,
            );
        }

        if (in_array($appointment['appointment_status'], array('expired', 'completed'), true)) {
            DB::rollback();

            return new \WP_Error('webtanan_appointment_not_cancellable', __('این نوبت قابل لغو نیست.', 'webtanan-booking'), array('status' => 409));
        }

        $preview = self::cancellation_preview($appointment, $cancelled_by);
        if (empty($preview['can_cancel'])) {
            DB::rollback();

            return new \WP_Error('webtanan_appointment_not_cancellable_by_policy', $preview['message'], array('status' => 409));
        }

        $wpdb->update(
            $appointments,
            array(
                'appointment_status' => 'cancelled',
                'cancelled_by' => sanitize_key($cancelled_by),
                'cancel_reason' => sanitize_textarea_field($reason),
                'cancelled_at' => $now,
                'updated_at' => $now,
            ),
            array('id' => $appointment_id)
        );

        $refund_amount = (float) $preview['refund_amount'];
        $existing_refund = self::existing_refund_amount($appointment_id);
        if ($refund_amount > 0 && (int) $appointment['patient_user_id'] > 0) {
            if ($existing_refund > 0) {
                $refund_amount = $existing_refund;
            } else {
                $ledger = Wallet::add_entry(
                    array(
                        'user_id' => (int) $appointment['patient_user_id'],
                        'user_type' => 'patient',
                        'related_appointment_id' => $appointment_id,
                        'related_transaction_id' => (int) $appointment['transaction_id'],
                        'entry_type' => 'refund',
                        'amount' => $refund_amount,
                        'description' => __('استرداد بابت لغو نوبت.', 'webtanan-booking'),
                    )
                );
                if (is_wp_error($ledger)) {
                    DB::rollback();

                    return $ledger;
                }

                $reversal = self::record_cancellation_reversal($appointment, $refund_amount);
                if (is_wp_error($reversal)) {
                    DB::rollback();

                    return $reversal;
                }
            }
        }

        $clinic_commission_booking = 'pay_at_clinic' === $appointment['appointment_status'] || in_array($appointment['payment_status'], array('cash_at_clinic', 'pos_at_clinic'), true);
        if ($clinic_commission_booking) {
            $debt_reversal = self::record_pay_at_clinic_debt_reversal($appointment);
            if (is_wp_error($debt_reversal)) {
                DB::rollback();

                return $debt_reversal;
            }
        }

        DB::commit();
        SMS::send_pattern($appointment['patient_mobile'], 'appointment_cancelled', array('code' => $appointment['appointment_code']), $appointment_id);
        SMS::send_staff_appointment_notification($appointment_id, 'staff_appointment_cancelled');
        do_action('saas_appointment_cancelled', $appointment_id, $appointment, $refund_amount);

        return array('status' => 'cancelled', 'refund_amount' => $refund_amount, 'policy' => $preview);
    }

    public static function bulk_cancel_appointments(array $appointment_ids, string $cancelled_by, string $reason = ''): array {
        $summary = array(
            'requested' => count($appointment_ids),
            'cancelled' => 0,
            'already_cancelled' => 0,
            'failed' => 0,
            'refund_total' => 0.0,
            'items' => array(),
        );

        foreach (array_values(array_unique(array_filter(array_map('absint', $appointment_ids)))) as $appointment_id) {
            $result = self::cancel_appointment($appointment_id, $cancelled_by, $reason);
            if (is_wp_error($result)) {
                $summary['failed']++;
                $summary['items'][] = array(
                    'appointment_id' => $appointment_id,
                    'status' => 'failed',
                    'message' => $result->get_error_message(),
                );
                continue;
            }

            if (!empty($result['already_cancelled'])) {
                $summary['already_cancelled']++;
            } else {
                $summary['cancelled']++;
            }

            $summary['refund_total'] += (float) ($result['refund_amount'] ?? 0);
            $summary['items'][] = array(
                'appointment_id' => $appointment_id,
                'status' => !empty($result['already_cancelled']) ? 'already_cancelled' : 'cancelled',
                'refund_amount' => (float) ($result['refund_amount'] ?? 0),
            );
        }

        return $summary;
    }

    public static function expire_locks(): int {
        global $wpdb;

        $updated = $wpdb->query(
            $wpdb->prepare(
                'UPDATE ' . DB::table('appointments') . " SET appointment_status = 'expired', updated_at = %s WHERE appointment_status = 'locked' AND locked_until IS NOT NULL AND locked_until < %s",
                DB::now(),
                DB::now()
            )
        );

        return (int) $updated;
    }

    public static function can_current_user_manage_appointment(int $appointment_id): bool {
        $appointment = self::get_appointment($appointment_id);
        if (!$appointment) {
            return false;
        }

        if (current_user_can('webtanan_manage_booking') || current_user_can('manage_options')) {
            return true;
        }

        if ((int) $appointment['patient_user_id'] === get_current_user_id()) {
            return true;
        }

        return self::current_user_can_access_doctor((int) $appointment['doctor_id']);
    }

    public static function current_user_can_access_doctor(int $doctor_id): bool {
        $doctor = self::get_doctor($doctor_id);
        if (!$doctor) {
            return false;
        }

        if (current_user_can('webtanan_manage_booking') || current_user_can('manage_options')) {
            return true;
        }

        $user_id = get_current_user_id();
        if (self::current_user_is_secretary()) {
            return in_array($doctor_id, self::assigned_doctor_ids_for_user($user_id), true);
        }

        if ($user_id > 0 && (int) $doctor['user_id'] === $user_id) {
            return true;
        }

        return false;
    }

    public static function assigned_doctor_ids_for_user(int $user_id): array {
        $assigned = $user_id > 0 ? get_user_meta($user_id, 'webtanan_assigned_doctor_ids', true) : array();
        if (is_string($assigned)) {
            $assigned = array_filter(array_map('absint', explode(',', $assigned)));
        }
        if (!is_array($assigned)) {
            $assigned = array();
        }

        return array_values(array_unique(array_filter(array_map('absint', $assigned))));
    }

    public static function current_user_is_secretary(): bool {
        $user = wp_get_current_user();

        return $user && in_array('webtanan_secretary', (array) $user->roles, true) && !current_user_can('webtanan_manage_booking') && !current_user_can('manage_options');
    }

    public static function get_appointment(int $appointment_id): ?array {
        global $wpdb;

        $appointment = $wpdb->get_row(
            $wpdb->prepare('SELECT * FROM ' . DB::table('appointments') . ' WHERE id = %d', $appointment_id),
            ARRAY_A
        );

        return is_array($appointment) ? $appointment : null;
    }

    public static function appointment_charge_amount(array $appointment): float {
        if (array_key_exists('booking_fee', $appointment)) {
            return max(0.0, (float) $appointment['booking_fee']);
        }

        return max(0.0, (float) ($appointment['visit_price'] ?? 0));
    }

    public static function waiting_list_snapshot(array $appointment): array {
        global $wpdb;

        $doctor_id = (int) ($appointment['doctor_id'] ?? 0);
        $date = (string) ($appointment['appointment_date'] ?? '');
        $start_time = self::normalize_time((string) ($appointment['start_time'] ?? ''));
        if ($doctor_id <= 0 || !self::is_valid_date($date) || !self::is_valid_time($start_time)) {
            return array(
                'queue_position' => 1,
                'ahead_count' => 0,
                'total_waiting' => 1,
                'estimated_time' => substr($start_time, 0, 5),
                'appointment_status' => (string) ($appointment['appointment_status'] ?? ''),
            );
        }

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, appointment_code, start_time, appointment_status, payment_method
                FROM " . DB::table('appointments') . "
                WHERE doctor_id = %d
                    AND appointment_date = %s
                    AND appointment_status IN ('confirmed','pay_at_clinic')
                    AND start_time <= %s
                ORDER BY start_time ASC, id ASC",
                $doctor_id,
                $date,
                $start_time
            ),
            ARRAY_A
        );

        $position = 1;
        foreach ($rows as $index => $row) {
            if ((int) $row['id'] === (int) ($appointment['id'] ?? 0) || (string) $row['appointment_code'] === (string) ($appointment['appointment_code'] ?? '')) {
                $position = $index + 1;
                break;
            }
        }

        return array(
            'queue_position' => $position,
            'ahead_count' => max(0, $position - 1),
            'total_waiting' => count($rows),
            'estimated_time' => substr($start_time, 0, 5),
            'appointment_status' => (string) ($appointment['appointment_status'] ?? ''),
        );
    }

    public static function doctor_booking_fee(array $doctor): float {
        if (array_key_exists('booking_fee', $doctor)) {
            return max(0.0, (float) $doctor['booking_fee']);
        }

        return max(0.0, (float) ($doctor['visit_price'] ?? 0));
    }

    public static function booking_fee_recipient(array $doctor): array {
        if (!empty($doctor['secretary_user_id'])) {
            return array(
                'user_id' => (int) $doctor['secretary_user_id'],
                'user_type' => 'secretary',
                'role' => 'secretary',
            );
        }

        return array(
            'user_id' => (int) ($doctor['user_id'] ?? 0),
            'user_type' => 'doctor',
            'role' => 'doctor',
        );
    }

    public static function settlement_summary(int $doctor_id): array {
        global $wpdb;

        $doctor = self::get_doctor($doctor_id);
        if (!$doctor) {
            return array(
                'total_balance' => 0.0,
                'available_balance' => 0.0,
                'pending_settlement' => 0.0,
                'commission_debt' => 0.0,
                'wallet_user_id' => 0,
                'wallet_user_type' => 'doctor',
            );
        }

        $recipient = self::booking_fee_recipient($doctor);
        $user_id = (int) ($recipient['user_id'] ?: ($doctor['user_id'] ?? 0));
        $user_type = (string) ($recipient['user_type'] ?: 'doctor');
        $balance = $user_id > 0 ? Wallet::balance($user_id, $user_type) : 0.0;
        $pending = (float) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COALESCE(SUM(amount), 0) FROM " . DB::table('settlement_requests') . " WHERE doctor_id = %d AND status IN ('pending','approved')",
                $doctor_id
            )
        );

        return array(
            'total_balance' => $balance,
            'available_balance' => max(0.0, $balance - $pending),
            'pending_settlement' => max(0.0, $pending),
            'commission_debt' => max(0.0, -1 * $balance),
            'wallet_user_id' => $user_id,
            'wallet_user_type' => $user_type,
        );
    }

    public static function create_settlement_request(int $doctor_id, float $amount, string $iban = '', string $admin_note = '') {
        global $wpdb;

        $doctor = self::get_doctor($doctor_id);
        if (!$doctor) {
            return new \WP_Error('webtanan_doctor_not_found', __('پزشک پیدا نشد.', 'webtanan-booking'), array('status' => 404));
        }

        $amount = abs($amount);
        $lock_key = self::acquire_settlement_lock($doctor_id);
        if (is_wp_error($lock_key)) {
            return $lock_key;
        }

        $summary = self::settlement_summary($doctor_id);
        if ($amount <= 0 || $amount > (float) $summary['available_balance']) {
            self::release_settlement_lock($lock_key);

            return new \WP_Error('webtanan_invalid_settlement_amount', __('مبلغ تسویه بیشتر از موجودی قابل برداشت است.', 'webtanan-booking'), array('status' => 400));
        }

        $now = DB::now();
        $inserted = $wpdb->insert(
            DB::table('settlement_requests'),
            array(
                'doctor_id' => $doctor_id,
                'amount' => $amount,
                'iban' => sanitize_text_field($iban ?: (string) $doctor['iban']),
                'status' => 'pending',
                'bank_tracking_number' => '',
                'admin_note' => sanitize_textarea_field($admin_note),
                'requested_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            )
        );

        if (!$inserted) {
            self::release_settlement_lock($lock_key);

            return new \WP_Error('webtanan_settlement_insert_failed', __('ثبت درخواست تسویه انجام نشد.', 'webtanan-booking'), array('status' => 500));
        }

        self::release_settlement_lock($lock_key);

        return array(
            'settlement_request_id' => (int) $wpdb->insert_id,
            'status' => 'pending',
            'available_balance' => max(0.0, (float) $summary['available_balance'] - $amount),
        );
    }

    public static function update_settlement_request(int $settlement_id, string $status, string $admin_note = '', string $bank_tracking_number = '') {
        global $wpdb;

        $statuses = array('pending', 'approved', 'rejected', 'paid', 'cancelled');
        $status = sanitize_key($status);
        if (!in_array($status, $statuses, true)) {
            return new \WP_Error('webtanan_invalid_settlement_status', __('وضعیت تسویه نامعتبر است.', 'webtanan-booking'), array('status' => 400));
        }

        $table = DB::table('settlement_requests');
        DB::start_transaction();
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d FOR UPDATE", $settlement_id), ARRAY_A);
        if (!$row) {
            DB::rollback();

            return new \WP_Error('webtanan_settlement_not_found', __('درخواست تسویه پیدا نشد.', 'webtanan-booking'), array('status' => 404));
        }

        $doctor = self::get_doctor((int) $row['doctor_id']);
        if (!$doctor) {
            DB::rollback();

            return new \WP_Error('webtanan_doctor_not_found', __('پزشک پیدا نشد.', 'webtanan-booking'), array('status' => 404));
        }

        if ('paid' === $row['status'] && 'paid' !== $status) {
            DB::rollback();

            return new \WP_Error('webtanan_settlement_paid_locked', __('تسویه پرداخت‌شده قابل تغییر به وضعیت دیگر نیست؛ برای اصلاح مالی باید رکورد دفترکل جداگانه ثبت شود.', 'webtanan-booking'), array('status' => 409));
        }

        $now = DB::now();
        if ('paid' === $status && 'paid' !== $row['status']) {
            if ('' === trim($bank_tracking_number) && empty($row['bank_tracking_number'])) {
                DB::rollback();

                return new \WP_Error('webtanan_settlement_tracking_required', __('برای ثبت تسویه پرداخت‌شده، شماره پیگیری بانکی الزامی است.', 'webtanan-booking'), array('status' => 400));
            }

            $recipient = self::booking_fee_recipient($doctor);
            $user_id = (int) ($recipient['user_id'] ?: ($doctor['user_id'] ?? 0));
            $user_type = (string) ($recipient['user_type'] ?: 'doctor');
            $balance = $user_id > 0 ? Wallet::balance($user_id, $user_type) : 0.0;
            if ($user_id <= 0 || $balance < (float) $row['amount']) {
                DB::rollback();

                return new \WP_Error('webtanan_settlement_balance_low', __('موجودی کیف پول برای پرداخت این تسویه کافی نیست.', 'webtanan-booking'), array('status' => 409));
            }

            $ledger = Wallet::add_entry(
                array(
                    'user_id' => $user_id,
                    'user_type' => $user_type,
                    'entry_type' => 'settlement',
                    'amount' => (float) $row['amount'],
                    'description' => sprintf(
                        /* translators: %d: settlement request id */
                        __('تسویه پرداخت‌شده توسط مدیر، شماره درخواست %d', 'webtanan-booking'),
                        $settlement_id
                    ),
                )
            );
            if (is_wp_error($ledger)) {
                DB::rollback();

                return $ledger;
            }
        }

        $wpdb->update(
            $table,
            array(
                'status' => $status,
                'bank_tracking_number' => sanitize_text_field($bank_tracking_number ?: (string) ($row['bank_tracking_number'] ?? '')),
                'admin_note' => sanitize_textarea_field($admin_note ?: (string) $row['admin_note']),
                'processed_at' => in_array($status, array('paid', 'rejected', 'cancelled'), true) ? $now : $row['processed_at'],
                'updated_at' => $now,
            ),
            array('id' => $settlement_id)
        );

        DB::commit();

        return array(
            'settlement_request_id' => $settlement_id,
            'doctor_id' => (int) $row['doctor_id'],
            'amount' => (float) $row['amount'],
            'old_status' => (string) $row['status'],
            'status' => $status,
        );
    }

    public static function record_revenue_split(array $appointment, int $transaction_id): void {
        $doctor = self::get_doctor((int) $appointment['doctor_id']);
        if (!$doctor) {
            return;
        }

        $amount = self::appointment_charge_amount($appointment);
        if ($amount <= 0) {
            return;
        }

        $recipient = self::booking_fee_recipient($doctor);
        $recipient_share = self::booking_fee_share_amount($doctor, $amount);
        $platform_share = max(0, $amount - $recipient_share);
        $settings = DB::get_settings();

        if ($recipient['user_id'] > 0 && $recipient_share > 0) {
            Wallet::add_entry(
                array(
                    'user_id' => $recipient['user_id'],
                    'user_type' => $recipient['user_type'],
                    'related_appointment_id' => (int) $appointment['id'],
                    'related_transaction_id' => $transaction_id,
                    'entry_type' => 'credit',
                    'amount' => $recipient_share,
                    'description' => __('سهم خدمات نوبت‌دهی از نوبت تاییدشده.', 'webtanan-booking'),
                )
            );
        }

        if ($platform_share > 0) {
            Wallet::add_entry(
                array(
                    'user_id' => (int) $settings['platform_wallet_user_id'],
                    'user_type' => 'platform',
                    'related_appointment_id' => (int) $appointment['id'],
                    'related_transaction_id' => $transaction_id,
                    'entry_type' => 'commission',
                    'amount' => $platform_share,
                    'description' => __('درآمد پلتفرم از خدمات نوبت‌دهی نوبت تاییدشده.', 'webtanan-booking'),
                )
            );
        }
    }

    private static function schedule_segments(int $doctor_id, string $date): array {
        global $wpdb;

        $weekday = strtolower(date('l', strtotime($date)));
        $schedule_table = DB::table('schedules');
        $exception_table = DB::table('schedule_exceptions');

        $base = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM $schedule_table WHERE doctor_id = %d AND weekday = %s AND is_active = 1 ORDER BY start_time ASC", $doctor_id, $weekday),
            ARRAY_A
        );
        $exceptions = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM $exception_table WHERE doctor_id = %d AND exception_date = %s ORDER BY start_time ASC", $doctor_id, $date),
            ARRAY_A
        );

        if (!$exceptions) {
            return $base;
        }

        foreach ($exceptions as $exception) {
            if ('day_off' === $exception['type']) {
                return array();
            }
        }

        $override_types = array('custom_shift', 'reduced_shift');
        $override = array_values(
            array_filter(
                $exceptions,
                static function ($exception) use ($override_types) {
                    return in_array($exception['type'], $override_types, true);
                }
            )
        );

        if ($override) {
            return $override;
        }

        $extra = array_values(
            array_filter(
                $exceptions,
                static function ($exception) {
                    return 'extra_shift' === $exception['type'];
                }
            )
        );

        return array_merge($base, $extra);
    }

    private static function slot_exists_in_schedule(int $doctor_id, string $date, string $start_time): bool {
        foreach (self::get_slots($doctor_id, $date) as $slot) {
            if (self::normalize_time($slot['start_time']) === $start_time) {
                return true;
            }
        }

        return false;
    }

    private static function slot_duration(int $doctor_id, string $date, string $start_time): int {
        foreach (self::get_slots($doctor_id, $date) as $slot) {
            if (self::normalize_time($slot['start_time']) === $start_time) {
                return max(1, (int) $slot['duration']);
            }
        }

        return 15;
    }

    private static function public_slot_status(array $appointment, int $now): string {
        if ('locked' === $appointment['appointment_status'] && !empty($appointment['locked_until']) && strtotime($appointment['locked_until']) > $now) {
            return 'locked';
        }

        if (in_array($appointment['appointment_status'], array('confirmed', 'completed', 'no_show', 'pay_at_clinic'), true)) {
            return 'booked';
        }

        return 'available';
    }

    private static function is_blocking_appointment(array $appointment): bool {
        if (in_array($appointment['appointment_status'], array('confirmed', 'completed', 'no_show', 'pay_at_clinic'), true)) {
            return true;
        }

        return 'locked' === $appointment['appointment_status'] && self::lock_is_valid($appointment);
    }

    private static function lock_is_valid(array $appointment): bool {
        return !empty($appointment['locked_until']) && strtotime($appointment['locked_until']) >= current_time('timestamp');
    }

    public static function cancellation_preview(array $appointment, string $cancelled_by = 'patient'): array {
        $settings = DB::get_settings();
        $policy = $settings['cancellation_policy'] ?? array();
        $appointment_ts = strtotime($appointment['appointment_date'] . ' ' . $appointment['start_time']);
        $hours = ($appointment_ts - current_time('timestamp')) / HOUR_IN_SECONDS;
        $status = (string) ($appointment['appointment_status'] ?? '');
        $can_cancel = !in_array($status, array('cancelled', 'expired', 'completed'), true);
        $message = __('این نوبت قابل لغو است.', 'webtanan-booking');

        if (!$can_cancel) {
            $message = __('این نوبت قبلاً نهایی، منقضی یا تکمیل شده و قابل لغو نیست.', 'webtanan-booking');
        }

        $cancelled_by = sanitize_key($cancelled_by ?: 'patient');
        $minimum_hours = max(0, (int) ($policy['patient_cancel_until_hours'] ?? 0));
        if ($can_cancel && 'patient' === $cancelled_by && $minimum_hours > 0 && $hours < $minimum_hours) {
            $can_cancel = false;
            $message = sprintf(
                /* translators: %d: hours */
                __('لغو بیمار فقط تا %d ساعت قبل از نوبت مجاز است.', 'webtanan-booking'),
                $minimum_hours
            );
        }

        return array(
            'can_cancel' => $can_cancel,
            'refund_amount' => $can_cancel ? self::refund_amount_for_cancel($appointment, $cancelled_by) : 0.0,
            'hours_to_appointment' => round($hours, 2),
            'cancel_deadline_hours' => $minimum_hours,
            'message' => $message,
        );
    }

    private static function refund_amount_for_cancel(array $appointment, string $cancelled_by): float {
        if (!in_array($appointment['payment_status'], array('paid', 'wallet_paid'), true)) {
            return 0.0;
        }

        $amount = self::appointment_charge_amount($appointment);
        $settings = DB::get_settings();
        $policy = $settings['cancellation_policy'] ?? array();
        if (in_array($cancelled_by, array('doctor', 'secretary', 'admin'), true)) {
            return $amount;
        }

        $appointment_ts = strtotime($appointment['appointment_date'] . ' ' . $appointment['start_time']);
        $hours = ($appointment_ts - current_time('timestamp')) / HOUR_IN_SECONDS;

        if ($hours >= (int) ($policy['full_refund_hours'] ?? 24)) {
            return round($amount * ((float) ($policy['full_refund_percent'] ?? 100) / 100), 2);
        }

        if ($hours >= (int) ($policy['partial_refund_hours'] ?? 6)) {
            return round($amount * ((float) ($policy['partial_refund_percent'] ?? 50) / 100), 2);
        }

        return round($amount * ((float) ($policy['late_refund_percent'] ?? 0) / 100), 2);
    }

    private static function existing_refund_amount(int $appointment_id): float {
        global $wpdb;

        return (float) $wpdb->get_var(
            $wpdb->prepare(
                'SELECT COALESCE(SUM(amount), 0) FROM ' . DB::table('wallets_ledger') . ' WHERE related_appointment_id = %d AND user_type = %s AND entry_type = %s',
                $appointment_id,
                'patient',
                'refund'
            )
        );
    }

    private static function acquire_settlement_lock(int $doctor_id) {
        global $wpdb;

        $lock_key = substr('webtanan_settlement_' . $doctor_id, 0, 64);
        $acquired = (int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 5)', $lock_key));
        if (1 !== $acquired) {
            return new \WP_Error('webtanan_settlement_lock_timeout', __('قفل درخواست تسویه انجام نشد. لطفاً دوباره تلاش کنید.', 'webtanan-booking'), array('status' => 409));
        }

        return $lock_key;
    }

    private static function release_settlement_lock(string $lock_key): void {
        global $wpdb;

        if ('' !== $lock_key) {
            $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock_key));
        }
    }

    private static function booking_fee_share_amount(array $doctor, float $amount): float {
        $type = (string) ($doctor['booking_fee_share_type'] ?? 'percent');
        $value = (float) ($doctor['booking_fee_share_value'] ?? 0);

        if ('fixed' === $type) {
            return min($amount, max(0.0, $value));
        }

        return min($amount, max(0.0, round($amount * ($value / 100), 2)));
    }

    private static function commission_amount(array $doctor, float $amount): float {
        $settings = DB::get_settings();
        $type = $doctor['platform_commission_type'] ?: $settings['default_commission_type'];
        $value = (float) $doctor['platform_commission_value'];
        if ($value <= 0) {
            $value = (float) $settings['default_commission_value'];
        }

        if ('fixed' === $type) {
            return min($amount, $value);
        }

        return round($amount * ($value / 100), 2);
    }

    private static function refund_transaction_to_wallet(int $transaction_id, string $description) {
        global $wpdb;

        $transactions = DB::table('transactions');
        $transaction = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $transactions WHERE id = %d FOR UPDATE", $transaction_id),
            ARRAY_A
        );

        if (!$transaction) {
            return new \WP_Error('webtanan_invalid_lock', __('توکن قفل نوبت مطابقت ندارد.', 'webtanan-booking'), array('status' => 409));
        }

        if (in_array($transaction['status'], array('refunded_to_wallet', 'expired_lock_wallet_charged'), true)) {
            return array(
                'status' => 'refunded_to_wallet',
                'transaction_id' => $transaction_id,
                'reason' => 'already_refunded',
            );
        }

        if ((int) $transaction['user_id'] > 0 && (float) $transaction['amount'] > 0) {
            Wallet::add_entry(
                array(
                    'user_id' => (int) $transaction['user_id'],
                    'user_type' => 'patient',
                    'related_appointment_id' => (int) $transaction['appointment_id'],
                    'related_transaction_id' => $transaction_id,
                    'entry_type' => 'refund',
                    'amount' => (float) $transaction['amount'],
                    'description' => $description,
                )
            );
        }

        $wpdb->update(
            $transactions,
            array('status' => 'expired_lock_wallet_charged', 'verified_at' => DB::now(), 'updated_at' => DB::now()),
            array('id' => $transaction_id)
        );

        return array(
            'status' => 'refunded_to_wallet',
            'transaction_id' => $transaction_id,
            'reason' => 'slot_reused_or_lock_mismatch',
        );
    }

    private static function record_pay_at_clinic_commission_debt(array $appointment): void {
        $doctor = self::get_doctor((int) $appointment['doctor_id']);
        if (!$doctor) {
            return;
        }

        $amount = self::appointment_charge_amount($appointment);
        $platform_share = max(0, $amount - self::booking_fee_share_amount($doctor, $amount));
        $recipient = self::booking_fee_recipient($doctor);
        $settings = DB::get_settings();

        if ($platform_share <= 0) {
            return;
        }

        if ($recipient['user_id'] > 0) {
            Wallet::add_entry(
                array(
                    'user_id' => $recipient['user_id'],
                    'user_type' => $recipient['user_type'],
                    'related_appointment_id' => (int) $appointment['id'],
                    'entry_type' => 'debit',
                    'amount' => $platform_share,
                    'description' => __('طلب خدمات نوبت‌دهی پلتفرم بابت نوبت پرداخت در مطب.', 'webtanan-booking'),
                )
            );
        }

        Wallet::add_entry(
            array(
                'user_id' => (int) $settings['platform_wallet_user_id'],
                'user_type' => 'platform',
                'related_appointment_id' => (int) $appointment['id'],
                'entry_type' => 'commission',
                'amount' => $platform_share,
                'description' => __('طلب خدمات نوبت‌دهی پلتفرم بابت نوبت پرداخت در مطب.', 'webtanan-booking'),
            )
        );
    }

    private static function record_pay_at_clinic_debt_reversal(array $appointment) {
        $doctor = self::get_doctor((int) $appointment['doctor_id']);
        if (!$doctor) {
            return true;
        }

        $amount = self::appointment_charge_amount($appointment);
        $platform_share = max(0, $amount - self::booking_fee_share_amount($doctor, $amount));
        $recipient = self::booking_fee_recipient($doctor);
        $settings = DB::get_settings();

        if ($platform_share <= 0) {
            return true;
        }

        if ($recipient['user_id'] > 0) {
            $recipient_entry = Wallet::add_entry(
                array(
                    'user_id' => $recipient['user_id'],
                    'user_type' => $recipient['user_type'],
                    'related_appointment_id' => (int) $appointment['id'],
                    'entry_type' => 'credit',
                    'amount' => $platform_share,
                    'description' => __('برگشت بدهی خدمات نوبت‌دهی پرداخت در مطب پس از لغو.', 'webtanan-booking'),
                )
            );
            if (is_wp_error($recipient_entry)) {
                return $recipient_entry;
            }
        }

        $platform_entry = Wallet::add_entry(
            array(
                'user_id' => (int) $settings['platform_wallet_user_id'],
                'user_type' => 'platform',
                'related_appointment_id' => (int) $appointment['id'],
                'entry_type' => 'debit',
                'amount' => $platform_share,
                'description' => __('برگشت طلب خدمات نوبت‌دهی پرداخت در مطب پس از لغو.', 'webtanan-booking'),
            )
        );

        return is_wp_error($platform_entry) ? $platform_entry : true;
    }

    private static function record_cancellation_reversal(array $appointment, float $refund_amount) {
        $doctor = self::get_doctor((int) $appointment['doctor_id']);
        $amount = self::appointment_charge_amount($appointment);
        if (!$doctor || $amount <= 0) {
            return true;
        }

        $ratio = min(1, $refund_amount / $amount);
        $recipient = self::booking_fee_recipient($doctor);
        $recipient_share = self::booking_fee_share_amount($doctor, $amount) * $ratio;
        $platform_share = max(0, ($amount - self::booking_fee_share_amount($doctor, $amount)) * $ratio);
        $settings = DB::get_settings();

        if ($recipient['user_id'] > 0 && $recipient_share > 0) {
            $recipient_entry = Wallet::add_entry(
                array(
                    'user_id' => $recipient['user_id'],
                    'user_type' => $recipient['user_type'],
                    'related_appointment_id' => (int) $appointment['id'],
                    'related_transaction_id' => (int) $appointment['transaction_id'],
                    'entry_type' => 'debit',
                    'amount' => $recipient_share,
                    'description' => __('برگشت سهم گیرنده خدمات نوبت‌دهی پس از لغو.', 'webtanan-booking'),
                )
            );
            if (is_wp_error($recipient_entry)) {
                return $recipient_entry;
            }
        }

        if ($platform_share > 0) {
            $platform_entry = Wallet::add_entry(
                array(
                    'user_id' => (int) $settings['platform_wallet_user_id'],
                    'user_type' => 'platform',
                    'related_appointment_id' => (int) $appointment['id'],
                    'related_transaction_id' => (int) $appointment['transaction_id'],
                    'entry_type' => 'debit',
                    'amount' => $platform_share,
                    'description' => __('برگشت درآمد خدمات نوبت‌دهی پلتفرم پس از لغو.', 'webtanan-booking'),
                )
            );
            if (is_wp_error($platform_entry)) {
                return $platform_entry;
            }
        }

        return true;
    }

    private static function is_valid_date(string $date): bool {
        $parts = explode('-', $date);

        return 3 === count($parts) && checkdate((int) $parts[1], (int) $parts[2], (int) $parts[0]);
    }

    private static function is_valid_time(string $time): bool {
        return (bool) preg_match('/^([01]\d|2[0-3]):[0-5]\d:00$/', $time);
    }

    private static function normalize_time(string $time): string {
        if (preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time)) {
            return $time . ':00';
        }

        if (preg_match('/^([01]\d|2[0-3]):[0-5]\d:[0-5]\d$/', $time)) {
            return substr($time, 0, 5) . ':00';
        }

        return '00:00:00';
    }
}
