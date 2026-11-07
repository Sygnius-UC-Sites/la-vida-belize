@extends('front.layouts.brad')

@section('title',       $cms->meta_title    ?? 'Complete Your Payment')
@section('keywords',    $cms->meta_keywords ?? '')
@section('description', $cms->meta_description ?? '')
@section('body-class', 'page booking-page magic-link-payment-body')

@section('head')
    @include('front.partials.booking-funnel-head')
@endsection

@php
    // Stripe publishable key (property override falls back to global setting).
    $stripePublishableKey = ($property && $property->stripe_publish_key)
        ? $property->stripe_publish_key
        : \ModelHelper::getDataFromSetting('stripe_publish_key');

    $bookingTotal = (float)($booking->total_amount ?? $booking->gross_amount ?? 0);
    $totalDue = \App\Services\Payments\DirectPaymentSchedule::amountDueForBooking($booking);
    if ($totalDue <= 0) {
        $totalDue = $bookingTotal;
    }
    $scheduleRows = \App\Services\Payments\DirectPaymentSchedule::scheduleFromBooking($booking);
@endphp

@section('content')

<section class="magic-link-payment" style="padding:40px 0;">
    <div class="container">

        <h1 style="margin-bottom:8px;">{{ $cms->name ?? 'Complete Your Payment' }}</h1>
        <p style="margin-bottom:24px;color:#555;">
            Your booking request for <strong>{{ $property->name ?? 'this property' }}</strong> has been approved.
            Please complete the payment below to confirm your reservation.
        </p>

        <div class="magic-link-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:32px;">

            <!-- Left: Booking summary -->
            <div class="magic-link-summary">
                <h3>Booking summary</h3>
                <table style="width:100%;border-collapse:collapse;margin-bottom:16px;">
                    <tr>
                        <td style="padding:6px 0;"><strong>Property</strong></td>
                        <td style="padding:6px 0;text-align:right;">{{ $property->name ?? '—' }}</td>
                    </tr>
                    @if($booking->checkin && $booking->checkout)
                        <tr>
                            <td style="padding:6px 0;"><strong>Check-in</strong></td>
                            <td style="padding:6px 0;text-align:right;">{{ \Carbon\Carbon::parse($booking->checkin)->format('M j, Y') }}</td>
                        </tr>
                        <tr>
                            <td style="padding:6px 0;"><strong>Check-out</strong></td>
                            <td style="padding:6px 0;text-align:right;">{{ \Carbon\Carbon::parse($booking->checkout)->format('M j, Y') }}</td>
                        </tr>
                    @endif
                    @if($booking->total_night)
                        <tr>
                            <td style="padding:6px 0;"><strong>Nights</strong></td>
                            <td style="padding:6px 0;text-align:right;">{{ $booking->total_night }}</td>
                        </tr>
                    @endif
                    <tr>
                        <td style="padding:6px 0;"><strong>Guests</strong></td>
                        <td style="padding:6px 0;text-align:right;">
                            {{ $booking->total_guests ?? ($booking->adults + (int)$booking->child) }}
                            ({{ $booking->adults ?? 0 }} adults
                            @if(($booking->child ?? 0) > 0), {{ $booking->child }} children @endif)
                        </td>
                    </tr>
                    @if($booking->total_pets)
                        <tr>
                            <td style="padding:6px 0;"><strong>Pets</strong></td>
                            <td style="padding:6px 0;text-align:right;">{{ $booking->total_pets }}</td>
                        </tr>
                    @endif
                    <tr>
                        <td style="padding:8px 0;border-top:2px solid #333;"><strong>Booking total</strong></td>
                        <td style="padding:8px 0;border-top:2px solid #333;text-align:right;">
                            ${{ number_format($bookingTotal, 2) }}
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:10px 0;font-weight:bold;font-size:1.1em;">Due today</td>
                        <td style="padding:10px 0;border-top:2px solid #333;font-weight:bold;font-size:1.1em;text-align:right;">
                            ${{ number_format($totalDue, 2) }}
                        </td>
                    </tr>
                </table>

                @if(!empty($scheduleRows))
                    <div style="border:1px solid #e5e7eb;border-radius:6px;padding:12px;margin-bottom:16px;background:#fafafa;">
                        <strong>Payment schedule</strong>
                        <ul style="margin:8px 0 0;padding-left:18px;">
                            @foreach($scheduleRows as $row)
                                <li>
                                    {{ $row['date_due'] ? \Carbon\Carbon::parse($row['date_due'])->format('M j, Y') : ($row['label'] ?? 'Payment') }}
                                    — ${{ number_format((float) $row['amount'], 2) }}
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div style="background:#f6f6f6;padding:12px;border-radius:6px;">
                    <p style="margin:0;"><strong>Reservation under:</strong> {{ $booking->name }}</p>
                    <p style="margin:4px 0 0;"><strong>Email:</strong> {{ $booking->email }}</p>
                    @if($booking->mobile)
                        <p style="margin:4px 0 0;"><strong>Phone:</strong> {{ $booking->mobile }}</p>
                    @endif
                </div>
            </div>

            <!-- Right: Payment method picker -->
            <div class="magic-link-pay">
                <h3>Payment method</h3>

                @php
                    $gatewayCount = count($enabledGateways);
                @endphp

                @if($gatewayCount === 0)
                    <div style="border:1px solid #c33;background:#fee;padding:12px;border-radius:6px;margin-bottom:12px;">
                        No payment gateway is currently enabled. Please contact us to complete your payment.
                    </div>
                    <p>
                        <a href="{{ url('contact-us') }}" style="color:#1a73e8;">Contact us &rarr;</a>
                    </p>
                @else
                    <form id="magic-link-form" onsubmit="return false;">
                        @csrf
                        <input type="hidden" name="booking_id" value="{{ $booking->id }}">

                        @if($gatewayCount > 1)
                            <div style="margin-bottom:16px;">
                                <p style="margin-bottom:8px;">Choose how you'd like to pay:</p>
                                @foreach($enabledGateways as $gw)
                                    <label style="display:block;margin:6px 0;padding:10px;border:1px solid #ccc;border-radius:6px;cursor:pointer;">
                                        <input type="radio" name="payment_method" value="{{ $gw }}" required {{ $loop->first ? 'checked' : '' }}>
                                        {{ ucfirst($gw) }}
                                    </label>
                                @endforeach
                            </div>
                        @else
                            {{-- Single-gateway path. We don't print "Payment via Stripe" above the
                                 card form — the card field itself is the UI. For non-card single
                                 gateways (PayPal / Authorize hosted / Square hosted) where there's
                                 no inline form to display, we still show a one-line label so the
                                 guest knows what to expect when they hit Pay. --}}
                            <input type="hidden" name="payment_method" value="{{ $enabledGateways[0] }}">
                            @if($enabledGateways[0] !== 'stripe')
                                <p style="color:#555;margin-bottom:16px;">You'll complete payment through <strong>{{ ucfirst($enabledGateways[0]) }}</strong>.</p>
                            @endif
                        @endif

                        {{-- Stripe Elements card field — visible only when stripe is the selected method. --}}
                        <div id="stripe-card-area" style="margin-bottom:16px;{{ in_array('stripe', $enabledGateways) ? '' : 'display:none;' }}">
                            <label style="display:block;margin-bottom:6px;">Card details *</label>
                            <div id="stripe-card-element"
                                 style="padding:12px;border:1px solid #ccc;border-radius:6px;background:#fff;"></div>
                            <div id="stripe-card-errors" role="alert"
                                 style="margin-top:6px;color:#c33;font-size:0.9em;min-height:1em;"></div>
                        </div>

                        <div id="magic-link-error" style="display:none;border:1px solid #c33;background:#fee;padding:12px;border-radius:6px;margin-bottom:12px;color:#c33;"></div>

                        <button type="submit" id="magic-link-submit"
                                data-booking-id="{{ $booking->id }}"
                                data-amount="{{ number_format($totalDue, 2, '.', '') }}"
                                data-stripe-url="{{ url('booking/payment/' . $booking->id) }}"
                                data-paypal-url="{{ url('booking/payment/paypal/' . $booking->id) }}"
                                data-authorize-url="{{ url('booking/payment/authorize/' . $booking->id) }}"
                                data-square-url="{{ url('booking/payment/square/' . $booking->id) }}"
                                style="width:100%;padding:14px;background:#1a73e8;color:#fff;border:none;border-radius:6px;font-size:1.05em;font-weight:bold;cursor:pointer;">
                            Pay ${{ number_format($totalDue, 2) }}
                        </button>
                    </form>
                @endif
            </div>

        </div><!-- /.magic-link-grid -->

    </div>
