# چک‌لیست امنیت

## ورودی و خروجی

- همه ورودی‌ها sanitize شوند.
- همه خروجی‌ها escape شوند.
- برای RESTهای authenticated از `X-WP-Nonce` استفاده شود.
- برای عملیات مالی به callback خام درگاه اعتماد نشود.

## OTP

- OTP خام در دیتابیس ذخیره نشود.
- حداکثر تلاش هر کد محدود باشد.
- ارسال کد در بازه ۱۰ دقیقه محدود باشد.
- IP و User Agent ثبت شود.
- در production مقدار `debug_otp` نباید فعال باشد.

## دسترسی

- بیمار فقط نوبت خودش را ببیند یا لغو کند.
- پزشک فقط به doctor_id خودش دسترسی داشته باشد.
- منشی فقط به doctor_idهای assign شده در user meta دسترسی داشته باشد.
- ادمین فقط با capabilityهای مشخص وارد بخش مالی شود.

## مالی

- هر تغییر پولی باید ledger entry داشته باشد.
- هر payment callback باید verify شود.
- هر ref_id و authority ذخیره شود.
- وضعیت نوبت و تراکنش idempotent تغییر کند.
- تسویه پزشک باید بعد از پرداخت بانکی، ledger منفی `settlement` ثبت کند.

## کش

این داده‌ها در HTML cache نشوند:

- اسلات‌های آزاد
- وضعیت lock
- موجودی کیف پول
- وضعیت پرداخت
- رسید پرداخت

## قبل از انتشار عمومی

- تست race condition با درخواست همزمان
- تست پرداخت موفق و ناموفق
- تست late callback
- تست لغو و refund
- تست دسترسی منشی به پزشک غیرمجاز
- تست rate limit OTP

## سخت‌سازی نسخه 1.0.4

- صفحه مدیریت منشی‌ها فقط کاربران دارای نقش `webtanan_secretary` را می‌پذیرد.
- پزشکان مجاز منشی فقط در user meta با کلید `webtanan_assigned_doctor_ids` ذخیره می‌شوند.
- دسترسی مالی منشی فقط با user meta `webtanan_secretary_can_view_finance = yes` فعال است.
- endpointهای `doctor-dashboard/*` برای منشی فقط از لیست `webtanan_assigned_doctor_ids` پزشک انتخاب می‌کنند؛ فیلد `secretary_user_id` مجوز مشاهده داده نیست.
- endpointهای مالی `doctor-dashboard/wallet`، `doctor-dashboard/settlements` و `doctor-dashboard/settlement-request` علاوه بر assignment، toggle مالی را هم بررسی می‌کنند و در صورت عدم مجوز 403 برمی‌گردانند.
- در `POST /appointments/cancel` مقدار `cancelled_by` از کلاینت پذیرفته نمی‌شود؛ backend actor را از مالکیت بیمار، ادمین بودن، پزشک بودن یا منشی assign شده تشخیص می‌دهد.
- refund لغو نوبت فقط پس از lock شدن ردیف نوبت با `FOR UPDATE` و داخل transaction انجام می‌شود.
- قبل از ثبت refund، ledger برای `related_appointment_id + user_type=patient + entry_type=refund` بررسی می‌شود تا استرداد دوباره ساخته نشود.
