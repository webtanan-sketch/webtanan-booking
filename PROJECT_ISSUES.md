# GitHub Project Issues

Copy each section into a separate GitHub Issue.


## 1. 📅 Implement Real Jalali Calendar Picker

**Milestone:** `v0.7.0 - Persian Calendar & Booking UX`  
**Labels:** `type: feature, type: ui-ux, priority: high, phase: calendar`

## Goal
Replace browser-native date inputs with a real Persian/Jalali date picker.

## Requirements
- Full RTL support
- Jalali month and day names
- Today button
- Previous/next month navigation
- Mobile-friendly layout
- Works inside doctor profile page
- Works inside doctor/secretary dashboard
- Works inside admin appointment filters
- Does not break WordPress admin styles

## Acceptance Criteria
- User can select a Jalali date without browser date input.
- Selected date is internally converted to Gregorian format for database/API usage.
- UI remains fully Persian and RTL.

---


## 2. 📅 Build Appointment Slot Calendar UI

**Milestone:** `v0.7.0 - Persian Calendar & Booking UX`  
**Labels:** `type: feature, type: ui-ux, type: api, priority: high, phase: calendar`

## Goal
Create a professional slot selection interface for doctor booking pages.

## Requirements
- Load slots from REST API
- Show available, locked, booked and expired states
- Show first available appointment
- Show loading state while fetching slots
- Show empty state when no slots are available
- Avoid rendering live slot data in cached HTML

## Acceptance Criteria
- Slots are loaded dynamically after page load.
- Cached doctor pages do not display stale appointment data.
- Patient can clearly understand which slots are available.

---


## 3. 🔐 Improve OTP Flow During Booking

**Milestone:** `v0.7.0 - Persian Calendar & Booking UX`  
**Labels:** `type: feature, type: security, type: ui-ux, priority: high, phase: calendar`

## Goal
Make patient OTP login smooth inside the booking flow.

## Requirements
- Mobile number input
- Send OTP button
- Countdown timer
- Resend OTP after timeout
- Error message for invalid OTP
- Auto-login after successful verification
- Continue booking after login without losing selected slot

## Acceptance Criteria
- Patient does not lose selected slot after OTP verification.
- OTP errors are displayed in Persian.
- Rate limits remain active.

---


## 4. 🧾 Redesign Appointment Receipt Page

**Milestone:** `v0.7.0 - Persian Calendar & Booking UX`  
**Labels:** `type: feature, type: ui-ux, priority: medium, phase: calendar`

## Goal
Create a professional printable receipt for confirmed appointments.

## Requirements
- Appointment code
- Doctor name
- Specialty
- Patient name
- Patient mobile
- Appointment date and time
- Payment method
- Payment status
- Tracking number if available
- Clinic address
- Print button
- Mobile-friendly design

## Acceptance Criteria
- Receipt is readable on mobile and desktop.
- Receipt can be printed cleanly.
- Private receipt access is protected by ownership check.

---


## 5. 🧑‍⚕️ Redesign Doctor Dashboard UI

**Milestone:** `v0.8.0 - Doctor and Secretary Dashboard`  
**Labels:** `type: feature, type: ui-ux, priority: high, phase: dashboard`

## Goal
Upgrade the doctor dashboard to a modern SaaS-style interface.

## Requirements
- Professional sidebar
- Today summary cards
- Appointment statistics
- Daily appointment list
- Calendar view
- Wallet summary
- Settlement summary
- Mobile responsive layout
- Persian RTL interface

## Acceptance Criteria
- Doctor can manage daily clinic operations without WordPress admin.
- Dashboard feels like a real SaaS panel, not a basic shortcode page.

---


## 6. 🧑‍💼 Build Secretary Assignment Admin Screen

**Milestone:** `v0.8.0 - Doctor and Secretary Dashboard`  
**Labels:** `type: feature, type: security, priority: high, phase: dashboard`

## Goal
Allow admins to assign secretaries to one or more doctors.

## Requirements
- Select secretary user
- Select assigned doctors
- Save doctor IDs into user meta
- Manage finance permission
- Show current assignments
- Prevent secretary from accessing unauthorized doctors

