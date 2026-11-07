/**
 * [PMS-MODULE] Booking-widget data layer.
 *
 * The site's own date picker stays exactly as it is (its blocked dates are
 * kept correct server-side via the sync's ical_events backfill). This script
 * only replaces the page's quote function and the form submit target:
 *
 *   1. window.ajaxCallingData (the function the picker already calls after a
 *      date selection) now fetches the LIVE PMS quote and renders it through
 *      the site's own quote partial — same container, same styling.
 *   2. The booking form submits into the PMS funnel.
 *
 * No pickers are rebuilt, no CSS is added, no elements are restyled.
 */
(function () {
    'use strict';
    var cfg = window.PMS_WIDGET;
    if (!cfg || !cfg.roomId) return;

    function $id(x) { return document.getElementById(x); }
    function vals() {
        var s = $id('start_date'), e = $id('end_date'), a = $id('adults-data'), c = $id('child-data');
        return {
            start:  s ? s.value : '',
            end:    e ? e.value : '',
            adults: a ? parseInt(a.value || '0', 10) : 0,
            childs: c ? parseInt(c.value || '0', 10) : 0
        };
    }
    function post(url, data, cb) {
        var x = new XMLHttpRequest();
        x.open('POST', url, true);
        x.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        var body = Object.keys(data).map(function (k) { return encodeURIComponent(k) + '=' + encodeURIComponent(data[k]); }).join('&');
        x.onreadystatechange = function () { if (x.readyState === 4) { try { cb(null, JSON.parse(x.responseText)); } catch (e) { cb(e); } } };
        x.send(body);
    }

    var seq = 0;
    function pmsQuote() {
        var v = vals();
        var area = $id('gaurav-new-data-area');
        if (!area || !v.start || !v.end) return;
        if ((v.adults + v.childs) < 1) {
            var err = $id('error-show-data');
            if (err) err.innerHTML = 'Please select the number of guests';
            return;
        }
        var my = ++seq;
        area.innerHTML = '<small>Checking availability and price…</small>';
        post(cfg.quoteUrl, { room_id: cfg.roomId, checkin: v.start, checkout: v.end, adults: v.adults, childs: v.childs, pets: 0 },
            function (e, q) {
                if (my !== seq) return;
                if (e || !q || !q.ok) {
                    area.innerHTML = '<small>' + ((q && q.error) ? String(q.error) : 'Price unavailable for these dates. Please try different dates.') + '</small>';
                    var sb0 = $id('submit-button-gaurav-data');
                    if (sb0) sb0.style.display = 'none';
                    return;
                }
                if (q.html) { area.innerHTML = q.html; }
                var sb = $id('submit-button-gaurav-data');
                if (sb) sb.style.display = '';
                var fs = $id('form_start_date'), fe = $id('form_end_date');
                if (fs) fs.value = v.start;
                if (fe) fe.value = v.end;
            });
    }

    function hookSubmit() {
        var form = $id('booking_form') || document.querySelector('form.booking_form');
        if (!form) return;
        form.addEventListener('submit', function (e) {
            var v = vals();
            if (!v.start || !v.end) return; // site's own validation handles it
            e.preventDefault();
            e.stopImmediatePropagation();
            window.location = cfg.funnelUrl
                + '?property_id=' + encodeURIComponent(cfg.roomId)
                + '&start_date=' + encodeURIComponent(v.start)
                + '&end_date=' + encodeURIComponent(v.end)
                + '&adults=' + encodeURIComponent(v.adults || 1)
                + '&childs=' + encodeURIComponent(v.childs || 0)
                + '&no_of_pets=0';
        }, true);
    }

    function arm() {
        var pickerInput = $id('widget-check-in');
        if (pickerInput && cfg.availUrl) {
            var from = new Date();
            var to = new Date();
            to.setMonth(to.getMonth() + 18);
            var ymd = function (date) {
                return date.getFullYear() + '-' + String(date.getMonth() + 1).padStart(2, '0')
                    + '-' + String(date.getDate()).padStart(2, '0');
            };
            fetch(cfg.availUrl + '?room_id=' + encodeURIComponent(cfg.roomId)
                + '&start_date=' + ymd(from) + '&end_date=' + ymd(to), {
                headers: { 'Accept': 'application/json' },
                credentials: 'same-origin'
            }).then(function (response) {
                return response.json();
            }).then(function (availability) {
                // The site's picker is VRDatepicker (Easepick wrapper). Feed it the
                // live availability so blocked dates, half-day turnover (no-checkin/
                // no-checkout), min-stay AND nightly rates render on the calendar.
                // (Older builds exposed JCDatepicker — keep it as a fallback so a
                // stale picker bundle still receives the data.)
                var dp = window.VRDatepicker || window.JCDatepicker;
                if (dp && dp.applyAvailability) {
                    dp.applyAvailability(pickerInput, availability);
                }
            }).catch(function () {
                // The server-rendered calendar remains fail-safe if refresh is unavailable.
            });
        }

        // Replace the page's quote function IN PLACE — the untouched picker
        // and guest buttons already call ajaxCallingData(); now it runs the
        // live PMS quote instead of the legacy one (which errored).
        window.ajaxCallingData = pmsQuote;
        hookSubmit();
    }

    if (document.readyState === 'complete') { setTimeout(arm, 0); }
    else { window.addEventListener('load', function () { setTimeout(arm, 0); }); }
})();
