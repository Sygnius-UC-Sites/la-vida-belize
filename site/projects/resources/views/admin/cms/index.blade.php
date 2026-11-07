@extends('admin.layouts')
@section('title', 'Pages')

@php
    $guestSeo = $guestSeo ?? \App\Models\Cms::guestSeoUrls();
    $cmsTemplateCategories = [
        'home' => 'marketing',
        'about' => 'marketing',
        'about-owner' => 'marketing',
        'property-list' => 'marketing',
        'experiences' => 'marketing',
        'groups' => 'marketing',
        'reviews' => 'marketing',
        'attractions' => 'marketing',
        'attractions-dining' => 'marketing',
        'attractions-adventures' => 'marketing',
        'attractions-taxi' => 'marketing',
        'attractions-golf-cart' => 'marketing',
        'attractions-car-rental' => 'marketing',
        'faq' => 'info',
        'common' => 'info',
        'contact' => 'info',
        'privacy' => 'info',
        'terms' => 'info',
        'legal-page' => 'info',
        'booking' => 'transact',
        'checkout' => 'transact',
        'magic-link-payment' => 'transact',
        'magic-link-expired' => 'transact',
        'payment-success' => 'transact',
        'payment-failed' => 'transact',
        'booking-confirmed' => 'transact',
        'booking-request-received' => 'transact',
    ];
    $cmsTemplateLabels = [
        'home' => 'Home',
        'about' => 'About Us',
        'about-owner' => 'About Owner',
        'property-list' => 'Properties',
        'experiences' => 'Experience',
        'groups' => 'Group Stays',
        'reviews' => 'Reviews',
        'attractions' => 'Attractions',
        'attractions-dining' => 'Dining',
        'attractions-adventures' => 'Adventures',
        'attractions-taxi' => 'Taxi',
        'attractions-golf-cart' => 'Golf cart',
        'attractions-car-rental' => 'Car rental',
        'faq' => 'FAQ',
        'common' => 'Static',
        'contact' => 'Contact',
        'privacy' => 'Privacy',
        'terms' => 'Terms',
        'legal-page' => 'Legal',
        'booking' => 'Booking',
        'checkout' => 'Checkout',
        'magic-link-payment' => 'Magic link',
        'magic-link-expired' => 'Magic link',
        'payment-success' => 'Payment',
        'payment-failed' => 'Payment',
        'booking-confirmed' => 'Booking conf.',
        'booking-request-received' => 'Booking conf.',
    ];

    $cmsStats = ['total' => 0, 'marketing' => 0, 'info' => 0, 'transact' => 0];
    foreach ($data as $row) {
        if (!in_array($row->seo_url, $guestSeo, true)) {
            continue;
        }
        $cmsStats['total']++;
        $cat = $cmsTemplateCategories[$row->templete] ?? null;
        if ($cat && isset($cmsStats[$cat])) $cmsStats[$cat]++;
    }
@endphp

@section('css')
@parent
@include('admin.cms.partials.cms-shell')
@stop

