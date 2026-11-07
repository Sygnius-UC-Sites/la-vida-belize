<style>
.property-gallery-manager {
    display: grid;
    gap: 14px;
}
.gallery-help {
    margin: 0;
    color: #64748b;
    font-size: 13px;
    line-height: 1.5;
}
.gallery-command-bar {
    position: sticky;
    top: 76px;
    z-index: 24;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 12px 14px;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    background: rgba(255,255,255,0.96);
    box-shadow: 0 12px 28px rgba(15,23,42,0.08);
}
.gallery-command-title {
    display: flex;
    align-items: center;
    gap: 10px;
    min-width: 180px;
    color: #0f172a;
    font-size: 14px;
    font-weight: 700;
}
.gallery-command-title i {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    border-radius: 9px;
    background: #eaf2ff;
    color: #1d4ed8;
}
.gallery-command-meta {
    display: block;
    margin-top: 2px;
    color: #64748b;
    font-size: 11px;
    font-weight: 500;
}
.gallery-command-actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    flex-wrap: wrap;
    gap: 8px;
}
.gallery-command-actions .btn {
    border-radius: 8px;
}
.gallery-upload-card,
.gallery-grid-shell {
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    background: #fff;
    box-shadow: 0 10px 24px rgba(15,23,42,0.05);
}
.gallery-upload-card {
    display: grid;
    grid-template-columns: minmax(180px, 260px) 1fr;
    gap: 16px;
    padding: 14px;
}
.gallery-upload-copy {
    padding: 6px 4px;
}
.gallery-upload-copy h5 {
    margin: 0 0 6px;
    color: #0f172a;
    font-size: 15px;
    font-weight: 700;
}
.gallery-upload-copy p {
    margin: 0;
    color: #64748b;
    font-size: 12px;
    line-height: 1.45;
}
.gallery-upload-card .input-images-2 {
    min-height: 116px;
}
.gallery-grid-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 12px 14px;
    border-bottom: 1px solid #e2e8f0;
}
.gallery-grid-title {
    margin: 0;
    color: #0f172a;
    font-size: 14px;
    font-weight: 700;
}
.gallery-grid-hint {
    margin: 2px 0 0;
    color: #64748b;
    font-size: 12px;
}
.gallery-grid-count {
    color: #475569;
    font-size: 12px;
    font-weight: 700;
}
.gallery-grid-scroll {
    max-height: min(68vh, 720px);
    overflow: auto;
    overscroll-behavior: contain;
    padding: 14px;
}
#gallery-grid.gallery-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(132px, 1fr));
    gap: 10px;
    align-items: start;
}
.gallery-card {
    min-width: 0;
}
.gallery-card.is-cover {
    grid-column: span 2;
    grid-row: span 2;
}
.gallery-card-frame {
    position: relative;
    aspect-ratio: 1 / 1;
    border-radius: 9px;
    overflow: hidden;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    cursor: grab;
    transition: box-shadow 0.12s, transform 0.12s, border-color 0.12s;
}
.gallery-card-frame:hover {
    border-color: #93c5fd;
    box-shadow: 0 12px 24px rgba(15,23,42,0.16);
    transform: translateY(-1px);
}
.gallery-card-frame:active { cursor: grabbing; }
.gallery-card-frame img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}
.gallery-select {
    position: absolute;
    top: 7px;
    left: 7px;
    z-index: 2;
    width: 18px;
    height: 18px;
    cursor: pointer;
}
.gallery-cover-badge {
    position: absolute;
    top: 7px;
    right: 7px;
    z-index: 2;
    background: #0f766e;
    color: #fff;
    padding: 4px 8px;
    border-radius: 999px;
    font-size: 10px;
    font-weight: 600;
    letter-spacing: 0.02em;
}
.gallery-category-badge {
    position: absolute;
    bottom: 7px;
    left: 7px;
    z-index: 2;
    max-width: calc(100% - 14px);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    background: rgba(15,23,42,0.72);
    color: #fff;
    padding: 4px 8px;
    border-radius: 999px;
    font-size: 10px;
    font-weight: 600;
}
.gallery-card-caption {
    margin-top: 6px;
    min-height: 32px;
    color: #475569;
    font-size: 11px;
    line-height: 1.35;
    overflow: hidden;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
}
.sortable-ghost {
    opacity: 0.4;
}
.sortable-chosen .gallery-card-frame {
    box-shadow: 0 0 0 3px #007bff;
}
/* Explicit move controls — reliable click/keyboard reordering that doesn't
   depend on drag-and-drop. Shown on hover (always visible on touch devices). */
