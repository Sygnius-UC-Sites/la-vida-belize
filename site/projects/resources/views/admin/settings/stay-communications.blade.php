@extends('admin.layouts')

@section('title', 'Email Templates')

@section('css')
@parent
<style>
    .stay-comm-header {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: 18px;
        align-items: start;
        padding: 20px 4px 18px;
    }
    .stay-comm-header h1 {
        margin: 0 0 8px;
        color: #0f172a;
        font-size: 28px;
        font-weight: 750;
        letter-spacing: 0;
    }
    .stay-comm-header p {
        margin: 0;
        max-width: 1080px;
        color: #64748b;
        line-height: 1.45;
    }
    .stay-comm-header .btn {
        align-self: start;
        white-space: nowrap;
        border-radius: 8px;
        padding: 9px 14px;
        font-weight: 650;
        text-decoration: none !important;
    }
    .stay-comm-tokens {
        background: #fff !important;
        border: 1px solid #e5e9f0 !important;
        border-left: 4px solid #1e3a5f !important;
        border-radius: 12px !important;
        color: #334155 !important;
        box-shadow: 0 1px 2px rgba(15, 28, 51, .04);
        padding: 16px 18px !important;
    }
    .stay-comm-tokens code {
        display: inline-block;
        margin: 2px 2px;
        border-radius: 5px;
        background: #f1f5f9;
        color: #334155;
        padding: 2px 6px;
        font-size: 12px;
    }
    .stay-comm-tabs.card {
        overflow: hidden !important;
    }
    .stay-comm-tabs .card-header {
        background: #f8fafc !important;
        padding: 12px !important;
    }
    .stay-comm-tabs .nav-tabs {
        display: flex !important;
        flex-wrap: wrap;
        gap: 8px;
        list-style: none !important;
        margin: 0 !important;
        padding: 0 !important;
        border: 0 !important;
    }
    .stay-comm-tabs .nav-item {
        list-style: none !important;
        margin: 0 !important;
    }
    .stay-comm-tabs .nav-link {
        display: inline-flex !important;
        align-items: center;
        min-height: 38px;
        padding: 8px 12px !important;
        border: 1px solid #dbe3ef !important;
        border-radius: 8px !important;
        background: #fff !important;
        color: #334155 !important;
        font-weight: 650;
        text-decoration: none !important;
        line-height: 1.2;
    }
    .stay-comm-tabs .nav-link.active {
        background: #1e293b !important;
        border-color: #1e293b !important;
        color: #fff !important;
        box-shadow: 0 8px 18px rgba(15, 23, 42, .12);
    }
    .stay-comm-tabs .card-body {
        padding: 20px !important;
    }
    .stay-comm-save {
        position: sticky;
        bottom: 16px;
        z-index: 20;
        display: flex;
        justify-content: flex-end;
        padding: 12px;
        margin: 18px 0 40px !important;
        background: rgba(255, 255, 255, .92);
        border: 1px solid #e5e9f0;
        border-radius: 12px;
        box-shadow: 0 12px 30px rgba(15, 28, 51, .10);
        backdrop-filter: blur(10px);
    }
    .stay-comm-save .btn {
        min-width: 132px;
        border-radius: 8px;
        font-weight: 700;
    }
    @media (max-width: 760px) {
        .stay-comm-header {
            grid-template-columns: 1fr;
        }
        .stay-comm-header .btn {
            width: 100%;
            text-align: center;
        }
        .stay-comm-tabs .nav-link {
            width: 100%;
            justify-content: center;
        }
        .stay-comm-tabs .nav-item {
            flex: 1 1 100%;
        }
    }
</style>
@stop

@section('content_header')
    <div class="stay-comm-header">
        <div>
            <h1>Email Templates</h1>
            <p>Subject, body, attachments, and send timing for every email the system sends — both <strong>guest-facing</strong> booking lifecycle messages and <strong>owner-facing</strong> admin alerts. Each template can be edited, previewed, and given attachments without leaving this page.</p>
        </div>
        <a href="{{ url('admin/email-templates') }}" class="btn btn-outline-secondary">
            👁 Preview gallery (all 11 in one scroll)
        </a>
    </div>
@stop

