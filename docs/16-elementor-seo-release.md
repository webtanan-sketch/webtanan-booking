# نسخه 1.0.0: تکمیل لایه Elementor و SEO

این فاز ادامه مستقیم بازطراحی فرانت‌اند است و روی قابل استفاده کردن افزونه برای صفحه‌ساز Elementor و صفحات عمومی قابل ایندکس تمرکز دارد. داده‌های حساس زمانی همچنان از REST بارگذاری می‌شوند تا کش HTML باعث نمایش نوبت اشتباه نشود.

## تغییرات پیاده‌سازی

- شورت‌کد `[webtanan_booking_doctor_search]` اکنون پارامترهای `per_page`، `specialty_id`، `province_id`، `city_id`، `payment_filter`، `sort` و `show_filters` را می‌پذیرد.
- شورت‌کد `[webtanan_booking_doctor_list]` با `province_id`، `city_id`، `payment_filter` و `sort` هماهنگ شد.
- API `GET /wp-json/saas/v1/doctors` از فیلترهای `city_id`، `province_id`، `payment_filter` و مرتب‌سازی `sort=first_available` پشتیبانی می‌کند.
- فرم مدیریت پزشک و متاباکس CPT فیلدهای `province_id` و `city_id` را ذخیره می‌کنند.
- آرشیو عمومی پزشکان فیلترهای شهر و استان را به کوئری server-side اضافه کرد.
- ویجت‌های Elementor جستجوی پزشک و لیست پزشکان کنترل‌های واقعی برای تخصص، شهر، استان، روش پرداخت و مرتب‌سازی دارند.
- Schema صفحه پزشک از نوع `Physician` با `@id`، توضیح، `priceRange` و `availableService` کامل‌تر شد.

## به‌روزرسانی 1.0.5: جستجوی AJAX پیشرفته

- کلاس `includes/Elementor/class-doctor-search-widget.php` کنترل‌های Elementor برای `per_page`، متن جستجوی پیش‌فرض، placeholder، تخصص، استان، شهر، روش پرداخت، چیدمان شبکه/لیست و مرتب‌سازی پیش‌فرض دارد.
- خروجی ویجت همچنان از شورت‌کد `[webtanan_booking_doctor_search]` استفاده می‌کند، اما هیچ حلقه PHP برای نمایش پزشکان یا اولین نوبت آزاد اجرا نمی‌شود.
- فرم جستجو با `frontend.js` submit را متوقف می‌کند و درخواست `GET /wp-json/saas/v1/doctors` را با پارامترهای `search`، `specialty_id`، `province_id`، `city_id`، `payment_filter` و `sort=first_available` ارسال می‌کند.
- نتیجه‌ها در DOM با کارت پزشک، تصویر، تخصص، badgeهای پرداخت و تایید، هزینه نوبت‌دهی و مقدار `next_available` پاسخ REST رندر می‌شوند.
- حالت loading spinner و empty state اضافه شد تا تجربه کاربر در صفحات Elementor کش‌شده هم شفاف بماند.

## به‌روزرسانی 1.1.0: JSON-LD پزشک

- خروجی JSON-LD از hook `wp_head` در `Frontend::render_doctor_schema_markup()` ساخته می‌شود و دیگر داخل بدنه قالب `single-saas-doctors.php` چاپ نمی‌شود.
- نوع schema برابر `Physician` است و فیلدهای `name`، `url`، `description`، `image`، `telephone`، `medicalSpecialty`، `priceRange`، `address`، `identifier` و `availableService` را در صورت وجود مقدار معتبر خروجی می‌دهد.
- `priceRange` ابتدا از `booking_fee` و اگر موجود نبود از `visit_price` ساخته می‌شود.
- فیلدهای خالی با helper داخلی حذف می‌شوند تا JSON-LD شامل کلیدهای empty/null نباشد.
- اگر برای دیدگاه‌های تاییدشده comment meta امتیاز معتبر وجود داشته باشد، `aggregateRating` با `ratingValue`، `reviewCount`، `bestRating` و `worstRating` اضافه می‌شود.

## نکات نگهداری

- فیلدهای شهر و استان فعلاً عددی هستند تا با ساختار فعلی دیتابیس سازگار بمانند. در نسخه‌های بعد می‌توان آن‌ها را به جدول/تاکسونومی شهر و استان متصل کرد.
- مرتب‌سازی نزدیک‌ترین نوبت آزاد پس از دریافت پزشکان از دیتابیس و با کمک `Booking::next_available()` انجام می‌شود؛ بنابراین برای صفحات بزرگ باید مقدار `per_page` کنترل‌شده بماند.
- کارت پزشک همچنان اولین نوبت آزاد را از REST دریافت می‌کند و آن را داخل HTML کش‌شده اولیه قرار نمی‌دهد.
- اگر endpoint پزشکان با `sort=first_available` فراخوانی شود، کارت AJAX از همان `next_available` پاسخ استفاده می‌کند و درخواست جداگانه برای هر پزشک نمی‌سازد.
# v1.2.4 Doctors Archive And Elementor Sync

- The `saas_doctors` archive template now uses the same AJAX-powered discovery layer as the Elementor Doctor Search widget.
- The archive no longer renders doctor availability in PHP. The doctor list, filters, and first available appointment are loaded through `/wp-json/saas/v1/doctors` and `next-available`.
- `[webtanan_booking_doctors_archive]` now delegates to the full search widget shell so normal WordPress pages and Elementor pages share the same UI.
- The Elementor Doctor List widget now supports `grid` and `list` layouts and defaults to `per_page=50` to match the public archive behavior.
- Elementor Doctor List and Doctor Card widgets explicitly declare `webtanan-booking-frontend` as style/script dependencies for stable editor preview rendering.
- The AJAX doctor card renderer now uses a unified card structure with image, badges, specialty/address, service fee, visit fee, first available slot, and two actions: `گرفتن نوبت` and `پروفایل پزشک`.
