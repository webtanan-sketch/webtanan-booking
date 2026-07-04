<?php
/**
 * Doctor CPT and admin metabox syncing to operational table.
 *
 * @package WebtananBooking
 */

namespace Webtanan\Booking;

defined('ABSPATH') || exit;

final class Post_Types {
    public static function init(): void {
        add_action('init', array(__CLASS__, 'register'));
        add_filter('query_vars', array(__CLASS__, 'query_vars'));
        add_action('template_redirect', array(__CLASS__, 'redirect_legacy_specialty_url'), 1);
        add_action('add_meta_boxes_saas_doctors', array(__CLASS__, 'add_doctor_metabox'));
        add_action('save_post_saas_doctors', array(__CLASS__, 'save_doctor_metabox'), 10, 2);
        add_filter('single_template', array(__CLASS__, 'single_template'));
        add_filter('archive_template', array(__CLASS__, 'archive_template'));
    }

    public static function register(): void {
        register_post_type(
            'saas_doctors',
            array(
                'labels' => array(
                    'name' => __('پزشکان', 'webtanan-booking'),
                    'singular_name' => __('پزشک', 'webtanan-booking'),
                    'add_new_item' => __('افزودن پزشک', 'webtanan-booking'),
                    'edit_item' => __('ویرایش پزشک', 'webtanan-booking'),
                    'view_item' => __('نمایش پزشک', 'webtanan-booking'),
                    'search_items' => __('جستجوی پزشکان', 'webtanan-booking'),
                ),
                'public' => true,
                'show_ui' => true,
                'show_in_menu' => false,
                'show_in_rest' => true,
                'has_archive' => true,
                'rewrite' => array('slug' => 'doctors'),
                'menu_icon' => 'dashicons-calendar-alt',
                'supports' => array('title', 'editor', 'thumbnail', 'excerpt', 'custom-fields', 'author'),
            )
        );

        register_taxonomy(
            'saas_doctor_location',
            'saas_doctors',
            array(
                'labels' => array(
                    'name' => __('موقعیت‌های پزشکان', 'webtanan-booking'),
                    'singular_name' => __('موقعیت پزشک', 'webtanan-booking'),
                ),
                'public' => true,
                'show_ui' => true,
                'show_in_rest' => true,
                'hierarchical' => true,
                'rewrite' => array('slug' => 'doctor-location'),
            )
        );

        add_rewrite_rule(
            '^doctors/specialty/([^/]+)/?$',
            'index.php?post_type=saas_doctors&webtanan_specialty=$matches[1]',
            'top'
        );

        if ('2' !== get_option('webtanan_booking_rewrite_version')) {
            flush_rewrite_rules(false);
            update_option('webtanan_booking_rewrite_version', '2', false);
        }
    }

    public static function query_vars(array $vars): array {
        $vars[] = 'webtanan_specialty';

        return $vars;
    }

