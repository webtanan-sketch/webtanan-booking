# Product Experience Stabilization

## Typography

- The active theme owns the public typography token through `--webtanan-theme-font`.
- `Webtanan SaaS Theme` looks for real, licensed IRANSans files in `assets/fonts/` using these names:
  - `IRANSansWeb.woff2` or `IRANSans-Regular.woff2`
  - `IRANSansWeb_Bold.woff2` or `IRANSans-Bold.woff2`
- The Customizer font URLs remain supported when the local files are not used.
- Plugin frontend and admin containers inherit the theme font while the companion theme is active.

## Public Pricing Policy

- The public doctor profile, doctor cards, archive, and search widgets do not show `visit_price`.
- The website only charges `booking_fee`, presented to the patient as `هزینه خدمات رزرو نوبت` during checkout and on the final receipt.
- Visit-price fields remain operational data for clinic/admin workflows and are not part of the public payment promise.

## Booking Reliability

- Lock responses now contain `locked_until_timestamp` in addition to the existing `locked_until` string.
- The booking wizard uses the server timestamp for its countdown, avoiding browser and WordPress timezone parsing differences.
- The patient form is a labelled two-column grid on desktop and a single column touch-friendly form on mobile.

## Account And Dashboard Shell

- The theme header displays `ورود / ثبت‌نام` for guests.
- Logged-in patients see a patient-panel link; doctors and secretaries see the clinic dashboard; administrators see the WordPress management link.
- Dashboard-specific duplicate headers are visually removed. A dedicated mobile menu button opens the role-aware sidebar below the site header.

## Doctor Discovery

- The public archive keeps only useful filters: name search, specialty, and available appointments.
- Payment-method, price, and raw numeric province/city filters are not rendered publicly.
- Homepage sections show real newest doctors and doctors ordered by confirmed appointment volume. Time-sensitive availability still loads through REST.

## SMS OTP Pattern

- OTP patterns receive the variable `verifycode`.
- The Pattern examples screen shows `{verifycode}` for the login message and retains `code` only for non-OTP legacy/general pattern use.
