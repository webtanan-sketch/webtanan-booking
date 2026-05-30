<?php
/**
 * IPPanel Edge API SMS service.
 *
 * @package WebtananBooking
 */

namespace Webtanan\Booking;

defined('ABSPATH') || exit;

final class IPPanel_SMS_Service {
    public function send_pattern(string $mobile, string $pattern_code, array $params = array()): array {
        $settings = $this->settings();
        $mobile = self::normalize_mobile_e164($mobile);
        $from_number = self::normalize_mobile_e164($settings['from_number'] ?: $settings['originator']);
        $pattern_code = sanitize_text_field($pattern_code);

        if (!$mobile) {
            return $this->failed('recipient_missing', __('شماره موبایل گیرنده خالی است.', 'webtanan-booking'));
        }

        if (!$pattern_code) {
            return $this->failed('pattern_missing', __('کد پترن پیامک خالی است.', 'webtanan-booking'));
        }

        if (empty($settings['api_key'])) {
            return $this->failed('api_key_missing', __('کلید API آی‌پی‌پنل خالی است.', 'webtanan-booking'));
        }

        if (!$from_number) {
            return $this->failed('from_number_missing', __('شماره ارسال‌کننده آی‌پی‌پنل خالی است.', 'webtanan-booking'));
        }

        $payload = array(
            'sending_type' => 'pattern',
            'from_number' => $from_number,
            'code' => $pattern_code,
            'recipients' => array($mobile),
            'params' => self::sanitize_params($params),
        );

        if (!empty($settings['test_mode'])) {
            return array(
                'success' => true,
                'status' => 'test_mode',
                'provider' => 'ippanel',
                'payload' => $payload,
                'message' => 'IPPanel test mode: HTTP request was not sent.',
            );
        }

        $response = wp_remote_post(
            trailingslashit($settings['base_url']) . 'api/send',
            array(
                'headers' => array(
                    'Authorization' => $settings['api_key'],
                    'Content-Type' => 'application/json',
                ),
                'timeout' => 20,
                'body' => wp_json_encode($payload, JSON_UNESCAPED_UNICODE),
            )
        );

        if (is_wp_error($response)) {
            return $this->failed('http_error', $response->get_error_message(), array('payload' => $payload));
        }

        $http_code = (int) wp_remote_retrieve_response_code($response);
        $body_raw = (string) wp_remote_retrieve_body($response);
        $body = json_decode($body_raw, true);
        if (!is_array($body)) {
            $body = array('raw' => $body_raw);
        }

        $meta_status = isset($body['meta']['status']) ? (bool) $body['meta']['status'] : ($http_code >= 200 && $http_code < 300);
        $success = $http_code >= 200 && $http_code < 300 && $meta_status;

        return array(
            'success' => $success,
            'status' => $success ? 'sent' : 'failed',
            'provider' => 'ippanel',
            'http_code' => $http_code,
            'payload' => $payload,
            'response' => $body,
            'message_outbox_ids' => $body['data']['message_outbox_ids'] ?? array(),
            'message' => $body['meta']['message'] ?? wp_remote_retrieve_response_message($response),
        );
    }

    public function list_patterns(int $page = 1, int $per_page = 100): array {
        $settings = $this->settings();

        if (empty($settings['api_key'])) {
            return $this->failed('api_key_missing', __('کلید API آی‌پی‌پنل خالی است.', 'webtanan-booking'));
        }

        $url = add_query_arg(
            array(
                'page' => max(1, $page),
                'per_page' => max(1, min(100, $per_page)),
            ),
            trailingslashit($settings['base_url']) . 'api/patterns'
        );

        $response = wp_remote_get(
            $url,
            array(
                'headers' => array(
                    'Authorization' => $settings['api_key'],
                    'Content-Type' => 'application/json',
                ),
                'timeout' => 20,
            )
        );

        if (is_wp_error($response)) {
            return $this->failed('http_error', $response->get_error_message());
        }

        $http_code = (int) wp_remote_retrieve_response_code($response);
        $body_raw = (string) wp_remote_retrieve_body($response);
        $body = json_decode($body_raw, true);
        if (!is_array($body)) {
            $body = array('raw' => $body_raw);
        }

        return array(
            'success' => $http_code >= 200 && $http_code < 300,
            'status' => $http_code >= 200 && $http_code < 300 ? 'ok' : 'failed',
            'provider' => 'ippanel',
            'http_code' => $http_code,
            'response' => $body,
        );
    }

    public static function normalize_mobile_e164(string $mobile): string {
        $mobile = trim($mobile);
        $mobile = preg_replace('/[^0-9+]/', '', $mobile);

        if (0 === strpos($mobile, '+98')) {
            return $mobile;
        }

        if (0 === strpos($mobile, '0098')) {
            return '+' . substr($mobile, 2);
        }

        if (0 === strpos($mobile, '98')) {
            return '+' . $mobile;
        }

        if (0 === strpos($mobile, '0')) {
            return '+98' . substr($mobile, 1);
        }

        if (0 === strpos($mobile, '9')) {
            return '+98' . $mobile;
        }

        return $mobile;
    }

    public static function sanitize_params(array $params): array {
        $clean = array();

        foreach ($params as $key => $value) {
            $key = sanitize_key((string) $key);
            if ('' === $key) {
                continue;
            }

            if (is_scalar($value) || null === $value) {
                $clean[$key] = sanitize_text_field((string) $value);
            }
        }

        return $clean;
    }

    private function settings(): array {
        $settings = DB::get_settings();
        $sms = $settings['sms_provider_settings'] ?? array();
        $defaults = DB::default_settings()['sms_provider_settings'];
        $sms = array_replace_recursive($defaults, is_array($sms) ? $sms : array());
        $sms['base_url'] = untrailingslashit($sms['base_url'] ?: 'https://edge.ippanel.com/v1');

        return $sms;
    }

    private function failed(string $code, string $message, array $extra = array()): array {
        return array_merge(
            array(
                'success' => false,
                'status' => 'failed',
                'provider' => 'ippanel',
                'error_code' => $code,
                'message' => $message,
            ),
            $extra
        );
    }
}
