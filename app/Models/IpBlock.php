<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class IpBlock extends Model
{
    // Old table has only created_at (no updated_at).
    public const UPDATED_AT = null;

    // See App\Http\Middleware\BlockBannedIps — the blocked-IP list is
    // cached for this long instead of being queried on every request.
    public const CACHE_KEY = 'ip_blocks:blocked_ips';

    public const CACHE_TTL = 300; // 5 minutes

    protected $fillable = [
        'ip',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    /**
     * Any create/update/delete invalidates the cache immediately — verified
     * against Filament's actual delete/bulk-delete behavior (both fetch
     * each record and call $record->delete(), so these events fire).
     * Caveat: a raw query-builder bulk delete, e.g. IpBlock::where(...)
     * ->delete(), bypasses Eloquent model events entirely and would NOT
     * invalidate the cache — stick to $model->delete() / Model::destroy().
     */
    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(self::CACHE_KEY));
        static::deleted(fn () => Cache::forget(self::CACHE_KEY));
    }
}
