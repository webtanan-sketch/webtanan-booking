=== Webtanan Booking ===
Contributors: webtanan
Tags: booking, doctors, appointments, saas, elementor
Requires at least: 6.0
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 1.0.3
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

This is a foundation phase. Before production launch, test AqayePardakht with real credentials, complete settlement approval workflow, Persian calendar UI, dashboard UX, test suite and concurrency testing on the target MySQL/MariaDB engine.
