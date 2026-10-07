<?php

namespace App\Observers;

use App\Jobs\SendOrderSms;
use App\Models\Order;
use App\Services\Sms\SmsService;

/**
 * Turns order lifecycle changes into customer SMS — in one place, so it
 * catches every path that writes an order (checkout, landing page, the
 * bKash callback, the admin edit form, courier dispatch) without any of
 * those needing to know SMS exists.
 *
 * The SMS is a queued job (SendOrderSms), dispatched after the response and
 * after the surrounding DB transaction commits, so a slow or failing
 * gateway can neither delay the customer nor hold order/stock row locks.
 * (With QUEUE_CONNECTION=sync it simply runs right after the response.)
 * SmsService::sendOrderEvent() is idempotent per (order, event), so a
 * status flipped back and forth never re-sends.
 */
class OrderObserver
{
    public function created(Order $order): void
    {
        // COD is confirmed the moment it's placed. A bKash order is not
        // confirmed until it's paid — see updated().
        //
        // Flagged (suspected duplicate/fake) orders get no confirmation
        // yet: texting a possibly-fake number costs money and bothers
        // whoever owns it. It is sent if an admin clears the flag — below.
        if ($order->payment_method === 'cod' && ! $order->is_flagged) {
            $this->send($order, SmsService::EVENT_CONFIRMED);
        }
    }

    public function updated(Order $order): void
    {
        // Admin reviewed a flagged COD order and cleared the flag: it's now
        // a normal confirmed order. (SmsService dedupes, so this can never
        // double-send.)
        if ($order->wasChanged('is_flagged')
            && ! $order->is_flagged
            && $order->payment_method === 'cod'
            && $order->status !== 'cancelled') {
            $this->send($order, SmsService::EVENT_CONFIRMED);
        }

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
        SendOrderSms::dispatch($order->id, $event)->afterCommit()->afterResponse();
    }
}
