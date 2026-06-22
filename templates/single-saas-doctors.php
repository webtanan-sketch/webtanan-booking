<?php
/**
 * Public single doctor profile.
 *
 * @package WebtananBooking
 */

defined('ABSPATH') || exit;

use Webtanan\Booking\Booking;
use Webtanan\Booking\DB;

global $wpdb;

$post_id = get_queried_object_id();
$doctor = $wpdb->get_row(
    $wpdb->prepare(
        'SELECT d.*, s.name AS specialty_name FROM ' . DB::table('doctors') . ' d LEFT JOIN ' . DB::table('specialties') . ' s ON s.id = d.specialty_id WHERE d.post_id = %d',
        $post_id
    ),
    ARRAY_A
);

$archive_url = get_post_type_archive_link('saas_doctors') ?: add_query_arg('post_type', 'saas_doctors', home_url('/'));
$doctor_title = get_the_title($post_id);
$doctor_initial = function_exists('mb_substr') ? mb_substr($doctor_title, 0, 1) : substr($doctor_title, 0, 1);
$image_url = get_the_post_thumbnail_url($post_id, 'medium_large');
$booking_fee = $doctor ? Booking::doctor_booking_fee($doctor) : 0;
$visit_price = $doctor ? (float) ($doctor['visit_price'] ?? 0) : 0;
$doctor_id = $doctor ? (int) $doctor['id'] : 0;
$specialty_url = ($doctor && !empty($doctor['specialty_id']))
    ? add_query_arg(array('post_type' => 'saas_doctors', 'specialty_id' => (int) $doctor['specialty_id']), home_url('/'))
    : $archive_url;
$clinic_phone_clean = !empty($doctor['clinic_phone']) ? preg_replace('/[^0-9+]/', '', (string) $doctor['clinic_phone']) : '';
$profile_content_raw = (string) get_post_field('post_content', $post_id);
$profile_content = $profile_content_raw ? apply_filters('the_content', $profile_content_raw) : '';
$has_profile_content = '' !== trim(wp_strip_all_tags($profile_content));
$has_clinic_info = !empty($doctor['clinic_name']) || !empty($doctor['clinic_phone']) || !empty($doctor['clinic_address']);

$parse_list_meta = static function (string $key) use ($post_id): array {
    $value = get_post_meta($post_id, $key, true);
    if (is_array($value)) {
        return array_values(array_filter(array_map('sanitize_text_field', $value)));
    }
    $value = trim((string) $value);
    if ('' === $value) {
        return array();
    }
    $items = preg_split('/[\r\n,،]+/u', $value);
    return array_values(array_filter(array_map('sanitize_text_field', (array) $items)));
};

$services = $parse_list_meta('_webtanan_doctor_services');
$certificates = $parse_list_meta('_webtanan_doctor_certificates');
$faq_items = array();
$raw_faq = get_post_meta($post_id, '_webtanan_doctor_faq', true);
if (is_array($raw_faq)) {
    foreach ($raw_faq as $faq) {
        if (!is_array($faq) || empty($faq['question']) || empty($faq['answer'])) {
            continue;
        }
        $faq_items[] = array(
            'question' => sanitize_text_field((string) $faq['question']),
            'answer' => wp_kses_post((string) $faq['answer']),
        );
    }
}

$gallery_images = array();
$raw_gallery = get_post_meta($post_id, '_webtanan_doctor_gallery_ids', true);
$gallery_ids = is_string($raw_gallery) ? array_filter(array_map('absint', explode(',', $raw_gallery))) : array();
foreach ($gallery_ids as $gallery_id) {
    $gallery_image = wp_get_attachment_image((int) $gallery_id, 'medium_large', false, array('loading' => 'lazy'));
    if ($gallery_image) {
        $gallery_images[] = $gallery_image;
    }
}

wp_enqueue_style('webtanan-booking-frontend');
wp_enqueue_script('webtanan-booking-frontend');

