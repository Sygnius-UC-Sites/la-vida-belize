@extends('admin.layouts')

@section('title', 'Mail Settings')

@section('content_header')
    <h1>Mail Settings</h1>
    <p class="text-muted">
        Outbound email is sent via a single shared mailer account.
        Each install has its own verified <strong>from domain</strong> &mdash; the mailer hard-rejects sends whose <code>From:</code> address doesn't match.
        Credentials, region, and per-install limits are configured below; nothing here is hard-coded.
    </p>
@stop

@section('content')
    @if(session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
    @if(session('danger'))  <div class="alert alert-danger">{{ session('danger') }}</div> @endif
    @if($errors->any())
        <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    {{-- Status banner --}}
    @if($values['mail_send_disabled'])
        <div class="alert alert-warning d-flex align-items-center justify-content-between">
            <div>
                <strong>⚠️ Outbound mail is paused.</strong>
                Either an admin paused it manually, or the auto-disable threshold tripped (see "Health" below).
            </div>
            <form method="POST" action="{{ route('admin.mail.resume') }}" class="m-0">
                @csrf
                <button class="btn btn-warning btn-sm" type="submit">Resume sending</button>
            </form>
        </div>
    @else
        <div class="d-flex justify-content-end mb-3">
            <form method="POST" action="{{ route('admin.mail.pause') }}" class="m-0">
                @csrf
                <button class="btn btn-outline-warning btn-sm" type="submit"
                        onclick="return confirm('Pause all outbound mail immediately?')">Pause sending</button>
            </form>
        </div>
    @endif

    {{-- Health stats --}}
    <div class="card mb-3">
        <div class="card-header"><h5 class="m-0">Health (rolling 24h)</h5></div>
        <div class="card-body">
            <div class="row text-center">
                <div class="col"><div class="text-muted small">Deliveries</div><div style="font-size:1.6em;font-weight:600;">{{ $stats['deliveries'] }}</div></div>
                <div class="col"><div class="text-muted small">Bounces</div>
                    <div style="font-size:1.6em;font-weight:600; color: {{ $stats['bounce_rate'] >= $values['mail_bounce_rate_threshold'] ? '#c33' : '#222' }};">
                        {{ $stats['bounces'] }} <small>({{ number_format($stats['bounce_rate'] * 100, 2) }}%)</small>
                    </div>
                </div>
                <div class="col"><div class="text-muted small">Complaints</div>
                    <div style="font-size:1.6em;font-weight:600; color: {{ $stats['complaint_rate'] >= $values['mail_complaint_rate_threshold'] ? '#c33' : '#222' }};">
                        {{ $stats['complaints'] }} <small>({{ number_format($stats['complaint_rate'] * 100, 4) }}%)</small>
                    </div>
                </div>
                <div class="col"><div class="text-muted small">Sample size</div><div style="font-size:1.6em;font-weight:600;">{{ $stats['sample_size'] }}</div></div>
            </div>
            <p class="text-muted small mt-3 mb-0">
                Auto-pause kicks in when bounce rate ≥ <code>{{ $values['mail_bounce_rate_threshold'] * 100 }}%</code> or complaint rate ≥ <code>{{ $values['mail_complaint_rate_threshold'] * 100 }}%</code>, with at least 50 sample events. The provider's own thresholds are 5% / 0.1% — these are tighter so this install pauses before the provider does.
            </p>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.mail.update') }}">
        @csrf
        @method('PUT')

        {{-- Mailer credentials --}}
        <div class="card mb-3">
            <div class="card-header">
                <h5 class="m-0">
                    <input type="hidden" name="mail_enabled" value="0">
                    <input type="checkbox" name="mail_enabled" value="1" {{ $values['mail_enabled'] ? 'checked' : '' }}>
                    Mailer credentials (shared across all your client installs)
                </h5>
            </div>
            <div class="card-body">
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label>Key ID</label>
                        <input type="text" name="mail_provider_key_id" class="form-control" value="{{ $values['mail_provider_key_id'] }}" placeholder="key-id…" autocomplete="off">
                    </div>
                    <div class="form-group col-md-3">
                        <label>Region</label>
                        <input type="text" name="mail_provider_region" class="form-control" value="{{ $values['mail_provider_region'] }}" placeholder="us-east-1">
                    </div>
                </div>
                <div class="form-group">
                    <label>Secret key {{ $secretSet ? '(stored — leave blank to keep)' : '(required)' }}</label>
                    <input type="password" name="mail_provider_secret" class="form-control" value="" placeholder="{{ $secretSet ? '••••••••• ENCRYPTED AT REST' : 'Paste secret key' }}" autocomplete="new-password">
                    <small class="text-muted">Encrypted with <code>APP_KEY</code> via Laravel <code>Crypt</code>; never returned to this form.</small>
                </div>
            </div>
        </div>

        {{-- Per-install identity --}}
        <div class="card mb-3">
            <div class="card-header"><h5 class="m-0">This install's sending identity</h5></div>
            <div class="card-body">
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label>From email <span class="text-danger">*</span></label>
                        <input type="email" name="mail_from_email" class="form-control" value="{{ $values['mail_from_email'] }}" placeholder="hello.com">
                    </div>
                    <div class="form-group col-md-6">
                        <label>From name</label>
                        <input type="text" name="mail_from_name" class="form-control" value="{{ $values['mail_from_name'] }}" placeholder="Base Vacation Rentals">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label>Verified domain <span class="text-danger">*</span></label>
                        <input type="text" name="mail_domain" class="form-control" value="{{ $values['mail_domain'] }}" placeholder="example.com">
                        <small class="text-muted"><strong>Hard-enforced:</strong> sends are rejected if the From address isn't <code>*@</code> this domain (or a subdomain). Verify the domain in SES first.</small>
                    </div>
                    <div class="form-group col-md-6">
                        <label>Reply-To (optional)</label>
                        <input type="email" name="mail_reply_to" class="form-control" value="{{ $values['mail_reply_to'] }}" placeholder="contact@example.com">
                    </div>
                </div>
                <div class="form-group mb-0">
                    <label>Configuration set (recommended)</label>
                    <input type="text" name="mail_configuration_set" class="form-control" value="{{ $values['mail_configuration_set'] }}" placeholder="install-name-prod">
                    <small class="text-muted">Tag this install's sends. Bounce/complaint events get routed via the provider's notification topic — see Webhook section below.</small>
                </div>
            </div>
        </div>

        {{-- Limits --}}
        <div class="card mb-3">
            <div class="card-header"><h5 class="m-0">Limits &amp; auto-disable thresholds</h5></div>
            <div class="card-body">
                <div class="form-row">
                    <div class="form-group col-md-4">
                        <label>Max sends per minute (this install)</label>
                        <input type="number" name="mail_max_per_minute" class="form-control" value="{{ $values['mail_max_per_minute'] }}" min="1" max="10000">
                    </div>
                    <div class="form-group col-md-4">
                        <label>Bounce-rate threshold (0–1)</label>
                        <input type="number" step="0.001" name="mail_bounce_rate_threshold" class="form-control" value="{{ $values['mail_bounce_rate_threshold'] }}" min="0" max="1">
                    </div>
                    <div class="form-group col-md-4">
                        <label>Complaint-rate threshold (0–1)</label>
                        <input type="number" step="0.0001" name="mail_complaint_rate_threshold" class="form-control" value="{{ $values['mail_complaint_rate_threshold'] }}" min="0" max="1">
                    </div>
                </div>
                <small class="text-muted">The provider enforces 5% / 0.1% account-wide. Setting these tighter (3% / 0.05% defaults) means this install pauses itself before the provider does — protecting your other client installs that share the same account.</small>
            </div>
        </div>

        {{-- Webhook --}}
        <div class="card mb-3">
            <div class="card-header"><h5 class="m-0">Bounce / complaint webhook</h5></div>
            <div class="card-body">
                <div class="form-group">
                    <label>Your webhook URL</label>
                    <input type="text" class="form-control" value="{{ $webhookUrl }}" readonly onfocus="this.select()" style="background:#f8f9fa;">
                    <small class="text-muted">Subscribe a notification topic to this URL (HTTP/HTTPS). The provider sends bounce/complaint events to the topic; the topic POSTs them here. The endpoint handles subscription confirmation automatically and verifies signatures.</small>
                </div>
                <div class="form-group mb-0">
                    <label>Allowed topic ID prefix (optional, recommended)</label>
                    <input type="text" name="mail_topic_arn_prefix" class="form-control" value="{{ $values['mail_topic_arn_prefix'] }}" placeholder="topic-prefix">
                    <small class="text-muted">If set, the webhook ignores notifications whose topic ID doesn't start with this string. Protects against forged messages from other topics in your provider account.</small>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-between align-items-center">
            <button type="submit" class="btn btn-primary">Save settings</button>
        </div>
    </form>

    {{-- Test send --}}
    <div class="card mt-4">
        <div class="card-header"><h5 class="m-0">Send test email</h5></div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.mail.test') }}" class="form-inline">
                @csrf
                <input type="email" name="test_to" class="form-control mr-2" placeholder="you@example.com" required style="min-width:300px;">
                <button class="btn btn-outline-primary" type="submit">Send test</button>
            </form>
            <small class="text-muted d-block mt-2">Uses the From identity above. Check the recent-sends table for the result.</small>
        </div>
    </div>

    {{-- Recent sends --}}
    <div class="card mt-4">
        <div class="card-header"><h5 class="m-0">Recent sends (last 50)</h5></div>
        <div class="card-body p-0">
            @if($recentSends->isEmpty())
                <div class="p-3 text-muted">No mail has been sent yet.</div>
            @else
                <table class="table table-sm m-0">
                    <thead><tr>
                        <th>When</th><th>Purpose</th><th>From</th><th>To</th><th>Subject</th><th>Status</th><th>Error</th>
                    </tr></thead>
                    <tbody>
                    @foreach($recentSends as $r)
                        <tr>
                            <td><small>{{ $r->sent_at ?? $r->created_at }}</small></td>
                            <td><small><code>{{ $r->purpose }}</code></small></td>
                            <td><small>{{ $r->from_email }}</small></td>
                            <td><small>{{ $r->to_email }}</small></td>
                            <td><small>{{ \Illuminate\Support\Str::limit($r->subject, 60) }}</small></td>
                            <td>
                                @if($r->status === 'sent')
                                    <span class="badge badge-success">sent</span>
                                @elseif(in_array($r->status, ['failed','disabled','rate_limited','suppressed']))
                                    <span class="badge badge-danger">{{ $r->status }}</span>
                                @else
                                    <span class="badge badge-secondary">{{ $r->status }}</span>
                                @endif
                            </td>
                            <td><small>{{ $r->error_code ? "[{$r->error_code}] " . \Illuminate\Support\Str::limit((string)$r->error_message, 80) : '' }}</small></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>

    {{-- Suppression list --}}
    <div class="card mt-4 mb-5">
        <div class="card-header"><h5 class="m-0">Suppression list</h5></div>
        <div class="card-body p-0">
            @if($suppressions->isEmpty())
                <div class="p-3 text-muted">No suppressed addresses. Permanent bounces and complaints land here automatically via the webhook.</div>
            @else
                <table class="table table-sm m-0">
                    <thead><tr><th>Email</th><th>Reason</th><th>Subtype</th><th>Source</th><th>Added</th><th></th></tr></thead>
                    <tbody>
                    @foreach($suppressions as $s)
                        <tr>
                            <td>{{ $s->email }}</td>
                            <td><code>{{ $s->reason }}</code></td>
                            <td><small>{{ $s->bounce_subtype ?: '—' }}</small></td>
                            <td><small>{{ $s->source }}</small></td>
                            <td><small>{{ $s->created_at }}</small></td>
                            <td>
                                <form method="POST" action="{{ route('admin.mail.suppression.remove') }}" class="m-0">
                                    @csrf
                                    <input type="hidden" name="email" value="{{ $s->email }}">
                                    <button class="btn btn-link btn-sm text-danger p-0" type="submit"
                                            onclick="return confirm('Remove {{ $s->email }} from suppression?')">remove</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>
@stop
