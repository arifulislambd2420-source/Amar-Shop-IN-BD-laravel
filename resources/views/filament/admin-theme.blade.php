{{-- Admin panel look. The sidebar and the main colour are the admin's OWN colours (Site settings → General →
     Admin colours; App\Support\AdminTheme), not the shop's brand colours; the main colour reaches Filament as
     --primary-* (AdminPanelProvider::colors). The dashboard cards' tones still follow the shop palette.
     Rendered into <head>; plain CSS on purpose, since the admin's own stylesheet is a prebuilt bundle. --}}
@php
    $c = fn (string $name) => \App\Support\SiteSettingsHelper::color($name);
    $sb = \App\Support\AdminTheme::sidebarVars();
@endphp
<style>
    :root {
        --dash-success: {{ $c('success') }};
        --dash-warning: {{ $c('accent') }};
        --dash-info: {{ $c('secondary') }};
        --dash-danger: {{ $c('error') }};
        @foreach ($sb as $name => $value)
        {{ $name }}: {{ $value }};
        @endforeach
    }

    /* ── Sidebar: solid colour, light (or dark) text, groups as menu entries ───────── */
    .fi-sidebar,
    .dark .fi-sidebar {
        background: var(--sb-bg) !important;
        color: var(--sb-text);
        box-shadow: none;
    }
    .fi-sidebar-header-ctn,
    .fi-sidebar-header {
        background: transparent !important;
        box-shadow: none !important;
    }
    /* the shop's name / logo at the top of the phone drawer: readable on the sidebar colour */
    .fi-sidebar-header .fi-logo,
    .fi-sidebar-header a,
    .fi-sidebar-header-logo-ctn {
        color: var(--sb-text) !important;
    }
    .fi-sidebar-header .fi-logo img {
        background: #ffffff;
        border-radius: 0.5rem;
        padding: 0.2rem 0.45rem;
    }
    .fi-sidebar-nav {
        scrollbar-width: thin;
        scrollbar-color: var(--sb-line) transparent;
    }
    .fi-sidebar-nav-groups { gap: 0.2rem; }

    /* every entry: icon, label, and (for groups) an arrow on the right */
    .fi-sidebar-item-btn,
    .fi-sidebar-group-btn {
        border-radius: 0.8rem;
        padding: 0.6rem 0.8rem;
        transition: background-color .15s ease, color .15s ease;
    }
    .fi-sidebar-item-label,
    .fi-sidebar-group-label {
        color: var(--sb-text);
        font-size: 0.92rem;
        font-weight: 500;
        letter-spacing: 0;
        text-transform: none;
    }
    .fi-sidebar-group-label::before { content: none; }
    .fi-sidebar-item-icon,
    .fi-sidebar-group-btn > svg,
    .fi-sidebar-group-btn .fi-icon {
        color: var(--sb-muted);
    }
    .fi-sidebar-group-btn { cursor: pointer; }
    .fi-sidebar-item-btn:hover,
    .fi-sidebar-group-btn:hover {
        background: var(--sb-hover);
    }
    .fi-sidebar-item-btn:hover .fi-sidebar-item-icon,
    .fi-sidebar-group-btn:hover > svg { color: var(--sb-text); }

    /* arrow: ">" when the group is closed, "v" when it is open */
    .fi-sidebar-group-collapse-btn { color: var(--sb-muted) !important; rotate: 0deg !important; }
    .fi-sidebar-group-collapse-btn svg { transition: transform .18s ease; transform: rotate(180deg); }
    .fi-sidebar-group.fi-collapsed .fi-sidebar-group-collapse-btn svg { transform: rotate(90deg); }
    .fi-sidebar-group.fi-active > .fi-sidebar-group-btn .fi-sidebar-group-label { color: var(--sb-text); font-weight: 600; }

    /* sub-menu: indented, with a thin guide line */
    .fi-sidebar-group-items { gap: 0.1rem; padding: 0.1rem 0 0.3rem 0.35rem; }
    .fi-sidebar-item-grouped-border-part,
    .fi-sidebar-item-grouped-border-part-not-first,
    .fi-sidebar-item-grouped-border-part-not-last { background-color: var(--sb-line) !important; }
    .fi-sidebar-group-items .fi-sidebar-item-label { font-size: 0.875rem; color: var(--sb-muted); }
    .fi-sidebar-group-items .fi-sidebar-item-btn:hover .fi-sidebar-item-label { color: var(--sb-text); }

    /* the open page: a bright rounded box */
    .fi-sidebar-item.fi-active > .fi-sidebar-item-btn {
        background: var(--primary-500) !important;
        box-shadow: 0 6px 16px -6px var(--primary-500);
    }
    .fi-sidebar-item.fi-active > .fi-sidebar-item-btn .fi-sidebar-item-label,
    .fi-sidebar-item.fi-active > .fi-sidebar-item-btn .fi-sidebar-item-icon {
        color: #ffffff !important;
        font-weight: 600;
    }
    .fi-sidebar-item-badge-ctn .fi-badge { font-weight: 700; }

    /* ── Sidebar foot: signed-in admin, View site, Log out ───────────────────────── */
    .fi-sidebar-footer { padding: 0 !important; }
    .admin-sb-foot {
        margin: 0.5rem 0.75rem 0.75rem;
        padding-top: 0.75rem;
        border-top: 1px solid var(--sb-line);
        display: flex;
        flex-direction: column;
        gap: 0.15rem;
    }
    .admin-sb-user { display: flex; align-items: center; gap: 0.65rem; padding: 0.35rem 0.5rem 0.6rem; }
    .admin-sb-avatar {
        display: inline-flex; align-items: center; justify-content: center;
        width: 2.25rem; height: 2.25rem; flex: none; border-radius: 999px;
        background: var(--primary-500); color: #fff; font-weight: 700;
    }
    .admin-sb-who { display: flex; flex-direction: column; min-width: 0; line-height: 1.25; }
    .admin-sb-name { color: var(--sb-text); font-weight: 600; font-size: 0.9rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .admin-sb-role { color: var(--sb-muted); font-size: 0.78rem; }
    .admin-sb-link {
        display: flex; align-items: center; gap: 0.7rem; width: 100%;
        padding: 0.55rem 0.8rem; border-radius: 0.8rem; border: 0; background: transparent;
        color: var(--sb-muted); font-size: 0.9rem; font-weight: 500; text-align: left; cursor: pointer; text-decoration: none;
    }
    .admin-sb-link:hover { background: var(--sb-hover); color: var(--sb-text); }

    /* ── Topbar: the "open the website" icon beside the shop's name ──────────────── */
    .admin-viewsite-btn {
        display: inline-flex; align-items: center; justify-content: center;
        width: 2.25rem; height: 2.25rem; margin-left: 0.6rem; border-radius: 0.7rem;
        border: 1px solid rgba(127, 127, 127, .3); color: var(--primary-600);
        transition: background-color .15s ease;
    }
    .admin-viewsite-btn:hover { background: color-mix(in srgb, var(--primary-500) 12%, transparent); }
    /* ── Dashboard: calm cards, date buttons, status list, folded details ───────── */
    .dash-stat {
        --tone: var(--primary-500);
        border-radius: 1rem;
    }
    .dash-stat .fi-wi-stats-overview-stat-label-ctn > svg {
        width: 2.1rem; height: 2.1rem; padding: 0.4rem; border-radius: 0.65rem;
        color: var(--tone);
        background: color-mix(in srgb, var(--tone) 12%, transparent);
    }
    .dash-stat .fi-wi-stats-overview-stat-value { font-size: 1.5rem; }
    a.dash-stat:hover, .dash-stat a:hover { filter: brightness(0.98); }
    .dash-stat--info    { --tone: var(--dash-info); }
    .dash-stat--success { --tone: var(--dash-success); }
    .dash-stat--warning { --tone: var(--dash-warning); }
    .dash-stat--danger  { --tone: var(--dash-danger); }

    /* the date filter: one small row of buttons at the top right */
    .dash-filter > div { display: flex; flex-wrap: wrap; justify-content: flex-end; align-items: center; gap: 0.5rem; }
    .dash-filter .fi-fo-field-wrp { width: auto; }
    .dash-filter .fi-btn-group .fi-btn, .dash-filter .fi-fo-toggle-buttons-btn-ctn label { font-size: 0.8rem; }

    .dash-empty { padding: 1.5rem 0.5rem; text-align: center; opacity: .6; font-size: 0.9rem; }
    .dash-status-list { display: flex; flex-direction: column; gap: 0.15rem; }
    .dash-status-row {
        display: grid; grid-template-columns: minmax(6.5rem, 9rem) 1fr 2.5rem; align-items: center; gap: 0.75rem;
        padding: 0.45rem 0.5rem; border-radius: 0.6rem; font-size: 0.9rem;
    }
    .dash-status-row:hover { background: color-mix(in srgb, var(--primary-500) 8%, transparent); }
    .dash-status-row.is-zero { opacity: .55; }
    .dash-status-bar { height: 0.4rem; border-radius: 999px; background: rgba(127, 127, 127, .18); overflow: hidden; }
    .dash-status-bar > span { display: block; height: 100%; background: var(--primary-500); border-radius: 999px; }
    .dash-status-count { text-align: right; font-weight: 600; }

    .dash-details { border: 1px solid rgba(127, 127, 127, .25); border-radius: 1rem; overflow: hidden; }
    .dash-details-head {
        display: flex; align-items: center; gap: 0.75rem; width: 100%; padding: 0.9rem 1.1rem;
        text-align: left; font-weight: 600; cursor: pointer;
    }
    .dash-details-head small { flex: 1; font-weight: 400; opacity: .6; }
    .dash-tabs { display: flex; gap: 0.25rem; overflow-x: auto; padding: 0 1rem; border-bottom: 1px solid rgba(127, 127, 127, .25); }
    .dash-tab { padding: 0.6rem 0.9rem; font-size: 0.875rem; white-space: nowrap; border-bottom: 2px solid transparent; opacity: .7; }
    .dash-tab.is-active { border-bottom-color: var(--primary-500); color: var(--primary-600); opacity: 1; font-weight: 600; }
    .dash-tab-body { padding: 1rem; }
    /* Phones: 16px text in fields so iOS doesn't zoom the page on focus. */
    @media (max-width: 640px) {
        .fi-input, .fi-select-input, .fi-fo-textarea textarea, .fi-input-wrp input, .fi-input-wrp select, .fi-input-wrp textarea {
            font-size: 16px !important;
        }
    }
</style>