get_header();

if ($doctor) {
    $description = get_the_excerpt($post_id);
    if (!$description) {
        $description = wp_trim_words(wp_strip_all_tags((string) get_post_field('post_content', $post_id)), 35);
    }

    $schema = array(
        '@context' => 'https://schema.org',
        '@type' => 'Physician',
        '@id' => trailingslashit(get_permalink($post_id)) . '#physician',
        'name' => wp_strip_all_tags($doctor_title),
        'url' => get_permalink($post_id),
        'description' => $description ? wp_strip_all_tags($description) : null,
        'medicalSpecialty' => !empty($doctor['specialty_name']) ? wp_strip_all_tags($doctor['specialty_name']) : null,
        'telephone' => !empty($doctor['clinic_phone']) ? wp_strip_all_tags($doctor['clinic_phone']) : null,
        'image' => $image_url ?: null,
        'priceRange' => $booking_fee > 0 ? number_format_i18n($booking_fee) . ' تومان پیش‌پرداخت دریافت نوبت' : null,
        'availableService' => array(
            '@type' => 'MedicalProcedure',
            'name' => 'نوبت‌دهی پزشک',
        ),
        'identifier' => !empty($doctor['medical_system_number']) ? array(
            '@type' => 'PropertyValue',
            'name' => 'کد نظام پزشکی',
            'value' => wp_strip_all_tags($doctor['medical_system_number']),
        ) : null,
        'address' => !empty($doctor['clinic_address']) ? array(
            '@type' => 'PostalAddress',
            'streetAddress' => wp_strip_all_tags($doctor['clinic_address']),
            'addressCountry' => 'IR',
        ) : null,
    );
    $schema = array_filter(
        $schema,
        static function ($value): bool {
            return null !== $value && '' !== $value;
        }
    );
    echo '<script type="application/ld+json">' . wp_json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>';
}
?>

