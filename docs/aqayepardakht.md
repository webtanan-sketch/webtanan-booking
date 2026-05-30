سند فنی اتصال درگاه پرداخت آقای پرداخت به افزونه SaaS نوبت‌دهی وردپرس
۱. هدف ماژول پرداخت

در هسته افزونه SaaS نوبت‌دهی، باید یک ماژول مرکزی برای اتصال به درگاه پرداخت آقای پرداخت پیاده‌سازی شود.

این ماژول مسئول موارد زیر است:

ایجاد تراکنش پرداخت
انتقال بیمار به صفحه پرداخت
دریافت callback از درگاه
بررسی وضعیت پرداخت
Verify نهایی تراکنش
ثبت لاگ مالی
تایید نوبت در صورت پرداخت موفق
شارژ کیف پول بیمار در سناریوی برگشت دیرهنگام
مدیریت خطاهای درگاه

تمام پرداخت‌های آنلاین پزشکان باید از همین تنظیمات مرکزی استفاده کنند. یعنی تنظیمات درگاه فقط توسط مدیر اصلی سایت انجام می‌شود و برای کل پزشکان مشترک است.

۲. تنظیمات درگاه در پنل مدیر اصلی

در پیشخوان وردپرس، در تنظیمات افزونه، یک بخش با عنوان زیر ایجاد شود:

تنظیمات درگاه پرداخت آقای پرداخت
فیلدهای موردنیاز
فعال/غیرفعال بودن درگاه
حالت تست / Sandbox
کد پین درگاه
آدرس ایجاد تراکنش
آدرس Verify
آدرس انتقال به پرداخت
روش بازگشت callback
حداقل مبلغ قابل پرداخت
حداکثر مبلغ قابل پرداخت
توضیحات پیش‌فرض تراکنش
فعال بودن پرداخت آنلاین
فعال بودن پرداخت از کیف پول
فعال بودن پرداخت در مطب
مقادیر پیش‌فرض

طبق مستندات رسمی آقای پرداخت، آدرس ایجاد تراکنش نسخه ۲ به شکل زیر است:

https://panel.aqayepardakht.ir/api/v2/create

آدرس Verify نیز طبق مستندات رسمی:

https://panel.aqayepardakht.ir/api/v2/verify

آدرس انتقال کاربر به صفحه پرداخت:

https://panel.aqayepardakht.ir/startpay/{transid}

برای حالت sandbox:

https://panel.aqayepardakht.ir/startpay/sandbox/{transid}

در مستندات ذکر شده که برای استفاده تستی می‌توان از پین sandbox استفاده کرد.

۳. نکات مهم مستندات آقای پرداخت

طبق مستندات آقای پرداخت:

۱. ایجاد تراکنش با متد POST انجام می‌شود.
۲. مبلغ تراکنش بر حسب تومان است.
۳. مبلغ باید بین ۱,۰۰۰ تا ۴۰۰,۰۰۰,۰۰۰ تومان باشد.
۴. آدرس callback اجباری است.
۵. callback باید با دامنه تاییدشده در آقای پرداخت هم‌خوانی داشته باشد.
۶. callback_method می‌تواند POST یا GET باشد.
۷. خود مستندات توصیه کرده‌اند برای callback_method از GET استفاده شود.
۸. بعد از create، مقدار transid دریافت می‌شود.
۹. کاربر باید با transid به صفحه startpay منتقل شود.
۱۰. پس از برگشت از بانک، حتماً باید verify انجام شود.

این موارد در مستندات رسمی آقای پرداخت آمده‌اند.

۴. ساختار جدول تراکنش‌ها

در طرح قبلی جدول wp_saas_transactions داشتیم. برای آقای پرداخت، این جدول باید کامل‌تر شود.

