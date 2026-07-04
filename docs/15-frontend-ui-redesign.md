# نسخه 0.9.0: بازطراحی ظاهر فرانت‌اند

## Empty-day recovery در نسخه 1.4.3

- اگر روز انتخاب‌شده اسلات نداشته باشد یا همه ساعت‌ها پر/گذشته باشند، یک CTA کوچک برای نمایش اولین نوبت آزاد ظاهر می‌شود.
- CTA از `GET /doctors/{id}/next-available` فقط یک اسلات می‌گیرد، نوار روزها را به تاریخ مقصد منتقل می‌کند و ساعت‌های همان روز را دوباره بارگذاری می‌کند.
- این رفتار هم در shortcode تقویم و هم در مودال رزرو صفحه پزشک فعال است و داده زمان‌دار در HTML کش‌شده ذخیره نمی‌شود.

این فاز روی ظاهر بخش‌هایی تمرکز دارد که بیمار، پزشک و منشی می‌بینند. منطق رزرو، پرداخت، کیف پول و دسترسی‌ها تغییر معماری نداشته و همچنان بر پایه REST و جداول اختصاصی عمل می‌کند.

## محدوده بازطراحی

- آرشیو عمومی پزشکان با هدر کاربردی، شمارنده پزشکان فعال، فیلترهای خواناتر و کارت‌های منظم‌تر.
- کارت پزشک در قالب عمومی، شورت‌کد و خروجی AJAX با یک زبان طراحی واحد.
- صفحه اختصاصی پزشک با وضعیت تایید، روش‌های پرداخت، CTA دریافت نوبت، کارت اطلاعات و گالری مطب.
- ویجت رزرو با هدر داخلی، انتخابگر تاریخ شمسی، اسلات‌های رنگ‌بندی‌شده و فرم پرداخت تمیزتر.
- پروفایل پزشک اکنون به جای رندر مستقیم تقویم در HTML اولیه، CTA و popup رزرو دارد. خود popup پس از کلیک کاربر از REST داده می‌گیرد.
- اولین نوبت آزاد در پروفایل پزشک و کارت پزشک فقط از `GET /doctors/{id}/next-available` بارگذاری می‌شود و در HTML کش‌شده قرار نمی‌گیرد.
- ورود OTP با هدر مشخص و فرم ساده‌تر.
- پنل بیمار و داشبورد پزشک/منشی با ظاهر SaaS، ناوبری روشن‌تر، کارت‌های آماری، جدول‌ها، تقویم و مودال‌های هماهنگ.
- رسید نوبت در قالب صفحه و پنجره چاپی با چیدمان تمیزتر.

## اصول UI

- RTL کامل و تایپوگرافی مناسب فارسی.
- رنگ‌بندی پزشکی آرام با تمایز وضعیت‌های موفق، هشدار، لغو، پرداخت حضوری و رزرو.
- استفاده از radius کنترل‌شده، border ظریف و سایه‌های کم‌قدرت.
- نمایش داده‌های حساس زمانی مثل اسلات‌ها و اولین نوبت آزاد همچنان از REST انجام می‌شود.
- HTML اولیه صفحه پزشک قابل cache است، اما وضعیت نوبت‌ها و اولین زمان آزاد در کلاینت به‌روزرسانی می‌شود.

## جریان Popup رزرو پزشک

۱. کاربر در صفحه پزشک روی دکمه دریافت نوبت کلیک می‌کند.
۲. modal سبک با روزهای افقی شمسی باز می‌شود. مقدار داخلی هر روز همچنان Gregorian `YYYY-MM-DD` است.
۳. با انتخاب هر روز، اسلات‌ها از `GET /doctors/{id}/slots?date=YYYY-MM-DD` دریافت می‌شوند.
۴. وضعیت اسلات‌ها با رنگ‌های جداگانه نمایش داده می‌شود: آزاد، در حال رزرو و رزروشده.
۵. پس از انتخاب اسلات، فرم اطلاعات بیمار نمایش داده می‌شود.
۶. submit فرم، `POST /appointments/lock` را صدا می‌زند و `lock_token` و `locked_until` را نگه می‌دارد.
۷. اگر کاربر وارد نشده باشد، مرحله OTP داخل همان modal نمایش داده می‌شود و پس از تایید، همان lock برای پرداخت حفظ می‌شود.
۸. مرحله پرداخت، کیف پول بیمار و درگاه‌های فعال را از REST می‌گیرد و سپس `POST /appointments/pay` را با `method=wallet` یا `method=online` اجرا می‌کند.

این workflow هیچ اسلات، وضعیت lock یا موجودی کیف پولی را در PHP اولیه رندر نمی‌کند.

