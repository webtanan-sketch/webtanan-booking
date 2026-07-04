<?php
/**
 * Patient identity, profile completion and dependents.
 *
 * @package WebtananBooking
 */

namespace Webtanan\Booking;

defined('ABSPATH') || exit;

final class Patient_Profile {
    private const DEPENDENTS_META_KEY = 'webtanan_patient_dependents';
    private const NATIONAL_CODE_META_KEY = 'webtanan_national_code';
    private const MAX_DEPENDENTS = 20;

    public static function context(int $user_id = 0): array {
        $user_id = $user_id ?: get_current_user_id();
        $user = $user_id > 0 ? get_userdata($user_id) : false;
        if (!$user) {
            return array(
                'logged_in' => false,
                'profile_complete' => false,
                'profile' => array(),
                'dependents' => array(),
                'roles' => array(),
                'redirect_url' => self::login_url(),
            );
        }

        $profile = self::profile($user_id);

        return array(
            'logged_in' => true,
            'profile_complete' => (bool) ($profile['complete'] ?? false),
            'profile' => $profile,
            'dependents' => self::dependents($user_id),
            'roles' => array_values(array_map('sanitize_key', (array) $user->roles)),
            'redirect_url' => self::redirect_url($user_id),
        );
    }

    public static function profile(int $user_id = 0): array {
        $user_id = $user_id ?: get_current_user_id();
        $user = $user_id > 0 ? get_userdata($user_id) : false;
        if (!$user) {
            return array();
        }

        $first_name = sanitize_text_field((string) get_user_meta($user_id, 'first_name', true));
        $last_name = sanitize_text_field((string) get_user_meta($user_id, 'last_name', true));
        $national_code = self::normalize_national_code((string) get_user_meta($user_id, self::NATIONAL_CODE_META_KEY, true));
        $mobile = self::user_mobile($user_id);
        $full_name = trim($first_name . ' ' . $last_name);

        return array(
            'id' => $user_id,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'full_name' => $full_name,
            'display_name' => $full_name ?: sanitize_text_field((string) $user->display_name),
            'national_code' => $national_code,
            'mobile' => $mobile,
            'complete' => '' !== $first_name && '' !== $last_name && self::is_valid_national_code($national_code),
        );
    }

    public static function update_profile(int $user_id, array $input) {
        if ($user_id <= 0 || !get_userdata($user_id)) {
            return new \WP_Error('webtanan_patient_profile_user_missing', __('حساب کاربری پیدا نشد.', 'webtanan-booking'), array('status' => 404));
        }

        $first_name = sanitize_text_field((string) ($input['first_name'] ?? ''));
        $last_name = sanitize_text_field((string) ($input['last_name'] ?? ''));
        $national_code = self::normalize_national_code((string) ($input['national_code'] ?? ''));

        if ('' === $first_name || '' === $last_name) {
            return new \WP_Error('webtanan_patient_profile_name_required', __('نام و نام خانوادگی را کامل وارد کنید.', 'webtanan-booking'), array('status' => 400));
        }

        if (!self::is_valid_national_code($national_code)) {
            return new \WP_Error('webtanan_patient_profile_national_code_invalid', __('کد ملی واردشده معتبر نیست.', 'webtanan-booking'), array('status' => 400));
        }

        $updated = wp_update_user(
            array(
                'ID' => $user_id,
                'first_name' => $first_name,
                'last_name' => $last_name,
                'display_name' => trim($first_name . ' ' . $last_name),
            )
        );
        if (is_wp_error($updated)) {
            return $updated;
        }

        update_user_meta($user_id, self::NATIONAL_CODE_META_KEY, $national_code);

        return self::context($user_id);
    }

    public static function dependents(int $user_id = 0): array {
        $user_id = $user_id ?: get_current_user_id();
        if ($user_id <= 0) {
            return array();
        }

        $items = get_user_meta($user_id, self::DEPENDENTS_META_KEY, true);
        if (!is_array($items)) {
            return array();
        }

        $dependents = array();
        foreach ($items as $item) {
            if (!is_array($item) || empty($item['id'])) {
                continue;
            }

            $dependents[] = self::sanitize_dependent($item);
        }

        return array_values($dependents);
    }

