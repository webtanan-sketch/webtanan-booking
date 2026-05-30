سند فنی پیاده‌سازی ارسال پیامک IPPanel با Pattern در افزونه SaaS نوبت‌دهی
۱. هدف ماژول پیامک

در افزونه نوبت‌دهی، تمام پیامک‌های سیستمی باید از طریق IPPanel و به روش ارسال با الگو یا Pattern ارسال شوند. دلیل استفاده از پترن این است که طبق مستندات IPPanel، ارسال پیامک با Pattern نیاز به بازبینی لحظه‌ای توسط مانیتورینگ ندارد و بلافاصله بعد از درخواست ارسال می‌شود؛ بنابراین برای پیامک‌های حساس مثل OTP، تایید نوبت، لغو نوبت و یادآوری مناسب است.

این ماژول باید به‌صورت مرکزی در افزونه قرار بگیرد و تمام پزشکان، منشی‌ها و بیماران از همین تنظیمات عمومی استفاده کنند.

۲. تنظیمات اصلی در پنل مدیر کل سایت

در پیشخوان وردپرس، داخل تنظیمات افزونه، یک بخش با عنوان زیر ایجاد شود:

تنظیمات پیامک IPPanel
۲.۱ فیلدهای تنظیمات
فعال/غیرفعال بودن ارسال پیامک
Base URL
API Key / Access Token
شماره فرستنده / From Number
حالت تست
ذخیره لاگ پیامک
ارسال پیامک به بیمار
ارسال پیامک به پزشک
ارسال پیامک به منشی
ارسال یادآوری نوبت
مدت زمان قبل از یادآوری

مقدار پیش‌فرض Base URL باید این باشد:

https://edge.ippanel.com/v1

طبق مستندات IPPanel، آدرس پایه API همین مقدار است.

۳. احراز هویت IPPanel

برای ارسال درخواست‌ها، باید مقدار API Key یا Token در هدر Authorization ارسال شود. مستندات IPPanel اعلام کرده‌اند که تمام endpointهای API نیاز به احراز هویت دارند و مقدار دسترسی باید در Header قرار بگیرد.

نمونه Header
Authorization: YOUR_API_KEY_OR_TOKEN
Content-Type: application/json
نکته مهم برای توسعه‌دهنده

برای این پروژه بهتر است از API Key دائمی استفاده شود، نه Token موقت. طبق مستندات IPPanel، API Key از مسیر زیر در پنل قابل دریافت است:

حساب کاربری > برنامه‌نویسان > کلیدهای دسترسی

همچنین در مستندات توضیح داده شده که API Key برخلاف Token منقضی نمی‌شود.

۴. فرمت شماره موبایل

IPPanel برای شماره گیرنده از فرمت E.164 استفاده می‌کند. یعنی شماره باید با + شروع شود، سپس کد کشور و بعد شماره موبایل بیاید. برای ایران نمونه درست به این شکل است:

+989121234567

بنابراین در افزونه باید یک تابع مرکزی برای تبدیل شماره موبایل ساخته شود.

ورودی‌های احتمالی
09121234567
9121234567
+989121234567
00989121234567
خروجی استاندارد
+989121234567
تابع پیشنهادی
function saas_normalize_mobile_e164($mobile) {
    $mobile = trim($mobile);
    $mobile = preg_replace('/[^0-9+]/', '', $mobile);

    if (str_starts_with($mobile, '+98')) {
        return $mobile;
    }

    if (str_starts_with($mobile, '0098')) {
        return '+' . substr($mobile, 2);
    }

    if (str_starts_with($mobile, '98')) {
        return '+' . $mobile;
    }

    if (str_starts_with($mobile, '0')) {
        return '+98' . substr($mobile, 1);
    }

    if (str_starts_with($mobile, '9')) {
        return '+98' . $mobile;
    }

    return $mobile;
}
۵. ارسال پیامک با Pattern

Endpoint ارسال پیامک پترنی طبق مستندات IPPanel به این شکل است:

POST {base_url}/api/send

