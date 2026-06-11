# دیتابیس

جدول‌ها با prefix وردپرس ساخته می‌شوند. اگر prefix سایت `wp_` باشد، نام جدول‌ها به شکل زیر است.

## جدول‌ها

- `wp_saas_doctors`
- `wp_saas_specialties`
- `wp_saas_schedules`
- `wp_saas_schedule_exceptions`
- `wp_saas_appointments`
- `wp_saas_transactions`
- `wp_saas_wallets_ledger`
- `wp_saas_settlement_requests`
- `wp_saas_otp_logs`
- `wp_saas_sms_logs`

## جدول پزشکان

`wp_saas_doctors` به CPT پزشک وصل است.

کلیدهای مهم:

- `post_id`: اتصال به `saas_doctors`
- `user_id`: حساب وردپرسی پزشک
- `specialty_id`: اتصال به جدول تخصص‌ها
- `visit_price`: تعرفه ویزیت
- `platform_commission_type`
- `platform_commission_value`
- `allow_online_payment`
- `allow_pay_at_clinic`

## جدول نوبت‌ها

`wp_saas_appointments` هسته رزرو است.

ایندکس حیاتی:

```sql
UNIQUE KEY unique_doctor_slot (
    doctor_id,
    appointment_date,
    start_time
)
```

این ایندکس تضمین می‌کند یک پزشک در یک تاریخ و ساعت فقط یک رکورد قطعی/درحال رزرو داشته باشد. در نسخه‌های آینده اگر `capacity_per_slot` بیشتر از ۱ نیاز باشد، باید مدل seat یا slot inventory جداگانه اضافه شود.

## جدول تراکنش‌ها

`wp_saas_transactions` رفت‌وبرگشت به درگاه را نگهداری می‌کند. از نسخه `0.4.0` فیلدهای اختصاصی آقای پرداخت و درگاه‌های مشابه هم ذخیره می‌شوند:

- `gateway_name`
- `gateway_transid`
- `gateway_tracking_number`
- `gateway_card_number`
- `gateway_bank`
- `invoice_id`
- `callback_status`
- `request_payload`
- `callback_payload`
- `create_response`
- `verify_payload`
- `verify_response`
- `error_code`
- `error_message`

ایندکس‌های مهم:

```sql
KEY gateway_transid (gateway_transid),
KEY invoice_id (invoice_id),
KEY status (status)
```

قانون مالی: هیچ callback بدون verify نباید باعث تایید نوبت یا ثبت سهم پزشک شود.

## جدول دفتر کل کیف پول

`wp_saas_wallets_ledger` فقط balance نگه نمی‌دارد، بلکه هر ورود و خروج پول را ثبت می‌کند.

قانون:

- `credit`, `refund`, `commission` مقدار مثبت ایجاد می‌کنند.
- `debit`, `wallet_payment`, `settlement` مقدار منفی ایجاد می‌کنند.
- `balance_after` بعد از هر ردیف ثبت می‌شود.

## جدول OTP

`wp_saas_otp_logs` کد خام را ذخیره نمی‌کند و فقط hash، purpose، زمان انقضا، IP و User Agent را نگه می‌دارد.

برای محدودیت ارسال نسخه `1.1.0`، ایندکس ترکیبی زیر اضافه شده است:

```sql
KEY mobile_purpose_created (mobile,purpose,created_at)
```

این ایندکس برای کوئری سریع `COUNT(*)` روی هر موبایل و purpose در بازه زمانی ۱۵ دقیقه استفاده می‌شود.

## Migration

برای migrationهای بعدی باید نسخه دیتابیس با option زیر کنترل شود:

`webtanan_booking_db_version`

در نسخه‌های بعدی بهتر است migrationها idempotent باشند و قبل از تغییر index یا column وجود آن بررسی شود.
# v1.2.2 Tables Added

## `wp_saas_patient_records`

Stores the main medical record for a patient under a doctor. It is keyed by `doctor_id + patient_user_id` and keeps summary, allergies, chronic conditions, current medications, mobile, national code, creator/updater, and timestamps.

## `wp_saas_patient_record_notes`

Stores append-only visit notes for a medical record. Each note can be linked to an appointment and has `visibility=patient|private`.

## `wp_saas_survey_responses`

Stores private survey feedback after an appointment. If the patient allows public display, a pending WordPress comment is also created on the doctor CPT, while this table remains the operational source for reports.
