<?php
/**
 * Public single doctor profile.
 *
 * @package WebtananBooking
 */

defined('ABSPATH') || exit;

use Webtanan\Booking\Booking;
use Webtanan\Booking\DB;
use Webtanan\Booking\Frontend;
use Webtanan\Booking\Post_Types;

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
$doctor_id = $doctor ? (int) $doctor['id'] : 0;
$specialty_url = ($doctor && !empty($doctor['specialty_id']))
    ? Post_Types::specialty_url((int) $doctor['specialty_id'])
    : $archive_url;
$clinic_phone_clean = !empty($doctor['clinic_phone']) ? preg_replace('/[^0-9+]/', '', (string) $doctor['clinic_phone']) : '';
$profile_content_raw = (string) get_post_field('post_content', $post_id);
$profile_content = $profile_content_raw ? apply_filters('the_content', $profile_content_raw) : '';
$has_profile_content = '' !== trim(wp_strip_all_tags($profile_content));
$has_clinic_info = !empty($doctor['clinic_name']) || !empty($doctor['clinic_phone']) || !empty($doctor['clinic_address']);
$rating_summary = Frontend::doctor_rating_summary($post_id);

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
$gallery_ids = is_array($raw_gallery)
    ? array_filter(array_map('absint', $raw_gallery))
    : array_filter(array_map('absint', explode(',', (string) $raw_gallery)));
foreach ($gallery_ids as $gallery_id) {
    $gallery_image = wp_get_attachment_image((int) $gallery_id, 'medium_large', false, array('loading' => 'lazy'));
    if ($gallery_image) {
        $gallery_images[] = $gallery_image;
    }
}

wp_enqueue_style('webtanan-booking-frontend');
wp_enqueue_script('webtanan-booking-frontend');

get_header();
?>

