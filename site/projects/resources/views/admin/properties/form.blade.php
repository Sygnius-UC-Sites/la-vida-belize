{{-- Phase 3 of property-edit redesign — single-section app with AJAX save.

     User feedback after Phase 2: the long-scroll layout is too much. Switch
     to a single-section view where only one section is visible at a time;
     clicking the nav swaps without scrolling or reload, and saves go via
     AJAX so the page never refreshes.

     All 12 sections still render in the DOM (CKEditor / image-uploader /
     calendar widgets keep state across switches). Only the active section
     is `display:block`. URL hash drives state so deep links + browser
     back/forward navigate between sections. --}}

@php
    /* Phase 2.1 — sections grouped by mental model so the 12-item nav reads
     * as 5 chunks instead of one long list. Group headers are quiet
     * uppercase labels in the sidebar; sections still render top-to-bottom
     * in the right column with visual headers.
     *
     * Mental groups:
     *   LISTING        — what the property IS (the listing card on Airbnb)
     *   PRICING & CAL  — what it costs + when it's available
     *   BOOKING FLOW   — how guests actually book
     *   GUEST STAY     — what guests get during the stay
     *   WEBSITE        — what shows up on the public site (detail page + SEO)
     */
    $peSections = [
        ['id' => 'listing',     'label' => 'Listing basics',           'icon' => '📋', 'partial' => 'main',                        'requiresData' => false, 'group' => 'Listing'],
        ['id' => 'photos',      'label' => 'Photos',                   'icon' => '🖼️', 'partial' => 'gallery-images',              'requiresData' => false, 'group' => 'Listing'],
        ['id' => 'amenities',   'label' => 'Amenities',                'icon' => '✨', 'partial' => 'amenities',                   'requiresData' => true,  'group' => 'Listing'],

        ['id' => 'pricing',     'label' => 'Pricing & taxes',          'icon' => '💰', 'partial' => 'pricing-taxes',               'requiresData' => true,  'group' => 'Pricing & calendar'],
        ['id' => 'calendar',    'label' => 'Calendar & rates',         'icon' => '📅', 'partial' => 'calendar',                    'requiresData' => true,  'group' => 'Pricing & calendar'],
        ['id' => 'fees',        'label' => 'Add-ons & extra fees',     'icon' => '➕', 'partial' => 'additional-fees',             'requiresData' => true,  'group' => 'Pricing & calendar'],

        ['id' => 'booking',     'label' => 'Booking settings',         'icon' => '🛏️', 'partial' => 'booking-settings',            'requiresData' => true,  'group' => 'Booking flow'],
        ['id' => 'rules',       'label' => 'House rules & cancellation','icon' => '📜', 'partial' => 'house-rules-cancellation',   'requiresData' => true,  'group' => 'Booking flow'],
        ['id' => 'rental',      'label' => 'Rental agreement',         'icon' => '✍️', 'partial' => 'rental-agreement',            'requiresData' => true,  'group' => 'Booking flow'],

        ['id' => 'stay',        'label' => 'Stay info & guest comms',  'icon' => '🔑', 'partial' => 'stay-info',                   'requiresData' => true,  'group' => 'Guest stay'],

        ['id' => 'detail',      'label' => 'Detail page (CMS)',        'icon' => '📰', 'partial' => 'detail-page',                 'requiresData' => false, 'group' => 'Public website'],
        ['id' => 'seo',         'label' => 'SEO',                      'icon' => '🔎', 'partial' => 'seo',                         'requiresData' => false, 'group' => 'Public website'],
    ];

    // Per-customer feature flags (hidden dev panel /admin/dev/features):
    // sections switched off there are removed for the customer. 'listing'
    // (basics) can never be hidden; developers always see every section.
    if (!\App\Helper\FeatureFlags::isDeveloper()) {
        $peSections = array_values(array_filter($peSections, function ($s) {
            return $s['id'] === 'listing'
                || \App\Helper\FeatureFlags::enabled('prop_section_' . $s['id']);
        }));
    }
@endphp

