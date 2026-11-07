<p class="text-muted">
    Tick the amenities your property has. Each pick shows up on the public detail page in
    the "Show all amenities" drawer, grouped by category. For anything not in the library
    below, use the "Custom amenities" section at the bottom.
</p>

@isset($data)
    @php
        $library = \Helper::getAmenityLibrary();

        $existingGroups = \App\Models\PropertyAmenityGroup::where('property_id', $data->id)
            ->orderBy('sorting')->orderBy('id')->get();
        $existingRows = [];
        foreach ($existingGroups as $g) {
            foreach (\App\Models\PropertyAmenity::where('property_amenity_id', $g->id)
                ->orderBy('sorting')->orderBy('id')->get() as $a) {
                $existingRows[] = [
                    'group_id'          => $g->id,
                    'group_name'        => $g->name,
                    'amenity_id'        => $a->id,
                    'name'              => $a->name,
                    'icon'              => $a->icon,
                    'status'            => $a->status,
                    'is_card_highlight' => !empty($a->is_card_highlight),
                ];
            }
        }

        $libraryKeys = [];
        foreach ($library as $cat => $items) {
            foreach ($items as $i) {
                $libraryKeys[$cat . '::' . $i['name']] = true;
            }
        }

        $tickedKeys = [];
        $customRows = [];
        foreach ($existingRows as $row) {
            $key = $row['group_name'] . '::' . $row['name'];
            if (isset($libraryKeys[$key]) && $row['status'] === 'true') {
                $tickedKeys[$key] = true;
            } elseif (!isset($libraryKeys[$key])) {
                $customRows[] = $row;
            }
        }
    @endphp

    <div class="alert alert-info small mb-2">
        💡 <strong>Highlighted amenities (★):</strong> Click ★ next to any amenity below to feature it as a "main" amenity. Highlighted amenities appear in <strong>two</strong> places — on the property listing cards (property listing and booking pages) AND on the property detail page directly above the "Show all amenities" button. Mark up to {{ \Helper::getCardHighlightLimit() }} stars; all other amenities still show inside the "Show all amenities" drawer. Click <strong>Save library picks</strong> at the bottom of this tab to persist your selections.
    </div>

    <style>
        .star-toggle-input { display: none; }
        .star-toggle-label { cursor: pointer; padding: 0 4px; user-select: none; }
        .star-toggle-label .fa-star { color: #ccc; transition: color 0.1s; }
        .star-toggle-input:checked + .star-toggle-label .fa-star { color: #f1c40f; }
    </style>

    <form method="POST" action="{{ url('admin/properties/'.$data->id.'/amenities/sync-library') }}" id="library-sync-form">
        @csrf

        @foreach ($library as $categoryName => $items)
            <div class="card card-info mb-3">
                <div class="card-header">
                    <h5 class="card-title m-0">{{ $categoryName }}</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        @foreach ($items as $item)
                            @php
                                $key = $categoryName . '::' . $item['name'];
                                $existingId = null;
                                $isHighlighted = false;
                                if (isset($tickedKeys[$key])) {
                                    $matchRow = collect($existingRows)->first(function ($r) use ($categoryName, $item) {
                                        return $r['group_name'] === $categoryName && $r['name'] === $item['name'];
                                    });
                                    if ($matchRow) {
                                        $existingId = $matchRow['amenity_id'];
                                        $isHighlighted = $matchRow['is_card_highlight'];
                                    }
                                }
                            @endphp
                            <div class="col-md-4">
                                <div class="d-flex align-items-center" style="padding: 6px 0;">
                                    <label class="d-flex align-items-center mb-0" style="cursor: pointer; flex: 1;">
                                        <input type="checkbox"
                                               name="library_amenities[]"
                                               value="{{ $key }}"
                                               class="mr-2"
                                               {{ isset($tickedKeys[$key]) ? 'checked' : '' }}>
                                        <i class="fa-solid fa-{{ $item['icon'] }}" style="margin: 0 8px; width: 20px; text-align: center;"></i>
                                        <span>{{ $item['name'] }}</span>
                                    </label>
                                    @if ($existingId)
                                        <input type="checkbox"
                                               name="card_highlights[]"
                                               value="{{ $existingId }}"
                                               id="star-{{ $existingId }}"
                                               class="star-toggle-input"
                                               form="library-sync-form"
                                               {{ $isHighlighted ? 'checked' : '' }}>
                                        <label for="star-{{ $existingId }}" class="star-toggle-label mb-0" title="Mark as a highlight on listing cards (★ = featured on cards)">
                                            <i class="fa-solid fa-star"></i>
                                        </label>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endforeach

        <div class="text-right mb-3">
            <button type="submit" class="btn btn-primary btn-lg">Save library picks</button>
        </div>
    </form>

    <div class="card card-secondary mt-3">
        <div class="card-header">
            <h5 class="card-title m-0">Custom amenities (not in the library)</h5>
        </div>
        <div class="card-body">
            @if (count($customRows) === 0)
                <p class="text-muted small mb-2">No custom amenities yet. Use the form below to add ones not in the library.</p>
            @else
                <form method="POST" action="{{ url('admin/properties/'.$data->id.'/amenities/custom-delete') }}" onsubmit="return confirm('Delete the selected amenities?');">
                    @csrf
                    <div class="row mb-2 font-weight-bold text-muted small">
                        <div class="col-md-1">Select</div>
                        <div class="col-md-1">Icon</div>
                        <div class="col-md-1">Star</div>
                        <div class="col-md-3">Name</div>
                        <div class="col-md-4">Group</div>
                        <div class="col-md-2 text-right">Actions</div>
                    </div>
                    @foreach ($customRows as $row)
                        <div class="row align-items-center mb-2">
                            <div class="col-md-1 text-center">
                                <input type="checkbox" name="ids[]" value="{{ $row['amenity_id'] }}">
                            </div>
                            <div class="col-md-1 text-center">
                                <i class="fa-solid fa-{{ $row['icon'] ?: 'circle-info' }}"></i>
                            </div>
                            <div class="col-md-1 text-center">
                                <input type="checkbox"
                                       name="card_highlights[]"
                                       value="{{ $row['amenity_id'] }}"
                                       id="custom-star-{{ $row['amenity_id'] }}"
                                       class="star-toggle-input"
                                       form="library-sync-form"
                                       {{ !empty($row['is_card_highlight']) ? 'checked' : '' }}>
                                <label for="custom-star-{{ $row['amenity_id'] }}" class="star-toggle-label mb-0" title="Mark as a highlight on listing cards (★ = featured on cards)">
                                    <i class="fa-solid fa-star"></i>
                                </label>
                            </div>
                            <div class="col-md-3">{{ $row['name'] }}</div>
                            <div class="col-md-4 text-muted small">{{ $row['group_name'] }}</div>
                            <div class="col-md-2 text-right">
                                <button type="button"
                                        class="btn btn-sm btn-outline-danger"
                                        onclick="if (confirm('Delete this custom amenity?')) { document.getElementById('single-del-{{ $row['amenity_id'] }}').submit(); }">🗑</button>
                            </div>
                        </div>
                    @endforeach
                    <button type="submit" class="btn btn-danger btn-sm mt-2">Delete selected</button>
                </form>


                {{-- Hidden single-delete forms (re-uses Phase 4B route) --}}
                @foreach ($customRows as $row)
                    <form id="single-del-{{ $row['amenity_id'] }}"
                          method="POST"
                          action="{{ url('admin/properties/'.$data->id.'/group-amenities/'.$row['group_id'].'/amenities/'.$row['amenity_id'].'/delete') }}"
                          style="display:none;">
                        @csrf
                        @method('DELETE')
                    </form>
                @endforeach

                <hr>
            @endif

            <h6 class="mt-2">Add new custom amenities</h6>
            <form method="POST" action="{{ url('admin/properties/'.$data->id.'/amenities/custom-add') }}" id="custom-bulk-add">
                @csrf
                <div id="custom-rows">
                    <div class="row align-items-center mb-2 custom-add-row">
                        <div class="col-md-3 form-group">
                            <input type="text" name="name[]" class="form-control" placeholder="Amenity name (e.g. Tennis court)">
                        </div>
                        <div class="col-md-2 form-group">
                            <select name="icon[]" class="form-control">
                                <option value="">(no icon)</option>
                                @foreach (\Helper::getAmenityIcons() as $iconName => $iconLabel)
                                    <option value="{{ $iconName }}">{{ $iconLabel }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3 form-group">
                            <select name="category[]" class="form-control category-select">
                                @foreach (array_keys(\Helper::getAmenityLibrary()) as $cat)
                                    <option value="{{ $cat }}">{{ $cat }}</option>
                                @endforeach
                                <option value="__custom__">--- Use a custom category ---</option>
                            </select>
                        </div>
                        <div class="col-md-2 form-group">
                            <input type="text" name="custom_category[]" class="form-control custom-category-input"
                                   placeholder="Custom category name" style="display:none;">
                        </div>
                        <div class="col-md-1 form-group text-center add-row-star">
                            <input type="hidden" name="highlight[0]" value="0" class="add-row-highlight-hidden">
                            <input type="checkbox" name="highlight[0]" value="1" id="add-star-0" class="star-toggle-input add-row-highlight-checkbox">
                            <label for="add-star-0" class="star-toggle-label mb-0" title="Mark as a highlight on listing cards (★ = featured on cards)">
                                <i class="fa-solid fa-star"></i>
                            </label>
                        </div>
                        <div class="col-md-1 text-right">
                            <button type="button" class="btn btn-sm btn-outline-secondary remove-custom-row" title="Remove this row">×</button>
                        </div>
                    </div>
                </div>

                <button type="button" class="btn btn-sm btn-outline-primary mt-2" id="add-another-custom-row">
                    <i class="fa fa-plus"></i> Add another row
                </button>
                <button type="submit" class="btn btn-primary btn-sm mt-2">Save all</button>
            </form>
        </div>
    </div>
@endisset

<script>
(function () {
    var rowsContainer = document.getElementById('custom-rows');
    if (!rowsContainer) return;
    var firstRow = rowsContainer.querySelector('.custom-add-row');

    function reindexRows() {
        rowsContainer.querySelectorAll('.custom-add-row').forEach(function (row, idx) {
            var hidden = row.querySelector('.add-row-highlight-hidden');
            var cb     = row.querySelector('.add-row-highlight-checkbox');
            var label  = row.querySelector('.star-toggle-label');
            if (hidden) hidden.name = 'highlight[' + idx + ']';
            if (cb) {
                cb.name = 'highlight[' + idx + ']';
                cb.id = 'add-star-' + idx;
            }
            if (label) label.setAttribute('for', 'add-star-' + idx);
        });
    }

    document.getElementById('add-another-custom-row').addEventListener('click', function () {
        var clone = firstRow.cloneNode(true);
        clone.querySelectorAll('input, select').forEach(function (el) {
            if (el.tagName === 'SELECT') {
                el.selectedIndex = 0;
            } else if (el.type === 'checkbox') {
                el.checked = false;
            } else if (el.type === 'hidden') {
                // leave hidden's value=0 alone
            } else {
                el.value = '';
            }
        });
        clone.querySelector('.custom-category-input').style.display = 'none';
        rowsContainer.appendChild(clone);
        reindexRows();
    });

    rowsContainer.addEventListener('click', function (e) {
        if (e.target.closest('.remove-custom-row')) {
            var rows = rowsContainer.querySelectorAll('.custom-add-row');
            if (rows.length > 1) {
                e.target.closest('.custom-add-row').remove();
                reindexRows();
            } else {
                rows[0].querySelectorAll('input').forEach(function (el) {
                    if (el.type === 'checkbox') { el.checked = false; }
                    else if (el.type !== 'hidden') { el.value = ''; }
                });
                rows[0].querySelectorAll('select').forEach(function (el) { el.selectedIndex = 0; });
                rows[0].querySelector('.custom-category-input').style.display = 'none';
            }
        }
    });

    rowsContainer.addEventListener('change', function (e) {
        if (e.target.matches('.category-select')) {
            var customInput = e.target.closest('.custom-add-row').querySelector('.custom-category-input');
            customInput.style.display = (e.target.value === '__custom__') ? '' : 'none';
        }
    });
})();
</script>
