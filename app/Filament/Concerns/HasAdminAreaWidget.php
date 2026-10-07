<?php

namespace App\Filament\Concerns;

use App\Support\AdminAccess;

/** Dashboard widgets: only shown to roles that may use the widget's area. */
trait HasAdminAreaWidget
{
    public static function adminArea(): string
    {
        return property_exists(static::class, 'adminArea') ? static::$adminArea : AdminAccess::AREA_GENERAL;
    }

    public static function canView(): bool
    {
        return AdminAccess::allows(static::adminArea()) && parent::canView();
    }
}