    public static function save_dependent(int $user_id, array $input) {
        if ($user_id <= 0 || !get_userdata($user_id)) {
            return new \WP_Error('webtanan_dependent_user_missing', __('حساب کاربری پیدا نشد.', 'webtanan-booking'), array('status' => 404));
        }

        $dependents = self::dependents($user_id);
        $requested_id = sanitize_key((string) ($input['id'] ?? ''));
        $first_name = sanitize_text_field((string) ($input['first_name'] ?? ''));
        $last_name = sanitize_text_field((string) ($input['last_name'] ?? ''));
        $national_code = self::normalize_national_code((string) ($input['national_code'] ?? ''));
        $mobile = OTP::normalize_mobile((string) ($input['mobile'] ?? ''));
        $relationship = sanitize_text_field((string) ($input['relationship'] ?? ''));

        if ('' === $first_name || '' === $last_name) {
            return new \WP_Error('webtanan_dependent_name_required', __('نام و نام خانوادگی مراجعه‌کننده را وارد کنید.', 'webtanan-booking'), array('status' => 400));
        }

        if (!self::is_valid_national_code($national_code)) {
            return new \WP_Error('webtanan_dependent_national_code_invalid', __('کد ملی مراجعه‌کننده معتبر نیست.', 'webtanan-booking'), array('status' => 400));
        }

        if ('' !== $mobile && !preg_match('/^\+?[0-9]{10,15}$/', $mobile)) {
            return new \WP_Error('webtanan_dependent_mobile_invalid', __('شماره موبایل مراجعه‌کننده معتبر نیست.', 'webtanan-booking'), array('status' => 400));
        }

        foreach ($dependents as $dependent) {
            if ($dependent['national_code'] === $national_code && $dependent['id'] !== $requested_id) {
                return new \WP_Error('webtanan_dependent_duplicate', __('این فرد قبلاً به حساب شما اضافه شده است.', 'webtanan-booking'), array('status' => 409));
            }
        }

        $item = array(
            'id' => $requested_id ?: strtolower(wp_generate_uuid4()),
            'first_name' => $first_name,
            'last_name' => $last_name,
            'full_name' => trim($first_name . ' ' . $last_name),
            'national_code' => $national_code,
            'mobile' => $mobile,
            'relationship' => $relationship ?: __('عضو خانواده', 'webtanan-booking'),
        );

        $found = false;
        foreach ($dependents as $index => $dependent) {
            if ($dependent['id'] === $item['id']) {
                $dependents[$index] = $item;
                $found = true;
                break;
            }
        }

        if (!$found) {
            if (count($dependents) >= self::MAX_DEPENDENTS) {
                return new \WP_Error('webtanan_dependents_limit', __('حداکثر تعداد افراد قابل تعریف برای این حساب تکمیل شده است.', 'webtanan-booking'), array('status' => 409));
            }
            $dependents[] = $item;
        }

        update_user_meta($user_id, self::DEPENDENTS_META_KEY, array_values($dependents));

        return array(
            'dependent' => $item,
            'dependents' => array_values($dependents),
        );
    }

    public static function delete_dependent(int $user_id, string $dependent_id) {
        $dependent_id = sanitize_key($dependent_id);
        $dependents = self::dependents($user_id);
        $filtered = array_values(
            array_filter(
                $dependents,
                static function (array $dependent) use ($dependent_id): bool {
                    return $dependent['id'] !== $dependent_id;
                }
            )
        );

        if (count($filtered) === count($dependents)) {
            return new \WP_Error('webtanan_dependent_not_found', __('فرد موردنظر پیدا نشد.', 'webtanan-booking'), array('status' => 404));
        }

        update_user_meta($user_id, self::DEPENDENTS_META_KEY, $filtered);

        return array('deleted' => true, 'dependents' => $filtered);
    }

    public static function booking_identity(int $user_id, string $dependent_id = '') {
        $profile = self::profile($user_id);
        if (!$profile || empty($profile['complete'])) {
            return new \WP_Error(
                'webtanan_patient_profile_incomplete',
                __('برای رزرو نوبت ابتدا اطلاعات هویتی حساب را کامل کنید.', 'webtanan-booking'),
                array('status' => 409, 'profile_required' => true)
            );
        }

        $dependent_id = sanitize_key($dependent_id);
        if ('' === $dependent_id || 'self' === $dependent_id) {
            return array(
                'patient_user_id' => $user_id,
                'patient_first_name' => $profile['first_name'],
                'patient_last_name' => $profile['last_name'],
                'patient_national_code' => $profile['national_code'],
                'patient_mobile' => $profile['mobile'],
                'dependent_id' => '',
            );
        }

        foreach (self::dependents($user_id) as $dependent) {
            if ($dependent['id'] !== $dependent_id) {
                continue;
            }

            return array(
                'patient_user_id' => $user_id,
                'patient_first_name' => $dependent['first_name'],
                'patient_last_name' => $dependent['last_name'],
                'patient_national_code' => $dependent['national_code'],
                'patient_mobile' => $dependent['mobile'] ?: $profile['mobile'],
                'dependent_id' => $dependent['id'],
            );
        }

        return new \WP_Error('webtanan_dependent_forbidden', __('مراجعه‌کننده انتخاب‌شده به حساب شما تعلق ندارد.', 'webtanan-booking'), array('status' => 403));
    }