CREATE TABLE wp_saas_transactions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    transaction_code VARCHAR(100) NOT NULL,
    gateway_name VARCHAR(50) NOT NULL DEFAULT 'aqayepardakht',
    gateway_transid VARCHAR(100) NULL,
    gateway_tracking_number VARCHAR(100) NULL,
    gateway_card_number VARCHAR(30) NULL,
    gateway_bank VARCHAR(100) NULL,
    invoice_id VARCHAR(100) NULL,
    user_id BIGINT UNSIGNED NULL,
    doctor_id BIGINT UNSIGNED NULL,
    appointment_id BIGINT UNSIGNED NULL,
    amount BIGINT UNSIGNED NOT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'initiated',
    callback_status VARCHAR(10) NULL,
    request_payload LONGTEXT NULL,
    create_response LONGTEXT NULL,
    callback_payload LONGTEXT NULL,
    verify_payload LONGTEXT NULL,
    verify_response LONGTEXT NULL,
    error_code VARCHAR(50) NULL,
    error_message TEXT NULL,
    verified_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY unique_transaction_code (transaction_code),
    KEY idx_gateway_transid (gateway_transid),
    KEY idx_invoice_id (invoice_id),
    KEY idx_user_id (user_id),
    KEY idx_doctor_id (doctor_id),
    KEY idx_appointment_id (appointment_id),
    KEY idx_status (status)
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
وضعیت‌های پیشنهادی تراکنش
initiated
create_failed
created
redirected
callback_received
callback_failed
verify_pending
verified
verify_failed
already_verified
expired_lock_wallet_charged
cancelled
۵. مرحله اول: ایجاد تراکنش

برای ایجاد تراکنش، باید درخواست POST به آدرس زیر ارسال شود:

https://panel.aqayepardakht.ir/api/v2/create
پارامترهای ورودی

طبق مستندات رسمی، پارامترهای اصلی ایجاد تراکنش شامل موارد زیر هستند:

pin
amount
callback
callback_method
card_number
invoice_id
mobile
email
description
پارامترهای اجباری
pin
amount
callback
پارامترهای اختیاری
callback_method
card_number
invoice_id
mobile
email
description
نکته مهم

مبلغ باید بر حسب تومان ارسال شود، نه ریال. در مستندات بازه مجاز مبلغ از ۱,۰۰۰ تا ۴۰۰,۰۰۰,۰۰۰ تومان اعلام شده است.

۶. Payload ایجاد تراکنش برای افزونه نوبت‌دهی

برای رزرو نوبت، Payload باید به این شکل ساخته شود:

{
  "pin": "GATEWAY_PIN",
  "amount": 250000,
  "callback": "https://example.com/wp-json/saas/v1/payment/aqayepardakht/callback",
  "callback_method": "GET",
  "invoice_id": "APT-45872",
  "mobile": "09121234567",
  "description": "پرداخت نوبت دکتر احمدی - کد نوبت APT-45872"
}
توضیح invoice_id

بهتر است invoice_id برابر با کد داخلی نوبت یا تراکنش باشد.

مثلاً:

APT-45872

یا:

TXN-20260125-45872

این کار کمک می‌کند هنگام callback و verify، پرداخت به نوبت درست متصل شود.

۷. پاسخ ایجاد تراکنش

اگر اطلاعات درست باشد، آقای پرداخت پاسخ موفق را با وضعیت success و مقدار transid برمی‌گرداند.

نمونه پاسخ موفق:

{
  "status": "success",
  "transid": "TransId"
}

اگر خطا رخ دهد، پاسخ شامل status: error و code خواهد بود.

نمونه خطا:

{
  "status": "error",
  "code": "error code"
}
۸. کلاس مرکزی درگاه آقای پرداخت

در افزونه باید یک کلاس مستقل ساخته شود:

class SaaS_AqayePardakht_Gateway
وظایف این کلاس
خواندن تنظیمات درگاه از wp_options
ساخت تراکنش
ارسال درخواست create
ثبت response در جدول transactions
تولید لینک پرداخت
verify تراکنش
مدیریت خطاها
برگرداندن خروجی استاندارد به هسته افزونه
۹. نمونه کد ایجاد تراکنش در وردپرس
class SaaS_AqayePardakht_Gateway {

    private string $pin;
    private bool $sandbox;
    private string $create_url;
    private string $verify_url;

    public function __construct() {
        $settings = get_option('saas_payment_aqayepardakht_settings', []);

        $this->pin        = $settings['sandbox'] ? 'sandbox' : ($settings['pin'] ?? '');
        $this->sandbox    = !empty($settings['sandbox']);
        $this->create_url = 'https://panel.aqayepardakht.ir/api/v2/create';
        $this->verify_url = 'https://panel.aqayepardakht.ir/api/v2/verify';
    }

