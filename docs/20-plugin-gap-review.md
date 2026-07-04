# Webtanan Booking Gap Review - v1.4.5

## Final Audit In v1.4.3

- روز بدون ساعت آزاد در تقویم عمومی و مودال رزرو، دکمه «نمایش اولین نوبت آزاد» دارد. تاریخ مقصد از REST خوانده می‌شود و تقویم روی همان بازه جابه‌جا می‌شود.
- ارسال OTP تنها زمانی موفق اعلام می‌شود که IPPanel وضعیت `sent`، `queued` یا `test_mode` برگرداند؛ رکورد کدی که ارسال نشده حذف می‌شود.
- نام پارامتر OTP پترن قابل تنظیم است و باید دقیقاً با placeholder تاییدشده IPPanel برابر باشد. مقدار ارسالی فقط کد عددی است.
- شماره و کد نوشته‌شده با ارقام فارسی، عربی یا لاتین به شکل یکسان پردازش می‌شوند.
- مسیر تخصص‌ها در حالت permalink به شکل `/doctors/specialty/{slug}/` است و URL قدیمی `specialty_id` به مسیر تمیز هدایت می‌شود.
- صفحات خصوصی حساب، پرداخت، پنل‌ها، صف و نظرسنجی از طریق `wp_robots` و فیلتر رسمی Rank Math با `noindex,nofollow` مشخص می‌شوند.
- آرشیو تخصص canonical، title و description اختصاصی دارد؛ فیلترهای جستجوی موقت noindex هستند.
- schema تکراری داخل بدنه صفحه پزشک حذف شد و فقط JSON-LD کامل `Physician` در `wp_head` باقی ماند.
- امتیاز و تعداد نظر ساختگی حذف شد و کارت‌ها فقط دیدگاه‌های تاییدشده دارای امتیاز را نمایش می‌دهند.
- ستون ایندکس‌شده `next_free_slot_cache` اضافه شد؛ کران ۱۵ دقیقه‌ای آن را به‌روزرسانی می‌کند و مرتب‌سازی لیست پزشکان ابتدا از cache استفاده می‌کند.

## UX Audit In v1.4.4

- تمام requestهای استفاده‌شده در `frontend.js` با routeهای ثبت‌شده REST تطبیق داده شدند و مسیر فرانت بدون route متناظر پیدا نشد.
- خطای قطع شبکه در تمام فرم‌ها به پیام فارسی قابل اقدام تبدیل شد.
- فوکوس مودال‌ها، بازگشت فوکوس، کلید Escape و بستن منوی موبایل اصلاح شد.
- منوی بیمار دارای نمای واقعی راهنما است و لینک‌های بیرونی دیگر توسط router داخلی داشبورد بلعیده نمی‌شوند.
- خدمات، مدارک و پرسش‌های پرتکرار پزشک از پنل پزشک و مدیریت قابل ویرایش هستند.
- برنامه هفتگی و روزهای خاص اکنون حذف امن، بررسی مالکیت و ثبت تکراری بدون duplicate دارند.
- کد OTP حتی در `WP_DEBUG` داخل پاسخ REST برگردانده نمی‌شود.

## Recovery And Mobile UX In v1.4.5

- کاربر لاگین‌شده برای ادامه پرداخت نوبت خودش دوباره OTP وارد نمی‌کند.
- مهلت قفل در فاکتور پرداخت مجدد به‌صورت زنده نمایش داده می‌شود؛ پس از پایان، همان ساعت با یک دکمه دوباره بررسی و در صورت آزاد بودن قفل می‌شود.
- خطاهای موقت IPPanel حداکثر سه بار و با قفل کران retry می‌شوند؛ OTP برای جلوگیری از ارسال تکراری خودکار retry نمی‌شود.
- متغیرهای هر پترن پیامک whitelist مستقل دارند و آرگومان‌های نامرتبط به IPPanel ارسال نمی‌شوند.
- در قالب همراه، موبایل فقط یک همبرگر در هدر دارد و لینک‌های نقش‌محور پنل داخل همان منو قرار می‌گیرند؛ سایدبار دسکتاپ حفظ شده است.
- نظر بیمار مستقیماً داخل پنل قابل ثبت و ویرایش است و خطای دیتابیس دیگر پاسخ موفق کاذب تولید نمی‌کند.

## Completed In This Pass

- SMS pattern settings now show all available variables, example pattern codes, and example Persian copy.
- Admin can send normal/free-text SMS to one or many mobile numbers; every recipient is logged in `wp_saas_sms_logs`.
- Doctor/admin/secretary cancellation now always gives full wallet refund for paid appointments.
- Bulk cancellation exists for selected doctor/date in admin and selected/day appointments in doctor dashboard.
- Medical record custom tables were added:
  - `wp_saas_patient_records`
  - `wp_saas_patient_record_notes`
