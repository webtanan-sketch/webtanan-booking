<?php
/**
 * Public doctors archive.
 *
 * @package WebtananBooking
 */

defined('ABSPATH') || exit;

use Webtanan\Booking\Frontend;

$search = isset($_GET['doctor_search']) ? sanitize_text_field(wp_unslash($_GET['doctor_search'])) : '';
$specialty_id = isset($_GET['specialty_id']) ? absint($_GET['specialty_id']) : 0;
$province_id = isset($_GET['province_id']) ? absint($_GET['province_id']) : 0;
$city_id = isset($_GET['city_id']) ? absint($_GET['city_id']) : 0;
$payment_filter = isset($_GET['payment_filter']) ? sanitize_key(wp_unslash($_GET['payment_filter'])) : '';

wp_enqueue_style('webtanan-booking-frontend');
wp_enqueue_script('webtanan-booking-frontend');

get_header();
?>

<main class="webtanan-booking webtanan-doctors-archive wb-doctors-archive-page" dir="rtl">
    <section class="webtanan-public-bar wb-archive-hero">
        <div class="webtanan-public-container">
            <div class="webtanan-public-head wb-archive-head">
                <div>
                    <span class="webtanan-kicker wb-kicker"><?php esc_html_e('نوبت‌دهی پزشکان', 'webtanan-booking'); ?></span>
                    <h1><?php esc_html_e('جستجو و گرفتن نوبت از پزشکان', 'webtanan-booking'); ?></h1>
                    <p class="webtanan-public-lead"><?php esc_html_e('پزشک، تخصص یا روش پرداخت را انتخاب کنید و نزدیک‌ترین نوبت‌های آزاد را به‌صورت زنده ببینید.', 'webtanan-booking'); ?></p>
                </div>
                <div class="webtanan-public-count wb-archive-live-note">
                    <strong><?php esc_html_e('زنده', 'webtanan-booking'); ?></strong>
                    <span><?php esc_html_e('نوبت‌ها از API بارگذاری می‌شوند', 'webtanan-booking'); ?></span>
                </div>
            </div>
        </div>
    </section>

    <section class="webtanan-public-container wb-archive-body">
        <div class="webtanan-shortcode-specialties wb-archive-specialties">
            <?php echo Frontend::specialty_list_shortcode(array('show_count' => 'yes')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </div>

        <?php
        echo Frontend::doctor_search_shortcode(
            array(
                'per_page' => 50,
                'default_search' => $search,
                'search_placeholder' => __('نام پزشک، تخصص یا آدرس مطب', 'webtanan-booking'),
                'specialty_id' => $specialty_id,
                'province_id' => $province_id,
                'city_id' => $city_id,
                'payment_filter' => $payment_filter,
                'sort' => 'first_available',
                'layout' => 'grid',
                'show_filters' => 'yes',
                'show_sort' => 'yes',
            )
        ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        ?>
    </section>
</main>

<?php
get_footer();
