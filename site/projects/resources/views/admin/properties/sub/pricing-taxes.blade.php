{{-- Phase 1 of property-edit redesign — pricing-related fields lifted from
     the old kitchen-sink "Booking Rules" tab so admins can find pricing in
     one place that's actually called "Pricing & taxes".

     Field names + IDs + values + helper text are verbatim from booking-rules.
     No backend / validation changes. Base nightly rate + weekend rate stay
     on the Calendar tab — they're managed inline there with the per-day
     overrides via a separate AJAX endpoint.

     Plan: docs/superpowers/plans/2026-05-08-property-edit-redesign.md --}}

<h4 class="text-warning">Pricing &amp; taxes</h4>
<p class="text-muted small mb-3">
    Per-property tax, security deposit, pet fees and extra-guest fee.
    Base nightly rate, weekend rate, and per-day overrides live on the
    <strong>Calendar</strong> tab. Optional one-off charges (kayak rental,
    welcome basket, etc.) live on the <strong>Additional Fees</strong> tab.
</p>

{{-- QA pass: cleaning fee field. The `properties.cleaning_fee` column has existed
     since the original schema and is already wired into the quote/checkout math
     via the existing tax/discount toggles below, but no admin input field
     surfaced it. Adding it here so admins can set per-property cleaning fees. --}}
<div class="card card-info">
    <div class="card-header">
        <h5 class="card-title m-0">Cleaning fee</h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-4">
                <div class="form-group">
                    {!! Form::label("cleaning_fee", "Cleaning fee per stay ($)") !!}
                    {!! Form::number("cleaning_fee", $data->cleaning_fee ?? null, ["class" => "form-control", "min" => 0, "step" => "0.01", "placeholder" => "e.g. 100.00"]) !!}
                    <small class="form-text text-muted">One-time fee added to every booking. Set to <code>0</code> or leave empty to skip. Tax and discount behavior for cleaning is controlled in the two tables further down this page.</small>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ========== Per-property Tax & Deposit ========== --}}
<div class="card card-info mt-3">
    <div class="card-header">
        <h5 class="card-title m-0">Tax &amp; security deposit</h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-3">
                <div class="form-group">
                    {!! Form::label("tax", "Tax (%)") !!}
                    {!! Form::text("tax", $data->tax ?? null, ["class" => "form-control", "placeholder" => "e.g. 13.00"]) !!}
                    <small class="text-muted">Applied to the line items selected in "Custom Tax Base" below.</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    {!! Form::label("security_deposit_amount", "Refundable security deposit ($)") !!}
                    {!! Form::number("security_deposit_amount", $data->security_deposit_amount ?? null, ["class" => "form-control", "min" => 0, "step" => "0.01"]) !!}
                    <small class="text-muted">Refundable deposit amount in dollars. Whether it's actually charged depends on the toggle to the right.</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    {!! Form::label("security_deposit_charge_at_booking", "Charge deposit at booking?") !!}
                    {!! Form::select("security_deposit_charge_at_booking", [0 => "No — informational only", 1 => "Yes — charge with booking total"], $data->security_deposit_charge_at_booking ?? 0, ["class" => "form-control"]) !!}
                    <small class="text-muted">Yes = added to the guest's total at checkout, refundable on demand later. No = the amount appears in quotes / agreements but is not charged.</small>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    {!! Form::label("security_deposit_text", "Deposit description (shown to guest)") !!}
                    {!! Form::text("security_deposit_text", $data->security_deposit_text ?? null, ["class" => "form-control", "maxlength" => 191, "placeholder" => "e.g. Refundable on departure if no damages"]) !!}
                    <small class="text-muted">Free-form description shown to the guest (e.g. 'Refundable on departure if no damages').</small>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ========== Pet fees ========== --}}
