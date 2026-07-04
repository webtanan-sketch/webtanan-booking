<?php
/**
 * Front-end shortcodes and assets.
 *
 * @package WebtananBooking
 */

namespace Webtanan\Booking;

defined('ABSPATH') || exit;

final class Frontend {
    private static $doctor_rating_cache = array();

    public static function init(): void {
        add_action('wp_enqueue_scripts', array(__CLASS__, 'register_assets'));
        add_action('wp_head', array(__CLASS__, 'render_doctor_schema_markup'), 20);
        add_action('wp_head', array(__CLASS__, 'render_fallback_archive_meta'), 3);
        add_filter('wp_robots', array(__CLASS__, 'filter_wp_robots'));
        add_filter('rank_math/frontend/robots', array(__CLASS__, 'filter_rank_math_robots'));
        add_filter('rank_math/frontend/canonical', array(__CLASS__, 'filter_canonical_url'));
        add_filter('rank_math/opengraph/url', array(__CLASS__, 'filter_canonical_url'));
        add_filter('rank_math/frontend/title', array(__CLASS__, 'filter_seo_title'));
        add_filter('rank_math/frontend/description', array(__CLASS__, 'filter_seo_description'));
        add_filter('document_title_parts', array(__CLASS__, 'filter_document_title_parts'));
        add_filter('show_admin_bar', array(__CLASS__, 'filter_admin_bar_visibility'));
        add_filter('query_vars', array(__CLASS__, 'query_vars'));
        add_action('template_redirect', array(__CLASS__, 'maybe_render_auth_page'));
        add_action('template_redirect', array(__CLASS__, 'maybe_render_payment_result_page'));
        add_action('template_redirect', array(__CLASS__, 'maybe_render_resume_payment_page'));
        add_action('template_redirect', array(__CLASS__, 'maybe_render_public_flow_page'));
        add_shortcode('webtanan_booking_auth', array(__CLASS__, 'auth_shortcode'));
        add_shortcode('webtanan_booking_homepage', array(__CLASS__, 'homepage_shortcode'));
        add_shortcode('webtanan_booking_doctor_search', array(__CLASS__, 'doctor_search_shortcode'));
        add_shortcode('webtanan_booking_doctor_list', array(__CLASS__, 'doctor_list_shortcode'));
        add_shortcode('webtanan_booking_doctor_card', array(__CLASS__, 'doctor_card_shortcode'));
        add_shortcode('webtanan_booking_specialty_list', array(__CLASS__, 'specialty_list_shortcode'));
        add_shortcode('webtanan_booking_doctors_archive', array(__CLASS__, 'doctors_archive_shortcode'));
        add_shortcode('webtanan_booking_calendar', array(__CLASS__, 'calendar_shortcode'));
        add_shortcode('webtanan_booking_next_available', array(__CLASS__, 'next_available_shortcode'));
        add_shortcode('webtanan_booking_patient_panel', array(__CLASS__, 'patient_panel_shortcode'));
        add_shortcode('webtanan_booking_doctor_dashboard', array(__CLASS__, 'doctor_dashboard_shortcode'));
        add_shortcode('webtanan_booking_resume_payment', array(__CLASS__, 'resume_payment_shortcode'));
        add_shortcode('webtanan_booking_waiting_list', array(__CLASS__, 'waiting_list_shortcode'));
        add_shortcode('webtanan_booking_survey', array(__CLASS__, 'survey_shortcode'));
    }

    public static function filter_admin_bar_visibility(bool $show): bool {
        if (!is_user_logged_in() || current_user_can('manage_options')) {
            return $show;
        }

        $user = wp_get_current_user();
        $private_roles = array('webtanan_patient', 'webtanan_doctor', 'webtanan_secretary');

        return array_intersect($private_roles, (array) $user->roles) ? false : $show;
    }

    public static function register_assets(): void {
        wp_register_style('webtanan-booking-fontawesome', WEBTANAN_BOOKING_URL . 'assets/vendor/fontawesome/css/all.min.css', array(), self::asset_version('assets/vendor/fontawesome/css/all.min.css'));
        wp_register_style('webtanan-booking-frontend', WEBTANAN_BOOKING_URL . 'assets/css/frontend.css', array('webtanan-booking-fontawesome'), self::asset_version('assets/css/frontend.css'));
        wp_register_script('webtanan-booking-jalaali-engine', WEBTANAN_BOOKING_URL . 'assets/vendor/jalaali-js/jalaali.min.js', array(), self::asset_version('assets/vendor/jalaali-js/jalaali.min.js'), true);
        wp_register_script('webtanan-booking-jalali', WEBTANAN_BOOKING_URL . 'assets/js/jalali-calendar.js', array('webtanan-booking-jalaali-engine'), self::asset_version('assets/js/jalali-calendar.js'), true);
        wp_register_script('webtanan-booking-chartjs', WEBTANAN_BOOKING_URL . 'assets/vendor/chartjs/chart.umd.min.js', array(), self::asset_version('assets/vendor/chartjs/chart.umd.min.js'), true);
        wp_register_script('webtanan-booking-frontend', WEBTANAN_BOOKING_URL . 'assets/js/frontend.js', array('webtanan-booking-jalali', 'webtanan-booking-chartjs'), self::asset_version('assets/js/frontend.js'), true);
        wp_localize_script(
            'webtanan-booking-frontend',
            'WebtananBooking',
            array(
                'restRoot' => esc_url_raw(rest_url()),
                'restNamespace' => 'saas/v1',
                'restUrl' => esc_url_raw(rest_url('saas/v1')),
                'assetsUrl' => esc_url_raw(WEBTANAN_BOOKING_URL . 'assets/'),
                'archiveUrl' => esc_url_raw(get_post_type_archive_link('saas_doctors') ?: add_query_arg('post_type', 'saas_doctors', home_url('/'))),
                'nonce' => wp_create_nonce('wp_rest'),
                'isLoggedIn' => is_user_logged_in(),
                'loginUrl' => esc_url_raw(Patient_Profile::login_url()),
                'today' => current_time('Y-m-d'),
                'strings' => array(
                    'loading' => __('در حال بارگذاری...', 'webtanan-booking'),
                    'noSlots' => __('نوبت آزادی پیدا نشد.', 'webtanan-booking'),
                    'selectSlot' => __('انتخاب نوبت', 'webtanan-booking'),
                    'locked' => __('نوبت برای شما نگه داشته شد. لطفاً قبل از پایان زمان باقی‌مانده پرداخت را کامل کنید.', 'webtanan-booking'),
                    'selectGateway' => __('انتخاب درگاه پرداخت', 'webtanan-booking'),
                    'redirectingToGateway' => __('در حال انتقال به درگاه پرداخت...', 'webtanan-booking'),
                    'loginRequiredForPayment' => __('برای پرداخت آنلاین ابتدا وارد حساب شوید.', 'webtanan-booking'),
                    'error' => __('خطایی رخ داد. لطفاً دوباره تلاش کنید.', 'webtanan-booking'),
                ),
            )
        );
        self::add_custom_font_inline_style('webtanan-booking-frontend');
    }

    public static function query_vars(array $vars): array {
        $vars[] = 'webtanan_payment_result';
        $vars[] = 'webtanan_auth_page';
        $vars[] = 'webtanan_resume_payment';
        $vars[] = 'webtanan_waiting_list';
        $vars[] = 'webtanan_survey';
        $vars[] = 'appointment_code';
        $vars[] = 'transaction_id';
        $vars[] = 'token';

        return $vars;
    }

    public static function maybe_render_auth_page(): void {
        if (!get_query_var('webtanan_auth_page') && empty($_GET['webtanan_auth_page'])) {
            return;
        }

        self::enqueue();
        status_header(200);
        get_header();
        echo '<main class="webtanan-booking wb-auth-page" dir="rtl"><div class="wb-auth-page-inner">';
        echo '<header class="wb-auth-page-head"><span>' . esc_html__('حساب کاربری وب‌تنان', 'webtanan-booking') . '</span><h1>' . esc_html__('ورود یا ثبت‌نام با شماره موبایل', 'webtanan-booking') . '</h1><p>' . esc_html__('برای مدیریت نوبت‌ها و ادامه رزرو، شماره موبایل خود را تایید کنید.', 'webtanan-booking') . '</p></header>';
        echo self::auth_shortcode();
        echo '</div></main>';
        get_footer();
        exit;
    }

