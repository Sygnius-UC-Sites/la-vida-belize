@extends('admin.layouts')
@section('title', 'Email Template Previews')

@section('content_header')
    <h1 class="m-0 text-dark">Email Template Previews</h1>
@stop

@section('css')
    @parent
    <style>
        body.et-page { background: #f4f6f8; }
        .et-intro {
            max-width: 1200px;
            margin: 12px auto 24px;
            padding: 18px 22px;
            background: #fff;
            border: 1px solid #e6e8ee;
            border-radius: 12px;
            color: #1f2937;
        }
        .et-intro h2 { margin: 0 0 6px; font-size: 18px; }
        .et-intro p  { margin: 0; color: #4b5563; font-size: 14px; line-height: 1.55; }
        .et-grid {
            max-width: 1200px;
            margin: 0 auto 60px;
            display: grid;
            grid-template-columns: 1fr;
            gap: 28px;
        }
        @media (min-width: 1100px) { .et-grid { grid-template-columns: 1fr 1fr; } }
        .et-card {
            background: #fff;
            border: 1px solid #e6e8ee;
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(15, 23, 42, .04);
            display: flex;
            flex-direction: column;
        }
        .et-card__head {
            padding: 14px 18px;
            border-bottom: 1px solid #e6e8ee;
            background: #fafafb;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }
        .et-card__title {
            margin: 0;
            font-size: 15px;
            font-weight: 700;
            color: #0f172a;
            letter-spacing: -.01em;
        }
        .et-card__when {
            margin: 2px 0 0;
            font-size: 12px;
            color: #6b7280;
        }
        .et-card__actions a {
            color: #d9a24a;
            font-weight: 600;
            font-size: 13px;
            text-decoration: none;
        }
        .et-card__actions a:hover { text-decoration: underline; }
        .et-iframe-wrap {
            position: relative;
            background: #fafafb;
            min-height: 520px;
            max-height: 700px;
            overflow: hidden;
        }
        .et-iframe-wrap iframe {
            width: 100%;
            height: 700px;
            border: 0;
            display: block;
            background: #fafafb;
        }
        .et-card__head .et-type-pill {
            display: inline-block;
            padding: 2px 8px;
            background: #faf3e6;
            border: 1px solid #e8c896;
            color: #7a5818;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .04em;
            text-transform: uppercase;
        }

        /* Attachment manager — sits between the card head and the iframe.
           One row per file with name, size, delete button. The `+ Add file`
           tile triggers the hidden file input, which posts via fetch + a
           DataTransfer-style FormData. The whole panel is JS-driven so
           uploads don't require a page reload. */
        .et-attach {
            padding: 12px 18px 14px;
            border-bottom: 1px solid #e6e8ee;
            background: #fff;
        }
        .et-attach__title {
            font-size: 11.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .05em;
            color: #6b7280;
            margin: 0 0 8px;
        }
        .et-attach__list {
            list-style: none;
            margin: 0 0 8px;
            padding: 0;
        }
        .et-attach__row {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 6px 8px;
            background: #fafafb;
            border: 1px solid #ececef;
            border-radius: 8px;
            margin-bottom: 6px;
            font-size: 13px;
        }
        .et-attach__row:last-child { margin-bottom: 0; }
        .et-attach__row .name {
            flex: 1;
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            color: #0f172a;
            font-weight: 600;
        }
        .et-attach__row .size {
            color: #6b7280;
            font-size: 12px;
            font-variant-numeric: tabular-nums;
        }
        .et-attach__row .del {
            background: none;
            border: 0;
            color: #b42318;
            cursor: pointer;
            font-size: 14px;
            padding: 4px 6px;
            border-radius: 6px;
        }
        .et-attach__row .del:hover { background: #fee2e2; }
        .et-attach__upload {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            background: #d9a24a;
            color: #fff;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            border: 0;
            transition: background .15s;
        }
        .et-attach__upload:hover { background: #c0883a; }
        .et-attach__upload[disabled] { opacity: .6; cursor: wait; }
        .et-attach__hint {
            display: block;
            margin-top: 6px;
            font-size: 11.5px;
            color: #9ca3af;
        }
        .et-attach__error {
            color: #b42318;
            font-size: 12px;
            margin-top: 6px;
            display: none;
        }
        .et-attach__error.show { display: block; }
        .et-attach__empty {
            color: #9ca3af;
            font-style: italic;
            font-size: 12.5px;
            margin: 0 0 8px;
        }
    </style>
@endsection

@section('content')
    <body class="et-page"></body>
    <div class="et-intro">
        <h2>Quick visual scan of every lifecycle email</h2>
        <p>This page is a <strong>scrollable gallery</strong> of all 11 emails rendered with sample data — useful when you want to eyeball them all at once. To <strong>edit</strong> subjects, body copy, send timing, or attachments, head to the <a href="{{ url('admin/settings/stay-communications') }}"><strong>Email Templates editor</strong></a>; every email has its own <em>Preview</em> + <em>Attachments</em> panel inline next to its edit fields, so you don't have to bounce back to this page.</p>
    </div>

    <div class="et-grid">
        @foreach($types as $type => $meta)
            <article class="et-card">
                <header class="et-card__head">
                    <div>
                        <h3 class="et-card__title">{{ $meta['label'] }} <span class="et-type-pill">{{ $type }}</span></h3>
                        <p class="et-card__when">{{ $meta['when'] }}</p>
                    </div>
                    <div class="et-card__actions">
                        <a href="{{ route('email-templates.render', ['type' => $type]) }}" target="_blank" rel="noopener">
                            Full preview &nearr;
                        </a>
                    </div>
                </header>
                {{-- Attachment manager (per-type) --}}
                <section class="et-attach" data-type="{{ $type }}">
                    <p class="et-attach__title">📎 Attachments — sent with every {{ strtolower($meta['label']) }}</p>
                    @php $files = $attachments[$type] ?? collect(); @endphp
                    <ul class="et-attach__list" data-attach-list>
                        @forelse($files as $f)
                            <li class="et-attach__row" data-attach-id="{{ $f->id }}">
                                <span class="name" title="{{ $f->original_name }}">{{ $f->original_name }}</span>
                                <span class="size">{{ $f->humanSize() }}</span>
                                <button type="button" class="del" data-attach-del="{{ $f->id }}" title="Remove">✕</button>
                            </li>
                        @empty
                            <li class="et-attach__empty" data-attach-empty>No attachments yet.</li>
                        @endforelse
                    </ul>
                    <label class="et-attach__upload">
                        + Add file
                        <input type="file" data-attach-upload hidden>
                    </label>
                    <span class="et-attach__hint">PDF, DOC, image, ZIP, ICS, audio/video. Max 10 MB per file, 20 MB total per email.</span>
                    <div class="et-attach__error" data-attach-error></div>
                </section>

                <div class="et-iframe-wrap">
                    <iframe src="{{ route('email-templates.render', ['type' => $type]) }}"
                            loading="lazy"
                            title="{{ $meta['label'] }} preview"></iframe>
                </div>
            </article>
        @endforeach
    </div>

    <script>
    /* Attachment manager wiring. One handler set per card via event delegation
       so it works for any number of cards. CSRF token from the meta tag is
       used for both POST (upload) and DELETE (remove) requests. The list re-
       renders client-side after each successful action — no page reload. */
    (function () {
        var csrf = document.querySelector('meta[name="csrf-token"]');
        var token = csrf ? csrf.content : '';

        function showError(scope, msg) {
            var box = scope.querySelector('[data-attach-error]');
            if (!box) return;
            box.textContent = msg;
            box.classList.add('show');
        }
        function clearError(scope) {
            var box = scope.querySelector('[data-attach-error]');
            if (!box) return;
            box.textContent = '';
            box.classList.remove('show');
        }
        function renderRow(att) {
            var li = document.createElement('li');
            li.className = 'et-attach__row';
            li.dataset.attachId = att.id;
            li.innerHTML =
                '<span class="name" title="' + att.original_name + '">' + att.original_name + '</span>' +
                '<span class="size">' + att.human_size + '</span>' +
                '<button type="button" class="del" data-attach-del="' + att.id + '" title="Remove">&times;</button>';
            return li;
        }

        document.addEventListener('change', function (e) {
            var input = e.target;
            if (!input.matches || !input.matches('[data-attach-upload]')) return;
            var scope = input.closest('.et-attach');
            if (!scope) return;
            var type = scope.dataset.type;
            var file = input.files && input.files[0];
            if (!file) return;
            clearError(scope);
            var btn = scope.querySelector('.et-attach__upload');
            if (btn) btn.setAttribute('disabled', 'disabled');

            var fd = new FormData();
            fd.append('file', file);
            fd.append('_token', token);
            fetch(@json(url('admin/email-templates')) + '/' + type + '/attachments', {
                method: 'POST',
                body: fd,
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json', 'X-CSRF-TOKEN': token },
                credentials: 'same-origin',
            })
            .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
            .then(function (res) {
                if (res.j && res.j.ok) {
                    var list = scope.querySelector('[data-attach-list]');
                    var empty = list && list.querySelector('[data-attach-empty]');
                    if (empty) empty.remove();
                    if (list) list.appendChild(renderRow(res.j.attachment));
                    input.value = '';
                } else {
                    showError(scope, (res.j && res.j.error) || 'Upload failed.');
                }
            })
            .catch(function () { showError(scope, 'Network error during upload.'); })
            .finally(function () { if (btn) btn.removeAttribute('disabled'); });
        }, true);

        document.addEventListener('click', function (e) {
            var btn = e.target.closest && e.target.closest('[data-attach-del]');
            if (!btn) return;
            if (!confirm('Remove this attachment? It will be deleted from disk.')) return;
            var id = btn.getAttribute('data-attach-del');
            var scope = btn.closest('.et-attach');
            clearError(scope);
            fetch(@json(url('admin/email-templates/attachments')) + '/' + id, {
                method: 'DELETE',
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json', 'X-CSRF-TOKEN': token },
                credentials: 'same-origin',
            })
            .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
            .then(function (res) {
                if (res.j && res.j.ok) {
                    var row = scope.querySelector('[data-attach-id="' + id + '"]');
                    if (row) row.remove();
                    var list = scope.querySelector('[data-attach-list]');
                    if (list && !list.querySelector('.et-attach__row')) {
                        var li = document.createElement('li');
                        li.className = 'et-attach__empty';
                        li.dataset.attachEmpty = '1';
                        li.textContent = 'No attachments yet.';
                        list.appendChild(li);
                    }
                } else {
                    showError(scope, (res.j && res.j.error) || 'Delete failed.');
                }
            })
            .catch(function () { showError(scope, 'Network error during delete.'); });
        });
    })();
    </script>
@stop
