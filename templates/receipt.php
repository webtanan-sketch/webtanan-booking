<?php
/**
 * Printable appointment receipt template.
 *
 * Expected variables:
 * $appointment, $doctor, $transaction
 *
 * @package WebtananBooking
 */

defined('ABSPATH') || exit;

$appointment = isset($appointment) && is_array($appointment) ? $appointment : array();
$doctor = isset($doctor) && is_array($doctor) ? $doctor : array();
$transaction = isset($transaction) && is_array($transaction) ? $transaction : array();
$payment_amount = class_exists('\Webtanan\Booking\Booking') ? \Webtanan\Booking\Booking::appointment_charge_amount($appointment) : (float) ($appointment['booking_fee'] ?? $appointment['visit_price'] ?? 0);
$display_visit_price = (float) ($appointment['display_visit_price'] ?? $appointment['visit_price'] ?? 0);
?>
<div class="webtanan-booking webtanan-receipt" dir="rtl">
    <div class="webtanan-receipt-head">
        <span class="webtanan-kicker"><?php esc_html_e('رسید', 'webtanan-booking'); ?></span>
        <h2><?php esc_html_e('رسید نوبت', 'webtanan-booking'); ?></h2>
    </div>
    <dl>
        <dt><?php esc_html_e('کد نوبت', 'webtanan-booking'); ?></dt>
        <dd><?php echo esc_html($appointment['appointment_code'] ?? ''); ?></dd>
        <dt><?php esc_html_e('نام پزشک', 'webtanan-booking'); ?></dt>
        <dd><?php echo esc_html($doctor['title'] ?? ''); ?></dd>
        <dt><?php esc_html_e('نام بیمار', 'webtanan-booking'); ?></dt>
        <dd><?php echo esc_html(trim(($appointment['patient_first_name'] ?? '') . ' ' . ($appointment['patient_last_name'] ?? ''))); ?></dd>
        <dt><?php esc_html_e('تاریخ و ساعت', 'webtanan-booking'); ?></dt>
        <dd><?php echo esc_html(($appointment['appointment_date'] ?? '') . ' ' . substr((string) ($appointment['start_time'] ?? ''), 0, 5)); ?></dd>
        <dt><?php esc_html_e('هزینه خدمات نوبت‌دهی', 'webtanan-booking'); ?></dt>
        <dd><?php echo esc_html(number_format_i18n($payment_amount)); ?></dd>
        <?php if ($display_visit_price > 0) : ?>
            <dt><?php esc_html_e('تعرفه ویزیت اعلامی', 'webtanan-booking'); ?></dt>
            <dd><?php echo esc_html(number_format_i18n($display_visit_price)); ?></dd>
        <?php endif; ?>
        <dt><?php esc_html_e('روش پرداخت', 'webtanan-booking'); ?></dt>
        <dd><?php echo esc_html($appointment['payment_method'] ?? ''); ?></dd>
        <dt><?php esc_html_e('کد پیگیری بانکی', 'webtanan-booking'); ?></dt>
        <dd><?php echo esc_html($transaction['gateway_ref_id'] ?? ''); ?></dd>
        <dt><?php esc_html_e('آدرس مطب', 'webtanan-booking'); ?></dt>
        <dd><?php echo esc_html($doctor['clinic_address'] ?? ''); ?></dd>
    </dl>
</div>
