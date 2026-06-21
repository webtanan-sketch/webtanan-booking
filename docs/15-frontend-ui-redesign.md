# نسخه 0.9.0: بازطراحی ظاهر فرانت‌اند

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
