<?php
/**
 * Plugin-owned landing page template.
 *
 * @package WebtananBooking
 */

defined('ABSPATH') || exit;

use Webtanan\Booking\DB;
use Webtanan\Booking\Frontend;

global $wpdb;

$archive_url = get_post_type_archive_link('saas_doctors') ?: add_query_arg('post_type', 'saas_doctors', home_url('/'));
$doctor_count = (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . DB::table('doctors') . ' WHERE is_active = 1 AND is_verified = 1');
$specialty_count = (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . DB::table('specialties') . ' WHERE is_active = 1');
?>

<section class="webtanan-booking webtanan-sample-ui wb-homepage hero" dir="rtl">
    <div class="wb-home-hero">
        <div class="container webtanan-public-container wb-home-hero-grid webtanan-saas-hero-grid">
            <div class="wb-home-copy">
                <span class="wb-kicker"><?php esc_html_e('سامانه هوشمند نوبت‌دهی پزشکان وب‌تنان', 'webtanan-booking'); ?></span>
                <h1><?php esc_html_e('سریع‌تر پزشک مناسب را پیدا کنید و نوبت خود را آنلاین بگیرید', 'webtanan-booking'); ?></h1>
                <p><?php esc_html_e('وب‌تنان جستجوی پزشک، مشاهده زمان‌های آزاد، پرداخت امن و دریافت رسید نوبت را در یک مسیر ساده و قابل اعتماد کنار هم قرار می‌دهد.', 'webtanan-booking'); ?></p>

                <form class="search-section wb-home-search" action="<?php echo esc_url($archive_url); ?>" method="get">
                    <input type="hidden" name="post_type" value="saas_doctors">
                    <div class="search-row">
                        <input class="search-input" type="search" name="search" placeholder="<?php esc_attr_e('نام پزشک، تخصص، شهر یا آدرس مطب', 'webtanan-booking'); ?>">
                    <button type="submit" class="btn btn-primary wb-btn wb-btn-primary">
                        <i class="fas fa-search" aria-hidden="true"></i>
                        <?php esc_html_e('جستجو', 'webtanan-booking'); ?>
                    </button>
                    </div>
                </form>

                <div class="wb-home-stats webtanan-saas-hero-stats">
                    <div><strong><?php echo esc_html(number_format_i18n($doctor_count)); ?></strong><span><?php esc_html_e('پزشک فعال', 'webtanan-booking'); ?></span></div>
                    <div><strong><?php echo esc_html(number_format_i18n($specialty_count)); ?></strong><span><?php esc_html_e('تخصص پزشکی', 'webtanan-booking'); ?></span></div>
                    <div><strong><?php esc_html_e('رسید فوری', 'webtanan-booking'); ?></strong><span><?php esc_html_e('پس از پرداخت موفق', 'webtanan-booking'); ?></span></div>
                </div>
            </div>

            <div class="wb-home-preview" aria-hidden="true">
                <div class="wb-home-preview-card">
                    <span><?php esc_html_e('نوبت‌دهی سریع و مطمئن', 'webtanan-booking'); ?></span>
                    <strong><?php esc_html_e('انتخاب پزشک، زمان و پرداخت در یک مسیر ساده', 'webtanan-booking'); ?></strong>
                </div>
                <div class="wb-home-preview-list">
                    <div><i class="fas fa-user-md" aria-hidden="true"></i><?php esc_html_e('پزشکان معتبر', 'webtanan-booking'); ?></div>
                    <div><i class="fas fa-calendar-check" aria-hidden="true"></i><?php esc_html_e('زمان‌های آزاد واقعی', 'webtanan-booking'); ?></div>
                    <div><i class="fas fa-receipt" aria-hidden="true"></i><?php esc_html_e('فاکتور قابل چاپ', 'webtanan-booking'); ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="container webtanan-public-container wb-home-section features">
        <header class="wb-section-head">
            <div>
                <span class="wb-kicker"><?php esc_html_e('تخصص‌ها', 'webtanan-booking'); ?></span>
                <h2><?php esc_html_e('جستجو بر اساس تخصص', 'webtanan-booking'); ?></h2>
            </div>
            <a class="btn btn-outline wb-btn wb-btn-ghost" href="<?php echo esc_url($archive_url); ?>"><?php esc_html_e('همه پزشکان', 'webtanan-booking'); ?></a>
        </header>
        <?php echo Frontend::specialty_list_shortcode(array('show_count' => 'yes')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
    </div>

    <div class="container webtanan-public-container wb-home-section doctors">
        <header class="wb-section-head">
            <div>
                <span class="wb-kicker"><?php esc_html_e('پزشکان پیشنهادی', 'webtanan-booking'); ?></span>
                <h2><?php esc_html_e('نزدیک‌ترین گزینه‌ها برای گرفتن نوبت', 'webtanan-booking'); ?></h2>
            </div>
        </header>
        <?php echo Frontend::doctor_list_shortcode(array('per_page' => 6, 'sort' => 'first_available', 'layout' => 'grid')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
    </div>

    <div class="container webtanan-public-container wb-home-section wb-home-steps how-it-works">
        <article><span>1</span><h3><?php esc_html_e('پزشک را انتخاب کنید', 'webtanan-booking'); ?></h3><p><?php esc_html_e('بر اساس نام، تخصص و اولین نوبت آزاد جستجو کنید.', 'webtanan-booking'); ?></p></article>
        <article><span>2</span><h3><?php esc_html_e('زمان مناسب را بردارید', 'webtanan-booking'); ?></h3><p><?php esc_html_e('زمان انتخاب‌شده برای مدت کوتاه برای شما نگه داشته می‌شود.', 'webtanan-booking'); ?></p></article>
        <article><span>3</span><h3><?php esc_html_e('پرداخت کنید و رسید بگیرید', 'webtanan-booking'); ?></h3><p><?php esc_html_e('بعد از پرداخت، کد پیگیری و فاکتور نوبت آماده چاپ است.', 'webtanan-booking'); ?></p></article>
    </div>
</section>