## نکات نگهداری

- کلاس‌های اصلی قبلی حفظ شده‌اند تا شورت‌کدها و ویجت‌های Elementor نشکنند.
- خروجی dynamic در `assets/js/frontend.js` با کارت عمومی server-side هماهنگ شد.
- قالب رسید اکنون کلاس پایه `webtanan-booking` دارد تا متغیرهای CSS افزونه درست اعمال شوند.
- اتصال lock مهمان به کاربر لاگین‌شده در مرحله پرداخت انجام می‌شود تا OTP داخل modal باعث از دست رفتن `lock_token` نشود.
# v1.2 Payment Result And UI Polish

- The single doctor sticky card no longer renders the embedded calendar. It shows only doctor summary, first available slot loaded via REST, and the "Book appointment" CTA. The calendar remains inside the modal.
- The booking payment step now behaves like a checkout: appointment summary, booking service fee, optional visit price note, wallet option, and gateway options.
- Slot colors were clarified: available is green, locked is amber, booked/expired is muted, and cancelled is danger-tinted.
- A full payment result page renders a printable appointment invoice instead of raw gateway JSON.
- Resume-payment UI is available through `[webtanan_booking_resume_payment]` and the automatic result page.
- Admin settings include plugin-wide font family plus `woff`, `woff2`, or `ttf` upload/URL support.
# v1.2.4 Doctor Listing Template

- The doctors archive page was rebuilt as a wide, RTL-first SaaS discovery page.
- Static PHP doctor loops were removed from the archive template to avoid cache collisions with first available slots.
- The CPT archive, `[webtanan_booking_doctors_archive]`, Elementor Doctor Search, and Elementor Doctor List now share the same AJAX card renderer.
- Doctor cards use the unified layout: photo, tinted badges, title, specialty/address, service fee, visit fee, dynamic first available slot, and clear booking/profile actions.
- Grid and list layouts are responsive: wide desktop uses dense card grids, list mode uses a wider media column, and mobile stacks into a single column.

# v1.2.5 Frontend UX Polish

- A final frontend polish layer was added to `assets/css/frontend.css` for the public doctor archive, Elementor discovery widgets, doctor cards, single doctor profile, booking modal, checkout, payment result, patient panel, doctor dashboard, medical records, waiting list, and survey pages.
- The single doctor booking area now has a stable `#booking` anchor and the primary CTA uses the friendlier Persian wording `گرفتن نوبت`.
- Dashboard section kickers were changed from the raw product label to `پیشخوان` so patient and clinic panels feel less technical.
- Time-sensitive data remains cache-safe: doctor availability, first available appointment, slots, wallet state, payment state, queue state, and survey state are still loaded through REST/AJAX.
- Wide desktop layouts now use larger constrained containers, stable CSS grids, improved card spacing, and text overflow guards for names, clinic titles, financial values, and table cells.
- Mobile behavior was tightened for modal date strips, slot grids, profile cards, dashboard navigation, doctor cards, checkout summaries, and public flow pages.
- Slot colors are normalized across modal and dashboard views: green for available, amber for in-progress, muted for booked/expired, and danger tint for cancelled.

# v1.2.6 Frontend Rebuild

- `assets/css/frontend.css` was rebuilt as a clean frontend layer instead of adding another polish override. The file is now split by responsibility: tokens/base, controls, doctor discovery, single doctor, booking modal/checkout, dashboards, calendar, tables, records/profile/wallet, public waiting/survey, and responsive rules.
- Doctor archive, Elementor Doctor Search/List, AJAX results, and the single Doctor Card shortcode now share the same card contract: photo, title, specialty/address, payment badges, booking service fee, visit fee, dynamic first available slot, and two CTAs.
- The doctor search form now styles the real `.wb-search-field` markup and uses responsive CSS grid so specialty/province/city/payment/sort filters do not collapse on wide screens.
- The single doctor sidebar remains cache-safe and lightweight: no embedded calendar is rendered in the sticky card; the booking flow still opens inside the modal and loads days/slots through REST.
- Doctor dashboard calendar was changed from a raw month grid to practical daily/weekly appointment cards with patient name, source label, time range, payment label, and status badge.
- REST responses were extended additively with display-only fields such as `display_status`, `display_payment`, `source_label`, `patient_display_name`, `time_range`, and `slot_tone`. Existing raw fields remain available for backward compatibility.
- Duplicate JavaScript render paths were removed: doctor cards now delegate to `doctorCardUnified()`, and the patient wallet keeps a single renderer with top-up UI.
- The dashboard wrappers still render below the site header/footer, but the plugin content uses a wide independent layout so it is not trapped inside narrow theme containers.

