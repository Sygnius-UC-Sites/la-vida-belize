{{-- Shared client-side handler for the .rental-doc-upload widget.
     Used on /admin/settings/rental-agreement (global) AND on
     /admin/properties/{id}/edit Booking Rules tab (per-property).
     Both pages render the same widget markup; this script handles upload
     → AJAX convert → CKEditor pre-fill.

     Phase 2 of the rental-agreement rebuild
     (plan: docs/superpowers/plans/2026-05-08-rental-agreement-rebuild.md). --}}
<script>
(function () {
    var endpoint = @json(route('admin.rental-agreement.convert-doc'));
    var token    = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

    document.addEventListener('change', function (e) {
        var input = e.target.closest && e.target.closest('[data-rental-doc-input]');
        if (!input) return;
        var btn = input.closest('.rental-doc-upload').querySelector('[data-rental-doc-upload]');
        if (btn) btn.disabled = !(input.files && input.files.length);
    }, true);

    document.addEventListener('click', function (e) {
        var btn = e.target.closest && e.target.closest('[data-rental-doc-upload]');
        if (!btn) return;
        var wrap = btn.closest('.rental-doc-upload');
        var input = wrap.querySelector('[data-rental-doc-input]');
        var statusEl = wrap.querySelector('.rental-doc-status');
        if (!input.files || !input.files.length) return;

        var fd = new FormData();
        fd.append('file', input.files[0]);
        fd.append('_token', token);
        fd.append('context', wrap.dataset.context || 'global');
        var pid = wrap.dataset.propertyId;
        if (pid) fd.append('property_id', pid);

        btn.disabled = true;
        var origLabel = btn.textContent;
        btn.textContent = 'Converting…';
        statusEl.style.display = 'block';
        statusEl.className = 'rental-doc-status mt-2 text-muted';
        statusEl.textContent = 'Reading the document…';

        fetch(endpoint, {
            method: 'POST',
            body: fd,
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json', 'X-CSRF-TOKEN': token },
            credentials: 'same-origin',
        })
        .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
        .then(function (res) {
            if (!res.j || !res.j.ok) {
                statusEl.className = 'rental-doc-status mt-2 text-danger';
                statusEl.textContent = (res.j && res.j.error) || 'Conversion failed.';
                return;
            }
            // Locate the CKEditor textarea this widget targets. Search the
            // sibling form-group so multiple upload widgets on one page can
            // each map to their own editor (e.g. property-edit has rental
            // agreement + house rules editors side-by-side in future).
            var ta = wrap.parentElement.querySelector('textarea.ckeditor')
                  || wrap.closest('.form-group').querySelector('textarea.ckeditor');
            if (window.CKEDITOR && ta && CKEDITOR.instances[ta.id]) {
                CKEDITOR.instances[ta.id].setData(res.j.html);
            } else if (ta) {
                ta.value = res.j.html;
            }
            statusEl.className = 'rental-doc-status mt-2 text-success';
            var msg = '✓ Loaded <strong>' + res.j.source_doc_filename + '</strong> (' +
                      Math.round(res.j.source_doc_size / 1024) + ' KB).';
            // Smart-tokenize summary (Phase 2). Show what was auto-converted
            // so the admin knows what to spot-check before saving.
            if (res.j.tokens_replaced > 0) {
                var pairs = [];
                Object.keys(res.j.tokens_breakdown || {}).forEach(function (t) {
                    pairs.push(res.j.tokens_breakdown[t] + '× <code>{{' + t + '}}</code>');
                });
                msg += '<br><span style="color:#7a5818;">Auto-detected ' + res.j.tokens_replaced +
                       ' placeholder' + (res.j.tokens_replaced === 1 ? '' : 's') +
                       ' and converted to merge tokens: ' + pairs.join(', ') + '.</span>';
            }
            msg += '<br>Review and click Save to commit.';
            statusEl.innerHTML = msg;
            input.value = '';
        })
        .catch(function () {
            statusEl.className = 'rental-doc-status mt-2 text-danger';
            statusEl.textContent = 'Network error during upload.';
        })
        .finally(function () {
            btn.disabled = false;
            btn.textContent = origLabel;
        });
    });
})();
</script>