## Technical Notes
Use:
- `webtanan_assigned_doctor_ids`
- `webtanan_secretary_can_view_finance`

## Acceptance Criteria
- Secretary can only access assigned doctors.
- Secretary cannot access financial data unless permission is enabled.

---


## 7. 📋 Improve Walk-in Appointment Creation

**Milestone:** `v0.8.0 - Doctor and Secretary Dashboard`  
**Labels:** `type: feature, type: ui-ux, priority: medium, phase: dashboard`

## Goal
Improve the UX for registering appointments from the clinic dashboard.

## Requirements
- Select doctor
- Select Jalali date
- Select available slot
- Enter patient details
- Select payment status
- Support cash_at_clinic
- Support pos_at_clinic
- Support unpaid
- Register platform commission debt if needed

## Acceptance Criteria
- Doctor or secretary can register walk-in appointments quickly.
- Pay-at-clinic commission is reflected in wallet ledger.

---


## 8. 📊 Add Printable Daily Appointment Report

**Milestone:** `v0.8.0 - Doctor and Secretary Dashboard`  
**Labels:** `type: feature, type: ui-ux, priority: medium, phase: dashboard`

## Goal
Allow clinic staff to print daily appointment lists.

## Requirements
- Filter by doctor
- Filter by Jalali date
- Show patient name
- Show mobile
- Show time
- Show payment status
- Show attendance status
- Print-friendly layout

## Acceptance Criteria
- Secretary can print a clean daily appointment list.

---


## 9. 💸 Implement Cancellation and Refund Rules

**Milestone:** `v0.9.0 - Finance, Refund and Settlement`  
**Labels:** `type: feature, type: payment, priority: high, phase: finance`

## Goal
Allow admins to define cancellation and refund policies.

## Requirements
- Cancellation allowed until X hours before appointment
- Full refund rule
- Partial refund rule
- No refund rule
- Full refund when cancelled by doctor
- Full refund when cancelled by admin
- Refund to patient wallet
- Ledger entry for every refund

## Acceptance Criteria
- Cancellation result is calculated based on admin settings.
- Every refund has a wallet ledger record.

---


## 10. 👛 Add Patient Wallet Transaction History

**Milestone:** `v0.9.0 - Finance, Refund and Settlement`  
**Labels:** `type: feature, type: ui-ux, type: payment, priority: medium, phase: finance`

## Goal
Allow patients to view wallet activity.

## Requirements
- Show credit entries
- Show debit entries
- Show refund entries
- Show wallet payment entries
- Show related appointment code
- Show date and description
- Mobile-friendly table/cards

## Acceptance Criteria
- Patient can clearly understand wallet balance changes.

---


## 11. 🧾 Complete Doctor Settlement Workflow

**Milestone:** `v0.9.0 - Finance, Refund and Settlement`  
**Labels:** `type: feature, type: payment, priority: high, phase: finance`

## Goal
Make doctor settlement production-ready.

## Requirements
- Doctor settlement request
- Admin approval
- Admin rejection with reason
- Mark settlement as paid
- Store bank tracking number
- Create negative settlement ledger entry
- Show settlement history to doctor
- Prevent settlement above withdrawable balance

## Acceptance Criteria
- Settlement changes are traceable in ledger.
- Doctor can see settlement status.
- Admin can audit settlement history.

---


## 12. 📊 Add Financial Reports for Admin

**Milestone:** `v0.9.0 - Finance, Refund and Settlement`  
**Labels:** `type: feature, type: payment, priority: medium, phase: finance`

## Goal
Provide platform-level financial reports.

## Requirements
- Total platform commission
- Doctor earnings
- Patient wallet balances
- Pay-at-clinic commission debt
- Successful payments
- Failed payments
- Late callback wallet credits
- Settlement report

## Acceptance Criteria
- Admin can review financial health of the platform.

---


## 13. 🧩 Build Elementor Doctor Card Widget

**Milestone:** `v1.0.0 - Elementor, SEO and Public Release`  
**Labels:** `type: feature, type: ui-ux, priority: high, phase: seo`

## Goal
Create an Elementor widget for displaying doctor cards.

