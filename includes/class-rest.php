<?php
/**
 * REST API routes.
 *
 * @package WebtananBooking
 */

namespace Webtanan\Booking;

defined('ABSPATH') || exit;

final class REST {
    private const NS = 'saas/v1';

    public static function init(): void {
        add_action('rest_api_init', array(__CLASS__, 'register_routes'));
    }

    public static function register_routes(): void {
        register_rest_route(self::NS, '/doctors', array(
            'methods' => \WP_REST_Server::READABLE,
            'callback' => array(__CLASS__, 'doctors'),
            'permission_callback' => '__return_true',
        ));

        register_rest_route(self::NS, '/doctors/(?P<id>\d+)', array(
            'methods' => \WP_REST_Server::READABLE,
            'callback' => array(__CLASS__, 'doctor'),
            'permission_callback' => '__return_true',
        ));

        register_rest_route(self::NS, '/doctors/(?P<id>\d+)/next-available', array(
            'methods' => \WP_REST_Server::READABLE,
            'callback' => array(__CLASS__, 'next_available'),
            'permission_callback' => '__return_true',
        ));

        register_rest_route(self::NS, '/doctors/(?P<id>\d+)/slots', array(
            'methods' => \WP_REST_Server::READABLE,
            'callback' => array(__CLASS__, 'slots'),
            'permission_callback' => '__return_true',
            'args' => array(
                'date' => array('required' => true, 'sanitize_callback' => 'sanitize_text_field'),
            ),
        ));

        register_rest_route(self::NS, '/appointments/lock', array(
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => array(__CLASS__, 'lock_appointment'),
            'permission_callback' => '__return_true',
        ));

        register_rest_route(self::NS, '/appointments/pay', array(
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => array(__CLASS__, 'pay_appointment'),
            'permission_callback' => array(__CLASS__, 'logged_in'),
        ));

        register_rest_route(self::NS, '/payment/gateways', array(
            'methods' => \WP_REST_Server::READABLE,
            'callback' => array(__CLASS__, 'payment_gateways'),
            'permission_callback' => '__return_true',
        ));

        register_rest_route(self::NS, '/payment/aqayepardakht/callback', array(
            'methods' => array(\WP_REST_Server::READABLE, \WP_REST_Server::CREATABLE),
            'callback' => array(__CLASS__, 'aqayepardakht_callback'),
            'permission_callback' => '__return_true',
        ));

        register_rest_route(self::NS, '/appointments/confirm', array(
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => array(__CLASS__, 'confirm_appointment'),
            'permission_callback' => array(__CLASS__, 'finance_permission'),
        ));

        register_rest_route(self::NS, '/appointments/cancel', array(
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => array(__CLASS__, 'cancel_appointment'),
            'permission_callback' => array(__CLASS__, 'logged_in'),
        ));

        register_rest_route(self::NS, '/wallet/balance', array(
            'methods' => \WP_REST_Server::READABLE,
            'callback' => array(__CLASS__, 'wallet_balance'),
            'permission_callback' => array(__CLASS__, 'logged_in'),
        ));

        register_rest_route(self::NS, '/wallet/ledger', array(
            'methods' => \WP_REST_Server::READABLE,
            'callback' => array(__CLASS__, 'wallet_ledger'),
            'permission_callback' => array(__CLASS__, 'logged_in'),
        ));

        register_rest_route(self::NS, '/wallet/pay', array(
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => array(__CLASS__, 'wallet_pay'),
            'permission_callback' => array(__CLASS__, 'logged_in'),
        ));

        register_rest_route(self::NS, '/auth/send-otp', array(
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => array(__CLASS__, 'send_otp'),
            'permission_callback' => '__return_true',
        ));

        register_rest_route(self::NS, '/auth/verify-otp', array(
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => array(__CLASS__, 'verify_otp'),
            'permission_callback' => '__return_true',
        ));

        register_rest_route(self::NS, '/auth/logout', array(
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => array(__CLASS__, 'logout'),
            'permission_callback' => array(__CLASS__, 'logged_in'),
        ));

        register_rest_route(self::NS, '/doctor-dashboard/context', array(
            'methods' => \WP_REST_Server::READABLE,
            'callback' => array(__CLASS__, 'doctor_dashboard_context'),
            'permission_callback' => array(__CLASS__, 'doctor_dashboard_permission'),
        ));

        register_rest_route(self::NS, '/doctor-dashboard/summary', array(
            'methods' => \WP_REST_Server::READABLE,
            'callback' => array(__CLASS__, 'doctor_dashboard_summary'),
            'permission_callback' => array(__CLASS__, 'doctor_dashboard_permission'),
        ));

        register_rest_route(self::NS, '/doctor-dashboard/appointments', array(
            array(
                'methods' => \WP_REST_Server::READABLE,
                'callback' => array(__CLASS__, 'doctor_dashboard_appointments'),
                'permission_callback' => array(__CLASS__, 'doctor_dashboard_permission'),
            ),
            array(
                'methods' => \WP_REST_Server::CREATABLE,
                'callback' => array(__CLASS__, 'doctor_dashboard_create_appointment'),
                'permission_callback' => array(__CLASS__, 'doctor_dashboard_permission'),
            ),
        ));

        register_rest_route(self::NS, '/doctor-dashboard/appointments/(?P<id>\d+)/payment', array(
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => array(__CLASS__, 'doctor_dashboard_update_payment'),
            'permission_callback' => array(__CLASS__, 'doctor_dashboard_permission'),
        ));

        register_rest_route(self::NS, '/doctor-dashboard/appointments/(?P<id>\d+)/status', array(
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => array(__CLASS__, 'doctor_dashboard_update_status'),
            'permission_callback' => array(__CLASS__, 'doctor_dashboard_permission'),
        ));

        register_rest_route(self::NS, '/doctor-dashboard/calendar', array(
            'methods' => \WP_REST_Server::READABLE,
            'callback' => array(__CLASS__, 'doctor_dashboard_calendar'),
            'permission_callback' => array(__CLASS__, 'doctor_dashboard_permission'),
        ));

        register_rest_route(self::NS, '/doctor-dashboard/schedules', array(
            array(
                'methods' => \WP_REST_Server::READABLE,
                'callback' => array(__CLASS__, 'doctor_dashboard_schedules'),
                'permission_callback' => array(__CLASS__, 'doctor_dashboard_permission'),
            ),
            array(
                'methods' => \WP_REST_Server::CREATABLE,
                'callback' => array(__CLASS__, 'doctor_dashboard_schedule'),
                'permission_callback' => array(__CLASS__, 'doctor_dashboard_permission'),
            ),
        ));

        register_rest_route(self::NS, '/doctor-dashboard/exceptions', array(
            array(
                'methods' => \WP_REST_Server::READABLE,
                'callback' => array(__CLASS__, 'doctor_dashboard_exceptions'),
                'permission_callback' => array(__CLASS__, 'doctor_dashboard_permission'),
            ),
            array(
                'methods' => \WP_REST_Server::CREATABLE,
                'callback' => array(__CLASS__, 'doctor_dashboard_create_exception'),
                'permission_callback' => array(__CLASS__, 'doctor_dashboard_permission'),
            ),
        ));

        register_rest_route(self::NS, '/doctor-dashboard/patients', array(
            'methods' => \WP_REST_Server::READABLE,
            'callback' => array(__CLASS__, 'doctor_dashboard_patients'),
            'permission_callback' => array(__CLASS__, 'doctor_dashboard_permission'),
        ));

        register_rest_route(self::NS, '/doctor-dashboard/wallet', array(
            'methods' => \WP_REST_Server::READABLE,
            'callback' => array(__CLASS__, 'doctor_dashboard_wallet'),
            'permission_callback' => array(__CLASS__, 'doctor_dashboard_permission'),
        ));

        register_rest_route(self::NS, '/doctor-dashboard/settlements', array(
            'methods' => \WP_REST_Server::READABLE,
            'callback' => array(__CLASS__, 'doctor_dashboard_settlements'),
            'permission_callback' => array(__CLASS__, 'doctor_dashboard_permission'),
        ));

        register_rest_route(self::NS, '/doctor-dashboard/settlement-request', array(
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => array(__CLASS__, 'doctor_dashboard_settlement'),
            'permission_callback' => array(__CLASS__, 'doctor_dashboard_permission'),
        ));

        register_rest_route(self::NS, '/patient-panel/summary', array(
            'methods' => \WP_REST_Server::READABLE,
            'callback' => array(__CLASS__, 'patient_panel_summary'),
            'permission_callback' => array(__CLASS__, 'logged_in'),
        ));

        register_rest_route(self::NS, '/patient-panel/appointments', array(
            'methods' => \WP_REST_Server::READABLE,
            'callback' => array(__CLASS__, 'patient_panel_appointments'),
            'permission_callback' => array(__CLASS__, 'logged_in'),
        ));

        register_rest_route(self::NS, '/patient-panel/wallet', array(
            'methods' => \WP_REST_Server::READABLE,
            'callback' => array(__CLASS__, 'patient_panel_wallet'),
            'permission_callback' => array(__CLASS__, 'logged_in'),
        ));

        register_rest_route(self::NS, '/appointments/(?P<id>\d+)/receipt', array(
            'methods' => \WP_REST_Server::READABLE,
            'callback' => array(__CLASS__, 'appointment_receipt'),
            'permission_callback' => array(__CLASS__, 'logged_in'),
        ));
    }

