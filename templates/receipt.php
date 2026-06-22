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

$payment_amount = class_exists('\Webtanan\Booking\Booking')
    ? \Webtanan\Booking\Booking::appointment_charge_amount($appointment)
    : (float) ($appointment['booking_fee'] ?? $appointment['visit_price'] ?? 0);
$display_visit_price = (float) ($appointment['display_visit_price'] ?? $appointment['visit_price'] ?? 0);
$remaining_amount = max(0, $display_visit_price - $payment_amount);
$patient_name = trim(($appointment['patient_first_name'] ?? '') . ' ' . ($appointment['patient_last_name'] ?? ''));
$time_label = trim(($appointment['appointment_date'] ?? '') . ' ' . substr((string) ($appointment['start_time'] ?? ''), 0, 5));
?>
<div class="webtanan-booking webtanan-receipt wb-printable-receipt" dir="rtl">
    <button type="button" class="wb-btn wb-btn-primary wb-print-button" onclick="window.print()"><?php esc_html_e('چاپ رسید نوبت', 'webtanan-booking'); ?></button>

    <article class="wb-receipt-paper">
        <div class="wb-receipt-watermark" aria-hidden="true">+</div>

        <header class="webtanan-receipt-head wb-receipt-paper-head">
            <span class="webtanan-kicker wb-kicker"><?php esc_html_e('فاکتور نوبت', 'webtanan-booking'); ?></span>
            <h1><?php esc_html_e('رسید نوبت شما', 'webtanan-booking'); ?></h1>
            <?php if (!empty($appointment['appointment_code'])) : ?>
                <div class="wb-receipt-hero-code">
                    <span><?php esc_html_e('کد پیگیری نوبت', 'webtanan-booking'); ?></span>
                    <strong><?php echo esc_html($appointment['appointment_code']); ?></strong>
                </div>
            <?php endif; ?>
        </header>

        <dl class="wb-factor-list">
            <div>
                <dt><?php esc_html_e('نام بیمار', 'webtanan-booking'); ?></dt>
                <dd><?php echo esc_html($patient_name ?: '-'); ?></dd>
            </div>
            <div>
                <dt><?php esc_html_e('نام پزشک', 'webtanan-booking'); ?></dt>
                <dd><?php echo esc_html($doctor['title'] ?? '-'); ?></dd>
            </div>
            <div>
                <dt><?php esc_html_e('تاریخ و ساعت مراجعه', 'webtanan-booking'); ?></dt>
                <dd><?php echo esc_html($time_label ?: '-'); ?></dd>
            </div>
            <div>
                <dt><?php esc_html_e('روش پرداخت', 'webtanan-booking'); ?></dt>
                <dd><?php echo esc_html($appointment['payment_method'] ?? '-'); ?></dd>
            </div>
            <?php if (!empty($transaction['gateway_ref_id'])) : ?>
                <div>
                    <dt><?php esc_html_e('کد پیگیری بانکی', 'webtanan-booking'); ?></dt>
                    <dd><?php echo esc_html($transaction['gateway_ref_id']); ?></dd>
                </div>
            <?php endif; ?>
            <?php if (!empty($doctor['clinic_address'])) : ?>
                <div>
                    <dt><?php esc_html_e('آدرس مطب', 'webtanan-booking'); ?></dt>
                    <dd><?php echo esc_html($doctor['clinic_address']); ?></dd>
                </div>
            <?php endif; ?>
        </dl>

        <table class="wb-receipt-accounting">
            <tbody>
                <tr>
                    <th><?php esc_html_e('مبلغ پیش‌پرداخت (پرداخت شده در سایت)', 'webtanan-booking'); ?></th>
                    <td><?php echo esc_html(number_format_i18n($payment_amount)); ?> <?php esc_html_e('تومان', 'webtanan-booking'); ?></td>
                </tr>
                <tr>
                    <th><?php esc_html_e('باقی‌مانده ویزیت (پرداخت در مطب)', 'webtanan-booking'); ?></th>
                    <td><?php echo esc_html(number_format_i18n($remaining_amount)); ?> <?php esc_html_e('تومان', 'webtanan-booking'); ?></td>
                </tr>
            </tbody>
        </table>

        <p class="webtanan-checkout-note"><?php esc_html_e('این رسید برای پیگیری نوبت صادر شده است. لطفاً هنگام مراجعه، کد پیگیری را همراه داشته باشید.', 'webtanan-booking'); ?></p>
    </article>
</div>
