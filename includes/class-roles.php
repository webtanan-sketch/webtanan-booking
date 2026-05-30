<?php
/**
 * WordPress roles and capabilities.
 *
 * @package WebtananBooking
 */

namespace Webtanan\Booking;

defined('ABSPATH') || exit;

final class Roles {
    public static function add_roles(): void {
        add_role(
            'webtanan_doctor',
            __('پزشک وب‌تنان', 'webtanan-booking'),
            array(
                'read' => true,
                'webtanan_access_doctor_dashboard' => true,
                'webtanan_manage_own_schedule' => true,
                'webtanan_manage_own_appointments' => true,
                'webtanan_request_settlement' => true,
            )
        );

        add_role(
            'webtanan_secretary',
            __('منشی وب‌تنان', 'webtanan-booking'),
            array(
                'read' => true,
                'webtanan_access_secretary_dashboard' => true,
                'webtanan_manage_assigned_appointments' => true,
            )
        );

        add_role(
            'webtanan_patient',
            __('بیمار وب‌تنان', 'webtanan-booking'),
            array(
                'read' => true,
                'webtanan_access_patient_panel' => true,
            )
        );

        $admin = get_role('administrator');
        if ($admin) {
            foreach (self::admin_capabilities() as $capability) {
                $admin->add_cap($capability);
            }
        }
    }

    public static function remove_roles(): void {
        remove_role('webtanan_doctor');
        remove_role('webtanan_secretary');
        remove_role('webtanan_patient');
    }

    public static function admin_capabilities(): array {
        return array(
            'webtanan_manage_booking',
            'webtanan_manage_doctors',
            'webtanan_manage_finance',
            'webtanan_manage_settings',
            'webtanan_view_reports',
            'webtanan_impersonate_staff',
        );
    }
}
