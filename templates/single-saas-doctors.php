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
$gallery_ids = array();
if ($post_id) {
    $raw_gallery = get_post_meta($post_id, '_webtanan_doctor_gallery_ids', true);
    $gallery_ids = is_string($raw_gallery) ? array_filter(array_map('absint', explode(',', $raw_gallery))) : array();
}

wp_enqueue_style('webtanan-booking-frontend');
wp_enqueue_script('webtanan-booking-frontend');

get_header();

if ($doctor) {
    $schema = array(
        '@context' => 'https://schema.org',
        '@type' => 'Physician',
        'name' => wp_strip_all_tags($doctor_title),
        'url' => get_permalink($post_id),
        'medicalSpecialty' => !empty($doctor['specialty_name']) ? wp_strip_all_tags($doctor['specialty_name']) : null,
        'telephone' => !empty($doctor['clinic_phone']) ? wp_strip_all_tags($doctor['clinic_phone']) : null,
        'image' => $image_url ?: null,
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
            <div class="webtanan-profile-actions">
                <a class="webtanan-button webtanan-button-primary" href="#webtanan-booking-section"><?php esc_html_e('دریافت نوبت', 'webtanan-booking'); ?></a>
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
                    <?php echo do_shortcode('[webtanan_booking_calendar doctor_id="' . absint($doctor['id']) . '"]'); ?>
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
        </section>
    </div>
</main>

<?php
get_footer();
