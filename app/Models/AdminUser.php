<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Admin user (separate from customer `users`). Filament admin-guard wiring
 * happens in a later phase; for now this is just the table + model.
 */
class AdminUser extends Authenticatable
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
}
