<?php
/**
 * Database table helpers and schema installation.
 *
 * @package WebtananBooking
 */

namespace Webtanan\Booking;

defined('ABSPATH') || exit;

final class DB {
    public const OPTION_DB_VERSION = 'webtanan_booking_db_version';
    public const OPTION_SETTINGS = 'webtanan_booking_settings';

    private const TABLES = array(
        'doctors' => 'saas_doctors',
        'specialties' => 'saas_specialties',
        'schedules' => 'saas_schedules',
        'schedule_exceptions' => 'saas_schedule_exceptions',
        'appointments' => 'saas_appointments',
        'transactions' => 'saas_transactions',
        'wallets_ledger' => 'saas_wallets_ledger',
        'settlement_requests' => 'saas_settlement_requests',
        'otp_logs' => 'saas_otp_logs',
        'sms_logs' => 'saas_sms_logs',
        'patient_records' => 'saas_patient_records',
        'patient_record_notes' => 'saas_patient_record_notes',
        'survey_responses' => 'saas_survey_responses',
    );

    public static function table(string $key): string {
        global $wpdb;

        return $wpdb->prefix . (self::TABLES[$key] ?? $key);
    }

    public static function now(): string {
        return current_time('mysql');
    }

    public static function utc_now(): string {
        return gmdate('Y-m-d H:i:s');
    }

    public static function create_tables(): void {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();
        $doctors = self::table('doctors');
        $specialties = self::table('specialties');
        $schedules = self::table('schedules');
        $exceptions = self::table('schedule_exceptions');
        $appointments = self::table('appointments');
        $transactions = self::table('transactions');
        $ledger = self::table('wallets_ledger');
        $settlements = self::table('settlement_requests');
        $otp = self::table('otp_logs');
        $sms = self::table('sms_logs');
        $patient_records = self::table('patient_records');
        $patient_record_notes = self::table('patient_record_notes');
        $survey_responses = self::table('survey_responses');

        $schemas = array();

        $schemas[] = "CREATE TABLE $doctors (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            post_id bigint(20) unsigned NOT NULL DEFAULT 0,
            user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            secretary_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            doctor_code varchar(64) NOT NULL DEFAULT '',
            medical_system_number varchar(64) NOT NULL DEFAULT '',
            specialty_id bigint(20) unsigned NOT NULL DEFAULT 0,
            city_id bigint(20) unsigned NOT NULL DEFAULT 0,
            province_id bigint(20) unsigned NOT NULL DEFAULT 0,
            clinic_name varchar(191) NOT NULL DEFAULT '',
            clinic_address text NULL,
            clinic_phone varchar(32) NOT NULL DEFAULT '',
            visit_price decimal(14,2) NOT NULL DEFAULT 0.00,
            booking_fee decimal(14,2) NOT NULL DEFAULT 0.00,
            booking_fee_share_type varchar(20) NOT NULL DEFAULT 'percent',
            booking_fee_share_value decimal(14,2) NOT NULL DEFAULT 0.00,
            platform_commission_type varchar(20) NOT NULL DEFAULT 'percent',
            platform_commission_value decimal(14,2) NOT NULL DEFAULT 0.00,
            iban varchar(34) NOT NULL DEFAULT '',
            bank_account_owner varchar(191) NOT NULL DEFAULT '',
            is_active tinyint(1) NOT NULL DEFAULT 1,
            is_verified tinyint(1) NOT NULL DEFAULT 0,
            allow_online_payment tinyint(1) NOT NULL DEFAULT 1,
            allow_pay_at_clinic tinyint(1) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY post_id (post_id),
            KEY user_id (user_id),
            KEY secretary_user_id (secretary_user_id),
            KEY specialty_id (specialty_id),
            KEY city_id (city_id),
            KEY province_id (province_id),
            KEY is_active (is_active),
            KEY is_verified (is_verified)
        ) $charset_collate;";