- Doctor dashboard can create/update patient records and append visit notes.
- Patient panel can read patient-visible medical records.
- 15-minute cron was added for waiting-list and survey SMS jobs.
- Signed public REST endpoints were added for live waiting-list position and survey submission.
- Signed SMS links now open public HTML pages instead of raw REST JSON.
- New shortcodes were added for public flows:
  - `[webtanan_booking_waiting_list]`
  - `[webtanan_booking_survey]`
- Admin now has a dedicated `Survey` report screen with doctor/status/rating/search filters and moderation actions.
- Walk-in and online appointments stay in the same appointment table and timeline, with UI badges.
- Main wording fixes: `پیشخوان`, `روزهای خاص`, `برنامه تاریخ خاص`.

## Frontend UI/UX Completed In v1.2.5

- A frontend UI/UX polish layer now covers archive/listing pages, Elementor doctor cards, the single doctor profile, booking modal, checkout, patient panel, doctor dashboard, medical records, waiting-list page, and survey page.
- The single doctor booking CTA now has a stable `#booking` anchor and uses the user-facing `گرفتن نوبت` wording.
- Dashboard headers now use `پیشخوان` instead of a raw product label.
- The UI pass is CSS/markup focused and does not change REST payloads, financial logic, database schema, refunds, or booking transactions.

## Frontend UI/UX Completed In v1.2.6

- `frontend.css` was rebuilt into a single clean design layer to remove conflicting selector generations.
- Public doctor cards now have one shared rendering contract across AJAX archive results, Elementor widgets, and the single-card shortcode.
- The doctor search UI now uses the actual `.wb-search-field` markup and a responsive grid that remains stable on wide and mobile widths.
- Doctor dashboard calendar now renders daily/weekly operational cards instead of the old raw month grid.
- REST payloads received additive display fields only; booking, payment, refund, wallet ledger, and database schema behavior are unchanged.
- Duplicate JavaScript renderers were removed to reduce future UI drift.

## Medical Record Hardening Completed In v1.2.7

- Medical-record file uploads were added with a dedicated custom table: `wp_saas_patient_record_files`.
- Supported file types are `jpg`, `png`, `webp`, and `pdf`, with a 10 MB limit.
- Doctors can upload patient-visible or private files from the patient record tab.
- Patients can see only patient-visible files in their read-only medical-record panel.
- Medical-record audit logging was added with `wp_saas_patient_record_audit_logs`.
- Logged events include record view, patient view, record update, note creation, and file upload.
- Secretary medical-record access now has a separate user meta toggle: `webtanan_secretary_can_manage_records`.

## Production Engine Fixes Completed In v1.3.0

- Seeder trigger is now protected by `is_admin()`, `manage_options`, and nonce action `webtanan_booking_seed_data`.
- The admin dashboard generates the safe seed URL with `wp_nonce_url()`.
- Public doctor slots now use `REST::get_doctor_slots()` with a virtual slot generator.
- Slot output is generated from schedule rules even when no appointment rows exist yet.
- Jalali/Gregorian input dates are normalized before schedule lookup.
- Blocking appointments are overlaid onto virtual slots; cancelled, expired, and stale locked rows do not hide available times.

## Partially Complete

- Medical records support text fields, notes, and uploaded files. Prescription-specific structured data is not yet implemented.
- کد و لاگ خطای واقعی IPPanel تکمیل است، اما تحویل پیامک و پرداخت فقط با credential واقعی دامنه مقصد قابل تایید نهایی است.

## Still Missing / Recommended Next

- Structured prescription module and prescription-specific print/export templates.
- Optional per-note file binding UI; the backend table already supports `note_id`.
- Automated concurrency tests for simultaneous slot locks and repeated payment callbacks.
- Automated browser QA for 390, 768, 1366, and 1920 pixel widths.
- Deeper browser QA against real Elementor templates, sticky theme headers, and production cache plugins.
- Real IPPanel delivery, Rank Math sitemap output and low-value gateway callback must be smoke-tested on the deployed WordPress site.

## Operational Notes

- Plugin version is `1.4.5`. Full-canvas dashboards are intentionally not used; dashboards remain under the active theme header/footer according to the approved product layout.
- `DB::create_tables()` still runs on boot when the stored version differs.
- Refund idempotency remains centralized in `Booking::cancel_appointment()` and `wp_saas_wallets_ledger`.
- Survey and waiting-list public access relies on HMAC tokens generated from appointment id/code/mobile and purpose.
