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

$archive_url = get_post_type_archive_link('saas_doctors') ?: home_url('/');
$doctor_title = get_the_title($post_id);
$image_url = get_the_post_thumbnail_url($post_id, 'medium_large');
$booking_fee = $doctor ? Booking::doctor_booking_fee($doctor) : 0;
$visit_price = $doctor ? (float) ($doctor['visit_price'] ?? 0) : 0;
$doctor_id = $doctor ? (int) $doctor['id'] : 0;
$gallery_ids = array();
if ($post_id) {
    $raw_gallery = get_post_meta($post_id, '_webtanan_doctor_gallery_ids', true);
    $gallery_ids = is_string($raw_gallery) ? array_filter(array_map('absint', explode(',', $raw_gallery))) : array();
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
        'priceRange' => $booking_fee > 0 ? number_format_i18n($booking_fee) . ' ' . __('تومان هزینه نوبت‌دهی', 'webtanan-booking') : null,
        'availableService' => array(
            '@type' => 'MedicalProcedure',
            'name' => __('نوبت‌دهی پزشک', 'webtanan-booking'),
        ),
        'identifier' => !empty($doctor['medical_system_number']) ? array(
            '@type' => 'PropertyValue',
            'name' => __('کد نظام پزشکی', 'webtanan-booking'),
            'value' => wp_strip_all_tags($doctor['medical_system_number']),
        ) : null,
        'address' => !empty($doctor['clinic_address']) ? array(
            '@type' => 'PostalAddress',
            'streetAddress' => wp_strip_all_tags($doctor['clinic_address']),
            'addressCountry' => 'IR',
        ) : null,
    );
    $schema = array_filter($schema, static function ($value): bool {
        return null !== $value && '' !== $value;
    });
    echo '<script type="application/ld+json">' . wp_json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>';
}
?>