<style>
    /* ─── Layout ─── (Phase 3.1 — viewport-locked so the sections column is
       the ONLY scroll context. Nav and the page itself don't scroll on
       desktop, which kills the previous "page + section + menu all
       scrolling" feeling. The 220px offset accounts for AdminLTE chrome
       (top navbar + breadcrumbs + card header + body padding). On a tight
       viewport the min-height kicks in and the page falls back to its
       natural scroll — still better than three. */
    .pe-redesign-layout {
        display: grid;
        grid-template-columns: 240px 1fr;
        gap: 28px;
        align-items: stretch;
        /* 290px = AdminLTE chrome above (~177px: navbar + content-header +
           card-header + card padding) + sticky save bar below (~70px) +
           card padding bottom + content footer margin (~43px). Tuned so
           the page itself doesn't scroll on standard laptop viewports. */
        height: calc(100vh - 290px);
        min-height: 420px;
        margin-bottom: 0;
    }
    @media (max-width: 992px) {
        /* Mobile / narrow admin: collapse the nav to a horizontal scroll
           chip-bar above the sections. Drop the viewport lock — single
           column stacks naturally and inner scroll on a phone feels worse
           than page scroll. */
        .pe-redesign-layout {
            grid-template-columns: 1fr;
            height: auto;
            min-height: 0;
        }
        .pe-redesign-nav {
            position: sticky !important;
            top: 0;
            height: auto !important;
            overflow-y: visible !important;
            background: #fff;
            z-index: 5;
            display: flex;
            overflow-x: auto;
            white-space: nowrap;
            border-bottom: 1px solid #e6e8ee;
            padding: 8px 4px !important;
            margin: 0 -16px 16px;
        }
        .pe-redesign-nav a {
            display: inline-block !important;
            white-space: nowrap;
            padding: 6px 12px !important;
            border-radius: 999px !important;
            margin: 0 4px;
        }
        .pe-redesign-sections {
            height: auto !important;
            overflow: visible !important;
            padding-right: 0 !important;
        }
    }

    /* ─── Nav (height-bounded by parent grid row) ─── */
    .pe-redesign-nav {
        position: relative;
        height: 100%;
        max-height: 100%;
        overflow-y: auto;
        background: #fff;
        border: 1px solid #e6e8ee;
        border-radius: 12px;
        padding: 10px;
        box-shadow: 0 1px 3px rgba(15, 23, 42, .04);
        /* Quieter scrollbar — the nav fits in normal viewports so the
           bar rarely shows; when it does, keep it subtle. */
        scrollbar-width: thin;
    }
    .pe-redesign-nav::-webkit-scrollbar { width: 6px; }
    .pe-redesign-nav::-webkit-scrollbar-thumb {
        background: rgba(15, 23, 42, .15);
        border-radius: 3px;
    }
    .pe-redesign-nav a {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 9px 12px;
        border-radius: 8px;
        text-decoration: none;
        color: #475569;
        font-size: 13.5px;
        font-weight: 500;
        transition: background .12s, color .12s;
    }
    .pe-redesign-nav a:hover {
        background: #f1f5f9;
        color: #0f172a;
    }
    .pe-redesign-nav a.is-active {
        background: #15253f;
        color: #ffffff;
        font-weight: 600;
        box-shadow: 0 6px 16px -6px rgba(15, 28, 51, .35);
    }
    .pe-redesign-nav a.is-active .pe-nav-icon { color: #ffffff; }
    .pe-redesign-nav .pe-nav-icon {
        font-size: 16px;
        width: 22px;
        text-align: center;
        flex-shrink: 0;
    }
    .pe-redesign-nav .pe-nav-label {
        flex: 1;
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    /* Group headers — quiet uppercase chips that separate the nav into
       five mental chunks (Listing / Pricing / Booking / Stay / Website).
       Not clickable; pure visual scaffolding. */
    .pe-redesign-nav .pe-nav-group {
        padding: 14px 12px 6px;
        font-size: 10.5px;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: #94a3b8;
    }
    .pe-redesign-nav .pe-nav-group:first-child {
        padding-top: 4px;
    }
    /* On mobile (chip-bar mode), suppress the group headers — they don't
       fit the horizontal layout and the chips are already short enough. */
    @media (max-width: 992px) {
        .pe-redesign-nav .pe-nav-group { display: none; }
    }

    /* ─── Sections (the only scroll context on desktop) ─── */
    .pe-redesign-sections {
        height: 100%;
        overflow-y: auto;
        overflow-x: hidden;
        /* Tiny right-pad so the scrollbar never kisses the section card's
           right border. */
        padding-right: 4px;
        scrollbar-gutter: stable;
    }
    .pe-redesign-sections::-webkit-scrollbar { width: 8px; }
    .pe-redesign-sections::-webkit-scrollbar-thumb {
        background: rgba(15, 23, 42, .18);
        border-radius: 4px;
    }
    .pe-redesign-sections::-webkit-scrollbar-thumb:hover {
        background: rgba(15, 23, 42, .28);
    }
    .pe-redesign-sections > section {
        background: #fff;
        border: 1px solid #e6e8ee;
        border-radius: 14px;
        padding: 20px 22px 24px;
        /* Single-section view → only one section is visible at a time, so
           inter-section margin is wasted. Keep a tiny floor for the rare
           case multiple sections coexist (initial 250ms before single-mode
           kicks in). */
        margin-bottom: 0;
    }
    .pe-section-head {
        display: flex;
        align-items: center;
        gap: 10px;
        margin: -4px 0 12px;
        padding-bottom: 10px;
        border-bottom: 1px solid #f1f5f9;
    }
    .pe-section-head h2 {
        margin: 0;
        font-size: 17px;
        font-weight: 700;
        color: #0f172a;
        letter-spacing: -0.01em;
    }
    .pe-section-head .pe-icon {
        font-size: 22px;
        line-height: 1;
    }
    /* Hide the original `<h4 class="text-warning">` headings inside each
       partial — they duplicated the section's own heading. The partials
       now sit inside `<section>` wrappers that supply the heading; their
       internal h4 is redundant. */
    .pe-redesign-sections > section > h4.text-warning:first-of-type { display: none; }
    .pe-redesign-sections > section > h4.text-warning:first-of-type + p.text-muted { margin-top: -4px; }

    /* ─── Phase 3 single-section visibility ─── */
    /* Default: all sections visible (so widgets init at proper dimensions).
       The JS adds `pe-single-mode` to the container after widgets init,
       which then hides non-active sections via display:none. Inputs in
       hidden sections still submit — display:none doesn't disable them. */
    .pe-redesign-sections.pe-single-mode > section[data-pe-section]:not(.is-active) {
        display: none;
    }

    /* Brief green pulse on the nav item that was just saved — mitigation for
       the trade-off that AJAX save commits ALL sections, not just the
       visible one. Tells the admin "your changes landed". */
    @keyframes pe-saved-pulse {
        0%   { background: rgba(34, 197, 94, .25); }
        100% { background: transparent; }
    }
    .pe-redesign-nav a.pe-just-saved {
        animation: pe-saved-pulse 1.4s ease-out;
    }

    /* ─── Toast for AJAX save feedback ─── */
    .pe-toast-host {
        position: fixed;
        right: 24px;
        bottom: 24px;
        z-index: 1080;
        display: flex;
        flex-direction: column;
        gap: 10px;
        pointer-events: none;
    }
    .pe-toast {
        min-width: 240px;
        max-width: 360px;
        padding: 12px 16px;
        border-radius: 10px;
        background: #0f172a;
        color: #fff;
        font-size: 13.5px;
        line-height: 1.4;
        box-shadow: 0 8px 24px rgba(15, 23, 42, .18);
        pointer-events: auto;
        transform: translateY(8px);
        opacity: 0;
        transition: opacity .18s, transform .18s;
    }
    .pe-toast.is-show { opacity: 1; transform: translateY(0); }
    .pe-toast.pe-toast-success { background: #059669; }
    .pe-toast.pe-toast-error   { background: #991b1b; }
    .pe-toast strong { display: block; margin-bottom: 2px; }
    .pe-toast ul { margin: 4px 0 0; padding-left: 18px; font-size: 12.5px; }

    /* In-flight Save button state */
    .pe-sticky-save-bar .btn[disabled] { opacity: .65; cursor: progress; }

    /* ─── Sticky-bottom Save bar ─── */
    .pe-sticky-save-bar {
        position: sticky;
        bottom: 0;
        margin: 14px -16px -16px;
        padding: 14px 22px;
        background: #fff;
        border-top: 1px solid #e6e8ee;
        box-shadow: 0 -2px 10px rgba(15, 23, 42, .04);
        display: flex;
        gap: 10px;
        align-items: center;
        z-index: 4;
    }
    .pe-sticky-save-bar .pe-current-section {
        margin-left: auto;
        font-size: 12.5px;
        color: #6b7280;
    }
    .pe-sticky-save-bar .pe-current-section strong {
        color: #0f172a;
    }
    .pe-create-locked {
        border: 1px dashed #cbd5e1;
        border-radius: 10px;
        background: #f8fafc;
        padding: 18px;
        color: #475569;
    }
    .pe-create-locked h3 {
        margin: 0 0 6px;
        color: #0f172a;
        font-size: 16px;
        font-weight: 700;
    }
    .pe-create-locked p {
        margin: 0 0 12px;
        max-width: 720px;
        line-height: 1.5;
    }
    .pe-create-locked ol {
        margin: 0;
        padding-left: 20px;
    }
    .pe-create-locked li {
        margin: 4px 0;
    }
</style>

{{-- ─── Listing Studio: top horizontal pill nav ───
     Visible only when the page hosts a `.ls-hero` (i.e. the property edit
     page). On the create page (no hero), this stays hidden via the global
     CSS rule and the original left-nav layout below kicks in instead. --}}
<nav class="ls-pillbar" id="ls-pillbar" role="tablist" aria-label="Listing sections">
    @php $lastGroupPill = null; @endphp
    @foreach($peSections as $i => $sec)
        @if($sec['requiresData'] && !isset($data))
            @continue
        @endif
        @if(($sec['group'] ?? null) !== $lastGroupPill && $lastGroupPill !== null)
            <span class="ls-pill-divider" aria-hidden="true"></span>
        @endif
        @php $lastGroupPill = $sec['group'] ?? null; @endphp
        <a href="#{{ $sec['id'] }}" data-pe-nav="{{ $sec['id'] }}" role="tab">
            <span class="ls-pill-icon">{{ $sec['icon'] }}</span>
            <span class="ls-pill-label">{{ $sec['label'] }}</span>
        </a>
    @endforeach
</nav>

<div class="pe-redesign-layout">

    {{-- ─── Original left nav (kept for the create page) ───
         Hidden when `.ls-hero` exists (property edit page) via global CSS.
         Sections are grouped by mental model. --}}
    <nav class="pe-redesign-nav" id="pe-nav">
        @php $lastGroup = null; @endphp
        @foreach($peSections as $sec)
            @if($sec['requiresData'] && !isset($data))
                @continue
            @endif
            @if(($sec['group'] ?? null) !== $lastGroup)
                <div class="pe-nav-group">{{ $sec['group'] }}</div>
                @php $lastGroup = $sec['group']; @endphp
            @endif
            <a href="#{{ $sec['id'] }}" data-pe-nav="{{ $sec['id'] }}">
                <span class="pe-nav-icon">{{ $sec['icon'] }}</span>
                <span class="pe-nav-label">{{ $sec['label'] }}</span>
            </a>
        @endforeach
    </nav>

    {{-- ─── Sections wrapped in the Studio canvas ───
         The `.ls-canvas` wrapper styles only engage when `.ls-hero` is
         present (via global CSS). On the create page it's a transparent
         pass-through. --}}
    <div class="ls-canvas">
        <div class="pe-redesign-sections" id="pe-sections">
            @foreach($peSections as $sec)
                @if($sec['requiresData'] && !isset($data))
                    @continue
                @endif
                <section id="{{ $sec['id'] }}" data-pe-section="{{ $sec['id'] }}">
                    <header class="pe-section-head">
                        <span class="pe-icon">{{ $sec['icon'] }}</span>
                        <h2>{{ $sec['label'] }}</h2>
                    </header>
                    @if(!isset($data) && $sec['id'] !== 'listing')
                        <div class="pe-create-locked">
                            @if($sec['id'] === 'photos')
                                <h3>Create the listing before uploading gallery photos</h3>
                                <p>The full gallery manager needs a saved listing so each photo has a property to attach to. Add the basics, click <strong>Create listing and continue setup</strong>, then Joe will open the Photos step with the bulk uploader, captions, categories, and reorder controls.</p>
                                <ol>
                                    <li>Add the listing basics and primary feature/banner images in the Basics step.</li>
                                    <li>Create the listing shell.</li>
                                    <li>Upload the full gallery on the next screen.</li>
                                </ol>
                            @else
                                <h3>This step unlocks after the listing is created</h3>
                                <p>Save the basic listing first. Then this setup studio will let you complete {{ strtolower($sec['label']) }} without losing work.</p>
                            @endif
                        </div>
                    @else
                        @include('admin.properties.sub.' . $sec['partial'])
                    @endif
                </section>
            @endforeach
        </div>
    </div>

</div>


<script>
/* Listing Studio — single-section view + auto-save.

   Behaviour:
   - All 12 sections render in DOM. Initial load leaves them visible so
     CKEditor / image-uploader / FullCalendar see real dimensions and init
     correctly. After ~250ms (let widgets attach), we add `pe-single-mode`
     to the container, which hides non-active sections via CSS.
   - Clicking either nav (top pillbar in studio mode, left rail in create
     mode) swaps the active section. No scroll. No reload.
   - URL hash drives state: deep links + browser back/forward.
   - Auto-save: when `#ls-save-status` exists in the DOM (studio mode), the
     form auto-saves on field blur (debounced 800ms) AND on section change.
     Status indicator in the hero shows "Saving…" / "Saved" / "Save failed".
     Manual submit also still works as fallback (form.submit()).
   - On non-studio pages (create), a manual save button submits the form
     normally; this script keeps the AJAX-fallback path for that flow. */
(function () {
    var navs            = document.querySelectorAll('#pe-nav, #ls-pillbar, [data-pe-nav-host]');
    var sections        = document.querySelectorAll('[data-pe-section]');
    var sectionsHost    = document.getElementById('pe-sections');
    if (!navs.length || !sections.length || !sectionsHost) return;
    var saveStatusEl    = document.getElementById('ls-save-status');
    var saveLabelEl     = document.getElementById('ls-save-label');
    var isStudioMode    = !!saveStatusEl;
    // Backwards-compat: keep `nav` available for the rest of the function
    // even though we now drive both navs in lockstep.
    var nav             = navs[0];

    // Collect nav links across BOTH navs, keyed by section id. Multiple
    // links can share the same key (one per nav variant) — each gets the
    // active class toggled together.
    var navLinks = {};
    navs.forEach(function (n) {
        n.querySelectorAll('[data-pe-nav]').forEach(function (a) {
            var k = a.getAttribute('data-pe-nav');
            if (!navLinks[k]) navLinks[k] = [];
            navLinks[k].push(a);
        });
    });

    var FIRST_ID = (function () {
        var first = nav.querySelector('[data-pe-nav]');
        return first ? first.getAttribute('data-pe-nav') : null;
    })();

    /* ─── Section show/hide ─── */
    var currentSectionId = null;
    function setActive(id, opts) {
        opts = opts || {};
        if (!navLinks[id]) id = FIRST_ID;
        if (!id) return;

        // If we're navigating away from a section in studio mode, flush
        // pending edits before showing the new one.
        if (isStudioMode && currentSectionId && currentSectionId !== id) {
            flushPendingSave('section-change');
        }

        // Toggle sections.
        sections.forEach(function (s) {
            s.classList.toggle('is-active', s.getAttribute('data-pe-section') === id);
        });
        // Toggle nav highlight (across all nav variants).
        Object.keys(navLinks).forEach(function (k) {
            navLinks[k].forEach(function (a) {
                a.classList.toggle('is-active', k === id);
            });
        });
        // Legacy sticky-save-bar crumb (still rendered on create page).
        var crumb = document.getElementById('pe-current-section-name');
        if (crumb && navLinks[id] && navLinks[id][0]) {
            var label = navLinks[id][0].querySelector('.pe-nav-label, .ls-pill-label');
            crumb.textContent = label ? label.textContent : id;
        }
        currentSectionId = id;

        // CKEditor instances inside hidden sections render with iframe
        // width=0; resize() recomputes layout when shown.
        if (window.CKEDITOR && CKEDITOR.instances) {
            var section = document.querySelector('[data-pe-section="' + id + '"]');
            if (section) {
                section.querySelectorAll('textarea').forEach(function (ta) {
                    var inst = CKEDITOR.instances[ta.id] || CKEDITOR.instances[ta.name];
                    if (inst && typeof inst.resize === 'function') {
                        try { inst.resize('100%', inst.config.height || 200); } catch (e) {}
                    }
                });
            }
        }

        // FullCalendar (calendar section) re-renders on window resize.
        // Cheap broadcast — most widgets that care about size listen.
        if (id === 'calendar') {
            try { window.dispatchEvent(new Event('resize')); } catch (e) {}
        }

        // URL sync (replaceState — don't push unless this came from a click).
        if (opts.pushHistory) {
            history.pushState({ peSection: id }, '', '#' + id);
        } else if (opts.replaceHash !== false) {
            history.replaceState({ peSection: id }, '', '#' + id);
        }
    }

    /* ─── Nav click (both pillbar + left rail) ─── */
    navs.forEach(function (n) {
        n.addEventListener('click', function (e) {
            var a = e.target.closest('[data-pe-nav]');
            if (!a) return;
            e.preventDefault();
            var id = a.getAttribute('data-pe-nav');
            setActive(id, { pushHistory: true });
        });
    });

    /* ─── Browser back/forward ─── */
    window.addEventListener('popstate', function (e) {
        var id = (location.hash || '').replace(/^#/, '');
        if (id && navLinks[id]) setActive(id, { replaceHash: false });
    });

    /* ─── Initial section + deferred single-mode toggle ─── */
    var initialId = (location.hash || '').replace(/^#/, '');
    if (!initialId || !navLinks[initialId]) initialId = FIRST_ID;

    // Highlight nav immediately so the admin sees feedback even before
    // single-mode kicks in.
    setActive(initialId, { replaceHash: false });

    // Defer the hide-non-active until widgets have had a chance to attach
    // at full width. CKEditor attaches on $(document).ready; image-uploader
    // and FullCalendar attach on the same tick. 250ms is enough headroom
    // and short enough that admins don't notice the brief flash of all
    // sections rendered.
    function enableSingleMode() {
        sectionsHost.classList.add('pe-single-mode');
        // Re-apply active so any CKEditor we just hid gets a resize.
        setActive(initialId, { replaceHash: false });
    }
    if (document.readyState === 'complete' || document.readyState === 'interactive') {
        setTimeout(enableSingleMode, 250);
    } else {
        document.addEventListener('DOMContentLoaded', function () {
            setTimeout(enableSingleMode, 250);
        });
    }

    /* ─── Save status (hero indicator in studio mode) ─── */
    function setSaveStatus(state, label) {
        if (!saveStatusEl) return;
        saveStatusEl.classList.remove('is-saving', 'is-error');
        if (state === 'saving') saveStatusEl.classList.add('is-saving');
        else if (state === 'error') saveStatusEl.classList.add('is-error');
        if (saveLabelEl) saveLabelEl.textContent = label || '';
    }

    function timeAgo(date) {
        var diff = Math.max(0, Math.floor((Date.now() - date) / 1000));
        if (diff < 5) return 'just now';
        if (diff < 60) return diff + ' sec ago';
        var min = Math.floor(diff / 60);
        if (min < 60) return min + ' min ago';
        return Math.floor(min / 60) + ' hr ago';
    }

    /* ─── Form + auto-save engine ─── */
    var form = document.getElementById('property-edit-form');
    if (!form) return;

    var saveTimer       = null;
    var inFlight        = false;
    var dirty           = false;
    var lastSavedAt     = null;
    var refreshAgoTimer = null;

    function startAgoTicker() {
        if (refreshAgoTimer) return;
        refreshAgoTimer = setInterval(function () {
            if (!lastSavedAt || inFlight || dirty) return;
            setSaveStatus('saved', 'Saved · ' + timeAgo(lastSavedAt));
        }, 30000);
    }

    function performSave(reason) {
        if (inFlight) {
            // Another save came in mid-flight — mark dirty so we re-save
            // when this one returns.
            dirty = true;
            return;
        }
        if (typeof window.fetch !== 'function' || typeof FormData !== 'function') return;

        // Flush CKEditor content back to underlying textareas first.
        if (window.CKEDITOR && CKEDITOR.instances) {
            Object.keys(CKEDITOR.instances).forEach(function (k) {
                try { CKEDITOR.instances[k].updateElement(); } catch (err) {}
            });
        }

        inFlight = true;
        dirty = false;
        setSaveStatus('saving', 'Saving…');

        var fd = new FormData(form);
        fetch(form.action, {
            method: 'POST',
            body: fd,
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json, text/html;q=0.9, */*;q=0.5',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') || {}).content
                    || (form.querySelector('input[name="_token"]') || {}).value
                    || '',
            },
            redirect: 'follow',
        }).then(function (res) {
            return res.text().then(function (body) {
                return { status: res.status, body: body };
            });
        }).then(function (resp) {
            inFlight = false;

            if (resp.status === 422) {
                var msg = 'Could not save';
                try {
                    var json = JSON.parse(resp.body);
                    if (json && json.errors) {
                        var firstKey = Object.keys(json.errors)[0];
                        if (firstKey) msg = json.errors[firstKey][0];
                    } else if (json && json.message) {
                        msg = json.message;
                    }
                } catch (err) {}
                setSaveStatus('error', msg);
                return;
            }

            if (resp.status >= 200 && resp.status < 400) {
                lastSavedAt = Date.now();
                setSaveStatus('saved', 'Saved · just now');
                startAgoTicker();
                if (dirty) {
                    // Edits arrived during the request — re-save.
                    setTimeout(function () { performSave('coalesce'); }, 100);
                }
                return;
            }

            setSaveStatus('error', 'Save failed · HTTP ' + resp.status);
        }).catch(function (err) {
            inFlight = false;
            setSaveStatus('error', 'Save failed · network');
        });
    }

    function flushPendingSave(reason) {
        if (saveTimer) {
            clearTimeout(saveTimer);
            saveTimer = null;
        }
        // Only flush if there are unsaved edits (dirty flag) or we were
        // told this is a section-change (always commit before navigating).
        if (dirty || reason === 'section-change') {
            performSave(reason);
        }
    }

    function scheduleAutoSave() {
        if (!isStudioMode) return;  // Manual submit on create page.
        dirty = true;
        if (saveTimer) clearTimeout(saveTimer);
        saveTimer = setTimeout(function () {
            saveTimer = null;
            performSave('debounce');
        }, 900);
    }

    if (isStudioMode) {
        // Listen for any field change inside the form. `change` covers
        // selects/checkboxes/file inputs; `input` covers text/textarea.
        // Both events can fire for the same edit; the debounce coalesces.
        form.addEventListener('input', function (e) {
            if (e.target && (e.target.matches('input, textarea, select'))) {
                scheduleAutoSave();
            }
        });
        form.addEventListener('change', function (e) {
            if (e.target && (e.target.matches('input, textarea, select'))) {
                scheduleAutoSave();
            }
        });

        // CKEditor edits don't trigger 'input' on the textarea — listen
        // for editor-level change events instead. Run once after CKEditor
        // is ready.
        function wireCKEditorAutosave() {
            if (!window.CKEDITOR || !CKEDITOR.instances) return;
            Object.keys(CKEDITOR.instances).forEach(function (k) {
                var inst = CKEDITOR.instances[k];
                if (inst._lsAutosaveWired) return;
                inst._lsAutosaveWired = true;
                inst.on('change', scheduleAutoSave);
                inst.on('blur', scheduleAutoSave);
            });
        }
        setTimeout(wireCKEditorAutosave, 800);
        setTimeout(wireCKEditorAutosave, 2400);

        // Save on tab away — flush any pending debounce so changes don't
        // sit in memory while the admin walks away.
        window.addEventListener('beforeunload', function () {
            if (dirty) flushPendingSave('unload');
        });

        // Initial idle state.
        setSaveStatus('saved', 'All changes saved');
    }

    // Manual submit (Update button on create page, or anything that calls
    // form.submit()) → run an immediate save through the same engine.
    form.addEventListener('submit', function (e) {
        if (typeof window.fetch !== 'function' || typeof FormData !== 'function') return;
        e.preventDefault();
        if (saveTimer) { clearTimeout(saveTimer); saveTimer = null; }
        performSave('submit');
    });
})();
</script>