    public function create_payment(array $data): array {
        $payload = [
            'pin'             => $this->pin,
            'amount'          => (int) $data['amount'],
            'callback'        => esc_url_raw($data['callback']),
            'callback_method' => 'GET',
            'invoice_id'      => sanitize_text_field($data['invoice_id']),
            'mobile'          => sanitize_text_field($data['mobile'] ?? ''),
            'description'     => sanitize_textarea_field($data['description'] ?? ''),
        ];

        $response = wp_remote_post($this->create_url, [
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body'    => wp_json_encode($payload, JSON_UNESCAPED_UNICODE),
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'message' => $response->get_error_message(),
                'payload' => $payload,
            ];
        }

        $http_code = wp_remote_retrieve_response_code($response);
        $body_raw  = wp_remote_retrieve_body($response);
        $body      = json_decode($body_raw, true);

        if ($http_code === 200 && isset($body['status']) && $body['status'] === 'success') {
            return [
                'success'     => true,
                'transid'     => $body['transid'],
                'payment_url' => $this->get_payment_url($body['transid']),
                'response'    => $body,
                'payload'     => $payload,
            ];
        }

        return [
            'success'    => false,
            'error_code' => $body['code'] ?? null,
            'response'   => $body,
            'payload'    => $payload,
        ];
    }

    public function get_payment_url(string $transid): string {
        if ($this->sandbox) {
            return 'https://panel.aqayepardakht.ir/startpay/sandbox/' . urlencode($transid);
        }

        return 'https://panel.aqayepardakht.ir/startpay/' . urlencode($transid);
    }
}
۱۰. مرحله دوم: انتقال بیمار به صفحه پرداخت

پس از دریافت transid، کاربر باید به این آدرس هدایت شود:

https://panel.aqayepardakht.ir/startpay/{transid}

برای sandbox:

https://panel.aqayepardakht.ir/startpay/sandbox/{transid}
نکته مهم Referer

طبق مستندات آقای پرداخت، در مرحله انتقال به صفحه پرداخت، آدرس Referer باید با دامنه‌ای که ترمینال شاپرکی برای آن صادر شده هماهنگ باشد، وگرنه کاربر با صفحه خطا مواجه می‌شود.

پس برنامه‌نویس باید توجه کند که:

پرداخت از همان دامنه اصلی سایت انجام شود.
callback روی همان دامنه تاییدشده باشد.
ریدایرکت از دامنه‌های دیگر یا iframe انجام نشود.
۱۱. مرحله سوم: بازگشت از درگاه Callback

بعد از خروج کاربر از بانک و بازگشت به سایت پذیرنده، آقای پرداخت پارامترهایی را به callback ارسال می‌کند. اگر هنگام create مقدار callback_method برابر GET ارسال شود، بازگشت هم با GET انجام می‌شود.

پارامترهای callback

طبق مستندات، پارامترهای برگشتی شامل موارد زیر هستند:

transid
cardnumber
tracking_number
invoice_id
bank
status
مقدار status
status = 1 یعنی پرداخت از سمت درگاه موفق گزارش شده است.
status = 0 یعنی پرداخت ناموفق بوده است.

مستندات اعلام کرده‌اند برای جلوگیری از درخواست اضافه، اگر status ناموفق بود، می‌توان درخواست verify را ارسال نکرد.

۱۲. آدرس callback در افزونه

بهتر است callback به‌صورت REST API در وردپرس ساخته شود:

/wp-json/saas/v1/payment/aqayepardakht/callback

نمونه کامل:

