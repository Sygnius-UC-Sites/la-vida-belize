{{-- Phase 1 of property-edit redesign — rental-agreement controls in
     their own dedicated tab. Previously buried inside "Booking Rules"
     mixed with pricing and policies. Markup verbatim from the old
     booking-rules.blade.php; the Phase-2 Word doc upload widget + the
     Phase-3 merge-token chip bar are both included here. --}}

<h4 class="text-warning">Rental agreement</h4>
<p class="text-muted small mb-3">
    Whether this property requires the guest to sign a rental agreement,
    when they sign it, and the per-property override of the global template.
    Edit the global default at
    <a href="{{ url('admin/settings/rental-agreement') }}">Settings &rarr; Rental Agreement</a>.
</p>

<div class="card card-info">
    <div class="card-header">
        <h5 class="card-title m-0">Required &amp; signing timing</h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    {!! Form::label("rental_agreement_required", "Rental agreement required?") !!}
                    {!! Form::select("rental_agreement_required", [0 => "No", 1 => "Yes — guest must sign the agreement"], $data->rental_agreement_required ?? 0, ["class" => "form-control"]) !!}
                    <small class="text-muted">When Yes, the agreement template (set globally or overridden below) must be signed. The signing-timing setting next to this controls whether that's before payment or after the booking confirms.</small>
                </div>
            </div>
            {{-- Phase 4 — per-property signing-mode override.
                 'inherit' = use whatever the global setting says
                 'pre_payment' = block checkout until signed
                 'post_confirmation' = magic-link email after payment lands --}}
            <div class="col-md-6">
                <div class="form-group">
                    {!! Form::label("rental_agreement_signing_mode", "Signing timing for this property") !!}
                    {!! Form::select("rental_agreement_signing_mode", [
                        'inherit' => 'Inherit global default',
                        'pre_payment' => 'Before payment — block checkout until signed',
                        'post_confirmation' => 'After booking confirmed — magic-link email after payment',
                    ], $data->rental_agreement_signing_mode ?? 'inherit', ["class" => "form-control"]) !!}
                    <small class="text-muted">Most properties use the global default. Override per-property when one villa needs a different flow than the rest.</small>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card card-info">
    <div class="card-header">
        <h5 class="card-title m-0">Per-property template override</h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-12">
                <div class="form-group">
                    <label class="d-flex align-items-center">
                        <input type="checkbox" id="override_template" name="override_template" value="1" class="mr-2"
                               {{ !empty($data->rental_agreement_template_html) ? 'checked' : '' }}>
                        <span class="ml-2"><strong>Override global rental agreement template for this property</strong></span>
                    </label>

                    {{-- Phase 2 — Word doc upload + auto-convert. Same widget
                         as the global settings page; the JS in the parent layout
                         picks it up via `data-rental-doc-input`. The data-context
                         tells the controller to update properties.* not basic_settings. --}}
                    <div class="rental-doc-upload mb-3 p-3 border rounded bg-light"
                         data-context="property" data-property-id="{{ $data->id ?? '' }}">
                        <div class="d-flex align-items-center" style="gap: 12px; flex-wrap: wrap;">
                            <strong style="white-space:nowrap;">📄 Upload Word document:</strong>
                            <input type="file" data-rental-doc-input accept=".docx,.doc,.rtf,.odt" style="flex:1;min-width:240px;">
                            <button type="button" class="btn btn-secondary btn-sm" data-rental-doc-upload disabled>Convert &amp; load below</button>
                        </div>
                        <small class="form-text text-muted mt-1">.docx, .doc, .rtf, .odt — max 5 MB. Per-property file overrides the global template.</small>
                        @if(!empty($data->rental_agreement_source_doc_path))
                            <div class="mt-2">
                                <span class="badge badge-success">Currently loaded:</span>
                                <a href="{{ asset($data->rental_agreement_source_doc_path) }}" target="_blank" rel="noopener">{{ basename($data->rental_agreement_source_doc_path) }}</a>
                            </div>
                        @endif
                        <div class="rental-doc-status mt-2" style="display:none;"></div>
                    </div>

                    @include('admin.settings._partials.merge-token-bar', ['for' => 'rental_agreement_template_html'])
                    {!! Form::textarea("rental_agreement_template_html", $data->rental_agreement_template_html ?? "",
                        array_merge(
                            ["class" => "form-control ckeditor", "rows" => 14, "id" => "rental_agreement_template_html"],
                            empty($data->rental_agreement_template_html) ? ["disabled" => "disabled"] : []
                        )) !!}
                    <small class="text-muted">Leave checkbox unchecked to inherit the global template (Settings → Rental Agreement).</small>
                </div>
            </div>
            <div class="col-md-12">
                <div class="form-group">
                    <label class="d-flex align-items-center">
                        <input type="checkbox" id="override_rules" name="override_rules" value="1" class="mr-2"
                               {{ !empty($data->rental_agreement_house_rules_html) ? 'checked' : '' }}>
                        <span class="ml-2"><strong>Override global house rules for this property</strong></span>
                    </label>
                    @include('admin.settings._partials.merge-token-bar', ['for' => 'rental_agreement_house_rules_html'])
                    {!! Form::textarea("rental_agreement_house_rules_html", $data->rental_agreement_house_rules_html ?? "",
                        array_merge(
                            ["class" => "form-control ckeditor", "rows" => 10, "id" => "rental_agreement_house_rules_html"],
                            empty($data->rental_agreement_house_rules_html) ? ["disabled" => "disabled"] : []
                        )) !!}
                    <small class="text-muted">Leave checkbox unchecked to inherit the global house rules.</small>
                </div>
            </div>
        </div>
    </div>
</div>