.gallery-move-controls {
    position: absolute;
    bottom: 7px;
    right: 7px;
    z-index: 3;
    display: flex;
    gap: 4px;
    opacity: 0;
    transition: opacity 0.12s;
}
.gallery-card-frame:hover .gallery-move-controls { opacity: 1; }
.gallery-move {
    border: none;
    background: rgba(0,0,0,0.65);
    color: #fff;
    width: 28px;
    height: 28px;
    border-radius: 4px;
    font-size: 13px;
    line-height: 1;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}
.gallery-move:hover { background: #007bff; }
.gallery-move:disabled { opacity: 0.3; cursor: default; }
#photo-modal {
    background: rgba(15, 23, 42, 0.58);
    padding-left: 0 !important;
    z-index: 1110 !important;
    pointer-events: auto;
}
body:has(#photo-modal.show) .modal-backdrop.show,
#photo-modal + .modal-backdrop {
    z-index: 1100 !important;
}
#photo-modal.show {
    display: flex !important;
    align-items: flex-start;
    justify-content: center;
    overflow-y: auto !important;
    padding-top: 86px;
}
#photo-modal .modal-dialog {
    position: relative !important;
    top: auto !important;
    left: auto !important;
    transform: none !important;
    margin: 0 !important;
    max-width: min(860px, calc(100vw - 28px)) !important;
    width: min(860px, calc(100vw - 28px)) !important;
    max-height: calc(100vh - 104px);
    pointer-events: auto;
}
#photo-modal .modal-content {
    background: #fff;
    border: 0;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 24px 80px rgba(15,23,42,0.32);
    max-height: inherit;
    display: flex;
    flex-direction: column;
}
#photo-modal .modal-header,
#photo-modal .modal-footer {
    background: #fff;
    border-color: #e2e8f0;
}
#photo-modal .modal-body {
    background: #fff;
    flex: 1 1 auto;
    max-height: none;
    overflow: auto;
}
#photo-modal #modal-preview {
    display: block;
    max-height: 34vh !important;
    border-radius: 8px;
}
@media (hover: none) { .gallery-move-controls { opacity: 1; } }
@media (max-width: 767.98px) {
    .gallery-command-bar,
    .gallery-upload-card {
        display: flex;
        flex-direction: column;
        align-items: stretch;
    }
    .gallery-command-actions {
        justify-content: flex-start;
    }
    #gallery-grid.gallery-grid {
        grid-template-columns: repeat(auto-fill, minmax(106px, 1fr));
    }
    .gallery-card.is-cover {
        grid-column: span 1;
        grid-row: span 1;
    }
    #photo-modal .modal-dialog {
        max-height: calc(100vh - 90px);
    }
    #photo-modal.show {
        padding-top: 76px;
    }
    #photo-modal .modal-body {
        max-height: none;
    }
}
</style>

