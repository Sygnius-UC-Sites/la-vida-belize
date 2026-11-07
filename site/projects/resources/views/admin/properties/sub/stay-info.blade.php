<div class="row">
    <div class="col-md-12">
        <div class="form-group">
            {!! Form::label("welcome_package_attachment", "Welcome package PDF") !!}
            {!! Form::file("welcome_package_attachment", ["class" => "form-control"]) !!}
            <small class="text-muted">Optional. Attached to the welcome email sent to guests before check-in.</small>
            @isset($data)
                @if (!empty($data->welcome_package_attachment))
                    <div class="mt-1"><a href="{{ asset($data->welcome_package_attachment) }}" target="_blank">View current attachment</a></div>
                @endif
            @endisset
        </div>
    </div>

    {{-- Merge token reference card (collapsed by default) --}}
    <div class="col-md-12">
        <div class="card card-secondary collapsed-card">
            <div class="card-header">
                <h5 class="card-title m-0">Merge tokens you can use in welcome / check-in / check-out content</h5>
                <div class="card-tools">
                    <button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fas fa-plus"></i></button>
                </div>
            </div>
            <div class="card-body" style="display: none;">
                <p class="text-muted">Type these literally in any of the rich-text fields below. They get replaced with real values when the email is sent to the guest.</p>
                <table class="table table-sm">
                    <thead><tr><th>Token</th><th>Replaced with</th></tr></thead>
                    <tbody>
                        <tr><td><code>@{{guest_name}}</code></td><td>The guest's name from the booking</td></tr>
                        <tr><td><code>@{{guest_email}}</code></td><td>The guest's email</td></tr>
                        <tr><td><code>@{{property_name}}</code></td><td>This property's name (Main tab → Identification)</td></tr>
                        <tr><td><code>@{{property_address}}</code></td><td>This property's address (Main tab → Contact)</td></tr>
                        <tr><td><code>@{{check_in}}</code></td><td>The booking's check-in date</td></tr>
                        <tr><td><code>@{{check_out}}</code></td><td>The booking's check-out date</td></tr>
                        <tr><td><code>@{{nights}}</code></td><td>Number of nights in the booking</td></tr>
                        <tr><td><code>@{{host_name}}</code></td><td>"Host name" below (or global default)</td></tr>
                        <tr><td><code>@{{host_phone}}</code></td><td>"Host phone (non-emergency)" below (or global default)</td></tr>
                        <tr><td><code>@{{emergency_phone}}</code></td><td>"24/7 emergency phone" below (or global default)</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="alert alert-info small">
            Leave any field blank to inherit the global default
            (<a href="{{ route('admin.stay-communications.edit') }}" target="_blank">Settings → Stay Communications</a>).
        </div>
    </div>

    {{-- Per-property timing overrides --}}
    <div class="col-md-4"><div class="form-group">
        {!! Form::label('welcome_email_send_day', 'Send welcome email this many days before check-in') !!}
        {!! Form::number('welcome_email_send_day', $data->welcome_email_send_day, ['class' => 'form-control', 'min' => 0, 'placeholder' => 'inherit']) !!}
        <small class="text-muted">Leave blank to inherit the global default. Set 0 to send the day of check-in.</small>
    </div></div>
    <div class="col-md-4"><div class="form-group">
        {!! Form::label('checkin_email_send_day', 'Send check-in info email this many days before check-in') !!}
        {!! Form::number('checkin_email_send_day', $data->checkin_email_send_day, ['class' => 'form-control', 'min' => 0, 'placeholder' => 'inherit']) !!}
        <small class="text-muted">Leave blank to inherit the global default. Set 0 to send the day of check-in.</small>
    </div></div>
    <div class="col-md-4"><div class="form-group">
        {!! Form::label('checkout_email_send_day', 'Send check-out reminder this many days before check-out') !!}
        {!! Form::number('checkout_email_send_day', $data->checkout_email_send_day, ['class' => 'form-control', 'min' => 0, 'placeholder' => 'inherit']) !!}
        <small class="text-muted">Leave blank to inherit the global default. Set 0 to send the day of check-out.</small>
    </div></div>

    {{-- Welcome content --}}
    <div class="col-12"><h4 class="mt-3">Welcome content (overrides global)</h4></div>
    <div class="col-md-12"><div class="form-group">
        {!! Form::label('welcome_package_description', 'Welcome email message body') !!}
        {!! Form::textarea('welcome_package_description', $data->welcome_package_description, ['class' => 'form-control ckeditor', 'rows' => 8]) !!}
        <small class="text-muted">Shown to the guest in the welcome email. Supports merge tokens (see panel above).</small>
    </div></div>

    {{-- Check-in --}}
    <div class="col-12"><h4 class="mt-3">Check-in info (overrides global)</h4></div>
    <div class="col-md-6"><div class="form-group">
        {!! Form::label('checkin_arrival_window', 'Check-in window') !!}
        {!! Form::text('checkin_arrival_window', $data->checkin_arrival_window, ['class' => 'form-control']) !!}
        <small class="text-muted">When the guest can arrive (e.g. "3pm – 6pm").</small>
    </div></div>
    <div class="col-md-6"><div class="form-group">
        {!! Form::label('checkin_door_code', 'Door / lockbox code') !!}
        {!! Form::text('checkin_door_code', $data->checkin_door_code, ['class' => 'form-control']) !!}
        <small class="text-muted">Code the guest enters to access the property.</small>
    </div></div>
    <div class="col-md-6"><div class="form-group">
        {!! Form::label('checkin_wifi_name', 'Wi-Fi network name') !!}
        {!! Form::text('checkin_wifi_name', $data->checkin_wifi_name, ['class' => 'form-control']) !!}
    </div></div>
    <div class="col-md-6"><div class="form-group">
        {!! Form::label('checkin_wifi_password', 'Wi-Fi password') !!}
        {!! Form::text('checkin_wifi_password', $data->checkin_wifi_password, ['class' => 'form-control']) !!}
    </div></div>
    <div class="col-md-12"><div class="form-group">
        {!! Form::label('checkin_parking_instructions', 'Parking instructions') !!}
        {!! Form::textarea('checkin_parking_instructions', $data->checkin_parking_instructions, ['class' => 'form-control', 'rows' => 3]) !!}
        <small class="text-muted">Where the guest should park.</small>
    </div></div>
    <div class="col-md-4"><div class="form-group">
        {!! Form::label('checkin_host_name', 'Host name') !!}
        {!! Form::text('checkin_host_name', $data->checkin_host_name, ['class' => 'form-control']) !!}
        <small class="text-muted">Shown in check-in email signature. Also used in the <code>@{{host_name}}</code> merge token.</small>
    </div></div>
    <div class="col-md-4"><div class="form-group">
        {!! Form::label('checkin_host_phone', 'Host phone (non-emergency)') !!}
        {!! Form::text('checkin_host_phone', $data->checkin_host_phone, ['class' => 'form-control']) !!}
        <small class="text-muted">For everyday questions during the stay. Used in <code>@{{host_phone}}</code>.</small>
    </div></div>
    <div class="col-md-4"><div class="form-group">
        {!! Form::label('checkin_emergency_phone', '24/7 emergency phone') !!}
        {!! Form::text('checkin_emergency_phone', $data->checkin_emergency_phone, ['class' => 'form-control']) !!}
        <small class="text-muted">For urgent issues — escalates faster than the host phone. Used in <code>@{{emergency_phone}}</code>.</small>
    </div></div>
    <div class="col-md-12"><div class="form-group">
        {!! Form::label('detail_check_in_instructions', 'Additional check-in notes') !!}
        {!! Form::textarea('detail_check_in_instructions', $data->detail_check_in_instructions, ['class' => 'form-control ckeditor', 'rows' => 4]) !!}
        <small class="text-muted">Anything else the guest should know on arrival. Supports merge tokens (see panel above).</small>
    </div></div>

    {{-- Check-out --}}
    <div class="col-12"><h4 class="mt-3">Check-out info (overrides global)</h4></div>
    <div class="col-md-6"><div class="form-group">
        {!! Form::label('checkout_departure_time', 'Check-out time') !!}
        {!! Form::text('checkout_departure_time', $data->checkout_departure_time, ['class' => 'form-control']) !!}
        <small class="text-muted">When the guest must be out by (e.g. "11am").</small>
    </div></div>
    <div class="col-md-12"><div class="form-group">
        {!! Form::label('checkout_key_return', 'Key return instructions') !!}
        {!! Form::textarea('checkout_key_return', $data->checkout_key_return, ['class' => 'form-control', 'rows' => 3]) !!}
        <small class="text-muted">How and where the guest should return the keys when leaving.</small>
    </div></div>
    <div class="col-md-12"><div class="form-group">
        {!! Form::label('checkout_cleaning_checklist', 'Cleanup checklist') !!}
        {!! Form::textarea('checkout_cleaning_checklist', $data->checkout_cleaning_checklist, ['class' => 'form-control ckeditor', 'rows' => 6]) !!}
        <small class="text-muted">Tasks the guest should do before leaving (e.g. dishes washed, trash out). Supports merge tokens.</small>
    </div></div>
    <div class="col-md-12"><div class="form-group">
        {!! Form::label('checkout_notes', 'Additional check-out notes') !!}
        {!! Form::textarea('checkout_notes', $data->checkout_notes, ['class' => 'form-control ckeditor', 'rows' => 4]) !!}
        <small class="text-muted">Anything else to tell the guest about checking out. Supports merge tokens.</small>
    </div></div>
</div>
