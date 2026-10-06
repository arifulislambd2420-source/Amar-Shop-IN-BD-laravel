{{-- Admin panel look, driven by the panel's primary color (= the site brand
     color from Site Setting → Colors, exposed by Filament as --primary-*).
     Rendered into <head> by AdminPanelProvider; plain CSS on purpose, since
     the admin's own stylesheet is a prebuilt bundle. --}}
<style>
    /* ── Sidebar ─────────────────────────────────────────────── */
    .fi-sidebar {
        background: color-mix(in srgb, var(--primary-500) 6%, #ffffff);
    }
    .dark .fi-sidebar {
        background: color-mix(in srgb, var(--primary-500) 12%, #0b0b0c);
    }

    .fi-sidebar-item-btn {
        position: relative;
        border-left: 3px solid transparent;
        border-radius: 0.5rem;
        transition: background-color .18s ease, transform .18s ease, border-color .18s ease;
    }
    .fi-sidebar-item-btn:hover {
        background: color-mix(in srgb, var(--primary-500) 11%, transparent);
        transform: translateX(3px);
    }
    .fi-sidebar-item.fi-active > .fi-sidebar-item-btn {
        background: color-mix(in srgb, var(--primary-500) 17%, transparent);
        border-left-color: var(--primary-500);
        transform: none;
    }
    .fi-sidebar-item.fi-active > .fi-sidebar-item-btn .fi-sidebar-item-label,
    .fi-sidebar-item.fi-active > .fi-sidebar-item-btn .fi-sidebar-item-icon {
        color: var(--primary-600);
        font-weight: 600;
    }
    .dark .fi-sidebar-item.fi-active > .fi-sidebar-item-btn .fi-sidebar-item-label,
    .dark .fi-sidebar-item.fi-active > .fi-sidebar-item-btn .fi-sidebar-item-icon {
        color: var(--primary-400);
    }

    .fi-sidebar-group-label {
        font-size: 0.68rem;
        font-weight: 700;
        letter-spacing: 0.09em;
        text-transform: uppercase;
        color: var(--primary-700);
    }
    .dark .fi-sidebar-group-label {
        color: var(--primary-300);
    }
    .fi-sidebar-group-label::before {
        content: "";
        display: inline-block;
        width: 0.35rem;
        height: 0.35rem;
        margin-right: 0.45rem;
        border-radius: 999px;
        vertical-align: middle;
        background: var(--primary-500);
    }

    /* ── Dashboard stat cards ────────────────────────────────── */
    .dash-stat {
        --tone: var(--primary-500);
        border-left: 4px solid var(--tone);
        background: color-mix(in srgb, var(--tone) 9%, transparent) !important;
    }
    .dash-stat--info    { --tone: #0ea5e9; }
    .dash-stat--success { --tone: #16a34a; }
    .dash-stat--warning { --tone: #f59e0b; }
    .dash-stat .fi-wi-stats-overview-stat-label-ctn > svg {
        width: 2.25rem;
        height: 2.25rem;
        padding: 0.45rem;
        border-radius: 0.65rem;
        color: var(--tone);
        background: color-mix(in srgb, var(--tone) 18%, transparent);
    }
    .dash-stat .fi-wi-stats-overview-stat-value {
        color: color-mix(in srgb, var(--tone) 70%, currentColor);
    }
</style>
