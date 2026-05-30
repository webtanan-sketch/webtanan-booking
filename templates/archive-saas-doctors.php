<?php
/**
 * Public doctors archive.
 *
 * @package WebtananBooking
 */

defined('ABSPATH') || exit;

use Webtanan\Booking\Booking;
use Webtanan\Booking\DB;

global $wpdb;

$search = isset($_GET['doctor_search']) ? sanitize_text_field(wp_unslash($_GET['doctor_search'])) : '';
$specialty_id = isset($_GET['specialty_id']) ? absint($_GET['specialty_id']) : 0;
$payment_filter = isset($_GET['payment_filter']) ? sanitize_key(wp_unslash($_GET['payment_filter'])) : '';
$where = "d.is_active = 1 AND d.is_verified = 1 AND p.post_status = 'publish'";
$params = array();

if ($search) {
    $like = '%' . $wpdb->esc_like($search) . '%';
    $where .= ' AND (p.post_title LIKE %s OR d.clinic_name LIKE %s OR d.clinic_address LIKE %s OR s.name LIKE %s)';
    array_push($params, $like, $like, $like, $like);
}

if ($specialty_id > 0) {
    $where .= ' AND d.specialty_id = %d';
    $params[] = $specialty_id;
}

if ('online' === $payment_filter) {
    $where .= ' AND d.allow_online_payment = 1';
} elseif ('clinic' === $payment_filter) {
    $where .= ' AND d.allow_pay_at_clinic = 1';
}

$sql = 'SELECT d.*, p.post_title, p.ID AS post_id, s.name AS specialty_name
    FROM ' . DB::table('doctors') . ' d
    INNER JOIN ' . $wpdb->posts . ' p ON p.ID = d.post_id
    LEFT JOIN ' . DB::table('specialties') . " s ON s.id = d.specialty_id
    WHERE $where
    ORDER BY d.is_verified DESC, p.post_title ASC
    LIMIT 60";
$doctors = $params ? $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A) : $wpdb->get_results($sql, ARRAY_A);
$specialties = $wpdb->get_results('SELECT id, name FROM ' . DB::table('specialties') . ' WHERE is_active = 1 ORDER BY sort_order ASC, name ASC LIMIT 300', ARRAY_A);

wp_enqueue_style('webtanan-booking-frontend');
wp_enqueue_script('webtanan-booking-frontend');

get_header();
?>

<main class="webtanan-booking webtanan-doctors-archive" dir="rtl">
    <div class="webtanan-public-bar">
        <div class="webtanan-public-container">
            <div class="webtanan-public-head">
                <div>
                    <span class="webtanan-kicker"><?php esc_html_e('وب‌تنان بوکینگ', 'webtanan-booking'); ?></span>
                    <h1><?php esc_html_e('جستجو و دریافت نوبت پزشکان', 'webtanan-booking'); ?></h1>
                    <p class="webtanan-public-lead"><?php esc_html_e('پزشک، تخصص یا روش پرداخت را انتخاب کنید و نزدیک‌ترین زمان‌های آزاد را ببینید.', 'webtanan-booking'); ?></p>
                </div>
                <div class="webtanan-public-count">
                    <strong><?php echo esc_html(number_format_i18n(count($doctors))); ?></strong>
                    <span><?php esc_html_e('نتیجه', 'webtanan-booking'); ?></span>
                </div>
            </div>
        </div>
    </div>

    <div class="webtanan-public-container">
        <form class="webtanan-archive-filter" method="get">
            <input type="search" name="doctor_search" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('نام پزشک، تخصص یا آدرس مطب', 'webtanan-booking'); ?>">
            <select name="specialty_id">
                <option value="0"><?php esc_html_e('همه تخصص‌ها', 'webtanan-booking'); ?></option>
                <?php foreach ($specialties as $specialty) : ?>
                    <option value="<?php echo esc_attr((string) $specialty['id']); ?>" <?php selected($specialty_id, (int) $specialty['id']); ?>><?php echo esc_html($specialty['name']); ?></option>
                <?php endforeach; ?>
            </select>
            <select name="payment_filter">
                <option value=""><?php esc_html_e('همه روش‌های پرداخت', 'webtanan-booking'); ?></option>
                <option value="online" <?php selected($payment_filter, 'online'); ?>><?php esc_html_e('پرداخت آنلاین', 'webtanan-booking'); ?></option>
                <option value="clinic" <?php selected($payment_filter, 'clinic'); ?>><?php esc_html_e('پرداخت در مطب', 'webtanan-booking'); ?></option>
            </select>
            <button type="submit" class="webtanan-button webtanan-button-primary"><?php esc_html_e('فیلتر', 'webtanan-booking'); ?></button>
        </form>

        <div class="webtanan-result-head">
            <strong><?php esc_html_e('پزشکان قابل نوبت‌دهی', 'webtanan-booking'); ?></strong>
            <span><?php esc_html_e('پزشکان فعال و تاییدشده', 'webtanan-booking'); ?></span>
        </div>

        <div class="webtanan-public-doctor-grid">
            <?php if (!$doctors) : ?>
                <p class="webtanan-empty-state"><?php esc_html_e('پزشکی با این فیلتر پیدا نشد.', 'webtanan-booking'); ?></p>
            <?php endif; ?>

            <?php foreach ($doctors as $doctor) : ?>
                <?php
                $post_id = (int) $doctor['post_id'];
                $title = html_entity_decode(get_the_title($post_id), ENT_QUOTES, get_bloginfo('charset'));
                $image_url = get_the_post_thumbnail_url($post_id, 'medium');
                $booking_fee = Booking::doctor_booking_fee($doctor);
                $visit_price = (float) ($doctor['visit_price'] ?? 0);
                ?>
                <article class="webtanan-public-doctor-card">
                    <a class="webtanan-public-doctor-photo" href="<?php echo esc_url(get_permalink($post_id)); ?>">
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
                        <h2><a href="<?php echo esc_url(get_permalink($post_id)); ?>"><?php echo esc_html($title); ?></a></h2>
                        <?php if (!empty($doctor['specialty_name'])) : ?><p class="webtanan-meta"><?php echo esc_html($doctor['specialty_name']); ?></p><?php endif; ?>
                        <?php if (!empty($doctor['clinic_address'])) : ?><p class="webtanan-meta"><?php echo esc_html(wp_trim_words($doctor['clinic_address'], 18)); ?></p><?php endif; ?>
                        <div class="webtanan-public-fees">
                            <span><?php esc_html_e('هزینه نوبت‌دهی:', 'webtanan-booking'); ?> <strong><?php echo esc_html(number_format_i18n($booking_fee)); ?></strong> <?php esc_html_e('تومان', 'webtanan-booking'); ?></span>
                            <?php if ($visit_price > 0) : ?>
                                <span><?php esc_html_e('ویزیت اعلامی:', 'webtanan-booking'); ?> <?php echo esc_html(number_format_i18n($visit_price)); ?> <?php esc_html_e('تومان', 'webtanan-booking'); ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="webtanan-public-actions">
                            <a class="webtanan-button webtanan-button-primary" href="<?php echo esc_url(get_permalink($post_id)); ?>"><?php esc_html_e('مشاهده و دریافت نوبت', 'webtanan-booking'); ?></a>
                            <div class="webtanan-next-available" data-webtanan-widget="next-available" data-doctor-id="<?php echo esc_attr((string) $doctor['id']); ?>"></div>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</main>

<?php
get_footer();
