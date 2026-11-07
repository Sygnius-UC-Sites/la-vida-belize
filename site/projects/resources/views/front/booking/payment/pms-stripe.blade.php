@extends('front.layouts.brad')
@section("title",$data->meta_title)
@section("keywords",$data->meta_keywords)
@section("description",$data->meta_description)
@section('body-class', 'page booking-page')

@section('content')


    @php
        $name=$data->name;
        $bannerImage=asset('front/images/internal-banner.webp');
        $currency = ModelHelper::getDataFromSetting('payment_currency');

        // deposit_due_amount = first installment set by PageController (or full amount if no schedule).
        // total_payment is a legacy integer COUNT (1/2/3) — never treat as a dollar amount.
        $depositDue  = (float)($booking['deposit_due_amount'] ?? 0);
        $totalAmount = (float)$booking['total_amount'];
        $amount      = ($depositDue > 0 && $depositDue < $totalAmount) ? $depositDue : $totalAmount;
        $hasSchedule = $depositDue > 0 && $depositDue < $totalAmount;

        // Security deposit — derive from stored raw quote (quote_data_all) per PMS.
        // quote_data_all stores the raw PMS adapter response, not the normalized quote.
        $securityDeposit  = 0;
        $depositInTotal   = false;   // is the deposit baked into total_amount?
        $depositIsPreauth = true;    // is it a card hold (vs an upfront charge)?
        $qd = [];
        if (!empty($booking['quote_data_all'])) {
            $qd = json_decode($booking['quote_data_all'], true) ?? [];
        }

        $pmsSource = $property->pms_source ?? '';
        if ($pmsSource === 'lodgify') {
            // Lodgify: deposit in scheduled_damage_protection; amount_gross excludes it
            $sdp = ($qd['data'][0] ?? $qd['data'] ?? [])['scheduled_damage_protection'][0] ?? [];
            $securityDeposit  = (float)($sdp['amount'] ?? 0);
            $depositInTotal   = false; // amount_gross never includes the pre-auth
            $depositIsPreauth = strtolower($sdp['type'] ?? 'authorization') === 'authorization';

        } elseif ($pmsSource === 'hostfully') {
            // Hostfully: deposit in quote.securityDeposit; includeSecurityDepositInTotal flag
            $hfQuote         = $qd['data']['quote'] ?? [];
            $securityDeposit = (float)($hfQuote['securityDeposit'] ?? 0);
            $depositInTotal  = (bool)($hfQuote['includeSecurityDepositInTotal'] ?? false);
            $depositIsPreauth = !$depositInTotal; // if not in total, it's a separate pre-auth

        } elseif ($pmsSource === 'guesty') {
            // Guesty: SDC item already in line_items; security deposit is a separate hold
            $money           = $qd['data']['money'] ?? [];
            $securityDeposit = (float)($money['securityDeposit'] ?? 0);
            $depositInTotal  = false;
            $depositIsPreauth = true;

        } elseif ($pmsSource === 'hostaway') {
            // Hostaway: refundableDamageDeposit; separate from total
            $haResult        = $qd['data']['result'] ?? $qd['result'] ?? [];
            $securityDeposit = (float)($haResult['refundableDamageDeposit'] ?? 0);
            $depositInTotal  = false;
            $depositIsPreauth = true;
        }

        $amountData = json_decode($booking['amount_data'] ?? '[]', true) ?? [];
    @endphp