## Requirements
- Doctor image
- Name
- Specialty
- Clinic location
- First available appointment via AJAX
- Online/pay-at-clinic badges
- View profile button
- Book appointment button

## Acceptance Criteria
- Widget can be used inside Elementor pages.
- First available slot is loaded dynamically.

---


## 14. 🔍 Build Advanced Doctor Search Widget

**Milestone:** `v1.0.0 - Elementor, SEO and Public Release`  
**Labels:** `type: feature, type: ui-ux, type: api, priority: high, phase: seo`

## Goal
Create a public doctor search and filtering widget.

## Requirements
- Search by doctor name
- Filter by specialty
- Filter by city
- Filter by province
- Filter by payment method
- Sort by first available appointment
- AJAX results loading

## Acceptance Criteria
- Users can find doctors quickly.
- Search results remain SEO/cache friendly where possible.

---


## 15. 🧑‍⚕️ Improve Public Doctor Profile Template

**Milestone:** `v1.0.0 - Elementor, SEO and Public Release`  
**Labels:** `type: feature, type: ui-ux, priority: high, phase: seo`

## Goal
Create a professional public doctor profile layout.

## Requirements
- Doctor hero section
- Verified badge
- Specialty
- Clinic location
- Doctor biography
- Services section
- Appointment booking panel
- Patient reviews section
- Clinic map section
- SEO-friendly content area
- Mobile-first design

## Acceptance Criteria
- Doctor profile looks professional and trustworthy.
- Dynamic booking data is loaded through REST API.

---


## 16. 🧬 Add Doctor Schema Markup

**Milestone:** `v1.0.0 - Elementor, SEO and Public Release`  
**Labels:** `type: feature, type: documentation, priority: medium, phase: seo`

## Goal
Improve SEO for public doctor profiles.

## Requirements
- Add structured data for doctor profile
- Include name, specialty, address, phone if public, image and profile URL
- Avoid exposing private data
- Keep compatibility with SEO plugins

## Acceptance Criteria
- Doctor pages include valid schema markup.
- Private pages remain noindex.

---


## 17. 🔒 Perform REST API Security Audit

**Milestone:** `v1.1.0 - Production Hardening`  
**Labels:** `type: security, type: api, priority: high, phase: hardening`

## Goal
Review all REST endpoints for permission and ownership checks.

## Requirements
- Patient ownership check
- Doctor ownership check
- Secretary assigned doctor check
- Admin capability check
- Nonce validation for authenticated routes
- Sanitize all input
- Escape all output

## Acceptance Criteria
- Unauthorized users cannot access private data.
- Secretary cannot access unassigned doctors.

---


## 18. ⚔️ Test Appointment Race Conditions

**Milestone:** `v1.1.0 - Production Hardening`  
**Labels:** `type: security, type: database, priority: high, phase: hardening`

## Goal
Ensure overbooking cannot happen under concurrent requests.

## Requirements
- Simulate multiple users locking same slot
- Verify unique index behavior
- Verify transaction rollback behavior
- Verify expired lock replacement
- Verify payment confirmation idempotency

## Acceptance Criteria
- Only one user can confirm a specific doctor/date/time slot.

---


## 19. 💳 Test Payment Callback Idempotency

**Milestone:** `v1.1.0 - Production Hardening`  
**Labels:** `type: security, type: payment, priority: high, phase: hardening`

## Goal
Ensure repeated callbacks do not duplicate financial effects.

## Requirements
- Repeat successful callback
- Repeat verify
- Refresh payment result page
- Trigger late callback twice
- Check wallet ledger
- Check appointment status

## Acceptance Criteria
- No duplicate wallet credit.
- No duplicate doctor earning.
- No duplicate platform commission.

---


## 20. 🚀 Cache Compatibility Testing

**Milestone:** `v1.1.0 - Production Hardening`  
**Labels:** `type: bug, type: ui-ux, priority: medium, phase: hardening`

## Goal
Ensure WP cache plugins do not break appointment availability.

## Requirements
- Test with WP Rocket
- Test with LiteSpeed Cache
- Test cached doctor profile page
- Verify slots load from REST API
- Verify first available appointment updates dynamically

## Acceptance Criteria
- Cached pages never show stale slot availability.

---