با توجه به Base URL پروژه:

https://edge.ippanel.com/v1/api/send
Body اصلی ارسال پترن
{
  "sending_type": "pattern",
  "from_number": "+983000505",
  "code": "PATTERN_CODE",
  "recipients": [
    "+989121234567"
  ],
  "params": {
    "code": "12345"
  }
}
توضیح پارامترها
sending_type:
باید همیشه pattern باشد.

from_number:
شماره فرستنده اختصاص داده‌شده در IPPanel، با فرمت E.164.

code:
کد پترنی که قبلاً در IPPanel ساخته و تایید شده است.

recipients:
آرایه گیرندگان. در ارسال پترنی فقط یک گیرنده برای هر درخواست مجاز است.

params:
متغیرهای جایگزین داخل متن پترن. نام کلیدها باید دقیقاً با متغیرهای تعریف‌شده در پترن یکسان باشد.

طبق مستندات، در ارسال پترن فقط یک گیرنده برای هر درخواست مجاز است و اگر پترن متغیر نداشته باشد، params باید به‌صورت آبجکت خالی ارسال شود.

۶. ساختار پیشنهادی تنظیم کد پترن‌ها در افزونه

در تنظیمات افزونه، مدیر کل سایت باید بتواند برای هر نوع پیامک، کد Pattern و وضعیت فعال/غیرفعال آن را مشخص کند.

بخش تنظیمات پترن‌ها
کد پترن OTP ورود
کد پترن تایید نوبت برای بیمار
کد پترن تایید نوبت برای پزشک/منشی
کد پترن لغو نوبت برای بیمار
کد پترن لغو نوبت برای پزشک/منشی
کد پترن شارژ کیف پول
کد پترن یادآوری نوبت
کد پترن پرداخت ناموفق
کد پترن برگشت دیرهنگام از درگاه و شارژ کیف پول
کد پترن ثبت درخواست تسویه پزشک
کد پترن تایید تسویه پزشک
کد پترن رد تسویه پزشک
۷. پترن‌های موردنیاز افزونه
۷.۱ پترن ارسال OTP
عنوان پیشنهادی در IPPanel
ورود به سامانه نوبت‌دهی
متن پیشنهادی پترن
کد ورود شما به سامانه نوبت‌دهی %code% است.
متغیرها
{
  "code": "integer"
}
params هنگام ارسال
{
  "code": "458921"
}
۷.۲ پترن تایید نوبت برای بیمار
متن پیشنهادی
نوبت شما با دکتر %doctor_name% در تاریخ %date% ساعت %time% ثبت شد. کد نوبت: %appointment_code%
متغیرها
{
  "doctor_name": "string",
  "date": "string",
  "time": "string",
  "appointment_code": "string"
}
params
{
  "doctor_name": "احمدی",
  "date": "1403/11/20",
  "time": "17:30",
  "appointment_code": "A-45872"
}
۷.۳ پترن تایید نوبت برای پزشک یا منشی
متن پیشنهادی
نوبت جدید برای دکتر %doctor_name% ثبت شد. بیمار: %patient_name% تاریخ: %date% ساعت: %time%
متغیرها
{
  "doctor_name": "string",
  "patient_name": "string",
  "date": "string",
  "time": "string"
}
۷.۴ پترن لغو نوبت برای بیمار
متن پیشنهادی
نوبت شما با دکتر %doctor_name% در تاریخ %date% ساعت %time% لغو شد. وضعیت وجه: %refund_status%
متغیرها
{
  "doctor_name": "string",
  "date": "string",
  "time": "string",
  "refund_status": "string"
}
۷.۵ پترن لغو نوبت برای پزشک یا منشی
متن پیشنهادی
نوبت بیمار %patient_name% در تاریخ %date% ساعت %time% لغو شد.
متغیرها
{
  "patient_name": "string",
  "date": "string",
  "time": "string"
}
۷.۶ پترن شارژ کیف پول بیمار
متن پیشنهادی
مبلغ %amount% تومان به کیف پول شما شارژ شد. دلیل: %reason%
متغیرها
{
  "amount": "string",
  "reason": "string"
}
۷.۷ پترن برگشت دیرهنگام از درگاه

