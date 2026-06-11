<?php
/**
 * Dummy data seeder for local/admin QA.
 *
 * @package WebtananBooking
 */

namespace Webtanan\Booking;

defined('ABSPATH') || exit;

final class Seeder {
    private const OPTION_LAST_SEEDED = 'webtanan_booking_dummy_seeded_at';
    private const META_DOCTOR_CODE = '_webtanan_dummy_doctor_code';

    public static function init(): void {
        add_action('admin_init', array(__CLASS__, 'maybe_seed_from_admin'));
        add_action('admin_notices', array(__CLASS__, 'admin_notice'));
    }

    public static function maybe_seed_from_admin(): void {
        if (!is_admin() || !current_user_can('manage_options')) {
            return;
        }

        if (!isset($_GET['page'], $_GET['webtanan_seed_data'])) {
            return;
        }

        if ('webtanan-booking' !== sanitize_key((string) wp_unslash($_GET['page']))) {
            return;
        }

        if ('1' !== sanitize_text_field((string) wp_unslash($_GET['webtanan_seed_data']))) {
            return;
        }

        self::seed_dummy_data();

        wp_safe_redirect(
            add_query_arg(
                array(
                    'page' => 'webtanan-booking',
                    'webtanan_seeded' => '1',
                ),
                admin_url('admin.php')
            )
        );
        exit;
    }

    public static function admin_notice(): void {
        if (!is_admin() || !current_user_can('manage_options')) {
            return;
        }

        if (!isset($_GET['page'], $_GET['webtanan_seeded'])) {
            return;
        }

        if ('webtanan-booking' !== sanitize_key((string) wp_unslash($_GET['page'])) || '1' !== sanitize_text_field((string) wp_unslash($_GET['webtanan_seeded']))) {
            return;
        }

        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Dummy Data Injected Successfully', 'webtanan-booking') . '</p></div>';
    }

    public static function seed_dummy_data(): array {
        global $wpdb;

        if (!function_exists('wp_insert_user') || !function_exists('wp_insert_post')) {
            require_once ABSPATH . 'wp-admin/includes/user.php';
            require_once ABSPATH . 'wp-admin/includes/post.php';
        }

        $now = DB::now();
        $specialties = self::seed_specialties($now);
        $doctors = self::localized_dummy_doctors();
        $created = array(
            'specialties' => count($specialties),
            'users' => 0,
            'posts' => 0,
            'doctors' => 0,
            'schedules' => 0,
        );

        foreach ($doctors as $index => $doctor) {
            $user_id = self::upsert_doctor_user($index, $doctor);
            if ($user_id > 0) {
                $created['users']++;
            }

            $post_id = self::upsert_doctor_post($doctor, $user_id);
            if ($post_id > 0) {
                $created['posts']++;
            }

            $specialty_id = (int) ($specialties[$doctor['specialty_slug']] ?? reset($specialties));
            $doctor_id = self::upsert_doctor_row($doctor, $post_id, $user_id, $specialty_id, $now);
            if ($doctor_id > 0) {
                $created['doctors']++;
                $created['schedules'] += self::upsert_schedules($doctor_id, $index, $now);
            }
        }

        update_option(self::OPTION_LAST_SEEDED, $now, false);

        return $created;
    }

    private static function seed_specialties(string $now): array {
        global $wpdb;

        $table = DB::table('specialties');
        $items = array(
            array('name' => 'قلب و عروق', 'slug' => 'cardiology', 'sort_order' => 10),
            array('name' => 'دندانپزشک', 'slug' => 'dentist', 'sort_order' => 20),
            array('name' => 'چشم پزشک', 'slug' => 'ophthalmologist', 'sort_order' => 30),
        );
        $items = self::localized_dummy_specialties();
        $ids = array();

        foreach ($items as $item) {
            $existing_id = (int) $wpdb->get_var(
                $wpdb->prepare("SELECT id FROM $table WHERE slug = %s LIMIT 1", $item['slug'])
            );

            $data = array(
                'name' => $item['name'],
                'slug' => $item['slug'],
                'parent_id' => 0,
                'is_active' => 1,
                'sort_order' => (int) $item['sort_order'],
                'updated_at' => $now,
            );

            if ($existing_id > 0) {
                $wpdb->update($table, $data, array('id' => $existing_id));
                $ids[$item['slug']] = $existing_id;
                continue;
            }

            $data['created_at'] = $now;
            $wpdb->insert($table, $data);
            $ids[$item['slug']] = (int) $wpdb->insert_id;
        }

        return $ids;
    }

