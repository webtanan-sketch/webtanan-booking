# چک‌لیست امنیت

## ورودی و خروجی

- همه ورودی‌ها sanitize شوند.
- همه خروجی‌ها escape شوند.
- برای RESTهای authenticated از `X-WP-Nonce` استفاده شود.
- permission callbackهای authenticated علاوه بر `is_user_logged_in()` باید nonce معتبر `wp_rest` را با `X-WP-Nonce` یا `_wpnonce` بررسی کنند.
- برای عملیات مالی به callback خام درگاه اعتماد نشود.

## OTP

- OTP خام در دیتابیس ذخیره نشود.
- حداکثر تلاش هر کد محدود باشد.
- ارسال OTP برای هر شماره موبایل و purpose حداکثر ۳ بار در بازه ۱۵ دقیقه مجاز است.
- محدودیت ارسال OTP باید با کوئری `COUNT(*)` روی جدول `wp_saas_otp_logs` و شرط‌های `mobile`، `purpose` و `created_at` بررسی شود؛ رکوردها نباید برای شمارش به PHP کشیده شوند.
- در صورت عبور از محدودیت، endpoint باید خطای `429 Too Many Requests` با پیام فارسی شفاف برگرداند.
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

## سخت‌سازی نسخه 1.1.0

- schema پزشک از hook `wp_head` ساخته می‌شود و فقط در صفحه `saas_doctors` عمومی، برای پزشک فعال و تاییدشده خروجی می‌دهد.
- JSON-LD فیلد خالی تولید نمی‌کند؛ کلیدهای بدون مقدار مثل تصویر، آدرس، تلفن یا aggregate rating حذف می‌شوند.
- `aggregateRating` فقط وقتی comment meta امتیاز معتبر بین ۱ تا ۵ وجود داشته باشد خروجی داده می‌شود.
- محدودیت ارسال OTP با کلیدهای `otp_rate_limit_max_sends` و `otp_rate_limit_window_minutes` کنترل می‌شود؛ مقدار پیش‌فرض ۳ درخواست در ۱۵ دقیقه است.
- جدول OTP برای rate limit ایندکس ترکیبی `mobile_purpose_created (mobile,purpose,created_at)` دارد.
- endpointهای authenticated مثل پنل بیمار، داشبورد پزشک، لغو نوبت، کیف پول و عملیات مالی فقط با nonce معتبر REST اجازه اجرا می‌گیرند.
- مسیرهای public مثل مشاهده پزشکان، اسلات‌ها، ارسال OTP و تایید OTP همچنان public هستند؛ تایید OTP بعد از موفقیت nonce تازه برای ادامه flow authenticated برمی‌گرداند.