# v1.3 Plugin-Owned Frontend Direction

- تصمیم معماری UI تغییر کرد: ظاهر اصلی وب‌تنان داخل خود پلاگین نگهداری می‌شود و قالب فقط نقش پوسته خام سایت را دارد.
- فونت محلی Vazir به پلاگین اضافه شد و `frontend.css` آن را برای خروجی‌های عمومی افزونه بارگذاری می‌کند.
- Font Awesome و Chart.js به‌صورت local در پوشه `assets/vendor` افزونه قرار گرفتند تا وابستگی CDN حذف شود.
- صفحه لیست پزشکان و شورت‌کد جستجو به ساختار discovery با search bar بالا و sidebar فیلتر منتقل شدند.
- کارت پزشک اکنون اطلاعات بیشتری نمایش می‌دهد: تصویر، نام، تخصص لینک‌شده، امتیاز نمونه، وضعیت آنلاین/حضوری، آدرس، مطب، کد نظام پزشکی، تعرفه ویزیت، پیش‌پرداخت دریافت نوبت و اولین نوبت آزاد AJAX.
- صفحه اختصاصی پزشک بازنویسی شد: hero پزشک، تب‌های پروفایل، خدمات و تخصص‌ها، آدرس و تماس، نظرات بیماران، گالری و FAQ فقط در صورت وجود داده نمایش داده می‌شوند.
- کارت رزرو پروفایل پزشک در sidebar سمت راست دسکتاپ قرار می‌گیرد و در موبایل به جریان طبیعی صفحه برمی‌گردد. اسلات‌ها در HTML اولیه چاپ نمی‌شوند و انتخاب زمان داخل modal از REST انجام می‌شود.
- صفحه لندینگ پلاگین با شورت‌کد `[webtanan_booking_homepage]` اضافه شد و شامل hero، جستجوی سریع، آمار، تخصص‌ها، پزشکان پیشنهادی و مراحل گرفتن نوبت است.
- داشبورد پزشک از Chart.js محلی استفاده می‌کند و endpoint خلاصه پزشک، داده هفتگی نوبت و درآمد را به‌صورت افزایشی برمی‌گرداند.

# v1.4.0 Turnkey SaaS Theme + Plugin UI

- The companion `Webtanan SaaS Theme` now owns the public shell: landing page, native header/footer fallback, Elementor-safe locations, and the premium `saas_doctors` archive.
- Theme typography is centralized through local `IRANSans` font-face declarations, with `IRANYekanXFaNum` as a local fallback. Plugin frontend/admin CSS now inherits the active theme font and no longer hardcodes `Tahoma`.
- A dashboard admin setup page was added to the theme with two safe actions: idempotent page setup and optional booking demo-data setup through the plugin seeder.
- The native header CTA opens a global patient OTP modal through `data-webtanan-modal-trigger="auth"`. If the booking plugin is inactive, the CTA falls back to the configured patient portal URL.
- `front-page.php` renders a conversion-focused medical SaaS intro, dynamic doctor search, clickable specialty pills, and featured active doctors ordered by cached next slot when available.
- `archive-saas_doctors.php` renders the premium discovery experience and delegates doctor results to the plugin AJAX search widget so first available appointments remain cache-safe.

# v1.3.2 Role-Aware Login And Patient Booking

- ورود و ثبت‌نام از یک صفحه مستقل OTP انجام می‌شود و شماره موبایل پس از تایید به نقش واقعی حساب متصل می‌ماند.
- پزشک و منشی پس از ورود به پیشخوان پزشک هدایت می‌شوند؛ بیمار به پنل بیمار می‌رود.
- بیمار جدید پیش از رزرو باید نام، نام خانوادگی و کد ملی معتبر را تکمیل کند.
- انتخاب ساعت برای کاربر مهمان قفل ایجاد نمی‌کند. ورود، تکمیل پروفایل و انتخاب مراجعه‌کننده قبل از `POST /appointments/lock` انجام می‌شود.
- بیمار می‌تواند «خودم» یا یکی از افراد ذخیره‌شده در بخش «افراد من» را انتخاب کند.
- اطلاعات هویتی نوبت از سمت سرور و بر اساس مالکیت حساب ساخته می‌شود؛ نام، کد ملی و موبایل ارسال‌شده از کلاینت مبنای رزرو نیست.
- کارت پزشک نشان پرداخت ندارد و تایید پزشک با نشان آبی کنار نام نمایش داده می‌شود.
- هدر داخلی تکراری از پنل بیمار و پزشک حذف شد؛ shell سایت از قالب و منوی نقش‌محور از افزونه تامین می‌شود.
- شمارش معکوس رزرو از timestamp واقعی سرور استفاده می‌کند تا اختلاف timezone باعث پایان فوری مهلت نشود.
- Doctor specialties are clickable in theme cards, plugin AJAX cards, single doctor profiles, and shortcode-rendered doctor cards using `/?post_type=saas_doctors&specialty_id={id}`.
- The printable receipt template was rebuilt as a clean invoice: tracking code, patient, doctor, date/time, paid prepayment, remaining visit amount, clinic address, and print action.
- Visible frontend developer copy such as API/loading implementation notes was removed from public templates. Time-sensitive information is still loaded via REST/AJAX, but the user-facing copy is now product-friendly.

