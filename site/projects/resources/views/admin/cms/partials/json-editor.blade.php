{{--
    Phase 13P — generic JSON-array editor for CMS pages.

    Replaces a raw JSON textarea with a friendly repeater UI: per-row form fields,
    drag-to-reorder via Sortable.js, [×] delete, [+ Add row]. A hidden input
    re-serializes the JSON on every change so the controller's existing save path
    is untouched.

    Inputs (passed as $jeName + $jeValue + $jeSchema + $jeTitle + $jeHint + $jeAddLabel):
      - $jeName   (string)  — the form field name (e.g. "condo_tabs"). Hidden input emits JSON here.
      - $jeValue  (string|array|null) — current value (raw JSON string, decoded array, or null).
      - $jeSchema (array)   — list of field defs, each:
            ['key'=>'eyebrow','label'=>'Eyebrow','type'=>'text','placeholder'=>'…','help'=>'…','col'=>3,'rows'=>3]
        - type ∈ text|textarea|select|tags|number
        - col (1-12, default 12)  — Bootstrap column width inside the row
        - rows (textarea only, default 2)
        - options (select only) — assoc array key=>label
      - $jeTitle    (string, optional) — small heading above the editor
      - $jeHint     (string, optional) — help text under the heading
      - $jeAddLabel (string, optional, default "+ Add row")
      - $jeStringArray (bool, optional, default false) — TRUE means each item is a plain string
                                                         (not an object). Schema must be a single text field.

    Usage example:
      @include('admin.cms.partials.json-editor', [
          'jeName'   => 'amenities_list',
          'jeValue'  => $data->amenities_list ?? null,
          'jeTitle'  => 'Amenity cards',
          'jeHint'   => 'One card per amenity. Icon options: bed, wifi, map-pin.',
          'jeAddLabel' => '+ Add amenity card',
          'jeSchema' => [
              ['key'=>'icon','label'=>'Icon','type'=>'select','col'=>3,'options'=>['bed'=>'bed','wifi'=>'wifi','map-pin'=>'map-pin']],
              ['key'=>'title','label'=>'Title','type'=>'text','col'=>4],
              ['key'=>'desc','label'=>'Description','type'=>'textarea','col'=>5,'rows'=>2],
          ],
      ])
--}}

@php
    $jeId       = 'je-' . str_replace(['[', ']', "'", '"', '.'], '-', $jeName) . '-' . uniqid();
    $jeStringArray = $jeStringArray ?? false;
    $jeAddLabel = $jeAddLabel ?? '+ Add row';

    // Decode the current value into an array (regardless of input shape).
    $jeRaw = $jeValue;
    if (is_string($jeRaw) && trim($jeRaw) !== '') {
        $decoded = json_decode($jeRaw, true);
        $jeItems = is_array($decoded) ? $decoded : [];
    } elseif (is_array($jeRaw)) {
        $jeItems = $jeRaw;
    } else {
        $jeItems = [];
    }
@endphp

