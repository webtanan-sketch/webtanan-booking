(function () {
    'use strict';

    const monthNames = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];
    const weekDays = ['شنبه', 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه'];

    function div(a, b) {
        return Math.floor(a / b);
    }

    function gregorianToJalali(gy, gm, gd) {
        const gdm = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
        let jy = gy <= 1600 ? 0 : 979;
        gy -= gy <= 1600 ? 621 : 1600;
        const gy2 = gm > 2 ? gy + 1 : gy;
        let days = (365 * gy) + div(gy2 + 3, 4) - div(gy2 + 99, 100) + div(gy2 + 399, 400) - 80 + gd + gdm[gm - 1];
        jy += 33 * div(days, 12053);
        days %= 12053;
        jy += 4 * div(days, 1461);
        days %= 1461;
        if (days > 365) {
            jy += div(days - 1, 365);
            days = (days - 1) % 365;
        }

        const jm = days < 186 ? 1 + div(days, 31) : 7 + div(days - 186, 30);
        const jd = 1 + (days < 186 ? days % 31 : (days - 186) % 30);

        return { jy, jm, jd };
    }

    function jalaliToGregorian(jy, jm, jd) {
        jy += 1595;
        let days = -355668 + (365 * jy) + (div(jy, 33) * 8) + div((jy % 33) + 3, 4) + jd + (jm < 7 ? (jm - 1) * 31 : ((jm - 7) * 30) + 186);
        let gy = 400 * div(days, 146097);
        days %= 146097;
        if (days > 36524) {
            gy += 100 * div(--days, 36524);
            days %= 36524;
            if (days >= 365) {
                days++;
            }
        }
        gy += 4 * div(days, 1461);
        days %= 1461;
        if (days > 365) {
            gy += div(days - 1, 365);
            days = (days - 1) % 365;
        }
        let gd = days + 1;
        const leap = (gy % 4 === 0 && gy % 100 !== 0) || gy % 400 === 0;
        const sal = [0, 31, leap ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
        let gm = 1;
        while (gm <= 12 && gd > sal[gm]) {
            gd -= sal[gm];
            gm++;
        }

        return { gy, gm, gd };
    }

    function parseISO(value) {
        const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value || '');
        if (!match) {
            return null;
        }

        return new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3]));
    }

    function pad(value) {
        return String(value).padStart(2, '0');
    }

    function formatISO(date) {
        return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
    }

    function addDays(value, days) {
        const date = typeof value === 'string' ? parseISO(value) : new Date(value.getTime());
        date.setDate(date.getDate() + days);

        return date;
    }

    function toJalali(value) {
        const date = typeof value === 'string' ? parseISO(value) : value;
        if (!date) {
            return null;
        }

        return gregorianToJalali(date.getFullYear(), date.getMonth() + 1, date.getDate());
    }

    function fromJalali(jy, jm, jd) {
        const g = jalaliToGregorian(jy, jm, jd);

        return new Date(g.gy, g.gm - 1, g.gd);
    }

    function daysInJalaliMonth(jy, jm) {
        const current = fromJalali(jy, jm, 1);
        const next = jm === 12 ? fromJalali(jy + 1, 1, 1) : fromJalali(jy, jm + 1, 1);

        return Math.round((next.getTime() - current.getTime()) / 86400000);
    }

    function saturdayIndex(date) {
        return (date.getDay() + 1) % 7;
    }

    function weekRange(value) {
        const date = typeof value === 'string' ? parseISO(value) : value;
        const start = addDays(date, -saturdayIndex(date));

        return Array.from({ length: 7 }, (_, index) => formatISO(addDays(start, index)));
    }

    function jalaliMonthRange(value) {
        const date = typeof value === 'string' ? parseISO(value) : value;
        const j = toJalali(date);
        if (!j) {
            return [];
        }

        const count = daysInJalaliMonth(j.jy, j.jm);
        return Array.from({ length: count }, (_, index) => formatISO(fromJalali(j.jy, j.jm, index + 1)));
    }

    function formatJalaliDate(value, withWeekday = true) {
        const date = typeof value === 'string' ? parseISO(value) : value;
        const j = toJalali(date);
        if (!j) {
            return value || '';
        }

        const weekday = weekDays[saturdayIndex(date)];
        const label = `${toPersianNumber(j.jd)} ${monthNames[j.jm - 1]} ${toPersianNumber(j.jy)}`;

        return withWeekday ? `${weekday} ${label}` : label;
    }

    function toPersianNumber(value) {
        return String(value).replace(/\d/g, (digit) => '۰۱۲۳۴۵۶۷۸۹'[Number(digit)]);
    }

    function closeAll(except) {
        document.querySelectorAll('.webtanan-jalali-date.is-open').forEach((picker) => {
            if (picker !== except) {
                picker.classList.remove('is-open');
                const trigger = picker.querySelector('.webtanan-jalali-trigger');
                if (trigger) {
                    trigger.setAttribute('aria-expanded', 'false');
                }
            }
        });
    }

    function enhanceInput(input) {
        if (!input || input.dataset.jalaliReady === '1') {
            return;
        }

        input.dataset.jalaliReady = '1';
        if (!input.value && input.required) {
            input.value = formatISO(new Date());
        }

        const selected = parseISO(input.value) || new Date();
        let view = toJalali(selected);
        input.type = 'hidden';

        const picker = document.createElement('div');
        picker.className = 'webtanan-jalali-date';
        picker.dir = 'rtl';
        picker.innerHTML = '<button type="button" class="webtanan-jalali-trigger" aria-haspopup="dialog" aria-expanded="false"></button><div class="webtanan-jalali-popover" role="dialog"></div>';
        input.insertAdjacentElement('afterend', picker);

        const trigger = picker.querySelector('.webtanan-jalali-trigger');
        const popover = picker.querySelector('.webtanan-jalali-popover');

        function updateTrigger() {
            trigger.textContent = input.value ? formatJalaliDate(input.value) : 'انتخاب تاریخ';
        }

        function setValue(date) {
            input.value = formatISO(date);
            input.dispatchEvent(new Event('input', { bubbles: true }));
            input.dispatchEvent(new Event('change', { bubbles: true }));
            updateTrigger();
        }

        function render() {
            const first = fromJalali(view.jy, view.jm, 1);
            const blanks = saturdayIndex(first);
            const days = daysInJalaliMonth(view.jy, view.jm);
            const selectedJ = toJalali(input.value);
            let cells = '';

            for (let i = 0; i < blanks; i++) {
                cells += '<span class="webtanan-jalali-empty"></span>';
            }

            for (let day = 1; day <= days; day++) {
                const date = fromJalali(view.jy, view.jm, day);
                const iso = formatISO(date);
                const today = iso === formatISO(new Date());
                const selectedDay = selectedJ && selectedJ.jy === view.jy && selectedJ.jm === view.jm && selectedJ.jd === day;
                cells += `<button type="button" class="webtanan-jalali-day${today ? ' is-today' : ''}${selectedDay ? ' is-selected' : ''}" data-date="${iso}">${toPersianNumber(day)}</button>`;
            }

            popover.innerHTML = `
                <div class="webtanan-jalali-head">
                    <button type="button" data-jalali-nav="prev" aria-label="ماه قبل">‹</button>
                    <strong>${monthNames[view.jm - 1]} ${toPersianNumber(view.jy)}</strong>
                    <button type="button" data-jalali-nav="next" aria-label="ماه بعد">›</button>
                </div>
                <div class="webtanan-jalali-weekdays">${weekDays.map((day) => `<span>${day}</span>`).join('')}</div>
                <div class="webtanan-jalali-grid">${cells}</div>
                <div class="webtanan-jalali-actions"><button type="button" data-jalali-today="1">امروز</button></div>
            `;
        }

        trigger.addEventListener('click', () => {
            const isOpen = picker.classList.toggle('is-open');
            trigger.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            if (isOpen) {
                const current = parseISO(input.value) || new Date();
                view = toJalali(current);
                closeAll(picker);
                render();
            }
        });

        popover.addEventListener('click', (event) => {
            const nav = event.target.closest('[data-jalali-nav]');
            if (nav) {
                if (nav.dataset.jalaliNav === 'next') {
                    view.jm++;
                    if (view.jm > 12) {
                        view.jm = 1;
                        view.jy++;
                    }
                } else {
                    view.jm--;
                    if (view.jm < 1) {
                        view.jm = 12;
                        view.jy--;
                    }
                }
                render();
                return;
            }

            if (event.target.closest('[data-jalali-today]')) {
                const today = new Date();
                view = toJalali(today);
                setValue(today);
                render();
                picker.classList.remove('is-open');
                trigger.setAttribute('aria-expanded', 'false');
                return;
            }

            const day = event.target.closest('[data-date]');
            if (day) {
                setValue(parseISO(day.dataset.date));
                picker.classList.remove('is-open');
                trigger.setAttribute('aria-expanded', 'false');
            }
        });

        input.addEventListener('change', updateTrigger);
        updateTrigger();
    }

    function enhance(root) {
        const scope = root && root.querySelectorAll ? root : document;
        scope.querySelectorAll('.webtanan-booking input[type="date"], .webtanan-admin input[type="date"], input.webtanan-jalali-input[type="date"]').forEach(enhanceInput);
    }

    document.addEventListener('click', (event) => {
        if (!event.target.closest('.webtanan-jalali-date')) {
            closeAll();
        }
    });

    document.addEventListener('DOMContentLoaded', () => {
        enhance(document);
        const observer = new MutationObserver(() => enhance(document));
        observer.observe(document.body, { childList: true, subtree: true });
    });

    window.WebtananJalaliCalendar = {
        enhance,
        formatJalaliDate,
        weekRange,
        jalaliMonthRange,
        addDays,
        formatISO,
        parseISO,
        toJalali,
        fromJalali,
        monthNames,
        weekDays,
        toPersianNumber
    };
}());
