# REST API

namespace:

`/wp-json/saas/v1`

## پزشکان

### `GET /doctors`

پارامترها:

- `search`
- `per_page`
- `specialty_id`
- `province_id`
- `city_id`
- `payment_filter`: مقدارهای `online` یا `clinic`
- `sort`: مقدار `first_available` برای مرتب‌سازی بر اساس نزدیک‌ترین نوبت آزاد

خروجی: لیست پزشکان فعال.

### `GET /doctors/{id}`

خروجی: اطلاعات عمومی و عملیاتی قابل نمایش پزشک.

### `GET /doctors/{id}/next-available`

خروجی: چند اسلات آزاد بعدی.

### `GET /doctors/{id}/slots?date=YYYY-MM-DD`

خروجی:

- `available`
- `locked`
- `booked`

## نوبت‌دهی

### `POST /appointments/lock`

ورودی:

```json
{
  "doctor_id": 1,
  "appointment_date": "2026-06-01",
  "start_time": "09:00",
  "patient_first_name": "Ali",
  "patient_last_name": "Ahmadi",
  "patient_national_code": "0012345678",
  "patient_mobile": "09120000000",
  "payment_method": "online"
}
```

خروجی:

```json
{
  "appointment_id": 10,
  "lock_token": "...",
  "locked_until": "2026-06-01 10:15:00",
  "amount": 500000
}
```

### `POST /appointments/pay`

برای پرداخت آنلاین، `method` برابر `online` و `gateway` برابر شناسه درگاه انتخابی است.

```json
{
  "appointment_id": 10,
  "lock_token": "...",
  "method": "online",
  "gateway": "aqayepardakht"
}
```

خروجی پرداخت آنلاین شامل `checkout_url` است و بیمار باید به آن منتقل شود.

برای کیف پول، `method` برابر `wallet` است. برای پرداخت در مطب، `method` برابر `pay_at_clinic` است.

نکته modal رزرو: اگر اسلات قبل از OTP توسط کاربر مهمان lock شده باشد، این endpoint پس از ورود موفق با OTP و قبل از پرداخت، رکورد locked معتبر را به `current_user_id` وصل می‌کند؛ شرط اتصال، تطابق `appointment_id`، `lock_token` و وضعیت `locked` است.

### `GET /payment/gateways`

لیست درگاه‌های فعال قابل انتخاب توسط بیمار را برمی‌گرداند.

### `GET|POST /payment/aqayepardakht/callback`

callback عمومی آقای پرداخت است. پارامترهای `transid`، `invoice_id`، `status`، `tracking_number`، `cardnumber` و `bank` ذخیره می‌شوند. اگر `status=1` باشد، verify از آقای پرداخت انجام می‌شود.

### `POST /appointments/confirm`

این endpoint فعلا برای مدیر مالی/ادمین است. در نسخه production باید callback درگاه، verify، امضا و ref_id دقیق اضافه شود.

### `POST /appointments/cancel`

لغو با ownership check انجام می‌شود.

از نسخه `1.0.4`، endpoint مقدار `cancelled_by` ارسال‌شده از کلاینت را مبنای تصمیم مالی قرار نمی‌دهد. backend actor را بر اساس مالکیت بیمار، ادمین بودن، پزشک مالک یا منشی assign شده تشخیص می‌دهد. استرداد فقط در `wp_saas_wallets_ledger` با `entry_type=refund` ثبت می‌شود و قبل از insert، وجود refund قبلی همان `appointment_id` بررسی می‌شود.

## OTP

### `POST /auth/send-otp`

OTP هش‌شده ذخیره می‌شود و ارسال‌ها rate limit دارند.

### `POST /auth/verify-otp`

در صورت نبود کاربر، نقش `webtanan_patient` ساخته می‌شود و کاربر login می‌شود.

## داشبورد پزشک

- `GET /doctor-dashboard/appointments`
- `GET /doctor-dashboard/calendar`
- `POST /doctor-dashboard/schedules`
- `POST /doctor-dashboard/settlement-request`

دسترسی این APIها بر اساس نقش پزشک، منشی مجاز یا ادمین بررسی می‌شود.
