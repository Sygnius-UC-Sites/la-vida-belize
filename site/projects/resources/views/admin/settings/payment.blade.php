@extends('admin.layouts')

@section('title', 'Payment Settings')

@section('content_header')
    <h1>Payment Settings</h1>
    <p class="text-muted">Global defaults for installments, tax base, and discount base. Per-property overrides can be set on each property's "Booking Rules" tab.</p>
@stop

@section('content')
@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

@if($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

{!! Form::open(['route' => 'admin.settings.payment.update', 'method' => 'POST']) !!}

{{-- ========== Currency (Phase 13C — moved here from the deleted Booking & Payments tab) ========== --}}
<div class="card">
    <div class="card-header">
        <h5 class="card-title m-0">Currency</h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    {!! Form::label("payment_currency", "Currency symbol") !!}
                    {!! Form::select("payment_currency",
                        ['$' => 'Dollar ($)', '€' => 'Euro (€)', 'CAD' => 'CAD'],
                        $settings['payment_currency'] ?? '$',
                        ["class" => "form-control"]) !!}
                    <small class="form-text text-muted">Symbol shown in prices, quotes, and emails site-wide. Cosmetic only — actual transaction currency is configured in your payment processor (Stripe / Square / etc.).</small>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ========== Installment plan ========== --}}
<div class="card">
    <div class="card-header">
        <h5 class="card-title m-0">Installment plan (global default)</h5>
    </div>
    <div class="card-body">
        <p class="text-muted small">Owner picks a plan and the system auto-applies it to bookings whose lead-time meets the threshold.</p>
        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    {!! Form::label("installment_plan", "Plan") !!}
                    {!! Form::select("installment_plan",
                        ["full" => "Pay in full only", "two" => "2 installments (when lead-time threshold met)", "three" => "3 installments (when lead-time threshold met)"],
                        $settings['installment_plan'] ?? 'full',
                        ["class" => "form-control"]) !!}
                </div>
            </div>
        </div>

        <h6>2-installment configuration (used when lead time &ge; Min lead days)</h6>
        <div class="row">
            <div class="col-md-4">
                <div class="form-group">
                    {!! Form::label("installment_2_days", "Days from installment 1 to installment 2") !!}
                    {!! Form::number("installment_2_days", $settings['installment_2_days'] ?? 30, ["class" => "form-control", "min" => 0, "required" => true]) !!}
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    {!! Form::label("installment_2_percent", "Installment 2 percent (1-99)") !!}
                    {!! Form::number("installment_2_percent", $settings['installment_2_percent'] ?? 35, ["class" => "form-control", "min" => 1, "max" => 99, "required" => true]) !!}
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    {!! Form::label("installment_2_min_lead_days", "Min lead days for 2-installment offer") !!}
                    {!! Form::number("installment_2_min_lead_days", $settings['installment_2_min_lead_days'] ?? 46, ["class" => "form-control", "min" => 0, "required" => true]) !!}
                </div>
            </div>
        </div>

        <h6>3-installment configuration (used when lead time &ge; Min lead days)</h6>
        <div class="row">
            <div class="col-md-4">
                <div class="form-group">
                    {!! Form::label("installment_3_days", "Days from installment 2 to installment 3") !!}
                    {!! Form::number("installment_3_days", $settings['installment_3_days'] ?? 15, ["class" => "form-control", "min" => 0, "required" => true]) !!}
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    {!! Form::label("installment_3_percent", "Installment 3 percent (1-99)") !!}
                    {!! Form::number("installment_3_percent", $settings['installment_3_percent'] ?? 20, ["class" => "form-control", "min" => 1, "max" => 99, "required" => true]) !!}
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    {!! Form::label("installment_3_min_lead_days", "Min lead days for 3-installment offer") !!}
                    {!! Form::number("installment_3_min_lead_days", $settings['installment_3_min_lead_days'] ?? 115, ["class" => "form-control", "min" => 0, "required" => true]) !!}
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ========== Tax base ========== --}}
<div class="card">
    <div class="card-header">
        <h5 class="card-title m-0">Tax base (which line items are taxable, by default)</h5>
    </div>
    <div class="card-body">
        <p class="text-muted small">Per-property overrides can change this on each property's Booking Rules tab.</p>
        <div class="row">
            @foreach (['nights' => 'Nights', 'cleaning_fee' => 'Cleaning fee', 'pet_fee' => 'Pet fee', 'extra_guest_fee' => 'Extra guest fee', 'hottub_addon' => 'Hot tub add-on', 'firewood_addon' => 'Firewood add-on', 'rv_hookup_addon' => 'RV hookup add-on', 'extra_visitor_addon' => 'Extra visitor add-on'] as $cat => $label)
                <div class="col-md-3">
                    <div class="form-group form-check">
                        <input type="hidden" name="tax_applies_to_{{ $cat }}" value="0">
                        <input type="checkbox" name="tax_applies_to_{{ $cat }}" value="1" id="tax_applies_to_{{ $cat }}" class="form-check-input" {{ ($settings["tax_applies_to_$cat"] ?? '0') === '1' ? 'checked' : '' }}>
                        <label class="form-check-label" for="tax_applies_to_{{ $cat }}">{{ $label }}</label>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>

{{-- ========== Discount base ========== --}}
<div class="card">
    <div class="card-header">
        <h5 class="card-title m-0">Discount base (which line items the LOS discount applies to, by default)</h5>
    </div>
    <div class="card-body">
        <p class="text-muted small">Length-of-stay discounts (weekly, monthly) are pulled from per-day or date-range rate rows. This setting controls which line items the discount % is applied to.</p>
        <div class="row">
            @foreach (['nights' => 'Nights', 'cleaning_fee' => 'Cleaning fee', 'pet_fee' => 'Pet fee', 'extra_guest_fee' => 'Extra guest fee', 'hottub_addon' => 'Hot tub add-on', 'firewood_addon' => 'Firewood add-on', 'rv_hookup_addon' => 'RV hookup add-on', 'extra_visitor_addon' => 'Extra visitor add-on'] as $cat => $label)
                <div class="col-md-3">
                    <div class="form-group form-check">
                        <input type="hidden" name="discount_applies_to_{{ $cat }}" value="0">
                        <input type="checkbox" name="discount_applies_to_{{ $cat }}" value="1" id="discount_applies_to_{{ $cat }}" class="form-check-input" {{ ($settings["discount_applies_to_$cat"] ?? '0') === '1' ? 'checked' : '' }}>
                        <label class="form-check-label" for="discount_applies_to_{{ $cat }}">{{ $label }}</label>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>

<button type="submit" class="btn btn-primary">Save settings</button>

{!! Form::close() !!}
@stop
