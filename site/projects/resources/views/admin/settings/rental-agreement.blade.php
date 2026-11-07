@extends('admin.layouts')

@section('title', 'Rental Agreement Settings')

@section('content_header')
    <h1>Rental Agreement — Global Settings</h1>
    <p class="text-muted">Edit the default agreement template and house rules. Each property can override these on its Booking Rules tab.</p>
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

    {{-- Phase 3 — chip bar replaces the static cheat sheet. Click any chip
         to insert the token at the cursor position in the editor below. --}}

    {!! Form::open(['route' => 'admin.rental-agreement.update', 'method' => 'PUT']) !!}

    <div class="card">
        <div class="card-header">
            <h5 class="card-title m-0">When &amp; how the agreement is signed</h5>
        </div>
        <div class="card-body">
            <div class="form-group">
                {!! Form::label('default_enabled', 'When creating a new property, default the "rental agreement required" flag to:') !!}
                {!! Form::select('default_enabled', ['0' => 'Off', '1' => 'On'], $default_enabled, ['class' => 'form-control', 'style' => 'max-width:200px;']) !!}
                <small class="form-text text-muted">Existing properties keep whatever flag they already have. This only affects newly-created ones.</small>
            </div>
            <hr>
            {{-- Phase 4 — global signing-mode default. Per-property override
                 lives on each property's Booking Rules tab and falls back here
                 when set to "Inherit global". --}}
            <div class="form-group">
                <label>Default signing mode <small class="text-muted">(applies to properties set to "Inherit global")</small></label>
                <div class="form-check">
                    {!! Form::radio('signing_mode_global', 'pre_payment', $signing_mode_global === 'pre_payment', ['class' => 'form-check-input', 'id' => 'mode-pre']) !!}
                    <label class="form-check-label" for="mode-pre"><strong>Before payment</strong> — guest must sign the agreement before they can complete checkout.</label>
                </div>
                <div class="form-check">
                    {!! Form::radio('signing_mode_global', 'post_confirmation', $signing_mode_global === 'post_confirmation', ['class' => 'form-check-input', 'id' => 'mode-post']) !!}
                    <label class="form-check-label" for="mode-post"><strong>After booking confirmed</strong> — guest pays first; we email a magic link to sign once payment lands. Check-in info is held until they sign.</label>
                </div>
                <small class="form-text text-muted">
                    Pre-payment is the safest default — you have a signed agreement before money moves. Post-confirmation is friendlier for instant-book traffic where signing on the way in feels heavy.
                </small>
            </div>
        </div>
    </div>

    <div class="card mt-3">
        <div class="card-header">
            <h5 class="card-title m-0">Agreement template (global default)</h5>
        </div>
        <div class="card-body">
            <p class="text-muted small">This is the body of the rental agreement. Use the merge tokens above for per-booking values. Per-property overrides can be set on each property's Booking Rules tab.</p>

            {{-- Phase 2 — Word doc upload + auto-convert. Pre-fills the
                 CKEditor below with the converted HTML; admin then reviews
                 and clicks Save. The original .docx is also persisted so it
                 can be re-downloaded. --}}
            <div class="rental-doc-upload mb-3 p-3 border rounded bg-light" data-context="global">
                <div class="d-flex align-items-center" style="gap: 12px; flex-wrap: wrap;">
                    <strong style="white-space:nowrap;">📄 Upload Word document:</strong>
                    <input type="file" data-rental-doc-input accept=".docx,.doc,.rtf,.odt" style="flex:1;min-width:240px;">
                    <button type="button" class="btn btn-secondary btn-sm" data-rental-doc-upload disabled>Convert &amp; load below</button>
                </div>
                <small class="form-text text-muted mt-1">.docx, .doc, .rtf, .odt — max 5 MB. The conversion replaces the editor content; you can still tweak before saving.</small>
                @if(!empty($source_doc_path))
                    <div class="mt-2">
                        <span class="badge badge-success">Currently loaded:</span>
                        <a href="{{ asset($source_doc_path) }}" target="_blank" rel="noopener">{{ basename($source_doc_path) }}</a>
                    </div>
                @endif
                <div class="rental-doc-status mt-2" style="display:none;"></div>
            </div>

            @include('admin.settings._partials.merge-token-bar', ['for' => 'template_html'])
            {!! Form::textarea('template_html', $template_html, ['class' => 'form-control ckeditor', 'rows' => 20, 'id' => 'template_html']) !!}
        </div>
    </div>

    <div class="card mt-3">
        <div class="card-header">
            <h5 class="card-title m-0">House rules (global default)</h5>
        </div>
        <div class="card-body">
            <p class="text-muted small">These rules are appended to the agreement and signed alongside it.</p>
            @include('admin.settings._partials.merge-token-bar', ['for' => 'house_rules_html'])
            {!! Form::textarea('house_rules_html', $house_rules_html, ['class' => 'form-control ckeditor', 'rows' => 14, 'id' => 'house_rules_html']) !!}
        </div>
    </div>

    {{-- ─── Phase 5 — editable email templates fired by the rental-agreement
         lifecycle. Each section has its own subject + body editor, a merge-token
         chip bar to quick-insert tokens, and the inline preview/attachments
         helpers used by the rest of /admin/settings/stay-communications. --}}
    <div class="card mt-4">
        <div class="card-header bg-light">
            <h5 class="card-title m-0">📨 Rental-agreement emails</h5>
            <small class="text-muted">Subject + body for the three emails that fire around signing. Available merge tokens are listed in the chip bars below each editor.</small>
        </div>
        <div class="card-body">

            {{-- 1. Magic-link "please sign" email — post-confirmation flow --}}
            <h6 class="text-muted text-uppercase small mt-2">📩 Please-sign link <small class="text-muted text-lowercase">(after payment when post-confirmation signing is configured)</small></h6>
            <div class="form-group">
                {!! Form::label('request_subject', 'Subject') !!}
                {!! Form::text('request_subject', $request_subject, ['class' => 'form-control']) !!}
            </div>
            <div class="form-group">
                {!! Form::label('request_body_html', 'Body') !!}
                @include('admin.settings._partials.merge-token-bar', ['for' => 'request_body_html'])
                {!! Form::textarea('request_body_html', $request_body_html, ['class' => 'form-control ckeditor', 'rows' => 8, 'id' => 'request_body_html']) !!}
                <small class="form-text text-muted">Use <code>@{{rental_agreement_link}}</code> for the magic-link button.</small>
            </div>
            @include('admin.email-templates._partials.inline-helpers', ['type' => 'rental-agreement-request'])

            <hr>

            {{-- 2. Signed confirmation to guest --}}
            <h6 class="text-muted text-uppercase small mt-3">✅ Signed confirmation to guest</h6>
            <div class="form-group">
                {!! Form::label('signed_subject', 'Subject') !!}
                {!! Form::text('signed_subject', $signed_subject, ['class' => 'form-control']) !!}
            </div>
            <div class="form-group">
                {!! Form::label('signed_body_html', 'Body') !!}
                @include('admin.settings._partials.merge-token-bar', ['for' => 'signed_body_html'])
                {!! Form::textarea('signed_body_html', $signed_body_html, ['class' => 'form-control ckeditor', 'rows' => 8, 'id' => 'signed_body_html']) !!}
                <small class="form-text text-muted">The signed PDF is attached automatically — guests get the agreement + audit trail for their records.</small>
            </div>
            @include('admin.email-templates._partials.inline-helpers', ['type' => 'rental-agreement-signed'])

            <hr>

            {{-- 3. Owner alert when guest signs --}}
            <h6 class="text-muted text-uppercase small mt-3">📨 Owner alert when guest signs</h6>
            <div class="form-group">
                {!! Form::label('admin_alert_signed_subject', 'Subject') !!}
                {!! Form::text('admin_alert_signed_subject', $admin_alert_signed_subject, ['class' => 'form-control']) !!}
            </div>
            <div class="form-group">
                {!! Form::label('admin_alert_signed_body_html', 'Body') !!}
                @include('admin.settings._partials.merge-token-bar', ['for' => 'admin_alert_signed_body_html'])
                {!! Form::textarea('admin_alert_signed_body_html', $admin_alert_signed_body_html, ['class' => 'form-control ckeditor', 'rows' => 8, 'id' => 'admin_alert_signed_body_html']) !!}
                <small class="form-text text-muted">Sent to the owner inbox at the same moment the guest receives their signed-confirmation email. The same PDF is attached.</small>
            </div>
            @include('admin.email-templates._partials.inline-helpers', ['type' => 'rental-agreement-signed-admin'])

        </div>
    </div>

    <div class="mt-3 mb-5">
        <button type="submit" class="btn btn-primary">Save</button>
    </div>

    {!! Form::close() !!}
@endsection

{{-- AdminLTE layout renders @stack('js') (and @yield('js')) — NOT
     @stack('scripts'). Earlier this page was @push('scripts')-ing the
     CKEditor init; that worked only because CKEditor 4 auto-instantiates
     on .ckeditor class. The upload-handler JS doesn't have a similar
     fallback so it has to land in a stack the layout actually outputs. --}}
@push('js')
<script>
$(function() {
    if (window.CKEDITOR) {
        $('.ckeditor').each(function() {
            CKEDITOR.replace(this);
        });
    }
});
</script>
@include('admin.settings._partials.rental-doc-upload-js')
@include('admin.settings._partials.merge-token-bar-js')
@endpush
