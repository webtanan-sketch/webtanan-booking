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
