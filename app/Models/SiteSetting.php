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

    /** Any change through the model drops the settings cache, so a saved value shows at once. */
    protected static function booted(): void
    {
        static::saved(fn () => \App\Support\SiteSettingsHelper::forget());
        static::deleted(fn () => \App\Support\SiteSettingsHelper::forget());
    }
}
