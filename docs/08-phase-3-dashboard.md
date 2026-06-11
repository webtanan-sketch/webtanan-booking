# فاز ۳: داشبورد پزشک، منشی و پنل بیمار

این فاز داشبورد فرانت‌اند را روی همان معماری فاز اول سوار می‌کند. ورود به پیشخوان وردپرس برای کار روزانه پزشک، منشی و بیمار لازم نیست.

## شورت‌کدها

- `[webtanan_booking_doctor_dashboard]`
- `[webtanan_booking_patient_panel]`

## داشبورد پزشک و منشی

بخش‌های اضافه‌شده:

- پیش‌خوان امروز
- تقویم نوبت‌دهی
- لیست بیماران
- برنامه کاری
- مرخصی‌ها و استثنائات
- کیف پول و مالی
- ثبت نوبت حضوری

## APIهای جدید

namespace:

`/wp-json/saas/v1`

مسیرها:

- `GET /doctor-dashboard/context`
- `GET /doctor-dashboard/summary`
- `GET /doctor-dashboard/appointments`
- `POST /doctor-dashboard/appointments`
- `POST /doctor-dashboard/appointments/{id}/payment`
- `POST /doctor-dashboard/appointments/{id}/status`
- `GET /doctor-dashboard/calendar`
- `GET /doctor-dashboard/schedules`
- `POST /doctor-dashboard/schedules`
- `GET /doctor-dashboard/exceptions`
- `POST /doctor-dashboard/exceptions`
- `GET /doctor-dashboard/patients`
- `GET /doctor-dashboard/wallet`
- `GET /doctor-dashboard/settlements`
- `POST /doctor-dashboard/settlement-request`

## ثبت نوبت حضوری

منشی یا پزشک می‌تواند برای یک slot داخل برنامه کاری، نوبت حضوری ثبت کند. وضعیت پرداخت می‌تواند یکی از این موارد باشد:

- `cash_at_clinic`
- `pos_at_clinic`
- `unpaid`

برای پرداخت حضوری، بدهی کمیسیون پلتفرم در ledger پزشک ثبت می‌شود و برای پلتفرم entry نوع `commission` ایجاد می‌شود.

## منشی

منشی فقط به پزشک‌هایی دسترسی دارد که شناسه آن‌ها در user meta زیر ثبت شده باشد:

`webtanan_assigned_doctor_ids`

برای دسترسی مالی منشی، مقدار user meta زیر باید `yes` باشد:

`webtanan_secretary_can_view_finance`

## پنل بیمار

بخش‌های اضافه‌شده:

- نوبت‌های آینده
- سوابق نوبت
- کیف پول
- لغو نوبت‌های مجاز
- مشاهده رسید

به‌روزرسانی 1.0.5:

- شورت‌کد `[webtanan_booking_patient_panel]` پنل را با سه تب اصلی «نوبت‌های آینده»، «سوابق نوبت» و «کیف پول» نمایش می‌دهد.
- تب نوبت‌های آینده از `GET /patient-panel/appointments?scope=upcoming` استفاده می‌کند و برای نوبت‌های مجاز دکمه لغو نشان می‌دهد.
- لغو نوبت از طریق مودال تایید انجام می‌شود و سپس فقط `appointment_id` و `reason` به `POST /appointments/cancel` ارسال می‌شود؛ سمت سرور مالکیت بیمار و قوانین refund را محاسبه می‌کند.
- تب سوابق از `GET /patient-panel/appointments?scope=history` استفاده می‌کند و رسید هر نوبت از endpoint مالکیت‌دار `GET /appointments/{id}/receipt` باز می‌شود.
- تب کیف پول از `GET /patient-panel/wallet` استفاده می‌کند و ledger را به صورت جدول شامل نوع entry، مبلغ، `balance_after`، توضیح و `appointment_code` مرتبط نمایش می‌دهد.
- endpoint کیف پول بیمار ledger را از جدول اختصاصی `wp_saas_wallets_ledger` می‌خواند و برای نمایش کد نوبت فقط روی جدول اختصاصی نوبت‌ها join می‌زند.

APIهای بیمار:

- `GET /patient-panel/summary`
- `GET /patient-panel/appointments`
- `GET /patient-panel/wallet`
- `GET /appointments/{id}/receipt`
- `POST /appointments/cancel`

## محدودیت‌های باقی‌مانده

- UI تقویم هنوز input تاریخ مرورگر است؛ نمایش عنوان روز با تقویم فارسی انجام می‌شود، ولی انتخابگر شمسی اختصاصی باید در فاز بعدی اضافه شود.
- تست همزمانی و تست مرورگر واقعی باید روی نصب وردپرس مقصد انجام شود.
# v1.2 Patient Wallet And Doctor Profile Dashboard

- Patient panel wallet tab now includes online wallet top-up with preset amounts and a custom amount field.
- The patient wallet list renders ledger entries with friendly labels and current `balance_after`.
- Doctor dashboard context now returns `can_switch_doctors` and `can_edit_profile`.
- A doctor role user sees only the doctor row linked to their own `user_id`; admin users can switch doctors.
- The new "Profile" tab lets the doctor edit display name, summary, biography, medical system number, clinic name/address/phone, IBAN, account owner, featured image, and gallery.
- Front-end profile upload uses secure REST upload and accepts only `jpg`, `png`, and `webp` images up to 5 MB.
# v1.2.2 Dashboard Additions

## Doctor Dashboard

- New tab: `پرونده بیماران`.
- Doctors can open a patient record from today's appointments or the patient list.
- Record fields: summary, allergies, chronic conditions, current medications.
- Visit notes support two visibility modes: `patient` and `private`.
- Bulk cancellation is available from today's appointment view:
  - Cancel selected appointments.
  - Cancel all active appointments for the selected day.
  - Every item is processed through the same idempotent cancellation/refund engine.

## Patient Panel

- New tab: `پرونده پزشکی`.
- Patients see only their own records and only notes marked as visible to patient.
- The patient view is read-only in this phase.

## Wording Update

- `پیش‌خوان` is normalized to `پیشخوان`.
- `مرخصی‌ها` is replaced with `روزهای خاص` / `برنامه تاریخ خاص`.
- Walk-in appointments remain in the same appointment table and timeline as online appointments; the UI distinguishes them with `حضوری` and `آنلاین` badges.