https://example.com/wp-json/saas/v1/payment/aqayepardakht/callback
تعریف Route
add_action('rest_api_init', function () {
    register_rest_route('saas/v1', '/payment/aqayepardakht/callback', [
        'methods'             => ['GET', 'POST'],
        'callback'            => 'saas_handle_aqayepardakht_callback',
        'permission_callback' => '__return_true',
    ]);
});
۱۳. منطق callback
function saas_handle_aqayepardakht_callback(WP_REST_Request $request) {
    $params = $request->get_params();

    $transid         = sanitize_text_field($params['transid'] ?? '');
    $status          = sanitize_text_field($params['status'] ?? '');
    $invoice_id      = sanitize_text_field($params['invoice_id'] ?? '');
    $tracking_number = sanitize_text_field($params['tracking_number'] ?? '');
    $cardnumber      = sanitize_text_field($params['cardnumber'] ?? '');
    $bank            = sanitize_text_field($params['bank'] ?? '');

    // 1. پیدا کردن تراکنش داخلی با transid یا invoice_id
    $transaction = saas_find_transaction_by_gateway_transid_or_invoice($transid, $invoice_id);

    if (!$transaction) {
        return saas_payment_result_page([
            'success' => false,
            'message' => 'تراکنش در سیستم یافت نشد.',
        ]);
    }

    // 2. ثبت callback payload
    saas_update_transaction_callback($transaction->id, [
        'callback_status'         => $status,
        'gateway_tracking_number' => $tracking_number,
        'gateway_card_number'     => $cardnumber,
        'gateway_bank'            => $bank,
        'callback_payload'        => $params,
        'status'                  => $status == '1' ? 'callback_received' : 'callback_failed',
    ]);

    // 3. اگر status ناموفق است، verify انجام نشود
    if ($status != '1') {
        saas_release_locked_appointment_if_needed($transaction->appointment_id);

        return saas_payment_result_page([
            'success' => false,
            'message' => 'پرداخت توسط کاربر انجام نشد یا ناموفق بود.',
        ]);
    }

    // 4. اگر status موفق بود، verify انجام شود
    $gateway = new SaaS_AqayePardakht_Gateway();
    $verify_result = $gateway->verify_payment(
        $transaction->gateway_transid,
        (int) $transaction->amount
    );

    return saas_process_verified_payment($transaction->id, $verify_result);
}
۱۴. مرحله چهارم: Verify تراکنش

برای verify باید درخواست POST به آدرس زیر ارسال شود:

https://panel.aqayepardakht.ir/api/v2/verify
پارامترهای اجباری verify

طبق مستندات رسمی:

pin
amount
transid

نمونه Payload:

{
  "pin": "GATEWAY_PIN",
  "amount": 250000,
  "transid": "123456789"
}
پاسخ موفق verify

اگر Verify موفق باشد، پاسخ به شکل زیر خواهد بود:

{
  "status": "success",
  "code": "1"
}

طبق جدول کدهای مستندات، کد 1 به معنی پرداخت موفق است و کد 2 به معنی این است که تراکنش قبلاً وریفای و پرداخت شده است.

۱۵. نمونه کد Verify
public function verify_payment(string $transid, int $amount): array {
    $payload = [
        'pin'     => $this->pin,
        'amount'  => $amount,
        'transid' => $transid,
    ];

    $response = wp_remote_post($this->verify_url, [
        'headers' => [
            'Content-Type' => 'application/json',
        ],
        'body'    => wp_json_encode($payload, JSON_UNESCAPED_UNICODE),
        'timeout' => 30,
    ]);

    if (is_wp_error($response)) {
        return [
            'success' => false,
            'message' => $response->get_error_message(),
            'payload' => $payload,
        ];
    }

    $http_code = wp_remote_retrieve_response_code($response);
    $body_raw  = wp_remote_retrieve_body($response);
    $body      = json_decode($body_raw, true);

    if ($http_code === 200 && isset($body['status'], $body['code'])) {
        if ($body['status'] === 'success' && $body['code'] === '1') {
            return [
                'success' => true,
                'already_verified' => false,
                'response' => $body,
                'payload' => $payload,
            ];
        }

        if ($body['code'] === '2') {
            return [
                'success' => true,
                'already_verified' => true,
                'response' => $body,
                'payload' => $payload,
            ];
        }
    }

    return [
        'success'    => false,
        'error_code' => $body['code'] ?? null,
        'response'   => $body,
        'payload'    => $payload,
    ];
}
۱۶. اتصال پرداخت به سیستم رزرو نوبت

این بخش بسیار حیاتی است. پرداخت موفق به‌تنهایی نباید باعث ثبت نهایی نوبت شود. باید وضعیت locked_until هم بررسی شود.

فرآیند کامل پرداخت نوبت
۱. بیمار ساعت نوبت را انتخاب می‌کند.
۲. سیستم با Transaction دیتابیس، نوبت را برای ۱۵ دقیقه lock می‌کند.
۳. رکورد appointment با وضعیت locked ساخته می‌شود.
۴. رکورد transaction با وضعیت initiated ساخته می‌شود.
۵. درخواست create به آقای پرداخت ارسال می‌شود.
۶. اگر create موفق بود، transid ذخیره می‌شود.
۷. بیمار به startpay منتقل می‌شود.
۸. بیمار از بانک به callback برمی‌گردد.
۹. اگر callback status برابر 1 بود، verify انجام می‌شود.
۱۰. اگر verify موفق بود، سیستم مجدداً appointment را با lock دیتابیس بررسی می‌کند.
۱۱. اگر locked_until هنوز معتبر بود، نوبت confirmed می‌شود.
۱۲. سهم پزشک و سهم پلتفرم در wallets_ledger ثبت می‌شود.
۱۳. پیامک تایید ارسال می‌شود.
۱۷. سناریوی مهم: پرداخت موفق اما قفل نوبت منقضی شده

