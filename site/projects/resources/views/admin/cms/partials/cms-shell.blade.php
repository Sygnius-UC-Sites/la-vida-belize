{{-- Phase 13A — Scoped CMS-admin design tokens.
     Every selector below is prefixed with .cms-page so styles never leak to the
     rest of the admin panel. The .cms-page wrapper sits inside @section('content'),
     so we don't need to fight AdminLTE's body-class system. --}}
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
.cms-page {
    --cms-bg:               #f7f8fb;
    --cms-surface:          #ffffff;
    --cms-surface-hover:    #fafbfd;
    --cms-surface-elevated: #ffffff;
    --cms-border:           #e6e8ee;
    --cms-border-strong:    #c7ccd6;
    --cms-text:             #0b1220;
    --cms-text-secondary:   #2c3447;
    --cms-text-muted:       #5b6473;
    --cms-text-subtle:      #8d96a7;
    --cms-accent:           #1e293b;
    --cms-accent-hover:     #0f172a;
    --cms-accent-soft:      #eef2f7;
    --cms-accent-strong:    #020617;
    --cms-success:          #15803d;
    --cms-success-soft:     #dcfce7;
    --cms-warning:          #b45309;
    --cms-warning-soft:     #fff1d6;
    --cms-info:             #0369a1;
    --cms-info-soft:        #e0f2fe;
    --cms-danger:           #b42318;
    --cms-danger-soft:      #fef0ee;
    --cms-radius-sm:        6px;
    --cms-radius-md:        10px;
    --cms-radius-lg:        14px;
    --cms-radius-xl:        18px;
    --cms-shadow-sm:        0 1px 2px rgba(15, 23, 42, .05);
    --cms-shadow-md:        0 4px 14px rgba(15, 23, 42, .07);
    --cms-shadow-lg:        0 10px 28px rgba(15, 23, 42, .10);
    --cms-ring:             0 0 0 3px var(--cms-accent-soft);

    --cms-font: "Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    --cms-font-mono: "JetBrains Mono", ui-monospace, "SF Mono", Menlo, Consolas, monospace;
}

/* Force-fill the AdminLTE content-wrapper that hosts us so the page doesn't
   show AdminLTE's primary background bleeding through at the edges.
   Two selectors: :has() for modern browsers; .content-wrapper as a fallback
   that matches anywhere the wrapper exists, paired with .cms-page as a sibling. */
.content-wrapper:has(.cms-page),
.content-wrapper.cms-content-wrapper { background: var(--cms-bg) !important; padding-bottom: 32px !important; }

.content-wrapper:has(.cms-page) > .content {
    animation: none !important;
    transform: none !important;
}

/* Foundational overrides — !important to beat AdminLTE's body/inherited rules. */
.cms-page,
.cms-page *,
.cms-page input,
.cms-page select,
.cms-page button,
.cms-page textarea,
.cms-page h1,
.cms-page h2,
.cms-page h3,
.cms-page h4,
.cms-page h5,
.cms-page h6,
.cms-page p,
.cms-page span,
.cms-page a,
.cms-page div {
    font-family: var(--cms-font) !important;
}
.cms-page {
    color: var(--cms-text) !important;
    -webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;
    font-feature-settings: "cv11", "ss01", "ss03";
    background: var(--cms-bg) !important;
    min-height: calc(100vh - 100px);
    padding: 0 0 24px 0 !important;
    margin: 0 !important;
}
.cms-page * { box-sizing: border-box; }

/* ── Page header ─────────────────────────────────────────────────────────── */
.cms-page .cms-page-header {
    background: var(--cms-surface) !important;
    border-bottom: 1px solid var(--cms-border) !important;
    padding: 22px 28px 20px 28px !important;
    display: flex !important;
    align-items: center;
    gap: 14px;
    flex-wrap: wrap;
}
.cms-page .cms-page-header h1 {
    font-size: 24px !important;
    font-weight: 700 !important;
    color: var(--cms-text) !important;
    margin: 0 !important;
    letter-spacing: -.018em !important;
    line-height: 1.2 !important;
}
.cms-page .cms-page-header .cms-page-sub {
    color: var(--cms-text-muted) !important;
    font-size: 13px !important;
    margin-top: 2px !important;
    font-weight: 400 !important;
}
.cms-page .cms-page-header .cms-header-text { display: flex; flex-direction: column; }
.cms-page .cms-add-btn {
    margin-left: auto !important;
    background: var(--cms-accent) !important;
    border: 0 !important;
    color: #fff !important;
    padding: 9px 16px !important;
    border-radius: var(--cms-radius-sm) !important;
    font-weight: 600 !important;
    font-size: 14px !important;
    text-decoration: none !important;
    display: inline-flex !important;
    align-items: center;
    gap: 7px;
    line-height: 1;
    box-shadow: 0 1px 0 rgba(15, 23, 42, .04), 0 1px 2px rgba(15, 23, 42, .15) !important;
    transition: background .15s, transform .12s, box-shadow .15s;
}
.cms-page .cms-add-btn:hover {
    background: var(--cms-accent-hover) !important;
    color: #fff !important;
    text-decoration: none !important;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(15, 23, 42, .22) !important;
}

