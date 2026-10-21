<?php

namespace App\Providers\Filament;

use App\Filament\Auth\EditProfile;
use App\Filament\Auth\Login;
use App\Http\Middleware\EnsureAdminAreaAccess;
use App\Http\Middleware\RememberAdminIp;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetAdminLocale;
use App\Support\AdminPath;
use App\Support\AdminTheme;
use App\Support\InitialAvatarProvider;
use App\Support\SiteSettingsHelper;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    /** The admin's main colour: its own setting (Site settings → General → Admin colours), not the shop's brand colour. */
    private function primaryColor(): string|array
    {
        try {
            return AdminTheme::primary();
        } catch (\Throwable) {
            return AdminTheme::DEFAULT_PRIMARY;
        }
    }

    /** Evaluated lazily at render time, so it never hits the DB during boot/migrate. */
    private function brandName(): string
    {
        try {
            $name = SiteSettingsHelper::get('site_name');
        } catch (\Throwable) {
            $name = null;
        }

        return ($name ?: 'আমারশপ').' Admin';
    }

    /** A Site Setting value, or null (never touches the DB during boot/migrate failures). */
    private function setting(string $key): ?string
    {
        try {
            return SiteSettingsHelper::get($key) ?: null;
        } catch (\Throwable) {
            return null;
        }
    }

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            // /admin unless changed in Admin → Admin address (or put back with `php artisan admin:reset-path`).
            ->path(AdminPath::current())
            ->authGuard('admin')
            ->brandName(fn () => $this->brandName())
            // The shop's own logo / favicon from Site Setting (text name when there's no logo).
            ->brandLogo(fn () => $this->setting('site_logo'))
            ->brandLogoHeight('2.25rem')
            ->favicon(fn () => $this->setting('site_favicon') ?? $this->setting('site_logo'))
            ->login(Login::class)
            ->profile(EditProfile::class)
            // The avatar menu at the top right (name, my profile, Log out) with the admin's initial, drawn locally.
            ->defaultAvatarProvider(InitialAvatarProvider::class)
            ->colors(fn () => [
                'primary' => $this->primaryColor(),
            ])
            // Sidebar: every group is a menu entry with its own icon, an arrow on the right, and opens its sub-menu on click;
            // the items inside keep their own icons (resources/views/vendor/filament-panels/components/sidebar/group.blade.php).
            // Log out lives in the avatar menu at the top right.
            ->sidebarCollapsibleOnDesktop()
            // Every admin page uses the whole screen instead of a narrow column in the middle.
            ->maxContentWidth(Width::Full)
            // Moving between pages swaps only the content (no full reload), so the open groups and the sidebar's scroll stay put.
            ->spa()
            ->navigationGroups([
                NavigationGroup::make()->label(fn () => __('বিক্রয়'))->icon(Heroicon::OutlinedShoppingBag)->collapsed(),
                NavigationGroup::make()->label(fn () => __('ক্যাটালগ'))->icon(Heroicon::OutlinedSquares2x2)->collapsed(),
                NavigationGroup::make()->label(fn () => __('মার্কেটিং'))->icon(Heroicon::OutlinedMegaphone)->collapsed(),
                NavigationGroup::make()->label(fn () => __('কনটেন্ট'))->icon(Heroicon::OutlinedDocumentText)->collapsed(),
                NavigationGroup::make()->label(fn () => __('গ্রাহক'))->icon(Heroicon::OutlinedUsers)->collapsed(),
                NavigationGroup::make()->label(fn () => __('সেটিংস'))->icon(Heroicon::OutlinedCog6Tooth)->collapsed(),
            ])
            // Topbar: an icon next to the shop's name that opens the website in a new tab.
            ->renderHook(PanelsRenderHook::TOPBAR_LOGO_AFTER, fn (): string => view('filament.admin-view-site')->render())
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            // (App\Filament\Pages\Dashboard — the dashboard with the date filter — is found by discoverPages above.)
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            // Default AccountWidget / FilamentInfoWidget are intentionally not registered.
            ->renderHook(PanelsRenderHook::HEAD_END, fn (): string => view('filament.admin-theme')->render())
            ->middleware([
                SetAdminLocale::class,
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                SecurityHeaders::class,
            ])
            ->authMiddleware([
                SetAdminLocale::class, // persistent: Livewire updates stay Bangla too
                Authenticate::class,
                RememberAdminIp::class, // after Authenticate: only signed-in admins' IPs are remembered
                EnsureAdminAreaAccess::class,
            ], isPersistent: true);
    }
}
