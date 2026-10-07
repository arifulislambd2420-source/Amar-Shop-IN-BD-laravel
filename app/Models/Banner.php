<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Banner extends Model
{
    use \App\Models\Concerns\FlushesStorefrontCache;

    public $timestamps = false;

    protected $fillable = [
        'image',
        'link',
        'position',
        'sort_order',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'active' => 'boolean',
        ];
    }
}
