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
        'name',
        'email',
        'password',
        'role',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    /** Same as the column default, so a freshly created (not reloaded) admin is active too. */
    protected $attributes = [
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Last line of defence, whatever path tries it (admin screen, code, tinker): the shop
        // must always keep one active super admin — it can't be deleted, deactivated or demoted.
        static::deleting(function (AdminUser $user): void {
            if ($user->isLastActiveSuperAdmin()) {
                throw new \DomainException(__('শেষ সক্রিয় সুপার অ্যাডমিনকে মোছা যাবে না।'));
            }
        });

        static::updating(function (AdminUser $user): void {
            $wasSuper = $user->getOriginal('role') === self::ROLE_SUPER_ADMIN && (bool) $user->getOriginal('is_active');
            $stillSuper = $user->role === self::ROLE_SUPER_ADMIN && $user->is_active;

            if ($wasSuper && ! $stillSuper && ! self::otherActiveSuperAdmins($user->id)->exists()) {
                throw new \DomainException(__('শেষ সক্রিয় সুপার অ্যাডমিনকে নিষ্ক্রিয় করা বা তার রোল বদলানো যাবে না।'));
            }
        });
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === self::ROLE_SUPER_ADMIN;
    }

    private static function otherActiveSuperAdmins(?int $exceptId)
    {
        return static::where('role', self::ROLE_SUPER_ADMIN)->where('is_active', true)->where('id', '!=', $exceptId);
    }

    /** The only active super admin left (so the shop would be locked out without them). */
    public function isLastActiveSuperAdmin(): bool
    {
        return $this->isSuperAdmin() && $this->is_active && ! self::otherActiveSuperAdmins($this->id)->exists();
    }

    /**
     * Every active admin_users row may sign in; what they can open is decided by
     * their role (App\Support\AdminAccess). An inactive admin is turned away at
     * sign-in and, if already signed in, on the very next request.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return (bool) $this->is_active;
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
        return $this->name ?: $this->username;
    }
}
