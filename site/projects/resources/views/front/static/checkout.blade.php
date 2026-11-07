@extends('front.layouts.brad')

@section('title',       $cms->meta_title    ?? ('Confirm and Pay - ' . ($setting_data['website'] ?? config('app.name', 'Vacation Rentals'))))
@section('keywords',    $cms->meta_keywords ?? '')
@section('description', $cms->meta_description ?? '')
@section('body-class', 'page booking-page checkout-page-body')

@section('head')
    @include('front.partials.booking-funnel-head')
@endsection

@php
    $checkoutMode = $checkoutMode ?? 'direct';
    $isPmsCheckout = $checkoutMode === 'pms';

    $stripePublishableKey = $isPmsCheckout
        ? \ModelHelper::getDataFromSetting('stripe_publish_key')
        : (($property && $property->stripe_publish_key)
        ? $property->stripe_publish_key
        : \ModelHelper::getDataFromSetting('stripe_publish_key'));

    $instant       = $isPmsCheckout ? true : (bool) $property->instant_booking_enabled;
    $gatewayCount  = count($enabledGateways);

    // Phase 1 split: mandatory PropertyFees auto-charge, optional ones become checkboxes here.
    $mandatoryFees = $allFees->where('is_mandatory', true);
    $optionalFees  = $allFees->where('is_mandatory', false);

    // Source dropdown options — guests pick one; saved on booking_requests.source.
    $sourceOptions = [
        ''                => '— Select —',
        'google'          => 'Google',
        'social'          => 'Social Media',
        'friend'          => 'Friend referral',
        'returning_guest' => 'Returning guest',
        'other'           => 'Other',
    ];

    // Property hero image for the right-column preview.
    $propertyName = $property->name ?? 'this property';
    $previewImage = $checkoutPreviewImage
        ?? ($property->banner_image ?: ($property->feature_image ?: ($property->image ?? null)));
    $propertyUrl = $checkoutPropertyUrl ?? url($property->seo_url);
    $propertyTypeLabel = $checkoutPropertyType ?? ($property->type ?? $property->property_type ?? 'Stay');
    $quoteCurrency = $checkoutCurrency ?? ($property->currency ?? 'USD');
    $maxGuests = $isPmsCheckout
        ? max((int) ($property->max_people ?? $property->accommodates ?? 1), 1)
        : max((int) $property->sleeps, (int) $property->no_of_guest, 1);
    $petsAllowed = $isPmsCheckout
        ? ((bool) ($property->pets_allowed ?? false) && (int) ($property->max_pets ?? 0) > 0)
        : ($property->accepts_pets && (int)$property->max_pet > 0);
    $maxPets = $isPmsCheckout ? (int) ($property->max_pets ?? 0) : (int) $property->max_pet;
    $minStayValue = $isPmsCheckout ? (int) ($property->custom_min_stay ?: ($property->min_stay ?? 0)) : (int) ($property->min_stay ?? 0);
    $maxStayValue = $isPmsCheckout ? null : ($property->max_stay ?? null);
    $rentalAgreementRequired = (int) ($property->rental_agreement_required ?? 0) === 1;
    $termsUrl = url('terms-of-service');
    $privacyUrl = url('privacy-policy');
    $policiesUrl = $propertyUrl . '#policies';
@endphp

@section('content')

<section class="checkout-topbar">
    <div class="container">
        <a href="{{ $propertyUrl }}"
           class="checkout-topbar__back" aria-label="Back to property">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="19" y1="12" x2="5" y2="12"/>
                <polyline points="12 19 5 12 12 5"/>
            </svg>
            <span>Back to {{ $propertyName }}</span>
        </a>
        <div class="checkout-topbar__title">
            <h1>{{ $cms->name ?? 'Confirm and pay' }}</h1>
            <p class="checkout-topbar__sub">Review your trip and complete your booking.</p>
        </div>
    </div>
</section>