<main class="webtanan-booking webtanan-sample-ui" dir="rtl">
    <?php if (!$doctor) : ?>
        <section class="container">
            <div class="webtanan-empty-state"><?php esc_html_e('اطلاعات این پزشک هنوز تکمیل نشده است.', 'webtanan-booking'); ?></div>
        </section>
    <?php else : ?>
        <div class="container profile-layout">
            <section class="profile-main">
                <article class="profile-hero">
                    <div class="hero-avatar">
                        <?php if ($image_url) : ?>
                            <img src="<?php echo esc_url($image_url); ?>" alt="<?php echo esc_attr($doctor_title); ?>">
                        <?php else : ?>
                            <i class="fas fa-user-md" aria-hidden="true"></i>
                        <?php endif; ?>
                    </div>

                    <div class="hero-info">
                        <div class="webtanan-doctor-badges wb-profile-badges">
                            <span class="wb-rating-chip"><i class="fas fa-star" aria-hidden="true"></i><?php echo $rating_summary['count'] > 0 ? esc_html(number_format_i18n($rating_summary['rating'], 1)) . ' <small>' . esc_html(sprintf(__('(%s نظر)', 'webtanan-booking'), number_format_i18n($rating_summary['count']))) . '</small>' : '<small>' . esc_html__('بدون نظر', 'webtanan-booking') . '</small>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                            <button type="button" class="wb-favorite-doctor" data-webtanan-favorite-doctor data-doctor-id="<?php echo esc_attr((string) $doctor_id); ?>" aria-pressed="false">
                                <i class="far fa-heart" aria-hidden="true"></i>
                                <span><?php esc_html_e('افزودن به پزشکان منتخب', 'webtanan-booking'); ?></span>
                            </button>
                        </div>

                        <h1>
                            <?php echo esc_html($doctor_title); ?>
                            <?php if (!empty($doctor['is_verified'])) : ?>
                                <span class="wb-verified-mark" title="<?php esc_attr_e('پزشک تاییدشده', 'webtanan-booking'); ?>" aria-label="<?php esc_attr_e('پزشک تاییدشده', 'webtanan-booking'); ?>"><i class="fas fa-check" aria-hidden="true"></i></span>
                            <?php endif; ?>
                        </h1>

                        <?php if (!empty($doctor['specialty_name'])) : ?>
                            <p class="specialty">
                                <a class="wb-specialty-link" href="<?php echo esc_url($specialty_url); ?>"><?php echo esc_html($doctor['specialty_name']); ?></a>
                            </p>
                        <?php endif; ?>

                        <?php if (!empty($doctor['medical_system_number'])) : ?>
                            <span class="mc-code"><?php echo esc_html(sprintf(__('شماره نظام پزشکی: %s', 'webtanan-booking'), $doctor['medical_system_number'])); ?></span>
                        <?php endif; ?>

                    </div>
                </article>

                <nav class="profile-tabs" aria-label="<?php esc_attr_e('بخش‌های پروفایل پزشک', 'webtanan-booking'); ?>">
                    <?php if ($has_profile_content) : ?><a href="#about"><?php esc_html_e('درباره پزشک', 'webtanan-booking'); ?></a><?php endif; ?>
                    <a href="#services"><?php esc_html_e('خدمات و تخصص‌ها', 'webtanan-booking'); ?></a>
                    <a href="#reviews"><?php esc_html_e('نظرات بیماران', 'webtanan-booking'); ?></a>
                    <?php if ($has_clinic_info) : ?><a href="#clinic"><?php esc_html_e('آدرس و تماس', 'webtanan-booking'); ?></a><?php endif; ?>
                    <?php if ($gallery_images) : ?><a href="#gallery"><?php esc_html_e('گالری', 'webtanan-booking'); ?></a><?php endif; ?>
                    <?php if ($faq_items) : ?><a href="#faq"><?php esc_html_e('سوالات متداول', 'webtanan-booking'); ?></a><?php endif; ?>
                </nav>

                <?php if ($has_profile_content) : ?>
                    <article id="about" class="content-box">
                        <header>
                            <h2><?php printf(esc_html__('درباره %s', 'webtanan-booking'), esc_html($doctor_title)); ?></h2>
                        </header>
                        <div class="webtanan-profile-content">
                            <?php echo wp_kses_post($profile_content); ?>
                        </div>
                    </article>
                <?php endif; ?>

                <article id="services" class="content-box">
                    <header>
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
                    <article id="clinic" class="content-box">
                        <header>
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
                    <article id="gallery" class="content-box">
                        <header>
                            <h2><?php esc_html_e('گالری مطب', 'webtanan-booking'); ?></h2>
                        </header>
                        <div class="webtanan-clinic-gallery">
                            <?php foreach ($gallery_images as $gallery_image) : ?>
                                <?php echo wp_kses_post($gallery_image); ?>
                            <?php endforeach; ?>
                        </div>
                    </article>
                <?php endif; ?>

                <article id="reviews" class="content-box">
                    <header>
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
                                <?php
                                $comment_rating = 0.0;
                                foreach (array('_webtanan_rating', 'webtanan_rating', 'rating', '_rating') as $rating_key) {
                                    $value = (float) get_comment_meta($comment->comment_ID, $rating_key, true);
                                    if ($value >= 1 && $value <= 5) {
                                        $comment_rating = max($comment_rating, $value);
                                    }
                                }
                                ?>
                                <article class="webtanan-review-card review">
                                    <strong><?php echo esc_html($comment->comment_author ?: __('بیمار وب‌تنان', 'webtanan-booking')); ?></strong>
                                    <?php if ($comment_rating > 0) : ?><span class="wb-review-rate"><i class="fas fa-star" aria-hidden="true"></i><?php echo esc_html(number_format_i18n($comment_rating, 1)); ?></span><?php endif; ?>
                                    <p><?php echo esc_html(wp_trim_words($comment->comment_content, 32)); ?></p>
                                </article>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <div class="webtanan-empty-state"><?php esc_html_e('هنوز نظری برای این پزشک منتشر نشده است.', 'webtanan-booking'); ?></div>
                        <?php endif; ?>
                    </div>
                </article>

                <?php if ($faq_items) : ?>
                    <article id="faq" class="content-box">
                        <header>
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

            <aside id="booking" class="profile-sidebar" aria-label="<?php esc_attr_e('گرفتن نوبت', 'webtanan-booking'); ?>">
                <div class="booking-widget appointment-form-side">
                    <header class="booking-widget-header">
                        <i class="far fa-calendar-alt" aria-hidden="true"></i>
                        <?php esc_html_e('رزرو نوبت', 'webtanan-booking'); ?>
                    </header>

                    <div class="wb-sidebar-booking-form">
                        <div class="webtanan-profile-next">
                            <span><?php esc_html_e('اولین نوبت آزاد', 'webtanan-booking'); ?></span>
                            <div class="webtanan-next-available" data-webtanan-widget="next-available" data-doctor-id="<?php echo esc_attr((string) $doctor_id); ?>"></div>
                        </div>

                        <button type="button" class="webtanan-button webtanan-button-primary wb-btn wb-btn-primary wb-booking-cta" data-webtanan-booking-open data-doctor-id="<?php echo esc_attr((string) $doctor_id); ?>">
                            <i class="fas fa-calendar-check" aria-hidden="true"></i>
                            <?php esc_html_e('رزرو نوبت', 'webtanan-booking'); ?>
                        </button>
                    </div>

                    <?php if ($clinic_phone_clean) : ?>
                        <a class="webtanan-button wb-btn wb-booking-phone" href="tel:<?php echo esc_attr($clinic_phone_clean); ?>">
                            <i class="fas fa-phone" aria-hidden="true"></i>
                            <?php esc_html_e('تماس با مطب', 'webtanan-booking'); ?>
                        </a>
                    <?php endif; ?>

                </div>
            </aside>
        </div>

        <div class="webtanan-booking-modal" data-webtanan-widget="booking-modal" data-doctor-id="<?php echo esc_attr((string) $doctor_id); ?>" data-doctor-title="<?php echo esc_attr($doctor_title); ?>" hidden>
            <div class="webtanan-booking-modal-backdrop" data-webtanan-booking-close></div>
            <section class="webtanan-booking-modal-panel wb-booking-wizard" role="dialog" aria-modal="true" aria-labelledby="webtanan-booking-modal-title" tabindex="-1">
                <header class="webtanan-booking-modal-head">
                    <h2 id="webtanan-booking-modal-title"><?php esc_html_e('انتخاب نوبت مطب', 'webtanan-booking'); ?></h2>
                    <button type="button" class="webtanan-modal-close" data-webtanan-booking-close aria-label="<?php esc_attr_e('بستن', 'webtanan-booking'); ?>">&times;</button>
                </header>

                <div class="wb-booking-doctor-summary">
                    <div class="wb-booking-doctor-avatar">
                        <?php if ($image_url) : ?>
                            <img src="<?php echo esc_url($image_url); ?>" alt="<?php echo esc_attr($doctor_title); ?>">
                        <?php else : ?>
                            <i class="fas fa-user-md" aria-hidden="true"></i>
                        <?php endif; ?>
                    </div>
                    <div>
                        <h3>
                            <?php echo esc_html($doctor_title); ?>
                            <?php if (!empty($doctor['is_verified'])) : ?>
                                <span class="wb-verified-mark" title="<?php esc_attr_e('پزشک تاییدشده', 'webtanan-booking'); ?>"><i class="fas fa-check" aria-hidden="true"></i></span>
                            <?php endif; ?>
                        </h3>
                        <?php if (!empty($doctor['specialty_name'])) : ?><p><?php echo esc_html($doctor['specialty_name']); ?></p><?php endif; ?>
                        <?php if (!empty($doctor['clinic_address'])) : ?><address><i class="fas fa-map-marker-alt" aria-hidden="true"></i><?php echo esc_html($doctor['clinic_address']); ?></address><?php endif; ?>
                    </div>
                </div>

                <?php if (!empty($doctor['clinic_name']) || !empty($doctor['clinic_address'])) : ?>
                    <div class="wb-booking-clinic-note">
                        <i class="fas fa-circle-info" aria-hidden="true"></i>
                        <div>
                            <strong><?php echo esc_html(sprintf(__('اطلاعات مراجعه به %s', 'webtanan-booking'), $doctor['clinic_name'] ?: __('مطب پزشک', 'webtanan-booking'))); ?></strong>
                            <p><?php esc_html_e('لطفاً چند دقیقه زودتر در مطب حضور داشته باشید و کد پیگیری نوبت را همراه داشته باشید.', 'webtanan-booking'); ?></p>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="webtanan-booking-modal-steps" aria-live="polite"></div>
                <div class="webtanan-booking-day-strip" aria-label="<?php esc_attr_e('انتخاب روز نوبت', 'webtanan-booking'); ?>"></div>
                <div class="webtanan-booking-modal-slots" aria-live="polite"></div>

                <section class="webtanan-booking-modal-auth" hidden>
                    <div class="wb-booking-section-head">
                        <strong><?php esc_html_e('ورود به حساب کاربری', 'webtanan-booking'); ?></strong>
                        <p><?php esc_html_e('برای ادامه رزرو، شماره موبایل خود را تایید کنید.', 'webtanan-booking'); ?></p>
                    </div>
                    <form class="webtanan-booking-auth-mobile">
                        <label class="wb-booking-field">
                            <span><?php esc_html_e('شماره موبایل', 'webtanan-booking'); ?></span>
                            <input type="tel" name="mobile" inputmode="tel" autocomplete="tel" placeholder="09123456789" required>
                        </label>
                        <button type="submit" class="webtanan-button webtanan-button-primary wb-btn wb-btn-primary"><?php esc_html_e('دریافت کد تایید', 'webtanan-booking'); ?></button>
                    </form>
                    <form class="webtanan-booking-modal-otp" hidden>
                        <p></p>
                        <input type="text" name="otp" inputmode="numeric" autocomplete="one-time-code" maxlength="6" placeholder="<?php esc_attr_e('کد تایید پیامک', 'webtanan-booking'); ?>" required>
                        <button type="submit" class="webtanan-button webtanan-button-primary wb-btn wb-btn-primary"><?php esc_html_e('تایید و ادامه', 'webtanan-booking'); ?></button>
                        <button type="button" class="webtanan-button wb-btn webtanan-booking-resend-otp"><?php esc_html_e('ارسال دوباره کد', 'webtanan-booking'); ?></button>
                    </form>
                </section>

                <form class="webtanan-booking-modal-profile" hidden>
                    <div class="wb-booking-section-head">
                        <strong><?php esc_html_e('تکمیل اطلاعات حساب', 'webtanan-booking'); ?></strong>
                        <p><?php esc_html_e('اطلاعات هویتی صاحب حساب را یک‌بار وارد کنید.', 'webtanan-booking'); ?></p>
                    </div>
                    <div class="wb-booking-field-grid">
                        <label class="wb-booking-field"><span><?php esc_html_e('نام', 'webtanan-booking'); ?></span><input type="text" name="first_name" autocomplete="given-name" required></label>
                        <label class="wb-booking-field"><span><?php esc_html_e('نام خانوادگی', 'webtanan-booking'); ?></span><input type="text" name="last_name" autocomplete="family-name" required></label>
                        <label class="wb-booking-field wb-booking-field-wide"><span><?php esc_html_e('کد ملی', 'webtanan-booking'); ?></span><input type="text" name="national_code" maxlength="10" inputmode="numeric" autocomplete="off" required></label>
                    </div>
                    <button type="submit" class="webtanan-button webtanan-button-primary wb-btn wb-btn-primary"><?php esc_html_e('ذخیره اطلاعات و ادامه', 'webtanan-booking'); ?></button>
                </form>

                <section class="webtanan-booking-modal-person" hidden>
                    <div class="wb-booking-section-head">
                        <strong><?php esc_html_e('مراجعه‌کننده', 'webtanan-booking'); ?></strong>
                        <p><?php esc_html_e('این نوبت را برای چه کسی رزرو می‌کنید؟', 'webtanan-booking'); ?></p>
                    </div>
                    <div class="wb-booking-person-list"></div>
                    <button type="button" class="wb-booking-add-person"><i class="fas fa-plus" aria-hidden="true"></i><?php esc_html_e('رزرو برای فرد دیگر', 'webtanan-booking'); ?></button>
                    <form class="wb-booking-dependent-form" hidden>
                        <div class="wb-booking-field-grid">
                            <label class="wb-booking-field"><span><?php esc_html_e('نام', 'webtanan-booking'); ?></span><input type="text" name="first_name" required></label>
                            <label class="wb-booking-field"><span><?php esc_html_e('نام خانوادگی', 'webtanan-booking'); ?></span><input type="text" name="last_name" required></label>
                            <label class="wb-booking-field"><span><?php esc_html_e('نسبت', 'webtanan-booking'); ?></span><input type="text" name="relationship" placeholder="<?php esc_attr_e('مثلاً فرزند یا همسر', 'webtanan-booking'); ?>"></label>
                            <label class="wb-booking-field"><span><?php esc_html_e('کد ملی', 'webtanan-booking'); ?></span><input type="text" name="national_code" maxlength="10" inputmode="numeric" required></label>
                            <label class="wb-booking-field wb-booking-field-wide"><span><?php esc_html_e('شماره موبایل (اختیاری)', 'webtanan-booking'); ?></span><input type="tel" name="mobile" inputmode="tel"></label>
                        </div>
                        <div class="wb-booking-inline-actions">
                            <button type="button" class="webtanan-button wb-booking-cancel-person"><?php esc_html_e('انصراف', 'webtanan-booking'); ?></button>
                            <button type="submit" class="webtanan-button webtanan-button-primary"><?php esc_html_e('ذخیره فرد', 'webtanan-booking'); ?></button>
                        </div>
                    </form>
                    <button type="button" class="webtanan-button webtanan-button-primary wb-btn wb-btn-primary wb-booking-person-continue"><?php esc_html_e('ادامه رزرو', 'webtanan-booking'); ?></button>
                </section>

                <div class="webtanan-booking-modal-payment" hidden></div>
                <div class="webtanan-booking-modal-message" aria-live="polite"></div>
            </section>
        </div>
    <?php endif; ?>
</main>

<?php
get_footer();