@isset($data)
    @php
        $photos = \App\Models\PropertyGallery::where('property_id', $data->id)
            ->orderBy('sorting')->orderBy('id')->get();
        $coverImage = $data->feature_image;
        $coverFilename = $coverImage ? basename($coverImage) : null;
        $categories = \Helper::getGalleryCategories();
    @endphp

    <div class="property-gallery-manager">
        <p class="gallery-help">
            Add, organize, and reorder property photos. Click a photo to edit the caption/category or set it as cover; drag photos or use the arrow buttons to change order.
        </p>

        {{-- Sticky command bar --}}
        <div class="gallery-command-bar">
            <div class="gallery-command-title">
                <i class="fa-solid fa-images"></i>
                <span>
                    {{ $photos->count() }} {{ $photos->count() === 1 ? 'photo' : 'photos' }}
                    <span class="gallery-command-meta">
                        @if ($coverImage)
                            Cover: {{ $coverFilename }}
                        @else
                            No cover photo set
                        @endif
                    </span>
                </span>
            </div>
            <div class="gallery-command-actions">
                <button type="button" class="btn btn-sm btn-outline-secondary" id="gallery-select-all">
                    <i class="fa-solid fa-check-square"></i> Select all
                </button>
                <button type="button" class="btn btn-sm btn-outline-danger" id="gallery-bulk-delete" disabled>
                    <i class="fa-solid fa-trash"></i> Delete selected (<span id="gallery-selected-count">0</span>)
                </button>
                <div class="btn-group btn-group-sm">
                    <button type="button" class="btn btn-outline-info dropdown-toggle" data-toggle="dropdown" id="gallery-bulk-cat-btn" disabled>
                        <i class="fa-solid fa-tag"></i> Move to
                    </button>
                    <div class="dropdown-menu">
                        @foreach ($categories as $cat => $label)
                            <a class="dropdown-item" href="javascript:;" data-bulk-category="{{ $cat }}">{{ $label }}</a>
                        @endforeach
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item text-muted" href="javascript:;" data-bulk-category="">Clear category</a>
                    </div>
                </div>
            </div>
        </div>

        {{-- Drop zone --}}
        <div class="gallery-upload-card">
            <div class="gallery-upload-copy">
                <h5><i class="fa-solid fa-cloud-arrow-up"></i> Upload photos</h5>
                <p>Drag photos here or click to select. JPG, PNG, WebP, and GIF are supported.</p>
            </div>
            <div class="input-images-2"></div>
        </div>

        {{-- Photo grid --}}
        <div class="gallery-grid-shell">
            <div class="gallery-grid-header">
                <div>
                    <h5 class="gallery-grid-title">Photo library</h5>
                    <p class="gallery-grid-hint">The cover photo is shown larger. The photo area scrolls independently when there are many images.</p>
                </div>
                <span class="gallery-grid-count">{{ $photos->count() }} total</span>
            </div>
            <div class="gallery-grid-scroll">
                @if ($photos->isEmpty())
                    <div class="text-center" style="padding: 3rem 1rem;">
                        <p class="text-muted mb-0">No photos yet. Drag some into the uploader above to get started.</p>
                    </div>
                @else
                    <div class="gallery-grid" id="gallery-grid">
                        @foreach ($photos as $photo)
                            <div class="gallery-card {{ $photo->image === $coverImage ? 'is-cover' : '' }}"
                                 data-photo-id="{{ $photo->id }}"
                                 data-image-src="{{ asset($photo->image) }}"
                                 data-caption="{{ $photo->caption }}"
                                 data-category="{{ $photo->category }}">
                                <div class="gallery-card-frame" data-photo-open>
                                    <input type="checkbox" class="gallery-select" value="{{ $photo->id }}" onclick="event.stopPropagation();">
                                    @if ($photo->image === $coverImage)
                                        <span class="gallery-cover-badge">Cover</span>
                                    @endif
                                    @if ($photo->category && isset($categories[$photo->category]))
                                        <span class="gallery-category-badge">{{ $categories[$photo->category] }}</span>
                                    @endif
                                    <img src="{{ asset($photo->image) }}" alt="{{ $photo->caption ?: 'Property photo' }}" loading="lazy">
                                    <div class="gallery-move-controls">
                                        <button type="button" class="gallery-move" data-move="left" title="Move earlier" aria-label="Move photo earlier" onclick="event.stopPropagation();">&#9664;</button>
                                        <button type="button" class="gallery-move" data-move="right" title="Move later" aria-label="Move photo later" onclick="event.stopPropagation();">&#9654;</button>
                                    </div>
                                </div>
                                <div class="gallery-card-caption">{{ $photo->caption ?: '(no caption)' }}</div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Modal editor --}}
    <div id="photo-modal" class="modal fade" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit photo</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <img id="modal-preview" src="" alt="" style="width: 100%; max-height: 60vh; object-fit: contain; background: #000;">
                    <div class="form-group mt-3">
                        <label>Caption</label>
                        <input type="text" id="modal-caption" class="form-control" placeholder="(no caption)">
                        <small class="text-muted">Saves automatically when you click outside the field.</small>
                    </div>
                    <div class="form-group">
                        <label>Category</label>
                        <select id="modal-category" class="form-control">
                            <option value="">(no category)</option>
                            @foreach ($categories as $cat => $label)
                                <option value="{{ $cat }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        <small class="text-muted">Used to group photos in the public "Show all photos" lightbox.</small>
                    </div>
                </div>
                <div class="modal-footer justify-content-between">
                    <span>
                        <button type="button" class="btn btn-warning" id="modal-set-cover">★ Set as cover</button>
                        <button type="button" class="btn btn-danger" id="modal-delete">🗑 Delete photo</button>
                    </span>
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        var propertyId = {{ $data->id }};
        var csrfToken  = '{{ csrf_token() }}';
        var grid       = document.getElementById('gallery-grid');
        var modal      = $('#photo-modal');
        var modalImg   = document.getElementById('modal-preview');
        var modalCap   = document.getElementById('modal-caption');
        var modalCat   = document.getElementById('modal-category');
        var modalCover = document.getElementById('modal-set-cover');
        var modalDel   = document.getElementById('modal-delete');
        var selectAllBtn = document.getElementById('gallery-select-all');
        var bulkDelBtn = document.getElementById('gallery-bulk-delete');
        var bulkCatBtn = document.getElementById('gallery-bulk-cat-btn');
        var selectedCountEl = document.getElementById('gallery-selected-count');
        var currentPhotoId = null;

        function selectedIds() {
            return Array.from(document.querySelectorAll('.gallery-select:checked')).map(function (c) { return c.value; });
        }

        function refreshBulkButtons() {
            var n = selectedIds().length;
            selectedCountEl.textContent = n;
            bulkDelBtn.disabled = (n === 0);
            bulkCatBtn.disabled = (n === 0);
        }

        if (grid) {
            grid.addEventListener('change', function (e) {
                if (e.target.matches('.gallery-select')) refreshBulkButtons();
            });
        }

        if (selectAllBtn) {
            selectAllBtn.addEventListener('click', function () {
                var boxes = document.querySelectorAll('.gallery-select');
                var allChecked = Array.from(boxes).every(function (b) { return b.checked; });
                boxes.forEach(function (b) { b.checked = !allChecked; });
                refreshBulkButtons();
            });
        }

        function postForm(action, fields) {
            var f = document.createElement('form');
            f.method = 'POST';
            f.action = action;
            f.style.display = 'none';
            var csrf = document.createElement('input');
            csrf.type = 'hidden'; csrf.name = '_token'; csrf.value = csrfToken;
            f.appendChild(csrf);
            Object.keys(fields).forEach(function (k) {
                var val = fields[k];
                if (Array.isArray(val)) {
                    val.forEach(function (v) {
                        var i = document.createElement('input');
                        i.type = 'hidden'; i.name = k + '[]'; i.value = v;
                        f.appendChild(i);
                    });
                } else {
                    var i = document.createElement('input');
                    i.type = 'hidden'; i.name = k; i.value = val;
                    f.appendChild(i);
                }
            });
            document.body.appendChild(f);
            f.submit();
        }

        if (bulkDelBtn) {
            bulkDelBtn.addEventListener('click', function () {
                var ids = selectedIds();
                if (ids.length === 0) return;
                if (!confirm('Delete ' + ids.length + ' selected photo(s)?')) return;
                postForm('{{ url("admin/properties/".$data->id."/gallery/bulk-delete") }}', { ids: ids });
            });
        }

        document.querySelectorAll('[data-bulk-category]').forEach(function (a) {
            a.addEventListener('click', function () {
                var ids = selectedIds();
                if (ids.length === 0) return;
                var cat = a.getAttribute('data-bulk-category');
                postForm('{{ url("admin/properties/".$data->id."/gallery/bulk-update-category") }}', {
                    ids: ids,
                    category: cat
                });
            });
        });

        // Click-to-open modal
        if (grid) {
            grid.addEventListener('click', function (e) {
                if (e.target.matches('.gallery-select')) return;
                var card = e.target.closest('.gallery-card');
                if (!card) return;
                currentPhotoId = card.getAttribute('data-photo-id');
                modalImg.src = card.getAttribute('data-image-src');
                modalCap.value = card.getAttribute('data-caption') || '';
                modalCat.value = card.getAttribute('data-category') || '';
                modal.one('shown.bs.modal', function () {
                    var modalNode = document.getElementById('photo-modal');
                    if (modalNode) modalNode.scrollTop = 0;
                    var bodyNode = modalNode ? modalNode.querySelector('.modal-body') : null;
                    if (bodyNode) bodyNode.scrollTop = 0;
                });
                var modalNode = document.getElementById('photo-modal');
                if (modalNode && modalNode.parentNode !== document.body) {
                    document.body.appendChild(modalNode);
                }
                modal.modal('show');
            });
        }

        // Caption autosave on blur
        modalCap.addEventListener('blur', function () {
            if (!currentPhotoId) return;
            fetch('{{ url("admin/properties/".$data->id."/gallery") }}/' + currentPhotoId, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({ caption: modalCap.value })
            }).then(function () {
                var card = document.querySelector('.gallery-card[data-photo-id="' + currentPhotoId + '"]');
                if (card) {
                    card.setAttribute('data-caption', modalCap.value);
                    var capEl = card.querySelector('.gallery-card-caption');
                    if (capEl) capEl.textContent = modalCap.value || '(no caption)';
                }
            });
        });

        // Category autosave on change
        modalCat.addEventListener('change', function () {
            if (!currentPhotoId) return;
            var newCat = modalCat.value;
            fetch('{{ url("admin/properties/".$data->id."/gallery") }}/' + currentPhotoId, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({ category: newCat })
            }).then(function () {
                var card = document.querySelector('.gallery-card[data-photo-id="' + currentPhotoId + '"]');
                if (!card) return;
                card.setAttribute('data-category', newCat);
                var existing = card.querySelector('.gallery-category-badge');
                if (existing) existing.remove();
                if (newCat) {
                    var label = modalCat.options[modalCat.selectedIndex].text;
                    var badge = document.createElement('span');
                    badge.className = 'gallery-category-badge';
                    badge.textContent = label;
                    card.querySelector('.gallery-card-frame').appendChild(badge);
                }
            });
        });

        // Set as cover
        modalCover.addEventListener('click', function () {
            if (!currentPhotoId) return;
            postForm('{{ url("admin/properties/".$data->id."/gallery") }}/' + currentPhotoId + '/set-cover', {});
        });

        // Delete photo
        modalDel.addEventListener('click', function () {
            if (!currentPhotoId) return;
            if (!confirm('Delete this photo? This cannot be undone.')) return;
            fetch('{{ url("admin/image-delete-asset") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: 'id=' + encodeURIComponent(currentPhotoId) + '&_token=' + encodeURIComponent(csrfToken)
            }).then(function () {
                var card = document.querySelector('.gallery-card[data-photo-id="' + currentPhotoId + '"]');
                if (card) card.remove();
                modal.modal('hide');
                currentPhotoId = null;
                location.reload();
            });
        });

    });
    </script>

    <script>
    // Independent gallery drag-reorder init — kept OUT of the big handler above so an
    // unrelated error there can't stop it, and resilient to script load order (waits
    // for SortableJS) and to lazy rendering (runs even if DOMContentLoaded already fired).
    (function () {
        var csrfToken, base, grid;

        // Persist the current DOM order as the `sorting` value of each photo.
        // Shared by the drag-reorder (SortableJS onEnd) and the ◀ ▶ move buttons.
        function persistOrder() {
            var cards = grid.querySelectorAll('.gallery-card');
            var reqs = [];
            cards.forEach(function (card, idx) {
                var photoId = card.getAttribute('data-photo-id');
                reqs.push(fetch(base + '/' + photoId, {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                    credentials: 'same-origin',
                    body: JSON.stringify({ sorting: idx })
                }).then(function (r) { if (!r.ok) throw new Error('HTTP ' + r.status); }));
            });
            return Promise.all(reqs)
                .then(function () { if (window.toastr) toastr.success('Photo order saved'); })
                .catch(function (e) {
                    if (window.toastr) toastr.error('Could not save photo order (' + e.message + ')');
                    else alert('Could not save photo order: ' + e.message);
                });
        }

        // Disable ◀ on the first card and ▶ on the last so the controls
        // communicate the boundaries.
        function refreshMoveButtons() {
            var cards = grid.querySelectorAll('.gallery-card');
            cards.forEach(function (card, idx) {
                var left = card.querySelector('.gallery-move[data-move="left"]');
                var right = card.querySelector('.gallery-move[data-move="right"]');
                if (left) left.disabled = (idx === 0);
                if (right) right.disabled = (idx === cards.length - 1);
            });
        }

        // ◀ ▶ click handler — swaps the card with its neighbour in the DOM,
        // then persists the new order. Reliable, no drag needed.
        function onMoveClick(e) {
            var btn = e.target.closest('.gallery-move');
            if (!btn) return;
            e.preventDefault();
            e.stopPropagation();
            var card = btn.closest('.gallery-card');
            if (!card) return;
            if (btn.getAttribute('data-move') === 'left') {
                var prev = card.previousElementSibling;
                if (prev) card.parentNode.insertBefore(card, prev);
            } else {
                var next = card.nextElementSibling;
                if (next) card.parentNode.insertBefore(next, card);
            }
            refreshMoveButtons();
            persistOrder();
        }

        function initGallerySortable() {
            grid = document.getElementById('gallery-grid');
            if (!grid) return;
            if (grid.dataset.sortableReady) return;
            csrfToken = '{{ csrf_token() }}';
            base = '{{ url("admin/properties/".$data->id."/gallery") }}';

            // Wire the always-available ◀ ▶ buttons immediately (independent of
            // SortableJS — works even if the drag library never loads). Bound
            // directly per-button (not delegated) so the buttons can stop the
            // click from bubbling to the card-opens-modal handler.
            grid.querySelectorAll('.gallery-move').forEach(function (btn) {
                btn.addEventListener('click', onMoveClick);
            });
            refreshMoveButtons();

            // Drag-and-drop is a progressive enhancement on top of the buttons.
            if (typeof Sortable === 'undefined') return setTimeout(function () {
                if (typeof Sortable !== 'undefined' && !grid.dataset.dragReady) attachDrag();
            }, 200);
            attachDrag();
        }

        function attachDrag() {
            if (grid.dataset.dragReady) return;
            grid.dataset.dragReady = '1';
            grid.dataset.sortableReady = '1';
            Sortable.create(grid, {
                animation: 150,
                handle: '.gallery-card-frame',
                ghostClass: 'sortable-ghost',
                chosenClass: 'sortable-chosen',
                filter: '.gallery-move',          // don't start a drag from the move buttons
                onEnd: function () { refreshMoveButtons(); persistOrder(); }
            });
        }

        if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initGallerySortable);
        else initGallerySortable();
    })();
    </script>
@endisset