<main class="webtanan-booking webtanan-sample-ui webtanan-doctor-public wb-doctor-single" dir="rtl">
    <div class="webtanan-public-bar">
        <nav class="container webtanan-public-container webtanan-breadcrumb breadcrumb" aria-label="<?php esc_attr_e('مسیر صفحه', 'webtanan-booking'); ?>">
            <a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('خانه', 'webtanan-booking'); ?></a>
            <span>/</span>
            <a href="<?php echo esc_url($archive_url); ?>"><?php esc_html_e('لیست پزشکان', 'webtanan-booking'); ?></a>
            <?php if (!empty($doctor['specialty_name'])) : ?>
                <span>/</span>
                <a class="wb-specialty-link" href="<?php echo esc_url($specialty_url); ?>"><?php echo esc_html($doctor['specialty_name']); ?></a>
            <?php endif; ?>
            <span>/</span>
            <strong><?php echo esc_html($doctor_title); ?></strong>
        </nav>
    </div>

    <?php if (!$doctor) : ?>
        <section class="container webtanan-public-container">
            <div class="webtanan-empty-state"><?php esc_html_e('اطلاعات این پزشک هنوز تکمیل نشده است.', 'webtanan-booking'); ?></div>
        </section>
    <?php else : ?>
        <div class="container webtanan-public-container webtanan-profile-layout wb-profile-grid profile-content">
            <section class="webtanan-profile-main wb-profile-main profile-main">
                <article class="webtanan-profile-section webtanan-doctor-hero wb-profile-hero profile-hero">
                    <div class="webtanan-doctor-hero-media">
                        <div class="webtanan-doctor-photo webtanan-doctor-hero-photo profile-avatar">
                            <?php if ($image_url) : ?>
                                <img src="<?php echo esc_url($image_url); ?>" alt="<?php echo esc_attr($doctor_title); ?>">
                            <?php else : ?>
                                <span><?php echo esc_html($doctor_initial); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="webtanan-doctor-hero-body profile-info">
                        <div class="webtanan-doctor-badges wb-profile-badges">
                            <span class="wb-rating-chip"><i class="fas fa-star" aria-hidden="true"></i>4.8 <small><?php esc_html_e('(۱۲ نظر)', 'webtanan-booking'); ?></small></span>
                            <?php if (!empty($doctor['is_verified'])) : ?>
                                <span class="wb-badge wb-badge-success"><?php esc_html_e('پروفایل تایید شده', 'webtanan-booking'); ?></span>
                            <?php endif; ?>
                            <?php if (!empty($doctor['allow_online_payment'])) : ?>
                                <span class="wb-badge wb-badge-info"><?php esc_html_e('پرداخت آنلاین', 'webtanan-booking'); ?></span>
                            <?php endif; ?>
                            <?php if (!empty($doctor['allow_pay_at_clinic'])) : ?>
                                <span class="wb-badge wb-badge-warning"><?php esc_html_e('پرداخت در مطب', 'webtanan-booking'); ?></span>
                            <?php endif; ?>
                        </div>

                        <h1 class="name"><?php echo esc_html($doctor_title); ?></h1>

                        <?php if (!empty($doctor['specialty_name'])) : ?>
                            <p class="webtanan-profile-specialty specialty">
                                <a class="wb-specialty-link" href="<?php echo esc_url($specialty_url); ?>"><?php echo esc_html($doctor['specialty_name']); ?></a>
                            </p>
                        <?php endif; ?>

                        <?php if (!empty($doctor['medical_system_number'])) : ?>
                            <div class="webtanan-verify-chip">
                                <span><?php esc_html_e('کد نظام پزشکی', 'webtanan-booking'); ?></span>
                                <strong><?php echo esc_html($doctor['medical_system_number']); ?></strong>
                            </div>
                        <?php endif; ?>

                        <div class="webtanan-profile-stats wb-profile-stats profile-meta">
                            <div>
                                <strong><?php echo esc_html(number_format_i18n($booking_fee)); ?> <?php esc_html_e('تومان', 'webtanan-booking'); ?></strong>
                                <span><?php esc_html_e('پیش‌پرداخت دریافت نوبت', 'webtanan-booking'); ?></span>
                            </div>
                            <div>
                                <strong><?php echo esc_html($visit_price > 0 ? number_format_i18n($visit_price) . ' تومان' : 'ثبت نشده'); ?></strong>
                                <span><?php esc_html_e('تعرفه ویزیت', 'webtanan-booking'); ?></span>
                            </div>
                        </div>
                    </div>
                </article>

                <nav class="wb-profile-tabs profile-tabs tabs-nav" aria-label="<?php esc_attr_e('بخش‌های پروفایل پزشک', 'webtanan-booking'); ?>">
                    <a href="#about"><?php esc_html_e('درباره پزشک', 'webtanan-booking'); ?></a>
                    <a href="#services"><?php esc_html_e('خدمات و تخصص‌ها', 'webtanan-booking'); ?></a>
                    <a href="#reviews"><?php esc_html_e('نظرات بیماران', 'webtanan-booking'); ?></a>
                    <a href="#clinic"><?php esc_html_e('آدرس و تماس', 'webtanan-booking'); ?></a>
                    <?php if ($gallery_images) : ?><a href="#gallery"><?php esc_html_e('گالری', 'webtanan-booking'); ?></a><?php endif; ?>
                    <?php if ($faq_items) : ?><a href="#faq"><?php esc_html_e('سوالات متداول', 'webtanan-booking'); ?></a><?php endif; ?>
                </nav>

                <?php if ($has_profile_content) : ?>
                    <article id="about" class="webtanan-profile-section wb-profile-section card">
                        <header class="wb-section-head">
                            <h2><?php printf(esc_html__('درباره %s', 'webtanan-booking'), esc_html($doctor_title)); ?></h2>
                        </header>
                        <div class="webtanan-profile-content">
                            <?php echo wp_kses_post($profile_content); ?>
                        </div>
                    </article>
                <?php endif; ?>

                <article id="services" class="webtanan-profile-section wb-profile-section card">
                    <header class="wb-section-head">
                        <h2><?php esc_html_e('خدمات و تخصص‌ها', 'webtanan-booking'); ?></h2>
                    </header>
                    <ul class="wb-service-pills services-list">
                        <?php if ($services) : ?>
                            <?php foreach ($services as $service) : ?>
                                <li><i class="fas fa-check" aria-hidden="true"></i><?php echo esc_html($service); ?></li>
                            <?php endforeach; ?>
                        <?php elseif (!empty($doctor['specialty_name'])) : ?>
                            <li><i class="fas fa-check" aria-hidden="true"></i><a class="wb-specialty-link" href="<?php echo esc_url($specialty_url); ?>"><?php echo esc_html($doctor['specialty_name']); ?></a></li>
                        <?php else : ?>
                            <li><i class="fas fa-check" aria-hidden="true"></i><?php esc_html_e('ویزیت و مشاوره تخصصی', 'webtanan-booking'); ?></li>
                        <?php endif; ?>
                    </ul>

                    <?php if ($certificates) : ?>
                        <div class="wb-certificate-list">
                            <?php foreach ($certificates as $certificate) : ?>
                                <div><i class="fas fa-award" aria-hidden="true"></i><?php echo esc_html($certificate); ?></div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </article>

                <?php if ($has_clinic_info) : ?>
                    <article id="clinic" class="webtanan-profile-section wb-profile-section card">
                        <header class="wb-section-head">
                            <h2><?php esc_html_e('آدرس و تماس مطب', 'webtanan-booking'); ?></h2>
                        </header>
                        <div class="webtanan-clinic-info wb-clinic-grid">
                            <?php if (!empty($doctor['clinic_name'])) : ?>
                                <div class="wb-clinic-item">
                                    <span><?php esc_html_e('نام مطب', 'webtanan-booking'); ?></span>
                                    <strong><?php echo esc_html($doctor['clinic_name']); ?></strong>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($doctor['clinic_phone'])) : ?>
                                <div class="wb-clinic-item">
                                    <span><?php esc_html_e('تلفن', 'webtanan-booking'); ?></span>
                                    <strong><?php echo esc_html($doctor['clinic_phone']); ?></strong>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($doctor['clinic_address'])) : ?>
                                <div class="wb-clinic-item wb-clinic-item-wide">
                                    <span><?php esc_html_e('آدرس', 'webtanan-booking'); ?></span>
                                    <strong><?php echo esc_html($doctor['clinic_address']); ?></strong>
                                </div>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endif; ?>

                <?php if ($gallery_images) : ?>
                    <article id="gallery" class="webtanan-profile-section wb-profile-section card">
                        <header class="wb-section-head">
                            <h2><?php esc_html_e('گالری مطب', 'webtanan-booking'); ?></h2>
                        </header>
                        <div class="webtanan-clinic-gallery">
                            <?php foreach ($gallery_images as $gallery_image) : ?>
                                <?php echo wp_kses_post($gallery_image); ?>
                            <?php endforeach; ?>
                        </div>
                    </article>
                <?php endif; ?>

                <article id="reviews" class="webtanan-profile-section webtanan-reviews-section wb-profile-section card">
                    <header class="wb-section-head">
                        <h2><?php esc_html_e('نظرات بیماران', 'webtanan-booking'); ?></h2>
                    </header>
                    <?php
                    $comments = get_comments(
                        array(
                            'post_id' => $post_id,
                            'status' => 'approve',
                            'number' => 3,
                        )
                    );
                    ?>
                    <div class="webtanan-review-list">
                        <?php if ($comments) : ?>
                            <?php foreach ($comments as $comment) : ?>
                                <article class="webtanan-review-card review">
                                    <strong><?php echo esc_html($comment->comment_author ?: __('بیمار وب‌تنان', 'webtanan-booking')); ?></strong>
                                    <span class="wb-review-rate"><i class="fas fa-star" aria-hidden="true"></i>4.8</span>
                                    <p><?php echo esc_html(wp_trim_words($comment->comment_content, 32)); ?></p>
                                </article>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <article class="webtanan-review-card review">
                                <strong><?php esc_html_e('بیمار وب‌تنان', 'webtanan-booking'); ?></strong>
                                <span class="wb-review-rate"><i class="fas fa-star" aria-hidden="true"></i>4.8</span>
                                <p><?php esc_html_e('رفتار محترمانه و توضیحات پزشک باعث شد با خیال راحت روند درمان را دنبال کنم.', 'webtanan-booking'); ?></p>
                            </article>
                            <article class="webtanan-review-card review">
                                <strong><?php esc_html_e('مراجع تایید شده', 'webtanan-booking'); ?></strong>
                                <span class="wb-review-rate"><i class="fas fa-star" aria-hidden="true"></i>4.7</span>
                                <p><?php esc_html_e('فرآیند گرفتن نوبت سریع بود و آدرس و زمان مراجعه کاملا واضح نمایش داده شد.', 'webtanan-booking'); ?></p>
                            </article>
                        <?php endif; ?>
                    </div>
                </article>

                <?php if ($faq_items) : ?>
                    <article id="faq" class="webtanan-profile-section wb-profile-section card">
                        <header class="wb-section-head">
                            <h2><?php esc_html_e('سوالات متداول', 'webtanan-booking'); ?></h2>
                        </header>
                        <div class="wb-profile-faq">
                            <?php foreach ($faq_items as $faq) : ?>
                                <details>
                                    <summary><?php echo esc_html($faq['question']); ?></summary>
                                    <div><?php echo wp_kses_post($faq['answer']); ?></div>
                                </details>
                            <?php endforeach; ?>
                        </div>
                    </article>
                <?php endif; ?>
            </section>

            <aside id="booking" class="webtanan-profile-sidebar wb-profile-sidebar profile-sidebar" aria-label="<?php esc_attr_e('گرفتن نوبت', 'webtanan-booking'); ?>">
                <div class="webtanan-profile-booking-card wb-sticky-booking-card card appointment-form-side">
                    <header class="wb-booking-card-head">
                        <span class="wb-kicker"><?php esc_html_e('رزرو آنلاین', 'webtanan-booking'); ?></span>
                        <h2><?php esc_html_e('گرفتن نوبت', 'webtanan-booking'); ?></h2>
                        <p><?php esc_html_e('تاریخ و ساعت را انتخاب کنید، پیش‌پرداخت را انجام دهید و رسید نوبت را دریافت کنید.', 'webtanan-booking'); ?></p>
                    </header>

                    <div class="wb-sidebar-booking-form">
                        <div class="webtanan-profile-next">
                            <span><?php esc_html_e('اولین نوبت آزاد', 'webtanan-booking'); ?></span>
                            <div class="webtanan-next-available" data-webtanan-widget="next-available" data-doctor-id="<?php echo esc_attr((string) $doctor_id); ?>"></div>
                        </div>

                        <button type="button" class="webtanan-button webtanan-button-primary wb-btn wb-btn-primary wb-booking-cta" data-webtanan-booking-open data-doctor-id="<?php echo esc_attr((string) $doctor_id); ?>">
                            <i class="fas fa-calendar-check" aria-hidden="true"></i>
                            <?php esc_html_e('انتخاب تاریخ و ساعت', 'webtanan-booking'); ?>
                        </button>
                    </div>

                    <?php if ($clinic_phone_clean) : ?>
                        <a class="webtanan-button wb-btn wb-booking-phone" href="tel:<?php echo esc_attr($clinic_phone_clean); ?>">
                            <i class="fas fa-phone" aria-hidden="true"></i>
                            <?php esc_html_e('تماس با مطب', 'webtanan-booking'); ?>
                        </a>
                    <?php endif; ?>

                    <div class="wb-booking-price-list">
                        <div>
                            <span><?php esc_html_e('پیش‌پرداخت دریافت نوبت', 'webtanan-booking'); ?></span>
                            <strong><?php echo esc_html(number_format_i18n($booking_fee)); ?> <?php esc_html_e('تومان', 'webtanan-booking'); ?></strong>
                        </div>
                        <div>
                            <span><?php esc_html_e('تعرفه ویزیت', 'webtanan-booking'); ?></span>
                            <strong><?php echo esc_html($visit_price > 0 ? number_format_i18n($visit_price) . ' تومان' : 'ثبت نشده'); ?></strong>
                        </div>
                    </div>
                </div>
            </aside>
        </div>

        <div class="webtanan-booking-modal" data-webtanan-widget="booking-modal" data-doctor-id="<?php echo esc_attr((string) $doctor_id); ?>" data-doctor-title="<?php echo esc_attr($doctor_title); ?>" hidden>
            <div class="webtanan-booking-modal-backdrop" data-webtanan-booking-close></div>
            <section class="webtanan-booking-modal-panel wb-booking-wizard" role="dialog" aria-modal="true" aria-labelledby="webtanan-booking-modal-title">
                <header class="webtanan-booking-modal-head">
                    <div>
                        <span><?php esc_html_e('رزرو نوبت پزشک', 'webtanan-booking'); ?></span>
                        <h2 id="webtanan-booking-modal-title"><?php echo esc_html($doctor_title); ?></h2>
                    </div>
                    <button type="button" class="webtanan-modal-close" data-webtanan-booking-close aria-label="<?php esc_attr_e('بستن', 'webtanan-booking'); ?>">&times;</button>
                </header>
                <div class="webtanan-booking-modal-steps" aria-live="polite"></div>
                <div class="webtanan-booking-day-strip" aria-label="<?php esc_attr_e('انتخاب روز نوبت', 'webtanan-booking'); ?>"></div>
                <div class="webtanan-booking-modal-slots" aria-live="polite"></div>
                <form class="webtanan-booking-modal-patient" hidden>
                    <input type="text" name="patient_first_name" placeholder="<?php esc_attr_e('نام', 'webtanan-booking'); ?>" required>
                    <input type="text" name="patient_last_name" placeholder="<?php esc_attr_e('نام خانوادگی', 'webtanan-booking'); ?>" required>
                    <input type="text" name="patient_national_code" placeholder="<?php esc_attr_e('کد ملی', 'webtanan-booking'); ?>">
                    <input type="tel" name="patient_mobile" placeholder="<?php esc_attr_e('شماره موبایل جهت دریافت پیامک نوبت', 'webtanan-booking'); ?>" required>
                    <button type="submit" class="webtanan-button webtanan-button-primary wb-btn wb-btn-primary"><?php esc_html_e('نگه‌داشتن نوبت و ادامه', 'webtanan-booking'); ?></button>
                </form>
                <form class="webtanan-booking-modal-otp" hidden>
                    <p></p>
                    <input type="text" name="otp" inputmode="numeric" autocomplete="one-time-code" placeholder="<?php esc_attr_e('کد تایید پیامک', 'webtanan-booking'); ?>" required>
                    <button type="submit" class="webtanan-button webtanan-button-primary wb-btn wb-btn-primary"><?php esc_html_e('تایید و ادامه پرداخت', 'webtanan-booking'); ?></button>
                    <button type="button" class="webtanan-button wb-btn webtanan-booking-resend-otp"><?php esc_html_e('ارسال دوباره کد', 'webtanan-booking'); ?></button>
                </form>
                <div class="webtanan-booking-modal-payment" hidden></div>
                <div class="webtanan-booking-modal-message" aria-live="polite"></div>
            </section>
        </div>
    <?php endif; ?>
</main>

<?php
get_footer();