    public static function doctors(\WP_REST_Request $request): \WP_REST_Response {
        global $wpdb;

        $limit = max(1, min(50, absint($request->get_param('per_page') ?: 20)));
        $search = sanitize_text_field((string) $request->get_param('search'));
        $specialty_id = absint($request->get_param('specialty_id'));
        $online = sanitize_key((string) $request->get_param('online'));
        $pay_at_clinic = sanitize_key((string) $request->get_param('pay_at_clinic'));
        $where = "d.is_active = 1 AND d.is_verified = 1 AND p.post_status = 'publish'";
        $params = array();

        if ($search) {
            $where .= ' AND (p.post_title LIKE %s OR d.clinic_name LIKE %s OR d.clinic_address LIKE %s OR s.name LIKE %s)';
            $like = '%' . $wpdb->esc_like($search) . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        if ($specialty_id > 0) {
            $where .= ' AND d.specialty_id = %d';
            $params[] = $specialty_id;
        }

        if (in_array($online, array('1', 'yes', 'true'), true)) {
            $where .= ' AND d.allow_online_payment = 1';
        }

        if (in_array($pay_at_clinic, array('1', 'yes', 'true'), true)) {
            $where .= ' AND d.allow_pay_at_clinic = 1';
        }

        $sql = "SELECT d.*, p.post_title, p.ID AS post_id, s.name AS specialty_name
            FROM " . DB::table('doctors') . ' d
            INNER JOIN ' . $wpdb->posts . " p ON p.ID = d.post_id
            LEFT JOIN " . DB::table('specialties') . " s ON s.id = d.specialty_id
            WHERE $where
            ORDER BY d.is_verified DESC, p.post_title ASC
            LIMIT %d";
        $params[] = $limit;

        $rows = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);

        return rest_ensure_response(array_map(array(__CLASS__, 'format_doctor'), $rows));
    }