</section>

{{-- Disable browser caching so a Back / Forward returning here always
     re-runs payByMagicLink() on the server — that's where we check
     payment_status='paid' and bounce the guest forward to the confirmed
     page. Without this, a paid booking could re-render the payment form
     from bfcache. --}}
<meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate, max-age=0">
<meta http-equiv="Pragma" content="no-cache">
<meta http-equiv="Expires" content="0">

@if(in_array('stripe', $enabledGateways) && $stripePublishableKey)
<script src="https://js.stripe.com/v3/"></script>
@endif

<script>
/* ──────────────────────────────────────────────────────────────────
   Navigation guard — same shape as booking-confirmed / request-received,
   tuned for "mid-payment" semantics:
     • bfcache restore (Back/Forward) → hard reload so the controller
       re-checks payment_status. If already paid, server redirects to
       /booking-confirmed-instant; otherwise the form re-mounts cleanly.
     • popstate (Back hit on this same tab) → prompt; if guest confirms
       leave, send them home; otherwise push state back.
     • beforeunload → confirmation prompt once any field is touched, but not
       for our own successful payment/gateway redirects.
   ────────────────────────────────────────────────────────────────── */
(function () {
    var safeUrl = @json(url('/'));
    var anyTouched = false;
    var paying = false; // flips true the moment Pay is clicked, never resets
    var allowNavigation = false;

    // Push a state entry on top of this URL so a Back tap fires popstate
    // here instead of dropping the guest into the magic-link email.
    if (window.history.pushState) {
        window.history.pushState({ paying: true }, '', window.location.href);
    }

    window.addEventListener('popstate', function () {
        var msg = paying
            ? 'A payment may be in progress. If you leave, you could double-charge or lose the booking. Are you sure you want to leave?'
            : 'Leaving this page will cancel your payment. Are you sure?';
        if (confirm(msg)) {
            window.location.replace(safeUrl);
        } else {
            // Re-push so the next Back hit prompts again.
            if (window.history.pushState) {
                window.history.pushState({ paying: true }, '', window.location.href);
            }
        }
    });

    window.addEventListener('pageshow', function (event) {
        // bfcache restore — reload so the controller re-runs and the
        // payment_status='paid' redirect can fire. event.persisted is
        // true only on a back-forward-cache restore.
        if (event.persisted) {
            window.location.reload();
        }
    });

    window.addEventListener('beforeunload', function (e) {
        if (allowNavigation) return;
        if (!anyTouched && !paying) return; // don't nag if they haven't started
        var msg = paying
            ? 'A payment is being processed. Leaving now could double-charge your card or fail the booking.'
            : 'You have unsaved payment details. Leave and lose them?';
        e.preventDefault();
        e.returnValue = msg;
        return msg;
    });

    // Mark "touched" once any user input lands.
    document.addEventListener('input', function () { anyTouched = true; }, true);
    document.addEventListener('change', function () { anyTouched = true; }, true);

    // Expose for the payment handler below to flip `paying` and read it.
    window.__caMagicLinkGuard = {
        markPaying:   function () { paying = true; },
        clearPaying:  function () { paying = false; },
        isPaying:     function () { return paying; },
        allowNavigation: function () {
            allowNavigation = true;
            paying = false;
            anyTouched = false;
        }
    };
})();

