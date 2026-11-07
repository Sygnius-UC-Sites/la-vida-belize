@isset($data)
{{-- Phase 6 — Airbnb-style Calendar tab. JSON-only AJAX; no full page reloads. --}}
<style>
    #calendar-tab-grid { background:#fff; padding:10px; border-radius:6px; }
    #calendar-tab-grid .fc-daygrid-day-frame { min-height: 96px; padding-bottom:2px; }
    #calendar-tab-grid .fc-daygrid-day-events { display:none; }
    #calendar-tab-grid .fc-toolbar-title { font-size:1.25rem !important; }
    #calendar-tab-grid .fc-col-header-cell-cushion { font-size: 12px; padding: 6px 2px; color:#6c757d; text-transform:uppercase; }
    #calendar-tab-grid .fc-daygrid-day-number { font-size: 12px; padding: 4px 6px; color:#495057; font-weight:500; }
    #calendar-tab-grid .fc-day-today .fc-daygrid-day-number { color:#fff; background:#007bff; border-radius:50%; width:22px; height:22px; line-height:14px; text-align:center; padding:4px 0; }
    .cal-slot { padding: 2px 6px 4px; font-size: 12px; line-height: 1.25; }
    .cal-slot .price { font-weight: 600; color:#212529; font-size:13px; }
    .cal-slot .min-stay { color:#6c757d; font-size:10px; }
    .cal-slot .badge-status { font-size:10px; padding:1px 5px; border-radius:3px; color:#fff; display:inline-block; margin-top:2px; }
    .cal-slot .src-label { color:#6c757d; font-size:10px; margin-top:1px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    #calendar-tab-grid .fc-daygrid-day.cal-status-booked { background:#d1ecf1; }
    #calendar-tab-grid .fc-daygrid-day.cal-status-blocked { background:#fff3cd; }
    #calendar-tab-grid .fc-daygrid-day.cal-status-override .fc-daygrid-day-frame { box-shadow: inset 3px 0 0 #007bff; }
    #calendar-tab-grid .fc-daygrid-day.cal-status-weekend .fc-daygrid-day-frame { box-shadow: inset 3px 0 0 #ffc107; }
    #calendar-tab-grid .fc-daygrid-day.cal-selected { outline: 2px solid #ff5a5f; outline-offset: -2px; }
    .cal-status-booked .cal-slot .badge-status { background:#28a745; }
    .cal-status-blocked .cal-slot .badge-status { background:#ffc107; color:#212529; }
    .cal-cell { padding:4px 6px; font-size:12px; line-height:1.25; height:100%; }
    .cal-cell .price { font-weight:600; color:#212529; }
    .cal-cell .min-stay { color:#6c757d; font-size:10px; }
    .cal-cell.cell-blocked { background: var(--cal-source-color, #fff3cd); }
    /* FullCalendar-rendered cells (the main grid). Same CSS variable wins so that
       admin-selected feed colour shows up when the day is blocked by an iCal feed. */
    #calendar-tab-grid .fc-daygrid-day.cal-status-blocked { background: var(--cal-source-color, #fff3cd); }
    #calendar-tab-grid .fc-daygrid-day.cal-status-booked  { background: var(--cal-source-color, #d4edda); }
    .cal-cell.cell-booked { background:#d1ecf1; }
    .cal-cell.cell-override { box-shadow: inset 3px 0 0 #007bff; }
    .cal-cell .badge-status { font-size:10px; padding:2px 4px; border-radius:3px; color:#fff; display:inline-block; margin-top:2px; }
    .cal-cell.cell-booked .badge-status { background:#28a745; }
    .cal-cell.cell-blocked .badge-status { background:#ffc107; color:#212529; }
    /* Drawer clears the fixed dark topbar (64px) and has a backdrop behind it.
       Width capped to 400px and max-width 92% so it never escapes the viewport. */
    .cal-drawer {
        position: fixed;
        top: 64px;
        right: 0;
        bottom: 0;
        height: auto;
        width: 400px;
        max-width: 92vw;
        background: #ffffff;
        box-shadow: -1px 0 0 #e3e7ee, -24px 0 60px -20px rgba(15, 28, 51, .25);
        z-index: 1040;
        transform: translateX(100%);
        transition: transform .28s cubic-bezier(.16, 1, .3, 1);
        overflow-y: auto;
        border-left: 1px solid #e3e7ee;
    }
    .cal-drawer.open { transform: translateX(0); }
    /* Backdrop behind the drawer (dim the rest of the page when open) */
    .cal-drawer-backdrop {
        position: fixed;
        inset: 64px 0 0 0;
        background: rgba(15, 28, 51, 0.18);
        backdrop-filter: blur(4px) saturate(140%);
        -webkit-backdrop-filter: blur(4px) saturate(140%);
        opacity: 0;
        visibility: hidden;
        transition: opacity .24s ease, visibility .24s;
        z-index: 1039;
    }
    .cal-drawer-backdrop.open { opacity: 1; visibility: visible; }

    .cal-drawer .drawer-header {
        position: sticky; top: 0; background: #fff; z-index: 1;
        padding: 16px 20px;
        border-bottom: 1px solid #e3e7ee;
        display: flex; align-items: center; justify-content: space-between;
    }
    .cal-drawer .drawer-header h6 {
        font-size: 14px; font-weight: 600; color: #0f1c33;
        letter-spacing: -.005em; margin: 0;
    }
    .cal-drawer .drawer-body { padding: 18px 20px; }
    .cal-drawer .form-group label {
        font-size: 11px; color: #5a6478;
        text-transform: uppercase; letter-spacing: .08em;
        font-weight: 600; margin-bottom: 6px;
    }
    .cal-drawer .meta {
        background: #f5f5f7; padding: 12px 14px; border-radius: 8px;
        margin-bottom: 16px; font-size: 13px; color: #2a3855;
    }
    .cal-drawer .meta strong { color: #0f1c33; font-weight: 600; }
    .cal-feed-row {
        padding: 10px 12px; border: 1px solid #e3e7ee; border-radius: 8px;
        margin-bottom: 8px; display: flex; align-items: center;
        gap: 10px; flex-wrap: wrap;
    }
    .cal-feed-row .feed-color { width: 12px; height: 12px; border-radius: 50%; flex-shrink: 0; }
    .cal-feed-row .feed-meta { flex: 1; min-width: 200px; font-size: 13px; }
    .cal-feed-row .feed-meta small { display: block; color: #5a6478; font-size: 11.5px; }
    .cal-feed-row .feed-error { color: #be1818; font-size: 11.5px; }
    .cal-loading { color: #5a6478; font-style: italic; }
    .cal-pricing-rules-table th, .cal-pricing-rules-table td { font-size: 12.5px; padding: 8px 10px; }

    /* Replace inline width="120px" / "100px" / "140px" hardcodes with
       sensible flexible widths via class — kept as overrides since the
       inline `style="width:Npx"` is locked in the markup. */
    #cal-defaults-row input[type="number"],
    #cal-defaults-weekend-row input[type="number"] {
        width: 110px !important;
        max-width: 100% !important;
    }
    #cal-defaults-row,
    #cal-defaults-weekend-row {
        flex-wrap: wrap;
        gap: 8px 14px;
    }
    #cal-defaults-row .form-control-sm,
    #cal-defaults-weekend-row .form-control-sm {
        margin-right: 0 !important;
    }
    /* Calendar sync section — tighter, no nested cluster */
    .cal-sync-section { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 16px 22px; }
    .cal-sync-block { display: flex; flex-direction: column; gap: 8px; }
    .cal-sync-block .input-group { flex-wrap: nowrap; }
    .cal-sync-block input[type="text"],
    .cal-sync-block input[type="url"],
    .cal-sync-block input[type="date"] {
        width: 100%;
        max-width: 100%;
    }

    /* Pricing rules table on narrow widths — stack rather than spilling */
    .cal-pricing-rules-table { width: 100%; }

    /* Bootstrap modals — bump above the fixed topbar (1030) and dark
       drawer-backdrop. Pricing-rule + bulk-clear modals would otherwise
       sit behind the topbar. */
    body .modal { z-index: 1100 !important; }
    body .modal-backdrop { z-index: 1090 !important; }
    body .modal-dialog { margin-top: 80px; }
    /* Premium modal chrome */
    body .modal-content {
        border: 1px solid #e3e7ee;
        border-radius: 14px;
        box-shadow: 0 28px 60px -22px rgba(15, 28, 51, .35), 0 10px 24px -10px rgba(15, 28, 51, .15);
    }
    body .modal-header {
        border-bottom: 1px solid #e3e7ee;
        padding: 16px 20px;
    }
    body .modal-title {
        font-size: 16px;
        font-weight: 600;
        color: #0f1c33;
        letter-spacing: -.01em;
    }
    body .modal-body { padding: 20px; }
    body .modal-footer {
        border-top: 1px solid #e3e7ee;
        padding: 14px 20px;
    }
</style>

<div id="cal-tab-root" data-property-id="{{ $data->id }}">
    <div class="d-flex align-items-center mb-3">
        <h5 class="mb-0 mr-3">Calendar &amp; Pricing</h5>
        <span class="badge badge-light" id="cal-last-refresh-strip">Loading sync status…</span>
        <button type="button" class="btn btn-sm btn-outline-secondary ml-auto" id="cal-refresh-all-btn">
            <i class="fa fa-sync"></i> Refresh all feeds
        </button>
    </div>

    {{-- Property defaults ------------------------------------------------ --}}
    <div class="card mb-3">
        <div class="card-header py-2"><strong>Default pricing</strong> <small class="text-muted">The fallback price + min nights used for any day without a rule or override</small></div>
        <div class="card-body py-2">
            {{-- Plain div, NOT a <form> — this partial renders inside the outer
                 property-edit form, and HTML5 disallows nested forms. --}}
            <div id="cal-defaults-row" class="form-inline">
                <label class="mr-2">Default price ($)</label>
                <input type="number" id="cal-defaults-standard-rate" min="0" step="1" class="form-control form-control-sm mr-3" style="width:120px" value="{{ $data->standard_rate }}">
                <label class="mr-2">Default min nights</label>
                <input type="number" id="cal-defaults-min-stay" min="1" step="1" class="form-control form-control-sm mr-3" style="width:100px" value="{{ $data->min_stay }}">
                <button type="button" class="btn btn-sm btn-primary" id="cal-defaults-save">Save defaults</button>
                <span class="ml-3 text-muted small" id="cal-defaults-status"></span>
            </div>
            @php
                $wkSet = collect(explode(',', (string) $data->weekend_days))
                    ->map(fn ($s) => trim($s))->filter()->all();
                $wkDefault = empty($wkSet) ? ['5','6'] : $wkSet;
            @endphp
            <div id="cal-defaults-weekend-row" class="form-inline mt-2">
                <label class="mr-2">Weekend rate ($)</label>
                <input type="number" id="cal-defaults-weekend-rate" min="0" step="1" class="form-control form-control-sm mr-3" style="width:120px" value="{{ $data->weekend_rate }}" placeholder="(disabled)">
                <label class="mr-2">Apply on</label>
                @foreach (['Sun'=>0,'Mon'=>1,'Tue'=>2,'Wed'=>3,'Thu'=>4,'Fri'=>5,'Sat'=>6] as $lbl => $i)
                    <label class="mr-2 mb-0" style="font-weight:normal">
                        <input type="checkbox" class="cal-weekend-day" value="{{ $i }}" {{ in_array((string) $i, $wkDefault, true) ? 'checked' : '' }}> {{ $lbl }}
                    </label>
                @endforeach
                <small class="text-muted ml-2">Leave the rate blank to disable. Range rules still win.</small>
            </div>
        </div>
    </div>

    {{-- Pricing rules ----------------------------------------------------- --}}
    <div class="card mb-3">
        <div class="card-header py-2 d-flex align-items-center">
            <strong>Pricing rules</strong>
            <small class="text-muted ml-2">Date ranges with default price + per-weekday overrides</small>
            <button type="button" class="btn btn-sm btn-primary ml-auto" id="cal-add-rule-btn">
                <i class="fa fa-plus"></i> Add rule
            </button>
        </div>
        <div class="card-body p-2">
            <div class="table-responsive">
                <table class="table table-sm table-bordered cal-pricing-rules-table mb-0">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Range</th>
                            <th>Default</th>
                            <th title="Monday">Mon</th>
                            <th title="Tuesday">Tue</th>
                            <th title="Wednesday">Wed</th>
                            <th title="Thursday">Thu</th>
                            <th title="Friday">Fri</th>
                            <th title="Saturday">Sat</th>
                            <th title="Sunday">Sun</th>
                            <th>Min</th>
                            <th style="width:90px">&nbsp;</th>
                        </tr>
                    </thead>
                    <tbody id="cal-rules-tbody">
                        @forelse($ranges ?? [] as $rg)
                        <tr data-rule-id="{{ $rg->id }}">
                            <td>{{ $rg->name_of_price ?: '—' }}</td>
                            <td>{{ $rg->start_date }} → {{ $rg->end_date }}</td>
                            <td>{{ $rg->price !== null ? '$'.number_format($rg->price,2) : '—' }}</td>
                            <td>{{ $rg->monday_price !== null ? '$'.$rg->monday_price : '—' }}</td>
                            <td>{{ $rg->tuesday_price !== null ? '$'.$rg->tuesday_price : '—' }}</td>
                            <td>{{ $rg->wednesday_price !== null ? '$'.$rg->wednesday_price : '—' }}</td>
                            <td>{{ $rg->thrusday_price !== null ? '$'.$rg->thrusday_price : '—' }}</td>
                            <td>{{ $rg->friday_price !== null ? '$'.$rg->friday_price : '—' }}</td>
                            <td>{{ $rg->saturday_price !== null ? '$'.$rg->saturday_price : '—' }}</td>
                            <td>{{ $rg->sunday_price !== null ? '$'.$rg->sunday_price : '—' }}</td>
                            <td>{{ $rg->min_stay ?? '—' }}</td>
                            <td>
                                <button type="button" class="btn btn-xs btn-outline-secondary cal-edit-rule" data-rule='@json($rg)'><i class="fa fa-pencil-alt"></i></button>
                                <button type="button" class="btn btn-xs btn-outline-danger cal-delete-rule" data-rule-id="{{ $rg->id }}"><i class="fa fa-trash"></i></button>
                            </td>
                        </tr>
                        @empty
                        <tr id="cal-rules-empty"><td colspan="12" class="text-center text-muted py-3">No pricing rules yet. Click <strong>Add rule</strong> to create one.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Calendar grid ---------------------------------------------------- --}}
    <div class="card mb-3">
        <div class="card-header py-2 d-flex align-items-center">
            <strong>Calendar</strong>
            <small class="text-muted ml-2">Click a day or drag-select a range to edit prices &amp; availability</small>
            <button type="button" class="btn btn-sm btn-outline-warning ml-auto" id="cal-bulk-clear-btn">
                <i class="fa fa-eraser"></i> Clear day overrides…
            </button>
        </div>
        <div class="card-body">
            <div id="calendar-tab-grid"></div>
        </div>
    </div>

    {{-- Reservations ----------------------------------------------------- --}}
    <div class="card mb-3">
        <div class="card-header py-2"><strong>Reservations</strong> <small class="text-muted" id="cal-reservations-count">…</small></div>
        <div class="card-body">
            <div id="cal-reservations" class="cal-loading">Loading…</div>
        </div>
    </div>

    {{-- iCal sync ------------------------------------------------------- --}}
    <div class="card mb-3">
        <div class="card-header py-2 d-flex align-items-center" data-toggle="collapse" data-target="#cal-sync-body" style="cursor:pointer">
            <strong>Calendar sync (iCal &amp; Excel import)</strong>
            <small class="text-muted ml-2">Connect external calendars or import an Excel price sheet</small>
            <i class="fa fa-chevron-down ml-auto"></i>
        </div>
        <div class="collapse show" id="cal-sync-body">
            <div class="card-body">
                <div class="cal-sync-section">

                    <div class="cal-sync-block">
                        <h6 class="mb-1">Imported feeds</h6>
                        <div id="cal-feeds-list" class="cal-loading">Loading…</div>
                    </div>

                    <div class="cal-sync-block">
                        <h6 class="mb-1">Add feed</h6>
                        <form id="cal-add-feed-form" class="mb-0">
                            <div class="form-group mb-2">
                                <input type="url" name="ical_link" class="form-control form-control-sm" placeholder="https://example.com/calendar.ics" required>
                            </div>
                            <div class="d-flex" style="gap:8px">
                                <input type="text" name="label" class="form-control form-control-sm" placeholder="Label (e.g. Airbnb)" style="flex:1;min-width:0">
                                <input type="color" name="color" class="form-control form-control-sm" value="#1e3a5f" style="height:31px;width:46px;padding:2px;flex-shrink:0">
                                <button type="submit" class="btn btn-sm btn-primary" style="flex-shrink:0">Add</button>
                            </div>
                        </form>
                    </div>

                    <div class="cal-sync-block">
                        <h6 class="mb-1">Export this calendar</h6>
                        <p class="small text-muted mb-2">Paste this URL into Airbnb / VRBO / Booking.com to sync outbound bookings.</p>
                        <div class="input-group input-group-sm">
                            <input type="text" class="form-control" id="cal-export-url" value="{{ asset('uploads/ical/' . sprintf('%06d', $data->id) . '.ics') }}" readonly>
                            <div class="input-group-append">
                                <button class="btn btn-outline-secondary" type="button" id="cal-copy-export-url" title="Copy"><i class="fa fa-copy"></i></button>
                                <button class="btn btn-outline-secondary" type="button" id="cal-regen-export" title="Regenerate"><i class="fa fa-sync"></i></button>
                            </div>
                        </div>
                    </div>

                    <div class="cal-sync-block">
                        <h6 class="mb-1">Import prices from Excel</h6>
                        <form id="cal-excel-import-form" enctype="multipart/form-data" class="mb-0">
                            <div class="form-group mb-2">
                                <input type="file" name="file" accept=".csv,.xls,.xlsx" class="form-control-file" required>
                                <small class="form-text text-muted">2 columns: <code>date</code> (m/d/Y), <code>price</code>. Header row skipped.</small>
                            </div>
                            <button type="submit" class="btn btn-sm btn-primary">Upload</button>
                        </form>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

{{-- Drawer ---------------------------------------------------------------- --}}
<div class="cal-drawer-backdrop" id="cal-drawer-backdrop"></div>
<div class="cal-drawer" id="cal-day-drawer">
    <div class="drawer-header">
        <div style="min-width:0;flex:1">
            <strong id="cal-drawer-title">Edit days</strong>
            <div class="form-inline mt-1" style="font-size:13px">
                <input type="date" id="cal-drawer-from" class="form-control form-control-sm mr-1" style="width:140px">
                <span class="mx-1 text-muted">→</span>
                <input type="date" id="cal-drawer-to" class="form-control form-control-sm" style="width:140px">
            </div>
            <div class="text-muted small mt-1" id="cal-drawer-subtitle"></div>
            <div class="text-muted" style="font-size:11px;margin-top:2px">Tip: Shift-click another day to extend the selection.</div>
        </div>
        <button type="button" class="close ml-2" id="cal-drawer-close" aria-label="Close">&times;</button>
    </div>
    <div class="drawer-body">
        <div class="meta" id="cal-drawer-meta"></div>
        <div id="cal-drawer-mixed" class="alert alert-info py-2 px-3 mb-2" style="display:none;font-size:12px"></div>
        <div id="cal-drawer-readonly" class="alert alert-warning py-2 px-3 mb-2" style="display:none;font-size:12px">
            This day has an active booking. Use the Reservations card or the booking record to make changes.
        </div>
        <form id="cal-drawer-form">
            <div class="form-group">
                <label>Price ($)</label>
                <input type="number" name="price" step="0.01" min="0" class="form-control" placeholder="leave blank to revert to rule">
            </div>
            <div class="form-group">
                <label>Minimum nights</label>
                <input type="number" name="min_stay" min="1" class="form-control" placeholder="leave blank to revert to rule">
            </div>
            <div class="form-group">
                <label>Availability</label>
                <div>
                    <label class="mr-3"><input type="radio" name="is_available" value="1"> Available</label>
                    <label><input type="radio" name="is_available" value="0"> Blocked</label>
                </div>
            </div>
            <div class="form-group">
                <label>Notes</label>
                <textarea name="notes" rows="2" class="form-control" placeholder="Internal note (not shown to guests)"></textarea>
            </div>
            <div class="d-flex">
                <button type="submit" class="btn btn-primary mr-2" id="cal-drawer-save">Save</button>
                <button type="button" class="btn btn-outline-secondary mr-2" id="cal-drawer-reset">Reset to default</button>
                <button type="button" class="btn btn-link ml-auto" id="cal-drawer-cancel">Cancel</button>
            </div>
        </form>
    </div>
</div>

{{-- Pricing rule modal ---------------------------------------------------- --}}
<div class="modal fade" id="cal-rule-modal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="cal-rule-form">
                <input type="hidden" name="id">
                <div class="modal-header py-2">
                    <h5 class="modal-title">Pricing rule</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 form-group"><label>Name</label><input type="text" name="name_of_price" class="form-control" maxlength="120" placeholder="e.g. Summer 2026"></div>
                        <div class="col-md-3 form-group"><label>Start date</label><input type="date" name="start_date" class="form-control" required></div>
                        <div class="col-md-3 form-group"><label>End date</label><input type="date" name="end_date" class="form-control" required></div>
                    </div>
                    <div class="row">
                        <div class="col-md-3 form-group"><label>Default price ($)</label><input type="number" name="price" step="0.01" min="0" class="form-control"></div>
                        <div class="col-md-3 form-group"><label>Min nights</label><input type="number" name="min_stay" min="1" class="form-control"></div>
                        <div class="col-md-6"></div>
                    </div>
                    <p class="text-muted small mb-2">Per-weekday overrides (optional — leave blank to use the default above):</p>
                    <div class="row">
                        <div class="col form-group"><label>Mon</label><input type="number" name="monday_price" step="0.01" class="form-control form-control-sm"></div>
                        <div class="col form-group"><label>Tue</label><input type="number" name="tuesday_price" step="0.01" class="form-control form-control-sm"></div>
                        <div class="col form-group"><label>Wed</label><input type="number" name="wednesday_price" step="0.01" class="form-control form-control-sm"></div>
                        <div class="col form-group"><label>Thu</label><input type="number" name="thrusday_price" step="0.01" class="form-control form-control-sm"></div>
                        <div class="col form-group"><label>Fri</label><input type="number" name="friday_price" step="0.01" class="form-control form-control-sm"></div>
                        <div class="col form-group"><label>Sat</label><input type="number" name="saturday_price" step="0.01" class="form-control form-control-sm"></div>
                        <div class="col form-group"><label>Sun</label><input type="number" name="sunday_price" step="0.01" class="form-control form-control-sm"></div>
                    </div>
                    <div class="row">
                        <div class="col-md-3 form-group"><label>Weekly discount %</label><input type="number" name="discount_weekly" step="0.01" min="0" max="100" class="form-control"></div>
                        <div class="col-md-3 form-group"><label>Monthly discount %</label><input type="number" name="discount_monthly" step="0.01" min="0" max="100" class="form-control"></div>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save rule</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Bulk-clear day-overrides modal ---------------------------------------- --}}
<div class="modal fade" id="cal-bulk-clear-modal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h5 class="modal-title">Clear day overrides</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <p class="text-muted small">Removes single-day overrides (custom price, blocked status, notes) inside the date range. Pricing rules and bookings are unaffected.</p>
                <div class="row">
                    <div class="col-6 form-group"><label>From</label><input type="date" id="cal-bulk-clear-from" class="form-control"></div>
                    <div class="col-6 form-group"><label>To</label><input type="date" id="cal-bulk-clear-to" class="form-control"></div>
                </div>
                <p class="small mb-2"><a href="#" id="cal-bulk-clear-all-future">Use all future overrides (today → +10y)</a></p>
                <div class="alert alert-info py-2 px-3 mb-0" id="cal-bulk-clear-preview">Pick a range to see how many overrides will be cleared.</div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="cal-bulk-clear-confirm" disabled>Clear overrides</button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var root = document.getElementById('cal-tab-root');
    if (!root) return;

    /* Portal modals + drawer to <body> so they escape any ancestor that
       has `transform`, `backdrop-filter`, or other properties that create
       a containing block for fixed-positioned descendants. Without this,
       Bootstrap modals get pushed behind the fixed topbar and the drawer
       lands relative to the studio container instead of the viewport. */
    ['cal-rule-modal', 'cal-bulk-clear-modal', 'cal-day-drawer', 'cal-drawer-backdrop'].forEach(function (id) {
        var el = document.getElementById(id);
        if (el && el.parentNode !== document.body) document.body.appendChild(el);
    });

    var propertyId = root.dataset.propertyId;
    var csrfToken = '{{ csrf_token() }}';
    var routes = {
        days:        '{{ url("admin/properties") }}/' + propertyId + '/calendar/days',
        bulkUpdate:  '{{ url("admin/properties") }}/' + propertyId + '/calendar/days/bulk-update',
        clearDays:   '{{ url("admin/properties") }}/' + propertyId + '/calendar/days',
        reservations:'{{ url("admin/properties") }}/' + propertyId + '/calendar/reservations',
        pricingRule: '{{ url("admin/properties") }}/' + propertyId + '/calendar/pricing-rule',
        feeds:       '{{ url("admin/properties") }}/' + propertyId + '/calendar/ical-feeds',
        regenExport: '{{ url("admin/properties") }}/' + propertyId + '/calendar/regenerate-export',
        importPrices:'{{ url("admin/properties") }}/' + propertyId + '/calendar/import-prices',
        defaults:    '{{ url("admin/properties") }}/' + propertyId + '/calendar/defaults',
        overridesCount: '{{ url("admin/properties") }}/' + propertyId + '/calendar/overrides-count',
        pricingRulesList: '{{ url("admin/properties") }}/' + propertyId + '/calendar/pricing-rules',
    };

    function fetchJson(url, options) {
        options = options || {};
        options.headers = Object.assign({
            'X-CSRF-TOKEN': csrfToken,
            'Accept': 'application/json',
        }, options.headers || {});
        if (options.body && !(options.body instanceof FormData)) {
            options.headers['Content-Type'] = 'application/json';
            options.body = JSON.stringify(options.body);
        }
        return fetch(url, options).then(function (r) {
            if (!r.ok) {
                return r.json().catch(function () { return {}; }).then(function (j) {
                    throw new Error(j.error || j.message || ('HTTP ' + r.status));
                });
            }
            return r.json();
        });
    }

    function fmtDate(d) { return d.toISOString().slice(0,10); }
    function priceFmt(v) { return v == null ? '—' : '$' + Number(v).toLocaleString(undefined, {minimumFractionDigits:0, maximumFractionDigits:2}); }

    // ====== Calendar grid =================================================
    var fcCal = null;
    var dayPayloadCache = {}; // key=YYYY-MM-DD → payload

    function ensureFullCalendar(cb) {
        // FullCalendar v5.10.1 is loaded synchronously in edit.blade.php (js section).
        if (window.FullCalendar) { cb(); return; }
        var el = document.getElementById('calendar-tab-grid');
        if (el) el.innerHTML = '<div class="alert alert-danger m-2">FullCalendar JS failed to load. Check that <code>admin-fullcalendar/main.min.js</code> exists at the htdocs root.</div>';
    }

    function escapeHtml(s) { var d=document.createElement('div'); d.textContent=s||''; return d.innerHTML; }

    function renderSlotHtml(p) {
        if (!p) return '';
        var html = '<div class="price">' + priceFmt(p.price) + '</div>';
        if (p.min_stay) html += '<div class="min-stay">min ' + p.min_stay + 'n</div>';
        if (p.status === 'booked') html += '<span class="badge-status">Booked</span>';
        else if (p.status === 'blocked') html += '<span class="badge-status">Blocked</span>';
        if (p.source_label && p.source !== 'default' && p.status === 'available') {
            html += '<div class="src-label">' + escapeHtml(p.source_label) + '</div>';
        }
        return html;
    }

    // Update each cell's slot from the cache. Slot DIVs are created at cell
    // mount time via dayCellDidMount (not here), so paintCells just rewrites
    // their innerHTML. This avoids any race with FC's initial render.
    function paintCells() {
        var slots = document.querySelectorAll('#calendar-tab-grid .cal-slot[data-date]');
        slots.forEach(function (slot) {
            var p = dayPayloadCache[slot.dataset.date];
            slot.innerHTML = renderSlotHtml(p);
            var cell = slot.closest('.fc-daygrid-day');
            if (!cell) return;
            cell.classList.remove('cal-status-booked', 'cal-status-blocked', 'cal-status-override', 'cal-status-weekend');
            cell.style.removeProperty('--cal-source-color');
            if (p) {
                if (p.status === 'booked') cell.classList.add('cal-status-booked');
                else if (p.status === 'blocked') cell.classList.add('cal-status-blocked');
                if (p.source === 'override') cell.classList.add('cal-status-override');
                else if (p.source === 'weekend') cell.classList.add('cal-status-weekend');
                // Apply iCal feed colour (or booking colour) — picked by admin in the
                // calendar feed-list. Falls back to the static .cal-status-blocked
                // background when no colour is set.
                if (p.source_color) {
                    cell.style.setProperty('--cal-source-color', p.source_color);
                }
            }
        });
    }

    function fetchVisibleRange(view) {
        var startStr = (view.activeStart || view.currentStart).toISOString().slice(0,10);
        var endStr = (view.activeEnd || view.currentEnd).toISOString().slice(0,10);
        return fetchJson(routes.days + '?from=' + startStr + '&to=' + endStr).then(function (j) {
            (j.days || []).forEach(function (d) { dayPayloadCache[d.date] = d; });
        });
    }

    function fetchAndPaint() {
        if (!fcCal) return;
        return fetchVisibleRange(fcCal.view).then(paintCells)
            .catch(function (e) { console.error('[calendar-tab] fetch failed', e); });
    }

    function buildCalendar() {
        var el = document.getElementById('calendar-tab-grid');
        fcCal = new FullCalendar.Calendar(el, {
            initialView: 'dayGridMonth',
            height: 'auto',
            headerToolbar: { left:'prev,next today', center:'title', right:'' },
            selectable: true,
            unselectAuto: false,
            fixedWeekCount: false,
            showNonCurrentDates: false,
            // Inject the slot div the moment FC creates each day cell. This
            // way the slot is always present in the DOM and paintCells can
            // patch its content asynchronously without racing FC's render.
            dayCellDidMount: function (arg) {
                var frame = arg.el.querySelector('.fc-daygrid-day-frame');
                if (!frame) return;
                if (frame.querySelector(':scope > .cal-slot')) return;
                var slot = document.createElement('div');
                slot.className = 'cal-slot';
                slot.dataset.date = arg.date.getFullYear() + '-' + String(arg.date.getMonth()+1).padStart(2,'0') + '-' + String(arg.date.getDate()).padStart(2,'0');
                frame.appendChild(slot);
                // Paint immediately if data already cached (covers post-init re-renders).
                var p = dayPayloadCache[slot.dataset.date];
                if (p) { slot.innerHTML = renderSlotHtml(p); }
            },
            datesSet: function () {
                // Close any open day-edit drawer when the visible month changes
                // — the previously-selected dates are no longer in view.
                if (drawer && drawer.classList.contains('open')) closeDrawer();
                fetchAndPaint();
            },
            select: function (info) {
                var to = new Date(info.end); to.setDate(to.getDate() - 1);
                drawerState.anchor = info.startStr;
                openDrawer(info.startStr, fmtDate(to));
            },
            dateClick: function (info) {
                if (info.jsEvent && info.jsEvent.shiftKey && drawerState.anchor) {
                    var a = drawerState.anchor, b = info.dateStr;
                    var from = a < b ? a : b, to = a < b ? b : a;
                    if (!drawer.classList.contains('open')) drawer.classList.add('open');
                    applyRange(from, to);
                } else {
                    drawerState.anchor = info.dateStr;
                    openDrawer(info.dateStr, info.dateStr);
                }
            },
        });
        fcCal.render();
        window.calendarTabFc = fcCal;
        // ResizeObserver is the canonical fix for the FC + Bootstrap-tab-fade
        // first-paint race: whenever the container's width settles (e.g. when
        // the .fade opacity transition completes and the pane finally takes its
        // final width), FC reflows. Without this, the user has to click or
        // open devtools — anything that triggers a layout recalc — to fix the
        // overlapping headers on the left side.
        if (window.ResizeObserver) {
            var lastW = 0;
            var ro = new ResizeObserver(function (entries) {
                var w = entries[0].contentRect.width;
                if (w > 0 && Math.abs(w - lastW) > 4) {
                    lastW = w;
                    if (fcCal) fcCal.updateSize();
                }
            });
            ro.observe(el);
        }
        // Belt-and-suspenders ladder for older browsers / edge timing.
        [50, 200, 600, 1200].forEach(function (t) {
            setTimeout(function () { fcCal && fcCal.updateSize(); }, t);
        });
    }

    function reflowCalendars() { if (fcCal) fcCal.updateSize(); }
    function refetchAll() {
        dayPayloadCache = {};
        fetchAndPaint();
    }

    // ====== Drawer ========================================================
    var drawer = document.getElementById('cal-day-drawer');
    var drawerBackdrop = document.getElementById('cal-drawer-backdrop');
    var drawerForm = document.getElementById('cal-drawer-form');
    var drawerState = { from: null, to: null, anchor: null };
    if (drawerBackdrop) {
        drawerBackdrop.addEventListener('click', function () {
            if (drawer && drawer.classList.contains('open')) {
                drawer.classList.remove('open');
                drawerBackdrop.classList.remove('open');
            }
        });
    }

    function eachDateInRange(from, to) {
        var out = [];
        var d = new Date(from + 'T00:00:00');
        var endD = new Date(to + 'T00:00:00');
        while (d <= endD) {
            out.push(d.getFullYear() + '-' + String(d.getMonth()+1).padStart(2,'0') + '-' + String(d.getDate()).padStart(2,'0'));
            d.setDate(d.getDate() + 1);
        }
        return out;
    }
    function fmtDateLong(iso) {
        var d = new Date(iso + 'T00:00:00');
        return d.toLocaleDateString('en-US', { weekday:'long', month:'long', day:'numeric', year:'numeric' });
    }
    function fmtDateShort(iso) {
        var d = new Date(iso + 'T00:00:00');
        return d.toLocaleDateString('en-US', { month:'short', day:'numeric' });
    }
    function setRadio(name, value) {
        var radios = drawerForm.querySelectorAll('input[type="radio"][name="' + name + '"]');
        radios.forEach(function (r) { r.checked = (r.value === value); });
    }

    function openDrawer(from, to) {
        drawer.classList.add('open');
        if (drawerBackdrop) drawerBackdrop.classList.add('open');
        applyRange(from, to);
    }

    function applyRange(from, to) {
        drawerState.from = from; drawerState.to = to;
        var dates = eachDateInRange(from, to);

        // Title + subtitle + the editable date inputs
        var title = (from === to) ? fmtDateLong(from) : (fmtDateShort(from) + ' → ' + fmtDateShort(to));
        document.getElementById('cal-drawer-title').textContent = title;
        document.getElementById('cal-drawer-subtitle').textContent = (dates.length === 1) ? '' : (dates.length + ' days selected');
        document.getElementById('cal-drawer-from').value = from;
        document.getElementById('cal-drawer-to').value = to;

        // Highlight the selected cells on the grid
        document.querySelectorAll('#calendar-tab-grid .cal-selected').forEach(function (el) { el.classList.remove('cal-selected'); });
        dates.forEach(function (d) {
            var slot = document.querySelector('#calendar-tab-grid .cal-slot[data-date="' + d + '"]');
            if (slot) slot.closest('.fc-daygrid-day').classList.add('cal-selected');
        });

        drawerForm.reset();
        document.getElementById('cal-drawer-mixed').style.display = 'none';
        document.getElementById('cal-drawer-readonly').style.display = 'none';

        // Snapshot of the first day's data for current-state strip
        var first = dayPayloadCache[from];
        var meta = '';
        if (first) {
            meta = 'Currently: <strong>' + priceFmt(first.price) + '</strong> · min ' + (first.min_stay || '—') + ' nights · ' + first.status;
            if (first.source_label) meta += ' · <span class="text-muted">' + escapeHtml(first.source_label) + '</span>';
        }
        document.getElementById('cal-drawer-meta').innerHTML = meta;

        // Booked? Block all form inputs.
        var anyBooked = dates.some(function (d) { var p = dayPayloadCache[d]; return p && p.status === 'booked'; });
        if (anyBooked) {
            document.getElementById('cal-drawer-readonly').style.display = '';
            ['price','min_stay','notes'].forEach(function (n) {
                var f = drawerForm.querySelector('[name="' + n + '"]'); if (f) f.disabled = true;
            });
            drawerForm.querySelectorAll('input[type="radio"]').forEach(function (r) { r.disabled = true; });
            document.getElementById('cal-drawer-save').disabled = true;
            document.getElementById('cal-drawer-reset').disabled = true;
        } else {
            ['price','min_stay','notes'].forEach(function (n) {
                var f = drawerForm.querySelector('[name="' + n + '"]'); if (f) f.disabled = false;
            });
            drawerForm.querySelectorAll('input[type="radio"]').forEach(function (r) { r.disabled = false; });
            document.getElementById('cal-drawer-save').disabled = false;
            document.getElementById('cal-drawer-reset').disabled = false;
        }

        // Always pre-fill from the resolved cache value so the user sees the
        // current state of each field. For ranges, only set the field if every
        // day shares the same value; otherwise leave blank + show a hint.
        var prices = []; var mins = []; var avails = []; var notes = [];
        dates.forEach(function (d) {
            var p = dayPayloadCache[d]; if (!p) return;
            prices.push(p.price != null && p.price !== '' ? Number(p.price) : null);
            mins.push(p.min_stay != null && p.min_stay !== '' ? Number(p.min_stay) : null);
            notes.push(p.notes || '');
            avails.push(p.is_available ? '1' : '0');
        });
        var allEqual = function (arr) { return arr.length && arr.every(function (v) { return v === arr[0]; }); };

        if (allEqual(prices) && prices[0] != null) drawerForm.querySelector('[name="price"]').value = prices[0];
        if (allEqual(mins) && mins[0] != null) drawerForm.querySelector('[name="min_stay"]').value = mins[0];
        if (allEqual(avails)) setRadio('is_available', avails[0] || '1');
        else setRadio('is_available', '1');
        if (allEqual(notes) && notes[0]) drawerForm.querySelector('[name="notes"]').value = notes[0];

        console.log('[calendar-tab] drawer opened with cache for', from, '→', dayPayloadCache[from]);

        // Mixed-range hint
        if (dates.length > 1) {
            var mixed = [];
            if (!allEqual(prices)) mixed.push('prices');
            if (!allEqual(mins)) mixed.push('min nights');
            if (!allEqual(avails)) mixed.push('availability');
            if (!allEqual(notes)) mixed.push('notes');
            if (mixed.length) {
                var box = document.getElementById('cal-drawer-mixed');
                box.textContent = 'Multiple values across selected days for: ' + mixed.join(', ') + '. Saving will overwrite all selected days.';
                box.style.display = '';
            }
        }
    }

    function onDrawerDateChange() {
        var f = document.getElementById('cal-drawer-from').value;
        var t = document.getElementById('cal-drawer-to').value;
        if (!f || !t) return;
        if (f > t) { var tmp = f; f = t; t = tmp; }
        applyRange(f, t);
    }
    document.getElementById('cal-drawer-from').addEventListener('change', onDrawerDateChange);
    document.getElementById('cal-drawer-to').addEventListener('change', onDrawerDateChange);

    function closeDrawer() {
        drawer.classList.remove('open');
        if (drawerBackdrop) drawerBackdrop.classList.remove('open');
        drawerState.from = null; drawerState.to = null; drawerState.anchor = null;
        document.getElementById('cal-drawer-from').value = '';
        document.getElementById('cal-drawer-to').value = '';
        document.querySelectorAll('#calendar-tab-grid .cal-selected').forEach(function (el) { el.classList.remove('cal-selected'); });
        if (fcCal) fcCal.unselect();
    }
    document.getElementById('cal-drawer-close').addEventListener('click', closeDrawer);
    document.getElementById('cal-drawer-cancel').addEventListener('click', closeDrawer);
    // ESC closes the drawer
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && drawer.classList.contains('open')) closeDrawer();
    });

    drawerForm.addEventListener('submit', function (e) {
        e.preventDefault();
        var fd = new FormData(drawerForm);
        var body = { from: drawerState.from, to: drawerState.to };
        if (fd.get('price') !== '' && fd.get('price') !== null) body.price = parseFloat(fd.get('price'));
        if (fd.get('min_stay') !== '' && fd.get('min_stay') !== null) body.min_stay = parseInt(fd.get('min_stay'),10);
        body.is_available = fd.get('is_available') === '1';
        body.notes = fd.get('notes') || '';
        fetchJson(routes.bulkUpdate, { method:'POST', body: body }).then(function () {
            refetchAll();
            closeDrawer();
        }).catch(function (err) { alert('Save failed: ' + err.message); });
    });

    document.getElementById('cal-drawer-reset').addEventListener('click', function () {
        if (!confirm('Remove day overrides in this range and revert to pricing rules?')) return;
        var url = routes.clearDays + '?from=' + drawerState.from + '&to=' + drawerState.to;
        fetchJson(url, { method:'DELETE' }).then(function () {
            refetchAll();
            closeDrawer();
        }).catch(function (err) { alert('Reset failed: ' + err.message); });
    });

    // ====== Pricing rules =================================================
    var ruleModal = document.getElementById('cal-rule-modal');
    var ruleForm = document.getElementById('cal-rule-form');

    function showRuleModal(rule) {
        ruleForm.reset();
        ruleForm.querySelector('[name="id"]').value = rule ? rule.id : '';
        if (rule) {
            ['name_of_price','start_date','end_date','price','min_stay',
             'monday_price','tuesday_price','wednesday_price','thrusday_price',
             'friday_price','saturday_price','sunday_price','discount_weekly','discount_monthly'
            ].forEach(function (f) {
                var el = ruleForm.querySelector('[name="'+f+'"]');
                if (el && rule[f] != null) el.value = rule[f];
            });
        }
        window.jQuery(ruleModal).modal('show');
    }
    document.getElementById('cal-add-rule-btn').addEventListener('click', function () { showRuleModal(null); });

    document.getElementById('cal-rules-tbody').addEventListener('click', function (e) {
        var editBtn = e.target.closest('.cal-edit-rule');
        var delBtn = e.target.closest('.cal-delete-rule');
        if (editBtn) { showRuleModal(JSON.parse(editBtn.dataset.rule)); }
        else if (delBtn) {
            if (!confirm('Delete this pricing rule? Day overrides linked to it will also be removed.')) return;
            fetchJson(routes.pricingRule + '/' + delBtn.dataset.ruleId, { method:'DELETE' })
                .then(function () { reloadRulesTable(); refetchAll(); })
                .catch(function (err) { alert('Delete failed: ' + err.message); });
        }
    });

    ruleForm.addEventListener('submit', function (e) {
        e.preventDefault();
        var fd = new FormData(ruleForm);
        var body = {};
        fd.forEach(function (v, k) { if (v !== '' && k !== 'id') body[k] = v; });
        var id = fd.get('id');
        var url = id ? (routes.pricingRule + '/' + id) : routes.pricingRule;
        var method = id ? 'PUT' : 'POST';
        fetchJson(url, { method: method, body: body })
            .then(function () { window.jQuery(ruleModal).modal('hide'); reloadRulesTable(); refetchAll(); })
            .catch(function (err) { alert('Save failed: ' + err.message); });
    });

    /* AJAX-only refresh of the rules table — no page reload. Fetches the
       JSON list and re-renders the tbody. */
    function reloadRulesTable() {
        var tbody = document.getElementById('cal-rules-tbody');
        if (!tbody) return Promise.resolve();
        return fetchJson(routes.pricingRulesList).then(function (resp) {
            var rules = (resp && resp.rules) ? resp.rules : [];
            if (!rules.length) {
                tbody.innerHTML = '<tr id="cal-rules-empty"><td colspan="12" class="text-center text-muted py-3">No pricing rules yet. Click <strong>Add rule</strong> to create one.</td></tr>';
                return;
            }
            var fmt = function (v, prefix) {
                if (v === null || v === undefined || v === '') return '—';
                return prefix ? (prefix + v) : v;
            };
            var esc = function (s) {
                return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
                    return { '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c];
                });
            };
            var html = '';
            rules.forEach(function (r) {
                html += '<tr data-rule-id="' + r.id + '">'
                     +     '<td>' + esc(r.name_of_price || '—') + '</td>'
                     +     '<td>' + esc(r.start_date) + ' → ' + esc(r.end_date) + '</td>'
                     +     '<td>' + (r.price !== null ? '$' + parseFloat(r.price).toFixed(2) : '—') + '</td>'
                     +     '<td>' + fmt(r.monday_price, '$') + '</td>'
                     +     '<td>' + fmt(r.tuesday_price, '$') + '</td>'
                     +     '<td>' + fmt(r.wednesday_price, '$') + '</td>'
                     +     '<td>' + fmt(r.thrusday_price, '$') + '</td>'
                     +     '<td>' + fmt(r.friday_price, '$') + '</td>'
                     +     '<td>' + fmt(r.saturday_price, '$') + '</td>'
                     +     '<td>' + fmt(r.sunday_price, '$') + '</td>'
                     +     '<td>' + (r.min_stay == null ? '—' : r.min_stay) + '</td>'
                     +     '<td>'
                     +         '<button type="button" class="btn btn-xs btn-outline-secondary cal-edit-rule" data-rule=\'' + esc(JSON.stringify(r)) + '\'><i class="fa fa-pencil-alt"></i></button> '
                     +         '<button type="button" class="btn btn-xs btn-outline-danger cal-delete-rule" data-rule-id="' + r.id + '"><i class="fa fa-trash"></i></button>'
                     +     '</td>'
                     + '</tr>';
            });
            tbody.innerHTML = html;
        }).catch(function (err) {
            console.warn('Failed to refresh rules', err);
        });
    }

    // ====== iCal feeds ====================================================
    function renderFeeds(list) {
        var c = document.getElementById('cal-feeds-list');
        if (!list.length) { c.innerHTML = '<div class="text-muted small">No feeds connected yet.</div>'; updateLastRefreshStrip(null); return; }
        c.classList.remove('cal-loading');
        c.innerHTML = list.map(function (f) {
            var color = f.color || '#6c757d';
            var when = f.last_refreshed_human || 'never';
            return '<div class="cal-feed-row" data-feed-id="'+f.id+'">'
                + '<span class="feed-color" style="background:'+color+'"></span>'
                + '<div class="feed-meta">'
                + '<strong>'+escapeHtml(f.label || 'Untitled feed')+'</strong>'
                + '<small>'+escapeHtml(f.ical_link)+'</small>'
                + '<small>Last refreshed: '+when+'</small>'
                + (f.last_error ? '<div class="feed-error"><i class="fa fa-exclamation-triangle"></i> '+escapeHtml(f.last_error.substring(0,160))+'</div>' : '')
                + '</div>'
                + '<button type="button" class="btn btn-xs btn-outline-secondary cal-refresh-feed"><i class="fa fa-sync"></i></button>'
                + '<button type="button" class="btn btn-xs btn-outline-danger cal-delete-feed"><i class="fa fa-trash"></i></button>'
                + '</div>';
        }).join('');
        var mostRecent = list.map(function (f) { return f.last_refreshed_at; }).filter(Boolean).sort().pop();
        updateLastRefreshStrip(mostRecent ? list.find(function (f) { return f.last_refreshed_at === mostRecent; }).last_refreshed_human : null);
    }

    function updateLastRefreshStrip(human) {
        var el = document.getElementById('cal-last-refresh-strip');
        el.textContent = human ? ('Last auto-refresh: ' + human) : 'No iCal feeds connected';
    }

    function loadFeeds() {
        return fetchJson(routes.feeds).then(function (j) { renderFeeds(j.feeds || []); });
    }

    document.getElementById('cal-feeds-list').addEventListener('click', function (e) {
        var row = e.target.closest('.cal-feed-row'); if (!row) return;
        var id = row.dataset.feedId;
        if (e.target.closest('.cal-refresh-feed')) {
            e.target.closest('button').disabled = true;
            fetchJson(routes.feeds + '/' + id + '/refresh', { method:'POST' })
                .then(function () { return loadFeeds(); })
                .then(refetchAll)
                .catch(function (err) { alert('Refresh failed: ' + err.message); });
        } else if (e.target.closest('.cal-delete-feed')) {
            if (!confirm('Disconnect this iCal feed and remove all blocks it imported?')) return;
            fetchJson(routes.feeds + '/' + id, { method:'DELETE' })
                .then(function () { return loadFeeds(); })
                .then(refetchAll)
                .catch(function (err) { alert('Delete failed: ' + err.message); });
        }
    });

    document.getElementById('cal-add-feed-form').addEventListener('submit', function (e) {
        e.preventDefault();
        var fd = new FormData(this);
        var body = { ical_link: fd.get('ical_link'), label: fd.get('label'), color: fd.get('color') };
        fetchJson(routes.feeds, { method:'POST', body: body })
            .then(function () { e.target.reset(); return loadFeeds(); })
            .then(refetchAll)
            .catch(function (err) { alert('Add failed: ' + err.message); });
    });

    document.getElementById('cal-refresh-all-btn').addEventListener('click', function () {
        var btn = this; btn.disabled = true;
        fetchJson(routes.feeds).then(function (j) {
            var feeds = j.feeds || [];
            return Promise.all(feeds.map(function (f) {
                return fetchJson(routes.feeds + '/' + f.id + '/refresh', { method:'POST' });
            }));
        }).then(function () { return loadFeeds(); })
          .then(function () { refetchAll(); btn.disabled = false; })
          .catch(function (err) { alert('Refresh failed: ' + err.message); btn.disabled = false; });
    });

    document.getElementById('cal-copy-export-url').addEventListener('click', function () {
        var inp = document.getElementById('cal-export-url');
        inp.select(); document.execCommand('copy');
        var btn = this; btn.classList.add('btn-success'); btn.classList.remove('btn-outline-secondary');
        setTimeout(function () { btn.classList.remove('btn-success'); btn.classList.add('btn-outline-secondary'); }, 1200);
    });

    document.getElementById('cal-regen-export').addEventListener('click', function () {
        this.disabled = true;
        var that = this;
        fetchJson(routes.regenExport, { method:'POST' })
            .then(function (j) { document.getElementById('cal-export-url').value = j.url; that.disabled = false; })
            .catch(function (err) { alert('Regenerate failed: ' + err.message); that.disabled = false; });
    });

    document.getElementById('cal-excel-import-form').addEventListener('submit', function (e) {
        e.preventDefault();
        var fd = new FormData(this);
        fetchJson(routes.importPrices, { method:'POST', body: fd })
            .then(function (j) { alert('Imported ' + j.imported + ' rows.'); e.target.reset(); refetchAll(); })
            .catch(function (err) { alert('Import failed: ' + err.message); });
    });

    // ====== Default pricing (property.standard_rate + property.min_stay) ===
    var defaultsSaveBtn = document.getElementById('cal-defaults-save');
    if (defaultsSaveBtn) {
        defaultsSaveBtn.addEventListener('click', function () {
            var rate = document.getElementById('cal-defaults-standard-rate').value;
            var minStay = document.getElementById('cal-defaults-min-stay').value;
            var weekendRate = document.getElementById('cal-defaults-weekend-rate').value;
            var weekendDays = Array.from(document.querySelectorAll('.cal-weekend-day:checked'))
                .map(function (el) { return el.value; }).join(',');
            var statusEl = document.getElementById('cal-defaults-status');
            statusEl.textContent = 'Saving…';
            fetchJson(routes.defaults, { method: 'POST', body: {
                standard_rate: rate,
                min_stay: minStay,
                weekend_rate: weekendRate,
                weekend_days: weekendDays,
            } }).then(function () {
                statusEl.textContent = 'Saved ✓';
                setTimeout(function () { statusEl.textContent = ''; }, 2000);
                refetchAll();
            }).catch(function (err) {
                statusEl.textContent = '';
                alert('Save failed: ' + err.message);
            });
        });
    }

    // ====== Bulk-clear day overrides ======================================
    function refreshBulkClearPreview() {
        var f = document.getElementById('cal-bulk-clear-from').value;
        var t = document.getElementById('cal-bulk-clear-to').value;
        var btn = document.getElementById('cal-bulk-clear-confirm');
        var box = document.getElementById('cal-bulk-clear-preview');
        if (!f || !t || f > t) {
            box.textContent = 'Pick a valid date range.';
            btn.disabled = true; return;
        }
        box.textContent = 'Counting…';
        fetchJson(routes.overridesCount + '?from=' + f + '&to=' + t).then(function (j) {
            box.textContent = 'This will remove ' + j.count + ' single-day override' + (j.count === 1 ? '' : 's') + '.';
            btn.disabled = (j.count === 0);
        }).catch(function (e) {
            box.textContent = 'Failed to count: ' + e.message;
            btn.disabled = true;
        });
    }

    var bulkClearBtn = document.getElementById('cal-bulk-clear-btn');
    if (bulkClearBtn) {
        bulkClearBtn.addEventListener('click', function () {
            var today = new Date();
            var year = new Date(); year.setDate(year.getDate() + 365);
            document.getElementById('cal-bulk-clear-from').value = today.toISOString().slice(0,10);
            document.getElementById('cal-bulk-clear-to').value = year.toISOString().slice(0,10);
            refreshBulkClearPreview();
            window.jQuery('#cal-bulk-clear-modal').modal('show');
        });
        document.getElementById('cal-bulk-clear-from').addEventListener('change', refreshBulkClearPreview);
        document.getElementById('cal-bulk-clear-to').addEventListener('change', refreshBulkClearPreview);
        document.getElementById('cal-bulk-clear-all-future').addEventListener('click', function (e) {
            e.preventDefault();
            var today = new Date();
            var far = new Date(); far.setFullYear(far.getFullYear() + 10);
            document.getElementById('cal-bulk-clear-from').value = today.toISOString().slice(0,10);
            document.getElementById('cal-bulk-clear-to').value = far.toISOString().slice(0,10);
            refreshBulkClearPreview();
        });
        document.getElementById('cal-bulk-clear-confirm').addEventListener('click', function () {
            var f = document.getElementById('cal-bulk-clear-from').value;
            var t = document.getElementById('cal-bulk-clear-to').value;
            var url = routes.clearDays + '?from=' + f + '&to=' + t;
            fetchJson(url, { method: 'DELETE' }).then(function (j) {
                window.jQuery('#cal-bulk-clear-modal').modal('hide');
                alert('Cleared ' + j.deleted + ' override' + (j.deleted === 1 ? '' : 's') + '.');
                refetchAll();
            }).catch(function (e) { alert('Clear failed: ' + e.message); });
        });
    }

    // ====== Reservations ==================================================
    function loadReservations() {
        fetchJson(routes.reservations).then(function (j) {
            var c = document.getElementById('cal-reservations');
            var bookings = j.bookings || [], ical = j.ical || [];
            document.getElementById('cal-reservations-count').textContent = '(' + (bookings.length + ical.length) + ' upcoming + recent)';
            if (!bookings.length && !ical.length) { c.classList.remove('cal-loading'); c.innerHTML = '<div class="text-muted">No reservations.</div>'; return; }
            var html = '<table class="table table-sm table-hover mb-0"><thead><tr><th>Source</th><th>Guest / summary</th><th>Check-in</th><th>Check-out</th><th>Total</th><th></th></tr></thead><tbody>';
            bookings.forEach(function (b) {
                html += '<tr><td><span class="badge badge-success">Booking</span></td><td>'+escapeHtml(b.guest||'(unnamed)')+'<br><small class="text-muted">'+escapeHtml(b.email||'')+'</small></td><td>'+b.checkin+'</td><td>'+b.checkout+'</td><td>'+priceFmt(b.total)+'</td><td><a href="{{ url("admin/booking-enquiries") }}/'+b.id+'/agreement.pdf" class="btn btn-xs btn-outline-secondary">PDF</a></td></tr>';
            });
            ical.forEach(function (e) {
                html += '<tr><td><span class="badge badge-warning">iCal</span></td><td>'+escapeHtml(e.summary||'')+'</td><td>'+e.checkin+'</td><td>'+e.checkout+'</td><td>—</td><td></td></tr>';
            });
            html += '</tbody></table>';
            c.classList.remove('cal-loading'); c.innerHTML = html;
        }).catch(function (err) {
            document.getElementById('cal-reservations').innerHTML = '<div class="text-danger">Failed to load: '+escapeHtml(err.message)+'</div>';
        });
    }

    // ====== Boot ==========================================================
    var booted = false;
    function bootOnce() {
        if (booted) return; booted = true;
        console.log('[calendar-tab] booting');
        ensureFullCalendar(function () {
            console.log('[calendar-tab] FullCalendar present, building grid');
            try {
                buildCalendar();
                loadFeeds();
                loadReservations();
            } catch (e) {
                console.error('[calendar-tab] buildCalendar threw', e);
                var el = document.getElementById('calendar-tab-grid');
                if (el) el.innerHTML = '<div class="alert alert-danger m-2">Calendar failed to render: ' + escapeHtml(e.message) + '</div>';
            }
        });
    }

    // Boot lazily — only when the Calendar tab's container has settled to a
    // non-zero width. Bootstrap 4's .fade opacity transition leaves the pane
    // measurable (offsetWidth > 0) earlier than the layout actually settles,
    // so we rAF-poll instead of using a fixed timeout. Once a positive width
    // shows up we boot; the ResizeObserver inside buildCalendar handles any
    // width changes after that.
    function deferredBoot() {
        var el = document.getElementById('calendar-tab-grid');
        var attempts = 0;
        function tryBoot() {
            attempts++;
            if (el && el.offsetWidth > 0) {
                bootOnce();
                // Final reflow ladder for any lingering layout shifts the RO doesn't catch.
                [100, 400, 900].forEach(function (t) { setTimeout(reflowCalendars, t); });
            } else if (attempts < 60) {
                // ~1 second of rAF polling at 60Hz before giving up.
                requestAnimationFrame(tryBoot);
            } else {
                bootOnce();
            }
        }
        requestAnimationFrame(tryBoot);
    }

    if (window.jQuery) {
        jQuery(document).ready(function () {
            var pane = document.getElementById('custom-tabs-three-calendar');
            if (pane && pane.classList.contains('active')) deferredBoot();
            jQuery('#custom-tabs-three-calendar-tab').on('shown.bs.tab', deferredBoot);
        });
    } else {
        document.addEventListener('DOMContentLoaded', deferredBoot);
    }
})();
</script>
@endisset
