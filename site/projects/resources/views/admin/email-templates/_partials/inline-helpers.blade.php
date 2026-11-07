{{--
  Reusable helper block embedded after each template's subject+body pair
  on /admin/settings/stay-communications. Provides:

    • 👁 Preview — lazy-loads an iframe rendering the template with sample
                   data (route('email-templates.render')).
    • 📎 Attachments — shows admin-uploaded files for this email type and
                       lets the admin add / remove without leaving the page.

  Required props:
    $type           string  Email-type slug (welcome, checkin, …).
    $attachments    \Illuminate\Support\Collection  Pre-grouped attachments
                                                    for this type. May be
                                                    empty.
--}}
@php
    $files     = $attachments[$type] ?? collect();
    $previewId = 'tplp-' . $type;
    $attachId  = 'tpla-' . $type;
@endphp

<div class="tpl-inline-helpers" data-type="{{ $type }}">
    <div class="tpl-toolbar">
        <button type="button" class="btn btn-sm btn-outline-secondary tpl-toggle"
                data-target="#{{ $previewId }}">
            👁 Preview rendered email
        </button>
        <button type="button" class="btn btn-sm btn-outline-secondary tpl-toggle"
                data-target="#{{ $attachId }}">
            📎 Attachments <span class="badge badge-secondary tpl-attach-count">{{ $files->count() }}</span>
        </button>
        <a href="{{ route('email-templates.render', ['type' => $type]) }}" target="_blank" rel="noopener"
           class="btn btn-sm btn-link" title="Open the rendered preview in a new tab">
            Open in new tab ↗
        </a>
    </div>

    {{-- Preview iframe — lazily set src on first toggle so 11 cards don't all
         hammer the server on page load. --}}
    <div class="tpl-pane" id="{{ $previewId }}" hidden>
        <iframe class="tpl-preview-iframe"
                data-src="{{ route('email-templates.render', ['type' => $type]) }}"
                title="{{ $type }} preview"></iframe>
    </div>

    {{-- Attachment manager — same data shape as the standalone admin/email-templates page. --}}
    <div class="tpl-pane tpl-attach" id="{{ $attachId }}" data-type="{{ $type }}">
        <div class="tpl-attach__title">
            Files attached to every <code>{{ $type }}</code> send
        </div>
        <ul class="tpl-attach__list" data-attach-list>
            @forelse($files as $f)
                <li class="tpl-attach__row" data-attach-id="{{ $f->id }}">
                    <span class="name" title="{{ $f->original_name }}">{{ $f->original_name }}</span>
                    <span class="size">{{ $f->humanSize() }}</span>
                    <button type="button" class="del" data-attach-del="{{ $f->id }}" title="Remove">✕</button>
                </li>
            @empty
                <li class="tpl-attach__empty" data-attach-empty>No attachments yet.</li>
            @endforelse
        </ul>
        <label class="tpl-attach__upload">
            + Add file
            <input type="file" data-attach-upload hidden>
        </label>
        <span class="tpl-attach__hint">PDF, DOC, image, ZIP, ICS, audio/video. Max 10 MB / file, 20 MB total per email.</span>
        <div class="tpl-attach__error" data-attach-error></div>
    </div>
</div>
