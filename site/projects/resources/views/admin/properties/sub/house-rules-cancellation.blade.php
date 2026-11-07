{{-- Phase 1 of property-edit redesign — house rules + cancellation policy
     lifted from "Booking Rules". The cancellation auto-fill JS lives at the
     bottom of this file; it stays scoped to the cancellation_policy_type_select
     element so it's safe to load wherever the field renders. --}}

<h4 class="text-warning">House rules &amp; cancellation</h4>
<p class="text-muted small mb-3">
    Pet, smoking and accessibility policies (shown in the property's
    house-rules section), plus the cancellation policy guests agree to at
    booking. Pet pricing lives on the <strong>Pricing &amp; taxes</strong> tab.
</p>

@php
    // Cancellation templates from basic_settings — used by the auto-fill JS.
    $cancelTemplates = \DB::table('basic_settings')
        ->where('name', 'like', 'cancellation_template_%')
        ->pluck('value', 'name')
        ->mapWithKeys(fn($v, $k) => [str_replace('cancellation_template_', '', $k) => $v])
        ->toArray();
@endphp

{{-- ========== House rules / accessibility ========== --}}
<div class="card card-info">
    <div class="card-header">
        <h5 class="card-title m-0">House rules &amp; accessibility</h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-3">
                <div class="form-group">
                    {!! Form::label("accepts_pets", "Pets allowed?") !!}
                    {!! Form::select("accepts_pets", [0 => "No", 1 => "Yes"], $data->accepts_pets ?? 0, ["class" => "form-control"]) !!}
                    <small class="text-muted">If No, the booking widget hides the pet count selector.</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    {!! Form::label("smoking_allowed", "Smoking allowed?") !!}
                    {!! Form::select("smoking_allowed", [0 => "No", 1 => "Yes"], $data->smoking_allowed ?? 0, ["class" => "form-control"]) !!}
                    <small class="text-muted">Shown on the property detail page house-rules section.</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    {!! Form::label("parties_allowed", "Parties allowed?") !!}
                    {!! Form::select("parties_allowed", [0 => "No", 1 => "Yes"], $data->parties_allowed ?? 0, ["class" => "form-control"]) !!}
                    <small class="text-muted">Shown on the property detail page house-rules section.</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    {!! Form::label("wheelchair_accessible", "Wheelchair accessible?") !!}
                    {!! Form::select("wheelchair_accessible", [0 => "No", 1 => "Yes"], $data->wheelchair_accessible ?? 0, ["class" => "form-control"]) !!}
                    <small class="text-muted">Shown on the property detail page accessibility section.</small>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ========== Cancellation policy ========== --}}
<div class="card card-info">
    <div class="card-header">
        <h5 class="card-title m-0">Cancellation policy</h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    {!! Form::label("cancellation_policy_type", "Cancellation policy") !!}
                    {!! Form::select("cancellation_policy_type",
                        ["flexible" => "Flexible", "moderate" => "Moderate", "strict" => "Strict", "firm" => "Firm", "super_strict" => "Super Strict", "custom" => "Custom (use the text below as-is)"],
                        $data->cancellation_policy_type ?? "moderate",
                        ["class" => "form-control", "id" => "cancellation_policy_type_select"]) !!}
                    <small class="text-muted">Picking a preset auto-fills the policy text below. Choose 'Custom' to keep whatever's already in the text box.</small>
                </div>
            </div>
            <div class="col-md-12">
                <div class="form-group">
                    {!! Form::label("cancellation_policy", "Cancellation policy text (shown to guest)") !!}
                    {!! Form::textarea("cancellation_policy", $data->cancellation_policy ?? "", ["class" => "form-control", "rows" => 3, "id" => "cancellation_policy_text"]) !!}
                    <small class="text-muted">Free-form policy shown to the guest at booking. Auto-filled when you pick a preset above.</small>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Auto-fill cancellation_policy textarea when type changes to a template.
// Same JS that used to live at the bottom of the old booking-rules tab.
(function () {
    const select = document.getElementById('cancellation_policy_type_select');
    const text = document.getElementById('cancellation_policy_text');
    const templates = @json($cancelTemplates);
    if (!select || !text) return;
    select.addEventListener('change', function () {
        const v = this.value;
        if (v === 'custom') return;  // don't overwrite custom text
        if (templates[v] !== undefined && (text.value.trim() === '' || confirm('Replace cancellation policy text with the ' + v + ' template?'))) {
            text.value = templates[v];
        }
    });
})();
</script>
