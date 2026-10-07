<?php

namespace App\Jobs;

use App\Services\Sms\SmsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Customer SMS for an order event, sent from the queue so a slow or failing
 * SMS gateway never delays checkout or admin actions. SmsService is
 * idempotent per (order, event), so a retried job can't double-send.
 */
class SendOrderSms implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $orderId, public string $event)
    {
    }

    /** @return list<int> seconds between retries */
    public function backoff(): array
    {
        return [30, 120];
    }

    public function handle(SmsService $sms): void
    {
        $sms->sendOrderEvent($this->orderId, $this->event);
    }
}