    public static function user_mobile(int $user_id): string {
        foreach (array('webtanan_mobile', 'billing_phone', 'mobile', 'phone') as $meta_key) {
            $mobile = OTP::normalize_mobile((string) get_user_meta($user_id, $meta_key, true));
            if ('' !== $mobile) {
                return $mobile;
            }
        }

        $user = get_userdata($user_id);
        if ($user && preg_match('/^(?:\+?98|0098|0)?9\d{9}$/', (string) $user->user_login)) {
            return OTP::normalize_mobile((string) $user->user_login);
        }

        return '';
    }

    public static function find_user_id_by_mobile(string $mobile, int $exclude_user_id = 0): int {
        $mobile = OTP::normalize_mobile($mobile);
        if ('' === $mobile) {
            return 0;
        }

        $digits = preg_replace('/\D+/', '', $mobile) ?: '';
        $local = '98' === substr($digits, 0, 2) ? '0' . substr($digits, 2) : $digits;
        $variants = array_values(array_unique(array_filter(array($mobile, $digits, '+' . $digits, $local))));
        $args = array(
            'number' => 1,
            'fields' => 'ID',
            'meta_query' => array(
                'relation' => 'OR',
                array('key' => 'webtanan_mobile', 'value' => $variants, 'compare' => 'IN'),
                array('key' => 'billing_phone', 'value' => $variants, 'compare' => 'IN'),
                array('key' => 'mobile', 'value' => $variants, 'compare' => 'IN'),
                array('key' => 'phone', 'value' => $variants, 'compare' => 'IN'),
            ),
        );
        if ($exclude_user_id > 0) {
            $args['exclude'] = array($exclude_user_id);
        }

        $users = get_users($args);
        if ($users) {
            return (int) $users[0];
        }

        foreach ($variants as $variant) {
            $user = get_user_by('login', $variant);
            if ($user instanceof \WP_User && (int) $user->ID !== $exclude_user_id) {
                return (int) $user->ID;
            }
        }

        return 0;
    }

    public static function redirect_url(int $user_id): string {
        $user = get_userdata($user_id);
        if (!$user) {
            return self::login_url();
        }

        $roles = array_map('sanitize_key', (array) $user->roles);
        if (user_can($user, 'manage_options')) {
            return admin_url();
        }

        if (array_intersect(array('webtanan_doctor', 'webtanan_secretary'), $roles)) {
            $page_id = absint(get_option('webtanan_saas_doctor_dashboard_page_id'));
            if (!$page_id) {
                $page = get_page_by_path('داشبورد-پزشک') ?: get_page_by_path('doctor-dashboard');
                $page_id = $page instanceof \WP_Post ? (int) $page->ID : 0;
            }

            return $page_id ? get_permalink($page_id) : home_url('/doctor-dashboard/');
        }

        $page_id = absint(get_option('webtanan_saas_patient_portal_page_id'));
        if (!$page_id) {
            $page = get_page_by_path('پورتال-بیمار') ?: get_page_by_path('portal-patient');
            $page_id = $page instanceof \WP_Post ? (int) $page->ID : 0;
        }

        return $page_id ? get_permalink($page_id) : home_url('/portal-patient/');
    }

    public static function login_url(): string {
        $page_id = absint(get_option('webtanan_saas_login_page_id'));
        if (!$page_id) {
            $page = get_page_by_path('ورود') ?: get_page_by_path('login');
            $page_id = $page instanceof \WP_Post ? (int) $page->ID : 0;
        }
        if ($page_id) {
            return get_permalink($page_id);
        }

        return add_query_arg('webtanan_auth_page', '1', home_url('/'));
    }

    public static function is_valid_national_code(string $national_code): bool {
        $national_code = self::normalize_national_code($national_code);
        if (!preg_match('/^\d{10}$/', $national_code) || preg_match('/^(\d)\1{9}$/', $national_code)) {
            return false;
        }

        $sum = 0;
        for ($index = 0; $index < 9; $index++) {
            $sum += ((int) $national_code[$index]) * (10 - $index);
        }

        $remainder = $sum % 11;
        $control = (int) $national_code[9];

        return $remainder < 2 ? $control === $remainder : $control === (11 - $remainder);
    }

    private static function normalize_national_code(string $value): string {
        $value = strtr(
            trim($value),
            array(
                '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
                '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
                '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
                '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            )
        );

        return preg_replace('/\D+/', '', $value) ?: '';
    }

    private static function sanitize_dependent(array $item): array {
        $first_name = sanitize_text_field((string) ($item['first_name'] ?? ''));
        $last_name = sanitize_text_field((string) ($item['last_name'] ?? ''));

        return array(
            'id' => sanitize_key((string) ($item['id'] ?? '')),
            'first_name' => $first_name,
            'last_name' => $last_name,
            'full_name' => trim($first_name . ' ' . $last_name),
            'national_code' => self::normalize_national_code((string) ($item['national_code'] ?? '')),
            'mobile' => OTP::normalize_mobile((string) ($item['mobile'] ?? '')),
            'relationship' => sanitize_text_field((string) ($item['relationship'] ?? '')),
        );
    }
}