اگر بیمار دیر از درگاه برگردد، ممکن است locked_until تمام شده باشد.

در این حالت:

۱. پرداخت verify شده است.
۲. پول واقعاً از حساب بیمار کم شده است.
۳. اما نوبت دیگر قابل تایید نیست.
۴. نباید نوبت confirmed شود.
۵. مبلغ باید به کیف پول بیمار منتقل شود.
۶. وضعیت تراکنش باید expired_lock_wallet_charged شود.
۷. پیامک شارژ کیف پول ارسال شود.
۸. نزدیک‌ترین نوبت‌های خالی همان پزشک پیشنهاد شود.
منطق پیشنهادی
function saas_process_verified_payment($transaction_id, array $verify_result) {
    global $wpdb;

    if (empty($verify_result['success'])) {
        saas_mark_transaction_verify_failed($transaction_id, $verify_result);
        return saas_payment_result_page([
            'success' => false,
            'message' => 'پرداخت تایید نشد.',
        ]);
    }

    $wpdb->query('START TRANSACTION');

    try {
        $transaction = saas_get_transaction_for_update($transaction_id);
        $appointment = saas_get_appointment_for_update($transaction->appointment_id);

        if (!$appointment) {
            throw new Exception('Appointment not found.');
        }

        if ($appointment->appointment_status === 'confirmed') {
            $wpdb->query('COMMIT');

            return saas_payment_result_page([
                'success' => true,
                'message' => 'این نوبت قبلاً تایید شده است.',
            ]);
        }

        $now = current_time('mysql');

        if ($appointment->locked_until >= $now && $appointment->appointment_status === 'locked') {
            saas_confirm_appointment($appointment->id);
            saas_mark_transaction_verified($transaction->id, $verify_result);
            saas_split_payment_between_doctor_and_platform($transaction->id);

            $wpdb->query('COMMIT');

            do_action('saas_appointment_confirmed', $appointment->id);

            return saas_payment_result_page([
                'success' => true,
                'message' => 'پرداخت موفق بود و نوبت شما ثبت شد.',
            ]);
        }

        // پرداخت موفق، اما نوبت منقضی شده
        saas_credit_patient_wallet(
            $transaction->user_id,
            $transaction->amount,
            'شارژ کیف پول بابت برگشت دیرهنگام از درگاه',
            $transaction->id,
            $appointment->id
        );

        saas_mark_transaction_expired_lock_wallet_charged($transaction->id, $verify_result);
        saas_mark_appointment_expired($appointment->id);

        $wpdb->query('COMMIT');

        do_action('saas_payment_late_return_wallet_charged', $appointment->id, $transaction->user_id, $transaction->amount);

        return saas_payment_result_page([
            'success' => false,
            'message' => 'پرداخت شما انجام شد اما زمان رزرو منقضی شده بود. مبلغ به کیف پول شما منتقل شد.',
        ]);

    } catch (Throwable $e) {
        $wpdb->query('ROLLBACK');

        saas_log_payment_exception($transaction_id, $e->getMessage());

        return saas_payment_result_page([
            'success' => false,
            'message' => 'خطایی در پردازش پرداخت رخ داد. لطفاً با پشتیبانی تماس بگیرید.',
        ]);
    }
}
۱۸. مدیریت خطاهای درگاه

طبق مستندات آقای پرداخت، کدهای خطای مهم شامل موارد زیر هستند:

-1: amount نمی‌تواند خالی باشد
-2: کد پین درگاه نمی‌تواند خالی باشد
-3: callback نمی‌تواند خالی باشد
-4: amount باید عددی باشد
-5: amount باید بین 1,000 تا 400,000,000 تومان باشد
-6: کد پین درگاه اشتباه است
-7: transid نمی‌تواند خالی باشد
-8: تراکنش مورد نظر وجود ندارد
-9: کد پین درگاه با درگاه تراکنش مطابقت ندارد
-10: مبلغ با مبلغ تراکنش مطابقت ندارد
-11: درگاه در انتظار تایید یا غیرفعال است
-12: امکان ارسال درخواست برای این پذیرنده وجود ندارد
-13: شماره کارت باید ۱۶ رقم باشد
-14: درگاه روی سایت دیگری در حال استفاده است
-15: آدرس callback با دامنه تاییدشده درگاه مغایرت دارد
-16: ارجاع‌دهنده نامعتبر است
-17: مقدار callback_method باید POST یا GET باشد