@include("front.layouts.banner")
<section class="pay-page">
    <div class="container">
        <h1 class="pay-title">Confirm and Pay</h1>
        <div class="row g-4">

            {{-- ── Left: Trip info + payment form ── --}}
            <div class="col-lg-7">
                <div class="pay-left">

                    {{-- Trip summary --}}
                    <div class="trip-block">
                        <h4>Your trip</h4>
                        <div class="trip-row">
                            <div>
                                <div class="trip-label">Dates</div>
                                <div class="trip-value">
                                    {{ date('M j, Y', strtotime($booking->checkin)) }} &rarr; {{ date('M j, Y', strtotime($booking->checkout)) }}
                                </div>
                            </div>
                            <div class="text-end">
                                <div class="trip-label">Guests</div>
                                <div class="trip-value">{{ $booking->total_guests }} guest{{ $booking->total_guests > 1 ? 's' : '' }}</div>
                            </div>
                        </div>
                    </div>

                    {{-- Pay with --}}
                    <div class="pay-with-header">
                        <h4>Pay with card</h4>
                        <div class="card-logos">
                            <img src="{{ asset('front')}}/images/visa.svg"   alt="Visa">
                            <img src="{{ asset('front')}}/images/master.svg" alt="Mastercard">
                            <img src="{{ asset('front')}}/images/amex.svg"   alt="Amex">
                        </div>
                    </div>

                    {{-- Alerts --}}
                    @if(Session::has('success'))
                        <div class="pay-alert-success">{{ Session::get('success') }}</div>
                    @endif
                    @if(Session::has('danger'))
                        <div class="pay-alert-danger" style="display:block">{{ Session::get('danger') }}</div>
                    @endif
                    <div id="pay-error" class="pay-alert-danger"></div>

                    {{-- ── Price-change gate ──────────────────────────────────────────
                         Shown when the PMS returned a different total during quote refresh.
                         The payment form is hidden until the guest accepts the new total.
                         Acceptance is a POST (CSRF-protected) that updates the full quote
                         snapshot — total_amount, line items, taxes, payment schedule,
                         deposit_due_amount — not only the headline total.
                    --}}
                    @if(!empty($priceChanged))
                    <div id="price-change-banner"
                         style="border:2px solid #b45309; border-radius:8px; padding:20px;
                                background:#fffbeb; margin-bottom:20px;">
                        <h4 style="color:#b45309; margin:0 0 10px;">
                            <i class="fa-solid fa-triangle-exclamation me-2"></i>
                            Price has changed
                        </h4>
                        <p style="margin:0 0 12px; color:#444;">
                            The price for this property has been updated since you started your booking.
                        </p>
                        <div style="display:flex; gap:32px; margin-bottom:16px;">
                            <div>
                                <div style="font-size:.8rem; color:#888; text-transform:uppercase;">Original total</div>
                                <div style="font-size:1.2rem; text-decoration:line-through; color:#999;">
                                    {{ strtoupper($booking->currency ?? 'USD') }}
                                    {{ number_format($priceChanged['old'], 2) }}
                                </div>
                            </div>
                            <div>
                                <div style="font-size:.8rem; color:#888; text-transform:uppercase;">New total</div>
                                <div style="font-size:1.5rem; font-weight:700; color:#b45309;">
                                    {{ strtoupper($booking->currency ?? 'USD') }}
                                    {{ number_format($priceChanged['new'], 2) }}
                                </div>
                            </div>
                        </div>
                        <p style="font-size:.85rem; color:#666; margin:0 0 14px;">
                            To continue, please accept the updated price. You will only be charged
                            the new total shown above.
                        </p>
                        {{-- POST with CSRF — not a GET query parameter --}}
                        <form method="POST" action="{{ route('pms.stripe.accept-price', $booking->id) }}">
                            @csrf
                            <input type="hidden" name="token" value="{{ $booking->payment_token }}">
                            <button type="submit" class="btn btn-warning fw-bold px-4">
                                Accept updated price and continue
                            </button>
                            <a href="{{ url($property->seo_url) }}" class="btn btn-link text-muted ms-3">
                                Go back
                            </a>
                        </form>
                    </div>
                    @endif

                    {{-- Form — no action/method; submission handled entirely by Stripe.js.
                         HIDDEN when a price-change acceptance is pending. --}}
                    <div id="payment-form-wrap" @if(!empty($priceChanged)) style="display:none;" @endif>
                    <form id="payment-form" role="form">

                        <div class="field-group">
                            <label for="card-name">Name on card</label>
                            <input type="text" id="card-name" name="card_name"
                                   class="pay-input" placeholder="John Smith" required>
                        </div>

                        <div class="field-group">
                            <label>Card details</label>
                            <div class="stripe-element-wrap" id="stripe-element-wrap">
                                <div id="stripe-card-element"></div>
                            </div>
                            <div id="stripe-card-errors"></div>
                        </div>

                        <div class="secure-badge">
                            <i class="fa-solid fa-lock"></i>
                            <span>Your payment is secured with 256-bit SSL encryption</span>
                        </div>

                        @if($property->cancellation_policy)
                            <div class="policy-block">
                                <h4>Cancellation policy</h4>
                                <div>{!! $property->cancellation_policy !!}</div>
                            </div>
                        @endif

                        <div class="policy-block">
                            <h4>Ground rules</h4>
                            <p>We ask every guest to remember a few simple things about what makes a great guest.</p>
                            <ul>
                                <li>Follow the house rules</li>
                                <li>Treat your host's home like your own</li>
                            </ul>
                        </div>

                        <button type="submit" class="pay-btn" id="submit-btn">
                            Confirm &amp; Pay &mdash; {!! $currency !!}{{ number_format($amount, 2) }}
                            @if($hasSchedule)
                                <span style="display:block;font-size:12px;font-weight:400;opacity:.85;margin-top:3px;">Deposit due today &middot; balance charged later per schedule</span>
                            @endif
                        </button>
                    </form>
                    </div>{{-- /payment-form-wrap --}}

                </div>
            </div>

            {{-- ── Right: Property + price summary ── --}}
            <div class="col-lg-5">
                <div class="pay-right">

                    {{-- Property card with dates --}}
                    <div class="prop-card">
                        <img src="{{ asset($property->image_url) }}" alt="{{ $property->name }}">
                        <div>
                            <div class="prop-name">{{ $property->name }}</div>
                            @php
                                $loc = implode(', ', array_filter([$property->city ?? '', $property->country ?? '']));
                            @endphp
                            @if($loc)
                                <div class="prop-type">{{ $loc }}</div>
                            @endif
                            <div class="prop-dates">
                                {{ date('M j, Y', strtotime($booking->checkin)) }} &rarr; {{ date('M j, Y', strtotime($booking->checkout)) }}
                            </div>
                            <div class="prop-dates">{{ $booking->total_guests }} guest{{ $booking->total_guests > 1 ? 's' : '' }} &middot; {{ $booking->total_night }} night{{ $booking->total_night > 1 ? 's' : '' }}</div>
                        </div>
                    </div>

                    {{-- Price breakdown --}}
                    <div class="price-title">Price details</div>

                    @php
                        $feeItems   = json_decode($booking->before_total_fees, true) ?? [];
                        $afterFees = json_decode($booking->after_total_fees ?? '[]', true) ?? [];
                        $fallbackTaxItems = collect($afterFees)->map(fn($taxItem) => [
                            'label'  => $taxItem['name'] ?? 'Tax',
                            'amount' => $taxItem['value'] ?? $taxItem['amount'] ?? 0,
                        ])->all();
                        if (empty($fallbackTaxItems) && $booking->tax) {
                            $fallbackTaxItems = [['label' => 'Tax', 'amount' => $booking->tax]];
                        }
                    @endphp

                    @include('front.partials.pms-fee-groups', [
                        'feeItems'          => $feeItems,
                        'currencySymbol'    => $currency,
                        'fallbackTaxItems'  => $fallbackTaxItems,
                        'securityDeposit'   => $securityDeposit,
                        'depositInTotal'    => $depositInTotal,
                        'depositIsPreauth'  => $depositIsPreauth,
                    ])

                    <div class="price-total">
                        <span>Total</span>
                        <span>{!! $currency !!}{{ number_format($booking->total_amount, 2) }}</span>
                    </div>

                    {{-- Manual collection notice (Lodgify: total_to_collect_manually > 0 means
                         part of the payment is collected outside the platform) --}}
                    @php
                        // Raw Lodgify quote is stored in quote_data_all; it may be wrapped in data[0]
                        $rawEntry        = $qd['data'][0] ?? $qd ?? [];
                        $manualCollection = (float)($rawEntry['total_to_collect_manually'] ?? 0);
                    @endphp
                    @if($manualCollection > 0)
                        <div class="price-row" style="color:var(--bv-copper, #c4703f);font-size:13px;margin-top:6px;">
                            <span><i class="fas fa-info-circle"></i> {!! $currency !!}{{ number_format($manualCollection, 2) }} of this total will be collected separately by the property.</span>
                        </div>
                    @endif

                    {{-- Payment schedule / deposit split --}}
                    @if($hasSchedule && count($amountData))
                        <div class="price-schedule">
                            @foreach($amountData as $b)
                                <div class="price-row">
                                    <span>{{ $b['label'] ?? ('Due ' . ($b['date_due'] ?? '')) }}</span>
                                    <span>{!! $currency !!}{{ number_format($b['amount'], 2) }}</span>
                                </div>
                            @endforeach
                        </div>
                        <div class="pay-today-box">
                            <span>PAY TODAY</span>
                            <span>{!! $currency !!}{{ number_format($amount, 2) }}</span>
                        </div>
                    @endif

                </div>
            </div>

        </div>
    </div>
