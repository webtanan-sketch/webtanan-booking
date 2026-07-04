<?php
/**
 * Plugin-owned doctors archive.
 *
 * @package WebtananBooking
 */

defined('ABSPATH') || exit;

use Webtanan\Booking\Frontend;
use Webtanan\Booking\Post_Types;

$search = isset($_GET['doctor_search']) ? sanitize_text_field(wp_unslash($_GET['doctor_search'])) : sanitize_text_field(wp_unslash($_GET['search'] ?? ''));
$specialty_id = Post_Types::specialty_id_from_request();
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
                <h1><?php esc_html_e('جستجوی پزشک و رزرو نوبت', 'webtanan-booking'); ?></h1>
            </div>
        </div>
    </section>

    <section class="container webtanan-public-container wb-archive-body wb-sample-archive-body">
        <?php
        echo Frontend::doctor_search_shortcode(
            array(
                'per_page' => 50,
                'default_search' => $search,
                'search_placeholder' => __('نام پزشک، تخصص، شهر یا آدرس مطب', 'webtanan-booking'),
                'specialty_id' => $specialty_id,
                'sort' => 'first_available',
                'layout' => 'grid',
                'available_only' => $available_only,
                'show_filters' => 'yes',
                'show_location' => 'no',
                'show_sort' => 'no',
            )
        ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        ?>
    </section>
</main>

<?php
get_footer();