/* ── Stats strip ─────────────────────────────────────────────────────────── */
.cms-page .cms-stats {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 14px;
    padding: 22px 28px 0 28px;
}
.cms-page .cms-stat {
    background: var(--cms-surface) !important;
    border: 1px solid var(--cms-border) !important;
    border-radius: var(--cms-radius-md) !important;
    padding: 16px 18px !important;
    box-shadow: var(--cms-shadow-sm) !important;
    position: relative;
    overflow: hidden;
}
.cms-page .cms-stat::before {
    content: "";
    position: absolute;
    inset: 0 auto 0 0;
    width: 3px;
    background: var(--cms-border-strong);
    border-radius: var(--cms-radius-md) 0 0 var(--cms-radius-md);
}
.cms-page .cms-stat--total::before    { background: var(--cms-accent); }
.cms-page .cms-stat--marketing::before { background: var(--cms-accent); }
.cms-page .cms-stat--info::before      { background: var(--cms-info); }
.cms-page .cms-stat--transact::before  { background: var(--cms-warning); }
.cms-page .cms-stat-label {
    color: var(--cms-text-muted);
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .04em;
    margin: 0 0 4px 0;
}
.cms-page .cms-stat-value {
    color: var(--cms-text) !important;
    font-size: 28px !important;
    font-weight: 700 !important;
    line-height: 1.1 !important;
    letter-spacing: -.02em !important;
    font-variant-numeric: tabular-nums;
}
.cms-page .cms-stat-hint {
    color: var(--cms-text-subtle);
    font-size: 12px;
    margin-top: 4px;
}

/* ── Filter bar ──────────────────────────────────────────────────────────── */
.cms-page .cms-filter-card {
    background: var(--cms-surface);
    border: 1px solid var(--cms-border);
    border-radius: var(--cms-radius-md);
    box-shadow: var(--cms-shadow-sm);
    padding: 14px 18px;
    margin: 22px 28px 0 28px;
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}
.cms-page .cms-filter-card input,
.cms-page .cms-filter-card select {
    border: 1px solid var(--cms-border-strong);
    border-radius: var(--cms-radius-sm);
    padding: 0 12px;
    font-size: 14px;
    background: var(--cms-surface);
    color: var(--cms-text);
    height: 38px;
    transition: border-color .15s, box-shadow .15s;
    line-height: 38px;
}
.cms-page .cms-filter-card input:focus,
.cms-page .cms-filter-card select:focus {
    outline: 0;
    border-color: var(--cms-accent);
    box-shadow: var(--cms-ring);
}
.cms-page .cms-filter-card input[type=text] { width: 280px; }
.cms-page .cms-filter-card .cms-reset-btn {
    background: transparent;
    border: 1px solid var(--cms-border-strong);
    color: var(--cms-text-secondary);
    padding: 0 16px;
    border-radius: var(--cms-radius-sm);
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    height: 38px;
    transition: background .12s, color .12s, border-color .12s;
}
.cms-page .cms-filter-card .cms-reset-btn:hover {
    background: var(--cms-bg);
    color: var(--cms-text);
    border-color: var(--cms-text-muted);
}

