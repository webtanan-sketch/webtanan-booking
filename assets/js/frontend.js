(function () {
    'use strict';

    const cfg = window.WebtananBooking || {};
    const apiBase = cfg.restUrl || '/wp-json/saas/v1';

    function request(path, options = {}) {
        const headers = Object.assign({
            'Content-Type': 'application/json',
            'X-WP-Nonce': cfg.nonce || ''
        }, options.headers || {});

        return fetch(apiBase + path, Object.assign({}, options, { headers }))
            .then((response) => response.json().then((body) => {
                if (!response.ok) {
                    const message = body && body.message ? body.message : (cfg.strings && cfg.strings.error) || 'خطایی رخ داد. لطفاً دوباره تلاش کنید.';
                    throw new Error(message);
                }
                return body;
            }));
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

    function formObject(form) {
        const data = new FormData(form);
        const out = {};
        data.forEach((value, key) => {
            out[key] = value;
        });
        return out;
    }

    function panel(message) {
        return `<div class="webtanan-panel">${esc(message)}</div>`;
    }

    const statusLabels = {
        available: 'آزاد',
        locked: 'در حال رزرو',
        booked: 'رزروشده',
        pending: 'در انتظار',
        confirmed: 'تاییدشده',
        cancelled: 'لغوشده',
        expired: 'منقضی',
        completed: 'مراجعه کرد',
        no_show: 'مراجعه نکرد',
        pay_at_clinic: 'پرداخت در مطب',
        unpaid: 'پرداخت‌نشده',
        paid: 'پرداخت آنلاین',
        failed: 'ناموفق',
        refunded_to_wallet: 'برگشت به کیف پول',
        cash_at_clinic: 'نقدی',
        pos_at_clinic: 'کارت‌خوان',
        wallet_paid: 'کیف پول'
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
        cancelled: 'لغوشده'
    };

    function statusLabel(status) {
        return statusLabels[status] || status || '-';
    }

    function ledgerLabel(type) {
        return ledgerLabels[type] || type || '-';
    }

    function settlementLabel(status) {
        return settlementLabels[status] || status || '-';
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

    function doctorCard(doctor) {
        const title = doctor.title || doctor.clinic_name || 'پزشک';
        const image = doctor.thumbnail
            ? `<img src="${esc(doctor.thumbnail)}" alt="${esc(title)}">`
            : `<span>${esc(title.charAt(0))}</span>`;
        const visitPrice = Number(doctor.display_visit_price || doctor.visit_price || 0);
        const badges = [
            doctor.is_verified ? 'تاییدشده' : '',
            doctor.allow_online_payment ? 'پرداخت آنلاین' : '',
            doctor.allow_pay_at_clinic ? 'پرداخت در مطب' : ''
        ].filter(Boolean).map((label) => `<span>${esc(label)}</span>`).join('');

        return `<article class="webtanan-public-doctor-card webtanan-public-doctor-card-compact">
            <a class="webtanan-public-doctor-photo" href="${esc(doctor.permalink || '#')}">${image}</a>
            <div class="webtanan-public-doctor-body">
                <div class="webtanan-doctor-badges">${badges}</div>
                <h2><a href="${esc(doctor.permalink || '#')}">${esc(title)}</a></h2>
                ${doctor.specialty_name ? `<p class="webtanan-meta">${esc(doctor.specialty_name)}</p>` : ''}
                ${doctor.clinic_address ? `<p class="webtanan-meta">${esc(doctor.clinic_address)}</p>` : ''}
                <div class="webtanan-public-fees">
                    <span>هزینه نوبت‌دهی: <strong>${money(doctor.booking_fee)}</strong> تومان</span>
                    ${visitPrice > 0 ? `<span>ویزیت اعلامی: ${money(visitPrice)} تومان</span>` : ''}
                </div>
                <div class="webtanan-public-actions">
                    <a class="webtanan-button webtanan-button-primary" href="${esc(doctor.permalink || '#')}">مشاهده و دریافت نوبت</a>
                    <div class="webtanan-next-available" data-webtanan-widget="next-available" data-doctor-id="${esc(doctor.id || 0)}"></div>
                </div>
            </div>
        </article>`;
    }

    function initDoctorList(el, search = '') {
        const perPage = el.dataset.perPage || '12';
        const specialtyId = el.dataset.specialtyId || '';
        const cityId = el.dataset.cityId || '';
        const provinceId = el.dataset.provinceId || '';
        const paymentFilter = el.dataset.paymentFilter || '';
        const sort = el.dataset.sort || '';
        const online = el.dataset.online || '';
        const payAtClinic = el.dataset.payAtClinic || '';
        el.innerHTML = panel(cfg.strings && cfg.strings.loading || 'در حال بارگذاری...');
        request(`/doctors?${qs({ per_page: perPage, search, specialty_id: specialtyId, city_id: cityId, province_id: provinceId, payment_filter: paymentFilter, sort, online, pay_at_clinic: payAtClinic })}`)
            .then((doctors) => {
                if (!Array.isArray(doctors) || !doctors.length) {
                    el.innerHTML = panel('پزشکی برای نمایش پیدا نشد.');
                    return;
                }

                el.innerHTML = `<div class="webtanan-doctor-grid">${doctors.map(doctorCard).join('')}</div>`;
                el.querySelectorAll('[data-webtanan-widget="next-available"]').forEach(initNextAvailable);
            })
            .catch((error) => {
                el.innerHTML = panel(error.message);
            });
    }

    function initDoctorSearch(el) {
        const input = el.querySelector('.webtanan-doctor-search-input');
        const button = el.querySelector('.webtanan-search-button');
        const specialty = el.querySelector('.webtanan-doctor-specialty-filter');
        const payment = el.querySelector('.webtanan-doctor-payment-filter');
        const sort = el.querySelector('.webtanan-doctor-sort-filter');
        const results = el.querySelector('.webtanan-doctor-results');
        if (results && el.dataset.perPage) {
            results.dataset.perPage = el.dataset.perPage;
        }
        const syncFilters = () => {
            if (!results) {
                return;
            }

            results.dataset.specialtyId = specialty ? specialty.value : (el.dataset.specialtyId || '');
            results.dataset.cityId = el.dataset.cityId || '';
            results.dataset.provinceId = el.dataset.provinceId || '';
            results.dataset.paymentFilter = payment ? payment.value : (el.dataset.paymentFilter || '');
            results.dataset.sort = sort ? sort.value : (el.dataset.sort || '');
        };
        const run = () => {
            syncFilters();
            initDoctorList(results, input ? input.value : '');
        };
        button && button.addEventListener('click', run);
        input && input.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
                event.preventDefault();
                run();
            }
        });
        [specialty, payment, sort].forEach((field) => {
            field && field.addEventListener('change', run);
        });
        run();
    }

    function initAuth(el) {
        const mobileForm = el.querySelector('.webtanan-auth-mobile');
        const otpForm = el.querySelector('.webtanan-auth-otp');
        const backButton = el.querySelector('.webtanan-auth-back');
        const message = el.querySelector('.webtanan-auth-message');
        let mobile = '';

        if (cfg.isLoggedIn) {
            el.innerHTML = `<div class="webtanan-panel"><strong>وارد حساب شده‌اید.</strong><br><button type="button" class="webtanan-button webtanan-auth-logout">خروج از حساب</button></div>`;
            el.querySelector('.webtanan-auth-logout').addEventListener('click', () => {
                request('/auth/logout', { method: 'POST', body: '{}' }).then(() => window.location.reload());
            });
            return;
        }

        mobileForm.addEventListener('submit', (event) => {
            event.preventDefault();
            mobile = new FormData(mobileForm).get('mobile') || '';
            message.textContent = cfg.strings && cfg.strings.loading || 'در حال بارگذاری...';
            request('/auth/send-otp', {
                method: 'POST',
                body: JSON.stringify({ mobile, purpose: 'login' })
            }).then((result) => {
                mobileForm.hidden = true;
                otpForm.hidden = false;
                const debug = result.debug_otp ? ` کد تست: ${result.debug_otp}` : '';
                message.textContent = `کد ورود ارسال شد.${debug}`;
            }).catch((error) => {
                message.textContent = error.message;
            });
        });

        otpForm.addEventListener('submit', (event) => {
            event.preventDefault();
            const otp = new FormData(otpForm).get('otp') || '';
            message.textContent = cfg.strings && cfg.strings.loading || 'در حال بارگذاری...';
            request('/auth/verify-otp', {
                method: 'POST',
                body: JSON.stringify({ mobile, otp, purpose: 'login' })
            }).then((result) => {
                if (result.nonce) {
                    cfg.nonce = result.nonce;
                }
                message.textContent = 'ورود با موفقیت انجام شد.';
                window.location.reload();
            }).catch((error) => {
                message.textContent = error.message;
            });
        });

        backButton && backButton.addEventListener('click', () => {
            otpForm.hidden = true;
            mobileForm.hidden = false;
            message.textContent = '';
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
                        slotsWrap.innerHTML = panel(cfg.strings && cfg.strings.noSlots || 'نوبت آزادی پیدا نشد.');
                        return;
                    }
                    slotsWrap.innerHTML = `<div class="webtanan-slot-date-title">${esc(displayDate(dateInput.value || cfg.today))}</div>
                        <div class="webtanan-slot-legend">
                            <span data-status="available">آزاد</span>
                            <span data-status="locked">در حال رزرو</span>
                            <span data-status="booked">رزروشده</span>
                        </div>` + slots.map((slot) => {
                        const disabled = slot.status !== 'available' ? 'disabled' : '';
                        return `<button type="button" class="webtanan-slot" data-status="${esc(slot.status)}" data-date="${esc(slot.date)}" data-start="${esc(slot.start_time)}" ${disabled}><strong>${esc(slot.start_time)}</strong><span>${esc(statusLabel(slot.status))}</span></button>`;
                    }).join('');
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

                    gatewaySelect.innerHTML = gateways.map((gateway) => `<option value="${esc(gateway.id)}">${esc(gateway.title)}${gateway.sandbox ? ' - Sandbox' : ''}</option>`).join('');
                    return gateways;
                })
                .catch((error) => {
                    gateways = [];
                    gatewaySelect.innerHTML = `<option value="">${esc(error.message)}</option>`;
                    return gateways;
                });
        }

        slotsWrap.addEventListener('click', (event) => {
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

                message.innerHTML = `نوبت قفل شد اما لینک پرداخت دریافت نشد.<br><code>${esc(payment && payment.transaction_code || '')}</code>`;
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
        const patientForm = modal.querySelector('.webtanan-booking-modal-patient');
        const otpForm = modal.querySelector('.webtanan-booking-modal-otp');
        const paymentEl = modal.querySelector('.webtanan-booking-modal-payment');
        const messageEl = modal.querySelector('.webtanan-booking-modal-message');
        const resendOtp = modal.querySelector('.webtanan-booking-resend-otp');
        const state = {
            date: cfg.today,
            selectedSlot: null,
            patient: {},
            lock: null,
            otpSent: false,
            gateways: [],
            walletBalance: null
        };

        if (!doctorId || doctorId === '0') {
            return;
        }

        function setMessage(message, tone = '') {
            messageEl.textContent = message || '';
            messageEl.dataset.tone = tone;
        }

        function setStep(step) {
            stepsEl.innerHTML = ['day', 'patient', 'otp', 'payment'].map((item) => {
                const labels = { day: 'انتخاب زمان', patient: 'اطلاعات بیمار', otp: 'ورود با موبایل', payment: 'پرداخت' };
                return `<span data-active="${item === step ? 'true' : 'false'}">${esc(labels[item])}</span>`;
            }).join('');
        }

        function openModal() {
            modal.hidden = false;
            document.documentElement.classList.add('webtanan-modal-open');
            setStep('day');
            renderDays();
            loadSlots(state.date);
            setTimeout(() => panelEl && panelEl.focus && panelEl.focus(), 30);
        }

        function closeModal() {
            modal.hidden = true;
            document.documentElement.classList.remove('webtanan-modal-open');
        }

        function renderDays() {
            const days = Array.from({ length: 14 }, (_, index) => addDaysISO(cfg.today, index));
            dayStrip.innerHTML = days.map((date, index) => {
                const active = date === state.date;
                const label = displayDate(date, false);
                const weekday = displayDate(date, true).replace(label, '').trim();
                return `<button type="button" class="webtanan-booking-day" data-date="${esc(date)}" data-active="${active ? 'true' : 'false'}">
                    <span>${index === 0 ? 'امروز' : esc(weekday || label.split(' ')[0] || '')}</span>
                    <strong>${esc(label)}</strong>
                </button>`;
            }).join('');
        }

        function resetAfterDateChange() {
            state.selectedSlot = null;
            state.lock = null;
            state.otpSent = false;
            patientForm.hidden = true;
            otpForm.hidden = true;
            paymentEl.hidden = true;
            paymentEl.innerHTML = '';
            setMessage('');
        }

        function loadSlots(date) {
            resetAfterDateChange();
            setStep('day');
            slotsEl.innerHTML = panel(cfg.strings && cfg.strings.loading || 'در حال بارگذاری...');
            request(`/doctors/${doctorId}/slots?date=${encodeURIComponent(date)}`)
                .then((slots) => {
                    if (!Array.isArray(slots) || !slots.length) {
                        slotsEl.innerHTML = panel(cfg.strings && cfg.strings.noSlots || 'نوبت آزادی پیدا نشد.');
                        return;
                    }

                    slotsEl.innerHTML = `<div class="webtanan-booking-modal-legend">
                        <span data-status="available">آزاد</span>
                        <span data-status="locked">در حال رزرو</span>
                        <span data-status="booked">رزروشده</span>
                    </div>
                    <div class="webtanan-booking-modal-slot-grid">${slots.map((slot) => {
                        const disabled = slot.status !== 'available' ? 'disabled' : '';
                        return `<button type="button" class="webtanan-booking-modal-slot" data-status="${esc(slot.status)}" data-date="${esc(slot.date)}" data-start="${esc(slot.start_time)}" ${disabled}>
                            <strong>${esc(slot.start_time)}</strong>
                            <span>${esc(statusLabel(slot.status))}</span>
                        </button>`;
                    }).join('')}</div>`;
                })
                .catch((error) => {
                    slotsEl.innerHTML = panel(error.message);
                });
        }

        function showPatientStep() {
            setStep('patient');
            patientForm.hidden = false;
            otpForm.hidden = true;
            paymentEl.hidden = true;
            setMessage('این زمان پس از ثبت اطلاعات برای ۱۵ دقیقه قفل می‌شود.');
            const first = patientForm.querySelector('input');
            first && first.focus();
        }

        function lockSelectedSlot() {
            const payload = Object.assign({}, state.patient, {
                doctor_id: doctorId,
                appointment_date: state.selectedSlot.date,
                start_time: state.selectedSlot.start,
                payment_method: 'online'
            });
            setMessage('در حال قفل کردن نوبت...');
            return request('/appointments/lock', {
                method: 'POST',
                body: JSON.stringify(payload)
            }).then((lock) => {
                state.lock = lock;
                setMessage(`نوبت تا ${lock.locked_until || '۱۵ دقیقه آینده'} برای شما فریز شد.`, 'success');
                return lock;
            });
        }

        function sendOtp() {
            if (!state.patient.patient_mobile) {
                return Promise.reject(new Error('شماره موبایل وارد نشده است.'));
            }
            setStep('otp');
            patientForm.hidden = true;
            paymentEl.hidden = true;
            otpForm.hidden = false;
            otpForm.querySelector('p').textContent = `کد ورود برای ${state.patient.patient_mobile} ارسال می‌شود. نوبت lock شده حفظ می‌شود.`;
            setMessage('در حال ارسال کد ورود...');
            return request('/auth/send-otp', {
                method: 'POST',
                body: JSON.stringify({ mobile: state.patient.patient_mobile, purpose: 'login' })
            }).then((result) => {
                state.otpSent = true;
                const debug = result.debug_otp ? ` کد تست: ${result.debug_otp}` : '';
                setMessage(`کد ورود ارسال شد.${debug}`, 'success');
                const input = otpForm.querySelector('input[name="otp"]');
                input && input.focus();
            });
        }

        function verifyOtp() {
            const otp = new FormData(otpForm).get('otp') || '';
            setMessage('در حال تایید کد ورود...');
            return request('/auth/verify-otp', {
                method: 'POST',
                body: JSON.stringify({ mobile: state.patient.patient_mobile, otp, purpose: 'login' })
            }).then((result) => {
                if (result.nonce) {
                    cfg.nonce = result.nonce;
                }
                cfg.isLoggedIn = true;
                setMessage('ورود انجام شد. نوبت فریز شده آماده پرداخت است.', 'success');
                return showPaymentStep();
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

        function showPaymentStep() {
            setStep('payment');
            patientForm.hidden = true;
            otpForm.hidden = true;
            paymentEl.hidden = false;
            paymentEl.innerHTML = panel(cfg.strings && cfg.strings.loading || 'در حال بارگذاری...');
            return loadPaymentData().then(() => {
                const amount = Number(state.lock && state.lock.amount || 0);
                const walletDisabled = state.walletBalance < amount ? 'disabled' : '';
                const walletText = state.walletBalance < amount ? 'موجودی کافی نیست' : 'پرداخت از کیف پول';
                const gateways = state.gateways.map((gateway) => `<button type="button" class="webtanan-payment-option" data-method="online" data-gateway="${esc(gateway.id)}">
                    <strong>${esc(gateway.title || gateway.id)}</strong>
                    <span>${gateway.sandbox ? 'حالت تست' : 'درگاه آنلاین'}</span>
                </button>`).join('');

                paymentEl.innerHTML = `<div class="webtanan-payment-summary">
                    <span>مبلغ قابل پرداخت</span>
                    <strong>${money(amount)} تومان</strong>
                    <small>زمان انتخابی: ${esc(displayDate(state.selectedSlot.date, false))} ساعت ${esc(state.selectedSlot.start)}</small>
                </div>
                <div class="webtanan-payment-options">
                    <button type="button" class="webtanan-payment-option" data-method="wallet" ${walletDisabled}>
                        <strong>${walletText}</strong>
                        <span>موجودی: ${money(state.walletBalance)} تومان</span>
                    </button>
                    ${gateways || '<div class="webtanan-panel">درگاه آنلاینی فعال نیست.</div>'}
                </div>`;
            });
        }

        function pay(method, gateway = '') {
            if (!state.lock) {
                return;
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
                paymentEl.innerHTML = `<div class="webtanan-booking-success"><strong>نوبت با موفقیت ثبت شد.</strong><span>کد نوبت: ${esc(result && (result.appointment_code || result.appointment_id) || '')}</span></div>`;
                setMessage('رزرو تکمیل شد.', 'success');
            }).catch((error) => {
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
            const slot = event.target.closest('.webtanan-booking-modal-slot');
            if (slot && slot.dataset.status === 'available') {
                modal.querySelectorAll('.webtanan-booking-modal-slot').forEach((item) => {
                    item.dataset.selected = 'false';
                });
                slot.dataset.selected = 'true';
                state.selectedSlot = { date: slot.dataset.date, start: slot.dataset.start };
                showPatientStep();
                return;
            }
            const paymentOption = event.target.closest('.webtanan-payment-option');
            if (paymentOption && !paymentOption.disabled) {
                pay(paymentOption.dataset.method, paymentOption.dataset.gateway || '');
            }
        });

        patientForm.addEventListener('submit', (event) => {
            event.preventDefault();
            if (!state.selectedSlot) {
                setMessage('ابتدا یک ساعت آزاد انتخاب کنید.', 'error');
                return;
            }
            state.patient = formObject(patientForm);
            lockSelectedSlot()
                .then(() => (cfg.isLoggedIn ? showPaymentStep() : sendOtp()))
                .catch((error) => setMessage(error.message, 'error'));
        });

        otpForm.addEventListener('submit', (event) => {
            event.preventDefault();
            verifyOtp().catch((error) => setMessage(error.message, 'error'));
        });

        resendOtp && resendOtp.addEventListener('click', () => {
            sendOtp().catch((error) => setMessage(error.message, 'error'));
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
                el.innerHTML = `<div class="webtanan-next-card"><span>اولین نوبت آزاد</span><strong>${slots.map((slot) => `${esc(displayDate(slot.date, false))} - ${esc(slot.start_time)}`).join('، ')}</strong></div>`;
            })
            .catch((error) => {
                el.innerHTML = panel(error.message);
            });
    }

    function badge(value, type) {
        return `<span class="wb-badge wb-badge-${esc(type || value)}">${esc(value)}</span>`;
    }

    function confirmModal(options) {
        return new Promise((resolve) => {
            const overlay = document.createElement('div');
            overlay.className = 'wb-confirm-overlay';
            overlay.dir = 'rtl';
            overlay.innerHTML = `<div class="wb-confirm-modal" role="dialog" aria-modal="true">
                <h3>${esc(options.title || 'تایید عملیات')}</h3>
                <p>${esc(options.message || 'آیا از انجام این عملیات مطمئن هستید؟')}</p>
                ${options.reason ? `<label><span>${esc(options.reason)}</span><textarea rows="3"></textarea></label>` : ''}
                <div class="wb-confirm-actions">
                    <button type="button" class="wb-button wb-confirm-cancel">انصراف</button>
                    <button type="button" class="wb-button ${options.danger ? 'wb-button-danger' : 'wb-button-primary'} wb-confirm-ok">${esc(options.confirmText || 'تایید')}</button>
                </div>
            </div>`;
            document.body.appendChild(overlay);
            const close = (result) => {
                overlay.remove();
                resolve(result);
            };
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
            return `<button type="button" class="wb-table-action" data-action="receipt" data-id="${item.id}">رسید</button>${cancel}`;
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

        return `<button type="button" class="wb-table-action" data-action="receipt" data-id="${item.id}">رسید</button>${paymentButtons}${attendanceButtons}${cancel}`;
    }

    function appointmentsTable(items, mode = 'staff') {
        if (!items.length) {
            return panel('موردی برای نمایش وجود ندارد.');
        }
        return `<div class="wb-table-wrap">
            <table class="wb-table">
                <thead>
                    <tr>
                        <th>زمان</th>
                        <th>بیمار</th>
                        <th>کد ملی</th>
                        <th>پرداخت</th>
                        <th>وضعیت</th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    ${items.map((item) => `<tr>
                        <td><strong>${esc(displayDate(item.appointment_date, false))}</strong><br>${esc(item.start_time)}</td>
                        <td>${esc(item.patient_full_name || '-')}<br><span>${esc(item.patient_mobile || '')}</span></td>
                        <td>${esc(item.patient_national_code || '-')}</td>
                        <td>${badge(item.payment_label || item.payment_status, item.payment_status)}</td>
                        <td>${badge(item.appointment_label || item.appointment_status, item.appointment_status)}</td>
                        <td class="wb-actions">${appointmentActions(item, mode)}</td>
                    </tr>`).join('')}
                </tbody>
            </table>
        </div>`;
    }

    function statCard(label, value, suffix = '') {
        return `<div class="wb-stat">
            <div class="wb-stat-label">${esc(label)}</div>
            <div class="wb-stat-value">${esc(value)}${suffix ? `<span>${esc(suffix)}</span>` : ''}</div>
        </div>`;
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
        const state = { doctorId: 0, view: 'today', date: cfg.today || new Date().toISOString().slice(0, 10), calendarMode: 'day', context: null };

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
            return `<div class="wb-page-head"><div><h2>${esc(title)}</h2>${subtitle ? `<p>${esc(subtitle)}</p>` : ''}</div></div>`;
        }

        function renderToday() {
            setLoading();
            const search = '';
            Promise.all([
                request(`/doctor-dashboard/summary?${doctorQuery({ date: state.date })}`),
                request(`/doctor-dashboard/appointments?${doctorQuery({ date: state.date, search })}`)
            ]).then(([summary, items]) => {
                content.innerHTML = `${renderTitle('پیش‌خوان امروز', 'خلاصه وضعیت مطب در روز انتخاب‌شده')}
                    <div class="wb-filterbar">
                        <input type="date" class="wb-dashboard-date" value="${esc(state.date)}">
                        <input type="search" class="wb-appointment-search" placeholder="جستجوی نام، موبایل یا کد ملی">
                        <button type="button" class="wb-button wb-refresh-today">نمایش</button>
                    </div>
                    <div class="wb-stats-grid">
                        ${statCard('نوبت‌های روز', money(summary.appointments_today), 'نفر')}
                        ${statCard('ویزیت شده', money(summary.completed_today), 'نفر')}
                        ${statCard('درآمد روز', summary.revenue_today == null ? 'محدود' : money(summary.revenue_today), summary.revenue_today == null ? '' : 'تومان')}
                        ${statCard('موجودی کیف پول', summary.wallet_balance == null ? 'محدود' : money(summary.wallet_balance), summary.wallet_balance == null ? '' : 'تومان')}
                    </div>
                    <div class="wb-section-head"><h3>لیست نوبت‌ها</h3></div>
                    <div class="wb-appointments-table">${appointmentsTable(items)}</div>`;
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

        function renderCalendar() {
            content.innerHTML = `${renderTitle('تقویم نوبت‌دهی', 'برنامه نوبت‌ها در نمای روزانه، هفتگی یا ماهانه')}
                <div class="wb-filterbar">
                    <input type="date" class="wb-calendar-date" value="${esc(state.date)}">
                    <div class="wb-segmented" role="group" aria-label="نوع نمایش تقویم">
                        <button type="button" class="${state.calendarMode === 'day' ? 'is-active' : ''}" data-calendar-mode="day">روزانه</button>
                        <button type="button" class="${state.calendarMode === 'week' ? 'is-active' : ''}" data-calendar-mode="week">هفتگی</button>
                        <button type="button" class="${state.calendarMode === 'month' ? 'is-active' : ''}" data-calendar-mode="month">ماهانه</button>
                    </div>
                    <button type="button" class="wb-button wb-load-calendar">نمایش تقویم</button>
                    <button type="button" class="wb-button wb-calendar-today">امروز</button>
                </div>
                <div class="wb-calendar-legend">
                    <span data-status="available">آزاد</span>
                    <span data-status="locked">در حال رزرو</span>
                    <span data-status="confirmed">رزروشده</span>
                    <span data-status="pay_at_clinic">پرداخت در مطب</span>
                    <span data-status="completed">مراجعه کرد</span>
                    <span data-status="no_show">مراجعه نکرد</span>
                    <span data-status="cancelled">لغوشده</span>
                </div>
                <div class="wb-calendar-results">${panel('در حال بارگذاری')}</div>`;
            loadCalendar();
        }

        function loadCalendar() {
            const date = content.querySelector('.wb-calendar-date') ? content.querySelector('.wb-calendar-date').value : state.date;
            state.date = date;
            const results = content.querySelector('.wb-calendar-results');
            results.innerHTML = panel('در حال بارگذاری');
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
            return `<section class="wb-calendar-day">
                <header>
                    <strong>${esc(displayDate(date))}</strong>
                    <span>${money(available)} نوبت آزاد</span>
                </header>
                ${slots.length ? `<div class="wb-slot-grid">${slots.map((slot) => {
                    const detailed = slotDetailedStatus(slot);
                    return `<div class="wb-slot-card" data-status="${esc(detailed)}"><strong>${esc(slot.start_time)}</strong><span>${esc(statusLabel(detailed))}</span></div>`;
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
                    results.innerHTML = items.length ? `<div class="wb-table-wrap"><table class="wb-table"><thead><tr><th>بیمار</th><th>موبایل</th><th>کد ملی</th><th>تعداد نوبت</th><th>آخرین مراجعه</th></tr></thead><tbody>${items.map((item) => `<tr><td>${esc(`${item.patient_first_name || ''} ${item.patient_last_name || ''}`.trim() || '-')}</td><td>${esc(item.patient_mobile || '-')}</td><td>${esc(item.patient_national_code || '-')}</td><td>${money(item.appointment_count)}</td><td>${esc(item.last_visit_date || '-')}</td></tr>`).join('')}</tbody></table></div>` : panel('بیماری یافت نشد.');
                })
                .catch((error) => {
                    results.innerHTML = panel(error.message);
                });
        }

        function renderSchedule() {
            content.innerHTML = `${renderTitle('برنامه کاری', 'شیفت‌های فعال پزشک')}
                <form class="wb-inline-form wb-schedule-form">
                    <select name="weekday">
                        <option value="saturday">شنبه</option>
                        <option value="sunday">یکشنبه</option>
                        <option value="monday">دوشنبه</option>
                        <option value="tuesday">سه‌شنبه</option>
                        <option value="wednesday">چهارشنبه</option>
                        <option value="thursday">پنجشنبه</option>
                        <option value="friday">جمعه</option>
                    </select>
                    <input type="time" name="start_time" required>
                    <input type="time" name="end_time" required>
                    <input type="number" name="slot_duration" min="1" value="15" aria-label="مدت ویزیت">
                    <button type="submit" class="wb-button wb-button-primary">افزودن شیفت</button>
                </form>
                <div class="wb-schedule-results">${panel('در حال بارگذاری')}</div>`;
            loadSchedules();
        }

        function loadSchedules() {
            request(`/doctor-dashboard/schedules?${doctorQuery()}`)
                .then((items) => {
                    const target = content.querySelector('.wb-schedule-results');
                    target.innerHTML = items.length ? `<div class="wb-list">${items.map((item) => `<div class="wb-list-row"><strong>${esc(item.weekday)}</strong><span>${esc(item.start_time)} تا ${esc(item.end_time)}</span><span>${money(item.slot_duration)} دقیقه</span></div>`).join('')}</div>` : panel('برنامه کاری ثبت نشده است.');
                })
                .catch((error) => {
                    content.querySelector('.wb-schedule-results').innerHTML = panel(error.message);
                });
        }

        function renderExceptions() {
            content.innerHTML = `${renderTitle('مرخصی‌ها و استثنائات', 'تعطیلی یا شیفت ویژه')}
                <form class="wb-inline-form wb-exception-form">
                    <input type="date" name="exception_date" required>
                    <select name="type">
                        <option value="day_off">تعطیلی کامل</option>
                        <option value="extra_shift">شیفت اضافه</option>
                        <option value="custom_shift">شیفت جایگزین</option>
                        <option value="reduced_shift">شیفت کوتاه</option>
                    </select>
                    <input type="time" name="start_time">
                    <input type="time" name="end_time">
                    <input type="text" name="reason" placeholder="دلیل">
                    <button type="submit" class="wb-button wb-button-primary">ثبت</button>
                </form>
                <div class="wb-exception-results">${panel('در حال بارگذاری')}</div>`;
            loadExceptions();
        }

        function loadExceptions() {
            request(`/doctor-dashboard/exceptions?${doctorQuery({ from: cfg.today })}`)
                .then((items) => {
                    const target = content.querySelector('.wb-exception-results');
                    target.innerHTML = items.length ? `<div class="wb-list">${items.map((item) => `<div class="wb-list-row"><strong>${esc(item.exception_date)}</strong><span>${esc(item.type)}</span><span>${esc(item.reason || '')}</span></div>`).join('')}</div>` : panel('استثنایی ثبت نشده است.');
                })
                .catch((error) => {
                    content.querySelector('.wb-exception-results').innerHTML = panel(error.message);
                });
        }

        function renderWallet() {
            content.innerHTML = `${renderTitle('کیف پول و مالی', 'موجودی، دفتر کل و درخواست تسویه')}
                <div class="wb-wallet-results">${panel('در حال بارگذاری')}</div>`;
            Promise.all([
                request(`/doctor-dashboard/wallet?${doctorQuery()}`),
                request(`/doctor-dashboard/settlements?${doctorQuery()}`)
            ]).then(([wallet, settlements]) => {
                content.querySelector('.wb-wallet-results').innerHTML = `<div class="wb-stats-grid">
                        ${statCard('موجودی کل', money(wallet.total_balance != null ? wallet.total_balance : wallet.balance), 'تومان')}
                        ${statCard('موجودی قابل برداشت', money(wallet.available_balance != null ? wallet.available_balance : wallet.balance), 'تومان')}
                        ${statCard('در انتظار تسویه', money(wallet.pending_settlement || 0), 'تومان')}
                        ${statCard('بدهی کمیسیون حضوری', money(wallet.commission_debt || 0), 'تومان')}
                    </div>
                    <form class="wb-inline-form wb-settlement-form">
                        <input type="number" name="amount" min="1" placeholder="مبلغ تسویه">
                        <input type="text" name="iban" placeholder="شماره شبا">
                        <button type="submit" class="wb-button wb-button-primary">ثبت درخواست تسویه</button>
                    </form>
                    <div class="wb-section-head"><h3>دفتر کل</h3></div>
                    <div class="wb-list">${wallet.ledger.map((item) => `<div class="wb-list-row"><strong>${esc(ledgerLabel(item.entry_type))}</strong><span>${money(item.amount)} تومان</span><span>${esc(item.created_at)}</span></div>`).join('') || 'موردی ثبت نشده است.'}</div>
                    <div class="wb-section-head"><h3>درخواست‌های تسویه</h3></div>
                    <div class="wb-list">${settlements.map((item) => `<div class="wb-list-row"><strong>${money(item.amount)} تومان</strong><span>${esc(settlementLabel(item.status))}</span><span>${esc(item.bank_tracking_number || item.requested_at || item.created_at)}</span></div>`).join('') || 'درخواستی ثبت نشده است.'}</div>`;
            }).catch((error) => {
                content.querySelector('.wb-wallet-results').innerHTML = panel(error.message);
            });
        }

        function render() {
            if (state.view === 'calendar') {
                renderCalendar();
            } else if (state.view === 'patients') {
                renderPatients();
            } else if (state.view === 'schedule') {
                renderSchedule();
            } else if (state.view === 'exceptions') {
                renderExceptions();
            } else if (state.view === 'wallet') {
                renderWallet();
            } else {
                renderToday();
            }
        }

        function setView(view) {
            state.view = view;
            el.querySelectorAll('.wb-nav-item').forEach((button) => {
                button.classList.toggle('is-active', button.dataset.wbView === view);
            });
            render();
        }

        function openModal() {
            modal.hidden = false;
        }

        function closeModal() {
            modal.hidden = true;
            walkinForm.reset();
            const message = walkinForm.querySelector('.wb-form-message');
            if (message) {
                message.textContent = '';
            }
        }

        el.addEventListener('click', (event) => {
            const nav = event.target.closest('[data-wb-view]');
            if (nav && nav.closest('.wb-nav')) {
                setView(nav.dataset.wbView);
                return;
            }
            if (event.target.closest('.wb-open-walkin')) {
                openModal();
                return;
            }
            if (event.target.closest('.wb-close-modal')) {
                closeModal();
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
            if (event.target.closest('.wb-load-calendar')) {
                loadCalendar();
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
            if (event.target.closest('.wb-logout')) {
                request('/auth/logout', { method: 'POST', body: '{}' }).then(() => window.location.reload());
                return;
            }
            const action = event.target.closest('[data-action]');
            if (!action) {
                return;
            }
            const id = action.dataset.id;
            if (action.dataset.action === 'payment') {
                request(`/doctor-dashboard/appointments/${id}/payment`, {
                    method: 'POST',
                    body: JSON.stringify({ payment_status: action.dataset.status })
                }).then(reloadAppointments).catch((error) => alert(error.message));
            } else if (action.dataset.action === 'attendance') {
                request(`/doctor-dashboard/appointments/${id}/status`, {
                    method: 'POST',
                    body: JSON.stringify({ appointment_status: action.dataset.status })
                }).then(reloadAppointments).catch((error) => alert(error.message));
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
                    }).then(reloadAppointments).catch((error) => alert(error.message));
                });
                return;
            } else if (action.dataset.action === 'receipt') {
                openReceipt(id);
            }
        });

        content.addEventListener('submit', (event) => {
            if (event.target.matches('.wb-schedule-form')) {
                event.preventDefault();
                request(`/doctor-dashboard/schedules?${doctorQuery()}`, {
                    method: 'POST',
                    body: JSON.stringify(formObject(event.target))
                }).then(() => {
                    event.target.reset();
                    loadSchedules();
                }).catch((error) => alert(error.message));
            } else if (event.target.matches('.wb-exception-form')) {
                event.preventDefault();
                request(`/doctor-dashboard/exceptions?${doctorQuery()}`, {
                    method: 'POST',
                    body: JSON.stringify(formObject(event.target))
                }).then(() => {
                    event.target.reset();
                    loadExceptions();
                }).catch((error) => alert(error.message));
            } else if (event.target.matches('.wb-settlement-form')) {
                event.preventDefault();
                request(`/doctor-dashboard/settlement-request?${doctorQuery()}`, {
                    method: 'POST',
                    body: JSON.stringify(formObject(event.target))
                }).then(() => renderWallet()).catch((error) => alert(error.message));
            }
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
                doctorSelect.hidden = context.doctors.length < 2;
                render();
            })
            .catch((error) => {
                content.innerHTML = panel(error.message);
            });
    }

    function openReceipt(id) {
        request(`/appointments/${id}/receipt`)
            .then((receipt) => {
                const item = receipt.appointment;
                const doctor = receipt.doctor || {};
                const paymentAmount = item.payment_amount != null ? item.payment_amount : (item.booking_fee != null ? item.booking_fee : 0);
                const visitPrice = Number(item.display_visit_price || item.visit_price || 0);
                const visitPriceRow = visitPrice > 0 ? `<dt>تعرفه ویزیت اعلامی</dt><dd>${money(visitPrice)} تومان</dd>` : '';
                const popup = window.open('', '_blank', 'width=760,height=900');
                if (!popup) {
                    return;
                }
                popup.document.write(`<!doctype html><html dir="rtl" lang="fa"><head><meta charset="utf-8"><title>${esc(receipt.print_title || 'رسید')}</title><style>
                    body{font-family:Tahoma,Arial,sans-serif;margin:0;background:#f5f8f8;color:#1f2937;line-height:1.8}
                    .receipt{width:min(760px,calc(100% - 32px));margin:28px auto;background:#fff;border:1px solid #d9e2e1;border-radius:8px;overflow:hidden}
                    .head{padding:22px 24px;border-bottom:1px solid #edf2f1;background:#f8fafc}
                    h1{margin:0;font-size:24px}.code{color:#64748b;margin-top:4px}
                    dl{display:grid;grid-template-columns:180px 1fr;gap:0;margin:0;padding:12px 24px 22px}
                    dt,dd{padding:10px 0;border-bottom:1px solid #edf2f1}dt{color:#64748b}dd{margin:0;font-weight:700}
                    .note{margin:0;padding:14px 24px;color:#64748b;font-size:13px;background:#fff7ed;border-top:1px solid #fed7aa}
                    @media print{body{background:#fff}.receipt{margin:0 auto;border-color:#d9e2e1}}
                </style></head><body><main class="receipt"><div class="head"><h1>رسید نوبت</h1><div class="code">${esc(item.appointment_code || '')}</div></div><dl><dt>کد نوبت</dt><dd>${esc(item.appointment_code)}</dd><dt>نام پزشک</dt><dd>${esc(item.doctor_title || doctor.title || '')}</dd><dt>نام بیمار</dt><dd>${esc(item.patient_full_name)}</dd><dt>کد ملی</dt><dd>${esc(item.patient_national_code)}</dd><dt>موبایل</dt><dd>${esc(item.patient_mobile)}</dd><dt>تاریخ</dt><dd>${esc(displayDate(item.appointment_date, false))}</dd><dt>ساعت</dt><dd>${esc(item.start_time)}</dd><dt>هزینه خدمات نوبت‌دهی</dt><dd>${money(paymentAmount)} تومان</dd>${visitPriceRow}<dt>روش پرداخت</dt><dd>${esc(item.payment_label)}</dd><dt>آدرس مطب</dt><dd>${esc(item.clinic_address || doctor.clinic_address || '')}</dd></dl><p class="note">هزینه ویزیت توسط سایت دریافت نشده و در صورت نمایش فقط جهت اطلاع بیمار است.</p></main><script>window.print();<\/script></body></html>`);
                popup.document.close();
            })
            .catch((error) => alert(error.message));
    }

    function initPatientPanel(el) {
        if (!cfg.isLoggedIn) {
            el.innerHTML = panel('برای مشاهده پنل بیمار ابتدا وارد شوید.');
            return;
        }

        const content = el.querySelector('.wb-content');
        const todayLabels = el.querySelectorAll('.wb-today-label');
        const state = { view: 'patient-overview' };

        todayLabels.forEach((node) => {
            node.textContent = faDate(cfg.today);
        });

        function renderOverview() {
            content.innerHTML = panel('در حال بارگذاری');
            request('/patient-panel/summary')
                .then((summary) => {
                    content.innerHTML = `<div class="wb-page-head"><div><h2>پنل بیمار</h2><p>خلاصه نوبت‌ها و کیف پول</p></div></div>
                        <div class="wb-stats-grid">
                            ${statCard('نوبت‌های آینده', money(summary.upcoming_count), 'مورد')}
                            ${statCard('سوابق نوبت', money(summary.history_count), 'مورد')}
                            ${statCard('موجودی کیف پول', money(summary.wallet_balance), 'تومان')}
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
                    content.innerHTML = `<div class="wb-page-head"><div><h2>${scope === 'history' ? 'سوابق نوبت' : 'نوبت‌های آینده'}</h2></div></div>${appointmentsTable(items, 'patient')}`;
                })
                .catch((error) => {
                    content.innerHTML = panel(error.message);
                });
        }

        function renderWallet() {
            content.innerHTML = panel('در حال بارگذاری');
            request('/patient-panel/wallet')
                .then((wallet) => {
                    content.innerHTML = `<div class="wb-page-head"><div><h2>کیف پول</h2></div></div>
                        <div class="wb-stats-grid">${statCard('موجودی', money(wallet.balance), 'تومان')}</div>
                        <div class="wb-list">${wallet.ledger.map((item) => `<div class="wb-list-row"><strong>${esc(ledgerLabel(item.entry_type))}</strong><span>${money(item.amount)} تومان</span><span>${esc(item.created_at)}</span></div>`).join('') || 'گردشی ثبت نشده است.'}</div>`;
                })
                .catch((error) => {
                    content.innerHTML = panel(error.message);
                });
        }

        function render() {
            if (state.view === 'patient-appointments') {
                renderAppointments('upcoming');
            } else if (state.view === 'patient-history') {
                renderAppointments('history');
            } else if (state.view === 'patient-wallet') {
                renderWallet();
            } else {
                renderOverview();
            }
        }

        el.addEventListener('click', (event) => {
            const nav = event.target.closest('[data-wb-view]');
            if (nav && nav.closest('.wb-nav')) {
                state.view = nav.dataset.wbView;
                el.querySelectorAll('.wb-nav-item').forEach((button) => {
                    button.classList.toggle('is-active', button.dataset.wbView === state.view);
                });
                render();
                return;
            }
            if (event.target.closest('.wb-logout')) {
                request('/auth/logout', { method: 'POST', body: '{}' }).then(() => window.location.reload());
                return;
            }
            const action = event.target.closest('[data-action]');
            if (!action) {
                return;
            }
            if (action.dataset.action === 'receipt') {
                openReceipt(action.dataset.id);
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
                        body: JSON.stringify({ appointment_id: action.dataset.id, cancelled_by: 'patient', reason: result.reason })
                    }).then(render).catch((error) => alert(error.message));
                });
            }
        });

        render();
    }

    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('[data-webtanan-widget="auth"]').forEach(initAuth);
        document.querySelectorAll('[data-webtanan-widget="doctor-list"]').forEach((el) => initDoctorList(el));
        document.querySelectorAll('[data-webtanan-widget="doctor-search"]').forEach(initDoctorSearch);
        document.querySelectorAll('[data-webtanan-widget="calendar"]').forEach(initCalendar);
        document.querySelectorAll('[data-webtanan-widget="booking-modal"]').forEach(initBookingModal);
        document.querySelectorAll('[data-webtanan-widget="next-available"]').forEach(initNextAvailable);
        document.querySelectorAll('[data-webtanan-widget="patient-panel"]').forEach(initPatientPanel);
        document.querySelectorAll('[data-webtanan-widget="doctor-dashboard"]').forEach(initDoctorDashboard);
    });
}());