    public static function doctor(\WP_REST_Request $request) {
        global $wpdb;

        $id = absint($request['id']);
        $row = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT d.*, p.post_title, p.ID AS post_id, s.name AS specialty_name FROM ' . DB::table('doctors') . ' d INNER JOIN ' . $wpdb->posts . ' p ON p.ID = d.post_id LEFT JOIN ' . DB::table('specialties') . ' s ON s.id = d.specialty_id WHERE d.id = %d AND d.is_active = 1 AND d.is_verified = 1 AND p.post_status = %s',
                $id,
                'publish'
            ),
            ARRAY_A
        );

        if (!$row) {
            return new \WP_Error('webtanan_doctor_not_found', __('پزشک پیدا نشد.', 'webtanan-booking'), array('status' => 404));
        }

        return rest_ensure_response(self::format_doctor($row));
    }

    public static function next_available(\WP_REST_Request $request): \WP_REST_Response {
        return rest_ensure_response(Booking::next_available(absint($request['id']), 5));
    }

    public static function slots(\WP_REST_Request $request): \WP_REST_Response {
        return rest_ensure_response(Booking::get_slots(absint($request['id']), sanitize_text_field((string) $request->get_param('date'))));
    }

    public static function lock_appointment(\WP_REST_Request $request) {
        $result = Booking::lock_appointment($request->get_params());

        return is_wp_error($result) ? $result : rest_ensure_response($result);
    }

    public static function pay_appointment(\WP_REST_Request $request) {
        $appointment_id = absint($request->get_param('appointment_id'));
        $lock_token = sanitize_text_field((string) $request->get_param('lock_token'));
        $method = sanitize_key((string) $request->get_param('method'));
        $gateway = sanitize_key((string) ($request->get_param('gateway') ?: $request->get_param('gateway_name')));

        if ('wallet' === $method) {
            $result = Wallet::pay_for_appointment($appointment_id, $lock_token);
        } elseif ('pay_at_clinic' === $method) {
            $result = Booking::confirm_pay_at_clinic($appointment_id, $lock_token);
        } else {
            $result = Booking::initiate_payment($appointment_id, $lock_token, $gateway);
        }

        return is_wp_error($result) ? $result : rest_ensure_response($result);
    }

    public static function payment_gateways(\WP_REST_Request $request): \WP_REST_Response {
        return rest_ensure_response(Payment_Gateways::available_gateways());
    }

    public static function aqayepardakht_callback(\WP_REST_Request $request) {
        return Payment_Gateways::handle_aqayepardakht_callback($request);
    }

    public static function confirm_appointment(\WP_REST_Request $request) {
        $result = Booking::confirm_appointment_after_payment(
            absint($request->get_param('appointment_id')),
            absint($request->get_param('transaction_id')),
            sanitize_text_field((string) $request->get_param('lock_token'))
        );

        return is_wp_error($result) ? $result : rest_ensure_response($result);
    }

    public static function cancel_appointment(\WP_REST_Request $request) {
        $appointment_id = absint($request->get_param('appointment_id'));
        if (!Booking::can_current_user_manage_appointment($appointment_id)) {
            return new \WP_Error('webtanan_forbidden', __('شما اجازه لغو این نوبت را ندارید.', 'webtanan-booking'), array('status' => 403));
        }

        $result = Booking::cancel_appointment($appointment_id, sanitize_key((string) $request->get_param('cancelled_by') ?: 'patient'), sanitize_textarea_field((string) $request->get_param('reason')));

        return is_wp_error($result) ? $result : rest_ensure_response($result);
    }

    public static function wallet_balance(\WP_REST_Request $request): \WP_REST_Response {
        $user_type = sanitize_key((string) ($request->get_param('user_type') ?: 'patient'));

        return rest_ensure_response(array('balance' => Wallet::balance(get_current_user_id(), $user_type), 'user_type' => $user_type));
    }

    public static function wallet_ledger(\WP_REST_Request $request): \WP_REST_Response {
        $user_type = sanitize_key((string) ($request->get_param('user_type') ?: 'patient'));

        return rest_ensure_response(Wallet::ledger(get_current_user_id(), $user_type));
    }

    public static function wallet_pay(\WP_REST_Request $request) {
        $result = Wallet::pay_for_appointment(absint($request->get_param('appointment_id')), sanitize_text_field((string) $request->get_param('lock_token')));

        return is_wp_error($result) ? $result : rest_ensure_response($result);
    }

    public static function send_otp(\WP_REST_Request $request) {
        $result = OTP::send((string) $request->get_param('mobile'), (string) ($request->get_param('purpose') ?: 'login'));

        return is_wp_error($result) ? $result : rest_ensure_response($result);
    }

    public static function verify_otp(\WP_REST_Request $request) {
        $result = OTP::verify((string) $request->get_param('mobile'), (string) $request->get_param('otp'), (string) ($request->get_param('purpose') ?: 'login'));

        return is_wp_error($result) ? $result : rest_ensure_response($result);
    }

    public static function logout(): \WP_REST_Response {
        wp_logout();

        return rest_ensure_response(array('logged_out' => true));
    }

    public static function doctor_dashboard_context(\WP_REST_Request $request): \WP_REST_Response {
        $doctors = self::dashboard_doctors_for_current_user();
        $active_doctor_id = self::current_dashboard_doctor_id($request);
        $user = wp_get_current_user();

        return rest_ensure_response(
            array(
                'user' => array(
                    'id' => get_current_user_id(),
                    'display_name' => $user ? $user->display_name : '',
                    'roles' => $user ? array_values((array) $user->roles) : array(),
                ),
                'doctors' => $doctors,
                'active_doctor_id' => $active_doctor_id,
                'can_view_finance' => $active_doctor_id ? self::current_user_can_view_doctor_finance($active_doctor_id) : false,
                'today' => current_time('Y-m-d'),
            )
        );
    }

    public static function doctor_dashboard_summary(\WP_REST_Request $request) {
        global $wpdb;

        $doctor_id = self::current_dashboard_doctor_id($request);
        if (!$doctor_id) {
            return new \WP_Error('webtanan_doctor_context_missing', __('دسترسی پزشک پیدا نشد.', 'webtanan-booking'), array('status' => 403));
        }

        $doctor = Booking::get_doctor($doctor_id);
        $date = sanitize_text_field((string) ($request->get_param('date') ?: current_time('Y-m-d')));
        $appointments = DB::table('appointments');

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT
                    COUNT(*) AS total,
                    SUM(CASE WHEN appointment_status = 'completed' THEN 1 ELSE 0 END) AS completed,
                    SUM(CASE WHEN appointment_status = 'no_show' THEN 1 ELSE 0 END) AS no_show,
                    SUM(CASE WHEN appointment_status = 'locked' THEN 1 ELSE 0 END) AS locked,
                    SUM(CASE WHEN payment_status IN ('paid','wallet_paid','cash_at_clinic','pos_at_clinic') THEN booking_fee ELSE 0 END) AS revenue
                FROM $appointments
                WHERE doctor_id = %d AND appointment_date = %s",
                $doctor_id,
                $date
            ),
            ARRAY_A
        );

        $next = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $appointments
                WHERE doctor_id = %d
                    AND appointment_status IN ('confirmed','pay_at_clinic')
                    AND CONCAT(appointment_date, ' ', start_time) >= %s
                ORDER BY appointment_date ASC, start_time ASC
                LIMIT 1",
                $doctor_id,
                current_time('mysql')
            ),
            ARRAY_A
        );

        $can_view_finance = self::current_user_can_view_doctor_finance($doctor_id);
        $wallet_subject = $doctor ? self::doctor_wallet_subject($doctor) : array('user_id' => 0, 'user_type' => 'doctor');
        $wallet_balance = ($doctor && $can_view_finance && $wallet_subject['user_id'] > 0) ? Wallet::balance((int) $wallet_subject['user_id'], $wallet_subject['user_type']) : null;

        return rest_ensure_response(
            array(
                'date' => $date,
                'doctor_id' => $doctor_id,
                'appointments_today' => (int) ($row['total'] ?? 0),
                'completed_today' => (int) ($row['completed'] ?? 0),
                'no_show_today' => (int) ($row['no_show'] ?? 0),
                'locked_today' => (int) ($row['locked'] ?? 0),
                'revenue_today' => $can_view_finance ? (float) ($row['revenue'] ?? 0) : null,
                'wallet_balance' => $wallet_balance,
                'can_view_finance' => $can_view_finance,
                'next_appointment' => $next ? self::format_appointment($next) : null,
            )
        );
    }

    public static function doctor_dashboard_appointments(\WP_REST_Request $request) {
        global $wpdb;

        $doctor_id = self::current_dashboard_doctor_id($request);
        if (!$doctor_id) {
            return new \WP_Error('webtanan_doctor_context_missing', __('دسترسی پزشک پیدا نشد.', 'webtanan-booking'), array('status' => 403));
        }

        $date = sanitize_text_field((string) ($request->get_param('date') ?: current_time('Y-m-d')));
        $search = sanitize_text_field((string) $request->get_param('search'));
        $status = sanitize_key((string) $request->get_param('status'));
        $payment_status = sanitize_key((string) $request->get_param('payment_status'));
        $table = DB::table('appointments');
        $where = 'doctor_id = %d AND appointment_date = %s';
        $params = array($doctor_id, $date);

        if ($status) {
            $where .= ' AND appointment_status = %s';
            $params[] = $status;
        }

        if ($payment_status) {
            $where .= ' AND payment_status = %s';
            $params[] = $payment_status;
        }

        if ($search) {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where .= ' AND (patient_first_name LIKE %s OR patient_last_name LIKE %s OR patient_mobile LIKE %s OR patient_national_code LIKE %s)';
            array_push($params, $like, $like, $like, $like);
        }

        $rows = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM $table WHERE $where ORDER BY start_time ASC LIMIT 200", $params),
            ARRAY_A
        );

        return rest_ensure_response(array_map(array(__CLASS__, 'format_appointment'), $rows));
    }

    public static function doctor_dashboard_create_appointment(\WP_REST_Request $request) {
        $doctor_id = self::current_dashboard_doctor_id($request);
        if (!$doctor_id || !Booking::current_user_can_access_doctor($doctor_id)) {
            return new \WP_Error('webtanan_doctor_context_missing', __('دسترسی پزشک پیدا نشد.', 'webtanan-booking'), array('status' => 403));
        }

        $params = $request->get_params();
        $params['doctor_id'] = $doctor_id;
        $result = Booking::create_staff_appointment($params);

        return is_wp_error($result) ? $result : rest_ensure_response($result);
    }

    public static function doctor_dashboard_update_payment(\WP_REST_Request $request) {
        $appointment_id = absint($request['id']);
        $appointment = Booking::get_appointment($appointment_id);
        if (!$appointment || !Booking::current_user_can_access_doctor((int) $appointment['doctor_id'])) {
            return new \WP_Error('webtanan_forbidden', __('شما اجازه تغییر این نوبت را ندارید.', 'webtanan-booking'), array('status' => 403));
        }

        $result = Booking::update_clinic_payment_status($appointment_id, sanitize_key((string) $request->get_param('payment_status')));

        return is_wp_error($result) ? $result : rest_ensure_response($result);
    }

    public static function doctor_dashboard_update_status(\WP_REST_Request $request) {
        $appointment_id = absint($request['id']);
        $appointment = Booking::get_appointment($appointment_id);
        if (!$appointment || !Booking::current_user_can_access_doctor((int) $appointment['doctor_id'])) {
            return new \WP_Error('webtanan_forbidden', __('شما اجازه تغییر این نوبت را ندارید.', 'webtanan-booking'), array('status' => 403));
        }

        $result = Booking::update_attendance_status($appointment_id, sanitize_key((string) $request->get_param('appointment_status')));

        return is_wp_error($result) ? $result : rest_ensure_response($result);
    }

    public static function doctor_dashboard_calendar(\WP_REST_Request $request) {
        $doctor_id = self::current_dashboard_doctor_id($request);
        if (!$doctor_id) {
            return new \WP_Error('webtanan_doctor_context_missing', __('دسترسی پزشک پیدا نشد.', 'webtanan-booking'), array('status' => 403));
        }

        return rest_ensure_response(Booking::get_slots($doctor_id, sanitize_text_field((string) ($request->get_param('date') ?: current_time('Y-m-d')))));
    }

    public static function doctor_dashboard_schedules(\WP_REST_Request $request) {
        global $wpdb;

        $doctor_id = self::current_dashboard_doctor_id($request);
        if (!$doctor_id) {
            return new \WP_Error('webtanan_doctor_context_missing', __('دسترسی پزشک پیدا نشد.', 'webtanan-booking'), array('status' => 403));
        }

        $rows = $wpdb->get_results(
            $wpdb->prepare('SELECT * FROM ' . DB::table('schedules') . ' WHERE doctor_id = %d ORDER BY FIELD(weekday, "saturday","sunday","monday","tuesday","wednesday","thursday","friday"), start_time ASC', $doctor_id),
            ARRAY_A
        );

        return rest_ensure_response($rows);
    }

    public static function doctor_dashboard_schedule(\WP_REST_Request $request) {
        global $wpdb;

        $doctor_id = self::current_dashboard_doctor_id($request);
        if (!$doctor_id) {
            return new \WP_Error('webtanan_doctor_context_missing', __('دسترسی پزشک پیدا نشد.', 'webtanan-booking'), array('status' => 403));
        }

        $weekday = sanitize_key((string) $request->get_param('weekday'));
        if (!in_array($weekday, array('saturday', 'sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday'), true)) {
            return new \WP_Error('webtanan_invalid_weekday', __('روز هفته معتبر نیست.', 'webtanan-booking'), array('status' => 400));
        }

        $start_time = self::normalize_rest_time((string) $request->get_param('start_time'));
        $end_time = self::normalize_rest_time((string) $request->get_param('end_time'));
        if (!$start_time || !$end_time || strtotime('1970-01-01 ' . $end_time) <= strtotime('1970-01-01 ' . $start_time)) {
            return new \WP_Error('webtanan_invalid_schedule_time', __('بازه زمانی برنامه کاری معتبر نیست.', 'webtanan-booking'), array('status' => 400));
        }

        $now = DB::now();
        $wpdb->insert(
            DB::table('schedules'),
            array(
                'doctor_id' => $doctor_id,
                'weekday' => $weekday,
                'start_time' => $start_time,
                'end_time' => $end_time,
                'slot_duration' => max(1, absint($request->get_param('slot_duration') ?: 15)),
                'capacity_per_slot' => max(1, absint($request->get_param('capacity_per_slot') ?: 1)),
                'is_active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            )
        );

        return rest_ensure_response(array('schedule_id' => (int) $wpdb->insert_id));
    }

    public static function doctor_dashboard_exceptions(\WP_REST_Request $request) {
        global $wpdb;

        $doctor_id = self::current_dashboard_doctor_id($request);
        if (!$doctor_id) {
            return new \WP_Error('webtanan_doctor_context_missing', __('دسترسی پزشک پیدا نشد.', 'webtanan-booking'), array('status' => 403));
        }

        $from = sanitize_text_field((string) ($request->get_param('from') ?: current_time('Y-m-d')));
        $rows = $wpdb->get_results(
            $wpdb->prepare('SELECT * FROM ' . DB::table('schedule_exceptions') . ' WHERE doctor_id = %d AND exception_date >= %s ORDER BY exception_date ASC, start_time ASC LIMIT 100', $doctor_id, $from),
            ARRAY_A
        );

        return rest_ensure_response($rows);
    }

    public static function doctor_dashboard_create_exception(\WP_REST_Request $request) {
        global $wpdb;

        $doctor_id = self::current_dashboard_doctor_id($request);
        if (!$doctor_id) {
            return new \WP_Error('webtanan_doctor_context_missing', __('دسترسی پزشک پیدا نشد.', 'webtanan-booking'), array('status' => 403));
        }

        $type = sanitize_key((string) $request->get_param('type'));
        if (!in_array($type, array('day_off', 'custom_shift', 'reduced_shift', 'extra_shift'), true)) {
            return new \WP_Error('webtanan_invalid_exception_type', __('نوع استثنا معتبر نیست.', 'webtanan-booking'), array('status' => 400));
        }

        $date = sanitize_text_field((string) $request->get_param('exception_date'));
        $start_time = self::normalize_rest_time((string) $request->get_param('start_time'));
        $end_time = self::normalize_rest_time((string) $request->get_param('end_time'));
        if ('day_off' !== $type && (!$start_time || !$end_time)) {
            return new \WP_Error('webtanan_invalid_exception_time', __('بازه زمانی استثنا معتبر نیست.', 'webtanan-booking'), array('status' => 400));
        }

        $now = DB::now();
        $wpdb->insert(
            DB::table('schedule_exceptions'),
            array(
                'doctor_id' => $doctor_id,
                'exception_date' => $date,
                'type' => $type,
                'start_time' => 'day_off' === $type ? null : $start_time,
                'end_time' => 'day_off' === $type ? null : $end_time,
                'slot_duration' => max(1, absint($request->get_param('slot_duration') ?: 15)),
                'capacity_per_slot' => max(1, absint($request->get_param('capacity_per_slot') ?: 1)),
                'reason' => sanitize_textarea_field((string) $request->get_param('reason')),
                'created_at' => $now,
                'updated_at' => $now,
            )
        );

        return rest_ensure_response(array('exception_id' => (int) $wpdb->insert_id));
    }

    public static function doctor_dashboard_patients(\WP_REST_Request $request) {
        global $wpdb;

        $doctor_id = self::current_dashboard_doctor_id($request);
        if (!$doctor_id) {
            return new \WP_Error('webtanan_doctor_context_missing', __('دسترسی پزشک پیدا نشد.', 'webtanan-booking'), array('status' => 403));
        }

        $search = sanitize_text_field((string) $request->get_param('search'));
        $table = DB::table('appointments');
        $where = "doctor_id = %d AND patient_mobile <> ''";
        $params = array($doctor_id);

        if ($search) {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where .= ' AND (patient_first_name LIKE %s OR patient_last_name LIKE %s OR patient_mobile LIKE %s OR patient_national_code LIKE %s)';
            array_push($params, $like, $like, $like, $like);
        }

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT
                    patient_first_name,
                    patient_last_name,
                    patient_national_code,
                    patient_mobile,
                    COUNT(*) AS appointment_count,
                    MAX(appointment_date) AS last_visit_date
                FROM $table
                WHERE $where
                GROUP BY patient_first_name, patient_last_name, patient_national_code, patient_mobile
                ORDER BY last_visit_date DESC
                LIMIT 100",
                $params
            ),
            ARRAY_A
        );

        return rest_ensure_response($rows);
    }

    public static function doctor_dashboard_wallet(\WP_REST_Request $request) {
        $doctor_id = self::current_dashboard_doctor_id($request);
        $doctor = $doctor_id ? Booking::get_doctor($doctor_id) : null;
        if (!$doctor || !self::current_user_can_view_doctor_finance($doctor_id)) {
            return new \WP_Error('webtanan_finance_forbidden', __('شما اجازه مشاهده اطلاعات مالی این پزشک را ندارید.', 'webtanan-booking'), array('status' => 403));
        }

        $wallet_subject = self::doctor_wallet_subject($doctor);
        $summary = Booking::settlement_summary($doctor_id);

        return rest_ensure_response(
            array(
                'balance' => Wallet::balance((int) $wallet_subject['user_id'], $wallet_subject['user_type']),
                'total_balance' => (float) $summary['total_balance'],
                'available_balance' => (float) $summary['available_balance'],
                'pending_settlement' => (float) $summary['pending_settlement'],
                'commission_debt' => (float) $summary['commission_debt'],
                'ledger' => Wallet::ledger((int) $wallet_subject['user_id'], $wallet_subject['user_type'], 100),
                'user_type' => $wallet_subject['user_type'],
            )
        );
    }

    public static function doctor_dashboard_settlements(\WP_REST_Request $request) {
        global $wpdb;

        $doctor_id = self::current_dashboard_doctor_id($request);
        if (!$doctor_id || !self::current_user_can_view_doctor_finance($doctor_id)) {
            return new \WP_Error('webtanan_finance_forbidden', __('شما اجازه مشاهده اطلاعات مالی این پزشک را ندارید.', 'webtanan-booking'), array('status' => 403));
        }

        $rows = $wpdb->get_results(
            $wpdb->prepare('SELECT * FROM ' . DB::table('settlement_requests') . ' WHERE doctor_id = %d ORDER BY id DESC LIMIT 50', $doctor_id),
            ARRAY_A
        );

        return rest_ensure_response($rows);
    }

    public static function doctor_dashboard_settlement(\WP_REST_Request $request) {
        global $wpdb;

        $doctor_id = self::current_dashboard_doctor_id($request);
        $doctor = $doctor_id ? Booking::get_doctor($doctor_id) : null;
        if (!$doctor) {
            return new \WP_Error('webtanan_doctor_context_missing', __('دسترسی پزشک پیدا نشد.', 'webtanan-booking'), array('status' => 403));
        }

        if (!self::current_user_can_view_doctor_finance($doctor_id)) {
            return new \WP_Error('webtanan_finance_forbidden', __('شما اجازه ثبت درخواست تسویه برای این پزشک را ندارید.', 'webtanan-booking'), array('status' => 403));
        }

        $amount = abs((float) $request->get_param('amount'));
        $result = Booking::create_settlement_request(
            $doctor_id,
            $amount,
            sanitize_text_field((string) ($request->get_param('iban') ?: $doctor['iban'])),
            ''
        );
        if (is_wp_error($result)) {
            return $result;
        }

        SMS::send_doctor_notification($doctor_id, 'settlement_requested', array('amount' => (string) $amount));

        return rest_ensure_response($result);
    }

    public static function patient_panel_summary(\WP_REST_Request $request): \WP_REST_Response {
        global $wpdb;

        $user_id = get_current_user_id();
        $appointments = DB::table('appointments');
        $today = current_time('Y-m-d');

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT
                    SUM(CASE WHEN appointment_date >= %s AND appointment_status IN ('confirmed','pay_at_clinic','locked') THEN 1 ELSE 0 END) AS upcoming,
                    SUM(CASE WHEN appointment_date < %s OR appointment_status IN ('completed','cancelled','expired','no_show') THEN 1 ELSE 0 END) AS history
                FROM $appointments
                WHERE patient_user_id = %d",
                $today,
                $today,
                $user_id
            ),
            ARRAY_A
        );

        return rest_ensure_response(
            array(
                'upcoming_count' => (int) ($row['upcoming'] ?? 0),
                'history_count' => (int) ($row['history'] ?? 0),
                'wallet_balance' => Wallet::balance($user_id, 'patient'),
            )
        );
    }

    public static function patient_panel_appointments(\WP_REST_Request $request): \WP_REST_Response {
        global $wpdb;

        $scope = sanitize_key((string) ($request->get_param('scope') ?: 'upcoming'));
        $today = current_time('Y-m-d');
        $table = DB::table('appointments');
        $where = 'patient_user_id = %d';
        $params = array(get_current_user_id());

        if ('history' === $scope) {
            $where .= " AND (appointment_date < %s OR appointment_status IN ('completed','cancelled','expired','no_show'))";
            $params[] = $today;
        } else {
            $where .= " AND appointment_date >= %s AND appointment_status IN ('confirmed','pay_at_clinic','locked')";
            $params[] = $today;
        }

        $rows = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM $table WHERE $where ORDER BY appointment_date ASC, start_time ASC LIMIT 100", $params),
            ARRAY_A
        );

        return rest_ensure_response(array_map(array(__CLASS__, 'format_appointment'), $rows));
    }

    public static function patient_panel_wallet(\WP_REST_Request $request): \WP_REST_Response {
        $user_id = get_current_user_id();

        return rest_ensure_response(
            array(
                'balance' => Wallet::balance($user_id, 'patient'),
                'ledger' => Wallet::ledger($user_id, 'patient', 100),
            )
        );
    }

    public static function appointment_receipt(\WP_REST_Request $request) {
        $appointment = Booking::get_appointment(absint($request['id']));
        if (!$appointment || !self::current_user_can_view_appointment($appointment)) {
            return new \WP_Error('webtanan_receipt_forbidden', __('شما اجازه مشاهده این رسید را ندارید.', 'webtanan-booking'), array('status' => 403));
        }

        $doctor = Booking::get_doctor((int) $appointment['doctor_id']);
        $doctor_data = $doctor ? self::format_doctor_for_dashboard($doctor) : array();

        return rest_ensure_response(
            array(
                'appointment' => self::format_appointment($appointment),
                'doctor' => $doctor_data,
                'print_title' => __('رسید نوبت', 'webtanan-booking'),
            )
        );
    }

    public static function logged_in(): bool {
        return is_user_logged_in();
    }

    public static function finance_permission(): bool {
        return current_user_can('webtanan_manage_finance') || current_user_can('manage_options');
    }

    public static function doctor_dashboard_permission(): bool {
        return is_user_logged_in() && (current_user_can('webtanan_access_doctor_dashboard') || current_user_can('webtanan_access_secretary_dashboard') || current_user_can('webtanan_manage_booking') || current_user_can('manage_options'));
    }

    private static function current_dashboard_doctor_id(\WP_REST_Request $request): int {
        $requested = absint($request->get_param('doctor_id'));
        if ($requested && Booking::current_user_can_access_doctor($requested)) {
            return $requested;
        }

        $doctors = self::dashboard_doctors_for_current_user();
        if ($doctors) {
            return (int) $doctors[0]['id'];
        }

        return 0;
    }

    private static function dashboard_doctors_for_current_user(): array {
        global $wpdb;

        $user_id = get_current_user_id();
        $doctor_table = DB::table('doctors');
        $specialty_table = DB::table('specialties');
        $where = '1 = 1';
        $params = array();

        if (!current_user_can('webtanan_manage_booking') && !current_user_can('manage_options')) {
            $assigned = get_user_meta($user_id, 'webtanan_assigned_doctor_ids', true);
            if (is_string($assigned)) {
                $assigned = array_filter(array_map('absint', explode(',', $assigned)));
            }
            if (!is_array($assigned)) {
                $assigned = array();
            }

            $clauses = array('d.user_id = %d', 'd.secretary_user_id = %d');
            $params[] = $user_id;
            $params[] = $user_id;

            if ($assigned) {
                $placeholders = implode(',', array_fill(0, count($assigned), '%d'));
                $clauses[] = "d.id IN ($placeholders)";
                foreach ($assigned as $assigned_id) {
                    $params[] = (int) $assigned_id;
                }
            }

            $where = '(' . implode(' OR ', $clauses) . ')';
        }

        $sql = "SELECT d.*, s.name AS specialty_name
            FROM $doctor_table d
            LEFT JOIN $specialty_table s ON s.id = d.specialty_id
            WHERE $where
            ORDER BY d.is_active DESC, d.id ASC
            LIMIT 100";

        $rows = $params ? $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A) : $wpdb->get_results($sql, ARRAY_A);

        return array_map(array(__CLASS__, 'format_doctor_for_dashboard'), $rows);
    }

    private static function doctor_wallet_subject(array $doctor): array {
        $recipient = Booking::booking_fee_recipient($doctor);
        if (!empty($recipient['user_id'])) {
            return array(
                'user_id' => (int) $recipient['user_id'],
                'user_type' => (string) $recipient['user_type'],
            );
        }

        return array(
            'user_id' => (int) ($doctor['user_id'] ?? 0),
            'user_type' => 'doctor',
        );
    }

    private static function current_user_can_view_doctor_finance(int $doctor_id): bool {
        $doctor = Booking::get_doctor($doctor_id);
        if (!$doctor) {
            return false;
        }

        if (current_user_can('webtanan_manage_finance') || current_user_can('manage_options')) {
            return true;
        }

        $user_id = get_current_user_id();
        if ($user_id > 0 && (int) $doctor['user_id'] === $user_id) {
            return true;
        }

        return 'yes' === get_user_meta($user_id, 'webtanan_secretary_can_view_finance', true);
    }

    private static function current_user_can_view_appointment(array $appointment): bool {
        if (current_user_can('webtanan_manage_booking') || current_user_can('manage_options')) {
            return true;
        }

        if ((int) $appointment['patient_user_id'] === get_current_user_id()) {
            return true;
        }

        return Booking::current_user_can_access_doctor((int) $appointment['doctor_id']);
    }

    private static function format_doctor(array $row): array {
        $post_id = (int) $row['post_id'];

        return array(
            'id' => (int) $row['id'],
            'post_id' => $post_id,
            'title' => html_entity_decode(get_the_title($post_id), ENT_QUOTES, get_bloginfo('charset')),
            'permalink' => get_permalink($post_id),
            'specialty_id' => (int) $row['specialty_id'],
            'specialty_name' => $row['specialty_name'] ?? '',
            'clinic_name' => $row['clinic_name'] ?? '',
            'clinic_address' => $row['clinic_address'] ?? '',
            'clinic_phone' => $row['clinic_phone'] ?? '',
            'visit_price' => (float) $row['visit_price'],
            'display_visit_price' => (float) $row['visit_price'],
            'booking_fee' => Booking::doctor_booking_fee($row),
            'booking_fee_share_type' => $row['booking_fee_share_type'] ?? 'percent',
            'booking_fee_share_value' => (float) ($row['booking_fee_share_value'] ?? 0),
            'is_verified' => (bool) $row['is_verified'],
            'allow_online_payment' => (bool) $row['allow_online_payment'],
            'allow_pay_at_clinic' => (bool) $row['allow_pay_at_clinic'],
            'thumbnail' => get_the_post_thumbnail_url($post_id, 'medium') ?: '',
            'gallery' => self::doctor_gallery_urls($post_id),
        );
    }

    private static function format_doctor_for_dashboard(array $row): array {
        $post_id = (int) $row['post_id'];

        return array(
            'id' => (int) $row['id'],
            'post_id' => $post_id,
            'user_id' => (int) $row['user_id'],
            'secretary_user_id' => (int) ($row['secretary_user_id'] ?? 0),
            'title' => $post_id ? html_entity_decode(get_the_title($post_id), ENT_QUOTES, get_bloginfo('charset')) : ($row['clinic_name'] ?: __('پزشک', 'webtanan-booking')),
            'specialty_id' => (int) $row['specialty_id'],
            'specialty_name' => $row['specialty_name'] ?? '',
            'clinic_name' => $row['clinic_name'] ?? '',
            'clinic_address' => $row['clinic_address'] ?? '',
            'clinic_phone' => $row['clinic_phone'] ?? '',
            'visit_price' => (float) $row['visit_price'],
            'display_visit_price' => (float) $row['visit_price'],
            'booking_fee' => Booking::doctor_booking_fee($row),
            'booking_fee_share_type' => $row['booking_fee_share_type'] ?? 'percent',
            'booking_fee_share_value' => (float) ($row['booking_fee_share_value'] ?? 0),
            'is_active' => (bool) $row['is_active'],
            'is_verified' => (bool) $row['is_verified'],
            'allow_pay_at_clinic' => (bool) $row['allow_pay_at_clinic'],
            'thumbnail' => get_the_post_thumbnail_url($post_id, 'medium') ?: '',
            'gallery' => self::doctor_gallery_urls($post_id),
        );
    }

    private static function format_appointment(array $row): array {
        $doctor = Booking::get_doctor((int) $row['doctor_id']);
        $doctor_title = '';
        $clinic_address = '';
        if ($doctor) {
            $doctor_title = (int) $doctor['post_id'] > 0 ? html_entity_decode(get_the_title((int) $doctor['post_id']), ENT_QUOTES, get_bloginfo('charset')) : '';
            $clinic_address = $doctor['clinic_address'] ?? '';
        }

        $payment_labels = array(
            'unpaid' => __('پرداخت‌نشده', 'webtanan-booking'),
            'paid' => __('پرداخت آنلاین', 'webtanan-booking'),
            'failed' => __('پرداخت ناموفق', 'webtanan-booking'),
            'refunded_to_wallet' => __('برگشت به کیف پول', 'webtanan-booking'),
            'cash_at_clinic' => __('نقدی در مطب', 'webtanan-booking'),
            'pos_at_clinic' => __('کارت‌خوان در مطب', 'webtanan-booking'),
            'wallet_paid' => __('پرداخت از کیف پول', 'webtanan-booking'),
        );
        $status_labels = array(
            'pending' => __('در انتظار', 'webtanan-booking'),
            'locked' => __('در حال رزرو', 'webtanan-booking'),
            'confirmed' => __('تاییدشده', 'webtanan-booking'),
            'cancelled' => __('لغوشده', 'webtanan-booking'),
            'expired' => __('منقضی', 'webtanan-booking'),
            'completed' => __('مراجعه کرد', 'webtanan-booking'),
            'no_show' => __('مراجعه نکرد', 'webtanan-booking'),
            'pay_at_clinic' => __('پرداخت در مطب', 'webtanan-booking'),
        );

        $payment_amount = Booking::appointment_charge_amount($row);
        $cancellation = Booking::cancellation_preview($row, 'patient');

        return array(
            'id' => (int) $row['id'],
            'appointment_code' => $row['appointment_code'],
            'doctor_id' => (int) $row['doctor_id'],
            'doctor_title' => $doctor_title,
            'clinic_address' => $clinic_address,
            'patient_user_id' => (int) $row['patient_user_id'],
            'patient_first_name' => $row['patient_first_name'],
            'patient_last_name' => $row['patient_last_name'],
            'patient_full_name' => trim($row['patient_first_name'] . ' ' . $row['patient_last_name']),
            'patient_national_code' => $row['patient_national_code'],
            'patient_mobile' => $row['patient_mobile'],
            'appointment_date' => $row['appointment_date'],
            'start_time' => substr((string) $row['start_time'], 0, 5),
            'end_time' => substr((string) $row['end_time'], 0, 5),
            'visit_price' => (float) $row['visit_price'],
            'display_visit_price' => (float) ($row['display_visit_price'] ?? $row['visit_price']),
            'booking_fee' => (float) ($row['booking_fee'] ?? $payment_amount),
            'payment_amount' => $payment_amount,
            'payment_method' => $row['payment_method'],
            'payment_status' => $row['payment_status'],
            'payment_label' => $payment_labels[$row['payment_status']] ?? $row['payment_status'],
            'appointment_status' => $row['appointment_status'],
            'appointment_label' => $status_labels[$row['appointment_status']] ?? $row['appointment_status'],
            'locked_until' => $row['locked_until'],
            'transaction_id' => (int) $row['transaction_id'],
            'cancelled_by' => $row['cancelled_by'],
            'cancel_reason' => $row['cancel_reason'],
            'can_cancel' => (bool) $cancellation['can_cancel'],
            'refund_estimate' => (float) $cancellation['refund_amount'],
            'cancellation_message' => $cancellation['message'],
            'hours_to_appointment' => (float) $cancellation['hours_to_appointment'],
            'confirmed_at' => $row['confirmed_at'],
            'cancelled_at' => $row['cancelled_at'],
            'created_at' => $row['created_at'],
        );
    }

    private static function doctor_gallery_urls(int $post_id): array {
        if ($post_id <= 0) {
            return array();
        }

        $ids = get_post_meta($post_id, '_webtanan_doctor_gallery_ids', true);
        if (is_string($ids)) {
            $ids = array_filter(array_map('absint', explode(',', $ids)));
        }
        if (!is_array($ids)) {
            return array();
        }

        $gallery = array();
        foreach ($ids as $id) {
            $url = wp_get_attachment_image_url((int) $id, 'large');
            if ($url) {
                $gallery[] = array(
                    'id' => (int) $id,
                    'url' => esc_url_raw($url),
                    'thumbnail' => esc_url_raw(wp_get_attachment_image_url((int) $id, 'medium') ?: $url),
                    'alt' => get_post_meta((int) $id, '_wp_attachment_image_alt', true),
                );
            }
        }

        return $gallery;
    }

    private static function normalize_rest_time(string $time): string {
        $time = sanitize_text_field($time);

        if (preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time)) {
            return $time . ':00';
        }

        if (preg_match('/^([01]\d|2[0-3]):[0-5]\d:[0-5]\d$/', $time)) {
            return substr($time, 0, 5) . ':00';
        }

        return '';
    }
}
