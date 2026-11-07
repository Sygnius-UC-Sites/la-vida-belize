<div class="row mt-2">
    <div class="col-md-12">
        <div class="alert alert-info">
            <strong>Site navigation builder</strong>
            <small class="d-block">Drag rows to reorder. Click a row to edit its label or URL. Use <strong>Add link</strong> for a regular menu item, <strong>Add dropdown</strong> for a menu with sub-items, or <strong>Add featured property</strong> to auto-link to your primary property listing.</small>
        </div>
    </div>
</div>

<style>
.nav-builder { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 14px; }
.nav-builder-list { list-style: none; padding: 0; margin: 0; }
.nav-builder-list li { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; margin-bottom: 8px; }
.nav-builder-list li.dropdown-item-row { background: #fff; }
.nav-builder-row { display: flex; align-items: center; padding: 10px 12px; gap: 10px; }
.nav-builder-handle { color: #94a3b8; cursor: grab; font-size: 16px; }
.nav-builder-handle:active { cursor: grabbing; }
.nav-builder-info { flex: 1; min-width: 0; }
.nav-builder-info .nav-label { font-weight: 600; color: #0f172a; font-size: 14px; }
.nav-builder-info .nav-url { font-family: ui-monospace, "JetBrains Mono", Menlo, monospace; font-size: 12px; color: #64748b; padding: 2px 6px; background: #eef2f7; border: 1px solid #e2e8f0; border-radius: 4px; display: inline-block; margin-left: 6px; }
.nav-builder-info .nav-type-chip { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 11px; font-weight: 600; margin-left: 6px; }
.nav-builder-info .nav-type-link { background: #e0f2fe; color: #0369a1; }
.nav-builder-info .nav-type-dropdown { background: #eef0ff; color: #4338ca; }
.nav-builder-info .nav-type-property { background: #ffedd5; color: #b45309; }
.nav-builder-actions button { background: transparent; border: 1px solid #e2e8f0; color: #475569; border-radius: 6px; padding: 4px 10px; font-size: 12px; cursor: pointer; margin-left: 4px; }
.nav-builder-actions button:hover { background: #f8fafc; color: #0f172a; }
.nav-builder-actions button.btn-danger-soft { color: #b91c1c; }
.nav-builder-actions button.btn-danger-soft:hover { background: #fee2e2; }
.nav-builder-children { padding: 0 12px 10px 38px; }
.nav-builder-children ul { list-style: none; padding: 0; margin: 0; }
.nav-builder-children li { background: #fff; border: 1px solid #e2e8f0; border-radius: 6px; margin-top: 6px; }
.nav-builder-add-row { display: flex; gap: 8px; margin-top: 12px; }
.nav-builder-add-row button { background: #1e293b; color: #fff; border: 0; border-radius: 6px; padding: 8px 14px; font-size: 13px; font-weight: 600; cursor: pointer; }
.nav-builder-add-row button:hover { background: #0f172a; }
.nav-builder-add-row button.btn-secondary { background: #fff; color: #1e293b; border: 1px solid #cbd5e1; }
.nav-builder-add-row button.btn-secondary:hover { background: #f8fafc; }
.nav-edit-panel { background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 8px; padding: 12px; margin: 8px 12px 12px 12px; }
.nav-edit-panel .form-row { display: flex; gap: 10px; margin-bottom: 8px; }
.nav-edit-panel .form-row > div { flex: 1; }
.nav-edit-panel label { font-size: 12px; font-weight: 600; color: #475569; margin-bottom: 4px; display: block; }
.nav-edit-panel input { width: 100%; padding: 6px 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13px; height: 34px; }
.nav-edit-panel input:focus { outline: 0; border-color: #1e293b; box-shadow: 0 0 0 3px #eef2f7; }
.nav-edit-panel .panel-actions { display: flex; gap: 6px; justify-content: flex-end; margin-top: 4px; }
.nav-edit-panel .panel-actions button { padding: 6px 12px; font-size: 12px; border-radius: 6px; cursor: pointer; }
.nav-edit-panel .panel-actions .btn-save { background: #1e293b; color: #fff; border: 0; }
.nav-edit-panel .panel-actions .btn-cancel { background: transparent; color: #475569; border: 1px solid #cbd5e1; }
.nav-empty { text-align: center; padding: 30px; color: #64748b; font-size: 14px; }
</style>

<div class="row">
    <div class="col-md-12">
        <div class="nav-builder">
            <ul class="nav-builder-list" id="nav-builder-list"></ul>
            <div class="nav-builder-add-row">
                <button type="button" id="nav-add-link"><i class="fa fa-link"></i> Add link</button>
                <button type="button" class="btn-secondary" id="nav-add-dropdown"><i class="fa fa-folder"></i> Add dropdown</button>
                <button type="button" class="btn-secondary" id="nav-add-property"><i class="fa fa-home"></i> Add featured property</button>
            </div>
        </div>
        {{-- Hidden input — what actually gets saved. JSON regenerated from DOM on every change. --}}
        <input type="hidden" id="nav-menu-json" name="nav_menu" value='{{ ModelHelper::getDataFromSetting('nav_menu') ?: '[]' }}'>
    </div>
</div>

<script>
(function () {
    var $list = document.getElementById('nav-builder-list');
    var $hidden = document.getElementById('nav-menu-json');
    if (!$list || !$hidden) return;

    function escapeHtml(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    function loadMenu() {
        try {
            var raw = $hidden.value || '[]';
            var data = JSON.parse(raw);
            return Array.isArray(data) ? data : [];
        } catch (e) {
            return [];
        }
    }

    function saveMenu(menu) {
        $hidden.value = JSON.stringify(menu);
    }

    function typeChip(type) {
        if (type === 'dropdown') return '<span class="nav-type-chip nav-type-dropdown">Dropdown</span>';
        if (type === 'property') return '<span class="nav-type-chip nav-type-property">Featured property</span>';
        return '<span class="nav-type-chip nav-type-link">Link</span>';
    }

    function renderItem(item, index) {
        var li = document.createElement('li');
        li.dataset.index = index;
        li.dataset.type = item.type || 'link';

        var label = item.label || (item.type === 'property' ? '(featured property — auto-labelled)' : '(no label)');
        var url = item.type === 'property' ? '(auto)' : (item.url || '');

        var rowHtml = ''
            + '<div class="nav-builder-row">'
            + '  <span class="nav-builder-handle" title="Drag to reorder"><i class="fa fa-bars"></i></span>'
            + '  <div class="nav-builder-info">'
            + '    <span class="nav-label">' + escapeHtml(label) + '</span>'
            + (url && url !== '(auto)' ? '<code class="nav-url">' + escapeHtml(url) + '</code>' : '')
            + '    ' + typeChip(item.type || 'link')
            + '  </div>'
            + '  <div class="nav-builder-actions">'
            + (item.type === 'property' ? '' : '<button type="button" class="nav-edit-btn">Edit</button>')
            + '    <button type="button" class="nav-delete-btn btn-danger-soft">Delete</button>'
            + '  </div>'
            + '</div>';

        li.innerHTML = rowHtml;

        // Dropdown children
        if (item.type === 'dropdown') {
            var children = Array.isArray(item.children) ? item.children : [];
            var childrenWrap = document.createElement('div');
            childrenWrap.className = 'nav-builder-children';
            var childrenList = '<ul class="nav-children-list">';
            children.forEach(function (c, ci) {
                childrenList += '<li class="dropdown-item-row" data-child-index="' + ci + '">'
                    + '<div class="nav-builder-row">'
                    + '  <span class="nav-builder-handle"><i class="fa fa-bars"></i></span>'
                    + '  <div class="nav-builder-info">'
                    + '    <span class="nav-label">' + escapeHtml(c.label || '(no label)') + '</span>'
                    + '    <code class="nav-url">' + escapeHtml(c.url || '') + '</code>'
                    + '  </div>'
                    + '  <div class="nav-builder-actions">'
                    + '    <button type="button" class="nav-child-edit-btn">Edit</button>'
                    + '    <button type="button" class="nav-child-delete-btn btn-danger-soft">Delete</button>'
                    + '  </div>'
                    + '</div></li>';
            });
            childrenList += '</ul>';
            childrenList += '<div class="nav-builder-add-row" style="margin-top:8px;"><button type="button" class="btn-secondary nav-add-child-btn">+ Add sub-item</button></div>';
            childrenWrap.innerHTML = childrenList;
            li.appendChild(childrenWrap);
        }

        return li;
    }

    function render() {
        var menu = loadMenu();
        $list.innerHTML = '';
        if (menu.length === 0) {
            $list.innerHTML = '<div class="nav-empty">No menu items yet. Use the buttons below to add your first one.</div>';
            return;
        }
        menu.forEach(function (item, i) { $list.appendChild(renderItem(item, i)); });

        // Top-level Sortable
        if (window.Sortable) {
            new Sortable($list, {
                handle: '.nav-builder-handle',
                animation: 150,
                onEnd: function () {
                    var newOrder = [];
                    $list.querySelectorAll(':scope > li').forEach(function (el) {
                        newOrder.push(menu[el.dataset.index]);
                    });
                    saveMenu(newOrder);
                    render();
                },
            });
            // Per-dropdown children Sortable
            $list.querySelectorAll('.nav-children-list').forEach(function (childUl, parentIdx) {
                new Sortable(childUl, {
                    handle: '.nav-builder-handle',
                    animation: 150,
                    onEnd: function () {
                        var item = menu[parentIdx];
                        if (!item || item.type !== 'dropdown') return;
                        var newChildren = [];
                        childUl.querySelectorAll(':scope > li').forEach(function (el) {
                            newChildren.push(item.children[el.dataset.childIndex]);
                        });
                        item.children = newChildren;
                        saveMenu(menu);
                        render();
                    },
                });
            });
        }
    }

    function openEditPanel(li, item, onSave) {
        // Close any other open panels
        document.querySelectorAll('.nav-edit-panel').forEach(function (p) { p.remove(); });
        var panel = document.createElement('div');
        panel.className = 'nav-edit-panel';
        panel.innerHTML = ''
            + '<div class="form-row">'
            + '  <div><label>Label</label><input type="text" class="edit-label" value="' + escapeHtml(item.label || '') + '" placeholder="e.g. Home"></div>'
            + (item.type === 'dropdown' ? '' : '<div><label>URL</label><input type="text" class="edit-url" value="' + escapeHtml(item.url || '') + '" placeholder="e.g. / or our-story"></div>')
            + '</div>'
            + '<div class="panel-actions">'
            + '  <button type="button" class="btn-cancel">Cancel</button>'
            + '  <button type="button" class="btn-save">Save</button>'
            + '</div>';
        li.appendChild(panel);
        var labelInput = panel.querySelector('.edit-label');
        labelInput.focus();
        panel.querySelector('.btn-cancel').addEventListener('click', function () { panel.remove(); });
        panel.querySelector('.btn-save').addEventListener('click', function () {
            var label = labelInput.value.trim();
            var urlInput = panel.querySelector('.edit-url');
            var url = urlInput ? urlInput.value.trim() : null;
            if (!label) { labelInput.focus(); return; }
            onSave(label, url);
            panel.remove();
            render();
        });
    }

    // Add link
    document.getElementById('nav-add-link').addEventListener('click', function () {
        var menu = loadMenu();
        menu.push({ type: 'link', label: 'New link', url: '/' });
        saveMenu(menu); render();
        // Auto-open the editor for the new item
        var newLi = $list.querySelector('li[data-index="' + (menu.length - 1) + '"]');
        if (newLi) openEditPanel(newLi, menu[menu.length - 1], function (label, url) {
            var m = loadMenu(); m[m.length - 1].label = label; m[m.length - 1].url = url; saveMenu(m);
        });
    });

    // Add dropdown
    document.getElementById('nav-add-dropdown').addEventListener('click', function () {
        var menu = loadMenu();
        menu.push({ type: 'dropdown', label: 'New dropdown', children: [] });
        saveMenu(menu); render();
        var newLi = $list.querySelector('li[data-index="' + (menu.length - 1) + '"]');
        if (newLi) openEditPanel(newLi, menu[menu.length - 1], function (label, _url) {
            var m = loadMenu(); m[m.length - 1].label = label; saveMenu(m);
        });
    });

    // Add property
    document.getElementById('nav-add-property').addEventListener('click', function () {
        var menu = loadMenu();
        menu.push({ type: 'property' });
        saveMenu(menu); render();
    });

    // Delegated clicks: top-level edit/delete + child edit/delete + add-child
    $list.addEventListener('click', function (e) {
        var li = e.target.closest('li[data-index]');
        var menu = loadMenu();
        if (!li) return;
        var idx = parseInt(li.dataset.index, 10);
        var item = menu[idx];
        if (!item) return;

        if (e.target.classList.contains('nav-edit-btn') || e.target.closest('.nav-edit-btn')) {
            openEditPanel(li, item, function (label, url) {
                var m = loadMenu(); m[idx].label = label;
                if (item.type !== 'dropdown') m[idx].url = url;
                saveMenu(m);
            });
            return;
        }
        if (e.target.classList.contains('nav-delete-btn') || e.target.closest('.nav-delete-btn')) {
            if (!confirm('Delete "' + (item.label || 'item') + '"?')) return;
            var m = loadMenu(); m.splice(idx, 1); saveMenu(m); render();
            return;
        }

        var childLi = e.target.closest('li[data-child-index]');
        if (childLi && item.type === 'dropdown') {
            var ci = parseInt(childLi.dataset.childIndex, 10);
            if (e.target.classList.contains('nav-child-edit-btn') || e.target.closest('.nav-child-edit-btn')) {
                openEditPanel(childLi, item.children[ci] || {}, function (label, url) {
                    var m = loadMenu(); m[idx].children[ci] = { label: label, url: url }; saveMenu(m);
                });
                return;
            }
            if (e.target.classList.contains('nav-child-delete-btn') || e.target.closest('.nav-child-delete-btn')) {
                if (!confirm('Delete "' + (item.children[ci] && item.children[ci].label || 'item') + '"?')) return;
                var m = loadMenu(); m[idx].children.splice(ci, 1); saveMenu(m); render();
                return;
            }
        }

        if (e.target.classList.contains('nav-add-child-btn') || e.target.closest('.nav-add-child-btn')) {
            var m = loadMenu();
            m[idx].children = m[idx].children || [];
            m[idx].children.push({ label: 'New sub-item', url: '/' });
            saveMenu(m); render();
            // Auto-edit the new sub-item
            var refreshedLi = $list.querySelector('li[data-index="' + idx + '"]');
            var newChildLi = refreshedLi && refreshedLi.querySelector('li[data-child-index="' + (m[idx].children.length - 1) + '"]');
            if (newChildLi) openEditPanel(newChildLi, m[idx].children[m[idx].children.length - 1], function (label, url) {
                var mm = loadMenu(); mm[idx].children[mm[idx].children.length - 1] = { label: label, url: url }; saveMenu(mm);
            });
        }
    });

    render();

    // SortableJS is loaded by the admin layout at the BOTTOM of the page, so it
    // isn't defined yet when this inline script runs — render() then silently
    // skips the drag wiring and rows can't be reordered. Re-render once the
    // page (and Sortable) has finished loading to attach the drag handles.
    if (!window.Sortable) {
        window.addEventListener('load', function () {
            if (window.Sortable) render();
        });
    }
})();
</script>
