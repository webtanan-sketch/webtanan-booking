<?php
/**
 * AqayePardakht payment gateway adapter.
 *
 * @package WebtananBooking
 */

namespace Webtanan\Booking;

defined('ABSPATH') || exit;

final class AqayePardakht_Gateway {
    public const ID = 'aqayepardakht';

    public function id(): string {
        return self::ID;
    }

    public function title(): string {
        return __('آقای پرداخت', 'webtanan-booking');
    }

    public function settings(): array {
        $defaults = DB::default_settings();
        $settings = DB::get_settings();
        $gateway_defaults = $defaults['gateway_settings'][self::ID] ?? array();
        $gateway_settings = $settings['gateway_settings'][self::ID] ?? array();

        return array_replace_recursive($gateway_defaults, is_array($gateway_settings) ? $gateway_settings : array());
    }

    public function is_enabled(): bool {
        $settings = $this->settings();

        return !empty($settings['enabled']);
    }

    public function public_config(): array {
        $settings = $this->settings();

        return array(
            'id' => self::ID,
            'title' => $this->title(),
            'sandbox' => !empty($settings['sandbox']),
        );
    }

    public function callback_url(): string {
        return rest_url('saas/v1/payment/aqayepardakht/callback');
    }

    public function create_payment(array $payment) {
        $settings = $this->settings();
        $amount = (int) round((float) ($payment['amount'] ?? 0));
        $min = max(0, (int) ($settings['min_amount'] ?? 1000));
        $max = max($min, (int) ($settings['max_amount'] ?? 400000000));

        if ($amount < $min || $amount > $max) {
            return new \WP_Error(
                'webtanan_aqayepardakht_amount_out_of_range',
                __('مبلغ پرداخت خارج از بازه مجاز آقای پرداخت است.', 'webtanan-booking'),
                array('status' => 400)
            );
        }

        $pin = $this->pin();
        if ('' === $pin) {
            return new \WP_Error(
                'webtanan_aqayepardakht_pin_missing',
                __('پین آقای پرداخت تنظیم نشده است.', 'webtanan-booking'),
                array('status' => 500)
            );
        }

        $payload = array(
            'pin' => $pin,
            'amount' => $amount,
            'callback' => $this->callback_url(),
            'callback_method' => $this->callback_method(),
            'invoice_id' => sanitize_text_field((string) ($payment['invoice_id'] ?? '')),
            'mobile' => sanitize_text_field((string) ($payment['mobile'] ?? '')),
            'description' => sanitize_textarea_field((string) ($payment['description'] ?? '')),
        );
        $payload = array_filter(
            $payload,
            static function ($value): bool {
                return '' !== $value && null !== $value;
            }
        );

        $response = wp_remote_post(
            esc_url_raw((string) $settings['create_url']),
            array(
                'timeout' => 30,
                'headers' => array('Content-Type' => 'application/json'),
                'body' => wp_json_encode($payload, JSON_UNESCAPED_UNICODE),
            )
        );

        if (is_wp_error($response)) {
            return new \WP_Error(
                'webtanan_aqayepardakht_create_http_error',
                $response->get_error_message(),
                array('status' => 502, 'payload' => $payload)
            );
        }

        $parsed = $this->parse_response($response);
        $body = $parsed['body'];
        $status = strtolower((string) ($body['status'] ?? ''));
        $transid = sanitize_text_field((string) ($body['transid'] ?? ''));

        if ('success' !== $status || '' === $transid) {
            return new \WP_Error(
                'webtanan_aqayepardakht_create_failed',
                $this->error_message($body, __('آقای پرداخت تراکنش پرداخت را ایجاد نکرد.', 'webtanan-booking')),
                array(
                    'status' => 502,
                    'payload' => $payload,
                    'response' => $parsed,
                    'error_code' => sanitize_text_field((string) ($body['code'] ?? '')),
                )
            );
        }

        return array(
            'success' => true,
            'transid' => $transid,
            'payment_url' => $this->payment_url($transid),
            'payload' => $payload,
            'response' => $parsed,
        );
    }

    public function verify_payment(string $transid, $amount) {
        $settings = $this->settings();
        $payload = array(
            'pin' => $this->pin(),
            'amount' => (int) round((float) $amount),
            'transid' => sanitize_text_field($transid),
        );

        $response = wp_remote_post(
            esc_url_raw((string) $settings['verify_url']),
            array(
                'timeout' => 30,
                'headers' => array('Content-Type' => 'application/json'),
                'body' => wp_json_encode($payload, JSON_UNESCAPED_UNICODE),
            )
        );

        if (is_wp_error($response)) {
            return new \WP_Error(
                'webtanan_aqayepardakht_verify_http_error',
                $response->get_error_message(),
                array('status' => 502, 'payload' => $payload)
            );
        }

        $parsed = $this->parse_response($response);
        $body = $parsed['body'];
        $status = strtolower((string) ($body['status'] ?? ''));
        $code = isset($body['code']) ? (int) $body['code'] : 0;
        $verified = 'success' === $status && in_array($code, array(1, 2), true);

        return array(
            'success' => $verified,
            'already_verified' => 2 === $code,
            'payload' => $payload,
            'response' => $parsed,
            'code' => $code,
            'message' => $this->error_message($body, $verified ? __('پرداخت تایید شد.', 'webtanan-booking') : __('تایید پرداخت در آقای پرداخت ناموفق بود.', 'webtanan-booking')),
        );
    }

    private function pin(): string {
        $settings = $this->settings();
        $pin = trim((string) ($settings['pin'] ?? ''));

        if ('' === $pin && !empty($settings['sandbox'])) {
            return 'sandbox';
        }

        return $pin;
    }

    private function callback_method(): string {
        $settings = $this->settings();
        $method = strtoupper((string) ($settings['callback_method'] ?? 'GET'));

        return in_array($method, array('GET', 'POST'), true) ? $method : 'GET';
    }

    private function payment_url(string $transid): string {
        $settings = $this->settings();
        $base = rtrim((string) ($settings['startpay_url'] ?? 'https://panel.aqayepardakht.ir/startpay'), '/');
        $path = !empty($settings['sandbox']) ? '/sandbox/' : '/';

        return esc_url_raw($base . $path . rawurlencode($transid));
    }

    private function parse_response(array $response): array {
        $body_raw = wp_remote_retrieve_body($response);
        $decoded = json_decode((string) $body_raw, true);

        return array(
            'http_code' => (int) wp_remote_retrieve_response_code($response),
            'body' => is_array($decoded) ? $decoded : array('raw' => (string) $body_raw),
            'raw_body' => (string) $body_raw,
        );
    }

    private function error_message(array $body, string $fallback): string {
        foreach (array('message', 'error', 'errors') as $key) {
            if (!empty($body[$key]) && is_scalar($body[$key])) {
                return sanitize_text_field((string) $body[$key]);
            }
        }

        if (!empty($body['code'])) {
            return sprintf('%s Code: %s', $fallback, sanitize_text_field((string) $body['code']));
        }

        return $fallback;
    }
}
