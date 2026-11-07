/**
 * [PMS-MODULE] Quote-page funnel — keeps the SITE's own quote/guest page
 * exactly as designed; only changes what happens underneath:
 *
 *   1. The form posts to the PMS booking endpoint (live PMS pricing is
 *      re-validated server-side) instead of the legacy endpoint.
 *   2. Raw card inputs (PCI risk) are swapped IN-PLACE for a Stripe Elements
 *      card field in the same spot — same form, same layout.
 *   3. Payment is confirmed via Stripe PaymentIntents (3DS-ready) and then
 *      the PMS reservation is created server-side.
 *
 * If the page has no card fields, the form simply continues to the module's
 * payment page after saving.
 */
(function () {
    'use strict';
    var cfg = window.PMS_FUNNEL;
    if (!cfg || !cfg.saveUrl) return;

    function onReady(fn) {
        if (document.readyState !== 'loading') return setTimeout(fn, 0);
        document.addEventListener('DOMContentLoaded', function () { setTimeout(fn, 0); });
    }

    function findForm() {
        return document.getElementById('paymentFrm')
            || document.querySelector('form[action*="save-booking-data"]')
            || document.getElementById('save-booking-data');
    }

    function note(msg, ok) {
        if (window.toastr) { ok ? toastr.success(msg) : toastr.error(msg); }
        else if (!ok) { alert(msg); }
    }

    function setBusy(busy) {
        var l = document.getElementById('sygnius-loader');
        if (l) { l.classList.toggle('d-none', !busy); }
        var b = document.getElementById('submit-pay-now-button')
             || (findForm() ? findForm().querySelector('[type="submit"]') : null);
        if (b) b.disabled = busy;
    }

    function hiddenInput(form, name, value) {
        var el = form.querySelector('input[name="' + name + '"]');
        if (!el) {
            el = document.createElement('input');
            el.type = 'hidden';
            el.name = name;
            form.appendChild(el);
        }
        el.value = value;
    }

    var stripe = null, cardElement = null, hasCardOnPage = false;

    function swapCardFields(form) {
        var cardInput = form.querySelector('input[name="card_number"]');
        if (!cardInput) return; // page has no card fields — nothing to swap
        hasCardOnPage = true;

        // Hide + disable every raw card control (disabled fields are not
        // serialized, so raw card data never leaves the browser).
        ['card_number', 'card_cvv', 'card_expiry_month', 'card_expiry_year'].forEach(function (n) {
            var el = form.querySelector('[name="' + n + '"]');
            if (!el) return;
            el.disabled = true;
            el.required = false;
            var group = el.closest('.form-group, .col-md-4, .col-md-6, .col-12') || el;
            group.style.display = 'none';
        });

        // Mount a Stripe card Element where the card number used to be.
        var holder = document.createElement('div');
        holder.innerHTML = '<label>Card details</label>'
            + '<div id="pms-card-element" class="form-control" style="height:auto;padding:12px;"></div>'
            + '<div id="pms-card-errors" style="color:#c00;font-size:90%;margin-top:6px;"></div>';
        var anchor = cardInput.closest('.form-group, .col-md-4, .col-md-6, .col-12') || cardInput;
        anchor.parentNode.insertBefore(holder, anchor);

        stripe      = Stripe(cfg.stripeKey);
        cardElement = stripe.elements().create('card', { hidePostalCode: true });
        cardElement.mount('#pms-card-element');
        cardElement.on('change', function (ev) {
            document.getElementById('pms-card-errors').textContent = ev.error ? ev.error.message : '';
        });
    }

    function post(url, body, cb) {
        var x = new XMLHttpRequest();
        x.open('POST', url, true);
        x.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        x.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        x.onreadystatechange = function () { if (x.readyState === 4) { try { cb(null, JSON.parse(x.responseText)); } catch (e) { cb(e); } } };
        x.send(body);
    }
    function getJSON(url, cb) {
        var x = new XMLHttpRequest();
        x.open('GET', url, true);
        x.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        x.onreadystatechange = function () { if (x.readyState === 4) { try { cb(null, JSON.parse(x.responseText)); } catch (e) { cb(e); } } };
        x.send();
    }

    function serialize(form) {
        var parts = [];
        Array.prototype.forEach.call(form.elements, function (el) {
            if (!el.name || el.disabled) return;
            if ((el.type === 'checkbox' || el.type === 'radio') && !el.checked) return;
            parts.push(encodeURIComponent(el.name) + '=' + encodeURIComponent(el.value));
        });
        return parts.join('&');
    }

    onReady(function () {
        var form = findForm();
        if (!form) return;

        form.setAttribute('action', cfg.saveUrl);
        hiddenInput(form, 'pms_ajax', '1');
        hiddenInput(form, 'pms_room_db_id', cfg.roomDbId);

        // Stripe.js for the in-place card field
        if (form.querySelector('input[name="card_number"]') && typeof Stripe === 'undefined') {
            var s = document.createElement('script');
            s.src = 'https://js.stripe.com/v3/';
            s.onload = function () { swapCardFields(form); };
            document.head.appendChild(s);
        } else {
            swapCardFields(form);
        }

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            e.stopImmediatePropagation();
            setBusy(true);

            post(cfg.saveUrl, serialize(form), function (err, r1) {
                if (err || !r1 || r1.status !== 200) {
                    setBusy(false);
                    note((r1 && r1.message) || 'Could not save your booking. Please try again.', false);
                    return;
                }
                if (!hasCardOnPage || !cardElement) {
                    // No card on this page → continue on the payment page.
                    window.location = r1.payment_page_url;
                    return;
                }
                getJSON(r1.payment_json_url, function (err2, r2) {
                    if (err2 || !r2 || !r2.ok || !r2.client_secret) {
                        // Could not prepare the charge inline — payment page instead.
                        window.location = r1.payment_page_url;
                        return;
                    }
                    var nameEl  = form.querySelector('[name="firstname"], [name="first_name"]');
                    var lastEl  = form.querySelector('[name="lastname"], [name="last_name"]');
                    var emailEl = form.querySelector('[name="email"]');
                    stripe.confirmCardPayment(r2.client_secret, {
                        payment_method: {
                            card: cardElement,
                            billing_details: {
                                name:  ((nameEl ? nameEl.value : '') + ' ' + (lastEl ? lastEl.value : '')).trim() || undefined,
                                email: emailEl ? emailEl.value : undefined
                            }
                        }
                    }).then(function (res) {
                        if (res.error) {
                            setBusy(false);
                            document.getElementById('pms-card-errors').textContent = res.error.message;
                            note(res.error.message, false);
                            return;
                        }
                        note('Payment confirmed — completing your reservation…', true);
                        window.location = r2.return_url;
                    });
                });
            });
        }, true);
    });
})();
