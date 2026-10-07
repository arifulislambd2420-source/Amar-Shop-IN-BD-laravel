<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\Courier\PathaoCourierService;
use App\Services\Courier\RedxCourierService;
use App\Services\Courier\SteadfastCourierService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;

/**
 * Hands an order to a courier API from the queue. The result lands on the
 * order itself (consignment_id on success, courier_error on failure), which
 * is what the admin order page shows.
 */
class SendOrderToCourier implements ShouldQueue
{
    use Queueable;

    public const COURIERS = [
        'steadfast' => SteadfastCourierService::class,
        'pathao' => PathaoCourierService::class,
        'redx' => RedxCourierService::class,
    ];

    public int $tries = 1;

    public function __construct(public int $orderId, public string $courier)
    {
    }

    public function handle(): void
    {
        $order = Order::find($this->orderId);
        $class = self::COURIERS[$this->courier] ?? null;

        if (! $order || ! $class) {
            return;
        }

        try {
            app($class)->dispatch($order);

            $order->forceFill(['courier_error' => null])->save();
        } catch (RuntimeException $e) {
            // Missing keys, already sent, or an API error: not worth retrying
            // blindly (a double dispatch could create two consignments).
            $order->forceFill(['courier_error' => ucfirst($this->courier).': '.$e->getMessage()])->save();
        }
    }
}