/* ── List card ───────────────────────────────────────────────────────────── */
.cms-page .cms-list-card {
    background: var(--cms-surface);
    border: 1px solid var(--cms-border);
    border-radius: var(--cms-radius-md);
    box-shadow: var(--cms-shadow-sm);
    margin: 18px 28px 0 28px;
    /* No overflow:hidden — would clip the action menu popup. Use per-corner
       border-radius on the table itself to keep the rounded card edges. */
}
.cms-page .cms-list-table { width: 100%; border-collapse: separate; border-spacing: 0; }
.cms-page .cms-list-table thead tr th:first-child { border-top-left-radius: var(--cms-radius-md); }
.cms-page .cms-list-table thead tr th:last-child  { border-top-right-radius: var(--cms-radius-md); }
.cms-page .cms-list-table tbody tr:last-child td:first-child { border-bottom-left-radius: var(--cms-radius-md); }
.cms-page .cms-list-table tbody tr:last-child td:last-child  { border-bottom-right-radius: var(--cms-radius-md); }
.cms-page .cms-list-table th {
    background: var(--cms-bg);
    color: var(--cms-text-muted);
    font-weight: 600;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: .06em;
    padding: 12px 18px;
    text-align: left;
    border-bottom: 1px solid var(--cms-border);
}
.cms-page .cms-list-table td {
    padding: 16px 18px;
    border-bottom: 1px solid var(--cms-border);
    vertical-align: middle;
    font-size: 14px;
    color: var(--cms-text);
}
.cms-page .cms-list-table tr:last-child td { border-bottom: 0; }
.cms-page .cms-list-table tbody tr {
    transition: background .12s;
    position: relative;
}
.cms-page .cms-list-table tbody tr:hover { background: var(--cms-surface-hover); }
.cms-page .cms-list-table tbody tr:hover td:first-child::before {
    content: "";
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 3px;
    background: var(--cms-accent);
}

.cms-page .cms-row-thumb {
    width: 84px;
    height: 56px;
    object-fit: cover;
    border-radius: var(--cms-radius-sm);
    background: var(--cms-bg);
    border: 1px solid var(--cms-border);
    flex-shrink: 0;
    display: block;
}
.cms-page .cms-row-thumb--empty {
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--cms-text-subtle);
    font-size: 18px;
    background: linear-gradient(135deg, var(--cms-bg) 0%, var(--cms-surface-hover) 100%);
}
.cms-page .cms-row-name {
    font-weight: 600;
    color: var(--cms-text);
    font-size: 15px;
    line-height: 1.3;
    letter-spacing: -.005em;
}
.cms-page .cms-row-slug {
    display: inline-block;
    background: var(--cms-bg);
    color: var(--cms-text-secondary);
    font-family: var(--cms-font-mono);
    font-size: 12px;
    padding: 2px 8px;
    border-radius: 4px;
    margin-top: 5px;
    border: 1px solid var(--cms-border);
}

.cms-page .cms-chip {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 10px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 600;
    line-height: 1.4;
    letter-spacing: -.01em;
}
.cms-page .cms-chip::before {
    content: "";
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: currentColor;
    opacity: .7;
}
.cms-page .cms-chip--marketing { background: var(--cms-accent-soft); color: var(--cms-accent); }
.cms-page .cms-chip--info      { background: var(--cms-info-soft);   color: var(--cms-info); }
.cms-page .cms-chip--transact  { background: var(--cms-warning-soft); color: var(--cms-warning); }
.cms-page .cms-chip--default   { background: var(--cms-bg);          color: var(--cms-text-muted); }

.cms-page .cms-row-actions { position: relative; text-align: right; }
.cms-page .cms-action-btn {
    background: transparent;
    border: 1px solid var(--cms-border);
    color: var(--cms-text-muted);
    width: 34px;
    height: 34px;
    border-radius: var(--cms-radius-sm);
    cursor: pointer;
    transition: background .12s, color .12s, border-color .12s;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}
.cms-page .cms-action-btn:hover {
    background: var(--cms-surface);
    color: var(--cms-text);
    border-color: var(--cms-border-strong);
}
.cms-page .cms-action-menu {
    position: absolute;
    right: 0;
    top: 100%;
    margin-top: 6px;
    background: var(--cms-surface);
    border: 1px solid var(--cms-border);
    border-radius: var(--cms-radius-md);
    box-shadow: var(--cms-shadow-lg);
    min-width: 200px;
    z-index: 50;
    overflow: hidden;
    display: none;
    padding: 4px;
}
.cms-page .cms-action-menu.show { display: block; }
/* Last row (and second-to-last) open the menu UPWARD so it doesn't clip below the page. */
.cms-page .cms-list-table tbody tr:last-child .cms-action-menu,
.cms-page .cms-list-table tbody tr:nth-last-child(2) .cms-action-menu {
    top: auto;
    bottom: 100%;
    margin-top: 0;
    margin-bottom: 6px;
}
.cms-page .cms-action-menu a,
.cms-page .cms-action-menu button {
    display: flex;
    align-items: center;
    gap: 10px;
    width: 100%;
    text-align: left;
    padding: 9px 12px;
    background: transparent;
    border: 0;
    color: var(--cms-text);
    font-size: 14px;
    text-decoration: none;
    cursor: pointer;
    border-radius: var(--cms-radius-sm);
    line-height: 1;
}
.cms-page .cms-action-menu a:hover,
.cms-page .cms-action-menu button:hover { background: var(--cms-bg); color: var(--cms-text); text-decoration: none; }
.cms-page .cms-action-menu .cms-action-divider {
    height: 1px;
    background: var(--cms-border);
    margin: 4px 2px;
}
.cms-page .cms-action-menu .cms-action-danger { color: var(--cms-danger); }
.cms-page .cms-action-menu .cms-action-danger:hover { background: var(--cms-danger-soft); color: var(--cms-danger); }
.cms-page .cms-action-menu i { width: 14px; color: var(--cms-text-muted); }
.cms-page .cms-action-menu .cms-action-danger i { color: var(--cms-danger); }

