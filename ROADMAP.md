# 🗺️ Webtanan Booking SaaS Roadmap

This roadmap tracks the next development phases of **Webtanan Booking**, a WordPress SaaS appointment booking plugin for doctors, clinics, secretaries, patients, payments, wallets, SMS notifications, and Persian medical booking workflows.

---

## 🎯 Current Status

The plugin already includes:

- 🧑‍⚕️ Doctor CPT: `saas_doctors`
- 🗄️ Custom operational tables
- 👥 Doctor, secretary, and patient roles
- 🔐 OTP authentication
- 📅 Appointment lock with `locked_until` and `lock_token`
- 💳 AqayePardakht payment gateway integration
- 📨 IPPanel Pattern SMS integration
- 👛 Wallet ledger
- 🧾 Transaction logs
- 🛠️ Persian admin management pages
- 🧑‍⚕️ Front-end doctor/secretary dashboard
- 👤 Patient panel
- 🔎 AJAX Elementor doctor search and public doctor profiles
- 🧬 Doctor JSON-LD schema generated in `wp_head`
- 🛡️ OTP send rate limiting with indexed lookup
- 📊 Admin financial reports

The next work should focus on browser/load testing, race-condition test automation, payment callback replay testing, and cache compatibility verification.

---

## 🧱 Project Board Columns

Recommended GitHub Project statuses:

```text
Backlog
Ready
In Progress
Review
Testing
Done
Blocked
```

---

## 🏷️ Labels

Recommended labels:

```text
type: feature
type: bug
type: security
type: ui-ux
type: api
type: payment
type: sms
type: database
type: documentation
priority: high
priority: medium
priority: low
phase: calendar
phase: dashboard
phase: finance
phase: seo
phase: hardening
```

---

## 🚀 Milestone v0.7.0 — Persian Calendar & Booking UX

Goal: replace the basic booking experience with a professional Persian/Jalali appointment booking flow.

### Issues

- 📅 Implement Real Jalali Calendar Picker
- 📅 Build Appointment Slot Calendar UI
- 🔐 Improve OTP Flow During Booking
- 🧾 Redesign Appointment Receipt Page

### Expected Output

- Real Jalali calendar picker
- Professional slot selection UI
- Better OTP flow inside booking
- Clean printable appointment receipt
- Improved payment result pages

---

## 🧑‍⚕️ Milestone v0.8.0 — Doctor and Secretary Dashboard

Goal: make the clinic dashboard usable for real daily operations without entering WordPress admin.

### Issues

- 🧑‍⚕️ Redesign Doctor Dashboard UI
- 🧑‍💼 Build Secretary Assignment Admin Screen
- 📋 Improve Walk-in Appointment Creation
- 📊 Add Printable Daily Appointment Report

### Expected Output

- Professional doctor/secretary dashboard
- Secretary assignment management
- Pay-at-clinic appointment flow
- Daily printable clinic report

---

## 💰 Milestone v0.9.0 — Finance, Refund and Settlement

Goal: complete financial operations for real-world use.

### Issues

- 💸 Implement Cancellation and Refund Rules
- 👛 Add Patient Wallet Transaction History
- 🧾 Complete Doctor Settlement Workflow
- 📊 Add Financial Reports for Admin

### Expected Output

- Configurable cancellation rules
- Refund to wallet
- Complete doctor settlement flow
- Financial reports and auditability

---

## 🔎 Milestone v1.0.0 — Elementor, SEO and Public Release

Goal: make the public-facing website professional, SEO-ready, and Elementor-friendly.

Status: mostly complete through versions `1.0.0` to `1.1.0`.

### Issues

- 🧩 Build Elementor Doctor Card Widget
- 🔍 Build Advanced Doctor Search Widget
- 🧑‍⚕️ Improve Public Doctor Profile Template
- 🧬 Add Doctor Schema Markup

### Expected Output

- Elementor widgets
- Advanced doctor search
- Professional doctor profile template
- SEO-ready doctor pages

---

## 🛡️ Milestone v1.1.0 — Production Hardening

Goal: test, secure, and stabilize the plugin before real customer deployment.

Status: started in version `1.1.0`.

### Issues

- ✅ 🔒 Perform REST API Security Audit
- ✅ 🧬 Add Doctor Schema Markup
- ⚔️ Test Appointment Race Conditions
- 💳 Test Payment Callback Idempotency
- 🚀 Cache Compatibility Testing

### Expected Output

- Hardened REST permissions with explicit `wp_rest` nonce checks on authenticated routes
- OTP rate limiting: 3 sends per mobile/purpose in 15 minutes
- Doctor JSON-LD schema with empty fields omitted
- Race condition tests
- Payment callback idempotency
- Cache compatibility verification

---

## 🚨 Non-negotiable Rules

- Appointments, transactions, and wallet entries must not be stored in `wp_posts`.
- Payment callbacks must never be trusted without provider verification.
- Successful payment after lock expiration must not confirm appointment.
- Late successful payments must credit the patient wallet.
- Every money movement must have a wallet ledger entry.
- Slot availability must be loaded through REST API, not cached HTML.
- Appointment confirmation and financial operations must be idempotent.
- OTP codes must not be stored as raw text.
- Secretary access must be limited to assigned doctors.
- Final UI must be fully RTL and Persian-ready.
