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
            'permission_callback' => array(__CLASS__, 'logged_in'),
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

        register_rest_route(self::NS, '/auth/context', array(
            'methods' => \WP_REST_Server::READABLE,
            'callback' => array(__CLASS__, 'auth_context'),
            'permission_callback' => '__return_true',
        ));

        register_rest_route(self::NS, '/auth/verify-otp', array(
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => array(__CLASS__, 'verify_otp'),
            'permission_callback' => '__return_true',
        ));

        register_rest_route(self::NS, '/auth/complete-profile', array(
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => array(__CLASS__, 'complete_patient_profile'),
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

        register_rest_route(self::NS, '/doctor-dashboard/schedules/(?P<id>\d+)', array(
            'methods' => \WP_REST_Server::DELETABLE,
            'callback' => array(__CLASS__, 'doctor_dashboard_delete_schedule'),
            'permission_callback' => array(__CLASS__, 'doctor_dashboard_permission'),
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

        register_rest_route(self::NS, '/doctor-dashboard/exceptions/(?P<id>\d+)', array(
            'methods' => \WP_REST_Server::DELETABLE,
            'callback' => array(__CLASS__, 'doctor_dashboard_delete_exception'),
            'permission_callback' => array(__CLASS__, 'doctor_dashboard_permission'),
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

        register_rest_route(self::NS, '/doctor-dashboard/patients/(?P<patient_id>\d+)/record/files', array(
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => array(__CLASS__, 'doctor_dashboard_upload_patient_record_file'),
            'permission_callback' => array(__CLASS__, 'doctor_dashboard_permission'),
        ));

        register_rest_route(self::NS, '/doctor-dashboard/patients/(?P<patient_id>\d+)/record/audit', array(
            'methods' => \WP_REST_Server::READABLE,
            'callback' => array(__CLASS__, 'doctor_dashboard_patient_record_audit'),
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

        register_rest_route(self::NS, '/patient-panel/profile', array(
            array(
                'methods' => \WP_REST_Server::READABLE,
                'callback' => array(__CLASS__, 'patient_panel_profile'),
                'permission_callback' => array(__CLASS__, 'logged_in'),
            ),
            array(
                'methods' => \WP_REST_Server::CREATABLE,
                'callback' => array(__CLASS__, 'patient_panel_update_profile'),
                'permission_callback' => array(__CLASS__, 'logged_in'),
            ),
        ));

        register_rest_route(self::NS, '/patient-panel/dependents', array(
            array(
                'methods' => \WP_REST_Server::READABLE,
                'callback' => array(__CLASS__, 'patient_panel_dependents'),
                'permission_callback' => array(__CLASS__, 'logged_in'),
            ),
            array(
                'methods' => \WP_REST_Server::CREATABLE,
                'callback' => array(__CLASS__, 'patient_panel_save_dependent'),
                'permission_callback' => array(__CLASS__, 'logged_in'),
            ),
        ));

        register_rest_route(self::NS, '/patient-panel/dependents/(?P<id>[a-zA-Z0-9_-]+)', array(
            'methods' => \WP_REST_Server::DELETABLE,
            'callback' => array(__CLASS__, 'patient_panel_delete_dependent'),
            'permission_callback' => array(__CLASS__, 'logged_in'),
        ));

        register_rest_route(self::NS, '/patient-panel/favorites', array(
            'methods' => \WP_REST_Server::READABLE,
            'callback' => array(__CLASS__, 'patient_panel_favorites'),
            'permission_callback' => array(__CLASS__, 'logged_in'),
        ));

        register_rest_route(self::NS, '/patient-panel/favorites/(?P<doctor_id>\d+)', array(
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => array(__CLASS__, 'patient_panel_toggle_favorite'),
            'permission_callback' => array(__CLASS__, 'logged_in'),
        ));

        register_rest_route(self::NS, '/patient-panel/appointments', array(
            'methods' => \WP_REST_Server::READABLE,
            'callback' => array(__CLASS__, 'patient_panel_appointments'),
            'permission_callback' => array(__CLASS__, 'logged_in'),
        ));

        register_rest_route(self::NS, '/patient-panel/appointments/(?P<id>\d+)/resume', array(
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => array(__CLASS__, 'patient_panel_resume_appointment'),
            'permission_callback' => array(__CLASS__, 'logged_in'),
        ));

        register_rest_route(self::NS, '/patient-panel/appointments/(?P<id>\d+)/survey-link', array(
            'methods' => \WP_REST_Server::READABLE,
            'callback' => array(__CLASS__, 'patient_panel_survey_link'),
            'permission_callback' => array(__CLASS__, 'logged_in'),
        ));

        register_rest_route(self::NS, '/patient-panel/appointments/(?P<id>\d+)/survey', array(
            array(
                'methods' => \WP_REST_Server::READABLE,
                'callback' => array(__CLASS__, 'patient_panel_appointment_survey'),
                'permission_callback' => array(__CLASS__, 'logged_in'),
            ),
            array(
                'methods' => \WP_REST_Server::CREATABLE,
                'callback' => array(__CLASS__, 'patient_panel_submit_appointment_survey'),
                'permission_callback' => array(__CLASS__, 'logged_in'),
            ),
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
        $available_only = in_array(sanitize_key((string) $request->get_param('available_only')), array('1', 'yes', 'true'), true);
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

        $order_by = 'd.is_verified DESC, p.post_title ASC';
        if ('first_available' === $sort || $available_only) {
            $order_by = 'CASE WHEN d.next_free_slot_cache IS NULL THEN 1 ELSE 0 END ASC, d.next_free_slot_cache ASC, p.post_title ASC';
        }

        $sql = "SELECT d.*, p.post_title, p.ID AS post_id, s.name AS specialty_name
            FROM " . DB::table('doctors') . ' d
            INNER JOIN ' . $wpdb->posts . " p ON p.ID = d.post_id
            LEFT JOIN " . DB::table('specialties') . " s ON s.id = d.specialty_id
            WHERE $where
            ORDER BY $order_by
            LIMIT %d";
        $params[] = $limit;

        $rows = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);
        if (('first_available' === $sort || $available_only) && is_array($rows)) {
            foreach ($rows as &$row) {
                $cached_slot = null;
                $cached = (string) ($row['next_free_slot_cache'] ?? '');
                if ($cached && $cached > DB::now()) {
                    $cached_date = substr($cached, 0, 10);
                    $cached_time = substr($cached, 11, 5);
                    foreach (self::get_doctor_slots((int) $row['id'], $cached_date) as $slot) {
                        if ('available' === ($slot['status'] ?? '') && $cached_time === substr((string) ($slot['start_time'] ?? ''), 0, 5)) {
                            $cached_slot = $slot;
                            break;
                        }
                    }
                }

                if (!$cached_slot) {
                    $next = Booking::next_available((int) $row['id'], 1);
                    $cached_slot = $next[0] ?? null;
                    $wpdb->update(
                        DB::table('doctors'),
                        array('next_free_slot_cache' => $cached_slot ? $cached_slot['date'] . ' ' . substr((string) $cached_slot['start_time'], 0, 5) . ':00' : null),
                        array('id' => (int) $row['id']),
                        array('%s'),
                        array('%d')
                    );
                }

                $row['_next_available_slot'] = $cached_slot;
                $row['_next_available_sort'] = $row['_next_available_slot'] ? $row['_next_available_slot']['date'] . ' ' . $row['_next_available_slot']['start_time'] : '9999-12-31 23:59';
            }
            unset($row);

            if ($available_only) {
                $rows = array_values(
                    array_filter(
                        $rows,
                        static function (array $row): bool {
                            return !empty($row['_next_available_slot']);
                        }
                    )
                );
            }

            if ('first_available' === $sort) {
                usort(
                    $rows,
                    static function (array $a, array $b): int {
                        return strcmp((string) $a['_next_available_sort'], (string) $b['_next_available_sort']);
                    }
                );
            }
        }

        Frontend::prime_doctor_rating_cache(array_column((array) $rows, 'post_id'));

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
        return rest_ensure_response(Booking::next_available(absint($request['id']), 1));
    }

    public static function slots(\WP_REST_Request $request): \WP_REST_Response {
        return rest_ensure_response(self::get_doctor_slots(absint($request['id']), (string) $request->get_param('date')));
    }

    public static function get_doctor_slots(int $doctor_id, string $raw_date): array {
        global $wpdb;

        $doctor_id = absint($doctor_id);
        $date = self::normalize_rest_date($raw_date);
        if ($doctor_id <= 0 || !$date) {
            return array();
        }

        $doctor = Booking::get_doctor($doctor_id);
        if (!$doctor || 1 !== (int) ($doctor['is_active'] ?? 0) || 1 !== (int) ($doctor['is_verified'] ?? 0)) {
            return array();
        }

        $timestamp = strtotime($date . ' 00:00:00');
        if (!$timestamp) {
            return array();
        }

        $weekday = strtolower(date('l', $timestamp));
        $schedule_table = DB::table('schedules');
        $appointment_table = DB::table('appointments');
        $exception_table = DB::table('schedule_exceptions');

        $segments = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT start_time, end_time, slot_duration, capacity_per_slot
                FROM $schedule_table
                WHERE doctor_id = %d AND weekday = %s AND is_active = 1
                ORDER BY start_time ASC",
                $doctor_id,
                $weekday
            ),
            ARRAY_A
        );

        $exceptions = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT type, start_time, end_time, slot_duration, capacity_per_slot
                FROM $exception_table
                WHERE doctor_id = %d AND exception_date = %s
                ORDER BY start_time ASC",
                $doctor_id,
                $date
            ),
            ARRAY_A
        );

        if ($exceptions) {
            foreach ($exceptions as $exception) {
                if ('day_off' === (string) $exception['type']) {
                    return array();
                }
            }

            $override = array_values(
                array_filter(
                    $exceptions,
                    static function (array $exception): bool {
                        return in_array((string) $exception['type'], array('custom_shift', 'reduced_shift'), true);
                    }
                )
            );

            if ($override) {
                $segments = $override;
            } else {
                $extra = array_values(
                    array_filter(
                        $exceptions,
                        static function (array $exception): bool {
                            return 'extra_shift' === (string) $exception['type'];
                        }
                    )
                );
                if ($extra) {
                    $segments = array_merge((array) $segments, $extra);
                }
            }
        }

        if (!$segments) {
            return array();
        }

        $appointments = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, start_time, end_time, appointment_status, payment_status, locked_until
                FROM $appointment_table
                WHERE doctor_id = %d AND appointment_date = %s",
                $doctor_id,
                $date
            ),
            ARRAY_A
        );

        $appointment_map = array();
        foreach ((array) $appointments as $appointment) {
            $appointment_time = self::normalize_rest_time((string) ($appointment['start_time'] ?? ''));
            if ($appointment_time) {
                $appointment_map[$appointment_time] = $appointment;
            }
        }

        $now = current_time('timestamp');
        $today = current_time('Y-m-d');
        $current_time = current_time('H:i:s');
        $virtual_slots = array();

        foreach ((array) $segments as $segment) {
            $start_time = self::normalize_rest_time((string) ($segment['start_time'] ?? ''));
            $end_time = self::normalize_rest_time((string) ($segment['end_time'] ?? ''));
            $duration = max(1, absint($segment['slot_duration'] ?? 15));
            $capacity = max(1, absint($segment['capacity_per_slot'] ?? 1));

            if (!$start_time || !$end_time) {
                continue;
            }

            $cursor = strtotime($date . ' ' . $start_time);
            $end = strtotime($date . ' ' . $end_time);
            if (!$cursor || !$end || $cursor >= $end) {
                continue;
            }

            while ($cursor + ($duration * MINUTE_IN_SECONDS) <= $end) {
                $slot_start = date('H:i:s', $cursor);
                $slot_end = date('H:i:s', $cursor + ($duration * MINUTE_IN_SECONDS));
                $status = 'available';
                $appointment_id = 0;
                $appointment_status = '';
                $payment_status = '';

                if (isset($appointment_map[$slot_start])) {
                    $appointment = $appointment_map[$slot_start];
                    $raw_status = sanitize_key((string) ($appointment['appointment_status'] ?? ''));
                    $is_valid_lock = 'locked' === $raw_status && !empty($appointment['locked_until']) && strtotime((string) $appointment['locked_until']) > $now;

                    if ($is_valid_lock) {
                        $status = 'locked';
                        $appointment_id = (int) $appointment['id'];
                        $appointment_status = 'locked';
                        $payment_status = sanitize_key((string) ($appointment['payment_status'] ?? ''));
                    } elseif (in_array($raw_status, array('confirmed', 'completed', 'no_show', 'pay_at_clinic'), true)) {
                        $status = 'booked';
                        $appointment_id = (int) $appointment['id'];
                        $appointment_status = $raw_status;
                        $payment_status = sanitize_key((string) ($appointment['payment_status'] ?? ''));
                    }
                }

                if ('available' === $status && ($date < $today || ($date === $today && $slot_start <= $current_time))) {
                    $status = 'past';
                }

                $virtual_slots[$slot_start] = array(
                    'doctor_id' => $doctor_id,
                    'date' => $date,
                    'start_time' => substr($slot_start, 0, 5),
                    'end_time' => substr($slot_end, 0, 5),
                    'duration' => $duration,
                    'capacity_per_slot' => $capacity,
                    'status' => $status,
                    'appointment_status' => $appointment_status,
                    'payment_status' => $payment_status,
                    'appointment_id' => $appointment_id,
                );

                $cursor += $duration * MINUTE_IN_SECONDS;
            }
        }

        ksort($virtual_slots);

        return array_map(array(__CLASS__, 'format_public_slot'), array_values($virtual_slots));
    }

    public static function lock_appointment(\WP_REST_Request $request) {
        $params = $request->get_params();
        $date = self::normalize_rest_date((string) ($params['appointment_date'] ?? ''));
        if (!$date) {
            return new \WP_Error('webtanan_invalid_appointment_date', __('تاریخ نوبت معتبر نیست.', 'webtanan-booking'), array('status' => 400));
        }

        $identity = Patient_Profile::booking_identity(
            get_current_user_id(),
            sanitize_key((string) ($params['dependent_id'] ?? ''))
        );
        if (is_wp_error($identity)) {
            return $identity;
        }

        $params['appointment_date'] = $date;
        $params['patient_user_id'] = get_current_user_id();
        $params['patient_first_name'] = $identity['patient_first_name'];
        $params['patient_last_name'] = $identity['patient_last_name'];
        $params['patient_national_code'] = $identity['patient_national_code'];
        $params['patient_mobile'] = $identity['patient_mobile'];
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

        return is_wp_error($result) ? $result : rest_ensure_response(array('sent' => true, 'expires_at' => $result['expires_at'] ?? '', 'expires_in' => absint($result['expires_in'] ?? 180)));
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

    public static function auth_context(): \WP_REST_Response {
        return rest_ensure_response(Patient_Profile::context());
    }

    public static function verify_otp(\WP_REST_Request $request) {
        $result = OTP::verify((string) $request->get_param('mobile'), (string) $request->get_param('otp'), (string) ($request->get_param('purpose') ?: 'login'));

        if (is_wp_error($result)) {
            return $result;
        }

        $user_id = (int) ($result['user_id'] ?? 0);
        $account_type = 'doctor' === sanitize_key((string) $request->get_param('account_type')) ? 'doctor' : 'patient';
        $user = $user_id > 0 ? get_userdata($user_id) : false;
        $roles = $user ? array_map('sanitize_key', (array) $user->roles) : array();
        $is_clinic_user = (bool) array_intersect(array('administrator', 'webtanan_doctor', 'webtanan_secretary'), $roles);
        if ('doctor' === $account_type && !$is_clinic_user) {
            update_user_meta($user_id, 'webtanan_requested_role', 'doctor');
            update_user_meta($user_id, 'webtanan_doctor_application_status', 'pending');
        }

        return rest_ensure_response(
            array_merge(
                $result,
                Patient_Profile::context($user_id),
                array(
                    'account_type' => $account_type,
                    'doctor_application_pending' => 'pending' === get_user_meta($user_id, 'webtanan_doctor_application_status', true),
                )
            )
        );
    }

    public static function complete_patient_profile(\WP_REST_Request $request) {
        $completion_token = sanitize_text_field((string) $request->get_param('completion_token'));
        $user_id = get_current_user_id();
        if ($user_id <= 0 || !self::valid_rest_nonce($request)) {
            $user_id = OTP::validate_completion_token($completion_token);
        }
        if ($user_id <= 0) {
            return new \WP_Error('webtanan_profile_session_expired', __('مهلت تکمیل اطلاعات تمام شده است. لطفاً دوباره وارد شوید.', 'webtanan-booking'), array('status' => 401));
        }

        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id, true);
        $result = Patient_Profile::update_profile($user_id, $request->get_params());
        if (!is_wp_error($result)) {
            if ('doctor' === get_user_meta($user_id, 'webtanan_requested_role', true)) {
                update_user_meta($user_id, 'webtanan_doctor_medical_system_number', sanitize_text_field((string) $request->get_param('medical_system_number')));
                update_user_meta($user_id, 'webtanan_doctor_specialty_request', sanitize_text_field((string) $request->get_param('specialty')));
            }
            OTP::consume_completion_token($completion_token);
            $result['nonce'] = wp_create_nonce('wp_rest');
            $result['doctor_application_pending'] = 'pending' === get_user_meta($user_id, 'webtanan_doctor_application_status', true);
        }

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
                    SUM(CASE WHEN appointment_status IN ('confirmed','pay_at_clinic') THEN 1 ELSE 0 END) AS active,
                    SUM(CASE WHEN payment_status IN ('paid','wallet_paid','cash_at_clinic','pos_at_clinic') THEN booking_fee ELSE 0 END) AS revenue
                FROM $appointments
                WHERE doctor_id = %d AND appointment_date = %s
                    AND NOT (appointment_status IN ('locked','pending','expired') AND payment_status IN ('unpaid','failed'))",
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
        $start_date = gmdate('Y-m-d', strtotime($date . ' -6 days'));
        $trend_rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT
                    appointment_date,
                    COUNT(*) AS total,
                    SUM(CASE WHEN appointment_status = 'completed' THEN 1 ELSE 0 END) AS completed,
                    SUM(CASE WHEN appointment_status IN ('cancelled','expired','expired_lock_wallet_charged') THEN 1 ELSE 0 END) AS cancelled,
                    SUM(CASE WHEN payment_status IN ('paid','wallet_paid','cash_at_clinic','pos_at_clinic') THEN booking_fee ELSE 0 END) AS revenue
                FROM $appointments
                WHERE doctor_id = %d
                    AND appointment_date BETWEEN %s AND %s
                    AND NOT (appointment_status IN ('locked','pending','expired') AND payment_status IN ('unpaid','failed'))
                GROUP BY appointment_date
                ORDER BY appointment_date ASC",
                $doctor_id,
                $start_date,
                $date
            ),
            ARRAY_A
        );
        $trend_by_date = array();
        foreach ($trend_rows as $trend_row) {
            $trend_by_date[(string) $trend_row['appointment_date']] = $trend_row;
        }
        $weekly_chart = array();
        for ($i = 0; $i < 7; $i++) {
            $chart_date = gmdate('Y-m-d', strtotime($start_date . ' +' . $i . ' days'));
            $trend_row = $trend_by_date[$chart_date] ?? array();
            $weekly_chart[] = array(
                'date' => $chart_date,
                'appointments' => (int) ($trend_row['total'] ?? 0),
                'completed' => (int) ($trend_row['completed'] ?? 0),
                'cancelled' => (int) ($trend_row['cancelled'] ?? 0),
                'revenue' => $can_view_finance ? (float) ($trend_row['revenue'] ?? 0) : null,
            );
        }

        return rest_ensure_response(
            array(
                'date' => $date,
                'doctor_id' => $doctor_id,
                'appointments_today' => (int) ($row['total'] ?? 0),
                'completed_today' => (int) ($row['completed'] ?? 0),
                'no_show_today' => (int) ($row['no_show'] ?? 0),
                'locked_today' => (int) ($row['locked'] ?? 0),
                'active_today' => (int) ($row['active'] ?? 0),
                'revenue_today' => $can_view_finance ? (float) ($row['revenue'] ?? 0) : null,
                'wallet_balance' => $wallet_balance,
                'can_view_finance' => $can_view_finance,
                'next_appointment' => $next ? self::format_appointment($next) : null,
                'weekly_chart' => $weekly_chart,
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
        $where .= " AND NOT (appointment_status IN ('locked','pending','expired') AND payment_status IN ('unpaid','failed'))";

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

        $slots = Booking::get_slots($doctor_id, $date);
        $appointments = array();
        $appointment_ids = array_filter(array_map('absint', wp_list_pluck($slots, 'appointment_id')));

        if ($appointment_ids) {
            global $wpdb;
            $placeholders = implode(',', array_fill(0, count($appointment_ids), '%d'));
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    'SELECT * FROM ' . DB::table('appointments') . " WHERE id IN ($placeholders)",
                    $appointment_ids
                ),
                ARRAY_A
            );

            foreach ((array) $rows as $row) {
                $appointments[(int) $row['id']] = $row;
            }
        }

        $dashboard_slots = array_map(
                static function (array $slot) use ($appointments) {
                    $appointment = !empty($slot['appointment_id']) && isset($appointments[(int) $slot['appointment_id']]) ? $appointments[(int) $slot['appointment_id']] : array();
                    if ($appointment && in_array((string) ($appointment['appointment_status'] ?? ''), array('locked', 'pending', 'expired'), true) && in_array((string) ($appointment['payment_status'] ?? ''), array('unpaid', 'failed'), true)) {
                        return null;
                    }
                    return self::format_dashboard_slot($slot, $appointment);
                },
                $slots
            );

        return rest_ensure_response(array_values(array_filter($dashboard_slots)));
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
        $table = DB::table('schedules');
        $existing_id = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM $table WHERE doctor_id = %d AND weekday = %s AND start_time = %s LIMIT 1",
                $doctor_id,
                $weekday,
                $start_time
            )
        );
        $data = array(
            'doctor_id' => $doctor_id,
            'weekday' => $weekday,
            'start_time' => $start_time,
            'end_time' => $end_time,
            'slot_duration' => max(1, absint($request->get_param('slot_duration') ?: 15)),
            'capacity_per_slot' => max(1, absint($request->get_param('capacity_per_slot') ?: 1)),
            'is_active' => 1,
            'updated_at' => $now,
        );

        if ($existing_id > 0) {
            $wpdb->update($table, $data, array('id' => $existing_id));
            $schedule_id = $existing_id;
        } else {
            $data['created_at'] = $now;
            $wpdb->insert($table, $data);
            $schedule_id = (int) $wpdb->insert_id;
        }

        return rest_ensure_response(array('schedule_id' => $schedule_id, 'updated' => $existing_id > 0));
    }

    public static function doctor_dashboard_delete_schedule(\WP_REST_Request $request) {
        global $wpdb;

        $doctor_id = self::current_dashboard_doctor_id($request);
        $schedule_id = absint($request['id']);
        if (!$doctor_id || !$schedule_id) {
            return new \WP_Error('webtanan_schedule_not_found', __('برنامه نوبت‌دهی پیدا نشد.', 'webtanan-booking'), array('status' => 404));
        }

        $deleted = $wpdb->delete(DB::table('schedules'), array('id' => $schedule_id, 'doctor_id' => $doctor_id), array('%d', '%d'));
        if (false === $deleted) {
            return new \WP_Error('webtanan_schedule_delete_failed', __('حذف برنامه انجام نشد. دوباره تلاش کنید.', 'webtanan-booking'), array('status' => 500));
        }
        if (0 === $deleted) {
            return new \WP_Error('webtanan_schedule_not_found', __('این برنامه وجود ندارد یا به پزشک انتخاب‌شده تعلق ندارد.', 'webtanan-booking'), array('status' => 404));
        }

        return rest_ensure_response(array('deleted' => true, 'schedule_id' => $schedule_id));
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
        $end_date = self::normalize_rest_date((string) ($request->get_param('end_date') ?: $date));
        if (!$end_date || $end_date < $date) {
            return new \WP_Error('webtanan_invalid_exception_end_date', __('تاریخ پایان بازه معتبر نیست.', 'webtanan-booking'), array('status' => 400));
        }
        $range_days = (int) floor((strtotime($end_date) - strtotime($date)) / DAY_IN_SECONDS) + 1;
        if ($range_days > 60) {
            return new \WP_Error('webtanan_exception_range_too_large', __('حداکثر بازه قابل ثبت ۶۰ روز است.', 'webtanan-booking'), array('status' => 400));
        }
        $start_time = self::normalize_rest_time((string) $request->get_param('start_time'));
        $end_time = self::normalize_rest_time((string) $request->get_param('end_time'));
        if ('day_off' !== $type && (!$start_time || !$end_time)) {
            return new \WP_Error('webtanan_invalid_exception_time', __('بازه زمانی استثنا معتبر نیست.', 'webtanan-booking'), array('status' => 400));
        }

        $now = DB::now();
        $table = DB::table('schedule_exceptions');
        $ids = array();
        for ($offset = 0; $offset < $range_days; $offset++) {
            $current_date = wp_date('Y-m-d', strtotime($date . ' +' . $offset . ' days'));
            $data = array(
                'doctor_id' => $doctor_id,
                'exception_date' => $current_date,
                'type' => $type,
                'start_time' => 'day_off' === $type ? null : $start_time,
                'end_time' => 'day_off' === $type ? null : $end_time,
                'slot_duration' => max(1, absint($request->get_param('slot_duration') ?: 15)),
                'capacity_per_slot' => max(1, absint($request->get_param('capacity_per_slot') ?: 1)),
                'reason' => sanitize_textarea_field((string) $request->get_param('reason')),
                'created_at' => $now,
                'updated_at' => $now,
            );
            $existing_id = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT id FROM $table WHERE doctor_id = %d AND exception_date = %s AND type = %s AND COALESCE(start_time, '') = %s LIMIT 1",
                    $doctor_id,
                    $current_date,
                    $type,
                    'day_off' === $type ? '' : $start_time
                )
            );
            if ($existing_id > 0) {
                $wpdb->update($table, $data, array('id' => $existing_id));
                $ids[] = $existing_id;
            } else {
                $wpdb->insert($table, $data);
                $ids[] = (int) $wpdb->insert_id;
            }
        }

        return rest_ensure_response(array('exception_ids' => $ids, 'created_count' => count($ids)));
    }

    public static function doctor_dashboard_delete_exception(\WP_REST_Request $request) {
        global $wpdb;

        $doctor_id = self::current_dashboard_doctor_id($request);
        $exception_id = absint($request['id']);
        if (!$doctor_id || !$exception_id) {
            return new \WP_Error('webtanan_exception_not_found', __('برنامه تاریخ خاص پیدا نشد.', 'webtanan-booking'), array('status' => 404));
        }

        $deleted = $wpdb->delete(DB::table('schedule_exceptions'), array('id' => $exception_id, 'doctor_id' => $doctor_id), array('%d', '%d'));
        if (false === $deleted) {
            return new \WP_Error('webtanan_exception_delete_failed', __('حذف برنامه تاریخ خاص انجام نشد. دوباره تلاش کنید.', 'webtanan-booking'), array('status' => 500));
        }
        if (0 === $deleted) {
            return new \WP_Error('webtanan_exception_not_found', __('این برنامه وجود ندارد یا به پزشک انتخاب‌شده تعلق ندارد.', 'webtanan-booking'), array('status' => 404));
        }

        return rest_ensure_response(array('deleted' => true, 'exception_id' => $exception_id));
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
        if (!$doctor_id || !$patient_id || !self::current_user_can_manage_patient_record($doctor_id, $patient_id, 'read')) {
            return new \WP_Error('webtanan_patient_record_forbidden', __('شما اجازه مشاهده پرونده این بیمار را ندارید.', 'webtanan-booking'), array('status' => 403));
        }

        $record = self::patient_record_payload($doctor_id, $patient_id, true);
        if (!empty($record['id'])) {
            self::log_patient_record_audit((int) $record['id'], $doctor_id, $patient_id, 'view_record', 'record', (int) $record['id']);
        }

        return rest_ensure_response($record);
    }

    public static function doctor_dashboard_update_patient_record(\WP_REST_Request $request) {
        global $wpdb;

        $doctor_id = self::current_dashboard_doctor_id($request);
        $patient_id = absint($request['patient_id']);
        if (!$doctor_id || !$patient_id || !self::current_user_can_manage_patient_record($doctor_id, $patient_id, 'write')) {
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
        self::log_patient_record_audit((int) $record['id'], $doctor_id, $patient_id, 'update_record', 'record', (int) $record['id']);

        return rest_ensure_response(self::patient_record_payload($doctor_id, $patient_id, false));
    }

    public static function doctor_dashboard_add_patient_record_note(\WP_REST_Request $request) {
        global $wpdb;

        $doctor_id = self::current_dashboard_doctor_id($request);
        $patient_id = absint($request['patient_id']);
        if (!$doctor_id || !$patient_id || !self::current_user_can_manage_patient_record($doctor_id, $patient_id, 'write')) {
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
        $note_id = (int) $wpdb->insert_id;
        self::log_patient_record_audit((int) $record['id'], $doctor_id, $patient_id, 'add_note', 'note', $note_id, array('visibility' => $visibility));

        return rest_ensure_response(self::patient_record_payload($doctor_id, $patient_id, false));
    }

    public static function doctor_dashboard_upload_patient_record_file(\WP_REST_Request $request) {
        global $wpdb;

        $doctor_id = self::current_dashboard_doctor_id($request);
        $patient_id = absint($request['patient_id']);
        if (!$doctor_id || !$patient_id || !self::current_user_can_manage_patient_record($doctor_id, $patient_id, 'write')) {
            return new \WP_Error('webtanan_patient_record_file_forbidden', __('شما اجازه افزودن فایل به پرونده این بیمار را ندارید.', 'webtanan-booking'), array('status' => 403));
        }

        $files = $request->get_file_params();
        if (empty($files['file'])) {
            return new \WP_Error('webtanan_patient_record_file_missing', __('فایل پرونده ارسال نشده است.', 'webtanan-booking'), array('status' => 400));
        }

        $file = $files['file'];
        if (!empty($file['size']) && (int) $file['size'] > 10 * MB_IN_BYTES) {
            return new \WP_Error('webtanan_patient_record_file_too_large', __('حجم فایل پرونده باید کمتر از ۱۰ مگابایت باشد.', 'webtanan-booking'), array('status' => 400));
        }

        $visibility = sanitize_key((string) ($request->get_param('visibility') ?: 'patient'));
        if (!in_array($visibility, array('patient', 'private'), true)) {
            $visibility = 'patient';
        }

        $allowed_mimes = array(
            'jpg|jpeg|jpe' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'pdf' => 'application/pdf',
        );

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $upload = wp_handle_upload($file, array('test_form' => false, 'mimes' => $allowed_mimes));
        if (!empty($upload['error'])) {
            return new \WP_Error('webtanan_patient_record_file_upload_failed', sanitize_text_field($upload['error']), array('status' => 400));
        }

        $attachment_id = wp_insert_attachment(
            array(
                'post_mime_type' => sanitize_mime_type((string) $upload['type']),
                'post_title' => sanitize_file_name(pathinfo((string) $upload['file'], PATHINFO_FILENAME)),
                'post_content' => '',
                'post_status' => 'inherit',
            ),
            (string) $upload['file']
        );

        if (is_wp_error($attachment_id)) {
            return $attachment_id;
        }

        $metadata = wp_generate_attachment_metadata((int) $attachment_id, (string) $upload['file']);
        wp_update_attachment_metadata((int) $attachment_id, $metadata);

        $record = self::patient_record_payload($doctor_id, $patient_id, true);
        $file_url = wp_get_attachment_url((int) $attachment_id);
        $wpdb->insert(
            DB::table('patient_record_files'),
            array(
                'record_id' => (int) $record['id'],
                'note_id' => absint($request->get_param('note_id')),
                'appointment_id' => absint($request->get_param('appointment_id')),
                'doctor_id' => $doctor_id,
                'patient_user_id' => $patient_id,
                'attachment_id' => (int) $attachment_id,
                'file_url' => esc_url_raw((string) $file_url),
                'file_name' => sanitize_file_name((string) ($file['name'] ?? basename((string) $upload['file']))),
                'mime_type' => sanitize_mime_type((string) $upload['type']),
                'file_size' => (int) ($file['size'] ?? 0),
                'visibility' => $visibility,
                'uploaded_by' => get_current_user_id(),
                'created_at' => DB::now(),
            )
        );

        $file_id = (int) $wpdb->insert_id;
        self::log_patient_record_audit((int) $record['id'], $doctor_id, $patient_id, 'upload_file', 'file', $file_id, array('visibility' => $visibility, 'mime_type' => (string) $upload['type']));

        return rest_ensure_response(self::patient_record_file_payload($file_id));
    }

    public static function doctor_dashboard_patient_record_audit(\WP_REST_Request $request) {
        global $wpdb;

        $doctor_id = self::current_dashboard_doctor_id($request);
        $patient_id = absint($request['patient_id']);
        if (!$doctor_id || !$patient_id || !self::current_user_can_manage_patient_record($doctor_id, $patient_id, 'read')) {
            return new \WP_Error('webtanan_patient_record_audit_forbidden', __('شما اجازه مشاهده لاگ پرونده این بیمار را ندارید.', 'webtanan-booking'), array('status' => 403));
        }

        $record = self::patient_record_payload($doctor_id, $patient_id, true);
        if (empty($record['id'])) {
            return rest_ensure_response(array());
        }

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT * FROM ' . DB::table('patient_record_audit_logs') . ' WHERE record_id = %d ORDER BY id DESC LIMIT 100',
                (int) $record['id']
            ),
            ARRAY_A
        );

        return rest_ensure_response(array_map(array(__CLASS__, 'format_patient_record_audit'), $rows));
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
        $services = self::sanitize_profile_list($request->get_param('services'));
        $certificates = self::sanitize_profile_list($request->get_param('certificates'));
        $faq_items = self::sanitize_profile_faq($request->get_param('faq'));

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
            update_post_meta($post_id, '_webtanan_doctor_services', $services);
            update_post_meta($post_id, '_webtanan_doctor_certificates', $certificates);
            update_post_meta($post_id, '_webtanan_doctor_faq', $faq_items);
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

    public static function patient_panel_profile(\WP_REST_Request $request): \WP_REST_Response {
        return rest_ensure_response(Patient_Profile::context(get_current_user_id()));
    }

    public static function patient_panel_update_profile(\WP_REST_Request $request) {
        $result = Patient_Profile::update_profile(get_current_user_id(), $request->get_params());

        return is_wp_error($result) ? $result : rest_ensure_response($result);
    }

    public static function patient_panel_dependents(\WP_REST_Request $request): \WP_REST_Response {
        return rest_ensure_response(array('dependents' => Patient_Profile::dependents(get_current_user_id())));
    }

    public static function patient_panel_save_dependent(\WP_REST_Request $request) {
        $result = Patient_Profile::save_dependent(get_current_user_id(), $request->get_params());

        return is_wp_error($result) ? $result : rest_ensure_response($result);
    }

    public static function patient_panel_delete_dependent(\WP_REST_Request $request) {
        $result = Patient_Profile::delete_dependent(get_current_user_id(), sanitize_key((string) $request['id']));

        return is_wp_error($result) ? $result : rest_ensure_response($result);
    }

    public static function patient_panel_favorites(\WP_REST_Request $request): \WP_REST_Response {
        global $wpdb;

        $ids = array_values(array_unique(array_filter(array_map('absint', (array) get_user_meta(get_current_user_id(), 'webtanan_favorite_doctor_ids', true)))));
        if (!$ids) {
            return rest_ensure_response(array('ids' => array(), 'doctors' => array()));
        }

        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT d.*, p.post_title, s.name AS specialty_name
                FROM ' . DB::table('doctors') . ' d
                LEFT JOIN ' . $wpdb->posts . ' p ON p.ID = d.post_id
                LEFT JOIN ' . DB::table('specialties') . " s ON s.id = d.specialty_id
                WHERE d.id IN ($placeholders) AND d.is_active = 1 AND d.is_verified = 1",
                $ids
            ),
            ARRAY_A
        );
        $by_id = array();
        foreach ((array) $rows as $row) {
            $by_id[(int) $row['id']] = self::format_doctor($row);
        }
        $doctors = array();
        foreach ($ids as $id) {
            if (isset($by_id[$id])) {
                $doctors[] = $by_id[$id];
            }
        }

        return rest_ensure_response(array('ids' => $ids, 'doctors' => $doctors));
    }

    public static function patient_panel_toggle_favorite(\WP_REST_Request $request): \WP_REST_Response {
        $doctor_id = absint($request['doctor_id']);
        $doctor = Booking::get_doctor($doctor_id);
        if (!$doctor || empty($doctor['is_active']) || empty($doctor['is_verified'])) {
            return new \WP_REST_Response(array('code' => 'webtanan_doctor_not_found', 'message' => __('پزشک پیدا نشد.', 'webtanan-booking')), 404);
        }

        $user_id = get_current_user_id();
        $ids = array_values(array_unique(array_filter(array_map('absint', (array) get_user_meta($user_id, 'webtanan_favorite_doctor_ids', true)))));
        $favorite = filter_var($request->get_param('favorite'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        $favorite = null === $favorite ? !in_array($doctor_id, $ids, true) : $favorite;

        if ($favorite && !in_array($doctor_id, $ids, true)) {
            array_unshift($ids, $doctor_id);
        } elseif (!$favorite) {
            $ids = array_values(array_diff($ids, array($doctor_id)));
        }
        update_user_meta($user_id, 'webtanan_favorite_doctor_ids', array_slice($ids, 0, 100));

        return rest_ensure_response(array('doctor_id' => $doctor_id, 'favorite' => (bool) $favorite, 'ids' => $ids));
    }

    public static function patient_panel_appointments(\WP_REST_Request $request): \WP_REST_Response {
        global $wpdb;

        $scope = sanitize_key((string) ($request->get_param('scope') ?: 'upcoming'));
        $today = current_time('Y-m-d');
        $now_time = current_time('H:i:s');
        $table = DB::table('appointments');
        $where = 'patient_user_id = %d';
        $params = array(get_current_user_id());

        if ('history' === $scope) {
            $where .= " AND (appointment_date < %s OR (appointment_date = %s AND start_time < %s) OR appointment_status IN ('completed','cancelled','no_show') OR (appointment_status = 'expired' AND payment_status NOT IN ('unpaid','failed')))";
            $params[] = $today;
            $params[] = $today;
            $params[] = $now_time;
        } else {
            $where .= " AND (appointment_date > %s OR (appointment_date = %s AND start_time >= %s)) AND (appointment_status IN ('confirmed','pay_at_clinic','locked','pending') OR (appointment_status = 'expired' AND payment_status IN ('unpaid','failed')))";
            $params[] = $today;
            $params[] = $today;
            $params[] = $now_time;
        }

        $rows = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM $table WHERE $where ORDER BY appointment_date ASC, start_time ASC LIMIT 100", $params),
            ARRAY_A
        );

        return rest_ensure_response(array_map(array(__CLASS__, 'format_appointment'), $rows));
    }

    public static function patient_panel_resume_appointment(\WP_REST_Request $request) {
        $appointment_id = absint($request['id']);
        $appointment = Booking::get_appointment($appointment_id);
        if (!$appointment || (int) $appointment['patient_user_id'] !== get_current_user_id()) {
            return new \WP_Error('webtanan_resume_forbidden', __('این نوبت به حساب شما تعلق ندارد.', 'webtanan-booking'), array('status' => 403));
        }
        if (!self::appointment_can_resume_payment($appointment)) {
            return new \WP_Error('webtanan_resume_payment_not_available', __('این نوبت دیگر قابل پرداخت نیست.', 'webtanan-booking'), array('status' => 409));
        }

        $lock = Booking::renew_lock_for_resume($appointment_id, (string) $appointment['patient_mobile']);
        if (is_wp_error($lock)) {
            return $lock;
        }

        $fresh = Booking::get_appointment($appointment_id);

        return rest_ensure_response(
            array(
                'lock' => $lock,
                'appointment' => $fresh ? self::format_appointment($fresh) : self::format_appointment($appointment),
            )
        );
    }

    public static function patient_panel_survey_link(\WP_REST_Request $request) {
        $appointment = Booking::get_appointment(absint($request['id']));
        if (!$appointment || (int) $appointment['patient_user_id'] !== get_current_user_id()) {
            return new \WP_Error('webtanan_survey_forbidden', __('این نوبت به حساب شما تعلق ندارد.', 'webtanan-booking'), array('status' => 403));
        }
        if (!self::appointment_survey_is_open($appointment)) {
            return new \WP_Error('webtanan_survey_not_available', __('فرم نظرسنجی پس از زمان مراجعه فعال می‌شود.', 'webtanan-booking'), array('status' => 409));
        }

        return rest_ensure_response(array('url' => SMS::public_survey_url($appointment)));
    }

    public static function patient_panel_appointment_survey(\WP_REST_Request $request) {
        $appointment = Booking::get_appointment(absint($request['id']));
        if (!$appointment || (int) $appointment['patient_user_id'] !== get_current_user_id()) {
            return new \WP_Error('webtanan_survey_forbidden', __('این نوبت به حساب شما تعلق ندارد.', 'webtanan-booking'), array('status' => 403));
        }
        if (!self::appointment_survey_is_open($appointment)) {
            return new \WP_Error('webtanan_survey_not_available', __('فرم نظرسنجی پس از زمان مراجعه فعال می‌شود.', 'webtanan-booking'), array('status' => 409));
        }

        return rest_ensure_response(self::survey_payload($appointment));
    }

    public static function patient_panel_submit_appointment_survey(\WP_REST_Request $request) {
        $appointment = Booking::get_appointment(absint($request['id']));
        if (!$appointment || (int) $appointment['patient_user_id'] !== get_current_user_id()) {
            return new \WP_Error('webtanan_survey_forbidden', __('این نوبت به حساب شما تعلق ندارد.', 'webtanan-booking'), array('status' => 403));
        }
        if (!self::appointment_survey_is_open($appointment)) {
            return new \WP_Error('webtanan_survey_not_available', __('فرم نظرسنجی پس از زمان مراجعه فعال می‌شود.', 'webtanan-booking'), array('status' => 409));
        }

        return self::save_survey_response($appointment, $request);
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
        $files_table = DB::table('patient_record_files');

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
            $record['files'] = array_map(
                array(__CLASS__, 'format_patient_record_file'),
                $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT *
                        FROM $files_table
                        WHERE record_id = %d AND visibility = 'patient'
                        ORDER BY id DESC
                        LIMIT 100",
                        (int) $record['id']
                    ),
                    ARRAY_A
                )
            );
            self::log_patient_record_audit((int) $record['id'], (int) $record['doctor_id'], $user_id, 'patient_view_record', 'record', (int) $record['id']);
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
        if (!self::appointment_survey_is_open($appointment)) {
            return new \WP_Error('webtanan_survey_not_available', __('فرم نظرسنجی پس از زمان مراجعه فعال می‌شود.', 'webtanan-booking'), array('status' => 409));
        }

        return rest_ensure_response(self::survey_payload($appointment));
    }

    public static function submit_appointment_survey(\WP_REST_Request $request) {
        $appointment = self::appointment_by_code((string) $request['code']);
        if (!$appointment || !SMS::verify_appointment_token($appointment, 'survey', (string) $request->get_param('token'))) {
            return new \WP_Error('webtanan_survey_forbidden', __('لینک نظرسنجی معتبر نیست.', 'webtanan-booking'), array('status' => 403));
        }
        if (!self::appointment_survey_is_open($appointment)) {
            return new \WP_Error('webtanan_survey_not_available', __('فرم نظرسنجی پس از زمان مراجعه فعال می‌شود.', 'webtanan-booking'), array('status' => 409));
        }

        return self::save_survey_response($appointment, $request);
    }

    private static function survey_payload(array $appointment): array {
        global $wpdb;

        $doctor = Booking::get_doctor((int) $appointment['doctor_id']);
        $response = $wpdb->get_row(
            $wpdb->prepare('SELECT rating, feedback, public_consent, status FROM ' . DB::table('survey_responses') . ' WHERE appointment_id = %d LIMIT 1', (int) $appointment['id']),
            ARRAY_A
        );

        return array(
            'appointment_code' => (string) $appointment['appointment_code'],
            'doctor_name' => $doctor && !empty($doctor['post_id']) ? html_entity_decode(get_the_title((int) $doctor['post_id']), ENT_QUOTES, get_bloginfo('charset')) : '',
            'date' => (string) $appointment['appointment_date'],
            'time' => substr((string) $appointment['start_time'], 0, 5),
            'message' => __('از همراهی شما ممنونیم. امتیاز و تجربه خود را ثبت کنید.', 'webtanan-booking'),
            'submitted' => !empty($response),
            'rating' => isset($response['rating']) ? (int) $response['rating'] : 5,
            'feedback' => (string) ($response['feedback'] ?? ''),
            'public_consent' => !isset($response['public_consent']) || (bool) $response['public_consent'],
            'status' => sanitize_key((string) ($response['status'] ?? '')),
        );
    }

    private static function save_survey_response(array $appointment, \WP_REST_Request $request) {
        global $wpdb;

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
            $saved = $wpdb->update($table, $data, array('id' => $existing_id));
        } else {
            $data['created_at'] = $now;
            $saved = $wpdb->insert($table, $data);
        }

        if (false === $saved) {
            return new \WP_Error('webtanan_survey_save_failed', __('ثبت نظر انجام نشد. لطفاً دوباره تلاش کنید.', 'webtanan-booking'), array('status' => 500));
        }

        if ($public_consent) {
            self::create_pending_survey_comment($appointment, $rating, $feedback);
        } else {
            self::hide_survey_comment($appointment);
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

    private static function appointment_survey_is_open(array $appointment): bool {
        if (!in_array((string) ($appointment['appointment_status'] ?? ''), array('confirmed', 'pay_at_clinic', 'completed'), true)) {
            return false;
        }

        try {
            $appointment_at = new \DateTimeImmutable(
                (string) ($appointment['appointment_date'] ?? '') . ' ' . (string) ($appointment['start_time'] ?? ''),
                wp_timezone()
            );
        } catch (\Exception $exception) {
            return false;
        }

        return $appointment_at <= current_datetime();
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

    private static function current_user_can_manage_patient_record(int $doctor_id, int $patient_user_id, string $mode = 'read'): bool {
        if (!self::doctor_can_access_patient($doctor_id, $patient_user_id)) {
            return false;
        }

        if (current_user_can('webtanan_manage_booking') || current_user_can('manage_options')) {
            return true;
        }

        $doctor = Booking::get_doctor($doctor_id);
        if ($doctor && (int) ($doctor['user_id'] ?? 0) === get_current_user_id()) {
            return true;
        }

        if (Booking::current_user_is_secretary()) {
            return 'yes' === get_user_meta(get_current_user_id(), 'webtanan_secretary_can_manage_records', true);
        }

        return false;
    }

    private static function log_patient_record_audit(int $record_id, int $doctor_id, int $patient_user_id, string $action, string $object_type = 'record', int $object_id = 0, array $details = array()): void {
        global $wpdb;

        if ($record_id <= 0) {
            return;
        }

        $user = wp_get_current_user();
        $roles = $user && $user->exists() ? (array) $user->roles : array();
        $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
        $agent = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '';

        $wpdb->insert(
            DB::table('patient_record_audit_logs'),
            array(
                'record_id' => $record_id,
                'doctor_id' => $doctor_id,
                'patient_user_id' => $patient_user_id,
                'actor_user_id' => get_current_user_id(),
                'actor_role' => sanitize_key((string) ($roles[0] ?? 'guest')),
                'action' => sanitize_key($action),
                'object_type' => sanitize_key($object_type),
                'object_id' => $object_id,
                'ip_address' => $ip,
                'user_agent' => $agent,
                'details' => $details ? wp_json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
                'created_at' => DB::now(),
            )
        );
    }

    private static function format_patient_record_file(array $row): array {
        $attachment_id = absint($row['attachment_id'] ?? 0);
        $url = !empty($row['file_url']) ? (string) $row['file_url'] : ($attachment_id ? (string) wp_get_attachment_url($attachment_id) : '');

        return array(
            'id' => (int) ($row['id'] ?? 0),
            'record_id' => (int) ($row['record_id'] ?? 0),
            'note_id' => (int) ($row['note_id'] ?? 0),
            'appointment_id' => (int) ($row['appointment_id'] ?? 0),
            'attachment_id' => $attachment_id,
            'file_url' => esc_url_raw($url),
            'file_name' => sanitize_text_field((string) ($row['file_name'] ?? '')),
            'mime_type' => sanitize_mime_type((string) ($row['mime_type'] ?? '')),
            'file_size' => (int) ($row['file_size'] ?? 0),
            'visibility' => sanitize_key((string) ($row['visibility'] ?? 'patient')),
            'uploaded_by' => (int) ($row['uploaded_by'] ?? 0),
            'created_at' => sanitize_text_field((string) ($row['created_at'] ?? '')),
        );
    }

    private static function patient_record_file_payload(int $file_id): array {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare('SELECT * FROM ' . DB::table('patient_record_files') . ' WHERE id = %d', $file_id),
            ARRAY_A
        );

        return $row ? self::format_patient_record_file($row) : array();
    }

    private static function format_patient_record_audit(array $row): array {
        $actor_id = absint($row['actor_user_id'] ?? 0);
        $actor = $actor_id ? get_user_by('id', $actor_id) : false;
        $details = array();
        if (!empty($row['details'])) {
            $decoded = json_decode((string) $row['details'], true);
            $details = is_array($decoded) ? $decoded : array();
        }

        return array(
            'id' => (int) ($row['id'] ?? 0),
            'action' => sanitize_key((string) ($row['action'] ?? '')),
            'action_label' => self::patient_record_audit_action_label((string) ($row['action'] ?? '')),
            'object_type' => sanitize_key((string) ($row['object_type'] ?? '')),
            'object_id' => (int) ($row['object_id'] ?? 0),
            'actor_user_id' => $actor_id,
            'actor_name' => $actor ? $actor->display_name : __('سیستم', 'webtanan-booking'),
            'actor_role' => sanitize_key((string) ($row['actor_role'] ?? '')),
            'details' => $details,
            'created_at' => sanitize_text_field((string) ($row['created_at'] ?? '')),
        );
    }

    private static function patient_record_audit_action_label(string $action): string {
        $labels = array(
            'view_record' => __('مشاهده پرونده', 'webtanan-booking'),
            'patient_view_record' => __('مشاهده توسط بیمار', 'webtanan-booking'),
            'update_record' => __('ویرایش پرونده', 'webtanan-booking'),
            'add_note' => __('افزودن یادداشت', 'webtanan-booking'),
            'upload_file' => __('آپلود فایل', 'webtanan-booking'),
        );

        return $labels[$action] ?? $action;
    }

    private static function patient_record_payload(int $doctor_id, int $patient_user_id, bool $create): array {
        global $wpdb;

        $records_table = DB::table('patient_records');
        $notes_table = DB::table('patient_record_notes');
        $files_table = DB::table('patient_record_files');
        $audit_table = DB::table('patient_record_audit_logs');
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

        $files = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT *
                FROM $files_table
                WHERE record_id = %d
                ORDER BY id DESC
                LIMIT 100",
                (int) $record['id']
            ),
            ARRAY_A
        );

        $audit_logs = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT *
                FROM $audit_table
                WHERE record_id = %d
                ORDER BY id DESC
                LIMIT 30",
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
        $record['files'] = array_map(array(__CLASS__, 'format_patient_record_file'), $files);
        $record['audit_logs'] = array_map(array(__CLASS__, 'format_patient_record_audit'), $audit_logs);
        $record['can_upload_files'] = self::current_user_can_manage_patient_record($doctor_id, $patient_user_id, 'write');

        return $record;
    }

    private static function create_pending_survey_comment(array $appointment, int $rating, string $feedback): void {
        global $wpdb;

        $doctor = Booking::get_doctor((int) $appointment['doctor_id']);
        $post_id = $doctor ? absint($doctor['post_id'] ?? 0) : 0;
        if ($post_id <= 0) {
            return;
        }

        $existing_comment_id = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT c.comment_ID
                FROM $wpdb->commentmeta cm
                INNER JOIN $wpdb->comments c ON c.comment_ID = cm.comment_id
                WHERE c.comment_post_ID = %d AND cm.meta_key = %s AND cm.meta_value = %d",
                $post_id,
                '_webtanan_survey_appointment_id',
                (int) $appointment['id']
            )
        );
        if ($existing_comment_id > 0) {
            wp_update_comment(
                array(
                    'comment_ID' => $existing_comment_id,
                    'comment_content' => $feedback ?: sprintf(__('امتیاز ثبت‌شده: %d از ۵', 'webtanan-booking'), $rating),
                    'comment_approved' => 0,
                )
            );
            update_comment_meta($existing_comment_id, '_webtanan_rating', $rating);
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

    private static function hide_survey_comment(array $appointment): void {
        global $wpdb;

        $comment_id = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT comment_id FROM $wpdb->commentmeta WHERE meta_key = %s AND meta_value = %d LIMIT 1",
                '_webtanan_survey_appointment_id',
                (int) $appointment['id']
            )
        );
        if ($comment_id > 0) {
            wp_set_comment_status($comment_id, 'trash');
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

        try {
            $appointment_at = new \DateTimeImmutable(
                (string) ($appointment['appointment_date'] ?? '') . ' ' . (string) ($appointment['start_time'] ?? ''),
                wp_timezone()
            );
            if ($appointment_at <= current_datetime()) {
                return false;
            }
        } catch (\Exception $exception) {
            return false;
        }

        return in_array((string) $appointment['payment_status'], array('unpaid', 'failed'), true);
    }

    private static function appointment_status_label(string $status): string {
        $labels = array(
            'pending' => __('در انتظار', 'webtanan-booking'),
            'locked' => __('در حال گرفتن نوبت', 'webtanan-booking'),
            'confirmed' => __('قطعی شده', 'webtanan-booking'),
            'cancelled' => __('لغو شده', 'webtanan-booking'),
            'expired' => __('منقضی شده', 'webtanan-booking'),
            'completed' => __('مراجعه کرد', 'webtanan-booking'),
            'no_show' => __('مراجعه نکرد', 'webtanan-booking'),
            'pay_at_clinic' => __('پرداخت در مطب', 'webtanan-booking'),
            'available' => __('ساعت آزاد', 'webtanan-booking'),
            'booked' => __('پر شده', 'webtanan-booking'),
            'past' => __('زمان گذشته', 'webtanan-booking'),
        );

        return $labels[$status] ?? ($status ?: __('نامشخص', 'webtanan-booking'));
    }

    private static function payment_status_label(string $status): string {
        $labels = array(
            'unpaid' => __('پرداخت‌نشده', 'webtanan-booking'),
            'paid' => __('پرداخت آنلاین', 'webtanan-booking'),
            'failed' => __('پرداخت ناموفق', 'webtanan-booking'),
            'refunded_to_wallet' => __('استرداد به کیف پول', 'webtanan-booking'),
            'cash_at_clinic' => __('نقدی در مطب', 'webtanan-booking'),
            'pos_at_clinic' => __('کارت‌خوان در مطب', 'webtanan-booking'),
            'wallet_paid' => __('پرداخت از کیف پول', 'webtanan-booking'),
        );

        return $labels[$status] ?? ($status ?: __('نامشخص', 'webtanan-booking'));
    }

    private static function status_tone(string $status): string {
        if (in_array($status, array('available', 'confirmed', 'completed', 'paid', 'wallet_paid', 'credit', 'approved'), true)) {
            return 'success';
        }

        if (in_array($status, array('locked', 'pending', 'pay_at_clinic', 'cash_at_clinic', 'pos_at_clinic', 'unpaid'), true)) {
            return 'warning';
        }

        if (in_array($status, array('cancelled', 'failed', 'no_show', 'rejected', 'debit'), true)) {
            return 'danger';
        }

        if (in_array($status, array('booked', 'past', 'expired', 'settlement', 'commission'), true)) {
            return 'muted';
        }

        return 'info';
    }

    private static function appointment_source_label(array $row): string {
        return in_array((string) ($row['payment_method'] ?? ''), array('pay_at_clinic', 'cash_at_clinic', 'pos_at_clinic'), true)
            ? __('نوبت حضوری', 'webtanan-booking')
            : __('نوبت آنلاین', 'webtanan-booking');
    }

    private static function format_public_slot(array $slot): array {
        $status = (string) ($slot['status'] ?? 'available');
        $appointment_status = (string) ($slot['appointment_status'] ?? '');
        $display_status = $appointment_status && 'pending' !== $appointment_status ? self::appointment_status_label($appointment_status) : self::appointment_status_label($status);

        $slot['display_status'] = $display_status;
        $slot['slot_tone'] = self::status_tone($appointment_status ?: $status);
        $slot['time_range'] = substr((string) ($slot['start_time'] ?? ''), 0, 5) . (!empty($slot['end_time']) ? ' - ' . substr((string) $slot['end_time'], 0, 5) : '');

        return $slot;
    }

    private static function format_dashboard_slot(array $slot, array $appointment = array()): array {
        $slot = self::format_public_slot($slot);

        if ($appointment) {
            $formatted = self::format_appointment($appointment);
            $slot['appointment'] = $formatted;
            $slot['patient_display_name'] = $formatted['patient_display_name'];
            $slot['source_label'] = $formatted['source_label'];
            $slot['display_status'] = $formatted['display_status'];
            $slot['display_payment'] = $formatted['display_payment'];
            $slot['slot_tone'] = $formatted['status_tone'];
            $slot['time_range'] = $formatted['time_range'];
        }

        return $slot;
    }

    private static function format_doctor(array $row): array {
        $post_id = (int) $row['post_id'];
        $rating_summary = Frontend::doctor_rating_summary($post_id);
        $excerpt = '';
        if ($post_id > 0) {
            $excerpt = get_the_excerpt($post_id);
            if (!$excerpt) {
                $excerpt = wp_trim_words(wp_strip_all_tags((string) get_post_field('post_content', $post_id)), 24);
            }
        }

        $doctor = array(
            'id' => (int) $row['id'],
            'post_id' => $post_id,
            'title' => html_entity_decode(get_the_title($post_id), ENT_QUOTES, get_bloginfo('charset')),
            'permalink' => get_permalink($post_id),
            'profile_excerpt' => $excerpt,
            'doctor_code' => $row['doctor_code'] ?? '',
            'medical_system_number' => $row['medical_system_number'] ?? '',
            'specialty_id' => (int) $row['specialty_id'],
            'specialty_name' => $row['specialty_name'] ?? '',
            'specialty_url' => Post_Types::specialty_url((int) $row['specialty_id'], (string) ($row['specialty_name'] ?? '')),
            'city_id' => (int) ($row['city_id'] ?? 0),
            'province_id' => (int) ($row['province_id'] ?? 0),
            'clinic_name' => $row['clinic_name'] ?? '',
            'clinic_address' => $row['clinic_address'] ?? '',
            'clinic_short_address' => !empty($row['clinic_address']) ? wp_trim_words((string) $row['clinic_address'], 14) : '',
            'clinic_phone' => $row['clinic_phone'] ?? '',
            'rating' => (float) $rating_summary['rating'],
            'reviews_count' => (int) $rating_summary['count'],
            'gender_label' => __('پزشک', 'webtanan-booking'),
            'online_status_label' => !empty($row['allow_online_payment']) ? __('آنلاین', 'webtanan-booking') : __('حضوری', 'webtanan-booking'),
            'visit_price' => (float) $row['visit_price'],
            'display_visit_price' => (float) $row['visit_price'],
            'booking_fee' => Booking::doctor_booking_fee($row),
            'display_booking_fee' => Booking::doctor_booking_fee($row),
            'booking_fee_share_type' => $row['booking_fee_share_type'] ?? 'percent',
            'booking_fee_share_value' => (float) ($row['booking_fee_share_value'] ?? 0),
            'is_verified' => (bool) $row['is_verified'],
            'allow_online_payment' => (bool) $row['allow_online_payment'],
            'allow_pay_at_clinic' => (bool) $row['allow_pay_at_clinic'],
            'payment_badges' => array_values(
                array_filter(
                    array(
                        !empty($row['allow_online_payment']) ? __('پرداخت آنلاین', 'webtanan-booking') : '',
                        !empty($row['allow_pay_at_clinic']) ? __('پرداخت در مطب', 'webtanan-booking') : '',
                    )
                )
            ),
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

        $payment_amount = Booking::appointment_charge_amount($row);
        $cancellation = Booking::cancellation_preview($row, 'patient');
        $payment_status = (string) $row['payment_status'];
        $appointment_status = (string) $row['appointment_status'];
        $patient_full_name = trim($row['patient_first_name'] . ' ' . $row['patient_last_name']);
        $source_label = self::appointment_source_label($row);

        return array(
            'id' => (int) $row['id'],
            'appointment_code' => $row['appointment_code'],
            'doctor_id' => (int) $row['doctor_id'],
            'doctor_title' => $doctor_title,
            'clinic_address' => $clinic_address,
            'patient_user_id' => (int) $row['patient_user_id'],
            'patient_first_name' => $row['patient_first_name'],
            'patient_last_name' => $row['patient_last_name'],
            'patient_full_name' => $patient_full_name,
            'patient_display_name' => $patient_full_name ?: __('بیمار', 'webtanan-booking'),
            'patient_national_code' => $row['patient_national_code'],
            'patient_mobile' => $row['patient_mobile'],
            'appointment_date' => $row['appointment_date'],
            'start_time' => substr((string) $row['start_time'], 0, 5),
            'end_time' => substr((string) $row['end_time'], 0, 5),
            'time_range' => substr((string) $row['start_time'], 0, 5) . ' - ' . substr((string) $row['end_time'], 0, 5),
            'visit_price' => (float) $row['visit_price'],
            'display_visit_price' => (float) ($row['display_visit_price'] ?? $row['visit_price']),
            'booking_fee' => (float) ($row['booking_fee'] ?? $payment_amount),
            'payment_amount' => $payment_amount,
            'payment_method' => $row['payment_method'],
            'booking_source' => in_array((string) $row['payment_method'], array('pay_at_clinic', 'cash_at_clinic', 'pos_at_clinic'), true) ? 'clinic' : 'online',
            'source_label' => $source_label,
            'payment_status' => $payment_status,
            'payment_label' => self::payment_status_label($payment_status),
            'display_payment' => self::payment_status_label($payment_status),
            'payment_tone' => self::status_tone($payment_status),
            'appointment_status' => $appointment_status,
            'appointment_label' => self::appointment_status_label($appointment_status),
            'display_status' => self::appointment_status_label($appointment_status),
            'status_tone' => self::status_tone($appointment_status),
            'locked_until' => $row['locked_until'],
            'transaction_id' => (int) $row['transaction_id'],
            'cancelled_by' => $row['cancelled_by'],
            'cancel_reason' => $row['cancel_reason'],
            'can_cancel' => (bool) $cancellation['can_cancel'],
            'refund_estimate' => (float) $cancellation['refund_amount'],
            'cancellation_message' => $cancellation['message'],
            'hours_to_appointment' => (float) $cancellation['hours_to_appointment'],
            'can_resume_payment' => self::appointment_can_resume_payment($row),
            'can_review' => self::appointment_survey_is_open($row),
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
            'services' => $post_id > 0 ? self::profile_meta_list($post_id, '_webtanan_doctor_services') : array(),
            'certificates' => $post_id > 0 ? self::profile_meta_list($post_id, '_webtanan_doctor_certificates') : array(),
            'faq' => $post_id > 0 ? self::profile_meta_faq($post_id) : array(),
        );
    }

    private static function profile_meta_list(int $post_id, string $key): array {
        $value = get_post_meta($post_id, $key, true);
        return self::sanitize_profile_list($value);
    }

    private static function sanitize_profile_list($value): array {
        if (is_string($value)) {
            $value = preg_split('/[\r\n,،]+/u', $value);
        }
        if (!is_array($value)) {
            return array();
        }

        $items = array_map(
            static function ($item): string {
                return sanitize_text_field((string) $item);
            },
            $value
        );

        return array_values(array_unique(array_filter($items)));
    }

    private static function profile_meta_faq(int $post_id): array {
        return self::sanitize_profile_faq(get_post_meta($post_id, '_webtanan_doctor_faq', true));
    }

    private static function sanitize_profile_faq($value): array {
        if (is_string($value)) {
            $rows = preg_split('/\r\n|\r|\n/', $value);
            $value = array();
            foreach ((array) $rows as $row) {
                $parts = preg_split('/\s*[|｜]\s*/u', (string) $row, 2);
                if (2 === count($parts)) {
                    $value[] = array('question' => $parts[0], 'answer' => $parts[1]);
                }
            }
        }
        if (!is_array($value)) {
            return array();
        }

        $items = array();
        foreach ($value as $item) {
            if (!is_array($item)) {
                continue;
            }
            $question = sanitize_text_field((string) ($item['question'] ?? ''));
            $answer = sanitize_textarea_field((string) ($item['answer'] ?? ''));
            if ('' !== $question && '' !== $answer) {
                $items[] = array('question' => $question, 'answer' => $answer);
            }
        }

        return $items;
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
