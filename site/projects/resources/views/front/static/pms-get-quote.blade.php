@extends("front.layouts.shared-master")
@section("title",$data->meta_title)
@section("keywords",$data->meta_keywords)
@section("description",$data->meta_description)
@section("logo",$data->image ?? '')
@section("header-section")
    {!! $data->header_section ?? '' !!}
@stop
@section("footer-section")
    {!! $data->footer_section ?? '' !!}
@stop
@section("container")

{{-- NOTE: this page deliberately does NOT load pms-scope.css or wrap in
     .pms-scope — that sheet themes the dark property page and its
     .booking-section{padding:80px 0} / cream .price-row rules wreck this
     white-card layout (giant gaps, invisible totals). css/booking.css
     (base-themed) fully styles this page. --}}
    @php
        $name=$data->name;
        $bannerImage=asset('front/images/breadcrumb.webp');
        if(!empty($data->bannerImage)){
            $bannerImage=asset($data->bannerImage);
        }

        $currency=ModelHelper::getDataFromSetting('payment_currency');
        $currencyPrefix = preg_match('/^[A-Z]{3}$/', (string) $currency)
            ? $currency . ' '
            : (string) $currency;

        $start_date=$main_data["start_date"];
        $end_date=$main_data["end_date"];
        $adults=$main_data["adults"];
        $child=$main_data["child"];
        $total_guests=$adults+$child;
        $gross_amount=$main_data['data'][0]['amount_gross'];
        $day=$main_data['total_night'];
        $sub_total=$gross_amount;
        $total_amount=$gross_amount;
        
        $total_pets=0;
        $tax=$main_data['data'][0]['total_vat'];
        $after_total_fees=$main_data['data'][0]['add_ons'];
        $before_total_fees=$main_data['data'][0]['room_types'][0]['price_types'];
        $amount_data=$main_data['data'][0]['scheduled_payments'];
        $total_payment=$main_data['data'][0]['amount_gross'];
        $propertyUrl = \App\Support\PropertyUrl::url($property);
        $termsUrl = url('terms-of-service');
        $privacyUrl = url('privacy-policy');
        $policiesUrl = $propertyUrl . '#policies';
    @endphp

<section class="booking-breadcrumb">
    <div class="breadcrumb-overlay"></div>
    <div class="breadcrumb-content">
        <div class="breadcrumb-left">
            <h1 class="breadcrumb-page-title">Booking</h1>
            <p class="breadcrumb-subtitle">Complete your reservation for {{ $property->name }}</p>
        </div>
        <nav class="breadcrumb-nav">
            <ul class="breadcrumb-list">
                <li><a href="{{ url('/') }}"><i class="fas fa-house"></i> Home</a></li>
                <li class="breadcrumb-sep"><i class="fas fa-chevron-right"></i></li>
                <li><a href="{{ $propertyUrl }}">{{ $property->name }}</a></li>
                <li class="breadcrumb-sep"><i class="fas fa-chevron-right"></i></li>
                <li class="breadcrumb-active">Confirm & Pay</li>
            </ul>
        </nav>
    </div>
</section>