    private static function asset_version(string $relative_path): string {
        $path = WEBTANAN_BOOKING_PATH . ltrim($relative_path, '/');
        if (is_readable($path)) {
            return WEBTANAN_BOOKING_VERSION . '.' . filemtime($path);
        }

        return WEBTANAN_BOOKING_VERSION;
    }

    private static function add_custom_font_inline_style(string $handle): void {
        if ('webtanan-saas-theme' === get_template()) {
            return;
        }

        $settings = DB::get_settings();
        $font_family = sanitize_text_field((string) ($settings['ui_font_family'] ?? ''));
        $font_url = esc_url_raw((string) ($settings['ui_font_url'] ?? ''));
        $font_attachment_id = absint($settings['ui_font_attachment_id'] ?? 0);
        if ('' === $font_url && $font_attachment_id > 0) {
            $font_url = esc_url_raw((string) wp_get_attachment_url($font_attachment_id));
        }

        if ('' === $font_family || '' === $font_url) {
            return;
        }

        $format = 'truetype';
        if (preg_match('/\.woff2(\?|$)/i', $font_url)) {
            $format = 'woff2';
        } elseif (preg_match('/\.woff(\?|$)/i', $font_url)) {
            $format = 'woff';
        }

        $css = '@font-face{font-family:"' . esc_attr($font_family) . '";src:url("' . esc_url($font_url) . '") format("' . esc_attr($format) . '");font-display:swap;}';
        $css .= '.webtanan-booking,.webtanan-booking *{font-family:"' . esc_attr($font_family) . '",inherit;}';
        wp_add_inline_style($handle, $css);
    }

    public static function maybe_render_payment_result_page(): void {
        if (!get_query_var('webtanan_payment_result')) {
            return;
        }

        $transaction_id = absint(get_query_var('transaction_id') ?: ($_GET['transaction_id'] ?? 0));
        $token = sanitize_text_field((string) (get_query_var('token') ?: ($_GET['token'] ?? '')));
        if (!Payment_Gateways::validate_payment_result_token($transaction_id, $token)) {
            status_header(403);
            self::enqueue();
            get_header();
            echo '<main class="webtanan-booking webtanan-payment-result" dir="rtl"><section class="wb-result-card wb-result-state-danger"><h1>' . esc_html__('لینک نتیجه پرداخت معتبر نیست', 'webtanan-booking') . '</h1><p>' . esc_html__('برای امنیت پرداخت، این لینک قابل نمایش نیست.', 'webtanan-booking') . '</p></section></main>';
            get_footer();
            exit;
        }

        $data = Payment_Gateways::payment_result_data($transaction_id);
        self::enqueue();
        get_header();
        echo self::payment_result_markup(is_wp_error($data) ? null : $data, $data);
        get_footer();
        exit;
    }

    public static function maybe_render_resume_payment_page(): void {
        if (!get_query_var('webtanan_resume_payment')) {
            return;
        }

        self::enqueue();
        get_header();
        echo '<main class="webtanan-booking webtanan-payment-result webtanan-resume-payment-page" dir="rtl">' . self::resume_payment_shortcode() . '</main>';
        get_footer();
        exit;
    }

    public static function maybe_render_public_flow_page(): void {
        $is_waiting_list = (bool) get_query_var('webtanan_waiting_list') || isset($_GET['webtanan_waiting_list']);
        $is_survey = (bool) get_query_var('webtanan_survey') || isset($_GET['webtanan_survey']);
        if (!$is_waiting_list && !$is_survey) {
            return;
        }

        $code = sanitize_text_field((string) (get_query_var('appointment_code') ?: ($_GET['appointment_code'] ?? ($_GET['code'] ?? ''))));
        $token = sanitize_text_field((string) (get_query_var('token') ?: ($_GET['token'] ?? '')));

        self::register_assets();
        self::enqueue();
        get_header();
        echo '<main class="webtanan-booking wb-public-flow-page" dir="rtl">';
        echo $is_waiting_list
            ? self::waiting_list_shortcode(array('code' => $code, 'token' => $token))
            : self::survey_shortcode(array('code' => $code, 'token' => $token));
        echo '</main>';
        get_footer();
        exit;
    }

    public static function filter_wp_robots(array $robots): array {
        if (!self::should_noindex_request()) {
            return $robots;
        }

        $robots['noindex'] = true;
        $robots['nofollow'] = true;
        unset($robots['index'], $robots['follow']);

        return $robots;
    }

    public static function filter_rank_math_robots(array $robots): array {
        if (!self::should_noindex_request()) {
            return $robots;
        }

        $robots['index'] = 'noindex';
        $robots['follow'] = 'nofollow';

        return $robots;
    }

    public static function filter_canonical_url($canonical) {
        if (self::is_private_request()) {
            return '';
        }

        if (is_post_type_archive('saas_doctors')) {
            $specialty_id = Post_Types::specialty_id_from_request();
            if ($specialty_id > 0) {
                return Post_Types::specialty_url($specialty_id);
            }

            return get_post_type_archive_link('saas_doctors') ?: $canonical;
        }

        return $canonical;
    }

    public static function filter_seo_title(string $title): string {
        if (!is_post_type_archive('saas_doctors')) {
            return $title;
        }

        $specialty = Post_Types::specialty(Post_Types::specialty_id_from_request());
        if ($specialty) {
            return sprintf(__('پزشکان %s و رزرو نوبت آنلاین', 'webtanan-booking'), $specialty['name']);
        }

        return __('لیست پزشکان و رزرو نوبت آنلاین', 'webtanan-booking');
    }

    public static function filter_seo_description(string $description): string {
        if (!is_post_type_archive('saas_doctors')) {
            return $description;
        }

        $specialty = Post_Types::specialty(Post_Types::specialty_id_from_request());
        if ($specialty) {
            return sprintf(__('پزشکان تخصص %s را مقایسه کنید، نزدیک‌ترین ساعت آزاد را ببینید و نوبت خود را آنلاین رزرو کنید.', 'webtanan-booking'), $specialty['name']);
        }

        return __('پزشک مناسب را بر اساس نام و تخصص پیدا کنید، ساعت‌های آزاد را مقایسه کنید و نوبت بگیرید.', 'webtanan-booking');
    }

    public static function filter_document_title_parts(array $parts): array {
        if (is_post_type_archive('saas_doctors')) {
            $parts['title'] = self::filter_seo_title((string) ($parts['title'] ?? ''));
        }

        return $parts;
    }

    public static function render_fallback_archive_meta(): void {
        if (defined('RANK_MATH_VERSION') || defined('WPSEO_VERSION') || !is_post_type_archive('saas_doctors')) {
            return;
        }

        $canonical = self::filter_canonical_url(get_post_type_archive_link('saas_doctors'));
        $description = self::filter_seo_description('');
        if ($canonical) {
            echo '<link rel="canonical" href="' . esc_url($canonical) . '">' . "\n";
        }
        if ($description) {
            echo '<meta name="description" content="' . esc_attr($description) . '">' . "\n";
        }
    }

