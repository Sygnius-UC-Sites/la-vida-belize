<div class="owner-form-grid">
    <div class="owner-form-card">
        <div class="owner-form-card-heading">Discount basics</div>
        <div class="owner-form-card-description">
            Set the public coupon name, guest code, eligible property, and discount value.
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    {!! Form::label("name", "Coupon name *") !!}
                    {!! Form::text("name", null, ["class" => "form-control", "required" => "required", "placeholder" => "e.g. Returning guest offer"]) !!}
                    <span class="text-danger">{{ $errors->first("name") }}</span>
                </div>
            </div>

            <div class="col-md-6">
                <div class="form-group">
                    {!! Form::label("code", "Coupon code *") !!}
                    {!! Form::text("code", null, ["class" => "form-control", "required" => "required", "placeholder" => "e.g. RETURN10"]) !!}
                    <span class="text-danger">{{ $errors->first("code") }}</span>
                </div>
            </div>

            <div class="col-md-6">
                <div class="form-group">
                    {!! Form::label("property_id", "Property") !!}
                    {!! Form::select("property_id", ModelHelper::getProppertySelectList(), null, ["class" => "form-control", "placeholder" => "All properties"]) !!}
                    <small class="text-muted d-block">Leave blank if this coupon can apply to every property.</small>
                    <span class="text-danger">{{ $errors->first("property_id") }}</span>
                </div>
            </div>

            <div class="col-md-3">
                <div class="form-group">
                    {!! Form::label("type", "Discount type *") !!}
                    {!! Form::select("type", ["per" => "Percent (%)", "excat" => "Fixed amount ($)"], null, ["class" => "form-control", "required" => "required"]) !!}
                    <span class="text-danger">{{ $errors->first("type") }}</span>
                </div>
            </div>

            <div class="col-md-3">
                <div class="form-group">
                    {!! Form::label("amount", "Discount amount *") !!}
                    {!! Form::number("amount", null, ["class" => "form-control", "required" => "required", "placeholder" => "10"]) !!}
                    <span class="text-danger">{{ $errors->first("amount") }}</span>
                </div>
            </div>
        </div>
    </div>

    <div class="owner-form-card">
        <div class="owner-form-card-heading">Booking rules</div>
        <div class="owner-form-card-description">
            Optional limits that prevent overuse or apply the discount only to larger bookings.
        </div>

        <div class="row">
            <div class="col-md-3">
                <div class="form-group">
                    {!! Form::label("min_stay", "Minimum nights") !!}
                    {!! Form::number("min_stay", null, ["class" => "form-control", "placeholder" => "No minimum"]) !!}
                    <span class="text-danger">{{ $errors->first("min_stay") }}</span>
                </div>
            </div>

            <div class="col-md-3">
                <div class="form-group">
                    {!! Form::label("max_uses", "Total uses") !!}
                    {!! Form::number("max_uses", null, ["class" => "form-control", "placeholder" => "Unlimited"]) !!}
                    <span class="text-danger">{{ $errors->first("max_uses") }}</span>
                </div>
            </div>

            <div class="col-md-3">
                <div class="form-group">
                    {!! Form::label("max_uses_per_customer", "Uses per guest") !!}
                    {!! Form::number("max_uses_per_customer", null, ["class" => "form-control", "placeholder" => "e.g. 1"]) !!}
                    <span class="text-danger">{{ $errors->first("max_uses_per_customer") }}</span>
                </div>
            </div>

            <div class="col-md-3">
                <div class="form-group">
                    {!! Form::label("min_order_amount", "Minimum booking total ($)") !!}
                    {!! Form::number("min_order_amount", null, ["class" => "form-control", "step" => "0.01", "placeholder" => "No minimum"]) !!}
                    <span class="text-danger">{{ $errors->first("min_order_amount") }}</span>
                </div>
            </div>
        </div>
    </div>

    <div class="owner-form-card">
        <div class="owner-form-card-heading">Valid dates</div>
        <div class="owner-form-card-description">
            Choose the date window when guests are allowed to use this coupon.
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    {!! Form::label("start_date", "Start date") !!}
                    {!! Form::text("start_date", null, [
                        "class" => "form-control",
                        "readonly",
                        "data-vr-datepicker" => "range",
                        "data-end-input" => "#coupon-end-date",
                        "placeholder" => "Select start date",
                    ]) !!}
                    <span class="text-danger">{{ $errors->first("start_date") }}</span>
                </div>
            </div>

            <div class="col-md-6">
                <div class="form-group">
                    {!! Form::label("end_date", "End date") !!}
                    {!! Form::text("end_date", null, [
                        "class" => "form-control",
                        "readonly",
                        "id" => "coupon-end-date",
                        "placeholder" => "Select end date",
                    ]) !!}
                    <span class="text-danger">{{ $errors->first("end_date") }}</span>
                </div>
            </div>

            <div class="col-md-12">
                <div class="form-group">
                    {!! Form::label("description", "Internal notes") !!}
                    {!! Form::textarea("description", null, ["class" => "form-control", "rows" => "3", "placeholder" => "Optional notes for the owner/admin team"]) !!}
                    <span class="text-danger">{{ $errors->first("description") }}</span>
                </div>
            </div>
        </div>
    </div>
</div>
