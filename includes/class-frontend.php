<?php
/**
 * Front-end shortcodes and assets.
 *
 * @package WebtananBooking
 */

namespace Webtanan\Booking;

defined('ABSPATH') || exit;

final class Frontend {
    public static function init(): void {
        add_action('wp_enqueue_scripts', array(__CLASS__, 'register_assets'));
        add_action('wp_head', array(__CLASS__, 'maybe_noindex_private_pages'), 1);
        add_shortcode('webtanan_booking_auth', array(__CLASS__, 'auth_shortcode'));
        add_shortcode('webtanan_booking_doctor_search', array(__CLASS__, 'doctor_search_shortcode'));
        add_shortcode('webtanan_booking_doctor_list', array(__CLASS__, 'doctor_list_shortcode'));
        add_shortcode('webtanan_booking_doctor_card', array(__CLASS__, 'doctor_card_shortcode'));
        add_shortcode('webtanan_booking_specialty_list', array(__CLASS__, 'specialty_list_shortcode'));
        add_shortcode('webtanan_booking_doctors_archive', array(__CLASS__, 'doctors_archive_shortcode'));
        add_shortcode('webtanan_booking_calendar', array(__CLASS__, 'calendar_shortcode'));
        add_shortcode('webtanan_booking_next_available', array(__CLASS__, 'next_available_shortcode'));
        add_shortcode('webtanan_booking_patient_panel', array(__CLASS__, 'patient_panel_shortcode'));
        add_shortcode('webtanan_booking_doctor_dashboard', array(__CLASS__, 'doctor_dashboard_shortcode'));
    }

    public static function register_assets(): void {
        wp_register_style('webtanan-booking-frontend', WEBTANAN_BOOKING_URL . 'assets/css/frontend.css', array(), WEBTANAN_BOOKING_VERSION);
        wp_register_script('webtanan-booking-jalali', WEBTANAN_BOOKING_URL . 'assets/js/jalali-calendar.js', array(), WEBTANAN_BOOKING_VERSION, true);
        wp_register_script('webtanan-booking-frontend', WEBTANAN_BOOKING_URL . 'assets/js/frontend.js', array('webtanan-booking-jalali'), WEBTANAN_BOOKING_VERSION, true);
        wp_localize_script(
            'webtanan-booking-frontend',
            'WebtananBooking',
            array(
                'restUrl' => esc_url_raw(rest_url('saas/v1')),
                'nonce' => wp_create_nonce('wp_rest'),
                'isLoggedIn' => is_user_logged_in(),
                'today' => current_time('Y-m-d'),
                'strings' => array(
                    'loading' => __('در حال بارگذاری...', 'webtanan-booking'),
                    'noSlots' => __('نوبت آزادی پیدا نشد.', 'webtanan-booking'),
                    'selectSlot' => __('انتخاب نوبت', 'webtanan-booking'),
                    'locked' => __('نوبت قفل شد. لطفاً قبل از پایان زمان رزرو پرداخت را کامل کنید.', 'webtanan-booking'),
                    'selectGateway' => __('انتخاب درگاه پرداخت', 'webtanan-booking'),
                    'redirectingToGateway' => __('در حال انتقال به درگاه پرداخت...', 'webtanan-booking'),
                    'loginRequiredForPayment' => __('برای پرداخت آنلاین ابتدا وارد حساب شوید.', 'webtanan-booking'),
                    'error' => __('خطایی رخ داد. لطفاً دوباره تلاش کنید.', 'webtanan-booking'),
                ),
            )
        );
    }

    public static function maybe_noindex_private_pages(): void {
        if (!is_singular()) {
            return;
        }

        $post = get_post();
        if (!$post || empty($post->post_content)) {
            return;
        }

        $private_shortcodes = array(
            'webtanan_booking_auth',
            'webtanan_booking_patient_panel',
            'webtanan_booking_doctor_dashboard',
        );

        foreach ($private_shortcodes as $shortcode) {
            if (has_shortcode($post->post_content, $shortcode)) {
                echo "<meta name=\"robots\" content=\"noindex,nofollow\">\n";
                return;
            }
        }
    }

