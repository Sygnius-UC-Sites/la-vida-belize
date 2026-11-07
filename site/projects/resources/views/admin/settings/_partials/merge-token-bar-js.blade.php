{{-- Click-handler + styles for the merge-token chip bar.
     Single event-delegated handler so any number of chip bars on a page
     work without per-bar wiring.

     Phase 3 of the rental-agreement rebuild. --}}
<style>
    .merge-token-bar {
        margin-bottom: 8px;
        padding: 10px 12px;
        background: #faf3e6;
        border: 1px solid #e8c896;
        border-radius: 8px;
    }
    .merge-token-bar__head {
        display: flex;
        align-items: baseline;
        gap: 12px;
        margin-bottom: 8px;
        flex-wrap: wrap;
    }
    .merge-token-bar__head strong {
        color: #7a5818;
        font-size: 13px;
        text-transform: uppercase;
        letter-spacing: .04em;
    }
    .merge-token-group {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 6px;
        margin-bottom: 6px;
    }
    .merge-token-group:last-child { margin-bottom: 0; }
    .merge-token-group__label {
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: .06em;
        color: #94613a;
        font-weight: 700;
        margin-right: 6px;
        min-width: 92px;
    }
    .merge-token-chip {
        background: #fff;
        border: 1px solid #d9a24a;
        color: #7a5818;
        font-size: 12.5px;
        font-weight: 600;
        padding: 3px 10px;
        border-radius: 999px;
        cursor: pointer;
        transition: background .15s, color .15s, transform .1s;
        font-family: inherit;
    }
    .merge-token-chip:hover {
        background: #d9a24a;
        color: #fff;
        transform: translateY(-1px);
    }
    .merge-token-chip:active { transform: translateY(0); }
    .merge-token-bar__flash {
        display: inline-block;
        margin-left: 8px;
        font-size: 12px;
        color: #047857;
        opacity: 0;
        transition: opacity .15s;
    }
    .merge-token-bar__flash.show { opacity: 1; }
</style>

<script>
(function () {
    /* Insert a token literal at the current cursor position of a CKEditor
       instance. Falls back to writing into the underlying textarea if
       CKEditor isn't (yet) instantiated. */
    function insertToken(editorId, tokenLiteral) {
        if (window.CKEDITOR && CKEDITOR.instances && CKEDITOR.instances[editorId]) {
            CKEDITOR.instances[editorId].insertText(tokenLiteral);
            CKEDITOR.instances[editorId].focus();
            return true;
        }
        // Pre-CKEditor-instantiation fallback. Append to the raw textarea so
        // a value isn't lost.
        var ta = document.getElementById(editorId);
        if (!ta) return false;
        var pos = ta.selectionStart || ta.value.length;
        ta.value = ta.value.slice(0, pos) + tokenLiteral + ta.value.slice(ta.selectionEnd || pos);
        ta.dispatchEvent(new Event('change', { bubbles: true }));
        return true;
    }

    /* Brief "✓ inserted" flash next to the bar so the admin sees feedback
       (CKEditor's content area is often scrolled out of view when you click
       a chip in the bar above). */
    function flash(bar, msg) {
        var head = bar.querySelector('.merge-token-bar__head');
        if (!head) return;
        var fl = head.querySelector('.merge-token-bar__flash');
        if (!fl) {
            fl = document.createElement('span');
            fl.className = 'merge-token-bar__flash';
            head.appendChild(fl);
        }
        fl.textContent = msg;
        fl.classList.add('show');
        setTimeout(function () { fl.classList.remove('show'); }, 1300);
    }

    document.addEventListener('click', function (e) {
        var chip = e.target.closest && e.target.closest('.merge-token-chip');
        if (!chip) return;
        e.preventDefault();
        var bar = chip.closest('.merge-token-bar');
        var editorId = bar && bar.getAttribute('data-for-editor');
        var token = chip.getAttribute('data-token');
        if (!editorId || !token) return;
        if (insertToken(editorId, token)) {
            flash(bar, '✓ inserted');
        }
    });
})();
</script>