    private static function localized_dummy_specialties(): array {
        return array(
            array('name' => 'قلب و عروق', 'slug' => 'cardiology', 'sort_order' => 10),
            array('name' => 'دندانپزشک', 'slug' => 'dentist', 'sort_order' => 20),
            array('name' => 'چشم پزشک', 'slug' => 'ophthalmologist', 'sort_order' => 30),
        );
    }

    private static function dummy_doctors(): array {
        return array(
            array(
                'code' => 'webtanan-demo-doctor-1',
                'name' => 'دکتر آرمان نیک‌روش',
                'specialty_slug' => 'cardiology',
                'medical_system_number' => 'WT-10001',
                'clinic_name' => 'کلینیک قلب آریا',
                'clinic_address' => 'تهران، خیابان ولیعصر، بالاتر از پارک ساعی، پلاک ۱۲',
                'clinic_phone' => '02188990011',
                'visit_price' => 450000,
                'booking_fee' => 65000,
                'province_id' => 8,
                'city_id' => 1,
                'bio' => 'متخصص قلب و عروق با تمرکز بر پایش فشار خون، اکوکاردیوگرافی و پیشگیری از بیماری‌های قلبی.',
            ),
            array(
                'code' => 'webtanan-demo-doctor-2',
                'name' => 'دکتر نازنین پارسا',
                'specialty_slug' => 'dentist',
                'medical_system_number' => 'WT-10002',
                'clinic_name' => 'دندانپزشکی لبخند پارسا',
                'clinic_address' => 'تهران، سعادت‌آباد، میدان کاج، ساختمان پزشکان نور',
                'clinic_phone' => '02122334455',
                'visit_price' => 380000,
                'booking_fee' => 55000,
                'province_id' => 8,
                'city_id' => 1,
                'bio' => 'دندانپزشک عمومی با خدمات ترمیم، جرم‌گیری، زیبایی و مشاوره درمان‌های پیشگیرانه.',
            ),
            array(
                'code' => 'webtanan-demo-doctor-3',
                'name' => 'دکتر کیان مهرگان',
                'specialty_slug' => 'ophthalmologist',
                'medical_system_number' => 'WT-10003',
                'clinic_name' => 'مرکز چشم پزشکی مهرگان',
                'clinic_address' => 'کرج، عظیمیه، بلوار شریعتی، مجتمع سلامت مهر',
                'clinic_phone' => '02633445566',
                'visit_price' => 420000,
                'booking_fee' => 60000,
                'province_id' => 5,
                'city_id' => 2,
                'bio' => 'چشم پزشک با تجربه در معاینه کامل چشم، کنترل فشار چشم و تشخیص بیماری‌های شبکیه.',
            ),
            array(
                'code' => 'webtanan-demo-doctor-4',
                'name' => 'دکتر مهسا رادفر',
                'specialty_slug' => 'cardiology',
                'medical_system_number' => 'WT-10004',
                'clinic_name' => 'مطب قلب رادفر',
                'clinic_address' => 'اصفهان، چهارباغ بالا، کوچه بهار، پلاک ۸',
                'clinic_phone' => '03136667788',
                'visit_price' => 400000,
                'booking_fee' => 60000,
                'province_id' => 4,
                'city_id' => 3,
                'bio' => 'فلوشیپ پیشگیری قلب با تمرکز بر چکاپ دوره‌ای، تست ورزش و مدیریت ریسک بیماران.',
            ),
            array(
                'code' => 'webtanan-demo-doctor-5',
                'name' => 'دکتر سینا فرهمند',
                'specialty_slug' => 'dentist',
                'medical_system_number' => 'WT-10005',
                'clinic_name' => 'کلینیک دندان فرهمند',
                'clinic_address' => 'شیراز، خیابان قصردشت، نبش کوچه ۲۴',
                'clinic_phone' => '07136223344',
                'visit_price' => 350000,
                'booking_fee' => 50000,
                'province_id' => 7,
                'city_id' => 4,
                'bio' => 'دندانپزشک خانواده با ارائه خدمات معاینه، درمان پوسیدگی و مراقبت‌های زیبایی دندان.',
            ),
        );
    }