    public static function auth_shortcode(): string {
        self::enqueue();

        ob_start();
        ?>
        <div class="webtanan-booking webtanan-auth-box" data-webtanan-widget="auth" dir="rtl">
            <div class="webtanan-auth-head">
                <span><?php esc_html_e('ورود امن', 'webtanan-booking'); ?></span>
                <strong><?php esc_html_e('ورود به سامانه نوبت‌دهی', 'webtanan-booking'); ?></strong>
            </div>
            <form class="webtanan-auth-mobile">
                <label>
                    <span><?php esc_html_e('شماره موبایل', 'webtanan-booking'); ?></span>
                    <input type="tel" name="mobile" placeholder="09123456789" required>
                </label>
                <button type="submit" class="webtanan-button webtanan-button-primary"><?php esc_html_e('ارسال کد ورود', 'webtanan-booking'); ?></button>
            </form>
            <form class="webtanan-auth-otp" hidden>
                <label>
                    <span><?php esc_html_e('کد تایید', 'webtanan-booking'); ?></span>
                    <input type="text" name="otp" inputmode="numeric" autocomplete="one-time-code" required>
                </label>
                <button type="submit" class="webtanan-button webtanan-button-primary"><?php esc_html_e('ورود', 'webtanan-booking'); ?></button>
                <button type="button" class="webtanan-button webtanan-auth-back"><?php esc_html_e('تغییر شماره', 'webtanan-booking'); ?></button>
            </form>
            <div class="webtanan-auth-message" aria-live="polite"></div>
        </div>
        <?php

        return (string) ob_get_clean();
    }