این پترن برای سناریوی بسیار مهمی است که بیمار پرداخت موفق داشته اما قفل نوبت منقضی شده است.

متن پیشنهادی
پرداخت شما انجام شد اما نوبت انتخابی دیگر در دسترس نیست. مبلغ %amount% تومان به کیف پول شما منتقل شد.
متغیرها
{
  "amount": "string"
}
۷.۸ پترن یادآوری نوبت
متن پیشنهادی
یادآوری نوبت: شما فردا ساعت %time% با دکتر %doctor_name% نوبت دارید. کد نوبت: %appointment_code%
متغیرها
{
  "time": "string",
  "doctor_name": "string",
  "appointment_code": "string"
}
۷.۹ پترن ثبت درخواست تسویه پزشک
متن پیشنهادی
درخواست تسویه شما به مبلغ %amount% تومان ثبت شد و در انتظار بررسی است.
متغیرها
{
  "amount": "string"
}
۷.۱۰ پترن تایید تسویه پزشک
متن پیشنهادی
درخواست تسویه شما به مبلغ %amount% تومان پرداخت شد. کد پیگیری: %tracking_code%
متغیرها
{
  "amount": "string",
  "tracking_code": "string"
}
۸. ایجاد پترن در IPPanel

برنامه‌نویس می‌تواند امکان ایجاد پترن از داخل افزونه را هم اضافه کند، اما پیشنهاد اصلی این است که ساخت و تایید پترن‌ها در خود پنل IPPanel انجام شود و فقط کد پترن داخل افزونه ذخیره شود.

با این حال، طبق مستندات IPPanel امکان ساخت Pattern از طریق API نیز وجود دارد. Endpoint ساخت پترن این است:

POST {base_url}/api/user/pattern

نمونه Body ساخت پترن:

{
  "title": "appointment confirmed",
  "description": "تایید نوبت بیمار",
  "is_share": false,
  "message": "نوبت شما با دکتر %doctor_name% در تاریخ %date% ساعت %time% ثبت شد. کد نوبت: %appointment_code%",
  "website": "https://yoursite.com",
  "variable": [
    {
      "name": "doctor_name",
      "type": "string"
    },
    {
      "name": "date",
      "type": "string"
    },
    {
      "name": "time",
      "type": "string"
    },
    {
      "name": "appointment_code",
      "type": "string"
    }
  ]
}

نکته مهم: پترن‌ها باید ابتدا در IPPanel فعال و تایید شوند. طبق خروجی مستندات، وضعیت پترن می‌تواند مثلاً pending یا active باشد.

۹. دریافت و بررسی لیست پترن‌ها

برای اینکه مدیر سایت بتواند صحت کد پترن‌ها را تست کند، بهتر است در تنظیمات افزونه یک دکمه وجود داشته باشد:

بررسی اتصال و دریافت لیست پترن‌ها

Endpoint دریافت لیست پترن‌ها طبق مستندات:

GET {base_url}/api/patterns?page=1&per_page=100

نمونه استفاده:

$response = wp_remote_get($base_url . '/api/patterns?page=1&per_page=100', [
    'headers' => [
        'Authorization' => $api_key,
        'Content-Type'  => 'application/json',
    ],
    'timeout' => 20,
]);

در صفحه تنظیمات، فقط پترن‌هایی با وضعیت active باید قابل انتخاب یا تایید باشند.

۱۰. کلاس مرکزی ارسال پیامک

در هسته افزونه باید یک سرویس مستقل ساخته شود:

class SaaS_IPPanel_SMS_Service
وظایف این کلاس
خواندن تنظیمات IPPanel از wp_options
استانداردسازی شماره موبایل
ساخت payload ارسال پترن
ارسال درخواست با wp_remote_post
ثبت لاگ قبل از ارسال
ثبت نتیجه ارسال بعد از پاسخ API
مدیریت خطاها
برگرداندن خروجی استاندارد به سایر بخش‌های افزونه
نمونه کد پیشنهادی
class SaaS_IPPanel_SMS_Service {

