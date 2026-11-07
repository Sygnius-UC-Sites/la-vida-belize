@extends('admin.layouts')
@section('title', 'Admin')

{{-- No content_header on this page — heading lives inside the page
     toolbar below, so we save the vertical real estate. --}}

@section('css')
@parent
<style>
    #properties-table_wrapper,
    #properties-table_wrapper .dataTables_scroll,
    #properties-table {
        overflow: visible !important;
    }
    #properties-table td:last-child {
        overflow: visible !important;
        position: relative;
    }
</style>
@stop

@section('content')
@parent
@php
    $adminPropertyThumb = function ($path) {
        $path = trim((string) $path);

        if ($path === '' || $path === '/') {
            return asset('No_image_available.svg.webp');
        }

        if (str_starts_with($path, '//')) {
            return 'https:' . $path;
        }

        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }

        if (preg_match('#^[a-z0-9.-]+\.[a-z]{2,}/#i', $path)) {
            return 'https://' . $path;
        }

        return asset(ltrim($path, '/'));
    };

    $adminFallbackThumb = asset('No_image_available.svg.webp');
@endphp
<section class="content">
    <div class="row">
        <div class="col-12">

            <div class="ca-page-toolbar">
                <div class="ca-page-toolbar-title">
                    <h1>Properties</h1>
                    <span class="ca-page-toolbar-count" id="prop-total-count">{{ $data->count() + ($pmsUnmatched ?? collect())->count() }}</span>
                </div>
                <div class="ca-page-toolbar-filters">
                    <div class="ca-filter-search">
                        <i class="fa fa-search" aria-hidden="true"></i>
                        <input type="text" id="prop-filter-keyword" placeholder="Search name or slug…">
                    </div>
                    <select id="prop-filter-status" class="ca-filter-select">
                        <option value="">All statuses</option>
                        <option value="true">Active</option>
                        <option value="false">Inactive</option>
                    </select>
                    <select id="prop-filter-booking-mode" class="ca-filter-select">
                        <option value="">All booking modes</option>
                        <option value="true">Instant</option>
                        <option value="false">Request</option>
                    </select>
                    <button type="button" id="prop-filter-reset" class="ca-filter-reset">Reset</button>
                </div>
                <a href="{{ route('properties.create') }}" class="ca-page-toolbar-action">
                    <i class="fa fa-plus"></i> Add property
                </a>
            </div>

            <div class="card">
                <div class="card-header" style="display:none"></div>

                <div id="prop-bulk-strip" class="alert alert-info py-2 px-3 mb-0" style="display:none;border-radius:0;">
                    <span id="prop-bulk-count">0 selected</span>
                    <button type="button" class="btn btn-sm btn-success ml-3" id="prop-bulk-activate">Activate</button>
                    <button type="button" class="btn btn-sm btn-warning ml-1" id="prop-bulk-deactivate">Deactivate</button>
                    <button type="button" class="btn btn-sm btn-danger ml-1" id="prop-bulk-delete">Delete</button>
                    <a href="#" class="ml-3" id="prop-bulk-clear">Clear selection</a>
                </div>

                <div class="card-body">
                    <table id="properties-table" class="table table-hover">
                        <thead>
                            <tr>
                                <th style="width:36px"><input type="checkbox" id="prop-bulk-master"></th>
                                <th>Property</th>
                                <th>Categories</th>
                                <th>Booking</th>
                                <th>Status</th>
                                <th>Updated</th>
                                <th style="width:60px"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($data as $p)
                            @php
                                $bookingMode = ($p->instant_booking_enabled || $p->instant_booking_button == 'true') ? 'true' : 'false';
                                $publicUrl = \App\Support\PropertyUrl::url($p);
                                $thumb = $adminPropertyThumb($p->feature_image);
                            @endphp
                            <tr data-id="{{ $p->id }}" data-status="{{ $p->status }}" data-booking-mode="{{ $bookingMode }}">
                                <td><input type="checkbox" class="prop-bulk-cb" value="{{ $p->id }}"></td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <img src="{{ $thumb }}" style="width:60px;height:40px;object-fit:cover;border-radius:4px;flex-shrink:0" class="mr-3" alt="" onerror="this.onerror=null;this.src='{{ $adminFallbackThumb }}';">
                                        <div style="min-width:0;flex:1">
                                            <div><strong>{{ $p->name }}</strong></div>
                                            <small class="text-muted d-block" style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:380px">/{{ $p->seo_url }}</small>
                                            <div class="mt-1">
                                                @if($p->location && $p->location->name)
                                                    <span class="badge badge-light"><i class="fa fa-map-marker-alt"></i> {{ $p->location->name }}</span>
                                                @endif
                                                <span class="badge badge-light" title="Photos in gallery"><i class="fa fa-image"></i> {{ $p->photos_count ?? 0 }}</span>
                                                @php
                                                    $pmsColors = ['lodgify'=>'info','hostfully'=>'success','guesty'=>'warning','hostaway'=>'primary'];
                                                    $linkedRoom = isset($pmsBySlug) ? ($pmsBySlug[$p->seo_url] ?? null) : null;
                                                    $rowPmsSource = $p->pms_source ?: ($linkedRoom->pms_source ?? null);
                                                @endphp
                                                @if($rowPmsSource)
                                                    <span class="badge badge-{{ $pmsColors[$rowPmsSource] ?? 'secondary' }}" title="Synced from {{ ucfirst($rowPmsSource) }}">
                                                        <i class="fa fa-sync-alt"></i> {{ ucfirst($rowPmsSource) }}
                                                    </span>
                                                    @if($linkedRoom)
                                                        <a class="badge badge-light" href="{{ url('admin/pms_rooms/'.$linkedRoom->id.'/edit') }}" title="Read-only: full synced data (calendar, fees, raw PMS fields)">
                                                            <i class="fa fa-database"></i> Synced data
                                                        </a>
                                                        @if($linkedRoom->status !== 'true')
                                                            <span class="badge badge-danger" title="The PMS account no longer returns this listing — the live calendar has detached. The page still works with its own rates/calendar.">
                                                                <i class="fa fa-triangle-exclamation"></i> No longer in PMS account
                                                            </span>
                                                        @endif
                                                    @endif
                                                @endif
                                                @if($p->is_home === 'true')
                                                    <span class="badge badge-success" title="Featured on the home page"><i class="fa fa-home"></i> Home page</span>
                                                @endif
                                                @if($p->is_parent)
                                                    <span class="badge badge-light text-muted" title="Sub-unit of property #{{ $p->is_parent }}">Unit of #{{ $p->is_parent }}</span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    @php
                                        $typeLabel = match(strtolower((string) $p->type)) {
                                            'condo', 'condos' => 'Condo',
                                            'house', 'houses' => 'House',
                                            default => 'No division',
                                        };
                                        $typeBadge = match(strtolower((string) $p->type)) {
                                            'condo', 'condos' => 'badge-primary',
                                            'house', 'houses' => 'badge-warning',
                                            default => 'badge-secondary',
                                        };
                                    @endphp
                                    <span class="badge {{ $typeBadge }} mb-1">{{ $typeLabel }}</span>
                                    @forelse($p->categories as $propertyCategory)
                                        <span class="badge badge-light">{{ $propertyCategory->name }}</span>
                                    @empty
                                        <span class="text-muted d-block">No custom category</span>
                                    @endforelse
                                </td>
                                <td>
                                    @if($bookingMode === 'true')
                                        <span class="badge badge-success">Instant</span>
                                    @else
                                        <span class="badge badge-info">Request</span>
                                    @endif
                                </td>
                                <td>
                                    @if($p->status == 'true')
                                        <span class="badge badge-success">Active</span>
                                    @else
                                        <span class="badge badge-secondary">Inactive</span>
                                    @endif
                                </td>
                                <td>
                                    <span title="{{ $p->updated_at }}">{{ $p->updated_at ? $p->updated_at->diffForHumans() : '—' }}</span>
                                </td>
                                <td>
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-secondary" type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                            <i class="fa fa-ellipsis-v"></i>
                                        </button>
                                        <div class="dropdown-menu dropdown-menu-right">
                                            <a class="dropdown-item" href="{{ route('properties.edit', $p->id) }}"><i class="fa fa-pencil-alt mr-2"></i>Edit</a>
                                            <a class="dropdown-item" href="{{ $publicUrl }}" target="_blank" rel="noopener"><i class="fa fa-external-link-alt mr-2"></i>View public page</a>
                                            <a class="dropdown-item" href="{{ route('properties.copyData', $p->id) }}"><i class="fa fa-copy mr-2"></i>Duplicate</a>
                                            @if($p->is_home === 'true')
                                                <a class="dropdown-item" href="{{ route('properties.toggle-home', $p->id) }}"><i class="fa fa-home mr-2"></i>Remove from home page</a>
                                            @else
                                                <a class="dropdown-item" href="{{ route('properties.toggle-home', $p->id) }}"><i class="fa fa-home mr-2"></i>Feature on home page</a>
                                            @endif
                                            <div class="dropdown-divider"></div>
                                            @if($p->status == 'true')
                                                <a class="dropdown-item prop-toggle-status" href="#" data-id="{{ $p->id }}" data-name="{{ $p->name }}" data-target-url="{{ route('properties.deactive', $p->id) }}" data-action="Deactivate"><i class="fa fa-times mr-2"></i>Deactivate</a>
                                            @else
                                                <a class="dropdown-item prop-toggle-status" href="#" data-id="{{ $p->id }}" data-name="{{ $p->name }}" data-target-url="{{ route('properties.active', $p->id) }}" data-action="Activate"><i class="fa fa-check mr-2"></i>Activate</a>
                                            @endif
                                            <a class="dropdown-item text-danger prop-delete" href="#" data-id="{{ $p->id }}" data-name="{{ $p->name }}"><i class="fa fa-trash mr-2"></i>Delete</a>
                                        </div>
                                    </div>
                                    <form method="post" action="{{ route('properties.destroy', $p->id) }}" id="delete-form-{{ $p->id }}" style="display:none">
                                        @csrf
                                        @method('DELETE')
                                    </form>
                                </td>
                            </tr>
                            @endforeach

                            {{-- PMS-synced rooms not yet linked to a core property.
                                 They live in the same list (unified admin) — editing
                                 opens the PMS room editor. Link a room by giving it
                                 the same URL slug as a property (or vice versa). --}}
                            @foreach(($pmsUnmatched ?? collect()) as $room)
                            @php
                                $pmsColors = ['lodgify'=>'info','hostfully'=>'success','guesty'=>'warning','hostaway'=>'primary'];
                                $roomThumb = $adminPropertyThumb($room->image_url);
                            @endphp
                            <tr data-id="pms-{{ $room->id }}" data-type="pms" data-status="{{ $room->status }}" data-booking-mode="">
                                <td></td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <img src="{{ $roomThumb }}" style="width:60px;height:40px;object-fit:cover;border-radius:4px;flex-shrink:0" class="mr-3" alt="" onerror="this.onerror=null;this.src='{{ $adminFallbackThumb }}';">
                                        <div style="min-width:0;flex:1">
                                            <div><strong>{{ $room->name }}</strong></div>
                                            <small class="text-muted d-block" style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:380px">/{{ $room->seo_url }}</small>
                                            <div class="mt-1">
                                                <span class="badge badge-{{ $pmsColors[$room->pms_source] ?? 'secondary' }}" title="Synced from {{ ucfirst($room->pms_source) }}">
                                                    <i class="fa fa-sync-alt"></i> {{ ucfirst($room->pms_source) }}
                                                </span>
                                                @if($room->status !== 'true')
                                                    <span class="badge badge-danger" title="The PMS account no longer returns this listing. It is kept for history — delete it from the PMS record editor if it should go.">
                                                        <i class="fa fa-triangle-exclamation"></i> No longer in PMS account
                                                    </span>
                                                @endif
                                                <span class="badge badge-warning" title="This room is synced from the PMS but has no page on the website yet — publish it below">
                                                    <i class="fa fa-unlink"></i> Not on the website yet
                                                </span>
                                                {{-- Publish with placement options --}}
                                                <form method="post" action="{{ url('admin/pms_rooms/'.$room->id.'/publish') }}"
                                                      class="d-inline-flex align-items-center" style="gap:10px; margin-top:6px; flex-wrap:wrap;">
                                                    @csrf
                                                    <label class="mb-0" style="font-weight:400; font-size:12px; cursor:pointer;">
                                                        <input type="checkbox" checked disabled> Search &amp; stays page
                                                    </label>
                                                    <label class="mb-0" style="font-weight:400; font-size:12px; cursor:pointer;">
                                                        <input type="checkbox" name="show_home" value="1"> Feature on home page
                                                    </label>
                                                    @foreach(($propertyCategories ?? collect()) as $propertyCategory)
                                                        <label class="mb-0" style="font-weight:400; font-size:12px; cursor:pointer;">
                                                            <input type="checkbox" name="property_category_ids[]" value="{{ $propertyCategory->id }}"> {{ $propertyCategory->name }}
                                                        </label>
                                                    @endforeach
                                                    <button type="submit" class="btn btn-xs btn-primary py-0 px-2" style="font-size:12px;"
                                                            title="Creates the site page at /{{ $room->seo_url }} with the room's photos and description; the live PMS calendar attaches automatically">
                                                        <i class="fa fa-globe"></i> Publish to site
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td><span class="text-muted">Not assigned</span></td>
                                <td><span class="badge badge-info">Via {{ ucfirst($room->pms_source) }}</span></td>
                                <td>
                                    @if($room->status == 'true')
                                        <span class="badge badge-success">Active</span>
                                    @else
                                        <span class="badge badge-secondary">Inactive</span>
                                    @endif
                                </td>
                                <td><span title="{{ $room->updated_at }}">{{ $room->updated_at ? $room->updated_at->diffForHumans() : '—' }}</span></td>
                                <td>
                                    <a class="btn btn-sm btn-outline-secondary" href="{{ url('admin/pms_rooms/'.$room->id.'/edit') }}" title="Edit PMS room">
                                        <i class="fa fa-pencil-alt"></i>
                                    </a>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</section>