.cms-page .cms-row-time {
    color: var(--cms-text-muted);
    font-size: 13px;
    font-variant-numeric: tabular-nums;
}

/* ── Empty states ────────────────────────────────────────────────────────── */
.cms-page .cms-empty {
    text-align: center;
    padding: 72px 28px;
    color: var(--cms-text-muted);
}
.cms-page .cms-empty-icon {
    width: 56px;
    height: 56px;
    border-radius: 50%;
    background: var(--cms-accent-soft);
    color: var(--cms-accent);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
    margin-bottom: 14px;
}
.cms-page .cms-empty h3 {
    color: var(--cms-text);
    font-weight: 700;
    margin: 0 0 6px 0;
    font-size: 18px;
    letter-spacing: -.01em;
}
.cms-page .cms-empty p { margin: 0 0 18px 0; }

/* ── Editor screens ─────────────────────────────────────────────────────── */
.cms-page .cms-editor-wrap {
    padding: 22px 28px 84px 28px;
}
.cms-page .cms-editor-layout {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 300px;
    gap: 18px;
    align-items: start;
}
.cms-page .cms-editor-card,
.cms-page .cms-help-panel,
.cms-page .cms-save-bar {
    background: var(--cms-surface);
    border: 1px solid var(--cms-border);
    border-radius: var(--cms-radius-md);
    box-shadow: var(--cms-shadow-sm);
}
.cms-page .cms-editor-card {
    padding: 20px;
}
.cms-page .cms-editor-card > .row:first-child {
    border-bottom: 1px solid var(--cms-border);
    padding-bottom: 8px;
    margin-bottom: 18px;
}
.cms-page .cms-editor-card .row {
    row-gap: 10px;
}
.cms-page .cms-editor-card .alert {
    border: 1px solid var(--cms-border) !important;
    border-radius: var(--cms-radius-md) !important;
    background: var(--cms-bg) !important;
    color: var(--cms-text-secondary) !important;
    padding: 14px 16px !important;
    margin: 12px 0 16px !important;
}
.cms-page .cms-editor-card .alert h3,
.cms-page .cms-editor-card .alert h4 {
    color: var(--cms-text) !important;
    font-size: 15px !important;
    line-height: 1.25 !important;
    margin: 0 0 4px !important;
    font-weight: 700 !important;
}
.cms-page .cms-editor-card .alert small {
    color: var(--cms-text-muted) !important;
    font-size: 12px !important;
}
.cms-page .form-group {
    margin-bottom: 16px !important;
}
.cms-page .form-group label {
    display: block !important;
    color: var(--cms-text) !important;
    font-size: 12px !important;
    font-weight: 700 !important;
    letter-spacing: .035em !important;
    text-transform: uppercase !important;
    margin-bottom: 7px !important;
}
.cms-page .form-control {
    border: 1px solid var(--cms-border-strong) !important;
    border-radius: var(--cms-radius-sm) !important;
    background: var(--cms-surface) !important;
    color: var(--cms-text) !important;
    font-size: 14px !important;
    min-height: 40px !important;
    box-shadow: none !important;
    transition: border-color .15s, box-shadow .15s, background .15s;
}
.cms-page textarea.form-control {
    min-height: 82px !important;
    line-height: 1.45 !important;
}
.cms-page .form-control:focus {
    outline: 0 !important;
    border-color: var(--cms-accent) !important;
    box-shadow: var(--cms-ring) !important;
}
.cms-page .form-text,
.cms-page small.text-muted {
    color: var(--cms-text-muted) !important;
    font-size: 12px !important;
    line-height: 1.45 !important;
}
.cms-page .cms-help-panel {
    padding: 18px;
    position: sticky;
    top: 76px;
}
.cms-page .cms-help-title {
    font-size: 14px !important;
    font-weight: 800 !important;
    color: var(--cms-text) !important;
    margin: 0 0 10px !important;
}
.cms-page .cms-help-list {
    list-style: none;
    padding: 0;
    margin: 0 0 16px;
}
.cms-page .cms-help-list li {
    border-top: 1px solid var(--cms-border);
    padding: 10px 0;
    color: var(--cms-text-secondary);
    font-size: 13px;
    line-height: 1.45;
}
.cms-page .cms-help-list li:first-child { border-top: 0; padding-top: 0; }
.cms-page .cms-help-list strong {
    display: block;
    color: var(--cms-text);
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: .04em;
    margin-bottom: 2px;
}
.cms-page .cms-save-bar {
    position: fixed;
    right: 28px;
    bottom: 18px;
    left: 28px;
    z-index: 1020;
    padding: 12px 16px;
    display: flex;
    align-items: center;
    gap: 10px;
}
.cms-page .cms-save-bar .cms-save-note {
    color: var(--cms-text-muted);
    font-size: 13px;
    margin-right: auto;
}
.cms-page .cms-secondary-btn,
.cms-page .cms-primary-btn {
    border-radius: var(--cms-radius-sm) !important;
    display: inline-flex !important;
    align-items: center;
    justify-content: center;
    gap: 7px;
    min-height: 38px;
    padding: 0 15px !important;
    font-size: 14px !important;
    font-weight: 700 !important;
    line-height: 1 !important;
    text-decoration: none !important;
    border: 1px solid var(--cms-border-strong) !important;
    cursor: pointer;
}
.cms-page .cms-primary-btn {
    background: var(--cms-accent) !important;
    color: #fff !important;
    border-color: var(--cms-accent) !important;
}
.cms-page .cms-primary-btn:hover {
    background: var(--cms-accent-hover) !important;
    border-color: var(--cms-accent-hover) !important;
    color: #fff !important;
}
.cms-page .cms-secondary-btn {
    background: var(--cms-surface) !important;
    color: var(--cms-text-secondary) !important;
}
.cms-page .cms-secondary-btn:hover {
    background: var(--cms-bg) !important;
    color: var(--cms-text) !important;
}
.cms-page .cke {
    border: 1px solid var(--cms-border-strong) !important;
    border-radius: var(--cms-radius-sm) !important;
    overflow: hidden;
}

