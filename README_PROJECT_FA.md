# راهنمای اضافه کردن Roadmap به GitHub Projects

این پکیج برای مدیریت ادامه توسعه افزونه **Webtanan Booking** آماده شده است.

## محتویات پکیج

```text
README_PROJECT_FA.md
ROADMAP.md
PROJECT_ISSUES.md
project/labels.json
project/issues.json
project/milestones.md
scripts/create-github-roadmap.sh
.github/ISSUE_TEMPLATE/*.md
```

## روش ۱: اضافه کردن دستی داخل GitHub

### ۱. ساخت Project

داخل مخزن GitHub بروید به:

```text
Projects > New project
```

نوع پروژه را بهتر است روی **Board** بگذارید.

نام پروژه:

```text
Webtanan Booking SaaS Roadmap
```

توضیح پروژه:

```text
Roadmap and task tracking board for Webtanan Booking, a WordPress SaaS appointment booking plugin for doctors, clinics, patients, payments, wallets, SMS notifications, and Persian medical booking workflows.
```

### ۲. ستون‌های پیشنهادی

داخل Project، فیلد Status یا ستون‌ها را این‌طور بچینید:

```text
Backlog
Ready
In Progress
Review
Testing
Done
Blocked
```

### ۳. ساخت Milestoneها

از مسیر زیر وارد شوید:

```text
Issues > Milestones > New milestone
```

این Milestoneها را بسازید:

```text
v0.7.0 - Persian Calendar & Booking UX
v0.8.0 - Doctor and Secretary Dashboard
v0.9.0 - Finance, Refund and Settlement
v1.0.0 - Elementor, SEO and Public Release
v1.1.0 - Production Hardening
```

### ۴. ساخت Labelها

از مسیر زیر وارد شوید:

```text
Issues > Labels > New label
```

لیبل‌ها داخل فایل زیر آماده هستند:

```text
project/labels.json
```

می‌توانید دستی بسازید یا از اسکریپت استفاده کنید.

### ۵. ساخت Issueها

فایل زیر همه Issueهای آماده را دارد:

```text
PROJECT_ISSUES.md
```

برای هر بخش، یک Issue جدید بسازید و Title و Body را کپی کنید.

همچنین نسخه JSON همه Issueها در این فایل وجود دارد:

```text
project/issues.json
```

## روش ۲: اضافه کردن فایل‌ها به خود مخزن

اگر می‌خواهید این رودمپ در خود Repository هم دیده شود:

۱. فایل `ROADMAP.md` را در ریشه مخزن قرار دهید.

۲. پوشه `.github/ISSUE_TEMPLATE` را داخل مخزن کپی کنید.

۳. سپس commit بزنید:

```bash
git add ROADMAP.md .github/ISSUE_TEMPLATE
git commit -m "Add GitHub project roadmap and issue templates"
git push
```

بعد از این کار، وقتی در GitHub روی **New Issue** بزنید، قالب‌های Issue آماده نمایش داده می‌شوند.

## روش ۳: ساخت خودکار با GitHub CLI

اگر روی سیستم شما GitHub CLI نصب است، می‌توانید از اسکریپت استفاده کنید.

### پیش‌نیاز

```bash
gh auth login
```

### اجرای اسکریپت

در ریشه مخزن اجرا کنید:

```bash
bash scripts/create-github-roadmap.sh
```

این اسکریپت تلاش می‌کند:

- Labelها را بسازد.
- Issueها را ایجاد کند.
- Labelهای مربوط به هر Issue را اضافه کند.

نکته: ساخت Milestone با GitHub CLI به دسترسی و تنظیمات مخزن بستگی دارد. اگر اسکریپت نتوانست Milestone بسازد، Milestoneها را دستی بسازید.

## پیشنهاد اجرایی

برای شروع، فقط فاز `v0.7.0` را وارد Project کنید:

```text
📅 Implement Real Jalali Calendar Picker
📅 Build Appointment Slot Calendar UI
🔐 Improve OTP Flow During Booking
🧾 Redesign Appointment Receipt Page
```

بعد از تکمیل این فاز، فازهای بعدی را وارد کنید.