@section('content')
    @if(session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
    @if($errors->any())
        <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    <div class="alert alert-info stay-comm-tokens">
        <strong>Available merge tokens — paste verbatim into any subject or body field; replaced at send time:</strong>
        <div class="mt-2"><strong>Guest:</strong> <code>@{{guest_name}}</code>, <code>@{{guest_email}}</code></div>
        <div class="mt-1"><strong>Booking:</strong> <code>@{{check_in}}</code>, <code>@{{check_out}}</code>, <code>@{{nights}}</code>, <code>@{{booking_id}}</code>, <code>@{{cancel_date}}</code>, <code>@{{total_amount}}</code></div>
        <div class="mt-1"><strong>Property:</strong> <code>@{{property_name}}</code>, <code>@{{property_address}}</code></div>
        <div class="mt-1"><strong>Host:</strong> <code>@{{host_name}}</code>, <code>@{{host_phone}}</code>, <code>@{{emergency_phone}}</code></div>
        <div class="mt-1"><strong>Site / brand:</strong> <code>@{{brand}}</code>, <code>@{{site_email}}</code>, <code>@{{site_phone}}</code></div>
        <div class="mt-1"><strong>Quote:</strong> <code>@{{invoice_table}}</code> (the exact stored line items, total, and payment schedule; confirmation and receipt emails add it automatically when omitted)</div>
        <div class="mt-1"><strong>Payment-only:</strong> <code>@{{magic_link}}</code>, <code>@{{installment_number}}</code>, <code>@{{installment_amount}}</code>, <code>@{{installment_due_date}}</code>, <code>@{{payment_amount}}</code>, <code>@{{payment_date}}</code>, <code>@{{payment_method}}</code>, <code>@{{transaction_id}}</code>, <code>@{{outstanding_balance}}</code></div>
        <div class="mt-1"><strong>Owner alerts only:</strong> <code>@{{admin_url}}</code> (deep link to the booking record in admin)</div>
    </div>

    {!! Form::open(['route' => 'admin.stay-communications.update', 'method' => 'PUT']) !!}

    <div class="card card-primary card-outline card-outline-tabs stay-comm-tabs">
        <div class="card-header p-0 border-bottom-0">
            <ul class="nav nav-tabs" id="ge-tabs" role="tablist">
                <li class="nav-item"><a class="nav-link active" id="ge-timing-tab" data-toggle="pill" href="#ge-timing" role="tab" aria-selected="true">⏱️ Send Timing</a></li>
                <li class="nav-item"><a class="nav-link" id="ge-lifecycle-tab" data-toggle="pill" href="#ge-lifecycle" role="tab" aria-selected="false">📩 Booking lifecycle</a></li>
                <li class="nav-item"><a class="nav-link" id="ge-prestay-tab" data-toggle="pill" href="#ge-prestay" role="tab" aria-selected="false">🌴 Pre-stay</a></li>
                <li class="nav-item"><a class="nav-link" id="ge-installments-tab" data-toggle="pill" href="#ge-installments" role="tab" aria-selected="false">💳 Payment reminders</a></li>
                <li class="nav-item"><a class="nav-link" id="ge-poststay-tab" data-toggle="pill" href="#ge-poststay" role="tab" aria-selected="false">⭐ Post-stay</a></li>
                <li class="nav-item"><a class="nav-link" id="ge-owner-tab" data-toggle="pill" href="#ge-owner" role="tab" aria-selected="false">📨 Owner alerts</a></li>
            </ul>
        </div>
        <div class="card-body">
            <div class="tab-content" id="ge-tabs-content">

                {{-- ═══════════════════════ Tab 1: Send Timing ═══════════════════════ --}}
                <div class="tab-pane fade active show" id="ge-timing" role="tabpanel">
                    <p class="text-muted">For pre-stay emails, positive N = N days before the event (0 = day-of). For the post-stay review request, N = days after check-out.</p>
                    <div class="row">
                        <div class="col-md-3"><div class="form-group">
                            {!! Form::label('welcome_package_send_day', 'Welcome — days before check-in') !!}
                            {!! Form::number('welcome_package_send_day', $welcome_package_send_day !== '' ? $welcome_package_send_day : 0, ['class' => 'form-control', 'min' => 0]) !!}
                            <small class="form-text text-muted">Welcome package + greeting (typical: 3–7 days before).</small>
                        </div></div>
                        <div class="col-md-3"><div class="form-group">
                            {!! Form::label('checkin_email_send_day', 'Check-in — days before check-in') !!}
                            {!! Form::number('checkin_email_send_day', $checkin_email_send_day !== '' ? $checkin_email_send_day : 0, ['class' => 'form-control', 'min' => 0]) !!}
                            <small class="form-text text-muted">Arrival details: door code, wifi, parking. Typical: 1 day before.</small>
                        </div></div>
                        <div class="col-md-3"><div class="form-group">
                            {!! Form::label('checkout_email_send_day', 'Check-out — days before check-out') !!}
                            {!! Form::number('checkout_email_send_day', $checkout_email_send_day !== '' ? $checkout_email_send_day : 0, ['class' => 'form-control', 'min' => 0]) !!}
                            <small class="form-text text-muted">Departure reminder + cleaning checklist. Typical: 1 day before.</small>
                        </div></div>
                        <div class="col-md-3"><div class="form-group">
                            {!! Form::label('review_send_day', 'Review request — days after check-out') !!}
                            {!! Form::number('review_send_day', $review_send_day !== '' ? $review_send_day : 2, ['class' => 'form-control', 'min' => 0]) !!}
                            <small class="form-text text-muted">Post-stay review email. Typical: 1–3 days after check-out.</small>
                        </div></div>
                    </div>
                </div>

                {{-- ═══════════════════════ Tab 2: Booking lifecycle ═══════════════════════ --}}
                <div class="tab-pane fade" id="ge-lifecycle" role="tabpanel">

                    <h5 class="text-muted text-uppercase small mt-2">📨 Enquiry confirmation</h5>
                    <p class="text-muted small">Sent immediately when a visitor submits the public enquiry form.</p>
                    <div class="form-group">
                        {!! Form::label('enquiry_default_subject', 'Subject') !!}
                        {!! Form::text('enquiry_default_subject', $enquiry_default_subject, ['class' => 'form-control']) !!}
                    </div>
                    <div class="form-group">
                        {!! Form::label('enquiry_default_body_html', 'Body') !!}
                        {!! Form::textarea('enquiry_default_body_html', $enquiry_default_body_html, ['class' => 'form-control ckeditor', 'rows' => 8]) !!}
                    </div>
                    @include('admin.email-templates._partials.inline-helpers', ['type' => 'enquiry'])

                    <hr>
                    <h5 class="text-muted text-uppercase small mt-3">💸 Awaiting payment (magic link)</h5>
                    <p class="text-muted small">Sent when an admin approves an enquiry — contains the secure <code>@{{magic_link}}</code> for the guest to pay.</p>
                    <div class="form-group">
                        {!! Form::label('awaiting_payment_default_subject', 'Subject') !!}
                        {!! Form::text('awaiting_payment_default_subject', $awaiting_payment_default_subject, ['class' => 'form-control']) !!}
                    </div>
                    <div class="form-group">
                        {!! Form::label('awaiting_payment_default_body_html', 'Body') !!}
                        {!! Form::textarea('awaiting_payment_default_body_html', $awaiting_payment_default_body_html, ['class' => 'form-control ckeditor', 'rows' => 8]) !!}
                    </div>
                    @include('admin.email-templates._partials.inline-helpers', ['type' => 'awaiting-payment'])

                    <hr>
                    <h5 class="text-muted text-uppercase small mt-3">✅ Booking confirmed</h5>
                    <p class="text-muted small">Sent when payment lands successfully. The "you're booked" email.</p>
                    <div class="form-group">
                        {!! Form::label('confirmed_default_subject', 'Subject') !!}
                        {!! Form::text('confirmed_default_subject', $confirmed_default_subject, ['class' => 'form-control']) !!}
                    </div>
                    <div class="form-group">
                        {!! Form::label('confirmed_default_body_html', 'Body') !!}
                        {!! Form::textarea('confirmed_default_body_html', $confirmed_default_body_html, ['class' => 'form-control ckeditor', 'rows' => 8]) !!}
                    </div>
                    @include('admin.email-templates._partials.inline-helpers', ['type' => 'confirmed'])

                    <hr>
                    <h5 class="text-muted text-uppercase small mt-3">🧾 Payment receipt</h5>
                    <p class="text-muted small">Sent for every successful payment or installment. Includes the amount received, transaction details, outstanding balance, and the accepted quote.</p>
                    <div class="form-group">
                        {!! Form::label('receipt_default_subject', 'Subject') !!}
                        {!! Form::text('receipt_default_subject', $receipt_default_subject, ['class' => 'form-control']) !!}
                    </div>
                    <div class="form-group">
                        {!! Form::label('receipt_default_body_html', 'Body') !!}
                        {!! Form::textarea('receipt_default_body_html', $receipt_default_body_html, ['class' => 'form-control ckeditor', 'rows' => 8]) !!}
                    </div>
                    @include('admin.email-templates._partials.inline-helpers', ['type' => 'receipt'])

                    <hr>
                    <h5 class="text-muted text-uppercase small mt-3">🚫 Cancellation</h5>
                    <p class="text-muted small">Sent when an admin cancels a booking from the booking-enquiries list.</p>
                    <div class="form-group">
                        {!! Form::label('cancellation_default_subject', 'Subject') !!}
                        {!! Form::text('cancellation_default_subject', $cancellation_default_subject, ['class' => 'form-control']) !!}
                    </div>
                    <div class="form-group">
                        {!! Form::label('cancellation_default_body_html', 'Body') !!}
                        {!! Form::textarea('cancellation_default_body_html', $cancellation_default_body_html, ['class' => 'form-control ckeditor', 'rows' => 8]) !!}
                    </div>
                    @include('admin.email-templates._partials.inline-helpers', ['type' => 'cancellation'])
                </div>

                {{-- ═══════════════════════ Tab 3: Pre-stay ═══════════════════════ --}}
                <div class="tab-pane fade" id="ge-prestay" role="tabpanel">

                    <h5 class="text-muted text-uppercase small mt-2">🎁 Welcome package</h5>
                    <p class="text-muted small">Cron-driven — goes out N days before check-in (set in the Send Timing tab). Body is whatever you put in the description below; subject + wrapper come from the editable fields.</p>
                    <div class="form-group">
                        {!! Form::label('welcome_default_subject', 'Subject') !!}
                        {!! Form::text('welcome_default_subject', $welcome_default_subject, ['class' => 'form-control']) !!}
                    </div>
                    <div class="form-group">
                        {!! Form::label('welcome_default_description', 'Body / description (HTML, supports merge tokens)') !!}
                        {!! Form::textarea('welcome_default_description', $welcome_default_description, ['class' => 'form-control ckeditor', 'rows' => 10]) !!}
                    </div>
                    <div class="form-group">
                        {!! Form::label('welcome_default_attachment_path', 'Attachment path (optional, relative to public/)') !!}
                        {!! Form::text('welcome_default_attachment_path', $welcome_default_attachment_path, ['class' => 'form-control', 'placeholder' => 'uploads/welcome-packet.pdf']) !!}
                        <small class="form-text text-muted">Per-property attachments (set on each property's Stay Info tab) override this when present.</small>
                    </div>
                    @include('admin.email-templates._partials.inline-helpers', ['type' => 'welcome'])

                    <hr>
                    <h5 class="text-muted text-uppercase small mt-3">🔑 Check-in info</h5>
                    <p class="text-muted small">Cron-driven — goes out N days before check-in. Subject is editable; body is composed from the structured fields below (door code, wifi, parking, host info, free-form notes).</p>
                    <div class="form-group">
                        {!! Form::label('checkin_default_subject', 'Subject') !!}
                        {!! Form::text('checkin_default_subject', $checkin_default_subject, ['class' => 'form-control']) !!}
                    </div>
                    <div class="row">
                        <div class="col-md-6"><div class="form-group">
                            {!! Form::label('checkin_default_arrival_window', 'Arrival window') !!}
                            {!! Form::text('checkin_default_arrival_window', $checkin_default_arrival_window, ['class' => 'form-control']) !!}
                        </div></div>
                        <div class="col-md-6"><div class="form-group">
                            {!! Form::label('checkin_default_door_code', 'Door code (typically per-property)') !!}
                            {!! Form::text('checkin_default_door_code', $checkin_default_door_code, ['class' => 'form-control']) !!}
                        </div></div>
                        <div class="col-md-6"><div class="form-group">
                            {!! Form::label('checkin_default_wifi_name', 'Wi-Fi name (typically per-property)') !!}
                            {!! Form::text('checkin_default_wifi_name', $checkin_default_wifi_name, ['class' => 'form-control']) !!}
                        </div></div>
                        <div class="col-md-6"><div class="form-group">
                            {!! Form::label('checkin_default_wifi_password', 'Wi-Fi password (typically per-property)') !!}
                            {!! Form::text('checkin_default_wifi_password', $checkin_default_wifi_password, ['class' => 'form-control']) !!}
                        </div></div>
                        <div class="col-md-12"><div class="form-group">
                            {!! Form::label('checkin_default_parking_instructions', 'Parking instructions (HTML)') !!}
                            {!! Form::textarea('checkin_default_parking_instructions', $checkin_default_parking_instructions, ['class' => 'form-control ckeditor', 'rows' => 4]) !!}
                        </div></div>
                        <div class="col-md-4"><div class="form-group">
                            {!! Form::label('checkin_default_host_name', 'Host name') !!}
                            {!! Form::text('checkin_default_host_name', $checkin_default_host_name, ['class' => 'form-control']) !!}
                        </div></div>
                        <div class="col-md-4"><div class="form-group">
                            {!! Form::label('checkin_default_host_phone', 'Host phone') !!}
                            {!! Form::text('checkin_default_host_phone', $checkin_default_host_phone, ['class' => 'form-control']) !!}
                        </div></div>
                        <div class="col-md-4"><div class="form-group">
                            {!! Form::label('checkin_default_emergency_phone', 'Emergency phone') !!}
                            {!! Form::text('checkin_default_emergency_phone', $checkin_default_emergency_phone, ['class' => 'form-control']) !!}
                        </div></div>
                        <div class="col-md-12"><div class="form-group">
                            {!! Form::label('checkin_default_notes_html', 'Free-form notes (HTML)') !!}
                            {!! Form::textarea('checkin_default_notes_html', $checkin_default_notes_html, ['class' => 'form-control ckeditor', 'rows' => 4]) !!}
                        </div></div>
                    </div>
                    @include('admin.email-templates._partials.inline-helpers', ['type' => 'checkin'])

                    <hr>
                    <h5 class="text-muted text-uppercase small mt-3">🚪 Check-out info</h5>
                    <p class="text-muted small">Cron-driven — goes out N days before check-out. Subject is editable; body is composed from the structured fields below.</p>
                    <div class="form-group">
                        {!! Form::label('checkout_default_subject', 'Subject') !!}
                        {!! Form::text('checkout_default_subject', $checkout_default_subject, ['class' => 'form-control']) !!}
                    </div>
                    <div class="form-group">
                        {!! Form::label('checkout_default_departure_time', 'Departure time') !!}
                        {!! Form::text('checkout_default_departure_time', $checkout_default_departure_time, ['class' => 'form-control']) !!}
                    </div>
                    <div class="form-group">
                        {!! Form::label('checkout_default_key_return', 'Key return (HTML)') !!}
                        {!! Form::textarea('checkout_default_key_return', $checkout_default_key_return, ['class' => 'form-control ckeditor', 'rows' => 4]) !!}
                    </div>
                    <div class="form-group">
                        {!! Form::label('checkout_default_cleaning_checklist', 'Cleaning checklist (HTML)') !!}
                        {!! Form::textarea('checkout_default_cleaning_checklist', $checkout_default_cleaning_checklist, ['class' => 'form-control ckeditor', 'rows' => 6]) !!}
                    </div>
                    <div class="form-group">
                        {!! Form::label('checkout_default_notes_html', 'Notes (HTML)') !!}
                        {!! Form::textarea('checkout_default_notes_html', $checkout_default_notes_html, ['class' => 'form-control ckeditor', 'rows' => 4]) !!}
                    </div>
                    @include('admin.email-templates._partials.inline-helpers', ['type' => 'checkout'])
                </div>

                {{-- ═══════════════════════ Tab 4: Payment reminders ═══════════════════════ --}}
                <div class="tab-pane fade" id="ge-installments" role="tabpanel">

                    <h5 class="text-muted text-uppercase small mt-2">2️⃣ 2nd installment reminder</h5>
                    <p class="text-muted small">Cron-driven — goes out when the 2nd installment is due. Use the payment-only tokens to surface the amount and due date.</p>
                    <div class="form-group">
                        {!! Form::label('installment2_default_subject', 'Subject') !!}
                        {!! Form::text('installment2_default_subject', $installment2_default_subject, ['class' => 'form-control']) !!}
                    </div>
                    <div class="form-group">
                        {!! Form::label('installment2_default_body_html', 'Body') !!}
                        {!! Form::textarea('installment2_default_body_html', $installment2_default_body_html, ['class' => 'form-control ckeditor', 'rows' => 8]) !!}
                    </div>
                    @include('admin.email-templates._partials.inline-helpers', ['type' => 'installment2'])

                    <hr>
                    <h5 class="text-muted text-uppercase small mt-3">3️⃣ Final (3rd) installment reminder</h5>
                    <p class="text-muted small">Last payment before check-in. Tone tends to be a bit more urgent — adjust copy to taste.</p>
                    <div class="form-group">
                        {!! Form::label('installment3_default_subject', 'Subject') !!}
                        {!! Form::text('installment3_default_subject', $installment3_default_subject, ['class' => 'form-control']) !!}
                    </div>
                    <div class="form-group">
                        {!! Form::label('installment3_default_body_html', 'Body') !!}
                        {!! Form::textarea('installment3_default_body_html', $installment3_default_body_html, ['class' => 'form-control ckeditor', 'rows' => 8]) !!}
                    </div>
                    @include('admin.email-templates._partials.inline-helpers', ['type' => 'installment3'])
                </div>

                {{-- ═══════════════════════ Tab 5: Post-stay ═══════════════════════ --}}
                <div class="tab-pane fade" id="ge-poststay" role="tabpanel">

                    <h5 class="text-muted text-uppercase small mt-2">⭐ Review request</h5>
                    <p class="text-muted small">Cron-driven — goes out N days after check-out (set in the Send Timing tab).</p>
                    <div class="form-group">
                        {!! Form::label('review_default_subject', 'Subject') !!}
                        {!! Form::text('review_default_subject', $review_default_subject, ['class' => 'form-control']) !!}
                    </div>
                    <div class="form-group">
                        {!! Form::label('review_default_body_html', 'Body') !!}
                        {!! Form::textarea('review_default_body_html', $review_default_body_html, ['class' => 'form-control ckeditor', 'rows' => 8]) !!}
                    </div>
                    @include('admin.email-templates._partials.inline-helpers', ['type' => 'review'])
                </div>

                {{-- ═══════════════════════ Tab 6: Owner alerts ═══════════════════════ --}}
                <div class="tab-pane fade" id="ge-owner" role="tabpanel">
                    <p class="text-muted">Internal notifications you (the property owner / admin) get when guest-facing events happen. Each routes to the inbox configured under <strong>Settings → Mail</strong> (with the primary admin email as fallback).</p>

                    @php
                        $alerts = [
                            ['enquiry',          '📨 New booking enquiry',                'admin_alert_enquiry_subject',           'admin_alert_enquiry_body_html',           null,  'Sent when a visitor submits the public enquiry form.'],
                            ['awaiting_payment', '💸 Awaiting-payment link sent',         'admin_alert_awaiting_payment_subject',  'admin_alert_awaiting_payment_body_html',  null,  'Sent when you click "Confirm" on an enquiry — the magic-link payment email goes to the guest, this alert goes to you.'],
                            ['confirmed',        '✅ Booking confirmed (payment received)', 'admin_alert_confirmed_subject',         'admin_alert_confirmed_body_html',         null,  'Sent when a payment lands successfully and the booking flips to confirmed.'],
                            ['receipt',          '🧾 Payment receipt',                    'admin_alert_receipt_subject',           'admin_alert_receipt_body_html',           null,  'Sent for every successful payment or installment with transaction and balance details.'],
                            ['cancelled',        '🚫 Booking cancelled',                  'admin_alert_cancelled_subject',         'admin_alert_cancelled_body_html',         null,  'Sent when an admin cancels a booking from the booking-enquiries list.'],
                            ['installment2',     '2️⃣ Installment 2 reminder sent',       'admin_alert_installment2_subject',      'admin_alert_installment2_body_html',      null,  'Sent (cron) after the 2nd-installment reminder email goes out to the guest.'],
                            ['installment3',     '3️⃣ Final installment reminder sent',   'admin_alert_installment3_subject',      'admin_alert_installment3_body_html',      null,  'Sent (cron) after the final-installment reminder email goes out to the guest.'],
                            ['rental_agreement', '📜 Rental agreement signed',            'admin_alert_rental_agreement_subject',  'admin_alert_rental_agreement_body_html',  null,  'Sent when the guest signs the rental agreement — the signed PDF is attached.'],
                            ['welcome',          '🎁 Welcome email sent (BCC)',           'admin_alert_welcome_subject',           'admin_alert_welcome_body_html',           'admin_bcc_welcome_enabled',  'Optional. Toggle on to BCC yourself when the welcome package goes out.'],
                            ['checkin',          '🔑 Check-in info sent (BCC)',           'admin_alert_checkin_subject',           'admin_alert_checkin_body_html',           'admin_bcc_checkin_enabled',  'Optional. Toggle on to BCC yourself when check-in details go out.'],
                            ['checkout',         '🚪 Check-out info sent (BCC)',          'admin_alert_checkout_subject',          'admin_alert_checkout_body_html',          'admin_bcc_checkout_enabled', 'Optional. Toggle on to BCC yourself when check-out details go out.'],
                        ];
                    @endphp

                    @foreach($alerts as $i => $alert)
                        @php [$slug, $label, $subjectKey, $bodyKey, $toggleKey, $hint] = $alert; @endphp
                        @if(!$loop->first)<hr>@endif
                        <h5 class="text-muted text-uppercase small mt-{{ $loop->first ? '2' : '3' }}">{{ $label }}</h5>
                        <p class="text-muted small">{{ $hint }}</p>
                        @if($toggleKey)
                            <div class="form-group">
                                <div class="custom-control custom-switch">
                                    <input type="hidden" name="{{ $toggleKey }}" value="0">
                                    <input type="checkbox" class="custom-control-input" id="{{ $toggleKey }}" name="{{ $toggleKey }}" value="1" {{ ($$toggleKey ?? '') === '1' ? 'checked' : '' }}>
                                    <label class="custom-control-label" for="{{ $toggleKey }}">Send this alert when the corresponding guest email goes out</label>
                                </div>
                            </div>
                        @endif
                        <div class="form-group">
                            {!! Form::label($subjectKey, 'Subject') !!}
                            {!! Form::text($subjectKey, $$subjectKey, ['class' => 'form-control']) !!}
                        </div>
                        <div class="form-group">
                            {!! Form::label($bodyKey, 'Body') !!}
                            {!! Form::textarea($bodyKey, $$bodyKey, ['class' => 'form-control ckeditor', 'rows' => 6]) !!}
                        </div>
                    @endforeach
                </div>

            </div>
        </div>
    </div>

    <div class="stay-comm-save"><button type="submit" class="btn btn-primary">Save</button></div>

    {!! Form::close() !!}

    {{-- Inline preview + attachment manager styles & wiring (used by every
         template's @include of admin.email-templates._partials.inline-helpers).
         Kept here so the partial stays markup-only. --}}
    <style>
        .tpl-inline-helpers {
            margin: -8px 0 18px;
            padding: 10px 12px;
            background: #fafafb;
            border: 1px dashed #d6d6dc;
            border-radius: 8px;
        }
        .tpl-inline-helpers .tpl-toolbar {
            display: flex; gap: 8px; align-items: center; flex-wrap: wrap;
        }
        .tpl-inline-helpers .tpl-toolbar .badge { margin-left: 4px; }
        .tpl-inline-helpers .tpl-toolbar .btn-link { color: #6b7280; }
        .tpl-inline-helpers .tpl-pane {
            margin-top: 12px;
            padding: 12px;
            background: #fff;
            border: 1px solid #e6e8ee;
            border-radius: 8px;
        }
        .tpl-preview-iframe {
            width: 100%;
            height: 620px;
            border: 0;
            background: #fafafb;
            border-radius: 6px;
        }
        .tpl-attach__title {
            font-size: 11.5px; font-weight: 700; text-transform: uppercase;
            letter-spacing: .05em; color: #6b7280; margin: 0 0 8px;
        }
        .tpl-attach__title code {
            background: #faf3e6; color: #7a5818; padding: 0 6px; border-radius: 4px;
            font-size: 11px;
        }
        .tpl-attach__list { list-style: none; margin: 0 0 8px; padding: 0; }
        .tpl-attach__row {
            display: flex; align-items: center; gap: 10px;
            padding: 6px 8px;
            background: #fafafb; border: 1px solid #ececef;
            border-radius: 8px; margin-bottom: 6px; font-size: 13px;
        }
        .tpl-attach__row:last-child { margin-bottom: 0; }
        .tpl-attach__row .name {
            flex: 1; min-width: 0;
            overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
            color: #0f172a; font-weight: 600;
        }
        .tpl-attach__row .size { color: #6b7280; font-size: 12px; font-variant-numeric: tabular-nums; }
        .tpl-attach__row .del {
            background: none; border: 0; color: #b42318; cursor: pointer;
            font-size: 14px; padding: 4px 6px; border-radius: 6px;
        }
        .tpl-attach__row .del:hover { background: #fee2e2; }
        .tpl-attach__upload {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 5px 12px; background: #d9a24a; color: #fff;
            border-radius: 8px; font-size: 13px; font-weight: 600;
            cursor: pointer; border: 0; transition: background .15s;
            margin: 0;
        }
        .tpl-attach__upload:hover { background: #c0883a; }
        .tpl-attach__upload[disabled] { opacity: .6; cursor: wait; }
        .tpl-attach__hint { display: block; margin-top: 6px; font-size: 11.5px; color: #9ca3af; }
        .tpl-attach__error { color: #b42318; font-size: 12px; margin-top: 6px; display: none; }
        .tpl-attach__error.show { display: block; }
        .tpl-attach__empty { color: #9ca3af; font-style: italic; font-size: 12.5px; margin: 0 0 8px; }
    </style>

    <script>
    /* Toggle handlers + attachment-manager AJAX. Event-delegated so it works
       for every per-template helper block on this page (and any added later
       via JS injection). The preview iframe is lazy-mounted on first show
       so 11 iframes don't all hit the server on page load. */
    (function () {
        var tokenEl = document.querySelector('meta[name="csrf-token"]');
        var token = tokenEl ? tokenEl.content : '';

        function ensureIframe(pane) {
            var iframe = pane.querySelector('iframe.tpl-preview-iframe');
            if (iframe && !iframe.src && iframe.dataset.src) {
                iframe.src = iframe.dataset.src;
            }
        }
        document.addEventListener('click', function (e) {
            var t = e.target.closest && e.target.closest('.tpl-toggle');
            if (!t) return;
            e.preventDefault();
            var sel = t.getAttribute('data-target');
            if (!sel) return;
            var pane = document.querySelector(sel);
            if (!pane) return;
            if (pane.hasAttribute('hidden')) {
                pane.removeAttribute('hidden');
                ensureIframe(pane);
            } else {
                pane.setAttribute('hidden', 'hidden');
            }
        });

        // Attachment manager — same vocabulary as the standalone /admin/email-templates page.
        function showError(scope, msg) {
            var box = scope.querySelector('[data-attach-error]');
            if (!box) return;
            box.textContent = msg;
            box.classList.add('show');
        }
        function clearError(scope) {
            var box = scope.querySelector('[data-attach-error]');
            if (!box) return;
            box.textContent = '';
            box.classList.remove('show');
        }
        function renderRow(att) {
            var li = document.createElement('li');
            li.className = 'tpl-attach__row';
            li.dataset.attachId = att.id;
            li.innerHTML =
                '<span class="name" title="' + att.original_name + '">' + att.original_name + '</span>' +
                '<span class="size">' + att.human_size + '</span>' +
                '<button type="button" class="del" data-attach-del="' + att.id + '" title="Remove">&times;</button>';
            return li;
        }
        function bumpCountBadge(scope, delta) {
            // .tpl-inline-helpers wraps each block; its toolbar has a count badge
            // with the same tpl-attach-count class on the Attachments toggle.
            var wrap = scope.closest('.tpl-inline-helpers');
            if (!wrap) return;
            var badge = wrap.querySelector('.tpl-attach-count');
            if (!badge) return;
            badge.textContent = Math.max(0, parseInt(badge.textContent || '0', 10) + delta);
        }

        document.addEventListener('change', function (e) {
            var input = e.target;
            if (!input.matches || !input.matches('[data-attach-upload]')) return;
            var scope = input.closest('.tpl-attach');
            if (!scope) return;
            var type = scope.dataset.type;
            var file = input.files && input.files[0];
            if (!file) return;
            clearError(scope);
            var btn = scope.querySelector('.tpl-attach__upload');
            if (btn) btn.setAttribute('disabled', 'disabled');

            var fd = new FormData();
            fd.append('file', file);
            fd.append('_token', token);
            fetch(@json(url('admin/email-templates')) + '/' + type + '/attachments', {
                method: 'POST', body: fd,
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json', 'X-CSRF-TOKEN': token },
                credentials: 'same-origin',
            })
            .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
            .then(function (res) {
                if (res.j && res.j.ok) {
                    var list = scope.querySelector('[data-attach-list]');
                    var empty = list && list.querySelector('[data-attach-empty]');
                    if (empty) empty.remove();
                    if (list) list.appendChild(renderRow(res.j.attachment));
                    bumpCountBadge(scope, 1);
                    input.value = '';
                } else {
                    showError(scope, (res.j && res.j.error) || 'Upload failed.');
                }
            })
            .catch(function () { showError(scope, 'Network error during upload.'); })
            .finally(function () { if (btn) btn.removeAttribute('disabled'); });
        }, true);

        document.addEventListener('click', function (e) {
            var btn = e.target.closest && e.target.closest('[data-attach-del]');
            if (!btn) return;
            if (!confirm('Remove this attachment? It will be deleted from disk.')) return;
            var id = btn.getAttribute('data-attach-del');
            var scope = btn.closest('.tpl-attach');
            clearError(scope);
            fetch(@json(url('admin/email-templates/attachments')) + '/' + id, {
                method: 'DELETE',
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json', 'X-CSRF-TOKEN': token },
                credentials: 'same-origin',
            })
            .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
            .then(function (res) {
                if (res.j && res.j.ok) {
                    var row = scope.querySelector('[data-attach-id="' + id + '"]');
                    if (row) row.remove();
                    var list = scope.querySelector('[data-attach-list]');
                    if (list && !list.querySelector('.tpl-attach__row')) {
                        var li = document.createElement('li');
                        li.className = 'tpl-attach__empty';
                        li.dataset.attachEmpty = '1';
                        li.textContent = 'No attachments yet.';
                        list.appendChild(li);
                    }
                    bumpCountBadge(scope, -1);
                } else {
                    showError(scope, (res.j && res.j.error) || 'Delete failed.');
                }
            })
            .catch(function () { showError(scope, 'Network error during delete.'); });
        });
    })();
    </script>
@endsection

@push('scripts')
<script>
$(function() {
    if (window.CKEDITOR) {
        $('.ckeditor').each(function() { CKEDITOR.replace(this); });
    }
    // Remember the last-active tab across saves so the admin returns to where they were.
    var GE_TAB_KEY = 'ge_active_tab';
    var saved = localStorage.getItem(GE_TAB_KEY);
    if (saved) { $('#ge-tabs a[href="' + saved + '"]').tab('show'); }
    $('#ge-tabs a').on('shown.bs.tab', function (e) {
        try { localStorage.setItem(GE_TAB_KEY, $(e.target).attr('href')); } catch (_) {}
    });
});
</script>
@endpush
