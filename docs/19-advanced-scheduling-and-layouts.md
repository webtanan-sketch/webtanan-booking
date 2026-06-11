# Phase 19: Core Engine Fixes, Layout Breakout, and Database Seeding

This phase addresses critical architectural limitations preventing the plugin from acting as a true SaaS platform.

## 1. Database Seeder (Dummy Data)
To properly QA the system, we need realistic data. We must implement a `Seeder` class that generates:
- 5 WordPress Users (Doctor role).
- 5 `saas_doctors` CPTs.
- 5 records in `wp_saas_doctors` with realistic visit prices and booking fees.
- 3 Specialties in `wp_saas_specialties`.
- Realistic schedules in `wp_saas_schedules` covering the next 30 days for these doctors so slots actually appear!

## 2. The "No Slots" Bug (Jalali vs Gregorian)
The REST API is failing to find slots because the frontend calendar sends Jalali dates (e.g., 1403-04-15) but the database `wp_saas_schedules` expects Gregorian. `class-rest.php` MUST intercept the date parameter and strictly convert it using `Jalali_Calendar` helpers before running SQL queries.

## 3. Full-Screen SaaS Dashboards
The shortcodes `[webtanan_booking_doctor_dashboard]` and `[webtanan_booking_patient_panel]` must break out of the active WordPress theme's narrow container.
We will use the Viewport Breakout technique:
```css
.webtanan-dashboard-fullscreen-wrapper {
  width: 100vw;
  position: relative;
  left: 50%;
  right: 50%;
  margin-left: -50vw;
  margin-right: -50vw;
  min-height: 100vh;
  background: var(--wb-bg-body);
  z-index: 9999;
}
```

## 4. Implemented Stability Fixes

- Frontend REST calls now build URLs from `restRoot` + `restNamespace` instead of concatenating `restUrl + path`.
- Pretty permalink mode (`/wp-json/saas/v1/...`) and plain permalink mode (`?rest_route=/saas/v1/...`) are both supported.
- Query strings such as `date`, `per_page`, `doctor_id`, and `user_type` are appended as normal URL parameters and are not merged into `rest_route`.
- `class-frontend.php` localizes `restRoot`, `restNamespace`, and legacy `restUrl` for backwards compatibility.
- `class-rest.php` normalizes malformed plain REST URLs such as `?rest_route=/saas/v1/doctors?per_page=50` before WordPress route matching, so cached old JavaScript cannot break every endpoint.
- Frontend assets use a file modification based version string to force browsers and cache plugins to reload `frontend.js` after route fixes.
- `GET /doctors` defaults to `per_page=50`, and doctor search/list widgets load public doctors dynamically.
- Elementor Doctor Search defaults to 50 results so seeded doctors appear without an initial search.

## 5. Date Normalization Contract

All REST inputs that can carry appointment or schedule dates must normalize the incoming value before querying custom tables.

Accepted examples:

- `2026-06-08`
- `1405-03-18`
- `۱۴۰۵/۰۳/۱۸`
- `١٤٠٥/٠٣/١٨`

Normalized output:

- Gregorian `YYYY-MM-DD`

Applied endpoints:

- `GET /doctors/{id}/slots`
- `POST /appointments/lock`
- `GET /doctor-dashboard/summary`
- `GET /doctor-dashboard/appointments`
- `POST /doctor-dashboard/appointments`
- `GET /doctor-dashboard/calendar`
- `GET /doctor-dashboard/exceptions`
- `POST /doctor-dashboard/exceptions`

## 6. Seeder Contract

`includes/class-seeder.php` is idempotent and runs only for admins through:

`wp-admin/admin.php?page=webtanan-booking&webtanan_seed_data=1`

It creates or updates:

- 3 active specialties.
- 5 doctor users with the `webtanan_doctor` role.
- 5 published `saas_doctors` CPT posts.
- 5 operational rows in `wp_saas_doctors`.
- Active schedule rows for all weekdays represented in the next 30 days.

Running the trigger again updates existing dummy records and does not duplicate them.
# v1.2 Production Flow Additions

- Payment callbacks now redirect to a signed result page, preventing raw REST JSON from being printed to the browser.
- Wallet top-up was implemented with `appointment_id = 0` transactions and idempotent ledger credits.
- Resume payment uses appointment code plus OTP and renews expired locks only when the original slot is still free.
- Doctor dashboard profile editing was added with ownership enforcement for doctor users and admin-only switching.
- Patient dashboards and doctor dashboards remain full-screen wrappers and now include wallet top-up/profile workflows.
- Admin wallet adjustment gained a select2-like user search backed by `wp_ajax_webtanan_booking_user_search`.
- Font settings were added to plugin settings and are applied to frontend and admin UI when configured.
