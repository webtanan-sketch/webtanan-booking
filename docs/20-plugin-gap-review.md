# Webtanan Booking Gap Review - v1.2.3

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

## Partially Complete

- Survey submission stores a private response and creates a pending public WordPress comment when the patient consents. The new admin survey screen can mark responses as approved, private, pending, or rejected.
- Waiting-list live view is now available through both the signed REST endpoint and a polished public HTML page with 30-second polling.
- Medical records support text fields and notes. File attachments, prescriptions, and document uploads are not yet implemented.

## Still Missing / Recommended Next

- Medical record attachments and per-note file uploads.
- Audit log for medical-record reads/edits.
- More granular secretary access to medical records if clinics require it.
- Automated browser QA for 390, 768, 1366, and 1920 pixel widths.

## Operational Notes

- Plugin version is bumped to `1.2.3`. No database schema change was required in this pass.
- Database schema from `1.2.2` remains valid; `DB::create_tables()` still runs on boot when the stored version differs.
- Refund idempotency remains centralized in `Booking::cancel_appointment()` and `wp_saas_wallets_ledger`.
- Survey and waiting-list public access relies on HMAC tokens generated from appointment id/code/mobile and purpose.
