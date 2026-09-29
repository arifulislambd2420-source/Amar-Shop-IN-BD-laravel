<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SmsLog extends Model
{
    protected $fillable = [
        'order_id',
        'event',
        'phone',
        'message',
        'status',
        'response',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
