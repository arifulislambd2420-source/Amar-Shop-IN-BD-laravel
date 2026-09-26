<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SiteSetting extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'setting_key',
        'setting_value',
    ];
}