# v1.4.1 Home Landing + Doctor Card Enrichment

- The companion theme home page was expanded from a simple hero/search layout into a full SaaS landing page: hero actions, live system metrics, highlighted search, specialty pills, benefits, featured doctors, three-step booking explanation, and final CTA.
- Featured doctors now show payment badges, specialty link, clinic label/address, booking prepayment, visit price, and next-slot CTA where cached data is available.
- The public doctor REST response now includes safe display fields for cards: `profile_excerpt`, `medical_system_number`, `doctor_code`, `clinic_short_address`, and existing clinic/payment metadata.
- The shared AJAX/Elementor doctor card renderer now displays trust metadata, clinic/address, a profile excerpt, payment badges, service fee, visit fee, first available slot, and clear booking/profile actions.
- The PHP `[webtanan_booking_doctor_card]` output was aligned with the AJAX card contract so Elementor, shortcode, archive, and theme discovery cards no longer feel like different products.

# v1.3.1 Product Polish

- Patient-facing copy now uses a strict lexicon layer in `assets/js/frontend.js`; raw database enums such as `locked`, `booked`, `pay_at_clinic`, and `expired_lock_wallet_charged` are never printed directly.
- The public meaning of `booking_fee` is standardized as `پیش‌پرداخت دریافت نوبت` across doctor cards, checkout, receipts, payment result pages, and resume-payment UI.
- Booking modal flow is now a 3-step wizard: time selection, mobile/OTP confirmation, and final checkout.
- After a temporary reservation is created, the modal shows a sticky trust banner with a live countdown based on `locked_until`; if the timer expires, the modal resets to time selection with a polite Persian message.
- Slot labels are context-aware: available slots say `آزاد`, unavailable patient-facing slots say `پر شده`, and dashboard/payment tables use the fuller Persian status labels.
- Gateway choices render as selectable radio-cards. Payment only starts from the final green CTA: `پرداخت و ثبت قطعی نوبت`.
- Developer terms like `قفل نوبت`, `Slot Locked`, raw gateway ids, and raw unknown payment statuses were removed from visible UI fallbacks.

# v1.4.2 Sample HTML Alignment

- خروجی‌های اصلی فرانت با قالب نمونه HTML هماهنگ شدند، بدون تغییر قرارداد REST یا منطق مالی.
- جستجوی پزشک اکنون DOM نمونه را استفاده می‌کند: `search-section`, `search-row`, `filter-row`, `filter-group`, `results-header`, `doctor-list`, و `doctor-card`.
- کارت AJAX پزشک، شورت‌کد `[webtanan_booking_doctor_list]`، شورت‌کد `[webtanan_booking_doctor_card]` و ویجت‌های Elementor همگی زیر لایه scoped با کلاس `webtanan-sample-ui` رندر می‌شوند.
- صفحه پروفایل پزشک کلاس‌های نمونه را روی داده‌های پویا دارد: `profile-hero`, `profile-avatar`, `profile-info`, `profile-tabs`, `profile-content`, `profile-main`, `profile-sidebar`, و `appointment-form-side`.
- داشبورد پزشک و پنل بیمار با shell نمونه هماهنگ شدند: `sample-dashboard-header`, `sidebar`, `nav-item`, `main-content`, `stats-grid`, `dashboard-grid`, `card`, `appointment-item`, و `footer-bar`.
- قالب همراه نیز برای `front-page.php` و `archive-saas_doctors.php` از همان لایه sample استفاده می‌کند تا shell قالب و خروجی پلاگین visually یکپارچه باشند.
- منوی کناری داشبوردها در موبایل با `wb-sample-sidebar-toggle` به drawer تبدیل می‌شود و در دسکتاپ sidebar ثابت نمونه را حفظ می‌کند.
## v1.4 Sample DOM Reset