    public static function specialty_id_from_request(): int {
        global $wpdb;

        $legacy_id = isset($_GET['specialty_id']) ? absint($_GET['specialty_id']) : 0;
        if ($legacy_id > 0) {
            return $legacy_id;
        }

        $slug = sanitize_title(rawurldecode((string) get_query_var('webtanan_specialty')));
        if ('' === $slug) {
            return 0;
        }

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                'SELECT id FROM ' . DB::table('specialties') . ' WHERE slug = %s AND is_active = 1 LIMIT 1',
                $slug
            )
        );
    }

    public static function specialty(int $specialty_id): ?array {
        global $wpdb;

        if ($specialty_id <= 0) {
            return null;
        }

        $row = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT id, name, slug FROM ' . DB::table('specialties') . ' WHERE id = %d AND is_active = 1 LIMIT 1',
                $specialty_id
            ),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    public static function specialty_url(int $specialty_id, string $fallback_search = ''): string {
        $archive_url = get_post_type_archive_link('saas_doctors') ?: add_query_arg('post_type', 'saas_doctors', home_url('/'));
        $specialty = self::specialty($specialty_id);
        if ($specialty && !empty($specialty['slug']) && get_option('permalink_structure')) {
            $slug = rawurlencode(rawurldecode((string) $specialty['slug']));

            return home_url(user_trailingslashit('doctors/specialty/' . $slug));
        }

        if ($specialty_id > 0) {
            return add_query_arg('specialty_id', $specialty_id, $archive_url);
        }

        return '' !== trim($fallback_search) ? add_query_arg('search', trim($fallback_search), $archive_url) : $archive_url;
    }

    public static function redirect_legacy_specialty_url(): void {
        if (is_admin() || wp_doing_ajax() || !is_post_type_archive('saas_doctors') || empty($_GET['specialty_id']) || !get_option('permalink_structure')) {
            return;
        }

        $filter_keys = array('search', 'doctor_search', 'city_id', 'province_id', 'available_only', 'payment_filter');
        foreach ($filter_keys as $key) {
            if (!empty($_GET[$key])) {
                return;
            }
        }

        $specialty_id = absint($_GET['specialty_id']);
        if (!self::specialty($specialty_id)) {
            return;
        }

        wp_safe_redirect(self::specialty_url($specialty_id), 301, 'Webtanan Booking');
        exit;
    }

    public static function add_doctor_metabox(): void {
        add_meta_box(
            'webtanan_booking_doctor_operational',
            __('اطلاعات عملیاتی نوبت‌دهی وب‌تنان', 'webtanan-booking'),
            array(__CLASS__, 'render_doctor_metabox'),
            'saas_doctors',
            'normal',
            'high'
        );
    }

    public static function single_template(string $template): string {
        if (is_singular('saas_doctors')) {
            $custom = WEBTANAN_BOOKING_PATH . 'templates/single-saas-doctors.php';
            if (file_exists($custom)) {
                return $custom;
            }
        }

        return $template;
    }

    public static function archive_template(string $template): string {
        if (is_post_type_archive('saas_doctors')) {
            $custom = WEBTANAN_BOOKING_PATH . 'templates/archive-saas-doctors.php';
            if (file_exists($custom)) {
                return $custom;
            }
        }

        return $template;
    }

    public static function render_doctor_metabox(\WP_Post $post): void {
        global $wpdb;

        wp_nonce_field('webtanan_booking_save_doctor', 'webtanan_booking_doctor_nonce');

        $doctor = $wpdb->get_row(
            $wpdb->prepare('SELECT * FROM ' . DB::table('doctors') . ' WHERE post_id = %d', $post->ID),
            ARRAY_A
        );
        $doctor = is_array($doctor) ? $doctor : array();
        $settings = DB::get_settings();
        $specialties = $wpdb->get_results('SELECT id, name FROM ' . DB::table('specialties') . ' WHERE is_active = 1 ORDER BY sort_order ASC, name ASC');
        $doctor_user_id = (int) ($doctor['user_id'] ?? 0);
        $doctor_login_mobile = $doctor_user_id > 0 ? Patient_Profile::user_mobile($doctor_user_id) : '';

        $field = static function (string $key, $default = '') use ($doctor) {
            return $doctor[$key] ?? $default;
        };

        ?>
        <div class="webtanan-admin-grid">
            <p>
                <label for="webtanan_doctor_login_mobile"><?php esc_html_e('شماره موبایل ورود پزشک', 'webtanan-booking'); ?></label>
                <input type="tel" inputmode="numeric" dir="ltr" id="webtanan_doctor_login_mobile" name="webtanan_doctor[login_mobile]" value="<?php echo esc_attr($doctor_login_mobile); ?>" class="widefat" placeholder="09123456789">
                <span class="description"><?php esc_html_e('برای ورود پزشک با کد یک‌بارمصرف استفاده می‌شود.', 'webtanan-booking'); ?></span>
            </p>
            <p>
                <label for="webtanan_doctor_user_id"><?php esc_html_e('کاربر وردپرس پزشک', 'webtanan-booking'); ?></label>
                <?php
                wp_dropdown_users(
                    array(
                        'name' => 'webtanan_doctor[user_id]',
                        'id' => 'webtanan_doctor_user_id',
                        'selected' => (int) $field('user_id', 0),
                        'show_option_none' => __('انتخاب کاربر', 'webtanan-booking'),
                    )
                );
                ?>
            </p>
            <p>
                <label for="webtanan_doctor_secretary_user_id"><?php esc_html_e('منشی دریافت‌کننده سهم خدمات نوبت‌دهی', 'webtanan-booking'); ?></label>
                <?php
                wp_dropdown_users(
                    array(
                        'name' => 'webtanan_doctor[secretary_user_id]',
                        'id' => 'webtanan_doctor_secretary_user_id',
                        'selected' => (int) $field('secretary_user_id', 0),
                        'show_option_none' => __('بدون منشی؛ سهم به پزشک واریز شود', 'webtanan-booking'),
                    )
                );
                ?>
            </p>
            <p>
                <label><?php esc_html_e('کد پزشک', 'webtanan-booking'); ?></label>
                <input type="text" name="webtanan_doctor[doctor_code]" value="<?php echo esc_attr($field('doctor_code')); ?>" class="widefat">
            </p>
            <p>
                <label><?php esc_html_e('شماره نظام پزشکی', 'webtanan-booking'); ?></label>
                <input type="text" name="webtanan_doctor[medical_system_number]" value="<?php echo esc_attr($field('medical_system_number')); ?>" class="widefat">
            </p>
            <p>
                <label><?php esc_html_e('تخصص', 'webtanan-booking'); ?></label>
                <select name="webtanan_doctor[specialty_id]" class="widefat">
                    <option value="0"><?php esc_html_e('انتخاب تخصص', 'webtanan-booking'); ?></option>
                    <?php foreach ($specialties as $specialty) : ?>
                        <option value="<?php echo esc_attr((string) $specialty->id); ?>" <?php selected((int) $field('specialty_id', 0), (int) $specialty->id); ?>>
                            <?php echo esc_html($specialty->name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </p>
            <p>
                <label><?php esc_html_e('شناسه استان', 'webtanan-booking'); ?></label>
                <input type="number" min="0" name="webtanan_doctor[province_id]" value="<?php echo esc_attr((string) $field('province_id', 0)); ?>" class="widefat">
            </p>
            <p>
                <label><?php esc_html_e('شناسه شهر', 'webtanan-booking'); ?></label>
                <input type="number" min="0" name="webtanan_doctor[city_id]" value="<?php echo esc_attr((string) $field('city_id', 0)); ?>" class="widefat">
            </p>
            <p>
                <label><?php esc_html_e('نام مطب', 'webtanan-booking'); ?></label>
                <input type="text" name="webtanan_doctor[clinic_name]" value="<?php echo esc_attr($field('clinic_name')); ?>" class="widefat">
            </p>
            <p>
                <label><?php esc_html_e('تلفن مطب', 'webtanan-booking'); ?></label>
                <input type="text" name="webtanan_doctor[clinic_phone]" value="<?php echo esc_attr($field('clinic_phone')); ?>" class="widefat">
            </p>
            <p class="webtanan-admin-grid-full">
                <label><?php esc_html_e('آدرس مطب', 'webtanan-booking'); ?></label>
                <textarea name="webtanan_doctor[clinic_address]" class="widefat" rows="3"><?php echo esc_textarea($field('clinic_address')); ?></textarea>
            </p>
            <p>
                <label><?php esc_html_e('هزینه خدمات نوبت‌دهی', 'webtanan-booking'); ?></label>
                <input type="number" min="0" step="1000" name="webtanan_doctor[booking_fee]" value="<?php echo esc_attr((string) $field('booking_fee', 0)); ?>" class="widefat">
            </p>
            <p>
                <label><?php esc_html_e('نوع سهم هزینه نوبت‌دهی', 'webtanan-booking'); ?></label>
                <select name="webtanan_doctor[booking_fee_share_type]" class="widefat">
                    <option value="percent" <?php selected($field('booking_fee_share_type', 'percent'), 'percent'); ?>><?php esc_html_e('درصدی', 'webtanan-booking'); ?></option>
                    <option value="fixed" <?php selected($field('booking_fee_share_type', 'percent'), 'fixed'); ?>><?php esc_html_e('مبلغ ثابت', 'webtanan-booking'); ?></option>
                </select>
            </p>
            <p>
                <label><?php esc_html_e('مقدار سهم هزینه نوبت‌دهی', 'webtanan-booking'); ?></label>
                <input type="number" min="0" step="1000" name="webtanan_doctor[booking_fee_share_value]" value="<?php echo esc_attr((string) $field('booking_fee_share_value', 0)); ?>" class="widefat">
            </p>
            <p>
                <label><?php esc_html_e('تعرفه ویزیت نمایشی (اختیاری)', 'webtanan-booking'); ?></label>
                <input type="number" min="0" step="1000" name="webtanan_doctor[visit_price]" value="<?php echo esc_attr((string) $field('visit_price', 0)); ?>" class="widefat">
            </p>
            <input type="hidden" name="webtanan_doctor[platform_commission_type]" value="<?php echo esc_attr($field('platform_commission_type', $settings['default_commission_type'])); ?>">
            <input type="hidden" name="webtanan_doctor[platform_commission_value]" value="<?php echo esc_attr((string) $field('platform_commission_value', $settings['default_commission_value'])); ?>">
            <p>
                <label><?php esc_html_e('شماره شبا', 'webtanan-booking'); ?></label>
                <input type="text" name="webtanan_doctor[iban]" value="<?php echo esc_attr($field('iban')); ?>" class="widefat">
            </p>
            <p>
                <label><?php esc_html_e('صاحب حساب بانکی', 'webtanan-booking'); ?></label>
                <input type="text" name="webtanan_doctor[bank_account_owner]" value="<?php echo esc_attr($field('bank_account_owner')); ?>" class="widefat">
            </p>
            <p>
                <label><input type="checkbox" name="webtanan_doctor[is_active]" value="1" <?php checked((int) $field('is_active', 1), 1); ?>> <?php esc_html_e('فعال', 'webtanan-booking'); ?></label>
            </p>
            <p>
                <label><input type="checkbox" name="webtanan_doctor[is_verified]" value="1" <?php checked((int) $field('is_verified', 0), 1); ?>> <?php esc_html_e('تاییدشده', 'webtanan-booking'); ?></label>
            </p>
            <p>
                <label><input type="checkbox" name="webtanan_doctor[allow_online_payment]" value="1" <?php checked((int) $field('allow_online_payment', 1), 1); ?>> <?php esc_html_e('اجازه پرداخت آنلاین', 'webtanan-booking'); ?></label>
            </p>
            <p>
                <label><input type="checkbox" name="webtanan_doctor[allow_pay_at_clinic]" value="1" <?php checked((int) $field('allow_pay_at_clinic', 0), 1); ?>> <?php esc_html_e('اجازه پرداخت در مطب', 'webtanan-booking'); ?></label>
            </p>
        </div>
        <?php
    }

    public static function save_doctor_metabox(int $post_id, \WP_Post $post): void {
        global $wpdb;

        if (!isset($_POST['webtanan_booking_doctor_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['webtanan_booking_doctor_nonce'])), 'webtanan_booking_save_doctor')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $raw = isset($_POST['webtanan_doctor']) && is_array($_POST['webtanan_doctor']) ? wp_unslash($_POST['webtanan_doctor']) : array();
        $now = DB::now();
        $user_id = isset($raw['user_id']) ? absint($raw['user_id']) : 0;
        $login_mobile = OTP::normalize_mobile((string) ($raw['login_mobile'] ?? ''));
        if ($user_id > 0 && '' === $login_mobile) {
            return;
        }
        if ($user_id > 0) {
            $mobile_owner = Patient_Profile::find_user_id_by_mobile($login_mobile, $user_id);
            if ($mobile_owner > 0) {
                return;
            }
        }
        $data = array(
            'post_id' => $post_id,
            'user_id' => $user_id,
            'secretary_user_id' => isset($raw['secretary_user_id']) ? absint($raw['secretary_user_id']) : 0,
            'doctor_code' => isset($raw['doctor_code']) ? sanitize_text_field($raw['doctor_code']) : '',
            'medical_system_number' => isset($raw['medical_system_number']) ? sanitize_text_field($raw['medical_system_number']) : '',
            'specialty_id' => isset($raw['specialty_id']) ? absint($raw['specialty_id']) : 0,
            'city_id' => isset($raw['city_id']) ? absint($raw['city_id']) : 0,
            'province_id' => isset($raw['province_id']) ? absint($raw['province_id']) : 0,
            'clinic_name' => isset($raw['clinic_name']) ? sanitize_text_field($raw['clinic_name']) : '',
            'clinic_address' => isset($raw['clinic_address']) ? sanitize_textarea_field($raw['clinic_address']) : '',
            'clinic_phone' => isset($raw['clinic_phone']) ? sanitize_text_field($raw['clinic_phone']) : '',
            'visit_price' => isset($raw['visit_price']) ? (float) $raw['visit_price'] : 0,
            'booking_fee' => isset($raw['booking_fee']) ? (float) $raw['booking_fee'] : 0,
            'booking_fee_share_type' => (isset($raw['booking_fee_share_type']) && 'fixed' === $raw['booking_fee_share_type']) ? 'fixed' : 'percent',
            'booking_fee_share_value' => isset($raw['booking_fee_share_value']) ? (float) $raw['booking_fee_share_value'] : 0,
            'platform_commission_type' => (isset($raw['platform_commission_type']) && 'fixed' === $raw['platform_commission_type']) ? 'fixed' : 'percent',
            'platform_commission_value' => isset($raw['platform_commission_value']) ? (float) $raw['platform_commission_value'] : 0,
            'iban' => isset($raw['iban']) ? sanitize_text_field($raw['iban']) : '',
            'bank_account_owner' => isset($raw['bank_account_owner']) ? sanitize_text_field($raw['bank_account_owner']) : '',
            'is_active' => isset($raw['is_active']) ? 1 : 0,
            'is_verified' => isset($raw['is_verified']) ? 1 : 0,
            'allow_online_payment' => isset($raw['allow_online_payment']) ? 1 : 0,
            'allow_pay_at_clinic' => isset($raw['allow_pay_at_clinic']) ? 1 : 0,
            'updated_at' => $now,
        );

        $table = DB::table('doctors');
        $existing_id = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE post_id = %d", $post_id));
        if ($user_id > 0) {
            update_user_meta($user_id, 'webtanan_mobile', $login_mobile);
            $doctor_user = get_user_by('id', $user_id);
            if ($doctor_user instanceof \WP_User && !in_array('webtanan_doctor', (array) $doctor_user->roles, true)) {
                $doctor_user->add_role('webtanan_doctor');
            }
        }
        if ($existing_id > 0) {
            $wpdb->update($table, $data, array('id' => $existing_id));
            return;
        }

        $data['created_at'] = $now;
        $wpdb->insert($table, $data);
    }
}