        $schemas[] = "CREATE TABLE $specialties (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(191) NOT NULL,
            slug varchar(191) NOT NULL,
            parent_id bigint(20) unsigned NOT NULL DEFAULT 0,
            is_active tinyint(1) NOT NULL DEFAULT 1,
            sort_order int(11) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY slug (slug),
            KEY parent_id (parent_id),
            KEY is_active (is_active),
            KEY sort_order (sort_order)
        ) $charset_collate;";

        $schemas[] = "CREATE TABLE $schedules (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            doctor_id bigint(20) unsigned NOT NULL,
            weekday varchar(20) NOT NULL,
            start_time time NOT NULL,
            end_time time NOT NULL,
            slot_duration int(11) NOT NULL DEFAULT 15,
            capacity_per_slot int(11) NOT NULL DEFAULT 1,
            is_active tinyint(1) NOT NULL DEFAULT 1,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY doctor_weekday (doctor_id,weekday),
            KEY is_active (is_active)
        ) $charset_collate;";

        $schemas[] = "CREATE TABLE $exceptions (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            doctor_id bigint(20) unsigned NOT NULL,
            exception_date date NOT NULL,
            type varchar(32) NOT NULL,
            start_time time NULL,
            end_time time NULL,
            slot_duration int(11) NOT NULL DEFAULT 15,
            capacity_per_slot int(11) NOT NULL DEFAULT 1,
            reason text NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY doctor_date (doctor_id,exception_date),
            KEY type (type)
        ) $charset_collate;";

        $schemas[] = "CREATE TABLE $appointments (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            appointment_code varchar(64) NOT NULL,
            doctor_id bigint(20) unsigned NOT NULL,
            patient_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            patient_first_name varchar(100) NOT NULL DEFAULT '',
            patient_last_name varchar(100) NOT NULL DEFAULT '',
            patient_national_code varchar(20) NOT NULL DEFAULT '',
            patient_mobile varchar(32) NOT NULL DEFAULT '',
            appointment_date date NOT NULL,
            start_time time NOT NULL,
            end_time time NOT NULL,
            visit_price decimal(14,2) NOT NULL DEFAULT 0.00,
            booking_fee decimal(14,2) NOT NULL DEFAULT 0.00,
            display_visit_price decimal(14,2) NOT NULL DEFAULT 0.00,
            payment_method varchar(32) NOT NULL DEFAULT '',
            payment_status varchar(32) NOT NULL DEFAULT 'unpaid',
            appointment_status varchar(32) NOT NULL DEFAULT 'pending',
            locked_until datetime NULL,
            lock_token varchar(128) NOT NULL DEFAULT '',
            transaction_id bigint(20) unsigned NOT NULL DEFAULT 0,
            cancelled_by varchar(64) NOT NULL DEFAULT '',
            cancel_reason text NULL,
            confirmed_at datetime NULL,
            cancelled_at datetime NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY appointment_code (appointment_code),
            UNIQUE KEY unique_doctor_slot (doctor_id,appointment_date,start_time),
            KEY idx_doctor_date (doctor_id,appointment_date),
            KEY idx_patient_user (patient_user_id),
            KEY idx_status (appointment_status),
            KEY idx_payment_status (payment_status),
            KEY idx_locked_until (locked_until),
            KEY idx_transaction_id (transaction_id)
        ) $charset_collate;";

        $schemas[] = "CREATE TABLE $transactions (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            transaction_code varchar(64) NOT NULL,
            user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            doctor_id bigint(20) unsigned NOT NULL DEFAULT 0,
            appointment_id bigint(20) unsigned NOT NULL DEFAULT 0,
            gateway_name varchar(64) NOT NULL DEFAULT '',
            gateway_transid varchar(100) NOT NULL DEFAULT '',
            gateway_authority varchar(191) NOT NULL DEFAULT '',
            gateway_ref_id varchar(191) NOT NULL DEFAULT '',
            gateway_tracking_number varchar(100) NOT NULL DEFAULT '',
            gateway_card_number varchar(30) NOT NULL DEFAULT '',
            gateway_bank varchar(100) NOT NULL DEFAULT '',
            invoice_id varchar(100) NOT NULL DEFAULT '',
            callback_status varchar(10) NOT NULL DEFAULT '',
            amount decimal(14,2) NOT NULL DEFAULT 0.00,
            status varchar(32) NOT NULL DEFAULT 'initiated',
            request_payload longtext NULL,
            callback_payload longtext NULL,
            create_response longtext NULL,
            verify_payload longtext NULL,
            verify_response longtext NULL,
            error_code varchar(50) NOT NULL DEFAULT '',
            error_message text NULL,
            verified_at datetime NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY transaction_code (transaction_code),
            KEY user_id (user_id),
            KEY doctor_id (doctor_id),
            KEY appointment_id (appointment_id),
            KEY gateway_transid (gateway_transid),
            KEY gateway_authority (gateway_authority),
            KEY invoice_id (invoice_id),
            KEY status (status)
        ) $charset_collate;";

        $schemas[] = "CREATE TABLE $ledger (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            user_type varchar(32) NOT NULL,
            related_appointment_id bigint(20) unsigned NOT NULL DEFAULT 0,
            related_transaction_id bigint(20) unsigned NOT NULL DEFAULT 0,
            entry_type varchar(32) NOT NULL,
            amount decimal(14,2) NOT NULL DEFAULT 0.00,
            balance_after decimal(14,2) NOT NULL DEFAULT 0.00,
            description text NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY user_lookup (user_id,user_type),
            KEY related_appointment_id (related_appointment_id),
            KEY related_transaction_id (related_transaction_id),
            KEY entry_type (entry_type),
            KEY created_at (created_at)
        ) $charset_collate;";

        $schemas[] = "CREATE TABLE $settlements (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            doctor_id bigint(20) unsigned NOT NULL,
            amount decimal(14,2) NOT NULL DEFAULT 0.00,
            iban varchar(34) NOT NULL DEFAULT '',
            status varchar(32) NOT NULL DEFAULT 'pending',
            bank_tracking_number varchar(100) NOT NULL DEFAULT '',
            admin_note text NULL,
            requested_at datetime NULL,
            processed_at datetime NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY doctor_id (doctor_id),
            KEY status (status),
            KEY requested_at (requested_at)
        ) $charset_collate;";

        $schemas[] = "CREATE TABLE $otp (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            mobile varchar(32) NOT NULL,
            otp_code_hash varchar(255) NOT NULL,
            purpose varchar(32) NOT NULL DEFAULT 'login',
            expires_at datetime NOT NULL,
            attempt_count int(11) NOT NULL DEFAULT 0,
            is_used tinyint(1) NOT NULL DEFAULT 0,
            ip_address varchar(64) NOT NULL DEFAULT '',
            user_agent text NULL,
            created_at datetime NOT NULL,
            used_at datetime NULL,
            PRIMARY KEY  (id),
            KEY mobile (mobile),
            KEY mobile_purpose_created (mobile,purpose,created_at),
            KEY purpose (purpose),
            KEY expires_at (expires_at),
            KEY is_used (is_used),
            KEY created_at (created_at)
        ) $charset_collate;";

        $schemas[] = "CREATE TABLE $sms (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            mobile varchar(32) NOT NULL DEFAULT '',
            pattern_code varchar(100) NOT NULL DEFAULT '',
            message_type varchar(64) NOT NULL,
            variables longtext NULL,
            provider_response longtext NULL,
            status varchar(32) NOT NULL DEFAULT 'pending',
            related_appointment_id bigint(20) unsigned NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY mobile (mobile),
            KEY message_type (message_type),
            KEY status (status),
            KEY related_appointment_id (related_appointment_id),
            KEY created_at (created_at)
        ) $charset_collate;";

        $schemas[] = "CREATE TABLE $patient_records (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            doctor_id bigint(20) unsigned NOT NULL DEFAULT 0,
            patient_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            patient_mobile varchar(32) NOT NULL DEFAULT '',
            patient_national_code varchar(20) NOT NULL DEFAULT '',
            summary longtext NULL,
            allergies text NULL,
            chronic_conditions text NULL,
            current_medications text NULL,
            created_by bigint(20) unsigned NOT NULL DEFAULT 0,
            updated_by bigint(20) unsigned NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY doctor_patient (doctor_id,patient_user_id),
            KEY doctor_id (doctor_id),
            KEY patient_user_id (patient_user_id),
            KEY patient_mobile (patient_mobile),
            KEY patient_national_code (patient_national_code)
        ) $charset_collate;";

        $schemas[] = "CREATE TABLE $patient_record_notes (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            record_id bigint(20) unsigned NOT NULL DEFAULT 0,
            appointment_id bigint(20) unsigned NOT NULL DEFAULT 0,
            author_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            note_type varchar(32) NOT NULL DEFAULT 'visit',
            title varchar(191) NOT NULL DEFAULT '',
            body longtext NULL,
            visibility varchar(32) NOT NULL DEFAULT 'patient',
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY record_id (record_id),
            KEY appointment_id (appointment_id),
            KEY author_user_id (author_user_id),
            KEY visibility (visibility),
            KEY created_at (created_at)
        ) $charset_collate;";

        $schemas[] = "CREATE TABLE $survey_responses (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            appointment_id bigint(20) unsigned NOT NULL DEFAULT 0,
            doctor_id bigint(20) unsigned NOT NULL DEFAULT 0,
            patient_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            rating tinyint(3) unsigned NOT NULL DEFAULT 0,
            feedback text NULL,
            public_consent tinyint(1) NOT NULL DEFAULT 1,
            status varchar(32) NOT NULL DEFAULT 'pending',
            token_hash varchar(128) NOT NULL DEFAULT '',
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY appointment_id (appointment_id),
            KEY doctor_id (doctor_id),
            KEY patient_user_id (patient_user_id),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";

        foreach ($schemas as $schema) {
            dbDelta($schema);
        }

        update_option(self::OPTION_DB_VERSION, WEBTANAN_BOOKING_VERSION);
    }

    public static function default_settings(): array {
        return array(
            'default_commission_type' => 'percent',
            'default_commission_value' => 10,
            'lock_duration_minutes' => 15,
            'otp_expiration_minutes' => 3,
            'otp_max_attempts' => 3,
            'otp_max_sends_per_10_minutes' => 3,
            'otp_rate_limit_max_sends' => 3,
            'otp_rate_limit_window_minutes' => 15,
            'reminder_time_hours' => 24,
            'platform_wallet_user_id' => 0,
            'wallet_topup_min_amount' => 10000,
            'wallet_topup_max_amount' => 50000000,
            'ui_font_family' => '',
            'ui_font_attachment_id' => 0,
            'ui_font_url' => '',
            'gateway_settings' => array(
                'active_gateway' => 'aqayepardakht',
                'merchant_id' => '',
                'callback_url' => '',
                'aqayepardakht' => array(
                    'enabled' => false,
                    'sandbox' => true,
                    'pin' => 'sandbox',
                    'callback_method' => 'GET',
                    'min_amount' => 1000,
                    'max_amount' => 400000000,
                    'description_template' => 'Appointment payment {appointment_code}',
                    'create_url' => 'https://panel.aqayepardakht.ir/api/v2/create',
                    'verify_url' => 'https://panel.aqayepardakht.ir/api/v2/verify',
                    'startpay_url' => 'https://panel.aqayepardakht.ir/startpay',
                ),
            ),
            'sms_provider_settings' => array(
                'enabled' => false,
                'provider' => 'ippanel',
                'base_url' => 'https://edge.ippanel.com/v1',
                'api_key' => '',
                'originator' => '',
                'from_number' => '',
                'test_mode' => false,
                'log_enabled' => true,
                'send_to_patient' => true,
                'send_to_doctor' => true,
                'send_to_secretary' => true,
                'reminder_enabled' => true,
                'patterns' => array(
                    'otp' => array('enabled' => true, 'code' => ''),
                    'appointment_confirmed' => array('enabled' => true, 'code' => ''),
                    'staff_appointment_confirmed' => array('enabled' => true, 'code' => ''),
                    'appointment_cancelled' => array('enabled' => true, 'code' => ''),
                    'staff_appointment_cancelled' => array('enabled' => true, 'code' => ''),
                    'wallet_charged' => array('enabled' => true, 'code' => ''),
                    'late_payment_wallet_charged' => array('enabled' => true, 'code' => ''),
                    'reminder_24h' => array('enabled' => true, 'code' => ''),
                    'payment_failed' => array('enabled' => true, 'code' => ''),
                    'settlement_requested' => array('enabled' => true, 'code' => ''),
                    'settlement_paid' => array('enabled' => true, 'code' => ''),
                    'settlement_status' => array('enabled' => true, 'code' => ''),
                    'appointment_survey' => array('enabled' => true, 'code' => ''),
                    'waiting_list_30m' => array('enabled' => true, 'code' => ''),
                    'bulk_appointment_cancelled' => array('enabled' => true, 'code' => ''),
                ),
            ),
            'cancellation_policy' => array(
                'patient_cancel_until_hours' => 0,
                'full_refund_hours' => 24,
                'full_refund_percent' => 100,
                'partial_refund_hours' => 6,
                'partial_refund_percent' => 50,
                'late_refund_percent' => 0,
                'doctor_admin_full_refund' => true,
            ),
        );
    }

    public static function get_settings(): array {
        $settings = get_option(self::OPTION_SETTINGS, array());

        if (!is_array($settings)) {
            $settings = array();
        }

        return array_replace_recursive(self::default_settings(), $settings);
    }

    public static function update_settings(array $settings): void {
        update_option(self::OPTION_SETTINGS, array_replace_recursive(self::get_settings(), $settings));
    }

    public static function start_transaction(): void {
        global $wpdb;

        $wpdb->query('START TRANSACTION');
    }

    public static function commit(): void {
        global $wpdb;

        $wpdb->query('COMMIT');
        if (class_exists(__NAMESPACE__ . '\Wallet')) {
            Wallet::release_locks();
        }
    }

    public static function rollback(): void {
        global $wpdb;

        $wpdb->query('ROLLBACK');
        if (class_exists(__NAMESPACE__ . '\Wallet')) {
            Wallet::release_locks();
        }
    }

    public static function code(string $prefix): string {
        return strtoupper($prefix) . '-' . strtoupper(wp_generate_password(12, false, false));
    }
}
