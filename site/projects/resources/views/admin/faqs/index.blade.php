@extends('admin.layouts')
@section('title', 'FAQs')
@php
$name="FAQs";$route="faqs";
@endphp

@section('css')
@parent
<style>
body.fq-page { background: #f7f8fb; font-family: "Inter", -apple-system, BlinkMacSystemFont, sans-serif !important; }
body.fq-page .content-wrapper { background: #f7f8fb !important; }
body.fq-page h1 { font-size: 22px !important; font-weight: 700 !important; letter-spacing: -.018em !important; color: #0b1220 !important; }
body.fq-page .fq-card { background: #fff; border: 1px solid #e6e8ee; border-radius: 10px; box-shadow: 0 1px 2px rgba(15,23,42,.05); margin: 0 0 18px 0; }
body.fq-page .fq-filter { display: flex; gap: 10px; padding: 14px 18px; border-bottom: 1px solid #e6e8ee; flex-wrap: wrap; align-items: center; }
body.fq-page .fq-filter input, body.fq-page .fq-filter select { border: 1px solid #c7ccd6; border-radius: 6px; padding: 0 12px; height: 38px; font-size: 14px; background: #fff; color: #0b1220; }
body.fq-page .fq-filter input:focus, body.fq-page .fq-filter select:focus { outline: 0; border-color: #1e293b; box-shadow: 0 0 0 3px #eef2f7; }
body.fq-page .fq-filter input[type=text] { width: 280px; }
body.fq-page .fq-reset { background: transparent; border: 1px solid #c7ccd6; color: #2c3447; padding: 0 16px; height: 38px; border-radius: 6px; font-size: 13px; cursor: pointer; }
body.fq-page .fq-table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 14px; }
body.fq-page .fq-table th { background: #f7f8fb; color: #5b6473; font-size: 11px !important; text-transform: uppercase; letter-spacing: .06em; padding: 12px 16px !important; text-align: left; border-bottom: 1px solid #e6e8ee; font-weight: 600; }
body.fq-page .fq-table td { padding: 14px 16px !important; border-bottom: 1px solid #e6e8ee; vertical-align: top; color: #0b1220; }
body.fq-page .fq-table tbody tr:last-child td { border-bottom: 0; }
body.fq-page .fq-table tbody tr:hover { background: #fafbfd; }
body.fq-page .fq-question { font-weight: 600; font-size: 15px; color: #0b1220; line-height: 1.4; }
body.fq-page .fq-answer { color: #5b6473; font-size: 12.5px; line-height: 1.5; margin-top: 4px; max-width: 580px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
body.fq-page .fq-chip { display: inline-block; padding: 3px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; line-height: 1.4; }
body.fq-page .fq-chip--global { background: #eef0ff; color: #4338ca; }
body.fq-page .fq-chip--prop   { background: #d1fae5; color: #047857; }
body.fq-page .fq-meta { color: #5b6473; font-size: 12px; }
body.fq-page .fq-actions { position: relative; text-align: right; }
body.fq-page .fq-action-btn { background: transparent; border: 1px solid #e6e8ee; color: #5b6473; width: 32px; height: 32px; border-radius: 6px; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; }
body.fq-page .fq-action-btn:hover { background: #f8fafc; color: #0b1220; border-color: #c7ccd6; }
body.fq-page .fq-action-menu { position: absolute; right: 0; top: 100%; margin-top: 4px; background: #fff; border: 1px solid #e6e8ee; border-radius: 10px; box-shadow: 0 10px 28px rgba(15,23,42,.10); min-width: 200px; z-index: 50; padding: 4px; display: none; }
body.fq-page .fq-action-menu.show { display: block; }
body.fq-page .fq-action-menu a, body.fq-page .fq-action-menu button { display: flex; align-items: center; gap: 10px; width: 100%; text-align: left; padding: 9px 12px; background: transparent; border: 0; color: #0b1220; font-size: 14px; text-decoration: none; cursor: pointer; border-radius: 6px; }
body.fq-page .fq-action-menu a:hover, body.fq-page .fq-action-menu button:hover { background: #f7f8fb; text-decoration: none; }
body.fq-page .fq-action-menu .fq-action-divider { height: 1px; background: #e6e8ee; margin: 4px 2px; }
body.fq-page .fq-action-menu .fq-danger { color: #b42318 !important; }
body.fq-page .fq-action-menu .fq-danger:hover { background: #fef0ee; }
body.fq-page .fq-add-btn { background: #1e293b; color: #fff; border: 0; padding: 8px 16px; border-radius: 6px; font-weight: 600; font-size: 14px; text-decoration: none; display: inline-flex; align-items: center; gap: 7px; }
body.fq-page .fq-add-btn:hover { background: #0f172a; color: #fff; text-decoration: none; }
body.fq-page .fq-empty { text-align: center; padding: 60px 20px; color: #5b6473; }
body.fq-page .fq-table tbody tr:last-child .fq-action-menu, body.fq-page .fq-table tbody tr:nth-last-child(2) .fq-action-menu { top: auto; bottom: 100%; margin-top: 0; margin-bottom: 4px; }
body.fq-page .fq-pill-count { background: #eef2f7; color: #5b6473; padding: 4px 10px; border-radius: 999px; font-size: 13px; font-weight: 500; }
</style>
@stop

@section('content_header')
<section class="content-header" style="padding: 18px 24px;">
    <div class="d-flex align-items-center" style="gap: 14px; flex-wrap: wrap;">
        <h1 class="m-0">{{$name}}</h1>
        <span class="fq-pill-count" id="fq-count">{{ count($data) }} total</span>
        <a href="{{ route('faq-categories.index') }}" class="fq-add-btn" style="margin-left:auto; background:#fff; color:#1e293b; border:1px solid #c7ccd6;"><i class="fa fa-tags"></i> Manage Categories</a>
        <a href="{{ route($route.'.create') }}" class="fq-add-btn"><i class="fa fa-plus"></i> Add FAQ</a>
    </div>
</section>
@stop

@section('content')
@parent
<script>document.body.classList.add('fq-page');</script>

<section class="content" style="padding: 0 24px;">
    <div class="fq-card">
        <div class="fq-filter">
            <input type="text" id="fq-filter-keyword" placeholder="Search question or answer…">
            <select id="fq-filter-scope">
                <option value="">All scopes</option>
                <option value="global">Global FAQs</option>
                <option value="prop">Property-specific</option>
            </select>
            <button type="button" class="fq-reset" id="fq-filter-reset">Reset</button>
        </div>

        @if(count($data) === 0)
            <div class="fq-empty">
                <p style="font-weight:600;color:#0b1220;font-size:18px;margin:0 0 4px 0;">No FAQs yet</p>
                <p style="margin:0 0 16px 0;">Add your first FAQ to help guests find answers fast.</p>
                <a href="{{ route($route.'.create') }}" class="fq-add-btn"><i class="fa fa-plus"></i> Add FAQ</a>
            </div>
        @else
        <table class="fq-table" id="fq-table">
            <thead>
                <tr>
                    <th>Question &amp; answer</th>
                    <th style="width:200px">Scope</th>
                    <th style="width:140px">Updated</th>
                    <th style="width:60px"></th>
                </tr>
            </thead>
            <tbody>
                @foreach($data as $client)
                @php
                    $propName = $client->property_id ? (App\Models\Property::find($client->property_id)->name ?? null) : null;
                    $scope = $propName ? 'prop' : 'global';
                @endphp
                <tr data-id="{{ $client->id }}"
                    data-search="{{ Str::lower(strip_tags($client->question . ' ' . ($client->answer ?? ''))) }}"
                    data-scope="{{ $scope }}">

                    <td>
                        <div class="fq-question">{{ Str::limit($client->question, 200) }}</div>
                        @if($client->answer)
                            <div class="fq-answer">{{ Str::limit(strip_tags($client->answer), 220) }}</div>
                        @else
                            <div class="fq-answer" style="font-style:italic;color:#94a3b8;">No answer yet</div>
                        @endif
                    </td>
                    <td>
                        @if($scope === 'prop')
                            <span class="fq-chip fq-chip--prop">🏠 {{ $propName }}</span>
                        @else
                            <span class="fq-chip fq-chip--global">🌐 Global FAQ</span>
                        @endif
                    </td>
                    <td>
                        <span class="fq-meta" title="{{ $client->updated_at }}">
                            {{ $client->updated_at ? \Carbon\Carbon::parse($client->updated_at)->diffForHumans() : '—' }}
                        </span>
                    </td>
                    <td class="fq-actions">
                        <button type="button" class="fq-action-btn fq-menu-toggle" aria-label="Actions"><i class="fa fa-ellipsis-v"></i></button>
                        <div class="fq-action-menu">
                            <a href="{{ route($route.'.edit', $client->id) }}"><i class="fa fa-pencil-alt"></i> Edit</a>
                            <a href="{{ route($route.'.copyData', $client->id) }}"><i class="fa fa-copy"></i> Duplicate</a>
                            <div class="fq-action-divider"></div>
                            <form method="POST" action="{{ route($route.'.destroy', $client->id) }}" id="fq-delete-form-{{ $client->id }}" style="display:none">@csrf @method('DELETE')</form>
                            <button type="button" class="fq-danger fq-delete-btn" data-id="{{ $client->id }}" data-name="{{ Str::limit($client->question, 60) }}"><i class="fa fa-trash"></i> Delete</button>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        <div class="fq-empty" id="fq-empty-filtered" style="display:none;">
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
    var FILTER_KEY = 'fq_admin_filters';
    var $kw  = document.getElementById('fq-filter-keyword');
    var $sc  = document.getElementById('fq-filter-scope');
    var $rst = document.getElementById('fq-filter-reset');
    var $cnt = document.getElementById('fq-count');
    var $tbl = document.getElementById('fq-table');
    var $emp = document.getElementById('fq-empty-filtered');
    if (!$tbl) return;
    var rows = Array.prototype.slice.call($tbl.querySelectorAll('tbody tr'));
    var total = rows.length;

    function loadF() { try { var f = JSON.parse(localStorage.getItem(FILTER_KEY) || '{}'); if($kw)$kw.value=f.kw||''; if($sc)$sc.value=f.sc||''; } catch(_) {} }
    function saveF() { try { localStorage.setItem(FILTER_KEY, JSON.stringify({ kw: $kw?$kw.value:'', sc: $sc?$sc.value:'' })); } catch(_) {} }
    function applyF() {
        var kw = ($kw ? $kw.value : '').trim().toLowerCase();
        var sc = $sc ? $sc.value : '';
        var visible = 0;
        rows.forEach(function (r) {
            var ok = (!kw || r.dataset.search.indexOf(kw) !== -1) && (!sc || r.dataset.scope === sc);
            r.style.display = ok ? '' : 'none';
            if (ok) visible++;
        });
        if ($cnt) $cnt.textContent = visible === total ? (total + ' total') : (visible + ' of ' + total);
        if ($emp) $emp.style.display = (visible === 0 ? 'block' : 'none');
        saveF();
    }
    loadF(); applyF();
    if ($kw) $kw.addEventListener('input', applyF);
    if ($sc) $sc.addEventListener('change', applyF);
    if ($rst) $rst.addEventListener('click', function () { if($kw)$kw.value=''; if($sc)$sc.value=''; applyF(); });

    document.querySelectorAll('.fq-menu-toggle').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            var menu = btn.nextElementSibling;
            var open = menu.classList.contains('show');
            document.querySelectorAll('.fq-action-menu.show').forEach(function (m) { m.classList.remove('show'); });
            if (!open) menu.classList.add('show');
        });
    });
    document.addEventListener('click', function () { document.querySelectorAll('.fq-action-menu.show').forEach(function (m) { m.classList.remove('show'); }); });

    document.querySelectorAll('.fq-delete-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var id = btn.getAttribute('data-id');
            var name = btn.getAttribute('data-name');
            Swal.fire({
                title: 'Delete this FAQ?', text: '"' + name + '"',
                icon: 'warning', showCancelButton: true,
                confirmButtonColor: '#b42318', confirmButtonText: 'Yes, delete it',
            }).then(function (r) { if (r.value) document.getElementById('fq-delete-form-' + id).submit(); });
        });
    });
})();
</script>
@stop
