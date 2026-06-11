# REST API

namespace:

`/wp-json/saas/v1`

## احراز هویت و nonce

مسیرهای عمومی مثل لیست پزشکان، اسلات‌ها، ارسال OTP و تایید OTP بدون login در دسترس هستند. تمام مسیرهای authenticated مثل پرداخت، لغو نوبت، کیف پول، پنل بیمار، داشبورد پزشک/منشی و عملیات مالی باید هدر `X-WP-Nonce` معتبر برای action وردپرس `wp_rest` ارسال کنند. پس از تایید OTP، endpoint تایید یک nonce تازه برمی‌گرداند تا ادامه flow رزرو و پرداخت داخل همان نشست انجام شود.

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

خروجی: لیست پزشکان فعال. اگر `sort=first_available` ارسال شود، هر آیتم می‌تواند فیلد `next_available` شامل `date` و `start_time` داشته باشد. بدون این sort، اولین نوبت آزاد در پاسخ اولیه محاسبه نمی‌شود و باید از endpoint اختصاصی `next-available` خوانده شود.

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

## پنل بیمار

- `GET /patient-panel/appointments?scope=upcoming|history`
- `GET /patient-panel/wallet`
- `GET /appointments/{id}/receipt`

`GET /patient-panel/wallet` فقط ledger کاربر لاگین‌شده را برمی‌گرداند و هر ردیف شامل `entry_type`، `amount`، `balance_after`، `related_appointment_id` و در صورت وجود `appointment_code` است.
## v1.2 Payment, Wallet, And Profile Endpoints

### Wallet Top-Up

`POST /wallet/topup`

Authenticated patient endpoint. It creates an online payment for increasing wallet balance. The transaction is stored in `wp_saas_transactions` with `appointment_id = 0`. After gateway verify succeeds, the backend inserts one idempotent `credit` row into `wp_saas_wallets_ledger`.

### Resume Payment Without Entering Patient Panel

- `POST /payments/resume/send-otp`
- `POST /payments/resume/verify`
- `POST /payments/resume/pay`

The patient resumes payment with appointment code plus mobile OTP. If the lock expired but the same slot is still free, the lock is renewed. If the slot is already taken, the API returns suggested replacement slots.

### Doctor Profile From Dashboard

- `GET /doctor-dashboard/profile`
- `POST /doctor-dashboard/profile`
- `POST /doctor-dashboard/profile/upload`

Doctors can edit only the doctor row linked to their own user. Admin users can switch doctors. Upload accepts `jpg`, `png`, and `webp` files up to 5 MB.

## v1.2.2 Medical Records, Bulk Cancel, Survey, And Waiting List

### Doctor Bulk Cancellation

- `POST /doctor-dashboard/appointments/bulk-cancel`
- Auth: doctor dashboard nonce and doctor/secretary/admin access.
- Body supports either `appointment_ids: [1,2,3]` or `date: "YYYY-MM-DD"` with `doctor_id`.
- The backend never trusts the client for refund. It calls `Booking::cancel_appointment()` for each appointment, so refund idempotency and ledger safety remain centralized.

### Medical Records

- `GET /doctor-dashboard/patients/{patient_id}/record`
- `POST /doctor-dashboard/patients/{patient_id}/record`
- `POST /doctor-dashboard/patients/{patient_id}/record/notes`
- `GET /patient-panel/medical-records`

Doctor endpoints require that the patient has at least one appointment with the active doctor. Patient panel returns only the logged-in patient's records and only notes with `visibility=patient`.

### Waiting List

- `GET /appointments/{appointment_code}/waiting-list?token=...`
- Public but signed with an HMAC token sent by SMS.
- Output includes `queue_position`, `ahead_count`, `total_waiting`, `estimated_time`, appointment date/time, and status.

### Survey

- `GET /appointments/{appointment_code}/survey?token=...`
- `POST /appointments/{appointment_code}/survey?token=...`

Survey responses are stored privately in `wp_saas_survey_responses`. If `public_consent=true`, a pending WordPress comment is also created on the doctor CPT with `_webtanan_rating`; it is not public until the site admin approves it.

## v1.2.3 Public Waiting List And Survey Pages

The signed REST endpoints remain available for machine-readable data:

- `GET /appointments/{appointment_code}/waiting-list?token=...`
- `GET /appointments/{appointment_code}/survey?token=...`
- `POST /appointments/{appointment_code}/survey?token=...`

For patient-facing SMS links, the plugin now generates public HTML pages instead of sending patients directly to raw JSON:

- `/?webtanan_waiting_list=1&appointment_code={code}&token={signed_token}`
- `/?webtanan_survey=1&appointment_code={code}&token={signed_token}`

These pages render the shortcodes below and use the existing REST endpoints through `frontend.js`:

- `[webtanan_booking_waiting_list code="..." token="..."]`
- `[webtanan_booking_survey code="..." token="..."]`

Both public pages are `noindex,nofollow`. They do not expose internal appointment IDs or lock tokens. The waiting-list page polls the signed REST endpoint every 30 seconds while the browser tab is visible.
