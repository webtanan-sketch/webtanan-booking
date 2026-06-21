# Webtanan Booking Gap Review - v1.3.0

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

- Survey submission stores a private response and creates a pending public WordPress comment when the patient consents. The new admin survey screen can mark responses as approved, private, pending, or rejected.
- Waiting-list live view is now available through both the signed REST endpoint and a polished public HTML page with 30-second polling.
- Medical records support text fields, notes, and uploaded files. Prescription-specific structured data is not yet implemented.

## Still Missing / Recommended Next

- Structured prescription module and prescription-specific print/export templates.
- Optional per-note file binding UI; the backend table already supports `note_id`.
- SaaS canvas template override for dashboard pages.
- Cron-cached `next_free_slot_cache` column and first-available sorting by cache.
- Automated browser QA for 390, 768, 1366, and 1920 pixel widths.
- Deeper browser QA against real Elementor templates, sticky theme headers, and production cache plugins.

## Operational Notes

- Plugin version is bumped to `1.3.0`. The v1.2.7 pass added medical-record file/audit tables, and v1.3.0 hardens seeding plus replaces public slot output with the virtual slot generator.
- `DB::create_tables()` still runs on boot when the stored version differs.
- Refund idempotency remains centralized in `Booking::cancel_appointment()` and `wp_saas_wallets_ledger`.
- Survey and waiting-list public access relies on HMAC tokens generated from appointment id/code/mobile and purpose.