    private static function localized_dummy_doctors(): array {
        return array(
            array(
                'code' => 'webtanan-demo-doctor-1',
                'name' => 'دکتر آرمان نیک روش',
                'specialty_slug' => 'cardiology',
                'medical_system_number' => 'WT-10001',
                'clinic_name' => 'کلینیک قلب آریا',
                'clinic_address' => 'تهران، خیابان ولیعصر، بالاتر از پارک ساعی، پلاک ۱۲',
                'clinic_phone' => '02188990011',
                'visit_price' => 450000,
                'booking_fee' => 65000,
                'province_id' => 8,
                'city_id' => 1,
                'bio' => 'متخصص قلب و عروق با تمرکز بر پایش فشار خون، اکوکاردیوگرافی و پیشگیری از بیماری های قلبی.',
            ),
            array(
                'code' => 'webtanan-demo-doctor-2',
                'name' => 'دکتر نازنین پارسا',
                'specialty_slug' => 'dentist',
                'medical_system_number' => 'WT-10002',
                'clinic_name' => 'دندانپزشکی لبخند پارسا',
                'clinic_address' => 'تهران، سعادت آباد، میدان کاج، ساختمان پزشکان نور',
                'clinic_phone' => '02122334455',
                'visit_price' => 380000,
                'booking_fee' => 55000,
                'province_id' => 8,
                'city_id' => 1,
                'bio' => 'دندانپزشک عمومی با خدمات ترمیم، جرم گیری، زیبایی و مشاوره درمان های پیشگیرانه.',
            ),
            array(
                'code' => 'webtanan-demo-doctor-3',
                'name' => 'دکتر کیان مهرگان',
                'specialty_slug' => 'ophthalmologist',
                'medical_system_number' => 'WT-10003',
                'clinic_name' => 'مرکز چشم پزشکی مهرگان',
                'clinic_address' => 'کرج، عظیمیه، بلوار شریعتی، مجتمع سلامت مهر',
                'clinic_phone' => '02633445566',
                'visit_price' => 420000,
                'booking_fee' => 60000,
                'province_id' => 5,
                'city_id' => 2,
                'bio' => 'چشم پزشک با تجربه در معاینه کامل چشم، کنترل فشار چشم و تشخیص بیماری های شبکیه.',
            ),
            array(
                'code' => 'webtanan-demo-doctor-4',
                'name' => 'دکتر مهسا رادفر',
                'specialty_slug' => 'cardiology',
                'medical_system_number' => 'WT-10004',
                'clinic_name' => 'مطب قلب رادفر',
                'clinic_address' => 'اصفهان، چهارباغ بالا، کوچه بهار، پلاک ۸',
                'clinic_phone' => '03136667788',
                'visit_price' => 400000,
                'booking_fee' => 60000,
                'province_id' => 4,
                'city_id' => 3,
                'bio' => 'فلوشیپ پیشگیری قلب با تمرکز بر چکاپ دوره ای، تست ورزش و مدیریت ریسک بیماران.',
            ),
            array(
                'code' => 'webtanan-demo-doctor-5',
                'name' => 'دکتر سینا فرهمند',
                'specialty_slug' => 'dentist',
                'medical_system_number' => 'WT-10005',
                'clinic_name' => 'کلینیک دندان فرهمند',
                'clinic_address' => 'شیراز، خیابان قصردشت، نبش کوچه ۲۴',
                'clinic_phone' => '07136223344',
                'visit_price' => 350000,
                'booking_fee' => 50000,
                'province_id' => 7,
                'city_id' => 4,
                'bio' => 'دندانپزشک خانواده با ارائه خدمات معاینه، درمان پوسیدگی و مراقبت های زیبایی دندان.',
            ),
        );
    }

    private static function upsert_doctor_user(int $index, array $doctor): int {
        $username = 'webtanan_demo_doctor_' . ($index + 1);
        $email = $username . '@example.test';
        $user_id = username_exists($username);

        if (!$user_id) {
            $user_id = email_exists($email);
        }

        if (!$user_id) {
            $user_id = wp_insert_user(
                array(
                    'user_login' => $username,
                    'user_pass' => wp_generate_password(24, true, true),
                    'user_email' => $email,
                    'display_name' => $doctor['name'],
                    'first_name' => $doctor['name'],
                    'role' => 'webtanan_doctor',
                )
            );
        }

        if (is_wp_error($user_id)) {
            return 0;
        }

        $user = new \WP_User((int) $user_id);
        if (!$user->has_cap('webtanan_access_doctor_dashboard')) {
            $user->set_role('webtanan_doctor');
        }

        update_user_meta((int) $user_id, 'webtanan_dummy_user', 'yes');

        return (int) $user_id;
    }