    private string $base_url;
    private string $api_key;
    private string $from_number;
    private bool $enabled;

    public function __construct() {
        $settings = get_option('saas_sms_ippanel_settings', []);

        $this->base_url    = rtrim($settings['base_url'] ?? 'https://edge.ippanel.com/v1', '/');
        $this->api_key     = $settings['api_key'] ?? '';
        $this->from_number = $settings['from_number'] ?? '';
        $this->enabled     = !empty($settings['enabled']);
    }

    public function send_pattern(string $mobile, string $pattern_code, array $params = [], array $context = []) {
        if (!$this->enabled) {
            return [
                'success' => false,
                'message' => 'SMS module is disabled.',
            ];
        }

        if (empty($this->api_key) || empty($this->from_number) || empty($pattern_code)) {
            return [
                'success' => false,
                'message' => 'IPPanel settings are incomplete.',
            ];
        }

        $recipient = saas_normalize_mobile_e164($mobile);

        $payload = [
            'sending_type' => 'pattern',
            'from_number'  => $this->from_number,
            'code'         => $pattern_code,
            'recipients'   => [$recipient],
            'params'       => (object) $params,
        ];

        $log_id = $this->create_sms_log($recipient, $pattern_code, $params, $context, 'pending');

        $response = wp_remote_post($this->base_url . '/api/send', [
            'headers' => [
                'Authorization' => $this->api_key,
                'Content-Type'  => 'application/json',
            ],
            'body'    => wp_json_encode($payload, JSON_UNESCAPED_UNICODE),
            'timeout' => 20,
        ]);

        if (is_wp_error($response)) {
            $this->update_sms_log($log_id, 'failed', [
                'error' => $response->get_error_message(),
            ]);

            return [
                'success' => false,
                'message' => $response->get_error_message(),
            ];
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body_raw    = wp_remote_retrieve_body($response);
        $body        = json_decode($body_raw, true);

        $is_success = $status_code === 200 && !empty($body['meta']['status']);

        $this->update_sms_log($log_id, $is_success ? 'sent' : 'failed', [
            'http_code' => $status_code,
            'response'  => $body,
        ]);

        return [
            'success'   => $is_success,
            'http_code' => $status_code,
            'response'  => $body,
        ];
    }

    private function create_sms_log($mobile, $pattern_code, $params, $context, $status) {
        global $wpdb;

        $table = $wpdb->prefix . 'saas_sms_logs';

        $wpdb->insert($table, [
            'mobile'                 => $mobile,
            'pattern_code'           => $pattern_code,
            'message_type'           => $context['message_type'] ?? null,
            'variables'              => wp_json_encode($params, JSON_UNESCAPED_UNICODE),
            'provider_response'      => null,
            'status'                 => $status,
            'related_appointment_id' => $context['appointment_id'] ?? null,
            'created_at'             => current_time('mysql'),
        ]);

        return $wpdb->insert_id;
    }

    private function update_sms_log($log_id, $status, $response) {
        global $wpdb;

        $table = $wpdb->prefix . 'saas_sms_logs';

        $wpdb->update($table, [
            'status'            => $status,
            'provider_response' => wp_json_encode($response, JSON_UNESCAPED_UNICODE),
        ], [
            'id' => $log_id,
        ]);
    }
}
۱۱. جدول لاگ پیامک

در افزونه باید جدول زیر ساخته شود:

CREATE TABLE wp_saas_sms_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    mobile VARCHAR(20) NOT NULL,
    pattern_code VARCHAR(100) NOT NULL,
    message_type VARCHAR(100) NULL,
    variables LONGTEXT NULL,
    provider_response LONGTEXT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'pending',
    related_appointment_id BIGINT UNSIGNED NULL,
    related_user_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_mobile (mobile),
    KEY idx_pattern_code (pattern_code),
    KEY idx_message_type (message_type),
    KEY idx_appointment (related_appointment_id),
    KEY idx_status (status)
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
وضعیت‌های پیشنهادی لاگ
pending
sent
failed
disabled
skipped
۱۲. اتصال ماژول پیامک به رویدادهای افزونه

ماژول پیامک نباید مستقیم داخل کد رزرو، پرداخت یا لغو نوشته شود. باید از Hook/Event داخلی استفاده شود تا سیستم تمیز و قابل توسعه بماند.

رویدادهای پیشنهادی
do_action('saas_appointment_confirmed', $appointment_id);
do_action('saas_appointment_cancelled', $appointment_id, $cancelled_by);
do_action('saas_wallet_charged', $user_id, $amount, $reason);
do_action('saas_payment_late_return_wallet_charged', $appointment_id, $user_id, $amount);
do_action('saas_settlement_requested', $settlement_id);
do_action('saas_settlement_paid', $settlement_id);
نمونه اتصال تایید نوبت
add_action('saas_appointment_confirmed', function($appointment_id) {
    $appointment = saas_get_appointment($appointment_id);
    $doctor      = saas_get_doctor($appointment->doctor_id);

    $settings = get_option('saas_sms_patterns', []);
    $sms = new SaaS_IPPanel_SMS_Service();

    if (!empty($settings['patient_appointment_confirmed']['enabled'])) {
        $sms->send_pattern(
            $appointment->patient_mobile,
            $settings['patient_appointment_confirmed']['code'],
            [
                'doctor_name'      => $doctor->display_name,
                'date'             => saas_format_jalali_date($appointment->appointment_date),
                'time'             => $appointment->start_time,
                'appointment_code' => $appointment->appointment_code,
            ],
            [
                'message_type'   => 'appointment_confirmed_patient',
                'appointment_id' => $appointment_id,
            ]
        );
    }
});
۱۳. ارسال پیامک OTP

OTP باید در خود افزونه تولید و مدیریت شود، اما ارسال آن از طریق پترن IPPanel انجام شود.

مراحل
۱. کاربر شماره موبایل را وارد می‌کند.
۲. سیستم شماره را استانداردسازی می‌کند.
۳. کد OTP تولید می‌شود.
۴. هش کد در جدول otp_logs ذخیره می‌شود.
۵. کد خام فقط برای ارسال به IPPanel استفاده می‌شود.
۶. پیامک با پترن OTP ارسال می‌شود.
۷. در صورت موفقیت ارسال، وضعیت otp_log به sent تغییر می‌کند.
نکات امنیتی
کد OTP خام در دیتابیس ذخیره نشود.
کد OTP هش شود.
برای هر شماره محدودیت ارسال تعریف شود.
برای هر IP محدودیت ارسال تعریف شود.
کد پس از ۲ یا ۳ دقیقه منقضی شود.
پس از ۳ تلاش اشتباه، کد باطل شود.
۱۴. جلوگیری از خطای 429 در IPPanel

طبق مستندات IPPanel، ارسال دو درخواست کاملاً مشابه شامل گیرنده، فرستنده و متن یکسان در کمتر از ۴۰ ثانیه باعث خطای 429 می‌شود.

بنابراین باید در افزونه قبل از ارسال پیامک بررسی شود:

آیا برای همین شماره، همین message_type و همین appointment_id در ۴۰ ثانیه گذشته پیامک موفق یا pending ثبت شده است؟

اگر بله، ارسال مجدد انجام نشود و وضعیت skipped ثبت شود.

تابع پیشنهادی
function saas_sms_recent_duplicate_exists($mobile, $message_type, $appointment_id = null) {
    global $wpdb;

    $table = $wpdb->prefix . 'saas_sms_logs';

    $sql = "
        SELECT id FROM {$table}
        WHERE mobile = %s
        AND message_type = %s
        AND created_at >= DATE_SUB(NOW(), INTERVAL 40 SECOND)
    ";

    $params = [$mobile, $message_type];

    if ($appointment_id) {
        $sql .= " AND related_appointment_id = %d";
        $params[] = $appointment_id;
    }

    return (bool) $wpdb->get_var($wpdb->prepare($sql, ...$params));
}
۱۵. یادآوری نوبت با Cron Job

در تنظیمات مدیر، گزینه‌ای اضافه شود:

ارسال یادآوری نوبت چند ساعت قبل؟

مقدار پیش‌فرض:

24 ساعت قبل
منطق Cron
هر ۱۵ یا ۳۰ دقیقه اجرا شود.
نوبت‌های confirmed آینده بررسی شوند.
اگر زمان نوبت در بازه یادآوری باشد و قبلاً پیامک یادآوری ارسال نشده باشد، پیامک ارسال شود.
بعد از ارسال، در sms_logs ثبت شود.
نمونه منطق
add_action('saas_cron_send_appointment_reminders', function() {
    $appointments = saas_get_appointments_for_reminder();

    $sms = new SaaS_IPPanel_SMS_Service();
    $settings = get_option('saas_sms_patterns', []);

    foreach ($appointments as $appointment) {
        if (saas_sms_reminder_already_sent($appointment->id)) {
            continue;
        }

        $doctor = saas_get_doctor($appointment->doctor_id);

        $sms->send_pattern(
            $appointment->patient_mobile,
            $settings['appointment_reminder']['code'],
            [
                'time'             => $appointment->start_time,
                'doctor_name'      => $doctor->display_name,
                'appointment_code' => $appointment->appointment_code,
            ],
            [
                'message_type'   => 'appointment_reminder_24h',
                'appointment_id' => $appointment->id,
            ]
        );
    }
});
۱۶. مدیریت خطاهای IPPanel

در پاسخ موفق IPPanel، خروجی شامل data و meta است و شناسه پیامک‌های خروجی در message_outbox_ids برمی‌گردد.

پاسخ موفق نمونه
{
  "data": {
    "message_outbox_ids": [
      1123544244
    ]
  },
  "meta": {
    "status": true,
    "message": "انجام شد",
    "message_code": "200-1"
  }
}
خطای پترن یافت نشد

اگر کد پترن اشتباه باشد، پاسخ می‌تواند با پیام «الگو یافت نشد» برگردد.

برنامه‌نویس باید در صورت خطا:

لاگ را failed کند.
متن خطا را در provider_response ذخیره کند.
در صفحه تنظیمات مدیر، آخرین خطای ارسال را نمایش دهد.
در عملیات اصلی مثل پرداخت و رزرو، خطای پیامک نباید باعث خراب شدن ثبت نوبت شود.

یعنی اگر نوبت با موفقیت ثبت شد اما پیامک ارسال نشد، نوبت نباید rollback شود؛ فقط لاگ پیامک failed شود.

۱۷. گزارش‌گیری پیامک در افزونه

در پنل مدیر سایت یک صفحه ایجاد شود:

گزارش پیامک‌ها
ستون‌های گزارش
تاریخ ارسال
شماره موبایل
نوع پیامک
کد پترن
وضعیت
شناسه نوبت
پاسخ IPPanel
عملیات ارسال مجدد

IPPanel خودش API گزارش‌گیری هم دارد و مستندات آن اعلام کرده که گزارش‌ها و داده‌های مربوط به پیام‌های ارسالی و دریافتی از طریق API قابل دریافت هستند.

اما برای افزونه، لاگ داخلی ضروری است؛ چون باید بدانیم هر پیامک مربوط به کدام نوبت، بیمار یا تراکنش بوده است.

۱۸. دکمه تست ارسال پیامک در تنظیمات

در صفحه تنظیمات IPPanel، مدیر باید بتواند یک شماره موبایل وارد کند و ارسال تست انجام دهد.

فیلدها
شماره موبایل تست
انتخاب نوع پترن
مقادیر تست برای متغیرها
دکمه ارسال تست
نمایش پاسخ API
کاربرد
بررسی درست بودن API Key
بررسی درست بودن from_number
بررسی فعال بودن پترن
بررسی تطابق params با متغیرهای پترن
۱۹. ساختار پیشنهادی ذخیره تنظیمات
option اول: تنظیمات اتصال
saas_sms_ippanel_settings

مقدار:

[
    'enabled' => true,
    'base_url' => 'https://edge.ippanel.com/v1',
    'api_key' => 'xxxxxxxx',
    'from_number' => '+983000505',
    'test_mode' => false,
    'log_enabled' => true,
    'send_to_patient' => true,
    'send_to_doctor' => true,
    'send_to_secretary' => true,
]
option دوم: کد پترن‌ها
saas_sms_patterns

مقدار:

[
    'otp' => [
        'enabled' => true,
        'code' => 'pattern_code_otp',
    ],
    'patient_appointment_confirmed' => [
        'enabled' => true,
        'code' => 'pattern_code_confirm_patient',
    ],
    'staff_appointment_confirmed' => [
        'enabled' => true,
        'code' => 'pattern_code_confirm_staff',
    ],
    'patient_appointment_cancelled' => [
        'enabled' => true,
        'code' => 'pattern_code_cancel_patient',
    ],
    'wallet_charged' => [
        'enabled' => true,
        'code' => 'pattern_code_wallet_charged',
    ],
    'late_payment_wallet_charged' => [
        'enabled' => true,
        'code' => 'pattern_code_late_payment',
    ],
    'appointment_reminder' => [
        'enabled' => true,
        'code' => 'pattern_code_reminder',
    ],
    'settlement_requested' => [
        'enabled' => true,
        'code' => 'pattern_code_settlement_requested',
    ],
    'settlement_paid' => [
        'enabled' => true,
        'code' => 'pattern_code_settlement_paid',
    ],
]
۲۰. نکات مهم برای قرارگیری در هسته افزونه

این ماژول باید به شکل زیر در معماری افزونه قرار بگیرد:

/includes
    /Services
        class-saas-ippanel-sms-service.php
    /Notifications
        class-saas-notification-manager.php
    /Cron
        class-saas-sms-reminder-cron.php
    /Admin
        class-saas-sms-settings-page.php
        class-saas-sms-logs-page.php
اصل مهم

کدهای رزرو، پرداخت، کیف پول و تسویه نباید مستقیماً wp_remote_post بزنند. همه ارسال‌ها باید فقط از مسیر زیر انجام شود:

SaaS_IPPanel_SMS_Service::send_pattern()
۲۱. چک‌لیست نهایی پیاده‌سازی برای برنامه‌نویس
۱. ساخت صفحه تنظیمات IPPanel در پنل ادمین وردپرس
۲. ذخیره Base URL، API Key و From Number
۳. ساخت صفحه مدیریت کد پترن‌ها
۴. ساخت کلاس SaaS_IPPanel_SMS_Service
۵. استانداردسازی شماره‌ها به فرمت E.164
۶. ارسال پیامک از endpoint /api/send با sending_type=pattern
۷. ارسال Authorization و Content-Type در header
۸. ثبت همه پیامک‌ها در wp_saas_sms_logs
۹. جلوگیری از ارسال تکراری در کمتر از ۴۰ ثانیه
۱۰. اتصال پیامک‌ها به رویدادهای رزرو، لغو، پرداخت، کیف پول و تسویه
۱۱. ساخت Cron برای یادآوری نوبت
۱۲. ایجاد دکمه تست اتصال و ارسال تست
۱۳. نمایش خطاهای IPPanel در پنل ادمین
۱۴. عدم rollback عملیات رزرو در صورت خطای پیامک
۱۵. بررسی فعال بودن پترن‌ها از طریق API لیست پترن‌ها
۲۲. متن نهایی قابل تحویل به برنامه‌نویس
لطفاً در هسته افزونه SaaS نوبت‌دهی وردپرس، یک ماژول مرکزی برای ارسال پیامک از طریق IPPanel Edge API پیاده‌سازی کنید.

ارسال پیامک‌ها باید فقط با روش Pattern انجام شود. Endpoint ارسال طبق مستندات IPPanel به صورت POST به آدرس https://edge.ippanel.com/v1/api/send است. در Body درخواست باید sending_type برابر pattern باشد، from_number از تنظیمات مدیر خوانده شود، code برابر کد پترن ثبت‌شده برای نوع پیامک باشد، recipients شامل فقط یک شماره موبایل با فرمت E.164 باشد و params شامل مقادیر جایگزین متغیرهای پترن باشد.

تنظیمات IPPanel باید فقط توسط مدیر اصلی سایت انجام شود و برای تمام پزشکان، منشی‌ها و بیماران به‌صورت مشترک استفاده شود. پزشکان نباید تنظیمات جداگانه API Key یا شماره فرستنده داشته باشند.

در پنل ادمین افزونه، بخش «تنظیمات پیامک IPPanel» ایجاد شود و شامل فعال/غیرفعال بودن پیامک، Base URL، API Key، From Number، حالت تست، ذخیره لاگ، ارسال به بیمار، ارسال به پزشک، ارسال به منشی و تنظیمات یادآوری نوبت باشد.

همچنین یک بخش برای مدیریت کد پترن‌ها ایجاد شود. مدیر باید بتواند برای پیامک OTP، تایید نوبت بیمار، تایید نوبت پزشک/منشی، لغو نوبت، شارژ کیف پول، برگشت دیرهنگام از درگاه، یادآوری نوبت، درخواست تسویه و تایید تسویه، کد پترن جداگانه وارد کند.

یک کلاس مستقل با نام SaaS_IPPanel_SMS_Service ساخته شود که مسئول خواندن تنظیمات، استانداردسازی شماره موبایل به فرمت +98، ساخت Payload، ارسال درخواست با wp_remote_post، مدیریت خطا، ثبت لاگ و برگرداندن خروجی استاندارد باشد.

تمام پیامک‌ها باید در جدول wp_saas_sms_logs ثبت شوند. این جدول باید شماره موبایل، کد پترن، نوع پیامک، متغیرهای ارسال‌شده، پاسخ IPPanel، وضعیت ارسال، شناسه نوبت مرتبط و تاریخ ایجاد را ذخیره کند.

پیامک‌ها باید از طریق رویدادهای داخلی افزونه ارسال شوند، نه به‌صورت مستقیم داخل کد رزرو یا پرداخت. برای مثال بعد از تایید نوبت، رویداد saas_appointment_confirmed اجرا شود و Notification Manager پیامک‌های مربوط به بیمار و منشی را ارسال کند.

برای OTP، کد باید داخل افزونه تولید شود، هش آن در جدول OTP ذخیره شود و مقدار خام فقط برای ارسال پترن به IPPanel استفاده گردد. محدودیت ارسال OTP برای هر شماره و IP باید پیاده‌سازی شود.

برای جلوگیری از خطای 429، افزونه باید قبل از ارسال پیامک بررسی کند که در ۴۰ ثانیه گذشته پیامک مشابه برای همان شماره و همان نوع پیامک ارسال نشده باشد.

برای یادآوری نوبت، Cron Job ایجاد شود که مثلاً هر ۱۵ یا ۳۰ دقیقه اجرا شود و نوبت‌های تاییدشده نزدیک به ۲۴ ساعت آینده را بررسی کند. اگر پیامک یادآوری قبلاً ارسال نشده باشد، با پترن مربوطه ارسال شود.

اگر ارسال پیامک با خطا مواجه شد، فقط لاگ failed ثبت شود و عملیات اصلی مانند ثبت نوبت، پرداخت یا شارژ کیف پول نباید rollback شود. خطای پیامک باید در پنل ادمین قابل مشاهده باشد.

در صفحه تنظیمات، دکمه تست اتصال و ارسال پیامک تستی ایجاد شود تا مدیر بتواند API Key، From Number و کد پترن را بررسی کند.