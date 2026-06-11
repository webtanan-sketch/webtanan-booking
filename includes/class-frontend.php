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
        add_action('wp_head', array(__CLASS__, 'render_doctor_schema_markup'), 20);
        add_filter('query_vars', array(__CLASS__, 'query_vars'));
        add_action('template_redirect', array(__CLASS__, 'maybe_render_payment_result_page'));
        add_action('template_redirect', array(__CLASS__, 'maybe_render_resume_payment_page'));
        add_action('template_redirect', array(__CLASS__, 'maybe_render_public_flow_page'));
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
        add_shortcode('webtanan_booking_resume_payment', array(__CLASS__, 'resume_payment_shortcode'));
        add_shortcode('webtanan_booking_waiting_list', array(__CLASS__, 'waiting_list_shortcode'));
        add_shortcode('webtanan_booking_survey', array(__CLASS__, 'survey_shortcode'));
    }

    public static function register_assets(): void {
        wp_register_style('webtanan-booking-frontend', WEBTANAN_BOOKING_URL . 'assets/css/frontend.css', array(), self::asset_version('assets/css/frontend.css'));
        wp_register_script('webtanan-booking-jalali', WEBTANAN_BOOKING_URL . 'assets/js/jalali-calendar.js', array(), self::asset_version('assets/js/jalali-calendar.js'), true);
        wp_register_script('webtanan-booking-frontend', WEBTANAN_BOOKING_URL . 'assets/js/frontend.js', array('webtanan-booking-jalali'), self::asset_version('assets/js/frontend.js'), true);
        wp_localize_script(
            'webtanan-booking-frontend',
            'WebtananBooking',
            array(
                'restRoot' => esc_url_raw(rest_url()),
                'restNamespace' => 'saas/v1',
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
        self::add_custom_font_inline_style('webtanan-booking-frontend');
    }

    public static function query_vars(array $vars): array {
        $vars[] = 'webtanan_payment_result';
        $vars[] = 'webtanan_resume_payment';
        $vars[] = 'webtanan_waiting_list';
        $vars[] = 'webtanan_survey';
        $vars[] = 'appointment_code';
        $vars[] = 'transaction_id';
        $vars[] = 'token';

        return $vars;
    }

    private static function asset_version(string $relative_path): string {
        $path = WEBTANAN_BOOKING_PATH . ltrim($relative_path, '/');
        if (is_readable($path)) {
            return WEBTANAN_BOOKING_VERSION . '.' . filemtime($path);
        }

        return WEBTANAN_BOOKING_VERSION;
    }

    private static function add_custom_font_inline_style(string $handle): void {
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
        $css .= '.webtanan-booking,.webtanan-booking *,.webtanan-dashboard-fullscreen-wrapper,.webtanan-dashboard-fullscreen-wrapper *{font-family:"' . esc_attr($font_family) . '",inherit;}';
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

    public static function maybe_noindex_private_pages(): void {
        if (get_query_var('webtanan_payment_result') || get_query_var('webtanan_resume_payment') || get_query_var('webtanan_waiting_list') || get_query_var('webtanan_survey')) {
            echo "<meta name=\"robots\" content=\"noindex,nofollow\">\n";
            return;
        }

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
        $booking_fee = Booking::doctor_booking_fee($doctor);
        $visit_price = (float) ($doctor['visit_price'] ?? 0);
        $display_price = $booking_fee > 0 ? $booking_fee : $visit_price;
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
            'priceRange' => $display_price > 0 ? number_format_i18n($display_price) . ' ' . __('تومان', 'webtanan-booking') : '',
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

                <div class="wb-factor-card">
                    <header>
                        <span><?php esc_html_e('فاکتور نوبت', 'webtanan-booking'); ?></span>
                        <strong><?php echo esc_html((string) ($appointment['appointment_code'] ?? $transaction['transaction_code'] ?? '')); ?></strong>
                    </header>
                    <dl class="wb-factor-list">
                        <?php if ($appointment) : ?>
                            <dt><?php esc_html_e('پزشک', 'webtanan-booking'); ?></dt>
                            <dd><?php echo esc_html($doctor_name); ?></dd>
                            <dt><?php esc_html_e('بیمار', 'webtanan-booking'); ?></dt>
                            <dd><?php echo esc_html(trim((string) $appointment['patient_first_name'] . ' ' . (string) $appointment['patient_last_name'])); ?></dd>
                            <dt><?php esc_html_e('تاریخ و ساعت', 'webtanan-booking'); ?></dt>
                            <dd><?php echo esc_html((string) $appointment['appointment_date'] . ' - ' . substr((string) $appointment['start_time'], 0, 5)); ?></dd>
                            <dt><?php esc_html_e('هزینه خدمات نوبت‌دهی', 'webtanan-booking'); ?></dt>
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
                    <?php echo self::resume_payment_shortcode(array('appointment_code' => (string) $appointment['appointment_code'], 'mobile' => (string) $appointment['patient_mobile'])); ?>
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
            'confirmed' => __('قطعی شده', 'webtanan-booking'),
            'wallet_refunded' => __('برگشت به کیف پول', 'webtanan-booking'),
            'wallet_charged' => __('شارژ موفق کیف پول', 'webtanan-booking'),
            'failed' => __('ناموفق', 'webtanan-booking'),
            'pending' => __('در حال بررسی', 'webtanan-booking'),
        );

        return $labels[$status] ?? $status;
    }

    public static function resume_payment_shortcode($atts = array()): string {
        self::enqueue();
        $atts = shortcode_atts(
            array(
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
        <section class="webtanan-booking webtanan-resume-payment" data-webtanan-widget="resume-payment" dir="rtl">
            <header>
                <span><?php esc_html_e('ادامه پرداخت', 'webtanan-booking'); ?></span>
                <h2><?php esc_html_e('پرداخت نوبت را کامل کن', 'webtanan-booking'); ?></h2>
                <p><?php esc_html_e('کد نوبت و موبایل را وارد کن؛ کد تایید می‌فرستیم و اگر ساعت هنوز قابل رزرو باشد، پرداخت ادامه پیدا می‌کند.', 'webtanan-booking'); ?></p>
            </header>
            <form class="wb-resume-form">
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

        return '<section class="webtanan-booking wb-public-flow wb-survey-widget" data-webtanan-widget="survey" data-code="' . esc_attr($code) . '" data-token="' . esc_attr($token) . '" dir="rtl"></section>';
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
                <label class="wb-search-field wb-search-field-query">
                    <span><?php esc_html_e('جستجو', 'webtanan-booking'); ?></span>
                    <input type="search" class="webtanan-doctor-search-input" name="search" value="<?php echo esc_attr($default_search); ?>" placeholder="<?php echo esc_attr((string) $atts['search_placeholder']); ?>">
                </label>
                <?php if ($show_filters) : ?>
                    <label class="wb-search-field">
                        <span><?php esc_html_e('تخصص', 'webtanan-booking'); ?></span>
                        <select class="webtanan-doctor-specialty-filter" name="specialty_id" aria-label="<?php esc_attr_e('تخصص', 'webtanan-booking'); ?>">
                            <option value="0"><?php esc_html_e('همه تخصص‌ها', 'webtanan-booking'); ?></option>
                            <?php foreach ($specialties as $specialty) : ?>
                                <option value="<?php echo esc_attr((string) $specialty['id']); ?>" <?php selected(absint($atts['specialty_id']), (int) $specialty['id']); ?>><?php echo esc_html($specialty['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="wb-search-field">
                        <span><?php esc_html_e('استان', 'webtanan-booking'); ?></span>
                        <select class="webtanan-doctor-province-filter" name="province_id" aria-label="<?php esc_attr_e('استان', 'webtanan-booking'); ?>">
                            <option value="0"><?php esc_html_e('همه استان‌ها', 'webtanan-booking'); ?></option>
                            <?php foreach ($provinces as $province) : ?>
                                <option value="<?php echo esc_attr((string) $province['id']); ?>" <?php selected(absint($atts['province_id']), (int) $province['id']); ?>><?php echo esc_html(sprintf(__('استان #%d', 'webtanan-booking'), (int) $province['id'])); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="wb-search-field">
                        <span><?php esc_html_e('شهر', 'webtanan-booking'); ?></span>
                        <select class="webtanan-doctor-city-filter" name="city_id" aria-label="<?php esc_attr_e('شهر', 'webtanan-booking'); ?>">
                            <option value="0"><?php esc_html_e('همه شهرها', 'webtanan-booking'); ?></option>
                            <?php foreach ($cities as $city) : ?>
                                <option value="<?php echo esc_attr((string) $city['id']); ?>" <?php selected(absint($atts['city_id']), (int) $city['id']); ?>><?php echo esc_html(sprintf(__('شهر #%d', 'webtanan-booking'), (int) $city['id'])); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="wb-search-field">
                        <span><?php esc_html_e('پرداخت', 'webtanan-booking'); ?></span>
                        <select class="webtanan-doctor-payment-filter" name="payment_filter" aria-label="<?php esc_attr_e('روش پرداخت', 'webtanan-booking'); ?>">
                            <option value="" <?php selected($payment_filter, ''); ?>><?php esc_html_e('همه روش‌های پرداخت', 'webtanan-booking'); ?></option>
                            <option value="online" <?php selected($payment_filter, 'online'); ?>><?php esc_html_e('پرداخت آنلاین', 'webtanan-booking'); ?></option>
                            <option value="clinic" <?php selected($payment_filter, 'clinic'); ?>><?php esc_html_e('پرداخت در مطب', 'webtanan-booking'); ?></option>
                        </select>
                    </label>
                    <?php if ($show_sort) : ?>
                        <label class="wb-search-field">
                            <span><?php esc_html_e('مرتب‌سازی', 'webtanan-booking'); ?></span>
                            <select class="webtanan-doctor-sort-filter" name="sort" aria-label="<?php esc_attr_e('مرتب‌سازی', 'webtanan-booking'); ?>">
                                <option value="first_available" <?php selected($sort ?: 'first_available', 'first_available'); ?>><?php esc_html_e('نزدیک‌ترین نوبت آزاد', 'webtanan-booking'); ?></option>
                                <option value="" <?php selected($sort, ''); ?>><?php esc_html_e('پیش‌فرض', 'webtanan-booking'); ?></option>
                            </select>
                        </label>
                    <?php endif; ?>
                <?php endif; ?>
                <button type="submit" class="webtanan-button webtanan-search-button wb-search-submit"><?php esc_html_e('جستجو', 'webtanan-booking'); ?></button>
            </form>
            <div class="webtanan-doctor-results" aria-live="polite"></div>
        </div>
        <?php

        return (string) ob_get_clean();
    }

    public static function doctor_list_shortcode($atts = array()): string {
        self::enqueue();
        $atts = shortcode_atts(array('per_page' => 50, 'specialty_id' => 0, 'city_id' => 0, 'province_id' => 0, 'payment_filter' => '', 'sort' => '', 'online' => '', 'pay_at_clinic' => '', 'layout' => 'grid'), $atts, 'webtanan_booking_doctor_list');
        $layout = 'list' === sanitize_key((string) $atts['layout']) ? 'list' : 'grid';

        return '<div class="webtanan-booking webtanan-doctor-list webtanan-doctor-list-container" data-webtanan-widget="doctor-list" data-per-page="' . esc_attr((string) absint($atts['per_page'])) . '" data-specialty-id="' . esc_attr((string) absint($atts['specialty_id'])) . '" data-city-id="' . esc_attr((string) absint($atts['city_id'])) . '" data-province-id="' . esc_attr((string) absint($atts['province_id'])) . '" data-payment-filter="' . esc_attr(sanitize_key((string) $atts['payment_filter'])) . '" data-sort="' . esc_attr(sanitize_key((string) $atts['sort'])) . '" data-online="' . esc_attr((string) $atts['online']) . '" data-pay-at-clinic="' . esc_attr((string) $atts['pay_at_clinic']) . '" data-layout="' . esc_attr($layout) . '" dir="rtl"></div>';
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
        <article class="webtanan-booking webtanan-public-doctor-card webtanan-doctor-card-shortcode wb-doctor-card" dir="rtl">
            <a class="webtanan-public-doctor-photo wb-doctor-card-photo" href="<?php echo esc_url($permalink); ?>" aria-label="<?php echo esc_attr($title); ?>">
                <?php if ($image_url) : ?>
                    <img src="<?php echo esc_url($image_url); ?>" alt="<?php echo esc_attr($title); ?>">
                <?php else : ?>
                    <span><?php echo esc_html(function_exists('mb_substr') ? mb_substr($title, 0, 1) : substr($title, 0, 1)); ?></span>
                <?php endif; ?>
            </a>
            <div class="webtanan-public-doctor-body wb-doctor-card-body">
                <div class="webtanan-doctor-badges wb-doctor-card-badges">
                    <?php if (!empty($doctor['is_verified'])) : ?><span class="wb-badge wb-badge-success"><?php esc_html_e('تاییدشده', 'webtanan-booking'); ?></span><?php endif; ?>
                    <?php if (!empty($doctor['allow_online_payment'])) : ?><span class="wb-badge wb-badge-info"><?php esc_html_e('پرداخت آنلاین', 'webtanan-booking'); ?></span><?php endif; ?>
                    <?php if (!empty($doctor['allow_pay_at_clinic'])) : ?><span class="wb-badge wb-badge-warning"><?php esc_html_e('پرداخت در مطب', 'webtanan-booking'); ?></span><?php endif; ?>
                </div>
                <h2 class="wb-doctor-card-title"><a href="<?php echo esc_url($permalink); ?>"><?php echo esc_html($title); ?></a></h2>
                <div class="wb-doctor-card-meta-row">
                    <?php if (!empty($doctor['specialty_name'])) : ?><p class="webtanan-meta wb-doctor-card-meta"><?php echo esc_html($doctor['specialty_name']); ?></p><?php endif; ?>
                    <?php if (!empty($doctor['clinic_address'])) : ?><p class="webtanan-meta wb-doctor-card-meta"><?php echo esc_html(wp_trim_words($doctor['clinic_address'], 18)); ?></p><?php endif; ?>
                </div>
                <div class="webtanan-public-fees wb-doctor-fees">
                    <span class="wb-doctor-fee"><?php esc_html_e('هزینه نوبت‌دهی:', 'webtanan-booking'); ?> <strong><?php echo esc_html(number_format_i18n($booking_fee)); ?></strong> <?php esc_html_e('تومان', 'webtanan-booking'); ?></span>
                    <?php if ($visit_price > 0) : ?><span class="wb-doctor-fee"><?php esc_html_e('ویزیت اعلامی:', 'webtanan-booking'); ?> <strong><?php echo esc_html(number_format_i18n($visit_price)); ?></strong> <?php esc_html_e('تومان', 'webtanan-booking'); ?></span><?php endif; ?>
                </div>
                <div class="webtanan-public-actions wb-doctor-card-actions">
                    <a class="webtanan-button webtanan-button-primary wb-btn wb-btn-primary" href="<?php echo esc_url($permalink); ?>"><?php esc_html_e('مشاهده و دریافت نوبت', 'webtanan-booking'); ?></a>
                    <div class="webtanan-next-available wb-next-available-wrap" data-webtanan-widget="next-available" data-doctor-id="<?php echo esc_attr((string) $doctor_id); ?>"></div>
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
        <div class="webtanan-dashboard-fullscreen-wrapper" dir="rtl">
            <div class="webtanan-booking webtanan-app-shell webtanan-patient-panel" data-webtanan-widget="patient-panel" dir="rtl">
                <aside class="wb-sidebar">
                    <div class="wb-brand">
                        <span class="wb-brand-mark">+</span>
                        <span><?php esc_html_e('پنل بیمار', 'webtanan-booking'); ?></span>
                    </div>
                    <nav class="wb-nav" aria-label="<?php esc_attr_e('ناوبری پنل بیمار', 'webtanan-booking'); ?>">
                        <button type="button" class="wb-nav-item is-active" data-wb-view="patient-appointments"><span><?php esc_html_e('نوبت‌های آینده', 'webtanan-booking'); ?></span></button>
                        <button type="button" class="wb-nav-item" data-wb-view="patient-history"><span><?php esc_html_e('سوابق نوبت', 'webtanan-booking'); ?></span></button>
                        <button type="button" class="wb-nav-item" data-wb-view="patient-records"><span><?php esc_html_e('پرونده پزشکی', 'webtanan-booking'); ?></span></button>
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
        </div>
        <?php

        return (string) ob_get_clean();
    }

    public static function doctor_dashboard_shortcode(): string {
        self::enqueue();

        ob_start();
        ?>
        <div class="webtanan-dashboard-fullscreen-wrapper" dir="rtl">
            <div class="webtanan-booking webtanan-app-shell webtanan-doctor-dashboard" data-webtanan-widget="doctor-dashboard" dir="rtl">
                <aside class="wb-sidebar">
                    <div class="wb-brand">
                        <span class="wb-brand-mark">+</span>
                        <span class="wb-brand-title"><?php esc_html_e('داشبورد مطب', 'webtanan-booking'); ?></span>
                    </div>
                    <nav class="wb-nav" aria-label="<?php esc_attr_e('ناوبری پنل مطب', 'webtanan-booking'); ?>">
                        <button type="button" class="wb-nav-item is-active" data-wb-view="today"><span><?php esc_html_e('پیشخوان امروز', 'webtanan-booking'); ?></span></button>
                        <button type="button" class="wb-nav-item" data-wb-view="calendar"><span><?php esc_html_e('تقویم نوبت‌دهی', 'webtanan-booking'); ?></span></button>
                        <button type="button" class="wb-nav-item" data-wb-view="patients"><span><?php esc_html_e('لیست بیماران', 'webtanan-booking'); ?></span></button>
                        <button type="button" class="wb-nav-item" data-wb-view="schedule"><span><?php esc_html_e('برنامه کاری', 'webtanan-booking'); ?></span></button>
                        <button type="button" class="wb-nav-item" data-wb-view="exceptions"><span><?php esc_html_e('روزهای خاص', 'webtanan-booking'); ?></span></button>
                        <button type="button" class="wb-nav-item" data-wb-view="records"><span><?php esc_html_e('پرونده بیماران', 'webtanan-booking'); ?></span></button>
                        <button type="button" class="wb-nav-item" data-wb-view="wallet"><span><?php esc_html_e('کیف پول و مالی', 'webtanan-booking'); ?></span></button>
                        <button type="button" class="wb-nav-item" data-wb-view="profile"><span><?php esc_html_e('پروفایل من', 'webtanan-booking'); ?></span></button>
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
        </div>
        <?php

        return (string) ob_get_clean();
    }

    private static function doctor_aggregate_rating(int $post_id): array {
        global $wpdb;

        $rating_meta_keys = array('rating', '_rating', 'webtanan_rating', '_webtanan_rating');
        $placeholders = implode(',', array_fill(0, count($rating_meta_keys), '%s'));
        $sql = "SELECT AVG(r.rating) AS avg_rating, COUNT(*) AS rating_count
            FROM (
                SELECT c.comment_ID, MAX(CAST(cm.meta_value AS DECIMAL(3,2))) AS rating
                FROM $wpdb->comments c
                INNER JOIN $wpdb->commentmeta cm ON cm.comment_id = c.comment_ID
                WHERE c.comment_post_ID = %d
                    AND c.comment_approved = '1'
                    AND cm.meta_key IN ($placeholders)
                    AND cm.meta_value <> ''
                GROUP BY c.comment_ID
            ) r
            WHERE r.rating BETWEEN 1 AND 5";
        $params = array_merge(array($post_id), $rating_meta_keys);
        $row = $wpdb->get_row($wpdb->prepare($sql, $params), ARRAY_A);

        if (!$row || (int) ($row['rating_count'] ?? 0) <= 0) {
            return array();
        }

        return array(
            '@type' => 'AggregateRating',
            'ratingValue' => round((float) $row['avg_rating'], 2),
            'reviewCount' => (int) $row['rating_count'],
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
