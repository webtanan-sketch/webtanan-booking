# رزرو، پرداخت و کیف پول

## جریان lock نوبت

وقتی بیمار یک ساعت را انتخاب می‌کند:

1. سیستم بررسی می‌کند پزشک فعال و تایید شده باشد.
2. تاریخ و ساعت باید داخل برنامه کاری پزشک باشد.
3. transaction دیتابیس شروع می‌شود.
4. رکورد همان slot با `FOR UPDATE` خوانده می‌شود.
5. اگر confirmed یا locked معتبر باشد، درخواست رد می‌شود.
6. اگر آزاد یا lock منقضی باشد، رکورد با status `locked` و `lock_token` ثبت می‌شود.
7. transaction commit می‌شود.

## جلوگیری از Overbooking

سه لایه محافظ وجود دارد:

- transaction
- row locking
- unique index روی `doctor_id + appointment_date + start_time`

## پرداخت موفق در زمان معتبر

بعد از verify درگاه:

1. lock دوباره بررسی می‌شود.
2. اگر معتبر بود، نوبت `confirmed` می‌شود.
3. تراکنش `verified` می‌شود.
4. سهم پزشک در wallet ledger پزشک ثبت می‌شود.
5. کارمزد پلتفرم در wallet ledger پلتفرم ثبت می‌شود.
6. پیامک تایید ثبت/ارسال می‌شود.

## پرداخت موفق با برگشت دیرهنگام

اگر پرداخت موفق باشد اما `locked_until` گذشته باشد:

1. نوبت confirmed نمی‌شود.
2. appointment به `expired` می‌رود.
3. transaction به `expired_lock_wallet_charged` می‌رود.
4. مبلغ به کیف پول بیمار credit می‌شود.
5. اسلات‌های جایگزین همان پزشک برگردانده می‌شود.

## کیف پول

کیف پول با دفتر کل پیاده‌سازی شده است.

هر ردیف ledger شامل:

- user_id
- user_type
- related_appointment_id
- related_transaction_id
- entry_type
- amount
- balance_after
- description

برای جلوگیری از race condition در ledger، هنگام ثبت هر ردیف از advisory lock سطح MySQL برای همان `user_type + user_id` استفاده می‌شود و lock تا commit یا rollback نگه داشته می‌شود.

## پرداخت در مطب

اگر پزشک `allow_pay_at_clinic` داشته باشد:

1. بیمار ابتدا slot را lock می‌کند.
2. endpoint پرداخت با `method=pay_at_clinic` فراخوانی می‌شود.
3. نوبت با وضعیت `pay_at_clinic` ثبت می‌شود.
4. اگر پلتفرم از پرداخت حضوری هم کمیسیون بگیرد، بدهی کمیسیون در ledger پزشک debit می‌شود و برای پلتفرم commission ثبت می‌شود.
5. در صورت لغو قبل از مراجعه، بدهی کمیسیون حضوری برگشت داده می‌شود.

## نکته مهم برای production

درگاه آقای پرداخت در نسخه `0.4.0` این مراحل را انجام می‌دهد. برای درگاه‌های بعدی هم همین قرارداد باید رعایت شود:

- تطبیق amount
- تطبیق authority
- verify با provider
- ذخیره callback payload
- ذخیره ref_id
- جلوگیری از تایید دوباره تراکنش