@section('content')
@parent
<div class="cms-page">
    <div class="cms-page-header">
        <div class="cms-header-text">
            <h1>Pages</h1>
            <span class="cms-page-sub">La Vida guest pages, plus booking-flow templates when you need them.</span>
        </div>
        <a href="{{ route('cms.create') }}" class="cms-add-btn">
            <i class="fa fa-plus"></i> Add page
        </a>
    </div>

    <div class="cms-stats">
        <div class="cms-stat cms-stat--total">
            <p class="cms-stat-label">Total pages</p>
            <div class="cms-stat-value" id="cms-stat-total">{{ $cmsStats['total'] }}</div>
            <div class="cms-stat-hint">La Vida guest pages</div>
        </div>
        <div class="cms-stat cms-stat--marketing">
            <p class="cms-stat-label">Marketing</p>
            <div class="cms-stat-value">{{ $cmsStats['marketing'] }}</div>
            <div class="cms-stat-hint">Home, story, listings…</div>
        </div>
        <div class="cms-stat cms-stat--info">
            <p class="cms-stat-label">Information</p>
            <div class="cms-stat-value">{{ $cmsStats['info'] }}</div>
            <div class="cms-stat-hint">FAQ, contact, static</div>
        </div>
        <div class="cms-stat cms-stat--transact">
            <p class="cms-stat-label">Transactional</p>
            <div class="cms-stat-value">{{ $cmsStats['transact'] }}</div>
            <div class="cms-stat-hint">Booking, checkout, magic-link…</div>
        </div>
    </div>

    <div class="cms-filter-card">
        <input type="text" id="cms-filter-keyword" placeholder="Search by name or slug…">
        <select id="cms-filter-group">
            <option value="website" selected>Website pages</option>
            <option value="booking">Booking flow</option>
            <option value="">All pages</option>
        </select>
        <select id="cms-filter-template">
            <option value="">All templates</option>
            @php $seenTemplates = []; @endphp
            @foreach($data as $row)
                @if($row->templete && !in_array($row->templete, $seenTemplates, true))
                    <option value="{{ $row->templete }}">{{ $cmsTemplateLabels[$row->templete] ?? $row->templete }}</option>
                    @php $seenTemplates[] = $row->templete; @endphp
                @endif
            @endforeach
        </select>
        <select id="cms-filter-banner">
            <option value="">Any banner</option>
            <option value="yes">Has banner</option>
            <option value="no">No banner</option>
        </select>
        <button type="button" class="cms-reset-btn" id="cms-filter-reset">Reset</button>
    </div>

    <div class="cms-list-card">
        @if(count($data) === 0)
            <div class="cms-empty">
                <div class="cms-empty-icon"><i class="fa fa-file-alt"></i></div>
                <h3>No pages yet</h3>
                <p>Create your first CMS page to start managing content.</p>
                <a href="{{ route('cms.create') }}" class="cms-add-btn">
                    <i class="fa fa-plus"></i> Add page
                </a>
            </div>
        @else
            <table class="cms-list-table" id="cms-list-table">
                <thead>
                    <tr>
                        <th style="width:120px">&nbsp;</th>
                        <th>Page</th>
                        <th style="width:170px">Template</th>
                        <th style="width:140px">Updated</th>
                        <th style="width:60px"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($data as $row)
                        @php
                            $cat = $cmsTemplateCategories[$row->templete] ?? 'default';
                            $label = $cmsTemplateLabels[$row->templete] ?? ($row->templete ?: 'Unknown');
                            $publicUrl = url($row->seo_url);
                            $isGuest = in_array($row->seo_url, $guestSeo, true);
                        @endphp
                        <tr data-id="{{ $row->id }}"
                            data-name="{{ Str::lower($row->name) }}"
                            data-slug="{{ Str::lower($row->seo_url) }}"
                            data-template="{{ $row->templete }}"
                            data-group="{{ $isGuest ? 'website' : 'booking' }}"
                            data-has-banner="{{ $row->bannerImage ? 'yes' : 'no' }}">
                            <td>
                                @if($row->bannerImage)
                                    <img src="{{ asset($row->bannerImage) }}" alt="" class="cms-row-thumb">
                                @else
                                    <div class="cms-row-thumb cms-row-thumb--empty" aria-hidden="true">
                                        <i class="fa fa-image"></i>
                                    </div>
                                @endif
                            </td>
                            <td>
                                <div class="cms-row-name">{{ $row->name }}</div>
                                <span class="cms-row-slug">/{{ $row->seo_url }}</span>
                            </td>
                            <td>
                                <span class="cms-chip cms-chip--{{ $cat }}">{{ $label }}</span>
                            </td>
                            <td>
                                <span class="cms-row-time" title="{{ $row->updated_at }}">
                                    {{ $row->updated_at ? $row->updated_at->diffForHumans() : '—' }}
                                </span>
                            </td>
                            <td class="cms-row-actions">
                                <button type="button" class="cms-action-btn cms-menu-toggle" aria-label="Actions">
                                    <i class="fa fa-ellipsis-v"></i>
                                </button>
                                <div class="cms-action-menu">
                                    <a href="{{ route('cms.edit', $row->id) }}"><i class="fa fa-pencil-alt"></i> Edit</a>
                                    <a href="{{ $publicUrl }}" target="_blank" rel="noopener"><i class="fa fa-external-link-alt"></i> View public</a>
                                    <button type="button" class="cms-duplicate-btn" data-id="{{ $row->id }}" data-name="{{ $row->name }}"><i class="fa fa-copy"></i> Duplicate</button>
                                    <div class="cms-action-divider"></div>
                                    <button type="button" class="cms-action-danger cms-delete-btn" data-id="{{ $row->id }}" data-name="{{ $row->name }}"><i class="fa fa-trash"></i> Delete</button>
                                </div>
                                <form method="POST" action="{{ route('cms.destroy', $row->id) }}" id="cms-delete-form-{{ $row->id }}" style="display:none">
                                    @csrf
                                    @method('DELETE')
                                </form>
                                <form method="POST" action="{{ route('cms.duplicate', $row->id) }}" id="cms-duplicate-form-{{ $row->id }}" style="display:none">
                                    @csrf
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="cms-empty" id="cms-empty-filtered" style="display:none">
                <div class="cms-empty-icon"><i class="fa fa-search"></i></div>
                <h3>No matches</h3>
                <p>Try clearing the filters or adjusting your search.</p>
                <button type="button" class="cms-reset-btn" id="cms-filter-reset-2">Clear filters</button>
            </div>
        @endif
    </div>
</div>
@stop