در افزونه باید یک تابع برای ترجمه کد خطا به پیام فارسی ساخته شود.

function saas_aqayepardakht_error_message($code): string {
    $errors = [
        '-1'  => 'مبلغ نمی‌تواند خالی باشد.',
        '-2'  => 'کد پین درگاه نمی‌تواند خالی باشد.',
        '-3'  => 'آدرس بازگشت نمی‌تواند خالی باشد.',
        '-4'  => 'مبلغ باید عددی باشد.',
        '-5'  => 'مبلغ باید بین ۱,۰۰۰ تا ۴۰۰,۰۰۰,۰۰۰ تومان باشد.',
        '-6'  => 'کد پین درگاه اشتباه است.',
        '-7'  => 'کد تراکنش نمی‌تواند خالی باشد.',
        '-8'  => 'تراکنش مورد نظر وجود ندارد.',
        '-9'  => 'کد پین با درگاه تراکنش مطابقت ندارد.',
        '-10' => 'مبلغ با مبلغ تراکنش مطابقت ندارد.',
        '-11' => 'درگاه در انتظار تایید یا غیرفعال است.',
        '-12' => 'امکان ارسال درخواست برای این پذیرنده وجود ندارد.',
        '-13' => 'شماره کارت باید ۱۶ رقم باشد.',
        '-14' => 'درگاه روی سایت دیگری در حال استفاده است.',
        '-15' => 'آدرس بازگشت با دامنه تاییدشده درگاه مغایرت دارد.',
        '-16' => 'ارجاع‌دهنده نامعتبر است.',
        '-17' => 'روش بازگشت باید POST یا GET باشد.',
        '0'   => 'پرداخت انجام نشد.',
        '1'   => 'پرداخت با موفقیت انجام شد.',
        '2'   => 'تراکنش قبلاً وریفای و پرداخت شده است.',
    ];

    return $errors[(string) $code] ?? 'خطای نامشخص در درگاه پرداخت.';
}
۱۹. تنظیمات پیشنهادی ذخیره در wp_options
saas_payment_aqayepardakht_settings

مقدار پیشنهادی:

[
    'enabled' => true,
    'sandbox' => false,
    'pin' => 'YOUR_GATEWAY_PIN',
    'callback_method' => 'GET',
    'min_amount' => 1000,
    'max_amount' => 400000000,
    'description_template' => 'پرداخت نوبت {doctor_name} - کد نوبت {appointment_code}',
]
۲۰. ساختار پیشنهادی فایل‌ها در افزونه
/includes
    /Gateways
        class-saas-aqayepardakht-gateway.php
        class-saas-payment-manager.php

    /Admin
        class-saas-payment-settings-page.php
        class-saas-transactions-page.php

    /REST
        class-saas-payment-routes.php

    /Services
        class-saas-wallet-service.php
        class-saas-appointment-payment-service.php
۲۱. صفحه گزارش تراکنش‌ها برای ادمین

در پنل مدیر افزونه، صفحه‌ای با عنوان زیر ایجاد شود:

تراکنش‌های پرداخت
ستون‌های پیشنهادی
شناسه داخلی
کد نوبت
نام بیمار
شماره موبایل بیمار
نام پزشک
مبلغ
درگاه
transid
tracking_number
شماره کارت
بانک
وضعیت
کد خطا
تاریخ ایجاد
تاریخ تایید
عملیات
عملیات‌ها
مشاهده جزئیات
بررسی مجدد Verify
مشاهده پاسخ خام درگاه
مشاهده نوبت مرتبط
مشاهده لاگ کیف پول
۲۲. جلوگیری از دوباره‌کاری و دوبار تایید شدن پرداخت

چون callback ممکن است چند بار فراخوانی شود یا کاربر صفحه را refresh کند، باید عملیات Verify و Confirm به‌صورت idempotent طراحی شود.

