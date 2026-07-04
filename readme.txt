=== Webtanan Booking ===
Contributors: webtanan
Tags: booking, doctors, appointments, saas, elementor
Requires at least: 6.0
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 1.4.5
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Webtanan Booking is a document-first WordPress SaaS appointment booking foundation for doctors, clinics, patients, wallets and settlement workflows.

== Description ==

This first development phase creates the core plugin foundation:

* Doctor public profiles with the `saas_doctors` custom post type.
* Operational custom tables for doctors, schedules, appointments, transactions, wallet ledger, settlements, OTP and SMS logs.
* Role foundation for doctor, secretary and patient.
* REST API namespace `saas/v1`.
* Appointment slot loading through REST so cache plugins do not freeze time-sensitive data.
* 15-minute configurable appointment lock with `locked_until`, `lock_token`, database transactions and the unique `doctor_id + appointment_date + start_time` index.
* OTP authentication foundation with hashed OTP storage.
* Wallet ledger foundation; balances are derived from ledger entries, not a single mutable balance field.
* Elementor widgets that wrap the plugin shortcodes.
* Development documentation in the `docs` directory.

Payment now has a gateway registry, a built-in AqayePardakht v2 adapter, selectable gateways in the booking calendar, transaction request/callback/verify logs and late-lock wallet fallback. SMS has a built-in IPPanel Edge API pattern sender and still exposes `webtanan_booking_sms_send` for custom overrides.

Version 0.5.0 adds the complete WordPress admin management layer: doctors, specialties, schedules, appointments, patients, transactions, wallet ledger, settlements, OTP logs, SMS logs, filters, operational forms and Persian language files.

Version 0.6.0 separates booking service fees from displayed visit fees. The website charges only the booking service fee, visit price is optional display-only information, and booking fee shares can be credited to the doctor or secretary. This version also adds doctor photo and clinic gallery management plus public single/archive doctor templates.

Version 0.6.1 adds setup shortcodes, an admin Shortcodes screen, OTP login shortcode, specialty list shortcode, filtered doctor-list attributes and public view links for doctors and specialties in admin tables.

Version 0.8.0 adds stronger cancellation/refund policy controls, settlement tracking with idempotent wallet ledger settlement entries, secretary assignment management and confirmation modals for sensitive frontend actions.

Version 0.9.0 refreshes the user-facing UI: archive and profile layouts, doctor cards, booking widget, OTP login, patient panel, doctor/secretary dashboard, receipt view and dynamic AJAX-rendered cards now share a cleaner RTL medical SaaS design.

Version 1.0.0 completes the first Elementor and SEO release layer: doctor search/list widgets expose specialty, city, province, payment and first-available sorting controls; public doctor filters use the same REST contract; doctor profiles include richer Physician schema markup.

Version 1.0.1 polishes the plugin appearance across the public archive, doctor profile, booking calendar, patient/clinic panels, receipt and WordPress admin screens with a cleaner medical SaaS visual system.

Version 1.0.2 adds the public doctor booking popup: first available slots and appointment availability are loaded through REST, Jalali days are shown in a horizontal modal, OTP stays inside the modal, and locked guest appointments are attached to the verified patient before payment.

Version 1.0.3 completes the Persian backend admin phase with clearer booking/ledger menus, a combined SMS/OTP log screen, Jalali appointment date filtering, visible platform commission fields on doctor forms, and idempotent settlement payment handling with required bank tracking numbers.

Version 1.0.4 hardens secretary access control and cancellation refunds: assigned secretaries can only see doctors saved in `webtanan_assigned_doctor_ids`, finance endpoints require `webtanan_secretary_can_view_finance`, and appointment cancellation creates at most one patient wallet refund ledger entry.

Version 1.0.5 completes the AJAX Elementor doctor search widget, upgrades the patient panel wallet history with related appointment codes, and adds optimized admin financial reports for platform commission, withdrawable earnings, clinic-payment debt and patient wallet balances.

Version 1.1.0 adds production hardening and SEO improvements: doctor profile JSON-LD is generated in `wp_head` with empty fields omitted, optional aggregate ratings are supported, OTP sending is limited to 3 requests per mobile/purpose in 15 minutes, and the OTP table has an indexed rate-limit lookup path.

Version 1.4.0 hardens OTP profile completion, adds safe patient/doctor account selection and pending doctor applications, restores logged-in payment for unpaid appointments, upgrades doctor and patient dashboards, uses a local Jalali engine, and adds specialty icons plus configurable homepage sections.

== Shortcodes ==

* `[webtanan_booking_doctor_search per_page="12" specialty_id="0" province_id="0" city_id="0" payment_filter="" sort=""]`
* `[webtanan_booking_doctors_archive per_page="12"]`
* `[webtanan_booking_doctor_list per_page="12" specialty_id="0" province_id="0" city_id="0" payment_filter="" sort=""]`
* `[webtanan_booking_specialty_list show_count="yes"]`
* `[webtanan_booking_calendar doctor_id="1"]`
* `[webtanan_booking_next_available doctor_id="1"]`
* `[webtanan_booking_auth]`
* `[webtanan_booking_patient_panel]`
* `[webtanan_booking_doctor_dashboard]`

The admin Shortcodes screen lists the recommended page setup. The doctor dashboard shortcode includes the front-end clinic dashboard for doctors and assigned secretaries. The patient panel uses the same visual system for upcoming appointments, history and wallet.

