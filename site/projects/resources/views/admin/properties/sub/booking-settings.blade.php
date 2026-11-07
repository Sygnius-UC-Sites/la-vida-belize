{{-- Phase 1 of property-edit redesign — booking-flow controls lifted from
     the old kitchen-sink "Booking Rules" tab.

     Field names + IDs + values are verbatim. Plan:
     docs/superpowers/plans/2026-05-08-property-edit-redesign.md --}}

<h4 class="text-warning">Booking settings</h4>
<p class="text-muted small mb-3">
    How guests can book this property. Instant book versus request, stay-length
    constraints, and per-property payment-plan overrides for the global default.
</p>

{{-- ========== Booking Mode ========== --}}
<div class="card card-info">
    <div class="card-header">
        <h5 class="card-title m-0">Booking mode</h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    {!! Form::label("instant_booking_enabled", "Instant booking enabled?") !!}
                    {!! Form::select("instant_booking_enabled", [0 => "No (request to book)", 1 => "Yes (book and pay immediately)"], $data->instant_booking_enabled ?? 0, ["class" => "form-control"]) !!}
                    <small class="text-muted">Yes = guest pays at booking. No = booking goes through admin approval first.</small>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ========== Stay Constraints ========== --}}
<div class="card card-info">
    <div class="card-header">
        <h5 class="card-title m-0">Stay constraints</h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    {!! Form::label("min_stay", "Minimum stay (nights)") !!}
                    {!! Form::number("min_stay", $data->min_stay ?? null, ["class" => "form-control", "min" => 1, "placeholder" => "e.g. 2"]) !!}
                    <small class="text-muted">Bookings shorter than this many nights are blocked.</small>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    {!! Form::label("max_stay", "Maximum stay (nights)") !!}
                    {!! Form::number("max_stay", $data->max_stay ?? null, ["class" => "form-control", "min" => 1, "placeholder" => "Leave blank for no max"]) !!}
                    <small class="text-muted">Bookings longer than this many nights are blocked. Leave blank for no maximum.</small>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ========== Custom Payment Plan ========== --}}
<div class="card card-secondary collapsed-card">
    <div class="card-header">
        <h5 class="card-title m-0">Custom Payment Plan (overrides global default)</h5>
        <div class="card-tools">
            <button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fas fa-plus"></i></button>
        </div>
    </div>
    <div class="card-body" style="display: none;">
        <p class="text-muted">Leave blank to use the global default from <strong>Settings &rarr; Payment</strong>.</p>
        <div class="row">
            <div class="col-md-3">
                <div class="form-group">
                    {!! Form::label("installment_plan", "Plan") !!}
                    {!! Form::select("installment_plan",
                        ["" => "(use global)", "full" => "Pay in full", "two" => "2 installments", "three" => "3 installments"],
                        $data->installment_plan ?? "",
                        ["class" => "form-control"]) !!}
                    <small class="text-muted">Override the global default for this property only. Leave blank to inherit.</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    {!! Form::label("installment_2_days", "Installment 2 due (days)") !!}
                    {!! Form::number("installment_2_days", $data->installment_2_days ?? null, ["class" => "form-control"]) !!}
                    <small class="text-muted">Days after the booking date that installment #2 is due.</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    {!! Form::label("installment_2_percent", "Installment 2 (%)") !!}
                    {!! Form::number("installment_2_percent", $data->installment_2_percent ?? null, ["class" => "form-control"]) !!}
                    <small class="text-muted">What percentage of the total is paid in installment #2.</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    {!! Form::label("installment_2_min_lead_days", "Min lead days for 2-installment") !!}
                    {!! Form::number("installment_2_min_lead_days", $data->installment_2_min_lead_days ?? null, ["class" => "form-control"]) !!}
                    <small class="text-muted">If the booking is fewer days out than this, the 2-installment plan isn't offered (collect in full instead).</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    {!! Form::label("installment_3_days", "Installment 3 due (days after #2)") !!}
                    {!! Form::number("installment_3_days", $data->installment_3_days ?? null, ["class" => "form-control"]) !!}
                    <small class="text-muted">Days after installment #2 that installment #3 is due.</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    {!! Form::label("installment_3_percent", "Installment 3 (%)") !!}
                    {!! Form::number("installment_3_percent", $data->installment_3_percent ?? null, ["class" => "form-control"]) !!}
                    <small class="text-muted">What percentage of the total is paid in installment #3.</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    {!! Form::label("installment_3_min_lead_days", "Min lead days for 3-installment") !!}
                    {!! Form::number("installment_3_min_lead_days", $data->installment_3_min_lead_days ?? null, ["class" => "form-control"]) !!}
                    <small class="text-muted">If the booking is fewer days out than this, the 3-installment plan isn't offered.</small>
                </div>
            </div>
        </div>
    </div>
</div>
