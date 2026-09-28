<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Admin user (separate from customer `users`). Backs the dedicated `admin`
 * auth guard used by the Filament admin panel. Admins log in with `username`.
 *
 * Every admin_users row is an admin (the `role` column exists for future,
 * finer-grained permissions), so canAccessPanel() always returns true.
 */
class AdminUser extends Authenticatable implements FilamentUser
{
    protected $table = 'admin_users';

    public $timestamps = false;

    protected $fillable = [
        'username',
        'password',
        'role',
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }

    /**
     * All seeded admin_users may access the Filament panel.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }
}
