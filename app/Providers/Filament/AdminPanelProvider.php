<?php

namespace App\Providers\Filament;

use App\Filament\Auth\Login;
use App\Http\Middleware\EnsureAdminAreaAccess;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetAdminLocale;
use App\Support\SiteSettingsHelper;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    /** Admin primary color = the site brand color (Site Setting → Colors). */
    private function primaryColor(): string|array
    {
        try {
            return SiteSettingsHelper::color('brand');
        } catch (\Throwable) {
            return Color::Amber;
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
            ->path('admin')
            ->authGuard('admin')
            ->brandName(fn () => $this->brandName())
            // The shop's own logo / favicon from Site Setting (text name when there's no logo).
            ->brandLogo(fn () => $this->setting('site_logo'))
            ->brandLogoHeight('2.25rem')
            ->favicon(fn () => $this->setting('site_favicon') ?? $this->setting('site_logo'))
            ->login(Login::class)
            ->colors(fn () => [
                'primary' => $this->primaryColor(),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
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
                EnsureAdminAreaAccess::class,
            ], isPersistent: true);
    }
}
