@extends('adminlte::page')

@section("css")
{{-- Welcome toast — referrer-checked in JS. We also stash the user's
     first name here so the JS can greet them without a server roundtrip. --}}
<meta name="ca-user-name" content="{{ trim(strtok((string) (auth()->user()->name ?? ''), ' ')) ?: '' }}">
@if(session('welcome_back'))
    <meta name="ca-welcome" content="{{ session('welcome_back') }}">
@endif
<link rel="icon" href="{{ asset($setting_data['favicon'] ?? 'front/images/favicon.png') }}">
<link rel="stylesheet" href="{{ asset('front/assets/fontawesome-free-6.4.0-web/css/all.min.css') }}">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fancyapps/ui@5.0/dist/fancybox/fancybox.css" />
<link rel="stylesheet" href="https://code.jquery.com/ui/1.13.1/themes/base/jquery-ui.css">
<link rel="stylesheet" href="{{ asset('toastr/toastr.css') }}">
{{-- Easepick base CSS is loaded INSIDE its Shadow DOM via VR_DATEPICKER_CONFIG.easepickCss; do NOT include it globally. --}}
<link rel="stylesheet" href="{{ asset('front/css/vr-datepicker.css') }}">
{{-- Editorial typography. Inter (body) + Fraunces (display serif) +
     JetBrains Mono (code). Fraunces is a variable serif with optical
     sizing — used for big property names, page heroes. --}}
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&family=Fraunces:opsz,wght@9..144,300;9..144,400;9..144,500;9..144,600;9..144,700&family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
    :root {
        --admin-font: "Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        /* Display = Instrument Serif (distinctive editorial serif), with
           Fraunces as a fallback for older OS font stacks. */
        --admin-font-display: "Instrument Serif", "Fraunces", "Times New Roman", Georgia, serif;
        --admin-font-mono: "JetBrains Mono", ui-monospace, "SF Mono", Menlo, Consolas, monospace;
    }
    /* Inter on body inherits to most descendants. List the elements browsers
       don't inherit font into (form controls), plus the AdminLTE shells that
       set their own font-family explicitly. NOTE: never use `*` descendants —
       that breaks FontAwesome icon glyphs (they rely on their own font-family). */
    body,
    body input,
    body select,
    body button,
    body textarea,
    body .main-sidebar,
    body .nav-sidebar,
    body .nav-sidebar .nav-link,
    body .nav-sidebar .nav-link p,
    body .main-header,
    body .main-header .nav-link,
    body .brand-link,
    body .brand-text,
    body .content-wrapper,
    body .card,
    body .card-title,
    body .card-body,
    body h1, body h2, body h3, body h4, body h5, body h6,
    body p, body a, body span, body div, body td, body th, body label {
        font-family: var(--admin-font) !important;
    }
    /* Belt-and-braces: force FontAwesome icons to keep their own font so glyphs render. */
    body i.fa, body i.fas, body i.far, body i.fab, body i.fal, body i.fad,
    body i.fa-solid, body i.fa-regular, body i.fa-brands, body i.fa-light, body i.fa-duotone,
    body .fa, body .fas, body .far, body .fal, body .fad,
    body .fa-solid, body .fa-regular, body .fa-light, body .fa-duotone {
        font-family: "Font Awesome 6 Free", "FontAwesome" !important;
    }
    body i.fab, body i.fa-brands, body .fab, body .fa-brands {
        font-family: "Font Awesome 6 Brands" !important;
    }
    body code, body pre, body kbd, body samp { font-family: var(--admin-font-mono) !important; }
    body code { background: #eef2f7; color: #334155; padding: 1px 6px; border-radius: 4px; font-size: 12.5px; border: 1px solid #e2e8f0; }
    body { -webkit-font-smoothing: antialiased; -moz-osx-font-smoothing: grayscale; }
    .table td,
    .table th {
        padding: 6px;
        font-size: 14px;
    }
    /* Helper text under form fields — make it actually readable, not 30%-opacity gray. */
    body .form-group .form-text,
    body .form-group small.text-muted,
    body .form-group .text-muted {
        color: #475569 !important;
        font-size: 12.5px !important;
        line-height: 1.5;
        margin-top: 5px;
        display: block;
    }
    body .form-group label {
        display: block;
        font-weight: 600;
        color: #1e293b;
        margin-bottom: 6px;
        font-size: 14px;
    }
    /* Section-divider alert with helper subtitle — make the subtitle readable on the alert background. */
    body .alert.alert-info small.d-block,
    body .alert.alert-warning small.d-block {
        color: rgba(0,0,0,.62) !important;
        font-size: 12.5px;
        margin-top: 4px;
        font-weight: 400;
    }

    /* ═══════════════════════════════════════════════════════════════════════
       Phase 13Q — Floating glass rail sidebar.
       Detaches the sidebar from the edge, gives it rounded corners, a soft
       shadow, and a translucent backdrop. Pill-shaped nav rows with a
       gradient pill on active. Body has a subtle cool gradient so the
       floating card reads as floating, not stuck.
       ═══════════════════════════════════════════════════════════════════════ */

    /* ─── Admin — banking-grade professional palette ───
       Cool light gray canvas (like high-end office paper), white cards
       with refined paper feel, deep navy chrome on the topbar, single
       deep-navy accent for actions. Restrained. Confident. Premium.
       No violet, no electric anything, no aurora — color is restraint.

       --ca-ink* = dark text on light canvas + white cards.
       --ca-on-dark* = light text on the dark navy topbar / hero. */
    :root {
        --ca-ink:            #0f1c33;   /* deep professional navy-charcoal */
        --ca-ink-soft:       #2a3855;
        --ca-ink-mute:       #5a6478;
        --ca-ink-quiet:      #94a0b3;

        --ca-on-dark:        #f3f5f9;
        --ca-on-dark-soft:   #c8d0dd;
        --ca-on-dark-mute:   #8a96ad;
        --ca-on-dark-quiet:  rgba(255, 255, 255, 0.45);

        --ca-surface:        #ffffff;
        --ca-surface-soft:   #f5f5f7;   /* near-white tint (used for hover, mute fills) */
        --ca-surface-mute:   #eeeef0;
        --ca-canvas:         #ffffff;   /* pure white page — premium minimal */
        --ca-canvas-deep:    #f5f5f7;
        /* Topbar / hero dark chrome — solid deep navy, no aurora. */
        --ca-chrome:         #15253f;
        --ca-chrome-deep:    #0f1c33;

        --ca-line:           #e3e7ee;
        --ca-line-strong:    #cdd4e0;
        --ca-line-soft:      rgba(15, 28, 51, 0.05);
        --ca-line-on-dark:           rgba(255, 255, 255, 0.10);
        --ca-line-on-dark-strong:    rgba(255, 255, 255, 0.18);

        /* Accent — single deep navy, banking aesthetic. */
        --ca-accent:         #1e3a5f;   /* deep navy primary */
        --ca-accent-deep:    #15253f;
        --ca-accent-bright:  #2c4d7a;
        --ca-accent-soft:    #c8d4e3;
        --ca-accent-faint:   rgba(30, 58, 95, 0.06);
        --ca-accent-glow:    rgba(30, 58, 95, 0.20);

        --ca-shadow-card:    0 1px 2px rgba(15,28,51,.04), 0 1px 3px rgba(15,28,51,.04);
        --ca-shadow-lift:    0 8px 24px -8px rgba(15,28,51,.12), 0 2px 8px -2px rgba(15,28,51,.06);
        --ca-shadow-pop:     0 18px 48px -16px rgba(15,28,51,.20), 0 6px 18px -6px rgba(15,28,51,.10);
        --ca-shadow-hero:    0 28px 60px -20px rgba(15,28,51,.30), 0 10px 24px -8px rgba(15,28,51,.14);
        --ca-shadow-glow:    0 0 0 3px rgba(30, 58, 95, .14);
        --ca-shadow-pressed: 0 1px 1px rgba(15,28,51,.06), inset 0 1px 0 rgba(255,255,255,.4);

        --ca-radius:         10px;
        --ca-radius-sm:      8px;
        --ca-radius-lg:      14px;

        --ca-ease:           cubic-bezier(.2, .7, .2, 1);
        --ca-ease-out:       cubic-bezier(.16, 1, .3, 1);

        --ca-status-live:    #047857;   /* refined emerald, deeper */
    }

    /* Page chrome — flat cool gray. No aurora, no gradients on the page
       itself. Cards sit on this with very subtle elevation; the lack of
       contrast solves the box-in-box feel. */
    body, body.layout-fixed {
        background: var(--ca-canvas) !important;
        background-attachment: fixed !important;
        min-height: 100vh;
        color: var(--ca-ink);
        font-feature-settings: "ss01" 1, "cv11" 1;
    }
    body .content-wrapper { background: transparent !important; padding-top: 14px !important; }
    /* Top navbar removed — Logout moved to the floating sidebar. */
    body .main-header { display: none !important; }

    /* The sidebar itself — floating, rounded, glassy. */
    body .main-sidebar,
    body.layout-fixed .main-sidebar {
        position: fixed !important;
        top: 14px !important;
        left: 14px !important;
        bottom: 14px !important;
        height: calc(100vh - 28px) !important;
        width: 244px !important;
        border-radius: 20px !important;
        overflow: hidden !important;
        background:
            linear-gradient(180deg, rgba(255,255,255,0.92) 0%, rgba(248,250,254,0.88) 100%) !important;
        backdrop-filter: blur(22px) saturate(180%);
        -webkit-backdrop-filter: blur(22px) saturate(180%);
        border: 1px solid rgba(255,255,255,0.6) !important;
        box-shadow:
            0 1px 0 rgba(255,255,255,0.7) inset,
            0 24px 60px -24px rgba(15,23,42,0.22),
            0 10px 24px -14px rgba(15,23,42,0.10) !important;
        transition: width 240ms cubic-bezier(.2,.7,.2,1), left 240ms cubic-bezier(.2,.7,.2,1) !important;
    }

    /* Push the rest of the layout to clear the floating card (14 + 244 + 14 ≈ 272). */
    body .content-wrapper,
    body .main-footer,
    body .main-header { margin-left: 272px !important; }

    /* Brand link — replace the dull text bar with a chip + wordmark.
       The chip is a real <span class="ca-brand-chip"> injected via the
       adminlte.logo config so we don't depend on ::before, which was
       getting swallowed by the package's brand-link layout. */
    body .main-sidebar .brand-link {
        background: transparent !important;
        border-bottom: 1px solid rgba(15,23,42,0.06) !important;
        padding: 18px 20px 16px !important;
        display: flex !important;
        align-items: center !important;
        gap: 10px !important;
        color: #0f172a !important;
        text-decoration: none !important;
        line-height: 1.2 !important;
    }
    body .main-sidebar .brand-link .brand-image { display: none !important; }
    body .main-sidebar .brand-link .brand-text {
        display: inline-flex !important;
        align-items: center !important;
        gap: 10px !important;
        color: #0f172a !important;
        font-weight: 700 !important;
        font-size: 15.5px !important;
        letter-spacing: -.012em !important;
        margin: 0 !important;
        padding: 0 !important;
    }
    /* Brand mark — an editorial monogram. A serif "C" in ink, no chip. */
    body .main-sidebar .brand-link .ca-brand-chip {
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        width: 30px !important;
        height: 30px !important;
        border-radius: 0 !important;
        background: transparent !important;
        position: relative !important;
        flex: 0 0 30px !important;
        vertical-align: middle !important;
        font-family: var(--admin-font-display) !important;
        font-weight: 500 !important;
        font-size: 26px !important;
        color: var(--ca-ink) !important;
        line-height: 1 !important;
        letter-spacing: -.02em !important;
    }
    body .main-sidebar .brand-link .ca-brand-chip::before {
        content: "C";
    }
    body .main-sidebar .brand-link .ca-brand-text {
        font-family: var(--admin-font) !important;
        font-weight: 600 !important;
        font-size: 13px !important;
        letter-spacing: .14em !important;
        text-transform: uppercase !important;
        color: var(--ca-ink) !important;
    }
    body .main-sidebar .brand-link .ca-brand-text {
        color: #0f172a !important;
        font-weight: 700 !important;
        font-size: 15.5px !important;
        letter-spacing: -.012em !important;
    }

    /* User panel */
    body .main-sidebar .user-panel {
        border-bottom-color: rgba(15,23,42,0.06) !important;
        padding: 12px 18px !important;
    }
    body .main-sidebar .user-panel .info a {
        color: #1e293b !important;
        font-weight: 600 !important;
        font-size: 13.5px !important;
    }

    /* Sidebar inner scroller padding. */
    body .main-sidebar .sidebar { padding: 10px 10px 24px 10px !important; }

    /* Section labels (e.g. "Account Settings") — tiny uppercase chips, not bars. */
    body .nav-sidebar .nav-header {
        color: #94a3b8 !important;
        font-size: 10.5px !important;
        font-weight: 700 !important;
        text-transform: uppercase !important;
        letter-spacing: .14em !important;
        background: transparent !important;
        padding: 18px 14px 4px 14px !important;
        margin-top: 4px !important;
    }

    /* Nav rows — full pill shape, no left bar accent. */
    body .nav-sidebar .nav-item { margin: 2px 0 !important; }
    body .nav-sidebar .nav-link {
        color: #475569 !important;
        background: transparent !important;
        border-radius: 12px !important;
        padding: 10px 12px !important;
        font-size: 13.5px !important;
        font-weight: 500 !important;
        position: relative !important;
        transition: background 160ms ease, color 160ms ease, box-shadow 200ms ease, transform 160ms ease !important;
    }
    body .nav-sidebar .nav-link p {
        font-weight: 500 !important;
        color: inherit !important;
    }
    body .nav-sidebar .nav-link i {
        color: #64748b !important;
        font-size: 14px !important;
        width: 22px !important;
        margin-right: 8px !important;
        transition: color 160ms ease !important;
    }

    /* Hover — gentle slate fill + tiny right-shift so it feels alive. */
    body .nav-sidebar .nav-link:hover,
    body .nav-sidebar .nav-link:focus {
        background: rgba(15,23,42,0.05) !important;
        color: #0f172a !important;
        transform: translateX(2px);
    }
    body .nav-sidebar .nav-link:hover i,
    body .nav-sidebar .nav-link:focus i { color: #0f172a !important; }

    /* Active — gradient pill + soft glow. The whole row is the highlight,
       no left-edge bar (that's the templatish bit we're avoiding). Selector
       is doubled with .main-sidebar.sidebar-light-primary to out-specific
       AdminLTE's own active rule. */
    body .main-sidebar .nav-sidebar > .nav-item > .nav-link.active,
    body .main-sidebar.sidebar-light-primary .nav-sidebar > .nav-item > .nav-link.active,
    body .main-sidebar .nav-sidebar .nav-link.active {
        background: var(--ca-ink) !important;
        background-color: var(--ca-ink) !important;
        color: #ffffff !important;
        font-weight: 600 !important;
        box-shadow: 0 4px 14px -8px rgba(10,10,10,.45) !important;
        transform: translateX(0);
    }
    body .main-sidebar .nav-sidebar .nav-link.active i,
    body .main-sidebar .nav-sidebar .nav-link.active p,
    body .main-sidebar .nav-sidebar .nav-link.active .right { color: #ffffff !important; }

    /* Submenu arrow */
    body .nav-sidebar .nav-link .right { color: #94a3b8 !important; transition: color 160ms, transform 160ms; }
    body .nav-sidebar .nav-link:hover .right { color: #0f172a !important; }

    /* Treeview — indented, with a faint vertical guide so the hierarchy reads. */
    body .nav-sidebar .nav-treeview {
        position: relative !important;
        padding-left: 6px !important;
        margin: 2px 0 4px 18px !important;
    }
    body .nav-sidebar .nav-treeview::before {
        content: "";
        position: absolute;
        left: 6px; top: 6px; bottom: 6px;
        width: 1px;
        background: rgba(15,23,42,0.10);
    }
    body .nav-sidebar .nav-treeview .nav-link {
        padding: 8px 12px 8px 18px !important;
        font-size: 12.5px !important;
        font-weight: 500 !important;
        color: #64748b !important;
        border-radius: 10px !important;
        margin: 1px 0 !important;
    }
    body .nav-sidebar .nav-treeview .nav-link i {
        font-size: 12px !important;
        width: 18px !important;
        color: #94a3b8 !important;
    }
    body .nav-sidebar .nav-treeview .nav-link:hover {
        background: rgba(15,23,42,0.04) !important;
        color: #0f172a !important;
        transform: none;
    }
    body .nav-sidebar .nav-treeview .nav-link.active {
        background: rgba(10, 10, 10, 0.06) !important;
        color: var(--ca-ink) !important;
        box-shadow: none !important;
        font-weight: 600 !important;
    }
    body .nav-sidebar .nav-treeview .nav-link.active i { color: var(--ca-ink) !important; }
    body .nav-sidebar .nav-treeview .nav-link.active p { color: var(--ca-ink) !important; }

    /* Open-parent state — soft slate fill so the user sees which group is open. */
    body .nav-sidebar .nav-item.menu-open > .nav-link:not(.active) {
        background: rgba(15,23,42,0.04) !important;
        color: #0f172a !important;
    }
    body .nav-sidebar .nav-item.menu-open > .nav-link:not(.active) i { color: #0f172a !important; }

    /* Footer — blends into the page background, not its own bar. */
    body .main-footer {
        background: transparent !important;
        border-top: 1px solid rgba(15,23,42,0.06) !important;
        color: #64748b !important;
        font-size: 12px !important;
        margin: 12px 14px 14px 272px !important;
        padding: 14px 18px !important;
    }

    /* Sidebar scrollbar — barely-there. */
    body .main-sidebar .sidebar::-webkit-scrollbar { width: 6px; }
    body .main-sidebar .sidebar::-webkit-scrollbar-track { background: transparent; }
    body .main-sidebar .sidebar::-webkit-scrollbar-thumb { background: rgba(15,23,42,0.12); border-radius: 3px; }
    body .main-sidebar .sidebar::-webkit-scrollbar-thumb:hover { background: rgba(15,23,42,0.22); }

    /* Mini-collapse: keep it floating, just narrower. */
    body.sidebar-collapse .main-sidebar { width: 68px !important; }
    body.sidebar-collapse .content-wrapper,
    body.sidebar-collapse .main-footer,
    body.sidebar-collapse .main-header { margin-left: 96px !important; }
    body.sidebar-collapse .main-footer { margin: 12px 14px 14px 96px !important; }

    /* Mobile: AdminLTE slides the sidebar off-screen. Keep our floating look,
       just override the negative-left transform with a clean off-screen slide. */
    @media (max-width: 991.98px) {
        body .main-sidebar,
        body.layout-fixed .main-sidebar {
            top: 8px !important;
            bottom: 8px !important;
            height: calc(100vh - 16px) !important;
        }
        body .content-wrapper,
        body .main-footer,
        body .main-header { margin-left: 0 !important; }
        body .main-footer { margin: 12px !important; }
        body .main-header.navbar { margin: 8px !important; }
        body.sidebar-open .main-sidebar { left: 8px !important; }
        body.sidebar-collapse .main-sidebar { left: -270px !important; }
    }

    /* ═══════════════════════════════════════════════════════════════════════
       Content area modern reskin — kills AdminLTE's dated colored card
       banners, dated Bootstrap buttons, loose content-header spacing, and
       the alert-info teal stripe. Aligned to the gold/cream brand.
       ═══════════════════════════════════════════════════════════════════════ */

    /* ─── Content header (page title row, sits on light canvas) ─── */
    body .content-header {
        padding: 26px 26px 16px !important;
    }
    body .content-header h1,
    body .content-header h2 {
        color: var(--ca-ink) !important;
        font-family: var(--admin-font);
        font-size: 22px !important;
        font-weight: 600 !important;
        letter-spacing: -.018em !important;
        line-height: 1.2 !important;
        margin: 0 !important;
    }
    body .content-header span,
    body .content-header label,
    body .content-header .text-muted,
    body .content-header small {
        color: var(--ca-ink-mute) !important;
    }
    /* Plain text links in the header use ink-soft; .btn variants keep
       their own text color (.btn-primary etc. stay white). */
    body .content-header a:not(.btn) {
        color: var(--ca-ink-soft) !important;
    }
    body .content-header a:not(.btn):hover { color: var(--ca-accent) !important; }
    body .content { padding: 0 26px 40px !important; }

    /* ─── Cards — flat, no container chrome ───
       The .card wrapper becomes invisible. Filter rows and tables sit
       directly on the page background. Premium, decluttered. The
       elements inside (toolbar, table) carry their own styling. */
    body .content .card,
    body .content .card.card-info,
    body .content .card.card-success,
    body .content .card.card-primary,
    body .content .card.card-warning,
    body .content .card.card-danger,
    body .content .card.card-secondary,
    body .content .card.card-default {
        background: transparent !important;
        border: none !important;
        border-radius: 0 !important;
        box-shadow: none !important;
        margin-bottom: 18px !important;
        overflow: visible !important;
    }
    /* Color-variant cards historically render a colored stripe on top via
       the `.card-{color}` class; this neutralizes the stripe so all cards
       look identical — pure white, hairline border, ink title. */
    body .content .card.card-info,
    body .content .card.card-success,
    body .content .card.card-primary,
    body .content .card.card-warning,
    body .content .card.card-danger {
        border-top: 1px solid var(--ca-line) !important;
    }

    /* ─── Card headers (used as toolbar / filter row) ───
       Flat — no background, no border, just spacing below before the
       table. The toolbar reads as a row of controls on the page itself. */
    body .content .card > .card-header,
    body .content .card.card-info > .card-header,
    body .content .card.card-success > .card-header,
    body .content .card.card-primary > .card-header,
    body .content .card.card-warning > .card-header,
    body .content .card.card-danger > .card-header {
        background: transparent !important;
        color: var(--ca-ink) !important;
        border: none !important;
        padding: 0 0 16px 0 !important;
        border-radius: 0 !important;
        margin-bottom: 0 !important;
    }
    body .content .card > .card-header .card-title,
    body .content .card > .card-header h3.card-title,
    body .content .card > .card-header h5.card-title {
        font-size: 14.5px !important;
        font-weight: 700 !important;
        color: var(--ca-ink) !important;
        letter-spacing: -.005em !important;
        margin: 0 !important;
        line-height: 1.4 !important;
    }
    body .content .card > .card-header .form-inline {
        gap: 10px !important;
    }
    /* Card body / table area — flat, no chrome. */
    body .content .card > .card-body {
        background: transparent !important;
        padding: 0 !important;
    }
    body .content .card > .card-body { padding: 18px !important; }

    /* ─── Buttons — modern, soft-shadowed, brand-aligned ─── */
    body .btn {
        border-radius: 9px !important;
        font-weight: 600 !important;
        font-size: 13.5px !important;
        padding: 8px 16px !important;
        letter-spacing: -.005em !important;
        border-width: 1px !important;
        transition: transform 120ms ease, box-shadow 160ms ease, background 160ms ease, color 160ms ease, border-color 160ms ease !important;
    }
    body .btn:hover { transform: translateY(-1px); }
    body .btn:active { transform: translateY(0); }
    body .btn .fa, body .btn .fas, body .btn .far { font-size: 13px; margin-right: 4px; }

    /* Primary action — solid electric indigo with gradient hover and glow. */
    body .btn-success,
    body .btn-primary {
        background: var(--ca-accent) !important;
        border-color: var(--ca-accent) !important;
        color: #ffffff !important;
        box-shadow: 0 1px 2px rgba(30,58,95,.22), 0 6px 16px -6px rgba(30,58,95,.40) !important;
    }
    body .btn-success:hover,
    body .btn-primary:hover {
        background: var(--ca-accent) !important;
        border-color: var(--ca-accent) !important;
        color: #ffffff !important;
        box-shadow: 0 12px 28px -8px rgba(30,58,95,.55), 0 2px 6px rgba(15,28,51,.25) !important;
        transform: translateY(-1px);
    }
    body .btn-success:focus,
    body .btn-primary:focus {
        box-shadow: var(--ca-shadow-glow), 0 1px 2px rgba(30,58,95,.18) !important;
    }

    /* Secondary / neutral — clean white with hairline border. */
    body .btn-secondary,
    body .btn-default,
    body .btn-light {
        background: #ffffff !important;
        border: 1px solid var(--ca-line) !important;
        color: var(--ca-ink) !important;
        box-shadow: 0 1px 2px rgba(24,24,27,.04) !important;
    }
    body .btn-secondary:hover,
    body .btn-default:hover,
    body .btn-light:hover {
        background: var(--ca-surface-mute) !important;
        border-color: #d4d4d8 !important;
        color: var(--ca-ink) !important;
    }

    /* Outline variants — indigo-bordered ghost. */
    body .btn-outline-primary,
    body .btn-outline-success,
    body .btn-outline-info {
        background: #ffffff !important;
        color: var(--ca-accent) !important;
        border: 1px solid var(--ca-accent-soft) !important;
        box-shadow: none !important;
    }
    body .btn-outline-primary:hover,
    body .btn-outline-success:hover,
    body .btn-outline-info:hover {
        background: var(--ca-accent-faint) !important;
        color: var(--ca-accent-deep) !important;
        border-color: var(--ca-accent) !important;
    }

    /* Info — neutralize the teal. Acts as a quiet "informational" button. */
    body .btn-info {
        background: #ffffff !important;
        color: var(--ca-ink-soft) !important;
        border: 1px solid var(--ca-line) !important;
        box-shadow: 0 1px 2px rgba(24,24,27,.04) !important;
    }
    body .btn-info:hover {
        background: var(--ca-surface-mute) !important;
        color: var(--ca-ink) !important;
        border-color: #d4d4d8 !important;
    }

    /* Destructive — clean rose-red. */
    body .btn-danger {
        background: #dc2626 !important;
        border-color: #dc2626 !important;
        color: #fff !important;
        box-shadow: 0 1px 2px rgba(220,38,38,.18) !important;
    }
    body .btn-danger:hover {
        background: #b91c1c !important;
        border-color: #b91c1c !important;
        box-shadow: 0 4px 10px -4px rgba(220,38,38,.40) !important;
    }

    /* Warning — quiet amber. */
    body .btn-warning {
        background: #f59e0b !important;
        border-color: #f59e0b !important;
        color: #18181b !important;
    }
    body .btn-warning:hover { background: #d97706 !important; border-color: #d97706 !important; color: #fff !important; }

    /* Tool buttons (card-tools) — tiny ghost icons. */
    body .card-tools .btn-tool {
        color: var(--ca-ink-mute) !important;
        background: transparent !important;
        border: none !important;
        box-shadow: none !important;
    }
    body .card-tools .btn-tool:hover { color: var(--ca-ink) !important; }

    /* ─── Alerts — neutral fills, hairline borders, no decorative tint ─── */
    body .alert {
        border-radius: var(--ca-radius-sm) !important;
        border-width: 1px !important;
        padding: 11px 14px !important;
        font-size: 13.5px !important;
    }
    body .alert.alert-info {
        background: #f4f4f5 !important;
        color: var(--ca-ink-soft) !important;
        border-color: var(--ca-line) !important;
    }
    body .alert.alert-success {
        background: #ecfdf5 !important;
        color: #065f46 !important;
        border-color: #a7f3d0 !important;
    }
    body .alert.alert-warning {
        background: #fffbeb !important;
        color: #854d0e !important;
        border-color: #fde68a !important;
    }
    body .alert.alert-danger {
        background: #fef2f2 !important;
        color: #991b1b !important;
        border-color: #fecaca !important;
    }

    /* ─── Form controls — clean, modern focus ring ─── */
    body .form-control,
    body .custom-select,
    body select.form-control {
        border-radius: 9px !important;
        border: 1px solid var(--ca-line) !important;
        background: #ffffff !important;
        color: var(--ca-ink) !important;
        font-size: 13.5px !important;
        padding: 8px 12px !important;
        height: auto !important;
        box-shadow: 0 1px 0 rgba(255,255,255,.7) inset !important;
        transition: border-color 140ms ease, box-shadow 140ms ease !important;
    }
    body .form-control:focus,
    body .custom-select:focus,
    body select.form-control:focus {
        border-color: var(--ca-accent) !important;
        box-shadow: 0 0 0 3px rgba(30,58,95,.20) !important;
        outline: none !important;
    }
    body textarea.form-control { line-height: 1.55; }
    body .form-group { margin-bottom: 14px; }
    body .owner-form-grid {
        display: grid;
        gap: 16px;
    }
    body .owner-form-card {
        background: #ffffff;
        border: 1px solid var(--ca-line);
        border-radius: 12px;
        padding: 18px;
    }
    body .owner-form-card-heading {
        color: var(--ca-ink);
        font-size: 13px;
        font-weight: 750;
        letter-spacing: .04em;
        margin-bottom: 14px;
        text-transform: uppercase;
    }
    body .owner-form-preview {
        display: block;
        width: 148px;
        height: 96px;
        object-fit: cover;
        border: 1px solid var(--ca-line);
        border-radius: 10px;
        margin-top: 10px;
        background: #f8fafc;
    }
    body .owner-narrow-card {
        max-width: 860px;
    }

    /* ─── Tables — softer borders, breathable rows ─── */
    body .content .card .table {
        font-size: 13.5px !important;
        color: var(--ca-ink) !important;
    }
    body .content .card .table thead th {
        background: var(--ca-surface-mute) !important;
        color: var(--ca-ink-soft) !important;
        font-weight: 600 !important;
        font-size: 11.5px !important;
        text-transform: uppercase !important;
        letter-spacing: .06em !important;
        border-top: none !important;
        border-bottom: 1px solid var(--ca-line) !important;
        padding: 10px 12px !important;
    }
    body .content .card .table td {
        padding: 11px 12px !important;
        border-top: 1px solid var(--ca-line-soft) !important;
        vertical-align: middle !important;
    }
    body .content .card .table tbody tr:hover { background: var(--ca-surface-soft) !important; }

    /* ─── Add-bar (top-right action row on index/edit pages) ─── */
    body .content-header .add-bar,
    body .content-header [class*="col-sm-6"] .row {
        justify-content: flex-end !important;
    }

    /* ─── Toastr — match brand ─── */
    body #toast-container > .toast-success,
    body #toast-container > .toast-error,
    body #toast-container > .toast-info {
        background-color: var(--ca-ink) !important;
        background-image: none !important;
        color: #ffffff !important;
        border-radius: 8px !important;
        box-shadow: 0 12px 28px -10px rgba(10,10,10,.45) !important;
        font-family: var(--admin-font) !important;
        font-size: 13px !important;
        padding: 14px 18px !important;
    }
    body #toast-container > .toast-success::before {
        content: "";
        display: inline-block;
        width: 6px; height: 6px;
        border-radius: 50%;
        background: var(--ca-status-live);
        margin-right: 10px;
        vertical-align: middle;
    }

    /* ═══════════════════════════════════════════════════════════════════════
       Custom top bar — hide AdminLTE's sidebar, replace with a fixed top
       bar that hosts brand · hover mega-menu · search · account.
       ═══════════════════════════════════════════════════════════════════════ */
    body .main-sidebar,
    body.layout-fixed .main-sidebar { display: none !important; }
    body .content-wrapper,
    body .main-footer,
    body .main-header { margin-left: 0 !important; }
    body .main-footer { margin: 12px 14px 14px 14px !important; }
    body .content-wrapper { padding-top: 78px !important; }

    .ca-topbar {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        z-index: 1030;
        height: 60px;
        /* Solid deep navy. No aurora, no gradient overlay — banking
           navbar aesthetic. The tiny inset highlight at the top edge
           is the only "premium" touch. */
        background: var(--ca-chrome);
        border-bottom: 1px solid rgba(255, 255, 255, 0.06);
        box-shadow:
            0 1px 0 rgba(255, 255, 255, 0.04) inset,
            0 1px 12px -2px rgba(15, 28, 51, 0.20);
        font-family: var(--admin-font);
        color: var(--ca-on-dark);
    }
    .ca-topbar-inner { position: relative; z-index: 1; }
    .ca-topbar-inner {
        max-width: 1480px;
        width: 100%;
        box-sizing: border-box;
        margin: 0 auto;
        height: 100%;
        padding: 0 22px;
        display: flex;
        align-items: center;
        gap: 14px;
    }
    .ca-brand {
        display: inline-flex;
        align-items: center;
        gap: 11px;
        text-decoration: none !important;
        color: #ffffff;
        flex: 0 1 245px;
        min-width: 0;
        transition: opacity 160ms var(--ca-ease);
    }
    .ca-brand:hover { opacity: .85; }
    .ca-brand-mark {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 28px;
        height: 28px;
        color: #ffffff;
    }
    .ca-brand-mark svg { width: 100%; height: 100%; }
    .ca-brand-name {
        font-family: var(--admin-font);
        font-weight: 700;
        font-size: 12.5px;
        letter-spacing: .16em;
        text-transform: uppercase;
        color: #ffffff;
        opacity: .92;
        min-width: 0;
        max-width: 190px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .ca-menu-trigger {
        display: inline-flex;
        align-items: center;
        gap: 9px;
        padding: 8px 14px;
        background: rgba(255, 255, 255, 0.04);
        border: 1px solid var(--ca-line-on-dark);
        border-radius: 999px;
        color: rgba(255, 255, 255, 0.92);
        font-size: 13px;
        font-weight: 500;
        cursor: pointer;
        transition: background 140ms ease, border-color 140ms ease, transform 140ms ease;
    }
    .ca-menu-trigger:hover {
        background: rgba(255, 255, 255, 0.10);
        border-color: var(--ca-line-on-dark-strong);
        color: #ffffff;
    }
    .ca-topbar.is-menu-open .ca-menu-trigger {
        background: var(--ca-accent);
        color: #ffffff;
        border-color: var(--ca-accent);
        box-shadow: 0 0 0 4px rgba(30,58,95,.20);
    }
    .ca-menu-bars {
        display: inline-flex;
        flex-direction: column;
        justify-content: space-between;
        width: 14px;
        height: 10px;
    }
    .ca-menu-bars > span {
        height: 1.5px;
        background: currentColor;
        border-radius: 1px;
        transition: transform 200ms ease, opacity 160ms ease;
    }
    .ca-menu-chev {
        font-size: 9px;
        opacity: .55;
        transition: transform 200ms ease;
    }
    .ca-topbar.is-menu-open .ca-menu-chev { transform: rotate(180deg); }

    .ca-search {
        position: relative;
        flex: 1 1 360px;
        min-width: 220px;
        max-width: 640px;
        margin: 0;
    }
    .ca-search-icon {
        position: absolute;
        left: 16px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--ca-ink-mute);
        font-size: 13px;
        pointer-events: none;
    }
    .ca-search-icon { color: rgba(255,255,255,0.55); }
    .ca-search-input {
        width: 100%;
        height: 40px;
        padding: 0 64px 0 42px;
        background: rgba(255, 255, 255, 0.06);
        border: 1px solid var(--ca-line-on-dark);
        border-radius: 10px;
        font-family: var(--admin-font);
        font-size: 13.5px;
        color: #ffffff;
        transition: border-color 140ms ease, box-shadow 140ms ease, background 140ms ease;
    }
    .ca-search-input::placeholder { color: rgba(255, 255, 255, 0.45); }
    .ca-search-input:hover {
        border-color: var(--ca-line-on-dark-strong);
        background: rgba(255, 255, 255, 0.10);
    }
    .ca-search-input:focus {
        outline: none;
        border-color: var(--ca-accent);
        background: rgba(255, 255, 255, 0.12);
        box-shadow: 0 0 0 3px rgba(30, 58, 95, .25);
    }
    .ca-search-kbd {
        position: absolute;
        right: 10px;
        top: 50%;
        transform: translateY(-50%);
        font-family: var(--admin-font-mono);
        font-size: 10.5px;
        font-weight: 500;
        color: rgba(255, 255, 255, 0.65);
        background: rgba(255, 255, 255, 0.08);
        border: 1px solid var(--ca-line-on-dark);
        border-radius: 5px;
        padding: 3px 7px;
        letter-spacing: .02em;
        pointer-events: none;
        transition: opacity 140ms ease;
    }

    /* ─── Search dropdown — light premium command palette ─── */
    .ca-search-dropdown {
        position: absolute;
        top: calc(100% + 10px);
        left: 0;
        right: 0;
        background: var(--ca-surface);
        border: 1px solid var(--ca-line);
        border-radius: 14px;
        box-shadow: var(--ca-shadow-pop);
        max-height: 480px;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        opacity: 0;
        visibility: hidden;
        transform: translateY(-4px);
        transition: opacity 160ms ease, transform 160ms ease, visibility 160ms;
        z-index: 1040;
        color: var(--ca-ink);
    }
    .ca-search.is-open .ca-search-dropdown {
        opacity: 1;
        visibility: visible;
        transform: translateY(0);
    }
    .ca-search-results {
        flex: 1;
        overflow-y: auto;
        padding: 8px;
        scrollbar-width: thin;
    }
    .ca-search-results::-webkit-scrollbar { width: 6px; }
    .ca-search-results::-webkit-scrollbar-thumb { background: var(--ca-line-strong); border-radius: 3px; }
    .ca-search-results::-webkit-scrollbar-thumb:hover { background: var(--ca-ink-quiet); }

    .ca-search-group {
        padding: 6px 0 4px;
    }
    .ca-search-group + .ca-search-group {
        border-top: 1px solid var(--ca-line);
        margin-top: 4px;
        padding-top: 10px;
    }
    .ca-search-group-label {
        padding: 4px 12px 6px;
        font-family: var(--admin-font);
        font-size: 10.5px;
        font-weight: 700;
        letter-spacing: .14em;
        text-transform: uppercase;
        color: var(--ca-ink-quiet);
    }
    .ca-search-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 10px 12px;
        border-radius: 8px;
        text-decoration: none !important;
        color: var(--ca-ink);
        font-family: var(--admin-font);
        cursor: pointer;
        transition: background 80ms ease;
    }
    .ca-search-item:hover,
    .ca-search-item.is-active {
        background: var(--ca-surface-soft);
    }
    .ca-search-item .ca-search-item-icon {
        width: 32px;
        height: 32px;
        flex-shrink: 0;
        border-radius: 8px;
        background: var(--ca-surface-mute);
        color: var(--ca-ink-soft);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 13px;
        transition: background 140ms ease, color 140ms ease;
    }
    .ca-search-item.is-active .ca-search-item-icon {
        background: var(--ca-accent);
        color: #ffffff;
    }
    .ca-search-item-body {
        flex: 1;
        min-width: 0;
    }
    .ca-search-item-title {
        font-size: 13.5px;
        font-weight: 500;
        color: var(--ca-ink);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .ca-search-item-title mark {
        background: var(--ca-accent-faint);
        color: var(--ca-accent);
        padding: 0 2px;
        border-radius: 2px;
        font-weight: 600;
    }
    .ca-search-item-sub {
        font-size: 11.5px;
        color: var(--ca-ink-mute);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        margin-top: 2px;
        font-family: var(--admin-font-mono);
    }
    .ca-search-item-kbd {
        font-family: var(--admin-font-mono);
        font-size: 10.5px;
        color: var(--ca-ink-mute);
        background: var(--ca-surface-mute);
        border: 1px solid var(--ca-line);
        border-radius: 4px;
        padding: 2px 6px;
        opacity: 0;
        transition: opacity 100ms ease;
        flex-shrink: 0;
    }
    .ca-search-item.is-active .ca-search-item-kbd { opacity: 1; }
    .ca-search-empty {
        padding: 36px 16px;
        text-align: center;
        color: var(--ca-ink-mute);
        font-size: 13px;
    }
    .ca-search-empty strong {
        display: block;
        color: var(--ca-ink);
        font-weight: 600;
        margin-bottom: 4px;
    }
    .ca-search-loading {
        position: absolute;
        right: 44px;
        top: 50%;
        transform: translateY(-50%);
        width: 12px;
        height: 12px;
        border-radius: 50%;
        border: 2px solid rgba(255, 255, 255, 0.20);
        border-top-color: rgba(255, 255, 255, 0.85);
        animation: ca-spin .9s linear infinite;
        opacity: 0;
        pointer-events: none;
        transition: opacity 140ms ease;
    }
    .ca-search.is-loading .ca-search-loading { opacity: 1; }
    .ca-search.is-loading .ca-search-kbd { opacity: 0; }
    @keyframes ca-spin { to { transform: translateY(-50%) rotate(360deg); } }

    .ca-search-foot {
        display: flex;
        gap: 16px;
        padding: 10px 14px;
        border-top: 1px solid var(--ca-line);
        background: var(--ca-surface-soft);
        font-family: var(--admin-font);
        font-size: 11px;
        color: var(--ca-ink-mute);
    }
    .ca-search-foot kbd {
        font-family: var(--admin-font-mono);
        font-size: 10px;
        background: var(--ca-surface);
        border: 1px solid var(--ca-line);
        border-radius: 3px;
        padding: 1px 5px;
        margin-right: 4px;
        color: var(--ca-ink-soft);
    }
    .ca-search-foot-hint { display: inline-flex; align-items: center; }

    .ca-account { flex: 0 0 auto; margin-left: 0; }
    .ca-account-link {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 4px 12px 4px 4px;
        background: rgba(255, 255, 255, 0.04);
        border: 1px solid var(--ca-line-on-dark);
        border-radius: 999px;
        color: rgba(255, 255, 255, 0.85);
        text-decoration: none !important;
        font-size: 13px;
        font-weight: 500;
        transition: background 140ms ease, border-color 140ms ease, color 140ms ease;
    }
    .ca-account-link:hover {
        background: rgba(255, 255, 255, 0.10);
        border-color: var(--ca-line-on-dark-strong);
        color: #ffffff;
    }
    .ca-account-avatar {
        width: 30px;
        height: 30px;
        border-radius: 50%;
        background: var(--ca-accent);
        color: #ffffff;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        font-size: 13px;
        flex-shrink: 0;
        box-shadow: 0 0 0 1px rgba(255,255,255,.08), 0 4px 10px -4px rgba(30,58,95,.45);
    }
    .ca-account-link i { font-size: 11px; opacity: .7; }

    /* ─── Mega menu — frosted glass, viewport-bound ───
       Translucent white with heavy backdrop blur. The page content
       behind shows through softly out of focus. Capped at viewport
       height so it never clips off the bottom. */
    .ca-megamenu {
        position: absolute;
        top: calc(100% + 12px);
        left: 24px;
        right: 24px;
        max-width: 1180px;
        margin: 0 auto;
        max-height: calc(100vh - 88px);
        overflow-y: auto;
        background: rgba(255, 255, 255, 0.78);
        backdrop-filter: blur(28px) saturate(180%);
        -webkit-backdrop-filter: blur(28px) saturate(180%);
        border: 1px solid rgba(255, 255, 255, 0.65);
        border-radius: 16px;
        box-shadow:
            0 1px 0 rgba(255, 255, 255, .9) inset,
            0 0 0 1px rgba(15, 28, 51, .04),
            0 28px 60px -22px rgba(15, 28, 51, .30),
            0 10px 24px -10px rgba(15, 28, 51, .14);
        padding: 20px 24px 18px;
        opacity: 0;
        visibility: hidden;
        transform: translateY(-6px);
        transition: opacity 180ms ease, transform 180ms ease, visibility 180ms;
        color: var(--ca-ink);
    }
    .ca-topbar.is-menu-open .ca-megamenu {
        opacity: 1;
        visibility: visible;
        transform: translateY(0);
    }
    .ca-megamenu-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 2px 24px;
    }
    .ca-megamenu-col { min-width: 0; padding: 0; }

    .ca-megamenu-title {
        display: block;
        font-family: var(--admin-font);
        font-size: 10.5px;
        font-weight: 700;
        letter-spacing: .14em;
        text-transform: uppercase;
        color: var(--ca-ink-quiet);
        padding: 0 0 8px;
        margin: 0 0 4px;
    }
    .ca-megamenu-title i { display: none; }

    .ca-megamenu-list { list-style: none; margin: 0; padding: 0; }
    .ca-megamenu-list li { margin: 0; }
    .ca-megamenu-list a {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        padding: 7px 10px;
        margin: 0 -10px;
        border-radius: 8px;
        color: var(--ca-ink);
        text-decoration: none !important;
        transition: background 140ms ease, color 140ms ease;
        position: relative;
    }
    .ca-megamenu-list a:hover { background: rgba(255, 255, 255, 0.55); }
    .ca-megamenu-list a i {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 14px;
        font-size: 11px;
        color: var(--ca-ink-quiet);
        margin-top: 3px;
        flex-shrink: 0;
        transition: color 140ms ease;
    }
    .ca-megamenu-list a:hover i { color: var(--ca-accent); }

    .ca-megamenu-item-body {
        display: flex;
        flex-direction: column;
        gap: 1px;
        min-width: 0;
    }
    .ca-megamenu-item-title {
        font-size: 13px;
        font-weight: 600;
        color: var(--ca-ink);
        letter-spacing: -.005em;
        line-height: 1.3;
        transition: color 140ms ease;
    }
    .ca-megamenu-list a:hover .ca-megamenu-item-title { color: var(--ca-accent); }
    .ca-megamenu-item-desc {
        font-size: 11.5px;
        color: var(--ca-ink-mute);
        line-height: 1.35;
        font-weight: 400;
    }

    .ca-megamenu-pill {
        grid-column: 1 / -1;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 16px;
        background: transparent;
        border: 1px solid var(--ca-line);
        border-radius: 999px;
        color: var(--ca-ink-soft);
        font-size: 12.5px;
        font-weight: 500;
        text-decoration: none !important;
        margin-top: 18px;
        justify-self: start;
        transition: background 140ms ease, border-color 140ms ease, color 140ms ease;
    }
    .ca-megamenu-pill:hover {
        background: var(--ca-ink);
        color: #ffffff;
        border-color: var(--ca-ink);
    }
    .ca-megamenu-pill i { font-size: 11px; }

    /* Top-bar mobile collapse — hide brand name on small screens, full
       width search. */
    @media (max-width: 1100px) {
        .ca-brand { flex-basis: auto; }
        .ca-brand-name { max-width: 130px; }
        .ca-search { flex-basis: 260px; }
        .ca-search-kbd { display: none; }
        .ca-search-input { padding-right: 14px; }
    }

    @media (max-width: 768px) {
        .ca-brand-name { display: none; }
        .ca-search { max-width: none; }
        .ca-search-input { padding-right: 14px; }
        .ca-account-link span:not(.ca-account-avatar) { display: none; }
        .ca-megamenu-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }

    /* ═══════════════════════════════════════════════════════════════════════
       Listing Studio — property edit redesigned as an editorial workspace.
       Hero card + LEFT section nav (Airbnb-style with groups) + auto-save.
       Page chrome (content-header, card-header, sticky-save-bar) is
       suppressed entirely in favor of the Studio's own structure.
       ═══════════════════════════════════════════════════════════════════════ */
    body:has(.ls-hero) .content-header { display: none !important; }
    body:has(.ls-hero) .content { padding: 0 !important; }
    body:has(.ls-hero) .content > .row,
    body:has(.ls-hero) .content > .row > .col-12 { padding: 0 !important; margin: 0 !important; }
    /* Suppress chrome ONLY on the OUTER content card (if any). The inner
       cards inside the studio (amenity groups, sub-sections) keep their
       headers — those are real category titles. */
    body:has(.ls-hero) .content > .row > .col-12 > .card {
        background: transparent !important;
        border: none !important;
        box-shadow: none !important;
        margin: 0 !important;
    }
    body:has(.ls-hero) .content > .row > .col-12 > .card > .card-header { display: none !important; }
    body:has(.ls-hero) .content > .row > .col-12 > .card > .card-body { padding: 0 !important; }
    body:has(.ls-hero) .pe-sticky-save-bar { display: none !important; }

    /* Top pill bar killed — section nav lives on the LEFT side now. */
    .ls-pillbar { display: none !important; }

    /* In studio mode the pe-redesign-layout grid drives the 2-column form
       layout (240px sticky nav + 1fr canvas). Restore grid behaviour
       (earlier it was display: contents). */
    body:has(.ls-hero) .pe-redesign-layout {
        display: grid !important;
        grid-template-columns: 244px 1fr !important;
        gap: 32px !important;
        align-items: start !important;
        margin: 0 !important;
        height: auto !important;
        min-height: 0 !important;
    }
    body:has(.ls-hero) .pe-redesign-nav {
        display: block !important;
        position: sticky !important;
        top: 78px !important;  /* clears the fixed top bar */
        max-height: none !important;
        overflow: visible !important;
        background: transparent !important;
        border: none !important;
        border-radius: 0 !important;
        box-shadow: none !important;
        padding: 6px 0 !important;
        align-self: start;
    }
    body:has(.ls-hero) .pe-redesign-nav a {
        display: flex !important;
        align-items: center !important;
        gap: 12px !important;
        padding: 10px 14px !important;
        margin: 1px 0 !important;
        border-radius: 8px !important;
        color: var(--ca-ink-soft) !important;
        font-size: 13.5px !important;
        font-weight: 500 !important;
        text-decoration: none !important;
        transition: background 140ms ease, color 140ms ease, transform 140ms ease !important;
    }
    body:has(.ls-hero) .pe-redesign-nav a:hover {
        background: rgba(15, 28, 51, 0.04) !important;
        color: var(--ca-ink) !important;
    }
    body:has(.ls-hero) .pe-redesign-nav a.is-active {
        background: var(--ca-accent) !important;
        color: #ffffff !important;
        font-weight: 600 !important;
        box-shadow:
            0 6px 16px -6px rgba(15, 28, 51, .30),
            0 2px 4px rgba(15, 28, 51, .12) !important;
    }
    body:has(.ls-hero) .pe-redesign-nav .pe-nav-group {
        color: var(--ca-ink-quiet) !important;
    }
    body:has(.ls-hero) .pe-redesign-nav .pe-nav-icon {
        opacity: .85 !important;
    }
    body:has(.ls-hero) .pe-redesign-nav .pe-nav-icon {
        font-size: 15px !important;
        width: 22px !important;
        text-align: center !important;
        flex-shrink: 0 !important;
        opacity: .85 !important;
    }
    body:has(.ls-hero) .pe-redesign-nav .pe-nav-label {
        flex: 1 !important;
        min-width: 0 !important;
        overflow: hidden !important;
        text-overflow: ellipsis !important;
        white-space: nowrap !important;
    }
    body:has(.ls-hero) .pe-redesign-nav .pe-nav-group {
        font-family: var(--admin-font) !important;
        padding: 18px 14px 6px !important;
        font-size: 10.5px !important;
        font-weight: 700 !important;
        letter-spacing: .14em !important;
        text-transform: uppercase !important;
        color: var(--ca-ink-quiet) !important;
        background: transparent !important;
    }
    body:has(.ls-hero) .pe-redesign-nav .pe-nav-group:first-child {
        padding-top: 4px !important;
    }
    /* Saved-pulse keeps working on the left rail's active item */
    body:has(.ls-hero) .pe-redesign-nav a.pe-just-saved {
        animation: pe-saved-pulse 1.4s ease-out;
    }

    /* ─── Listing Studio container ─── */
    .ls-studio {
        max-width: 1280px;
        margin: 0 auto;
        padding: 18px 24px 64px;
        font-family: var(--admin-font);
    }

    /* ─── Hero strip ─── tight one-row header. No photo banner, no
       glassmorphism, no big space. Sits flat on the page. */
    .ls-hero {
        display: flex;
        align-items: center;
        gap: 16px;
        padding: 18px 0;
        margin-bottom: 8px;
        border-bottom: 1px solid var(--ca-line);
        flex-wrap: wrap;
    }
    .ls-hero-back {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 34px;
        height: 34px;
        border-radius: 8px;
        background: transparent;
        border: 1px solid var(--ca-line);
        color: var(--ca-ink-soft);
        text-decoration: none !important;
        font-size: 12px;
        flex-shrink: 0;
        transition: background 140ms var(--ca-ease), border-color 140ms var(--ca-ease), color 140ms var(--ca-ease);
    }
    .ls-hero-back:hover {
        background: var(--ca-surface-soft);
        border-color: var(--ca-line-strong);
        color: var(--ca-ink);
    }
    .ls-hero-thumb {
        width: 56px;
        height: 40px;
        object-fit: cover;
        border-radius: 8px;
        flex-shrink: 0;
        box-shadow: 0 0 0 1px rgba(15, 28, 51, .08);
    }
    .ls-hero-id {
        flex: 1;
        min-width: 220px;
        display: flex;
        flex-direction: column;
        gap: 4px;
    }
    .ls-hero-eyebrow {
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 11.5px;
        font-weight: 500;
        color: var(--ca-ink-mute);
        letter-spacing: .02em;
    }
    .ls-status-pill {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 2px 9px;
        border-radius: 999px;
        background: rgba(4, 120, 87, .10);
        color: #047857;
        border: 1px solid rgba(4, 120, 87, .22);
        font-size: 10.5px;
        font-weight: 600;
        letter-spacing: .04em;
        text-transform: uppercase;
    }
    .ls-status-pill .ls-status-dot {
        width: 5px;
        height: 5px;
        border-radius: 50%;
        background: var(--ca-status-live);
    }
    .ls-status-pill.is-draft {
        background: rgba(180, 83, 9, .10);
        color: #b45309;
        border-color: rgba(180, 83, 9, .22);
    }
    .ls-status-pill.is-draft .ls-status-dot { background: #b45309; }

    .ls-hero h1 {
        font-family: var(--admin-font) !important;
        font-weight: 600 !important;
        font-size: 22px;
        line-height: 1.2;
        letter-spacing: -.018em;
        margin: 0;
        color: var(--ca-ink);
    }
    .ls-hero-meta {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 6px 14px;
        font-size: 12.5px;
        color: var(--ca-ink-mute);
    }
    .ls-hero-meta > span + span::before {
        content: "·";
        margin-right: 10px;
        color: var(--ca-ink-quiet);
    }
    .ls-hero-actions {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-shrink: 0;
        margin-left: auto;
    }
    .ls-hero-action {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 7px 12px;
        border-radius: 8px;
        background: transparent;
        border: 1px solid var(--ca-line);
        color: var(--ca-ink-soft) !important;
        text-decoration: none !important;
        font-size: 12.5px;
        font-weight: 500;
        cursor: pointer;
        transition: background 140ms var(--ca-ease), border-color 140ms var(--ca-ease), color 140ms var(--ca-ease);
    }
    .ls-hero-action:hover {
        background: var(--ca-surface-soft);
        border-color: var(--ca-line-strong);
        color: var(--ca-ink) !important;
    }
    .ls-hero-action i { font-size: 10px; opacity: .75; }

    /* Auto-save indicator (inline in the hero strip) */
    .ls-save-status {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        padding: 6px 11px;
        border-radius: 999px;
        background: rgba(4, 120, 87, .08);
        border: 1px solid rgba(4, 120, 87, .18);
        font-size: 11.5px;
        font-weight: 500;
        color: #047857;
    }
    .ls-save-dot {
        width: 6px; height: 6px;
        border-radius: 50%;
        background: var(--ca-status-live);
        transition: background 200ms;
    }
    .ls-save-status.is-saving {
        background: rgba(180, 83, 9, .08);
        border-color: rgba(180, 83, 9, .22);
        color: #b45309;
    }
    .ls-save-status.is-saving .ls-save-dot {
        background: #b45309;
        animation: ls-save-pulse 1.2s ease-in-out infinite;
    }
    .ls-save-status.is-error {
        background: rgba(190, 24, 24, .08);
        border-color: rgba(190, 24, 24, .22);
        color: #be1818;
    }
    .ls-save-status.is-error .ls-save-dot { background: #be1818; }
    @keyframes ls-save-pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: .4; }
    }

    /* ─── Top section pills (replaces left nav for property edit) ─── */
    .ls-pillbar {
        position: sticky;
        top: 0;
        z-index: 10;
        background: var(--ca-surface-soft);
        backdrop-filter: blur(20px) saturate(180%);
        -webkit-backdrop-filter: blur(20px) saturate(180%);
        margin: 0 -24px 28px;
        padding: 14px 24px 12px;
        border-bottom: 1px solid var(--ca-line);
        display: flex;
        gap: 6px;
        overflow-x: auto;
        white-space: nowrap;
        scrollbar-width: thin;
    }
    .ls-pillbar::-webkit-scrollbar { height: 4px; }
    .ls-pillbar::-webkit-scrollbar-thumb { background: var(--ca-line); border-radius: 2px; }
    .ls-pillbar a {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 8px 14px;
        border-radius: 999px;
        font-size: 13px;
        font-weight: 500;
        color: var(--ca-ink-mute);
        text-decoration: none !important;
        background: transparent;
        border: 1px solid transparent;
        transition: background 140ms ease, color 140ms ease, border-color 140ms ease;
        flex-shrink: 0;
    }
    .ls-pillbar a:hover { color: var(--ca-ink); background: rgba(10,10,10,0.04); }
    .ls-pillbar a.is-active {
        background: var(--ca-ink);
        color: #ffffff;
        font-weight: 600;
        box-shadow: 0 4px 12px -6px rgba(10,10,10,.45);
    }
    .ls-pillbar .ls-pill-icon { font-size: 13px; opacity: .85; }
    .ls-pillbar .ls-pill-divider {
        width: 1px;
        background: var(--ca-line);
        margin: 4px 4px;
        flex-shrink: 0;
    }

    /* ─── Editorial section canvas — flat, no box ───
       Form fields sit directly on the page background. No card, no
       border, no shadow. Just a clean editorial column. */
    body:has(.ls-hero) .ls-canvas {
        background: transparent;
        border: none;
        border-radius: 0;
        padding: 8px 0 40px 0;
        box-shadow: none;
        min-height: 0;
        color: var(--ca-ink);
    }
    body:has(.ls-hero) .ls-canvas .pe-section-head h2,
    body:has(.ls-hero) .ls-canvas .form-group label,
    body:has(.ls-hero) .ls-canvas h1, body:has(.ls-hero) .ls-canvas h2, body:has(.ls-hero) .ls-canvas h3, body:has(.ls-hero) .ls-canvas h4, body:has(.ls-hero) .ls-canvas h5 {
        color: var(--ca-ink) !important;
    }
    body:has(.ls-hero) .ls-canvas .text-muted, body:has(.ls-hero) .ls-canvas small.text-muted, body:has(.ls-hero) .ls-canvas .form-text {
        color: var(--ca-ink-mute) !important;
    }
    /* Form controls inside canvas — darken borders for white-on-white. */
    body:has(.ls-hero) .ls-canvas .form-control,
    body:has(.ls-hero) .ls-canvas .custom-select,
    body:has(.ls-hero) .ls-canvas select.form-control,
    body:has(.ls-hero) .ls-canvas input[type="text"],
    body:has(.ls-hero) .ls-canvas input[type="number"],
    body:has(.ls-hero) .ls-canvas input[type="email"],
    body:has(.ls-hero) .ls-canvas textarea {
        color: var(--ca-ink) !important;
        background: var(--ca-surface) !important;
        border: 1px solid var(--ca-line) !important;
    }
    body:has(.ls-hero) .ls-canvas .form-control::placeholder { color: var(--ca-ink-quiet) !important; }
    body:has(.ls-hero) .ls-canvas .form-control:focus {
        border-color: var(--ca-accent) !important;
        box-shadow: 0 0 0 3px rgba(30, 58, 95, .18) !important;
    }
    body:has(.ls-hero) .ls-canvas .pe-redesign-sections > section {
        background: transparent !important;
        border: none !important;
        border-radius: 0 !important;
        padding: 0 !important;
        margin: 0 !important;
    }
    body:has(.ls-hero) .ls-canvas .pe-redesign-sections {
        height: auto !important;
        overflow: visible !important;
        padding-right: 0 !important;
    }
    body:has(.ls-hero) .ls-canvas .pe-section-head {
        margin: 0 0 26px !important;
        padding: 0 !important;
        border: none !important;
        gap: 14px !important;
    }
    body:has(.ls-hero) .ls-canvas .pe-section-head h2 {
        font-family: var(--admin-font) !important;
        font-weight: 600 !important;
        font-size: 22px !important;
        line-height: 1.2 !important;
        letter-spacing: -.018em !important;
        color: var(--ca-ink) !important;
    }
    body:has(.ls-hero) .ls-canvas .pe-section-head .pe-icon {
        font-size: 22px !important;
        opacity: .65;
    }
    /* Quiet helper sentence under each section heading */
    body:has(.ls-hero) .ls-canvas .pe-section-head + .text-muted,
    body:has(.ls-hero) .ls-canvas .pe-section-head ~ p.text-muted:first-of-type {
        font-size: 14px !important;
        color: var(--ca-ink-mute) !important;
        margin-top: -16px !important;
        margin-bottom: 28px !important;
        max-width: 64ch;
        line-height: 1.55;
    }

    /* Form field type — bigger, more breathable inside the canvas */
    body:has(.ls-hero) .ls-canvas .form-group label {
        font-size: 12.5px !important;
        font-weight: 600 !important;
        color: var(--ca-ink) !important;
        text-transform: uppercase !important;
        letter-spacing: .08em !important;
        margin-bottom: 8px !important;
    }
    body:has(.ls-hero) .ls-canvas .form-control,
    body:has(.ls-hero) .ls-canvas .custom-select,
    body:has(.ls-hero) .ls-canvas select.form-control {
        font-size: 14px !important;
        padding: 10px 14px !important;
        border-radius: 8px !important;
        background-color: var(--ca-surface) !important;
        border: 1px solid var(--ca-line) !important;
    }
    body:has(.ls-hero) .ls-canvas .form-control:focus,
    body:has(.ls-hero) .ls-canvas select.form-control:focus,
    body:has(.ls-hero) .ls-canvas .custom-select:focus {
        background-color: #ffffff !important;
        border-color: var(--ca-accent) !important;
        box-shadow: 0 0 0 3px rgba(30, 58, 95, .12) !important;
    }
    /* Selects inside the studio: explicit chevron + reserved padding so
       they read as dropdowns, not plain text inputs. */
    body:has(.ls-hero) .ls-canvas select.form-control,
    body:has(.ls-hero) .ls-canvas select {
        background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'><path fill='none' stroke='%235a6478' stroke-width='1.6' stroke-linecap='round' stroke-linejoin='round' d='M2.5 4.5 6 8l3.5-3.5'/></svg>") !important;
        background-repeat: no-repeat !important;
        background-position: right 12px center !important;
        background-size: 12px 12px !important;
        padding-right: 36px !important;
        appearance: none !important;
        -webkit-appearance: none !important;
        -moz-appearance: none !important;
        cursor: pointer !important;
    }
    body:has(.ls-hero) .ls-canvas select.form-control:hover {
        border-color: var(--ca-line-strong) !important;
    }
    body:has(.ls-hero) .ls-canvas .form-text,
    body:has(.ls-hero) .ls-canvas small.text-muted {
        font-size: 12px !important;
        color: var(--ca-ink-mute) !important;
        margin-top: 6px !important;
    }

    /* Inner cards inside the studio (amenity groups, sub-sections) —
       totally flat. Group title + hairline + items. No box. */
    body:has(.ls-hero) .ls-canvas .card,
    body:has(.ls-hero) .ls-canvas .card.card-info,
    body:has(.ls-hero) .ls-canvas .card.card-success,
    body:has(.ls-hero) .content .ls-canvas .card {
        background: transparent !important;
        border: none !important;
        border-radius: 0 !important;
        box-shadow: none !important;
        margin-bottom: 28px !important;
    }
    body:has(.ls-hero) .ls-canvas .card > .card-header,
    body:has(.ls-hero) .content .ls-canvas .card > .card-header {
        background: transparent !important;
        padding: 0 0 10px 0 !important;
        border: none !important;
        border-bottom: 1px solid var(--ca-line) !important;
        margin-bottom: 16px !important;
        border-radius: 0 !important;
    }
    /* Group title — clean serif-adjacent feel, NOT aggressive uppercase caps. */
    body:has(.ls-hero) .ls-canvas .card > .card-header .card-title,
    body:has(.ls-hero) .content .ls-canvas .card > .card-header .card-title {
        font-family: var(--admin-font) !important;
        font-size: 15px !important;
        font-weight: 600 !important;
        text-transform: none !important;
        letter-spacing: -.005em !important;
        color: var(--ca-ink) !important;
    }
    body:has(.ls-hero) .ls-canvas .card > .card-body,
    body:has(.ls-hero) .content .ls-canvas .card > .card-body { padding: 0 !important; }

    /* ─── Form row grid ─── kill Bootstrap's row+col asymmetry inside
       the studio. Auto-fit so 2-item rows fill full width, 3-item rows
       split into thirds, etc. Empty tracks collapse. */
    body:has(.ls-hero) .ls-canvas .card-body > .row,
    body:has(.ls-hero) .ls-canvas > .row,
    body:has(.ls-hero) .ls-canvas .form > .row {
        display: grid !important;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)) !important;
        gap: 18px 22px !important;
        margin: 0 !important;
    }
    body:has(.ls-hero) .ls-canvas .card-body > .row > [class*="col-"],
    body:has(.ls-hero) .ls-canvas > .row > [class*="col-"],
    body:has(.ls-hero) .ls-canvas .form > .row > [class*="col-"] {
        max-width: none !important;
        flex: none !important;
        width: auto !important;
        padding: 0 !important;
    }
    /* Wide fields — long descriptions / textareas — span all columns */
    body:has(.ls-hero) .ls-canvas .card-body > .row > .col-md-12,
    body:has(.ls-hero) .ls-canvas > .row > .col-md-12,
    body:has(.ls-hero) .ls-canvas .form > .row > .col-md-12 {
        grid-column: 1 / -1 !important;
    }
    /* Small/utility cells (icons, sort arrows) shouldn't claim a full grid
       track — let them shrink. Used in the additional-fees view rows. */
    body:has(.ls-hero) .ls-canvas .card-body > .row > .col-md-1,
    body:has(.ls-hero) .ls-canvas .card-body > .row > .col-md-2 {
        align-self: center;
    }
    /* Form groups inside the grid — no extra margin (grid gap handles it) */
    body:has(.ls-hero) .ls-canvas .form-group { margin-bottom: 0 !important; }
    body:has(.ls-hero) .ls-canvas .form-group label {
        display: block;
        font-size: 12.5px !important;
        font-weight: 600 !important;
        color: var(--ca-ink) !important;
        text-transform: none !important;
        letter-spacing: -.005em !important;
        margin-bottom: 6px !important;
    }
    body:has(.ls-hero) .ls-canvas .form-group small.text-muted,
    body:has(.ls-hero) .ls-canvas .form-group .form-text {
        display: block;
        font-size: 12px !important;
        color: var(--ca-ink-mute) !important;
        margin-top: 6px !important;
        line-height: 1.5 !important;
    }
    body:has(.ls-hero) .ls-canvas .form-group .text-danger {
        display: block;
        font-size: 12px !important;
        margin-top: 4px !important;
    }

    /* ─── Amenity item rows — quiet, dense, scannable ─── */
    body:has(.ls-hero) .ls-canvas .card-body .col-md-4 > .d-flex,
    body:has(.ls-hero) .ls-canvas .card-body > .row > .col-md-4 > .d-flex {
        padding: 8px 12px !important;
        margin: 0 !important;
        border-radius: 8px !important;
        transition: background 100ms var(--ca-ease);
    }
    body:has(.ls-hero) .ls-canvas .card-body .col-md-4 > .d-flex:hover {
        background: var(--ca-surface-soft);
    }
    body:has(.ls-hero) .ls-canvas .card-body .col-md-4 > .d-flex label > span {
        font-size: 13.5px !important;
        font-weight: 500 !important;
        color: var(--ca-ink) !important;
    }
    body:has(.ls-hero) .ls-canvas .card-body .col-md-4 > .d-flex label i.fa-solid {
        color: var(--ca-ink-mute);
        opacity: .85;
        font-size: 13px;
    }
    /* Amenity star toggle — subtle */
    body:has(.ls-hero) .ls-canvas .star-toggle-label .fa-star {
        color: var(--ca-line-strong) !important;
        font-size: 13px !important;
        transition: color 140ms var(--ca-ease);
    }
    body:has(.ls-hero) .ls-canvas .star-toggle-input:checked + .star-toggle-label .fa-star {
        color: #f59e0b !important;
    }
    body:has(.ls-hero) .ls-canvas .star-toggle-label:hover .fa-star {
        color: var(--ca-ink-mute) !important;
    }
    /* Amenity checkboxes — refined */
    body:has(.ls-hero) .ls-canvas input[type="checkbox"] {
        width: 16px;
        height: 16px;
        accent-color: var(--ca-accent);
        cursor: pointer;
        margin: 0 !important;
    }

    /* Mobile */
    @media (max-width: 991px) {
        .ls-studio { padding: 12px 14px 48px; }
        body:has(.ls-hero) .ls-canvas { padding: 8px 0 24px 0; }
    }

    /* ═══════════════════════════════════════════════════════════════════════
       Motion + overlay polish — applies site-wide.
       Hover-lift on cards, smooth transitions on all interactive elements,
       page-level fade-in, and refined shadows that stack with depth.
       ═══════════════════════════════════════════════════════════════════════ */

    /* Page entrance — fade content in from below on initial load. */
    @keyframes ca-fade-up {
        from { opacity: 0; transform: translateY(8px); }
        to   { opacity: 1; transform: translateY(0); }
    }
    body .content-wrapper > .content,
    body .content-wrapper > .ls-studio {
        animation: ca-fade-up 380ms var(--ca-ease-out) both;
    }

    /* Cards no longer have hover-lift — they're transparent containers,
       lifting them creates a box-on-hover feeling we don't want. */

    /* Interactive icons / row hover affordance */
    body .content .card .table tbody tr {
        transition: background 140ms var(--ca-ease);
    }

    /* Buttons everywhere: smooth ease curve, scale-down on press */
    body .btn {
        transition: background 180ms var(--ca-ease), color 180ms var(--ca-ease),
                    border-color 180ms var(--ca-ease), box-shadow 200ms var(--ca-ease),
                    transform 180ms var(--ca-ease) !important;
    }
    body .btn:active { transform: translateY(0) scale(.98) !important; }

    /* Form controls: subtle lift on focus */
    body .form-control,
    body .custom-select {
        transition: border-color 180ms var(--ca-ease), box-shadow 200ms var(--ca-ease), background 180ms var(--ca-ease) !important;
    }

    /* Top-bar polish — soft shadow underneath when scrolled past the top */
    .ca-topbar {
        transition: box-shadow 240ms var(--ca-ease), background 240ms var(--ca-ease);
    }
    .ca-topbar.is-scrolled {
        background: rgba(10, 14, 26, 0.85) !important;
        box-shadow: 0 1px 0 var(--ca-line-on-dark), 0 12px 32px -12px rgba(0,0,0,.45);
    }

    /* Mega menu pop — soft elevation, no glow halo */
    .ca-megamenu {
        box-shadow: var(--ca-shadow-pop) !important;
        transform-origin: top center;
        transform: translateY(-6px) scale(.985);
        transition: opacity 200ms var(--ca-ease-out), transform 240ms var(--ca-ease-out), visibility 200ms;
    }
    .ca-topbar.is-menu-open .ca-megamenu {
        transform: translateY(0) scale(1);
    }
    /* Backdrop blur over the page when menu is open — directs attention */
    .ca-megamenu-backdrop {
        position: fixed;
        inset: 60px 0 0 0;
        background: rgba(15, 28, 51, 0.18);
        backdrop-filter: blur(8px) saturate(140%);
        -webkit-backdrop-filter: blur(8px) saturate(140%);
        opacity: 0;
        visibility: hidden;
        transition: opacity 240ms var(--ca-ease), visibility 240ms;
        z-index: 1029;
        pointer-events: none;
    }
    body.ca-menu-open .ca-megamenu-backdrop {
        opacity: 1;
        visibility: visible;
    }

    /* Megamenu list item — subtle hover lift, kept transitions tight. */
    .ca-megamenu-list a {
        transition: background 160ms var(--ca-ease), color 160ms var(--ca-ease), transform 160ms var(--ca-ease);
    }

    /* Search dropdown — soft elevation */
    .ca-search-dropdown {
        box-shadow: var(--ca-shadow-pop) !important;
        transform-origin: top center;
    }
    .ca-search.is-open .ca-search-dropdown {
        transform: translateY(0) scale(1);
    }
    .ca-search:not(.is-open) .ca-search-dropdown {
        transform: translateY(-4px) scale(.985);
    }
    .ca-search-item {
        transition: background 100ms var(--ca-ease), transform 100ms var(--ca-ease);
    }
    .ca-search-item:hover { transform: translateX(1px); }
    .ca-search-item.is-active {
        background: var(--ca-accent-faint) !important;
    }
    .ca-search-item.is-active .ca-search-item-icon {
        background: var(--ca-accent) !important;
        color: #ffffff !important;
        box-shadow: 0 4px 10px -4px rgba(79,70,229,.45);
    }
    .ca-search-item-title mark {
        background: rgba(79, 70, 229, .14) !important;
        color: var(--ca-accent-deep) !important;
        font-weight: 600;
    }

    /* Top-bar "Menu" button → indigo gradient when open */
    .ca-topbar.is-menu-open .ca-menu-trigger {
        background: var(--ca-accent) !important;
        color: #ffffff !important;
        border-color: transparent !important;
        box-shadow: 0 6px 18px -6px rgba(79,70,229,.45);
    }

    /* Account chip — indigo border glow on hover (dark-glass aesthetic) */
    .ca-account-link:hover {
        border-color: var(--ca-accent) !important;
        background: rgba(30, 58, 95, 0.10) !important;
        color: #ffffff !important;
        box-shadow: 0 0 0 3px rgba(30, 58, 95, .15);
    }

    /* Property edit section nav — saved-pulse glow ring */
    @keyframes pe-saved-pulse-indigo {
        0%   { box-shadow: 0 0 0 0 rgba(16,185,129,.55), 0 10px 24px -8px var(--ca-accent-glow); }
        70%  { box-shadow: 0 0 0 9px rgba(16,185,129,0),   0 10px 24px -8px var(--ca-accent-glow); }
        100% { box-shadow: 0 0 0 0 rgba(16,185,129,0),     0 10px 24px -8px var(--ca-accent-glow); }
    }
    body:has(.ls-hero) .pe-redesign-nav a.pe-just-saved {
        animation: pe-saved-pulse-indigo 1.4s var(--ca-ease-out);
    }

    /* Hero status pill — emerald glow when "Live" */
    .ls-hero .ls-status-pill:not(.is-draft) {
        background: rgba(16, 185, 129, .22) !important;
        box-shadow: inset 0 0 0 1px rgba(16, 185, 129, .35);
    }
    .ls-hero .ls-status-pill .ls-status-dot {
        animation: ca-pulse 2.4s var(--ca-ease) infinite;
    }
    @keyframes ca-pulse {
        0%, 100% { box-shadow: 0 0 0 2px rgba(16, 163, 74, .25); }
        50%      { box-shadow: 0 0 0 5px rgba(16, 163, 74, .10); }
    }

    /* Hero hover affordance for the action chips */
    .ls-hero-action,
    .ls-hero-back {
        transition: background 160ms var(--ca-ease), color 160ms var(--ca-ease),
                    border-color 160ms var(--ca-ease), transform 160ms var(--ca-ease);
    }
    .ls-hero-action:hover { transform: translateY(-1px); }

    /* Toast (toastr) modernization — indigo for info, emerald for success */
    body #toast-container > .toast-info {
        background-color: var(--ca-accent) !important;
    }

    /* Smooth scrolling site-wide */
    html { scroll-behavior: smooth; }

    /* Selection highlight — navy */
    ::selection {
        background: rgba(30, 58, 95, .22);
        color: var(--ca-ink);
    }

    /* Light scrollbars site-wide */
    body ::-webkit-scrollbar { width: 10px; height: 10px; }
    body ::-webkit-scrollbar-track { background: transparent; }
    body ::-webkit-scrollbar-thumb {
        background: var(--ca-line-strong);
        border-radius: 5px;
        border: 2px solid transparent;
        background-clip: padding-box;
    }
    body ::-webkit-scrollbar-thumb:hover { background: var(--ca-ink-quiet); background-clip: padding-box; }

    /* ─── Notification bell + dropdown ─── */
    .ca-bell {
        position: relative;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 36px; height: 36px;
        background: rgba(255, 255, 255, 0.04);
        border: 1px solid var(--ca-line-on-dark);
        border-radius: 999px;
        color: rgba(255, 255, 255, 0.86);
        cursor: pointer;
        transition: background 140ms ease, border-color 140ms ease, color 140ms ease;
        flex-shrink: 0;
    }
    .ca-bell:hover {
        background: rgba(255, 255, 255, 0.10);
        border-color: var(--ca-line-on-dark-strong);
        color: #ffffff;
    }
    .ca-bell.is-open {
        background: rgba(255, 255, 255, 0.14);
        border-color: rgba(255, 255, 255, 0.30);
        color: #ffffff;
    }
    .ca-bell i { font-size: 13px; }
    .ca-bell-dot {
        position: absolute;
        top: 7px; right: 8px;
        width: 8px; height: 8px;
        border-radius: 50%;
        background: var(--ca-status-live);
        border: 2px solid var(--ca-chrome);
        opacity: 0;
        transform: scale(.6);
        transition: opacity 200ms var(--ca-ease), transform 200ms var(--ca-ease);
    }
    .ca-bell.has-unread .ca-bell-dot { opacity: 1; transform: scale(1); }

    .ca-bell-dropdown {
        position: absolute;
        top: calc(100% + 10px);
        right: 22px;
        width: 380px;
        max-width: calc(100vw - 44px);
        background: var(--ca-surface);
        border: 1px solid var(--ca-line);
        border-radius: 14px;
        box-shadow: var(--ca-shadow-pop);
        max-height: calc(100vh - 100px);
        overflow: hidden;
        display: flex;
        flex-direction: column;
        opacity: 0;
        visibility: hidden;
        transform: translateY(-6px) scale(.985);
        transform-origin: top right;
        transition: opacity 180ms var(--ca-ease-out), transform 200ms var(--ca-ease-out), visibility 180ms;
        z-index: 1031;
        color: var(--ca-ink);
    }
    .ca-bell-dropdown.is-open {
        opacity: 1; visibility: visible; transform: translateY(0) scale(1);
    }
    .ca-bell-head {
        padding: 14px 16px;
        border-bottom: 1px solid var(--ca-line);
        display: flex; align-items: center; justify-content: space-between;
        font-size: 11px; font-weight: 700; letter-spacing: .14em;
        text-transform: uppercase; color: var(--ca-ink-mute);
    }
    .ca-bell-count {
        font-family: var(--admin-font-mono);
        font-size: 10.5px; font-weight: 600;
        color: var(--ca-ink-soft);
        background: var(--ca-surface-soft);
        padding: 2px 8px; border-radius: 999px;
        letter-spacing: .02em;
    }
    .ca-bell-body { flex: 1; overflow-y: auto; padding: 6px; }
    .ca-bell-body::-webkit-scrollbar { width: 6px; }
    .ca-bell-body::-webkit-scrollbar-thumb { background: var(--ca-line-strong); border-radius: 3px; }
    .ca-bell-item {
        display: flex; gap: 12px; align-items: flex-start;
        padding: 10px 12px;
        border-radius: 8px;
        text-decoration: none !important;
        color: var(--ca-ink);
        transition: background 120ms ease;
    }
    .ca-bell-item:hover { background: var(--ca-surface-soft); }
    .ca-bell-item-icon {
        flex-shrink: 0;
        width: 32px; height: 32px;
        border-radius: 8px;
        background: var(--ca-surface-mute);
        color: var(--ca-ink-soft);
        display: inline-flex; align-items: center; justify-content: center;
        font-size: 12px;
    }
    .ca-bell-item.kind-booking .ca-bell-item-icon { background: rgba(4, 120, 87, .10); color: #047857; }
    .ca-bell-item.kind-contact .ca-bell-item-icon { background: rgba(30, 58, 95, .10); color: var(--ca-accent); }
    .ca-bell-item.kind-payment .ca-bell-item-icon { background: rgba(180, 83, 9, .10); color: #b45309; }
    .ca-bell-item-body { flex: 1; min-width: 0; }
    .ca-bell-item-row {
        display: flex; align-items: baseline; gap: 10px; min-width: 0;
    }
    .ca-bell-item-title {
        flex: 1; min-width: 0;
        font-size: 13px; font-weight: 600;
        color: var(--ca-ink); letter-spacing: -.005em;
        line-height: 1.3;
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .ca-bell-item-time {
        flex-shrink: 0;
        font-size: 10.5px;
        color: var(--ca-ink-quiet);
        font-family: var(--admin-font-mono);
        white-space: nowrap;
    }
    .ca-bell-item-sub {
        display: block;
        font-size: 11.5px; color: var(--ca-ink-mute);
        margin-top: 2px;
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        font-family: var(--admin-font-mono);
    }
    .ca-bell-empty { padding: 36px 16px; text-align: center; font-size: 13px; color: var(--ca-ink-mute); }
    .ca-bell-foot {
        padding: 10px 14px;
        border-top: 1px solid var(--ca-line);
        background: var(--ca-surface-soft);
        display: flex; justify-content: space-between; gap: 12px;
        font-size: 12px;
    }
    .ca-bell-foot a {
        color: var(--ca-ink-soft); text-decoration: none;
        font-weight: 500;
    }
    .ca-bell-foot a:hover { color: var(--ca-accent); }

    /* ─── Welcome-back toast (premium pop on dashboard landing) ─── */
    .ca-welcome-toast {
        position: fixed;
        top: 88px;
        right: 24px;
        z-index: 1080;
        padding: 14px 18px 14px 16px;
        background: var(--ca-chrome);
        color: #ffffff;
        border-radius: 14px;
        box-shadow:
            0 1px 0 rgba(255,255,255,.06) inset,
            0 24px 60px -22px rgba(15, 28, 51, .55),
            0 8px 24px -8px rgba(15, 28, 51, .35);
        display: flex; align-items: center; gap: 12px;
        opacity: 0;
        transform: translateX(40px);
        transition: opacity 320ms var(--ca-ease-out), transform 380ms var(--ca-ease-out);
        max-width: 340px;
        font-family: var(--admin-font);
    }
    .ca-welcome-toast.is-show { opacity: 1; transform: translateX(0); }
    .ca-welcome-toast.is-leave { opacity: 0; transform: translateX(20px); }
    .ca-welcome-toast::before {
        content: "";
        flex-shrink: 0;
        width: 8px; height: 8px;
        border-radius: 50%;
        background: var(--ca-status-live);
        box-shadow: 0 0 0 4px rgba(16, 185, 129, .25);
        animation: ca-welcome-pulse 2.4s ease-in-out infinite;
    }
    @keyframes ca-welcome-pulse {
        0%, 100% { box-shadow: 0 0 0 4px rgba(16,185,129,.25); }
        50%      { box-shadow: 0 0 0 7px rgba(16,185,129,0); }
    }
    .ca-welcome-toast-body { display: flex; flex-direction: column; gap: 2px; min-width: 0; }
    .ca-welcome-toast-greeting {
        font-size: 14px; font-weight: 600; letter-spacing: -.005em;
        color: #ffffff;
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .ca-welcome-toast-sub {
        font-size: 11.5px; color: rgba(255,255,255,.65);
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }

    /* Reduced motion respect */
    @media (prefers-reduced-motion: reduce) {
        *, *::before, *::after {
            animation-duration: 0.01ms !important;
            animation-iteration-count: 1 !important;
            transition-duration: 0.01ms !important;
            scroll-behavior: auto !important;
        }
    }

    /* ═══════════════════════════════════════════════════════════════════════
       Premium polish — bring every Bootstrap/AdminLTE default in line
       with the topbar's premium feel. Tables, filters, pagination,
       badges, action buttons. The data IS the product; treat it well.
       ═══════════════════════════════════════════════════════════════════════ */

    /* Form controls — small variant (used in inline filters) */
    body .form-control-sm,
    body select.form-control-sm,
    body .input-group-sm > .form-control {
        height: 36px !important;
        font-size: 13px !important;
        padding: 6px 12px !important;
        border-radius: 8px !important;
        border: 1px solid var(--ca-line) !important;
        background: var(--ca-surface) !important;
        color: var(--ca-ink) !important;
        transition: border-color 140ms var(--ca-ease), box-shadow 200ms var(--ca-ease) !important;
    }
    body .form-control-sm::placeholder { color: var(--ca-ink-quiet) !important; }
    body .form-control-sm:focus {
        border-color: var(--ca-accent) !important;
        background: #ffffff !important;
        box-shadow: 0 0 0 3px rgba(30, 58, 95, .14) !important;
    }
    /* Small select chevron — replace browser default with a clean one */
    body select.form-control-sm,
    body select.form-control {
        background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'><path fill='none' stroke='%235a6478' stroke-width='1.6' stroke-linecap='round' stroke-linejoin='round' d='M2.5 4.5 6 8l3.5-3.5'/></svg>") !important;
        background-repeat: no-repeat !important;
        background-position: right 10px center !important;
        background-size: 12px 12px !important;
        padding-right: 32px !important;
        appearance: none !important;
        -webkit-appearance: none !important;
        -moz-appearance: none !important;
    }

    /* Reset button — quiet ghost */
    body .btn-outline-secondary {
        background: transparent !important;
        border: 1px solid var(--ca-line-strong) !important;
        color: var(--ca-ink-soft) !important;
        font-weight: 500 !important;
    }
    body .btn-outline-secondary:hover {
        background: var(--ca-surface-soft) !important;
        border-color: var(--ca-ink-mute) !important;
        color: var(--ca-ink) !important;
    }

    /* btn-sm sizing */
    body .btn-sm {
        font-size: 12.5px !important;
        padding: 6px 12px !important;
        border-radius: 7px !important;
        height: 32px !important;
        line-height: 1 !important;
    }

    /* ─── Badges (status pills) — refined ─── */
    body .badge {
        display: inline-flex !important;
        align-items: center !important;
        gap: 4px !important;
        padding: 3px 9px !important;
        font-size: 10.5px !important;
        font-weight: 600 !important;
        line-height: 1.5 !important;
        border-radius: 999px !important;
        letter-spacing: .03em !important;
    }
    body .badge.badge-light {
        background: var(--ca-surface-soft) !important;
        color: var(--ca-ink-soft) !important;
        border: 1px solid var(--ca-line) !important;
    }
    body .badge.badge-success,
    body .badge.bg-success,
    body .badge[class*="badge-success"] {
        background: rgba(4, 120, 87, .10) !important;
        color: #047857 !important;
        border: 1px solid rgba(4, 120, 87, .22) !important;
    }
    body .badge.badge-warning {
        background: rgba(217, 119, 6, .10) !important;
        color: #b45309 !important;
        border: 1px solid rgba(217, 119, 6, .22) !important;
    }
    body .badge.badge-danger {
        background: rgba(190, 24, 24, .10) !important;
        color: #be1818 !important;
        border: 1px solid rgba(190, 24, 24, .22) !important;
    }
    body .badge.badge-info {
        background: rgba(30, 58, 95, .10) !important;
        color: var(--ca-accent) !important;
        border: 1px solid rgba(30, 58, 95, .22) !important;
    }
    body .badge.badge-primary,
    body .badge.badge-secondary {
        background: rgba(30, 58, 95, .10) !important;
        color: var(--ca-accent) !important;
        border: 1px solid rgba(30, 58, 95, .22) !important;
    }

    /* ─── Tables (data) — flowing data list, no container ───
       The table has NO panel, NO outline, NO shadow, NO rounded corners.
       It flows on the page background like a continuous list. The only
       visual structure: a hairline below thead and between rows. */
    body .content .card .table,
    body .table {
        margin-bottom: 0 !important;
        border-collapse: separate !important;
        border-spacing: 0 !important;
        background: transparent !important;
        border-radius: 0 !important;
        overflow: visible !important;
        box-shadow: none !important;
    }
    body .content .card .table thead,
    body .table thead {
        background: transparent;
    }
    body .content .card .table thead th,
    body .table thead th {
        background: transparent !important;
        color: var(--ca-ink-mute) !important;
        font-weight: 600 !important;
        font-size: 11px !important;
        text-transform: uppercase !important;
        letter-spacing: .10em !important;
        border-top: none !important;
        border-bottom: 1px solid var(--ca-line) !important;
        padding: 12px 14px !important;
        white-space: nowrap;
    }
    body .content .card .table tbody td,
    body .table tbody td {
        padding: 16px 18px !important;
        border-top: none !important;
        border-bottom: 1px solid var(--ca-line-soft) !important;
        vertical-align: middle !important;
        color: var(--ca-ink) !important;
        font-size: 13.5px !important;
    }
    body .content .card .table tbody tr:last-child td,
    body .table tbody tr:last-child td {
        border-bottom: none !important;
    }
    body .content .card .table.table-hover tbody tr,
    body .table.table-hover tbody tr {
        transition: background 140ms var(--ca-ease);
    }
    body .content .card .table.table-hover tbody tr:hover,
    body .table.table-hover tbody tr:hover {
        background: var(--ca-surface-soft) !important;
    }
    body .table tbody td strong {
        font-weight: 600 !important;
        color: var(--ca-ink) !important;
        font-size: 14px !important;
        letter-spacing: -.005em;
    }
    body .table tbody td small.text-muted {
        color: var(--ca-ink-mute) !important;
        font-size: 11.5px !important;
        font-family: var(--admin-font-mono);
    }

    /* Property cell — refined thumb + name + meta layout */
    body .table tbody td img[style*="object-fit:cover"] {
        width: 64px !important;
        height: 44px !important;
        border-radius: 8px !important;
        box-shadow: 0 0 0 1px rgba(15, 28, 51, .08) !important;
    }
    /* The badges row under the property name (location, photos) */
    body .table tbody td .badge.badge-light {
        font-size: 10.5px !important;
        font-weight: 500 !important;
        padding: 2px 7px !important;
        margin-right: 4px !important;
    }
    body .table tbody td .badge.badge-light i {
        font-size: 9px;
        margin-right: 3px;
        color: var(--ca-ink-quiet);
    }

    /* Status pills with leading dot for live/active/instant feel */
    body .table .badge.badge-success::before,
    body .table .badge.badge-info::before,
    body .table .badge.badge-warning::before,
    body .table .badge.badge-danger::before,
    body .table .badge.badge-secondary::before {
        content: "";
        display: inline-block;
        width: 6px;
        height: 6px;
        border-radius: 50%;
        margin-right: 5px;
        background: currentColor;
    }
    body .table .badge.badge-secondary {
        background: rgba(100, 116, 139, .10) !important;
        color: #64748b !important;
        border: 1px solid rgba(100, 116, 139, .22) !important;
    }

    /* Header column with sort indicator (DataTables) */
    body .table thead th.sorting,
    body .table thead th.sorting_asc,
    body .table thead th.sorting_desc {
        cursor: pointer;
        position: relative;
        padding-right: 28px !important;
    }
    body .table thead th.sorting::after,
    body .table thead th.sorting_asc::after,
    body .table thead th.sorting_desc::after {
        content: "";
        position: absolute;
        right: 14px;
        top: 50%;
        transform: translateY(-50%);
        width: 8px;
        height: 8px;
        background: currentColor;
        opacity: .35;
        clip-path: polygon(50% 0, 100% 100%, 0 100%);
    }
    body .table thead th.sorting_asc::after { opacity: .85; }
    body .table thead th.sorting_desc::after {
        clip-path: polygon(0 0, 100% 0, 50% 100%);
        opacity: .85;
    }

    /* Row checkboxes & action buttons — refined */
    body .table input[type="checkbox"] {
        width: 16px;
        height: 16px;
        accent-color: var(--ca-accent);
        cursor: pointer;
    }
    body .table .btn-sm,
    body .table .btn {
        padding: 6px 10px !important;
        font-size: 12px !important;
    }

    /* ─── DataTables wrapper — pagination + length + info ─── */
    body .dataTables_wrapper {
        font-family: var(--admin-font);
        overflow: visible !important;
    }
    body .dataTables_wrapper .dataTables_scroll {
        overflow: visible !important;
    }
    body .dataTables_length,
    body .dataTables_filter,
    body .dataTables_info,
    body .dataTables_paginate {
        margin: 14px 0 !important;
        font-size: 13px !important;
        color: var(--ca-ink-soft) !important;
    }
    body .dataTables_length select,
    body .dataTables_filter input {
        height: 34px !important;
        border: 1px solid var(--ca-line) !important;
        border-radius: 8px !important;
        padding: 4px 10px !important;
        font-size: 13px !important;
        background: var(--ca-surface) !important;
        color: var(--ca-ink) !important;
        transition: border-color 140ms var(--ca-ease), box-shadow 200ms var(--ca-ease) !important;
        margin: 0 6px !important;
    }
    body .dataTables_length select:focus,
    body .dataTables_filter input:focus {
        border-color: var(--ca-accent) !important;
        box-shadow: 0 0 0 3px rgba(30, 58, 95, .14) !important;
        outline: none !important;
    }

    /* Pagination buttons */
    body .dataTables_paginate .paginate_button,
    body .pagination .page-link {
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        min-width: 34px !important;
        height: 34px !important;
        padding: 0 12px !important;
        margin: 0 2px !important;
        background: var(--ca-surface) !important;
        border: 1px solid var(--ca-line) !important;
        border-radius: 8px !important;
        color: var(--ca-ink-soft) !important;
        font-size: 13px !important;
        font-weight: 500 !important;
        cursor: pointer !important;
        transition: all 140ms var(--ca-ease) !important;
    }
    body .dataTables_paginate .paginate_button:hover,
    body .pagination .page-link:hover {
        background: var(--ca-surface-soft) !important;
        border-color: var(--ca-line-strong) !important;
        color: var(--ca-ink) !important;
    }
    body .dataTables_paginate .paginate_button.current,
    body .dataTables_paginate .paginate_button.active,
    body .pagination .page-item.active .page-link {
        background: var(--ca-accent) !important;
        border-color: var(--ca-accent) !important;
        color: #ffffff !important;
        box-shadow: 0 4px 10px -4px rgba(30, 58, 95, .35) !important;
    }
    body .dataTables_paginate .paginate_button.disabled,
    body .pagination .page-item.disabled .page-link {
        background: var(--ca-surface-soft) !important;
        color: var(--ca-ink-quiet) !important;
        border-color: var(--ca-line) !important;
        cursor: not-allowed !important;
        pointer-events: none !important;
    }

    /* Total badge next to page heading — refined chip */
    body .content-header .badge.badge-light {
        background: var(--ca-surface) !important;
        color: var(--ca-ink-soft) !important;
        border: 1px solid var(--ca-line) !important;
        padding: 4px 10px !important;
        font-size: 11.5px !important;
        font-weight: 600 !important;
        letter-spacing: .04em !important;
        margin-left: 8px !important;
    }

    /* "Add property" type page-header CTA buttons — premium navy */
    body .content-header .btn-primary,
    body .content-header .btn-success {
        height: 38px !important;
        padding: 0 18px !important;
        font-size: 13px !important;
    }

    /* Card body bulk-action strip (alert-info inside card) — neutralize */
    body .content .card > .alert.alert-info {
        background: var(--ca-surface-soft) !important;
        color: var(--ca-ink) !important;
        border: none !important;
        border-bottom: 1px solid var(--ca-line) !important;
        border-radius: 0 !important;
        margin: 0 !important;
        padding: 10px 0 !important;
        font-size: 13px !important;
    }

    /* ─── Page toolbar ─── one row containing the heading, filters, and
       the page's primary CTA. Replaces the standalone content_header +
       card-header pattern. Premium, dense, deliberate. */
    .ca-page-toolbar {
        display: flex;
        align-items: center;
        gap: 18px;
        padding: 24px 0 18px;
        flex-wrap: wrap;
    }
    .ca-page-toolbar-title {
        display: flex;
        align-items: baseline;
        gap: 12px;
        flex-shrink: 0;
    }
    .ca-page-toolbar-title h1 {
        font-family: var(--admin-font);
        font-size: 22px;
        font-weight: 600;
        letter-spacing: -.018em;
        line-height: 1.2;
        margin: 0;
        color: var(--ca-ink);
    }
    .ca-page-toolbar-count {
        font-family: var(--admin-font-mono);
        font-size: 11.5px;
        font-weight: 600;
        color: var(--ca-ink-mute);
        background: var(--ca-surface);
        padding: 3px 9px;
        border-radius: 999px;
        border: 1px solid var(--ca-line);
        line-height: 1;
        letter-spacing: .02em;
    }
    .ca-page-toolbar-filters {
        display: flex;
        align-items: center;
        gap: 8px;
        flex: 1;
        flex-wrap: wrap;
        margin-left: 8px;
    }
    .ca-filter-search {
        position: relative;
        flex: 1;
        min-width: 220px;
        max-width: 360px;
    }
    .ca-filter-search i {
        position: absolute;
        left: 12px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--ca-ink-quiet);
        font-size: 12px;
        pointer-events: none;
    }
    .ca-filter-search input {
        box-sizing: border-box;
        width: 100%;
        height: 36px;
        padding: 0 12px 0 34px;
        background: var(--ca-surface);
        border: 1px solid var(--ca-line);
        border-radius: 8px;
        font-family: var(--admin-font);
        font-size: 13px;
        color: var(--ca-ink);
        transition: border-color 140ms var(--ca-ease), box-shadow 200ms var(--ca-ease), background 140ms var(--ca-ease);
    }
    .ca-filter-search input::placeholder { color: var(--ca-ink-quiet); }
    .ca-filter-search input:hover { border-color: var(--ca-line-strong); }
    .ca-filter-search input:focus {
        outline: none;
        border-color: var(--ca-accent);
        background: #ffffff;
        box-shadow: 0 0 0 3px rgba(30, 58, 95, .14);
    }
    .ca-filter-select {
        box-sizing: border-box;
        height: 36px;
        padding: 0 32px 0 12px;
        background: var(--ca-surface) url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'><path fill='none' stroke='%235a6478' stroke-width='1.6' stroke-linecap='round' stroke-linejoin='round' d='M2.5 4.5 6 8l3.5-3.5'/></svg>") right 10px center / 12px 12px no-repeat;
        border: 1px solid var(--ca-line);
        border-radius: 8px;
        font-family: var(--admin-font);
        font-size: 13px;
        color: var(--ca-ink);
        appearance: none;
        -webkit-appearance: none;
        -moz-appearance: none;
        cursor: pointer;
        transition: border-color 140ms var(--ca-ease), box-shadow 200ms var(--ca-ease);
    }
    .ca-filter-select:hover { border-color: var(--ca-line-strong); }
    .ca-filter-select:focus {
        outline: none;
        border-color: var(--ca-accent);
        box-shadow: 0 0 0 3px rgba(30, 58, 95, .14);
    }
    .ca-filter-reset {
        box-sizing: border-box;
        height: 36px;
        padding: 0 14px;
        background: transparent;
        border: 1px solid var(--ca-line);
        border-radius: 8px;
        font-family: var(--admin-font);
        font-size: 13px;
        font-weight: 500;
        color: var(--ca-ink-mute);
        cursor: pointer;
        transition: background 140ms var(--ca-ease), border-color 140ms var(--ca-ease), color 140ms var(--ca-ease);
    }
    .ca-filter-reset:hover {
        background: var(--ca-surface-soft);
        border-color: var(--ca-line-strong);
        color: var(--ca-ink);
    }
    .ca-page-toolbar-action {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        height: 38px;
        padding: 0 16px;
        background: var(--ca-accent);
        border: none;
        border-radius: 8px;
        color: #ffffff !important;
        font-family: var(--admin-font);
        font-size: 13px;
        font-weight: 600;
        letter-spacing: .01em;
        text-decoration: none !important;
        flex-shrink: 0;
        box-shadow:
            0 1px 2px rgba(15, 28, 51, .14),
            0 4px 12px -4px rgba(15, 28, 51, .25);
        transition: background 140ms var(--ca-ease), transform 140ms var(--ca-ease), box-shadow 200ms var(--ca-ease);
    }
    .ca-page-toolbar-action:hover {
        background: var(--ca-accent-deep);
        color: #ffffff !important;
        transform: translateY(-1px);
        box-shadow:
            0 1px 2px rgba(15, 28, 51, .14),
            0 8px 18px -6px rgba(15, 28, 51, .35);
    }
    .ca-page-toolbar-action i { font-size: 11px; }

    /* Mobile: stack the toolbar */
    @media (max-width: 992px) {
        .ca-page-toolbar { flex-direction: column; align-items: stretch; }
        .ca-page-toolbar-title { justify-content: space-between; }
        .ca-page-toolbar-filters { margin-left: 0; }
        .ca-filter-search { max-width: none; }
    }

    /* Hide content-header on pages that have a .ca-page-toolbar — they
       carry their own heading inside the toolbar. */
    body:has(.ca-page-toolbar) .content-header { display: none !important; }

    /* ─── Dropdown menus (action ellipsis, etc.) — premium ─── */
    body .dropdown-menu {
        display: none !important;
        position: absolute !important;
        z-index: 2000 !important;
        background: var(--ca-surface) !important;
        border: 1px solid var(--ca-line) !important;
        border-radius: 10px !important;
        box-shadow: var(--ca-shadow-pop) !important;
        padding: 6px !important;
        min-width: 200px !important;
        font-family: var(--admin-font);
    }
    body .dropdown-menu.show {
        display: block !important;
    }
    body .dropdown-menu .dropdown-item {
        display: flex !important;
        align-items: center !important;
        width: 100% !important;
        clear: both !important;
        white-space: nowrap !important;
        padding: 8px 12px !important;
        border-radius: 6px !important;
        font-size: 13px !important;
        color: var(--ca-ink) !important;
        font-weight: 500 !important;
        transition: background 100ms var(--ca-ease), color 100ms var(--ca-ease) !important;
    }
    body .dropdown-menu .dropdown-item:hover,
    body .dropdown-menu .dropdown-item:focus {
        background: var(--ca-surface-soft) !important;
        color: var(--ca-accent) !important;
    }
    body .dropdown-menu .dropdown-item i {
        font-size: 12px !important;
        color: var(--ca-ink-mute) !important;
        width: 16px !important;
        text-align: center !important;
    }
    body .dropdown-menu .dropdown-item:hover i { color: var(--ca-accent) !important; }
    body .dropdown-menu .dropdown-item.text-danger { color: #be1818 !important; }
    body .dropdown-menu .dropdown-item.text-danger:hover {
        background: rgba(190, 24, 24, .06) !important;
        color: #991818 !important;
    }
    body .dropdown-menu .dropdown-item.text-danger:hover i { color: #be1818 !important; }
    body .dropdown-menu .dropdown-divider {
        margin: 6px 4px !important;
        border-top: 1px solid var(--ca-line) !important;
    }

    /* Action button (3-dot ellipsis in table rows) — quieter */
    body .table .dropdown .btn-outline-secondary {
        width: 32px !important;
        height: 32px !important;
        padding: 0 !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        background: transparent !important;
        border-color: transparent !important;
        color: var(--ca-ink-mute) !important;
    }
    body .table .dropdown .btn-outline-secondary:hover {
        background: var(--ca-surface-soft) !important;
        border-color: var(--ca-line) !important;
        color: var(--ca-ink) !important;
    }
    body .table .dropdown .btn-outline-secondary:focus {
        box-shadow: 0 0 0 3px rgba(30, 58, 95, .14) !important;
    }
</style>
<link rel="stylesheet" href="{{ asset('front/admin/css/admin-stable.css') }}?ver={{ file_exists(base_path('../front/admin/css/admin-stable.css')) ? filemtime(base_path('../front/admin/css/admin-stable.css')) : time() }}">
@stop

@section("js")
<script src="https://cdn.jsdelivr.net/npm/@fancyapps/ui@5.0/dist/fancybox/fancybox.umd.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<script src="https://code.jquery.com/ui/1.13.1/jquery-ui.js"></script>
<script src="{{ asset("ckeditor/ckeditor.js") }}"></script>
<script src="{{ asset("toastr/toastr.js") }}"></script>
<script>
    $(function() {

        @if(Session::has("success"))
        toastr.success("{{ Session::get("success") }}");
        @endif
        @if(Session::has("danger"))
        toastr.error("{{ Session::get("danger") }}");
        @endif

    });
    $(document).on("change", ".all-select", function() {

        select = $(this).prop("checked");
        if (select) {
            $(".single-select").each(function() {
                $(this).prop("checked", true);
            });
        } else {
            $(".single-select").each(function() {
                $(this).prop("checked", false);
            });
        }
    });


    $(document).on("change", ".single-select", function() {
        flag = 0;
        $(".single-select").each(function() {
            if ($(this).prop("checked")) {

            } else {
                flag = 1;
            }
        });
        if (flag == 1) {
            $(".all-select").prop("checked", false);
        } else {
            $(".all-select").prop("checked", true);
        }
    })

    function ajaxCallCheckBoxDelete(url, indexPage, indexName) {
        id = [];
        flag = 0;
        $(".single-select").each(function() {
            if ($(this).prop("checked")) {
                id.push($(this).val());
            } else {
                flag = 1;
            }
        });
        if (id.length > 0) {
            if (confirm("Are you sure you want to delete this " + indexName + "?")) {

                data = {
                    _token: "{{ csrf_token() }}",
                    id: id
                };
                $.post(url, data, function(data) {
                    if (flag == 1)
                        location.href = location.href;
                    else
                        location.href = indexPage;
                })
            }
        } else {
            alert("please select checkbox")
        }
    }

    $(function() {
        $('[data-toggle="tooltip"]').tooltip();
    });
</script>

<script>
window.VR_DATEPICKER_CONFIG = {
    easepickCss: "{{ asset('easepick/easepick.css') }}",
    themeCss: "{{ asset('front/css/vr-datepicker.css') }}",
    adminBlockedUrl: "{{ url('admin/api/datepicker/blocked-dates') }}"
};
</script>
<script src="{{ asset('easepick/easepick.umd.min.js') }}"></script>
<script src="{{ asset('front/js/vr-datepicker.js') }}"></script>

@stop


@section("footer")
<div class="float-right d-none d-sm-block">
    <b>Version</b> 1.0.0
</div>
<strong>Copyright &copy; {{ date('Y')}} <a href="{{ url('/') }}">Administrator</a>.</strong> All rights
reserved.
@stop

{{-- ─── Custom top bar ───
     Replaces AdminLTE's left sidebar entirely. Brand · hover mega-menu ·
     prominent search · account. The body section override below prepends
     this above AdminLTE's wrapper so it sits at the viewport top. --}}
@section('body')
    <header class="ca-topbar" id="ca-topbar">
        <div class="ca-topbar-inner">

            <a href="{{ url('/admin') }}" class="ca-brand" aria-label="{{ $setting_data['website'] ?? 'Admin' }} home">
                <span class="ca-brand-mark" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <rect x="2"  y="13" width="9"   height="9"   rx="2"  fill="currentColor"/>
                        <rect x="9.5" y="7.5" width="7" height="7" rx="1.6" fill="currentColor" opacity=".75"/>
                        <rect x="15.5" y="3.5" width="5" height="5" rx="1.2" fill="currentColor" opacity=".55"/>
                    </svg>
                </span>
                <span class="ca-brand-name">{{ $setting_data['website'] ?? 'Admin' }}</span>
            </a>

            <button type="button" class="ca-menu-trigger" id="ca-menu-trigger"
                    aria-haspopup="true" aria-expanded="false" aria-controls="ca-megamenu">
                <span class="ca-menu-bars" aria-hidden="true">
                    <span></span><span></span><span></span>
                </span>
                <span class="ca-menu-label">Menu</span>
                <i class="fa fa-chevron-down ca-menu-chev" aria-hidden="true"></i>
            </button>

            <div class="ca-search" role="search" id="ca-search">
                <i class="fa fa-search ca-search-icon" aria-hidden="true"></i>
                <input type="text" name="search" class="ca-search-input" id="ca-search-input"
                       placeholder="Search properties, bookings, content, settings…"
                       autocomplete="off" spellcheck="false"
                       aria-haspopup="listbox" aria-controls="ca-search-dropdown" role="combobox">
                <span class="ca-search-loading" aria-hidden="true"></span>
                <kbd class="ca-search-kbd" id="ca-search-kbd">⌘&nbsp;K</kbd>

                {{-- Dropdown panel — populated by JS. Hidden until input is focused. --}}
                <div class="ca-search-dropdown" id="ca-search-dropdown" role="listbox" aria-label="Search results">
                    <div class="ca-search-results" id="ca-search-results"></div>
                    <div class="ca-search-foot">
                        <span class="ca-search-foot-hint"><kbd>↑</kbd><kbd>↓</kbd> Navigate</span>
                        <span class="ca-search-foot-hint"><kbd>↵</kbd> Open</span>
                        <span class="ca-search-foot-hint"><kbd>Esc</kbd> Close</span>
                    </div>
                </div>
            </div>

            {{-- Notification bell — recent booking + contact + payment activity --}}
            <button type="button" class="ca-bell" id="ca-bell" aria-label="Notifications" aria-haspopup="true" aria-expanded="false">
                <i class="fa fa-bell" aria-hidden="true"></i>
                <span class="ca-bell-dot" id="ca-bell-dot" aria-hidden="true"></span>
            </button>

            <div class="ca-bell-dropdown" id="ca-bell-dropdown" role="menu" aria-label="Notifications">
                <div class="ca-bell-head">
                    <span>Activity</span>
                    <span class="ca-bell-count" id="ca-bell-count"></span>
                </div>
                <div class="ca-bell-body" id="ca-bell-body">
                    <div class="ca-bell-empty">Loading…</div>
                </div>
                <div class="ca-bell-foot">
                    <a href="{{ url('admin/booking-enquiries') }}">All bookings</a>
                    <a href="{{ url('admin/contact-us-enquiries') }}">All contacts</a>
                </div>
            </div>

            <div class="ca-account">
                <a href="{{ url('/logout') }}" class="ca-account-link" title="Sign out">
                    <span class="ca-account-avatar">{{ Str::upper(Str::substr(auth()->user()->name ?? 'A', 0, 1)) }}</span>
                    <i class="fa fa-sign-out-alt" aria-hidden="true"></i>
                </a>
            </div>

        </div>

        {{-- Mega menu — descriptions per item give it depth without
             feeling like a generic SaaS template. --}}
        @php
            $caDescMap = [
                'admin/booking-enquiries'              => 'Booking requests and reservations',
                'admin/contact-us-enquiries'           => 'Guest inquiries from the site',
                'admin/payments'                       => 'Charges, refunds, payouts',
                'admin/properties'                     => 'Rental properties and PMS links',
                'admin/property-categories'            => 'Optional owner-defined property groups',
                'admin/attractions'                    => 'Local experiences and packages',
                'admin/coupons'                        => 'Promotional discount codes',
                'admin/scrape-sources'                 => 'PMS sync and external imports',
                'admin/cms'                            => 'Pages, content, SEO',
                'admin/faqs'                           => 'Frequently asked questions',
                'admin/settings/stay-communications'   => 'Stay-cycle email content',
                'admin/email-templates'                => 'Render emails as guests see them',
                'admin/settings/rental-agreement'      => 'Terms guests sign before stay',
                'admin/setting'                        => 'Brand, contact, defaults',
                'admin/settings/payment'               => 'Deposits, taxes, fees',
                'admin/settings/payment-gateways'      => 'Stripe, PayPal, AuthorizeNet',
                'admin/settings/mail'                  => 'SMTP and deliverability',
                'admin/settings/spam-protection'       => 'Bot challenge, blocklists',
                'admin/users'                          => 'Admin logins and password resets',
                'admin/help'                           => 'Support and help desk',
                'logout'                               => 'End your session',
            ];
            $caMenu = config('adminlte.menu', []);
        @endphp
        <div class="ca-megamenu" id="ca-megamenu" role="menu" aria-label="Main navigation">
            <div class="ca-megamenu-grid">
                @foreach($caMenu as $section)
                    @if(!is_array($section)) @continue @endif
                    @if(isset($section['header'])) @continue @endif

                    @if(isset($section['submenu']) && is_array($section['submenu']))
                        <div class="ca-megamenu-col">
                            <div class="ca-megamenu-title">
                                <span>{{ $section['text'] ?? '' }}</span>
                            </div>
                            <ul class="ca-megamenu-list">
                                @foreach($section['submenu'] as $item)
                                    @if(!is_array($item)) @continue @endif
                                    @php $itemUrl = $item['url'] ?? ''; @endphp
                                    {{-- Per-customer feature flags: items for features switched
                                         off in /admin/dev/features are hidden (devs see all). --}}
                                    @if(\App\Helper\FeatureFlags::urlHidden($itemUrl)) @continue @endif
                                    <li>
                                        <a href="{{ url($itemUrl ?: '#') }}">
                                            @isset($item['icon'])
                                                <i class="{{ $item['icon'] }}" aria-hidden="true"></i>
                                            @endisset
                                            <span class="ca-megamenu-item-body">
                                                <span class="ca-megamenu-item-title">{{ $item['text'] ?? '' }}</span>
                                                @if(!empty($caDescMap[$itemUrl]))
                                                    <span class="ca-megamenu-item-desc">{{ $caDescMap[$itemUrl] }}</span>
                                                @endif
                                            </span>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @else
                        @php
                            $secUrl    = $section['url'] ?? '';
                            $secTarget = $section['target'] ?? null;
                        @endphp
                        <a href="{{ url($secUrl ?: '#') }}" class="ca-megamenu-pill"
                           @if($secTarget) target="{{ $secTarget }}" rel="noopener" @endif>
                            @isset($section['icon'])
                                <i class="{{ $section['icon'] }}" aria-hidden="true"></i>
                            @endisset
                            <span>{{ $section['text'] ?? '' }}</span>
                        </a>
                    @endif
                @endforeach
            </div>
        </div>
    </header>

    {{-- ── Developer bar ──
         Shown ONLY to developer accounts (FeatureFlags::isDeveloper) so the
         hidden feature-flags panel is one click away after login. Customers
         never see this — for them the panel URL 404s. --}}
    @if(\App\Helper\FeatureFlags::isDeveloper())
        @php
            $bvDevFlagsOff = count(array_filter(\App\Helper\FeatureFlags::flags(), fn ($v) => !$v));
        @endphp
        {{-- Fixed bottom-left so it can never hide behind the fixed top navbar. --}}
        <div style="position:fixed; left:16px; bottom:16px; z-index:2050; background:#0f172a; color:#cbd5e1; font:500 12px/1 'Source Sans Pro',-apple-system,sans-serif; padding:9px 14px; display:flex; align-items:center; gap:12px; letter-spacing:.02em; border-radius:999px; box-shadow:0 10px 30px -10px rgba(2,6,23,.6); border:1px solid rgba(148,163,184,.25);">
            <span style="display:inline-flex; align-items:center; gap:6px;" title="{{ auth()->user()->email ?? '' }}">
                <i class="fa fa-code" aria-hidden="true" style="color:#38bdf8;"></i>
                <strong style="color:#e2e8f0;">Developer mode</strong>
            </span>
            <a href="{{ url('admin/dev/features') }}"
               style="display:inline-flex; align-items:center; gap:7px; background:{{ $bvDevFlagsOff ? '#b45309' : '#334155' }}; color:#fff; padding:5px 12px; border-radius:999px; text-decoration:none; font-weight:600;">
                <i class="fa fa-toggle-on" aria-hidden="true"></i>
                Feature flags{{ $bvDevFlagsOff ? " · {$bvDevFlagsOff} off" : '' }}
            </a>
        </div>
    @endif

    @parent
@stop

{{-- Top bar JS — mega-menu hover/click + command palette search. --}}
@push('js')
<script>
(function () {
    /* ─── Static command palette index ───
        Built server-side from config/adminlte.php so it always matches the
        sidebar/megamenu structure. Quick actions appended at the end so
        Cmd+K → "new property" is one keystroke away. */
    var STATIC_INDEX = (function () {
        var idx = [];
        @php
            $idxItems = [];
            foreach (config('adminlte.menu', []) as $section) {
                if (!is_array($section)) continue;
                if (isset($section['header'])) continue;
                $sectionLabel = $section['text'] ?? '';
                if (isset($section['submenu']) && is_array($section['submenu'])) {
                    foreach ($section['submenu'] as $item) {
                        if (!is_array($item) || empty($item['url'])) continue;
                        $idxItems[] = [
                            'title' => $item['text'] ?? '',
                            'subtitle' => $sectionLabel . ' · /' . ltrim($item['url'], '/'),
                            'url' => url($item['url']),
                            'icon' => $item['icon'] ?? 'fas fa-fw fa-circle',
                            'group' => 'Pages',
                            'kbd' => 'Open',
                        ];
                    }
                } elseif (!empty($section['url'])) {
                    $idxItems[] = [
                        'title' => $section['text'] ?? '',
                        'subtitle' => '/' . ltrim($section['url'], '/'),
                        'url' => url($section['url']),
                        'icon' => $section['icon'] ?? 'fas fa-fw fa-circle',
                        'group' => 'Pages',
                        'kbd' => 'Open',
                    ];
                }
            }
            // Quick actions
            $idxItems[] = [
                'title' => 'Create new property',
                'subtitle' => 'Add a new rental property',
                'url' => url('admin/properties/create'),
                'icon' => 'fas fa-fw fa-plus',
                'group' => 'Actions',
                'kbd' => 'Create',
            ];
            $idxItems[] = [
                'title' => 'Create CMS page',
                'subtitle' => 'Add a new content page',
                'url' => url('admin/cms/create'),
                'icon' => 'fas fa-fw fa-plus',
                'group' => 'Actions',
                'kbd' => 'Create',
            ];
            $idxItems[] = [
                'title' => 'View public site',
                'subtitle' => 'Open the front-end in a new tab',
                'url' => url('/'),
                'icon' => 'fas fa-fw fa-external-link-alt',
                'group' => 'Actions',
                'target' => '_blank',
                'kbd' => 'Open',
            ];
        @endphp
        var data = @json($idxItems);
        for (var i = 0; i < data.length; i++) idx.push(data[i]);
        return idx;
    })();

    /* ─── DOM refs ─── */
    var trigger    = document.getElementById('ca-menu-trigger');
    var menuPanel  = document.getElementById('ca-megamenu');
    var bar        = document.getElementById('ca-topbar');
    var searchEl   = document.getElementById('ca-search');
    var input      = document.getElementById('ca-search-input');
    var dropdown   = document.getElementById('ca-search-dropdown');
    var resultsEl  = document.getElementById('ca-search-results');
    var kbd        = document.getElementById('ca-search-kbd');

    /* ─── Backdrop element for mega menu overlay ─── */
    var backdrop = document.createElement('div');
    backdrop.className = 'ca-megamenu-backdrop';
    document.body.appendChild(backdrop);

    /* ─── Mega menu hover/click ─── */
    if (trigger && menuPanel && bar) {
        function openMenu()  {
            bar.classList.add('is-menu-open');
            document.body.classList.add('ca-menu-open');
            trigger.setAttribute('aria-expanded', 'true');
        }
        function closeMenu() {
            bar.classList.remove('is-menu-open');
            document.body.classList.remove('ca-menu-open');
            trigger.setAttribute('aria-expanded', 'false');
        }

        var hoverTimer = null;
        trigger.addEventListener('mouseenter', function () {
            clearTimeout(hoverTimer);
            hoverTimer = setTimeout(openMenu, 100);
        });
        menuPanel.addEventListener('mouseenter', function () { clearTimeout(hoverTimer); openMenu(); });
        bar.addEventListener('mouseleave', function () {
            clearTimeout(hoverTimer);
            hoverTimer = setTimeout(closeMenu, 220);
        });
        trigger.addEventListener('click', function (e) {
            e.preventDefault();
            if (bar.classList.contains('is-menu-open')) closeMenu(); else openMenu();
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && bar.classList.contains('is-menu-open')) closeMenu();
        });
    }

    if (!searchEl || !input || !dropdown || !resultsEl) return;

    /* ─── Platform-aware ⌘K hint ─── */
    if (kbd) {
        var isMac = /Mac|iPhone|iPad|iPod/i.test(navigator.platform || '');
        kbd.textContent = isMac ? '⌘ K' : 'Ctrl K';
    }

    /* ─── State ─── */
    var activeItems = [];   // flat array of currently rendered items
    var activeIndex = -1;
    var ajaxAbort   = null;
    var ajaxTimer   = null;
    var lastQuery   = '';

    /* ─── Helpers ─── */
    function escapeHtml(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }
    function highlight(text, q) {
        if (!q) return escapeHtml(text);
        var safe = escapeHtml(text);
        var re = new RegExp('(' + q.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'gi');
        return safe.replace(re, '<mark>$1</mark>');
    }

    function filterStatic(q) {
        if (!q) return STATIC_INDEX.slice(0, 8);
        var ql = q.toLowerCase();
        return STATIC_INDEX
            .map(function (it) {
                var title = (it.title || '').toLowerCase();
                var sub   = (it.subtitle || '').toLowerCase();
                var score = -1;
                if (title.indexOf(ql) === 0) score = 0;
                else if (title.indexOf(ql) > -1) score = 1;
                else if (sub.indexOf(ql) > -1) score = 2;
                return score < 0 ? null : { item: it, score: score };
            })
            .filter(Boolean)
            .sort(function (a, b) { return a.score - b.score; })
            .map(function (s) { return s.item; })
            .slice(0, 8);
    }

    function groupItems(items) {
        // Group static items by their `group` key. Preserves first-seen order.
        var order = [];
        var byGroup = {};
        items.forEach(function (it) {
            var g = it.group || 'Pages';
            if (!byGroup[g]) { byGroup[g] = []; order.push(g); }
            byGroup[g].push(it);
        });
        return order.map(function (g) { return { label: g, items: byGroup[g] }; });
    }

    function renderResults(staticGroups, dynamicGroups, q) {
        activeItems = [];
        if (!staticGroups.length && !dynamicGroups.length) {
            resultsEl.innerHTML = q
                ? '<div class="ca-search-empty"><strong>No matches for "' + escapeHtml(q) + '"</strong>Try a property name, booking ID, or section name.</div>'
                : '<div class="ca-search-empty"><strong>What are you looking for?</strong>Type to search, or use ↑ ↓ ↵ to navigate suggestions.</div>';
            activeIndex = -1;
            return;
        }

        var html = '';
        function appendGroup(group) {
            if (!group.items.length) return;
            html += '<div class="ca-search-group"><div class="ca-search-group-label">' + escapeHtml(group.label) + '</div>';
            group.items.forEach(function (it) {
                var idx = activeItems.length;
                activeItems.push(it);
                var iconCls = it.icon || 'fas fa-fw fa-circle';
                html +=
                    '<a class="ca-search-item" href="' + escapeHtml(it.url) + '"' +
                        (it.target ? ' target="' + escapeHtml(it.target) + '"' : '') +
                        ' data-idx="' + idx + '" role="option">' +
                        '<span class="ca-search-item-icon"><i class="' + escapeHtml(iconCls) + '" aria-hidden="true"></i></span>' +
                        '<span class="ca-search-item-body">' +
                            '<span class="ca-search-item-title">' + highlight(it.title || '', q) + '</span>' +
                            (it.subtitle ? '<span class="ca-search-item-sub">' + highlight(it.subtitle, q) + '</span>' : '') +
                        '</span>' +
                        (it.kbd ? '<span class="ca-search-item-kbd">↵ ' + escapeHtml(it.kbd) + '</span>' : '') +
                    '</a>';
            });
            html += '</div>';
        }

        staticGroups.forEach(appendGroup);
        dynamicGroups.forEach(appendGroup);
        resultsEl.innerHTML = html;

        activeIndex = activeItems.length ? 0 : -1;
        applyActive();
    }

    function applyActive() {
        var rows = resultsEl.querySelectorAll('.ca-search-item');
        rows.forEach(function (r) { r.classList.remove('is-active'); });
        if (activeIndex >= 0 && rows[activeIndex]) {
            rows[activeIndex].classList.add('is-active');
            // Scroll into view if needed.
            var rect = rows[activeIndex].getBoundingClientRect();
            var hostRect = resultsEl.getBoundingClientRect();
            if (rect.bottom > hostRect.bottom) rows[activeIndex].scrollIntoView({ block: 'nearest' });
            else if (rect.top < hostRect.top) rows[activeIndex].scrollIntoView({ block: 'nearest' });
        }
    }

    function open() {
        searchEl.classList.add('is-open');
        // Initial render — if there's a query, run it; otherwise show the
        // top static suggestions as "Quick navigation".
        runSearch(input.value);
    }
    function close() {
        searchEl.classList.remove('is-open');
        searchEl.classList.remove('is-loading');
        if (ajaxAbort) { try { ajaxAbort.abort(); } catch (e) {} ajaxAbort = null; }
        if (ajaxTimer) { clearTimeout(ajaxTimer); ajaxTimer = null; }
    }

    function runSearch(q) {
        q = (q || '').trim();
        var staticHits = filterStatic(q);
        var staticGroups = groupItems(staticHits);

        if (q.length < 2) {
            renderResults(staticGroups, [], q);
            searchEl.classList.remove('is-loading');
            return;
        }

        // Render static results immediately, fetch dynamic in background.
        renderResults(staticGroups, [], q);

        if (ajaxTimer) clearTimeout(ajaxTimer);
        ajaxTimer = setTimeout(function () {
            ajaxTimer = null;
            if (ajaxAbort) { try { ajaxAbort.abort(); } catch (e) {} }
            ajaxAbort = (typeof AbortController === 'function') ? new AbortController() : null;
            searchEl.classList.add('is-loading');
            lastQuery = q;

            fetch('{{ url("admin/api/search") }}?q=' + encodeURIComponent(q), {
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                signal: ajaxAbort ? ajaxAbort.signal : undefined,
            }).then(function (r) {
                return r.ok ? r.json() : { groups: [] };
            }).then(function (data) {
                if (lastQuery !== q) return;  // a newer query is in flight
                searchEl.classList.remove('is-loading');
                var dynamicGroups = (data && Array.isArray(data.groups)) ? data.groups : [];
                renderResults(staticGroups, dynamicGroups, q);
            }).catch(function (err) {
                if (err && err.name === 'AbortError') return;
                searchEl.classList.remove('is-loading');
            });
        }, 220);
    }

    /* ─── Wire input ─── */
    input.addEventListener('focus', open);
    input.addEventListener('input', function () { runSearch(input.value); });
    input.addEventListener('keydown', function (e) {
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            if (!activeItems.length) return;
            activeIndex = (activeIndex + 1) % activeItems.length;
            applyActive();
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            if (!activeItems.length) return;
            activeIndex = (activeIndex - 1 + activeItems.length) % activeItems.length;
            applyActive();
        } else if (e.key === 'Enter') {
            if (activeIndex >= 0 && activeItems[activeIndex]) {
                e.preventDefault();
                var it = activeItems[activeIndex];
                if (it.target === '_blank') window.open(it.url, '_blank');
                else window.location.href = it.url;
            }
        } else if (e.key === 'Escape') {
            input.blur();
            close();
        }
    });

    // Click-outside closes.
    document.addEventListener('mousedown', function (e) {
        if (!searchEl.contains(e.target)) close();
    });

    // Click on a result row navigates (anchor's href handles it; we also
    // intercept Cmd/Ctrl-click for new-tab + middle-click).
    resultsEl.addEventListener('click', function (e) {
        var item = e.target.closest('.ca-search-item');
        if (!item) return;
        // Default anchor behavior handles same-tab nav and Cmd-click new-tab.
    });

    /* ─── Cmd+K / Ctrl+K — focus + open ─── */
    document.addEventListener('keydown', function (e) {
        if ((e.metaKey || e.ctrlKey) && e.key && e.key.toLowerCase() === 'k') {
            e.preventDefault();
            input.focus();
            input.select();
        }
    });

    input.addEventListener('focus', function () { if (kbd) kbd.style.opacity = '.35'; });
    input.addEventListener('blur',  function () { if (kbd) kbd.style.opacity = ''; });

    /* ─── Notification bell ─── */
    var bell = document.getElementById('ca-bell');
    var bellDropdown = document.getElementById('ca-bell-dropdown');
    var bellBody = document.getElementById('ca-bell-body');
    var bellCount = document.getElementById('ca-bell-count');
    var bellLoaded = false;

    function escapeHtmlNotif(s) {
        return String(s == null ? '' : s)
            .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
            .replace(/"/g,'&quot;').replace(/'/g,'&#39;');
    }
    function renderNotifications(data) {
        var items = (data && Array.isArray(data.items)) ? data.items : [];
        var unread = (data && data.unread) ? data.unread : 0;

        if (bellCount) bellCount.textContent = items.length ? (items.length + ' total') : '';
        if (bell) bell.classList.toggle('has-unread', unread > 0);

        if (!items.length) {
            bellBody.innerHTML = '<div class="ca-bell-empty"><strong>All quiet.</strong><br><span style="font-size:12px;color:var(--ca-ink-quiet)">No recent activity.</span></div>';
            return;
        }

        var html = '';
        items.forEach(function (it) {
            html += '<a class="ca-bell-item kind-' + escapeHtmlNotif(it.kind || '') + '" href="' + escapeHtmlNotif(it.url || '#') + '">' +
                        '<span class="ca-bell-item-icon"><i class="' + escapeHtmlNotif(it.icon || 'fas fa-circle') + '" aria-hidden="true"></i></span>' +
                        '<span class="ca-bell-item-body">' +
                            '<span class="ca-bell-item-row">' +
                                '<span class="ca-bell-item-title">' + escapeHtmlNotif(it.title || '') + '</span>' +
                                (it.time_human ? '<span class="ca-bell-item-time">' + escapeHtmlNotif(it.time_human) + '</span>' : '') +
                            '</span>' +
                            (it.subtitle ? '<span class="ca-bell-item-sub">' + escapeHtmlNotif(it.subtitle) + '</span>' : '') +
                        '</span>' +
                    '</a>';
        });
        bellBody.innerHTML = html;
    }
    function loadNotifications() {
        if (typeof window.fetch !== 'function') return;
        fetch('{{ url("admin/api/notifications") }}', {
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        }).then(function (r) { return r.ok ? r.json() : { items: [] }; })
          .then(renderNotifications)
          .catch(function () {
              bellBody.innerHTML = '<div class="ca-bell-empty">Couldn\'t load notifications.</div>';
          });
    }

    if (bell && bellDropdown) {
        // Initial load (sets the unread dot before user opens it)
        loadNotifications();
        // Poll every 60s while the page is open
        setInterval(loadNotifications, 60000);

        bell.addEventListener('click', function (e) {
            e.stopPropagation();
            var open = bellDropdown.classList.toggle('is-open');
            bell.classList.toggle('is-open', open);
            bell.setAttribute('aria-expanded', open ? 'true' : 'false');
            if (open && !bellLoaded) { loadNotifications(); bellLoaded = true; }
        });
        document.addEventListener('click', function (e) {
            if (!bellDropdown.contains(e.target) && e.target !== bell) {
                bellDropdown.classList.remove('is-open');
                bell.classList.remove('is-open');
                bell.setAttribute('aria-expanded', 'false');
            }
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && bellDropdown.classList.contains('is-open')) {
                bellDropdown.classList.remove('is-open');
                bell.classList.remove('is-open');
            }
        });
    }

    /* ─── Welcome-back toast ───
       Triggered when the page is loaded directly from /login (referrer-
       based, more reliable than session flash) OR when a server-side
       <meta name="ca-welcome"> flash is present. sessionStorage gates so
       it only fires once per browser tab session. */
    (function () {
        var sessionKey = 'ca_welcome_shown';
        if (sessionStorage.getItem(sessionKey) === '1') return;

        var fromLogin = (document.referrer || '').match(/\/(login|password\/reset)\b/);
        var welcomeMeta = document.querySelector('meta[name="ca-welcome"]');
        var welcomeMsgServer = welcomeMeta ? welcomeMeta.getAttribute('content') : null;

        if (!fromLogin && !welcomeMsgServer) return;

        var nameMeta = document.querySelector('meta[name="ca-user-name"]');
        var userName = nameMeta ? (nameMeta.getAttribute('content') || '').trim() : '';

        var greeting;
        if (welcomeMsgServer) {
            greeting = welcomeMsgServer;
        } else {
            var hr = new Date().getHours();
            var tod = (hr < 5) ? 'Good evening'
                    : (hr < 12) ? 'Good morning'
                    : (hr < 18) ? 'Good afternoon'
                    : 'Good evening';
            greeting = userName ? (tod + ', ' + userName) : tod;
        }

        var safe = function (s) {
            return String(s).replace(/[&<>"']/g, function (c) {
                return { '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' }[c];
            });
        };

        var toast = document.createElement('div');
        toast.className = 'ca-welcome-toast';
        toast.innerHTML = ''
            + '<div class="ca-welcome-toast-body">'
            +   '<div class="ca-welcome-toast-greeting">' + safe(greeting) + '</div>'
            +   '<div class="ca-welcome-toast-sub">Welcome back.</div>'
            + '</div>';
        document.body.appendChild(toast);

        sessionStorage.setItem(sessionKey, '1');

        setTimeout(function () { toast.classList.add('is-show'); }, 320);
        setTimeout(function () {
            toast.classList.remove('is-show');
            toast.classList.add('is-leave');
            setTimeout(function () { toast.remove(); }, 420);
        }, 4600);
    })();

    /* ─── Top-bar scroll shadow ─── */
    if (bar) {
        var lastScrolled = false;
        window.addEventListener('scroll', function () {
            var scrolled = window.scrollY > 4;
            if (scrolled !== lastScrolled) {
                bar.classList.toggle('is-scrolled', scrolled);
                lastScrolled = scrolled;
            }
        }, { passive: true });
    }
})();
</script>
@endpush
