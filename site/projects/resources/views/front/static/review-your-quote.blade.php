@extends('front.layouts.shared-master')
@section('title', $data->meta_title)
@section('keywords', $data->meta_keywords)
@section('description', $data->meta_description)
@section('logo', $data->image)
@section('header-section') {!! $data->header_section !!} @stop
@section('footer-section') {!! $data->footer_section !!} @stop
@section('container')
    @php
        $name = $data->name;
        $bannerImage = asset('front/images/breadcrumb.webp');
        if ($data->bannerImage) {
            $bannerImage = asset($data->bannerImage);
        }
        $currency = ModelHelper::getDataFromSetting('payment_currency');
        $start_date = $main_data['start_date'];
        $end_date = $main_data['end_date'];
        $adults = $main_data['adults'];
        $child = $main_data['child'];
        $total_guests = $adults + $child;
        $gross_amount = $main_data['gross_amount'];
        $day = $main_data['total_night'];

        // Phase 1 — Surface LOS discount via QuoteCalculator and apply it to the
        // nights subtotal before fees/tax. The legacy inline math stays for fees;
        // LOS is the only post-aggregation adjustment surfaced in this phase.
        $losDiscount = null;
        if ($property && $start_date && $end_date) {
            $losQuote = app(\App\Services\Booking\QuoteCalculator::class)->calculate(
                \App\Services\Booking\QuoteInput::fromArray([
                    'property_id' => $property->id,
                    'check_in'    => $start_date,
                    'check_out'   => $end_date,
                    'adults'      => max(1, (int) $adults),
                ])
            );
            if ($losQuote->los_discount) {
                $losDiscount = $losQuote->los_discount;
                $gross_amount = max(0, $gross_amount - $losDiscount['amount']);
            }
        }

        $key=[
            'stripe_publish_key'=>ModelHelper::getDataFromSetting('stripe_publish_key'),
            'stripe_secret_key'=>ModelHelper::getDataFromSetting('stripe_secret_key'),
        ];
        if($property){
            if($property->stripe_publish_key){
                if($property->stripe_secret_key){
                    $key=[
                        'stripe_publish_key'=>$property->stripe_publish_key,
                        'stripe_secret_key'=>$property->stripe_secret_key,
                    ];
                }
            }
        }

        $extra_guest_fee = 0;
        if ($property->guest_fee) {
            if ($property->guest_fee > 0) {
                if ($property->no_of_guest) {
                    if ($property->no_of_guest < $total_guests) {
                        $single_guest_fee = $property->guest_fee;
                        $rest_guests = $total_guests - $property->no_of_guest;
                        $guest_fee = $single_guest_fee * $day * $rest_guests;
                        $extra_guest_fee = $guest_fee;
                    }
                }
            }
        }

        $discount = 0;
        $temp = $temp1 = $temp2 = 0;
        $min_stay = $c_start_date = $c_end_date = $c_property_id = null;
        $coupon = App\Models\Coupon::where(['code' => Request::get('coupon')])->first();
        if ($coupon) {
            if (!empty($coupon->min_stay)) {
                $min_stay = $coupon->min_stay;
            }
            if (!empty($coupon->start_date)) {
                $c_start_date = $coupon->start_date;
            }
            if (!empty($coupon->end_date)) {
                $c_end_date = $coupon->end_date;
            }
            if (!empty($coupon->property_id)) {
                $c_property_id = $coupon->property_id;
            }
        }
        if (Request::get('coupon')) {
            $coupon = App\Models\Coupon::where(['code' => Request::get('coupon')])
                ->when($c_property_id !== null, function ($q) use ($property) {
                    $q->where('property_id', $property->id);
                })
                ->when($c_start_date !== null, function ($q) use ($start_date) {
                    $q->whereDate('start_date', '<=', $start_date);
                })
                ->when($c_end_date !== null, function ($q) use ($end_date) {
                    $q->whereDate('end_date', '>=', $end_date);
                })
                ->when($min_stay !== null, function ($q) use ($min_stay, $start_date, $end_date) {
                    $day = Helper::calculateDays($start_date, $end_date);
                    $q->where('min_stay', '<=', $day);
                })
                ->first();
            if ($coupon) {
                if ($coupon->type == 'excat') {
                    if ($gross_amount < $coupon->amount) {
                    } else {
                        $discount = $coupon->amount;
                    }
                } else {
                    if ($coupon->amount > 100) {
                    } else {
                        $discount = (($gross_amount + $extra_guest_fee) * $coupon->amount) / 100;
                    }
                }
            }
        }

        $sub_total = $gross_amount;
        $total_amount = $gross_amount;

        if ($discount > 0) {
            $total_amount = $gross_amount - $discount;
            $temp = $sub_total - $discount;
        }
        $temp1 = $sub_total;

        $before_total_fees = [];
        $after_total_fees = [];
        $total_guests = $main_data['adults'] + $main_data['childs'];
        $total_pets = $main_data['pet_fee_data_guarav'];
        $heating_pool_fee = $main_data['heating_pool_fee'];
        $extra_amount = $main_data['extra_amount'];
        $pet_fee = 0;
        $guest_fee = 0;
        $rest_guests = 0;
        $single_guest_fee = 0;
        $tax = 0;
        $define_tax = $property->tax;

        $security_deposit_data = [];
        if ($property->security_deposit_amount) {
            if ($property->security_deposit_text) {
                $security_deposit_data = [
                    'text' => $property->security_deposit_text,
                    'amount' => $property->security_deposit_amount,
                ];
            }
        }
    @endphp

    @if ($property->guest_fee)
        @if ($property->guest_fee > 0)
            @if ($property->no_of_guest)
                @if ($property->no_of_guest < $total_guests)
                    @php 
                      $single_guest_fee = $property->guest_fee;
                      $rest_guests = $total_guests - $property->no_of_guest;
                      $guest_fee = $single_guest_fee * $day * $rest_guests;
                      $sub_total += $guest_fee;
                      $total_amount += $guest_fee;
                   @endphp
                @endif
            @endif
        @endif
    @endif
    @if ($property->pet_fee_type == 'taxable')
        @if ($total_pets > 0)
            @php
                if ($total_pets == 1) {
                    $pet_fee = $property->one_pet_fee;
                }
                if ($total_pets == 2) {
                    $pet_fee = $property->two_pet_fee;
                }
                if ($total_pets == 3) {
                    $pet_fee = $property->three_pet_fee;
                }
                if ($total_pets == 4) {
                    $pet_fee = $property->four_pet_fee;
                }
                if ($total_pets == 5) {
                    $pet_fee = $property->five_pet_fee;
                }
                $sub_total += $pet_fee;
                $total_amount += $pet_fee;
            @endphp
        @endif
    @endif
    @if ($extra_amount)
        @if ($extra_amount > 0)
            @php
                $sub_total += $extra_amount;
                $total_amount += $extra_amount;
            @endphp
        @endif
    @endif
    @php $heating_pool_fee1=0; @endphp
    @if ($property->heating_pool_fee_type == 'taxable')
        @if ($property->heating_pool_fee)
            @if ($property->heating_pool_fee > 0)
                @if ($heating_pool_fee == 'Yes')
                    @php
                        $sub_total += $property->heating_pool_fee;
                        $total_amount += $property->heating_pool_fee;
                        $heating_pool_fee1 = $property->heating_pool_fee;
                    @endphp
                @endif
            @endif
        @endif
    @endif

    @foreach (App\Models\PropertyFee::where('property_id', $property->id)->where('fee_apply', 'gross')->get() as $c)
        @php  $fee=Helper::getFeeAmountAndName($c,$gross_amount); @endphp
        @if ($fee['status'] == true)
            @php
                $sub_total += $fee['amount'];
                $total_amount += $fee['amount'];
                $before_total_fees[] = [
                    'name' => $fee['name'],
                    'amount' => $fee['amount'],
                    'fee_name' => $c->fee_name,
                    'fee_rate' => $c->fee_rate,
                    'fee_type' => $c->fee_type,
                    'fee_apply' => $c->fee_apply,
                    'fee_status' => $c->fee_status,
                ];
            @endphp
        @endif
    @endforeach

    @if ($define_tax)
        @php
            $tax_subtotal = 0;
            if ($discount > 0) {
                $tax_subtotal = $sub_total - $discount;
            } else {
                $tax_subtotal = $sub_total;
            }
            $tax = round(($tax_subtotal * $define_tax) / 100, 2);
            $total_amount += $tax;
            $sub_total += $tax;
            //dd($sub_total,$tax);
        @endphp
    @endif

    @if ($property->pet_fee_type != 'taxable')
        @if ($total_pets > 0)
            @php
                if ($total_pets == 1) {
                    $pet_fee = $property->one_pet_fee;
                }
                if ($total_pets == 2) {
                    $pet_fee = $property->two_pet_fee;
                }
                if ($total_pets == 3) {
                    $pet_fee = $property->three_pet_fee;
                }
                if ($total_pets == 4) {
                    $pet_fee = $property->four_pet_fee;
                }
                if ($total_pets == 5) {
                    $pet_fee = $property->five_pet_fee;
                }
                $total_amount += $pet_fee;
            @endphp
        @endif
    @endif

    @if ($property->heating_pool_fee_type != 'taxable')
        @if ($property->heating_pool_fee)
            @if ($property->heating_pool_fee > 0)
                @if ($heating_pool_fee == 'Yes')
                    @php
                        $total_amount += $property->heating_pool_fee;
                        $heating_pool_fee1 = $property->heating_pool_fee;
                    @endphp
                @endif
            @endif
        @endif
    @endif

    @php $temp_sub = $sub_total; @endphp
    @foreach (App\Models\PropertyFee::where('property_id', $property->id)->where('fee_apply', 'total')->get() as $c)
        @php
            if ($discount > 0) {
                $sub_total = $total_amount;
            }
            $fee = Helper::getFeeAmountAndName($c, $sub_total);
        @endphp
        @if ($fee['status'] == true)
            @php
                $total_amount += $fee['amount'];
                $temp += $fee['amount'];
                $after_total_fees[] = [
                    'name' => $fee['name'],
                    'amount' => $fee['amount'],
                    'fee_name' => $c->fee_name,
                    'fee_rate' => $c->fee_rate,
                    'fee_type' => $c->fee_type,
                    'fee_apply' => $c->fee_apply,
                    'fee_status' => $c->fee_status,
                ];
            @endphp
        @endif
    @endforeach

    @php
        if ($discount > 0) {
            $sub_total = $temp_sub;
        }

        $card_need_hide = 0;
        $deducted_amount = 0;
        if (request()->selected_option) {
            $selectedOption = request()->selected_option;
            if (gettype($selectedOption) == 'string') {
                if ($selectedOption !== 'Credit Card') {
                    foreach (
                        App\Models\PropertyFee::where('property_id', $property->id)->where('fee_apply', 'total')->get()
                        as $c
                    ) {
                        if ($temp > 0) {
                            $sub_total = $temp;
                        }
                        if ($temp1 > $temp) {
                            $sub_total = $temp1 - $discount;
                        }
                        $fee = Helper::getFeeAmountAndName($c, $sub_total);
                        if (isset($fee)) {
                            if ($fee['status'] == true) {
                                if (Helper::checkCreditCardString($fee['name']) === true) {
                                    $deducted_amount = number_format($fee['amount'], 2);
                                    $card_need_hide = 1;
                                }
                            }
                        }
                    }
                    $total_amount -= $temp2;
                }
            }
        }

        if ($discount > 0) {
            $sub_total -= floatval($discount);
        }

        $days = Helper::calculateDays(date('Y-m-d'), $start_date);
        $payment_interval = ModelHelper::getDataFromSetting('payment_interval');
        if ($payment_interval) {
        } else {
            $payment_interval = 1;
        }
        $total_payment = $payment_interval;
        $amount_data = [];
        $remaining_total_amount = $total_amount;
        if ($payment_interval == 1) {
            $first_amount = $remaining_total_amount;
            $first_message = 'Total Amount Due ';
            $second_message = "($property->total_amount_include_text)";
            $total_payment = 1;
            $amount_data[] = [
                'amount' => $first_amount,
                'type' => 'first',
                'message' => $first_message,
                'sub_message' => $second_message,
            ];
        } elseif ($payment_interval == 2) {
            $second_days = ModelHelper::getDataFromSetting('second_min_total_days');
            if ($days >= $second_days) {
                $second_per = ModelHelper::getDataFromSetting('second_payment_per');
                $second_amount = round(($remaining_total_amount * $second_per) / 100, 2);
                $first_amount = $remaining_total_amount - $second_amount;
                $second_days = ModelHelper::getDataFromSetting('second_how_many_days');
                $second_date = date('F jS, Y', strtotime('- ' . $second_days . ' days', strtotime($start_date)));
                $first_message = 'Initial Payment to Secure your dates';
                $second_message = 'Final Payment Due on or Before ' . $second_date;
                $total_payment = 2;
                $amount_data[] = ['amount' => $first_amount, 'type' => 'first', 'message' => $first_message];
                $amount_data[] = ['amount' => $second_amount, 'type' => 'second', 'message' => $second_message];
            } else {
                $first_amount = $remaining_total_amount;
                $first_message = 'Total Amount Due ';
                $second_message = "($property->total_amount_include_text)";
                $total_payment = 1;
                $amount_data[] = [
                    'amount' => $first_amount,
                    'type' => 'first',
                    'message' => $first_message,
                    'sub_message' => $second_message,
                ];
            }
        } elseif ($payment_interval == 3) {
            $second_days = ModelHelper::getDataFromSetting('second_min_total_days');
            $third_days = ModelHelper::getDataFromSetting('third_min_total_days');
            if ($days >= $third_days) {
                $second_per = ModelHelper::getDataFromSetting('second_third_payment_per');
                $third_per = ModelHelper::getDataFromSetting('third_payment_per');
                $second_amount = round(($remaining_total_amount * $second_per) / 100, 2);
                $third_amount = round(($remaining_total_amount * $third_per) / 100, 2);
                $first_amount = $remaining_total_amount - $second_amount - $third_amount;
                $second_days = ModelHelper::getDataFromSetting('second_third_how_many_days');
                $third_days = ModelHelper::getDataFromSetting('third_how_many_days');
                $second_date = date('F jS, Y', strtotime('- ' . $second_days . ' days', strtotime($start_date)));
                $third_date = date('F jS, Y', strtotime('- ' . $third_days . ' days', strtotime($start_date)));
                $total_payment = 3;
                $first_message = 'Initial Payment to Secure your dates';
                $second_message = 'Next Payment Due on or Before ' . $second_date;
                $third_message = 'Final Payment Due on or Before ' . $third_date;
                $amount_data[] = ['amount' => $first_amount, 'type' => 'first', 'message' => $first_message];
                $amount_data[] = ['amount' => $second_amount, 'type' => 'second', 'message' => $second_message];
                $amount_data[] = ['amount' => $third_amount, 'type' => 'third', 'message' => $third_message];
            } elseif ($days >= $second_days) {
                $second_per = ModelHelper::getDataFromSetting('second_payment_per');
                $second_amount = round(($remaining_total_amount * $second_per) / 100, 2);
                $first_amount = $remaining_total_amount - $second_amount;
                $second_days = ModelHelper::getDataFromSetting('second_how_many_days');
                $second_date = date('F jS, Y', strtotime('- ' . $second_days . ' days', strtotime($start_date)));
                $first_message = 'Initial Payment to Secure your dates';
                $second_message = 'Final Payment Due on or Before ' . $second_date;
                $total_payment = 2;
                $amount_data[] = ['amount' => $first_amount, 'type' => 'first', 'message' => $first_message];
                $amount_data[] = ['amount' => $second_amount, 'type' => 'second', 'message' => $second_message];
            } else {
                $first_amount = $remaining_total_amount;
                $total_payment = 1;
                $first_message = 'Total Amount Due ';
                $second_message = "($property->total_amount_include_text)";
                $amount_data[] = [
                    'amount' => $first_amount,
                    'type' => 'first',
                    'message' => $first_message,
                    'sub_message' => $second_message,
                ];
            }
        }
    @endphp
    <section class="property-hero">
        <div class="hero-overlay">
            <div class="hero-content">
                <h1>Confirm &amp; Pay</h1>
                <p class="hero-subtitle">{{ $property->name ?? ($setting_data['website'] ?? 'Your stay') }}</p>
            </div>
        </div>
    </section>
    <div class="booking-container">
        <div class="booking-left">
                    <div class="trip">
                        <h4>Current Booking Details</h4>
                        <div class="trip-date">
                            <div class="left">
                                <p class="head">Dates</p>
                                <p>{{ date('F jS, Y', strtotime($start_date)) }} -
                                    {{ date('F jS, Y', strtotime($end_date)) }}
                                </p>
                            </div>
                            <div class="right"><button class="head edit">Edit</button></div>
                            <div class="date-box">
                                <button type="button" class="btn-close"></button>
                                <div class="row date-head">
                                    <div class="col-6 left">
                                        <h3>{{ $day }} nights</h3>
                                    </div>
                                    <div class="col-6 right">
                                        <form class="form booking_form" id="quote_form"
                                            action="{{ url('review-your-quote-lock-in-your-dates-today') }}" method="get">
                                            @foreach (Request::except(['end_date', 'start_date']) as $field_name => $value)
                                                <input type="hidden" name="{{ $field_name }}"
                                                    value="{{ $value }}" />
                                            @endforeach
                                            @php
                                                $rqBlocked = (new \App\Helper\LiveCart())->iCalDataCheckInCheckOutCheckinCheckout($property->id);
                                            @endphp
                                            <div class="main-cal">
                                                <div class="ovabrw_datetime_wrapper">
                                                    {!! Form::text('start_date', Request::get('start_date'), [
                                                        'required',
                                                        'readonly',
                                                        'autocomplete' => 'off',
                                                        'id' => 'start_date',
                                                        'placeholder' => 'Check in',
                                                        'data-vr-datepicker' => 'range-blocked',
                                                        'data-end-input' => '#end_date',
                                                        'data-blocked' => json_encode($rqBlocked['blocked'] ?? []),
                                                        'data-no-checkin' => json_encode($rqBlocked['checkin'] ?? []),
                                                        'data-no-checkout' => json_encode($rqBlocked['checkout'] ?? []),
                                                        'data-min-nights' => (int) ($property->min_stay ?? 0),
                                                    ]) !!}
                                                    <i class="fa-solid fa-calendar-days"></i>
                                                </div>
                                                <div class="ovabrw_datetime_wrapper">
                                                    {!! Form::text('end_date', Request::get('end_date'), [
                                                        'required',
                                                        'readonly',
                                                        'autocomplete' => 'off',
                                                        'id' => 'end_date',
                                                        'placeholder' => 'Check Out',
                                                    ]) !!}
                                                    <i class="fa-solid fa-calendar-days"></i>
                                                </div>
                                            </div>
                                            <div class="book-btn">
                                                <div class="ovabrw-book-now" id="submit-button-gaurav-data" style="">
                                                    <button type="submit" class="main-btn">
                                                        <span>Save</span></button>
                                                </div>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="trip-guest">
                            <div class="left">
                                <p class="head">Guests</p>
                                <p>{{ $total_guests }} Guests ({{ $adults }} Adults) @if (request()->pet)
                                        | {{ request()->pet }} Pets
                                    @endif
                                </p>
                            </div>
                            <div class="right"><button class="head edit-guest">Edit</button></div>
                            <div class="guest-box">
                                <button type="button" class="btn-close"></button>
                                <form method="get" action="{{ url('review-your-quote-lock-in-your-dates-today') }}"
                                    id="guest-changer-data">
                                    @foreach (Request::except(['adults', 'child', 'Guests']) as $field_name => $value)
                                        <input type="hidden" name="{{ $field_name }}" value="{{ $value }}" />
                                    @endforeach
                                    <input type="hidden" name="adults" value="{{ request()->adults }}" id="adults-data">
                                    <input type="hidden" name="child" value="{{ request()->child }}" id="child-data">
                                    <input type="hidden" name="pet" value="{{ request()->pet ?? 0 }}" id="pet-data">
                                    <input type="hidden" name="Guests" value="{{ request()->Guests }}"
                                        id="show-target-data">
                                    <input type="hidden" name="button_type" value="" id="btn-name">

                                    <div class="row guest-head">
                                        <div class="col-12 left">
                                            <h3>Guests</h3>
                                            <p class="det">This place has a maximum of {{ $property->sleeps }} guests.
                                                @if ($property->pet_fee)
                                                    @if ($property->pet_fee > 0)
                                                    @else
                                                        Pets aren't allowed.
                                                    @endif
                                                @else
                                                    Pets aren't allowed.
                                                @endif
                                            </p>
                                            <div class="adult-box">
                                                <p>Adults <span>Age 18+</span></p>
                                                <div class="adult-btn">
                                                    <button class="button1" type="button"
                                                        onclick="functiondec('#adults-data','#show-target-data','#child-data')"
                                                        value="Decrement Value">-</button>
                                                    <p id="adults-data-show">{{ request()->adults }}</p>
                                                    <button class="button11 button1" type="button"
                                                        onclick="functioninc('#adults-data','#show-target-data','#child-data')"
                                                        value="Increment Value">+</button>
                                                </div>
                                            </div>
                                            <div class="adult-box d-none">
                                                <p>Child<span>Ages 0-17</span></p>
                                                <div class="adult-btn">
                                                    <button class="button1" type="button" value="Decrement Value"
                                                        onclick="functiondec('#child-data','#show-target-data','#adults-data')">-</button>
                                                    <p id="child-data-show">{{ request()->child }}</p>
                                                    <button class="button11 button1" type="button" value="Increment Value"
                                                        onclick="functioninc('#child-data','#show-target-data','#adults-data')">+</button>
                                                </div>
                                            </div>
                                            <div class="pets-box">
                                                <p id="pet">Pets</p>
                                                <div class="adult-btn">
                                                    <button class="button1" type="button" value="Decrement Value"
                                                        onclick="petdec('#pet-data','#pet-data-show')">-</button>
                                                    <p id="pet-data-show"> {{ request()->pet }}</p>
                                                    <button class="button11 button1" type="button"
                                                        value="Increment Value"
                                                        onclick="petinc('#pet-data','#pet-data-show')">+</button>
                                                </div>
                                            </div>

                                        </div>
                                    </div>
                                    <div class="guest-button">
                                        <a href="javascript:;" class="cancl">Cancel</a>
                                        <button type="submit" class="main-btn"><span>Save</span></button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    <div class="trip">
                        <form id="web-form-data">
                            
                            @php $listData=collect() /* Phase 3: typed addon removed; UX rewire pending */; @endphp
                            @if (count($listData) > 0)
                                <h4>Enhance Your Stay with Optional Add-ons:</h4>
                                @foreach ($listData as $c)
                                    @php
                                        $value_1 = 10;
                                        $d_none_class = 'd-none';
                                        $type = 'per_person';
                                        $start = 1;
                                        $end = 100;
                                        if ($c->firewood_type == 'per stay') {
                                            $type = 'per_stay';
                                            $d_none_class = 'd-none';
                                        }
                                        if ($c->firewood_type == 'per night') {
                                            $type = 'per_night';
                                            $d_none_class = 'd-none';
                                        }
                                    @endphp
                                    <div class="row">
                                        <div class="col-12">
                                            <input type="checkbox" name="firewood_rate_id[id][{{ $c->id }}]"
                                                value="{{ $c->id }}"
                                                class="common-field-show-rate-demo firewood-rate-{{ $type }}"
                                                data-target="firewood_rate_id{{ $c->id }}field">
                                            {!! Form::selectRange('firewood_rate_id[field][' . $c->id . ']', 10, 500, $value_1, [
                                                'class' => ' ' . $d_none_class . ' rate-calculateion-data',
                                                'id' => 'firewood_rate_id' . $c->id . 'field',
                                            ]) !!}
                                            <p> {{ $c->firewood_name }} <sub>{{ $c->firewood_helping_text }}</sub></p>
                                        </div>
                                    </div>
                                @endforeach
                            @endif

                            @php $listData=collect() /* Phase 3: typed addon removed; UX rewire pending */; @endphp
                            @if (count($listData) > 0)
                                <h6>Hot Tub</h6>
                                @foreach ($listData as $c)
                                    @php
                                        $value_1 = 1;
                                        $d_none_class = 'd-none';
                                        $type = 'per_person';
                                        if ($c->hottub_type == 'per stay') {
                                            $type = 'per_stay';
                                            $d_none_class = 'd-none';
                                        }
                                        if ($c->hottub_type == 'per night') {
                                            $type = 'per_night';
                                            $d_none_class = 'd-none';
                                        }
                                    @endphp
                                    <div class="row">
                                        <div class="col-12">
                                            <input type="checkbox" name="hottub_rate_id[id][{{ $c->id }}]"
                                                value="{{ $c->id }}"
                                                class="common-field-show-rate-demo hottub-rate-{{ $type }}"
                                                data-target="hottub_rate_id{{ $c->id }}field">
                                            {!! Form::selectRange('hottub_rate_id[field][' . $c->id . ']', 1, 100, $value_1, [
                                                'class' => ' ' . $d_none_class . ' rate-calculateion-data',
                                                'id' => 'hottub_rate_id' . $c->id . 'field',
                                            ]) !!}
                                            <p> {{ $c->hottub_name }} @if ($c->hottub_helping_text != 'Optional Amenity')
                                                    <sub>{{ $c->hottub_helping_text }}</sub>
                                                @endif
                                            </p>
                                        </div>
                                    </div>
                                @endforeach
                            @endif

                            @php $listData=collect() /* Phase 3: typed addon removed; UX rewire pending */; @endphp
                            @if (count($listData) > 0)
                                <h6>RV Hookup</h6>
                                @foreach ($listData as $c)
                                    @php
                                        $value_1 = 1;
                                        $d_none_class = 'd-none';
                                        $type = 'per_person';
                                        if ($c->rvhookup_type == 'per stay') {
                                            $type = 'per_stay';
                                            $d_none_class = 'd-none';
                                        }
                                        if ($c->rvhookup_type == 'per night') {
                                            $type = 'per_night';
                                            $d_none_class = 'd-none';
                                        }
                                    @endphp
                                    <div class="row">
                                        <div class="col-12">
                                            <input type="checkbox" name="rvhookup_rate_id[id][{{ $c->id }}]"
                                                value="{{ $c->id }}"
                                                class="common-field-show-rate-demo rvhookup-rate-{{ $type }}"
                                                data-target="rvhookup_rate_id{{ $c->id }}field"
                                                data-quantity="rvhookup_rate_id_quantity{{ $c->id }}field">
                                            {!! Form::selectRange('rvhookup_rate_quantity[field][' . $c->id . ']', 0, $property->max_rv_hook_up, null, [
                                                'class' => ' rvhookup_rate_id_quantity  ' . $d_none_class . ' rate-calculateion-data',
                                                'id' => 'rvhookup_rate_id_quantity' . $c->id . 'field',
                                            ]) !!}



                                            {!! Form::selectRange('rvhookup_rate_id[field][' . $c->id . ']', 1, 100, $value_1, [
                                                'class' => ' ' . $d_none_class . ' rate-calculateion-data',
                                                'id' => 'rvhookup_rate_id' . $c->id . 'field',
                                            ]) !!}
                                            <p> {{ $c->rvhookup_name }}<sub> {{ $c->rvhookup_helping_text }}</sub></p>
                                        </div>
                                    </div>
                                @endforeach
                            @endif

                            @php $listData=collect() /* Phase 3: typed addon removed; UX rewire pending */;@endphp
                            @if (count($listData) > 0)
                                @foreach ($listData as $c)
                                    @php
                                        $value_1 = 1;
                                        $d_none_class = 'd-none';
                                        $type = 'per_person';
                                        if ($c->visitor_type == 'per stay') {
                                            $type = 'per_stay';
                                            $d_none_class = 'd-none';
                                        }
                                        if ($c->visitor_type == 'per night') {
                                            $type = 'per_night';
                                            $d_none_class = 'd-none';
                                        }
                                    @endphp
                                    <div class="row">
                                        <div class="col-12">
                                            <input type="checkbox" name="visitor_rate_id[id][{{ $c->id }}]"
                                                value="{{ $c->id }}"
                                                class="common-field-show-rate-demo visitor-rate-{{ $type }}"
                                                data-target="visitor_rate_id{{ $c->id }}field">

                                            {!! Form::selectRange('visitor_rate_id[field][' . $c->id . ']', 1, 100, $value_1, [
                                                'class' => ' ' . $d_none_class . ' rate-calculateion-data',
                                                'id' => 'visitor_rate_id' . $c->id . 'field',
                                            ]) !!}
                                            <p> {{ $c->visitor_name }} <sub>{{ $c->visitor_helping_text }}</sub></p>
                                        </div>
                                    </div>
                                @endforeach
                            @endif
                            
                            <input type="hidden" name="selected_option" value="Credit Card" />
                        </form>
                    </div>
                    <div class="visitor-rate">
                        <h3>Visitor Rate Information:</h3>
                        {!! $property->visitor_rate_info !!}
                    </div>

                    <div class="info-detail">
                  {!! Form::open([
    'url' => 'save-booking-data',
    'id' => 'payment-form',
    'class' => 'require-validation',
    'files' => true,
    'data-stripe-publishable-key' => $key['stripe_publish_key'],
    'onsubmit' => 'return checkSignature()',
]) !!}
                        <div class="rental-agreement d-none">
                            <h3>Rental Agreement - Policies & Booking Terms</h3>
                            <div class="document-html">
                                {!! $property->notes !!}
                            </div>
                            <div class="agreement">
                                <input type="checkbox" > I agree to the terms and conditions in the Rental
                                Agreement.
                            </div>
                            <p class="agreement-msg">After reviewing the Rental Agreement, confirm your acceptance above,
                                sign, upload a copy of your driver's license, and provide your full name, mobile number,
                                email, and any relevant booking details. If not paying by credit card on-line now, please
                                indicate your payment method (e.g., check, venmo, etc.) and when you expect to secure the
                                booking.</p>
                            <div class="sign">
                                <div class="sign-area row">
                                    <div class="col-6 sign-detail">
                                        <h4>E-Signature</h4>
                                        <canvas id="sig-canvas" width="300px" height="160"></canvas>
                                    </div>
                                    <div class="col-6 docu">
                                        <h4>Clear photo of your Driver's License</h4>
                                        <input type="file"  name="image[]"
                                            accept=".jpg,.jpeg,.png,.gif,.svg,.webp">
                                    </div>
                                </div>
                                <div class="sign-btn">
                                    <button type="button" class="btn btn-default main-btn" id="sig-clearBtn"><span>Clear
                                            Signature</span></button>
                                </div>
                                <div class="text-area">
                                    <textarea id="sig-dataUrl" name="signature" class="form-control" rows="5">Data URL for your signature will go here!</textarea>
                                </div>
                            </div>
                        </div>


                        <div class="card-details info-detail">
                        <div class="card-form">
                            <div class="card-form-head">
                                <h3>Pay with Credit/Debit card</h3>
                                <img src="{{ asset('front')}}/images/credit-card.svg" alt="" />
                            </div>
                            <style> .hide{display:none;}</style>
                            <div class="error hide" >
                                <div class="alert alert-danger"></div>
                            </div>
                            <div class='form-row row'>
                                <div class="col-6 form-floating name-on-card  required">
                                    <input type="text" class="form-control" id="name_on_card" placeholder="Enter Name on Card" title="Enter Name on Card" name="name_on_card">
                                    <label for="name_on_card">Name on Card</label>
                                </div>
                                 <div class="col-6 form-floating card required">
                                    <input autocomplete='off' type="text" class="form-control  card-number" id="card_number" placeholder="Enter Card Number" title="Enter  Card Number" name="card_number" required>
                                    <label for="card_number">Card Number</label>
                                </div>
                            </div>
                            <div class='form-row row'>
                                <div class="col-4 form-floating cvc required">
                                    <input autocomplete='off' type="text" class="form-control card-cvc" id="card_cvv" placeholder="Enter CVV" title="Enter CVV" name="card_cvv" required>
                                    <label for="card_cvv">Card (CVV)</label>
                                </div>
                                <div class="col-4 form-floating expiration required">
                                    <input autocomplete='off' type="text" class="form-control card-expiry-month" id="card_exp_month" placeholder="Enter Expiration Month (MM)" title="Enter Expiration Month (MM)" name="card_exp_month" required>
                                    <label for="card_exp_month">Expiration Month(MM)</label>
                                </div>
                                <div class="col-4 form-floating expiration required">
                                    <input autocomplete='off' type="text" class="form-control card-expiry-year" id="card_year" placeholder="Enter Expiration Year (YYYY)" title="Enter Expiration Year (YYYY)" name="card_year" required>
                                    <label for="card_year">Year (YYYY)</label>
                                </div>
                            </div>
                        </div>
                    </div>


                        <div class="row-data-hidden">
                            <input type="hidden" name="discount" value="{{ $discount }}" />
                            <input type="hidden" name="discount_coupon" value="{{ Request::get('coupon') }}" />
                            <input type="hidden" name="after_discount_total" value="{{ $remaining_total_amount }}" />
                            <input type="hidden" name="total_pets" value="{{ $total_pets }}">
                            <input type="hidden" name="pet_fee" value="{{ $pet_fee }}">
                            <input type="hidden" name="guest_fee" value="{{ $guest_fee }}">
                            <input type="hidden" name="rest_guests" value="{{ $rest_guests }}">
                            <input type="hidden" name="single_guest_fee" value="{{ $single_guest_fee }}">
                            <input type="hidden" name="total_payment" value="{{ $total_payment }}">
                            <input type="hidden" name="amount_data" value="{{ json_encode($amount_data) }}">
                            <input type="hidden" name="property_id" value="{{ $property->id }}">
                            <input type="hidden" name="checkin" value="{{ $start_date }}">
                            <input type="hidden" name="checkout" value="{{ $end_date }}">
                            <input type="hidden" name="total_guests" value="{{ $total_guests }}">
                            <input type="hidden" name="adults" value="{{ $adults }}">
                            <input type="hidden" name="child" value="{{ $child }}">
                            <input type="hidden" name="gross_amount" value="{{ $gross_amount }}">
                            <input type="hidden" name="total_night" value="{{ $day }}">
                            <input type="hidden" name="sub_amount" value="{{ $sub_total }}">
                            <input type="hidden" name="total_amount" value="{{ $total_amount }}">
                            <input type="hidden" name="after_total_fees" value="{{ json_encode($after_total_fees) }}">
                            <input type="hidden" name="before_total_fees"
                                value="{{ json_encode($before_total_fees) }}">
                            <input type="hidden" name="request_id" value="{{ uniqid() }}">
                            <input type="hidden" name="tax" value="{{ $tax }}">
                            <input type="hidden" name="define_tax" value="{{ $define_tax }}">
                            <input type="hidden" name="pet_fee_type" value="{{ $property->pet_fee_type }}" />
                            <input type="hidden" name="heating_pool_fee_type"
                                value="{{ $property->heating_pool_fee_type }}" />
                            <input type="hidden" name="heating_pool_fee" value="{{ $heating_pool_fee1 }}" />
                            <input type="hidden" name="extra_amount" value="{{ $extra_amount }}" />
                            <input type="hidden" name="rv_hookup_json" value="{{ json_encode([]) }}" />
                            <input type="hidden" name="visitor_hookup_json" value="{{ json_encode([]) }}" />
                            <input type="hidden" name="hottub_json" value="{{ json_encode([]) }}" />
                            <input type="hidden" name="firewood_json" value="{{ json_encode([]) }}" />
                            @if ($property->security_deposit_amount)
                                <input type="hidden" name="security_deposit_data"
                                    value="{{ json_encode($security_deposit_data) }}" />
                            @endif
                            <input type="hidden" name="card_need_hide" value="{{ $card_need_hide }}">
                        </div>
                        <div class="row">
                        <h3>Contact Information</h3>
                            <div class="col-12 form-floating">
                                <input type="text" class="form-control" id="name" placeholder="Enter Full Name"
                                    title="Enter Full Name" name="name" required>
                                <label for="name">Full Name</label>
                            </div>
                            <div class="col-12 form-floating">
                                <input type="email" class="form-control" id="email" placeholder="Enter Email"
                                    title="Enter Email" name="email" required>
                                <label for="email">Email</label>
                            </div>
                            <div class="col-12 form-floating">
                                <input type="tel" class="form-control" id="mobile" placeholder="Enter Mobile"
                                    title="Enter Mobile" name="mobile" required>
                                <label for="mobile">Mobile</label>
                            </div>
                            <div class="col-12 form-floating">
                                <textarea class="form-control" name="message" placeholder="Enter Message" title="Enter Message"
                                    id="floatingTextarea"></textarea>
                                <label for="floatingTextarea">Message</label>
                            </div>
                            <div class="col-12 form-floating d-none">
                                <textarea class="form-control" name="payment_method"
                                    placeholder="If not booking now with a Credit Card Payment, Please provide details on payment desired payment method:"
                                    title="If not booking now with a Credit Card Payment, Please provide details on payment desired payment method:"
                                    id="floatingTextarea1"></textarea>
                                <label for="floatingTextarea1">If not booking now with a Credit Card Payment, Please
                                    provide details on payment desired payment method:</label>
                            </div>
                            <div class="col-12 card-form d-none">
                                <label for="mobile">Choose Payment Method</label>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="operation" id="inlineRadio1"
                                        value="direct-booking" checked>
                                    <label class="form-check-label" for="inlineRadio1">Credit Card <span
                                            class="text-muted price-md-text">(4% processing fee applies) </span> </label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="operation" id="inlineRadio2"
                                        value="check-booking">
                                    <label class="form-check-label" for="inlineRadio2">Check <span
                                            class="text-muted price-md-text">(Note: Paying by Check waives the 4%
                                            processing fee) </span></label>
                                </div>

                                <div class="form-check form-check-inline d-none">
                                    <input class="form-check-input" type="radio" name="operation" id="inlineRadio3"
                                        value="venmo-booking">
                                    <label class="form-check-label" for="inlineRadio3">Venmo <span
                                            class="text-muted price-md-text">(Choose Friends and Family for no processing
                                            fees) </span></label>
                                </div>

                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="operation" id="inlineRadio4"
                                        value="other-booking">
                                    <label class="form-check-label" for="inlineRadio4">Other <span
                                            class="text-muted price-md-text">(Let's talk) </span></label>
                                </div>
                            </div>
                        </div>
                        <div class="form-btn sub">
                            {{-- <button type="submit" class="submit main-btn" name="operation1" value="check-booking"
                                id="sig-submitBtn"><span>Request to Book</span></button> --}}
                                <button type="submit" class="submit main-btn" name="payment_type"
                                    value="instent-booking" id="sig-submitBtn"><span>Pay
                                        Now</span></button>
                            
                        </div>
                        {!! Form::close() !!}
                        <!-- About Section -->

                        <div class="container">
                            <div class="row mt-4">

                                {!! $property->get_quote_description !!}

                            </div>
                        </div>

                    </div>
                </div>
        <div class="booking-right" id="show-price-div-data">
            <div class="sidebar-card">
                        <div class="property-preview">
                            <img src="{{ asset($property->feature_image) }}"
                                    alt="{{ $property->name }}" title="{{ $property->name }}">
                            <div class="pro-cont">
                                <p class="home-type d-none">{{ $property->heading }}</p>
                                <h4 class="pro-name">{{ $property->name }}</h4>
                                <div class="ammenity-home">
                                    @if ($property->sleeps)
                                        <span>{{ $property->sleeps }} Sleeps</span> <span>.</span>
                                    @endif
                                    @if ($property->beds)
                                        <span>{{ $property->beds }} Individual Beds</span> <span>.</span>
                                    @endif
                                    @if ($property->bathroom)
                                        <span>{{ $property->bathroom }} Bathrooms</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                        <div class="price-summary">
                            <h4>Booking Invoice</h4>
                            <div class="price-area">
                                <div class="prc">
                                    <p class="value">Rental Rate for {{ $day }} nights</p>
                                    <p class="total">{{ $currency }}{{ number_format($gross_amount, 2) }}</p>
                                </div>
                                @if ($extra_amount)
                                    @if ($extra_amount > 0)
                                        <div class="prc"> 
                                            <p class="value">Fee (Min. stay not met) <span class="icon">!<span
                                                        class="box-msg"> Minimum nights not met </span></span></p>
                                            <p class="total">{{ $currency }} {{ number_format($extra_amount, 2) }}
                                            </p>
                                        </div>
                                    @endif
                                @endif
                                @if ($property->guest_fee)
                                    @if ($property->guest_fee > 0)
                                        @if ($property->no_of_guest)
                                            @if ($property->no_of_guest < $total_guests)
                                                @php $single_guest_fee=$property->guest_fee; @endphp
                                                @php $rest_guests=$total_guests-$property->no_of_guest; @endphp
                                                @php $guest_fee=$single_guest_fee*$day*$rest_guests;   @endphp
                                                <div class="prc">
                                                    <p class="value">Additional Guest Fee <br> <span
                                                            style="font-size:13px;">({{ $day }} nights *
                                                            {{ $currency }}{{ $single_guest_fee }} *
                                                            {{ $rest_guests }} Guests) <span class="icon">!<span
                                                                    class="box-msg">Standard Rates are based on
                                                                    {{ $property->no_of_guest }} or less
                                                                    guests</span></span></span></p>
                                                    <p class="total">{{ $currency }}
                                                        {{ number_format($guest_fee, 2) }}</p>
                                                </div>
                                            @endif
                                        @endif
                                    @endif
                                @endif

                                @if ($property->pet_fee_type == 'taxable')
                                    @if ($total_pets > 0)
                                        @php
                                            if ($total_pets == 1) {
                                                $pet_fee = $property->one_pet_fee;
                                            }
                                            if ($total_pets == 2) {
                                                $pet_fee = $property->two_pet_fee;
                                            }
                                            if ($total_pets == 3) {
                                                $pet_fee = $property->three_pet_fee;
                                            }
                                            if ($total_pets == 4) {
                                                $pet_fee = $property->four_pet_fee;
                                            }
                                            if ($total_pets == 5) {
                                                $pet_fee = $property->five_pet_fee;
                                            }
                                        @endphp
                                        <div class="prc">
                                            <p class="value">Pet fee @if (request()->pet)
                                                    * {{ request()->pet }}
                                                @endif
                                            </p>
                                            <p class="total">{{ $currency }}{{ number_format($pet_fee, 2) }}</p>
                                        </div>
                                    @endif
                                @endif

                                @php  $heating_pool_fee1=0; @endphp
                                @if ($property->heating_pool_fee_type == 'taxable')
                                    @if ($property->heating_pool_fee)
                                        @if ($property->heating_pool_fee > 0)
                                            @if ($heating_pool_fee == 'Yes')
                                                @php  $heating_pool_fee1=$property->heating_pool_fee; @endphp
                                                <div class="prc">
                                                    <p class="value">Heating the Swimming Pool fee</p>
                                                    <p class="total">
                                                        {{ $currency }}{{ number_format($heating_pool_fee1, 2) }}
                                                    </p>
                                                </div>
                                            @endif
                                        @endif
                                    @endif
                                @endif

                                @foreach (App\Models\PropertyFee::where('property_id', $property->id)->where('fee_apply', 'gross')->get() as $c)
                                    @php  $fee=Helper::getFeeAmountAndName($c,$gross_amount);@endphp
                                    @if ($fee['status'] == true)
                                        <div class="prc">
                                            <p class="value">{{ $fee['name'] }}</p>
                                            <p class="total">{{ $currency }}{{ number_format($fee['amount'], 2) }}
                                            </p>
                                        </div>
                                        @php
                                            $before_total_fees[] = [
                                                'name' => $fee['name'],
                                                'amount' => $fee['amount'],
                                                'fee_name' => $c->fee_name,
                                                'fee_rate' => $c->fee_rate,
                                                'fee_type' => $c->fee_type,
                                                'fee_apply' => $c->fee_apply,
                                                'fee_status' => $c->fee_status,
                                            ];
                                        @endphp
                                    @endif
                                @endforeach

                                @php
                                    $apply_button_name = 'Apply';
                                    $apply = 0;
                                    $error = 0;
                                    $coupon1 = 0;
                                    $min_stay = $c_start_date = $c_end_date = $c_property_id = null;

                                    $coupon = App\Models\Coupon::where(['code' => Request::get('coupon')])->first();
                                    if ($coupon) {
                                        if (!empty($coupon->min_stay)) {
                                            $min_stay = $coupon->min_stay;
                                        }
                                        if (!empty($coupon->start_date)) {
                                            $c_start_date = $coupon->start_date;
                                        }
                                        if (!empty($coupon->end_date)) {
                                            $c_end_date = $coupon->end_date;
                                        }
                                        if (!empty($coupon->property_id)) {
                                            $c_property_id = $coupon->property_id;
                                        }
                                    }

                                    if (Request::get('coupon')) {
                                        $coupon1 = App\Models\Coupon::where(['code' => Request::get('coupon')])
                                            ->when($c_property_id !== null, function ($q) use ($property) {
                                                $q->where('property_id', $property->id);
                                            })
                                            ->when($c_start_date !== null, function ($q) use ($start_date) {
                                                $q->whereDate('start_date', '<=', $start_date);
                                            })
                                            ->when($c_end_date !== null, function ($q) use ($end_date) {
                                                $q->whereDate('end_date', '>=', $end_date);
                                            })
                                            ->when($min_stay !== null, function ($q) use (
                                                $min_stay,
                                                $start_date,
                                                $end_date,
                                            ) {
                                                $day = Helper::calculateDays($start_date, $end_date);
                                                $q->where('min_stay', '<=', $day);
                                            })
                                            ->first();
                                        if ($coupon1) {
                                            if ($coupon->type == 'excat') {
                                                if ($gross_amount < $coupon->amount) {
                                                    $error = 1;
                                                } else {
                                                    $apply = 1;
                                                    $discount = $coupon->amount;
                                                    $apply_button_name = 'Applied';
                                                }
                                            } else {
                                                if ($coupon->amount > 100) {
                                                    $error = 1;
                                                } else {
                                                    $apply = 1;
                                                    $discount =
                                                        (($gross_amount + $extra_guest_fee) * $coupon->amount) / 100;
                                                    $apply_button_name = 'Applied';
                                                }
                                            }
                                        }

                                        if ($coupon1 != null) {
                                            $apply = 1;
                                        } else {
                                            $error = 1;
                                        }
                                    }

                                    $disbaled = '';
                                    if ($apply == 1) {
                                        $disbaled = 'disabled';
                                    }
                                @endphp

                                <div class="price-area">
                                    <div class="total-amt">
                                        <p class="value"><input type="checkbox" {{ $apply == 1 ? 'disabled' : '' }}
                                                name="is_coupon" id="is_coupon" /> Coupon Code?</p>

                                    </div>

                                    <div id="coupon-form" style="display:none;">
                                        <div class="total-amt">
                                            <form method="get"
                                                action="{{ url('review-your-quote-lock-in-your-dates-today') }}"
                                                style="display:inline-block;">
                                                <label for="name">Coupon Code</label>
                                                <div class="c-btn">

                                                    <input type="text" class="form-control"
                                                        value="{{ Request::get('coupon') }}"
                                                        placeholder="Enter Coupon Code" name="coupon" 
                                                        @if ($apply == 1) readonly @endif>

                                                    @if ($apply == 1)
                                            </form>
                                            @endif
                                            @if ($apply == 0)
                                                <button class="submit main-btn {{ $apply == 1 ? 'd-none' : '' }}"
                                                    {{ $disbaled }}><span>{{ $apply_button_name }}</span></button>
                                        </div>
                                        @foreach (Request::except(['coupon']) as $key => $c_gaurav)
                                            <input type="hidden" name="{{ $key }}"
                                                value="{{ $c_gaurav }}" />
                                        @endforeach
                                    @else
                                        <form method="get"
                                            action="{{ url('review-your-quote-lock-in-your-dates-today') }}"
                                            style="display:inline-block;">

                                            <button class="submit main-btn "><span>Remove</span></button>

                                            @foreach (Request::except(['coupon']) as $key => $c_gaurav)
                                                <input type="hidden" name="{{ $key }}"
                                                    value="{{ $c_gaurav }}" />
                                            @endforeach

                                            @endif


                                        </form>
                                    </div>
                                </div>
                                @if ($error == 1)
                                    <div class="text-danger">Invalid Coupon</div>
                                @endif
                                @if ($apply == 1)
                                    <div class="text-success">Coupon code applied successfully</div>
                                @endif

                                @if ($apply == 1)
                                    <div class="total-amt">
                                        <p class="value">Discount</p>
                                        <p class="total">{{ $currency }}{{ number_format($discount, 2) }}</p>
                                    </div>
                                    <!--<div class="total-amt d-none">-->
                                    <!--    <p class="value">Total Amount after Discount</p>-->
                                    <!--    <p class="total">{{ $currency }}{{ number_format($remaining_total_amount, 2) }}</p>-->
                                    <!--</div>-->
                                @endif

                                @if ($sub_total != $gross_amount)
                                    <div class="total-amt">
                                        <p class="value">Sub total</p>
                                        <p class="total">{{ $currency }}{{ number_format($sub_total - $tax, 2) }}
                                        </p>
                                    </div>
                                @endif

                                @if ($losDiscount)
                                    <div class="prc los-discount">
                                        <p class="value">{{ $losDiscount['label'] }} ({{ $losDiscount['percent'] }}%)</p>
                                        <p class="total">−{{ $currency }} {{ number_format($losDiscount['amount'], 2) }}</p>
                                    </div>
                                @endif

                                @if ($define_tax)
                                    {{-- @php $tax=round(($sub_total*$define_tax)/100,2); dump($tax); @endphp --}}
                                    <div class="prc">
                                        <p class="value">Lodging Tax ({{ $define_tax }}%)</p>
                                        <p class="total">{{ $currency }} {{ number_format($tax, 2) }}</p>
                                    </div>
                                @endif
                                <div class="total-amt">
                                    <p class="value">TOTAL BOOKING COST </p>
                                    <p class="total">{{ $currency }} {{ number_format($sub_total, 2) }}</p>
                                </div>
                                @if ($property->pet_fee_type != 'taxable')
                                    @if ($total_pets > 0)
                                        @php
                                            if ($total_pets == 1) {
                                                $pet_fee = $property->one_pet_fee;
                                            }
                                            if ($total_pets == 2) {
                                                $pet_fee = $property->two_pet_fee;
                                            }
                                            if ($total_pets == 3) {
                                                $pet_fee = $property->three_pet_fee;
                                            }
                                            if ($total_pets == 4) {
                                                $pet_fee = $property->four_pet_fee;
                                            }
                                            if ($total_pets == 5) {
                                                $pet_fee = $property->five_pet_fee;
                                            }
                                        @endphp
                                        <div class="prc">
                                            <p class="value">Pet Fee</p>
                                            <p class="total">{{ $currency }} {{ number_format($pet_fee, 2) }}</p>
                                        </div>
                                    @endif
                                @endif

                                @if ($property->heating_pool_fee_type != 'taxable')
                                    @if ($property->heating_pool_fee)
                                        @if ($property->heating_pool_fee > 0)
                                            @if ($heating_pool_fee == 'Yes')
                                                @php $heating_pool_fee1=$property->heating_pool_fee;@endphp
                                                <div class="prc">
                                                    <p class="value">Heating the Swimming Pool fee</p>
                                                    <p class="total">{{ $currency }}
                                                        {{ number_format($heating_pool_fee1, 2) }}</p>
                                                </div>
                                            @endif
                                        @endif
                                    @endif
                                @endif
                                @php
                                    $temp_sub_total = 0;
                                    $card_flage = false;
                                @endphp
                                @foreach (App\Models\PropertyFee::where('property_id', $property->id)->where('fee_apply', 'total')->get() as $c)
                                    @php  $fee=Helper::getFeeAmountAndName($c,$sub_total); @endphp
                                    @if ($fee['status'] == true)
                                        <div class="prc" data-name="{{ $fee['name'] }}">
                                            <p class="value">
                                                @if (Helper::checkCreditCardString($fee['name']) === true)
                                                    @php
                                                        $temp_sub_total += $fee['amount'];
                                                        $card_flage = true;
                                                    @endphp
                                                    {{ $fee['name'] }}
                                                @else
                                                    {{ $fee['name'] }}
                                                    @php $temp_sub_total += $fee['amount']; @endphp
                                                @endif
                                            </p>
                                            <p class="total">{{ $currency }}{{ number_format($fee['amount'], 2) }}
                                            </p>
                                        </div>
                                        @php
                                            $after_total_fees[] = [
                                                'name' => $fee['name'],
                                                'amount' => $fee['amount'],
                                                'fee_name' => $c->fee_name,
                                                'fee_rate' => $c->fee_rate,
                                                'fee_type' => $c->fee_type,
                                                'fee_apply' => $c->fee_apply,
                                                'fee_status' => $c->fee_status,
                                            ];
                                        @endphp
                                    @endif
                                @endforeach
                            </div>
                            @php $sub_total+=$temp_sub_total;@endphp
                            <div class="total-amt">
                                <p class="value"> Total @if ($card_flage === true)
                                        with credit card processing fee:
                                    @endif
                                    </span>
                                </p>
                                @php  $remaining_total_amount=$sub_total; @endphp
                                @if ($apply == 1)
                                    <p class="total" id="total_amout">
                                        {{ $currency }}{{ number_format($remaining_total_amount, 2) }}</p>
                                @else
                                    <p class="total" id="total_amout">
                                        {{ $currency }}{{ number_format($total_amount, 2) }}</p>
                                @endif
                            </div>

                            @foreach ($amount_data as $c)
                                <div class="total-amt">
                                    <p class="value">{{ $c['message'] }} @if (isset($c['sub_message']))
                                            <span class="text-muted price-sm-text">{{ $c['sub_message'] }}</span>
                                        @endif
                                    </p>
                                    <p class="total">{{ $currency }}{{ number_format($c['amount'], 2) }}</p>
                                </div>
                            @endforeach

                            @php
                                $deposit_flag = false;
                                $deposit_amount = 0;
                                $deposite_text = '';
                            @endphp
                            @if (isset($security_deposit_data['amount']))
                                @php $deposit_flag = true;
                                        $deposit_amount = number_format($security_deposit_data['amount'], 2);
                                        $deposite_text = $security_deposit_data['text'];
                                @endphp
                                <div class="total-amt">
                                    <p class="value">{!! $deposite_text !!} </p>
                                    <p class="total">{{ $currency }}{{ $deposit_amount }}</p>
                                </div>
                            @endif
                        </div>
                    </div>
            </div>
        </div>
    </div>
@stop
@section('css')
    @parent
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;0,700;1,300;1,400;1,600&family=Jost:wght@200;300;400;500;600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" />
    <link rel="stylesheet" href="{{ asset('front/base/css/style.css') }}">
    <link rel="stylesheet" href="{{ asset('front/base/css/responsive.css') }}">
    <link rel="stylesheet" type="text/css" href="{{ asset('drag-drop-image-uploader/src/image-uploader.css') }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.5/css/select2.min.css" />
    <style>
        select.rate-calculateion-data {
            height: 23px;
            width: 70px;
            border: 1px solid var(--gold) !important;
            color: #0e0c0a !important;
            padding: 0px !important;
        }

        .image-uploader .uploaded .uploaded-image {
            display: inline-block;
            width: 100%;
            height: 125px;
            position: relative;
            margin: 0.5rem;
            background: #f3f3f3;
            cursor: default;
        }

        .image-uploader .uploaded .uploaded-image img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            position: absolute;
        }
    </style>
    <link rel="stylesheet" href="{{ asset('front/css/get-quote.css') }}" />