/* ── Responsive ──────────────────────────────────────────────────────────── */
@media (max-width: 1200px) {
    .cms-page .cms-stats { grid-template-columns: repeat(2, 1fr); }
    .cms-page .cms-editor-layout { grid-template-columns: 1fr; }
    .cms-page .cms-help-panel { position: static; }
}
@media (max-width: 992px) {
    .cms-page .cms-page-header,
    .cms-page .cms-stats,
    .cms-page .cms-filter-card,
    .cms-page .cms-list-card { margin-left: 14px; margin-right: 14px; padding-left: 16px; padding-right: 16px; }
    .cms-page .cms-stats { padding-top: 16px; }
    .cms-page .cms-filter-card input[type=text] { width: 100%; }
    .cms-page .cms-filter-card { flex-direction: column; align-items: stretch; }
    .cms-page .cms-filter-card input,
    .cms-page .cms-filter-card select,
    .cms-page .cms-filter-card .cms-reset-btn { width: 100%; }
    .cms-page .cms-editor-wrap { padding: 16px 14px 110px; }
    .cms-page .cms-save-bar {
        left: 14px;
        right: 14px;
        bottom: 12px;
        flex-wrap: wrap;
    }
    .cms-page .cms-save-bar .cms-save-note { flex: 1 0 100%; }
}
@media (max-width: 576px) {
    .cms-page .cms-stats { grid-template-columns: 1fr; }
    .cms-page .cms-list-table thead { display: none; }
    .cms-page .cms-list-table,
    .cms-page .cms-list-table tbody,
    .cms-page .cms-list-table tr,
    .cms-page .cms-list-table td { display: block; width: 100%; }
    .cms-page .cms-list-table tr {
        border-bottom: 1px solid var(--cms-border);
        padding: 14px 16px;
    }
    .cms-page .cms-list-table td { border-bottom: 0; padding: 4px 0; }
    .cms-page .cms-row-actions { text-align: left; margin-top: 10px; }
}
</style>
