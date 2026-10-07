<?php

namespace App\Support;

/** Customer-facing (Bangla) order status labels and badge colors. */
class OrderStatus
{
    public const LABELS = [
        'pending' => 'অপেক্ষমাণ',
        'processing' => 'কনফার্মড',
        'shipped' => 'শিপড',
        'out_for_delivery' => 'ডেলিভারির পথে',
        'delivered' => 'ডেলিভারড',
        'completed' => 'সম্পন্ন',
        'cancelled' => 'বাতিল',
        'on_hold' => 'যাচাই চলছে',
    ];

    public static function label(?string $status): string
    {
        return self::LABELS[$status] ?? (string) $status;
    }
}
