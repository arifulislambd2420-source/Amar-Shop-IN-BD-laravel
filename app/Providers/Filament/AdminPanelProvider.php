<?php

namespace App\Providers\Filament;

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
            return \App\Support\SiteSettingsHelper::color('brand');
        } catch (\Throwable) {
            return Color::Amber;
        }
    }

    /** Evaluated lazily at render time, so it never hits the DB during boot/migrate. */
    private function brandName(): string
    {
        try {
            $name = \App\Support\SiteSettingsHelper::get('site_name');
        } catch (\Throwable) {
            $name = null;
        }

        return ($name ?: 'আমারশপ').' Admin';
    }

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->authGuard('admin')
            ->brandName(fn () => $this->brandName())
            ->login(\App\Filament\Auth\Login::class)
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
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