== REST API ==

Base namespace: `/wp-json/saas/v1`

Important first-phase routes:

* `GET /doctors`
* `GET /doctors/{id}`
* `GET /doctors/{id}/next-available`
* `GET /doctors/{id}/slots?date=YYYY-MM-DD`
* `POST /appointments/lock`
* `POST /appointments/pay` with `method=online` and `gateway=aqayepardakht`
* `POST /appointments/confirm`
* `POST /appointments/cancel`
* `GET /payment/gateways`
* `GET|POST /payment/aqayepardakht/callback`
* `GET /wallet/balance`
* `GET /wallet/ledger`
* `POST /wallet/pay`
* `POST /auth/send-otp`
* `POST /auth/verify-otp`

For pay at clinic, call `POST /appointments/pay` with `method=pay_at_clinic` after a valid lock is created.

== Payment Gateways ==

The first built-in gateway is AqayePardakht. Configure it from Webtanan Booking > Settings:

* Enable AqayePardakht.
* Set sandbox mode as needed.
* Set the PIN.
* Use the callback URL shown in settings if the AqayePardakht panel asks for it.

Custom gateways can be registered through `webtanan_booking_available_gateways` and `webtanan_booking_gateway_adapter`.

== Admin Management ==

The WordPress admin now includes filtered management pages for:

* Doctors with an internal add/edit form.
* Specialties.
* Weekly schedules and schedule exceptions.
* Appointments with walk-in booking and quick payment/attendance/cancel actions.
* Patients.
* Transactions.
* Wallet ledger with manual adjustments.
* Settlement requests with approve/reject/paid workflow.
* OTP and SMS logs.

== Languages ==

Persian is the primary product language. Translation sources and compiled WordPress language files are included in `languages/webtanan-booking.pot`, `languages/webtanan-booking-fa_IR.po` and `languages/webtanan-booking-fa_IR.mo`.

== Provider Hooks ==

SMS adapter:

`webtanan_booking_sms_send`

== Important ==

Before production launch, verify the configured SMS patterns and AqayePardakht credentials on the target site, then run one real low-value gateway payment. Version 1.4.5 includes transactional slot locking, failed-payment recovery, wallet checkout, Jalali scheduling, role-aware dashboards, responsive booking UI, printable receipts, post-visit surveys and Rank Math-aware public SEO routes.

== Changelog ==

= 1.4.5 =

* Added owner-aware failed-payment recovery without a second OTP challenge, including a live lock countdown and one-click re-lock of the same slot.
* Added automatic retry for transient IPPanel DNS, timeout, connection, 429 and 5xx failures with capped attempts and a cron lock.
* Reduced IPPanel pattern payloads to message-specific variable whitelists.
* Unified the mobile dashboard navigation into the companion theme header hamburger while preserving the desktop sidebar.
* Added an authenticated in-panel survey form and hardened survey insert, update and publication-consent behavior.

= 1.4.4 =

* Completed a cross-screen UX audit for public booking, authentication, patient and clinic dashboards.
* Added Persian network-error handling, keyboard focus management and Escape behavior for dialogs and mobile sidebars.
* Connected doctor services, certificates and frequently asked questions to both doctor and administrator profile editors.
* Added secure deletion and duplicate-safe updates for weekly schedules and special-date rules.
* Fixed empty profile navigation targets and gallery compatibility for array-based media metadata.
* Removed OTP values from REST debug responses.

= 1.4.3 =

* Added a direct jump from an unavailable day to the doctor's first available appointment.
* Made the IPPanel OTP placeholder configurable and stopped reporting success when the provider rejects delivery.
* Added Persian/Arabic digit normalization for OTP mobile numbers and codes.
* Added clean specialty URLs, legacy redirects, canonical metadata and Rank Math robots/title/description filters.
* Removed duplicate Physician JSON-LD from the doctor body template.
* Replaced placeholder doctor ratings with approved real review aggregates.
* Added an indexed, cron-refreshed next-slot cache for faster doctor discovery sorting.

= 1.4.2 =

* Fixed the global OTP enhancer scope error that stopped all frontend widgets during DOM initialization.
* Added a runtime DOM initialization smoke test in addition to static JavaScript syntax validation.
* Completed the signed post-visit survey UI with responsive rating controls and visible loading/error states.
* Added secure survey access from eligible patient appointment history entries.

= 1.4.1 =

* Added doctor appointment card grids and immediate date-driven dashboard refreshes.
* Unified weekly schedules and special-date rules in one scheduling workspace.
* Split financial ledger and settlement request views.
* Added patient favorite doctors with secure REST-backed user preferences.
* Added six-box OTP input, automatic submit, countdown and timezone-safe verification.
* Restricted OTP pattern params to the numeric `verifyotp` value.
* Switched newly generated appointment codes to unique 10-digit numeric values.
* Added tabbed plugin settings for shorter admin workflows.

= 1.4.0 =

* Added patient/doctor account onboarding and secure profile completion.
* Added retry checkout for unpaid and failed appointments with wallet or gateway selection.
* Unified web and printable appointment receipts.
* Blocked past appointment times in both REST responses and server-side locking.
* Unified doctor cards and specialty archive links across theme, shortcodes and Elementor.
* Added administrator-controlled homepage section visibility, ordering and doctor limits.
* Hid the WordPress admin bar for patient, doctor and secretary roles.
