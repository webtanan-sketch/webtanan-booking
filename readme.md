# 🩺 Webtanan Booking

A production-oriented **SaaS appointment booking plugin for WordPress**, designed for doctors, clinics, medical centers, and healthcare appointment platforms.

Webtanan Booking combines **SEO-friendly WordPress doctor profiles** with **high-performance custom database tables** for appointments, payments, wallets, SMS logs, OTP authentication, financial operations, and settlement workflows.

> 🚧 **Current status:** Installable foundation / advanced MVP. The plugin already includes the core architecture, doctor CPT, custom operational tables, OTP, appointment locking, wallet ledger, IPPanel Pattern SMS integration, AqayePardakht payment gateway integration, admin management pages, front-end doctor/secretary dashboard, patient panel, and Persian language support. It is not yet a final production SaaS release.

---

## 📚 Table of Contents

* [🩺 Overview](#overview)
* [🎯 Core Philosophy](#core-philosophy)
* [✨ Main Features](#main-features)
* [🏗️ Architecture](#architecture)
* [🗄️ Database Tables](#database-tables)
* [👥 User Roles](#user-roles)
* [📅 Appointment Booking Flow](#appointment-booking-flow)
* [💳 Payment Flow](#payment-flow)
* [👛 Wallet Ledger](#wallet-ledger)
* [📨 SMS Notifications](#sms-notifications)
* [🛠️ Admin Panel](#admin-panel)
* [🧑‍⚕️ Front-end Dashboards](#front-end-dashboards)
* [🔎 Elementor and SEO](#elementor-and-seo)
* [🧩 Shortcodes](#shortcodes)
* [🔌 REST API](#rest-api)
* [🔐 Security Principles](#security-principles)
* [⚙️ Installation](#installation)
* [🧰 Configuration](#configuration)
* [🗺️ Development Roadmap](#development-roadmap)
* [🚨 Non-negotiable Rules](#non-negotiable-rules)
* [✅ Recommended Production Checklist](#recommended-production-checklist)
* [🎨 Suggested UI Direction](#suggested-ui-direction)
* [📄 License](#license)
* [🙏 Credits](#credits)

---

## 🩺 Overview

Webtanan Booking is a WordPress plugin for building a **multi-doctor SaaS appointment booking system**.

The plugin is designed for scenarios where a central website owner manages multiple doctors, clinics, secretaries, patients, appointments, payments, SMS notifications, wallets, and settlements.

The system supports:

* 🧑‍⚕️ Public doctor profiles
* 🔎 SEO-friendly doctor archive pages
* 📅 Appointment slot generation
* 🔒 Safe appointment locking
* 💳 Online payment
* 🏥 Pay-at-clinic appointments
* 👛 Patient wallet
* 💼 Doctor wallet
* 🧾 Platform commission
* 📨 SMS notifications through IPPanel Pattern SMS
* 💳 AqayePardakht payment gateway integration
* 🇮🇷 Persian admin interface
* 🧑‍⚕️ Front-end doctor and secretary dashboard
* 👤 Patient panel
* 🧩 Elementor-ready architecture

---

## 🎯 Core Philosophy

The plugin follows a **hybrid architecture**.

### 📝 1. WordPress CPT for public content

Doctor public profiles are stored as a WordPress Custom Post Type:

```text
saas_doctors
```

This allows:

* 🔎 SEO indexing
* 🧩 Elementor Theme Builder compatibility
* 📈 Rank Math / Yoast compatibility
* 🗂️ Doctor archive pages
* 🧑‍⚕️ Public single doctor pages
* 🔍 Search-friendly doctor content

### ⚙️ 2. Custom tables for operational data

High-volume and financial data are **not stored in `wp_posts`**.

Appointments, payments, wallets, schedules, OTP logs, SMS logs, and settlement requests are stored in dedicated custom tables for better performance, traceability, and data integrity.

---

## ✨ Main Features

### 🧑‍⚕️ Public Doctor Profiles

* Doctor Custom Post Type
* Public single doctor pages
* Public doctor archive
* Specialty filtering
* Doctor image and clinic gallery
* SEO-friendly structure
* Elementor-compatible layout approach

### 📅 Appointment Booking

* Doctor weekly schedules
* Schedule exceptions and vacations
* Appointment locking with `locked_until`
* Unique doctor/date/time slot protection
* Prevention of overbooking
* Online payment
* Wallet payment
* Pay at clinic
* Printable appointment receipt

### 💰 Financial System

* Patient wallet
* Doctor wallet
* Platform wallet
* Ledger-based financial tracking
* Platform commission
* Pay-at-clinic commission debt
* Settlement requests
* Transaction logs
* Late payment return handling

### 💳 Payment Gateway

* AqayePardakht API v2 integration
* Create transaction
* Redirect to payment page
* Callback handling
* Verify transaction
* Idempotent payment processing
* Late callback handling with patient wallet credit

### 📨 SMS System

* IPPanel Edge API integration
* Pattern-based SMS sending
* OTP SMS
* Appointment confirmation SMS
* Appointment cancellation SMS
* Wallet charge SMS
* Late payment wallet charge SMS
* Appointment reminder SMS
* Settlement status SMS
* Duplicate SMS prevention
* SMS logs

### 🖥️ Front-end Dashboards

* Doctor dashboard
* Secretary dashboard
* Patient panel
* Front-end appointment management
* Pay-at-clinic appointment registration
* Appointment status management
* Wallet overview
* Settlement requests

### 🛠️ Admin Management

* Persian admin interface
* Doctor management
* Specialty management
* Schedule and exception management
* Appointments management
* Patients management
* Transactions management
* Wallet ledger management
* Settlement management
* OTP logs
* SMS logs
* Payment and SMS settings

---

## 🏗️ Architecture

```text
WordPress
│
├── Public Content Layer
│   └── CPT: saas_doctors
│       ├── Doctor public profile
│       ├── Doctor archive
│       ├── SEO metadata
│       └── Elementor template compatibility
│
├── Operational Layer
│   ├── Custom appointment tables
│   ├── Custom transaction tables
│   ├── Wallet ledger
│   ├── SMS logs
│   ├── OTP logs
│   └── Settlement requests
│
├── Provider Layer
│   ├── IPPanel SMS adapter
│   └── AqayePardakht payment adapter
│
├── REST API Layer
│   ├── Doctors API
│   ├── Slots API
│   ├── Appointment API
│   ├── Payment API
│   ├── Wallet API
│   ├── Patient panel API
│   └── Doctor dashboard API
│
└── UI Layer
    ├── Admin pages
    ├── Front-end doctor dashboard
    ├── Front-end patient panel
    ├── Shortcodes
    └── Elementor wrappers/widgets
```

---

## 🗄️ Database Tables

The plugin creates custom tables using the current WordPress database prefix.

If the WordPress prefix is `wp_`, the tables are:

```text
wp_saas_doctors
wp_saas_specialties
wp_saas_schedules
wp_saas_schedule_exceptions
wp_saas_appointments
wp_saas_transactions
wp_saas_wallets_ledger
wp_saas_settlement_requests
wp_saas_otp_logs
wp_saas_sms_logs
```

### 🔒 Critical appointment index

The appointment table uses a unique index to prevent double booking:

```sql
UNIQUE KEY unique_doctor_slot (
    doctor_id,
    appointment_date,
    start_time
)
```

This ensures that a doctor cannot have multiple active records for the same date and time.

> Future note: if `capacity_per_slot > 1` is required, the appointment model should be extended with seat inventory or slot capacity tracking.

---

## 👥 User Roles

### 🛠️ Site Admin

The platform owner can:

* Manage doctors
* Manage specialties
* Manage schedules and exceptions
* Manage appointments
* Manage transactions
* Manage wallets and ledger records
* Manage doctor settlements
* Configure SMS provider
* Configure payment gateway
* View OTP and SMS logs
* Access financial reports

### 🧑‍⚕️ Doctor

Doctors can access a front-end dashboard to:

* View appointments
* Manage daily appointments
* Register walk-in appointments
* View patients
* Manage schedules
* Manage exceptions and vacations
* View wallet balance
* Request settlements

### 🧑‍💼 Secretary

Secretaries can access only assigned doctors.

Secretary access is controlled through user meta:

```text
webtanan_assigned_doctor_ids
```

Financial access is controlled through:

```text
webtanan_secretary_can_view_finance
```

### 👤 Patient

Patients can:

* Login via OTP
* Book appointments
* View upcoming appointments
* View appointment history
* Cancel eligible appointments
* View wallet balance
* View receipts
* Pay online, by wallet, or at clinic if enabled

---

## 📅 Appointment Booking Flow

The booking flow is designed to prevent overbooking and race conditions.

```text
1. Patient opens a doctor profile.
2. Available slots are loaded through REST API.
3. Patient selects a date and time.
4. The system validates the doctor, schedule, and slot.
5. A database transaction starts.
6. The target slot is selected with row locking.
7. If the slot is free, it is locked for 15 minutes.
8. The appointment receives a lock_token and locked_until value.
9. Patient proceeds to payment.
10. After payment verification, the lock is checked again.
11. If the lock is still valid, the appointment is confirmed.
12. If the lock has expired, the paid amount is credited to the patient wallet.
```

Protection layers:

* Database transaction
* Row locking
* Unique slot index
* Lock expiration
* Idempotent payment processing

---

## 💳 Payment Flow

The plugin currently supports AqayePardakht API v2.

### ✅ Online Payment Flow

```text
1. Appointment is locked.
2. Transaction record is created internally.
3. Payment create request is sent to AqayePardakht.
4. Gateway transid is stored.
5. Patient is redirected to startpay URL.
6. Gateway redirects patient back to callback URL.
7. Callback payload is stored.
8. If callback status is successful, verify request is sent.
9. If verify succeeds, the appointment lock is checked.
10. If valid, appointment is confirmed.
11. Doctor and platform ledger entries are created.
12. Confirmation SMS is sent.
```

### ⏱️ Late Payment Return

If payment is successful but the appointment lock has expired:

```text
1. Appointment is not confirmed.
2. Appointment status becomes expired.
3. Transaction status becomes expired_lock_wallet_charged.
4. Paid amount is credited to the patient wallet.
5. SMS notification is sent.
6. Alternative available slots can be suggested to the patient.
```

### 🔗 AqayePardakht URLs

Create transaction:

```text
https://panel.aqayepardakht.ir/api/v2/create
```

Verify transaction:

```text
https://panel.aqayepardakht.ir/api/v2/verify
```

Start payment:

```text
https://panel.aqayepardakht.ir/startpay/{transid}
```

Sandbox start payment:

```text
https://panel.aqayepardakht.ir/startpay/sandbox/{transid}
```

---

## 👛 Wallet Ledger

Wallets are ledger-based.

The plugin does not rely on a simple balance field as the source of truth.

Each financial movement is stored as a ledger entry:

```text
user_id
user_type
related_appointment_id
related_transaction_id
entry_type
amount
balance_after
description
created_at
```

Supported financial concepts include:

* Patient wallet credit
* Wallet payment debit
* Refund
* Doctor earning
* Platform commission
* Pay-at-clinic commission debt
* Settlement debit
* Manual adjustment

Ledger consistency is critical. Every money movement must have a traceable ledger entry.

---

## 📨 SMS Notifications

The plugin integrates with IPPanel Edge API using Pattern SMS.

All SMS operations go through a central service. Booking, payment, wallet, and settlement code must not directly call `wp_remote_post`.

Central call pattern:

```php
SMS::send_pattern($mobile, $message_type, $variables, $appointment_id);
```

### 🔗 IPPanel Endpoint

```text
POST https://edge.ippanel.com/v1/api/send
```

### 📦 Pattern Payload

```json
{
  "sending_type": "pattern",
  "from_number": "+983000505",
  "code": "PATTERN_CODE",
  "recipients": ["+989121234567"],
  "params": {}
}
```

### 🧾 Supported Message Types

```text
otp
appointment_confirmed
staff_appointment_confirmed
appointment_cancelled
staff_appointment_cancelled
wallet_charged
late_payment_wallet_charged
reminder_24h
payment_failed
settlement_requested
settlement_paid
settlement_status
```

### 🛡️ Duplicate SMS Protection

If the same mobile, message type, and appointment receive a successful or test SMS within 40 seconds, the duplicate send is blocked and logged as:

```text
duplicate_blocked
```

---

## 🛠️ Admin Panel

The admin interface includes operational pages for:

* Dashboard
* Doctors
* Specialties
* Schedules and exceptions
* Appointments
* Patients
* Transactions
* Wallet ledger
* Settlements
* OTP logs
* SMS logs
* Payment settings
* SMS settings
* Shortcode setup

The admin experience is primarily Persian and includes filters, quick actions, and management forms for the main entities.

---

## 🧑‍⚕️ Front-end Dashboards

### 🧑‍⚕️ Doctor / Secretary Dashboard

Shortcode:

```text
[webtanan_booking_doctor_dashboard]
```

Current dashboard areas:

* Today overview
* Appointment calendar
* Patients list
* Working schedule
* Exceptions and vacations
* Wallet and financial data
* Walk-in appointment creation
* Settlement requests

### 👤 Patient Panel

Shortcode:

```text
[webtanan_booking_patient_panel]
```

Current patient panel areas:

* Appointment summary
* Upcoming appointments
* Appointment history
* Wallet
* Appointment cancellation
* Receipt view

---

## 🔎 Elementor and SEO

The plugin is designed to be Elementor-friendly.

Doctor pages use WordPress CPT, so they can be styled with Elementor Theme Builder or custom templates.

Dynamic and time-sensitive data must be loaded through REST API, not rendered into cached HTML.

Do not cache these values in static HTML:

* Available slots
* Locked slot status
* First available appointment
* Wallet balance
* Payment status
* Payment receipt

Recommended future Elementor widgets:

* Doctor search widget
* Doctor list widget
* Doctor card widget
* First available slot widget
* Appointment calendar widget
* Specialty archive widget

Recommended SEO improvements:

* Doctor schema markup
* Specialty archive pages
* SEO-compatible doctor profile metadata
* Noindex for patient panel, doctor dashboard, payment result pages, and private screens

---

## 🧩 Shortcodes

The plugin currently includes front-end shortcodes such as:

```text
[webtanan_booking_doctor_dashboard]
[webtanan_booking_patient_panel]
```

Additional shortcode pages may exist depending on the installed version, including pages for login, doctors archive, specialty archive, and appointment booking setup.

---

## 🔌 REST API

Namespace:

```text
/wp-json/saas/v1
```

### 🧑‍⚕️ Doctors

```text
GET /doctors
GET /doctors/{id}
GET /doctors/{id}/next-available
GET /doctors/{id}/slots?date=YYYY-MM-DD
```

### 📅 Appointments

```text
POST /appointments/lock
POST /appointments/pay
POST /appointments/confirm
POST /appointments/cancel
GET  /appointments/{id}/receipt
```

### 💳 Payment

```text
GET      /payment/gateways
GET|POST /payment/aqayepardakht/callback
```

### 🔐 OTP

```text
POST /auth/send-otp
POST /auth/verify-otp
```

### 🧑‍⚕️ Doctor Dashboard

```text
GET  /doctor-dashboard/context
GET  /doctor-dashboard/summary
GET  /doctor-dashboard/appointments
POST /doctor-dashboard/appointments
POST /doctor-dashboard/appointments/{id}/payment
POST /doctor-dashboard/appointments/{id}/status
GET  /doctor-dashboard/calendar
GET  /doctor-dashboard/schedules
POST /doctor-dashboard/schedules
GET  /doctor-dashboard/exceptions
POST /doctor-dashboard/exceptions
GET  /doctor-dashboard/patients
GET  /doctor-dashboard/wallet
GET  /doctor-dashboard/settlements
POST /doctor-dashboard/settlement-request
```

### 👤 Patient Panel

```text
GET /patient-panel/summary
GET /patient-panel/appointments
GET /patient-panel/wallet
```

---

## 🔐 Security Principles

The plugin must follow these security principles:

* Sanitize all input
* Escape all output
* Use `X-WP-Nonce` for authenticated REST requests
* Do not trust raw gateway callbacks
* Always verify payment with the provider
* Never store raw OTP codes in the database
* Rate-limit OTP requests
* Restrict patients to their own appointments
* Restrict doctors to their own doctor profile
* Restrict secretaries to assigned doctors only
* Protect financial admin screens with explicit capabilities
* Make financial operations idempotent
* Never apply the same payment effect twice
* Log all payment create/callback/verify payloads
* Log all wallet ledger entries
* Keep time-sensitive data out of HTML cache

---

## ⚙️ Installation

1. Upload the plugin directory to:

```text
wp-content/plugins/webtanan-booking
```

2. Activate the plugin from WordPress admin.

3. Confirm that required tables are created during activation.

4. Create or edit a doctor profile.

5. Configure doctor operational settings.

6. Configure schedules.

7. Configure SMS settings.

8. Configure payment gateway settings.

9. Create required front-end pages and place shortcodes.

10. Test appointment booking from start to finish.

---

## 🧰 Configuration

### 📨 SMS Settings

Configure IPPanel settings from the plugin admin panel:

* Enable/disable SMS
* Base URL
* API key / access token
* From number
* Test mode
* Log mode
* Pattern codes for each message type

### 💳 Payment Settings

Configure AqayePardakht settings:

* Enable/disable gateway
* Sandbox mode
* Gateway pin
* Callback method
* Minimum amount
* Maximum amount
* Default payment description template

### 🧑‍⚕️ Doctor Settings

Each doctor can have:

* Connected public CPT profile
* User account
* Specialty
* Visit price
* Booking service fee
* Platform commission type
* Platform commission value
* Online payment status
* Pay-at-clinic status
* Clinic address
* Doctor image
* Clinic gallery

---

## 🗺️ Development Roadmap

### 🧱 Phase 1 — Stabilize Current Version

Goal: stabilize the current installable base.

Tasks:

* Review activation and migrations
* Fix PHP and JavaScript errors
* Review all REST endpoints
* Review roles and capabilities
* Fix old or inconsistent Persian strings
* Improve basic UI issues
* Verify table indexes

### 📅 Phase 2 — Professional Persian Calendar and Booking UX

Goal: replace browser date inputs with a real Persian calendar experience.

Tasks:

* Implement real Jalali date picker
* Implement daily/weekly/monthly calendar views
* Improve slot selection UI
* Show available, locked, booked, and cancelled states
* Improve booking flow
* Improve OTP flow during booking
* Improve payment result pages
* Improve printable receipt

### 🧑‍⚕️ Phase 3 — Complete Doctor and Secretary Dashboard

Goal: make daily clinic operation possible without WordPress admin.

Tasks:

* Professional dashboard UI
* Daily/weekly/monthly calendar
* Appointment filters
* Patient search
* Walk-in appointment UX
* Pay-at-clinic management
* Attendance status management
* Schedule exception management
* Secretary assignment management
* Secretary finance permission UI
* Printable daily appointment report

### 💰 Phase 4 — Complete Financial System

Goal: make financial operations production-ready.

Tasks:

* Cancellation rules
* Refund rules
* Refund to wallet
* Full settlement workflow
* Settlement approval/rejection/payment
* Settlement tracking number
* Doctor withdrawable balance
* Pay-at-clinic commission debt reports
* Financial audit tools

### 🔎 Phase 5 — Elementor and SEO

Goal: make public pages professional and SEO-ready.

Tasks:

* Doctor search widget
* Doctor list widget
* Doctor card widget
* First available slot widget
* Booking calendar widget
* Specialty archive pages
* Doctor schema markup
* SEO metadata compatibility
* Noindex private pages
* Cache compatibility tests

### 🛡️ Phase 6 — Production Hardening

Goal: prepare for real customer deployment.

Tasks:

* Race condition tests
* Payment callback tests
* Late callback tests
* Wallet ledger tests
* OTP rate-limit tests
* Role access tests
* Cache plugin tests
* Mobile browser tests
* Load tests on MySQL/MariaDB
* Error log audit
* Release documentation

---

## 🚨 Non-negotiable Rules

* Appointments, transactions, and wallet entries must not be stored in `wp_posts`.
* Doctor public content may use CPT, but operational data must use custom tables.
* Payment callbacks must never be trusted without verification.
* A successful payment after lock expiration must not confirm an appointment.
* Late successful payments must credit the patient wallet.
* Every money movement must be traceable in wallet ledger.
* Slot availability must be loaded through REST API, not cached HTML.
* Appointment confirmation and financial operations must be idempotent.
* OTP codes must not be stored as raw text.
* Secretary access must be limited to assigned doctors.
* The final UI must be fully RTL and Persian-ready.

---

## ✅ Recommended Production Checklist

Before using this plugin in production, verify:

* Plugin installs without fatal errors
* Database tables are created correctly
* Doctor creation works
* Schedule creation works
* Slot generation works
* Appointment lock works
* Expired locks are released
* Concurrent booking is blocked
* Online payment works
* Failed payment works
* Late callback credits wallet
* Wallet payment works
* Pay-at-clinic works
* Cancellation and refund rules work
* OTP sending and verification work
* IPPanel Pattern SMS works
* AqayePardakht verify works
* Doctor dashboard works on mobile
* Patient panel works on mobile
* Admin filters work
* REST permissions are enforced
* Cache plugins do not break slot status
* No duplicate financial ledger entries are created
* No duplicate appointment confirmation happens

---

## 🎨 Suggested UI Direction

The public doctor profile should use a modern, professional, mobile-first medical design:

* Clean doctor hero section
* Verified doctor badge
* Specialty and clinic location
* First available appointment indicator
* Live appointment calendar
* Clear slot status colors
* Wallet and online payment options
* SEO-friendly doctor biography
* Services section
* Clinic location section
* Patient reviews
* Printable receipt page

Dynamic data such as slots and first available time must always be fetched through REST API.

---

## 📄 License

License information should be defined by the project owner.

Recommended options:

* GPL-2.0-or-later for WordPress.org-style distribution
* Proprietary license for private SaaS/client delivery

---

## 🙏 Credits

Developed for the Webtanan SaaS medical appointment booking ecosystem.