قانون مهم
اگر تراکنش قبلاً verified شده بود، نباید دوباره کیف پول پزشک شارژ شود.
اگر نوبت قبلاً confirmed شده بود، نباید دوباره تایید شود.
اگر مبلغ قبلاً به کیف پول بیمار منتقل شده بود، نباید دوباره منتقل شود.
روش پیشنهادی
قبل از هر عملیات مالی:
۱. رکورد transaction با SELECT ... FOR UPDATE قفل شود.
۲. وضعیت فعلی بررسی شود.
۳. اگر verified یا expired_lock_wallet_charged بود، عملیات مالی تکرار نشود.
۴. فقط صفحه نتیجه مناسب نمایش داده شود.
۲۳. پرداخت در مطب و ارتباط با کارمزد

اگر پزشک گزینه «پرداخت در مطب» را فعال کرده باشد، پرداخت آنلاین انجام نمی‌شود. اما اگر پلتفرم بابت هر نوبت حتی پرداخت حضوری هم کارمزد می‌گیرد، باید در کیف پول پزشک بدهی ثبت شود.

مثلاً:

مبلغ ویزیت: ۳۰۰,۰۰۰ تومان
کارمزد پلتفرم: ۱۰٪
پرداخت بیمار: در مطب
بدهی پزشک به پلتفرم: ۳۰,۰۰۰ تومان

در ledger پزشک:

entry_type: platform_commission_debt
amount: -30000
description: بدهی کارمزد بابت نوبت پرداخت در مطب
۲۴. اتصال پرداخت به پیامک

بعد از موفقیت پرداخت و تایید نوبت:

do_action('saas_appointment_confirmed', $appointment_id);

این رویداد باید پیامک تایید نوبت برای بیمار و منشی را فعال کند.

اگر پرداخت موفق باشد اما زمان رزرو منقضی شده باشد:

do_action('saas_payment_late_return_wallet_charged', $appointment_id, $user_id, $amount);

این رویداد باید پیامک شارژ کیف پول را ارسال کند.

۲۵. نکات امنیتی مهم
۱. مبلغ پرداختی از سمت کاربر دریافت نشود؛ از دیتابیس خوانده شود.
۲. appointment_id از کاربر قابل اعتماد نیست؛ باید مالکیت بیمار بررسی شود.
۳. callback فقط با transid و invoice_id معتبر پردازش شود.
۴. verify حتماً انجام شود؛ callback status به‌تنهایی کافی نیست.
۵. اگر مبلغ verify با مبلغ داخلی سازگار نبود، تراکنش رد شود.
۶. عملیات مالی داخل دیتابیس Transaction انجام شود.
۷. تمام responseهای درگاه در دیتابیس ذخیره شوند.
۸. callback route عمومی است، پس باید ورودی‌ها کاملاً sanitize شوند.
۹. هر تراکنش فقط یک‌بار اثر مالی داشته باشد.
۱۰. خطای پرداخت نباید باعث حذف کامل نوبت شود؛ باید وضعیت آن مشخص شود.
۲۶. چک‌لیست نهایی پیاده‌سازی
۱. ساخت صفحه تنظیمات آقای پرداخت در پنل ادمین
۲. ذخیره pin، sandbox، callback_method و وضعیت فعال/غیرفعال
۳. ساخت کلاس SaaS_AqayePardakht_Gateway
۴. ساخت متد create_payment
۵. ساخت متد verify_payment
۶. ساخت REST route برای callback
۷. ثبت تراکنش در wp_saas_transactions قبل از انتقال به درگاه
۸. ذخیره transid بعد از create موفق
۹. انتقال بیمار به startpay
۱۰. دریافت callback با GET یا POST
۱۱. ثبت callback payload
۱۲. اگر status=0 بود، آزادسازی نوبت locked
۱۳. اگر status=1 بود، انجام verify
۱۴. اگر verify code=1 بود، بررسی locked_until
۱۵. اگر lock معتبر بود، تایید نوبت و تقسیم سهم پزشک/پلتفرم
۱۶. اگر lock منقضی شده بود، شارژ کیف پول بیمار
۱۷. جلوگیری از دوبار verify و دوبار شارژ
۱۸. ثبت تمام خطاها و responseها
۱۹. نمایش گزارش تراکنش‌ها به ادمین
۲۰. اتصال پرداخت موفق به سیستم پیامک
۲۷. متن نهایی قابل تحویل به برنامه‌نویس
لطفاً در هسته افزونه SaaS نوبت‌دهی وردپرس، یک ماژول مرکزی برای اتصال به درگاه پرداخت آقای پرداخت نسخه ۲ پیاده‌سازی کنید.

