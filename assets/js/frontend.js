(function () {
    'use strict';

    const cfg = window.WebtananBooking || {};
    const restNamespace = trimSlashes(cfg.restNamespace || 'saas/v1');
    const restRoot = cfg.restRoot || '';
    const legacyApiBase = cfg.restUrl || '/wp-json/saas/v1';

    function trimSlashes(value) {
        return String(value == null ? '' : value).replace(/^\/+|\/+$/g, '');
    }

    function splitRestPath(path) {
        const raw = String(path || '');
        const questionIndex = raw.indexOf('?');
        const route = questionIndex === -1 ? raw : raw.slice(0, questionIndex);
        const query = questionIndex === -1 ? '' : raw.slice(questionIndex + 1);

        return {
            route: trimSlashes(route),
            query: new URLSearchParams(query)
        };
    }

    function appendRestRouteToPrettyRoot(url, route) {
        const basePath = url.pathname.endsWith('/') ? url.pathname : `${url.pathname}/`;
        url.pathname = `${basePath}${trimSlashes(route)}`.replace(/\/{2,}/g, '/');

        return url;
    }

    function buildRestUrl(path) {
        const parts = splitRestPath(path);
        const route = `/${trimSlashes(`${restNamespace}/${parts.route}`)}`;
        const base = restRoot || legacyApiBase;
        const url = new URL(base, window.location.href);

        if (restRoot) {
            if (url.searchParams.has('rest_route')) {
                url.searchParams.set('rest_route', route);
            } else {
                appendRestRouteToPrettyRoot(url, route);
            }
        } else if (url.searchParams.has('rest_route')) {
            const currentRoute = trimSlashes(url.searchParams.get('rest_route') || restNamespace);
            const namespaceRoute = currentRoute.endsWith(restNamespace) ? currentRoute : restNamespace;
            url.searchParams.set('rest_route', `/${trimSlashes(`${namespaceRoute}/${parts.route}`)}`);
        } else {
            appendRestRouteToPrettyRoot(url, `/${parts.route}`);
        }

        parts.query.forEach((value, key) => {
            url.searchParams.set(key, value);
        });

        return url.toString();
    }

    function request(path, options = {}) {
        const skipNonce = Boolean(options.skipNonce);
        const headers = Object.assign({ 'Content-Type': 'application/json' }, options.headers || {});
        if (!skipNonce && cfg.nonce) {
            headers['X-WP-Nonce'] = cfg.nonce;
        }
        const fetchOptions = Object.assign({ credentials: 'same-origin' }, options, { headers });
        delete fetchOptions.skipNonce;

        return fetch(buildRestUrl(path), fetchOptions)
            .then((response) => response.text().then((text) => {
                let body = {};
                if (text) {
                    try {
                        body = JSON.parse(text);
                    } catch (error) {
                        body = { message: (cfg.strings && cfg.strings.error) || 'خطایی رخ داد. لطفاً دوباره تلاش کنید.' };
                    }
                }
                if (!response.ok) {
                    const message = body && body.message ? body.message : (cfg.strings && cfg.strings.error) || 'خطایی رخ داد. لطفاً دوباره تلاش کنید.';
                    const requestError = new Error(message);
                    requestError.code = body && body.code ? body.code : '';
                    requestError.data = body && body.data ? body.data : {};
                    throw requestError;
                }
                return body;
            }))
            .catch((error) => {
                if (error instanceof TypeError || /failed to fetch|networkerror|load failed/i.test(String(error && error.message || ''))) {
                    throw new Error('ارتباط با سایت برقرار نشد. اینترنت خود را بررسی کنید و دوباره تلاش کنید.');
                }
                throw error;
            });
    }

    function requestFormData(path, formData) {
        return fetch(buildRestUrl(path), {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'X-WP-Nonce': cfg.nonce || '' },
            body: formData
        }).then((response) => response.text().then((text) => {
            let body = {};
            if (text) {
                try {
                    body = JSON.parse(text);
                } catch (error) {
                    body = { message: (cfg.strings && cfg.strings.error) || 'خطایی رخ داد. دوباره تلاش کنید.' };
                }
            }
            if (!response.ok) {
                throw new Error((body && body.message) || (cfg.strings && cfg.strings.error) || 'خطایی رخ داد. دوباره تلاش کنید.');
            }
            return body;
        })).catch((error) => {
            if (error instanceof TypeError || /failed to fetch|networkerror|load failed/i.test(String(error && error.message || ''))) {
                throw new Error('ارتباط با سایت برقرار نشد. اینترنت خود را بررسی کنید و دوباره تلاش کنید.');
            }
            throw error;
        });
    }

    function esc(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function money(value) {
        return Number(value || 0).toLocaleString('fa-IR');
    }

    function faDate(value) {
        const date = value ? new Date(`${value}T00:00:00`) : new Date();
        try {
            return new Intl.DateTimeFormat('fa-IR-u-ca-persian', {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            }).format(date);
        } catch (error) {
            return value || '';
        }
    }

    function qs(params) {
        const clean = {};
        Object.keys(params).forEach((key) => {
            if (params[key] !== '' && params[key] != null) {
                clean[key] = params[key];
            }
        });
        return new URLSearchParams(clean).toString();
    }

    function dashboardViewFromHash(allowed, fallback) {
        let value = '';
        try {
            value = decodeURIComponent(String(window.location.hash || '').replace(/^#/, ''));
        } catch (error) {
            value = '';
        }

        return allowed.includes(value) ? value : fallback;
    }

    function formObject(form) {
        const data = new FormData(form);
        const out = {};
        data.forEach((value, key) => {
            out[key] = value;
        });
        return out;
    }

    function activateDialogFocus(dialog, closeCallback) {
        if (!dialog) {
            return () => {};
        }
        const previous = typeof HTMLElement !== 'undefined' && document.activeElement instanceof HTMLElement ? document.activeElement : null;
        const selector = 'a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';
        const keyHandler = (event) => {
            if (event.key === 'Escape') {
                event.preventDefault();
                closeCallback && closeCallback();
                return;
            }
            if (event.key !== 'Tab') {
                return;
            }
            const focusable = Array.from(dialog.querySelectorAll(selector)).filter((node) => node.offsetParent !== null);
            if (!focusable.length) {
                event.preventDefault();
                dialog.focus();
                return;
            }
            const first = focusable[0];
            const last = focusable[focusable.length - 1];
            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        };
        document.addEventListener('keydown', keyHandler);
        window.setTimeout(() => {
            const first = dialog.querySelector(selector);
            (first || dialog).focus();
        }, 30);

        return () => {
            document.removeEventListener('keydown', keyHandler);
            if (previous && document.body.contains(previous)) {
                previous.focus();
            }
        };
    }

    function otpDigits(value) {
        return String(value || '')
            .replace(/[۰-۹]/g, (digit) => String('۰۱۲۳۴۵۶۷۸۹'.indexOf(digit)))
            .replace(/[٠-٩]/g, (digit) => String('٠١٢٣٤٥٦٧٨٩'.indexOf(digit)))
            .replace(/\D/g, '')
            .slice(0, 6);
    }

    function enhanceOtpInput(input) {
        if (!input || input.dataset.otpBoxesReady === '1') {
            return;
        }
        input.dataset.otpBoxesReady = '1';
        input.type = 'hidden';
        input.classList.add('wb-otp-source');
        const wrapper = document.createElement('div');
        wrapper.className = 'wb-otp-boxes';
        wrapper.dir = 'ltr';
        wrapper.setAttribute('role', 'group');
        wrapper.setAttribute('aria-label', 'کد تایید شش رقمی');
        wrapper.innerHTML = Array.from({ length: 6 }, (_, index) => `<input type="text" inputmode="numeric" pattern="[0-9]*" maxlength="${index === 0 ? '6' : '1'}" autocomplete="${index === 0 ? 'one-time-code' : 'off'}" aria-label="رقم ${index + 1} کد تایید">`).join('');
        input.insertAdjacentElement('afterend', wrapper);
        const boxes = Array.from(wrapper.querySelectorAll('input'));
        const form = input.closest('form');

        const sync = (autoSubmit = true) => {
            const code = boxes.map((box) => otpDigits(box.value)).join('').slice(0, 6);
            input.value = code;
            if (autoSubmit && code.length === 6 && form && form.dataset.otpAutoSubmitting !== '1') {
                form.dataset.otpAutoSubmitting = '1';
                window.setTimeout(() => {
                    if (typeof form.requestSubmit === 'function') {
                        form.requestSubmit();
                    } else {
                        form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
                    }
                    window.setTimeout(() => { delete form.dataset.otpAutoSubmitting; }, 1200);
                }, 120);
            }
        };

        const fill = (value) => {
            const digits = otpDigits(value);
            boxes.forEach((box, index) => { box.value = digits[index] || ''; });
            sync(true);
            const next = boxes[Math.min(digits.length, boxes.length - 1)];
            next && next.focus();
        };

        boxes.forEach((box, index) => {
            box.addEventListener('input', () => {
                const digits = otpDigits(box.value);
                if (digits.length > 1) {
                    fill(digits);
                    return;
                }
                box.value = digits;
                if (digits && boxes[index + 1]) {
                    boxes[index + 1].focus();
                }
                sync(true);
            });
            box.addEventListener('keydown', (event) => {
                if (event.key === 'Backspace' && !box.value && boxes[index - 1]) {
                    boxes[index - 1].value = '';
                    boxes[index - 1].focus();
                    sync(false);
                }
                if (event.key === 'ArrowLeft' && boxes[index + 1]) {
                    boxes[index + 1].focus();
                }
                if (event.key === 'ArrowRight' && boxes[index - 1]) {
                    boxes[index - 1].focus();
                }
            });
        });
        wrapper.addEventListener('paste', (event) => {
            event.preventDefault();
            fill(event.clipboardData ? event.clipboardData.getData('text') : '');
        });
        input._webtananOtpFocus = () => boxes[0] && boxes[0].focus();
        if (input.value) {
            fill(input.value);
        }
    }

    function enhanceOtpInputs(root = document) {
        const scope = root && root.querySelectorAll ? root : document;
        scope.querySelectorAll('input[name="otp"][autocomplete="one-time-code"]').forEach(enhanceOtpInput);
    }

    function focusOtpForm(form) {
        enhanceOtpInputs(form);
        const input = form && form.querySelector('input[name="otp"]');
        if (input && typeof input._webtananOtpFocus === 'function') {
            input._webtananOtpFocus();
        }
    }

    function startOtpCountdown(form, seconds) {
        if (!form) {
            return;
        }
        if (form._webtananOtpTimer) {
            window.clearInterval(form._webtananOtpTimer);
        }
        let remaining = Math.max(1, Number(seconds || 180));
        let timer = form.querySelector('.wb-otp-countdown');
        if (!timer) {
            timer = document.createElement('div');
            timer.className = 'wb-otp-countdown';
            timer.setAttribute('aria-live', 'polite');
            const boxes = form.querySelector('.wb-otp-boxes');
            (boxes || form.firstElementChild || form).insertAdjacentElement(boxes ? 'afterend' : 'beforebegin', timer);
        }
        const paint = () => {
            if (remaining <= 0) {
                timer.textContent = 'مهلت کد تمام شد؛ کد تازه دریافت کنید.';
                timer.dataset.expired = 'true';
                window.clearInterval(form._webtananOtpTimer);
                return;
            }
            timer.dataset.expired = 'false';
            timer.textContent = `اعتبار کد: ${String(Math.floor(remaining / 60)).padStart(2, '0')}:${String(remaining % 60).padStart(2, '0')}`;
            remaining--;
        };
        paint();
        form._webtananOtpTimer = window.setInterval(paint, 1000);
    }

    function stopOtpCountdown(form) {
        if (form && form._webtananOtpTimer) {
            window.clearInterval(form._webtananOtpTimer);
            form._webtananOtpTimer = 0;
        }
    }

    function panel(message) {
        return `<div class="webtanan-panel">${esc(message)}</div>`;
    }

    function loadingPanel(message) {
        return `<div class="webtanan-loading-state"><span class="webtanan-spinner" aria-hidden="true"></span><span>${esc(message || (cfg.strings && cfg.strings.loading) || 'در حال بارگذاری...')}</span></div>`;
    }

    function toast(message, tone = 'info') {
        if (!message || !document.body) {
            return;
        }
        const previous = document.querySelector('.webtanan-toast');
        previous && previous.remove();
        const el = document.createElement('div');
        el.className = 'webtanan-toast';
        el.dir = 'rtl';
        el.dataset.tone = tone;
        el.textContent = message;
        document.body.appendChild(el);
        window.setTimeout(() => {
            if (document.body.contains(el)) {
                el.remove();
            }
        }, 5200);
    }

    const statusLabels = Object.freeze({
        available: 'ساعت آزاد',
        locked: 'در حال رزرو',
        booked: 'ثبت‌شده (قطعی)',
        pending: 'در انتظار تکمیل',
        confirmed: 'تایید شده',
        cancelled: 'لغو شده',
        expired: 'منقضی شده (بازگشت وجه به کیف پول)',
        expired_lock_wallet_charged: 'منقضی شده (بازگشت وجه به کیف پول)',
        completed: 'مراجعه کرد',
        no_show: 'مراجعه نکرد',
        pay_at_clinic: 'پرداخت در مطب (حضوری)',
        unpaid: 'پرداخت نشده',
        paid: 'پرداخت آنلاین',
        failed: 'ناموفق',
        refunded_to_wallet: 'استرداد به کیف پول',
        cash_at_clinic: 'نقدی در مطب',
        pos_at_clinic: 'کارت‌خوان در مطب',
        wallet_paid: 'پرداخت از کیف پول'
    });

    const slotStatusLabels = Object.freeze({
        available: 'آزاد',
        locked: 'در حال رزرو',
        booked: 'پر شده',
        confirmed: 'پر شده',
        pay_at_clinic: 'پر شده',
        expired: 'منقضی شده',
        expired_lock_wallet_charged: 'منقضی شده',
        cancelled: 'لغو شده',
        past: 'زمان گذشته'
    });

    const fieldLabels = Object.freeze({
        booking_fee: 'هزینه خدمات رزرو'
    });

    const weekdayLabels = {
        saturday: 'شنبه',
        sunday: 'یکشنبه',
        monday: 'دوشنبه',
        tuesday: 'سه‌شنبه',
        wednesday: 'چهارشنبه',
        thursday: 'پنجشنبه',
        friday: 'جمعه'
    };

    const exceptionTypeLabels = {
        day_off: 'تعطیلی کامل',
        custom_shift: 'شیفت جایگزین',
        reduced_shift: 'شیفت کوتاه',
        extra_shift: 'شیفت اضافه'
    };

    const ledgerLabels = {
        credit: 'افزایش اعتبار',
        debit: 'کاهش اعتبار',
        commission: 'کارمزد',
        refund: 'استرداد',
        settlement: 'تسویه',
        wallet_payment: 'پرداخت از کیف پول',
        manual_adjustment: 'اصلاح دستی'
    };

    const settlementLabels = {
        pending: 'در انتظار',
        approved: 'تاییدشده',
        rejected: 'ردشده',
        paid: 'پرداخت‌شده',
        cancelled: 'لغو شده'
    };

    function statusLabel(status) {
        return statusLabels[String(status || '').toLowerCase()] || 'در حال بررسی';
    }

    function slotStatusLabel(status) {
        return slotStatusLabels[String(status || '').toLowerCase()] || statusLabel(status);
    }

    function fieldLabel(field) {
        return fieldLabels[String(field || '').toLowerCase()] || 'مبلغ';
    }

    function statusTone(status) {
        const value = String(status || '').toLowerCase();
        if (['available', 'confirmed', 'completed', 'paid', 'wallet_paid', 'credit', 'approved'].includes(value)) {
            return 'success';
        }
        if (['locked', 'pending', 'pay_at_clinic', 'cash_at_clinic', 'pos_at_clinic', 'unpaid'].includes(value)) {
            return 'warning';
        }
        if (['cancelled', 'failed', 'no_show', 'rejected', 'debit'].includes(value)) {
            return 'danger';
        }
        if (['booked', 'past', 'expired', 'expired_lock_wallet_charged', 'settlement', 'commission'].includes(value)) {
            return 'muted';
        }

        return 'info';
    }

    function displayStatusLabel(label, status) {
        const cleanLabel = String(label || '').trim();
        if (!cleanLabel || cleanLabel === String(status || '') || /^[a-z0-9_-]+$/i.test(cleanLabel)) {
            return statusLabel(status);
        }

        return cleanLabel;
    }

    function ledgerLabel(type) {
        return ledgerLabels[type] || 'نامشخص';
    }

    function settlementLabel(status) {
        return settlementLabels[status] || 'نامشخص';
    }

    function weekdayLabel(day) {
        return weekdayLabels[day] || 'نامشخص';
    }

    function exceptionTypeLabel(type) {
        return exceptionTypeLabels[type] || 'نامشخص';
    }

    function jalali() {
        return window.WebtananJalaliCalendar || null;
    }

    function displayDate(value, withWeekday = true) {
        const helper = jalali();
        return helper ? helper.formatJalaliDate(value, withWeekday) : faDate(value);
    }

    function addDaysISO(value, days) {
        const helper = jalali();
        if (helper) {
            return helper.formatISO(helper.addDays(value, days));
        }
        const date = new Date(`${value}T00:00:00`);
        date.setDate(date.getDate() + days);
        return date.toISOString().slice(0, 10);
    }

    function calendarDateRange(mode, value) {
        const helper = jalali();
        if (helper && mode === 'week') {
            return helper.weekRange(value);
        }
        if (helper && mode === 'month') {
            return helper.jalaliMonthRange(value);
        }
        if (mode === 'week') {
            return Array.from({ length: 7 }, (_, index) => addDaysISO(value, index));
        }
        if (mode === 'month') {
            return Array.from({ length: 30 }, (_, index) => addDaysISO(value, index));
        }

        return [value];
    }

    function slotDetailedStatus(slot) {
        if (slot.appointment_status && slot.appointment_status !== 'pending') {
            return slot.appointment_status;
        }

        return slot.status || 'available';
    }

    function appointmentTimeRange(item) {
        const start = item.start_time || '';
        const end = item.end_time || '';
        return item.time_range || (end ? `${start} - ${end}` : start);
    }

    function appointmentPatientName(item) {
        return item.patient_display_name || item.patient_full_name || `${item.patient_first_name || ''} ${item.patient_last_name || ''}`.trim() || 'بیمار';
    }

    function doctorNextAvailableMarkup(slot) {
        if (!slot) {
            return `<div class="webtanan-next-card wb-next-availability webtanan-next-card-empty"><span>اولین نوبت آزاد</span><strong>فعلا نوبت آزادی ثبت نشده است</strong></div>`;
        }

        const date = slot.date || slot.appointment_date || '';
        const time = slot.start_time || '';
        return `<div class="webtanan-next-card wb-next-availability"><span>اولین نوبت آزاد</span><strong>${esc(displayDate(date, false))}${time ? ` - ${esc(time)}` : ''}</strong></div>`;
    }

    function nextAvailableActionMarkup() {
        return `<div class="wb-no-slot-action">
            <p>برای این روز ساعت آزادی باقی نمانده است.</p>
            <button type="button" class="webtanan-button wb-next-slot-button" data-action="show-next-available">
                <i class="far fa-calendar-check" aria-hidden="true"></i>
                نمایش اولین نوبت آزاد
            </button>
        </div>`;
    }

    function specialtyFilterUrl(id, name = '', preferredUrl = '') {
        if (preferredUrl) {
            return preferredUrl;
        }
        const url = new URL(cfg.archiveUrl || '/?post_type=saas_doctors', window.location.href);
        if (Number(id || 0) > 0) {
            url.searchParams.set('specialty_id', String(id));
        } else if (name) {
            url.searchParams.set('search', name);
        }

        return url.pathname + url.search;
    }

    function specialtyLinkMarkup(id, name, extraClass = '', preferredUrl = '') {
        if (!name) {
            return '';
        }

        return `<a class="wb-specialty-link ${esc(extraClass)}" href="${esc(specialtyFilterUrl(id, name, preferredUrl))}">${esc(name)}</a>`;
    }

    function doctorCard(doctor) {
        return doctorCardUnified(doctor);
    }

    function doctorCardUnified(doctor) {
        const title = doctor.title || doctor.clinic_name || 'پزشک';
        const permalink = doctor.permalink || '#';
        const image = doctor.thumbnail
            ? `<img src="${esc(doctor.thumbnail)}" alt="${esc(title)}" loading="lazy">`
            : `<i class="fas fa-user-md" aria-hidden="true"></i>`;
        const clinicName = doctor.clinic_name || '';
        const address = doctor.clinic_short_address || doctor.clinic_address || '';
        const medicalCode = doctor.medical_system_number || '';
        const rating = Number(doctor.rating || 0);
        const reviews = Number(doctor.reviews_count || 0);
        const ratingLabel = reviews > 0 ? `${rating.toLocaleString('fa-IR', { maximumFractionDigits: 1 })} (${reviews.toLocaleString('fa-IR')} نظر)` : 'بدون نظر';
        const verified = doctor.is_verified
            ? '<span class="wb-verified-mark" title="پزشک تاییدشده" aria-label="پزشک تاییدشده"><i class="fas fa-check" aria-hidden="true"></i></span>'
            : '';
        const hasInlineNextAvailable = Object.prototype.hasOwnProperty.call(doctor, 'next_available');
        const nextAvailable = hasInlineNextAvailable
            ? doctorNextAvailableMarkup(doctor.next_available)
            : `<div class="webtanan-next-available" data-webtanan-widget="next-available" data-doctor-id="${esc(doctor.id || 0)}"></div>`;

        return `<article class="doctor-card" data-doctor-id="${esc(doctor.id || '')}">
            <div class="doc-header">
                <a class="doc-avatar" href="${esc(permalink)}" aria-label="${esc(title)}">${image}</a>
                <div class="doc-info">
                    <h3><a href="${esc(permalink)}">${esc(title)}</a>${verified}</h3>
                    <div class="specialty">${specialtyLinkMarkup(doctor.specialty_id, doctor.specialty_name, 'wb-specialty-link', doctor.specialty_url || '')}</div>
                    <div class="rating"><i class="fas fa-star"></i><span>${esc(ratingLabel)}</span></div>
                </div>
            </div>
            <div class="doc-meta">
                ${address ? `<p><i class="fas fa-map-marker-alt"></i>${esc(address)}</p>` : ''}
                ${clinicName ? `<p><i class="fas fa-hospital"></i>${esc(clinicName)}</p>` : ''}
                ${medicalCode ? `<p><i class="fas fa-id-badge"></i>کد نظام پزشکی: ${esc(medicalCode)}</p>` : ''}
                <div class="wb-next-available-line"><i class="far fa-clock"></i>${nextAvailable}</div>
            </div>
            <a href="${esc(permalink)}#booking" class="btn btn-primary wb-doctor-card-booking"><i class="fas fa-calendar-plus"></i> رزرو نوبت</a>
            <a href="${esc(permalink)}" class="doctor-profile-link">مشاهده پروفایل</a>
        </article>`;
    }

    function initDoctorList(el, search = '') {
        const perPage = el.dataset.perPage || '12';
        const specialtyId = el.dataset.specialtyId || '';
        const cityId = el.dataset.cityId || '';
        const provinceId = el.dataset.provinceId || '';
        const sort = el.dataset.sort || '';
        const layout = el.dataset.layout === 'list' ? 'list' : 'grid';
        const online = el.dataset.online || '';
        const payAtClinic = el.dataset.payAtClinic || '';
        const availableOnly = el.dataset.availableOnly || '';
        el.innerHTML = loadingPanel(cfg.strings && cfg.strings.loading || 'در حال بارگذاری...');
        request(`/doctors?${qs({ per_page: perPage, search, specialty_id: specialtyId, city_id: cityId, province_id: provinceId, sort, online, pay_at_clinic: payAtClinic, available_only: availableOnly })}`)
            .then((doctors) => {
                if (!Array.isArray(doctors) || !doctors.length) {
                    el.innerHTML = `<div class="webtanan-empty-state">پزشکی با این فیلترها پیدا نشد.</div>`;
                    return;
                }

                el.innerHTML = `<div class="results-header"><div class="count"><strong>${money(doctors.length)}</strong> پزشک پیدا شد</div><div class="sort"><span>مرتب‌سازی:</span><strong>نزدیک‌ترین نوبت</strong></div></div><div class="doctor-list webtanan-doctor-grid webtanan-doctor-grid-${esc(layout)}">${doctors.map(doctorCardUnified).join('')}</div>`;
                el.querySelectorAll('[data-webtanan-widget="next-available"]').forEach(initNextAvailable);
            })
            .catch((error) => {
                el.innerHTML = panel(error.message);
            });
    }

    function initDoctorSearch(el) {
        const form = el.querySelector('.webtanan-doctor-search-form');
        const input = el.querySelector('.webtanan-doctor-search-input');
        const button = el.querySelector('.webtanan-search-button');
        const specialty = el.querySelector('.webtanan-doctor-specialty-filter');
        const province = el.querySelector('.webtanan-doctor-province-filter');
        const city = el.querySelector('.webtanan-doctor-city-filter');
        const sort = el.querySelector('.webtanan-doctor-sort-filter');
        const availableOnly = el.querySelector('.webtanan-doctor-available-filter');
        const results = el.querySelector('.webtanan-doctor-results');
        if (results && el.dataset.perPage) {
            results.dataset.perPage = el.dataset.perPage;
            results.dataset.layout = el.dataset.layout || 'grid';
        }
        const syncFilters = () => {
            if (!results) {
                return;
            }

            results.dataset.specialtyId = specialty ? specialty.value : (el.dataset.specialtyId || '');
            results.dataset.cityId = city ? city.value : (el.dataset.cityId || '');
            results.dataset.provinceId = province ? province.value : (el.dataset.provinceId || '');
            results.dataset.sort = sort ? sort.value : (el.dataset.sort || 'first_available');
            results.dataset.availableOnly = availableOnly && availableOnly.checked ? '1' : (el.dataset.availableOnly || '');
        };
        const syncUrl = () => {
            const params = new URLSearchParams(window.location.search);
            params.set('post_type', 'saas_doctors');
            const values = {
                search: input ? input.value.trim() : '',
                specialty_id: specialty ? specialty.value : '',
                province_id: province ? province.value : '',
                city_id: city ? city.value : '',
                sort: sort ? sort.value : '',
                available_only: availableOnly && availableOnly.checked ? '1' : ''
            };

            Object.keys(values).forEach((key) => {
                if (values[key] && values[key] !== '0') {
                    params.set(key, values[key]);
                } else {
                    params.delete(key);
                }
            });

            window.history.replaceState({}, '', `${window.location.pathname}?${params.toString()}`);
        };
        const run = (event) => {
            event && event.preventDefault();
            syncFilters();
            if (event) {
                syncUrl();
            }
            initDoctorList(results, input ? input.value : '');
        };
        form && form.addEventListener('submit', run);
        button && !form && button.addEventListener('click', run);
        input && input.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
                event.preventDefault();
                run();
            }
        });
        [specialty, province, city, sort].forEach((field) => {
            field && field.addEventListener('change', run);
        });
        availableOnly && availableOnly.addEventListener('change', run);
        run();
    }

    function initAuth(el) {
        const mobileForm = el.querySelector('.webtanan-auth-mobile');
        const otpForm = el.querySelector('.webtanan-auth-otp');
        const profileForm = el.querySelector('.webtanan-auth-profile');
        const backButton = el.querySelector('.webtanan-auth-back');
        const resendButton = el.querySelector('.webtanan-auth-resend');
        const message = el.querySelector('.webtanan-auth-message');
        let mobile = '';
        let authResult = cfg.authContext || {};
        let accountType = 'patient';
        let completionToken = '';

        const redirectToAccount = (context = {}) => {
            const url = context.redirect_url || el.dataset.redirectUrl || cfg.loginUrl || window.location.href;
            window.location.assign(url);
        };

        const showProfileCompletion = (context = {}) => {
            const profile = context.profile || {};
            if (context.account_type === 'doctor' || context.doctor_application_pending) {
                accountType = 'doctor';
            }
            mobileForm.hidden = true;
            otpForm.hidden = true;
            profileForm.hidden = false;
            ['first_name', 'last_name', 'national_code'].forEach((key) => {
                const input = profileForm.querySelector(`[name="${key}"]`);
                if (input) {
                    input.value = profile[key] || '';
                }
            });
            const doctorFields = profileForm.querySelector('.wb-doctor-application-fields');
            if (doctorFields) {
                const showDoctorFields = context.doctor_application_pending || accountType === 'doctor';
                doctorFields.hidden = !showDoctorFields;
                doctorFields.querySelectorAll('input').forEach((input) => {
                    input.required = showDoctorFields;
                });
            }
            message.textContent = accountType === 'doctor'
                ? 'اطلاعات هویتی و حرفه‌ای را برای بررسی عضویت پزشک کامل کنید.'
                : 'برای ثبت نوبت، اطلاعات هویتی حساب را کامل کنید.';
            const first = profileForm.querySelector('input');
            first && first.focus();
        };

        const renderLoggedIn = (context = {}) => {
            authResult = context;
            if (context.profile_complete === false && !(context.roles || []).some((role) => ['webtanan_doctor', 'webtanan_secretary', 'administrator'].includes(role))) {
                showProfileCompletion(authResult);
                return;
            }
            el.innerHTML = `<div class="webtanan-panel wb-auth-signed-in"><strong>شما وارد حساب خود شده‌اید.</strong><div><a class="webtanan-button webtanan-button-primary" href="${esc(context.redirect_url || el.dataset.redirectUrl || '/')}">ورود به پنل</a><button type="button" class="webtanan-button webtanan-auth-logout">خروج از حساب</button></div></div>`;
            el.querySelector('.webtanan-auth-logout').addEventListener('click', () => {
                request('/auth/logout', { method: 'POST', body: '{}' }).then(() => window.location.reload());
            });
        };

        if (cfg.isLoggedIn) {
            message.textContent = cfg.strings && cfg.strings.loading || 'در حال بارگذاری...';
            request('/auth/context')
                .then(renderLoggedIn)
                .catch((error) => {
                    message.textContent = error.message;
                });
            return;
        }

        mobileForm.addEventListener('submit', (event) => {
            event.preventDefault();
            const submitButton = mobileForm.querySelector('[type="submit"]');
            if (submitButton) {
                submitButton.disabled = true;
                submitButton.classList.add('is-loading');
            }
            mobile = new FormData(mobileForm).get('mobile') || '';
            accountType = new FormData(mobileForm).get('account_type') === 'doctor' ? 'doctor' : 'patient';
            message.textContent = cfg.strings && cfg.strings.loading || 'در حال بارگذاری...';
            request('/auth/send-otp', {
                method: 'POST',
                body: JSON.stringify({ mobile, purpose: 'login' }),
                skipNonce: true
            }).then((result) => {
                mobileForm.hidden = true;
                otpForm.hidden = false;
                message.textContent = 'کد ورود ارسال شد.';
                enhanceOtpInputs(otpForm);
                startOtpCountdown(otpForm, result.expires_in || 180);
                focusOtpForm(otpForm);
            }).catch((error) => {
                message.textContent = error.message;
                if (submitButton) {
                    submitButton.disabled = false;
                    submitButton.classList.remove('is-loading');
                }
            });
        });

        otpForm.addEventListener('submit', (event) => {
            event.preventDefault();
            const otp = new FormData(otpForm).get('otp') || '';
            message.textContent = cfg.strings && cfg.strings.loading || 'در حال بارگذاری...';
            request('/auth/verify-otp', {
                method: 'POST',
                body: JSON.stringify({ mobile, otp, purpose: 'login', account_type: accountType }),
                skipNonce: true
            }).then((result) => {
                stopOtpCountdown(otpForm);
                if (result.nonce) {
                    cfg.nonce = result.nonce;
                }
                cfg.isLoggedIn = true;
                completionToken = result.completion_token || '';
                authResult = result;
                cfg.authContext = result;
                const roles = Array.isArray(result.roles) ? result.roles : [];
                const clinicUser = roles.some((role) => ['webtanan_doctor', 'webtanan_secretary', 'administrator'].includes(role));
                if (result.doctor_application_pending && !clinicUser) {
                    showProfileCompletion(result);
                    return;
                }
                if (clinicUser || result.profile_complete) {
                    message.textContent = 'ورود با موفقیت انجام شد. در حال انتقال...';
                    redirectToAccount(result);
                    return;
                }
                showProfileCompletion(result);
            }).catch((error) => {
                message.textContent = error.message;
            });
        });

        backButton && backButton.addEventListener('click', () => {
            otpForm.hidden = true;
            mobileForm.hidden = false;
            message.textContent = '';
            const submitButton = mobileForm.querySelector('[type="submit"]');
            if (submitButton) {
                submitButton.disabled = false;
                submitButton.classList.remove('is-loading');
            }
        });

        resendButton && resendButton.addEventListener('click', () => {
            if (!mobile) {
                message.textContent = 'ابتدا شماره موبایل را وارد کنید.';
                return;
            }
            resendButton.disabled = true;
            resendButton.classList.add('is-loading');
            message.textContent = 'در حال ارسال دوباره کد...';
            request('/auth/send-otp', {
                method: 'POST',
                body: JSON.stringify({ mobile, purpose: 'login' }),
                skipNonce: true
            }).then((result) => {
                message.textContent = 'کد تازه ارسال شد.';
                startOtpCountdown(otpForm, result.expires_in || 180);
                focusOtpForm(otpForm);
            }).catch((error) => {
                message.textContent = error.message;
            }).finally(() => {
                resendButton.disabled = false;
                resendButton.classList.remove('is-loading');
            });
        });

        profileForm && profileForm.addEventListener('submit', (event) => {
            event.preventDefault();
            message.textContent = cfg.strings && cfg.strings.loading || 'در حال ذخیره اطلاعات...';
            request('/auth/complete-profile', {
                method: 'POST',
                body: JSON.stringify(Object.assign(formObject(profileForm), { completion_token: completionToken })),
                skipNonce: Boolean(completionToken)
            }).then((result) => {
                if (result.nonce) {
                    cfg.nonce = result.nonce;
                }
                authResult = result;
                cfg.authContext = result;
                completionToken = '';
                if (result.doctor_application_pending) {
                    el.innerHTML = `<div class="webtanan-panel wb-auth-application-success"><strong>درخواست عضویت پزشک ثبت شد</strong><p>پس از بررسی اطلاعات و تایید مدیریت، دسترسی پیشخوان پزشک برای شما فعال می‌شود.</p><a class="webtanan-button webtanan-button-primary" href="${esc(cfg.archiveUrl || '/')}">مشاهده پزشکان</a></div>`;
                    return;
                }
                message.textContent = 'اطلاعات شما ذخیره شد. در حال انتقال به پنل...';
                redirectToAccount(result);
            }).catch((error) => {
                message.textContent = error.message;
            });
        });
    }

    function initCalendar(el) {
        const doctorId = el.dataset.doctorId;
        const dateInput = el.querySelector('.webtanan-slot-date');
        const loadButton = el.querySelector('.webtanan-load-slots');
        const slotsWrap = el.querySelector('.webtanan-slots');
        const form = el.querySelector('.webtanan-booking-form');
        const gatewaySelect = el.querySelector('.webtanan-gateway-select');
        const message = el.querySelector('.webtanan-booking-message');
        let selectedSlot = null;
        let gateways = [];

        if (!doctorId || doctorId === '0') {
            slotsWrap.innerHTML = panel('شناسه پزشک تنظیم نشده است.');
            return;
        }

        function loadSlots() {
            selectedSlot = null;
            form.hidden = true;
            message.textContent = '';
            slotsWrap.innerHTML = panel(cfg.strings && cfg.strings.loading || 'در حال بارگذاری...');
            request(`/doctors/${doctorId}/slots?date=${encodeURIComponent(dateInput.value || cfg.today)}`)
                .then((slots) => {
                    if (!slots.length) {
                        slotsWrap.innerHTML = `${panel(cfg.strings && cfg.strings.noSlots || 'نوبت آزادی پیدا نشد.')}${nextAvailableActionMarkup()}`;
                        return;
                    }
                    const hasAvailable = slots.some((slot) => slot.status === 'available');
                    slotsWrap.innerHTML = `<div class="webtanan-slot-date-title">${esc(displayDate(dateInput.value || cfg.today))}</div>
                        <div class="webtanan-slot-legend">
                            <span data-status="available">آزاد</span>
                            <span data-status="locked">در حال رزرو</span>
                            <span data-status="booked">پر شده</span>
                        </div>` + slots.map((slot) => {
                        const disabled = slot.status !== 'available' ? 'disabled' : '';
                        return `<button type="button" class="webtanan-slot" data-status="${esc(slot.status)}" data-date="${esc(slot.date)}" data-start="${esc(slot.start_time)}" ${disabled}><strong>${esc(slot.start_time)}</strong><span>${esc(slotStatusLabel(slot.status))}</span></button>`;
                    }).join('') + (hasAvailable ? '' : nextAvailableActionMarkup());
                })
                .catch((error) => {
                    slotsWrap.innerHTML = panel(error.message);
                });
        }

        function loadPaymentGateways() {
            if (!gatewaySelect) {
                return Promise.resolve([]);
            }

            gatewaySelect.innerHTML = `<option value="">${esc(cfg.strings && cfg.strings.loading || 'در حال بارگذاری...')}</option>`;

            return request('/payment/gateways')
                .then((items) => {
                    gateways = Array.isArray(items) ? items : [];
                    if (!gateways.length) {
                        gatewaySelect.innerHTML = '<option value="">درگاه فعالی تنظیم نشده است</option>';
                        return gateways;
                    }

                    gatewaySelect.innerHTML = gateways.map((gateway) => `<option value="${esc(gateway.id)}">${esc(gateway.title || 'درگاه آنلاین')}${gateway.sandbox ? ' - حالت تست' : ''}</option>`).join('');
                    return gateways;
                })
                .catch((error) => {
                    gateways = [];
                    gatewaySelect.innerHTML = `<option value="">${esc(error.message)}</option>`;
                    return gateways;
                });
        }

        slotsWrap.addEventListener('click', (event) => {
            const nextButton = event.target.closest('[data-action="show-next-available"]');
            if (nextButton) {
                nextButton.disabled = true;
                nextButton.classList.add('is-loading');
                request(`/doctors/${doctorId}/next-available`)
                    .then((items) => {
                        const next = Array.isArray(items) ? items[0] : null;
                        if (!next || !next.date) {
                            throw new Error('در حال حاضر نوبت آزادی برای این پزشک ثبت نشده است.');
                        }
                        dateInput.value = next.date;
                        loadSlots();
                    })
                    .catch((error) => {
                        nextButton.disabled = false;
                        nextButton.classList.remove('is-loading');
                        message.textContent = error.message;
                    });
                return;
            }
            const button = event.target.closest('.webtanan-slot');
            if (!button || button.dataset.status !== 'available') {
                return;
            }
            slotsWrap.querySelectorAll('.webtanan-slot').forEach((slot) => {
                slot.dataset.selected = 'false';
            });
            button.dataset.selected = 'true';
            selectedSlot = { date: button.dataset.date, start: button.dataset.start };
            form.hidden = false;
        });

        form.addEventListener('submit', (event) => {
            event.preventDefault();
            if (!selectedSlot) {
                return;
            }
            if (!cfg.isLoggedIn) {
                message.textContent = cfg.strings && cfg.strings.loginRequiredForPayment || 'برای پرداخت آنلاین ابتدا وارد حساب شوید.';
                return;
            }
            if (!gateways.length) {
                message.textContent = 'درگاه پرداخت فعالی تنظیم نشده است.';
                return;
            }
            const data = new FormData(form);
            message.textContent = cfg.strings && cfg.strings.loading || 'در حال بارگذاری...';
            request('/appointments/lock', {
                method: 'POST',
                body: JSON.stringify({
                    doctor_id: doctorId,
                    appointment_date: selectedSlot.date,
                    start_time: selectedSlot.start,
                    patient_first_name: data.get('patient_first_name'),
                    patient_last_name: data.get('patient_last_name'),
                    patient_national_code: data.get('patient_national_code'),
                    patient_mobile: data.get('patient_mobile'),
                    payment_method: 'online'
                })
            }).then((result) => {
                message.textContent = cfg.strings && cfg.strings.redirectingToGateway || 'در حال انتقال به درگاه پرداخت...';
                return request('/appointments/pay', {
                    method: 'POST',
                    body: JSON.stringify({
                        appointment_id: result.appointment_id,
                        lock_token: result.lock_token,
                        method: 'online',
                        gateway: gatewaySelect ? gatewaySelect.value : ''
                    })
                });
            }).then((payment) => {
                if (payment && payment.checkout_url) {
                    window.location.href = payment.checkout_url;
                    return;
                }

                message.textContent = 'نوبت برای شما نگه داشته شد، اما لینک پرداخت آماده نشد. لطفاً چند لحظه بعد دوباره تلاش کنید.';
                loadSlots();
            }).catch((error) => {
                message.textContent = error.message;
            });
        });

        loadButton && loadButton.addEventListener('click', loadSlots);
        loadPaymentGateways();
        loadSlots();
    }

    function initBookingModal(modal) {
        const doctorId = modal.dataset.doctorId;
        const panelEl = modal.querySelector('.webtanan-booking-modal-panel');
        const stepsEl = modal.querySelector('.webtanan-booking-modal-steps');
        const dayStrip = modal.querySelector('.webtanan-booking-day-strip');
        const slotsEl = modal.querySelector('.webtanan-booking-modal-slots');
        const authEl = modal.querySelector('.webtanan-booking-modal-auth');
        const authMobileForm = modal.querySelector('.webtanan-booking-auth-mobile');
        const otpForm = modal.querySelector('.webtanan-booking-modal-otp');
        const profileForm = modal.querySelector('.webtanan-booking-modal-profile');
        const personEl = modal.querySelector('.webtanan-booking-modal-person');
        const personList = modal.querySelector('.wb-booking-person-list');
        const dependentForm = modal.querySelector('.wb-booking-dependent-form');
        const paymentEl = modal.querySelector('.webtanan-booking-modal-payment');
        const messageEl = modal.querySelector('.webtanan-booking-modal-message');
        const resendOtp = modal.querySelector('.webtanan-booking-resend-otp');
        let countdownTimer = 0;
        let releaseDialogFocus = () => {};
        const state = {
            date: cfg.today,
            dayWindowStart: cfg.today,
            selectedSlot: null,
            patientContext: cfg.authContext || null,
            selectedPerson: 'self',
            authMobile: '',
            completionToken: '',
            lock: null,
            otpSent: false,
            gateways: [],
            walletBalance: null,
            paymentSelection: null
        };
        let trustEl = modal.querySelector('.webtanan-booking-trust-banner');

        // Theme containers may create clipping/stacking contexts; portal the booking dialog to body.
        if (modal.parentNode !== document.body) {
            document.body.appendChild(modal);
        }

        if (!trustEl && stepsEl && stepsEl.parentNode) {
            trustEl = document.createElement('div');
            trustEl.className = 'webtanan-booking-trust-banner';
            trustEl.hidden = true;
            stepsEl.insertAdjacentElement('afterend', trustEl);
        }

        if (!doctorId || doctorId === '0') {
            return;
        }

        function setMessage(message, tone = '') {
            messageEl.textContent = message || '';
            messageEl.dataset.tone = tone;
        }

        function setStep(step) {
            stepsEl.hidden = step === 'time';
            stepsEl.innerHTML = ['time', 'auth', 'checkout'].map((item, index) => {
                const labels = { time: 'انتخاب زمان', auth: 'مراجعه‌کننده', checkout: 'ثبت نهایی' };
                const active = item === step;
                return `<span data-active="${active ? 'true' : 'false'}"><b>${index + 1}</b>${esc(labels[item])}</span>`;
            }).join('');
        }

        function parseLockExpiry(lock) {
            const serverTimestamp = Number(lock && lock.locked_until_timestamp || 0);
            if (Number.isFinite(serverTimestamp) && serverTimestamp > 0) {
                return serverTimestamp * 1000;
            }

            const value = lock && typeof lock === 'object' ? lock.locked_until : lock;
            if (!value) {
                return Date.now() + (15 * 60 * 1000);
            }

            const parts = String(value).match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})(?::(\d{2}))?/);
            if (parts) {
                const localTime = new Date(
                    Number(parts[1]),
                    Number(parts[2]) - 1,
                    Number(parts[3]),
                    Number(parts[4]),
                    Number(parts[5]),
                    Number(parts[6] || 0)
                ).getTime();
                if (localTime > Date.now() - 30000) {
                    return localTime;
                }
            }

            return Date.now() + (15 * 60 * 1000);
        }

        function formatCountdown(seconds) {
            const safe = Math.max(0, Number(seconds || 0));
            const minutes = Math.floor(safe / 60);
            const remain = safe % 60;
            return `${String(minutes).padStart(2, '0')}:${String(remain).padStart(2, '0')}`;
        }

        function hideTrustBanner() {
            if (countdownTimer) {
                window.clearInterval(countdownTimer);
                countdownTimer = 0;
            }
            if (trustEl) {
                trustEl.hidden = true;
                trustEl.innerHTML = '';
            }
        }

        function resetToTimeSelection(message = '') {
            hideTrustBanner();
            state.lock = null;
            state.otpSent = false;
            state.paymentSelection = null;
            authEl.hidden = true;
            otpForm.hidden = true;
            profileForm.hidden = true;
            personEl.hidden = true;
            paymentEl.hidden = true;
            paymentEl.innerHTML = '';
            slotsEl.hidden = false;
            setStep('time');
            loadSlots(state.date);
            if (message) {
                setMessage(message, 'error');
            }
        }

        function startCountdown(lock) {
            hideTrustBanner();
            const expiresAt = parseLockExpiry(lock);

            const tick = () => {
                const remaining = Math.ceil((expiresAt - Date.now()) / 1000);
                if (remaining <= 0) {
                    const expiredMessage = 'مهلت رزرو موقت به پایان رسید. لطفاً مجدداً تایم را انتخاب کنید.';
                    toast(expiredMessage, 'error');
                    resetToTimeSelection(expiredMessage);
                    return;
                }

                if (trustEl) {
                    trustEl.hidden = false;
                    trustEl.innerHTML = `⏳ نوبت برای شما نگه‌داشته شد. زمان باقی‌مانده: <strong class="wb-lock-countdown">${esc(formatCountdown(remaining))}</strong>`;
                }
            };

            tick();
            countdownTimer = window.setInterval(tick, 1000);
        }

        function openModal() {
            modal.hidden = false;
            document.documentElement.classList.add('webtanan-modal-open');
            releaseDialogFocus();
            releaseDialogFocus = activateDialogFocus(panelEl, closeModal);
            if (state.lock && parseLockExpiry(state.lock) > Date.now()) {
                startCountdown(state.lock);
                showPaymentStep().catch((error) => setMessage(error.message, 'error'));
                setTimeout(() => panelEl && panelEl.focus && panelEl.focus(), 30);
                return;
            }
            state.lock = null;
            setStep('time');
            renderDays();
            loadSlots(state.date);
            setTimeout(() => panelEl && panelEl.focus && panelEl.focus(), 30);
        }

        function closeModal() {
            hideTrustBanner();
            releaseDialogFocus();
            releaseDialogFocus = () => {};
            modal.hidden = true;
            document.documentElement.classList.remove('webtanan-modal-open');
        }

        function renderDays() {
            const days = Array.from({ length: 14 }, (_, index) => addDaysISO(state.dayWindowStart || cfg.today, index));
            dayStrip.innerHTML = days.map((date, index) => {
                const active = date === state.date;
                const isToday = date === cfg.today;
                const value = new Date(`${date}T00:00:00`);
                let weekday = '';
                let day = '';
                let month = '';
                try {
                    weekday = new Intl.DateTimeFormat('fa-IR-u-ca-persian', { weekday: 'long' }).format(value);
                    day = new Intl.DateTimeFormat('fa-IR-u-ca-persian', { day: 'numeric' }).format(value);
                    month = new Intl.DateTimeFormat('fa-IR-u-ca-persian', { month: 'short' }).format(value);
                } catch (error) {
                    const label = displayDate(date, false);
                    weekday = isToday ? 'امروز' : label;
                    day = '';
                    month = '';
                }
                return `<button type="button" class="webtanan-booking-day" data-date="${esc(date)}" data-active="${active ? 'true' : 'false'}">
                    <span>${isToday ? 'امروز' : esc(weekday)}</span>
                    <strong>${esc(day)}</strong>
                    <small>${esc(month)}</small>
                </button>`;
            }).join('');
        }

        function resetAfterDateChange() {
            hideTrustBanner();
            state.selectedSlot = null;
            state.lock = null;
            state.otpSent = false;
            state.paymentSelection = null;
            authEl.hidden = true;
            otpForm.hidden = true;
            profileForm.hidden = true;
            personEl.hidden = true;
            paymentEl.hidden = true;
            paymentEl.innerHTML = '';
            slotsEl.hidden = false;
            setMessage('');
        }

        function loadSlots(date) {
            resetAfterDateChange();
            setStep('time');
            slotsEl.innerHTML = panel(cfg.strings && cfg.strings.loading || 'در حال بارگذاری...');
            request(`/doctors/${doctorId}/slots?date=${encodeURIComponent(date)}`)
                .then((slots) => {
                    if (!Array.isArray(slots) || !slots.length) {
                        slotsEl.innerHTML = `${panel(cfg.strings && cfg.strings.noSlots || 'نوبت آزادی پیدا نشد.')}${nextAvailableActionMarkup()}`;
                        return;
                    }

                    const hasAvailable = slots.some((slot) => slot.status === 'available');
                    slotsEl.innerHTML = `<div class="webtanan-booking-modal-legend">
                        <span data-status="available"><i aria-hidden="true"></i>ساعت آزاد</span>
                        <span data-status="locked"><i aria-hidden="true"></i>در حال رزرو</span>
                        <span data-status="booked"><i aria-hidden="true"></i>پر شده</span>
                        <span data-status="past"><i aria-hidden="true"></i>زمان گذشته</span>
                    </div>
                    <div class="webtanan-booking-modal-slot-grid">${slots.map((slot) => {
                        const disabled = slot.status !== 'available' ? 'disabled' : '';
                        return `<button type="button" class="webtanan-booking-modal-slot" data-status="${esc(slot.status)}" data-date="${esc(slot.date)}" data-start="${esc(slot.start_time)}" ${disabled}>
                            <strong>${esc(slot.start_time)}</strong>
                            <span>${esc(slotStatusLabel(slot.status))}</span>
                        </button>`;
                    }).join('')}</div>${hasAvailable ? '' : nextAvailableActionMarkup()}`;
                })
                .catch((error) => {
                    slotsEl.innerHTML = panel(error.message);
                });
        }

        function lockSelectedSlot() {
            const payload = {
                doctor_id: doctorId,
                appointment_date: state.selectedSlot.date,
                start_time: state.selectedSlot.start,
                dependent_id: state.selectedPerson === 'self' ? '' : state.selectedPerson,
                payment_method: 'online'
            };
            setMessage('در حال نگه‌داشتن این زمان برای شما...');
            return request('/appointments/lock', {
                method: 'POST',
                body: JSON.stringify(payload)
            }).then((lock) => {
                state.lock = lock;
                startCountdown(lock);
                setMessage('تایم نوبت برای شما رزرو شد. لطفاً ادامه مراحل را کامل کنید.', 'success');
                return lock;
            });
        }

        function hideIdentitySections() {
            authEl.hidden = true;
            otpForm.hidden = true;
            profileForm.hidden = true;
            personEl.hidden = true;
        }

        function showAuthStep() {
            setStep('auth');
            slotsEl.hidden = true;
            hideIdentitySections();
            authEl.hidden = false;
            authMobileForm.hidden = false;
            setMessage('برای ادامه رزرو، وارد حساب خود شوید.');
            const input = authMobileForm.querySelector('input[name="mobile"]');
            input && input.focus();
        }

        function fillProfileForm(context) {
            const profile = context && context.profile ? context.profile : {};
            ['first_name', 'last_name', 'national_code'].forEach((key) => {
                const input = profileForm.querySelector(`[name="${key}"]`);
                if (input) {
                    input.value = profile[key] || '';
                }
            });
        }

        function showProfileStep(context = {}) {
            setStep('auth');
            slotsEl.hidden = true;
            hideIdentitySections();
            profileForm.hidden = false;
            fillProfileForm(context);
            setMessage('برای صدور رسید نوبت، اطلاعات صاحب حساب را کامل کنید.');
            const first = profileForm.querySelector('input');
            first && first.focus();
        }

        function personCard(person, value, selected) {
            const mobile = person.mobile || '';
            const relation = value === 'self' ? 'خودم' : (person.relationship || 'عضو خانواده');
            return `<label class="wb-booking-person-card" data-selected="${selected ? 'true' : 'false'}">
                <input type="radio" name="booking_person" value="${esc(value)}" ${selected ? 'checked' : ''}>
                <span class="wb-booking-person-radio" aria-hidden="true"></span>
                <span class="wb-booking-person-copy">
                    <strong>${esc(person.full_name || person.display_name || 'مراجعه‌کننده')}</strong>
                    <small>${esc(relation)}${mobile ? ` · ${esc(mobile)}` : ''}</small>
                </span>
            </label>`;
        }

        function renderPersonList(context) {
            const profile = context.profile || {};
            const dependents = Array.isArray(context.dependents) ? context.dependents : [];
            const validValues = ['self'].concat(dependents.map((item) => item.id));
            if (!validValues.includes(state.selectedPerson)) {
                state.selectedPerson = 'self';
            }

            personList.innerHTML = personCard(profile, 'self', state.selectedPerson === 'self')
                + dependents.map((dependent) => personCard(dependent, dependent.id, state.selectedPerson === dependent.id)).join('');
        }

        function showPersonStep(context) {
            state.patientContext = context;
            cfg.authContext = context;
            setStep('auth');
            slotsEl.hidden = true;
            hideIdentitySections();
            personEl.hidden = false;
            dependentForm.hidden = true;
            const continueButton = personEl.querySelector('.wb-booking-person-continue');
            if (continueButton) {
                continueButton.disabled = false;
            }
            renderPersonList(context);
            setMessage('مراجعه‌کننده را انتخاب کنید و ادامه دهید.');
        }

        function ensurePatientContext() {
            return request('/patient-panel/profile').then((context) => {
                state.patientContext = context;
                cfg.authContext = context;
                if (!context.profile_complete) {
                    showProfileStep(context);
                    return context;
                }
                showPersonStep(context);
                return context;
            });
        }

        function sendOtp() {
            if (!state.authMobile) {
                return Promise.reject(new Error('شماره موبایل وارد نشده است.'));
            }
            setStep('auth');
            slotsEl.hidden = true;
            paymentEl.hidden = true;
            authEl.hidden = false;
            authMobileForm.hidden = false;
            otpForm.hidden = true;
            setMessage('در حال ارسال کد تایید...');
            return request('/auth/send-otp', {
                method: 'POST',
                body: JSON.stringify({ mobile: state.authMobile, purpose: 'login' }),
                skipNonce: true
            }).then((result) => {
                state.otpSent = true;
                authMobileForm.hidden = true;
                otpForm.hidden = false;
                const description = otpForm.querySelector('p');
                if (description) {
                    description.textContent = `کد تایید برای ${state.authMobile} ارسال شد.`;
                }
                setMessage('کد تایید ارسال شد.', 'success');
                enhanceOtpInputs(otpForm);
                startOtpCountdown(otpForm, result.expires_in || 180);
                focusOtpForm(otpForm);
                return result;
            }).catch((error) => {
                state.otpSent = false;
                otpForm.hidden = true;
                authMobileForm.hidden = false;
                const mobileInput = authMobileForm.querySelector('input[name="mobile"]');
                mobileInput && mobileInput.focus();
                throw error;
            });
        }

        function verifyOtp() {
            const otp = new FormData(otpForm).get('otp') || '';
            setMessage('در حال بررسی کد تایید...');
            return request('/auth/verify-otp', {
                method: 'POST',
                body: JSON.stringify({ mobile: state.authMobile, otp, purpose: 'login', account_type: 'patient' }),
                skipNonce: true
            }).then((result) => {
                stopOtpCountdown(otpForm);
                if (result.nonce) {
                    cfg.nonce = result.nonce;
                }
                cfg.isLoggedIn = true;
                state.completionToken = result.completion_token || '';
                cfg.authContext = result;
                state.patientContext = result;
                setMessage('شماره موبایل تایید شد.', 'success');
                if (!result.profile_complete) {
                    showProfileStep(result);
                    return result;
                }
                return ensurePatientContext();
            });
        }

        function loadPaymentData() {
            const gatewaysPromise = request('/payment/gateways').catch(() => []);
            const walletPromise = request('/wallet/balance?user_type=patient').catch(() => ({ balance: 0 }));

            return Promise.all([gatewaysPromise, walletPromise]).then(([gateways, wallet]) => {
                state.gateways = Array.isArray(gateways) ? gateways : [];
                state.walletBalance = Number(wallet && wallet.balance || 0);
            });
        }

        function selectPaymentOption(button) {
            if (!button || button.disabled) {
                return;
            }
            paymentEl.querySelectorAll('.webtanan-payment-option').forEach((option) => {
                option.dataset.selected = 'false';
            });
            button.dataset.selected = 'true';
            state.paymentSelection = {
                method: button.dataset.method || '',
                gateway: button.dataset.gateway || ''
            };
            const submit = paymentEl.querySelector('.webtanan-pay-submit');
            if (submit) {
                submit.disabled = false;
            }
        }

        function showPaymentStep() {
            setStep('checkout');
            slotsEl.hidden = true;
            hideIdentitySections();
            paymentEl.hidden = false;
            paymentEl.innerHTML = loadingPanel('در حال آماده کردن فاکتور نوبت...');
            return loadPaymentData().then(() => {
                const amount = Number(state.lock && state.lock.amount || 0);
                const walletDisabled = state.walletBalance < amount ? 'disabled' : '';
                const walletText = state.walletBalance < amount ? 'کیف پول؛ موجودی کافی نیست' : 'پرداخت از کیف پول';
                const gatewayButtons = state.gateways.map((gateway, index) => `<button type="button" class="webtanan-payment-option" data-method="online" data-gateway="${esc(gateway.id)}" data-selected="${index === 0 ? 'true' : 'false'}">
                    <span class="wb-payment-radio" aria-hidden="true"></span>
                    <strong>${esc(gateway.title || 'درگاه آنلاین')}</strong>
                    <small>${gateway.sandbox ? 'پرداخت آزمایشی' : 'پرداخت آنلاین امن'}</small>
                </button>`).join('');
                const walletSelected = !state.gateways.length && !walletDisabled;
                state.paymentSelection = state.gateways.length
                    ? { method: 'online', gateway: state.gateways[0].id || '' }
                    : (walletSelected ? { method: 'wallet', gateway: '' } : null);

                paymentEl.innerHTML = `<div class="webtanan-checkout wb-booking-checkout">
                    <header class="webtanan-checkout-head">
                        <span>فاکتور نوبت</span>
                        <strong>تکمیل نوبت</strong>
                    </header>
                    <div class="webtanan-payment-summary">
                        <div><span>پزشک</span><strong>${esc(modal.dataset.doctorTitle || 'پزشک')}</strong></div>
                        <div><span>تاریخ نوبت</span><strong>${esc(displayDate(state.selectedSlot.date, false))}</strong></div>
                        <div><span>ساعت نوبت</span><strong>${esc(state.selectedSlot.start)}</strong></div>
                        <div><span>${fieldLabel('booking_fee')}</span><strong>${money(amount)} تومان</strong></div>
                    </div>
                    <p class="webtanan-checkout-note">اگر پرداخت دیر به سایت برگردد و این زمان از دست برود، مبلغ پرداختی خودکار به کیف پول شما برمی‌گردد.</p>
                    <div class="webtanan-payment-options" role="radiogroup" aria-label="انتخاب روش پرداخت">
                        <button type="button" class="webtanan-payment-option" data-method="wallet" data-selected="${walletSelected ? 'true' : 'false'}" ${walletDisabled}>
                            <span class="wb-payment-radio" aria-hidden="true"></span>
                            <strong>${esc(walletText)}</strong>
                            <small>موجودی: ${money(state.walletBalance)} تومان</small>
                        </button>
                        ${gatewayButtons || '<div class="webtanan-panel">فعلاً درگاه آنلاین فعالی وجود ندارد.</div>'}
                    </div>
                    <button type="button" class="webtanan-button webtanan-button-primary wb-btn wb-btn-success webtanan-pay-submit" ${state.paymentSelection ? '' : 'disabled'}>
                        <span class="wb-submit-label">پرداخت و ثبت قطعی نوبت</span>
                        <span class="webtanan-spinner" aria-hidden="true"></span>
                    </button>
                </div>`;
            });
        }

        function pay(method, gateway = '', trigger = null) {
            if (!state.lock) {
                resetToTimeSelection('مهلت رزرو موقت به پایان رسید. لطفاً مجدداً تایم را انتخاب کنید.');
                return;
            }
            if (!method) {
                setMessage('لطفاً روش پرداخت را انتخاب کنید.', 'error');
                return;
            }
            if (trigger) {
                trigger.disabled = true;
                trigger.classList.add('is-loading');
            }
            setMessage(method === 'wallet' ? 'در حال پرداخت از کیف پول...' : (cfg.strings && cfg.strings.redirectingToGateway || 'در حال انتقال به درگاه پرداخت...'));
            request('/appointments/pay', {
                method: 'POST',
                body: JSON.stringify({
                    appointment_id: state.lock.appointment_id,
                    lock_token: state.lock.lock_token,
                    method,
                    gateway
                })
            }).then((result) => {
                if (result && result.checkout_url) {
                    window.location.href = result.checkout_url;
                    return;
                }
                hideTrustBanner();
                const receiptId = Number((result && result.appointment_id) || (state.lock && state.lock.appointment_id) || 0);
                state.lock = null;
                if (receiptId > 0) {
                    paymentEl.innerHTML = loadingPanel('در حال آماده کردن فاکتور نوبت...');
                    request(`/appointments/${receiptId}/receipt`)
                        .then((receipt) => {
                            paymentEl.innerHTML = receiptCardMarkup(receipt);
                            const printButton = paymentEl.querySelector('[data-print-current-receipt]');
                            printButton && printButton.addEventListener('click', () => printReceipt(receipt));
                            setMessage('نوبت شما با موفقیت ثبت شد. فاکتور آماده چاپ است.', 'success');
                            paymentEl.scrollIntoView({ block: 'start', behavior: 'smooth' });
                        })
                        .catch(() => {
                            const publicCode = result && result.appointment_code ? `<span>کد پیگیری نوبت: ${esc(result.appointment_code)}</span>` : '<span>رسید نوبت از پنل بیمار قابل مشاهده است.</span>';
                            paymentEl.innerHTML = `<div class="webtanan-booking-success"><strong>نوبت با موفقیت قطعی شد.</strong>${publicCode}</div>`;
                            setMessage('نوبت شما با موفقیت ثبت شد.', 'success');
                        });
                    return;
                }
                const publicCode = result && result.appointment_code ? `<span>کد پیگیری نوبت: ${esc(result.appointment_code)}</span>` : '<span>رسید نوبت از پنل بیمار قابل مشاهده است.</span>';
                paymentEl.innerHTML = `<div class="webtanan-booking-success"><strong>نوبت با موفقیت قطعی شد.</strong>${publicCode}</div>`;
                setMessage('نوبت شما با موفقیت ثبت شد.', 'success');
            }).catch((error) => {
                if (trigger) {
                    trigger.disabled = false;
                    trigger.classList.remove('is-loading');
                }
                setMessage(error.message, 'error');
            });
        }

        document.querySelectorAll('[data-webtanan-booking-open]').forEach((button) => {
            if (String(button.dataset.doctorId || '') === String(doctorId)) {
                button.addEventListener('click', openModal);
            }
        });

        modal.addEventListener('click', (event) => {
            if (event.target.closest('[data-webtanan-booking-close]')) {
                closeModal();
                return;
            }
            const day = event.target.closest('.webtanan-booking-day');
            if (day) {
                state.date = day.dataset.date;
                renderDays();
                loadSlots(state.date);
                return;
            }
            const nextButton = event.target.closest('[data-action="show-next-available"]');
            if (nextButton) {
                nextButton.disabled = true;
                nextButton.classList.add('is-loading');
                setMessage('در حال پیدا کردن نزدیک‌ترین نوبت آزاد...');
                request(`/doctors/${doctorId}/next-available`)
                    .then((items) => {
                        const next = Array.isArray(items) ? items[0] : null;
                        if (!next || !next.date) {
                            throw new Error('در حال حاضر نوبت آزادی برای این پزشک ثبت نشده است.');
                        }
                        state.date = next.date;
                        state.dayWindowStart = next.date;
                        renderDays();
                        loadSlots(state.date);
                        if (dayStrip && typeof dayStrip.scrollTo === 'function') {
                            dayStrip.scrollTo({ left: dayStrip.scrollWidth, behavior: 'smooth' });
                        }
                    })
                    .catch((error) => {
                        nextButton.disabled = false;
                        nextButton.classList.remove('is-loading');
                        setMessage(error.message, 'error');
                    });
                return;
            }
            const slot = event.target.closest('.webtanan-booking-modal-slot');
            if (slot && slot.dataset.status === 'available') {
                modal.querySelectorAll('.webtanan-booking-modal-slot').forEach((item) => {
                    item.dataset.selected = 'false';
                });
                slot.dataset.selected = 'true';
                state.selectedSlot = { date: slot.dataset.date, start: slot.dataset.start };
                if (!cfg.isLoggedIn) {
                    showAuthStep();
                    return;
                }
                ensurePatientContext().catch((error) => setMessage(error.message, 'error'));
                return;
            }
            const personCardEl = event.target.closest('.wb-booking-person-card');
            if (personCardEl) {
                const radio = personCardEl.querySelector('input[name="booking_person"]');
                if (radio) {
                    radio.checked = true;
                    state.selectedPerson = radio.value || 'self';
                    personEl.querySelectorAll('.wb-booking-person-card').forEach((card) => {
                        card.dataset.selected = card === personCardEl ? 'true' : 'false';
                    });
                }
                return;
            }
            if (event.target.closest('.wb-booking-add-person')) {
                dependentForm.hidden = false;
                const first = dependentForm.querySelector('input');
                first && first.focus();
                return;
            }
            if (event.target.closest('.wb-booking-cancel-person')) {
                dependentForm.reset();
                dependentForm.hidden = true;
                return;
            }
            const personContinue = event.target.closest('.wb-booking-person-continue');
            if (personContinue) {
                if (!state.selectedSlot) {
                    setMessage('ابتدا یک ساعت آزاد انتخاب کنید.', 'error');
                    resetToTimeSelection();
                    return;
                }
                personContinue.disabled = true;
                lockSelectedSlot()
                    .then(showPaymentStep)
                    .catch((error) => {
                        personContinue.disabled = false;
                        setMessage(error.message, 'error');
                    });
                return;
            }
            const paymentOption = event.target.closest('.webtanan-payment-option');
            if (paymentOption && !paymentOption.disabled) {
                selectPaymentOption(paymentOption);
                return;
            }
            const paySubmit = event.target.closest('.webtanan-pay-submit');
            if (paySubmit) {
                const selection = state.paymentSelection || {};
                pay(selection.method, selection.gateway || '', paySubmit);
            }
        });

        authMobileForm.addEventListener('submit', (event) => {
            event.preventDefault();
            const submitButton = authMobileForm.querySelector('[type="submit"]');
            if (submitButton) {
                submitButton.disabled = true;
                submitButton.classList.add('is-loading');
            }
            state.authMobile = String(new FormData(authMobileForm).get('mobile') || '').trim();
            sendOtp().catch((error) => setMessage(error.message, 'error')).finally(() => {
                if (submitButton) {
                    submitButton.disabled = false;
                    submitButton.classList.remove('is-loading');
                }
            });
        });

        otpForm.addEventListener('submit', (event) => {
            event.preventDefault();
            verifyOtp().catch((error) => setMessage(error.message, 'error'));
        });

        resendOtp && resendOtp.addEventListener('click', () => {
            resendOtp.disabled = true;
            sendOtp().catch((error) => setMessage(error.message, 'error')).finally(() => {
                resendOtp.disabled = false;
            });
        });

        profileForm.addEventListener('submit', (event) => {
            event.preventDefault();
            setMessage('در حال ذخیره اطلاعات...');
            request('/auth/complete-profile', {
                method: 'POST',
                body: JSON.stringify(Object.assign(formObject(profileForm), { completion_token: state.completionToken })),
                skipNonce: Boolean(state.completionToken)
            }).then((context) => {
                if (context.nonce) {
                    cfg.nonce = context.nonce;
                }
                state.completionToken = '';
                cfg.authContext = context;
                state.patientContext = context;
                showPersonStep(context);
            }).catch((error) => setMessage(error.message, 'error'));
        });

        dependentForm.addEventListener('submit', (event) => {
            event.preventDefault();
            setMessage('در حال ذخیره اطلاعات مراجعه‌کننده...');
            request('/patient-panel/dependents', {
                method: 'POST',
                body: JSON.stringify(formObject(dependentForm))
            }).then((result) => {
                const dependent = result.dependent || {};
                const context = Object.assign({}, state.patientContext || {}, {
                    dependents: Array.isArray(result.dependents) ? result.dependents : []
                });
                state.selectedPerson = dependent.id || 'self';
                dependentForm.reset();
                dependentForm.hidden = true;
                showPersonStep(context);
                setMessage('مراجعه‌کننده ذخیره و انتخاب شد.', 'success');
            }).catch((error) => setMessage(error.message, 'error'));
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && !modal.hidden) {
                closeModal();
            }
        });
    }

    function initNextAvailable(el) {
        const doctorId = el.dataset.doctorId;
        if (!doctorId || doctorId === '0') {
            el.innerHTML = panel('شناسه پزشک تنظیم نشده است.');
            return;
        }
        request(`/doctors/${doctorId}/next-available`)
            .then((slots) => {
                if (!slots.length) {
                    el.innerHTML = panel(cfg.strings && cfg.strings.noSlots || 'نوبت آزادی پیدا نشد.');
                    return;
                }
                const slot = slots[0];
                el.innerHTML = `<div class="webtanan-next-card wb-next-availability"><span>اولین نوبت آزاد</span><strong>${esc(displayDate(slot.date, false))} - ${esc(slot.start_time)}</strong></div>`;
            })
            .catch((error) => {
                el.innerHTML = panel(error.message);
            });
    }

    function badge(value, type) {
        const raw = String(type || value || 'info').replace(/[^a-z0-9_-]/gi, '_').toLowerCase();
        const tone = statusTone(raw);
        const display = displayStatusLabel(value, raw);
        return `<span class="wb-badge wb-status-badge wb-badge-${esc(tone)}" data-status="${esc(raw)}">${esc(display)}</span>`;
    }

    function confirmModal(options) {
        return new Promise((resolve) => {
            const overlay = document.createElement('div');
            overlay.className = 'wb-confirm-overlay';
            overlay.dir = 'rtl';
            overlay.innerHTML = `<div class="wb-confirm wb-confirm-modal" role="dialog" aria-modal="true" tabindex="-1">
                <div class="wb-confirm-head">
                    <div>
                        <span class="wb-kicker">${options.danger ? 'نیازمند تایید' : 'تایید عملیات'}</span>
                        <h3>${esc(options.title || 'تایید عملیات')}</h3>
                    </div>
                </div>
                <div class="wb-confirm-body">
                    <p>${esc(options.message || 'آیا از انجام این عملیات مطمئن هستید؟')}</p>
                    ${options.reason ? `<label class="wb-confirm-reason"><span>${esc(options.reason)}</span><textarea rows="3"></textarea></label>` : ''}
                </div>
                <div class="wb-confirm-actions">
                    <button type="button" class="wb-button wb-confirm-cancel">انصراف</button>
                    <button type="button" class="wb-button ${options.danger ? 'wb-button-danger' : 'wb-button-primary'} wb-confirm-ok">${esc(options.confirmText || 'تایید')}</button>
                </div>
            </div>`;
            document.body.appendChild(overlay);
            let releaseFocus = () => {};
            const close = (result) => {
                releaseFocus();
                overlay.remove();
                resolve(result);
            };
            releaseFocus = activateDialogFocus(overlay.querySelector('.wb-confirm-modal'), () => close(null));
            overlay.querySelector('.wb-confirm-cancel').addEventListener('click', () => close(null));
            overlay.querySelector('.wb-confirm-ok').addEventListener('click', () => {
                const textarea = overlay.querySelector('textarea');
                close({ reason: textarea ? textarea.value : '' });
            });
            overlay.addEventListener('click', (event) => {
                if (event.target === overlay) {
                    close(null);
                }
            });
        });
    }

    function appointmentActions(item, mode) {
        if (mode === 'patient') {
            const refund = item.refund_estimate > 0 ? ` data-refund="${esc(item.refund_estimate)}"` : '';
            const message = item.cancellation_message ? ` data-message="${esc(item.cancellation_message)}"` : '';
            const cancel = item.can_cancel && ['confirmed', 'pay_at_clinic', 'locked'].includes(item.appointment_status)
                ? `<button type="button" class="wb-table-action danger" data-action="patient-cancel" data-id="${item.id}"${refund}${message}>لغو</button>`
                : '';
            const resume = item.can_resume_payment
                ? `<button type="button" class="wb-table-action primary" data-action="resume-payment" data-id="${item.id}">تکمیل پرداخت</button>`
                : '';
            const receipt = item.payment_status === 'paid' || item.payment_status === 'wallet_paid'
                ? `<button type="button" class="wb-table-action" data-action="receipt" data-id="${item.id}">رسید</button>`
                : '';
            const survey = item.can_review
                ? `<button type="button" class="wb-table-action primary" data-action="survey" data-id="${item.id}"><i class="fas fa-star" aria-hidden="true"></i> ثبت نظر</button>`
                : '';
            return `${resume}${receipt}${survey}${cancel}`;
        }

        const paymentButtons = item.payment_status === 'unpaid' || item.appointment_status === 'pay_at_clinic'
            ? `<button type="button" class="wb-table-action" data-action="payment" data-status="cash_at_clinic" data-id="${item.id}">نقدی</button>
               <button type="button" class="wb-table-action" data-action="payment" data-status="pos_at_clinic" data-id="${item.id}">کارت‌خوان</button>`
            : '';
        const attendanceButtons = ['confirmed', 'pay_at_clinic'].includes(item.appointment_status)
            ? `<button type="button" class="wb-table-action" data-action="attendance" data-status="completed" data-id="${item.id}">مراجعه کرد</button>
               <button type="button" class="wb-table-action" data-action="attendance" data-status="no_show" data-id="${item.id}">نیامد</button>`
            : '';
        const cancel = ['locked', 'confirmed', 'pay_at_clinic'].includes(item.appointment_status)
            ? `<button type="button" class="wb-table-action danger" data-action="cancel" data-id="${item.id}">لغو</button>`
            : '';
        const record = item.patient_user_id
            ? `<button type="button" class="wb-table-action" data-action="record" data-patient-id="${item.patient_user_id}">پرونده</button>`
            : '';

        return `<button type="button" class="wb-table-action" data-action="receipt" data-id="${item.id}">رسید</button>${record}${paymentButtons}${attendanceButtons}${cancel}`;
    }

    function appointmentsTable(items, mode = 'staff') {
        if (!items.length) {
            return panel('موردی برای نمایش وجود ندارد.');
        }
        const selectable = mode !== 'patient';
        return `<div class="appointment-list wb-appointments-table-wrap wb-appointment-grid-${esc(mode)}">
            ${selectable ? '<label class="sample-select-all"><input type="checkbox" class="wb-select-all-appointments" aria-label="انتخاب همه"> انتخاب همه نوبت‌های قابل لغو</label>' : ''}
            ${items.map((item) => {
                const title = mode === 'patient' ? (item.doctor_name || item.doctor_title || 'پزشک') : appointmentPatientName(item);
                const subtitle = mode === 'patient'
                    ? `${item.specialty_name || ''}${item.specialty_name ? ' · ' : ''}${displayDate(item.appointment_date, false)}`
                    : `${item.patient_mobile || ''}${item.patient_national_code ? ' · ' + item.patient_national_code : ''}`;
                return `<div class="appointment-item wb-appointment-row" data-status="${esc(item.appointment_status || '')}">
                    <div class="info">
                        ${selectable ? `<input type="checkbox" class="wb-appointment-check" value="${esc(item.id)}" ${['locked', 'confirmed', 'pay_at_clinic'].includes(item.appointment_status) ? '' : 'disabled'}>` : ''}
                        <div class="avatar-xs">${esc((title || 'ن').charAt(0))}</div>
                        <div class="details">
                            <div class="${mode === 'patient' ? 'doctor-name' : 'name'}">${esc(title)}</div>
                            <div class="specialty">${esc(subtitle || 'نوبت وب‌تنان')}</div>
                            <div class="time"><i class="far fa-calendar-alt"></i>${esc(displayDate(item.appointment_date, false))}، ${esc(appointmentTimeRange(item))}</div>
                        </div>
                    </div>
                    <div class="actions">
                        ${badge(item.display_status || displayStatusLabel(item.appointment_label, item.appointment_status), item.appointment_status)}
                        ${badge(item.display_payment || displayStatusLabel(item.payment_label, item.payment_status), item.payment_status)}
                        ${appointmentActions(item, mode)}
                    </div>
                </div>`;
            }).join('')}
        </div>`;
    }

    function statCard(label, value, suffix = '', icon = '•') {
        const tone = icon === '✓' ? 'green' : icon === 'ک' ? 'yellow' : icon === 'ت' ? 'green' : 'blue';
        return `<div class="stat-card wb-stat">
            <div class="stat-header">
                <span class="stat-label wb-stat-label">${esc(label)}</span>
                <span class="icon ${esc(tone)}"><i class="fas fa-chart-simple" aria-hidden="true"></i></span>
            </div>
            <div class="stat-number wb-stat-value">${esc(value)}${suffix ? `<span>${esc(suffix)}</span>` : ''}</div>
            <div class="stat-change up"><i class="fas fa-arrow-up"></i> به‌روزرسانی شده</div>
        </div>`;
    }

    function renderDoctorDashboardChart(canvas, rows, canViewFinance) {
        if (!canvas || !window.Chart || !Array.isArray(rows) || !rows.length) {
            return;
        }

        if (canvas._webtananChart) {
            canvas._webtananChart.destroy();
        }

        const styles = getComputedStyle(document.documentElement);
        const primary = styles.getPropertyValue('--wb-primary').trim() || '#2563eb';
        const success = styles.getPropertyValue('--wb-success').trim() || '#16a34a';
        const border = styles.getPropertyValue('--wb-border-color').trim() || '#e2e8f0';
        const textMuted = styles.getPropertyValue('--wb-text-muted').trim() || '#64748b';
        const fontFamily = getComputedStyle(canvas).fontFamily || 'inherit';
        const labels = rows.map((row) => faDate(row.date));
        const appointmentData = rows.map((row) => Number(row.appointments || 0));
        const revenueData = rows.map((row) => Number(row.revenue || 0));
        const datasets = [
            {
                type: 'bar',
                label: 'تعداد نوبت',
                data: appointmentData,
                borderColor: primary,
                backgroundColor: 'rgba(37, 99, 235, 0.16)',
                borderWidth: 1,
                borderRadius: 8,
                yAxisID: 'appointments'
            }
        ];

        if (canViewFinance) {
            datasets.push({
                type: 'line',
                label: 'درآمد',
                data: revenueData,
                borderColor: success,
                backgroundColor: 'rgba(22, 163, 74, 0.12)',
                borderWidth: 3,
                pointRadius: 4,
                pointHoverRadius: 6,
                tension: 0.35,
                yAxisID: 'revenue'
            });
        }

        canvas._webtananChart = new window.Chart(canvas.getContext('2d'), {
            type: 'bar',
            data: { labels, datasets },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: {
                        rtl: true,
                        labels: {
                            color: textMuted,
                            usePointStyle: true,
                            font: { family: fontFamily, size: 12, weight: '700' }
                        }
                    },
                    tooltip: {
                        rtl: true,
                        bodyFont: { family: fontFamily },
                        titleFont: { family: fontFamily, weight: '700' },
                        callbacks: {
                            label(context) {
                                if (context.dataset.yAxisID === 'revenue') {
                                    return `${context.dataset.label}: ${money(context.parsed.y)} تومان`;
                                }
                                return `${context.dataset.label}: ${money(context.parsed.y)} نوبت`;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { color: textMuted, font: { family: fontFamily } }
                    },
                    appointments: {
                        beginAtZero: true,
                        position: 'right',
                        grid: { color: border },
                        ticks: { color: textMuted, precision: 0, font: { family: fontFamily } }
                    },
                    revenue: {
                        beginAtZero: true,
                        display: !!canViewFinance,
                        position: 'left',
                        grid: { drawOnChartArea: false },
                        ticks: {
                            color: textMuted,
                            font: { family: fontFamily },
                            callback(value) {
                                return money(value);
                            }
                        }
                    }
                }
            }
        });
    }

    function initDoctorDashboard(el) {
        if (!cfg.isLoggedIn) {
            el.innerHTML = panel('برای مشاهده داشبورد ابتدا وارد حساب پزشک یا منشی شوید.');
            return;
        }

        const content = el.querySelector('.wb-content');
        const doctorSelect = el.querySelector('.wb-doctor-select');
        const modal = el.querySelector('.wb-modal');
        const walkinForm = el.querySelector('.wb-walkin-form');
        const todayLabels = el.querySelectorAll('.wb-today-label');
        const doctorViews = ['today', 'calendar', 'patients', 'records', 'schedule', 'exceptions', 'wallet', 'settlements', 'profile', 'settings'];
        const state = { doctorId: 0, view: dashboardViewFromHash(doctorViews, 'today'), date: cfg.today || new Date().toISOString().slice(0, 10), calendarMode: 'day', context: null };
        let releaseWalkinFocus = () => {};

        // Keep dialogs outside theme/dashboard stacking contexts so fixed positioning is reliable.
        if (modal && modal.parentNode !== document.body) {
            document.body.appendChild(modal);
        }

        todayLabels.forEach((node) => {
            node.textContent = faDate(state.date);
        });

        function doctorQuery(extra = {}) {
            return qs(Object.assign({ doctor_id: state.doctorId }, extra));
        }

        function setLoading() {
            content.innerHTML = panel(cfg.strings && cfg.strings.loading || 'در حال بارگذاری...');
        }

        function renderTitle(title, subtitle = '') {
            return `<div class="wb-page-head">
                <div class="wb-page-head-copy">
                    <span class="wb-kicker">پیشخوان</span>
                    <h2>${esc(title)}</h2>
                    ${subtitle ? `<p>${esc(subtitle)}</p>` : ''}
                </div>
            </div>`;
        }

        function renderToday() {
            setLoading();
            const search = '';
            Promise.all([
                request(`/doctor-dashboard/summary?${doctorQuery({ date: state.date })}`),
                request(`/doctor-dashboard/appointments?${doctorQuery({ date: state.date, search })}`)
            ]).then(([summary, items]) => {
                content.innerHTML = `<div class="sample-page-title">${renderTitle('پیشخوان امروز', 'خلاصه وضعیت مطب در روز انتخاب‌شده')}</div>
                    <div class="wb-filterbar search-box">
                        <input type="date" class="wb-dashboard-date" value="${esc(state.date)}">
                        <input type="search" class="wb-appointment-search" placeholder="جستجوی نام، موبایل یا کد ملی">
                        <button type="button" class="btn btn-primary wb-refresh-today">نمایش</button>
                        <button type="button" class="btn btn-outline wb-bulk-cancel-selected">لغو انتخاب‌شده‌ها</button>
                        <button type="button" class="btn btn-outline wb-bulk-cancel-day">لغو همه نوبت‌های این روز</button>
                    </div>
                    <div class="stats-grid wb-stats-grid">
                        ${statCard('نوبت‌های امروز', money(summary.appointments_today), 'نفر', 'ن')}
                        ${statCard('ویزیت شده', money(summary.completed_today), 'نفر', '✓')}
                        ${statCard('درآمد روز', summary.revenue_today == null ? 'محدود' : money(summary.revenue_today), summary.revenue_today == null ? '' : 'تومان', 'ت')}
                        ${statCard('موجودی کیف پول', summary.wallet_balance == null ? 'محدود' : money(summary.wallet_balance), summary.wallet_balance == null ? '' : 'تومان', 'ک')}
                    </div>
                    <div class="dashboard-grid">
                        <div>
                            <section class="card wb-chart-panel">
                                <div class="card-header wb-chart-head">
                                    <h3 class="wb-card-title"><i class="fas fa-chart-line"></i> آمار هفتگی نوبت‌ها</h3>
                                </div>
                                <div class="chart-container wb-chart-box">
                                    <canvas id="appointmentsChart" class="wb-dashboard-chart" height="160"></canvas>
                                </div>
                            </section>
                            <section class="card">
                                <div class="card-header"><h3 class="wb-card-title"><i class="fas fa-list-ul"></i> نوبت‌های امروز</h3></div>
                                <div class="wb-appointments-table">${appointmentsTable(items)}</div>
                            </section>
                        </div>
                        <div>
                            <section class="card">
                                <div class="card-header"><h3 class="wb-card-title"><i class="fas fa-bolt"></i> اقدامات سریع</h3></div>
                                <div class="quick-actions">
                                    <button type="button" class="action-btn wb-open-walkin"><i class="fas fa-plus-circle"></i>نوبت جدید</button>
                                    <button type="button" class="action-btn" data-wb-view="patients"><i class="fas fa-user-plus"></i>بیمار جدید</button>
                                    <button type="button" class="action-btn" data-wb-view="records"><i class="fas fa-file-prescription"></i>پرونده</button>
                                    <button type="button" class="action-btn" data-wb-view="calendar"><i class="fas fa-calendar-alt"></i>زمان‌بندی</button>
                                </div>
                            </section>
                            <section class="card today-summary-card">
                                <div class="wb-today-summary-head">
                                    <div><span>خلاصه امروز</span><strong>${money(summary.appointments_today)} نوبت</strong></div>
                                    <div><span>درآمد امروز</span><strong class="wb-money-credit">${summary.revenue_today == null ? 'محدود' : money(summary.revenue_today) + ' تومان'}</strong></div>
                                </div>
                                <div class="wb-today-summary-badges">
                                    <span class="badge badge-success">${money(summary.completed_today)} انجام شده</span>
                                    <span class="badge badge-warning">${money(summary.active_today || 0)} در انتظار مراجعه</span>
                                    <span class="badge badge-danger">${money(summary.no_show_today)} مراجعه نکرده</span>
                                </div>
                            </section>
                        </div>
                    </div>`;
                renderDoctorDashboardChart(content.querySelector('.wb-dashboard-chart'), summary.weekly_chart || [], summary.can_view_finance);
            }).catch((error) => {
                content.innerHTML = panel(error.message);
            });
        }

        function reloadAppointments() {
            const date = content.querySelector('.wb-dashboard-date') ? content.querySelector('.wb-dashboard-date').value : state.date;
            const search = content.querySelector('.wb-appointment-search') ? content.querySelector('.wb-appointment-search').value : '';
            state.date = date;
            request(`/doctor-dashboard/appointments?${doctorQuery({ date, search })}`)
                .then((items) => {
                    const wrap = content.querySelector('.wb-appointments-table');
                    if (wrap) {
                        wrap.innerHTML = appointmentsTable(items);
                    }
                })
                .catch((error) => {
                    const wrap = content.querySelector('.wb-appointments-table');
                    if (wrap) {
                        wrap.innerHTML = panel(error.message);
                    }
                });
        }

        function selectedAppointmentIds() {
            return Array.from(content.querySelectorAll('.wb-appointment-check:checked')).map((item) => item.value).filter(Boolean);
        }

        function bulkCancelAppointments(cancelDay = false) {
            const ids = cancelDay ? [] : selectedAppointmentIds();
            const date = content.querySelector('.wb-dashboard-date') ? content.querySelector('.wb-dashboard-date').value : state.date;
            if (!cancelDay && !ids.length) {
                content.querySelector('.wb-appointments-table').insertAdjacentHTML('beforebegin', panel('ابتدا چند نوبت را انتخاب کنید.'));
                return;
            }

            confirmModal({
                danger: true,
                title: cancelDay ? 'لغو همه نوبت‌های این روز' : 'لغو نوبت‌های انتخاب‌شده',
                message: cancelDay ? 'همه نوبت‌های فعال این تاریخ لغو می‌شوند و مبلغ پرداخت‌شده به کیف پول بیماران برمی‌گردد.' : 'نوبت‌های انتخاب‌شده لغو می‌شوند و مبلغ پرداخت‌شده به کیف پول بیماران برمی‌گردد.',
                reason: 'دلیل لغو',
                confirmText: 'لغو نوبت‌ها'
            }).then((answer) => {
                if (!answer) {
                    return;
                }
                request('/doctor-dashboard/appointments/bulk-cancel', {
                    method: 'POST',
                    body: JSON.stringify({
                        doctor_id: state.doctorId,
                        appointment_ids: ids,
                        date: cancelDay ? date : '',
                        reason: answer.reason || ''
                    })
                }).then((summary) => {
                    toast(`لغو گروهی انجام شد؛ ${money(summary.cancelled || 0)} نوبت لغو شد و ${money(summary.refund_total || 0)} تومان برگشت داده شد.`, 'success');
                    renderToday();
                }).catch((error) => {
                    content.querySelector('.wb-appointments-table').insertAdjacentHTML('beforebegin', panel(error.message));
                });
            });
        }

        function renderCalendar() {
            if (state.calendarMode === 'month') {
                state.calendarMode = 'week';
            }
            content.innerHTML = `${renderTitle('تقویم نوبت‌دهی', 'نمای کاربردی روزانه و هفتگی برای مدیریت نوبت‌های مطب')}
                <div class="wb-filterbar">
                    <input type="date" class="wb-calendar-date" value="${esc(state.date)}">
                    <div class="wb-segmented" role="group" aria-label="نوع نمایش تقویم">
                        <button type="button" class="${state.calendarMode === 'day' ? 'is-active' : ''}" data-calendar-mode="day">روزانه</button>
                        <button type="button" class="${state.calendarMode === 'week' ? 'is-active' : ''}" data-calendar-mode="week">هفتگی</button>
                    </div>
                    <button type="button" class="wb-button wb-load-calendar">نمایش تقویم</button>
                    <button type="button" class="wb-button wb-calendar-today">امروز</button>
                </div>
                <div class="wb-calendar-legend">
                    <span data-status="available">آزاد</span>
                    <span data-status="locked">در حال رزرو</span>
                    <span data-status="confirmed">تایید شده</span>
                    <span data-status="pay_at_clinic">پرداخت در مطب (حضوری)</span>
                    <span data-status="completed">مراجعه کرد</span>
                    <span data-status="no_show">مراجعه نکرد</span>
                    <span data-status="cancelled">لغو شده</span>
                </div>
                <div class="wb-calendar-results wb-calendar-board">${loadingPanel('در حال بارگذاری تقویم...')}</div>`;
            loadCalendar();
        }

        function loadCalendar() {
            const date = content.querySelector('.wb-calendar-date') ? content.querySelector('.wb-calendar-date').value : state.date;
            state.date = date;
            const results = content.querySelector('.wb-calendar-results');
            results.innerHTML = loadingPanel('در حال بارگذاری تقویم...');
            const dates = calendarDateRange(state.calendarMode, date);
            Promise.all(dates.map((day) => request(`/doctor-dashboard/calendar?${doctorQuery({ date: day })}`)))
                .then((days) => {
                    const html = dates.map((day, index) => renderCalendarDay(day, days[index] || [])).join('');
                    results.innerHTML = html || panel('برای این بازه اسلاتی ثبت نشده است.');
                })
                .catch((error) => {
                    results.innerHTML = panel(error.message);
                });
        }

        function renderCalendarDay(date, slots) {
            const available = slots.filter((slot) => slot.status === 'available').length;
            const booked = slots.filter((slot) => slot.status !== 'available').length;
            return `<section class="wb-calendar-day-card">
                <header class="wb-calendar-day-head">
                    <div>
                        <strong>${esc(displayDate(date))}</strong>
                        <span>${money(available)} ساعت آزاد، ${money(booked)} نوبت ثبت‌شده</span>
                    </div>
                    <button type="button" class="wb-table-action wb-calendar-focus-day" data-calendar-date="${esc(date)}">نمای روز</button>
                </header>
                ${slots.length ? `<div class="wb-calendar-slots">${slots.map((slot) => {
                    const detailed = slotDetailedStatus(slot);
                    const tone = slot.slot_tone || statusTone(detailed);
                    const patient = slot.patient_display_name ? `<strong class="wb-calendar-patient">${esc(slot.patient_display_name)}</strong>` : '';
                    const source = slot.source_label ? `<span class="wb-calendar-source">${esc(slot.source_label)}</span>` : '';
                    const controls = detailed === 'available'
                        ? `<button type="button" class="wb-table-action primary wb-open-walkin" data-date="${esc(date)}" data-time="${esc(slot.start_time || '')}">ثبت نوبت</button>`
                        : (slot.appointment ? `<div class="wb-calendar-slot-actions">${appointmentActions(slot.appointment, 'staff')}</div>` : '');
                    return `<article class="wb-calendar-slot-card" data-status="${esc(detailed)}" data-tone="${esc(tone)}">
                        <div class="wb-calendar-slot-time">${esc(slot.time_range || `${slot.start_time}${slot.end_time ? ` - ${slot.end_time}` : ''}`)}</div>
                        <div class="wb-calendar-slot-body">
                            ${patient || '<strong class="wb-calendar-patient">ساعت آزاد</strong>'}
                            <span>${esc(slot.display_status || statusLabel(detailed))}</span>
                        </div>
                        <div class="wb-calendar-slot-meta">
                            ${source}
                            ${slot.display_payment ? `<span>${esc(slot.display_payment)}</span>` : ''}
                        </div>
                        ${controls}
                    </article>`;
                }).join('')}</div>` : panel('برای این روز اسلاتی ثبت نشده است.')}
            </section>`;
        }

        function renderPatients() {
            content.innerHTML = `${renderTitle('لیست بیماران', 'جستجو بر اساس نام، موبایل یا کد ملی')}
                <div class="wb-filterbar">
                    <input type="search" class="wb-patient-search" placeholder="جستجوی بیمار">
                    <button type="button" class="wb-button wb-load-patients">جستجو</button>
                </div>
                <div class="wb-patient-results">${panel('در حال بارگذاری')}</div>`;
            loadPatients();
        }

        function loadPatients() {
            const search = content.querySelector('.wb-patient-search') ? content.querySelector('.wb-patient-search').value : '';
            const results = content.querySelector('.wb-patient-results');
            request(`/doctor-dashboard/patients?${doctorQuery({ search })}`)
                .then((items) => {
                    results.innerHTML = items.length ? `<div class="wb-table-wrap"><table class="wb-table"><thead><tr><th>بیمار</th><th>موبایل</th><th>کد ملی</th><th>تعداد نوبت</th><th>آخرین مراجعه</th><th>پرونده</th></tr></thead><tbody>${items.map((item) => `<tr><td class="wb-patient-cell"><strong>${esc(`${item.patient_first_name || ''} ${item.patient_last_name || ''}`.trim() || '-')}</strong></td><td>${esc(item.patient_mobile || '-')}</td><td>${esc(item.patient_national_code || '-')}</td><td>${money(item.appointment_count)}</td><td>${item.last_visit_date ? esc(displayDate(item.last_visit_date, false)) : '-'}</td><td>${item.patient_user_id ? `<button type="button" class="wb-table-action" data-action="record" data-patient-id="${esc(item.patient_user_id)}">مشاهده پرونده</button>` : '-'}</td></tr>`).join('')}</tbody></table></div>` : panel('بیماری یافت نشد.');
                })
                .catch((error) => {
                    results.innerHTML = panel(error.message);
                });
        }

        function renderRecords(patientId = '') {
            content.innerHTML = `${renderTitle('پرونده بیماران', 'جستجو، مشاهده و تکمیل پرونده پزشکی بیمار')}
                <div class="wb-filterbar">
                    <input type="search" class="wb-patient-search" placeholder="جستجوی بیمار">
                    <button type="button" class="wb-button wb-load-patients">جستجو</button>
                </div>
                <div class="wb-patient-results">${panel('در حال بارگذاری')}</div>
                <div class="wb-record-editor"></div>`;
            loadPatients();
            if (patientId) {
                loadPatientRecord(patientId);
            }
        }

        function loadPatientRecord(patientId) {
            const target = content.querySelector('.wb-record-editor');
            if (!target) {
                state.view = 'records';
                renderRecords(patientId);
                return;
            }
            target.innerHTML = loadingPanel('در حال بارگذاری پرونده...');
            request(`/doctor-dashboard/patients/${patientId}/record?${doctorQuery()}`)
                .then((record) => {
                    const notes = Array.isArray(record.notes) ? record.notes : [];
                    const files = Array.isArray(record.files) ? record.files : [];
                    const auditLogs = Array.isArray(record.audit_logs) ? record.audit_logs : [];
                    target.innerHTML = `<div class="wb-record-card" data-patient-id="${esc(patientId)}">
                        <div class="wb-section-head"><h3>پرونده ${esc(record.patient_full_name || 'بیمار')}</h3><span>${esc(record.patient_mobile || '')}</span></div>
                        <form class="wb-record-form">
                            <div class="wb-record-form-head"><strong>اطلاعات پایه پرونده</strong><span>این اطلاعات در مراجعات بعدی در دسترس پزشک خواهد بود.</span></div>
                            <label class="wb-form-wide"><span>خلاصه وضعیت بیمار</span><textarea name="summary" rows="3">${esc(record.summary || '')}</textarea></label>
                            <label><span>حساسیت‌ها</span><textarea name="allergies" rows="3">${esc(record.allergies || '')}</textarea></label>
                            <label><span>بیماری‌های زمینه‌ای</span><textarea name="chronic_conditions" rows="3">${esc(record.chronic_conditions || '')}</textarea></label>
                            <label class="wb-form-wide"><span>داروهای فعلی</span><textarea name="current_medications" rows="3">${esc(record.current_medications || '')}</textarea></label>
                            <button type="submit" class="wb-btn wb-btn-primary">ذخیره پرونده</button>
                        </form>
                        <form class="wb-record-note-form">
                            <div class="wb-section-head"><h3>یادداشت مراجعه</h3></div>
                            <label><span>نوع یادداشت</span><select name="note_type"><option value="visit">شرح مراجعه</option><option value="diagnosis">تشخیص</option><option value="prescription">نسخه و دارو</option><option value="lab">آزمایش و تصویربرداری</option><option value="followup">پیگیری بعدی</option></select></label>
                            <label><span>عنوان</span><input type="text" name="title" placeholder="عنوان کوتاه و روشن"></label>
                            <label class="wb-form-wide"><span>شرح</span><textarea name="body" rows="5" placeholder="شرح مراجعه، تشخیص، توصیه یا برنامه پیگیری" required></textarea></label>
                            <label><span>سطح دسترسی</span><select name="visibility">
                                <option value="patient">نمایش برای بیمار</option>
                                <option value="private">فقط پزشک/مدیر</option>
                            </select></label>
                            <button type="submit" class="wb-button wb-button-primary">افزودن یادداشت</button>
                        </form>
                        <form class="wb-record-file-form">
                            <div class="wb-section-head"><h3>فایل‌های پرونده</h3><span>تصویر، PDF یا مدارک مراجعه</span></div>
                            <input type="file" name="file" accept="image/jpeg,image/png,image/webp,application/pdf" required>
                            <select name="visibility">
                                <option value="patient">نمایش برای بیمار</option>
                                <option value="private">فقط پزشک/مدیر</option>
                            </select>
                            <button type="submit" class="wb-button wb-button-primary">آپلود فایل</button>
                        </form>
                        <div class="wb-record-files">
                            ${files.length ? files.map((file) => `<a class="wb-record-file" href="${esc(file.file_url || '#')}" target="_blank" rel="noopener">
                                <strong>${esc(file.file_name || 'فایل پرونده')}</strong>
                                <span>${esc(file.mime_type || '')} ${file.file_size ? `- ${money(Math.round(Number(file.file_size) / 1024))} KB` : ''}</span>
                                ${badge(file.visibility === 'private' ? 'خصوصی' : 'قابل مشاهده بیمار', file.visibility === 'private' ? 'cancelled' : 'confirmed')}
                            </a>`).join('') : panel('هنوز فایلی برای پرونده ثبت نشده است.')}
                        </div>
                        <div class="wb-record-notes">
                            ${notes.length ? notes.map((note) => `<article class="wb-record-note"><strong>${esc(note.title || 'یادداشت')}</strong><p>${esc(note.body || '')}</p><span>${esc(note.created_at || '')}</span>${badge(note.visibility === 'private' ? 'خصوصی' : 'قابل مشاهده بیمار', note.visibility === 'private' ? 'cancelled' : 'confirmed')}</article>`).join('') : panel('هنوز یادداشتی ثبت نشده است.')}
                        </div>
                        <div class="wb-record-audit">
                            <div class="wb-section-head"><h3>ردپای پرونده</h3></div>
                            ${auditLogs.length ? auditLogs.map((item) => `<div class="wb-record-audit-row"><strong>${esc(item.action_label || item.action || '')}</strong><span>${esc(item.actor_name || '')}</span><time>${esc(item.created_at || '')}</time></div>`).join('') : panel('هنوز لاگی برای این پرونده ثبت نشده است.')}
                        </div>
                        <div class="wb-record-message" aria-live="polite"></div>
                    </div>`;
                })
                .catch((error) => {
                    target.innerHTML = panel(error.message);
                });
        }

        function renderSchedule() {
            content.innerHTML = `${renderTitle('برنامه نوبت‌دهی', 'برنامه هفتگی تکرارشونده و روزهای خاص را یکجا مدیریت کنید')}
                <div class="wb-schedule-workspace">
                    <section class="card wb-schedule-section">
                        <div class="wb-section-head"><div><span class="wb-kicker">برنامه تکرارشونده</span><h3>ساعت‌های هفتگی</h3><p>این بازه‌ها هر هفته تکرار می‌شوند.</p></div></div>
                        <form class="wb-form-grid wb-schedule-form">
                            <label><span>روز هفته</span><select name="weekday"><option value="saturday">شنبه</option><option value="sunday">یکشنبه</option><option value="monday">دوشنبه</option><option value="tuesday">سه‌شنبه</option><option value="wednesday">چهارشنبه</option><option value="thursday">پنجشنبه</option><option value="friday">جمعه</option></select></label>
                            <label><span>شروع نوبت‌دهی</span><input type="time" name="start_time" required></label>
                            <label><span>پایان نوبت‌دهی</span><input type="time" name="end_time" required></label>
                            <label><span>فاصله هر نوبت</span><div class="wb-input-suffix"><input type="number" name="slot_duration" min="1" max="240" value="15" required><small>دقیقه</small></div></label>
                            <button type="submit" class="wb-btn wb-btn-primary wb-form-wide">افزودن بازه هفتگی</button>
                        </form>
                        <div class="wb-schedule-results">${loadingPanel('در حال بارگذاری برنامه هفتگی...')}</div>
                    </section>
                    <section class="card wb-schedule-section wb-special-days-section">
                        <div class="wb-section-head"><div><span class="wb-kicker">تاریخ مشخص</span><h3>روزها و بازه‌های خاص</h3><p>برای تعطیلی، شیفت اضافه یا تغییر ساعت یک بازه تاریخی تعریف کنید.</p></div></div>
                        <form class="wb-form-grid wb-exception-form">
                            <label><span>از تاریخ</span><input type="date" name="exception_date" required></label>
                            <label><span>تا تاریخ</span><input type="date" name="end_date"></label>
                            <label><span>نوع برنامه</span><select name="type"><option value="day_off">تعطیلی کامل</option><option value="extra_shift">شیفت اضافه</option><option value="custom_shift">شیفت جایگزین</option><option value="reduced_shift">ساعت کاری کوتاه‌تر</option></select></label>
                            <label><span>شروع</span><input type="time" name="start_time"></label>
                            <label><span>پایان</span><input type="time" name="end_time"></label>
                            <label><span>فاصله نوبت‌ها</span><div class="wb-input-suffix"><input type="number" name="slot_duration" min="1" max="240" value="15"><small>دقیقه</small></div></label>
                            <label class="wb-form-wide"><span>یادداشت</span><input type="text" name="reason" placeholder="مثلاً تعطیلی مطب یا شیفت عصر"></label>
                            <button type="submit" class="wb-btn wb-btn-primary wb-form-wide">ثبت برنامه تاریخ خاص</button>
                        </form>
                        <div class="wb-exception-results">${loadingPanel('در حال بارگذاری روزهای خاص...')}</div>
                    </section>
                </div>`;
            loadSchedules();
            loadExceptions();
        }

        function loadSchedules() {
            request(`/doctor-dashboard/schedules?${doctorQuery()}`)
                .then((items) => {
                    const target = content.querySelector('.wb-schedule-results');
                    if (!target) {
                        return;
                    }
                    const weekdays = ['saturday', 'sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday'];
                    const grouped = items.reduce((all, item) => {
                        (all[item.weekday] = all[item.weekday] || []).push(item);
                        return all;
                    }, {});
                    target.innerHTML = `<div class="wb-weekday-grid">${weekdays.map((weekday) => {
                        const shifts = grouped[weekday] || [];
                        return `<article class="wb-weekday-card" data-active="${shifts.length ? 'true' : 'false'}">
                            <header><strong>${esc(weekdayLabel(weekday))}</strong>${shifts.length ? '<span class="wb-badge wb-badge-success">فعال</span>' : '<span class="wb-badge wb-badge-muted">بدون برنامه</span>'}</header>
                            <div>${shifts.length ? shifts.map((item) => `<p><i class="far fa-clock" aria-hidden="true"></i><b>${esc(item.start_time)} تا ${esc(item.end_time)}</b><small>هر ${money(item.slot_duration)} دقیقه</small><button type="button" class="wb-icon-action wb-delete-schedule" data-action="delete-schedule" data-id="${esc(item.id)}" aria-label="حذف این بازه"><i class="fas fa-trash" aria-hidden="true"></i></button></p>`).join('') : '<p class="wb-weekday-empty">بازه‌ای ثبت نشده است.</p>'}</div>
                        </article>`;
                    }).join('')}</div>`;
                })
                .catch((error) => {
                    content.querySelector('.wb-schedule-results').innerHTML = panel(error.message);
                });
        }

        function renderExceptions() {
            state.view = 'schedule';
            renderSchedule();
        }

        function loadExceptions() {
            request(`/doctor-dashboard/exceptions?${doctorQuery({ from: cfg.today })}`)
                .then((items) => {
                    const target = content.querySelector('.wb-exception-results');
                    if (!target) {
                        return;
                    }
                    target.innerHTML = items.length ? `<div class="wb-special-day-list">${items.map((item) => `<article class="wb-special-day-card" data-type="${esc(item.type)}"><div><strong>${esc(displayDate(item.exception_date, false))}</strong><span>${esc(exceptionTypeLabel(item.type))}</span></div><p>${item.start_time && item.end_time ? `${esc(item.start_time)} تا ${esc(item.end_time)} · هر ${money(item.slot_duration)} دقیقه` : 'کل روز'}</p>${item.reason ? `<small>${esc(item.reason)}</small>` : ''}<button type="button" class="wb-icon-action wb-delete-exception" data-action="delete-exception" data-id="${esc(item.id)}" aria-label="حذف این برنامه"><i class="fas fa-trash" aria-hidden="true"></i></button></article>`).join('')}</div>` : panel('برای تاریخ‌های آینده برنامه خاصی ثبت نشده است.');
                })
                .catch((error) => {
                    content.querySelector('.wb-exception-results').innerHTML = panel(error.message);
                });
        }

        function renderWallet() {
            content.innerHTML = `${renderTitle('صورتحساب‌ها', 'مانده حساب و گردش‌های مالی مطب')}
                <div class="wb-wallet-results">${loadingPanel('در حال بارگذاری اطلاعات مالی...')}</div>`;
            request(`/doctor-dashboard/wallet?${doctorQuery()}`).then((wallet) => {
                const ledgerRows = Array.isArray(wallet.ledger) ? wallet.ledger : [];
                content.querySelector('.wb-wallet-results').innerHTML = `<div class="wb-stats-grid">
                        ${statCard('موجودی کل', money(wallet.total_balance != null ? wallet.total_balance : wallet.balance), 'تومان', 'ک')}
                        ${statCard('قابل برداشت', money(wallet.available_balance != null ? wallet.available_balance : wallet.balance), 'تومان', 'ب')}
                        ${statCard('بدهی کمیسیون حضوری', money(wallet.commission_debt || 0), 'تومان', 'د')}
                    </div>
                    <div class="wb-section-head"><h3>دفتر کل</h3></div>
                    ${ledgerRows.length ? `<div class="wb-table-wrap"><table class="wb-table wb-wallet-table"><thead><tr><th>نوع</th><th>مبلغ</th><th>مانده بعد</th><th>تاریخ</th></tr></thead><tbody>${ledgerRows.map((item) => `<tr><td>${badge(ledgerLabel(item.entry_type), item.entry_type)}</td><td class="${Number(item.amount || 0) >= 0 ? 'wb-money-credit' : 'wb-money-debit'}">${money(item.amount)} تومان</td><td>${money(item.balance_after)} تومان</td><td>${esc(item.created_at)}</td></tr>`).join('')}</tbody></table></div>` : panel('گردش مالی ثبت نشده است.')}`;
            }).catch((error) => {
                content.querySelector('.wb-wallet-results').innerHTML = panel(error.message);
            });
        }

        function renderSettlements() {
            content.innerHTML = `${renderTitle('تسویه حساب', 'ثبت درخواست برداشت و پیگیری تسویه‌های بانکی')}
                <div class="wb-settlement-results">${loadingPanel('در حال بارگذاری تسویه‌ها...')}</div>`;
            Promise.all([
                request(`/doctor-dashboard/wallet?${doctorQuery()}`),
                request(`/doctor-dashboard/settlements?${doctorQuery()}`)
            ]).then(([wallet, settlements]) => {
                const rows = Array.isArray(settlements) ? settlements : [];
                content.querySelector('.wb-settlement-results').innerHTML = `<div class="wb-stats-grid">
                        ${statCard('قابل برداشت', money(wallet.available_balance != null ? wallet.available_balance : wallet.balance), 'تومان', 'ب')}
                        ${statCard('در انتظار تسویه', money(wallet.pending_settlement || 0), 'تومان', 'ت')}
                    </div>
                    <section class="card wb-settlement-request-card">
                        <div class="wb-section-head"><div><h3>درخواست تسویه جدید</h3><p>مبلغ پس از تایید مدیریت به شماره شبا واریز می‌شود.</p></div></div>
                        <form class="wb-form-grid wb-settlement-form">
                            <label><span>مبلغ درخواست</span><input type="number" name="amount" min="1" max="${esc(wallet.available_balance != null ? wallet.available_balance : wallet.balance)}" required></label>
                            <label><span>شماره شبا</span><input type="text" name="iban" dir="ltr" placeholder="IR000000000000000000000000" required></label>
                            <button type="submit" class="wb-btn wb-btn-primary wb-form-wide">ثبت درخواست تسویه</button>
                        </form>
                    </section>
                    <div class="wb-section-head"><h3>سوابق درخواست‌ها</h3></div>
                    ${rows.length ? `<div class="wb-table-wrap"><table class="wb-table wb-settlement-table"><thead><tr><th>مبلغ</th><th>وضعیت</th><th>شماره پیگیری / تاریخ</th></tr></thead><tbody>${rows.map((item) => `<tr><td><strong>${money(item.amount)} تومان</strong></td><td>${badge(settlementLabel(item.status), item.status)}</td><td>${esc(item.bank_tracking_number || item.requested_at || item.created_at)}</td></tr>`).join('')}</tbody></table></div>` : panel('هنوز درخواست تسویه‌ای ثبت نشده است.')}`;
            }).catch((error) => {
                content.querySelector('.wb-settlement-results').innerHTML = panel(error.message);
            });
        }

        function renderProfile() {
            content.innerHTML = `${renderTitle('پروفایل من', 'اطلاعات عمومی پزشک، مطب و تصاویر')}
                <div class="wb-profile-editor">${loadingPanel('در حال بارگذاری پروفایل...')}</div>`;
            request(`/doctor-dashboard/profile?${doctorQuery()}`)
                .then((profile) => {
                    const gallery = Array.isArray(profile.gallery) ? profile.gallery : [];
                    content.querySelector('.wb-profile-editor').innerHTML = `<form class="wb-profile-form">
                        <div class="wb-profile-media-editor">
                            <div class="wb-profile-preview">${profile.thumbnail ? `<img src="${esc(profile.thumbnail)}" alt="">` : '<span>تصویر پزشک</span>'}</div>
                            <label class="wb-upload-button">آپلود تصویر اصلی<input type="file" name="thumbnail_file" accept="image/jpeg,image/png,image/webp" hidden></label>
                            <input type="hidden" name="thumbnail_id" value="${esc(profile.thumbnail_id || '')}">
                        </div>
                        <div class="wb-form-grid">
                            <label><span>نام نمایشی</span><input type="text" name="title" value="${esc(profile.title || '')}" required></label>
                            <label><span>شماره نظام پزشکی</span><input type="text" name="medical_system_number" value="${esc(profile.medical_system_number || '')}"></label>
                            <label><span>نام مطب</span><input type="text" name="clinic_name" value="${esc(profile.clinic_name || '')}"></label>
                            <label><span>تلفن مطب</span><input type="text" name="clinic_phone" value="${esc(profile.clinic_phone || '')}"></label>
                            <label class="wb-form-wide"><span>آدرس مطب</span><textarea name="clinic_address">${esc(profile.clinic_address || '')}</textarea></label>
                            <label class="wb-form-wide"><span>خلاصه کوتاه</span><textarea name="summary">${esc(profile.summary || '')}</textarea></label>
                            <label class="wb-form-wide"><span>بیوگرافی</span><textarea name="biography" rows="7">${esc(profile.biography || '')}</textarea></label>
                            <label class="wb-form-wide"><span>خدمات و حوزه‌های فعالیت</span><textarea name="services" rows="4" placeholder="هر خدمت را در یک خط بنویسید">${esc((profile.services || []).join('\n'))}</textarea><small>هر مورد در پروفایل عمومی به‌صورت جداگانه نمایش داده می‌شود.</small></label>
                            <label class="wb-form-wide"><span>مدارک و گواهی‌ها</span><textarea name="certificates" rows="4" placeholder="هر مدرک را در یک خط بنویسید">${esc((profile.certificates || []).join('\n'))}</textarea></label>
                            <label class="wb-form-wide"><span>پرسش‌های پرتکرار</span><textarea name="faq" rows="5" placeholder="پرسش | پاسخ">${esc((profile.faq || []).map((item) => `${item.question} | ${item.answer}`).join('\n'))}</textarea><small>هر خط را به شکل «پرسش | پاسخ» وارد کنید.</small></label>
                            <label><span>شماره شبا</span><input type="text" name="iban" value="${esc(profile.iban || '')}"></label>
                            <label><span>صاحب حساب</span><input type="text" name="bank_account_owner" value="${esc(profile.bank_account_owner || '')}"></label>
                        </div>
                        <div class="wb-gallery-editor">
                            <div class="wb-section-head"><h3>گالری مطب</h3></div>
                            <div class="wb-gallery-preview">${gallery.map((item) => `<span data-id="${esc(item.id)}"><img src="${esc(item.thumbnail || item.url)}" alt=""></span>`).join('')}</div>
                            <label class="wb-upload-button">افزودن تصویر گالری<input type="file" name="gallery_file" accept="image/jpeg,image/png,image/webp" hidden></label>
                            <input type="hidden" name="gallery_ids" value="${esc((profile.gallery_ids || []).join(','))}">
                        </div>
                        <button type="submit" class="wb-btn wb-btn-primary">ذخیره پروفایل</button>
                        <div class="wb-profile-message" aria-live="polite"></div>
                    </form>`;
                })
                .catch((error) => {
                    content.querySelector('.wb-profile-editor').innerHTML = panel(error.message);
                });
        }

        function renderSettings() {
            content.innerHTML = `${renderTitle('تنظیمات مطب', 'دسترسی سریع به تنظیمات اصلی حساب پزشک')}
                <div class="wb-settings-grid">
                    <button type="button" class="card wb-settings-card" data-wb-view="profile"><i class="fas fa-user-md" aria-hidden="true"></i><strong>پروفایل پزشک و مطب</strong><span>اطلاعات هویتی، آدرس، تصویر و گالری</span></button>
                    <button type="button" class="card wb-settings-card" data-wb-view="schedule"><i class="fas fa-calendar-alt" aria-hidden="true"></i><strong>برنامه نوبت‌دهی</strong><span>ساعت‌های هفتگی و روزهای خاص</span></button>
                    <button type="button" class="card wb-settings-card" data-wb-view="settlements"><i class="fas fa-money-check-alt" aria-hidden="true"></i><strong>تنظیمات تسویه</strong><span>شماره شبا و درخواست برداشت</span></button>
                </div>`;
        }

        function render() {
            if (state.view === 'calendar') {
                renderCalendar();
            } else if (state.view === 'patients') {
                renderPatients();
            } else if (state.view === 'records') {
                renderRecords();
            } else if (state.view === 'schedule') {
                renderSchedule();
            } else if (state.view === 'exceptions') {
                renderExceptions();
            } else if (state.view === 'wallet') {
                renderWallet();
            } else if (state.view === 'settlements') {
                renderSettlements();
            } else if (state.view === 'profile') {
                renderProfile();
            } else if (state.view === 'settings') {
                renderSettings();
            } else {
                renderToday();
            }
        }

        function setView(view) {
            if (!doctorViews.includes(view)) {
                view = 'today';
            }
            state.view = view;
            el.querySelectorAll('.wb-nav-item').forEach((button) => {
                const active = button.dataset.wbView === view;
                button.classList.toggle('is-active', active);
                button.classList.toggle('active', active);
            });
            render();
        }

        window.addEventListener('hashchange', () => {
            const view = dashboardViewFromHash(doctorViews, 'today');
            if (view !== state.view) {
                setView(view);
            }
        });

        function refreshCurrentView() {
            if (state.view === 'calendar') {
                loadCalendar();
            } else if (state.view === 'today') {
                renderToday();
            } else {
                setView(state.view);
            }
        }

        function openModal() {
            modal.hidden = false;
            document.documentElement.classList.add('webtanan-modal-open');
            releaseWalkinFocus();
            releaseWalkinFocus = activateDialogFocus(modal.querySelector('.wb-modal-panel'), closeModal);
        }

        function closeModal() {
            releaseWalkinFocus();
            releaseWalkinFocus = () => {};
            modal.hidden = true;
            document.documentElement.classList.remove('webtanan-modal-open');
            walkinForm.reset();
            walkinForm.querySelectorAll('[data-jalali-ready="1"]').forEach((input) => {
                input.dispatchEvent(new Event('change', { bubbles: true }));
            });
            const message = walkinForm.querySelector('.wb-form-message');
            if (message) {
                message.textContent = '';
            }
        }

        modal && modal.addEventListener('click', (event) => {
            if (event.target === modal || event.target.closest('.wb-close-modal')) {
                closeModal();
            }
        });

        el.addEventListener('click', (event) => {
            if (event.target.closest('.wb-logout')) {
                request('/auth/logout', { method: 'POST', body: '{}' }).then(() => window.location.reload());
                return;
            }
            const nav = event.target.closest('[data-wb-view]');
            if (nav && (nav.closest('.wb-nav') || nav.closest('.wb-settings-grid'))) {
                if (nav.tagName === 'A' && nav.getAttribute('href')) {
                    return;
                }
                setView(nav.dataset.wbView);
                return;
            }
            const walkinTrigger = event.target.closest('.wb-open-walkin');
            if (walkinTrigger) {
                openModal();
                const dateInput = walkinForm.querySelector('[name="appointment_date"]');
                const timeInput = walkinForm.querySelector('[name="start_time"]');
                if (walkinTrigger.dataset.date && dateInput) {
                    dateInput.value = walkinTrigger.dataset.date;
                    dateInput.dispatchEvent(new Event('change', { bubbles: true }));
                }
                if (walkinTrigger.dataset.time && timeInput) {
                    timeInput.value = walkinTrigger.dataset.time;
                }
                return;
            }
            if (event.target.closest('.wb-refresh-today')) {
                const date = content.querySelector('.wb-dashboard-date');
                if (date) {
                    state.date = date.value;
                }
                reloadAppointments();
                return;
            }
            if (event.target.closest('.wb-bulk-cancel-selected')) {
                bulkCancelAppointments(false);
                return;
            }
            if (event.target.closest('.wb-bulk-cancel-day')) {
                bulkCancelAppointments(true);
                return;
            }
            if (event.target.classList.contains('wb-select-all-appointments')) {
                const checked = event.target.checked;
                content.querySelectorAll('.wb-appointment-check:not(:disabled)').forEach((item) => {
                    item.checked = checked;
                });
                return;
            }
            const recordButton = event.target.closest('[data-action="record"]');
            if (recordButton) {
                state.view = 'records';
                el.querySelectorAll('.wb-nav-item').forEach((button) => {
                    const active = button.dataset.wbView === 'records';
                    button.classList.toggle('is-active', active);
                    button.classList.toggle('active', active);
                });
                renderRecords(recordButton.dataset.patientId);
                return;
            }
            if (event.target.closest('.wb-load-calendar')) {
                loadCalendar();
                return;
            }
            const focusDay = event.target.closest('.wb-calendar-focus-day');
            if (focusDay) {
                state.date = focusDay.dataset.calendarDate || state.date;
                state.calendarMode = 'day';
                renderCalendar();
                return;
            }
            const calendarMode = event.target.closest('[data-calendar-mode]');
            if (calendarMode) {
                state.calendarMode = calendarMode.dataset.calendarMode || 'day';
                renderCalendar();
                return;
            }
            if (event.target.closest('.wb-calendar-today')) {
                state.date = cfg.today || new Date().toISOString().slice(0, 10);
                renderCalendar();
                return;
            }
            if (event.target.closest('.wb-load-patients')) {
                loadPatients();
                return;
            }
            const quickSearch = event.target.closest('.wb-patient-search-button');
            if (quickSearch) {
                const value = el.querySelector('.wb-patient-quick-search') ? el.querySelector('.wb-patient-quick-search').value.trim() : '';
                const url = new URL(cfg.archiveUrl || '/?post_type=saas_doctors', window.location.href);
                if (value) {
                    url.searchParams.set('doctor_search', value);
                }
                window.location.href = url.toString();
                return;
            }
            const action = event.target.closest('[data-action]');
            if (!action) {
                return;
            }
            const id = action.dataset.id;
            if (action.dataset.action === 'delete-schedule' || action.dataset.action === 'delete-exception') {
                const isSchedule = action.dataset.action === 'delete-schedule';
                confirmModal({
                    title: isSchedule ? 'حذف بازه هفتگی' : 'حذف برنامه تاریخ خاص',
                    message: 'این تغییر روی نوبت‌های قطعی قبلی اثری ندارد، اما زمان‌های آزاد آینده را تغییر می‌دهد.',
                    confirmText: 'حذف برنامه',
                    danger: true
                }).then((result) => {
                    if (!result) {
                        return;
                    }
                    const path = isSchedule ? `/doctor-dashboard/schedules/${id}` : `/doctor-dashboard/exceptions/${id}`;
                    request(`${path}?${doctorQuery()}`, { method: 'DELETE' })
                        .then(() => {
                            toast('برنامه حذف شد.', 'success');
                            isSchedule ? loadSchedules() : loadExceptions();
                        })
                        .catch((error) => toast(error.message, 'error'));
                });
            } else if (action.dataset.action === 'payment') {
                request(`/doctor-dashboard/appointments/${id}/payment`, {
                    method: 'POST',
                    body: JSON.stringify({ payment_status: action.dataset.status })
                }).then(refreshCurrentView).catch((error) => toast(error.message, 'error'));
            } else if (action.dataset.action === 'attendance') {
                request(`/doctor-dashboard/appointments/${id}/status`, {
                    method: 'POST',
                    body: JSON.stringify({ appointment_status: action.dataset.status })
                }).then(refreshCurrentView).catch((error) => toast(error.message, 'error'));
            } else if (action.dataset.action === 'cancel') {
                confirmModal({
                    title: 'لغو نوبت',
                    message: 'لغو نوبت می‌تواند باعث ثبت استرداد یا برگشت سهم‌ها در کیف پول شود.',
                    reason: 'دلیل لغو',
                    confirmText: 'لغو نوبت',
                    danger: true
                }).then((result) => {
                    if (!result) {
                        return;
                    }
                    request('/appointments/cancel', {
                        method: 'POST',
                        body: JSON.stringify({ appointment_id: id, cancelled_by: 'secretary', reason: result.reason })
                    }).then(() => {
                        toast('نوبت با موفقیت لغو شد.', 'success');
                        refreshCurrentView();
                    }).catch((error) => toast(error.message, 'error'));
                });
                return;
            } else if (action.dataset.action === 'receipt') {
                openReceipt(id);
            } else if (action.dataset.action === 'record') {
                state.view = 'records';
                el.querySelectorAll('.wb-nav-item').forEach((button) => {
                    const active = button.dataset.wbView === 'records';
                    button.classList.toggle('is-active', active);
                    button.classList.toggle('active', active);
                });
                renderRecords(action.dataset.patientId || '');
            }
        });

        content.addEventListener('submit', (event) => {
            if (event.target.matches('.wb-schedule-form')) {
                event.preventDefault();
                request(`/doctor-dashboard/schedules?${doctorQuery()}`, {
                    method: 'POST',
                    body: JSON.stringify(formObject(event.target))
                }).then((result) => {
                    event.target.reset();
                    toast(result && result.updated ? 'بازه قبلی به‌روزرسانی شد.' : 'بازه هفتگی ثبت شد.', 'success');
                    loadSchedules();
                }).catch((error) => toast(error.message, 'error'));
            } else if (event.target.matches('.wb-exception-form')) {
                event.preventDefault();
                request(`/doctor-dashboard/exceptions?${doctorQuery()}`, {
                    method: 'POST',
                    body: JSON.stringify(formObject(event.target))
                }).then(() => {
                    event.target.reset();
                    toast('برنامه تاریخ خاص ثبت شد.', 'success');
                    loadExceptions();
                }).catch((error) => toast(error.message, 'error'));
            } else if (event.target.matches('.wb-settlement-form')) {
                event.preventDefault();
                request(`/doctor-dashboard/settlement-request?${doctorQuery()}`, {
                    method: 'POST',
                    body: JSON.stringify(formObject(event.target))
                }).then(() => renderSettlements()).catch((error) => toast(error.message, 'error'));
            } else if (event.target.matches('.wb-record-form')) {
                event.preventDefault();
                const card = event.target.closest('.wb-record-card');
                const patientId = card ? card.dataset.patientId : '';
                const message = card ? card.querySelector('.wb-record-message') : null;
                message && (message.textContent = 'در حال ذخیره پرونده...');
                request(`/doctor-dashboard/patients/${patientId}/record?${doctorQuery()}`, {
                    method: 'POST',
                    body: JSON.stringify(formObject(event.target))
                }).then(() => {
                    message && (message.textContent = 'پرونده ذخیره شد.');
                }).catch((error) => {
                    message && (message.textContent = error.message);
                });
            } else if (event.target.matches('.wb-record-note-form')) {
                event.preventDefault();
                const card = event.target.closest('.wb-record-card');
                const patientId = card ? card.dataset.patientId : '';
                const message = card ? card.querySelector('.wb-record-message') : null;
                message && (message.textContent = 'در حال ثبت یادداشت...');
                request(`/doctor-dashboard/patients/${patientId}/record/notes?${doctorQuery()}`, {
                    method: 'POST',
                    body: JSON.stringify(formObject(event.target))
                }).then(() => {
                    loadPatientRecord(patientId);
                }).catch((error) => {
                    message && (message.textContent = error.message);
                });
            } else if (event.target.matches('.wb-record-file-form')) {
                event.preventDefault();
                const form = event.target;
                const card = form.closest('.wb-record-card');
                const patientId = card ? card.dataset.patientId : '';
                const message = card ? card.querySelector('.wb-record-message') : null;
                const fileInput = form.querySelector('input[type="file"]');
                if (!fileInput || !fileInput.files || !fileInput.files[0]) {
                    message && (message.textContent = 'ابتدا یک فایل انتخاب کنید.');
                    return;
                }
                const data = new FormData();
                data.append('file', fileInput.files[0]);
                data.append('visibility', new FormData(form).get('visibility') || 'patient');
                message && (message.textContent = 'در حال آپلود فایل پرونده...');
                requestFormData(`/doctor-dashboard/patients/${patientId}/record/files?${doctorQuery()}`, data)
                    .then(() => {
                        loadPatientRecord(patientId);
                    })
                    .catch((error) => {
                        message && (message.textContent = error.message);
                    });
            } else if (event.target.matches('.wb-profile-form')) {
                event.preventDefault();
                const form = event.target;
                const message = form.querySelector('.wb-profile-message');
                message && (message.textContent = 'در حال ذخیره پروفایل...');
                const data = formObject(form);
                data.gallery_ids = String(data.gallery_ids || '').split(',').filter(Boolean);
                request(`/doctor-dashboard/profile?${doctorQuery()}`, {
                    method: 'POST',
                    body: JSON.stringify(data)
                }).then(() => {
                    message && (message.textContent = 'پروفایل ذخیره شد.');
                }).catch((error) => {
                    message && (message.textContent = error.message);
                });
            }
        });

        content.addEventListener('change', (event) => {
            if (event.target.matches('.wb-dashboard-date')) {
                state.date = event.target.value || state.date;
                window.setTimeout(renderToday, 0);
                return;
            }
            if (event.target.matches('.wb-calendar-date')) {
                state.date = event.target.value || state.date;
                loadCalendar();
                return;
            }
            const fileInput = event.target;
            if (!fileInput.matches('.wb-profile-form input[type="file"]') || !fileInput.files || !fileInput.files[0]) {
                return;
            }
            const form = fileInput.closest('.wb-profile-form');
            const message = form.querySelector('.wb-profile-message');
            const data = new FormData();
            data.append('file', fileInput.files[0]);
            message && (message.textContent = 'در حال آپلود تصویر...');
            requestFormData(`/doctor-dashboard/profile/upload?${doctorQuery()}`, data)
                .then((uploaded) => {
                    if (fileInput.name === 'thumbnail_file') {
                        form.querySelector('input[name="thumbnail_id"]').value = uploaded.id || '';
                        const preview = form.querySelector('.wb-profile-preview');
                        if (preview) {
                            preview.innerHTML = `<img src="${esc(uploaded.thumbnail || uploaded.url)}" alt="">`;
                        }
                    } else {
                        const idsInput = form.querySelector('input[name="gallery_ids"]');
                        const ids = String(idsInput.value || '').split(',').filter(Boolean);
                        ids.push(String(uploaded.id || ''));
                        idsInput.value = Array.from(new Set(ids)).join(',');
                        const gallery = form.querySelector('.wb-gallery-preview');
                        if (gallery) {
                            gallery.insertAdjacentHTML('beforeend', `<span data-id="${esc(uploaded.id || '')}"><img src="${esc(uploaded.thumbnail || uploaded.url)}" alt=""></span>`);
                        }
                    }
                    message && (message.textContent = 'تصویر آپلود شد.');
                    fileInput.value = '';
                })
                .catch((error) => {
                    message && (message.textContent = error.message);
                });
        });

        walkinForm.addEventListener('submit', (event) => {
            event.preventDefault();
            const message = walkinForm.querySelector('.wb-form-message');
            message.textContent = 'در حال ثبت نوبت';
            request(`/doctor-dashboard/appointments?${doctorQuery()}`, {
                method: 'POST',
                body: JSON.stringify(formObject(walkinForm))
            }).then(() => {
                closeModal();
                renderToday();
            }).catch((error) => {
                message.textContent = error.message;
            });
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && !modal.hidden) {
                closeModal();
            }
        });

        doctorSelect.addEventListener('change', () => {
            state.doctorId = Number(doctorSelect.value || 0);
            render();
        });

        request('/doctor-dashboard/context')
            .then((context) => {
                state.context = context;
                state.doctorId = context.active_doctor_id || (context.doctors[0] && context.doctors[0].id) || 0;
                if (!state.doctorId) {
                    content.innerHTML = panel('پزشکی برای این حساب پیدا نشد.');
                    return;
                }
                doctorSelect.innerHTML = context.doctors.map((doctor) => `<option value="${doctor.id}" ${Number(doctor.id) === Number(state.doctorId) ? 'selected' : ''}>${esc(doctor.title || doctor.clinic_name)}</option>`).join('');
                doctorSelect.hidden = !context.can_switch_doctors || context.doctors.length < 2;
                setView(state.view);
            })
            .catch((error) => {
                content.innerHTML = panel(error.message);
            });
    }

    function receiptRowsMarkup(receipt) {
        const item = receipt.appointment || {};
        const doctor = receipt.doctor || {};
        const paymentAmount = item.payment_amount != null ? item.payment_amount : (item.booking_fee != null ? item.booking_fee : 0);
        const rows = [
            ['کد نوبت', item.appointment_code || '-'],
            ['نام پزشک', item.doctor_title || doctor.title || ''],
            ['نام بیمار', item.patient_full_name || ''],
            ['کد ملی', item.patient_national_code || ''],
            ['موبایل', item.patient_mobile || ''],
            ['تاریخ', displayDate(item.appointment_date, false)],
            ['ساعت', item.start_time || ''],
            [fieldLabel('booking_fee'), `${money(paymentAmount)} تومان`],
        ];

        rows.push(['روش پرداخت', displayStatusLabel(item.payment_label, item.payment_status)]);
        rows.push(['آدرس مطب', item.clinic_address || doctor.clinic_address || '']);

        return rows.map((row) => `<dt>${esc(row[0])}</dt><dd>${esc(row[1])}</dd>`).join('');
    }

    function receiptAccountingMarkup(receipt) {
        const item = receipt.appointment || {};
        const paid = Number(item.payment_amount != null ? item.payment_amount : (item.booking_fee != null ? item.booking_fee : 0));
        return `<table class="wb-receipt-accounting">
            <tbody>
                <tr><th>هزینه خدمات رزرو نوبت</th><td>${money(paid)} تومان</td></tr>
            </tbody>
        </table>`;
    }

    function receiptCardMarkup(receipt) {
        const item = receipt.appointment || {};

        return `<article class="wb-receipt-paper wb-receipt-card webtanan-booking-success">
            <div class="wb-receipt-watermark" aria-hidden="true">+</div>
            <header class="webtanan-checkout-head wb-receipt-paper-head">
                <div>
                    <span>فاکتور نوبت</span>
                    <strong>نوبت با موفقیت قطعی شد</strong>
                </div>
                <button type="button" class="wb-btn wb-btn-primary" data-print-current-receipt>چاپ فاکتور</button>
            </header>
            ${item.appointment_code ? `<div class="wb-receipt-hero-code"><span>کد پیگیری نوبت</span><strong>${esc(item.appointment_code)}</strong></div>` : ''}
            <dl class="wb-factor-list">${receiptRowsMarkup(receipt)}</dl>
            ${receiptAccountingMarkup(receipt)}
            <p class="webtanan-checkout-note">کد نوبت را تا زمان مراجعه نگه دارید و هنگام ورود به مطب ارائه کنید.</p>
            ${item.appointment_code ? `<p class="wb-receipt-code">کد پیگیری نوبت: <strong>${esc(item.appointment_code)}</strong></p>` : ''}
        </article>`;
    }

    function printReceipt(receipt) {
        const item = receipt.appointment || {};
        const popup = window.open('', '_blank', 'width=760,height=900');
        if (!popup) {
            toast('مرورگر پنجره چاپ را مسدود کرده است. اجازه باز شدن پنجره جدید را فعال کنید.', 'error');
            return;
        }
        const printFontUrl = `${String(cfg.assetsUrl || '').replace(/\/$/, '')}/fonts/vazir/Vazir-Regular.woff2`;
        popup.document.write(`<!doctype html><html dir="rtl" lang="fa-IR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>${esc(receipt.print_title || 'رسید')}</title><style>
            @font-face{font-family:Vazir;src:url("${esc(printFontUrl)}") format("woff2");font-weight:400;font-display:swap}body{font-family:Vazir,Tahoma,Arial,sans-serif;margin:0;background:#eef7fb;color:#0f172a;line-height:1.85}
            .print{position:fixed;top:18px;right:18px;z-index:3;min-height:44px;padding:10px 16px;color:#fff;background:#0ea5e9;border:0;border-radius:10px;font-weight:900;box-shadow:0 12px 30px rgba(14,165,233,.24)}
            .receipt{position:relative;width:min(800px,calc(100% - 32px));margin:38px auto;background:#fff;border:1px solid #dbeafe;border-radius:18px;overflow:hidden;box-shadow:0 24px 70px rgba(15,23,42,.12)}
            .receipt:before{content:"+";position:absolute;left:38px;top:92px;color:rgba(14,165,233,.08);font-size:220px;font-weight:900;line-height:1;transform:rotate(8deg)}
            .head{position:relative;padding:28px 34px;border-bottom:1px solid #e2e8f0;background:linear-gradient(135deg,#f0f9ff,#fff)}
            h1{margin:0;font-size:30px}.code{display:inline-grid;gap:3px;margin-top:14px;padding:12px 16px;background:#ecfdf5;border:1px solid #bbf7d0;border-radius:12px;color:#047857}.code strong{font-size:24px;color:#065f46}
            dl{position:relative;display:grid;grid-template-columns:210px 1fr;gap:0;margin:0;padding:20px 34px 8px}
            dt,dd{padding:11px 0;border-bottom:1px solid #edf2f7}dt{color:#64748b}dd{margin:0;font-weight:800}
            table{position:relative;width:calc(100% - 68px);margin:18px 34px 28px;border-collapse:separate;border-spacing:0;border:1px solid #dbeafe;border-radius:14px;overflow:hidden}
            th,td{padding:14px 16px;border-bottom:1px solid #dbeafe;text-align:right}tr:last-child th,tr:last-child td{border-bottom:0}th{color:#475569;background:#f8fafc}td{font-weight:900}
            .note{position:relative;margin:0;padding:16px 34px;color:#64748b;font-size:13px;background:#fff7ed;border-top:1px solid #fed7aa}
            @media print{body{background:#fff}.print{display:none}.receipt{margin:0 auto;border-color:#dbeafe;box-shadow:none}}
        </style></head><body><button class="print" onclick="window.print()">چاپ رسید نوبت</button><main class="receipt"><div class="head"><h1>رسید نوبت</h1>${item.appointment_code ? `<div class="code"><span>کد پیگیری نوبت</span><strong>${esc(item.appointment_code)}</strong></div>` : ''}</div><dl>${receiptRowsMarkup(receipt)}</dl>${receiptAccountingMarkup(receipt)}<p class="note">این رسید برای پیگیری نوبت صادر شده است. لطفاً هنگام مراجعه، کد پیگیری را همراه داشته باشید.</p></main><script>window.print();<\/script></body></html>`);
        popup.document.close();
    }

    function openReceipt(id) {
        request(`/appointments/${id}/receipt`)
            .then(printReceipt)
            .catch((error) => toast(error.message, 'error'));
    }

    function initPatientPanel(el) {
        if (!cfg.isLoggedIn) {
            el.innerHTML = `<div class="webtanan-empty-state wb-login-required">
                <strong>برای مشاهده پنل بیمار وارد حساب شوید</strong>
                <p>ورود و ثبت‌نام با شماره موبایل انجام می‌شود.</p>
                <a class="wb-btn wb-btn-primary" href="${esc(cfg.loginUrl || '/?webtanan_auth_page=1')}">ورود / ثبت‌نام</a>
            </div>`;
            return;
        }

        const content = el.querySelector('.wb-content');
        const todayLabels = el.querySelectorAll('.wb-today-label');
        const patientViews = ['patient-overview', 'patient-appointments', 'patient-history', 'patient-records', 'patient-wallet', 'patient-family', 'patient-favorites', 'patient-profile', 'help'];
        const state = { view: dashboardViewFromHash(patientViews, 'patient-overview') };

        function closePatientPayment(overlay) {
            overlay && overlay.remove();
            document.documentElement.classList.remove('webtanan-modal-open');
        }

        function openPatientPayment(appointmentId) {
            const overlay = document.createElement('div');
            overlay.className = 'wb-payment-overlay';
            overlay.dir = 'rtl';
            overlay.innerHTML = `<section class="wb-payment-dialog" role="dialog" aria-modal="true" aria-label="تکمیل پرداخت نوبت" tabindex="-1">
                <header class="wb-modal-head"><div><span>فاکتور نوبت</span><h3>تکمیل پرداخت</h3></div><button type="button" class="wb-icon-button" data-close-payment aria-label="بستن">×</button></header>
                <div class="wb-payment-dialog-body">${loadingPanel('در حال آماده‌کردن فاکتور...')}</div>
            </section>`;
            document.body.appendChild(overlay);
            document.documentElement.classList.add('webtanan-modal-open');
            const body = overlay.querySelector('.wb-payment-dialog-body');
            let releaseFocus = () => {};
            let countdownTimer = 0;
            let currentLock = null;
            let selection = null;
            const clearCountdown = () => {
                if (countdownTimer) {
                    window.clearInterval(countdownTimer);
                    countdownTimer = 0;
                }
            };
            const close = () => {
                clearCountdown();
                releaseFocus();
                closePatientPayment(overlay);
            };
            releaseFocus = activateDialogFocus(overlay.querySelector('.wb-payment-dialog'), close);
            overlay.addEventListener('click', (event) => {
                if (event.target === overlay || event.target.closest('[data-close-payment]')) {
                    close();
                }
            });

            const renderExpiredLock = () => {
                clearCountdown();
                currentLock = null;
                selection = null;
                body.innerHTML = `<div class="wb-resume-expired-state">
                    <i class="far fa-clock" aria-hidden="true"></i>
                    <h3>مهلت پرداخت این نوبت تمام شد</h3>
                    <p>می‌توانید همین ساعت را دوباره بررسی کنید. اگر هنوز آزاد باشد، فوراً برای شما نگه داشته می‌شود.</p>
                    <button type="button" class="wb-btn wb-btn-primary" data-relock-appointment>بررسی و نگه‌داشتن دوباره همین ساعت</button>
                    <div class="wb-payment-message" aria-live="polite"></div>
                </div>`;
            };

            const startPaymentCountdown = (lock) => {
                clearCountdown();
                const expiresAt = Number(lock.locked_until_timestamp || 0) * 1000;
                const target = Number.isFinite(expiresAt) && expiresAt > 0 ? expiresAt : Date.now() + (15 * 60 * 1000);
                const paint = () => {
                    const remaining = Math.max(0, Math.ceil((target - Date.now()) / 1000));
                    const timer = body.querySelector('[data-payment-countdown]');
                    if (timer) {
                        timer.textContent = `${String(Math.floor(remaining / 60)).padStart(2, '0')}:${String(remaining % 60).padStart(2, '0')}`;
                    }
                    if (remaining <= 0) {
                        renderExpiredLock();
                    }
                };
                paint();
                if (currentLock) {
                    countdownTimer = window.setInterval(paint, 1000);
                }
            };

            const renderPayment = (resume, gateways, wallet) => {
                const lock = resume.lock || {};
                const item = resume.appointment || {};
                if (lock.status === 'already_confirmed') {
                    body.innerHTML = `<div class="webtanan-booking-success"><strong>این نوبت قبلاً قطعی شده است.</strong><button type="button" class="wb-btn" data-close-payment>بستن</button></div>`;
                    return;
                }
                currentLock = lock;
                selection = null;
                const amount = Number(lock.amount != null ? lock.amount : (item.payment_amount || item.booking_fee || 0));
                const walletBalance = Number(wallet.balance || 0);
                const gatewayItems = Array.isArray(gateways) ? gateways : [];
                const gatewayOptions = gatewayItems.map((gateway) => `<button type="button" class="webtanan-payment-option" data-method="online" data-gateway="${esc(gateway.id)}">
                    <span class="wb-payment-radio" aria-hidden="true"></span><strong>${esc(gateway.title || 'درگاه پرداخت')}</strong><small>${gateway.sandbox ? 'درگاه آزمایشی' : 'پرداخت آنلاین امن'}</small>
                </button>`).join('');
                body.innerHTML = `<div class="webtanan-checkout wb-patient-resume-checkout">
                    <div class="wb-resume-lock-banner" data-status="${esc(lock.status || 'locked')}">
                        <div><i class="fas fa-shield-alt" aria-hidden="true"></i><strong>${lock.status === 'relocked' ? 'این ساعت هنوز آزاد بود و دوباره برای شما نگه داشته شد.' : 'زمان نوبت برای شما نگه داشته شده است.'}</strong></div>
                        <span>زمان باقی‌مانده برای پرداخت: <b data-payment-countdown>--:--</b></span>
                    </div>
                    <div class="webtanan-payment-summary">
                        <div><span>پزشک</span><strong>${esc(item.doctor_title || 'پزشک')}</strong></div>
                        <div><span>تاریخ و ساعت</span><strong>${esc(displayDate(item.appointment_date, false))}، ${esc(item.start_time || '')}</strong></div>
                        <div><span>مراجعه‌کننده</span><strong>${esc(item.patient_display_name || '')}</strong></div>
                        <div><span>${fieldLabel('booking_fee')}</span><strong>${money(amount)} تومان</strong></div>
                    </div>
                    <div class="webtanan-payment-options">
                        <button type="button" class="webtanan-payment-option" data-method="wallet" ${walletBalance < amount ? 'disabled' : ''}>
                            <span class="wb-payment-radio" aria-hidden="true"></span><strong>پرداخت از کیف پول</strong><small>موجودی: ${money(walletBalance)} تومان</small>
                        </button>
                        ${gatewayOptions || '<div class="webtanan-panel">درگاه پرداخت فعالی تنظیم نشده است.</div>'}
                    </div>
                    <button type="button" class="wb-btn wb-btn-success wb-patient-pay-submit" disabled>پرداخت و ثبت قطعی نوبت</button>
                    <div class="wb-payment-message" aria-live="polite"></div>
                </div>`;
                startPaymentCountdown(lock);
            };

            const loadPayment = () => {
                clearCountdown();
                body.innerHTML = loadingPanel('در حال بررسی دوباره ساعت نوبت...');
                return Promise.all([
                    request(`/patient-panel/appointments/${appointmentId}/resume`, { method: 'POST', body: '{}' }),
                    request('/payment/gateways').catch(() => []),
                    request('/wallet/balance?user_type=patient').catch(() => ({ balance: 0 }))
                ]).then(([resume, gateways, wallet]) => {
                    renderPayment(resume, gateways, wallet);
                }).catch((error) => {
                    const suggestions = error.data && Array.isArray(error.data.suggested_slots) ? error.data.suggested_slots : [];
                    body.innerHTML = `<div class="webtanan-panel webtanan-panel-error"><strong>${esc(error.message)}</strong>${suggestions.length ? '<p>این ساعت دیگر آزاد نیست؛ از صفحه پزشک یک زمان تازه انتخاب کنید.</p>' : ''}</div><button type="button" class="wb-btn" data-close-payment>بستن</button>`;
                });
            };

            body.addEventListener('click', (event) => {
                if (event.target.closest('[data-relock-appointment]')) {
                    loadPayment();
                    return;
                }
                const option = event.target.closest('.webtanan-payment-option');
                if (option && !option.disabled) {
                    body.querySelectorAll('.webtanan-payment-option').forEach((node) => node.dataset.selected = 'false');
                    option.dataset.selected = 'true';
                    selection = { method: option.dataset.method || '', gateway: option.dataset.gateway || '' };
                    const submit = body.querySelector('.wb-patient-pay-submit');
                    submit && (submit.disabled = false);
                    return;
                }
                const submit = event.target.closest('.wb-patient-pay-submit');
                if (!submit || !selection || !currentLock) {
                    return;
                }
                submit.disabled = true;
                submit.classList.add('is-loading');
                const message = body.querySelector('.wb-payment-message');
                const paymentLock = Object.assign({}, currentLock);
                message.textContent = 'در حال انجام پرداخت...';
                request('/appointments/pay', {
                    method: 'POST',
                    body: JSON.stringify({
                        appointment_id: paymentLock.appointment_id,
                        lock_token: paymentLock.lock_token,
                        method: selection.method,
                        gateway: selection.gateway
                    })
                }).then((result) => {
                    if (result.checkout_url) {
                        clearCountdown();
                        window.location.href = result.checkout_url;
                        return;
                    }
                    clearCountdown();
                    return request(`/appointments/${paymentLock.appointment_id}/receipt`).then((receipt) => {
                        body.innerHTML = receiptCardMarkup(receipt);
                        const print = body.querySelector('[data-print-current-receipt]');
                        print && print.addEventListener('click', () => printReceipt(receipt));
                        render();
                    });
                }).catch((error) => {
                    if (error.code === 'webtanan_invalid_lock' || error.code === 'webtanan_lock_expired') {
                        renderExpiredLock();
                        return;
                    }
                    submit.disabled = false;
                    submit.classList.remove('is-loading');
                    message.textContent = error.message;
                });
            });

            loadPayment();
        }

        function openPatientSurvey(appointmentId) {
            const overlay = document.createElement('div');
            overlay.className = 'wb-payment-overlay wb-survey-overlay';
            overlay.dir = 'rtl';
            overlay.innerHTML = `<section class="wb-payment-dialog wb-survey-dialog" role="dialog" aria-modal="true" aria-label="ثبت نظر درباره مراجعه" tabindex="-1">
                <header class="wb-modal-head"><div><span>نظر شما</span><h3>تجربه مراجعه</h3></div><button type="button" class="wb-icon-button" data-close-survey aria-label="بستن">×</button></header>
                <div class="wb-payment-dialog-body">${loadingPanel('در حال آماده‌سازی فرم نظر...')}</div>
            </section>`;
            document.body.appendChild(overlay);
            document.documentElement.classList.add('webtanan-modal-open');
            const body = overlay.querySelector('.wb-payment-dialog-body');
            let releaseFocus = () => {};
            const close = () => {
                releaseFocus();
                overlay.remove();
                document.documentElement.classList.remove('webtanan-modal-open');
            };
            releaseFocus = activateDialogFocus(overlay.querySelector('.wb-survey-dialog'), close);
            overlay.addEventListener('click', (event) => {
                if (event.target === overlay || event.target.closest('[data-close-survey]')) {
                    close();
                }
            });

            request(`/patient-panel/appointments/${appointmentId}/survey`)
                .then((data) => {
                    const selectedRating = Number(data.rating || 5);
                    body.innerHTML = `<form class="wb-survey-form wb-patient-survey-form">
                        <div class="wb-public-doctor-name">${esc(data.doctor_name || 'پزشک')}</div>
                        ${publicAppointmentSummary(data)}
                        ${data.submitted ? '<div class="wb-survey-saved-note"><i class="fas fa-check-circle" aria-hidden="true"></i><span>نظر قبلی شما آماده ویرایش است.</span></div>' : ''}
                        <fieldset class="wb-rating-picker">
                            <legend>امتیاز شما</legend>
                            ${[5, 4, 3, 2, 1].map((rate) => `<label><input type="radio" name="rating" value="${rate}" ${rate === selectedRating ? 'checked' : ''}><span>${'★'.repeat(rate)}</span></label>`).join('')}
                        </fieldset>
                        <label class="wb-field-full"><span>توضیحات شما</span><textarea name="feedback" rows="5" placeholder="تجربه خود را کوتاه بنویسید.">${esc(data.feedback || '')}</textarea></label>
                        <label class="wb-checkbox-line"><input type="checkbox" name="public_consent" ${data.public_consent !== false ? 'checked' : ''}><span>پس از تایید مدیر، نظر بدون اطلاعات تماس در صفحه پزشک نمایش داده شود.</span></label>
                        <button type="submit" class="wb-btn wb-btn-primary">ثبت نظر</button>
                        <div class="wb-public-message" aria-live="polite"></div>
                    </form>`;
                    const form = body.querySelector('.wb-patient-survey-form');
                    form.addEventListener('submit', (event) => {
                        event.preventDefault();
                        const submit = form.querySelector('[type="submit"]');
                        const message = form.querySelector('.wb-public-message');
                        const values = new FormData(form);
                        submit.disabled = true;
                        submit.classList.add('is-loading');
                        message.textContent = 'در حال ثبت نظر...';
                        request(`/patient-panel/appointments/${appointmentId}/survey`, {
                            method: 'POST',
                            body: JSON.stringify({
                                rating: Number(values.get('rating') || 5),
                                feedback: values.get('feedback') || '',
                                public_consent: values.get('public_consent') === 'on'
                            })
                        }).then(() => {
                            body.innerHTML = `<div class="wb-public-success"><i class="fas fa-check-circle" aria-hidden="true"></i><h3>نظر شما ثبت شد</h3><p>از وقتی که برای بهبود کیفیت خدمات گذاشتید ممنونیم.</p><button type="button" class="wb-btn" data-close-survey>بستن</button></div>`;
                        }).catch((error) => {
                            submit.disabled = false;
                            submit.classList.remove('is-loading');
                            message.textContent = error.message;
                        });
                    });
                })
                .catch((error) => {
                    body.innerHTML = `<div class="webtanan-panel webtanan-panel-error">${esc(error.message)}</div><button type="button" class="wb-btn" data-close-survey>بستن</button>`;
                });
        }

        todayLabels.forEach((node) => {
            node.textContent = faDate(cfg.today);
        });

        function renderTitle(title, subtitle = '') {
            return `<div class="wb-page-head">
                <div class="wb-page-head-copy">
                    <span class="wb-kicker">پیشخوان</span>
                    <h2>${esc(title)}</h2>
                    ${subtitle ? `<p>${esc(subtitle)}</p>` : ''}
                </div>
            </div>`;
        }

        function renderOverview() {
            content.innerHTML = panel('در حال بارگذاری');
            Promise.all([request('/patient-panel/summary'), request('/patient-panel/profile'), request('/patient-panel/appointments?scope=upcoming')])
                .then(([summary, context, upcoming]) => {
                    const profile = context.profile || {};
                    const greeting = profile.first_name ? `سلام ${esc(profile.first_name)}، خوش آمدید` : 'سلام، خوش آمدید';
                    const profileAlert = context.profile_complete ? '' : `<div class="wb-profile-required">
                        <div><strong>اطلاعات حساب کامل نیست</strong><p>برای رزرو نوبت، نام، نام خانوادگی و کد ملی را تکمیل کنید.</p></div>
                        <button type="button" class="wb-btn wb-btn-primary" data-action="open-patient-profile">تکمیل اطلاعات</button>
                    </div>`;
                    content.innerHTML = `${profileAlert}<section class="welcome-section">
                            <div><h2>${greeting}</h2><p>نوبت‌ها، کیف پول و پرونده پزشکی خود را از اینجا پیگیری کنید.</p></div>
                            <a href="${esc((cfg.archiveUrl || '/?post_type=saas_doctors'))}" class="btn"><i class="fas fa-plus-circle"></i> نوبت جدید</a>
                        </section>
                        <div class="stats-grid wb-stats-grid">
                            ${statCard('نوبت‌های آینده', money(summary.upcoming_count), 'مورد', 'ن')}
                            ${statCard('سوابق نوبت', money(summary.history_count), 'مورد', 'س')}
                            ${statCard('موجودی کیف پول', money(summary.wallet_balance), 'تومان', 'ک')}
                            ${statCard('یادآوری‌ها', money(summary.upcoming_count || 0), 'مورد', 'ی')}
                        </div>
                        <div class="dashboard-grid">
                            <div class="card">
                                <div class="card-header"><h3 class="wb-card-title"><i class="fas fa-clock"></i> نوبت‌های آینده</h3></div>
                                ${Array.isArray(upcoming) && upcoming.length ? appointmentsTable(upcoming.slice(0, 3), 'patient') : panel('نوبت آینده‌ای ثبت نشده است.')}
                            </div>
                            <div class="card">
                                <div class="card-header"><h3 class="wb-card-title"><i class="fas fa-search"></i> جستجوی پزشک</h3></div>
                                <form class="search-box wb-patient-doctor-search" method="get" action="${esc(cfg.archiveUrl || '/?post_type=saas_doctors')}"><input type="search" name="search" class="wb-patient-quick-search" placeholder="نام پزشک، تخصص یا شهر" aria-label="جستجوی پزشک"><button type="submit" class="btn btn-primary">جستجو</button></form>
                            </div>
                        </div>`;
                })
                .catch((error) => {
                    content.innerHTML = panel(error.message);
                });
        }

        function renderAppointments(scope) {
            content.innerHTML = panel('در حال بارگذاری');
            request(`/patient-panel/appointments?scope=${scope}`)
                .then((items) => {
                    content.innerHTML = `<div class="card"><div class="card-header"><h3 class="wb-card-title"><i class="fas fa-${scope === 'history' ? 'history' : 'clock'}"></i>${scope === 'history' ? 'تاریخچه ویزیت‌ها' : 'نوبت‌های آینده'}</h3></div>${appointmentsTable(items, 'patient')}</div>`;
                })
                .catch((error) => {
                    content.innerHTML = panel(error.message);
                });
        }

        function renderWallet() {
            content.innerHTML = loadingPanel('در حال بارگذاری کیف پول...');
            request('/patient-panel/wallet')
                .then((wallet) => {
                    const rows = Array.isArray(wallet.ledger) ? wallet.ledger : [];
                    content.innerHTML = `${renderTitle('کیف پول', 'افزایش موجودی، پرداخت‌ها و برگشت پول‌ها')}
                        <div class="wb-stats-grid">${statCard('موجودی فعلی', money(wallet.balance), 'تومان', 'ک')}</div>
                        <form class="wb-wallet-topup-form">
                            <div class="wb-topup-presets" role="group" aria-label="مبلغ‌های پیشنهادی">
                                <button type="button" data-topup-amount="100000">۱۰۰ هزار</button>
                                <button type="button" data-topup-amount="250000">۲۵۰ هزار</button>
                                <button type="button" data-topup-amount="500000">۵۰۰ هزار</button>
                                <button type="button" data-topup-amount="1000000">۱ میلیون</button>
                            </div>
                            <label>
                                <span>مبلغ دلخواه شارژ</span>
                                <input type="number" name="amount" min="10000" step="1000" placeholder="مثلاً ۲۰۰۰۰۰" required>
                            </label>
                            <button type="submit" class="wb-btn wb-btn-primary">افزایش موجودی</button>
                        </form>
                        ${rows.length ? `<div class="wb-table-wrap">
                            <table class="wb-table wb-wallet-table">
                                <thead><tr><th>تاریخ</th><th>نوع</th><th>مبلغ</th><th>مانده بعد</th><th>نوبت</th><th>توضیح</th></tr></thead>
                                <tbody>${rows.map((item) => `<tr>
                                    <td>${esc(displayDate((item.created_at || '').slice(0, 10), false))}<br><span>${esc(item.created_at || '')}</span></td>
                                    <td>${badge(ledgerLabel(item.entry_type), item.entry_type)}</td>
                                    <td class="${Number(item.amount || 0) >= 0 ? 'wb-money-credit' : 'wb-money-debit'}">${money(item.amount)} تومان</td>
                                    <td>${money(item.balance_after)} تومان</td>
                                    <td>${esc(item.appointment_code || '-')}</td>
                                    <td>${esc(item.description || '-')}</td>
                                </tr>`).join('')}</tbody>
                            </table>
                        </div>` : panel('فعلاً گردشی برای کیف پول ثبت نشده است.')}`;
                })
                .catch((error) => {
                    content.innerHTML = panel(error.message);
                });
        }

        function renderMedicalRecords() {
            content.innerHTML = panel('در حال بارگذاری');
            request('/patient-panel/medical-records')
                .then((records) => {
                    const rows = Array.isArray(records) ? records : [];
                    content.innerHTML = `${renderTitle('پرونده پزشکی', 'یادداشت‌هایی که پزشک برای شما قابل مشاهده کرده است')}
                        ${rows.length ? `<div class="wb-record-list">${rows.map((record) => {
                            const notes = Array.isArray(record.notes) ? record.notes : [];
                            const files = Array.isArray(record.files) ? record.files : [];
                            return `<article class="wb-record-card wb-record-card-readonly">
                                <div class="wb-section-head"><h3>${esc(record.doctor_title || record.clinic_name || 'پزشک')}</h3><span>${esc(record.updated_at || '')}</span></div>
                                ${record.summary ? `<p>${esc(record.summary)}</p>` : ''}
                                <div class="wb-record-grid">
                                    <div><span>حساسیت‌ها</span><strong>${esc(record.allergies || '-')}</strong></div>
                                    <div><span>بیماری‌های زمینه‌ای</span><strong>${esc(record.chronic_conditions || '-')}</strong></div>
                                    <div><span>داروهای فعلی</span><strong>${esc(record.current_medications || '-')}</strong></div>
                                </div>
                                <div class="wb-record-files">${files.length ? files.map((file) => `<a class="wb-record-file" href="${esc(file.file_url || '#')}" target="_blank" rel="noopener"><strong>${esc(file.file_name || 'فایل پرونده')}</strong><span>${esc(file.mime_type || '')}</span></a>`).join('') : '<p>فایلی برای نمایش ثبت نشده است.</p>'}</div>
                                <div class="wb-record-notes">${notes.length ? notes.map((note) => `<div class="wb-record-note"><strong>${esc(note.title || 'یادداشت مراجعه')}</strong><p>${esc(note.body || '')}</p><span>${esc(note.created_at || '')}</span></div>`).join('') : '<p>یادداشتی برای نمایش ثبت نشده است.</p>'}</div>
                            </article>`;
                        }).join('')}</div>` : panel('هنوز پرونده‌ای برای نمایش ثبت نشده است.')}`;
                })
                .catch((error) => {
                    content.innerHTML = panel(error.message);
                });
        }

        function patientProfileForm(context) {
            const profile = context.profile || {};
            return `${renderTitle('پروفایل کاربری', 'اطلاعات هویتی مورد استفاده برای رزرو و رسید نوبت')}
                <form class="wb-patient-profile-form wb-account-form">
                    <div class="wb-booking-field-grid">
                        <label class="wb-booking-field"><span>نام</span><input type="text" name="first_name" value="${esc(profile.first_name || '')}" autocomplete="given-name" required></label>
                        <label class="wb-booking-field"><span>نام خانوادگی</span><input type="text" name="last_name" value="${esc(profile.last_name || '')}" autocomplete="family-name" required></label>
                        <label class="wb-booking-field"><span>کد ملی</span><input type="text" name="national_code" value="${esc(profile.national_code || '')}" inputmode="numeric" maxlength="10" required></label>
                        <label class="wb-booking-field"><span>شماره موبایل</span><input type="tel" value="${esc(profile.mobile || '')}" readonly></label>
                    </div>
                    <button type="submit" class="wb-btn wb-btn-primary">ذخیره تغییرات</button>
                    <div class="wb-form-message" aria-live="polite"></div>
                </form>`;
        }

        function renderPatientProfile() {
            content.innerHTML = loadingPanel('در حال بارگذاری پروفایل...');
            request('/patient-panel/profile')
                .then((context) => {
                    content.innerHTML = patientProfileForm(context);
                })
                .catch((error) => {
                    content.innerHTML = panel(error.message);
                });
        }

        function dependentManagerMarkup(context) {
            const dependents = Array.isArray(context.dependents) ? context.dependents : [];
            const cards = dependents.length ? dependents.map((item) => `<article class="wb-dependent-card" data-dependent-id="${esc(item.id)}">
                <div class="wb-dependent-avatar" aria-hidden="true">${esc((item.first_name || 'ف').slice(0, 1))}</div>
                <div class="wb-dependent-copy">
                    <h3>${esc(item.full_name || `${item.first_name || ''} ${item.last_name || ''}`.trim())}</h3>
                    <p>${esc(item.relationship || 'عضو خانواده')}</p>
                    <small>${item.mobile ? esc(item.mobile) : 'پیامک به شماره صاحب حساب ارسال می‌شود'}</small>
                </div>
                <div class="wb-dependent-actions">
                    <button type="button" data-action="edit-dependent" data-dependent="${esc(JSON.stringify(item))}">ویرایش</button>
                    <button type="button" data-action="delete-dependent" data-id="${esc(item.id)}">حذف</button>
                </div>
            </article>`).join('') : panel('هنوز فرد دیگری به حساب شما اضافه نشده است.');

            return `${renderTitle('افراد من', 'برای فرزند، همسر یا اعضای خانواده نیز نوبت رزرو کنید')}
                <div class="wb-section-actions"><button type="button" class="wb-btn wb-btn-primary" data-action="add-dependent"><i class="fas fa-plus"></i> افزودن فرد</button></div>
                <form class="wb-dependent-manager-form wb-account-form" hidden>
                    <input type="hidden" name="id" value="">
                    <div class="wb-booking-field-grid">
                        <label class="wb-booking-field"><span>نام</span><input type="text" name="first_name" required></label>
                        <label class="wb-booking-field"><span>نام خانوادگی</span><input type="text" name="last_name" required></label>
                        <label class="wb-booking-field"><span>نسبت</span><input type="text" name="relationship" placeholder="مثلاً فرزند یا همسر"></label>
                        <label class="wb-booking-field"><span>کد ملی</span><input type="text" name="national_code" inputmode="numeric" maxlength="10" required></label>
                        <label class="wb-booking-field wb-booking-field-wide"><span>شماره موبایل (اختیاری)</span><input type="tel" name="mobile" inputmode="tel"></label>
                    </div>
                    <div class="wb-form-actions">
                        <button type="button" class="wb-btn" data-action="cancel-dependent">انصراف</button>
                        <button type="submit" class="wb-btn wb-btn-primary">ذخیره فرد</button>
                    </div>
                    <div class="wb-form-message" aria-live="polite"></div>
                </form>
                <div class="wb-dependent-list">${cards}</div>`;
        }

        function renderDependents() {
            content.innerHTML = loadingPanel('در حال بارگذاری افراد...');
            request('/patient-panel/profile')
                .then((context) => {
                    content.innerHTML = dependentManagerMarkup(context);
                })
                .catch((error) => {
                    content.innerHTML = panel(error.message);
                });
        }

        function renderFavorites() {
            content.innerHTML = loadingPanel('در حال بارگذاری پزشکان منتخب...');
            request('/patient-panel/favorites')
                .then((result) => {
                    const doctors = Array.isArray(result.doctors) ? result.doctors : [];
                    content.innerHTML = `${renderTitle('پزشکان منتخب', 'پزشکانی که برای دسترسی سریع ذخیره کرده‌اید')}
                        ${doctors.length ? `<div class="webtanan-doctor-list-container wb-favorite-doctor-grid">${doctors.map(doctorCardUnified).join('')}</div>` : panel('هنوز پزشکی را به فهرست منتخب اضافه نکرده‌اید.')}`;
                    content.querySelectorAll('[data-webtanan-widget="next-available"]').forEach(initNextAvailable);
                })
                .catch((error) => {
                    content.innerHTML = panel(error.message);
                });
        }

        function renderHelp() {
            content.innerHTML = `${renderTitle('راهنمای پنل بیمار', 'مسیرهای اصلی برای مدیریت نوبت و حساب')}
                <div class="wb-help-grid">
                    <article class="card"><i class="fas fa-calendar-check" aria-hidden="true"></i><h3>نوبت‌ها</h3><p>نوبت‌های آینده، تکمیل پرداخت، رسید و لغو مجاز را از بخش «نوبت‌های من» مدیریت کنید.</p></article>
                    <article class="card"><i class="fas fa-wallet" aria-hidden="true"></i><h3>کیف پول</h3><p>موجودی، برگشت وجه و افزایش موجودی در بخش «کیف پول» در دسترس است.</p></article>
                    <article class="card"><i class="fas fa-users" aria-hidden="true"></i><h3>افراد من</h3><p>برای اعضای خانواده پروفایل بسازید و هنگام رزرو، مراجعه‌کننده را انتخاب کنید.</p></article>
                    <article class="card"><i class="fas fa-user-md" aria-hidden="true"></i><h3>گرفتن نوبت</h3><p>پزشک را جستجو کنید، ساعت آزاد را انتخاب کنید و پرداخت را تا پایان مهلت کامل کنید.</p><a class="wb-btn wb-btn-primary" href="${esc(cfg.archiveUrl || '/?post_type=saas_doctors')}">مشاهده پزشکان</a></article>
                </div>`;
        }

        function render() {
            if (state.view === 'patient-appointments') {
                renderAppointments('upcoming');
            } else if (state.view === 'patient-history') {
                renderAppointments('history');
            } else if (state.view === 'patient-records') {
                renderMedicalRecords();
            } else if (state.view === 'patient-wallet') {
                renderWallet();
            } else if (state.view === 'patient-family') {
                renderDependents();
            } else if (state.view === 'patient-favorites') {
                renderFavorites();
            } else if (state.view === 'patient-profile') {
                renderPatientProfile();
            } else if (state.view === 'help') {
                renderHelp();
            } else {
                renderOverview();
            }
        }

        function setPatientView(view) {
            state.view = patientViews.includes(view) ? view : 'patient-overview';
            el.querySelectorAll('.wb-nav-item').forEach((button) => {
                const active = button.dataset.wbView === state.view;
                button.classList.toggle('is-active', active);
                button.classList.toggle('active', active);
            });
            render();
        }

        window.addEventListener('hashchange', () => {
            const view = dashboardViewFromHash(patientViews, 'patient-overview');
            if (view !== state.view) {
                setPatientView(view);
            }
        });

        el.addEventListener('click', (event) => {
            if (event.target.closest('.wb-logout')) {
                request('/auth/logout', { method: 'POST', body: '{}' }).then(() => window.location.reload());
                return;
            }
            const nav = event.target.closest('[data-wb-view]');
            if (nav && nav.closest('.wb-nav')) {
                if (nav.tagName === 'A' && nav.getAttribute('href')) {
                    return;
                }
                setPatientView(nav.dataset.wbView);
                return;
            }
            const action = event.target.closest('[data-action]');
            if (!action) {
                return;
            }
            if (action.dataset.action === 'open-patient-profile') {
                setPatientView('patient-profile');
            } else if (action.dataset.action === 'add-dependent') {
                const form = content.querySelector('.wb-dependent-manager-form');
                form && form.reset();
                if (form) {
                    form.hidden = false;
                    form.querySelector('input[name="id"]').value = '';
                    form.querySelector('input:not([type="hidden"])')?.focus();
                }
            } else if (action.dataset.action === 'cancel-dependent') {
                const form = action.closest('form');
                form && form.reset();
                if (form) {
                    form.hidden = true;
                }
            } else if (action.dataset.action === 'edit-dependent') {
                const form = content.querySelector('.wb-dependent-manager-form');
                let item = {};
                try {
                    item = JSON.parse(action.dataset.dependent || '{}');
                } catch (error) {
                    item = {};
                }
                if (form) {
                    ['id', 'first_name', 'last_name', 'relationship', 'national_code', 'mobile'].forEach((key) => {
                        const input = form.querySelector(`[name="${key}"]`);
                        if (input) {
                            input.value = item[key] || '';
                        }
                    });
                    form.hidden = false;
                    form.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            } else if (action.dataset.action === 'delete-dependent') {
                confirmModal({
                    title: 'حذف فرد',
                    message: 'این فرد از فهرست حساب شما حذف شود؟ نوبت‌های قبلی پاک نخواهند شد.',
                    confirmText: 'حذف',
                    danger: true
                }).then((result) => {
                    if (!result) {
                        return;
                    }
                    request(`/patient-panel/dependents/${encodeURIComponent(action.dataset.id || '')}`, {
                        method: 'DELETE'
                    }).then(renderDependents).catch((error) => toast(error.message, 'error'));
                });
            } else if (action.dataset.action === 'receipt') {
                openReceipt(action.dataset.id);
            } else if (action.dataset.action === 'resume-payment') {
                openPatientPayment(action.dataset.id);
            } else if (action.dataset.action === 'survey') {
                action.disabled = true;
                openPatientSurvey(action.dataset.id);
                window.setTimeout(() => {
                    action.disabled = false;
                }, 300);
            } else if (action.dataset.action === 'patient-cancel') {
                const refundText = Number(action.dataset.refund || 0) > 0 ? ` مبلغ تقریبی ${money(action.dataset.refund)} تومان به کیف پول شما برمی‌گردد.` : ' طبق قوانین فعلی ممکن است استردادی ثبت نشود.';
                confirmModal({
                    title: 'لغو نوبت',
                    message: `${action.dataset.message || 'آیا از لغو این نوبت مطمئن هستید؟'}${refundText}`,
                    reason: 'دلیل لغو',
                    confirmText: 'لغو نوبت',
                    danger: true
                }).then((result) => {
                    if (!result) {
                        return;
                    }
                    request('/appointments/cancel', {
                        method: 'POST',
                        body: JSON.stringify({ appointment_id: action.dataset.id, reason: result.reason })
                    }).then(render).catch((error) => toast(error.message, 'error'));
                });
            }
        });

        el.addEventListener('click', (event) => {
            const preset = event.target.closest('[data-topup-amount]');
            if (!preset) {
                return;
            }
            const input = el.querySelector('.wb-wallet-topup-form input[name="amount"]');
            if (input) {
                input.value = preset.dataset.topupAmount || '';
                input.focus();
            }
        });

        el.addEventListener('submit', (event) => {
            const profileForm = event.target.closest('.wb-patient-profile-form');
            if (profileForm) {
                event.preventDefault();
                const message = profileForm.querySelector('.wb-form-message');
                message.textContent = 'در حال ذخیره اطلاعات...';
                request('/patient-panel/profile', {
                    method: 'POST',
                    body: JSON.stringify(formObject(profileForm))
                }).then((context) => {
                    cfg.authContext = context;
                    message.textContent = 'اطلاعات با موفقیت ذخیره شد.';
                }).catch((error) => {
                    message.textContent = error.message;
                });
                return;
            }

            const dependentManager = event.target.closest('.wb-dependent-manager-form');
            if (dependentManager) {
                event.preventDefault();
                const message = dependentManager.querySelector('.wb-form-message');
                message.textContent = 'در حال ذخیره اطلاعات...';
                request('/patient-panel/dependents', {
                    method: 'POST',
                    body: JSON.stringify(formObject(dependentManager))
                }).then(renderDependents).catch((error) => {
                    message.textContent = error.message;
                });
                return;
            }

            const form = event.target.closest('.wb-wallet-topup-form');
            if (!form) {
                return;
            }
            event.preventDefault();
            const button = form.querySelector('button[type="submit"]');
            button && (button.disabled = true);
            request('/wallet/topup', {
                method: 'POST',
                body: JSON.stringify({ amount: Number(new FormData(form).get('amount') || 0) })
            }).then((result) => {
                if (result && result.checkout_url) {
                    window.location.href = result.checkout_url;
                    return;
                }
                renderWallet();
            }).catch((error) => {
                toast(error.message, 'error');
                button && (button.disabled = false);
            });
        });

        setPatientView(state.view);
    }

    function initResumePayment(el) {
        const form = el.querySelector('.wb-resume-form');
        const otpForm = el.querySelector('.wb-resume-otp');
        const checkout = el.querySelector('.wb-resume-checkout');
        const message = el.querySelector('.wb-resume-message');
        const state = {
            appointmentId: Number(el.dataset.appointmentId || 0),
            appointmentCode: '',
            mobile: '',
            resumeToken: '',
            appointment: null,
            lock: null,
            gateways: [],
            walletBalance: 0,
            selection: null
        };
        let countdownTimer = 0;

        function clearResumeCountdown() {
            if (countdownTimer) {
                window.clearInterval(countdownTimer);
                countdownTimer = 0;
            }
        }

        function renderResumeExpired() {
            clearResumeCountdown();
            state.lock = null;
            state.selection = null;
            checkout.hidden = false;
            checkout.innerHTML = `<div class="wb-resume-expired-state"><i class="far fa-clock" aria-hidden="true"></i><h3>مهلت پرداخت این نوبت تمام شد</h3><p>اگر این ساعت هنوز آزاد باشد، می‌توانید دوباره آن را برای پرداخت نگه دارید.</p><button type="button" class="wb-btn wb-btn-primary" data-resume-relock>بررسی و قفل دوباره همین ساعت</button></div>`;
            setMessage('مهلت پرداخت تمام شده است؛ ساعت را دوباره بررسی کنید.', 'error');
        }

        function startResumeCountdown() {
            clearResumeCountdown();
            if (!state.lock || !state.appointmentId) {
                return;
            }
            const serverExpiry = Number(state.lock.locked_until_timestamp || 0) * 1000;
            const expiresAt = Number.isFinite(serverExpiry) && serverExpiry > 0 ? serverExpiry : Date.now() + (15 * 60 * 1000);
            const paint = () => {
                const remaining = Math.max(0, Math.ceil((expiresAt - Date.now()) / 1000));
                const timer = checkout.querySelector('[data-resume-countdown]');
                if (timer) {
                    timer.textContent = `${String(Math.floor(remaining / 60)).padStart(2, '0')}:${String(remaining % 60).padStart(2, '0')}`;
                }
                if (remaining <= 0) {
                    renderResumeExpired();
                }
            };
            paint();
            if (state.lock) {
                countdownTimer = window.setInterval(paint, 1000);
            }
        }

        function setMessage(text, type = '') {
            if (!message) {
                return;
            }
            message.textContent = text || '';
            message.dataset.type = type;
        }

        function renderCheckout() {
            const item = state.appointment || {};
            checkout.hidden = false;
            checkout.innerHTML = loadingPanel('در حال آماده کردن پرداخت...');
            Promise.all([
                request('/payment/gateways').catch(() => []),
                request('/wallet/balance?user_type=patient').catch(() => ({ balance: 0 }))
            ]).then(([gateways, wallet]) => {
                state.gateways = Array.isArray(gateways) ? gateways : [];
                state.walletBalance = Number(wallet && wallet.balance || 0);
                const amount = Number((state.lock && state.lock.amount) != null ? state.lock.amount : (item.payment_amount || item.booking_fee || 0));
                const options = state.gateways.map((gateway) => `<button type="button" class="webtanan-payment-option" data-resume-gateway="${esc(gateway.id)}">
                    <span class="wb-payment-radio" aria-hidden="true"></span>
                    <strong>${esc(gateway.title || 'درگاه آنلاین')}</strong>
                    <small>${gateway.sandbox ? 'درگاه آزمایشی' : 'پرداخت آنلاین امن'}</small>
                </button>`).join('');
                checkout.innerHTML = `<div class="webtanan-checkout">
                    <header class="webtanan-checkout-head"><span>فاکتور نوبت</span><strong>${esc(item.appointment_code || '')}</strong></header>
                    ${state.lock && state.appointmentId ? `<div class="wb-resume-lock-banner" data-status="${esc(state.lock.status || 'locked')}"><div><i class="fas fa-shield-alt" aria-hidden="true"></i><strong>${state.lock.status === 'relocked' ? 'این ساعت هنوز آزاد بود و دوباره برای شما نگه داشته شد.' : 'این ساعت برای شما نگه داشته شده است.'}</strong></div><span>زمان باقی‌مانده: <b data-resume-countdown>--:--</b></span></div>` : ''}
                    <div class="webtanan-payment-summary">
                        <div><span>پزشک</span><strong>${esc(item.doctor_title || '')}</strong></div>
                        <div><span>زمان نوبت</span><strong>${esc(displayDate(item.appointment_date, false))} ساعت ${esc(item.start_time || '')}</strong></div>
                        <div><span>${fieldLabel('booking_fee')}</span><strong>${money(amount)} تومان</strong></div>
                    </div>
                    <h3 class="wb-payment-method-title">روش پرداخت را انتخاب کن</h3>
                    <div class="webtanan-payment-options">
                        <button type="button" class="webtanan-payment-option" data-resume-method="wallet" ${state.walletBalance < amount ? 'disabled' : ''}>
                            <span class="wb-payment-radio" aria-hidden="true"></span>
                            <strong>پرداخت از کیف پول</strong>
                            <small>موجودی: ${money(state.walletBalance)} تومان</small>
                        </button>
                        ${options || '<div class="webtanan-panel">درگاه پرداخت فعالی تنظیم نشده است.</div>'}
                    </div>
                    <button type="button" class="wb-btn wb-btn-success wb-resume-pay-submit" disabled>پرداخت و ثبت قطعی نوبت</button>
                </div>`;
                startResumeCountdown();
            });
        }

        function loadOwnedAppointment() {
            if (!state.appointmentId) {
                return;
            }
            setMessage('در حال آماده کردن فاکتور...');
            checkout.hidden = false;
            checkout.innerHTML = loadingPanel('در حال بررسی ساعت انتخاب‌شده...');
            request(`/patient-panel/appointments/${state.appointmentId}/resume`, { method: 'POST', body: '{}' })
                .then((result) => {
                    state.lock = result.lock || {};
                    state.appointment = result.appointment || {};
                    if (state.lock.status === 'already_confirmed') {
                        setMessage('این نوبت قبلاً قطعی شده است.', 'success');
                        return;
                    }
                    setMessage(state.lock.status === 'relocked' ? 'این ساعت هنوز آزاد بود و دوباره برای شما نگه داشته شد.' : 'فاکتور آماده است؛ روش پرداخت را انتخاب کنید.', 'success');
                    renderCheckout();
                })
                .catch((error) => {
                    checkout.innerHTML = `<div class="webtanan-panel webtanan-panel-error">${esc(error.message)}</div>`;
                    setMessage(error.message, 'error');
                });
        }

        form && form.addEventListener('submit', (event) => {
            event.preventDefault();
            const data = formObject(form);
            state.appointmentCode = data.appointment_code || '';
            state.mobile = data.mobile || '';
            setMessage('در حال ارسال کد تایید...');
            request('/payments/resume/send-otp', {
                method: 'POST',
                body: JSON.stringify({ appointment_code: state.appointmentCode, mobile: state.mobile })
            }).then((result) => {
                form.hidden = true;
                otpForm.hidden = false;
                setMessage('کد تایید ارسال شد.', 'success');
                enhanceOtpInputs(otpForm);
                startOtpCountdown(otpForm, result.expires_in || 180);
                focusOtpForm(otpForm);
            }).catch((error) => setMessage(error.message, 'error'));
        });

        otpForm && otpForm.addEventListener('submit', (event) => {
            event.preventDefault();
            const otp = new FormData(otpForm).get('otp') || '';
            setMessage('در حال بررسی کد تایید...');
            request('/payments/resume/verify', {
                method: 'POST',
                body: JSON.stringify({ appointment_code: state.appointmentCode, mobile: state.mobile, otp })
            }).then((result) => {
                stopOtpCountdown(otpForm);
                if (result.nonce) {
                    cfg.nonce = result.nonce;
                    cfg.isLoggedIn = true;
                }
                state.resumeToken = result.resume_token || '';
                state.appointment = result.appointment || {};
                otpForm.hidden = true;
                setMessage('کد تایید شد. پرداخت را کامل کن.', 'success');
                renderCheckout();
            }).catch((error) => setMessage(error.message, 'error'));
        });

        checkout && checkout.addEventListener('click', (event) => {
            if (event.target.closest('[data-resume-relock]')) {
                loadOwnedAppointment();
                return;
            }
            const option = event.target.closest('[data-resume-gateway], [data-resume-method]');
            if (option && !option.disabled) {
                checkout.querySelectorAll('.webtanan-payment-option').forEach((node) => node.dataset.selected = 'false');
                option.dataset.selected = 'true';
                state.selection = {
                    method: option.dataset.resumeMethod || 'gateway',
                    gateway: option.dataset.resumeGateway || ''
                };
                const submit = checkout.querySelector('.wb-resume-pay-submit');
                submit && (submit.disabled = false);
                return;
            }

            const submit = event.target.closest('.wb-resume-pay-submit');
            if (!submit || !state.selection || (!state.appointmentId && !state.resumeToken)) {
                return;
            }

            submit.disabled = true;
            submit.classList.add('is-loading');
            setMessage(state.selection.method === 'wallet' ? 'در حال پرداخت از کیف پول...' : 'در حال انتقال به درگاه...');
            const direct = state.appointmentId > 0;
            const path = direct ? '/appointments/pay' : '/payments/resume/pay';
            const payload = direct ? {
                appointment_id: state.lock.appointment_id,
                lock_token: state.lock.lock_token,
                method: state.selection.method,
                gateway: state.selection.gateway
            } : {
                resume_token: state.resumeToken,
                method: state.selection.method,
                gateway: state.selection.gateway
            };

            request(path, { method: 'POST', body: JSON.stringify(payload) }).then((result) => {
                if (result && result.checkout_url) {
                    clearResumeCountdown();
                    window.location.href = result.checkout_url;
                    return;
                }
                clearResumeCountdown();
                const receiptId = Number(result.appointment_id || (state.lock && state.lock.appointment_id) || state.appointmentId || (state.appointment && state.appointment.id) || 0);
                if (!receiptId) {
                    setMessage('نوبت قطعی شد.', 'success');
                    return;
                }
                return request(`/appointments/${receiptId}/receipt`).then((receipt) => {
                    checkout.innerHTML = receiptCardMarkup(receipt);
                    const print = checkout.querySelector('[data-print-current-receipt]');
                    print && print.addEventListener('click', () => printReceipt(receipt));
                    setMessage('پرداخت انجام شد و نوبت قطعی شد.', 'success');
                });
            }).catch((error) => {
                if (error.code === 'webtanan_invalid_lock' || error.code === 'webtanan_lock_expired') {
                    renderResumeExpired();
                    return;
                }
                submit.disabled = false;
                submit.classList.remove('is-loading');
                setMessage(error.message, 'error');
            });
        });

        loadOwnedAppointment();
    }

    function publicFlowMissing(el, label) {
        el.innerHTML = `<div class="wb-public-card wb-public-error">
            <span class="wb-kicker">${esc(label)}</span>
            <h1>لینک معتبر نیست</h1>
            <p>برای حفظ امنیت اطلاعات نوبت، لطفا از همان لینکی استفاده کنید که با پیامک دریافت کرده‌اید.</p>
        </div>`;
    }

    function publicAppointmentSummary(data) {
        return `<div class="wb-public-summary">
            <div><span>کد نوبت</span><strong>${esc(data.appointment_code || '-')}</strong></div>
            <div><span>تاریخ</span><strong>${esc(displayDate(data.date || cfg.today || '', false))}</strong></div>
            <div><span>ساعت</span><strong>${esc(data.time || '-')}</strong></div>
        </div>`;
    }

    function initWaitingList(el) {
        const code = el.dataset.code || '';
        const token = el.dataset.token || '';
        if (!code || !token) {
            publicFlowMissing(el, 'صف انتظار');
            return;
        }

        const load = () => {
            el.innerHTML = loadingPanel('در حال به‌روزرسانی جایگاه شما...');
            request(`/appointments/${encodeURIComponent(code)}/waiting-list?token=${encodeURIComponent(token)}`)
                .then((data) => {
                    const ahead = Number(data.ahead_count || 0);
                    const position = Number(data.queue_position || 1);
                    el.innerHTML = `<div class="wb-public-card wb-waiting-card">
                        <span class="wb-kicker">صف انتظار مطب</span>
                        <h1>جایگاه شما در صف</h1>
                        ${publicAppointmentSummary(data)}
                        <div class="wb-queue-hero">
                            <span>نوبت شما</span>
                            <strong>${position.toLocaleString('fa-IR')}</strong>
                            <small>${ahead > 0 ? `${ahead.toLocaleString('fa-IR')} نفر جلوتر از شما هستند` : 'شما نفر بعدی هستید'}</small>
                        </div>
                        <div class="wb-public-metrics">
                            <div><span>تعداد حاضر در صف</span><strong>${Number(data.total_waiting || position).toLocaleString('fa-IR')}</strong></div>
                            <div><span>زمان تقریبی</span><strong>${esc(data.estimated_time || 'در حال محاسبه')}</strong></div>
                            <div><span>وضعیت</span>${badge(statusLabel(data.status) || 'در جریان', data.status || 'info')}</div>
                        </div>
                        <p class="wb-public-hint">این صفحه به‌صورت خودکار به‌روز می‌شود. لطفا نزدیک مطب آماده باشید.</p>
                    </div>`;
                })
                .catch((error) => {
                    el.innerHTML = `<div class="wb-public-card wb-public-error"><h1>امکان نمایش صف نیست</h1><p>${esc(error.message)}</p></div>`;
                });
        };

        load();
        window.setInterval(() => {
            if (!document.hidden && document.body.contains(el)) {
                load();
            }
        }, 30000);
    }

    function initSurvey(el) {
        const code = el.dataset.code || '';
        const token = el.dataset.token || '';
        if (!code || !token) {
            publicFlowMissing(el, 'نظرسنجی');
            return;
        }

        const renderForm = (data) => {
            el.setAttribute('aria-busy', 'false');
            el.innerHTML = `<div class="wb-public-card wb-survey-card">
                <span class="wb-kicker">نظرسنجی نوبت</span>
                <h1>تجربه مراجعه چطور بود؟</h1>
                <p>${esc(data.message || 'نظر شما به بهتر شدن کیفیت نوبت‌دهی کمک می‌کند.')}</p>
                ${data.doctor_name ? `<div class="wb-public-doctor-name">${esc(data.doctor_name)}</div>` : ''}
                ${publicAppointmentSummary(data)}
                ${data.submitted ? '<div class="wb-survey-saved-note"><i class="fas fa-check-circle" aria-hidden="true"></i><span>نظر شما قبلاً ثبت شده است؛ در صورت نیاز می‌توانید آن را به‌روزرسانی کنید.</span></div>' : ''}
                <form class="wb-survey-form">
                    <fieldset class="wb-rating-picker">
                        <legend>امتیاز شما</legend>
                        ${[5, 4, 3, 2, 1].map((rate) => `<label><input type="radio" name="rating" value="${rate}" ${rate === Number(data.rating || 5) ? 'checked' : ''}><span>${'★'.repeat(rate)}</span></label>`).join('')}
                    </fieldset>
                    <label class="wb-field-full">
                        <span>توضیحات شما</span>
                        <textarea name="feedback" rows="5" placeholder="اگر دوست دارید، تجربه خود را کوتاه بنویسید.">${esc(data.feedback || '')}</textarea>
                    </label>
                    <label class="wb-checkbox-line">
                        <input type="checkbox" name="public_consent" ${data.public_consent !== false ? 'checked' : ''}>
                        <span>در صورت تایید مدیر، نظر من بدون نمایش اطلاعات تماس در صفحه پزشک نمایش داده شود.</span>
                    </label>
                    <button type="submit" class="wb-btn wb-btn-primary">ثبت نظر</button>
                </form>
                <div class="wb-public-message" aria-live="polite"></div>
            </div>`;

            const form = el.querySelector('.wb-survey-form');
            const message = el.querySelector('.wb-public-message');
            form && form.addEventListener('submit', (event) => {
                event.preventDefault();
                const submit = form.querySelector('[type="submit"]');
                submit.disabled = true;
                message.innerHTML = 'در حال ثبت نظر...';
                const formData = new FormData(form);
                request(`/appointments/${encodeURIComponent(code)}/survey?token=${encodeURIComponent(token)}`, {
                    method: 'POST',
                    body: JSON.stringify({
                        rating: Number(formData.get('rating') || 5),
                        feedback: formData.get('feedback') || '',
                        public_consent: formData.get('public_consent') === 'on'
                    })
                }).then((result) => {
                    el.setAttribute('aria-busy', 'false');
                    el.innerHTML = `<div class="wb-public-card wb-public-success">
                        <span class="wb-kicker">نظر ثبت شد</span>
                        <h1>ممنون از همراهی شما</h1>
                        <p>${result.status === 'pending' ? 'نظر شما ثبت شد و پس از بررسی مدیر منتشر می‌شود.' : 'نظر شما به‌صورت خصوصی برای مدیریت کیفیت ذخیره شد.'}</p>
                    </div>`;
                }).catch((error) => {
                    submit.disabled = false;
                    message.innerHTML = `<span class="wb-text-danger">${esc(error.message)}</span>`;
                });
            });
        };

        el.innerHTML = loadingPanel('در حال آماده‌سازی فرم نظرسنجی...');
        request(`/appointments/${encodeURIComponent(code)}/survey?token=${encodeURIComponent(token)}`)
            .then(renderForm)
            .catch((error) => {
                el.setAttribute('aria-busy', 'false');
                el.innerHTML = `<div class="wb-public-card wb-public-error"><h1>لینک نظرسنجی معتبر نیست</h1><p>${esc(error.message)}</p></div>`;
            });
    }

    function initSampleShell() {
        const closeSidebar = (shell) => {
            if (!shell) {
                return;
            }
            const sidebar = shell.querySelector('.sidebar.open');
            sidebar && sidebar.classList.remove('open');
            shell.classList.remove('is-menu-open');
            const toggle = shell.querySelector('.wb-sample-sidebar-toggle');
            toggle && toggle.setAttribute('aria-expanded', 'false');
        };

        document.addEventListener('click', (event) => {
            const toggle = event.target.closest('.wb-sample-sidebar-toggle');
            if (toggle) {
                const shell = toggle.closest('.sample-dashboard-shell');
                if (!shell) {
                    return;
                }
                const sidebar = shell.querySelector('.sidebar');
                if (sidebar) {
                    sidebar.classList.toggle('open');
                    shell.classList.toggle('is-menu-open', sidebar.classList.contains('open'));
                    toggle.setAttribute('aria-expanded', sidebar.classList.contains('open') ? 'true' : 'false');
                    let backdrop = shell.querySelector('.wb-sidebar-backdrop');
                    if (!backdrop) {
                        backdrop = document.createElement('button');
                        backdrop.type = 'button';
                        backdrop.className = 'wb-sidebar-backdrop';
                        backdrop.setAttribute('aria-label', 'بستن منو');
                        shell.appendChild(backdrop);
                    }
                }
                return;
            }

            const shell = event.target.closest('.sample-dashboard-shell');
            if (!shell || window.innerWidth > 768) {
                return;
            }

            const sidebar = shell.querySelector('.sidebar.open');
            if (sidebar && (event.target.closest('.wb-sidebar-backdrop') || (!event.target.closest('.sidebar') && !event.target.closest('.hamburger')))) {
                closeSidebar(shell);
            } else if (sidebar && event.target.closest('.wb-nav-item')) {
                closeSidebar(shell);
            }
        });

        window.addEventListener('resize', () => {
            if (window.innerWidth > 768) {
                document.querySelectorAll('.sample-dashboard-shell.is-menu-open').forEach(closeSidebar);
            }
        }, { passive: true });

        document.addEventListener('keydown', (event) => {
            if (event.key !== 'Escape') {
                return;
            }
            const shell = document.querySelector('.sample-dashboard-shell.is-menu-open');
            if (shell) {
                closeSidebar(shell);
                const toggle = shell.querySelector('.wb-sample-sidebar-toggle');
                toggle && toggle.focus();
            }
        });
    }

    function initFavoriteDoctorButton(button) {
        const doctorId = Number(button.dataset.doctorId || 0);
        if (!doctorId || button.dataset.favoriteReady === '1') {
            return;
        }
        button.dataset.favoriteReady = '1';
        const icon = button.querySelector('i');
        const label = button.querySelector('span');

        const paint = (favorite) => {
            button.dataset.favorite = favorite ? 'true' : 'false';
            button.setAttribute('aria-pressed', favorite ? 'true' : 'false');
            icon && (icon.className = `${favorite ? 'fas' : 'far'} fa-heart`);
            label && (label.textContent = favorite ? 'حذف از پزشکان منتخب' : 'افزودن به پزشکان منتخب');
        };

        if (cfg.isLoggedIn) {
            request('/patient-panel/favorites')
                .then((result) => paint(Array.isArray(result.ids) && result.ids.map(Number).includes(doctorId)))
                .catch(() => paint(false));
        }

        button.addEventListener('click', () => {
            if (!cfg.isLoggedIn) {
                window.location.href = cfg.loginUrl || '/';
                return;
            }
            button.disabled = true;
            const favorite = button.dataset.favorite !== 'true';
            request(`/patient-panel/favorites/${doctorId}`, {
                method: 'POST',
                body: JSON.stringify({ favorite })
            }).then((result) => {
                paint(!!result.favorite);
                toast(result.favorite ? 'پزشک به فهرست منتخب اضافه شد.' : 'پزشک از فهرست منتخب حذف شد.', 'success');
            }).catch((error) => toast(error.message, 'error')).finally(() => {
                button.disabled = false;
            });
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        initSampleShell();
        enhanceOtpInputs(document);
        const otpObserver = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => mutation.addedNodes.forEach((node) => {
                if (node.nodeType === 1) {
                    enhanceOtpInputs(node);
                }
            }));
        });
        otpObserver.observe(document.body, { childList: true, subtree: true });
        document.querySelectorAll('[data-webtanan-widget="auth"]').forEach(initAuth);
        document.querySelectorAll('[data-webtanan-widget="doctor-list"]').forEach((el) => initDoctorList(el));
        document.querySelectorAll('[data-webtanan-widget="doctor-search"]').forEach(initDoctorSearch);
        document.querySelectorAll('[data-webtanan-widget="calendar"]').forEach(initCalendar);
        document.querySelectorAll('[data-webtanan-widget="booking-modal"]').forEach(initBookingModal);
        document.querySelectorAll('[data-webtanan-widget="next-available"]').forEach(initNextAvailable);
        document.querySelectorAll('[data-webtanan-widget="patient-panel"]').forEach(initPatientPanel);
        document.querySelectorAll('[data-webtanan-widget="doctor-dashboard"]').forEach(initDoctorDashboard);
        document.querySelectorAll('[data-webtanan-widget="resume-payment"]').forEach(initResumePayment);
        document.querySelectorAll('[data-webtanan-widget="waiting-list"]').forEach(initWaitingList);
        document.querySelectorAll('[data-webtanan-widget="survey"]').forEach(initSurvey);
        document.querySelectorAll('[data-webtanan-favorite-doctor]').forEach(initFavoriteDoctorButton);
    });
}());
