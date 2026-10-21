<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\Tracking\ServerPurchase;
use App\Support\TrackingSettings;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Throwable;

/**
 * Sends one order's Purchase to Meta (Conversions API) and Google Analytics 4 (Measurement Protocol)
 * from the queue. Each platform is tracked on the order (status sent / failed + the reason), is sent at
 * most once, and a platform that already went out is never repeated. Server trouble, rate limits and
 * timeouts are retried (after 1, 5 and 15 minutes); a rejected token or a bad request is not, it is
 * shown on the order with a "send again" button. No message stored here can contain a token or secret.
 */
class SendServerPurchase implements ShouldQueue
{
    use Queueable;

    public int $tries = 4;

    /** @var list<int> */
    public array $backoff = [60, 300, 900];

    public function __construct(public int $orderId, public bool $force = false)
    {
    }

    public function handle(ServerPurchase $purchase): void
    {
        // One run per order at a time (a double click on "send again", a retry overlapping a new job).
        $lock = Cache::lock('server-purchase:'.$this->orderId, 120);

        if (! $lock->get()) {
            $this->release(30);

            return;
        }

        try {
            $this->send($purchase);
        } finally {
            $lock->release();
        }
    }

    private function send(ServerPurchase $purchase): void
    {
        $order = Order::with('items')->find($this->orderId);

        if (! $order || ! ($this->force ? ServerPurchase::sendable($order) : ServerPurchase::eligible($order))) {
            return;
        }

        $retry = false;

        if (TrackingSettings::capiReady() && $order->capi_status !== 'sent') {
            $pixel = preg_replace('/\D+/', '', (string) \App\Support\SiteSettingsHelper::get('meta_pixel_id'));
            $result = $purchase->sendMeta($pixel, (string) TrackingSettings::capiToken(), $purchase->metaEvent($order), TrackingSettings::capiTestCode());

            $order->forceFill($this->outcome('capi', $result))->saveQuietly();
            $retry = $retry || (! $result['ok'] && $result['retry']);
        }

        if (TrackingSettings::ga4Ready() && $order->ga4_status !== 'sent') {
            $result = $purchase->sendGa4((string) TrackingSettings::ga4Id(), (string) TrackingSettings::ga4Secret(), $purchase->ga4Body($order));

            $order->forceFill($this->outcome('ga4', $result))->saveQuietly();
            $retry = $retry || (! $result['ok'] && $result['retry']);
        }

        if ($retry) {
            // The queue tries again after the back-off; the reason so far is already on the order.
            throw new RuntimeException('Purchase not delivered yet; will retry.');
        }
    }

    /** @return array<string, mixed> */
    private function outcome(string $platform, array $result): array
    {
        return $result['ok']
            ? ["{$platform}_status" => 'sent', "{$platform}_error" => null, "{$platform}_sent_at" => now()]
            : ["{$platform}_status" => 'failed', "{$platform}_error" => $result['message']];
    }

    /** Out of tries: whatever is still not sent stays "failed" with its last reason (set above). */
    public function failed(Throwable $e): void
    {
        // Nothing to add: each attempt already stored its own sanitized reason on the order.
    }
}
