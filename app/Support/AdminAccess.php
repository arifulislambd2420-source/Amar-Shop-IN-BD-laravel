<?php

namespace App\Support;

use App\Models\AdminUser;

/**
 * Admin roles → which areas of the panel they may use.
 *
 *  - super_admin: everything
 *  - manager:     everything except Settings pages/IP blocks and Admin users
 *  - order_staff: orders (and the dashboard's order widgets) only
 *
 * Every Filament resource / page / widget declares the area it belongs to
 * (see App\Filament\Concerns\HasAdminArea*); 'general' is the default.
 */
class AdminAccess
{
    public const AREA_GENERAL = 'general';
    public const AREA_ORDERS = 'orders';
    public const AREA_SETTINGS = 'settings';
    public const AREA_ADMINS = 'admins';

    /** @var array<string, list<string>> role => allowed areas ('*' = all) */
    public const ROLE_AREAS = [
        AdminUser::ROLE_SUPER_ADMIN => ['*'],
        AdminUser::ROLE_MANAGER => [self::AREA_GENERAL, self::AREA_ORDERS],
        AdminUser::ROLE_ORDER_STAFF => [self::AREA_ORDERS],
    ];

    public static function allows(string $area, ?AdminUser $user = null): bool
    {
        $user ??= auth('admin')->user();

        if (! $user instanceof AdminUser) {
            return false;
        }

        $areas = self::ROLE_AREAS[$user->role] ?? [];

        return in_array('*', $areas, true) || in_array($area, $areas, true);
    }
}