@if(!$quote->valid)
    <div class="container" style="margin: 24px auto; max-width: 720px;">
        <div class="checkout-error-card">
            <strong>We can't price this stay:</strong>
            <ul>
                @foreach($quote->errors as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
            <p>
                <a href="{{ $propertyUrl }}">
                    &larr; Back to {{ $propertyName }}
                </a>
            </p>
        </div>
    </div>
@else

<main class="booking-main container">

    <form id="checkout-form" method="POST" action="{{ $isPmsCheckout ? route('pms.save-booking-data') : url('save-booking-data') }}" onsubmit="return false;"
          data-checkout-mode="{{ $checkoutMode }}"
          data-quote-url="{{ $isPmsCheckout ? route('pms-quote') : url('api/booking/quote') }}"
          data-property-id="{{ $property->id }}"
          data-property-slug="{{ $property->seo_url }}">
        @csrf

        {{-- Hidden context — kept in sync by the right-column summary's live-quote JS. --}}
        <input type="hidden" name="property_id"     value="{{ $property->id }}">
        <input type="hidden" name="check_in"        id="form-check-in"   value="{{ $context->check_in }}">
        <input type="hidden" name="check_out"       id="form-check-out"  value="{{ $context->check_out }}">
        <input type="hidden" name="adults"          id="form-adults"     value="{{ $context->adults }}">
        <input type="hidden" name="children"        id="form-children"   value="{{ $context->children }}">
        <input type="hidden" name="pets"            id="form-pets"       value="{{ $context->pets }}">
        <input type="hidden" name="addons"          id="form-addons"     value='@json($addons)'>
        <input type="hidden" name="coupon_code"     id="form-coupon"     value="">
        <input type="hidden" name="idempotency_key" id="idempotency_key" value="">
        {{-- Concat fields — JS keeps them in sync with first/last name and phone parts so legacy code keeps working. --}}
        <input type="hidden" name="name"   id="form-name">
        <input type="hidden" name="mobile" id="form-mobile">
        @if($isPmsCheckout)
            <input type="hidden" name="pms_ajax" value="1">
            <input type="hidden" name="pms_room_db_id" value="{{ $property->id }}">
            <input type="hidden" name="checkin" value="{{ $context->check_in }}">
            <input type="hidden" name="checkout" value="{{ $context->check_out }}">
            <input type="hidden" name="child" value="{{ $context->children }}">
            <input type="hidden" name="pet" value="{{ $context->pets }}">
            <input type="hidden" name="request_id" value="">
            <input type="hidden" name="discount_coupon" value="{{ $pmsCheckoutPayload['discount_coupon'] ?? '' }}">
            <input type="hidden" name="optional_services" id="form-pms-optional-services" value='{{ $pmsCheckoutPayload['optional_services'] ?? '{}' }}'>
            <input type="hidden" name="requested_services" value='{{ $pmsCheckoutPayload['requested_services'] ?? '{}' }}'>
        @endif

        <div class="booking-layout">

            {{-- ============== LEFT COLUMN ============== --}}
            <div class="checkout-sections">

                <h2 class="section-title">Trip information</h2>
                <div class="checkout-card">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Dates</label>
                            <input type="text" id="trip-info-dates" readonly>
                        </div>
                        <div class="form-group">
                            <label>Guests</label>
                            <input type="text" id="trip-info-guests" readonly>
                        </div>
                    </div>
                    <p class="form-note">Edit dates or guests in the summary on the right.</p>
                </div>

                @if($instant && ($cardGateway || $paypalEnabled))
                    @php
                        // Phase 4 (2026-05-07) — payment method picker:
                        //   - Owners enable ONE card processor (stripe / authorize / square)
                        //     plus optionally PayPal as an additional option.
                        //   - When both are enabled, the guest gets a 2-card radio.
                        //   - When only one is enabled, that single option is preselected.
                        $defaultMethod = $cardGateway ?: 'paypal';
                    @endphp
                    <h2 class="section-title">{{ ($cardGateway && $paypalEnabled) ? 'Choose how to pay' : 'Payment' }}</h2>
                    <div class="payment-methods" id="payment-methods">

                        @if($cardGateway)
                            <label class="payment-method" data-method="card">
                                <input type="radio" name="payment_method" value="{{ $cardGateway }}"
                                       {{ $defaultMethod === $cardGateway ? 'checked' : '' }}>
                                <div class="payment-method__head">
                                    <span class="payment-method__radio" aria-hidden="true"></span>
                                    <span class="payment-method__title">Credit or debit card</span>
                                    <div class="payment-method__icons">
                                        <img src="https://img.icons8.com/color/48/000000/visa.webp"       alt="Visa"       loading="lazy">
                                        <img src="https://img.icons8.com/color/48/000000/mastercard.webp" alt="Mastercard" loading="lazy">
                                        <img src="https://img.icons8.com/color/48/000000/amex.webp"       alt="Amex"       loading="lazy">
                                        <img src="https://img.icons8.com/color/48/000000/discover.webp"   alt="Discover"   loading="lazy">
                                    </div>
                                </div>
                                <div class="payment-method__body">
                                    @if($cardGateway === 'stripe')
                                        <div id="stripe-card-area">
                                            <label>Card details *</label>
                                            <div id="stripe-card-element"></div>
                                            <div id="stripe-card-errors" role="alert"></div>
                                        </div>
                                    @else
                                        <p class="form-note">You'll be taken to our secure card form when you confirm.</p>
                                    @endif
                                </div>
                            </label>
                        @endif

                        @if($paypalEnabled)
                            <label class="payment-method" data-method="paypal">
                                <input type="radio" name="payment_method" value="paypal"
                                       {{ $defaultMethod === 'paypal' ? 'checked' : '' }}>
                                <div class="payment-method__head">
                                    <span class="payment-method__radio" aria-hidden="true"></span>
                                    <span class="payment-method__title">PayPal</span>
                                    <div class="payment-method__icons">
                                        <img src="https://img.icons8.com/color/48/000000/paypal.webp" alt="PayPal" loading="lazy">
                                    </div>
                                </div>
                                <div class="payment-method__body">
                                    <p class="form-note">You'll be redirected to PayPal to complete your payment securely. After approval you'll come back here.</p>
                                </div>
                            </label>
                        @endif

                    </div>
                @elseif($instant && $gatewayCount === 0)
                    <h2 class="section-title">Payment</h2>
                    <div class="checkout-card payment-card-empty">
                        No payment gateway is currently enabled for this property. Please contact the host directly.
                    </div>
                @elseif(!$instant)
                    {{-- Property is request-only (instant_booking_enabled = 0).
                         No card / PayPal selector — guest's submission becomes a
                         BookingRequest the host approves manually. The Confirm
                         button below already changes its label to
                         "Send Booking Request" via the @if at the button. --}}
                    <h2 class="section-title">How booking works here</h2>
                    <div class="checkout-card request-only-card">
                        <p><strong>This property doesn't accept instant payments.</strong></p>
                        <p>When you submit, the host receives your request and will reply within 24 hours. If they approve, they'll send you a secure payment link to complete the booking — no charge happens now.</p>
                    </div>
                @endif

                <h2 class="section-title">Contact information</h2>
                <div class="checkout-card">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="firstName">First name *</label>
                            <input type="text" id="firstName" name="first_name" placeholder="First name" required>
                        </div>
                        <div class="form-group">
                            <label for="lastName">Last name *</label>
                            <input type="text" id="lastName" name="last_name" placeholder="Last name" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="phone">Phone number *</label>
                        <div class="phone-input">
                            <select id="countryCode">
                                <option value="+1">US (+1)</option>
                                <option value="+506">CR (+506)</option>
                                <option value="+44">UK (+44)</option>
                                <option value="+91">IN (+91)</option>
                                <option value="+61">AU (+61)</option>
                                <option value="+33">FR (+33)</option>
                                <option value="+49">DE (+49)</option>
                            </select>
                            <input type="tel" id="phone" placeholder="123 456 7890" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="email">Email address *</label>
                        <input type="email" id="email" name="email" placeholder="you@example.com" required>
                    </div>
                    <div class="form-group">
                        <label for="source">How did you hear about us?</label>
                        <select id="source" name="source">
                            @foreach($sourceOptions as $val => $label)
                                <option value="{{ $val }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="message">Message (optional)</label>
                        <textarea id="message" name="message" rows="3" placeholder="Anything we should know about your stay?"></textarea>
                    </div>
                    <p class="form-note">The booking confirmation will be sent to this email.</p>
                </div>

                <div class="complete-booking">
                    {{-- Phase 4 — at checkout time the booking row doesn't
                         exist yet (it's created on submit), so we can't link
                         to a per-booking signing page from here. The current
                         model is therefore:
                           • Required + pre-payment mode  → consent checkbox
                             at checkout (current behaviour preserved); the
                             actual signing pad lives at the signing page
                             which the email-shell links to from the booking-
                             confirmed mail and the dedicated magic-link.
                           • Required + post-confirmation mode → consent
                             checkbox here too; ModelHelper auto-fires the
                             magic-link "please sign" email once the booking
                             confirms.
                           • Not required → standard terms checkbox.
                         The label adapts to tell the guest what to expect. --}}
                    @php
                        $signMode = $rentalAgreementRequired ? $property->effectiveSigningMode() : 'post_confirmation';
                    @endphp
                    @if($rentalAgreementRequired)
                        <div class="terms-checkbox">
                            <input type="checkbox" id="terms" name="rental_agreement_accepted" required>
                            <label for="terms">
                                By completing this booking you agree to sign the
                                <strong>Rental Agreement</strong>, the Privacy Policy and the House Rules.
                                @if($signMode === 'post_confirmation')
                                    <small class="text-muted d-block mt-1">After payment we'll email you a secure link to sign the agreement before your check-in.</small>
                                @else
                                    <small class="text-muted d-block mt-1">You'll be asked to sign the rental agreement before your check-in.</small>
                                @endif
                            </label>
                        </div>
                    @else
                        <div class="terms-checkbox">
                            <input type="checkbox" id="terms" required>
                            <label for="terms">By completing this booking you agree to the
                                <a href="{{ $termsUrl }}" target="_blank" rel="noopener">Terms and Conditions</a>,
                                <a href="{{ $privacyUrl }}" target="_blank" rel="noopener">Privacy Policy</a> and <a href="{{ $policiesUrl }}" target="_blank" rel="noopener">House Rules</a>.</label>
                        </div>
                    @endif

                    <x-spam-protection form="booking" />

                    <div id="checkout-error" class="checkout-inline-error" hidden></div>

                    <button type="submit" id="checkout-submit"
                            class="btn-confirm"
                            data-instant="{{ $instant ? '1' : '0' }}"
                            data-instant-url="{{ $isPmsCheckout ? route('pms.save-booking-data') : url('save-booking-data') }}"
                            data-request-url="{{ url('booking/request') }}"
                            data-total="{{ number_format((float)$quote->total, 2, '.', '') }}">
                        @if($instant && $gatewayCount > 0)
                            <span class="btn-confirm__label">Confirm and Pay</span>
                            <span class="btn-confirm__amount" id="confirm-amount">${{ number_format((float)$quote->total, 2) }}</span>
                        @else
                            <span class="btn-confirm__label">Send Booking Request</span>
                        @endif
                    </button>
                </div>
            </div>

            {{-- ============== RIGHT COLUMN: Airbnb-style live summary card ============== --}}
            <aside class="booking-sidebar">
                <div class="summary-card" id="summary-card"
                     data-property-id="{{ $property->id }}"
                     data-max-guests="{{ $maxGuests }}"
                     data-max-pets="{{ $maxPets }}">

                    <div class="property-preview">
                        @if($previewImage)
                            <img src="{{ Str::startsWith($previewImage, 'http') ? $previewImage : asset($previewImage) }}"
                                 alt="{{ $propertyName }}" loading="lazy">
                        @endif
                        <div class="property-info">
                            <h3>{{ ucfirst($propertyTypeLabel) }}</h3>
                            <p>{{ $propertyName }}</p>
                        </div>
                    </div>

                    <div class="policy-note">
                        <p>{{ $cancelText ?: 'Standard cancellation policy applies.' }}</p>
                    </div>

                    {{-- Editable dates ----------------------------------------- --}}
                    <div class="summary-section">
                        <div class="date-inputs summary-dates">
                            <div class="date-box">CHECK-IN<br>
                                <input type="text" id="summary-check-in" readonly
                                       placeholder="Add date"
                                       data-vr-datepicker="range-blocked"
                                       data-end-input="#summary-check-out"
                                       data-blocked='@json($widgetBlocked["blocked"] ?? [])'
                                       data-no-checkin='@json($widgetBlocked["checkin"] ?? [])'
                                       data-no-checkout='@json($widgetBlocked["checkout"] ?? [])'
                                       data-min-nights="{{ $minStayValue }}"
                                       data-max-nights="{{ $maxStayValue ?? '' }}"
                                       value="{{ $context->check_in }}">
                            </div>
                            <div class="date-box">CHECKOUT<br>
                                <input type="text" id="summary-check-out" readonly
                                       placeholder="Add date"
                                       value="{{ $context->check_out }}">
                            </div>
                        </div>
                        @php
                            $minStayHint = (int) $minStayValue;
                            $maxStayHint = (int) ($maxStayValue ?? 0);
                            $stayHintParts = [];
                            if ($minStayHint > 0) $stayHintParts[] = 'Min ' . $minStayHint . ' night' . ($minStayHint === 1 ? '' : 's');
                            if ($maxStayHint > 0) $stayHintParts[] = 'Max ' . $maxStayHint . ' night' . ($maxStayHint === 1 ? '' : 's');
                        @endphp
                        @if(!empty($stayHintParts))
                            <p class="stay-limits-hint">{{ implode(' · ', $stayHintParts) }}</p>
                        @endif
                    </div>

                    {{-- Editable guests popover -------------------------------- --}}
                    <div class="summary-section">
                        <div class="guests-selector" id="summary-guests-selector">
                            <button type="button" class="guests-trigger" id="summary-guests-trigger"
                                    aria-haspopup="true" aria-expanded="false"
                                    aria-controls="summary-guests-popover">
                                <span class="guests-trigger-label">GUESTS</span>
                                <span class="guests-trigger-value" id="summary-guests-trigger-value">{{ max(1,(int)$context->adults) }} guest{{ max(1,(int)$context->adults) === 1 ? '' : 's' }}</span>
                            </button>
                            <div class="guests-popover" id="summary-guests-popover" role="dialog" hidden>
                                <div class="guests-row" data-target="adults">
                                    <div class="guests-row__label">
                                        <strong>Adults</strong><small>Ages 13+</small>
                                    </div>
                                    <div class="stepper">
                                        <button type="button" class="stepper-btn" data-action="dec" aria-label="Decrease adults">–</button>
                                        <span class="stepper-value" id="summary-adults-value">{{ max(1,(int)$context->adults) }}</span>
                                        <button type="button" class="stepper-btn" data-action="inc" aria-label="Increase adults">+</button>
                                    </div>
                                </div>
                                <div class="guests-row" data-target="children">
                                    <div class="guests-row__label">
                                        <strong>Children</strong><small>Ages 2–12</small>
                                    </div>
                                    <div class="stepper">
                                        <button type="button" class="stepper-btn" data-action="dec" aria-label="Decrease children">–</button>
                                        <span class="stepper-value" id="summary-children-value">{{ (int)($context->children ?? 0) }}</span>
                                        <button type="button" class="stepper-btn" data-action="inc" aria-label="Increase children">+</button>
                                    </div>
                                </div>
                                @if($petsAllowed)
                                <div class="guests-row" data-target="pets">
                                    <div class="guests-row__label">
                                        <strong>Pets</strong><small>Up to {{ $maxPets }}</small>
                                    </div>
                                    <div class="stepper">
                                        <button type="button" class="stepper-btn" data-action="dec" aria-label="Decrease pets">–</button>
                                        <span class="stepper-value" id="summary-pets-value">{{ (int)$context->pets }}</span>
                                        <button type="button" class="stepper-btn" data-action="inc" aria-label="Increase pets">+</button>
                                    </div>
                                </div>
                                @endif
                                <button type="button" class="guests-popover-close" id="summary-guests-popover-close">Close</button>
                            </div>
                            {{-- Editor-side hidden inputs (driven by steppers, mirrored into form-* hiddens by JS). --}}
                            <input type="hidden" id="summary-adults"   value="{{ max(1,(int)$context->adults) }}">
                            <input type="hidden" id="summary-children" value="{{ (int)($context->children ?? 0) }}">
                            @if($petsAllowed)
                                <input type="hidden" id="summary-pets" value="{{ (int)$context->pets }}">
                            @endif
                        </div>
                    </div>

                    {{-- Optional addons ---------------------------------------- --}}
                    @if($optionalFees->count() > 0)
                        <div class="summary-section">
                            <h4 class="summary-section__title">Add-ons</h4>
                            @foreach($optionalFees as $fee)
                                <label class="summary-addon">
                                    <input type="checkbox" class="summary-addon-checkbox" data-fee-id="{{ $fee->id }}"
                                           {{ in_array($fee->id, $addons['fees'] ?? []) ? 'checked' : '' }}>
                                    <span class="summary-addon__label">{{ $fee->name }}</span>
                                    <span class="summary-addon__rate">${{ number_format($fee->rate, 2) }}{{ $fee->billing_type === 'per_day' ? ' / night' : '' }}</span>
                                </label>
                            @endforeach
                        </div>
                    @endif

                    {{-- Coupon — hidden when the Coupons feature is off ---------- --}}
                    @if(\App\Helper\FeatureFlags::enabled('coupons'))
                    <div class="summary-section">
                        <details class="summary-coupon" id="summary-coupon-block">
                            <summary>Have a coupon code?</summary>
                            <div class="summary-coupon__row" id="summary-coupon-input-row">
                                <input type="text" id="summary-coupon-code" placeholder="ENTER CODE" autocomplete="off" maxlength="40">
                                <button type="button" id="summary-coupon-apply">Apply</button>
                            </div>
                            <div id="summary-coupon-msg" class="summary-coupon__msg" hidden></div>
                            <div id="summary-coupon-applied" class="summary-coupon__applied" hidden>
                                <span><strong id="summary-coupon-applied-code">—</strong> applied</span>
                                <button type="button" id="summary-coupon-remove">Remove</button>
                            </div>
                        </details>
                    </div>
                    @endif

                    {{-- Live price breakdown ---------------------------------- --}}
                    <div class="price-breakdown" id="summary-breakdown">
                        @foreach($quote->breakdown as $line)
                            <div class="price-row">
                                <span>{{ $line['label'] }}</span>
                                <span>${{ number_format((float)$line['amount'], 2) }}</span>
                            </div>
                        @endforeach
                        @if($quote->los_discount && ($quote->los_discount['amount'] ?? 0) > 0)
                            <div class="price-row price-row--discount">
                                <span>{{ $quote->los_discount['label'] }} ({{ $quote->los_discount['percent'] }}%)</span>
                                <span>−${{ number_format((float)$quote->los_discount['amount'], 2) }}</span>
                            </div>
                        @endif
                        @if($quote->coupon_discount && ($quote->coupon_discount['amount'] ?? 0) > 0)
                            <div class="price-row price-row--discount">
                                <span>Coupon ({{ $quote->coupon_discount['code'] }})</span>
                                <span>−${{ number_format((float)$quote->coupon_discount['amount'], 2) }}</span>
                            </div>
                        @endif
                        @if($quote->tax > 0)
                            <div class="price-row">
                                <span>Tax</span>
                                <span>${{ number_format((float)$quote->tax, 2) }}</span>
                            </div>
                        @endif
                        @if($quote->damage_deposit > 0)
                            <div class="price-row">
                                <span>Refundable deposit</span>
                                <span>${{ number_format((float)$quote->damage_deposit, 2) }}</span>
                            </div>
                        @endif
                    </div>

                    <div class="total-row">
                        <span class="total-label">Total ({{ strtoupper($quoteCurrency) }})</span>
                        <span class="total-amount" id="summary-total">${{ number_format((float)$quote->total, 2) }}</span>
                    </div>

                    @php($initialInstallments = $quote->installments ?? [])
                    <div class="installments-card" id="summary-installments" @if(empty($initialInstallments)) hidden @endif>
                        <strong>Payment schedule</strong>
                        <ul>
                            @foreach($initialInstallments as $inst)
                                <li>{{ \Carbon\Carbon::parse($inst['due_date'])->format('M j, Y') }} — ${{ number_format((float)$inst['amount'], 2) }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </aside>

        </div>
    </form>
</main>

@endif

@endsection

@section('scripts')
@if(in_array('stripe', $enabledGateways) && $stripePublishableKey)
<script src="https://js.stripe.com/v3/"></script>
@endif

<script>
(function () {
    'use strict';

    // ============================================================
    // Back-navigation guard. If the guest just confirmed a booking
    // and hits Back, sessionStorage carries a breadcrumb dropped on
    // the booking-confirmed page. We bounce them to home before
    // anything else loads — they shouldn't be back on a stale form
    // for a paid booking. Cleared on bounce so a future legitimate
    // booking starts clean.
    //
    // pageshow handler covers the bfcache case: Safari / Chrome /
    // Firefox can serve this page from their back-forward cache
    // without re-running this IIFE, so we ALSO listen on pageshow
    // (event.persisted=true on a bfcache restore) and re-check the
    // breadcrumb at that point.
    // ============================================================
    var safeUrl = @json(url('/'));
    // Only bounce if a confirmation happened in the last 5 minutes. The breadcrumb
    // is set on booking-confirmed (with a millisecond timestamp) to catch an
    // immediate Back press. Without an expiry it persisted for the tab's entire
    // lifetime, so a guest who finished a booking, kept the tab open, and tried
    // another booking later would be bounced home on their first checkout load,
    // then proceed normally on the second try (because the bounce cleared the
    // crumb). 5 min comfortably covers the legit Back-press window without
    // tripping later sessions.
    var BREADCRUMB_TTL_MS = 5 * 60 * 1000;
    function bounceIfJustBooked() {
        try {
            var raw = sessionStorage.getItem('booking_completed');
            if (!raw) return false;
            var ts = parseInt(raw, 10);
            // Always remove the crumb on first read — it has done its job (or is stale).
            sessionStorage.removeItem('booking_completed');
            if (!isNaN(ts) && Date.now() - ts < BREADCRUMB_TTL_MS) {
                window.location.replace(safeUrl);
                return true;
            }
        } catch (_) {}
        return false;
    }
    if (bounceIfJustBooked()) return;
    window.addEventListener('pageshow', function (event) {
        if (event.persisted) bounceIfJustBooked();
    });

    // ============================================================
    // Existing payment dispatcher — preserved verbatim from prior
    // checkout.blade.php; Phase 3 just rewrapped the markup around
    // it. Real-money path; do not refactor without a smoke test.
    // ============================================================
    function uuidv4() {
        return ([1e7]+-1e3+-4e3+-8e3+-1e11).replace(/[018]/g, function (c) {
            return (c ^ (crypto.getRandomValues(new Uint8Array(1))[0] & (15 >> (c / 4)))).toString(16);
        });
    }

    var form = document.getElementById('checkout-form');
    if (!form) return;
    var checkoutMode = form.dataset.checkoutMode || 'direct';
    var isPmsCheckout = checkoutMode === 'pms';

    var idemField = document.getElementById('idempotency_key');
    if (idemField && !idemField.value) idemField.value = uuidv4();

    var btn      = document.getElementById('checkout-submit');
    var errBox   = document.getElementById('checkout-error');
    var cardArea = document.getElementById('stripe-card-area');
    var cardErr  = document.getElementById('stripe-card-errors');

    // ============================================================
    // beforeunload guard — once the guest has entered any field,
    // any attempt to leave the page (close tab, hit back, refresh,
    // type a new URL) prompts the browser's native "Leave / Stay"
    // dialog. Browsers no longer honour custom text — they show
    // their own generic message. Disabled the moment we start a
    // real submission so the redirect after payment is silent.
    // ============================================================
    var formDirty = false;
    function markDirty() { formDirty = true; }
    function clearDirty() { formDirty = false; }
    ['input', 'change'].forEach(function (evt) {
        form.addEventListener(evt, markDirty, true);
    });
    window.addEventListener('beforeunload', function (e) {
        if (!formDirty) return undefined;
        e.preventDefault();
        // Empty string is the legacy contract — required by Chrome / Edge
        // for the dialog to actually trigger. Modern browsers ignore the
        // string content and show their own message.
        e.returnValue = '';
        return '';
    });

    var stripe = null;
    var card   = null;
    var stripePk = @json($stripePublishableKey ?? '');

    if (window.Stripe && stripePk && document.getElementById('stripe-card-element')) {
        stripe = window.Stripe(stripePk);
        var elements = stripe.elements();
        card = elements.create('card', {
            style: {
                base: { fontSize: '15px', color: '#222', '::placeholder': { color: '#999' } },
                invalid: { color: '#c33' }
            }
        });
        card.mount('#stripe-card-element');
        card.on('change', function (event) {
            if (cardErr) cardErr.textContent = (event.error && event.error.message) || '';
        });
    }

    // Phase 4 — payment method selector. Toggling the radio:
    //   1. Puts an .is-selected class on the active card (CSS handles the visual).
    //   2. Hides every other card's body so only the selected method's content shows.
    //   3. Updates the Confirm button label: "Confirm and Pay $X" for card,
    //      "Continue to PayPal" when PayPal is selected.
    var paymentMethodsEl = document.getElementById('payment-methods');
    var confirmLabelEl   = btn ? btn.querySelector('.btn-confirm__label') : null;
    var confirmAmountElInBtn = btn ? btn.querySelector('.btn-confirm__amount') : null;
    var btnIsInstant     = btn ? btn.getAttribute('data-instant') === '1' : false;
    function syncPaymentMethod() {
        var checked = form.querySelector('input[name="payment_method"]:checked')
                   || form.querySelector('input[name="payment_method"]');
        var method  = checked ? checked.value : null;

        if (paymentMethodsEl) {
            Array.prototype.forEach.call(paymentMethodsEl.querySelectorAll('.payment-method'), function (label) {
                var input = label.querySelector('input[name="payment_method"]');
                label.classList.toggle('is-selected', !!(input && input.checked));
            });
        }
        if (cardArea) {
            // Stripe Elements only mounts inside the card option — hide it
            // entirely whenever the card option isn't the active one.
            cardArea.style.display = (method === 'stripe') ? '' : 'none';
        }
        // Only swap the Confirm button copy on properties that take instant
        // payment. Request-only properties keep "Send Booking Request" — set
        // by the Blade and not touched here.
        if (btnIsInstant && confirmLabelEl) {
            confirmLabelEl.textContent = (method === 'paypal') ? 'Continue to PayPal' : 'Confirm and Pay';
        }
        if (btnIsInstant && confirmAmountElInBtn) {
            // PayPal redirect doesn't need the inline amount badge — the PayPal
            // page shows the total. Hide the badge to keep the button clean.
            confirmAmountElInBtn.style.display = (method === 'paypal') ? 'none' : '';
        }
    }
    Array.prototype.forEach.call(
        form.querySelectorAll('input[name="payment_method"]'),
        function (el) { el.addEventListener('change', syncPaymentMethod); }
    );
    syncPaymentMethod();

    function showError(msg) {
        errBox.textContent = msg;
        errBox.removeAttribute('hidden');
    }
    function clearError() {
        errBox.textContent = '';
        errBox.setAttribute('hidden', '');
    }

    function setHidden(name, value) {
        var el = form.querySelector('input[type="hidden"][data-injected="' + name + '"]')
            || form.querySelector('input[type="hidden"][name="' + name + '"]');
        if (!el) {
            el = document.createElement('input');
            el.type = 'hidden';
            el.name = name;
            el.setAttribute('data-injected', name);
            form.appendChild(el);
        }
        el.value = value;
    }

    // ============================================================
    // Phase 3 — concat fields: keep the legacy `name` and `mobile`
    // hidden inputs synced from first/last name + countryCode/phone.
    // Backend continues to read `name` and `mobile`, so old reports,
    // emails and exports keep working.
    // ============================================================
    var firstNameEl = document.getElementById('firstName');
    var lastNameEl  = document.getElementById('lastName');
    var hiddenName  = document.getElementById('form-name');
    function syncName() {
        var first = (firstNameEl.value || '').trim();
        var last  = (lastNameEl.value  || '').trim();
        hiddenName.value = (first + ' ' + last).trim();
    }
    firstNameEl.addEventListener('input', syncName);
    lastNameEl.addEventListener('input',  syncName);
    syncName();

    var ccEl     = document.getElementById('countryCode');
    var phoneEl  = document.getElementById('phone');
    var hiddenMob = document.getElementById('form-mobile');
    function syncMobile() {
        var cc    = (ccEl.value || '').trim();
        var phone = (phoneEl.value || '').trim();
        hiddenMob.value = phone ? (cc + ' ' + phone).trim() : '';
    }
    ccEl.addEventListener('change', syncMobile);
    phoneEl.addEventListener('input', syncMobile);
    syncMobile();

    // ============================================================
    // Phase 3 — Airbnb-style live summary card on the right.
    // Wires guests popover, addon checkboxes, coupon Apply, and
    // a debounced re-quote against /api/booking/quote that updates
    // the breakdown + total + hidden form context fields in place.
    // ============================================================
    var quoteUrl     = form.dataset.quoteUrl;
    var propertyId   = parseInt(form.dataset.propertyId, 10);
    var summaryCard  = document.getElementById('summary-card');
    var maxGuests    = parseInt(summaryCard.dataset.maxGuests || '8', 10);
    var maxPets      = parseInt(summaryCard.dataset.maxPets   || '0', 10);

    var summary = {
        check_in:  document.getElementById('summary-check-in'),
        check_out: document.getElementById('summary-check-out'),
        adults:    document.getElementById('summary-adults'),
        children:  document.getElementById('summary-children'),
        pets:      document.getElementById('summary-pets'),
    };
    var hiddens = {
        check_in:  document.getElementById('form-check-in'),
        check_out: document.getElementById('form-check-out'),
        adults:    document.getElementById('form-adults'),
        children:  document.getElementById('form-children'),
        pets:      document.getElementById('form-pets'),
        addons:    document.getElementById('form-addons'),
        coupon:    document.getElementById('form-coupon'),
    };
    var breakdownEl     = document.getElementById('summary-breakdown');
    var totalEl         = document.getElementById('summary-total');
    var confirmAmountEl = document.getElementById('confirm-amount');
    var tripDates       = document.getElementById('trip-info-dates');
    var tripGuests      = document.getElementById('trip-info-guests');
    var couponInput        = document.getElementById('summary-coupon-code');
    var couponApplyBtn     = document.getElementById('summary-coupon-apply');
    var couponMsgEl        = document.getElementById('summary-coupon-msg');
    var couponInputRow     = document.getElementById('summary-coupon-input-row');
    var couponAppliedRow   = document.getElementById('summary-coupon-applied');
    var couponAppliedCode  = document.getElementById('summary-coupon-applied-code');
    var couponRemoveBtn    = document.getElementById('summary-coupon-remove');
    var clearDatesBtn      = document.getElementById('summary-clear-dates');

    // Track the most recently *successfully applied* coupon code so the JS
    // can distinguish "user is typing" from "coupon is active".
    var activeCoupon = (form.querySelector('input[name="discount_coupon"]') || {}).value || '';

    var quoteTimer = null;

    function n(v, d) {
        var x = parseInt(v, 10);
        return isNaN(x) ? d : x;
    }

    function gatherFees() {
        return Array.from(document.querySelectorAll('.summary-addon-checkbox:checked'))
            .map(function (el) { return parseInt(el.dataset.feeId, 10); })
            .filter(function (x) { return x > 0; });
    }

    function fmtMoney(amt) {
        return '$' + parseFloat(amt).toFixed(2);
    }

    function fmtDate(ymd) {
        if (!ymd) return '';
        var d = new Date(ymd + 'T00:00:00');
        return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
    }

    function refreshTripInfoLabel() {
        var ci = summary.check_in.value;
        var co = summary.check_out.value;
        if (ci && co) {
            tripDates.value = fmtDate(ci) + ' — ' + fmtDate(co);
        } else {
            tripDates.value = '';
            tripDates.placeholder = 'Add dates';
        }

        var a = n(summary.adults.value, 1);
        var c = summary.children ? n(summary.children.value, 0) : 0;
        var p = summary.pets ? n(summary.pets.value, 0) : 0;
        var parts = [];
        var totalGuests = a + c;
        parts.push(totalGuests + ' guest' + (totalGuests === 1 ? '' : 's'));
        if (c > 0) parts.push(c + ' child' + (c === 1 ? '' : 'ren'));
        if (p > 0) parts.push(p + ' pet' + (p === 1 ? '' : 's'));
        tripGuests.value = parts.join(', ');
    }

    function updateHiddens() {
        hiddens.check_in.value  = summary.check_in.value;
        hiddens.check_out.value = summary.check_out.value;
        hiddens.adults.value    = summary.adults.value || '1';
        hiddens.children.value  = summary.children ? (summary.children.value || '0') : '0';
        hiddens.pets.value      = summary.pets ? (summary.pets.value || '0') : '0';
        hiddens.addons.value    = JSON.stringify({ fees: gatherFees() });
        // Backend reads the *applied* coupon, not whatever's in the input box.
        // Prevents auto-applying half-typed codes on every keystroke.
        hiddens.coupon.value    = activeCoupon;
    }

    function showCouponMsg(text, kind) {
        if (!couponMsgEl) return;
        couponMsgEl.textContent = text;
        couponMsgEl.classList.remove('is-success', 'is-error');
        if (kind) couponMsgEl.classList.add('is-' + kind);
        couponMsgEl.removeAttribute('hidden');
    }
    function hideCouponMsg() {
        if (couponMsgEl) couponMsgEl.setAttribute('hidden', '');
    }
    function setCouponApplied(code) {
        activeCoupon = (code || '').toUpperCase();
        // Coupon UI may be absent (Coupons feature disabled) — the quote
        // refresh handler still calls this, so bail out after tracking state.
        if (!couponAppliedRow || !couponInputRow) return;
        if (activeCoupon) {
            couponAppliedCode.textContent = activeCoupon;
            couponAppliedRow.removeAttribute('hidden');
            couponInputRow.setAttribute('hidden', '');
            hideCouponMsg();
        } else {
            couponAppliedRow.setAttribute('hidden', '');
            couponInputRow.removeAttribute('hidden');
        }
    }

    function renderQuote(q) {
        // Build breakdown rows from the API response.
        var html = '';
        for (var i = 0; i < q.breakdown.length; i++) {
            var line = q.breakdown[i];
            // The API includes some categories already shown elsewhere — skip them here.
            if (line.category === 'damage_deposit') continue;
            html += '<div class="price-row"><span>' + escapeHtml(line.label) + '</span><span>' + fmtMoney(line.amount) + '</span></div>';
        }
        if (q.los_discount && q.los_discount.amount > 0) {
            html += '<div class="price-row price-row--discount"><span>' + escapeHtml(q.los_discount.label) + ' (' + q.los_discount.percent + '%)</span><span>−' + fmtMoney(q.los_discount.amount) + '</span></div>';
        }
        if (q.coupon_discount && q.coupon_discount.amount > 0) {
            html += '<div class="price-row price-row--discount"><span>Coupon (' + escapeHtml(q.coupon_discount.code) + ')</span><span>−' + fmtMoney(q.coupon_discount.amount) + '</span></div>';
        }
        if (q.tax > 0) {
            html += '<div class="price-row"><span>Tax</span><span>' + fmtMoney(q.tax) + '</span></div>';
        }
        if (q.damage_deposit > 0) {
            html += '<div class="price-row"><span>Refundable deposit</span><span>' + fmtMoney(q.damage_deposit) + '</span></div>';
        }
        breakdownEl.innerHTML = html;
        totalEl.textContent = fmtMoney(q.total);
        if (confirmAmountEl) confirmAmountEl.textContent = fmtMoney(q.total);
        if (btn) btn.dataset.total = parseFloat(q.total).toFixed(2);

        var installmentsCard = document.getElementById('summary-installments');
        if (installmentsCard) {
            var installments = Array.isArray(q.installments) ? q.installments : [];
            var list = installmentsCard.querySelector('ul');
            if (installments.length && list) {
                list.innerHTML = installments.map(function (inst) {
                    var due = fmtDate(inst.due_date);
                    var label = due || inst.label || ('Installment ' + inst.sequence);
                    return '<li>' + escapeHtml(label) + ' — ' + fmtMoney(inst.amount) + '</li>';
                }).join('');
                installmentsCard.removeAttribute('hidden');
            } else {
                installmentsCard.setAttribute('hidden', '');
            }
        }
    }

    function normalizePmsQuote(data) {
        var tax = 0;
        var breakdown = [];
        (data.line_items || []).forEach(function (line) {
            var type = line.type || 'fee';
            var amount = parseFloat(line.amount || 0);
            if (type === 'tax') {
                tax += amount;
                return;
            }
            if (type === 'deposit') return;
            breakdown.push({
                label: line.label || 'Charge',
                amount: amount,
                category: type
            });
        });

        var installments = (data.scheduled_payments || []).map(function (payment, idx) {
            return {
                due_date: payment.date_due || payment.due_date || payment.date || '',
                amount: parseFloat(payment.amount || 0),
                sequence: idx + 1,
                label: payment.label || 'Payment'
            };
        });

        return {
            breakdown: breakdown,
            total: parseFloat(data.total || 0),
            tax: tax,
            damage_deposit: data.deposit_in_total ? parseFloat(data.security_deposit || 0) : 0,
            installments: installments,
            los_discount: null,
            coupon_discount: null
        };
    }

    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, function (m) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m];
        });
    }

    function refreshQuote() {
        updateHiddens();
        refreshTripInfoLabel();
        if (clearDatesBtn) {
            if (summary.check_in.value || summary.check_out.value) clearDatesBtn.removeAttribute('hidden');
            else clearDatesBtn.setAttribute('hidden', '');
        }

        if (!summary.check_in.value || !summary.check_out.value) return;

        var payload = isPmsCheckout ? {
            room_id: propertyId,
            checkin: summary.check_in.value,
            checkout: summary.check_out.value,
            adults: n(summary.adults.value, 1),
            children: summary.children ? n(summary.children.value, 0) : 0,
            pets: summary.pets ? n(summary.pets.value, 0) : 0,
            optional_services: (document.getElementById('form-pms-optional-services') || {}).value || '{}'
        } : {
            property_id: propertyId,
            check_in:    summary.check_in.value,
            check_out:   summary.check_out.value,
            adults:      n(summary.adults.value, 1),
            children:    summary.children ? n(summary.children.value, 0) : 0,
            pets:        summary.pets ? n(summary.pets.value, 0) : 0,
            addons:      { fees: gatherFees() },
        };
        // Payload uses the *applied* coupon (not what's currently in the input
        // box) — typing/erasing characters mid-flight shouldn't bounce errors.
        if (activeCoupon) {
            if (isPmsCheckout) payload.promo_code = activeCoupon;
            else payload.coupon_code = activeCoupon;
        }
        var pendingCoupon = window.__pendingCoupon || null; // set by the Apply button only

        if (pendingCoupon) {
            if (isPmsCheckout) payload.promo_code = pendingCoupon;
            else payload.coupon_code = pendingCoupon;
        }

        fetch(quoteUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            credentials: 'same-origin',
            body: JSON.stringify(payload),
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (isPmsCheckout) {
                if (!data.ok) {
                    showError(data.error || 'Unable to compute quote');
                    if (pendingCoupon) {
                        couponApplyBtn.disabled = false;
                        couponApplyBtn.textContent = 'Apply';
                        showCouponMsg(data.error || 'Coupon could not be applied.', 'error');
                    }
                    return;
                }
                clearError();
                if (pendingCoupon) {
                    activeCoupon = pendingCoupon;
                    setCouponApplied(activeCoupon);
                    showCouponMsg('Coupon applied.', 'success');
                    couponApplyBtn.disabled = false;
                    couponApplyBtn.textContent = 'Apply';
                }
                renderQuote(normalizePmsQuote(data));
                return;
            }

            if (!data.valid) {
                var errs = data.errors || ['Unable to compute quote'];
                var couponErrs = errs.filter(function (m) { return /coupon/i.test(String(m)); });
                var otherErrs  = errs.filter(function (m) { return !/coupon/i.test(String(m)); });

                if (pendingCoupon) {
                    // The user just hit Apply — bind any coupon-flavoured errors to that input.
                    showCouponMsg(couponErrs.join('. ') || 'Coupon could not be applied.', 'error');
                    activeCoupon = ''; // failed apply → no active coupon
                    setCouponApplied('');
                    couponApplyBtn.disabled = false;
                    couponApplyBtn.textContent = 'Apply';
                } else if (couponErrs.length && activeCoupon) {
                    // The previously-applied coupon stopped being valid (dates moved out of window etc.).
                    showCouponMsg(couponErrs.join('. '), 'error');
                    activeCoupon = '';
                    setCouponApplied('');
                }

                if (otherErrs.length) {
                    showError(otherErrs.join('. '));
                } else if (!couponErrs.length) {
                    showError(errs.join('. '));
                } else {
                    clearError();
                }
                return;
            }

            // Valid response.
            clearError();

            if (pendingCoupon) {
                // Apply succeeded only if the response actually carries a coupon discount.
                if (data.quote.coupon_discount && data.quote.coupon_discount.amount > 0) {
                    activeCoupon = pendingCoupon;
                    setCouponApplied(activeCoupon);
                    showCouponMsg('Coupon applied — −$' + parseFloat(data.quote.coupon_discount.amount).toFixed(2) + ' off.', 'success');
                } else {
                    // Server accepted the request but no discount was granted (e.g.
                    // `min_order_amount` not met but valid otherwise).
                    activeCoupon = '';
                    setCouponApplied('');
                    showCouponMsg('Coupon could not be applied to this stay.', 'error');
                }
                couponApplyBtn.disabled = false;
                couponApplyBtn.textContent = 'Apply';
            }

            renderQuote(data.quote);
        })
        .catch(function () {
            showError('Could not refresh quote. Please try again.');
            if (pendingCoupon) {
                couponApplyBtn.disabled = false;
                couponApplyBtn.textContent = 'Apply';
                showCouponMsg('Network error — please try again.', 'error');
            }
        })
        .finally(function () { window.__pendingCoupon = null; });
    }

    function debouncedRefresh() {
        clearTimeout(quoteTimer);
        quoteTimer = setTimeout(refreshQuote, 250);
    }

    // ----- Date picker change events (jc-datepicker dispatches 'change' on select) -----
    summary.check_in.addEventListener('change',  debouncedRefresh);
    summary.check_out.addEventListener('change', debouncedRefresh);

    if (clearDatesBtn) {
        clearDatesBtn.addEventListener('click', function () {
            if (window.VR_DATEPICKER && window.VR_DATEPICKER.clear) {
                window.VR_DATEPICKER.clear(summary.check_in);
            } else {
                summary.check_in.value = '';
                summary.check_out.value = '';
                summary.check_in.dispatchEvent(new Event('change', { bubbles: true }));
            }
        });
    }

    // ----- Guests popover wiring (mirror of property-detail widget) -----
    var guestsRoot = document.getElementById('summary-guests-selector');
    if (guestsRoot) {
        var trigger    = document.getElementById('summary-guests-trigger');
        var popover    = document.getElementById('summary-guests-popover');
        var closeBtn   = document.getElementById('summary-guests-popover-close');
        var triggerVal = document.getElementById('summary-guests-trigger-value');

        function summaryLabel() {
            var a = n(summary.adults.value, 1);
            var c = summary.children ? n(summary.children.value, 0) : 0;
            var p = summary.pets ? n(summary.pets.value, 0) : 0;
            var totalGuests = a + c;
            var parts = [totalGuests + ' guest' + (totalGuests === 1 ? '' : 's')];
            if (p > 0) parts.push(p + ' pet' + (p === 1 ? '' : 's'));
            triggerVal.textContent = parts.join(', ');
        }

        function update(target, delta) {
            var hidden = summary[target];
            if (!hidden) return;
            var valueEl = document.getElementById('summary-' + target + '-value');
            var next = n(hidden.value, 0) + delta;
            if (target === 'adults') {
                var c = summary.children ? n(summary.children.value, 0) : 0;
                next = Math.max(1, Math.min(maxGuests - c, next));
            } else if (target === 'children') {
                var a = n(summary.adults.value, 1);
                next = Math.max(0, Math.min(maxGuests - a, next));
            } else if (target === 'pets') {
                next = Math.max(0, Math.min(maxPets, next));
            }
            hidden.value = String(next);
            if (valueEl) valueEl.textContent = String(next);
            syncDisabled();
            summaryLabel();
            debouncedRefresh();
        }
        function syncDisabled() {
            var a = n(summary.adults.value, 1);
            var c = summary.children ? n(summary.children.value, 0) : 0;
            var p = summary.pets ? n(summary.pets.value, 0) : 0;
            setRow('adults',   a <= 1, a >= maxGuests - c);
            setRow('children', c <= 0, c >= maxGuests - a);
            if (summary.pets) setRow('pets', p <= 0, p >= maxPets);
        }
        function setRow(target, decDisabled, incDisabled) {
            var row = popover.querySelector('.guests-row[data-target="' + target + '"]');
            if (!row) return;
            row.querySelector('.stepper-btn[data-action="dec"]').disabled = decDisabled;
            row.querySelector('.stepper-btn[data-action="inc"]').disabled = incDisabled;
        }

        trigger.addEventListener('click', function () {
            var open = !popover.hasAttribute('hidden');
            if (open) { popover.setAttribute('hidden', ''); trigger.setAttribute('aria-expanded', 'false'); }
            else      { popover.removeAttribute('hidden'); trigger.setAttribute('aria-expanded', 'true'); syncDisabled(); }
        });
        closeBtn.addEventListener('click', function () {
            popover.setAttribute('hidden', '');
            trigger.setAttribute('aria-expanded', 'false');
        });
        document.addEventListener('click', function (e) {
            if (!guestsRoot.contains(e.target)) {
                popover.setAttribute('hidden', '');
                trigger.setAttribute('aria-expanded', 'false');
            }
        });
        popover.addEventListener('click', function (e) {
            var b = e.target.closest('.stepper-btn');
            if (!b) return;
            var row = b.closest('.guests-row');
            if (!row) return;
            update(row.dataset.target, b.dataset.action === 'inc' ? 1 : -1);
        });

        summaryLabel();
        syncDisabled();
    }

    // ----- Addons + coupon -----
    Array.prototype.forEach.call(document.querySelectorAll('.summary-addon-checkbox'), function (el) {
        el.addEventListener('change', debouncedRefresh);
    });
    if (couponApplyBtn) {
        // Force uppercase as the user types — coupon codes match case-insensitively
        // server-side, but a consistent display avoids confusion.
        couponInput.addEventListener('input', function () {
            var pos = couponInput.selectionStart;
            couponInput.value = couponInput.value.toUpperCase();
            try { couponInput.setSelectionRange(pos, pos); } catch (_) {}
            hideCouponMsg();
        });
        function applyCoupon() {
            var code = (couponInput.value || '').trim().toUpperCase();
            if (!code) {
                showCouponMsg('Enter a code first.', 'error');
                return;
            }
            if (!summary.check_in.value || !summary.check_out.value) {
                showCouponMsg('Pick your dates first — coupons depend on the stay.', 'error');
                return;
            }
            window.__pendingCoupon = code;
            couponApplyBtn.disabled = true;
            couponApplyBtn.textContent = 'Applying…';
            refreshQuote();
        }
        couponApplyBtn.addEventListener('click', applyCoupon);
        couponInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') { e.preventDefault(); applyCoupon(); }
        });
    }
    if (couponRemoveBtn) {
        couponRemoveBtn.addEventListener('click', function () {
            activeCoupon = '';
            couponInput.value = '';
            setCouponApplied('');
            hideCouponMsg();
            refreshQuote();
        });
    }

    // Initial paint of mirrored fields and labels.
    updateHiddens();
    refreshTripInfoLabel();

    // ============================================================
    // Submit handler — preserved from legacy checkout.
    // ============================================================
    btn.addEventListener('click', function (e) {
        e.preventDefault();
        if (!form.checkValidity()) { form.reportValidity(); return; }

        // Belt-and-braces: regardless of how first_name / last_name / phone /
        // countryCode got into the DOM (typed, autofilled, pasted, browser
        // password-manager fill), refresh the legacy `name` and `mobile`
        // hidden inputs right now. The `input` event we listen on isn't
        // guaranteed to fire for autofill, and `booking_requests.name` is
        // NOT-NULL — without this the insert blew up with a 23000.
        syncName();
        syncMobile();

        clearError();
        var instant = btn.getAttribute('data-instant') === '1';
        var checkedMethod = form.querySelector('input[name="payment_method"]:checked')
                         || form.querySelector('input[name="payment_method"]');
        var method = checkedMethod ? checkedMethod.value : null;

        if (instant && method === 'stripe' && isPmsCheckout) {
            if (!stripe || !card) {
                showError('Stripe is not configured. Please refresh or contact support.');
                return;
            }

            setHidden('checkin',      form.querySelector('input[name="check_in"]').value);
            setHidden('checkout',     form.querySelector('input[name="check_out"]').value);
            setHidden('child',        form.querySelector('input[name="children"]').value);
            setHidden('pet',          form.querySelector('input[name="pets"]').value);
            setHidden('request_id',   form.querySelector('input[name="idempotency_key"]').value);
            setHidden('discount_coupon', activeCoupon || '');
            setHidden('pms_ajax', '1');
            setHidden('pms_room_db_id', String(propertyId));

            var origPmsLabel = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = 'Confirming...';

            fetch(btn.getAttribute('data-instant-url') || form.action, {
                method: 'POST',
                body: new FormData(form),
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                credentials: 'same-origin'
            })
            .then(function (resp) { return resp.json().then(function (data) { return { ok: resp.ok, data: data }; }); })
            .then(function (res) {
                if (!res.data || parseInt(res.data.status || 0, 10) !== 200 || !res.data.payment_json_url) {
                    throw new Error((res.data && (res.data.message || res.data.error_message)) || 'Could not prepare payment.');
                }
                btn.innerHTML = 'Securing payment...';
                return fetch(res.data.payment_json_url, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin'
                }).then(function (resp) {
                    return resp.json().then(function (data) {
                        if (!data.ok || !data.client_secret) {
                            var fallbackUrl = res.data.payment_page_url || '';
                            var msg = data.client_secret === null
                                ? 'The price changed while preparing payment. Please review the updated price.'
                                : (data.error || 'Could not initialize payment.');
                            var err = new Error(msg);
                            err.redirectUrl = fallbackUrl;
                            throw err;
                        }
                        data.payment_page_url = res.data.payment_page_url;
                        return data;
                    });
                });
            })
            .then(function (payment) {
                btn.innerHTML = 'Processing...';
                return stripe.confirmCardPayment(payment.client_secret, {
                    payment_method: {
                        card: card,
                        billing_details: {
                            name: hiddenName.value || ((firstNameEl.value || '') + ' ' + (lastNameEl.value || '')).trim(),
                            email: (document.getElementById('email') || {}).value || undefined,
                            phone: hiddenMob.value || undefined
                        }
                    },
                    return_url: payment.return_url
                }).then(function (result) {
                    if (result.error) {
                        throw new Error(result.error.message || 'Payment failed. Please check your card details and try again.');
                    }
                    if (result.paymentIntent && result.paymentIntent.status === 'succeeded') {
                        clearDirty();
                        window.location.href = payment.return_url + '&payment_intent=' + result.paymentIntent.id;
                        return;
                    }
                    throw new Error('Payment could not be confirmed. Please try again or contact support.');
                });
            })
            .catch(function (err) {
                if (err.redirectUrl) {
                    clearDirty();
                    window.location.href = err.redirectUrl;
                    return;
                }
                showError(err.message || 'Sorry, we could not complete your payment. Please try again.');
                btn.disabled = false;
                btn.innerHTML = origPmsLabel;
            });
            return;
        }

        if (instant && method === 'stripe') {
            if (!stripe || !card) {
                showError('Stripe is not configured. Please refresh or contact support.');
                return;
            }
            var origLabel = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = 'Processing...';

            stripe.createToken(card).then(function (result) {
                if (result.error) {
                    if (cardErr) cardErr.textContent = result.error.message || 'Card validation failed.';
                    btn.disabled = false;
                    btn.innerHTML = origLabel;
                    return;
                }
                setHidden('stripeToken',  result.token.id);
                setHidden('total_amount', btn.getAttribute('data-total'));
                setHidden('checkin',      form.querySelector('input[name="check_in"]').value);
                setHidden('checkout',     form.querySelector('input[name="check_out"]').value);
                setHidden('child',        form.querySelector('input[name="children"]').value);
                setHidden('total_pets',   form.querySelector('input[name="pets"]').value);
                setHidden('request_id',   form.querySelector('input[name="idempotency_key"]').value);

                var postUrl = btn.getAttribute('data-instant-url') || form.action;
                var fd = new FormData(form);

                fetch(postUrl, {
                    method: 'POST', body: fd,
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                    credentials: 'same-origin'
                })
                .then(function (resp) { return resp.json().then(function (data) { return { ok: resp.ok, data: data }; }); })
                .then(function (res) {
                    if (res.data && res.data.success && res.data.redirect_url) {
                        clearDirty();   // Don't prompt on the success redirect.
                        window.location.href = res.data.redirect_url;
                        return;
                    }
                    var msg = (res.data && (res.data.error_message || res.data.message)) || 'Sorry, we could not complete your payment. Please try again.';
                    showError(msg);
                    btn.disabled = false;
                    btn.innerHTML = origLabel;
                })
                .catch(function () {
                    showError('Network error. Please try again.');
                    btn.disabled = false;
                    btn.innerHTML = origLabel;
                });
            }).catch(function () {
                showError('Could not contact Stripe. Please try again.');
                btn.disabled = false;
                btn.innerHTML = origLabel;
            });
            return;
        }

        // PayPal / AuthorizeNet / Square: native form submission to /save-booking-data.
        // The backend dispatches to the matching Payment\* controller which builds
        // its own redirect URL (PayPal → paypal.com, Authorize → hosted form, etc).
        // No client-side tokenization needed for these.
        if (instant && method && method !== 'stripe') {
            setHidden('total_amount', btn.getAttribute('data-total'));
            setHidden('checkin',      form.querySelector('input[name="check_in"]').value);
            setHidden('checkout',     form.querySelector('input[name="check_out"]').value);
            setHidden('child',        form.querySelector('input[name="children"]').value);
            setHidden('total_pets',   form.querySelector('input[name="pets"]').value);
            setHidden('request_id',   form.querySelector('input[name="idempotency_key"]').value);

            var origLabel = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = (method === 'paypal') ? 'Redirecting to PayPal…' : 'Processing…';
            clearDirty();   // Don't prompt on the gateway redirect.
            // Native submit lets the browser follow whatever redirect the controller
            // emits (302 → paypal.com or → /booking/payment/authorize/post/{id} etc).
            form.removeAttribute('onsubmit');
            form.submit();
            return;
        }

        var url = btn.getAttribute('data-request-url');
        var origLabel = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = 'Submitting...';

        var fd = new FormData(form);

        fetch(url, {
            method: 'POST', body: fd,
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            credentials: 'same-origin'
        })
        .then(function (resp) { return resp.json().then(function (data) { return { ok: resp.ok, data: data }; }); })
        .then(function (res) {
            if (res.data && res.data.success && res.data.redirect_url) {
                clearDirty();   // Booking-request submitted — no leave-page prompt.
                window.location.href = res.data.redirect_url;
                return;
            }
            var msg = (res.data && (res.data.error_message || res.data.message)) || 'Sorry, we could not complete your request. Please try again.';
            showError(msg);
            btn.disabled = false;
            btn.innerHTML = origLabel;
        })
        .catch(function () {
            showError('Network error. Please try again.');
            btn.disabled = false;
            btn.innerHTML = origLabel;
        });
    });
})();
</script>
@endsection
