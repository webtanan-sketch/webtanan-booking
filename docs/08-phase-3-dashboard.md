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

- خلاصه نوبت‌ها
- نوبت‌های آینده
- سوابق نوبت
- کیف پول
- لغو نوبت‌های مجاز
- مشاهده رسید

APIهای بیمار:

- `GET /patient-panel/summary`
- `GET /patient-panel/appointments`
- `GET /patient-panel/wallet`
- `GET /appointments/{id}/receipt`

## محدودیت‌های باقی‌مانده

- UI تقویم هنوز input تاریخ مرورگر است؛ نمایش عنوان روز با تقویم فارسی انجام می‌شود، ولی انتخابگر شمسی اختصاصی باید در فاز بعدی اضافه شود.
- سطح دسترسی مالی منشی با user meta کنترل می‌شود و هنوز صفحه مدیریت اختصاصی برای assign کردن منشی ساخته نشده است.
- تست همزمانی و تست مرورگر واقعی باید روی نصب وردپرس مقصد انجام شود.
