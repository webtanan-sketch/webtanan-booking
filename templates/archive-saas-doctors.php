<?php
/**
 * Plugin-owned doctors archive.
 *
 * @package WebtananBooking
 */

defined('ABSPATH') || exit;

use Webtanan\Booking\Frontend;

$search = isset($_GET['doctor_search']) ? sanitize_text_field(wp_unslash($_GET['doctor_search'])) : sanitize_text_field(wp_unslash($_GET['search'] ?? ''));
$specialty_id = isset($_GET['specialty_id']) ? absint($_GET['specialty_id']) : 0;
$province_id = isset($_GET['province_id']) ? absint($_GET['province_id']) : 0;
$city_id = isset($_GET['city_id']) ? absint($_GET['city_id']) : 0;
$payment_filter = isset($_GET['payment_filter']) ? sanitize_key(wp_unslash($_GET['payment_filter'])) : '';
$available_only = !empty($_GET['available_only']) ? '1' : '0';

wp_enqueue_style('webtanan-booking-frontend');
wp_enqueue_script('webtanan-booking-frontend');

get_header();
?>

<main class="webtanan-booking webtanan-sample-ui webtanan-doctors-archive wb-doctors-archive-page" dir="rtl">
    <section class="wb-sample-archive-hero">
        <div class="container webtanan-public-container">
            <nav class="wb-sample-breadcrumb breadcrumb" aria-label="<?php esc_attr_e('مسیر صفحه', 'webtanan-booking'); ?>">
                <a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('خانه', 'webtanan-booking'); ?></a>
                <span>/</span>
                <strong><?php esc_html_e('لیست پزشکان', 'webtanan-booking'); ?></strong>
            </nav>

            <div class="wb-sample-archive-title">
                <span class="wb-kicker"><?php esc_html_e('سامانه هوشمند نوبت‌دهی پزشکان وب‌تنان', 'webtanan-booking'); ?></span>
                <h1><?php esc_html_e('جستجوی پزشک و گرفتن نوبت آنلاین', 'webtanan-booking'); ?></h1>
                <p><?php esc_html_e('نام پزشک، تخصص یا شهر را جستجو کنید و نزدیک‌ترین نوبت آزاد، تعرفه ویزیت، آدرس مطب و روش پرداخت را در یک نگاه ببینید.', 'webtanan-booking'); ?></p>
            </div>
        </div>
    </section>

    <section class="container webtanan-public-container wb-archive-body wb-sample-archive-body">
        <div class="wb-sample-specialties">
            <?php echo Frontend::specialty_list_shortcode(array('show_count' => 'yes')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </div>

        <?php
        echo Frontend::doctor_search_shortcode(
            array(
                'per_page' => 50,
                'default_search' => $search,
                'search_placeholder' => __('نام پزشک، تخصص، شهر یا آدرس مطب', 'webtanan-booking'),
                'specialty_id' => $specialty_id,
                'province_id' => $province_id,
                'city_id' => $city_id,
                'payment_filter' => $payment_filter,
                'sort' => 'first_available',
                'layout' => 'grid',
                'available_only' => $available_only,
                'show_filters' => 'yes',
                'show_sort' => 'yes',
            )
        ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        ?>
    </section>
</main>

<?php
get_footer();
