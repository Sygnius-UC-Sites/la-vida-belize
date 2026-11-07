@extends('admin.layouts')
@section('title', 'Booking Enquiries')
@php
$name="Booking Enquiries";$route="booking-enquiries";
@endphp

@section('css')
@parent
<style>
/* Phase 13F — booking-enquiries index, scoped to body.be-page so it can't leak. */
body.be-page { background: #f7f8fb; font-family: "Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif !important; }
body.be-page .content-wrapper { background: #f7f8fb !important; }
body.be-page h1 { font-size: 22px !important; font-weight: 700 !important; letter-spacing: -.018em !important; color: #0b1220 !important; }
body.be-page .be-card { background: #fff; border: 1px solid #e6e8ee; border-radius: 10px; box-shadow: 0 1px 2px rgba(15,23,42,.05); margin: 0 0 18px 0; }
body.be-page .be-filter { display: flex; gap: 10px; padding: 14px 18px; border-bottom: 1px solid #e6e8ee; flex-wrap: wrap; align-items: center; }
body.be-page .be-filter input,
body.be-page .be-filter select { border: 1px solid #c7ccd6; border-radius: 6px; padding: 0 12px; height: 38px; font-size: 14px; background: #fff; color: #0b1220; }
body.be-page .be-filter input[type=text] { width: 280px; }
body.be-page .be-filter input:focus, body.be-page .be-filter select:focus { outline: 0; border-color: #1e293b; box-shadow: 0 0 0 3px #eef2f7; }
body.be-page .be-reset { background: transparent; border: 1px solid #c7ccd6; color: #2c3447; padding: 0 16px; height: 38px; border-radius: 6px; font-size: 13px; cursor: pointer; }
body.be-page .be-table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 14px; }
body.be-page .be-table th { background: #f7f8fb; color: #5b6473; font-size: 11px !important; text-transform: uppercase; letter-spacing: .06em; padding: 12px 16px !important; text-align: left; border-bottom: 1px solid #e6e8ee; font-weight: 600; }
body.be-page .be-table td { padding: 14px 16px !important; border-bottom: 1px solid #e6e8ee; vertical-align: middle; color: #0b1220; font-size: 14px !important; }
body.be-page .be-table tbody tr:last-child td { border-bottom: 0; }
body.be-page .be-table tbody tr { transition: background .12s; position: relative; }
body.be-page .be-table tbody tr:hover { background: #fafbfd; }
body.be-page .be-name { font-weight: 600; font-size: 15px; }
body.be-page .be-meta { color: #5b6473; font-size: 12px; margin-top: 2px; }
body.be-page .be-id { font-family: "JetBrains Mono", ui-monospace, Menlo, monospace; background: #eef2f7; padding: 2px 6px; border-radius: 4px; font-size: 11px; color: #2c3447; border: 1px solid #e2e8f0; display: inline-block; }
body.be-page .be-dates { font-variant-numeric: tabular-nums; }
body.be-page .be-dates .be-arrow { color: #94a3b8; margin: 0 6px; }
body.be-page .be-nights { display: inline-block; background: #eef2f7; color: #2c3447; font-size: 11px; font-weight: 600; padding: 2px 7px; border-radius: 999px; margin-top: 4px; }
body.be-page .be-amount { font-weight: 600; font-variant-numeric: tabular-nums; }
body.be-page .be-pill { display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; line-height: 1.4; letter-spacing: -.01em; }
body.be-page .be-pill::before { content: ""; width: 6px; height: 6px; border-radius: 50%; background: currentColor; opacity: .8; }
body.be-page .be-pill--new       { background: #eef0ff; color: #4338ca; }
body.be-page .be-pill--pending   { background: #fff1d6; color: #b45309; }
body.be-page .be-pill--booked    { background: #d1fae5; color: #047857; }
body.be-page .be-pill--completed { background: #e0f2fe; color: #0369a1; }
body.be-page .be-pill--cancelled { background: #fef0ee; color: #b42318; }
body.be-page .be-pill--unknown   { background: #f1f5f9; color: #64748b; }
body.be-page .be-lifecycle { display: inline-flex; gap: 6px; }
body.be-page .be-dot { width: 22px; height: 22px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 9px; font-weight: 700; cursor: help; }
body.be-page .be-dot--sent    { background: #d1fae5; color: #047857; border: 1px solid #6ee7b7; }
body.be-page .be-dot--pending { background: #fef3c7; color: #b45309; border: 1px solid #fcd34d; }
body.be-page .be-dot--failed  { background: #fee2e2; color: #b42318; border: 1px solid #fca5a5; animation: bePulse 1.6s ease-in-out infinite; }
body.be-page .be-dot--idle    { background: #f1f5f9; color: #94a3b8; border: 1px solid #e2e8f0; }
@keyframes bePulse { 0%,100% { box-shadow: 0 0 0 0 rgba(180,35,24,.4);} 50% { box-shadow: 0 0 0 4px rgba(180,35,24,0);} }

/* Phase 13G — Legend banner at top of page */
body.be-page .be-legend { background: #fff; border: 1px solid #e6e8ee; border-radius: 10px; box-shadow: 0 1px 2px rgba(15,23,42,.05); margin: 0 0 14px 0; padding: 14px 18px; }
body.be-page .be-legend h4 { margin: 0 0 8px 0; font-size: 13px; color: #5b6473; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; }
body.be-page .be-legend-row { display: flex; gap: 24px; flex-wrap: wrap; align-items: center; }
body.be-page .be-legend-item { display: inline-flex; gap: 8px; align-items: center; font-size: 13px; color: #2c3447; }
body.be-page .be-legend-divider { width: 1px; height: 22px; background: #e6e8ee; }
body.be-page .be-actions { position: relative; text-align: right; }
body.be-page .be-action-btn { background: transparent; border: 1px solid #e6e8ee; color: #5b6473; width: 32px; height: 32px; border-radius: 6px; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; }
body.be-page .be-action-btn:hover { background: #f8fafc; color: #0b1220; border-color: #c7ccd6; }
body.be-page .be-action-menu { position: absolute; right: 0; top: 100%; margin-top: 4px; background: #fff; border: 1px solid #e6e8ee; border-radius: 10px; box-shadow: 0 10px 28px rgba(15,23,42,.10); min-width: 200px; z-index: 1200; padding: 4px; display: none; }
body.be-page .be-action-menu.show { display: block; }
body.be-page .be-action-menu a, body.be-page .be-action-menu button { display: flex; align-items: center; gap: 10px; width: 100%; text-align: left; padding: 9px 12px; background: transparent; border: 0; color: #0b1220; font-size: 14px; text-decoration: none; cursor: pointer; border-radius: 6px; }
body.be-page .be-action-menu a:hover, body.be-page .be-action-menu button:hover { background: #f7f8fb; text-decoration: none; }
body.be-page .be-action-menu .be-action-divider { height: 1px; background: #e6e8ee; margin: 4px 2px; }
body.be-page .be-action-menu .be-danger { color: #b42318 !important; }
body.be-page .be-action-menu .be-danger:hover { background: #fef0ee; }
body.be-page .be-empty { text-align: center; padding: 60px 20px; color: #5b6473; }
body.be-page .be-table tbody tr:last-child .be-action-menu,
body.be-page .be-table tbody tr:nth-last-child(2) .be-action-menu { top: auto; bottom: 100%; margin-top: 0; margin-bottom: 4px; }
body.be-page .be-content-header {
    padding: 18px 24px !important;
}
body.be-page .be-header {
    display: flex;
    align-items: center;
    gap: 14px;
    flex-wrap: wrap;
    width: 100%;
}
body.be-page .be-header-title {
    display: flex;
    align-items: center;
    gap: 12px;
    min-width: 0;
}
body.be-page .be-header-title h1 {
    margin: 0 !important;
}
body.be-page .content-header .be-header a.be-header-action,
body.be-page .content-header .be-header a.be-header-action:visited {
    margin-left: auto;
    background: #1e293b;
    color: #fff !important;
    font-weight: 600;
    border-radius: 6px;
    padding: 8px 14px;
    text-decoration: none !important;
    white-space: nowrap;
}
body.be-page .content-header .be-header a.be-header-action i {
    color: #fff !important;
}
body.be-page .content-header .be-header a.be-header-action:hover {
    background: #0f172a;
    color: #fff !important;
}
body.be-page .modal { z-index: 3000 !important; pointer-events: auto !important; }
body.be-page .modal.show { display: block !important; overflow-y: auto !important; }
body.be-page .modal-dialog,
body.be-page .modal-content { pointer-events: auto !important; }
body.be-page .modal-dialog {
    width: min(1100px, calc(100vw - 32px)) !important;
    max-width: min(1100px, calc(100vw - 32px)) !important;
    margin: 76px auto 24px !important;
    position: relative;
    z-index: 3001 !important;
}
body.be-page .modal-content {
    background: #fff !important;
    border: 1px solid #d9deea !important;
    border-radius: 12px !important;
    box-shadow: 0 24px 80px rgba(15,23,42,.28) !important;
    display: flex;
    flex-direction: column;
    max-height: calc(100dvh - 96px);
    overflow: hidden;
}
body.be-page .modal-header,
body.be-page .modal-footer { background: #fff !important; border-color: #e6e8ee !important; }
body.be-page .modal-header { display: flex !important; align-items: center !important; justify-content: space-between !important; padding: 18px 20px !important; }
body.be-page .modal-body {
    min-height: 0;
    flex: 1 1 auto;
    overflow: auto !important;
    -webkit-overflow-scrolling: touch;
    padding: 18px 20px !important;
}
body.be-page .modal-body table {
    min-width: 760px;
}
body.be-page .modal-body > table {
    min-width: 0;
}
body.be-page .modal-footer {
    position: sticky;
    bottom: 0;
    z-index: 2;
    flex-wrap: wrap;
    gap: 8px;
}
body.be-page .modal-title { margin: 0 !important; font-size: 18px !important; font-weight: 700 !important; color: #0b1220 !important; }
body.be-page .modal-header .close {
    position: static !important;
    margin: 0 0 0 auto !important;
    width: 32px;
    height: 32px;
    display: inline-flex !important;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    color: #0b1220 !important;
    opacity: 1 !important;
}
body.be-page .modal-backdrop.show { opacity: 1 !important; }
body.be-page .modal-backdrop {
    position: fixed !important;
    inset: 0 !important;
    width: 100vw !important;
    height: 100vh !important;
    background: rgba(15, 23, 42, .62) !important;
    z-index: 2990 !important;
    pointer-events: none !important;
}
body.be-page .modal .close,
body.be-page .modal button,
body.be-page .modal a,
body.be-page .modal input,
body.be-page .modal select,
body.be-page .modal textarea { pointer-events: auto !important; }
body.be-page .swal2-container,
body.be-page .swal2-container.swal2-backdrop-show,
body.be-page .swal-overlay { z-index: 4200 !important; }
body.be-page .swal-modal { z-index: 4201 !important; }
body.be-page .be-email-card {
    background: #f7f8fb;
    border: 1px solid #e6e8ee;
    border-radius: 10px;
    padding: 0;
    margin-bottom: 18px;
    font-family: 'Inter', -apple-system, sans-serif;
    overflow: hidden;
}
body.be-page .be-email-title {
    font-size: 13px;
    font-weight: 600;
    color: #5b6473;
    text-transform: uppercase;
    letter-spacing: .04em;
    margin: 0;
    padding: 14px 16px;
    background: #fff;
    border-bottom: 1px solid #e6e8ee;
}
body.be-page .be-email-row {
    display: grid !important;
    grid-template-columns: 42px minmax(0, 1fr) auto minmax(160px, auto);
    gap: 14px;
    align-items: center;
    padding: 14px 16px;
    border-top: 0;
    border-bottom: 1px solid #e6e8ee;
}
body.be-page .be-email-row:last-child {
    border-bottom: 0;
}
body.be-page .be-email-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 38px;
    height: 38px;
    border-radius: 10px;
    background: #fff;
    border: 1px solid #e6e8ee;
    font-size: 20px;
}
body.be-page .be-email-copy {
    min-width: 0;
}
body.be-page .be-email-label {
    font-weight: 600;
    color: #0b1220;
    font-size: 14px;
}
body.be-page .be-email-note {
    font-size: 12px;
    color: #5b6473;
    margin-top: 2px;
}
body.be-page .be-email-state {
    display: inline-block;
    font-size: 11px;
    font-weight: 600;
    padding: 3px 10px;
    border-radius: 999px;
    text-transform: uppercase;
    letter-spacing: .04em;
    white-space: nowrap;
}
body.be-page .be-email-actions {
    display: flex;
    flex-direction: row;
    gap: 6px;
    align-items: center;
    justify-content: flex-end;
    flex-wrap: wrap;
}
body.be-page .be-email-actions .btn {
    min-width: 112px;
    max-width: 100%;
    white-space: nowrap;
}
body.be-page .be-email-actions .disabled {
    background: #f8fafc !important;
    color: #94a3b8 !important;
}
body.be-page .modal .card-header .float-right {
    float: none !important;
    margin-left: auto;
}
body.be-page .modal .card-body .d-flex {
    gap: 10px !important;
}
body.be-page .modal .card-body form {
    margin: 0;
}
body.be-page .modal .card-body .input-group {
    max-width: 220px !important;
}
body.be-page .modal .card-outline {
    margin-bottom: 16px !important;
}
body.be-page .modal-footer {
    align-items: center;
    justify-content: flex-end;
}
body.be-page .modal-footer .btn {
    min-width: 118px;
}
body.be-page .modal-footer .btn-outline-danger {
    border-color: #f1b4ae !important;
    color: #b42318 !important;
}
body.be-page .modal-footer .btn-outline-danger:hover {
    background: #fef0ee !important;
}
body.be-page .modal-footer .btn-outline-success {
    border-color: #b7dac7 !important;
    color: #047857 !important;
}
body.be-page .modal-footer .btn-outline-success:hover {
    background: #ecfdf5 !important;
}
@media (max-width: 760px) {
    body.be-page .be-header {
        align-items: stretch;
    }
    body.be-page .be-header-title {
        width: 100%;
    }
    body.be-page .be-header-action {
        margin-left: 0;
        width: 100%;
        text-align: center;
    }
    body.be-page .modal-dialog {
        width: calc(100vw - 16px) !important;
        max-width: calc(100vw - 16px) !important;
        margin: 68px 8px 16px !important;
    }
    body.be-page .modal-content {
        max-height: calc(100vh - 84px);
    }
    body.be-page .be-email-row {
        grid-template-columns: 36px minmax(0, 1fr);
        align-items: start;
    }
    body.be-page .be-email-state,
    body.be-page .be-email-actions {
        grid-column: 2;
        justify-self: start;
        align-items: flex-start;
    }
}
</style>
@stop

@section('content_header')
<section class="content-header be-content-header">
    <div class="be-header">
        <div class="be-header-title">
            <h1>{{$name}}</h1>
        <span class="be-pill be-pill--unknown" id="be-count">{{ count($data) }} total</span>
        </div>
        <a href="{{ route($route.'.create') }}" class="btn be-header-action">
            <i class="fa fa-plus"></i> Add manual booking
        </a>
    </div>
</section>
@stop
@section('content')
@parent
<script>document.body.classList.add('be-page');</script>

<section class="content" style="padding: 0 24px;">

    {{-- Tabs: website bookings (actionable) vs imported PMS reservations (read-only) --}}
    <div class="be-tabs" style="display:flex;gap:6px;margin:16px 0 14px;border-bottom:1px solid #e5e9f0;">
        <button type="button" class="be-tab is-active" data-tab="direct"
            style="background:none;border:none;border-bottom:2px solid #1e293b;font-weight:600;padding:8px 14px;cursor:pointer;color:#0b1220;">
            Website bookings <span class="badge badge-secondary">{{ count($data) }}</span>
        </button>
        @php
            // Feature flag: imported-reservations tab can be sold separately.
            // Devs always see it.
            $beShowPmsTab = \App\Helper\FeatureFlags::isDeveloper()
                || \App\Helper\FeatureFlags::enabled('enquiries_imported_tab');
        @endphp
        @if($beShowPmsTab)
        <button type="button" class="be-tab" data-tab="pms"
            style="background:none;border:none;border-bottom:2px solid transparent;font-weight:600;padding:8px 14px;cursor:pointer;color:#64748b;">
            <i class="fa fa-plug"></i> Imported reservations <span class="badge badge-info">{{ count($pmsBookings ?? collect()) }}</span>
        </button>
        @endif
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        var tabs = document.querySelectorAll('.be-tab');
        tabs.forEach(function (t) {
            t.addEventListener('click', function () {
                tabs.forEach(function (x){ x.classList.remove('is-active'); x.style.borderBottomColor='transparent'; x.style.color='#64748b'; });
                t.classList.add('is-active'); t.style.borderBottomColor='#1e293b'; t.style.color='#0b1220';
                document.getElementById('be-panel-direct').style.display = t.dataset.tab==='direct' ? '' : 'none';
                var pmsPanel = document.getElementById('be-panel-pms');
                if (pmsPanel) pmsPanel.style.display = t.dataset.tab==='pms' ? '' : 'none';
            });
        });
    });
    </script>

    <div id="be-panel-direct">

    <div class="be-legend">
        <h4>📬 Lifecycle column — what the dots mean</h4>
        <div class="be-legend-row">
            <div class="be-legend-item"><span class="be-dot be-dot--sent">W</span><span><strong>Welcome</strong> — package + greeting (~3-7d before check-in)</span></div>
            <div class="be-legend-item"><span class="be-dot be-dot--sent">C</span><span><strong>Check-in</strong> — door code, wifi, parking (~1d before)</span></div>
            <div class="be-legend-item"><span class="be-dot be-dot--sent">C</span><span><strong>Check-out</strong> — departure + cleaning (~1d before)</span></div>
            <div class="be-legend-item"><span class="be-dot be-dot--sent">R</span><span><strong>Review</strong> — post-stay request (~1-3d after)</span></div>
        </div>
        <div class="be-legend-row mt-2" style="margin-top:10px; padding-top:10px; border-top:1px solid #f1f5f9;">
            <div class="be-legend-item"><span class="be-dot be-dot--sent">✓</span><span><strong>Sent</strong> successfully</span></div>
            <div class="be-legend-divider"></div>
            <div class="be-legend-item"><span class="be-dot be-dot--pending">!</span><span><strong>Late</strong> — should have sent by now</span></div>
            <div class="be-legend-divider"></div>
            <div class="be-legend-item"><span class="be-dot be-dot--failed">✗</span><span><strong>Failed</strong> — last attempt errored (hover for details)</span></div>
            <div class="be-legend-divider"></div>
            <div class="be-legend-item"><span class="be-dot be-dot--idle">·</span><span><strong>Not yet scheduled</strong></span></div>
        </div>
        <p class="text-muted" style="margin: 8px 0 0 0; font-size: 12px;">Hover any dot for the exact timestamp / error. Click <strong>View details</strong> on a row to see the full lifecycle and manually re-send any email.</p>
    </div>

    <div class="be-card">
        <div class="be-filter">
            <input type="text" id="be-filter-keyword" placeholder="Search by name, email, or booking ID…">
            <select id="be-filter-status">
                <option value="">All statuses</option>
                <option value="new">New enquiry</option>
                <option value="pending">Awaiting payment / agreement</option>
                <option value="booked">Booked</option>
                <option value="completed">Completed</option>
                <option value="cancelled">Cancelled</option>
            </select>
            <button type="button" class="be-reset" id="be-filter-reset">Reset</button>
        </div>

        @if(count($data) === 0)
            <div class="be-empty">
                <p style="font-weight:600;color:#0b1220;font-size:18px;margin:0 0 4px 0;">No website bookings yet</p>
                <p style="margin:0;">Bookings made directly on this website appear here — with approve / decline / payment actions.</p>
                @if(count($pmsBookings ?? collect()) > 0)
                    <p style="margin:10px 0 0;">You have <strong>{{ count($pmsBookings) }}</strong> reservations imported from your PMS —
                        see the <a href="#" onclick="document.querySelector('.be-tab[data-tab=pms]').click();return false;"><strong>Imported reservations</strong></a> tab above.</p>
                @endif
            </div>
        @else
        <table class="be-table" id="be-table">
            <thead>
                <tr>
                    <th>Booking</th>
                    <th>Property</th>
                    <th>Dates</th>
                    <th style="text-align:right">Amount</th>
                    <th>Status</th>
                    <th>Lifecycle</th>
                    <th style="width:60px"></th>
                </tr>
            </thead>
            <tbody>
                @php
                $payment_currency = ModelHelper::getDataFromSetting('payment_currency') ?: '$';
                @endphp
                @php
                /**
                 * Phase 13G — derive a 3-state status for a single email type.
                 * Returns ['state' => sent|pending|failed|idle, 'title' => tooltip text, 'letter' => 'W'|'C'|'C'|'R'].
                 * State logic:
                 *   - failed: error column has a value (last attempt errored)
                 *   - sent:   _email = 'true' AND no error
                 *   - pending: scheduled date has passed but flag still 'false' (should-have-sent-by-now)
                 *   - idle:   not yet due
                 */
                $beDotState = function ($booking, $type, $letter, $scheduledFor) {
                    $errCol     = $type . '_email_error';
                    $sentCol    = $type . '_email';
                    $sentAt     = $type . '_email_sent_at';
                    $attemptCol = $type . '_email_attempted_at';
                    $error      = $booking->{$errCol} ?? null;
                    $isSent     = ($booking->{$sentCol} ?? 'false') === 'true';
                    $sentAtVal  = $booking->{$sentAt} ?? null;
                    $attemptVal = $booking->{$attemptCol} ?? null;
                    $now        = \Carbon\Carbon::now();

                    $label = ucfirst(str_replace('-', ' ', $type));
                    if ($error) {
                        $msg = "{$label} — FAILED on " . \Carbon\Carbon::parse($attemptVal)->format('M j, Y g:i A')
                             . "\nError: " . substr($error, 0, 140);
                        return ['state' => 'failed', 'title' => $msg, 'letter' => $letter];
                    }
                    if ($isSent && $sentAtVal) {
                        return ['state' => 'sent', 'title' => "{$label} — sent " . \Carbon\Carbon::parse($sentAtVal)->diffForHumans(), 'letter' => $letter];
                    }
                    if ($scheduledFor && $now->gt(\Carbon\Carbon::parse($scheduledFor))) {
                        return ['state' => 'pending', 'title' => "{$label} — should have sent on " . \Carbon\Carbon::parse($scheduledFor)->format('M j, Y') . " but hasn't yet. Open the booking to re-send manually.", 'letter' => $letter];
                    }
                    return ['state' => 'idle', 'title' => "{$label} — scheduled for " . ($scheduledFor ? \Carbon\Carbon::parse($scheduledFor)->format('M j, Y') : 'after the relevant booking event'), 'letter' => $letter];
                };

                // Schedule offsets from settings
                $welcomeOffset  = (int) (\ModelHelper::getDataFromSetting('welcome_package_send_day') ?: 0);
                $checkinOffset  = (int) (\ModelHelper::getDataFromSetting('checkin_email_send_day')   ?: 0);
                $checkoutOffset = (int) (\ModelHelper::getDataFromSetting('checkout_email_send_day')  ?: 0);
                $reviewOffset   = (int) (\ModelHelper::getDataFromSetting('review_send_day')          ?: 2);
                @endphp
                @foreach($data as $client)
                @php
                $statusInfo = Helper::getBookingStatusLabel($client->booking_status);
                $propertyModel = app(\App\Services\Booking\RentalAgreementPropertyResolver::class)->resolve($client);
                $propertyName = $propertyModel->name ?? '#'.$client->property_id;
                $isPmsBooking = !empty($client->pms_source);
                if ($isPmsBooking && $client->booking_status === 'booking-confirmed') {
                    $statusInfo = ['label' => 'Booked', 'class' => 'booked'];
                } elseif ($isPmsBooking && $client->booking_status === 'booking-cancel') {
                    $statusInfo = ['label' => 'Cancelled', 'class' => 'cancelled'];
                } elseif ($isPmsBooking && $client->booking_status === 'payment-captured-pms-failed') {
                    $statusInfo = ['label' => 'PMS retry needed', 'class' => 'pending'];
                }
                $isInvoice = $client->booking_type_admin === 'invoice';
                $cancelledStatuses = ['cancelled', 'booking-cancel', 'booking-cancelled', 'declined'];
                $canCancelBooking = !in_array((string) $client->booking_status, $cancelledStatuses, true)
                    && ($isPmsBooking || ($client->booking_status === 'booked' && $isInvoice));

                $checkin   = \Carbon\Carbon::parse($client->checkin);
                $checkout  = \Carbon\Carbon::parse($client->checkout);
                $welcomeDue  = $checkin->copy()->subDays($welcomeOffset);
                $checkinDue  = $checkin->copy()->subDays($checkinOffset);
                $checkoutDue = $checkout->copy()->subDays($checkoutOffset);
                $reviewDue   = $checkout->copy()->addDays($reviewOffset);

                $dots = [
                    $beDotState($client, 'welcome',  'W', $welcomeDue),
                    $beDotState($client, 'checkin',  'C', $checkinDue),
                    $beDotState($client, 'checkout', 'C', $checkoutDue),
                    $beDotState($client, 'review',   'R', $reviewDue),
                ];
                @endphp
                <tr data-id="{{ $client->id }}"
                    data-search="{{ Str::lower($client->name . ' ' . $client->email . ' ' . $client->id) }}"
                    data-status="{{ $statusInfo['class'] }}">

                    <td>
                        <div class="be-name">{{ $client->name }}</div>
                        <div class="be-meta">{{ $client->email }} @if($client->mobile)· {{ $client->mobile }}@endif</div>
                        <div style="margin-top:4px;"><span class="be-id">#{{ $client->id }}</span> <span class="be-meta" style="display:inline;">enquired {{ \Carbon\Carbon::parse($client->created_at)->diffForHumans() }}</span></div>
                    </td>

                    <td>
                        <div class="be-name" style="font-size:14px;">{{ $propertyName }}</div>
                        <div class="be-meta">{{ $client->total_guests ?? 0 }} guest{{ ($client->total_guests ?? 0) == 1 ? '' : 's' }}</div>
                        @if($isPmsBooking)
                            @php $pmsColors = ['lodgify'=>'info','hostfully'=>'success','guesty'=>'warning','hostaway'=>'primary']; @endphp
                            <div style="margin-top:3px;">
                                <span class="badge badge-{{ $pmsColors[$client->pms_source] ?? 'secondary' }}" title="Booked through the {{ ucfirst($client->pms_source) }} PMS engine">
                                    <i class="fa fa-plug"></i> {{ ucfirst($client->pms_source) }}
                                </span>
                                <span class="badge badge-light" title="Managed from this booking detail panel">Website PMS booking</span>
                            </div>
                        @endif
                    </td>

                    <td>
                        <div class="be-dates">{{ date("M j", strtotime($client->checkin)) }}<span class="be-arrow">→</span>{{ date("M j, Y", strtotime($client->checkout)) }}</div>
                        <span class="be-nights">{{ $client->total_night ?? 0 }} night{{ ($client->total_night ?? 0) == 1 ? '' : 's' }}</span>
                    </td>

                    <td style="text-align:right">
                        {{-- Show the calculated total whenever we have one — was previously
                             gated to invoice-only bookings, which left enquiries blank. --}}
                        @if(!empty($client->total_amount) && (float) $client->total_amount > 0)
                            <span class="be-amount">{!! $payment_currency !!}{{ number_format((float)$client->total_amount, 2) }}</span>
                            @if($client->payment_status)
                                <div class="be-meta" style="text-align:right;">
                                    @switch($client->payment_status)
                                        @case('paid')         <span style="color:#28a745;">Paid</span> @break
                                        @case('partially')    <span style="color:#fd7e14;">Partially paid</span> @break
                                        @case('check-booking') <span style="color:#6c757d;">Awaiting approval</span> @break
                                        @case('failed')       <span style="color:#c33;">Payment failed</span> @break
                                        @default              {{ ucfirst(str_replace('-', ' ', (string) $client->payment_status)) }}
                                    @endswitch
                                </div>
                            @endif
                        @else
                            <span class="be-meta">—</span>
                        @endif
                    </td>

                    <td>
                        <span class="be-pill be-pill--{{ $statusInfo['class'] }}">{{ $statusInfo['label'] }}</span>
                    </td>

                    <td>
                        {{-- Lifecycle dots represent the welcome / check-in / check-out / review email
                             schedule. None of those emails fire until a booking is actually confirmed
                             (status = 'booked'), so showing a "delayed" warning color on a pending-
                             approval enquiry is misleading. Dots are only rendered for confirmed
                             bookings; for everything else show a neutral dash. --}}
                        @if(in_array($client->booking_status, ['booked', 'booking-confirmed'], true))
                            <div class="be-lifecycle">
                                @foreach($dots as $d)
                                    <span class="be-dot be-dot--{{ $d['state'] }}" title="{{ $d['title'] }}">{{ $d['letter'] }}</span>
                                @endforeach
                            </div>
                        @else
                            <span class="be-meta" title="Lifecycle emails activate once the booking is confirmed.">—</span>
                        @endif
                    </td>

                    <td class="be-actions">
                        <button type="button" class="be-action-btn be-menu-toggle" aria-label="Actions"><i class="fa fa-ellipsis-v"></i></button>
                        <div class="be-action-menu">
                            <a href="javascript:;" data-toggle="modal" data-target="#myModal{{ $client->id }}"><i class="fa fa-eye"></i> View details</a>
                            @if(!$isPmsBooking)
                                <a href="{{ route($route.'.edit', [$client->id]) }}"><i class="fa fa-pencil-alt"></i> Edit booking</a>
                            @endif
                            @if(in_array($client->booking_status, ['pending-approval','booking-accepted','rental-aggrement-success','rental-aggrement']))
                                {{-- Single click: marks the booking as accepted AND emails the guest a tokenised
                                     magic-link payment URL (valid 7 days). Wording adapts so a fresh request reads
                                     "Approve & send payment link" while a re-send for an already-accepted booking
                                     just reads "Send payment link". --}}
                                <a href="{{ url('admin/booking-enquiries/confirmed/' . $client->id) }}">
                                    <i class="fa fa-check"></i>
                                    {{ $client->booking_status === 'pending-approval' ? 'Approve & send payment link' : 'Send payment link' }}
                                </a>
                            @endif
                            @if($client->booking_status === 'pending-approval')
                                {{-- Decline: confirm dialog → POST /admin/booking-enquiries/decline/{id} → flips
                                     booking_status to 'declined' AND emails the guest a templated decline note.
                                     Editable in Email Templates → Decline. The record is kept (not deleted) so
                                     the host has an audit trail; use Delete record to remove permanently. --}}
                                <form method="post" action="{{ route('booking-enquiry-decline', [$client->id]) }}" style="display:none" id="decline-form-{{ $client->id }}">@csrf</form>
                                <button type="button" class="be-danger be-decline-btn"
                                        data-id="{{ $client->id }}" data-name="{{ $client->name }}">
                                    <i class="fa fa-times-circle"></i> Decline & notify guest
                                </button>
                            @endif
                            @if($canCancelBooking)
                                <form method="post" action="{{ $isPmsBooking ? route('admin.pms-bookings.destroy', [$client->id]) : route($route.'.destroy', [$client->id]) }}" style="display:none" id="cancel-form-{{ $client->id }}">@csrf @method('DELETE')</form>
                                <button type="button" class="be-danger be-cancel-btn" data-id="{{ $client->id }}" data-name="{{ $client->name }}"><i class="fa fa-times"></i> Cancel booking</button>
                            @endif
                            <div class="be-action-divider"></div>
                            <form method="POST" action="{{ route('booking-requests.destroyData', [$client->id]) }}" style="display:none" id="delete-form-{{ $client->id }}">@csrf @method('DELETE')</form>
                            <button type="button" class="be-danger delete-btn" data-id="{{ $client->id }}" data-name="{{ $client->name }}"><i class="fa fa-trash"></i> Delete record</button>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        <div class="be-empty" id="be-empty-filtered" style="display:none;">
            <p style="font-weight:600;color:#0b1220;font-size:16px;margin:0 0 4px 0;">No matches</p>
            <p style="margin:0;">Try clearing the filters.</p>
        </div>
        @endif
    </div>
    </div>{{-- /#be-panel-direct --}}

    {{-- Imported reservations from PMS (read-only) --}}
    @if($beShowPmsTab)
    <div id="be-panel-pms" style="display:none;">
        <div class="be-card">
            @php $pmsBookings = $pmsBookings ?? collect(); @endphp
            @if($pmsBookings->isEmpty())
                <div class="be-empty">
                    <p style="font-weight:600;color:#0b1220;font-size:18px;margin:0 0 4px 0;">No imported reservations</p>
                    <p style="margin:0;">Reservations from a connected PMS (Lodgify, Guesty…) appear here after a sync. Connect an account in <a href="{{ url('admin/pms-settings') }}">PMS Settings &amp; Sync</a>.</p>
                </div>
            @else
                <div style="padding:14px 16px;background:#f1fbfd;border-bottom:1px solid #e5e9f0;font-size:13px;color:#456;">
                    <i class="fa fa-info-circle"></i> These reservations were booked through your connected PMS (Airbnb, Booking.com, or directly in the PMS) and imported on sync. They block your calendar automatically. Read-only — manage them in your PMS account.
                </div>
                <table class="table table-hover mb-0" style="font-size:14px;">
                    <thead>
                        <tr style="background:#f8fafc;">
                            <th>Guest</th><th>Property</th><th>Dates</th><th>Guests</th>
                            <th style="text-align:right;">Amount</th><th>Status</th><th>Source</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($pmsBookings as $b)
                            @php
                                $guest = json_decode($b->guest ?? '{}', true) ?: [];
                                $guestName = $guest['guest_name'] ?? [];
                                $gname = ($guest['name'] ?? '')
                                    ?: ($guestName['full_name'] ?? '')
                                    ?: trim(($guest['first_name'] ?? '') . ' ' . ($guest['last_name'] ?? ''))
                                    ?: ($guest['email'] ?? '')
                                    ?: '—';
                                $gphone = $guest['phone'] ?? ($guest['phone_number'] ?? '');
                                $stColors = ['Booked'=>'success','Open'=>'warning','Tentative'=>'info','Declined'=>'secondary','Cancelled'=>'danger'];
                                $st = $b->status ?: '—';
                                $upcoming = $b->arrival && $b->arrival >= date('Y-m-d');
                            @endphp
                            <tr style="{{ $upcoming ? '' : 'opacity:.6;' }}">
                                <td>
                                    <strong>{{ $gname }}</strong>
                                    @if($gphone)<div class="text-muted" style="font-size:12px;">{{ $gphone }}</div>@endif
                                </td>
                                <td>{{ $b->property_name ?: ('#'.$b->property_id) }}</td>
                                <td>
                                    {{ $b->arrival ? date('M j', strtotime($b->arrival)) : '?' }}
                                    <span class="text-muted">→</span>
                                    {{ $b->departure ? date('M j, Y', strtotime($b->departure)) : '?' }}
                                    @if($upcoming)<span class="badge badge-light" title="Upcoming">soon</span>@endif
                                </td>
                                <td>{{ $b->people ?: '—' }}</td>
                                <td style="text-align:right;">
                                    @if($b->total_amount)
                                        {{ $b->currency ? $b->currency.' ' : '$' }}{{ number_format((float)$b->total_amount, 2) }}
                                    @else — @endif
                                </td>
                                <td><span class="badge badge-{{ $stColors[$st] ?? 'light' }}">{{ $st }}</span></td>
                                <td>
                                    <span class="badge badge-info"><i class="fa fa-plug"></i> {{ ucfirst($b->pms_source) }}</span>
                                    @if($b->source_text)<div class="text-muted" style="font-size:11px;">{{ $b->source_text }}</div>@endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>{{-- /#be-panel-pms --}}
    @endif

</section>
@stop
@section("js")
@parent
@php $data123=$data; @endphp
@foreach($data123 as $client)
<!-- The Modal -->
<div class="modal" id="myModal{{ $client->id }}">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">

            <!-- Modal Header -->
            <div class="modal-header">
                <h4 class="modal-title">Booking Detail</h4>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>

            <!-- Modal body -->
            <div class="modal-body">
                @php
                $data=$client->toArray();
                $property=app(\App\Services\Booking\RentalAgreementPropertyResolver::class)->resolve($client);
                $isPmsBooking = !empty($client->pms_source);
                $cancelledStatuses = ['cancelled', 'booking-cancel', 'booking-cancelled', 'declined'];
                $canCancelBooking = !in_array((string) $client->booking_status, $cancelledStatuses, true)
                    && ($isPmsBooking || ($client->booking_status === 'booked' && $client->booking_type_admin === 'invoice'));
                $stripePayment = App\Models\Payment::where('booking_id', $client->id)
                    ->where('type', 'stripe')->latest()->first();
                $depositStatus = (string) ($client->security_deposit_status ?? '');
                $depositAmount = (float) ($client->security_deposit_amount ?? 0);
                $depositCurrency = $client->security_deposit_currency ?: ($client->currency ?: 'USD');

                // Phase 13M — set of email types that have an actual snapshot stored
                // for this booking. Drives whether the 👁️ View sent copy button is shown.
                $sentTypes = \App\Services\Email\SendLog::sentTypesFor((int)$client->id);

                // Phase 13G/N — every customer-facing email shown in one unified list:
                // first the event-driven booking-lifecycle emails (sent at specific moments),
                // then the cron-driven stay emails (scheduled relative to check-in/check-out).
                $modalCheckin   = \Carbon\Carbon::parse($client->checkin);
                $modalCheckout  = \Carbon\Carbon::parse($client->checkout);
                $totalPayment   = (int)($client->total_payment ?? 1);
                $modalLifecycle = [
                    // Booking-time / event-driven (no schedule — triggered by an action)
                    ['type' => 'enquiry',          'label' => 'Enquiry confirmation', 'icon' => '📨', 'kind' => 'event', 'event_at' => \Carbon\Carbon::parse($client->created_at)],
                    ['type' => 'confirmed',        'label' => 'Booking confirmed',     'icon' => '✅', 'kind' => 'event'],
                ];
                // Magic-link pay-by-email is a sellable feature (dev panel).
                if (\App\Helper\FeatureFlags::isDeveloper() || \App\Helper\FeatureFlags::enabled('magic_link_payments')) {
                    array_splice($modalLifecycle, 1, 0, [
                        ['type' => 'awaiting-payment', 'label' => 'Awaiting-payment link', 'icon' => '💸', 'kind' => 'event'],
                    ]);
                }
                if ($totalPayment >= 2) $modalLifecycle[] = ['type' => 'installment2', 'label' => 'Installment 2 reminder', 'icon' => '2️⃣', 'kind' => 'event'];
                if ($totalPayment >= 3) $modalLifecycle[] = ['type' => 'installment3', 'label' => 'Installment 3 reminder', 'icon' => '3️⃣', 'kind' => 'event'];
                $modalLifecycle = array_merge($modalLifecycle, [
                    ['type' => 'welcome',  'label' => 'Welcome package',  'icon' => '🎁', 'kind' => 'cron', 'due' => $modalCheckin->copy()->subDays($welcomeOffset)],
                    ['type' => 'checkin',  'label' => 'Check-in info',    'icon' => '🔑', 'kind' => 'cron', 'due' => $modalCheckin->copy()->subDays($checkinOffset)],
                    ['type' => 'checkout', 'label' => 'Check-out info',   'icon' => '🚪', 'kind' => 'cron', 'due' => $modalCheckout->copy()->subDays($checkoutOffset)],
                    ['type' => 'review',   'label' => 'Review request',   'icon' => '⭐', 'kind' => 'cron', 'due' => $modalCheckout->copy()->addDays($reviewOffset)],
                    // Cancellation — always offer; only meaningful once the admin cancels the booking.
                    ['type' => 'cancellation', 'label' => 'Cancellation notice', 'icon' => '🚫', 'kind' => 'event'],
                ]);
                @endphp

                {{-- Phase 13G — Email Lifecycle card with per-email state, timestamps, errors, and "Send now" buttons --}}
                <div class="be-email-card">
                    <div class="be-email-title">📬 Email lifecycle</div>
                    @foreach($modalLifecycle as $life)
                        @php
                            // Phase 13N — derive state. Cron-driven types use the existing closure
                            // (which checks the *_email flag + scheduled date). Event-driven types
                            // use the SendLog snapshot store directly.
                            if ($life['kind'] === 'cron') {
                                $info = $beDotState($client, $life['type'], strtoupper(substr($life['type'],0,1)), $life['due']);
                                // Stay-emails only fire after the booking is confirmed. For pending /
                                // declined / cancelled enquiries the scheduled date is meaningless,
                                // so suppress the "late — should have sent already" warning state.
                                if ($client->booking_status !== 'booked' && $info['state'] === 'pending') {
                                    $info['state'] = 'idle';
                                }
                                $sentAt = $client->{$life['type'].'_email_sent_at'} ?? null;
                                $err    = $client->{$life['type'].'_email_error'} ?? null;
                                $att    = $client->{$life['type'].'_email_attempted_at'} ?? null;
                            } else {
                                $snap = \App\Services\Email\SendLog::latestFor((int)$client->id, $life['type']);
                                $info = ['state' => $snap ? ($snap->success ? 'sent' : 'failed') : 'idle', 'letter' => '', 'title' => ''];
                                $sentAt = $snap?->sent_at;
                                $err    = $snap?->error;
                                $att    = $snap?->created_at;
                            }
                            $colors = [
                                'sent'    => ['bg'=>'#d1fae5','fg'=>'#047857','label'=>'Sent successfully'],
                                'pending' => ['bg'=>'#fef3c7','fg'=>'#b45309','label'=>'Late — should have sent already'],
                                'failed'  => ['bg'=>'#fee2e2','fg'=>'#b42318','label'=>'Failed — last attempt errored'],
                                'idle'    => ['bg'=>'#f1f5f9','fg'=>'#94a3b8','label'=>$life['kind'] === 'cron' ? 'Not yet scheduled' : 'Not sent yet'],
                            ];
                            $col = $colors[$info['state']];
                        @endphp
                        <div class="be-email-row">
                            <div class="be-email-icon">{{ $life['icon'] }}</div>
                            <div class="be-email-copy">
                                <div class="be-email-label">{{ $life['label'] }}</div>
                                <div class="be-email-note">
                                    @if($info['state'] === 'sent')
                                        ✅ {{ $col['label'] }} — {{ \Carbon\Carbon::parse($sentAt)->format('M j, Y g:i A') }} ({{ \Carbon\Carbon::parse($sentAt)->diffForHumans() }})
                                    @elseif($info['state'] === 'failed')
                                        ❌ {{ $col['label'] }} — last attempt {{ \Carbon\Carbon::parse($att)->diffForHumans() }}<br>
                                        @if($err)<code style="background:#fee2e2;color:#b42318;padding:1px 5px;border-radius:3px;font-size:11px;">{{ $err }}</code>@endif
                                    @elseif($info['state'] === 'pending')
                                        ⚠️ {{ $col['label'] }} — was due on {{ $life['due']->format('M j, Y') }} ({{ $life['due']->diffForHumans() }})
                                    @elseif($life['kind'] === 'cron')
                                        ⏱️ Scheduled for {{ $life['due']->format('M j, Y') }} ({{ $life['due']->diffForHumans() }})
                                    @elseif($life['type'] === 'enquiry')
                                        ⏱️ Should send automatically when this enquiry was submitted ({{ $life['event_at']->diffForHumans() }}). If not received, click <strong>Send now</strong>.
                                    @elseif($life['type'] === 'awaiting-payment')
                                        ⏱️ Sends automatically when you click "Confirm" on a pending enquiry. Use <strong>Send now</strong> to re-send the magic-link.
                                    @elseif($life['type'] === 'confirmed')
                                        ⏱️ Sends automatically when payment is captured. Use <strong>Send now</strong> if the guest didn't receive it.
                                    @elseif($life['type'] === 'cancellation')
                                        ⏱️ Sends automatically when an admin cancels this booking. Use <strong>Send now</strong> only if you need to re-trigger it.
                                    @else
                                        ⏱️ Not sent yet — click <strong>Send now</strong> to fire it.
                                    @endif
                                </div>
                            </div>
                            <div>
                                <span class="be-email-state" style="background:{{ $col['bg'] }};color:{{ $col['fg'] }};">{{ $info['state'] }}</span>
                            </div>
                            <div class="be-email-actions">
                                @if(in_array($life['type'], $sentTypes, true))
                                    <a href="{{ url('admin/booking-enquiries/' . $client->id . '/email-preview/' . $life['type']) }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary" style="font-size:11px;padding:2px 8px;" title="View the exact copy that was sent to the guest">
                                        👁️ View sent copy
                                    </a>
                                @else
                                    <span class="btn btn-sm btn-outline-secondary disabled" style="font-size:11px;padding:2px 8px;opacity:.4;cursor:not-allowed;" title="Preview becomes available after the first send">
                                        👁️ Not sent yet
                                    </span>
                                @endif
                                <button type="button" class="btn btn-sm btn-outline-primary be-resend-btn" data-id="{{ $client->id }}" data-type="{{ $life['type'] }}" data-label="{{ $life['label'] }}">
                                    {{ $info['state'] === 'sent' ? 'Re-send' : 'Send now' }}
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div style="background:#fff;border:1px solid #e6e8ee;border-radius:10px;padding:14px;margin-bottom:18px;font-family:'Inter',-apple-system,sans-serif;">
                    <div style="display:flex;justify-content:space-between;gap:12px;align-items:flex-start;flex-wrap:wrap;">
                        <div>
                            <div style="font-size:13px;font-weight:600;color:#5b6473;text-transform:uppercase;letter-spacing:.04em;margin-bottom:4px;">Booking source</div>
                            @if($isPmsBooking)
                                @php $pmsColors = ['lodgify'=>'info','hostfully'=>'success','guesty'=>'warning','hostaway'=>'primary']; @endphp
                                <span class="badge badge-{{ $pmsColors[$client->pms_source] ?? 'secondary' }}" style="font-size:13px;">
                                    <i class="fa fa-plug"></i> {{ ucfirst($client->pms_source) }} website booking
                                </span>
                                @if($client->pms_booking_id)
                                    <span class="badge badge-light" style="font-size:13px;">PMS reservation {{ $client->pms_booking_id }}</span>
                                @endif
                                <div class="text-muted small mt-1">Cancellation, refund, PMS retry, and deposit actions are available below when applicable.</div>
                            @else
                                <span class="badge badge-dark" style="font-size:13px;">Direct website booking</span>
                                <div class="text-muted small mt-1">Managed by the website booking engine.</div>
                            @endif
                        </div>
                        <div style="display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end;">
                            @if($isPmsBooking && $client->booking_status === 'payment-captured-pms-failed')
                                <form method="post" action="{{ route('admin.booking.retry-pms', $client->id) }}" style="display:inline-block;">
                                    {!! csrf_field() !!}
                                    <button type="submit" class="btn btn-warning btn-sm"
                                            onclick="return confirm('Retry PMS reservation for this booking?')">
                                        <i class="fa fa-refresh"></i> Retry PMS reservation
                                    </button>
                                </form>
                            @endif
                            @if($canCancelBooking)
                                <form method="post" action="{{ $isPmsBooking ? route('admin.pms-bookings.destroy', [$client->id]) : route($route.'.destroy', [$client->id]) }}" style="display:inline-block;">
                                    {!! csrf_field() !!}
                                    {!! method_field('DELETE') !!}
                                    <button type="submit" class="btn btn-outline-danger btn-sm"
                                            onclick="return confirm('Cancel this booking? The guest will be notified and payment holds/installments will be handled where applicable.')">
                                        <i class="fa fa-times"></i> Cancel booking
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                </div>

                <table class="table table-bordered">
                    <tbody>
                        <tr>
                            <th colspan="4"><strong>Property Detail </strong></th>
                        </tr>
                        <tr>
                            <th>Request ID</th>
                            <td>{{ $data['request_id']  }}</td>

                            <th>Booking Date</th>
                            <td>{{ date("F jS, Y",strtotime($data['created_at'])) }}</td>
                        </tr>
                        <tr>
                            <th>Booking Status</th>
                            <td>
                                {!! Helper::getBookingStatus($client->booking_status,$client->id) !!}
                            </td>

                            <td><strong>Property Name :</strong></td>

                            <td>{{$property->name ?? $client->property_id }}</td>
                        </tr>
                        @if($data['booking_type_admin']=="invoice")
                        @if($data['booking_type_admin']=="invoice")
                        <tr>
                            <th colspan="3">Rental Aggrement</th>
                            <th>{{ $data['rental_aggrement_status'] }}</th>
                        </tr>
                        @endif
                        @if($data['rental_aggrement_status']=="true")

                        <tr>
                            <th>Sign</th>
                            <td><img src="{{ asset($data['rental_aggrement_signature']) }}" style="width: 100px;" /></td>

                            <th>Image</th>
                            <td><img src="{{ asset($data['rental_aggrement_images']) }}" style="width: 100px;" /></td>
                        </tr>
                        @endif

                        @endif




                        <tr>
                            <th colspan="4"><strong>User Detail </strong></th>
                        </tr>
                        <tr>
                            <td><strong>Name :</strong></td>
                            <td>{{$data['name']}}</td>

                            <td><strong>Email :</strong></td>
                            <td>{{$data['email']}}</td>
                        </tr>
                        <tr>
                            <td><strong>Mobile:</strong></td>
                            <td>{{$data['mobile']}}</td>

                            <td><strong>Message :</strong></td>
                            <td>
                                {{$data['message']}}
                            </td>
                        </tr>

                    </tbody>
                </table>
                <table class="table table-bordered">
                    <tbody>
                        <tr>
                            <th colspan="5"><strong>Booking Detail </strong></th>
                        </tr>



                        <tr>
                            <th><strong>Checkin :</strong></th>
                            <th><strong>Checkout :</strong></th>
                            <th><strong>Total Guest :</strong></th>
                            <th><strong>Total Night :</strong></th>
                            <td><strong>Gross Amount :</strong></td>

                        </tr>
                        <tr>
                            <td>{{$data['checkin'] }}</td>
                            <td>{{$data['checkout'] }}</td>
                            <td>{{$data['total_guests'] }} ({{$data['adults']}} Adults, {{$data['child']}} Child)</td>
                            <td>{{$data['total_night'] }}</td>
                            <td>@if($data['booking_type_admin']=="invoice") {!! $payment_currency !!}{{number_format($data['gross_amount'],2) }} @endif</td>
                        </tr>
                        @if($data['custom_before_total_fees'])
                        @php
                        $products=json_decode($data['custom_before_total_fees'],true);
                        @endphp
                        @foreach($products as $p)
                        @isset($p['product_amount'])
                        <tr>
                            <td colspan="4"><strong>{{$p['product_name']}}:</strong></td>
                            <td>{!! $payment_currency !!}{{number_format(($p['product_amount']),2) }}</td>
                        </tr>
                        @endisset
                        @endforeach
                        @endif
                        @if($data['booking_type_admin']=="invoice")
                        @if($data['rest_guests'])
                        @if($data['rest_guests']>0)
                        @if($data['guest_fee'])
                        @if($data['guest_fee']>0)
                        <tr>
                            <td colspan="4"><strong> Additional Guest Fee <br> <span style="font-size:13px;">({{$data['total_night']}} nights * {!! $payment_currency !!}{{$data['single_guest_fee']}} * {{$data['rest_guests']}} Guests)</span></strong></td>
                            <td>{!! $payment_currency !!}{{number_format($data['guest_fee'],2) }}</td>
                        </tr>
                        @endif
                        @endif
                        @endif
                        @endif



                        @if($data['extra_amount'])
                        @if($data['extra_amount']>0)

                        <tr>
                            <td colspan="4"><strong>Fee (Min. stay not met)<small>Minimum nights not met </small></strong></td>
                            <td>{!! $payment_currency !!}{{number_format($data['extra_amount'],2) }}</td>
                        </tr>

                        @endif
                        @endif


                        @if(null /* Phase 3 */ )
                        @php $ij=0; @endphp
                        @foreach(json_decode(null /* Phase 3 */) ?: [] as $c)
                        @php $c=(array)$c;@endphp
                        @if($c['status']=="true")
                        @if($ij==0)
                        <tr class="additional-fee">
                            <td colspan="5"><strong>RV Hookup </strong></td>
                        </tr>
                        @endif
                        <tr class="additional-fee">
                            <td colspan="4">{{ $c['rvhookup_name'] }} ({{ $c['rvhookup_rate'] *  $c['value'] }} @isset($c['quantity']) * {{ $c['quantity'] }} @endisset)<small>{{ $c['rvhookup_helping_text'] }}</small> </td>
                            <td>{!! $payment_currency !!}{{number_format($c['amount'],2)}}</td>
                        </tr>
                        @php $ij++; @endphp
                        @endif
                        @endforeach
                        @endif
                        @if(null /* Phase 3 */ )
                        @php $ij=0; @endphp
                        @foreach(json_decode(null /* Phase 3 */) ?: [] as $c)
                        @php $c=(array)$c;@endphp
                        @if($c['status']=="true")
                        @if($ij==0)
                        <tr class="additional-fee">
                            <td colspan="5"><strong>No. of visitor </strong></td>
                        </tr>
                        @endif
                        <tr class="additional-fee">
                            <td colspan="4">{{ $c['visitor_name'] }} ({{ $c['visitor_rate'] }} * {{ $c['value'] }})<small>{{ $c['visitor_helping_text'] }}</small> </td>
                            <td>{!! $payment_currency !!}{{number_format($c['amount'],2)}}</td>
                        </tr>
                        @php $ij++; @endphp
                        @endif
                        @endforeach
                        @endif
                        @if(null /* Phase 3 */ )
                        @php $ij=0; @endphp
                        @foreach(json_decode(null /* Phase 3 */) ?: [] as $c)
                        @php $c=(array)$c;@endphp
                        @if($c['status']=="true")
                        @if($ij==0)
                        <tr class="additional-fee">
                            <td colspan="5"><strong>Hot tub</strong> </td>
                        </tr>
                        @endif
                        <tr class="additional-fee">
                            <td colspan="4">{{ $c['hottub_name'] }} ({{ $c['hottub_rate'] }} * {{ $c['value'] }})<small>{{ $c['hottub_helping_text'] }}</small> </td>
                            <td>{!! $payment_currency !!}{{number_format($c['amount'],2)}}</td>
                        </tr>
                        @php $ij++; @endphp
                        @endif
                        @endforeach
                        @endif
                        @if(null /* Phase 3 */ )
                        @php $ij=0; @endphp
                        @foreach(json_decode(null /* Phase 3 */) ?: [] as $c)
                        @php $c=(array)$c;@endphp
                        @if($c['status']=="true")
                        @if($ij==0)
                        <tr class="additional-fee">
                            <td colspan="5"><strong>Firewood</strong></td>
                        </tr>
                        @endif
                        <tr class="additional-fee">
                            <td colspan="4">{{ $c['firewood_name'] }} ({{ $c['firewood_rate'] }} * {{ $c['value'] }})<small>{{ $c['firewood_helping_text'] }}</small> </td>
                            <td>{!! $payment_currency !!}{{number_format($c['amount'],2)}}</td>
                        </tr>
                        @php $ij++; @endphp
                        @endif
                        @endforeach
                        @endif


                        @if($data['pet_fee_type']=="taxable")
                        @if($data['total_pets'])
                        @if($data['total_pets']>0)
                        @if($data['pet_fee'])
                        @if($data['pet_fee']>0)
                        <tr>
                            <td colspan="4"><strong>Pet Fee :</strong></td>
                            <td>{!! $payment_currency !!}{{number_format($data['pet_fee'],2) }}</td>
                        </tr>
                        @endif
                        @endif
                        @endif
                        @endif
                        @endif


                        @if($data['heating_pool_fee_type']=="taxable")
                        @if($data['heating_pool_fee'])
                        @if($data['heating_pool_fee']>0)
                        <tr>
                            <td colspan="4"><strong>Heating the Swimming Pool fee :</strong></td>
                            <td>{!! $payment_currency !!}{{number_format($data['heating_pool_fee'],2) }}</td>
                        </tr>
                        @endif
                        @endif
                        @endif


                        @foreach(json_decode($data['before_total_fees']) ?: [] as $c)
                        <tr>
                            <td colspan="4"><strong>{{$c->name}} :</strong></td>
                            <td>{!! $payment_currency !!}{{number_format($c->amount ?? $c->value ?? 0,2) }}</td>
                        </tr>
                        @endforeach




                        @if($data['tax'])
                        <tr>
                            <td colspan="4"><strong>Tax ({{ $data['define_tax'] ?? '-' }}%) :</strong></td>
                            <td>{!! $payment_currency !!}{{number_format($data['tax'],2) }}</td>
                        </tr>
                        @endif

                        <tr>
                            <td colspan="4"><strong>Sub Total :</strong></td>
                            <td>{!! $payment_currency !!}{{number_format($data['sub_amount'],2) }}</td>
                        </tr>

                        @if($data['pet_fee_type']!="taxable")
                        @if($data['total_pets'])
                        @if($data['total_pets']>0)
                        @if($data['pet_fee'])
                        @if($data['pet_fee']>0)
                        <tr>
                            <td colspan="4"><strong>Pet Fee :</strong></td>
                            <td>{!! $payment_currency !!}{{number_format($data['pet_fee'],2) }}</td>
                        </tr>
                        @endif
                        @endif
                        @endif
                        @endif
                        @endif


                        @if($data['heating_pool_fee_type']!="taxable")
                        @if($data['heating_pool_fee'])
                        @if($data['heating_pool_fee']>0)
                        <tr>
                            <td colspan="4"><strong>Heating the Swimming Pool fee :</strong></td>
                            <td>{!! $payment_currency !!}{{number_format($data['heating_pool_fee'],2) }}</td>
                        </tr>
                        @endif
                        @endif
                        @endif

                        @foreach(json_decode($data['after_total_fees']) ?: [] as $c)
                        <tr>
                            <td colspan="4"><strong>{{$c->name}} :</strong></td>
                            <td>{!! $payment_currency !!}{{number_format($c->amount ?? $c->value ?? 0,2) }}</td>
                        </tr>
                        @endforeach

                        <tr>
                            <td colspan="4"><strong>Total :</strong></td>
                            <td>{!! $payment_currency !!}{{number_format($data['total_amount'],2) }}</td>
                        </tr>



                        @if($data['discount'])
                        @if($data['discount']!="")
                        @if($data['discount']!=0)

                        <tr>
                            <td colspan="4"><strong>Discount ({{ $data['discount_coupon'] }}):</strong></td>
                            <td>- {!! $payment_currency !!}{{number_format($data['discount'],2) }}</td>
                        </tr>
                        <tr>
                            <td colspan="4"><strong>Total Amount after Discount:</strong></td>
                            <td>{!! $payment_currency !!}{{number_format($data['after_discount_total'],2) }}</td>
                        </tr>
                        @endif
                        @endif
                        @endif
                        @if($data['extra_discount'])
                        <tr>
                            <td colspan="4"><strong>Extra Discount:</strong></td>
                            <td>- {!! $payment_currency !!}{{number_format($data['extra_discount'],2) }}</td>
                        </tr>
                        @endif
                        @if($data['amount_data'])
                        @php
                        $amount_data=json_decode($data['amount_data'],true);
                        @endphp
                        @if(is_array($amount_data))
                        @foreach($amount_data as $c)
                            {{-- Pre-Phase-13 bookings store amount_data as a list of
                                 {message, amount, status?} line items. Phase 13's
                                 QuoteCalculator uses a different schema (nights,
                                 breakdown, total, …) — skip those entries here so
                                 the legacy view doesn't crash on scalar values. --}}
                            @if(is_array($c) && isset($c['message'], $c['amount']))
                                @php $status = isset($c['status']) ? '(<span style="color:green;">Paid</span>)' : ''; @endphp
                                <tr>
                                    <td colspan="4"><strong>{{ $c['message'] }} {!! $status !!}:</strong></td>
                                    <td>{!! $payment_currency !!}{{ number_format((float) $c['amount'], 2) }}</td>
                                </tr>
                            @endif
                        @endforeach
                        @endif
                        @endif

                        @endif

                    </tbody>
                </table>
                @if($data['booking_type_admin']=="invoice")
                <table class="table table-bordered">
                    <tr>
                        <th colspan="4">Status</th>
                    </tr>
                    <tr>
                        <th>Payment </th>
                        <td>{{ $data['payment_status'] }}</td>

                        <th>Welcome Email </th>
                        <td>{{ $data['welcome_email'] }}</td>
                    </tr>
                    <tr>
                        <th>Review Email </th>
                        <td>{{ $data['review_email'] }}</td>

                        <th>Reminder Email </th>
                        <td>{{ $data['reminder_email'] }}</td>
                    </tr>
                    <tr>
                        <th>Checkin Email </th>
                        <td>{{ $data['checkin_email'] }}</td>

                        <th>Checkout Email </th>
                        <td>{{ $data['checkout_email'] }}</td>
                    </tr>
                </table>
                <table class="table table-bordered">
                    <tr>
                        <th>Payment Interval</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Tran ID</th>
                        <th>Mode</th>
                    </tr>
                    @if($data['amount_data'])
                    @php
                    $amount_data=json_decode($data['amount_data'],true);
                    @endphp
                    @if(is_array($amount_data))
                    @foreach($amount_data as $c)
                        {{-- Same Phase 13 guard as the legacy line-item iteration above. --}}
                        @if(is_array($c) && isset($c['message'], $c['amount']))
                            @php $status = isset($c['status']) ? '(<span style="color:green;">Paid</span>)' : ''; @endphp
                            <tr>
                                <td align="left" style="padding: 10px;" valign="top"><strong>{{ $c['message'] }} {!! $status !!}:</strong></td>
                                <td align="right" style="padding: 10px;" valign="top">{!! $payment_currency !!}{{ number_format((float) $c['amount'], 2) }}</td>
                                <td align="right" style="padding: 10px;" valign="top">{{ $c['status'] ?? '--' }}</td>
                                <td align="right" style="padding: 10px;" valign="top">{{ $c['date'] ?? '--' }}</td>
                                <td align="right" style="padding: 10px;" valign="top">{{ $c['tran_id'] ?? '--' }}</td>
                                <td align="right" style="padding: 10px;" valign="top">{{ $c['mode'] ?? '--' }}</td>
                            </tr>
                        @endif
                    @endforeach
                    @endif
                    @endif
                </table>
                @endif

                @if($depositAmount > 0 || !empty($client->stripe_deposit_charge_id))
                    <div class="card card-outline {{ $depositStatus === 'captured' ? 'card-danger' : ($depositStatus === 'released' ? 'card-success' : 'card-warning') }} mb-3">
                        <div class="card-header py-2">
                            <strong>Refundable Damage Deposit Hold</strong>
                            <span class="badge badge-{{ $depositStatus === 'authorized' ? 'warning' : ($depositStatus === 'captured' ? 'danger' : ($depositStatus === 'released' ? 'success' : 'secondary')) }} ml-2">
                                {{ $depositStatus ? ucfirst($depositStatus) : 'Not authorized' }}
                            </span>
                            @if($depositAmount > 0)
                                <span class="float-right text-muted small">{{ $depositCurrency }} {{ number_format($depositAmount, 2) }}</span>
                            @endif
                        </div>
                        @if($depositStatus === 'authorized')
                            <div class="card-body py-2">
                                <form method="post" action="{{ route('admin.booking.deposit.capture', $client->id) }}" style="display:inline-block;">
                                    {!! csrf_field() !!}
                                    <button type="submit" class="btn btn-danger btn-sm"
                                            onclick="return confirm('Charge {{ $depositCurrency }} {{ number_format($depositAmount, 2) }} from this hold? This cannot be undone.')">
                                        Capture deposit
                                    </button>
                                </form>
                                <form method="post" action="{{ route('admin.booking.deposit.release', $client->id) }}" style="display:inline-block;margin-left:6px;">
                                    {!! csrf_field() !!}
                                    <button type="submit" class="btn btn-success btn-sm"
                                            onclick="return confirm('Release this damage deposit hold?')">
                                        Release hold
                                    </button>
                                </form>
                                <small class="text-muted ml-2">Stripe hold: {{ $client->stripe_deposit_charge_id }}</small>
                            </div>
                        @elseif($depositStatus === 'failed')
                            <div class="card-body py-2">
                                <small class="text-danger">Deposit pre-authorization failed. Collect manually if this stay requires a hold.</small>
                            </div>
                        @else
                            <div class="card-body py-2">
                                <small class="text-muted">No active card hold to capture or release.</small>
                            </div>
                        @endif
                    </div>
                @endif

                @if($stripePayment && !in_array($stripePayment->status, ['refunded'], true))
                    <div class="card card-outline card-secondary mb-3">
                        <div class="card-header py-2">
                            <strong>Stripe Payment</strong>
                            <span class="badge badge-{{ $stripePayment->status === 'partially-refunded' ? 'warning' : 'success' }} ml-2">
                                {{ ucfirst($stripePayment->status) }}
                            </span>
                            <span class="float-right text-muted small">{{ $client->currency ?: '$' }} {{ number_format((float) $stripePayment->amount, 2) }}</span>
                        </div>
                        <div class="card-body py-2">
                            <form method="post" action="{{ route('admin.booking.refund', $client->id) }}"
                                  onsubmit="return confirmRefund(this)">
                                {!! csrf_field() !!}
                                <div class="d-flex align-items-center" style="gap:8px;flex-wrap:wrap;">
                                    <div class="input-group input-group-sm" style="max-width:170px;">
                                        <div class="input-group-prepend"><span class="input-group-text">{{ $client->currency ?: '$' }}</span></div>
                                        <input type="number" name="refund_amount" class="form-control"
                                               step="0.01" min="0.01" max="{{ $stripePayment->amount }}"
                                               placeholder="{{ $stripePayment->amount }}"
                                               title="Leave blank for full refund">
                                    </div>
                                    <button type="submit" class="btn btn-warning btn-sm">Issue refund</button>
                                    <small class="text-muted">Leave blank for full refund. PMS ledger sync runs when supported.</small>
                                </div>
                                @if($stripePayment->status === 'partially-refunded')
                                    <small class="text-warning d-block mt-1">A partial refund has already been issued for this booking.</small>
                                @endif
                            </form>
                        </div>
                    </div>
                @endif



            </div>

            @if(!in_array((string) $client->booking_status, $cancelledStatuses, true))
            <!-- Modal footer -->
            <div class="modal-footer">
                @if(!$isPmsBooking)
                    <a href="{!! route($route.'.edit', [$client->id]) !!}" class="btn btn-outline-success btn-sm raw-margin-right-8"><i class="fa fa-pencil-alt"></i> Edit booking</a>
                @endif
                @if($canCancelBooking)
                    <form method="post" action="{{ $isPmsBooking ? route('admin.pms-bookings.destroy', [$client->id]) : route($route.'.destroy', [$client->id]) }}" style="display: inline-block;">
                        {!! csrf_field() !!}
                        {!! method_field('DELETE') !!}
                        <button type="submit" class="btn btn-outline-danger btn-sm raw-margin-right-8"
                            onclick="return confirm('Cancel this booking? The guest will be notified and payment holds/installments will be handled where applicable.')">
                            Cancel booking
                        </button>
                    </form>
                @endif
            </div>
            @endif
        </div>
    </div>
</div>

@endforeach

<script>
(function () {
    var FILTER_KEY = 'be_admin_filters';
    var $kw  = document.getElementById('be-filter-keyword');
    var $st  = document.getElementById('be-filter-status');
    var $rst = document.getElementById('be-filter-reset');
    var $cnt = document.getElementById('be-count');
    var $tbl = document.getElementById('be-table');
    var $emp = document.getElementById('be-empty-filtered');
    if (!$tbl) return;
    var rows = Array.prototype.slice.call($tbl.querySelectorAll('tbody tr'));
    var total = rows.length;

    function loadFilters() {
        try { var f = JSON.parse(localStorage.getItem(FILTER_KEY) || '{}'); if ($kw) $kw.value = f.kw || ''; if ($st) $st.value = f.st || ''; } catch (_) {}
    }
    function persistFilters() {
        try { localStorage.setItem(FILTER_KEY, JSON.stringify({ kw: $kw ? $kw.value : '', st: $st ? $st.value : '' })); } catch (_) {}
    }
    function applyFilters() {
        var kw = ($kw ? $kw.value : '').trim().toLowerCase();
        var st = $st ? $st.value : '';
        var visible = 0;
        rows.forEach(function (r) {
            var kwMatch = !kw || r.dataset.search.indexOf(kw) !== -1;
            var stMatch = !st || r.dataset.status === st;
            var show = kwMatch && stMatch;
            r.style.display = show ? '' : 'none';
            if (show) visible++;
        });
        if ($cnt) $cnt.textContent = visible === total ? (total + ' total') : (visible + ' of ' + total);
        if ($emp) $emp.style.display = (visible === 0 ? 'block' : 'none');
        persistFilters();
    }
    loadFilters();
    applyFilters();
    if ($kw) $kw.addEventListener('input', applyFilters);
    if ($st) $st.addEventListener('change', applyFilters);
    if ($rst) $rst.addEventListener('click', function () { if ($kw) $kw.value = ''; if ($st) $st.value = ''; applyFilters(); });

    // Three-dot action menus
    document.querySelectorAll('.be-menu-toggle').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            var menu = btn.nextElementSibling;
            var open = menu.classList.contains('show');
            document.querySelectorAll('.be-action-menu.show').forEach(function (m) { m.classList.remove('show'); });
            if (!open) menu.classList.add('show');
        });
    });
    document.addEventListener('click', function () {
        document.querySelectorAll('.be-action-menu.show').forEach(function (m) { m.classList.remove('show'); });
    });

    // Delete record
    document.querySelectorAll('.delete-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var id = btn.getAttribute('data-id');
            var name = btn.getAttribute('data-name');
            Swal.fire({
                title: 'Delete record for "' + name + '"?',
                text: 'This permanently removes the booking record. This action cannot be undone.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#b42318',
                confirmButtonText: 'Yes, delete it',
            }).then(function (r) { if (r.value) document.getElementById('delete-form-' + id).submit(); });
        });
    });

    // Decline & notify guest — flips status to 'declined' and emails the guest a templated note.
    // The record is preserved (host has audit trail); use Delete record to remove permanently.
    document.querySelectorAll('.be-decline-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var id = btn.getAttribute('data-id');
            var name = btn.getAttribute('data-name');
            Swal.fire({
                title: 'Decline request from "' + name + '"?',
                text: 'The guest will receive an email letting them know we cannot accept these dates. The record is kept for your audit trail.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#b42318',
                confirmButtonText: 'Yes, decline & notify',
            }).then(function (r) { if (r.value) document.getElementById('decline-form-' + id).submit(); });
        });
    });

    // Phase 13G — manual email re-send buttons in the View modal.
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.be-resend-btn');
        if (!btn) return;
        var id = btn.getAttribute('data-id');
        var type = btn.getAttribute('data-type');
        var label = btn.getAttribute('data-label') || 'this email';
        Swal.fire({
            title: 'Send "' + label + '" now?',
            text: 'The guest will receive the email immediately. Re-sending overwrites any previous failure state.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#2c7a7b',
            confirmButtonText: 'Yes, send now',
        }).then(function (r) {
            if (!r.value) return;
            btn.disabled = true;
            btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Sending…';
            fetch('{{ url("admin/booking-enquiries") }}/' + id + '/resend/' + type, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
            }).then(function (resp) { return resp.json(); }).then(function (json) {
                if (json.ok) {
                    Swal.fire({ title: 'Sent!', text: json.message, icon: 'success', timer: 2200, showConfirmButton: false }).then(function () { location.reload(); });
                } else {
                    Swal.fire('Failed', json.error || 'Send failed', 'error');
                    btn.disabled = false;
                    btn.textContent = 'Send now';
                }
            }).catch(function (err) {
                Swal.fire('Failed', err.message || 'Network error', 'error');
                btn.disabled = false;
                btn.textContent = 'Send now';
            });
        });
    });

    // Cancel booking
    document.querySelectorAll('.be-cancel-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var id = btn.getAttribute('data-id');
            var name = btn.getAttribute('data-name');
            Swal.fire({
                title: 'Cancel booking for "' + name + '"?',
                text: 'The guest will be notified. PMS/calendar/payment holds/installments are handled where applicable.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#b45309',
                confirmButtonText: 'Yes, cancel booking',
            }).then(function (r) { if (r.value) document.getElementById('cancel-form-' + id).submit(); });
        });
    });
})();
</script>
@stop
