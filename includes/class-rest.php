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
        add_action('parse_request', array(__CLASS__, 'normalize_malformed_plain_rest_route'), 0);
        add_action('rest_api_init', array(__CLASS__, 'register_routes'));
    }

    public static function normalize_malformed_plain_rest_route(\WP $wp): void {
        $route = '';
        if (isset($wp->query_vars['rest_route'])) {
            $route = (string) $wp->query_vars['rest_route'];
        } elseif (isset($_GET['rest_route'])) {
            $route = (string) wp_unslash($_GET['rest_route']);
        }

        if ('' === $route || false === strpos($route, '?')) {
            return;
        }

        list($clean_route, $query_string) = explode('?', $route, 2);
        $clean_route = '/' . ltrim($clean_route, '/');
        $extra_params = array();
        parse_str($query_string, $extra_params);

        $wp->query_vars['rest_route'] = $clean_route;
        $_GET['rest_route'] = $clean_route;
        $_REQUEST['rest_route'] = $clean_route;

        foreach ($extra_params as $key => $value) {
            $key = sanitize_key((string) $key);
            if ('' === $key || isset($_GET[$key])) {
                continue;
            }

            $_GET[$key] = is_scalar($value) ? sanitize_text_field((string) $value) : $value;
            $_REQUEST[$key] = $_GET[$key];
            $wp->query_vars[$key] = $_GET[$key];
        }
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

        register_rest_route(self::NS, '/wallet/topup', array(
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => array(__CLASS__, 'wallet_topup'),
            'permission_callback' => array(__CLASS__, 'logged_in'),
        ));

        register_rest_route(self::NS, '/payments/resume/send-otp', array(
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => array(__CLASS__, 'resume_payment_send_otp'),
            'permission_callback' => '__return_true',
        ));

        register_rest_route(self::NS, '/payments/resume/verify', array(
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => array(__CLASS__, 'resume_payment_verify'),
            'permission_callback' => '__return_true',
        ));

        register_rest_route(self::NS, '/payments/resume/pay', array(
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => array(__CLASS__, 'resume_payment_pay'),
            'permission_callback' => '__return_true',
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

        register_rest_route(self::NS, '/doctor-dashboard/appointments/bulk-cancel', array(
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => array(__CLASS__, 'doctor_dashboard_bulk_cancel_appointments'),
            'permission_callback' => array(__CLASS__, 'doctor_dashboard_permission'),
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

        register_rest_route(self::NS, '/doctor-dashboard/patients/(?P<patient_id>\d+)/record', array(
            array(
                'methods' => \WP_REST_Server::READABLE,
                'callback' => array(__CLASS__, 'doctor_dashboard_patient_record'),
                'permission_callback' => array(__CLASS__, 'doctor_dashboard_permission'),
            ),
            array(
                'methods' => \WP_REST_Server::CREATABLE,
                'callback' => array(__CLASS__, 'doctor_dashboard_update_patient_record'),
                'permission_callback' => array(__CLASS__, 'doctor_dashboard_permission'),
            ),
        ));

        register_rest_route(self::NS, '/doctor-dashboard/patients/(?P<patient_id>\d+)/record/notes', array(
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => array(__CLASS__, 'doctor_dashboard_add_patient_record_note'),
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

        register_rest_route(self::NS, '/doctor-dashboard/profile', array(
            array(
                'methods' => \WP_REST_Server::READABLE,
                'callback' => array(__CLASS__, 'doctor_dashboard_profile'),
                'permission_callback' => array(__CLASS__, 'doctor_dashboard_permission'),
            ),
            array(
                'methods' => \WP_REST_Server::CREATABLE,
                'callback' => array(__CLASS__, 'doctor_dashboard_update_profile'),
                'permission_callback' => array(__CLASS__, 'doctor_dashboard_permission'),
            ),
        ));

        register_rest_route(self::NS, '/doctor-dashboard/profile/upload', array(
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => array(__CLASS__, 'doctor_dashboard_profile_upload'),
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

        register_rest_route(self::NS, '/patient-panel/medical-records', array(
            'methods' => \WP_REST_Server::READABLE,
            'callback' => array(__CLASS__, 'patient_panel_medical_records'),
            'permission_callback' => array(__CLASS__, 'logged_in'),
        ));

        register_rest_route(self::NS, '/appointments/(?P<code>[A-Za-z0-9_-]+)/waiting-list', array(
            'methods' => \WP_REST_Server::READABLE,
            'callback' => array(__CLASS__, 'appointment_waiting_list'),
            'permission_callback' => '__return_true',
        ));

        register_rest_route(self::NS, '/appointments/(?P<code>[A-Za-z0-9_-]+)/survey', array(
            array(
                'methods' => \WP_REST_Server::READABLE,
                'callback' => array(__CLASS__, 'appointment_survey'),
                'permission_callback' => '__return_true',
            ),
            array(
                'methods' => \WP_REST_Server::CREATABLE,
                'callback' => array(__CLASS__, 'submit_appointment_survey'),
                'permission_callback' => '__return_true',
            ),
        ));

        register_rest_route(self::NS, '/appointments/(?P<id>\d+)/receipt', array(
            'methods' => \WP_REST_Server::READABLE,
            'callback' => array(__CLASS__, 'appointment_receipt'),
            'permission_callback' => array(__CLASS__, 'logged_in'),
        ));
    }

    public static function doctors(\WP_REST_Request $request): \WP_REST_Response {
        global $wpdb;

        $limit = max(1, min(50, absint($request->get_param('per_page') ?: 50)));
        $search = sanitize_text_field((string) $request->get_param('search'));
        $specialty_id = absint($request->get_param('specialty_id'));
        $city_id = absint($request->get_param('city_id'));
        $province_id = absint($request->get_param('province_id'));
        $payment_filter = sanitize_key((string) ($request->get_param('payment_filter') ?: $request->get_param('payment_method')));
        $sort = sanitize_key((string) ($request->get_param('sort') ?: $request->get_param('orderby')));
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

        if ($city_id > 0) {
            $where .= ' AND d.city_id = %d';
            $params[] = $city_id;
        }

        if ($province_id > 0) {
            $where .= ' AND d.province_id = %d';
            $params[] = $province_id;
        }

        if ('online' === $payment_filter || in_array($online, array('1', 'yes', 'true'), true)) {
            $where .= ' AND d.allow_online_payment = 1';
        }

        if (in_array($payment_filter, array('clinic', 'pay_at_clinic'), true) || in_array($pay_at_clinic, array('1', 'yes', 'true'), true)) {
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
        if ('first_available' === $sort && is_array($rows)) {
            foreach ($rows as &$row) {
                $next = Booking::next_available((int) $row['id'], 1);
                $row['_next_available_slot'] = $next[0] ?? null;
                $row['_next_available_sort'] = $row['_next_available_slot'] ? $row['_next_available_slot']['date'] . ' ' . $row['_next_available_slot']['start_time'] : '9999-12-31 23:59';
            }
            unset($row);

            usort(
                $rows,
                static function (array $a, array $b): int {
                    return strcmp((string) $a['_next_available_sort'], (string) $b['_next_available_sort']);
                }
            );
        }

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
        $date = self::normalize_rest_date((string) $request->get_param('date'));
        if (!$date) {
            return rest_ensure_response(array());
        }

        return rest_ensure_response(Booking::get_slots(absint($request['id']), $date));
    }

    public static function lock_appointment(\WP_REST_Request $request) {
        $params = $request->get_params();
        $date = self::normalize_rest_date((string) ($params['appointment_date'] ?? ''));
        if (!$date) {
            return new \WP_Error('webtanan_invalid_appointment_date', __('تاریخ نوبت معتبر نیست.', 'webtanan-booking'), array('status' => 400));
        }

        $params['appointment_date'] = $date;
        $result = Booking::lock_appointment($params);

        return is_wp_error($result) ? $result : rest_ensure_response($result);
    }

    public static function pay_appointment(\WP_REST_Request $request) {
        $appointment_id = absint($request->get_param('appointment_id'));
        $lock_token = sanitize_text_field((string) $request->get_param('lock_token'));
        $method = sanitize_key((string) $request->get_param('method'));
        $gateway = sanitize_key((string) ($request->get_param('gateway') ?: $request->get_param('gateway_name')));

        self::attach_current_user_to_locked_appointment($appointment_id, $lock_token);

        if ('wallet' === $method) {
            $result = Wallet::pay_for_appointment($appointment_id, $lock_token);
        } elseif ('pay_at_clinic' === $method) {
            $result = Booking::confirm_pay_at_clinic($appointment_id, $lock_token);
        } else {
            $result = Booking::initiate_payment($appointment_id, $lock_token, $gateway);
        }

        return is_wp_error($result) ? $result : rest_ensure_response($result);
    }

    private static function attach_current_user_to_locked_appointment(int $appointment_id, string $lock_token): void {
        global $wpdb;

        $user_id = get_current_user_id();
        if ($appointment_id <= 0 || '' === $lock_token || $user_id <= 0) {
            return;
        }

        $wpdb->query(
            $wpdb->prepare(
                'UPDATE ' . DB::table('appointments') . ' SET patient_user_id = %d, updated_at = %s WHERE id = %d AND patient_user_id = 0 AND appointment_status = %s AND lock_token = %s',
                $user_id,
                DB::now(),
                $appointment_id,
                'locked',
                $lock_token
            )
        );
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
        $appointment = Booking::get_appointment($appointment_id);
        if (!$appointment || !Booking::can_current_user_manage_appointment($appointment_id)) {
            return new \WP_Error('webtanan_forbidden', __('شما اجازه لغو این نوبت را ندارید.', 'webtanan-booking'), array('status' => 403));
        }

        $result = Booking::cancel_appointment($appointment_id, self::cancellation_actor_for_current_user($appointment), sanitize_textarea_field((string) $request->get_param('reason')));

        return is_wp_error($result) ? $result : rest_ensure_response($result);
    }

    private static function cancellation_actor_for_current_user(array $appointment): string {
        $user_id = get_current_user_id();

        if ($user_id > 0 && (int) $appointment['patient_user_id'] === $user_id) {
            return 'patient';
        }

        if (current_user_can('webtanan_manage_booking') || current_user_can('manage_options')) {
            return 'admin';
        }

        if (Booking::current_user_can_access_doctor((int) $appointment['doctor_id'])) {
            return Booking::current_user_is_secretary() ? 'secretary' : 'doctor';
        }

        return 'patient';
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

    public static function wallet_topup(\WP_REST_Request $request) {
        $amount = (float) $request->get_param('amount');
        $gateway = sanitize_key((string) ($request->get_param('gateway') ?: $request->get_param('gateway_name')));
        $settings = DB::get_settings();
        $min_amount = max(1000, (float) ($settings['wallet_topup_min_amount'] ?? 10000));
        $max_amount = max($min_amount, (float) ($settings['wallet_topup_max_amount'] ?? 50000000));

        if ($amount < $min_amount || $amount > $max_amount) {
            return new \WP_Error(
                'webtanan_wallet_topup_amount_out_of_range',
                sprintf(__('مبلغ شارژ کیف پول باید بین %s تا %s تومان باشد.', 'webtanan-booking'), number_format_i18n($min_amount), number_format_i18n($max_amount)),
                array('status' => 400)
            );
        }

        $result = Payment_Gateways::initiate_wallet_topup(get_current_user_id(), $amount, $gateway);

        return is_wp_error($result) ? $result : rest_ensure_response($result);
    }

    public static function resume_payment_send_otp(\WP_REST_Request $request) {
        $appointment = self::appointment_by_code_and_mobile((string) $request->get_param('appointment_code'), (string) $request->get_param('mobile'));
        if (is_wp_error($appointment)) {
            return $appointment;
        }

        if (!self::appointment_can_resume_payment($appointment)) {
            return new \WP_Error('webtanan_resume_payment_not_available', __('این نوبت قابل پرداخت مجدد نیست.', 'webtanan-booking'), array('status' => 409));
        }

        $result = OTP::send((string) $appointment['patient_mobile'], 'resume_payment');

        return is_wp_error($result) ? $result : rest_ensure_response(array('sent' => true, 'expires_at' => $result['expires_at'] ?? ''));
    }

    public static function resume_payment_verify(\WP_REST_Request $request) {
        $appointment = self::appointment_by_code_and_mobile((string) $request->get_param('appointment_code'), (string) $request->get_param('mobile'));
        if (is_wp_error($appointment)) {
            return $appointment;
        }

        if (!self::appointment_can_resume_payment($appointment)) {
            return new \WP_Error('webtanan_resume_payment_not_available', __('این نوبت قابل پرداخت مجدد نیست.', 'webtanan-booking'), array('status' => 409));
        }

        $otp = OTP::verify((string) $appointment['patient_mobile'], (string) $request->get_param('otp'), 'resume_payment');
        if (is_wp_error($otp)) {
            return $otp;
        }

        return rest_ensure_response(
            array(
                'verified' => true,
                'resume_token' => Payment_Gateways::resume_token((int) $appointment['id'], (string) $appointment['patient_mobile']),
                'appointment' => self::format_appointment($appointment),
                'nonce' => wp_create_nonce('wp_rest'),
            )
        );
    }

    public static function resume_payment_pay(\WP_REST_Request $request) {
        $verified = Payment_Gateways::verify_resume_token((string) $request->get_param('resume_token'));
        if (is_wp_error($verified)) {
            return $verified;
        }

        $lock = Booking::renew_lock_for_resume((int) $verified['appointment_id'], (string) $verified['mobile']);
        if (is_wp_error($lock)) {
            return $lock;
        }

        if (!empty($lock['status']) && 'already_confirmed' === $lock['status']) {
            return rest_ensure_response($lock);
        }

        $method = sanitize_key((string) ($request->get_param('method') ?: 'gateway'));
        $gateway = sanitize_key((string) ($request->get_param('gateway') ?: $request->get_param('gateway_name')));
        if ('wallet' === $method) {
            $result = Wallet::pay_for_appointment((int) $lock['appointment_id'], (string) $lock['lock_token']);
        } else {
            $result = Booking::initiate_payment((int) $lock['appointment_id'], (string) $lock['lock_token'], $gateway);
        }

        return is_wp_error($result) ? $result : rest_ensure_response(array_merge($lock, $result));
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
                'can_switch_doctors' => self::current_user_can_switch_doctors($doctors),
                'can_edit_profile' => $active_doctor_id ? self::current_user_can_edit_doctor_profile(Booking::get_doctor($active_doctor_id) ?: array()) : false,
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
        $date = self::normalize_rest_date((string) ($request->get_param('date') ?: current_time('Y-m-d')));
        if (!$date) {
            return new \WP_Error('webtanan_invalid_dashboard_date', __('تاریخ داشبورد معتبر نیست.', 'webtanan-booking'), array('status' => 400));
        }
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

        $date = self::normalize_rest_date((string) ($request->get_param('date') ?: current_time('Y-m-d')));
        if (!$date) {
            return new \WP_Error('webtanan_invalid_dashboard_date', __('تاریخ داشبورد معتبر نیست.', 'webtanan-booking'), array('status' => 400));
        }
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
        $date = self::normalize_rest_date((string) ($params['appointment_date'] ?? ''));
        if (!$date) {
            return new \WP_Error('webtanan_invalid_appointment_date', __('تاریخ نوبت معتبر نیست.', 'webtanan-booking'), array('status' => 400));
        }

        $params['doctor_id'] = $doctor_id;
        $params['appointment_date'] = $date;
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

    public static function doctor_dashboard_bulk_cancel_appointments(\WP_REST_Request $request) {
        global $wpdb;

        $doctor_id = self::current_dashboard_doctor_id($request);
        if (!$doctor_id || !Booking::current_user_can_access_doctor($doctor_id)) {
            return new \WP_Error('webtanan_doctor_context_missing', __('دسترسی پزشک پیدا نشد.', 'webtanan-booking'), array('status' => 403));
        }

        $ids = self::sanitize_id_list($request->get_param('appointment_ids'));
        $date = self::normalize_rest_date((string) $request->get_param('date'));
        if (!$ids && $date) {
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
        }

        if (!$ids) {
            return new \WP_Error('webtanan_bulk_cancel_empty', __('هیچ نوبت قابل لغوی انتخاب نشده است.', 'webtanan-booking'), array('status' => 400));
        }

        $allowed_ids = array();
        foreach ($ids as $appointment_id) {
            $appointment = Booking::get_appointment($appointment_id);
            if ($appointment && (int) $appointment['doctor_id'] === $doctor_id && Booking::current_user_can_access_doctor($doctor_id)) {
                $allowed_ids[] = $appointment_id;
            }
        }

        if (!$allowed_ids) {
            return new \WP_Error('webtanan_bulk_cancel_forbidden', __('نوبت‌های انتخاب‌شده برای این پزشک قابل مدیریت نیستند.', 'webtanan-booking'), array('status' => 403));
        }

        $actor = current_user_can('webtanan_manage_booking') || current_user_can('manage_options') ? 'admin' : (Booking::current_user_is_secretary() ? 'secretary' : 'doctor');
        $summary = Booking::bulk_cancel_appointments($allowed_ids, $actor, sanitize_textarea_field((string) $request->get_param('reason')));
        SMS::send_doctor_notification(
            $doctor_id,
            'bulk_appointment_cancelled',
            array(
                'date' => $date ?: current_time('Y-m-d'),
                'status' => 'cancelled',
                'reason' => sanitize_textarea_field((string) $request->get_param('reason')),
                'amount' => (string) ($summary['refund_total'] ?? 0),
            )
        );

        return rest_ensure_response($summary);
    }

    public static function doctor_dashboard_calendar(\WP_REST_Request $request) {
        $doctor_id = self::current_dashboard_doctor_id($request);
        if (!$doctor_id) {
            return new \WP_Error('webtanan_doctor_context_missing', __('دسترسی پزشک پیدا نشد.', 'webtanan-booking'), array('status' => 403));
        }

        $date = self::normalize_rest_date((string) ($request->get_param('date') ?: current_time('Y-m-d')));
        if (!$date) {
            return rest_ensure_response(array());
        }

        return rest_ensure_response(Booking::get_slots($doctor_id, $date));
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

        $from = self::normalize_rest_date((string) ($request->get_param('from') ?: current_time('Y-m-d')));
        if (!$from) {
            return new \WP_Error('webtanan_invalid_exception_date', __('تاریخ استثنا معتبر نیست.', 'webtanan-booking'), array('status' => 400));
        }
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

        $date = self::normalize_rest_date((string) $request->get_param('exception_date'));
        if (!$date) {
            return new \WP_Error('webtanan_invalid_exception_date', __('تاریخ استثنا معتبر نیست.', 'webtanan-booking'), array('status' => 400));
        }
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
                    MAX(patient_user_id) AS patient_user_id,
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

    public static function doctor_dashboard_patient_record(\WP_REST_Request $request) {
        $doctor_id = self::current_dashboard_doctor_id($request);
        $patient_id = absint($request['patient_id']);
        if (!$doctor_id || !$patient_id || !self::doctor_can_access_patient($doctor_id, $patient_id)) {
            return new \WP_Error('webtanan_patient_record_forbidden', __('شما اجازه مشاهده پرونده این بیمار را ندارید.', 'webtanan-booking'), array('status' => 403));
        }

        $record = self::patient_record_payload($doctor_id, $patient_id, true);

        return rest_ensure_response($record);
    }

    public static function doctor_dashboard_update_patient_record(\WP_REST_Request $request) {
        global $wpdb;

        $doctor_id = self::current_dashboard_doctor_id($request);
        $patient_id = absint($request['patient_id']);
        if (!$doctor_id || !$patient_id || !self::doctor_can_access_patient($doctor_id, $patient_id)) {
            return new \WP_Error('webtanan_patient_record_forbidden', __('شما اجازه ویرایش پرونده این بیمار را ندارید.', 'webtanan-booking'), array('status' => 403));
        }

        $record = self::patient_record_payload($doctor_id, $patient_id, true);
        $now = DB::now();
        $data = array(
            'summary' => sanitize_textarea_field((string) $request->get_param('summary')),
            'allergies' => sanitize_textarea_field((string) $request->get_param('allergies')),
            'chronic_conditions' => sanitize_textarea_field((string) $request->get_param('chronic_conditions')),
            'current_medications' => sanitize_textarea_field((string) $request->get_param('current_medications')),
            'updated_by' => get_current_user_id(),
            'updated_at' => $now,
        );

        $wpdb->update(DB::table('patient_records'), $data, array('id' => (int) $record['id']));

        return rest_ensure_response(self::patient_record_payload($doctor_id, $patient_id, false));
    }

    public static function doctor_dashboard_add_patient_record_note(\WP_REST_Request $request) {
        global $wpdb;

        $doctor_id = self::current_dashboard_doctor_id($request);
        $patient_id = absint($request['patient_id']);
        if (!$doctor_id || !$patient_id || !self::doctor_can_access_patient($doctor_id, $patient_id)) {
            return new \WP_Error('webtanan_patient_record_forbidden', __('شما اجازه ویرایش پرونده این بیمار را ندارید.', 'webtanan-booking'), array('status' => 403));
        }

        $record = self::patient_record_payload($doctor_id, $patient_id, true);
        $body = sanitize_textarea_field((string) $request->get_param('body'));
        if ('' === $body) {
            return new \WP_Error('webtanan_patient_record_note_empty', __('متن یادداشت پرونده خالی است.', 'webtanan-booking'), array('status' => 400));
        }

        $visibility = sanitize_key((string) ($request->get_param('visibility') ?: 'patient'));
        if (!in_array($visibility, array('patient', 'private'), true)) {
            $visibility = 'patient';
        }

        $wpdb->insert(
            DB::table('patient_record_notes'),
            array(
                'record_id' => (int) $record['id'],
                'appointment_id' => absint($request->get_param('appointment_id')),
                'author_user_id' => get_current_user_id(),
                'note_type' => sanitize_key((string) ($request->get_param('note_type') ?: 'visit')),
                'title' => sanitize_text_field((string) $request->get_param('title')),
                'body' => $body,
                'visibility' => $visibility,
                'created_at' => DB::now(),
            )
        );

        return rest_ensure_response(self::patient_record_payload($doctor_id, $patient_id, false));
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

    public static function doctor_dashboard_profile(\WP_REST_Request $request) {
        $doctor_id = self::current_dashboard_doctor_id($request);
        $doctor = $doctor_id ? Booking::get_doctor($doctor_id) : null;
        if (!$doctor) {
            return new \WP_Error('webtanan_doctor_context_missing', __('دسترسی پزشک پیدا نشد.', 'webtanan-booking'), array('status' => 403));
        }

        return rest_ensure_response(self::profile_payload($doctor));
    }

    public static function doctor_dashboard_update_profile(\WP_REST_Request $request) {
        global $wpdb;

        $doctor_id = self::current_dashboard_doctor_id($request);
        $doctor = $doctor_id ? Booking::get_doctor($doctor_id) : null;
        if (!$doctor || !self::current_user_can_edit_doctor_profile($doctor)) {
            return new \WP_Error('webtanan_profile_forbidden', __('شما اجازه ویرایش این پروفایل را ندارید.', 'webtanan-booking'), array('status' => 403));
        }

        $post_id = absint($doctor['post_id'] ?? 0);
        $title = sanitize_text_field((string) $request->get_param('title'));
        $excerpt = sanitize_textarea_field((string) $request->get_param('summary'));
        $biography = wp_kses_post((string) $request->get_param('biography'));
        $thumbnail_id = absint($request->get_param('thumbnail_id'));
        $gallery_ids = self::sanitize_attachment_ids($request->get_param('gallery_ids'));

        if ($post_id > 0) {
            $post_data = array('ID' => $post_id);
            if ('' !== $title) {
                $post_data['post_title'] = $title;
            }
            $post_data['post_excerpt'] = $excerpt;
            $post_data['post_content'] = $biography;
            wp_update_post($post_data, true);

            if ($thumbnail_id > 0 && wp_attachment_is_image($thumbnail_id)) {
                set_post_thumbnail($post_id, $thumbnail_id);
            }
            update_post_meta($post_id, '_webtanan_doctor_gallery_ids', $gallery_ids);
        }

        $wpdb->update(
            DB::table('doctors'),
            array(
                'medical_system_number' => sanitize_text_field((string) $request->get_param('medical_system_number')),
                'clinic_name' => sanitize_text_field((string) $request->get_param('clinic_name')),
                'clinic_address' => sanitize_textarea_field((string) $request->get_param('clinic_address')),
                'clinic_phone' => sanitize_text_field((string) $request->get_param('clinic_phone')),
                'iban' => sanitize_text_field((string) $request->get_param('iban')),
                'bank_account_owner' => sanitize_text_field((string) $request->get_param('bank_account_owner')),
                'updated_at' => DB::now(),
            ),
            array('id' => $doctor_id)
        );

        $updated = Booking::get_doctor($doctor_id);

        return rest_ensure_response(self::profile_payload($updated ?: $doctor));
    }

    public static function doctor_dashboard_profile_upload(\WP_REST_Request $request) {
        $doctor_id = self::current_dashboard_doctor_id($request);
        $doctor = $doctor_id ? Booking::get_doctor($doctor_id) : null;
        if (!$doctor || !self::current_user_can_edit_doctor_profile($doctor)) {
            return new \WP_Error('webtanan_profile_upload_forbidden', __('شما اجازه آپلود تصویر برای این پروفایل را ندارید.', 'webtanan-booking'), array('status' => 403));
        }

        $files = $request->get_file_params();
        if (empty($files['file'])) {
            return new \WP_Error('webtanan_profile_upload_missing_file', __('فایل تصویر ارسال نشده است.', 'webtanan-booking'), array('status' => 400));
        }

        $file = $files['file'];
        if (!empty($file['size']) && (int) $file['size'] > 5 * MB_IN_BYTES) {
            return new \WP_Error('webtanan_profile_upload_too_large', __('حجم تصویر باید کمتر از ۵ مگابایت باشد.', 'webtanan-booking'), array('status' => 400));
        }

        $allowed_mimes = array(
            'jpg|jpeg|jpe' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
        );

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $upload = wp_handle_upload($file, array('test_form' => false, 'mimes' => $allowed_mimes));
        if (!empty($upload['error'])) {
            return new \WP_Error('webtanan_profile_upload_failed', sanitize_text_field($upload['error']), array('status' => 400));
        }

        $attachment_id = wp_insert_attachment(
            array(
                'post_mime_type' => sanitize_mime_type((string) $upload['type']),
                'post_title' => sanitize_file_name(pathinfo((string) $upload['file'], PATHINFO_FILENAME)),
                'post_content' => '',
                'post_status' => 'inherit',
            ),
            (string) $upload['file'],
            absint($doctor['post_id'] ?? 0)
        );

        if (is_wp_error($attachment_id)) {
            return $attachment_id;
        }

        $metadata = wp_generate_attachment_metadata((int) $attachment_id, (string) $upload['file']);
        wp_update_attachment_metadata((int) $attachment_id, $metadata);

        return rest_ensure_response(
            array(
                'id' => (int) $attachment_id,
                'url' => esc_url_raw(wp_get_attachment_url((int) $attachment_id)),
                'thumbnail' => esc_url_raw(wp_get_attachment_image_url((int) $attachment_id, 'medium') ?: wp_get_attachment_url((int) $attachment_id)),
            )
        );
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
        global $wpdb;

        $user_id = get_current_user_id();
        $ledger_table = DB::table('wallets_ledger');
        $appointments_table = DB::table('appointments');
        $ledger = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT l.*, a.appointment_code
                FROM $ledger_table l
                LEFT JOIN $appointments_table a ON a.id = l.related_appointment_id
                WHERE l.user_id = %d AND l.user_type = %s
                ORDER BY l.id DESC
                LIMIT 100",
                $user_id,
                'patient'
            ),
            ARRAY_A
        );

        return rest_ensure_response(
            array(
                'balance' => Wallet::balance($user_id, 'patient'),
                'ledger' => $ledger,
            )
        );
    }

    public static function patient_panel_medical_records(\WP_REST_Request $request): \WP_REST_Response {
        global $wpdb;

        $user_id = get_current_user_id();
        $records_table = DB::table('patient_records');
        $doctors_table = DB::table('doctors');
        $notes_table = DB::table('patient_record_notes');

        $records = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT r.*, d.post_id, d.clinic_name
                FROM $records_table r
                LEFT JOIN $doctors_table d ON d.id = r.doctor_id
                WHERE r.patient_user_id = %d
                ORDER BY r.updated_at DESC
                LIMIT 100",
                $user_id
            ),
            ARRAY_A
        );

        foreach ($records as &$record) {
            $record['doctor_title'] = !empty($record['post_id']) ? html_entity_decode(get_the_title((int) $record['post_id']), ENT_QUOTES, get_bloginfo('charset')) : ($record['clinic_name'] ?? '');
            $record['notes'] = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT id, appointment_id, note_type, title, body, created_at
                    FROM $notes_table
                    WHERE record_id = %d AND visibility = 'patient'
                    ORDER BY id DESC
                    LIMIT 100",
                    (int) $record['id']
                ),
                ARRAY_A
            );
        }
        unset($record);

        return rest_ensure_response($records);
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

    public static function appointment_waiting_list(\WP_REST_Request $request) {
        $appointment = self::appointment_by_code((string) $request['code']);
        if (!$appointment || !SMS::verify_appointment_token($appointment, 'waiting-list', (string) $request->get_param('token'))) {
            return new \WP_Error('webtanan_waiting_list_forbidden', __('لینک صف انتظار معتبر نیست.', 'webtanan-booking'), array('status' => 403));
        }

        return rest_ensure_response(
            array_merge(
                Booking::waiting_list_snapshot($appointment),
                array(
                    'appointment_code' => $appointment['appointment_code'],
                    'date' => $appointment['appointment_date'],
                    'time' => substr((string) $appointment['start_time'], 0, 5),
                )
            )
        );
    }

    public static function appointment_survey(\WP_REST_Request $request) {
        $appointment = self::appointment_by_code((string) $request['code']);
        if (!$appointment || !SMS::verify_appointment_token($appointment, 'survey', (string) $request->get_param('token'))) {
            return new \WP_Error('webtanan_survey_forbidden', __('لینک نظرسنجی معتبر نیست.', 'webtanan-booking'), array('status' => 403));
        }

        $doctor = Booking::get_doctor((int) $appointment['doctor_id']);

        return rest_ensure_response(
            array(
                'appointment_code' => $appointment['appointment_code'],
                'doctor_name' => $doctor && !empty($doctor['post_id']) ? html_entity_decode(get_the_title((int) $doctor['post_id']), ENT_QUOTES, get_bloginfo('charset')) : '',
                'date' => $appointment['appointment_date'],
                'time' => substr((string) $appointment['start_time'], 0, 5),
                'message' => __('از همراهی شما ممنونیم. امتیاز و تجربه خود را ثبت کنید.', 'webtanan-booking'),
            )
        );
    }

    public static function submit_appointment_survey(\WP_REST_Request $request) {
        global $wpdb;

        $appointment = self::appointment_by_code((string) $request['code']);
        if (!$appointment || !SMS::verify_appointment_token($appointment, 'survey', (string) $request->get_param('token'))) {
            return new \WP_Error('webtanan_survey_forbidden', __('لینک نظرسنجی معتبر نیست.', 'webtanan-booking'), array('status' => 403));
        }

        $rating = max(1, min(5, absint($request->get_param('rating'))));
        $feedback = sanitize_textarea_field((string) $request->get_param('feedback'));
        $public_consent = $request->get_param('public_consent');
        $public_consent = null === $public_consent ? true : (bool) $public_consent;
        $now = DB::now();
        $table = DB::table('survey_responses');
        $existing_id = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE appointment_id = %d", (int) $appointment['id']));
        $data = array(
            'appointment_id' => (int) $appointment['id'],
            'doctor_id' => (int) $appointment['doctor_id'],
            'patient_user_id' => (int) $appointment['patient_user_id'],
            'rating' => $rating,
            'feedback' => $feedback,
            'public_consent' => $public_consent ? 1 : 0,
            'status' => $public_consent ? 'pending' : 'private',
            'token_hash' => hash('sha256', (string) $request->get_param('token')),
            'updated_at' => $now,
        );

        if ($existing_id) {
            $wpdb->update($table, $data, array('id' => $existing_id));
        } else {
            $data['created_at'] = $now;
            $wpdb->insert($table, $data);
        }

        if ($public_consent) {
            self::create_pending_survey_comment($appointment, $rating, $feedback);
        }

        return rest_ensure_response(array('success' => true, 'status' => $public_consent ? 'pending' : 'private'));
    }

    private static function sanitize_id_list($raw): array {
        if (is_string($raw)) {
            $raw = preg_split('/[\s,]+/', $raw);
        }
        if (!is_array($raw)) {
            return array();
        }

        return array_values(array_unique(array_filter(array_map('absint', $raw))));
    }

    private static function appointment_by_code(string $code): ?array {
        global $wpdb;

        $code = sanitize_text_field($code);
        if ('' === $code) {
            return null;
        }

        $row = $wpdb->get_row(
            $wpdb->prepare('SELECT * FROM ' . DB::table('appointments') . ' WHERE appointment_code = %s LIMIT 1', $code),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    private static function doctor_can_access_patient(int $doctor_id, int $patient_user_id): bool {
        global $wpdb;

        if ($doctor_id <= 0 || $patient_user_id <= 0 || !Booking::current_user_can_access_doctor($doctor_id)) {
            return false;
        }

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                'SELECT COUNT(*) FROM ' . DB::table('appointments') . ' WHERE doctor_id = %d AND patient_user_id = %d',
                $doctor_id,
                $patient_user_id
            )
        ) > 0;
    }

    private static function patient_record_payload(int $doctor_id, int $patient_user_id, bool $create): array {
        global $wpdb;

        $records_table = DB::table('patient_records');
        $notes_table = DB::table('patient_record_notes');
        $appointments_table = DB::table('appointments');
        $record = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $records_table WHERE doctor_id = %d AND patient_user_id = %d LIMIT 1", $doctor_id, $patient_user_id),
            ARRAY_A
        );

        if (!$record && $create) {
            $patient = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT patient_mobile, patient_national_code
                    FROM $appointments_table
                    WHERE doctor_id = %d AND patient_user_id = %d
                    ORDER BY appointment_date DESC, start_time DESC
                    LIMIT 1",
                    $doctor_id,
                    $patient_user_id
                ),
                ARRAY_A
            );
            $now = DB::now();
            $wpdb->insert(
                $records_table,
                array(
                    'doctor_id' => $doctor_id,
                    'patient_user_id' => $patient_user_id,
                    'patient_mobile' => $patient['patient_mobile'] ?? '',
                    'patient_national_code' => $patient['patient_national_code'] ?? '',
                    'summary' => '',
                    'allergies' => '',
                    'chronic_conditions' => '',
                    'current_medications' => '',
                    'created_by' => get_current_user_id(),
                    'updated_by' => get_current_user_id(),
                    'created_at' => $now,
                    'updated_at' => $now,
                )
            );
            $record = $wpdb->get_row($wpdb->prepare("SELECT * FROM $records_table WHERE id = %d", (int) $wpdb->insert_id), ARRAY_A);
        }

        if (!$record) {
            return array();
        }

        $notes = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT *
                FROM $notes_table
                WHERE record_id = %d
                ORDER BY id DESC
                LIMIT 100",
                (int) $record['id']
            ),
            ARRAY_A
        );

        $patient = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT patient_first_name, patient_last_name, patient_mobile, patient_national_code
                FROM $appointments_table
                WHERE doctor_id = %d AND patient_user_id = %d
                ORDER BY appointment_date DESC, start_time DESC
                LIMIT 1",
                $doctor_id,
                $patient_user_id
            ),
            ARRAY_A
        );

        $record['patient_full_name'] = $patient ? trim($patient['patient_first_name'] . ' ' . $patient['patient_last_name']) : '';
        $record['patient_mobile'] = $record['patient_mobile'] ?: ($patient['patient_mobile'] ?? '');
        $record['patient_national_code'] = $record['patient_national_code'] ?: ($patient['patient_national_code'] ?? '');
        $record['notes'] = $notes;

        return $record;
    }

    private static function create_pending_survey_comment(array $appointment, int $rating, string $feedback): void {
        global $wpdb;

        $doctor = Booking::get_doctor((int) $appointment['doctor_id']);
        $post_id = $doctor ? absint($doctor['post_id'] ?? 0) : 0;
        if ($post_id <= 0) {
            return;
        }

        $exists = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*)
                FROM $wpdb->commentmeta cm
                INNER JOIN $wpdb->comments c ON c.comment_ID = cm.comment_id
                WHERE c.comment_post_ID = %d AND cm.meta_key = %s AND cm.meta_value = %d",
                $post_id,
                '_webtanan_survey_appointment_id',
                (int) $appointment['id']
            )
        );
        if ($exists > 0) {
            return;
        }

        $comment_id = wp_insert_comment(
            array(
                'comment_post_ID' => $post_id,
                'comment_author' => trim($appointment['patient_first_name'] . ' ' . $appointment['patient_last_name']) ?: __('بیمار', 'webtanan-booking'),
                'comment_author_email' => '',
                'comment_author_url' => '',
                'comment_content' => $feedback ?: sprintf(__('امتیاز ثبت‌شده: %d از ۵', 'webtanan-booking'), $rating),
                'comment_type' => 'comment',
                'comment_parent' => 0,
                'user_id' => (int) $appointment['patient_user_id'],
                'comment_approved' => 0,
            )
        );

        if ($comment_id) {
            add_comment_meta($comment_id, '_webtanan_rating', $rating, true);
            add_comment_meta($comment_id, '_webtanan_survey_appointment_id', (int) $appointment['id'], true);
        }
    }

    public static function logged_in(?\WP_REST_Request $request = null): bool {
        return is_user_logged_in() && self::valid_rest_nonce($request);
    }

    public static function finance_permission(?\WP_REST_Request $request = null): bool {
        return self::logged_in($request) && (current_user_can('webtanan_manage_finance') || current_user_can('manage_options'));
    }

    public static function doctor_dashboard_permission(?\WP_REST_Request $request = null): bool {
        return self::logged_in($request) && (current_user_can('webtanan_access_doctor_dashboard') || current_user_can('webtanan_access_secretary_dashboard') || current_user_can('webtanan_manage_booking') || current_user_can('manage_options'));
    }

    private static function valid_rest_nonce(?\WP_REST_Request $request = null): bool {
        $nonce = '';
        if ($request) {
            $nonce = sanitize_text_field((string) ($request->get_header('X-WP-Nonce') ?: $request->get_header('x_wp_nonce')));
            if ('' === $nonce) {
                $nonce = sanitize_text_field((string) $request->get_param('_wpnonce'));
            }
        } elseif (isset($_SERVER['HTTP_X_WP_NONCE'])) {
            $nonce = sanitize_text_field(wp_unslash($_SERVER['HTTP_X_WP_NONCE']));
        }

        return '' !== $nonce && (bool) wp_verify_nonce($nonce, 'wp_rest');
    }

    private static function current_user_can_switch_doctors(array $doctors): bool {
        if (current_user_can('webtanan_manage_booking') || current_user_can('manage_options')) {
            return count($doctors) > 1;
        }

        return Booking::current_user_is_secretary() && count($doctors) > 1;
    }

    private static function current_user_can_edit_doctor_profile(array $doctor): bool {
        if (!$doctor) {
            return false;
        }

        if (current_user_can('webtanan_manage_booking') || current_user_can('manage_options')) {
            return true;
        }

        return get_current_user_id() > 0 && (int) ($doctor['user_id'] ?? 0) === get_current_user_id();
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
            if (Booking::current_user_is_secretary()) {
                $assigned = Booking::assigned_doctor_ids_for_user($user_id);
                if (!$assigned) {
                    return array();
                }

                $placeholders = implode(',', array_fill(0, count($assigned), '%d'));
                $where = "d.id IN ($placeholders)";
                foreach ($assigned as $assigned_id) {
                    $params[] = (int) $assigned_id;
                }
            } else {
                $where = 'd.user_id = %d';
                $params[] = $user_id;
            }
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

        if (!Booking::current_user_can_access_doctor($doctor_id)) {
            return false;
        }

        $user_id = get_current_user_id();
        if ($user_id > 0 && (int) $doctor['user_id'] === $user_id) {
            return true;
        }

        return Booking::current_user_is_secretary() && 'yes' === get_user_meta($user_id, 'webtanan_secretary_can_view_finance', true);
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

    private static function appointment_by_code_and_mobile(string $appointment_code, string $mobile) {
        global $wpdb;

        $appointment_code = sanitize_text_field($appointment_code);
        $mobile = OTP::normalize_mobile($mobile);
        if ('' === $appointment_code || '' === $mobile) {
            return new \WP_Error('webtanan_resume_input_missing', __('کد نوبت و شماره موبایل را وارد کنید.', 'webtanan-booking'), array('status' => 400));
        }

        $appointment = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT * FROM ' . DB::table('appointments') . ' WHERE appointment_code = %s AND patient_mobile = %s ORDER BY id DESC LIMIT 1',
                $appointment_code,
                $mobile
            ),
            ARRAY_A
        );

        if (!$appointment) {
            return new \WP_Error('webtanan_resume_appointment_not_found', __('نوبتی با این کد و شماره موبایل پیدا نشد.', 'webtanan-booking'), array('status' => 404));
        }

        return $appointment;
    }

    private static function appointment_can_resume_payment(array $appointment): bool {
        if (in_array((string) $appointment['appointment_status'], array('confirmed', 'cancelled', 'completed', 'no_show'), true)) {
            return false;
        }

        return in_array((string) $appointment['payment_status'], array('unpaid', 'failed'), true);
    }

    private static function format_doctor(array $row): array {
        $post_id = (int) $row['post_id'];

        $doctor = array(
            'id' => (int) $row['id'],
            'post_id' => $post_id,
            'title' => html_entity_decode(get_the_title($post_id), ENT_QUOTES, get_bloginfo('charset')),
            'permalink' => get_permalink($post_id),
            'specialty_id' => (int) $row['specialty_id'],
            'specialty_name' => $row['specialty_name'] ?? '',
            'city_id' => (int) ($row['city_id'] ?? 0),
            'province_id' => (int) ($row['province_id'] ?? 0),
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

        if (array_key_exists('_next_available_slot', $row)) {
            $doctor['next_available'] = $row['_next_available_slot'];
        }

        return $doctor;
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
            'booking_source' => in_array((string) $row['payment_method'], array('pay_at_clinic', 'cash_at_clinic', 'pos_at_clinic'), true) ? 'clinic' : 'online',
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

    private static function profile_payload(array $doctor): array {
        $post_id = absint($doctor['post_id'] ?? 0);
        $thumbnail_id = $post_id > 0 ? (int) get_post_thumbnail_id($post_id) : 0;
        $gallery_ids = $post_id > 0 ? get_post_meta($post_id, '_webtanan_doctor_gallery_ids', true) : array();
        if (is_string($gallery_ids)) {
            $gallery_ids = array_filter(array_map('absint', explode(',', $gallery_ids)));
        }
        if (!is_array($gallery_ids)) {
            $gallery_ids = array();
        }

        return array(
            'doctor_id' => (int) $doctor['id'],
            'post_id' => $post_id,
            'title' => $post_id ? html_entity_decode(get_the_title($post_id), ENT_QUOTES, get_bloginfo('charset')) : '',
            'summary' => $post_id ? wp_strip_all_tags((string) get_post_field('post_excerpt', $post_id)) : '',
            'biography' => $post_id ? (string) get_post_field('post_content', $post_id) : '',
            'medical_system_number' => (string) ($doctor['medical_system_number'] ?? ''),
            'clinic_name' => (string) ($doctor['clinic_name'] ?? ''),
            'clinic_address' => (string) ($doctor['clinic_address'] ?? ''),
            'clinic_phone' => (string) ($doctor['clinic_phone'] ?? ''),
            'iban' => (string) ($doctor['iban'] ?? ''),
            'bank_account_owner' => (string) ($doctor['bank_account_owner'] ?? ''),
            'thumbnail_id' => $thumbnail_id,
            'thumbnail' => $thumbnail_id > 0 ? esc_url_raw(wp_get_attachment_image_url($thumbnail_id, 'medium') ?: wp_get_attachment_url($thumbnail_id)) : '',
            'gallery_ids' => array_values(array_map('absint', $gallery_ids)),
            'gallery' => self::doctor_gallery_urls($post_id),
        );
    }

    private static function sanitize_attachment_ids($value): array {
        if (is_string($value)) {
            $value = explode(',', $value);
        }
        if (!is_array($value)) {
            return array();
        }

        $ids = array_values(array_unique(array_filter(array_map('absint', $value))));
        $valid = array();
        foreach ($ids as $id) {
            if ($id > 0 && wp_attachment_is_image($id)) {
                $valid[] = $id;
            }
        }

        return $valid;
    }

    private static function normalize_rest_date(string $raw_date): string {
        $date = trim(self::normalize_digits(sanitize_text_field($raw_date)));
        if ('' === $date) {
            return '';
        }

        $date = str_replace(array('/', '.', ' '), '-', $date);
        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})/', $date, $matches)) {
            $year = (int) $matches[1];
            $month = (int) $matches[2];
            $day = (int) $matches[3];

            if ($year >= 1700) {
                return checkdate($month, $day, $year) ? sprintf('%04d-%02d-%02d', $year, $month, $day) : '';
            }

            if ($year >= 1200 && $year <= 1600 && self::is_valid_jalali_date($year, $month, $day)) {
                $gregorian = self::jalali_to_gregorian($year, $month, $day);

                return sprintf('%04d-%02d-%02d', $gregorian['gy'], $gregorian['gm'], $gregorian['gd']);
            }
        }

        $timestamp = strtotime($date);
        if (!$timestamp) {
            return '';
        }

        return gmdate('Y-m-d', $timestamp);
    }

    private static function latin_digits(string $value): string {
        return strtr(
            $value,
            array(
                '۰' => '0',
                '۱' => '1',
                '۲' => '2',
                '۳' => '3',
                '۴' => '4',
                '۵' => '5',
                '۶' => '6',
                '۷' => '7',
                '۸' => '8',
                '۹' => '9',
                '٠' => '0',
                '١' => '1',
                '٢' => '2',
                '٣' => '3',
                '٤' => '4',
                '٥' => '5',
                '٦' => '6',
                '٧' => '7',
                '٨' => '8',
                '٩' => '9',
            )
        );
    }

    private static function is_valid_jalali_date(int $year, int $month, int $day): bool {
        if ($year < 1200 || $year > 1600 || $month < 1 || $month > 12 || $day < 1) {
            return false;
        }

        if ($month <= 6) {
            return $day <= 31;
        }

        if ($month <= 11) {
            return $day <= 30;
        }

        return $day <= 30;
    }

    private static function normalize_digits(string $value): string {
        return strtr(
            $value,
            array(
                '۰' => '0',
                '۱' => '1',
                '۲' => '2',
                '۳' => '3',
                '۴' => '4',
                '۵' => '5',
                '۶' => '6',
                '۷' => '7',
                '۸' => '8',
                '۹' => '9',
                '٠' => '0',
                '١' => '1',
                '٢' => '2',
                '٣' => '3',
                '٤' => '4',
                '٥' => '5',
                '٦' => '6',
                '٧' => '7',
                '٨' => '8',
                '٩' => '9',
            )
        );
    }

    private static function is_jalali_leap_year(int $year): bool {
        $breaks = array(-61, 9, 38, 199, 426, 686, 756, 818, 1111, 1181, 1210, 1635, 2060, 2097, 2192, 2262, 2324, 2394, 2456, 3178);
        $gy = $year + 621;
        $leap_j = -14;
        $jp = $breaks[0];

        foreach (array_slice($breaks, 1) as $jm) {
            $jump = $jm - $jp;
            if ($year < $jm) {
                break;
            }
            $leap_j += self::jdiv($jump, 33) * 8 + self::jdiv($jump % 33, 4);
            $jp = $jm;
        }

        $n = $year - $jp;
        $leap_j += self::jdiv($n, 33) * 8 + self::jdiv(($n % 33) + 3, 4);
        if (($jump % 33) === 4 && $jump - $n === 4) {
            $leap_j++;
        }

        $leap_g = self::jdiv($gy, 4) - self::jdiv((self::jdiv($gy, 100) + 1) * 3, 4) - 150;
        $march = 20 + $leap_j - $leap_g;
        $unused = $march;
        $leap = (($n + 1) % 33) - 1;
        if ($leap === -1) {
            $leap = 4;
        }

        return 0 === $leap;
    }

    private static function jalali_to_gregorian(int $jy, int $jm, int $jd): array {
        $jy += 1595;
        $days = -355668 + (365 * $jy) + (self::jdiv($jy, 33) * 8) + self::jdiv(($jy % 33) + 3, 4) + $jd + ($jm < 7 ? ($jm - 1) * 31 : (($jm - 7) * 30) + 186);
        $gy = 400 * self::jdiv($days, 146097);
        $days %= 146097;

        if ($days > 36524) {
            $gy += 100 * self::jdiv(--$days, 36524);
            $days %= 36524;
            if ($days >= 365) {
                $days++;
            }
        }

        $gy += 4 * self::jdiv($days, 1461);
        $days %= 1461;

        if ($days > 365) {
            $gy += self::jdiv($days - 1, 365);
            $days = ($days - 1) % 365;
        }

        $gd = $days + 1;
        $sal = array(0, 31, self::is_gregorian_leap_year($gy) ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31);
        $gm = 1;

        while ($gm <= 12 && $gd > $sal[$gm]) {
            $gd -= $sal[$gm];
            $gm++;
        }

        return array('gy' => $gy, 'gm' => $gm, 'gd' => $gd);
    }

    private static function is_gregorian_leap_year(int $year): bool {
        return (0 === $year % 4 && 0 !== $year % 100) || 0 === $year % 400;
    }

    private static function jdiv(int $a, int $b): int {
        return (int) floor($a / $b);
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
