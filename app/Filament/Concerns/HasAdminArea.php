<?php

namespace App\Filament\Concerns;

use App\Support\AdminAccess;

/**
 * For Filament resources and pages: ties them to an admin "area" so the
 * signed-in admin's role decides whether the navigation item shows and
 * whether the page can be opened. Declare a non-default area with
 * `protected static string $adminArea = AdminAccess::AREA_SETTINGS;`.
 * (Direct URL access is also enforced by EnsureAdminAreaAccess middleware.)
 */
trait HasAdminArea
{
    public static function adminArea(): string
    {
        return property_exists(static::class, 'adminArea') ? static::$adminArea : AdminAccess::AREA_GENERAL;
    }

    public static function canAccess(): bool
    {
        return AdminAccess::allows(static::adminArea()) && parent::canAccess();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return AdminAccess::allows(static::adminArea()) && parent::shouldRegisterNavigation();
    }
}
