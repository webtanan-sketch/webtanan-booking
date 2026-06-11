# پیاده‌سازی IPPanel SMS

این فاز ماژول پیامک را طبق `ippanel.md` به هسته افزونه وصل می‌کند.

## کلاس‌های اصلی

- `includes/class-ippanel-sms-service.php`
- `includes/class-sms.php`

قانون اصلی این است که هیچ بخش رزرو، پرداخت یا کیف پول مستقیما `wp_remote_post` صدا نمی‌زند. همه پیامک‌ها از مسیر زیر عبور می‌کنند:

```php
SMS::send_pattern($mobile, $message_type, $variables, $appointment_id);
```

## Endpoint

ارسال Pattern به این آدرس انجام می‌شود:

```text
POST https://edge.ippanel.com/v1/api/send
```

Payload:

```json
{
  "sending_type": "pattern",
  "from_number": "+983000505",
  "code": "PATTERN_CODE",
  "recipients": ["+989121234567"],
  "params": {}
}
```

Headerها:

```text
Authorization: API_KEY
Content-Type: application/json
```

## تنظیمات ادمین

در صفحه تنظیمات افزونه بخش `IPPanel SMS Settings` اضافه شده است:

- فعال/غیرفعال بودن SMS
- Base URL
- API Key / Access Token
- From Number
- Test Mode
- ذخیره لاگ
- ارسال به بیمار
- ارسال به پزشک
- ارسال به منشی
- ارسال یادآوری
- فعال بودن و کد Pattern برای هر نوع پیام

## انواع پیام

- `otp`
- `appointment_confirmed`
- `staff_appointment_confirmed`
- `appointment_cancelled`
- `staff_appointment_cancelled`
- `wallet_charged`
- `late_payment_wallet_charged`
- `reminder_24h`
- `payment_failed`
- `settlement_requested`
- `settlement_paid`
- `settlement_status`

## لاگ

همه ارسال‌ها در جدول `wp_saas_sms_logs` ثبت می‌شوند، مگر اینکه `log_enabled` غیرفعال شود.

وضعیت‌های مهم:

- `sent`
- `failed`
- `test_mode`
- `disabled`
- `duplicate_blocked`

## جلوگیری از ارسال تکراری

اگر برای همان موبایل، همان نوع پیام و همان نوبت، در ۴۰ ثانیه گذشته پیام موفق یا تست ثبت شده باشد، ارسال جدید با وضعیت `duplicate_blocked` ثبت می‌شود.

## اتصال به رویدادها

پیامک‌ها به این رویدادهای داخلی وصل شده‌اند:

- ارسال OTP
- تایید نوبت برای بیمار
- تایید نوبت برای پزشک/منشی
- لغو نوبت برای بیمار
- لغو نوبت برای پزشک/منشی
- برگشت دیرهنگام پرداخت به کیف پول
- درخواست تسویه پزشک
- یادآوری نوبت با Cron

## تست

در صفحه تنظیمات افزونه دو ابزار اضافه شده است:

- ارسال پیامک تست به شماره واردشده
- بررسی اتصال و دریافت لیست Patternها از IPPanel
# v1.2.2 SMS Pattern Guide And Manual SMS

## Pattern Variables

All pattern screens now show the complete variable list before the pattern table:

`code`, `doctor_name`, `patient_name`, `date`, `time`, `appointment_code`, `amount`, `reason`, `refund_status`, `tracking_code`, `status`, `clinic_name`, `clinic_address`, `queue_position`, `ahead_count`, `waiting_list_url`, `survey_url`.

## New Message Types

- `appointment_survey`: sent about one hour after the appointment time with `survey_url`.
- `waiting_list_30m`: sent about 30 minutes before the appointment with `queue_position`, `ahead_count`, and `waiting_list_url`.
- `bulk_appointment_cancelled`: sent to the doctor when admin/clinic bulk cancels a day.
- `manual_sms`: logged message type for normal/free-text admin SMS.

## Example Pattern Codes

- OTP: `otp_login_001` - `کد ورود شما: {code}`
- Appointment confirmed: `appointment_confirmed_001` - `{patient_name} عزیز، نوبت شما با {doctor_name} در تاریخ {date} ساعت {time} قطعی شد. کد نوبت: {appointment_code}`
- Waiting list: `waiting_list_30m_001` - `{patient_name} عزیز، تا نوبت شما {ahead_count} نفر جلوتر هستند. جایگاه شما: {queue_position}. مشاهده زنده: {waiting_list_url}`
- Survey: `appointment_survey_001` - `{patient_name} عزیز، لطفاً تجربه مراجعه به {doctor_name} را ثبت کنید: {survey_url}`
- Bulk cancel: `bulk_cancel_001` - `نوبت‌های تاریخ {date} لغو شدند. دلیل: {reason}`

## Manual SMS

Admin settings include a normal SMS form. Admins can enter one mobile per line or comma-separated numbers and a free text message. The plugin sends through IPPanel `api/send` with `sending_type=normal` and stores one `wp_saas_sms_logs` row per recipient.
# v1.2.3 Public SMS Links

Waiting-list and survey SMS variables now point to patient-friendly public plugin pages:

- `waiting_list_url`: `/?webtanan_waiting_list=1&appointment_code={code}&token={signed_token}`
- `survey_url`: `/?webtanan_survey=1&appointment_code={code}&token={signed_token}`

The old REST endpoints are still used internally by `frontend.js`, but patients no longer see raw JSON after opening an SMS link. The public pages are signed, noindex, and do not expose internal appointment IDs or lock tokens.