</section>


@stop
@section('head')
@include('front.partials.booking-funnel-head')
@include('front.partials.pms-fee-groups-css')
<style>
/* ── Payment page reset & layout ───────────────────────────────────── */
.pay-page { padding: 48px 0 80px; background: #f7f7f7; min-height: 80vh; }
.pay-page h1.pay-title { font-size: 26px; font-weight: 700; margin-bottom: 32px; color: #1a1a1a; }

.pay-left  { background: #fff; border-radius: 16px; padding: 36px; box-shadow: 0 2px 16px rgba(0,0,0,.07); }
.pay-right { background: #fff; border-radius: 16px; padding: 28px; box-shadow: 0 2px 16px rgba(0,0,0,.07); position: sticky; top: 100px; }

/* Trip summary */
.trip-block { border-bottom: 1px solid #ebebeb; padding-bottom: 20px; margin-bottom: 24px; }
.trip-block h4 { font-size: 18px; font-weight: 700; margin-bottom: 16px; color: #1a1a1a; }
.trip-row { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px; }
.trip-row .trip-label { font-size: 13px; font-weight: 600; color: #717171; text-transform: uppercase; letter-spacing: .5px; margin-bottom: 2px; }
.trip-row .trip-value { font-size: 15px; color: #1a1a1a; }

/* Pay with header */
.pay-with-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid #ebebeb; padding-bottom: 16px; }
.pay-with-header h4 { font-size: 18px; font-weight: 700; color: #1a1a1a; margin: 0; }
.card-logos { display: flex; gap: 8px; align-items: center; }
.card-logos img { height: 24px; width: auto; opacity: .85; }

/* Form fields */
.field-group { margin-bottom: 16px; }
.field-group label { display: block; font-size: 13px; font-weight: 600; color: #484848; margin-bottom: 6px; }
.field-group input.pay-input {
    width: 100%; padding: 14px 16px; border: 1.5px solid #ddd; border-radius: 10px;
    font-size: 15px; color: #1a1a1a; background: #fafafa; outline: none;
    transition: border-color .2s;
}
.field-group input.pay-input:focus { border-color: #b5935a; background: #fff; box-shadow: 0 0 0 3px rgba(181,147,90,.12); }

/* Stripe Element wrapper */
.stripe-element-wrap {
    padding: 14px 16px; border: 1.5px solid #ddd; border-radius: 10px;
    background: #fafafa; transition: border-color .2s, box-shadow .2s;
}
.stripe-element-wrap.focused { border-color: #b5935a; background: #fff; box-shadow: 0 0 0 3px rgba(181,147,90,.12); }
.stripe-element-wrap.error   { border-color: #dc3545; }
#stripe-card-errors { font-size: 13px; color: #dc3545; margin-top: 6px; min-height: 18px; }

/* Lock badge */
.secure-badge { display: flex; align-items: center; gap: 6px; font-size: 12px; color: #717171; margin-top: 10px; }
.secure-badge i { color: #2ecc71; }

/* Alerts */
.pay-alert-danger  { background: #fff5f5; border: 1px solid #f5c2c7; color: #842029; border-radius: 10px; padding: 12px 16px; margin-bottom: 16px; font-size: 14px; display:none; }
.pay-alert-success { background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; border-radius: 10px; padding: 12px 16px; margin-bottom: 16px; font-size: 14px; }

/* Cancellation + rules */
.policy-block { border-top: 1px solid #ebebeb; margin-top: 24px; padding-top: 20px; }
.policy-block h4 { font-size: 16px; font-weight: 700; color: #1a1a1a; margin-bottom: 8px; }
.policy-block p, .policy-block li { font-size: 14px; color: #484848; line-height: 1.6; }

/* Pay button */
.pay-btn {
    display: block; width: 100%; padding: 16px; margin-top: 28px;
    background: #b5935a; color: #fff; font-size: 16px; font-weight: 700;
    border: none; border-radius: 12px; cursor: pointer; letter-spacing: .3px;
    transition: background .2s, transform .1s; text-align: center;
}
.pay-btn:hover   { background: #9e7d47; }
.pay-btn:active  { transform: scale(.99); }
.pay-btn:disabled { background: #ccc; cursor: not-allowed; }

/* Right panel — property card */
.prop-card { display: flex; gap: 14px; align-items: flex-start; border-bottom: 1px solid #ebebeb; padding-bottom: 20px; margin-bottom: 20px; }
.prop-card img { width: 88px; height: 72px; object-fit: cover; border-radius: 10px; flex-shrink: 0; }
.prop-card .prop-name  { font-size: 15px; font-weight: 700; color: #1a1a1a; margin-bottom: 3px; line-height: 1.3; }
.prop-card .prop-type  { font-size: 12px; color: #717171; margin-bottom: 3px; }
.prop-card .prop-dates { font-size: 12px; color: #717171; margin-top: 2px; }

/* Price breakdown */
.price-title { font-size: 17px; font-weight: 700; color: #1a1a1a; margin-bottom: 16px; }
.price-row     { display: flex; justify-content: space-between; font-size: 14px; color: #484848; margin-bottom: 10px; }
.price-row-sub { font-size: 12px; color: #717171; margin-bottom: 6px; }
.price-tag-refund { font-size: 11px; color: #2ecc71; font-weight: 600; }
.price-total   { display: flex; justify-content: space-between; font-size: 16px; font-weight: 700; color: #1a1a1a; border-top: 1px solid #ebebeb; padding-top: 14px; margin-top: 6px; }
.price-schedule { margin-top: 14px; border-top: 1px solid #ebebeb; padding-top: 14px; }
.price-schedule .price-row { color: #717171; font-size: 13px; }
.pay-today-box { display: flex; justify-content: space-between; align-items: center; background: #1a1a1a; color: #fff; border-radius: 10px; padding: 14px 16px; margin-top: 14px; font-size: 16px; font-weight: 700; }

@media (max-width: 768px) {
    .pay-left, .pay-right { padding: 24px; }
    .pay-right { position: static; margin-top: 24px; }
}
</style>
@stop
@section('scripts')
<script src="https://js.stripe.com/v3/"></script>
<script>
    // ── PaymentIntents flow ───────────────────────────────────────────────────
    // stripe.confirmCardPayment() handles 3DS/SCA automatically.
    // On success → redirect to /booking/payment/{id}/return (server verifies + creates booking).
    // On 3DS challenge → Stripe redirects to bank, then bounces back to return_url automatically.

    var RETURN_URL    = "{{ $returnUrl ?? '' }}";
    var CLIENT_SECRET = "{{ $clientSecret ?? '' }}";

    // Guard: do not initialise Stripe elements when there is no client secret.
    // This happens when price_changed_to is set — the payment form is hidden and
    // the guest must accept the new price first (fix #5). Attempting to mount the
    // card element without a client secret would throw a Stripe.js error.
    if (!CLIENT_SECRET) {
        // No payment intent yet — page shows only the price-change acceptance banner.
        // Nothing to initialise. JS exits here; the acceptance form uses a plain POST.
    } else {

    var stripe      = Stripe('{{ $stripe_publishable_key }}');
    var elements    = stripe.elements();
    var cardElement = elements.create('card', {
        style: {
            base:    { fontSize: '16px', color: '#495057', '::placeholder': { color: '#6c757d' } },
            invalid: { color: '#dc3545' }
        }
    });
    cardElement.mount('#stripe-card-element');

    cardElement.on('focus', function() {
        document.getElementById('stripe-element-wrap').classList.add('focused');
    });
    cardElement.on('blur', function() {
        document.getElementById('stripe-element-wrap').classList.remove('focused');
    });
    cardElement.on('change', function(event) {
        var wrap = document.getElementById('stripe-element-wrap');
        var err  = document.getElementById('stripe-card-errors');
        err.textContent = event.error ? event.error.message : '';
        wrap.classList.toggle('error', !!event.error);
        if (event.error) wrap.classList.remove('focused');
    });

    function showError(msg) {
        $('#sygnius-loader').addClass('d-none');
        $('#submit-btn').prop('disabled', false);
        var el = document.getElementById('pay-error');
        el.textContent = msg;
        el.style.display = 'block';
        el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    document.getElementById('payment-form').addEventListener('submit', function(e) {
        e.preventDefault();

        $('#submit-btn').prop('disabled', true);
        $('#sygnius-loader').removeClass('d-none');
        document.getElementById('pay-error').style.display = 'none';

        stripe.confirmCardPayment(CLIENT_SECRET, {
            payment_method: {
                card: cardElement,
                billing_details: {
                    name: document.getElementById('card-name').value
                }
            },
            return_url: RETURN_URL  // Stripe redirects here after 3DS challenge
        }).then(function(result) {
            if (result.error) {
                // Card declined, validation error, or 3DS cancelled
                showError(result.error.message || 'Payment failed. Please check your card details and try again.');
            } else if (result.paymentIntent && result.paymentIntent.status === 'succeeded') {
                // No 3DS required — payment confirmed immediately.
                // Redirect to return URL for server-side booking creation.
                window.location.href = RETURN_URL + '&payment_intent=' + result.paymentIntent.id;
            } else {
                // Unexpected status — show generic message
                showError('Payment could not be confirmed. Please try again or contact support.');
            }
        });
    });

    } // end if (CLIENT_SECRET)
</script>
@stop
