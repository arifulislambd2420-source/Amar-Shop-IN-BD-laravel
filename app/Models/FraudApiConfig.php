<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FraudApiConfig extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'type',
        'api_url',
        'api_key',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }
}