- قالب `webtanan-saas-theme` مالک shell عمومی سایت است: `site-header`, `header-inner`, `main-nav`, `site-footer`.
- هدر دیگر `<main>` باز نمی‌کند؛ هر تمپلیت صفحه، `main` مخصوص خود را می‌سازد تا nesting خراب ایجاد نشود.
- صفحه اصلی با کلاس‌های sample بازسازی شد: `hero-section`, `search-form`, `specialties-grid`, `doctor-card`.
- آرشیو پزشکان داخل قالب با `archive-layout`, `archive-sidebar`, `archive-main`, `doctors-grid` ساخته می‌شود و نتایج فقط از REST/AJAX پلاگین پر می‌شوند.
- پروفایل پزشک به قرارداد sample نزدیک شد: `profile-layout`, `profile-main`, `profile-hero`, `hero-avatar`, `hero-info`, `content-box`, `profile-sidebar`, `booking-widget`.
- کارت پزشک در JS و PHP به ساختار واحد sample تغییر کرد: `doctor-card`, `doc-header`, `doc-avatar`, `doc-info`, `doc-meta`.
- CSS قدیمی و متداخل پلاگین با یک لایه functional جایگزین شد؛ پلاگین فونت را از قالب ارث می‌برد و فقط stateهای لازم برای رزرو، مودال، داشبورد، جدول و کارت‌ها را استایل می‌کند.
- سایدبار داشبورد از `Sidebar_Menu::render()` ساخته می‌شود و خروجی آن کلاس‌های sample dashboard را دارد: `dashboard-sidebar`, `sidebar-brand`, `sidebar-menu`, `nav-item`.

## v1.4.0 Account, Payment And Mobile Completion

- فرم OTP دارای انتخاب بیمار/پزشک است؛ حساب پزشک تا زمان تایید مدیر فقط یک درخواست pending باقی می‌ماند.
- تکمیل پروفایل بعد از OTP با completion token کوتاه‌عمر انجام می‌شود تا تغییر هم‌زمان کوکی ورود باعث خطای nonce نشود.
- نوبت پرداخت‌نشده در پنل بیمار، فاکتور و انتخاب کیف پول/درگاه را بدون درخواست OTP دوباره باز می‌کند.
- نمای اول پنل بیمار سه نوبت آینده واقعی را نمایش می‌دهد و دیگر متن ارجاعی جایگزین داده نیست.
- کارت نوبت پزشک/منشی عملیات حضور، عدم حضور، پرداخت مطب، لغو و پرونده را در همان کارت نگه می‌دارد؛ ساعت آزاد نیز CTA ثبت حضوری دارد.
- مودال‌ها به `body` منتقل می‌شوند تا overflow یا stacking context قالب باعث نمایش پایین صفحه یا حذف backdrop نشود.
- منوی موبایل پنل‌ها drawer سمت راست با backdrop، بستن با انتخاب لینک و کنترل `aria-expanded` دارد.
- فرم‌های پروفایل پزشک، پرونده پزشکی، نوبت حضوری و روزهای خاص روی موبایل تک‌ستونه و لمس‌پذیر هستند.
## تکمیل تجربه پرداخت، اسلات و صفحه اصلی - 2026-06-28

- رسید وب و رسید چاپی اکنون از یک قرارداد بصری مشترک استفاده می‌کنند: کد پیگیری برجسته، مشخصات نوبت، جدول هزینه خدمات رزرو و حالت چاپ بدون هدر سایت.
- نتیجه پرداخت ناموفق برای بیمار واردشده مستقیماً فاکتور و انتخاب کیف پول/درگاه را نمایش می‌دهد و OTP تکراری درخواست نمی‌کند. بازیابی عمومی همچنان با کد نوبت و OTP محافظت می‌شود.
- ساعت‌های گذشته امروز در REST و دامنه رزرو با وضعیت `past` غیرفعال می‌شوند و کنترل نهایی سمت سرور مانع قفل‌کردن زمان گذشته است.
- راهنمای رنگ اسلات‌ها با نشان رنگی روشن برای ساعت آزاد، در حال رزرو، پرشده و زمان گذشته بازطراحی شد.
- لینک تخصص‌ها همیشه به آرشیو واقعی `saas_doctors` می‌رود و کارت‌های صفحه اصلی، جستجو، آرشیو و Elementor از renderer مشترک افزونه استفاده می‌کنند.
- مدیر می‌تواند نمایش، ترتیب، عنوان و تعداد آیتم سکشن‌های تخصص‌ها، جدیدترین پزشکان، پزشکان پرمراجعه و سه سکشن سفارشی را از تنظیمات کنترل کند.