<div class="card card-info">
    <div class="card-header">
        <h5 class="card-title m-0">Pet fees</h5>
    </div>
    <div class="card-body">
        <p class="text-muted small">Charged when the booking includes pets and "Pets allowed" (House Rules tab) is set to Yes. Calculated as <code>pets &times; rate &times; (nights if per-day else 1)</code>.</p>
        <div class="row">
            <div class="col-md-3">
                <div class="form-group">
                    {!! Form::label("pet_fee", "Per-pet rate ($)") !!}
                    {!! Form::number("pet_fee", $data->pet_fee ?? null, ["class" => "form-control", "min" => 0, "step" => "0.01"]) !!}
                    <small class="text-muted">Per-pet rate in dollars. Multiplied by pet count and (optionally) by nights.</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    {!! Form::label("pet_fee_interval", "Billing interval") !!}
                    {!! Form::select("pet_fee_interval", ["one_time" => "One-time (per stay)", "per_day" => "Per day"], $data->pet_fee_interval ?? "one_time", ["class" => "form-control"]) !!}
                    <small class="text-muted">Per day = pet rate × pet count × nights. One-time = pet rate × pet count, charged once for the whole stay.</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    {!! Form::label("pet_fee_type", "Taxable?") !!}
                    {!! Form::select("pet_fee_type", ["taxable" => "Taxable", "non-taxable" => "Not taxable"], $data->pet_fee_type ?? "taxable", ["class" => "form-control"]) !!}
                    <small class="text-muted">Whether the pet fee adds tax in the quote breakdown.</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    {!! Form::label("max_pet", "Max pets allowed") !!}
                    {!! Form::selectRange("max_pet", 0, 10, $data->max_pet ?? 0, ["class" => "form-control"]) !!}
                    <small class="text-muted">Maximum pets the guest can select on the booking widget. 0 = no pets allowed regardless of "Pets allowed" above.</small>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ========== Extra-guest fee ========== --}}
<div class="card card-info">
    <div class="card-header">
        <h5 class="card-title m-0">Extra-guest fee</h5>
    </div>
    <div class="card-body">
        <p class="text-muted small">Charged when the booking exceeds the threshold below. Calculated as <code>(guests &minus; threshold) &times; rate &times; nights</code>. Set rate to 0 to disable.</p>
        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    {!! Form::label("guest_fee", "Per-extra-guest, per-night rate ($)") !!}
                    {!! Form::number("guest_fee", $data->guest_fee ?? null, ["class" => "form-control", "min" => 0, "step" => "0.01"]) !!}
                    <small class="text-muted">Dollar rate per extra guest, per night. Set to 0 to disable extra-guest charges.</small>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    {!! Form::label("no_of_guest", "Threshold (extra-guest fee applies above this many guests)") !!}
                    {!! Form::number("no_of_guest", $data->no_of_guest ?? null, ["class" => "form-control", "min" => 0, "max" => 200]) !!}
                    <small class="text-muted">If the booking has more than this many guests, each extra guest is charged the rate above.</small>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ========== Custom Tax Base (override global) ========== --}}
<div class="card card-secondary collapsed-card">
    <div class="card-header">
        <h5 class="card-title m-0">Custom Tax Base (which line items are taxable for this property)</h5>
        <div class="card-tools">
            <button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fas fa-plus"></i></button>
        </div>
    </div>
    <div class="card-body" style="display: none;">
        <p class="text-muted">Override which line items the property's tax % applies to. Most properties leave these on '(use global)' and rely on the global default in Settings → Tax.</p>
        <div class="row">
            @foreach (['nights' => 'Nights', 'cleaning_fee' => 'Cleaning fee', 'pet_fee' => 'Pet fee', 'extra_guest_fee' => 'Extra guest fee'] as $cat => $label)
                <div class="col-md-3">
                    <div class="form-group">
                        {!! Form::label("tax_applies_to_$cat", $label) !!}
                        {!! Form::select("tax_applies_to_$cat",
                            ["" => "(use global)", "1" => "Taxable", "0" => "Not taxable"],
                            $data->{"tax_applies_to_$cat"} ?? "",
                            ["class" => "form-control"]) !!}
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>

{{-- ========== Custom Discount Base (override global) ========== --}}
<div class="card card-secondary collapsed-card">
    <div class="card-header">
        <h5 class="card-title m-0">Custom Discount Base (which line items LOS discount applies to)</h5>
        <div class="card-tools">
            <button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fas fa-plus"></i></button>
        </div>
    </div>
    <div class="card-body" style="display: none;">
        <p class="text-muted">Override which line items the LOS (length-of-stay) discount applies to. Same global-default pattern as Custom Tax Base above.</p>
        <div class="row">
            @foreach (['nights' => 'Nights', 'cleaning_fee' => 'Cleaning fee', 'pet_fee' => 'Pet fee', 'extra_guest_fee' => 'Extra guest fee'] as $cat => $label)
                <div class="col-md-3">
                    <div class="form-group">
                        {!! Form::label("discount_applies_to_$cat", $label) !!}
                        {!! Form::select("discount_applies_to_$cat",
                            ["" => "(use global)", "1" => "Discountable", "0" => "Not discountable"],
                            $data->{"discount_applies_to_$cat"} ?? "",
                            ["class" => "form-control"]) !!}
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
