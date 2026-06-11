# Webtanan Booking UI Design System

This document defines the strict UI/UX guidelines for all front-end output (widgets, modals, dashboards) in the Webtanan Booking plugin.

## 1. CSS Variables (The Foundation)
All custom CSS in `frontend.css` and `admin.css` MUST use these CSS variables. Do not hardcode HEX colors.

```css
:root {
  /* Brand Colors (Medical/Trust Theme) */
  --wb-primary: #0ea5e9; /* Light Blue */
  --wb-primary-hover: #0284c7;
  --wb-success: #10b981; /* Emerald Green for Available/Success */
  --wb-warning: #f59e0b; /* Amber for Frozen/Pending */
  --wb-danger: #ef4444; /* Red for Cancelled/Error */
  
  /* Surface & Backgrounds */
  --wb-bg-body: #f8fafc; /* slate-50 */
  --wb-bg-surface: #ffffff;
  --wb-bg-hover: #f1f5f9; /* slate-100 */
  
  /* Typography Colors */
  --wb-text-main: #0f172a; /* slate-900 */
  --wb-text-muted: #64748b; /* slate-500 */
  --wb-text-light: #94a3b8; /* slate-400 */
  
  /* Borders & Shadows */
  --wb-border-color: #e2e8f0; /* slate-200 */
  --wb-radius-sm: 8px;
  --wb-radius-md: 12px;
  --wb-radius-lg: 16px;
  --wb-shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
  --wb-shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
  --wb-shadow-float: 0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1);
}