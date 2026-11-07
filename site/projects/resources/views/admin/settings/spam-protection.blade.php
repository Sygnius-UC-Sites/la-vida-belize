@extends('admin.layouts')

@section('title', 'Spam Protection')

@section('content_header')
    <h1>Spam Protection</h1>
    <p class="text-muted">
        Layered checks applied to the public Contact, Booking enquiry, and Review forms.
        Each layer can be turned off independently &mdash; honeypot and time-trap are zero-cost
        and recommended on; the bot-challenge widget needs site/secret keys before it can be enabled.
        Settings on this page take precedence over <code>.env</code> and <code>config/spam.php</code>.
    </p>
@stop

@section('content')
    @if(session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
    @if($errors->any())
        <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    <form method="POST" action="{{ route('admin.spam-protection.update') }}">
        @csrf
        @method('PUT')

        {{-- Bot challenge widget --}}
        <div class="card mb-3">
            <div class="card-header">
                <h5 class="card-title m-0">
                    <input type="hidden" name="spam_botguard_enabled" value="0">
                    <input type="checkbox" name="spam_botguard_enabled" value="1" {{ $values['spam_botguard_enabled'] ? 'checked' : '' }}>
                    Bot challenge <small class="text-muted">(invisible CAPTCHA)</small>
                </h5>
            </div>
            <div class="card-body">
                <div class="form-group">
                    <label>Site key</label>
                    <input type="text" name="spam_botguard_site_key" class="form-control" value="{{ $values['spam_botguard_site_key'] }}" placeholder="0x4AAA...">
                    <small class="text-muted">Public key &mdash; rendered into form HTML.</small>
                </div>
                <div class="form-group">
                    <label>Secret key</label>
                    <input type="password" name="spam_botguard_secret_key" class="form-control" value="{{ $values['spam_botguard_secret_key'] }}" placeholder="0x4AAA...secret" autocomplete="off">
                    <small class="text-muted">Server-side &mdash; never exposed to the client.</small>
                </div>
                <div class="alert alert-info mb-0" style="font-size:.9em;">
                    The widget fails <strong>closed</strong>: if the provider is unreachable, submissions are rejected rather than silently allowed.
                    Leave this disabled until both keys are filled in &mdash; otherwise every submission gets a "Verification failed" error.
                </div>
            </div>
        </div>

        {{-- Honeypot --}}
        <div class="card mb-3">
            <div class="card-header">
                <h5 class="card-title m-0">
                    <input type="hidden" name="spam_honeypot_enabled" value="0">
                    <input type="checkbox" name="spam_honeypot_enabled" value="1" {{ $values['spam_honeypot_enabled'] ? 'checked' : '' }}>
                    Honeypot field
                </h5>
            </div>
            <div class="card-body">
                <p class="m-0 text-muted">
                    Hidden <code>website</code> field; if a bot fills it the submission is silently dropped (the user-visible flow looks like success, so the bot doesn't learn the trap exists). Zero impact on legitimate users.
                </p>
            </div>
        </div>

        {{-- Time-trap --}}
        <div class="card mb-3">
            <div class="card-header">
                <h5 class="card-title m-0">
                    <input type="hidden" name="spam_time_trap_enabled" value="0">
                    <input type="checkbox" name="spam_time_trap_enabled" value="1" {{ $values['spam_time_trap_enabled'] ? 'checked' : '' }}>
                    Time-trap (reject submissions submitted faster than humans can type)
                </h5>
            </div>
            <div class="card-body">
                <div class="form-group">
                    <label>Minimum seconds between page load and submit</label>
                    <input type="number" name="spam_time_trap_min_seconds" class="form-control" value="{{ $values['spam_time_trap_min_seconds'] }}" min="0" max="60">
                    <small class="text-muted">2&ndash;3 seconds catches simple form-fillers without inconveniencing real users. Tokens are HMAC-signed against <code>APP_KEY</code> so they can't be forged or replayed.</small>
                </div>
            </div>
        </div>

        {{-- Rate limiting --}}
        <div class="card mb-3">
            <div class="card-header">
                <h5 class="card-title m-0">
                    <input type="hidden" name="spam_rate_limit_enabled" value="0">
                    <input type="checkbox" name="spam_rate_limit_enabled" value="1" {{ $values['spam_rate_limit_enabled'] ? 'checked' : '' }}>
                    Per-IP rate limiting
                </h5>
            </div>
            <div class="card-body">
                <div class="form-row">
                    <div class="form-group col-md-4">
                        <label>Contact form (per minute)</label>
                        <input type="number" name="spam_rate_limit_max_contact" class="form-control" value="{{ $values['spam_rate_limit_max_contact'] }}" min="1" max="1000">
                    </div>
                    <div class="form-group col-md-4">
                        <label>Booking enquiry (per minute)</label>
                        <input type="number" name="spam_rate_limit_max_booking" class="form-control" value="{{ $values['spam_rate_limit_max_booking'] }}" min="1" max="1000">
                    </div>
                    <div class="form-group col-md-4">
                        <label>Review submission (per minute)</label>
                        <input type="number" name="spam_rate_limit_max_review" class="form-control" value="{{ $values['spam_rate_limit_max_review'] }}" min="1" max="1000">
                    </div>
                </div>
                <small class="text-muted">A small office sharing one IP can comfortably do 5&ndash;10 enquiries/minute. Lower numbers tighten the screw on bots; raise if you see legit users blocked.</small>
            </div>
        </div>

        {{-- Disposable email --}}
        <div class="card mb-3">
            <div class="card-header">
                <h5 class="card-title m-0">
                    <input type="hidden" name="spam_disposable_email_enabled" value="0">
                    <input type="checkbox" name="spam_disposable_email_enabled" value="1" {{ $values['spam_disposable_email_enabled'] ? 'checked' : '' }}>
                    Disposable / temporary email blocklist
                </h5>
            </div>
            <div class="card-body">
                <p class="text-muted">
                    Blocks well-known throwaway domains (Mailinator, 10MinuteMail, Guerrilla Mail, etc.).
                    The bundled list lives at <code>resources/spam/disposable_email_domains.txt</code>; add fresh ones below without redeploying.
                </p>
                <div class="form-group mb-0">
                    <label>Extra domains (one per line, or comma-separated)</label>
                    <textarea name="spam_extra_disposable_domains" rows="6" class="form-control" placeholder="example-throwaway.com&#10;temp-mailbox.io">{{ $values['spam_extra_disposable_domains'] }}</textarea>
                </div>
            </div>
        </div>

        <button type="submit" class="btn btn-primary">Save settings</button>
    </form>

    {{-- Recent rejections (read-only) --}}
    <div class="card mt-4">
        <div class="card-header">
            <h5 class="card-title m-0">Recent rejections (today)</h5>
        </div>
        <div class="card-body p-0">
            @if(empty($recentLog))
                <div class="p-3 text-muted">No rejections logged today. Logs live at <code>storage/logs/spam-YYYY-MM-DD.log</code> (30-day retention).</div>
            @else
                <table class="table table-sm m-0">
                    <thead><tr><th>Time</th><th>Form</th><th>Reason</th><th>IP</th><th>Email</th></tr></thead>
                    <tbody>
                    @foreach($recentLog as $row)
                        <tr>
                            <td><small>{{ $row['ts'] }}</small></td>
                            <td>{{ $row['form'] }}</td>
                            <td><code>{{ $row['reason'] }}</code></td>
                            <td>{{ $row['ip'] }}</td>
                            <td>{{ $row['email'] ?: '—' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>
@stop