    private static function upsert_doctor_post(array $doctor, int $user_id): int {
        global $wpdb;

        $post_id = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = %s AND meta_value = %s LIMIT 1",
                self::META_DOCTOR_CODE,
                $doctor['code']
            )
        );

        $postarr = array(
            'post_type' => 'saas_doctors',
            'post_status' => 'publish',
            'post_title' => $doctor['name'],
            'post_name' => sanitize_title($doctor['code']),
            'post_content' => $doctor['bio'],
            'post_excerpt' => wp_trim_words($doctor['bio'], 24),
            'post_author' => $user_id ?: get_current_user_id(),
        );

        if ($post_id > 0 && get_post($post_id)) {
            $postarr['ID'] = $post_id;
            $updated = wp_update_post($postarr, true);
            if (!is_wp_error($updated)) {
                update_post_meta($post_id, self::META_DOCTOR_CODE, $doctor['code']);
                return (int) $updated;
            }
        }

        $inserted = wp_insert_post($postarr, true);
        if (is_wp_error($inserted)) {
            return 0;
        }

        update_post_meta((int) $inserted, self::META_DOCTOR_CODE, $doctor['code']);

        return (int) $inserted;
    }

    private static function upsert_doctor_row(array $doctor, int $post_id, int $user_id, int $specialty_id, string $now): int {
        global $wpdb;

        if ($post_id <= 0 || $user_id <= 0 || $specialty_id <= 0) {
            return 0;
        }

        $table = DB::table('doctors');
        $existing_id = (int) $wpdb->get_var(
            $wpdb->prepare("SELECT id FROM $table WHERE doctor_code = %s OR post_id = %d LIMIT 1", $doctor['code'], $post_id)
        );

        $data = array(
            'post_id' => $post_id,
            'user_id' => $user_id,
            'doctor_code' => $doctor['code'],
            'medical_system_number' => $doctor['medical_system_number'],
            'specialty_id' => $specialty_id,
            'city_id' => (int) $doctor['city_id'],
            'province_id' => (int) $doctor['province_id'],
            'clinic_name' => $doctor['clinic_name'],
            'clinic_address' => $doctor['clinic_address'],
            'clinic_phone' => $doctor['clinic_phone'],
            'visit_price' => (float) $doctor['visit_price'],
            'booking_fee' => (float) $doctor['booking_fee'],
            'booking_fee_share_type' => 'percent',
            'booking_fee_share_value' => 80,
            'platform_commission_type' => 'percent',
            'platform_commission_value' => 15,
            'iban' => 'IR000000000000000000000000',
            'bank_account_owner' => $doctor['name'],
            'is_active' => 1,
            'is_verified' => 1,
            'allow_online_payment' => 1,
            'allow_pay_at_clinic' => 1,
            'updated_at' => $now,
        );

        if ($existing_id > 0) {
            $wpdb->update($table, $data, array('id' => $existing_id));
            return $existing_id;
        }

        $data['created_at'] = $now;
        $wpdb->insert($table, $data);

        return (int) $wpdb->insert_id;
    }

    private static function upsert_schedules(int $doctor_id, int $doctor_index, string $now): int {
        global $wpdb;

        $table = DB::table('schedules');
        $weekdays = self::weekdays_for_next_30_days();
        $created = 0;
        $duration = 15 + (($doctor_index % 2) * 5);
        $capacity = 1;
        $shift_one_start = '09:00:00';
        $shift_one_end = '13:00:00';
        $shift_two_start = '16:00:00';
        $shift_two_end = '20:00:00';

        foreach ($weekdays as $weekday) {
            $created += self::upsert_schedule_row($table, $doctor_id, $weekday, $shift_one_start, $shift_one_end, $duration, $capacity, $now);
            $created += self::upsert_schedule_row($table, $doctor_id, $weekday, $shift_two_start, $shift_two_end, $duration, $capacity, $now);
        }

        return $created;
    }

    private static function upsert_schedule_row(string $table, int $doctor_id, string $weekday, string $start_time, string $end_time, int $duration, int $capacity, string $now): int {
        global $wpdb;

        $existing_id = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM $table WHERE doctor_id = %d AND weekday = %s AND start_time = %s AND end_time = %s LIMIT 1",
                $doctor_id,
                $weekday,
                $start_time,
                $end_time
            )
        );

        $data = array(
            'doctor_id' => $doctor_id,
            'weekday' => $weekday,
            'start_time' => $start_time,
            'end_time' => $end_time,
            'slot_duration' => $duration,
            'capacity_per_slot' => $capacity,
            'is_active' => 1,
            'updated_at' => $now,
        );

        if ($existing_id > 0) {
            $wpdb->update($table, $data, array('id' => $existing_id));
            return 0;
        }

        $data['created_at'] = $now;
        $wpdb->insert($table, $data);

        return $wpdb->insert_id ? 1 : 0;
    }

    private static function weekdays_for_next_30_days(): array {
        $weekdays = array();
        $start = current_time('timestamp');

        for ($i = 0; $i <= 30; $i++) {
            $weekdays[] = strtolower(date('l', $start + ($i * DAY_IN_SECONDS)));
        }

        return array_values(array_unique($weekdays));
    }
}
