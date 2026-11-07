/* JC Datepicker wrapper. Auto-mounts Easepick on inputs with [data-jc-datepicker]. */
(function (window, document) {
    'use strict';

    var MOBILE_BREAKPOINT = 640;
    var CACHE_TTL_MS = 60 * 1000;
    var blockedCache = new Map(); // property_id -> { data, expiresAt }
    var instances = new WeakMap(); // input -> Easepick instance

    function isMobile() {
        return window.matchMedia('(max-width: ' + MOBILE_BREAKPOINT + 'px)').matches;
    }

    function parseJsonAttr(input, name) {
        var raw = input.getAttribute(name);
        if (!raw) return [];
        try { return JSON.parse(raw); } catch (e) { return []; }
    }

    function configFor(input) {
        var cfg = window.JC_DATEPICKER_CONFIG || {};
        return {
            easepickCss: cfg.easepickCss || '/easepick/easepick.css',
            themeCss: cfg.themeCss || '/front/css/jc-datepicker.css',
            adminBlockedUrl: cfg.adminBlockedUrl || '/admin/api/datepicker/blocked-dates'
        };
    }

    function destroyIfExists(input) {
        var existing = instances.get(input);
        if (existing) {
            existing.destroy();
            instances.delete(input);
        }
    }

    function instantiate(input) {
        var mode = input.dataset.jcDatepicker;
        if (!mode) return null;
        if (input.disabled || (input.readOnly && input.classList.contains('jc-datepicker-locked'))) {
            // Read-only checkout input: render no popup
            return null;
        }

        var cfg = configFor(input);
        var endInputSel = input.dataset.endInput;
        var endInput = endInputSel ? document.querySelector(endInputSel) : null;
        var blocked = parseJsonAttr(input, 'data-blocked');
        var noCheckin = parseJsonAttr(input, 'data-no-checkin');
        var noCheckout = parseJsonAttr(input, 'data-no-checkout');
        var minNights = parseInt(input.dataset.minNights || '0', 10);
        var maxNights = parseInt(input.dataset.maxNights || '0', 10);

        var plugins = [];
        var opts = {
            element: input,
            format: 'YYYY-MM-DD',
            css: [cfg.easepickCss, cfg.themeCss],
            zIndex: 1000
        };

        if (isMobile()) {
            opts.calendars = 1;
            opts.grid = 1;
        } else {
            opts.calendars = 2;
            opts.grid = 2;
        }

        if (mode === 'range' || mode === 'range-blocked') {
            plugins.push('RangePlugin');
            // `repick: true` is critical: without it, after a range is set the
            // picker rejects further clicks until you clear, leaving the user
            // stuck if they want to change either date — especially if the
            // new click falls past the current checkout. With repick, every
            // subsequent click re-anchors the range cleanly.
            //
            // `tooltip: true` shows the night count as the user hovers a
            // candidate end date. Easepick's tooltip only renders when the
            // tooltipNumber callback returns a positive *number* — string
            // returns are silently dropped — so we keep the count here and
            // surface min/max stay hints in a separate permanent label
            // below the inputs (rendered by the consuming Blade).
            opts.RangePlugin = {
                tooltip: true,
                repick: true,
            };
            if (endInput) opts.RangePlugin.elementEnd = endInput;
        }

        // LockPlugin always loaded — used for past-date lock minimum
        plugins.push('LockPlugin');
        opts.LockPlugin = {
            minDate: new Date(),
            filter: function (date, picked) {
                var ymd = date.format('YYYY-MM-DD');
                // Hard blocks (interior nights of existing bookings) are always locked.
                if (blocked.indexOf(ymd) !== -1) return true;

                // No-check-in days (existing bookings start that day).
                if (picked.length === 0 && noCheckin.indexOf(ymd) !== -1) return true;

                // Looking for a check-out date.
                if (picked.length === 1) {
                    // CRITICAL: never re-lock the day the user just picked as check-in.
                    // Without this, a back-to-back day (existing checkout, valid as new
                    // check-in) is in noCheckout, the filter returns "locked", and
                    // `inseparable: true` then sees a locked day inside every candidate
                    // range — locking every possible check-out and greying the calendar.
                    var startYmd = picked[0].format('YYYY-MM-DD');
                    if (ymd === startYmd) return false;
                    if (noCheckout.indexOf(ymd) !== -1) return true;
                }
                return false;
            }
        };
        if (mode === 'range' || mode === 'range-blocked') {
            opts.LockPlugin.inseparable = true;
        }
        if (minNights > 0 && (mode === 'range' || mode === 'range-blocked')) {
            opts.LockPlugin.minDays = minNights + 1; // Easepick counts days; min_stay is in nights
        }
        if (maxNights > 0 && (mode === 'range' || mode === 'range-blocked')) {
            opts.LockPlugin.maxDays = maxNights + 1;
        }

        opts.plugins = plugins;

        var picker = new easepick.create(opts);
        instances.set(input, picker);

        // Mobile modal class
        if (isMobile()) {
            picker.ui.container.classList.add('jc-mobile');
        }

        // Inject a "Clear dates" link INSIDE the picker UI (Airbnb-style).
        // Easepick re-renders the calendar grid on hover preview and on
        // month navigation, which sometimes wipes elements appended to the
        // container. We re-inject on every relevant lifecycle event AND
        // watch via MutationObserver so the button survives re-renders.
        if (mode === 'range' || mode === 'range-blocked') {
            function injectClearBtn() {
                var container = picker.ui.container;
                if (!container || container.querySelector('.jc-picker-clear')) return;
                var btn = container.ownerDocument.createElement('button');
                btn.type = 'button';
                btn.className = 'jc-picker-clear';
                btn.textContent = 'Clear dates';
                btn.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    if (window.JC_DATEPICKER && window.JC_DATEPICKER.clear) {
                        window.JC_DATEPICKER.clear(input);
                    }
                    // Don't close — let the guest pick a new range immediately.
                });
                container.appendChild(btn);
            }
            picker.on('show', function () {
                requestAnimationFrame(function () {
                    injectClearBtn();
                    // Watch for the button getting removed by a re-render and
                    // immediately put it back.
                    if (typeof MutationObserver === 'undefined') return;
                    var c = picker.ui.container;
                    if (!c || c.dataset.jcClearObserved) return;
                    c.dataset.jcClearObserved = '1';
                    new MutationObserver(function () {
                        if (!c.querySelector('.jc-picker-clear')) injectClearBtn();
                    }).observe(c, { childList: true, subtree: false });
                });
            });
            picker.on('view',     function () { requestAnimationFrame(injectClearBtn); });
            picker.on('select',   function () { requestAnimationFrame(injectClearBtn); });
            picker.on('preselect',function () { requestAnimationFrame(injectClearBtn); });
        }

        // Easepick mutates input.value programmatically when a date / range
        // is picked, but DOES NOT fire native input/change events. Anything
        // bound via input.addEventListener('change', ...) (e.g. the booking
        // widget's debouncedRefresh) silently misses every date change.
        // Dispatch the events ourselves on every selection so consumers can
        // treat the picker like a normal text input.
        picker.on('select', function () {
            input.dispatchEvent(new Event('input',  { bubbles: true }));
            input.dispatchEvent(new Event('change', { bubbles: true }));
            if (endInput) {
                endInput.dispatchEvent(new Event('input',  { bubbles: true }));
                endInput.dispatchEvent(new Event('change', { bubbles: true }));
            }
        });
        picker.on('clear', function () {
            input.dispatchEvent(new Event('change', { bubbles: true }));
            if (endInput) endInput.dispatchEvent(new Event('change', { bubbles: true }));
        });

        // Public clear hook so consumers (e.g. a "Clear dates" button) can
        // reset the picker without having to import the easepick instance.
        // Resets internal state, clears both inputs, fires change so any
        // dependent UI (booking widget breakdown) refreshes too.
        if (!window.JC_DATEPICKER) window.JC_DATEPICKER = {};
        if (!window.JC_DATEPICKER.clear) {
            window.JC_DATEPICKER.clear = function (sourceInput) {
                var p = instances.get(sourceInput);
                if (p && typeof p.clear === 'function') {
                    try { p.clear(); } catch (e) { /* easepick variants may throw — clear inputs manually below */ }
                }
                sourceInput.value = '';
                var endSel = sourceInput.dataset.endInput;
                if (endSel) {
                    var ei = document.querySelector(endSel);
                    if (ei) ei.value = '';
                }
                sourceInput.dispatchEvent(new Event('change', { bubbles: true }));
            };
        }

        // Auto-flip + horizontal clamp: Easepick's built-in flip-condition check is wrapper-relative
        // rather than viewport-relative, so it rarely triggers. After the picker shows:
        //   1. If it overflows the viewport bottom and fits above the input, flip it above.
        //   2. If it overflows left or right of the viewport (common in narrow right-rail
        //      booking widgets where the input sits near the right edge), nudge it back on-screen.
        picker.on('show', function () {
            requestAnimationFrame(function () {
                var container = picker.ui.container;
                var wrapperRect = picker.ui.wrapper.getBoundingClientRect();
                var inputRect = input.getBoundingClientRect();
                var vw = window.innerWidth;
                var vh = window.innerHeight;
                var GUTTER = 12;

                // Vertical flip
                var rect = container.getBoundingClientRect();
                var overflowsBottom = rect.bottom > vh;
                var fitsAbove = inputRect.top - rect.height >= 0;
                if (overflowsBottom && fitsAbove) {
                    container.style.top = ((inputRect.top - rect.height) - wrapperRect.top) + 'px';
                }

                // Horizontal clamp — re-measure (in case top changed) then nudge.
                rect = container.getBoundingClientRect();
                var currentLeft = parseFloat(container.style.left || '0') || 0;
                if (rect.right > vw - GUTTER) {
                    container.style.left = (currentLeft - (rect.right - (vw - GUTTER))) + 'px';
                    rect = container.getBoundingClientRect();
                }
                if (rect.left < GUTTER) {
                    var leftAdj = parseFloat(container.style.left || '0') || 0;
                    container.style.left = (leftAdj + (GUTTER - rect.left)) + 'px';
                }
            });
        });

        return picker;
    }

    function refreshBlockedDates(input, propertyId) {
        var now = Date.now();
        var cached = blockedCache.get(String(propertyId));
        if (cached && cached.expiresAt > now) {
            applyBlocked(input, cached.data);
            return Promise.resolve(cached.data);
        }
        var cfg = configFor(input);
        return fetch(cfg.adminBlockedUrl + '?property_id=' + encodeURIComponent(propertyId), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                blockedCache.set(String(propertyId), { data: data, expiresAt: now + CACHE_TTL_MS });
                applyBlocked(input, data);
                return data;
            });
    }

    function applyBlocked(input, data) {
        input.setAttribute('data-blocked', JSON.stringify(data.blocked || []));
        input.setAttribute('data-no-checkin', JSON.stringify(data.checkin || []));
        input.setAttribute('data-no-checkout', JSON.stringify(data.checkout || []));
        if (data.min_stay !== undefined && data.min_stay !== null) {
            input.dataset.minNights = String(data.min_stay);
        }
        if (data.max_stay !== undefined) {
            input.dataset.maxNights = data.max_stay ? String(data.max_stay) : '';
        }
        destroyIfExists(input);
        instantiate(input);
    }

    function init() {
        var inputs = document.querySelectorAll('input[data-jc-datepicker]');
        for (var i = 0; i < inputs.length; i++) {
            var input = inputs[i];
            instantiate(input);

            var sourceSel = input.dataset.propertySource;
            if (sourceSel) {
                var sourceEl = document.querySelector(sourceSel);
                if (sourceEl) {
                    (function (inputRef, src) {
                        src.addEventListener('change', function () {
                            if (src.value) refreshBlockedDates(inputRef, src.value);
                        });
                    })(input, sourceEl);
                }
            }
        }
        bindClearButtons();
    }

    /**
     * Auto-wire any element with class `jc-clear-dates` and `data-target`
     * pointing to a picker input. Single uniform mechanism — every picker
     * page (home, contact, /stays, condos search, etc.) just drops the
     * button HTML and gets visibility toggling + click-to-clear for free.
     *   <button class="jc-clear-dates" data-target="#start-input"
     *           data-end="#end-input" hidden>Clear dates</button>
     */
    function bindClearButtons() {
        var btns = document.querySelectorAll('.jc-clear-dates[data-target]');
        for (var i = 0; i < btns.length; i++) {
            var btn = btns[i];
            if (btn.dataset.jcClearBound) continue;
            btn.dataset.jcClearBound = '1';

            var startInput = document.querySelector(btn.dataset.target);
            if (!startInput) continue;
            var endInput = btn.dataset.end ? document.querySelector(btn.dataset.end) : null;

            (function (b, s, e) {
                function syncVisibility() {
                    var has = !!(s.value || (e && e.value));
                    if (has) b.removeAttribute('hidden');
                    else      b.setAttribute('hidden', '');
                }
                s.addEventListener('change', syncVisibility);
                if (e) e.addEventListener('change', syncVisibility);
                syncVisibility();

                b.addEventListener('click', function (ev) {
                    ev.preventDefault();
                    if (window.JC_DATEPICKER && window.JC_DATEPICKER.clear) {
                        window.JC_DATEPICKER.clear(s);
                    } else {
                        s.value = '';
                        if (e) e.value = '';
                        s.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                });
            })(btn, startInput, endInput);
        }
    }

    var lastIsMobile = isMobile();
    window.addEventListener('resize', function () {
        var nowMobile = isMobile();
        if (nowMobile === lastIsMobile) return;
        lastIsMobile = nowMobile;
        var inputs = document.querySelectorAll('input[data-jc-datepicker]');
        for (var i = 0; i < inputs.length; i++) {
            destroyIfExists(inputs[i]);
            instantiate(inputs[i]);
        }
    });

    window.JCDatepicker = {
        init: init,
        attach: instantiate,
        refreshBlockedDates: refreshBlockedDates,
        applyAvailability: function (input, data) {
            if (!input || !data || data.ok === false) return;
            applyBlocked(input, {
                blocked: data.blocked || [],
                checkin: data.no_checkin || data.checkin || [],
                checkout: data.no_checkout || data.checkout || [],
                min_stay: data.min_stay,
                max_stay: data.max_stay
            });
        },
        getValue: function (input) {
            var p = instances.get(input);
            if (!p) return null;
            var start = p.getStartDate();
            var end = p.getEndDate ? p.getEndDate() : null;
            if (!start) return null;
            return end ? { start: start.format('YYYY-MM-DD'), end: end.format('YYYY-MM-DD') } : start.format('YYYY-MM-DD');
        }
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})(window, document);
