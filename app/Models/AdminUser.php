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
 * `role` decides what the admin may open (see App\Support\AdminAccess):
 * super_admin (everything), manager (everything but settings and admin
 * users), order_staff (orders only). Existing rows default to super_admin.
 */
class AdminUser extends Authenticatable implements FilamentUser, HasName
{
    public const ROLE_SUPER_ADMIN = 'super_admin';
    public const ROLE_MANAGER = 'manager';
    public const ROLE_ORDER_STAFF = 'order_staff';

    public const ROLE_OPTIONS = [
        self::ROLE_SUPER_ADMIN => 'সুপার অ্যাডমিন (সব)',
        self::ROLE_MANAGER => 'ম্যানেজার (সেটিং ছাড়া সব)',
        self::ROLE_ORDER_STAFF => 'অর্ডার স্টাফ (শুধু অর্ডার)',
    ];

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

    public function isSuperAdmin(): bool
    {
        return $this->role === self::ROLE_SUPER_ADMIN;
    }

    /**
     * Every admin_users row may sign in; what they can open is decided by
     * their role (App\Support\AdminAccess).
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