    public static function doctor_search_shortcode($atts = array()): string {
        global $wpdb;

        self::enqueue();
        $atts = shortcode_atts(
            array(
                'per_page' => 12,
                'default_search' => '',
                'search_placeholder' => __('نام پزشک، تخصص یا آدرس مطب', 'webtanan-booking'),
                'specialty_id' => 0,
                'city_id' => 0,
                'province_id' => 0,
                'payment_filter' => '',
                'sort' => 'first_available',
                'layout' => 'grid',
                'show_filters' => 'yes',
                'show_sort' => 'yes',
            ),
            $atts,
            'webtanan_booking_doctor_search'
        );
        $show_filters = 'no' !== strtolower((string) $atts['show_filters']);
        $show_sort = 'no' !== strtolower((string) $atts['show_sort']);
        $specialties = $show_filters ? $wpdb->get_results('SELECT id, name FROM ' . DB::table('specialties') . ' WHERE is_active = 1 ORDER BY sort_order ASC, name ASC LIMIT 300', ARRAY_A) : array();
        $provinces = $show_filters ? $wpdb->get_results('SELECT DISTINCT province_id AS id FROM ' . DB::table('doctors') . ' WHERE province_id > 0 AND is_active = 1 AND is_verified = 1 ORDER BY province_id ASC LIMIT 300', ARRAY_A) : array();
        $cities = $show_filters ? $wpdb->get_results('SELECT DISTINCT city_id AS id FROM ' . DB::table('doctors') . ' WHERE city_id > 0 AND is_active = 1 AND is_verified = 1 ORDER BY city_id ASC LIMIT 300', ARRAY_A) : array();
        $payment_filter = sanitize_key((string) $atts['payment_filter']);
        $sort = sanitize_key((string) $atts['sort']);
        $layout = 'list' === sanitize_key((string) $atts['layout']) ? 'list' : 'grid';
        $default_search = sanitize_text_field((string) $atts['default_search']);

        ob_start();
        ?>
        <div class="webtanan-booking webtanan-doctor-search" data-webtanan-widget="doctor-search" data-per-page="<?php echo esc_attr((string) absint($atts['per_page'])); ?>" data-specialty-id="<?php echo esc_attr((string) absint($atts['specialty_id'])); ?>" data-city-id="<?php echo esc_attr((string) absint($atts['city_id'])); ?>" data-province-id="<?php echo esc_attr((string) absint($atts['province_id'])); ?>" data-payment-filter="<?php echo esc_attr($payment_filter); ?>" data-sort="<?php echo esc_attr($sort ?: 'first_available'); ?>" data-layout="<?php echo esc_attr($layout); ?>" dir="rtl">
            <form class="webtanan-toolbar webtanan-doctor-search-form" action="#" method="get">
                <span class="screen-reader-text"><?php esc_html_e('جستجوی پزشک', 'webtanan-booking'); ?></span>
                <input type="search" class="webtanan-doctor-search-input" name="search" value="<?php echo esc_attr($default_search); ?>" placeholder="<?php echo esc_attr((string) $atts['search_placeholder']); ?>">
                <?php if ($show_filters) : ?>
                    <select class="webtanan-doctor-specialty-filter" name="specialty_id" aria-label="<?php esc_attr_e('تخصص', 'webtanan-booking'); ?>">
                        <option value="0"><?php esc_html_e('همه تخصص‌ها', 'webtanan-booking'); ?></option>
                        <?php foreach ($specialties as $specialty) : ?>
                            <option value="<?php echo esc_attr((string) $specialty['id']); ?>" <?php selected(absint($atts['specialty_id']), (int) $specialty['id']); ?>><?php echo esc_html($specialty['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select class="webtanan-doctor-province-filter" name="province_id" aria-label="<?php esc_attr_e('استان', 'webtanan-booking'); ?>">
                        <option value="0"><?php esc_html_e('همه استان‌ها', 'webtanan-booking'); ?></option>
                        <?php foreach ($provinces as $province) : ?>
                            <option value="<?php echo esc_attr((string) $province['id']); ?>" <?php selected(absint($atts['province_id']), (int) $province['id']); ?>><?php echo esc_html(sprintf(__('استان #%d', 'webtanan-booking'), (int) $province['id'])); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select class="webtanan-doctor-city-filter" name="city_id" aria-label="<?php esc_attr_e('شهر', 'webtanan-booking'); ?>">
                        <option value="0"><?php esc_html_e('همه شهرها', 'webtanan-booking'); ?></option>
                        <?php foreach ($cities as $city) : ?>
                            <option value="<?php echo esc_attr((string) $city['id']); ?>" <?php selected(absint($atts['city_id']), (int) $city['id']); ?>><?php echo esc_html(sprintf(__('شهر #%d', 'webtanan-booking'), (int) $city['id'])); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select class="webtanan-doctor-payment-filter" name="payment_filter" aria-label="<?php esc_attr_e('روش پرداخت', 'webtanan-booking'); ?>">
                        <option value="" <?php selected($payment_filter, ''); ?>><?php esc_html_e('همه روش‌های پرداخت', 'webtanan-booking'); ?></option>
                        <option value="online" <?php selected($payment_filter, 'online'); ?>><?php esc_html_e('پرداخت آنلاین', 'webtanan-booking'); ?></option>
                        <option value="clinic" <?php selected($payment_filter, 'clinic'); ?>><?php esc_html_e('پرداخت در مطب', 'webtanan-booking'); ?></option>
                    </select>
                    <?php if ($show_sort) : ?>
                        <select class="webtanan-doctor-sort-filter" name="sort" aria-label="<?php esc_attr_e('مرتب‌سازی', 'webtanan-booking'); ?>">
                            <option value="first_available" <?php selected($sort ?: 'first_available', 'first_available'); ?>><?php esc_html_e('نزدیک‌ترین نوبت آزاد', 'webtanan-booking'); ?></option>
                            <option value="" <?php selected($sort, ''); ?>><?php esc_html_e('پیش‌فرض', 'webtanan-booking'); ?></option>
                        </select>
                    <?php endif; ?>
                <?php endif; ?>
                <button type="submit" class="webtanan-button webtanan-search-button"><?php esc_html_e('جستجو', 'webtanan-booking'); ?></button>
            </form>
            <div class="webtanan-doctor-results" aria-live="polite"></div>
        </div>
        <?php

        return (string) ob_get_clean();
    }

    public static function doctor_list_shortcode($atts = array()): string {
        self::enqueue();
        $atts = shortcode_atts(array('per_page' => 12, 'specialty_id' => 0, 'city_id' => 0, 'province_id' => 0, 'payment_filter' => '', 'sort' => '', 'online' => '', 'pay_at_clinic' => ''), $atts, 'webtanan_booking_doctor_list');

        return '<div class="webtanan-booking webtanan-doctor-list" data-webtanan-widget="doctor-list" data-per-page="' . esc_attr((string) absint($atts['per_page'])) . '" data-specialty-id="' . esc_attr((string) absint($atts['specialty_id'])) . '" data-city-id="' . esc_attr((string) absint($atts['city_id'])) . '" data-province-id="' . esc_attr((string) absint($atts['province_id'])) . '" data-payment-filter="' . esc_attr(sanitize_key((string) $atts['payment_filter'])) . '" data-sort="' . esc_attr(sanitize_key((string) $atts['sort'])) . '" data-online="' . esc_attr((string) $atts['online']) . '" data-pay-at-clinic="' . esc_attr((string) $atts['pay_at_clinic']) . '" dir="rtl"></div>';
    }

    public static function doctor_card_shortcode($atts = array()): string {
        global $wpdb;

        self::enqueue();
        $atts = shortcode_atts(array('doctor_id' => 0), $atts, 'webtanan_booking_doctor_card');
        $doctor_id = absint($atts['doctor_id']);
        if (!$doctor_id && is_singular('saas_doctors')) {
            $doctor_id = self::doctor_id_from_current_post();
        }

        $doctor = $doctor_id ? $wpdb->get_row(
            $wpdb->prepare(
                'SELECT d.*, p.post_title, s.name AS specialty_name
                FROM ' . DB::table('doctors') . ' d
                LEFT JOIN ' . $wpdb->posts . ' p ON p.ID = d.post_id
                LEFT JOIN ' . DB::table('specialties') . ' s ON s.id = d.specialty_id
                WHERE d.id = %d',
                $doctor_id
            ),
            ARRAY_A
        ) : null;

        if (!$doctor) {
            return '<div class="webtanan-booking webtanan-panel" dir="rtl">' . esc_html__('پزشک پیدا نشد.', 'webtanan-booking') . '</div>';
        }

        $post_id = (int) $doctor['post_id'];
        $title = $doctor['post_title'] ?: $doctor['clinic_name'];
        $image_url = $post_id ? get_the_post_thumbnail_url($post_id, 'medium') : '';
        $permalink = $post_id ? get_permalink($post_id) : '#';
        $booking_fee = Booking::doctor_booking_fee($doctor);
        $visit_price = (float) ($doctor['visit_price'] ?? 0);

        ob_start();
        ?>
        <article class="webtanan-booking webtanan-public-doctor-card webtanan-doctor-card-shortcode" dir="rtl">
            <a class="webtanan-public-doctor-photo" href="<?php echo esc_url($permalink); ?>">
                <?php if ($image_url) : ?>
                    <img src="<?php echo esc_url($image_url); ?>" alt="<?php echo esc_attr($title); ?>">
                <?php else : ?>
                    <span><?php echo esc_html(function_exists('mb_substr') ? mb_substr($title, 0, 1) : substr($title, 0, 1)); ?></span>
                <?php endif; ?>
            </a>
            <div class="webtanan-public-doctor-body">
                <div class="webtanan-doctor-badges">
                    <?php if (!empty($doctor['is_verified'])) : ?><span><?php esc_html_e('تاییدشده', 'webtanan-booking'); ?></span><?php endif; ?>
                    <?php if (!empty($doctor['allow_online_payment'])) : ?><span><?php esc_html_e('پرداخت آنلاین', 'webtanan-booking'); ?></span><?php endif; ?>
                    <?php if (!empty($doctor['allow_pay_at_clinic'])) : ?><span><?php esc_html_e('پرداخت در مطب', 'webtanan-booking'); ?></span><?php endif; ?>
                </div>
                <h2><a href="<?php echo esc_url($permalink); ?>"><?php echo esc_html($title); ?></a></h2>
                <?php if (!empty($doctor['specialty_name'])) : ?><p class="webtanan-meta"><?php echo esc_html($doctor['specialty_name']); ?></p><?php endif; ?>
                <?php if (!empty($doctor['clinic_address'])) : ?><p class="webtanan-meta"><?php echo esc_html(wp_trim_words($doctor['clinic_address'], 18)); ?></p><?php endif; ?>
                <div class="webtanan-public-fees">
                    <span><?php esc_html_e('هزینه نوبت‌دهی:', 'webtanan-booking'); ?> <strong><?php echo esc_html(number_format_i18n($booking_fee)); ?></strong> <?php esc_html_e('تومان', 'webtanan-booking'); ?></span>
                    <?php if ($visit_price > 0) : ?><span><?php esc_html_e('ویزیت اعلامی:', 'webtanan-booking'); ?> <?php echo esc_html(number_format_i18n($visit_price)); ?> <?php esc_html_e('تومان', 'webtanan-booking'); ?></span><?php endif; ?>
                </div>
                <div class="webtanan-public-actions">
                    <a class="webtanan-button webtanan-button-primary" href="<?php echo esc_url($permalink); ?>"><?php esc_html_e('مشاهده و دریافت نوبت', 'webtanan-booking'); ?></a>
                    <div class="webtanan-next-available" data-webtanan-widget="next-available" data-doctor-id="<?php echo esc_attr((string) $doctor_id); ?>"></div>
                </div>
            </div>
        </article>
        <?php

        return (string) ob_get_clean();
    }

    public static function specialty_list_shortcode($atts = array()): string {
        global $wpdb;

        self::enqueue();
        $atts = shortcode_atts(array('show_count' => 'yes'), $atts, 'webtanan_booking_specialty_list');
        $show_count = 'no' !== strtolower((string) $atts['show_count']);
        $archive_url = get_post_type_archive_link('saas_doctors') ?: home_url('/');
        $specialties = $wpdb->get_results(
            'SELECT s.*, COUNT(d.id) AS doctor_count
            FROM ' . DB::table('specialties') . ' s
            LEFT JOIN ' . DB::table('doctors') . ' d ON d.specialty_id = s.id AND d.is_active = 1 AND d.is_verified = 1
            WHERE s.is_active = 1
            GROUP BY s.id
            ORDER BY s.sort_order ASC, s.name ASC
            LIMIT 300',
            ARRAY_A
        );

        ob_start();
        ?>
        <div class="webtanan-booking webtanan-specialty-list" dir="rtl">
            <?php if (!$specialties) : ?>
                <div class="webtanan-panel"><?php esc_html_e('تخصص فعالی ثبت نشده است.', 'webtanan-booking'); ?></div>
            <?php endif; ?>
            <?php foreach ($specialties as $specialty) : ?>
                <a class="webtanan-specialty-chip" href="<?php echo esc_url(add_query_arg('specialty_id', (int) $specialty['id'], $archive_url)); ?>">
                    <span><?php echo esc_html($specialty['name']); ?></span>
                    <?php if ($show_count) : ?><small><?php echo esc_html(number_format_i18n((int) $specialty['doctor_count'])); ?></small><?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>
        <?php

        return (string) ob_get_clean();
    }

    public static function doctors_archive_shortcode($atts = array()): string {
        self::enqueue();
        $atts = shortcode_atts(array('per_page' => 12), $atts, 'webtanan_booking_doctors_archive');

        ob_start();
        ?>
        <div class="webtanan-booking webtanan-shortcode-archive" dir="rtl">
            <div class="webtanan-shortcode-specialties"><?php echo self::specialty_list_shortcode(array('show_count' => 'yes')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
            <div class="webtanan-doctor-search" data-webtanan-widget="doctor-search" data-per-page="<?php echo esc_attr((string) absint($atts['per_page'])); ?>" dir="rtl">
                <div class="webtanan-toolbar">
                    <input type="search" class="webtanan-doctor-search-input" placeholder="<?php esc_attr_e('نام پزشک، تخصص یا شهر', 'webtanan-booking'); ?>">
                    <button type="button" class="webtanan-button webtanan-search-button"><?php esc_html_e('جستجو', 'webtanan-booking'); ?></button>
                </div>
                <div class="webtanan-doctor-results" aria-live="polite"></div>
            </div>
        </div>
        <?php

        return (string) ob_get_clean();
    }

    public static function calendar_shortcode($atts = array()): string {
        self::enqueue();
        $atts = shortcode_atts(array('doctor_id' => 0), $atts, 'webtanan_booking_calendar');
        $doctor_id = absint($atts['doctor_id']);

        if (!$doctor_id && is_singular('saas_doctors')) {
            $doctor_id = self::doctor_id_from_current_post();
        }

        ob_start();
        ?>
        <div class="webtanan-booking webtanan-booking-calendar" data-webtanan-widget="calendar" data-doctor-id="<?php echo esc_attr((string) $doctor_id); ?>" dir="rtl">
            <div class="webtanan-booking-head">
                <strong><?php esc_html_e('انتخاب نوبت', 'webtanan-booking'); ?></strong>
                <span><?php esc_html_e('زمان‌های موجود برای رزرو', 'webtanan-booking'); ?></span>
            </div>
            <div class="webtanan-toolbar">
                <input type="date" class="webtanan-slot-date" value="<?php echo esc_attr(current_time('Y-m-d')); ?>">
                <button type="button" class="webtanan-button webtanan-load-slots"><?php esc_html_e('نمایش نوبت‌ها', 'webtanan-booking'); ?></button>
            </div>
            <div class="webtanan-slots" aria-live="polite"></div>
            <form class="webtanan-booking-form" hidden>
                <input type="text" name="patient_first_name" placeholder="<?php esc_attr_e('نام', 'webtanan-booking'); ?>">
                <input type="text" name="patient_last_name" placeholder="<?php esc_attr_e('نام خانوادگی', 'webtanan-booking'); ?>">
                <input type="text" name="patient_national_code" placeholder="<?php esc_attr_e('کد ملی', 'webtanan-booking'); ?>">
                <input type="tel" name="patient_mobile" placeholder="<?php esc_attr_e('موبایل', 'webtanan-booking'); ?>">
                <select name="gateway" class="webtanan-gateway-select" aria-label="<?php esc_attr_e('درگاه پرداخت', 'webtanan-booking'); ?>"></select>
                <button type="submit" class="webtanan-button webtanan-button-primary"><?php esc_html_e('قفل و ادامه پرداخت', 'webtanan-booking'); ?></button>
            </form>
            <div class="webtanan-booking-message" aria-live="polite"></div>
        </div>
        <?php

        return (string) ob_get_clean();
    }

    public static function next_available_shortcode($atts = array()): string {
        self::enqueue();
        $atts = shortcode_atts(array('doctor_id' => 0), $atts, 'webtanan_booking_next_available');
        $doctor_id = absint($atts['doctor_id']);

        if (!$doctor_id && is_singular('saas_doctors')) {
            $doctor_id = self::doctor_id_from_current_post();
        }

        return '<div class="webtanan-booking webtanan-next-available" data-webtanan-widget="next-available" data-doctor-id="' . esc_attr((string) $doctor_id) . '" dir="rtl"></div>';
    }

    public static function patient_panel_shortcode(): string {
        self::enqueue();

        ob_start();
        ?>
        <div class="webtanan-booking webtanan-app-shell webtanan-patient-panel" data-webtanan-widget="patient-panel" dir="rtl">
            <aside class="wb-sidebar">
                <div class="wb-brand">
                    <span class="wb-brand-mark">+</span>
                    <span><?php esc_html_e('پنل بیمار', 'webtanan-booking'); ?></span>
                </div>
                <nav class="wb-nav" aria-label="<?php esc_attr_e('ناوبری پنل بیمار', 'webtanan-booking'); ?>">
                    <button type="button" class="wb-nav-item is-active" data-wb-view="patient-appointments"><span><?php esc_html_e('نوبت‌های آینده', 'webtanan-booking'); ?></span></button>
                    <button type="button" class="wb-nav-item" data-wb-view="patient-history"><span><?php esc_html_e('سوابق نوبت', 'webtanan-booking'); ?></span></button>
                    <button type="button" class="wb-nav-item" data-wb-view="patient-wallet"><span><?php esc_html_e('کیف پول', 'webtanan-booking'); ?></span></button>
                </nav>
                <button type="button" class="wb-nav-item wb-logout"><?php esc_html_e('خروج از حساب', 'webtanan-booking'); ?></button>
            </aside>
            <main class="wb-main">
                <header class="wb-topbar">
                    <div>
                        <div class="wb-kicker"><?php esc_html_e('امروز', 'webtanan-booking'); ?></div>
                        <strong class="wb-today-label"></strong>
                    </div>
                    <div class="wb-user-chip"><?php echo esc_html(wp_get_current_user()->display_name ?: __('بیمار', 'webtanan-booking')); ?></div>
                </header>
                <section class="wb-content" aria-live="polite"></section>
            </main>
        </div>
        <?php

        return (string) ob_get_clean();
    }

    public static function doctor_dashboard_shortcode(): string {
        self::enqueue();

        ob_start();
        ?>
        <div class="webtanan-booking webtanan-app-shell webtanan-doctor-dashboard" data-webtanan-widget="doctor-dashboard" dir="rtl">
            <aside class="wb-sidebar">
                <div class="wb-brand">
                    <span class="wb-brand-mark">+</span>
                    <span class="wb-brand-title"><?php esc_html_e('داشبورد مطب', 'webtanan-booking'); ?></span>
                </div>
                <nav class="wb-nav" aria-label="<?php esc_attr_e('ناوبری پنل مطب', 'webtanan-booking'); ?>">
                    <button type="button" class="wb-nav-item is-active" data-wb-view="today"><span><?php esc_html_e('پیش‌خوان امروز', 'webtanan-booking'); ?></span></button>
                    <button type="button" class="wb-nav-item" data-wb-view="calendar"><span><?php esc_html_e('تقویم نوبت‌دهی', 'webtanan-booking'); ?></span></button>
                    <button type="button" class="wb-nav-item" data-wb-view="patients"><span><?php esc_html_e('لیست بیماران', 'webtanan-booking'); ?></span></button>
                    <button type="button" class="wb-nav-item" data-wb-view="schedule"><span><?php esc_html_e('برنامه کاری', 'webtanan-booking'); ?></span></button>
                    <button type="button" class="wb-nav-item" data-wb-view="exceptions"><span><?php esc_html_e('مرخصی‌ها', 'webtanan-booking'); ?></span></button>
                    <button type="button" class="wb-nav-item" data-wb-view="wallet"><span><?php esc_html_e('کیف پول و مالی', 'webtanan-booking'); ?></span></button>
                </nav>
                <button type="button" class="wb-nav-item wb-logout"><?php esc_html_e('خروج از حساب', 'webtanan-booking'); ?></button>
            </aside>
            <main class="wb-main">
                <header class="wb-topbar">
                    <div class="wb-topbar-date">
                        <div class="wb-kicker"><?php esc_html_e('امروز', 'webtanan-booking'); ?></div>
                        <strong class="wb-today-label"></strong>
                    </div>
                    <div class="wb-topbar-actions">
                        <select class="wb-doctor-select" aria-label="<?php esc_attr_e('پزشک', 'webtanan-booking'); ?>"></select>
                        <button type="button" class="wb-button wb-button-primary wb-open-walkin"><?php esc_html_e('ثبت نوبت حضوری', 'webtanan-booking'); ?></button>
                    </div>
                </header>
                <section class="wb-content" aria-live="polite"></section>
            </main>
            <div class="wb-modal" hidden>
                <div class="wb-modal-panel" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e('ثبت نوبت', 'webtanan-booking'); ?>">
                    <div class="wb-modal-head">
                        <strong><?php esc_html_e('ثبت نوبت حضوری', 'webtanan-booking'); ?></strong>
                        <button type="button" class="wb-icon-button wb-close-modal" aria-label="<?php esc_attr_e('بستن', 'webtanan-booking'); ?>">×</button>
                    </div>
                    <form class="wb-walkin-form">
                        <label><span><?php esc_html_e('تاریخ', 'webtanan-booking'); ?></span><input type="date" name="appointment_date" value="<?php echo esc_attr(current_time('Y-m-d')); ?>" required></label>
                        <label><span><?php esc_html_e('ساعت', 'webtanan-booking'); ?></span><input type="time" name="start_time" required></label>
                        <label><span><?php esc_html_e('نام', 'webtanan-booking'); ?></span><input type="text" name="patient_first_name" required></label>
                        <label><span><?php esc_html_e('نام خانوادگی', 'webtanan-booking'); ?></span><input type="text" name="patient_last_name" required></label>
                        <label><span><?php esc_html_e('کد ملی', 'webtanan-booking'); ?></span><input type="text" name="patient_national_code"></label>
                        <label><span><?php esc_html_e('موبایل', 'webtanan-booking'); ?></span><input type="tel" name="patient_mobile"></label>
                        <label><span><?php esc_html_e('وضعیت پرداخت', 'webtanan-booking'); ?></span>
                            <select name="payment_status">
                                <option value="cash_at_clinic"><?php esc_html_e('نقدی در مطب', 'webtanan-booking'); ?></option>
                                <option value="pos_at_clinic"><?php esc_html_e('کارت‌خوان', 'webtanan-booking'); ?></option>
                                <option value="unpaid"><?php esc_html_e('پرداخت‌نشده', 'webtanan-booking'); ?></option>
                            </select>
                        </label>
                        <div class="wb-form-actions">
                            <button type="button" class="wb-button wb-close-modal"><?php esc_html_e('انصراف', 'webtanan-booking'); ?></button>
                            <button type="submit" class="wb-button wb-button-primary"><?php esc_html_e('ثبت نوبت', 'webtanan-booking'); ?></button>
                        </div>
                        <div class="wb-form-message"></div>
                    </form>
                </div>
            </div>
        </div>
        <?php

        return (string) ob_get_clean();
    }

    private static function enqueue(): void {
        wp_enqueue_style('webtanan-booking-frontend');
        wp_enqueue_script('webtanan-booking-frontend');
    }

    private static function doctor_id_from_current_post(): int {
        global $wpdb;

        return (int) $wpdb->get_var(
            $wpdb->prepare('SELECT id FROM ' . DB::table('doctors') . ' WHERE post_id = %d', get_the_ID())
        );
    }
}