@section('js')
@parent
<script>
(function () {
    var FILTER_KEY = 'cms_admin_filters_v2';
    var $kw   = document.getElementById('cms-filter-keyword');
    var $grp  = document.getElementById('cms-filter-group');
    var $tpl  = document.getElementById('cms-filter-template');
    var $bn   = document.getElementById('cms-filter-banner');
    var $rst  = document.getElementById('cms-filter-reset');
    var $rst2 = document.getElementById('cms-filter-reset-2');
    var $cnt  = document.getElementById('cms-stat-total');
    var $tbl  = document.getElementById('cms-list-table');
    var $emptyFiltered = document.getElementById('cms-empty-filtered');

    if (!$tbl) return; // empty state — no rows to filter

    var rows = Array.prototype.slice.call($tbl.querySelectorAll('tbody tr'));
    var totalCount = rows.length;

    function loadFilters() {
        try {
            var raw = localStorage.getItem(FILTER_KEY);
            if (!raw) return;
            var f = JSON.parse(raw) || {};
            if ($kw)  $kw.value = f.keyword || '';
            if ($grp) $grp.value = (typeof f.group === 'string') ? f.group : 'website';
            if ($tpl) $tpl.value = f.template || '';
            if ($bn)  $bn.value = f.banner || '';
        } catch (e) {}
    }
    function persistFilters() {
        try {
            localStorage.setItem(FILTER_KEY, JSON.stringify({
                keyword:  $kw  ? $kw.value  : '',
                group:    $grp ? $grp.value : 'website',
                template: $tpl ? $tpl.value : '',
                banner:   $bn  ? $bn.value  : '',
            }));
        } catch (e) {}
    }
    function applyFilters() {
        var kw = ($kw ? $kw.value : '').trim().toLowerCase();
        var grp = $grp ? $grp.value : 'website';
        var tpl = $tpl ? $tpl.value : '';
        var bn = $bn ? $bn.value : '';
        var visible = 0;
        rows.forEach(function (r) {
            var nameMatch = !kw || r.dataset.name.indexOf(kw) !== -1 || r.dataset.slug.indexOf(kw) !== -1;
            var grpMatch = !grp || r.dataset.group === grp;
            var tplMatch = !tpl || r.dataset.template === tpl;
            var bnMatch = !bn || r.dataset.hasBanner === bn;
            var show = nameMatch && grpMatch && tplMatch && bnMatch;
            r.style.display = show ? '' : 'none';
            if (show) visible++;
        });
        if ($cnt) $cnt.textContent = visible === totalCount ? totalCount : (visible + ' / ' + totalCount);
        if ($emptyFiltered) $emptyFiltered.style.display = (visible === 0 ? 'block' : 'none');
        persistFilters();
    }
    function resetFilters() {
        if ($kw)  $kw.value = '';
        if ($grp) $grp.value = 'website';
        if ($tpl) $tpl.value = '';
        if ($bn)  $bn.value = '';
        applyFilters();
    }

    if ($grp && !$grp.value) $grp.value = 'website';
    loadFilters();
    applyFilters();

    if ($kw)   $kw.addEventListener('input', applyFilters);
    if ($grp)  $grp.addEventListener('change', applyFilters);
    if ($tpl)  $tpl.addEventListener('change', applyFilters);
    if ($bn)   $bn.addEventListener('change', applyFilters);
    if ($rst)  $rst.addEventListener('click', resetFilters);
    if ($rst2) $rst2.addEventListener('click', resetFilters);

    // Three-dot action menus — only one open at a time
    document.querySelectorAll('.cms-menu-toggle').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            var menu = btn.nextElementSibling;
            var isOpen = menu.classList.contains('show');
            document.querySelectorAll('.cms-action-menu.show').forEach(function (m) { m.classList.remove('show'); });
            if (!isOpen) menu.classList.add('show');
        });
    });
    document.addEventListener('click', function () {
        document.querySelectorAll('.cms-action-menu.show').forEach(function (m) { m.classList.remove('show'); });
    });

    // Duplicate
    document.querySelectorAll('.cms-duplicate-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var id = btn.getAttribute('data-id');
            var name = btn.getAttribute('data-name');
            Swal.fire({
                title: 'Duplicate "' + name + '"?',
                text: 'A copy will be created with a new slug. You can edit it afterwards.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#1e293b',
                confirmButtonText: 'Yes, duplicate',
            }).then(function (result) {
                if (result.value) document.getElementById('cms-duplicate-form-' + id).submit();
            });
        });
    });

    // Delete
    document.querySelectorAll('.cms-delete-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var id = btn.getAttribute('data-id');
            var name = btn.getAttribute('data-name');
            Swal.fire({
                title: 'Delete "' + name + '"?',
                text: 'This action cannot be undone.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#b42318',
                confirmButtonText: 'Yes, delete it',
            }).then(function (result) {
                if (result.value) document.getElementById('cms-delete-form-' + id).submit();
            });
        });
    });
})();
</script>
@stop