<main class="booking-container">
    <div class="booking-left">
        <h1>Confirm and pay</h1>

        <!-- Trip Information -->
        <section class="booking-section">
            <h3>Trip information</h3>
            <div class="form-row">
                <!-- Date Display/Trigger -->
                <div class="form-group">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label class="m-0"><i class="fas fa-calendar-alt" style="color:var(--bv-copper, #c4703f);margin-right:6px;"></i>DATES</label>
                        {{-- Param names follow the booking-flow contract (check_in/check_out/
                             adults/children/pets) — that's what BookingContext::fromRequest
                             reads to prefill the property page widget. --}}
                        <a href="{{ \App\Support\PropertyUrl::url($property, ['check_in' => $start_date, 'check_out' => $end_date, 'adults' => $adults, 'children' => $child, 'pets' => $pets ?? 0]) }}" class="btn-edit-trip">Edit</a>
                    </div>
                    <div class="date-input-wrap">
                        <input type="text" value="{{ date('d M Y', strtotime($start_date)) }} — {{ date('d M Y', strtotime($end_date)) }}"
                            readonly="readonly" class="date-picker-input">
                        <i class="fas fa-calendar-days date-icon"></i>
                    </div>
                </div>

                <!-- Guest Selector -->
                <div class="form-group">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label class="m-0"><i class="fas fa-users" style="color:var(--bv-copper, #c4703f);margin-right:6px;"></i>GUESTS</label>
                        @php $pets = $main_data['pet_fee_data_guarav'] ?? request('no_of_pets', 0); @endphp
                        <a href="{{ \App\Support\PropertyUrl::url($property, ['check_in' => $start_date, 'check_out' => $end_date, 'adults' => $adults, 'children' => $child, 'pets' => $pets]) }}" class="btn-edit-trip">Edit</a>
                    </div>
                    <div class="date-input-wrap">
                        <input type="text" value="{{ $total_guests }} Guest{{ $total_guests > 1 ? 's' : '' }} ({{ $adults }} Adults, {{ $child }} Children){{ $pets > 0 ? ', ' . $pets . ' Pet' . ($pets > 1 ? 's' : '') : '' }}" readonly=""
                            class="date-picker-input">
                        <i class="fas fa-chevron-down date-icon"></i>
                    </div>
                </div>
            </div>
        </section>

        <!-- Contact & Payment Information Form -->
        {!! Form::open(["url"=>"pms/save-booking-data", "id"=>"save-booking-data"]) !!}
            <!-- Hidden values for booking -->
            <input type="hidden" name="property_id" value="{{ $property->id }}">
            <input type="hidden" name="checkin" value="{{ $start_date }}">
            <input type="hidden" name="checkout" value="{{ $end_date }}">
            <input type="hidden" name="adults" value="{{ $adults }}">
            <input type="hidden" name="child" value="{{ $child }}">
            <input type="hidden" name="pet" value="{{ $pets }}">
            <input type="hidden" name="total_guests" value="{{ $total_guests }}">
            <input type="hidden" name="gross_amount" value="{{ $gross_amount }}">
            <input type="hidden" name="total_night" value="{{ $day }}">
            <input type="hidden" name="total_amount" value="{{ $total_amount }}">
            <input type="hidden" name="total_payment" value="{{ $total_payment }}">
            <input type="hidden" name="tax" value="{{ $tax }}">
            <input type="hidden" name="request_id" value="{{ uniqid() }}">
            <input type="hidden" name="after_total_fees" value="{{ json_encode($after_total_fees) }}">
            <input type="hidden" name="before_total_fees" value="{{ json_encode($before_total_fees) }}">
            <input type="hidden" name="amount_data" value="{{ json_encode($amount_data) }}">
            <input type="hidden" name="discount_coupon" value="{{ $main_data['data'][0]['applied_coupon'] ?? '' }}">
            {{-- Carry selected optional add-ons through checkout so saveBookingData re-quotes
                 with the same selections and the confirmed total matches what the guest saw. --}}
            <input type="hidden" name="optional_services" value="{{ $main_data['data'][0]['optional_services_json'] ?? '{}' }}">
            {{-- Carry requires_approval fees forward (fix #4). These are stored only — never
                 sent to the PMS or charged. FeeValidator already validated them upstream. --}}
            <input type="hidden" name="requested_services" value="{{ $main_data['data'][0]['requested_services_json'] ?? '{}' }}">
            <input type="hidden" name="operation" value="direct-booking">

            <section class="booking-section">
                <h3>Contact information</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label>FIRST NAME *</label>
                        <input type="text" name="first_name" placeholder="First Name" required>
                    </div>
                    <div class="form-group">
                        <label>LAST NAME *</label>
                        <input type="text" name="last_name" placeholder="Last Name" required>
                    </div>
                </div>
                <div class="form-group">
                    <label>PHONE NUMBER *</label>
                    <div class="phone-field-row">
                        <select class="phone-code-select" name="country_code">
                            <option value="+1">US (+1)</option>
                            <option value="+44">UK (+44)</option>
                            <option value="+91">IN (+91)</option>
                            <option value="+52">MX (+52)</option>
                        </select>
                        <input type="tel" name="mobile" placeholder="123 456 7890" class="phone-number-input" required>
                    </div>
                </div>
                <div class="form-group">
                    <label>EMAIL ADDRESS *</label>
                    <input type="email" name="email" placeholder="john@example.com" required>
                </div>
                <div class="form-group">
                    <label>MESSAGE (OPTIONAL)</label>
                    <textarea name="message" placeholder="Any special requests?" rows="3"></textarea>
                </div>
            </section>

            <!-- Complete Booking -->
            <div class="complete-booking">
                <h3>Complete booking</h3>
                <div class="terms-check">
                    <input type="checkbox" id="terms" required>
                    <label for="terms">By completing this booking you agree to the <a href="{{ $termsUrl }}" target="_blank" rel="noopener">Terms and Conditions</a>,
                        <a href="{{ $privacyUrl }}" target="_blank" rel="noopener">Privacy Policy</a> and <a href="{{ $policiesUrl }}" target="_blank" rel="noopener">House Rules</a>.</label>
                </div>
                <button type="submit" class="btn-confirm">CONFIRM AND BOOK NOW <i class="fas fa-arrow-right"></i></button>
            </div>
        {!! Form::close() !!}
    </div>

    <div class="booking-right">
        <div class="sidebar-card">
            <div class="property-preview">
                <img src="{{ asset($property->image_url) }}" alt="{{ $property->name }}">
                <div class="property-info">
                    <h4>{{ $property->name }}</h4>
                    <p>{{ $property->address ?? '' }}</p>
                </div>
            </div>

            <p>Payment will be charged when you book this property. Please review the <a href="{{ $policiesUrl }}" target="_blank" rel="noopener">Cancellation Policy</a> before booking.</p>

            @php
                $securityDeposit  = $main_data['data'][0]['security_deposit'] ?? null;
                $depositInTotal   = $main_data['data'][0]['deposit_in_total'] ?? false;
                $depositIsPreauth = $main_data['data'][0]['deposit_is_preauth'] ?? true;
                $appliedCoupon    = $main_data['data'][0]['applied_coupon'] ?? '';
                $manualCollection = $main_data['data'][0]['manual_collection'] ?? null;
            @endphp

            <div class="price-summary">
                @include('front.partials.pms-fee-groups', [
                    'feeItems'          => $before_total_fees,
                    'currencySymbol'    => $currencyPrefix,
                    'fallbackTaxItems'  => $tax ? [['label' => 'Taxes', 'amount' => $tax]] : [],
                    'securityDeposit'   => $securityDeposit,
                    'depositInTotal'    => $depositInTotal,
                    'depositIsPreauth'  => $depositIsPreauth,
                ])

                {{-- Total --}}
                <div class="price-row total">
                    <span>Total ({{ $main_data['data'][0]['currency'] ?? 'USD' }})</span>
                    <span>{{ $currencyPrefix }}{{ number_format($total_amount, 2) }}</span>
                </div>

                {{-- Manual collection notice (Lodgify: part of payment collected outside platform) --}}
                @if($manualCollection > 0)
                    <div class="price-row" style="color:var(--bv-copper, #c4703f);font-size:13px;margin-top:6px;">
                        <span><i class="fas fa-info-circle"></i> {{ $currencyPrefix }}{{ number_format($manualCollection, 2) }} of this total will be collected separately by the property.</span>
                    </div>
                @endif

                {{-- Payment schedule --}}
                @if(count($amount_data) > 1)
                    <div class="pms-payment-schedule" style="margin-top:14px;border-top:1px solid #ebebeb;padding-top:14px;">
                        <p style="font-size:12px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:#717171;margin-bottom:8px;">
                            <i class="fa-solid fa-calendar-days" style="margin-right:4px;"></i> Payment Schedule
                        </p>
                        @foreach($amount_data as $payment)
                            @php
                                $label    = $payment['label'] ?? 'Payment';
                                $date_due = $payment['date_due'] ?? '';
                                $ts       = $date_due ? strtotime($date_due) : 0;
                                $due_str  = $ts ? ' &mdash; due ' . date('M j, Y', $ts) : ($date_due ? ' &mdash; ' . $date_due : '');
                            @endphp
                            <div class="price-row" style="color:#717171;font-size:13px;">
                                <span>{!! $label . $due_str !!}</span>
                                <span>{{ $currencyPrefix }}{{ number_format($payment['amount'], 2) }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif

                {{-- Promo / coupon code — hidden entirely when the Coupons
                     feature is switched off for this customer (dev panel). --}}
                @if(\App\Helper\FeatureFlags::enabled('coupons'))
                <div style="margin-top:14px;border-top:1px solid #ebebeb;padding-top:14px;">
                    <p style="font-size:12px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:#717171;margin-bottom:8px;">Promo / Coupon Code</p>
                    <div style="display:flex;gap:8px;align-items:center;">
                        <input type="text" id="coupon-input" value="{{ $appliedCoupon }}"
                               placeholder="Enter code" style="flex:1;padding:8px 10px;border:1px solid #ddd;border-radius:6px;font-size:14px;">
                        <button type="button" id="coupon-apply-btn"
                                style="padding:8px 16px;background:var(--bv-copper, #c4703f);color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:14px;white-space:nowrap;">
                            Apply
                        </button>
                    </div>
                    @if($appliedCoupon)
                        <p style="font-size:12px;color:#2d8a4e;margin-top:6px;">✓ Coupon "{{ $appliedCoupon }}" applied</p>
                    @endif
                </div>
                @endif
            </div>
        </div>
    </div>

    <script>
    var couponBtn = document.getElementById('coupon-apply-btn');
    if (couponBtn) couponBtn.addEventListener('click', function() {
        var code = document.getElementById('coupon-input').value.trim();
        var url  = new URL(window.location.href);
        if (code) {
            url.searchParams.set('discount_coupon', code);
        } else {
            url.searchParams.delete('discount_coupon');
        }
        window.location.href = url.toString();
    });
    </script>
</main>

@stop
@section("css")
@parent
<link rel="stylesheet" href="{{ asset('css/booking.css') }}" />
<link rel="stylesheet" type="text/css" href="{{ asset('datepicker') }}/dist/css/hotel-datepicker.css"/>
<link rel="stylesheet" href="{{ asset('front')}}/css/datepicker.css" />
@include('front.partials.pms-fee-groups-css')
<style>
    .booking-breadcrumb { margin-top: 0; } /* Adjust if header is fixed */
    .btn-edit-trip {
        background: transparent;
        border: none;
        color: var(--bv-copper, #c4703f);
        font-size: 0.85rem;
        font-weight: 600;
        text-decoration: underline;
        padding: 0;
        cursor: pointer;
        font-family: Jost, sans-serif;
    }
    .btn-edit-trip:hover {
        color: var(--bv-charcoal, #211f1b);
    }
    .form-group label {
        margin-top: 0 !important;
        margin-bottom: 0 !important;
    }
    .guest-dropdown {
        z-index: 9999 !important; /* Ensure it stays above everything */
    }
    .price-summary .price-row {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        align-items: start;
        gap: 12px;
    }
    .price-summary .price-row > span:first-child {
        min-width: 0;
        overflow-wrap: anywhere;
    }
    .price-summary .price-row > span:last-child {
        white-space: nowrap;
        font-variant-numeric: tabular-nums;
    }
    @media (max-width: 480px) {
        .price-summary .price-row {
            gap: 8px;
            font-size: 13px;
        }
        .price-summary .price-row.total {
            font-size: 15px;
        }
    }
</style>
@stop
@section("js")
@parent
<script>
    // Simplified logic: no inline editing needed on this page as per user request
</script>
@stop