@stop

@section('js')
@parent
<script>
(function () {
    var FILTER_KEY = 'prop_admin_filters';
    var BULK_URLS = {
        activate:   '{{ route('properties.bulk-activate') }}',
        deactivate: '{{ route('properties.bulk-deactivate') }}',
        delete:     '{{ route('properties.bulk-delete') }}',
    };
    var CSRF = '{{ csrf_token() }}';

    // ---- DataTable + custom filters --------------------------------------
    var table = $('#properties-table').DataTable({
        pageLength: 50,
        lengthMenu: [[25, 50, 100, -1], [25, 50, 100, 'All']],
        order: [[1, 'asc']],
        columnDefs: [
            { orderable: false, targets: [0, 6] },
        ],
        dom: "<'row'<'col-sm-3'l><'col-sm-9'p>>" +
             "<'row'<'col-sm-12'tr>>" +
             "<'row'<'col-sm-5'i><'col-sm-7'p>>",
    });

    // Scoped DataTable filter — only applies to this table.
    $.fn.dataTable.ext.search.push(function (settings, searchData, dataIndex, rowData, counter) {
        if (settings.nTable.id !== 'properties-table') return true;
        var row = $(table.row(dataIndex).node());
        var s = $('#prop-filter-status').val();
        var b = $('#prop-filter-booking-mode').val();
        if (s && String(row.data('status')) !== s) return false;
        if (b && String(row.data('bookingMode')) !== b) return false;
        return true;
    });

    function applyFilters() {
        var kw = $('#prop-filter-keyword').val();
        table.search(kw).draw();
        persistFilters();
        refreshBulkStrip();
    }
    function persistFilters() {
        try {
            localStorage.setItem(FILTER_KEY, JSON.stringify({
                status:      $('#prop-filter-status').val(),
                bookingMode: $('#prop-filter-booking-mode').val(),
                keyword:     $('#prop-filter-keyword').val(),
            }));
        } catch (e) {}
    }
    function loadFilters() {
        try {
            var raw = localStorage.getItem(FILTER_KEY);
            if (!raw) return;
            var f = JSON.parse(raw) || {};
            $('#prop-filter-status').val(f.status || '');
            $('#prop-filter-booking-mode').val(f.bookingMode || '');
            $('#prop-filter-keyword').val(f.keyword || '');
        } catch (e) {}
    }

    loadFilters();
    applyFilters();

    $('#prop-filter-keyword').on('input', applyFilters);
    $('#prop-filter-status, #prop-filter-booking-mode').on('change', applyFilters);
    $('#prop-filter-reset').on('click', function () {
        $('#prop-filter-keyword, #prop-filter-status, #prop-filter-booking-mode').val('');
        applyFilters();
    });

    // ---- Bulk-action checkboxes ------------------------------------------
    $('#prop-bulk-master').on('change', function () {
        var checked = this.checked;
        $('#properties-table tbody .prop-bulk-cb:visible').prop('checked', checked);
        refreshBulkStrip();
    });
    $('#properties-table').on('change', '.prop-bulk-cb', refreshBulkStrip);
    $('#prop-bulk-clear').on('click', function (e) {
        e.preventDefault();
        $('.prop-bulk-cb, #prop-bulk-master').prop('checked', false);
        refreshBulkStrip();
    });

    function selectedIds() {
        return $('.prop-bulk-cb:checked').map(function () { return parseInt(this.value, 10); }).get();
    }
    function refreshBulkStrip() {
        var ids = selectedIds();
        if (ids.length) {
            $('#prop-bulk-count').text(ids.length + ' selected');
            $('#prop-bulk-strip').show();
        } else {
            $('#prop-bulk-strip').hide();
        }
    }

    function bulkAction(label, url, confirmText, successColor) {
        var ids = selectedIds();
        if (!ids.length) return;
        Swal.fire({
            title: confirmText,
            text: ids.length + ' selected. Continue?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: successColor || '#3085d6',
            confirmButtonText: 'Yes, ' + label.toLowerCase(),
        }).then(function (result) {
            if (!result.value) return;
            fetch(url, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json', 'Content-Type': 'application/json' },
                body: JSON.stringify({ ids: ids })
            }).then(function (r) {
                if (!r.ok) throw new Error('HTTP ' + r.status);
                return r.json();
            }).then(function () {
                Swal.fire('Done', label + 'd ' + ids.length + ' propert' + (ids.length === 1 ? 'y' : 'ies') + '.', 'success').then(function () { location.reload(); });
            }).catch(function (e) {
                Swal.fire('Failed', e.message, 'error');
            });
        });
    }

    $('#prop-bulk-activate').on('click', function () { bulkAction('Activate', BULK_URLS.activate, 'Activate selected properties?', '#28a745'); });
    $('#prop-bulk-deactivate').on('click', function () { bulkAction('Deactivate', BULK_URLS.deactivate, 'Deactivate selected properties?', '#ffc107'); });
    $('#prop-bulk-delete').on('click', function () { bulkAction('Delete', BULK_URLS.delete, 'Delete selected properties?', '#d33'); });

    // ---- Per-row toggle status -------------------------------------------
    $('#properties-table').on('click', '.prop-toggle-status', function (e) {
        e.preventDefault();
        var btn = $(this);
        Swal.fire({
            title: btn.data('action') + ' "' + btn.data('name') + '"?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            confirmButtonText: 'Yes, ' + btn.data('action').toLowerCase(),
        }).then(function (result) {
            if (result.value) location.href = btn.data('targetUrl');
        });
    });

    // ---- Per-row delete --------------------------------------------------
    $('#properties-table').on('click', '.prop-delete', function (e) {
        e.preventDefault();
        var btn = $(this);
        Swal.fire({
            title: 'Delete "' + btn.data('name') + '"?',
            text: 'This action cannot be undone.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            confirmButtonText: 'Yes, delete it',
        }).then(function (result) {
            if (result.value) document.getElementById('delete-form-' + btn.data('id')).submit();
        });
    });
})();
</script>
@stop