<style>
.je-card { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px; margin-top: 6px; }
.je-card .je-title { font-weight: 600; color: #0f172a; font-size: 13px; margin: 0 0 4px 0; }
.je-card .je-hint { color: #5b6473; font-size: 12px; margin: 0 0 10px 0; line-height: 1.5; }
.je-card .je-rows { display: flex; flex-direction: column; gap: 8px; }
.je-card .je-row { background: #fff; border: 1px solid #e2e8f0; border-radius: 6px; padding: 12px; position: relative; display: flex; align-items: flex-start; gap: 10px; }
.je-card .je-row .je-handle { color: #94a3b8; font-size: 16px; cursor: grab; padding-top: 22px; user-select: none; }
.je-card .je-row .je-handle:active { cursor: grabbing; }
.je-card .je-row .je-fields { flex: 1; min-width: 0; display: flex; flex-wrap: wrap; gap: 10px; }
.je-card .je-field { display: flex; flex-direction: column; min-width: 0; }
.je-card .je-field label { font-size: 11px; font-weight: 600; color: #5b6473; text-transform: uppercase; letter-spacing: .04em; margin: 0 0 4px 0; }
.je-card .je-field input, .je-card .je-field textarea, .je-card .je-field select { border: 1px solid #cbd5e1; border-radius: 6px; padding: 7px 10px; font-size: 13px; background: #fff; color: #0f172a; width: 100%; }
.je-card .je-field input:focus, .je-card .je-field textarea:focus, .je-card .je-field select:focus { outline: 0; border-color: #1e293b; box-shadow: 0 0 0 3px #eef2f7; }
.je-card .je-row .je-del { background: #fee2e2; color: #b42318; border: 1px solid #fca5a5; border-radius: 6px; padding: 5px 8px; font-size: 11px; cursor: pointer; align-self: flex-start; margin-top: 22px; flex-shrink: 0; }
.je-card .je-row .je-del:hover { background: #fecaca; }
.je-card .je-empty { color: #94a3b8; font-size: 13px; padding: 16px; text-align: center; font-style: italic; }
.je-card .je-add-btn { background: #1e293b; color: #fff; border: 0; border-radius: 6px; padding: 7px 14px; font-size: 13px; font-weight: 600; cursor: pointer; margin-top: 10px; }
.je-card .je-add-btn:hover { background: #0f172a; }
.je-card .je-img { display:flex; flex-direction:column; gap:6px; width:100%; }
.je-card .je-img-ctl { display:flex; align-items:center; gap:8px; }
.je-card .je-img-prev { width:38px; height:38px; object-fit:contain; border:1px solid #e2e8f0; border-radius:6px; background:#fff; }
.je-card .je-img-up { background:#eef2f7; border:1px solid #cbd5e1; border-radius:6px; padding:5px 10px; font-size:12px; font-weight:600; color:#0f172a; cursor:pointer; margin:0; }
.je-card .je-img-up:hover { background:#e2e8f0; }
.je-card .je-img-up.je-uploading { opacity:.6; pointer-events:none; }
</style>

<div class="je-card" data-je-id="{{ $jeId }}" data-je-string-array="{{ $jeStringArray ? '1' : '0' }}">
    @if(!empty($jeTitle)) <div class="je-title">{{ $jeTitle }}</div> @endif
    @if(!empty($jeHint))  <div class="je-hint">{{ $jeHint }}</div> @endif

    <input type="hidden" name="{{ $jeName }}" id="{{ $jeId }}-hidden" value="{{ json_encode($jeItems) }}">

    @php
        // Build a single "blank row" HTML once, used as both the empty-state add
        // template and the per-existing-row pattern.
        $renderRow = function ($item = []) use ($jeSchema, $jeStringArray) {
            $html  = '<div class="je-row">';
            $html .= '<span class="je-handle" title="Drag to reorder">⋮⋮</span>';
            $html .= '<div class="je-fields">';
            if ($jeStringArray) {
                $f = $jeSchema[0];
                $val = is_string($item) ? $item : '';
                $html .= '<div class="je-field" style="flex:1;min-width:0;">';
                $html .= '<label>' . e($f['label'] ?? 'Value') . '</label>';
                if (($f['type'] ?? '') === 'image') {
                    $isImg = $val !== '' && (\Illuminate\Support\Str::startsWith($val, ['http','/','front/','uploads/']) || \Illuminate\Support\Str::contains(strtolower($val), ['.png','.jpg','.jpeg','.svg','.webp','.gif']));
                    $prev  = $isImg ? (\Illuminate\Support\Str::startsWith($val,'http') ? $val : asset(ltrim($val,'/'))) : '';
                    $html .= '<div class="je-img">';
                    $html .= '<input type="text" data-je-key="0" class="je-img-path" value="' . e($val) . '" placeholder="' . e($f['placeholder'] ?? 'image path/URL') . '">';
                    $html .= '<div class="je-img-ctl">';
                    $html .= '<img class="je-img-prev" src="' . e($prev) . '"' . ($prev ? '' : ' style="display:none"') . ' alt="">';
                    $html .= '<label class="je-img-up">Upload image<input type="file" accept="image/*" class="je-img-file" hidden></label>';
                    $html .= '</div></div>';
                } else {
                    $html .= '<input type="text" data-je-key="0" value="' . e($val) . '" placeholder="' . e($f['placeholder'] ?? '') . '">';
                }
                $html .= '</div>';
            } else {
                foreach ($jeSchema as $f) {
                    $val = is_array($item) ? ($item[$f['key']] ?? '') : '';
                    if (($f['type'] ?? '') === 'tags' && is_array($val)) $val = implode(', ', $val);
                    $col = $f['col'] ?? 12;
                    $width = ($col / 12 * 100);
                    $type = $f['type'] ?? 'text';
                    $html .= '<div class="je-field" style="flex: 0 1 calc(' . $width . '% - 10px); min-width: 140px;">';
                    $html .= '<label>' . e($f['label']) . '</label>';
                    if ($type === 'textarea') {
                        $html .= '<textarea data-je-key="' . e($f['key']) . '" rows="' . e($f['rows'] ?? 2) . '" placeholder="' . e($f['placeholder'] ?? '') . '">' . e($val) . '</textarea>';
                    } elseif ($type === 'select') {
                        $html .= '<select data-je-key="' . e($f['key']) . '">';
                        $html .= '<option value="">—</option>';
                        foreach (($f['options'] ?? []) as $optVal => $optLabel) {
                            $sel = ((string)$val === (string)$optVal) ? ' selected' : '';
                            $html .= '<option value="' . e($optVal) . '"' . $sel . '>' . e($optLabel) . '</option>';
                        }
                        $html .= '</select>';
                    } elseif ($type === 'number') {
                        $html .= '<input type="number" data-je-key="' . e($f['key']) . '" value="' . e($val) . '" placeholder="' . e($f['placeholder'] ?? '') . '">';
                    } elseif ($type === 'image') {
                        $isImg = $val !== '' && (\Illuminate\Support\Str::startsWith($val, ['http','/','front/','uploads/']) || \Illuminate\Support\Str::contains(strtolower($val), ['.png','.jpg','.jpeg','.svg','.webp','.gif']));
                        $prev  = $isImg ? (\Illuminate\Support\Str::startsWith($val,'http') ? $val : asset(ltrim($val,'/'))) : '';
                        $html .= '<div class="je-img">';
                        $html .= '<input type="text" data-je-key="' . e($f['key']) . '" class="je-img-path" value="' . e($val) . '" placeholder="' . e($f['placeholder'] ?? 'image path/URL, or an icon class') . '">';
                        $html .= '<div class="je-img-ctl">';
                        $html .= '<img class="je-img-prev" src="' . e($prev) . '"' . ($prev ? '' : ' style="display:none"') . ' alt="">';
                        $html .= '<label class="je-img-up">⬆ Upload image<input type="file" accept="image/*" class="je-img-file" hidden></label>';
                        $html .= '</div></div>';
                    } elseif ($type === 'tags') {
                        $html .= '<input type="text" data-je-key="' . e($f['key']) . '" data-je-tags="1" value="' . e($val) . '" placeholder="' . e($f['placeholder'] ?? 'comma, separated, values') . '">';
                    } else {
                        $html .= '<input type="text" data-je-key="' . e($f['key']) . '" value="' . e($val) . '" placeholder="' . e($f['placeholder'] ?? '') . '">';
                    }
                    if (!empty($f['help'])) $html .= '<small style="color:#5b6473;font-size:11px;margin-top:3px;">' . e($f['help']) . '</small>';
                    $html .= '</div>';
                }
            }
            $html .= '</div>';
            $html .= '<button type="button" class="je-del" title="Remove this row"><i class="fa fa-trash"></i></button>';
            $html .= '</div>';
            return $html;
        };
    @endphp

    <div class="je-rows" id="{{ $jeId }}-rows">
        @foreach($jeItems as $idx => $item)
            {!! $renderRow($item) !!}
        @endforeach
    </div>

    {{-- Hidden template used by Add to spawn a fresh blank row, even when the editor starts empty. --}}
    <template class="je-template">{!! $renderRow([]) !!}</template>

    @if(empty($jeItems))
        <div class="je-empty" id="{{ $jeId }}-empty">No items yet. Click <strong>{{ $jeAddLabel }}</strong> to add the first one.</div>
    @endif

    <button type="button" class="je-add-btn" data-je-add="{{ $jeId }}">{{ $jeAddLabel }}</button>
</div>

@once
<script>
window.JE_UPLOAD_URL = "{{ route('je-upload') }}";
window.JsonEditor = window.JsonEditor || (function () {
    function jeUpload(card, fileInput) {
        if (!fileInput.files || !fileInput.files[0]) return;
        var wrap = fileInput.closest('.je-img');
        var pathInput = wrap.querySelector('.je-img-path');
        var prev = wrap.querySelector('.je-img-prev');
        var btn  = wrap.querySelector('.je-img-up');
        var token = (document.querySelector('meta[name=csrf-token]') || {}).content
                 || (document.querySelector('input[name=_token]') || {}).value || '';
        var fd = new FormData(); fd.append('file', fileInput.files[0]);
        if (btn) btn.classList.add('je-uploading');
        fetch(window.JE_UPLOAD_URL, {
            method: 'POST', body: fd, credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json' }
        }).then(function (r) { return r.json(); })
          .then(function (d) {
              if (d && d.path) {
                  pathInput.value = d.path;
                  if (prev) { prev.src = d.url || d.path; prev.style.display = ''; }
                  pathInput.dispatchEvent(new Event('input', { bubbles: true }));
              } else { alert('Image upload failed. Try a smaller image (max 5MB).'); }
          }).catch(function () { alert('Image upload failed.'); })
          .finally(function () { if (btn) btn.classList.remove('je-uploading'); fileInput.value = ''; });
    }
    function fieldsToObject(rowEl) {
        var out = {};
        rowEl.querySelectorAll('[data-je-key]').forEach(function (el) {
            var key = el.getAttribute('data-je-key');
            var val = el.value;
            if (el.getAttribute('data-je-tags') === '1') {
                val = String(val || '').split(',').map(function (s) { return s.trim(); }).filter(Boolean);
            }
            out[key] = val;
        });
        return out;
    }

    function fieldsToString(rowEl) {
        var inp = rowEl.querySelector('[data-je-key="0"]');
        return inp ? inp.value : '';
    }

    function reserialize(card) {
        var hidden = card.querySelector('input[type="hidden"]');
        var rows   = card.querySelectorAll('.je-row');
        var stringArray = card.getAttribute('data-je-string-array') === '1';
        var arr = [];
        rows.forEach(function (r) {
            arr.push(stringArray ? fieldsToString(r) : fieldsToObject(r));
        });
        if (hidden) hidden.value = JSON.stringify(arr);
    }

    function addRow(card) {
        var rowsContainer = card.querySelector('.je-rows');
        var template = card.querySelector('template.je-template');
        if (!template) return;
        // Clone the template's content; <template> contents live in template.content
        var frag = template.content.cloneNode(true);
        // The cloned fragment's first child is the row
        var row = frag.firstElementChild || frag.querySelector('.je-row');
        // Clear any leftover values (defensive — template should already be blank)
        row.querySelectorAll('input, textarea').forEach(function (i) { i.value = ''; });
        row.querySelectorAll('select').forEach(function (s) { s.selectedIndex = 0; });
        rowsContainer.appendChild(row);
        var emptyMsg = card.querySelector('.je-empty');
        if (emptyMsg) emptyMsg.style.display = 'none';
        reserialize(card);
    }

    function init(card) {
        // Sortable for drag reorder
        if (window.Sortable) {
            var rows = card.querySelector('.je-rows');
            new Sortable(rows, {
                handle: '.je-handle', animation: 150,
                onEnd: function () { reserialize(card); },
            });
        }
        // Wire input events (debounced via input event itself)
        card.addEventListener('input',  function () { reserialize(card); });
        card.addEventListener('change', function (e) {
            var fileInput = e.target.closest('.je-img-file');
            if (fileInput) { jeUpload(card, fileInput); return; }
            reserialize(card);
        });
        // Delete
        card.addEventListener('click', function (e) {
            var del = e.target.closest('.je-del');
            if (del) {
                var row = del.closest('.je-row');
                if (row && confirm('Remove this row?')) { row.remove(); reserialize(card); }
                return;
            }
            var add = e.target.closest('.je-add-btn');
            if (add) addRow(card);
        });
        reserialize(card); // ensure hidden is in sync on load
    }

    function initAll() {
        document.querySelectorAll('.je-card[data-je-id]').forEach(function (card) {
            if (card.dataset.jeInited) return;
            card.dataset.jeInited = '1';
            init(card);
        });
    }

    // Init on DOMContentLoaded; also expose for late callers.
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAll);
    } else {
        initAll();
    }
    return { init: init, initAll: initAll };
})();
</script>
@endonce