    public static function render_doctor_schema_markup(): void {
        global $wpdb;

        if (!is_singular('saas_doctors')) {
            return;
        }

        $post_id = get_queried_object_id();
        if (!$post_id) {
            return;
        }

        $doctor = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT d.*, s.name AS specialty_name
                FROM ' . DB::table('doctors') . ' d
                LEFT JOIN ' . DB::table('specialties') . ' s ON s.id = d.specialty_id
                WHERE d.post_id = %d AND d.is_active = 1 AND d.is_verified = 1
                LIMIT 1',
                $post_id
            ),
            ARRAY_A
        );

        if (!$doctor) {
            return;
        }

        $description = get_the_excerpt($post_id);
        if (!$description) {
            $description = wp_trim_words(wp_strip_all_tags((string) get_post_field('post_content', $post_id)), 35);
        }

        $image_url = get_the_post_thumbnail_url($post_id, 'full');
        $schema = array(
            '@context' => 'https://schema.org',
            '@type' => 'Physician',
            '@id' => trailingslashit(get_permalink($post_id)) . '#physician',
            'name' => wp_strip_all_tags(get_the_title($post_id)),
            'url' => get_permalink($post_id),
            'description' => $description ? wp_strip_all_tags($description) : '',
            'image' => $image_url ?: '',
            'telephone' => !empty($doctor['clinic_phone']) ? wp_strip_all_tags((string) $doctor['clinic_phone']) : '',
            'medicalSpecialty' => !empty($doctor['specialty_name']) ? wp_strip_all_tags((string) $doctor['specialty_name']) : '',
            'address' => !empty($doctor['clinic_address']) ? array(
                '@type' => 'PostalAddress',
                'streetAddress' => wp_strip_all_tags((string) $doctor['clinic_address']),
                'addressCountry' => 'IR',
            ) : array(),
            'identifier' => !empty($doctor['medical_system_number']) ? array(
                '@type' => 'PropertyValue',
                'name' => __('کد نظام پزشکی', 'webtanan-booking'),
                'value' => wp_strip_all_tags((string) $doctor['medical_system_number']),
            ) : array(),
            'availableService' => array(
                '@type' => 'MedicalProcedure',
                'name' => __('نوبت‌دهی پزشک', 'webtanan-booking'),
            ),
        );

        $rating = self::doctor_aggregate_rating($post_id);
        if ($rating) {
            $schema['aggregateRating'] = $rating;
        }

        $schema = self::compact_schema($schema);
        if (!$schema) {
            return;
        }

        echo '<script type="application/ld+json">' . wp_json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "</script>\n";
    }

    private static function payment_result_markup(?array $data, $raw_result): string {
        if (!$data) {
            $message = is_wp_error($raw_result) ? $raw_result->get_error_message() : __('نتیجه پرداخت پیدا نشد.', 'webtanan-booking');

            return '<main class="webtanan-booking webtanan-payment-result" dir="rtl"><section class="wb-result-card wb-result-state-danger"><h1>' . esc_html__('پرداخت پیدا نشد', 'webtanan-booking') . '</h1><p>' . esc_html($message) . '</p></section></main>';
        }

        $status = sanitize_key((string) ($data['status'] ?? 'pending'));
        $transaction = $data['transaction'];
        $appointment = $data['appointment'];
        $doctor = $data['doctor'];
        $doctor_name = (string) ($data['doctor_name'] ?? '');
        $doctor_url = (string) ($data['doctor_url'] ?? '');
        $state_class = 'wb-result-state-pending';
        $title = __('پرداخت در حال بررسی است', 'webtanan-booking');
        $message = __('اگر مبلغ از حساب شما کم شده باشد، نتیجه نهایی تا چند لحظه دیگر مشخص می‌شود.', 'webtanan-booking');

        if ('confirmed' === $status) {
            $state_class = 'wb-result-state-success';
            $title = __('نوبتت قطعی شد', 'webtanan-booking');
            $message = __('رسید نوبت آماده است. لطفاً کد نوبت را نگه دارید.', 'webtanan-booking');
        } elseif ('wallet_refunded' === $status) {
            $state_class = 'wb-result-state-warning';
            $title = __('پرداخت انجام شد، ولی ساعت از دست رفت', 'webtanan-booking');
            $message = __('پولت به کیف پول برگشت. می‌توانی یک ساعت آزاد دیگر انتخاب کنی.', 'webtanan-booking');
        } elseif ('wallet_charged' === $status) {
            $state_class = 'wb-result-state-success';
            $title = __('کیف پولت شارژ شد', 'webtanan-booking');
            $message = __('موجودی کیف پول با موفقیت افزایش پیدا کرد.', 'webtanan-booking');
        } elseif ('failed' === $status) {
            $state_class = 'wb-result-state-danger';
            $title = __('پرداخت کامل نشد', 'webtanan-booking');
            $message = __('می‌توانی دوباره پرداخت را امتحان کنی یا بعداً با کد نوبت ادامه بدهی.', 'webtanan-booking');
        }

        ob_start();
        ?>
        <main class="webtanan-booking webtanan-payment-result" dir="rtl">
            <section class="wb-result-card <?php echo esc_attr($state_class); ?>">
                <div class="wb-result-hero">
                    <div class="wb-result-icon" aria-hidden="true"><?php echo 'failed' === $status ? '!' : ('wallet_refunded' === $status ? '↺' : '✓'); ?></div>
                    <div>
                        <h1><?php echo esc_html($title); ?></h1>
                        <p><?php echo esc_html($message); ?></p>
                    </div>
                </div>

                <div class="wb-factor-card wb-receipt-paper">
                    <div class="wb-receipt-watermark" aria-hidden="true">+</div>
                    <header class="wb-receipt-paper-head">
                        <div>
                            <span><?php esc_html_e('فاکتور نوبت', 'webtanan-booking'); ?></span>
                            <strong><?php echo esc_html('confirmed' === $status ? __('رسید نوبت شما', 'webtanan-booking') : __('وضعیت پرداخت نوبت', 'webtanan-booking')); ?></strong>
                        </div>
                    </header>
                    <?php if (!empty($appointment['appointment_code']) || !empty($transaction['transaction_code'])) : ?>
                        <div class="wb-receipt-hero-code"><span><?php esc_html_e('کد پیگیری نوبت', 'webtanan-booking'); ?></span><strong><?php echo esc_html((string) ($appointment['appointment_code'] ?? $transaction['transaction_code'])); ?></strong></div>
                    <?php endif; ?>
                    <dl class="wb-factor-list">
                        <?php if ($appointment) : ?>
                            <dt><?php esc_html_e('پزشک', 'webtanan-booking'); ?></dt>
                            <dd><?php echo esc_html($doctor_name); ?></dd>
                            <dt><?php esc_html_e('بیمار', 'webtanan-booking'); ?></dt>
                            <dd><?php echo esc_html(trim((string) $appointment['patient_first_name'] . ' ' . (string) $appointment['patient_last_name'])); ?></dd>
                            <dt><?php esc_html_e('تاریخ و ساعت', 'webtanan-booking'); ?></dt>
                            <dd><?php echo esc_html(self::localized_appointment_date((string) $appointment['appointment_date']) . ' - ' . substr((string) $appointment['start_time'], 0, 5)); ?></dd>
                            <dt><?php esc_html_e('هزینه خدمات رزرو نوبت', 'webtanan-booking'); ?></dt>
                            <dd><?php echo esc_html(number_format_i18n((float) ($transaction['amount'] ?? 0))); ?> <?php esc_html_e('تومان', 'webtanan-booking'); ?></dd>
                            <dt><?php esc_html_e('وضعیت پرداخت', 'webtanan-booking'); ?></dt>
                            <dd><?php echo esc_html(self::payment_result_status_label($status)); ?></dd>
                            <?php if (!empty($transaction['gateway_ref_id']) || !empty($transaction['gateway_tracking_number'])) : ?>
                                <dt><?php esc_html_e('کد پیگیری', 'webtanan-booking'); ?></dt>
                                <dd><?php echo esc_html((string) ($transaction['gateway_ref_id'] ?: $transaction['gateway_tracking_number'])); ?></dd>
                            <?php endif; ?>
                            <?php if (!empty($doctor['clinic_address'])) : ?>
                                <dt><?php esc_html_e('آدرس مطب', 'webtanan-booking'); ?></dt>
                                <dd><?php echo esc_html((string) $doctor['clinic_address']); ?></dd>
                            <?php endif; ?>
                        <?php else : ?>
                            <dt><?php esc_html_e('شرح', 'webtanan-booking'); ?></dt>
                            <dd><?php esc_html_e('شارژ کیف پول', 'webtanan-booking'); ?></dd>
                            <dt><?php esc_html_e('مبلغ', 'webtanan-booking'); ?></dt>
                            <dd><?php echo esc_html(number_format_i18n((float) ($transaction['amount'] ?? 0))); ?> <?php esc_html_e('تومان', 'webtanan-booking'); ?></dd>
                            <dt><?php esc_html_e('وضعیت', 'webtanan-booking'); ?></dt>
                            <dd><?php echo esc_html(self::payment_result_status_label($status)); ?></dd>
                        <?php endif; ?>
                    </dl>
                    <table class="wb-receipt-accounting">
                        <tbody><tr><th><?php esc_html_e('هزینه خدمات رزرو نوبت', 'webtanan-booking'); ?></th><td><?php echo esc_html(number_format_i18n((float) ($transaction['amount'] ?? 0))); ?> <?php esc_html_e('تومان', 'webtanan-booking'); ?></td></tr></tbody>
                    </table>
                    <p class="webtanan-checkout-note"><?php esc_html_e('این فاکتور برای پیگیری پرداخت و نوبت شما صادر شده است.', 'webtanan-booking'); ?></p>
                </div>

                <?php if ('wallet_refunded' === $status && !empty($data['suggested_slots'])) : ?>
                    <div class="wb-suggested-slots">
                        <h2><?php esc_html_e('چند ساعت آزاد نزدیک', 'webtanan-booking'); ?></h2>
                        <div class="wb-slot-suggestions">
                            <?php foreach ((array) $data['suggested_slots'] as $slot) : ?>
                                <a href="<?php echo esc_url($doctor_url); ?>" class="wb-slot-chip"><?php echo esc_html((string) ($slot['date'] ?? '') . ' - ' . (string) ($slot['start_time'] ?? '')); ?></a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($appointment && in_array($status, array('failed', 'pending'), true)) : ?>
                    <?php
                    $is_owner = is_user_logged_in() && (int) ($appointment['patient_user_id'] ?? 0) === get_current_user_id();
                    echo self::resume_payment_shortcode(
                        array(
                            'appointment_id' => $is_owner ? (int) $appointment['id'] : 0,
                            'appointment_code' => (string) $appointment['appointment_code'],
                            'mobile' => (string) $appointment['patient_mobile'],
                        )
                    );
                    ?>
                <?php endif; ?>

                <div class="wb-result-actions">
                    <button type="button" class="wb-btn wb-btn-primary" onclick="window.print()"><?php esc_html_e('چاپ فاکتور', 'webtanan-booking'); ?></button>
                    <?php if ($doctor_url) : ?>
                        <a class="wb-btn" href="<?php echo esc_url($doctor_url); ?>"><?php esc_html_e('گرفتن نوبت جدید', 'webtanan-booking'); ?></a>
                    <?php endif; ?>
                    <a class="wb-btn" href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('بازگشت به سایت', 'webtanan-booking'); ?></a>
                </div>
            </section>
        </main>
        <?php

        return (string) ob_get_clean();
    }

    private static function payment_result_status_label(string $status): string {
        $labels = array(
            'confirmed' => __('تایید شده', 'webtanan-booking'),
            'wallet_refunded' => __('استرداد به کیف پول', 'webtanan-booking'),
            'wallet_charged' => __('شارژ موفق کیف پول', 'webtanan-booking'),
            'failed' => __('ناموفق', 'webtanan-booking'),
            'pending' => __('در حال بررسی', 'webtanan-booking'),
        );

        return $labels[$status] ?? __('در حال بررسی', 'webtanan-booking');
    }

    private static function localized_appointment_date(string $date): string {
        $timestamp = strtotime($date . ' 12:00:00');
        if (!$timestamp || !class_exists('IntlDateFormatter')) {
            return $date;
        }

        $formatter = new \IntlDateFormatter(
            'fa_IR@calendar=persian',
            \IntlDateFormatter::NONE,
            \IntlDateFormatter::NONE,
            wp_timezone_string(),
            \IntlDateFormatter::TRADITIONAL,
            'yyyy/MM/dd'
        );
        $formatted = $formatter->format($timestamp);

        return is_string($formatted) && '' !== $formatted ? $formatted : $date;
    }

    public static function resume_payment_shortcode($atts = array()): string {
        self::enqueue();
        $atts = shortcode_atts(
            array(
                'appointment_id' => 0,
                'appointment_code' => '',
                'mobile' => '',
            ),
            (array) $atts,
            'webtanan_booking_resume_payment'
        );

        return self::resume_payment_markup($atts);
    }

    private static function doctors_archive_embed_markup(array $atts): string {
        ob_start();
        ?>
        <div class="webtanan-booking webtanan-shortcode-archive wb-archive-embed" dir="rtl">
            <div class="webtanan-shortcode-specialties wb-archive-specialties">
                <?php echo self::specialty_list_shortcode(array('show_count' => 'yes')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </div>
            <?php
            echo self::doctor_search_shortcode(
                array(
                    'per_page' => absint($atts['per_page']),
                    'default_search' => sanitize_text_field((string) $atts['search']),
                    'search_placeholder' => __('نام پزشک، تخصص یا آدرس مطب', 'webtanan-booking'),
                    'specialty_id' => absint($atts['specialty_id']),
                    'province_id' => absint($atts['province_id']),
                    'city_id' => absint($atts['city_id']),
                    'payment_filter' => sanitize_key((string) $atts['payment_filter']),
                    'sort' => 'first_available',
                    'layout' => 'list' === sanitize_key((string) $atts['layout']) ? 'list' : 'grid',
                    'show_filters' => 'yes',
                    'show_sort' => 'yes',
                )
            ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            ?>
        </div>
        <?php

        return (string) ob_get_clean();
    }

    private static function resume_payment_markup(array $atts): string {
        ob_start();
        ?>
        <?php $appointment_id = absint($atts['appointment_id'] ?? 0); ?>
        <section class="webtanan-booking webtanan-resume-payment" data-webtanan-widget="resume-payment" data-appointment-id="<?php echo esc_attr((string) $appointment_id); ?>" dir="rtl">
            <header>
                <span><?php esc_html_e('ادامه پرداخت', 'webtanan-booking'); ?></span>
                <h2><?php esc_html_e('پرداخت نوبت را کامل کن', 'webtanan-booking'); ?></h2>
                <p><?php echo esc_html($appointment_id ? __('روش پرداخت را انتخاب کن و نوبتت را قطعی کن.', 'webtanan-booking') : __('کد نوبت و موبایل را وارد کن؛ بعد از تایید شماره، پرداخت ادامه پیدا می‌کند.', 'webtanan-booking')); ?></p>
            </header>
            <form class="wb-resume-form" <?php echo $appointment_id ? 'hidden' : ''; ?>>
                <label>
                    <span><?php esc_html_e('کد نوبت', 'webtanan-booking'); ?></span>
                    <input type="text" name="appointment_code" value="<?php echo esc_attr((string) $atts['appointment_code']); ?>" required>
                </label>
                <label>
                    <span><?php esc_html_e('شماره موبایل', 'webtanan-booking'); ?></span>
                    <input type="tel" name="mobile" value="<?php echo esc_attr((string) $atts['mobile']); ?>" required>
                </label>
                <button type="submit" class="wb-btn wb-btn-primary"><?php esc_html_e('ارسال کد تایید', 'webtanan-booking'); ?></button>
            </form>
            <form class="wb-resume-otp" hidden>
                <label>
                    <span><?php esc_html_e('کد تایید', 'webtanan-booking'); ?></span>
                    <input type="text" name="otp" inputmode="numeric" autocomplete="one-time-code" required>
                </label>
                <button type="submit" class="wb-btn wb-btn-primary"><?php esc_html_e('ادامه به پرداخت', 'webtanan-booking'); ?></button>
            </form>
            <div class="wb-resume-checkout" hidden></div>
            <div class="wb-resume-message" aria-live="polite"></div>
        </section>
        <?php

        return (string) ob_get_clean();
    }

    public static function waiting_list_shortcode($atts = array()): string {
        self::enqueue();
        $atts = shortcode_atts(
            array(
                'code' => '',
                'token' => '',
            ),
            $atts,
            'webtanan_booking_waiting_list'
        );

        $code = sanitize_text_field((string) ($atts['code'] ?: ($_GET['appointment_code'] ?? ($_GET['code'] ?? ''))));
        $token = sanitize_text_field((string) ($atts['token'] ?: ($_GET['token'] ?? '')));

        return '<section class="webtanan-booking wb-public-flow wb-waiting-list-widget" data-webtanan-widget="waiting-list" data-code="' . esc_attr($code) . '" data-token="' . esc_attr($token) . '" dir="rtl"></section>';
    }

    public static function survey_shortcode($atts = array()): string {
        self::enqueue();
        $atts = shortcode_atts(
            array(
                'code' => '',
                'token' => '',
            ),
            $atts,
            'webtanan_booking_survey'
        );

        $code = sanitize_text_field((string) ($atts['code'] ?: ($_GET['appointment_code'] ?? ($_GET['code'] ?? ''))));
        $token = sanitize_text_field((string) ($atts['token'] ?: ($_GET['token'] ?? '')));

        return '<section class="webtanan-booking wb-public-flow wb-survey-widget" data-webtanan-widget="survey" data-code="' . esc_attr($code) . '" data-token="' . esc_attr($token) . '" dir="rtl" aria-live="polite" aria-busy="true">'
            . '<div class="wb-public-card wb-public-loading">'
            . '<span class="wb-kicker">نظرسنجی نوبت</span>'
            . '<h1>ثبت تجربه مراجعه</h1>'
            . '<p>در حال آماده‌سازی فرم نظرسنجی...</p>'
            . '<span class="wb-public-spinner" aria-hidden="true"></span>'
            . '</div>'
            . '</section>';
    }

    public static function homepage_shortcode($atts = array()): string {
        self::enqueue();

        ob_start();
        $template = WEBTANAN_BOOKING_PATH . 'templates/homepage.php';
        if (is_readable($template)) {
            include $template;
        }

        return (string) ob_get_clean();
    }

    public static function auth_shortcode(): string {
        self::enqueue();
        $context = Patient_Profile::context();

        ob_start();
        ?>
        <div class="webtanan-booking webtanan-auth-box" data-webtanan-widget="auth" data-redirect-url="<?php echo esc_attr((string) ($context['redirect_url'] ?? '')); ?>" dir="rtl">
            <div class="webtanan-auth-head">
                <span><?php esc_html_e('ورود امن با پیامک', 'webtanan-booking'); ?></span>
                <strong><?php esc_html_e('خوش آمدید', 'webtanan-booking'); ?></strong>
            </div>
            <form class="webtanan-auth-mobile">
                <fieldset class="wb-account-type" aria-label="<?php esc_attr_e('نوع حساب', 'webtanan-booking'); ?>">
                    <legend><?php esc_html_e('نوع حساب', 'webtanan-booking'); ?></legend>
                    <label><input type="radio" name="account_type" value="patient" checked><span><i class="fas fa-user" aria-hidden="true"></i><?php esc_html_e('بیمار هستم', 'webtanan-booking'); ?></span></label>
                    <label><input type="radio" name="account_type" value="doctor"><span><i class="fas fa-user-md" aria-hidden="true"></i><?php esc_html_e('پزشک هستم', 'webtanan-booking'); ?></span></label>
                </fieldset>
                <label>
                    <span><?php esc_html_e('شماره موبایل', 'webtanan-booking'); ?></span>
                    <input type="tel" name="mobile" inputmode="tel" autocomplete="tel" placeholder="09123456789" required>
                </label>
                <button type="submit" class="webtanan-button webtanan-button-primary"><?php esc_html_e('ارسال کد ورود', 'webtanan-booking'); ?></button>
            </form>
            <form class="webtanan-auth-otp" hidden>
                <label>
                    <span><?php esc_html_e('کد تایید', 'webtanan-booking'); ?></span>
                    <input type="text" name="otp" inputmode="numeric" autocomplete="one-time-code" required>
                </label>
                <button type="submit" class="webtanan-button webtanan-button-primary"><?php esc_html_e('ورود', 'webtanan-booking'); ?></button>
                <button type="button" class="webtanan-button webtanan-auth-resend"><?php esc_html_e('ارسال دوباره کد', 'webtanan-booking'); ?></button>
                <button type="button" class="webtanan-button webtanan-auth-back"><?php esc_html_e('تغییر شماره', 'webtanan-booking'); ?></button>
            </form>
            <form class="webtanan-auth-profile" hidden>
                <div class="webtanan-auth-profile-head">
                    <strong><?php esc_html_e('تکمیل اطلاعات حساب', 'webtanan-booking'); ?></strong>
                    <p><?php esc_html_e('این اطلاعات برای ثبت دقیق نوبت و صدور رسید استفاده می‌شود.', 'webtanan-booking'); ?></p>
                </div>
                <div class="wb-booking-field-grid">
                    <label class="wb-booking-field">
                        <span><?php esc_html_e('نام', 'webtanan-booking'); ?></span>
                        <input type="text" name="first_name" autocomplete="given-name" required>
                    </label>
                    <label class="wb-booking-field">
                        <span><?php esc_html_e('نام خانوادگی', 'webtanan-booking'); ?></span>
                        <input type="text" name="last_name" autocomplete="family-name" required>
                    </label>
                    <label class="wb-booking-field wb-booking-field-wide">
                        <span><?php esc_html_e('کد ملی', 'webtanan-booking'); ?></span>
                        <input type="text" name="national_code" inputmode="numeric" maxlength="10" autocomplete="off" required>
                    </label>
                    <div class="wb-doctor-application-fields wb-booking-field-wide" hidden>
                        <label class="wb-booking-field">
                            <span><?php esc_html_e('شماره نظام پزشکی', 'webtanan-booking'); ?></span>
                            <input type="text" name="medical_system_number" autocomplete="off">
                        </label>
                        <label class="wb-booking-field">
                            <span><?php esc_html_e('تخصص', 'webtanan-booking'); ?></span>
                            <input type="text" name="specialty" autocomplete="organization-title">
                        </label>
                        <p><?php esc_html_e('حساب پزشک پس از بررسی و تایید مدیریت فعال می‌شود.', 'webtanan-booking'); ?></p>
                    </div>
                </div>
                <button type="submit" class="webtanan-button webtanan-button-primary"><?php esc_html_e('ذخیره و ورود به پنل', 'webtanan-booking'); ?></button>
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
                'per_page' => 50,
                'default_search' => '',
                'search_placeholder' => __('نام پزشک، تخصص یا آدرس مطب', 'webtanan-booking'),
                'specialty_id' => 0,
                'city_id' => 0,
                'province_id' => 0,
                'payment_filter' => '',
                'sort' => 'first_available',
                'layout' => 'grid',
                'show_filters' => 'yes',
                'show_location' => 'yes',
                'show_sort' => 'yes',
                'available_only' => '0',
            ),
            $atts,
            'webtanan_booking_doctor_search'
        );
        $show_filters = 'no' !== strtolower((string) $atts['show_filters']);
        $show_location = 'no' !== strtolower((string) $atts['show_location']);
        $show_sort = 'no' !== strtolower((string) $atts['show_sort']);
        $specialties = $show_filters ? $wpdb->get_results('SELECT id, name FROM ' . DB::table('specialties') . ' WHERE is_active = 1 ORDER BY sort_order ASC, name ASC LIMIT 300', ARRAY_A) : array();
        $provinces = $show_filters && $show_location ? $wpdb->get_results('SELECT DISTINCT province_id AS id FROM ' . DB::table('doctors') . ' WHERE province_id > 0 AND is_active = 1 AND is_verified = 1 ORDER BY province_id ASC LIMIT 300', ARRAY_A) : array();
        $cities = $show_filters && $show_location ? $wpdb->get_results('SELECT DISTINCT city_id AS id FROM ' . DB::table('doctors') . ' WHERE city_id > 0 AND is_active = 1 AND is_verified = 1 ORDER BY city_id ASC LIMIT 300', ARRAY_A) : array();
        $payment_filter = sanitize_key((string) $atts['payment_filter']);
        $sort = sanitize_key((string) $atts['sort']);
        $layout = 'list' === sanitize_key((string) $atts['layout']) ? 'list' : 'grid';
        $default_search = sanitize_text_field((string) $atts['default_search']);
        $available_only = in_array((string) $atts['available_only'], array('1', 'yes', 'true'), true) ? '1' : '0';

        ob_start();
        ?>
        <div class="webtanan-booking webtanan-sample-ui webtanan-doctor-search wb-doctor-discovery" data-webtanan-widget="doctor-search" data-per-page="<?php echo esc_attr((string) absint($atts['per_page'])); ?>" data-specialty-id="<?php echo esc_attr((string) absint($atts['specialty_id'])); ?>" data-city-id="<?php echo esc_attr((string) absint($atts['city_id'])); ?>" data-province-id="<?php echo esc_attr((string) absint($atts['province_id'])); ?>" data-payment-filter="<?php echo esc_attr($payment_filter); ?>" data-sort="<?php echo esc_attr($sort ?: 'first_available'); ?>" data-layout="<?php echo esc_attr($layout); ?>" data-available-only="<?php echo esc_attr($available_only); ?>" dir="rtl">
            <form class="search-section webtanan-doctor-search-form wb-discovery-form" action="<?php echo esc_url(get_post_type_archive_link('saas_doctors') ?: home_url('/')); ?>" method="get">
                <span class="screen-reader-text"><?php esc_html_e('جستجوی پزشک', 'webtanan-booking'); ?></span>
                <div class="search-row wb-discovery-searchbar">
                    <input type="search" class="search-input webtanan-doctor-search-input" name="search" value="<?php echo esc_attr($default_search); ?>" placeholder="<?php echo esc_attr((string) $atts['search_placeholder']); ?>">
                    <button type="submit" class="btn btn-primary webtanan-search-button wb-search-submit"><i class="fas fa-search" aria-hidden="true"></i><?php esc_html_e('جستجو', 'webtanan-booking'); ?></button>
                </div>
                <?php if ($show_filters) : ?>
                    <div class="filter-row wb-discovery-sidebar" aria-label="<?php esc_attr_e('فیلترهای جستجو', 'webtanan-booking'); ?>">
                    <label class="filter-group wb-search-field">
                        <span><?php esc_html_e('تخصص', 'webtanan-booking'); ?></span>
                        <select class="webtanan-doctor-specialty-filter" name="specialty_id" aria-label="<?php esc_attr_e('تخصص', 'webtanan-booking'); ?>">
                            <option value="0"><?php esc_html_e('همه تخصص‌ها', 'webtanan-booking'); ?></option>
                            <?php foreach ($specialties as $specialty) : ?>
                                <option value="<?php echo esc_attr((string) $specialty['id']); ?>" <?php selected(absint($atts['specialty_id']), (int) $specialty['id']); ?>><?php echo esc_html($specialty['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <?php if ($show_location) : ?>
                        <label class="filter-group wb-search-field">
                            <span><?php esc_html_e('استان', 'webtanan-booking'); ?></span>
                            <select class="webtanan-doctor-province-filter" name="province_id" aria-label="<?php esc_attr_e('استان', 'webtanan-booking'); ?>">
                                <option value="0"><?php esc_html_e('همه استان‌ها', 'webtanan-booking'); ?></option>
                                <?php foreach ($provinces as $province) : ?>
                                    <option value="<?php echo esc_attr((string) $province['id']); ?>" <?php selected(absint($atts['province_id']), (int) $province['id']); ?>><?php echo esc_html(sprintf(__('استان #%d', 'webtanan-booking'), (int) $province['id'])); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="filter-group wb-search-field">
                            <span><?php esc_html_e('شهر', 'webtanan-booking'); ?></span>
                            <select class="webtanan-doctor-city-filter" name="city_id" aria-label="<?php esc_attr_e('شهر', 'webtanan-booking'); ?>">
                                <option value="0"><?php esc_html_e('همه شهرها', 'webtanan-booking'); ?></option>
                                <?php foreach ($cities as $city) : ?>
                                    <option value="<?php echo esc_attr((string) $city['id']); ?>" <?php selected(absint($atts['city_id']), (int) $city['id']); ?>><?php echo esc_html(sprintf(__('شهر #%d', 'webtanan-booking'), (int) $city['id'])); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    <?php endif; ?>
                    <?php if ($show_sort) : ?>
                        <label class="filter-group wb-search-field">
                            <span><?php esc_html_e('مرتب‌سازی', 'webtanan-booking'); ?></span>
                            <select class="webtanan-doctor-sort-filter" name="sort" aria-label="<?php esc_attr_e('مرتب‌سازی', 'webtanan-booking'); ?>">
                                <option value="first_available" <?php selected($sort ?: 'first_available', 'first_available'); ?>><?php esc_html_e('نزدیک‌ترین نوبت آزاد', 'webtanan-booking'); ?></option>
                                <option value="" <?php selected($sort, ''); ?>><?php esc_html_e('پیش‌فرض', 'webtanan-booking'); ?></option>
                            </select>
                        </label>
                    <?php endif; ?>
                    <label class="filter-group toggle-group wb-search-field wb-search-toggle">
                        <input type="checkbox" class="webtanan-doctor-available-filter" name="available_only" value="1" <?php checked($available_only, '1'); ?>>
                        <span><?php esc_html_e('فقط پزشکان دارای نوبت آزاد', 'webtanan-booking'); ?></span>
                    </label>
                    </div>
                <?php endif; ?>
            </form>
            <div class="webtanan-doctor-results" aria-live="polite"></div>
        </div>
        <?php

        return (string) ob_get_clean();
    }

    public static function doctor_list_shortcode($atts = array()): string {
        self::enqueue();
        $atts = shortcode_atts(array('per_page' => 50, 'specialty_id' => 0, 'city_id' => 0, 'province_id' => 0, 'payment_filter' => '', 'sort' => '', 'online' => '', 'pay_at_clinic' => '', 'layout' => 'grid', 'available_only' => '0'), $atts, 'webtanan_booking_doctor_list');
        $layout = 'list' === sanitize_key((string) $atts['layout']) ? 'list' : 'grid';

        return '<div class="webtanan-booking webtanan-sample-ui webtanan-doctor-list webtanan-doctor-list-container" data-webtanan-widget="doctor-list" data-per-page="' . esc_attr((string) absint($atts['per_page'])) . '" data-specialty-id="' . esc_attr((string) absint($atts['specialty_id'])) . '" data-city-id="' . esc_attr((string) absint($atts['city_id'])) . '" data-province-id="' . esc_attr((string) absint($atts['province_id'])) . '" data-payment-filter="' . esc_attr(sanitize_key((string) $atts['payment_filter'])) . '" data-sort="' . esc_attr(sanitize_key((string) $atts['sort'])) . '" data-online="' . esc_attr((string) $atts['online']) . '" data-pay-at-clinic="' . esc_attr((string) $atts['pay_at_clinic']) . '" data-available-only="' . esc_attr(in_array((string) $atts['available_only'], array('1', 'yes', 'true'), true) ? '1' : '0') . '" data-layout="' . esc_attr($layout) . '" dir="rtl"></div>';
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
        $excerpt = $post_id ? get_the_excerpt($post_id) : '';
        if (!$excerpt && $post_id) {
            $excerpt = wp_trim_words(wp_strip_all_tags((string) get_post_field('post_content', $post_id)), 24);
        }
        $clinic_name = (string) ($doctor['clinic_name'] ?? '');
        $address = !empty($doctor['clinic_address']) ? wp_trim_words((string) $doctor['clinic_address'], 14) : '';
        $medical_code = (string) ($doctor['medical_system_number'] ?? '');
        $rating_summary = self::doctor_rating_summary($post_id);
        $doctor_archive_url = get_post_type_archive_link('saas_doctors') ?: add_query_arg('post_type', 'saas_doctors', home_url('/'));
        $specialty_url = !empty($doctor['specialty_id'])
            ? Post_Types::specialty_url((int) $doctor['specialty_id'])
            : $doctor_archive_url;

        ob_start();
        ?>
        <article class="webtanan-booking webtanan-sample-ui doctor-card" dir="rtl">
            <div class="doc-header">
                <a class="doc-avatar" href="<?php echo esc_url($permalink); ?>" aria-label="<?php echo esc_attr($title); ?>">
                    <?php if ($image_url) : ?>
                        <img src="<?php echo esc_url($image_url); ?>" alt="<?php echo esc_attr($title); ?>" loading="lazy">
                    <?php else : ?>
                        <i class="fas fa-user-md" aria-hidden="true"></i>
                    <?php endif; ?>
                </a>
                <div class="doc-info">
                    <h3>
                        <a href="<?php echo esc_url($permalink); ?>"><?php echo esc_html($title); ?></a>
                        <?php if (!empty($doctor['is_verified'])) : ?>
                            <span class="wb-verified-mark" title="<?php esc_attr_e('پزشک تاییدشده', 'webtanan-booking'); ?>" aria-label="<?php esc_attr_e('پزشک تاییدشده', 'webtanan-booking'); ?>"><i class="fas fa-check" aria-hidden="true"></i></span>
                        <?php endif; ?>
                    </h3>
                    <?php if (!empty($doctor['specialty_name'])) : ?>
                        <a class="specialty wb-specialty-link" href="<?php echo esc_url($specialty_url); ?>"><?php echo esc_html($doctor['specialty_name']); ?></a>
                    <?php endif; ?>
                    <div class="rating"><i class="fas fa-star" aria-hidden="true"></i><span><?php echo $rating_summary['count'] > 0 ? esc_html(sprintf(__('%1$s (%2$s نظر)', 'webtanan-booking'), number_format_i18n($rating_summary['rating'], 1), number_format_i18n($rating_summary['count']))) : esc_html__('بدون نظر', 'webtanan-booking'); ?></span></div>
                </div>
            </div>
            <div class="doc-meta">
                <?php if ($address) : ?><p><i class="fas fa-map-marker-alt" aria-hidden="true"></i><?php echo esc_html($address); ?></p><?php endif; ?>
                <?php if ($clinic_name) : ?><p><i class="fas fa-hospital" aria-hidden="true"></i><?php echo esc_html($clinic_name); ?></p><?php endif; ?>
                <?php if ($medical_code) : ?><p><i class="fas fa-id-badge" aria-hidden="true"></i><?php echo esc_html(sprintf(__('کد نظام پزشکی: %s', 'webtanan-booking'), $medical_code)); ?></p><?php endif; ?>
                <div class="wb-next-available-line"><i class="far fa-clock" aria-hidden="true"></i><div class="webtanan-next-available" data-webtanan-widget="next-available" data-doctor-id="<?php echo esc_attr((string) $doctor_id); ?>"></div></div>
            </div>
            <a class="btn btn-primary" href="<?php echo esc_url($permalink . '#booking'); ?>"><?php esc_html_e('رزرو نوبت', 'webtanan-booking'); ?></a>
            <a class="doctor-profile-link" href="<?php echo esc_url($permalink); ?>"><?php esc_html_e('مشاهده پروفایل', 'webtanan-booking'); ?></a>
        </article>
        <?php

        return (string) ob_get_clean();
    }

    public static function specialty_list_shortcode($atts = array()): string {
        global $wpdb;

        self::enqueue();
        $homepage = (array) (DB::get_settings()['homepage'] ?? array());
        $atts = shortcode_atts(array('show_count' => 'yes', 'show_icon' => 'yes', 'layout' => ($homepage['specialty_layout'] ?? 'top'), 'columns' => ($homepage['specialty_columns'] ?? 4), 'limit' => ($homepage['specialty_limit'] ?? 8)), $atts, 'webtanan_booking_specialty_list');
        $show_count = 'no' !== strtolower((string) $atts['show_count']);
        $show_icon = 'no' !== strtolower((string) $atts['show_icon']);
        $layout = in_array((string) $atts['layout'], array('top', 'right', 'left'), true) ? (string) $atts['layout'] : 'top';
        $columns = min(6, max(2, absint($atts['columns'])));
        $limit = min(50, max(1, absint($atts['limit'])));
        $archive_url = get_post_type_archive_link('saas_doctors') ?: home_url('/');
        $specialties = $wpdb->get_results(
            $wpdb->prepare('SELECT s.*, COUNT(d.id) AS doctor_count
            FROM ' . DB::table('specialties') . ' s
            LEFT JOIN ' . DB::table('doctors') . ' d ON d.specialty_id = s.id AND d.is_active = 1 AND d.is_verified = 1
            WHERE s.is_active = 1
            GROUP BY s.id
            ORDER BY s.sort_order ASC, s.name ASC
            LIMIT %d', $limit),
            ARRAY_A
        );

        ob_start();
        ?>
        <div class="webtanan-booking webtanan-specialty-list wb-specialty-layout-<?php echo esc_attr($layout); ?> wb-specialty-columns-<?php echo esc_attr((string) $columns); ?>" dir="rtl">
            <?php if (!$specialties) : ?>
                <div class="webtanan-panel"><?php esc_html_e('تخصص فعالی ثبت نشده است.', 'webtanan-booking'); ?></div>
            <?php endif; ?>
            <?php foreach ($specialties as $specialty) : ?>
                <a class="webtanan-specialty-chip" href="<?php echo esc_url(Post_Types::specialty_url((int) $specialty['id'])); ?>">
                    <?php if ($show_icon && !empty($specialty['icon_attachment_id'])) : ?><?php echo wp_get_attachment_image((int) $specialty['icon_attachment_id'], 'thumbnail', false, array('class' => 'webtanan-specialty-icon')); ?><?php endif; ?>
                    <span><?php echo esc_html($specialty['name']); ?></span>
                    <?php if ($show_count) : ?><small><?php echo esc_html(number_format_i18n((int) $specialty['doctor_count'])); ?></small><?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>
        <?php

        return (string) ob_get_clean();
    }

    public static function doctors_archive_shortcode($atts = array()): string {
        return self::doctors_archive_shell_shortcode($atts);
    }

    private static function doctors_archive_shell_shortcode($atts = array()): string {
        self::enqueue();
        $atts = shortcode_atts(
            array(
                'per_page' => 50,
                'search' => '',
                'specialty_id' => 0,
                'province_id' => 0,
                'city_id' => 0,
                'payment_filter' => '',
                'layout' => 'grid',
            ),
            $atts,
            'webtanan_booking_doctors_archive'
        );

        return self::doctors_archive_embed_markup($atts);
    }

    private static function legacy_doctors_archive_markup(array $atts): string {
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
                <strong><?php esc_html_e('گرفتن نوبت', 'webtanan-booking'); ?></strong>
                <span><?php esc_html_e('زمان‌های آزاد پزشک', 'webtanan-booking'); ?></span>
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
                <input type="tel" name="patient_mobile" placeholder="<?php esc_attr_e('شماره موبایل جهت دریافت پیامک نوبت', 'webtanan-booking'); ?>">
                <select name="gateway" class="webtanan-gateway-select" aria-label="<?php esc_attr_e('درگاه پرداخت', 'webtanan-booking'); ?>"></select>
                <button type="submit" class="webtanan-button webtanan-button-primary"><?php esc_html_e('نگه‌داشتن نوبت و ادامه پرداخت', 'webtanan-booking'); ?></button>
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
        <div class="webtanan-booking webtanan-sample-ui webtanan-app-shell webtanan-patient-panel sample-dashboard-shell" data-webtanan-widget="patient-panel" dir="rtl">
                <?php echo Sidebar_Menu::render('patient', 'patient-overview', array('id' => 'patient-sidebar')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                <main class="main-content wb-main">
                    <button class="wb-dashboard-mobile-menu wb-sample-sidebar-toggle" type="button" aria-label="<?php esc_attr_e('باز کردن منو', 'webtanan-booking'); ?>"><i class="fas fa-bars" aria-hidden="true"></i><?php esc_html_e('منوی پنل', 'webtanan-booking'); ?></button>
                    <section class="wb-content" aria-live="polite"></section>
                    <div class="footer-bar">&copy; <?php echo esc_html(wp_date('Y')); ?> <strong><?php esc_html_e('وب‌تنان', 'webtanan-booking'); ?></strong> · <?php esc_html_e('خوشحالیم همراه شما هستیم', 'webtanan-booking'); ?></div>
                </main>
        </div>
        <?php

        return (string) ob_get_clean();
    }

    public static function doctor_dashboard_shortcode(): string {
        self::enqueue();

        ob_start();
        ?>
        <div class="webtanan-booking webtanan-sample-ui webtanan-app-shell webtanan-doctor-dashboard sample-dashboard-shell" data-webtanan-widget="doctor-dashboard" dir="rtl">
                <?php echo Sidebar_Menu::render(Sidebar_Menu::context_for_current_user(), 'today', array('id' => 'doctor-sidebar')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                <main class="main-content wb-main">
                    <button class="wb-dashboard-mobile-menu wb-sample-sidebar-toggle" type="button" aria-label="<?php esc_attr_e('باز کردن منو', 'webtanan-booking'); ?>"><i class="fas fa-bars" aria-hidden="true"></i><?php esc_html_e('منوی پنل', 'webtanan-booking'); ?></button>
                    <div class="sample-doctor-toolbar">
                        <select class="wb-doctor-select" aria-label="<?php esc_attr_e('پزشک', 'webtanan-booking'); ?>"></select>
                        <button type="button" class="btn btn-primary wb-open-walkin"><i class="fas fa-plus-circle" aria-hidden="true"></i><?php esc_html_e('ثبت نوبت حضوری', 'webtanan-booking'); ?></button>
                    </div>
                    <section class="wb-content" aria-live="polite"></section>
                    <div class="footer-bar">&copy; <?php echo esc_html(wp_date('Y')); ?> <strong><?php esc_html_e('وب‌تنان', 'webtanan-booking'); ?></strong> · <?php esc_html_e('آخرین بروزرسانی: امروز', 'webtanan-booking'); ?></div>
                </main>
                <div class="wb-modal" hidden>
                    <div class="wb-modal-panel" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e('ثبت نوبت', 'webtanan-booking'); ?>" tabindex="-1">
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

    private static function should_noindex_request(): bool {
        if (self::is_private_request()) {
            return true;
        }

        if (is_singular('saas_doctors') && !self::is_current_doctor_indexable()) {
            return true;
        }

        if (!is_post_type_archive('saas_doctors')) {
            return false;
        }

        foreach (array('search', 'doctor_search', 'city_id', 'province_id', 'available_only', 'payment_filter', 'online', 'pay_at_clinic') as $key) {
            if (!empty($_GET[$key])) {
                return true;
            }
        }

        return false;
    }

    private static function is_current_doctor_indexable(): bool {
        global $wpdb;

        $post_id = get_queried_object_id();
        if ($post_id <= 0 || 'publish' !== get_post_status($post_id)) {
            return false;
        }

        return (bool) $wpdb->get_var(
            $wpdb->prepare(
                'SELECT id FROM ' . DB::table('doctors') . ' WHERE post_id = %d AND is_active = 1 AND is_verified = 1 LIMIT 1',
                $post_id
            )
        );
    }

    private static function is_private_request(): bool {
        foreach (array('webtanan_auth_page', 'webtanan_payment_result', 'webtanan_resume_payment', 'webtanan_waiting_list', 'webtanan_survey') as $key) {
            if (get_query_var($key) || isset($_GET[$key])) {
                return true;
            }
        }

        if (!is_singular()) {
            return false;
        }

        $post = get_post();
        if (!$post || empty($post->post_content)) {
            return false;
        }

        foreach (array('webtanan_booking_auth', 'webtanan_booking_patient_panel', 'webtanan_booking_doctor_dashboard', 'webtanan_booking_resume_payment', 'webtanan_booking_waiting_list', 'webtanan_booking_survey') as $shortcode) {
            if (has_shortcode($post->post_content, $shortcode)) {
                return true;
            }
        }

        return false;
    }

    public static function doctor_rating_summary(int $post_id): array {
        if ($post_id <= 0) {
            return array('rating' => 0.0, 'count' => 0);
        }

        if (!array_key_exists($post_id, self::$doctor_rating_cache)) {
            self::prime_doctor_rating_cache(array($post_id));
        }

        return self::$doctor_rating_cache[$post_id] ?? array('rating' => 0.0, 'count' => 0);
    }

    public static function prime_doctor_rating_cache(array $post_ids): void {
        global $wpdb;

        $post_ids = array_values(array_unique(array_filter(array_map('absint', $post_ids))));
        $post_ids = array_values(array_filter($post_ids, static function (int $post_id): bool {
            return !array_key_exists($post_id, self::$doctor_rating_cache);
        }));
        if (!$post_ids) {
            return;
        }

        foreach ($post_ids as $post_id) {
            self::$doctor_rating_cache[$post_id] = array('rating' => 0.0, 'count' => 0);
        }

        $rating_meta_keys = array('rating', '_rating', 'webtanan_rating', '_webtanan_rating');
        $post_placeholders = implode(',', array_fill(0, count($post_ids), '%d'));
        $meta_placeholders = implode(',', array_fill(0, count($rating_meta_keys), '%s'));
        $sql = "SELECT r.post_id, AVG(r.rating) AS avg_rating, COUNT(*) AS rating_count
            FROM (
                SELECT c.comment_post_ID AS post_id, c.comment_ID, MAX(CAST(cm.meta_value AS DECIMAL(3,2))) AS rating
                FROM $wpdb->comments c
                INNER JOIN $wpdb->commentmeta cm ON cm.comment_id = c.comment_ID
                WHERE c.comment_post_ID IN ($post_placeholders)
                    AND c.comment_approved = '1'
                    AND cm.meta_key IN ($meta_placeholders)
                    AND cm.meta_value <> ''
                GROUP BY c.comment_post_ID, c.comment_ID
            ) r
            WHERE r.rating BETWEEN 1 AND 5
            GROUP BY r.post_id";
        $rows = $wpdb->get_results($wpdb->prepare($sql, array_merge($post_ids, $rating_meta_keys)), ARRAY_A);
        foreach ((array) $rows as $row) {
            $post_id = (int) ($row['post_id'] ?? 0);
            if ($post_id <= 0) {
                continue;
            }
            self::$doctor_rating_cache[$post_id] = array(
                'rating' => round((float) $row['avg_rating'], 2),
                'count' => (int) $row['rating_count'],
            );
        }
    }

    private static function doctor_aggregate_rating(int $post_id): array {
        $summary = self::doctor_rating_summary($post_id);
        if ($summary['count'] <= 0) {
            return array();
        }

        return array(
            '@type' => 'AggregateRating',
            'ratingValue' => $summary['rating'],
            'reviewCount' => $summary['count'],
            'bestRating' => 5,
            'worstRating' => 1,
        );
    }

    private static function compact_schema(array $schema): array {
        $compact = array();
        foreach ($schema as $key => $value) {
            if (is_array($value)) {
                $value = self::compact_schema($value);
                if (!$value) {
                    continue;
                }
            } elseif (null === $value || '' === $value) {
                continue;
            }

            $compact[$key] = $value;
        }

        return $compact;
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
