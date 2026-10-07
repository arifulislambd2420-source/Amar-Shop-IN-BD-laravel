<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Customer model (maps to the old `users` table). Customers log in by PHONE.
 */
class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'phone',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function addresses(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(CustomerAddress::class);
    }

    /** Every way this customer's phone may be stored on an order (01…, 8801…, 1…). */
    public function orderPhones(): array
    {
        return app(\App\Services\OrderRiskService::class)->phoneVariants(preg_replace('/[^0-9]/', '', (string) $this->phone));
    }

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }
}
