<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Admin user (separate from customer `users`). Backs the dedicated `admin`
 * auth guard used by the Filament admin panel. Admins log in with `username`.
 *
 * Every admin_users row is an admin (the `role` column exists for future,
 * finer-grained permissions), so canAccessPanel() always returns true.
 */
class AdminUser extends Authenticatable implements FilamentUser, HasName
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

    /**
     * admin_users has no `name` column (only `username`), so Filament's
     * default FilamentManager::getUserName() fallback to $user->name
     * returns null and throws a TypeError when rendering the panel's
     * avatar/user menu. Implementing HasName tells Filament to use this
     * instead.
     */
    public function getFilamentName(): string
    {
        return $this->username;
    }
}