@stop
@section('js')
    @parent
    <script src="{{ asset('drag-drop-image-uploader/src/image-uploader.js') }}"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.5/js/select2.min.js"></script>
    <script src="{{ asset('front') }}/js/get-quote.js"></script>
    <script>
        $(document).ready(function() {
            $('#sig-submitBtn').click(function() {
                $('input[name="payment_type"]').val('');
            });
            $('#inlineRadio1').change(function() {
                if ($(this).is(':checked')) {
                    $('.prc').each(function() {
                        if ($(this).data('name') === 'Credit Card Processing Fee(4%)' || $(this)
                            .data('name') === 'Credit Card Fee(4%)') {
                            if ($(this).hasClass('d-none')) {
                                $(this).removeClass('d-none');
                            }

                            // var actual_text = "{{ number_format($total_amount, 2) }}";
                            // var currency = "{{ $currency }}";
                            // $('#total_amout').html(currency+actual_text);

                            // var actual_text1 = $('input[name="total_amount"]').val();
                            // $('#total_amout1').html(currency+actual_text1);

                            $(this).show();
                        }
                    });
                    $('#crd_txt').show();
                    $('#sig-submitBtn1').show();
                    $('input[name="selected_option"]').val('Credit Card');
                    ajaFunction();
                }
            });
            $('#inlineRadio2').change(function() {
                if ($(this).is(':checked')) {
                    $('.prc').each(function() {
                        if ($(this).data('name') === 'Credit Card Processing Fee(4%)' || $(this)
                            .data('name') === 'Credit Card Fee(4%)') {

                            // var amount = $(this).find('.total').text();
                            // var actual_text = "{{ number_format($total_amount, 2) }}";
                            // var num1 = parseFloat(actual_text.replace(/,/g, ''));
                            // let num2 = parseFloat(amount.replace(/[$,]/g, ''));
                            // let result = num1 - num2;
                            // var currency = "{{ $currency }}";
                            // $('#total_amout').html(currency+result);

                            // var amount1 = $(this).find('.total').text();
                            // var actual_text1 = $('input[name="total_amount"]').val();
                            // var num3 = parseFloat(actual_text1.replace(/,/g, ''));
                            // var num4 = parseFloat(amount1.replace(/[$,]/g, ''));                    
                            // let result1 = num3 - num4;
                            // result1 = result1.toFixed(1);
                            // $('#total_amout1').html(currency+result1);

                            $(this).hide();
                        }
                    });
                    $('#crd_txt').hide();
                    $('#sig-submitBtn1').hide();
                    $('input[name="selected_option"]').val('Check');
                    ajaFunction();
                }
            });
            $('#inlineRadio4').change(function() {
                if ($(this).is(':checked')) {
                    $('.prc').each(function() {
                        if ($(this).data('name') === 'Credit Card Processing Fee(4%)' || $(this)
                            .data('name') === 'Credit Card Fee(4%)') {

                            // var amount = $(this).find('.total').text();
                            // var actual_text = "{{ number_format($total_amount, 2) }}";
                            // var num1 = parseFloat(actual_text.replace(/,/g, ''));
                            // let num2 = parseFloat(amount.replace(/[$,]/g, ''));
                            // let result = num1 - num2;
                            // var currency = "{{ $currency }}";
                            // $('#total_amout').html(currency+result);

                            // var amount1 = $(this).find('.total').text();
                            // var actual_text1 = $('input[name="total_amount"]').val();
                            // var num3 = parseFloat(actual_text1.replace(/,/g, ''));
                            // var num4 = parseFloat(amount1.replace(/[$,]/g, ''));                    
                            // let result1 = num3 - num4;
                            // result1 = result1.toFixed(1);
                            // $('#total_amout1').html(currency+result1);

                            $(this).hide();
                        }
                    });
                    $('#crd_txt').hide();
                    $('#sig-submitBtn1').hide();
                    $('input[name="selected_option"]').val('other');
                    ajaFunction();
                }
            });
        });

        $(document).on("change", ".firewood-rate-per_night", function() {
            var target = $(this).data("target");
            $("#" + target).val("{{ $main_data['total_night'] }}");
            ajaFunction();
        });

        $(document).on("change", ".firewood-rate-per_stay", function() {
            var target = $(this).data("target");
            $("#" + target).val(1);
            ajaFunction();
        });

        $(document).on("change", ".firewood-rate-per_person", function() {
            var target = $(this).data("target");
            $("#" + target).toggleClass("d-none");
            ajaFunction();
        });

        $(document).on("change", ".hottub-rate-per_night", function() {
            var target = $(this).data("target");
            $("#" + target).val("{{ $main_data['total_night'] }}");
            ajaFunction();
        });

        $(document).on("change", ".hottub-rate-per_stay", function() {
            var target = $(this).data("target");
            $("#" + target).val(1);
            ajaFunction();
        });

        $(document).on("change", ".hottub-rate-per_person", function() {
            var target = $(this).data("target");
            $("#" + target).toggleClass("d-none");
            ajaFunction();
        });

        $(document).on("change", ".rvhookup-rate-per_night", function() {
            var quantity = $(this).data("quantity");
            $("#" + quantity).toggleClass("d-none");
            var target = $(this).data("target");
            $("#" + target).val("{{ $main_data['total_night'] }}");

            // ajaFunction();
        });

        $(document).on("change", ".rvhookup_rate_id_quantity", function() {
            // ajaFunction();
        });

        $(document).on("change", ".rvhookup-rate-per_stay", function() {
            var target = $(this).data("target");
            var quantity = $(this).data("quantity");
            $("#" + quantity).toggleClass("d-none");
            $("#" + target).val(1);
            // ajaFunction();
        });

        $(document).on("change", ".rvhookup-rate-per_person", function() {
            var target = $(this).data("target");
            var quantity = $(this).data("quantity");
            $("#" + quantity).toggleClass("d-none");
            $("#" + target).toggleClass("d-none");
            // ajaFunction();
        });

        $(document).on("change", ".visitor-rate-per_night", function() {
            var target = $(this).data("target");
            $("#" + target).val("{{ $main_data['total_night'] }}");
            ajaFunction();
        });

        $(document).on("change", ".visitor-rate-per_stay", function() {
            var target = $(this).data("target");
            $("#" + target).val(1);
            ajaFunction();
        });

        $(document).on("change", ".visitor-rate-per_person", function() {
            var target = $(this).data("target");
            $("#" + target).toggleClass("d-none");
            ajaFunction();
        });

        $(document).on("change", ".common-field-show-rate", function() {
            var target = $(this).data("target");
            if ($(this).prop("checked") == false)
                $("#" + target).val(0);
            $("#" + target).toggleClass("d-none");
        });

        $(document).on("change", ".rate-calculateion-data", function() {
            ajaFunction();
        });

        function ajaFunction() {
            $("#sygnius-loader").removeClass("d-none");
            url = "{!! route('getQUoteCHangeableData') . '?' . http_build_query(request()->all()) !!}";
            data = $("#web-form-data").serialize();
            $.post(url, data, function(data) {
                if (data.status == "404") {
                    toastr.error(data.message);
                    $("#sygnius-loader").addClass("d-none");
                } else {
                    $("#show-price-div-data").html(data.show_price_div_data);
                    $(".row-data-hidden").html(data.row_data_hidden);
                    $("#sygnius-loader").addClass("d-none");
                }
            });
        }

        function petdec($input_target, $field_target) {
            val = parseInt($($input_target).val());
            if (val == 0) {} else {
                val = val - 1;
            }
            $($input_target).val(val);
            $($field_target).html(val);
        }

        function petinc($input_target, $field_target) {
            val = parseInt($($input_target).val());
            if (val < {{ $property->max_pet }}) {
                val = val + 1;
            }
            $($input_target).val(val);
            $($field_target).html(val);
        }
        /*
             $('.input-images-2').imageUploader({
                label:'Drag & Drop file here or click to browse',
                maxFiles:1,
                imagesInputName: "image",
                extensions:['.jpg', '.jpeg', '.png', '.gif', '.svg','.webp'],
                mimes:['image/jpeg', 'image/png', 'image/gif', 'image/svg+xml' ,'image/webp']
            });
        */
        function functiondec($getter_setter, $show, $cal) {
            $("#submit-button-gaurav-data").hide();
            val = parseInt($($getter_setter).val());
            if (val > 0) {
                val = val - 1;
            }
            $($getter_setter).val(val);
            person1 = val;
            person2 = parseInt($($cal).val());
            $show_data = person1 + person2;
            $show_actual_data = $show_data + " Guests";
            if ($getter_setter == "#adults-data") {
                $($getter_setter + '-show').html(val);
                if (val <= 1) {
                    $($getter_setter + '-show').html(val);
                }
            } else {
                $($getter_setter + '-show').html(val);
                if (val <= 1) {
                    $($getter_setter + '-show').html(val);
                }
            }
            $($show).val($show_actual_data);
        }

        function functioninc($getter_setter, $show, $cal) {
            $("#submit-button-gaurav-data").hide();
            val = parseInt($($getter_setter).val());
            person1 = val;
            person2 = parseInt($($cal).val());
            $show_data = person1 + person2;
            @if ($property->sleeps)
                if ($show_data < {{ $property->sleeps }}) {
                    val = val + 1;
                }
            @endif
            person1 = val;
            person2 = parseInt($($cal).val());
            $show_data = person1 + person2;
            $($getter_setter).val(val);
            $show_actual_data = $show_data + " Guests";
            $($show).val($show_actual_data);
            if ($getter_setter == "#adults-data") {
                $($getter_setter + '-show').html(val);
                if (val <= 1) {
                    $($getter_setter + '-show').html(val);
                }
            } else {
                $($getter_setter + '-show').html(val);
                if (val <= 1) {
                    $($getter_setter + '-show').html(val);
                }
            }
            ajaxCallingData();
        }
    </script>

    <script>
        function ajaxCallingData() {}

        function clearDataForm() {
            $("#start_date").val('');
            $("#end_date").val('');
        }

        ['start_date', 'end_date'].forEach(function (id) {
            var el = document.getElementById(id);
            if (!el) return;
            el.addEventListener('change', function () {
                if (document.getElementById('start_date').value && document.getElementById('end_date').value) {
                    ajaxCallingData();
                }
            });
        });


        $(document).on("submit", "#save-booking-data", function() {
            $("#sygnius-loader").removeClass("d-none");
            var err = checkSignatureErr();
            if (err == false) {
                $("#sygnius-loader").addClass("d-none");
            }
        });
        $(document).on("submit", "#guest-changer-data", function() {
            $("#sygnius-loader").removeClass("d-none");
            var err = checkSignatureErr();
            if (err == false) {
                $("#sygnius-loader").addClass("d-none");
            }
        });
        $(document).on("submit", "#quote_form", function() {
            $("#sygnius-loader").removeClass("d-none");
            var err = checkSignatureErr();
            if (err == false) {
                $("#sygnius-loader").addClass("d-none");
            }
        });

        $(document).on("change", "#is_coupon", function() {
            if ($(this).prop("checked") == true) {
                $("#coupon-form").show();
            } else {
                $("#coupon-form").hide();
            }
        });
        $(function() {
            @if (Request::get('coupon'))
                $("#is_coupon").prop("checked", "true");
                $("#coupon-form").show();
            @endif
        });

        (function() {
            window.requestAnimFrame = (function(callback) {
                return window.requestAnimationFrame ||
                    window.webkitRequestAnimationFrame ||
                    window.mozRequestAnimationFrame ||
                    window.oRequestAnimationFrame ||
                    window.msRequestAnimaitonFrame ||
                    function(callback) {
                        window.setTimeout(callback, 1000 / 60);
                    };
            })();

            var canvas = document.getElementById("sig-canvas");
            var ctx = canvas.getContext("2d");
            ctx.strokeStyle = "#222222";
            ctx.lineWidth = 4;

            var drawing = false;
            var mousePos = {
                x: 0,
                y: 0
            };
            var lastPos = mousePos;

            canvas.addEventListener("mousedown", function(e) {
                drawing = true;
                lastPos = getMousePos(canvas, e);
            }, false);

            canvas.addEventListener("mouseup", function(e) {
                drawing = false;
            }, false);

            canvas.addEventListener("mousemove", function(e) {
                mousePos = getMousePos(canvas, e);
            }, false);

            // Add touch event support for mobile
            canvas.addEventListener("touchstart", function(e) {

            }, false);

            canvas.addEventListener("touchmove", function(e) {
                var touch = e.touches[0];
                var me = new MouseEvent("mousemove", {
                    clientX: touch.clientX,
                    clientY: touch.clientY
                });
                canvas.dispatchEvent(me);
            }, false);

            canvas.addEventListener("touchstart", function(e) {
                mousePos = getTouchPos(canvas, e);
                var touch = e.touches[0];
                var me = new MouseEvent("mousedown", {
                    clientX: touch.clientX,
                    clientY: touch.clientY
                });
                canvas.dispatchEvent(me);
            }, false);

            canvas.addEventListener("touchend", function(e) {
                var me = new MouseEvent("mouseup", {});
                canvas.dispatchEvent(me);
            }, false);

            function getMousePos(canvasDom, mouseEvent) {
                var rect = canvasDom.getBoundingClientRect();
                return {
                    x: mouseEvent.clientX - rect.left,
                    y: mouseEvent.clientY - rect.top
                }
            }

            function getTouchPos(canvasDom, touchEvent) {
                var rect = canvasDom.getBoundingClientRect();
                return {
                    x: touchEvent.touches[0].clientX - rect.left,
                    y: touchEvent.touches[0].clientY - rect.top
                }
            }

            function renderCanvas() {
                if (drawing) {
                    ctx.moveTo(lastPos.x, lastPos.y);
                    ctx.lineTo(mousePos.x, mousePos.y);
                    ctx.stroke();
                    lastPos = mousePos;
                }
            }

            // Prevent scrolling when touching the canvas
            document.body.addEventListener("touchstart", function(e) {
                if (e.target == canvas) {
                    e.preventDefault();
                }
            }, false);
            document.body.addEventListener("touchend", function(e) {
                if (e.target == canvas) {
                    e.preventDefault();
                }
            }, false);
            document.body.addEventListener("touchmove", function(e) {
                if (e.target == canvas) {
                    e.preventDefault();
                }
            }, false);

            (function drawLoop() {
                requestAnimFrame(drawLoop);
                renderCanvas();
            })();

            function clearCanvas() {
                canvas.width = canvas.width;
            }

            // Set up the UI
            var sigText = document.getElementById("sig-dataUrl");
            var sigImage = document.getElementById("sig-image");
            var clearBtn = document.getElementById("sig-clearBtn");
            var submitBtn = document.getElementById("sig-submitBtn");
            var submitBtn1 = document.getElementById("sig-submitBtn1");
            clearBtn.addEventListener("click", function(e) {
                clearCanvas();
                sigText.innerHTML = "Data URL for your signature will go here!";
                //sigImage.setAttribute("src", "");
            }, false);
            submitBtn.addEventListener("click", function(e) {
                var dataUrl = canvas.toDataURL();
                sigText.innerHTML = dataUrl;
                //sigImage.setAttribute("src", dataUrl);
            }, false);

            submitBtn1.addEventListener("click", function(e) {
                var dataUrl = canvas.toDataURL();
                sigText.innerHTML = dataUrl;
                //sigImage.setAttribute("src", dataUrl);
            }, false);

        })();

        {{-- function checkSignature() {
            var canvas = document.getElementById('sig-canvas');
            if (isCanvasEmpty(canvas)) {
                var element = document.getElementById("sygnius-loader");
                element.classList.add("d-none");
                toastr.error('Please enter signature!');
                //alert_by();   
                return false;
            } else {

                return true;
            }
        } --}}

        function checkSignatureErr() {
            var canvas = document.getElementById('sig-canvas');
            if (isCanvasEmpty(canvas)) {
                return false;
            } else {
                return true;
            }
        }


        function isCanvasEmpty(canvas) {
            const blankCanvas = document.createElement('canvas');
            blankCanvas.width = canvas.width;
            blankCanvas.height = canvas.height;
            return canvas.toDataURL() === blankCanvas.toDataURL();
        }
    </script>       

<script src="https://js.stripe.com/v2/"></script>
<script>
$(document).ready(function () {

    var $form = $('#payment-form');

    $form.on('submit', function (e) {

        if (!$form.data('cc-on-file')) {
            e.preventDefault();

            $('.error').addClass('hide');

            Stripe.setPublishableKey(
                $form.data('stripe-publishable-key')
            );

            Stripe.card.createToken({
                number: $('.card-number').val(),
                cvc: $('.card-cvc').val(),
                exp_month: $('.card-expiry-month').val(),
                exp_year: $('.card-expiry-year').val()
            }, stripeResponseHandler);
        }
    });

    function stripeResponseHandler(status, response) {

        if (response.error) {
            $('.error').removeClass('hide')
                .find('.alert')
                .text(response.error.message);

            $('#sig-submitBtn').prop('disabled', false);

        } else {
            var token = response.id;

            // Append token
            $form.append(
                "<input type='hidden' name='stripeToken' value='" + token + "' />"
            );

            // Clear sensitive fields
            $('.card-number, .card-cvc, .card-expiry-month, .card-expiry-year').val('');

            // Submit form
            $form.get(0).submit();
        }
    }
});
</script>

@stop