تنظیمات درگاه باید فقط توسط مدیر اصلی سایت انجام شود و برای تمام پزشکان مشترک باشد. پزشکان نباید تنظیمات درگاه جداگانه داشته باشند.

در پنل ادمین افزونه، بخشی با عنوان «تنظیمات درگاه آقای پرداخت» ایجاد شود که شامل فعال/غیرفعال بودن درگاه، حالت تست sandbox، کد پین درگاه، روش callback، حداقل مبلغ، حداکثر مبلغ و توضیحات پیش‌فرض تراکنش باشد.

برای ایجاد تراکنش باید درخواست POST به آدرس https://panel.aqayepardakht.ir/api/v2/create ارسال شود. پارامترهای اجباری شامل pin، amount و callback هستند. مبلغ باید بر حسب تومان و بین ۱,۰۰۰ تا ۴۰۰,۰۰۰,۰۰۰ تومان باشد. callback_method بهتر است GET ارسال شود. invoice_id باید برابر کد داخلی نوبت یا تراکنش باشد.

در صورت موفق بودن create، درگاه مقدار transid برمی‌گرداند. این مقدار باید در جدول wp_saas_transactions ذخیره شود و سپس کاربر به آدرس https://panel.aqayepardakht.ir/startpay/{transid} منتقل شود. در حالت sandbox، آدرس انتقال باید https://panel.aqayepardakht.ir/startpay/sandbox/{transid} باشد.

برای callback، یک REST route در وردپرس ایجاد شود، مثلاً:
/wp-json/saas/v1/payment/aqayepardakht/callback

در بازگشت از درگاه، پارامترهای transid، cardnumber، tracking_number، invoice_id، bank و status دریافت می‌شوند. اگر status برابر 0 بود، پرداخت ناموفق است و نباید verify انجام شود. در این حالت اگر نوبت هنوز locked است، آزاد شود و تراکنش callback_failed شود.

اگر status برابر 1 بود، باید درخواست verify به آدرس https://panel.aqayepardakht.ir/api/v2/verify ارسال شود. پارامترهای verify شامل pin، amount و transid هستند. اگر پاسخ verify دارای status=success و code=1 بود، پرداخت موفق قطعی محسوب می‌شود. کد 2 نیز به معنی تراکنش قبلاً وریفای و پرداخت شده است و باید به‌صورت idempotent مدیریت شود.

بعد از verify موفق، افزونه نباید بلافاصله نوبت را تایید کند. ابتدا باید appointment مربوطه با قفل دیتابیس بررسی شود. اگر وضعیت نوبت locked بود و locked_until هنوز معتبر بود، نوبت confirmed شود، تراکنش verified شود، سهم پزشک و سهم پلتفرم در wallets_ledger ثبت شود و پیامک تایید نوبت ارسال شود.

اگر پرداخت verify شد اما locked_until منقضی شده بود، نوبت نباید confirmed شود. در این حالت مبلغ پرداختی باید به کیف پول بیمار شارژ شود، تراکنش با وضعیت expired_lock_wallet_charged ثبت شود، پیامک اطلاع‌رسانی شارژ کیف پول ارسال شود و در پنل بیمار نزدیک‌ترین نوبت‌های خالی همان پزشک پیشنهاد شود.

تمام عملیات مالی، تایید نوبت، شارژ کیف پول و تقسیم سهم پزشک/پلتفرم باید داخل Transaction دیتابیس انجام شود. سیستم باید در برابر callback تکراری، refresh صفحه، verify دوباره و اجرای همزمان مقاوم باشد. هر تراکنش فقط یک بار باید اثر مالی داشته باشد.

تمام request و responseهای create، callback و verify باید در جدول wp_saas_transactions ذخیره شوند. همچنین در پنل ادمین صفحه گزارش تراکنش‌ها ایجاد شود تا مدیر بتواند وضعیت پرداخت‌ها، transid، شماره پیگیری، شماره کارت، بانک، کد خطا، پاسخ خام درگاه و نوبت مرتبط را مشاهده کند.

اگر ارسال پیامک بعد از پرداخت موفق با خطا مواجه شد، نباید عملیات پرداخت rollback شود. فقط باید لاگ پیامک failed ثبت شود.