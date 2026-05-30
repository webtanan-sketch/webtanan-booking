# پیاده‌سازی پرداخت انتخابی و آقای پرداخت

وضعیت: انجام شده در نسخه `0.4.0`

## هدف

در این فاز، پرداخت آنلاین از حالت placeholder خارج شد و به یک لایه درگاه قابل توسعه تبدیل شد. بیمار بعد از lock شدن نوبت، درگاه فعال را انتخاب می‌کند و برای پرداخت به درگاه منتقل می‌شود.

اولین درگاه داخلی افزونه `aqayepardakht` است و بر اساس مستندات `docs/aqayepardakht.md` از API نسخه ۲ استفاده می‌کند.

## فایل‌های اصلی

- `includes/class-aqayepardakht-gateway.php`
- `includes/class-payment-gateways.php`
- `includes/class-booking.php`
- `includes/class-rest.php`
- `includes/class-db.php`
- `includes/class-admin.php`
- `assets/js/frontend.js`

## تنظیمات ادمین

در صفحه تنظیمات افزونه، بخش Payment Gateway Settings اضافه شد:

- فعال‌سازی آقای پرداخت
- حالت sandbox
- PIN
- callback method
- حداقل و حداکثر مبلغ
- قالب توضیحات تراکنش
- URLهای create، verify و startpay
- نمایش callback URL:

`/wp-json/saas/v1/payment/aqayepardakht/callback`

## REST APIهای اضافه‌شده

```http
GET /wp-json/saas/v1/payment/gateways
GET|POST /wp-json/saas/v1/payment/aqayepardakht/callback
```

برای شروع پرداخت:

```http
POST /wp-json/saas/v1/appointments/pay
```

نمونه body:

```json
{
  "appointment_id": 12,
  "lock_token": "uuid-token",
  "method": "online",
  "gateway": "aqayepardakht"
}
```

خروجی موفق:

```json
{
  "transaction_id": 8,
  "transaction_code": "TRX-...",
  "gateway": {
    "id": "aqayepardakht",
    "title": "AqayePardakht",
    "sandbox": true
  },
  "checkout_url": "https://panel.aqayepardakht.ir/startpay/sandbox/{transid}"
}
```

## جریان پرداخت

1. بیمار یک اسلات آزاد را انتخاب می‌کند.
2. نوبت با `locked_until` و `lock_token` قفل می‌شود.
3. بیمار درگاه فعال را انتخاب می‌کند.
4. افزونه یک رکورد در `wp_saas_transactions` با وضعیت `initiated` می‌سازد.
5. درخواست create به آقای پرداخت ارسال می‌شود.
6. در صورت موفقیت، `transid` ذخیره و کاربر به `startpay` منتقل می‌شود.
7. callback عمومی، payload برگشتی را ذخیره می‌کند.
8. اگر `status=1` باشد، verify انجام می‌شود.
9. بعد از verify موفق، افزونه lock را دوباره بررسی می‌کند.
10. اگر lock معتبر باشد، نوبت confirmed و سهم پزشک/پلتفرم در ledger ثبت می‌شود.
11. اگر lock منقضی شده باشد، نوبت قطعی نمی‌شود و مبلغ به کیف پول بیمار شارژ می‌شود.

## فیلدهای تراکنش اضافه‌شده

جدول `wp_saas_transactions` گسترش پیدا کرد:

- `gateway_transid`
- `gateway_tracking_number`
- `gateway_card_number`
- `gateway_bank`
- `invoice_id`
- `callback_status`
- `create_response`
- `verify_payload`
- `verify_response`
- `error_code`
- `error_message`

ایندکس‌های `gateway_transid` و `invoice_id` هم اضافه شدند.

## وضعیت‌های تراکنش

- `initiated`
- `redirected`
- `callback_failed`
- `verifying`
- `verify_failed`
- `verified`
- `confirmation_failed`
- `expired_lock_wallet_charged`
- `create_failed`

## نکات امنیتی و مالی

- callback بدون verify معتبر نیست.
- verify موفق به تنهایی نوبت قطعی نمی‌سازد؛ ابتدا اعتبار lock بررسی می‌شود.
- callback و verify تا حد امکان idempotent طراحی شده‌اند تا ارسال تکراری callback باعث ثبت دوباره درآمد یا شارژ دوباره کیف پول نشود.
- در callback، تراکنش با row locking خوانده می‌شود و قبل از درخواست remote verify، وضعیت `verifying` ثبت می‌شود.
- هر payload مهم در جدول تراکنش ذخیره می‌شود تا مسیر هر پرداخت قابل ردیابی باشد، اما مقدارهای حساس مثل `pin` قبل از ذخیره redact می‌شوند.

## توسعه درگاه‌های بعدی

برای افزودن درگاه‌های دیگر:

- خروجی فیلتر `webtanan_booking_available_gateways` را گسترش دهید.
- درگاه را با فیلتر `webtanan_booking_gateway_adapter` برگردانید.
- آبجکت درگاه باید متدهای سازگار با `AqayePardakht_Gateway` داشته باشد: `id`، `is_enabled`، `public_config` و `create_payment`.