(function () {
    var form = document.getElementById('magic-link-form');
    if (!form) return;

    var btn       = document.getElementById('magic-link-submit');
    var errBox    = document.getElementById('magic-link-error');
    var cardArea  = document.getElementById('stripe-card-area');
    var cardErr   = document.getElementById('stripe-card-errors');
    var origLabel = btn ? btn.textContent : '';
    var inFlight  = false; // guard against double-clicks during async work
    var guard     = window.__caMagicLinkGuard;

    // CSRF token from form for fetch posts.
    var csrfInput = form.querySelector('input[name="_token"]');
    var csrfToken = csrfInput ? csrfInput.value : '';

    // Stripe Elements setup (v3, card field).
    var stripe = null;
    var card   = null;
    var stripePk = @json($stripePublishableKey ?? '');

    if (window.Stripe && stripePk) {
        stripe = window.Stripe(stripePk);
        var elements = stripe.elements();
        card = elements.create('card', {
            style: {
                base: {
                    fontSize: '15px',
                    color: '#222',
                    '::placeholder': { color: '#999' }
                },
                invalid: { color: '#c33' }
            }
        });
        card.mount('#stripe-card-element');
        card.on('change', function (event) {
            cardErr.textContent = (event.error && event.error.message) || '';
        });
    }

    // Show/hide card field as method radios change.
    function syncCardVisibility() {
        var checked = form.querySelector('input[name="payment_method"]:checked')
                   || form.querySelector('input[name="payment_method"]');
        var method = checked ? checked.value : null;
        if (cardArea) {
            cardArea.style.display = (method === 'stripe') ? '' : 'none';
        }
    }
    Array.prototype.forEach.call(
        form.querySelectorAll('input[name="payment_method"]'),
        function (el) { el.addEventListener('change', syncCardVisibility); }
    );
    syncCardVisibility();

    function showError(msg) {
        errBox.textContent = msg;
        errBox.style.display = 'block';
    }
    function clearError() {
        errBox.textContent = '';
        errBox.style.display = 'none';
    }

    function lockBtn(label) {
        inFlight = true;
        if (guard) guard.markPaying();
        btn.disabled = true;
        btn.setAttribute('aria-busy', 'true');
        btn.style.opacity = '0.7';
        btn.style.cursor = 'not-allowed';
        btn.textContent = label || 'Processing…';
    }
    function unlockBtn() {
        inFlight = false;
        if (guard) guard.clearPaying();
        btn.disabled = false;
        btn.removeAttribute('aria-busy');
        btn.style.opacity = '';
        btn.style.cursor = '';
        btn.textContent = origLabel;
    }

    btn.addEventListener('click', function (e) {
        e.preventDefault();
        // Double-click guard. The button.disabled flip below is the primary
        // protection, but a second click that lands in the same event-loop
        // tick (before the browser repaints disabled) would still fire — the
        // inFlight flag short-circuits that race.
        if (inFlight) return;
        if (!form.checkValidity()) { form.reportValidity(); return; }

        clearError();
        var checked = form.querySelector('input[name="payment_method"]:checked')
                   || form.querySelector('input[name="payment_method"]');
        var method  = checked ? checked.value : null;

        if (!method) {
            showError('Please choose a payment method.');
            return;
        }

        if (method === 'stripe') {
            if (!stripe || !card) {
                showError('Stripe is not configured. Please refresh or contact support.');
                return;
            }
            lockBtn('Processing…');

            stripe.createToken(card).then(function (result) {
                if (result.error) {
                    cardErr.textContent = result.error.message || 'Card validation failed.';
                    unlockBtn();
                    return;
                }
                var fd = new FormData();
                fd.append('_token', csrfToken);
                fd.append('stripeToken', result.token.id);
                fd.append('amount', btn.getAttribute('data-amount'));

                fetch(btn.getAttribute('data-stripe-url'), {
                    method: 'POST',
                    body: fd,
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                    credentials: 'same-origin'
                })
                .then(function (resp) { return resp.json().then(function (data) { return { ok: resp.ok, data: data }; }); })
                .then(function (res) {
                    if (res.data && res.data.success && res.data.redirect_url) {
                        // Successful charge — this redirect is intentional, so do
                        // not let the beforeunload guard show a scary browser prompt.
                        if (guard && guard.allowNavigation) guard.allowNavigation();
                        window.location.href = res.data.redirect_url;
                        return;
                    }
                    var msg = (res.data && (res.data.error_message || res.data.message)) || 'Payment could not be completed. Please try again.';
                    showError(msg);
                    unlockBtn();
                })
                .catch(function () {
                    showError('Network error. Please try again.');
                    unlockBtn();
                });
            }).catch(function () {
                showError('Could not contact Stripe. Please try again.');
                unlockBtn();
            });
            return;
        }

        // Other gateways (paypal/authorize/square): redirect to gateway's own form page.
        // Stripe-only is the v1 supported flow; the others render placeholder/legacy pages.
        var url = null;
        switch (method) {
            case 'paypal':    url = btn.getAttribute('data-paypal-url'); break;
            case 'authorize': url = btn.getAttribute('data-authorize-url'); break;
            case 'square':    url = btn.getAttribute('data-square-url'); break;
        }
        if (!url) {
            showError('Selected payment method is not available.');
            return;
        }
        // Lock the button before navigating so a second click before the new
        // page loads doesn't fire a duplicate request.
        lockBtn('Redirecting…');
        if (guard && guard.allowNavigation) guard.allowNavigation();
        window.location.href = url;
    });
})();
</script>

@endsection
