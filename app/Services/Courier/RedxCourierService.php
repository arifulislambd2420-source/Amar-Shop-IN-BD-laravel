<?php

namespace App\Services\Courier;

use App\Models\Order;
use RuntimeException;

/**
 * MOCK RedX integration — faithful port of the old app's
 * src/app/api/admin/orders/[id]/redx/route.ts: no real HTTP call, just a
 * simulated delay and a fake consignment id in the exact same format
 * (REDX-{id}-{random} / TRK-RX-{id}), with the same double-dispatch guard.
 */
class RedxCourierService
{
    public function dispatch(Order $order): Order
    {
        if ($order->consignment_id) {
            throw new RuntimeException('Order is already sent to courier');
        }

        // Simulated API request delay, same as the old app.
        usleep(1_000_000);

        $consignmentId = sprintf('REDX-%d-%d', $order->id, random_int(0, 9999));
        $trackingCode = sprintf('TRK-RX-%d', $order->id);

        $order->update([
            'consignment_id' => $consignmentId,
            'tracking_code' => $trackingCode,
            'courier_status' => 'pending',
            'status' => 'processing',
        ]);

        return $order;
    }
}
