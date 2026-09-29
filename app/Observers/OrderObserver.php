<?php

namespace App\Observers;

use App\Models\Order;
use App\Services\Sms\SmsService;

/**
 * Turns order lifecycle changes into customer SMS — in one place, so it
 * catches every path that writes an order (checkout, landing page, the
 * bKash callback, the admin edit form, courier dispatch) without any of
 * those needing to know SMS exists.
 *
 * The SMS is dispatched after the HTTP response is sent (and therefore
 * after any surrounding DB transaction committed), so a slow or failing
 * gateway can neither delay the customer nor hold order/stock row locks.
 * SmsService::sendOrderEvent() is idempotent per (order, event), so a
 * status flipped back and forth never re-sends.
 */
class OrderObserver
{
    public function created(Order $order): void
    {
        // COD is confirmed the moment it's placed. A bKash order is not
        // confirmed until it's paid — see updated().
        if ($order->payment_method === 'cod') {
            $this->send($order, SmsService::EVENT_CONFIRMED);
        }
    }

    public function updated(Order $order): void
    {
        // bKash payment just succeeded. Not for on_hold: money arrived but
        // stock ran out, so "confirmed" would be a promise we can't keep.
        if ($order->wasChanged('payment_status')
            && $order->payment_status === 'paid'
            && $order->payment_method === 'bkash'
            && $order->status !== 'on_hold') {
            $this->send($order, SmsService::EVENT_CONFIRMED);
        }

        if ($order->wasChanged('status')
            && in_array($order->status, [SmsService::EVENT_SHIPPED, SmsService::EVENT_DELIVERED], true)) {
            // Order statuses "shipped"/"delivered" double as SMS event names.
            $this->send($order, $order->status);
        }
    }

    private function send(Order $order, string $event): void
    {
        $orderId = $order->id;

        dispatch(fn () => app(SmsService::class)->sendOrderEvent($orderId, $event))->afterResponse();
    }
}
