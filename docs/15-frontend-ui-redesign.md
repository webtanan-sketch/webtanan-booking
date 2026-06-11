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
