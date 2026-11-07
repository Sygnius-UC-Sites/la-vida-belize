@extends('admin.layouts')
@section('title', 'Testimonials')
@php
$name="Testimonials";$route="testimonials";
@endphp

@section('css')
@parent
<style>
/* Phase 13O — testimonials index, scoped to body.tm-page so it can't leak. */
body.tm-page { background: #f7f8fb; font-family: "Inter", -apple-system, BlinkMacSystemFont, sans-serif !important; }
body.tm-page .content-wrapper { background: #f7f8fb !important; }
body.tm-page h1 { font-size: 22px !important; font-weight: 700 !important; letter-spacing: -.018em !important; color: #0b1220 !important; }
body.tm-page .tm-card { background: #fff; border: 1px solid #e6e8ee; border-radius: 10px; box-shadow: 0 1px 2px rgba(15,23,42,.05); margin: 0 0 18px 0; }
body.tm-page .tm-filter { display: flex; gap: 10px; padding: 14px 18px; border-bottom: 1px solid #e6e8ee; flex-wrap: wrap; align-items: center; }
body.tm-page .tm-filter input, body.tm-page .tm-filter select { border: 1px solid #c7ccd6; border-radius: 6px; padding: 0 12px; height: 38px; font-size: 14px; background: #fff; color: #0b1220; }
body.tm-page .tm-filter input:focus, body.tm-page .tm-filter select:focus { outline: 0; border-color: #1e293b; box-shadow: 0 0 0 3px #eef2f7; }
body.tm-page .tm-filter input[type=text] { width: 240px; }
body.tm-page .tm-reset { background: transparent; border: 1px solid #c7ccd6; color: #2c3447; padding: 0 16px; height: 38px; border-radius: 6px; font-size: 13px; cursor: pointer; }
body.tm-page .tm-table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 14px; }
body.tm-page .tm-table th { background: #f7f8fb; color: #5b6473; font-size: 11px !important; text-transform: uppercase; letter-spacing: .06em; padding: 12px 16px !important; text-align: left; border-bottom: 1px solid #e6e8ee; font-weight: 600; }
body.tm-page .tm-table td { padding: 14px 16px !important; border-bottom: 1px solid #e6e8ee; vertical-align: middle; color: #0b1220; }
body.tm-page .tm-table tbody tr:last-child td { border-bottom: 0; }
body.tm-page .tm-table tbody tr:hover { background: #fafbfd; }
body.tm-page .tm-name { font-weight: 600; font-size: 15px; }
body.tm-page .tm-meta { color: #5b6473; font-size: 12px; margin-top: 2px; }
body.tm-page .tm-stars { color: #f59e0b; font-size: 16px; letter-spacing: 1px; white-space: nowrap; }
body.tm-page .tm-stars--empty { color: #cbd5e1; }
body.tm-page .tm-pill { display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; line-height: 1.4; }
body.tm-page .tm-pill::before { content: ""; width: 6px; height: 6px; border-radius: 50%; background: currentColor; opacity: .8; }
body.tm-page .tm-pill--published { background: #d1fae5; color: #047857; }
body.tm-page .tm-pill--draft     { background: #fef3c7; color: #b45309; }
body.tm-page .tm-pill--unknown   { background: #f1f5f9; color: #64748b; }
body.tm-page .tm-home-chip { display: inline-block; background: #eef0ff; color: #4338ca; font-size: 11px; font-weight: 600; padding: 2px 7px; border-radius: 999px; margin-left: 4px; }
body.tm-page .tm-snippet { color: #5b6473; font-size: 12px; line-height: 1.4; margin-top: 4px; max-width: 380px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
body.tm-page .tm-actions { position: relative; text-align: right; }
body.tm-page .tm-action-btn { background: transparent; border: 1px solid #e6e8ee; color: #5b6473; width: 32px; height: 32px; border-radius: 6px; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; }
body.tm-page .tm-action-btn:hover { background: #f8fafc; color: #0b1220; border-color: #c7ccd6; }
body.tm-page .tm-action-menu { position: absolute; right: 0; top: 100%; margin-top: 4px; background: #fff; border: 1px solid #e6e8ee; border-radius: 10px; box-shadow: 0 10px 28px rgba(15,23,42,.10); min-width: 200px; z-index: 50; padding: 4px; display: none; }
body.tm-page .tm-action-menu.show { display: block; }
body.tm-page .tm-action-menu a, body.tm-page .tm-action-menu button { display: flex; align-items: center; gap: 10px; width: 100%; text-align: left; padding: 9px 12px; background: transparent; border: 0; color: #0b1220; font-size: 14px; text-decoration: none; cursor: pointer; border-radius: 6px; }
body.tm-page .tm-action-menu a:hover, body.tm-page .tm-action-menu button:hover { background: #f7f8fb; text-decoration: none; }
body.tm-page .tm-action-menu .tm-action-divider { height: 1px; background: #e6e8ee; margin: 4px 2px; }
body.tm-page .tm-action-menu .tm-danger { color: #b42318 !important; }
body.tm-page .tm-action-menu .tm-danger:hover { background: #fef0ee; }
body.tm-page .tm-add-btn { background: #1e293b; color: #fff; border: 0; padding: 8px 16px; border-radius: 6px; font-weight: 600; font-size: 14px; text-decoration: none; display: inline-flex; align-items: center; gap: 7px; }
body.tm-page .tm-add-btn:hover { background: #0f172a; color: #fff; text-decoration: none; }
body.tm-page .tm-empty { text-align: center; padding: 60px 20px; color: #5b6473; }
body.tm-page .tm-table tbody tr:last-child .tm-action-menu, body.tm-page .tm-table tbody tr:nth-last-child(2) .tm-action-menu { top: auto; bottom: 100%; margin-top: 0; margin-bottom: 4px; }
</style>
@stop

@section('content_header')
<section class="content-header" style="padding: 18px 24px;">
    <div class="d-flex align-items-center" style="gap: 14px; flex-wrap: wrap;">
        <h1 class="m-0">{{$name}}</h1>
        <span class="tm-pill tm-pill--unknown" id="tm-count">{{ count($data) }} total</span>
        <a href="{{ route($route.'.create') }}" class="tm-add-btn" style="margin-left:auto;"><i class="fa fa-plus"></i> Add testimonial</a>
    </div>
</section>
@stop

@section('content')
@parent
<script>document.body.classList.add('tm-page');</script>

<section class="content" style="padding: 0 24px;">
    <div class="tm-card">
        <div class="tm-filter">
            <input type="text" id="tm-filter-keyword" placeholder="Search by name, email, or message…">
            <select id="tm-filter-status">
                <option value="">All statuses</option>
                <option value="true">Published</option>
                <option value="false">Draft</option>
            </select>
            <select id="tm-filter-home">
                <option value="">All</option>
                <option value="true">Shown on home</option>
                <option value="false">Not on home</option>
            </select>
            <button type="button" class="tm-reset" id="tm-filter-reset">Reset</button>
        </div>

        @if(count($data) === 0)
            <div class="tm-empty">
                <p style="font-weight:600;color:#0b1220;font-size:18px;margin:0 0 4px 0;">No testimonials yet</p>
                <p style="margin:0 0 16px 0;">Add your first review to start building social proof on the home page.</p>
                <a href="{{ route($route.'.create') }}" class="tm-add-btn"><i class="fa fa-plus"></i> Add testimonial</a>
            </div>
        @else
        <table class="tm-table" id="tm-table">
            <thead>
                <tr>
                    <th>Reviewer</th>
                    <th style="width:140px">Rating</th>
                    <th>Property</th>
                    <th style="width:160px">Stay</th>
                    <th style="width:140px">Status</th>
                    <th style="width:60px"></th>
                </tr>
            </thead>
            <tbody>
                @foreach($data as $client)
                @php
                    $rating  = (int)($client->rating ?? $client->score ?? 0);
                    $status  = $client->status === 'true' ? 'published' : 'draft';
                    $isHome  = $client->is_home === 'true';
                    $propName = App\Models\Property::find($client->property_id)->name ?? null;
                @endphp
                <tr data-id="{{ $client->id }}"
                    data-search="{{ Str::lower($client->name . ' ' . ($client->email ?? '') . ' ' . ($client->message ?? '')) }}"
                    data-status="{{ $client->status }}"
                    data-home="{{ $client->is_home }}">

                    <td>
                        <div class="tm-name">{{ $client->name }}</div>
                        @if($client->email) <div class="tm-meta">{{ $client->email }}</div> @endif
                        @if($client->message) <div class="tm-snippet">"{{ Str::limit($client->message, 140) }}"</div> @endif
                    </td>
                    <td>
                        @if($rating > 0)
                            <span class="tm-stars">@for($i=1;$i<=5;$i++){{ $i <= $rating ? '★' : '☆' }}@endfor</span>
                            <div class="tm-meta">{{ $rating }}/5</div>
                        @else
                            <span class="tm-stars tm-stars--empty">☆☆☆☆☆</span>
                            <div class="tm-meta">No rating</div>
                        @endif
                    </td>
                    <td>
                        @if($propName)
                            <div class="tm-name" style="font-size:14px;">{{ $propName }}</div>
                        @else
                            <span class="tm-meta">— Generic —</span>
                        @endif
                        @if($client->booking_id)
                            <div class="tm-meta"><a href="{{ url('admin/booking-enquiries/' . $client->booking_id . '/edit') }}">Booking #{{ $client->booking_id }}</a></div>
                        @endif
                    </td>
                    <td>
                        @if($client->stay_date)
                            <div class="tm-meta">{{ $client->stay_date }}@if($client->end_date) → {{ $client->end_date }}@endif</div>
                        @else
                            <span class="tm-meta">—</span>
                        @endif
                    </td>
                    <td>
                        <span class="tm-pill tm-pill--{{ $status }}">{{ ucfirst($status) }}</span>
                        @if($isHome) <span class="tm-home-chip" title="Featured on home page">★ Home</span> @endif
                    </td>
                    <td class="tm-actions">
                        <button type="button" class="tm-action-btn tm-menu-toggle" aria-label="Actions"><i class="fa fa-ellipsis-v"></i></button>
                        <div class="tm-action-menu">
                            <a href="{{ route($route.'.edit', $client->id) }}"><i class="fa fa-pencil-alt"></i> Edit</a>
                            <a href="{{ route($route.'.copyData', $client->id) }}"><i class="fa fa-copy"></i> Duplicate</a>
                            <div class="tm-action-divider"></div>
                            <form method="POST" action="{{ route($route.'.destroy', $client->id) }}" id="tm-delete-form-{{ $client->id }}" style="display:none">@csrf @method('DELETE')</form>
                            <button type="button" class="tm-danger tm-delete-btn" data-id="{{ $client->id }}" data-name="{{ $client->name }}"><i class="fa fa-trash"></i> Delete</button>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        <div class="tm-empty" id="tm-empty-filtered" style="display:none;">
            <p style="font-weight:600;color:#0b1220;font-size:16px;margin:0 0 4px 0;">No matches</p>
            <p style="margin:0;">Try clearing the filters.</p>
        </div>
        @endif
    </div>
</section>
@stop

@section('js')
@parent
<script>
(function () {
    var FILTER_KEY = 'tm_admin_filters';
    var $kw  = document.getElementById('tm-filter-keyword');
    var $st  = document.getElementById('tm-filter-status');
    var $hm  = document.getElementById('tm-filter-home');
    var $rst = document.getElementById('tm-filter-reset');
    var $cnt = document.getElementById('tm-count');
    var $tbl = document.getElementById('tm-table');
    var $emp = document.getElementById('tm-empty-filtered');
    if (!$tbl) return;
    var rows = Array.prototype.slice.call($tbl.querySelectorAll('tbody tr'));
    var total = rows.length;

    function loadFilters() { try { var f = JSON.parse(localStorage.getItem(FILTER_KEY) || '{}'); if($kw)$kw.value=f.kw||''; if($st)$st.value=f.st||''; if($hm)$hm.value=f.hm||''; } catch (_) {} }
    function persistFilters() { try { localStorage.setItem(FILTER_KEY, JSON.stringify({ kw: $kw?$kw.value:'', st: $st?$st.value:'', hm: $hm?$hm.value:'' })); } catch (_) {} }
    function applyFilters() {
        var kw = ($kw ? $kw.value : '').trim().toLowerCase();
        var st = $st ? $st.value : '';
        var hm = $hm ? $hm.value : '';
        var visible = 0;
        rows.forEach(function (r) {
            var ok = (!kw || r.dataset.search.indexOf(kw) !== -1)
                  && (!st || r.dataset.status === st)
                  && (!hm || r.dataset.home === hm);
            r.style.display = ok ? '' : 'none';
            if (ok) visible++;
        });
        if ($cnt) $cnt.textContent = visible === total ? (total + ' total') : (visible + ' of ' + total);
        if ($emp) $emp.style.display = (visible === 0 ? 'block' : 'none');
        persistFilters();
    }
    loadFilters(); applyFilters();
    if ($kw) $kw.addEventListener('input', applyFilters);
    if ($st) $st.addEventListener('change', applyFilters);
    if ($hm) $hm.addEventListener('change', applyFilters);
    if ($rst) $rst.addEventListener('click', function () { if($kw)$kw.value=''; if($st)$st.value=''; if($hm)$hm.value=''; applyFilters(); });

    document.querySelectorAll('.tm-menu-toggle').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            var menu = btn.nextElementSibling;
            var open = menu.classList.contains('show');
            document.querySelectorAll('.tm-action-menu.show').forEach(function (m) { m.classList.remove('show'); });
            if (!open) menu.classList.add('show');
        });
    });
    document.addEventListener('click', function () { document.querySelectorAll('.tm-action-menu.show').forEach(function (m) { m.classList.remove('show'); }); });

    document.querySelectorAll('.tm-delete-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var id = btn.getAttribute('data-id');
            var name = btn.getAttribute('data-name');
            Swal.fire({
                title: 'Delete testimonial from "' + name + '"?',
                text: 'This action cannot be undone.',
                icon: 'warning', showCancelButton: true,
                confirmButtonColor: '#b42318', confirmButtonText: 'Yes, delete it',
            }).then(function (r) { if (r.value) document.getElementById('tm-delete-form-' + id).submit(); });
        });
    });
})();
</script>
@stop