<main class="webtanan-booking webtanan-doctor-public" dir="rtl">
    <div class="webtanan-public-bar">
        <div class="webtanan-public-container webtanan-breadcrumb">
            <a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('خانه', 'webtanan-booking'); ?></a>
            <span>/</span>
            <a href="<?php echo esc_url($archive_url); ?>"><?php esc_html_e('پزشکان', 'webtanan-booking'); ?></a>
            <?php if (!empty($doctor['specialty_name'])) : ?>
                <span>/</span>
                <span><?php echo esc_html($doctor['specialty_name']); ?></span>
            <?php endif; ?>
            <span>/</span>
            <strong><?php echo esc_html($doctor_title); ?></strong>
        </div>
    </div>

    <div class="webtanan-public-container webtanan-profile-layout">
        <aside class="webtanan-profile-card">
            <div class="webtanan-doctor-photo">
                <?php if ($image_url) : ?>
                    <img src="<?php echo esc_url($image_url); ?>" alt="<?php echo esc_attr($doctor_title); ?>">
                <?php else : ?>
                    <span><?php echo esc_html(function_exists('mb_substr') ? mb_substr($doctor_title, 0, 1) : substr($doctor_title, 0, 1)); ?></span>
                <?php endif; ?>
            </div>
            <h1><?php echo esc_html($doctor_title); ?></h1>
            <?php if (!empty($doctor['specialty_name'])) : ?>
                <p class="webtanan-profile-specialty"><?php echo esc_html($doctor['specialty_name']); ?></p>
            <?php endif; ?>
            <?php if (!empty($doctor['medical_system_number'])) : ?>
                <div class="webtanan-verify-chip"><?php esc_html_e('کد نظام پزشکی:', 'webtanan-booking'); ?> <?php echo esc_html($doctor['medical_system_number']); ?></div>
            <?php endif; ?>

            <?php if ($doctor) : ?>
                <div class="webtanan-profile-statusline">
                    <?php if (!empty($doctor['is_verified'])) : ?><span><?php esc_html_e('پروفایل تاییدشده', 'webtanan-booking'); ?></span><?php endif; ?>
                    <?php if (!empty($doctor['allow_online_payment'])) : ?><span><?php esc_html_e('پرداخت آنلاین', 'webtanan-booking'); ?></span><?php endif; ?>
                    <?php if (!empty($doctor['allow_pay_at_clinic'])) : ?><span><?php esc_html_e('پرداخت در مطب', 'webtanan-booking'); ?></span><?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="webtanan-profile-stats">
                <div>
                    <strong><?php echo esc_html(number_format_i18n($booking_fee)); ?></strong>
                    <span><?php esc_html_e('تومان هزینه نوبت‌دهی', 'webtanan-booking'); ?></span>
                </div>
                <div>
                    <strong><?php echo esc_html($visit_price > 0 ? number_format_i18n($visit_price) : '-'); ?></strong>
                    <span><?php esc_html_e('تعرفه ویزیت اعلامی', 'webtanan-booking'); ?></span>
                </div>
            </div>

            <p class="webtanan-profile-note"><?php esc_html_e('در این سایت هزینه خدمات نوبت‌دهی دریافت می‌شود؛ هزینه ویزیت، در صورت اعلام پزشک، فقط جهت اطلاع نمایش داده شده است.', 'webtanan-booking'); ?></p>
            <?php if ($doctor_id) : ?>
                <div class="webtanan-profile-next">
                    <span><?php esc_html_e('اولین نوبت آزاد', 'webtanan-booking'); ?></span>
                    <div class="webtanan-next-available" data-webtanan-widget="next-available" data-doctor-id="<?php echo esc_attr((string) $doctor_id); ?>"></div>
                </div>
            <?php endif; ?>
            <div class="webtanan-profile-actions">
                <?php if ($doctor_id) : ?>
                    <button type="button" class="webtanan-button webtanan-button-primary" data-webtanan-booking-open data-doctor-id="<?php echo esc_attr((string) $doctor_id); ?>"><?php esc_html_e('دریافت نوبت', 'webtanan-booking'); ?></button>
                <?php endif; ?>
                <?php if (!empty($doctor['clinic_phone'])) : ?><a class="webtanan-button" href="tel:<?php echo esc_attr(preg_replace('/[^0-9+]/', '', (string) $doctor['clinic_phone'])); ?>"><?php esc_html_e('تماس با مطب', 'webtanan-booking'); ?></a><?php endif; ?>
            </div>
        </aside>

        <section class="webtanan-profile-main">
            <article class="webtanan-profile-section webtanan-booking-section" id="webtanan-booking-section">
                <header>
                    <h2><?php printf(esc_html__('نوبت‌دهی اینترنتی %s', 'webtanan-booking'), esc_html($doctor_title)); ?></h2>
                    <?php if (!empty($doctor['clinic_name'])) : ?>
                        <span><?php echo esc_html($doctor['clinic_name']); ?></span>
                    <?php endif; ?>
                </header>
                <?php if (!empty($doctor['clinic_address'])) : ?>
                    <div class="webtanan-address-line"><?php echo esc_html($doctor['clinic_address']); ?></div>
                <?php endif; ?>
                <?php if ($doctor) : ?>
                    <div class="webtanan-profile-booking-cta">
                        <div>
                            <strong><?php esc_html_e('رزرو سریع از داخل همین صفحه', 'webtanan-booking'); ?></strong>
                            <p><?php esc_html_e('تاریخ‌ها، ساعت‌های آزاد و وضعیت رزرو به‌صورت زنده از REST دریافت می‌شوند و داخل HTML کش‌شده صفحه ذخیره نمی‌شوند.', 'webtanan-booking'); ?></p>
                        </div>
                        <button type="button" class="webtanan-button webtanan-button-primary" data-webtanan-booking-open data-doctor-id="<?php echo esc_attr((string) $doctor_id); ?>"><?php esc_html_e('باز کردن تقویم نوبت‌دهی', 'webtanan-booking'); ?></button>
                    </div>
                <?php else : ?>
                    <p><?php esc_html_e('اطلاعات نوبت‌دهی این پزشک هنوز تکمیل نشده است.', 'webtanan-booking'); ?></p>
                <?php endif; ?>
            </article>

            <article class="webtanan-profile-section">
                <h2><?php printf(esc_html__('درباره %s', 'webtanan-booking'), esc_html($doctor_title)); ?></h2>
                <div class="webtanan-profile-content">
                    <?php
                    while (have_posts()) :
                        the_post();
                        the_content();
                    endwhile;
                    ?>
                </div>
            </article>

            <article class="webtanan-profile-section">
                <h2><?php esc_html_e('اطلاعات مطب', 'webtanan-booking'); ?></h2>
                <div class="webtanan-clinic-info">
                    <?php if (!empty($doctor['clinic_name'])) : ?><p><strong><?php esc_html_e('نام مطب:', 'webtanan-booking'); ?></strong> <?php echo esc_html($doctor['clinic_name']); ?></p><?php endif; ?>
                    <?php if (!empty($doctor['clinic_phone'])) : ?><p><strong><?php esc_html_e('تلفن:', 'webtanan-booking'); ?></strong> <?php echo esc_html($doctor['clinic_phone']); ?></p><?php endif; ?>
                    <?php if (!empty($doctor['clinic_address'])) : ?><p><strong><?php esc_html_e('آدرس:', 'webtanan-booking'); ?></strong> <?php echo esc_html($doctor['clinic_address']); ?></p><?php endif; ?>
                </div>
            </article>

            <?php if ($gallery_ids) : ?>
                <article class="webtanan-profile-section">
                    <h2><?php esc_html_e('گالری مطب', 'webtanan-booking'); ?></h2>
                    <div class="webtanan-clinic-gallery">
                        <?php foreach ($gallery_ids as $gallery_id) : ?>
                            <?php echo wp_get_attachment_image((int) $gallery_id, 'medium_large', false, array('loading' => 'lazy')); ?>
                        <?php endforeach; ?>
                    </div>
                </article>
            <?php endif; ?>

            <article class="webtanan-profile-section webtanan-reviews-section">
                <h2><?php esc_html_e('نظر بیماران', 'webtanan-booking'); ?></h2>
                <?php
                $comments = get_comments(
                    array(
                        'post_id' => $post_id,
                        'status' => 'approve',
                        'number' => 3,
                    )
                );
                ?>
                <?php if ($comments) : ?>
                    <div class="webtanan-review-list">
                        <?php foreach ($comments as $comment) : ?>
                            <article class="webtanan-review-card">
                                <strong><?php echo esc_html($comment->comment_author ?: __('بیمار', 'webtanan-booking')); ?></strong>
                                <p><?php echo esc_html(wp_trim_words($comment->comment_content, 32)); ?></p>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php else : ?>
                    <p class="webtanan-empty-state"><?php esc_html_e('هنوز نظری برای این پزشک ثبت نشده است.', 'webtanan-booking'); ?></p>
                <?php endif; ?>
            </article>
        </section>
    </div>

    <?php if ($doctor_id) : ?>
        <div class="webtanan-booking-modal" data-webtanan-widget="booking-modal" data-doctor-id="<?php echo esc_attr((string) $doctor_id); ?>" data-doctor-title="<?php echo esc_attr($doctor_title); ?>" hidden>
            <div class="webtanan-booking-modal-backdrop" data-webtanan-booking-close></div>
            <section class="webtanan-booking-modal-panel" role="dialog" aria-modal="true" aria-labelledby="webtanan-booking-modal-title">
                <header class="webtanan-booking-modal-head">
                    <div>
                        <span><?php esc_html_e('رزرو نوبت پزشک', 'webtanan-booking'); ?></span>
                        <h2 id="webtanan-booking-modal-title"><?php echo esc_html($doctor_title); ?></h2>
                    </div>
                    <button type="button" class="webtanan-modal-close" data-webtanan-booking-close aria-label="<?php esc_attr_e('بستن', 'webtanan-booking'); ?>">×</button>
                </header>
                <div class="webtanan-booking-modal-steps" aria-live="polite"></div>
                <div class="webtanan-booking-day-strip" aria-label="<?php esc_attr_e('انتخاب روز نوبت', 'webtanan-booking'); ?>"></div>
                <div class="webtanan-booking-modal-slots" aria-live="polite"></div>
                <form class="webtanan-booking-modal-patient" hidden>
                    <input type="text" name="patient_first_name" placeholder="<?php esc_attr_e('نام', 'webtanan-booking'); ?>" required>
                    <input type="text" name="patient_last_name" placeholder="<?php esc_attr_e('نام خانوادگی', 'webtanan-booking'); ?>" required>
                    <input type="text" name="patient_national_code" placeholder="<?php esc_attr_e('کد ملی', 'webtanan-booking'); ?>">
                    <input type="tel" name="patient_mobile" placeholder="<?php esc_attr_e('موبایل', 'webtanan-booking'); ?>" required>
                    <button type="submit" class="webtanan-button webtanan-button-primary"><?php esc_html_e('قفل نوبت و ادامه', 'webtanan-booking'); ?></button>
                </form>
                <form class="webtanan-booking-modal-otp" hidden>
                    <p></p>
                    <input type="text" name="otp" inputmode="numeric" autocomplete="one-time-code" placeholder="<?php esc_attr_e('کد ورود', 'webtanan-booking'); ?>" required>
                    <button type="submit" class="webtanan-button webtanan-button-primary"><?php esc_html_e('تایید کد', 'webtanan-booking'); ?></button>
                    <button type="button" class="webtanan-button webtanan-booking-resend-otp"><?php esc_html_e('ارسال دوباره کد', 'webtanan-booking'); ?></button>
                </form>
                <div class="webtanan-booking-modal-payment" hidden></div>
                <div class="webtanan-booking-modal-message" aria-live="polite"></div>
            </section>
        </div>
    <?php endif; ?>
</main>

<?php
get_footer();
