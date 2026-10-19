<?php

namespace App\Services\Tracking;

use App\Jobs\SendServerPurchase;
use App\Models\Order;
use App\Support\Tracking;
use App\Support\TrackingSettings;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * The server-side Purchase: when an order reaches the status chosen in Admin → GTM / Pixel (Confirmed
 * by default) the server tells Meta (Conversions API) and Google Analytics 4 (Measurement Protocol)
 * about it — once per order, from the queue, with retries. The browser no longer sends Purchase.
 *
 * It uses what the order saved when it was placed (fbp, fbc, IP, user agent, page, GA client id) and
 * sends the phone, name and city as SHA-256 hashes (Advanced Matching). The event id is the
 * purchase-<invoice> one Meta uses to de-duplicate. Tokens and secrets never go into a log, an error
 * message stored on the order or a page: every message passes through sanitize().
 */
class ServerPurchase
{
    public const GRAPH_VERSION = 'v21.0';

    private const META_URL = 'https://graph.facebook.com/%s/%s/events';

    private const GA4_URL = 'https://www.google-analytics.com/mp/collect';

    private const GA4_DEBUG_URL = 'https://www.google-analytics.com/debug/mp/collect';

    // ── when ─────────────────────────────────────────────────────────────

    /** Something is set up to receive it (Meta or GA4). */
    public static function configured(): bool
    {
        return TrackingSettings::capiReady() || TrackingSettings::ga4Ready();
    }

    /** A real purchase that may be reported at all: not cancelled / on hold, not an unpaid or failed bKash attempt (the shop's Order::counted() rule). */
    public static function sendable(Order $order): bool
    {
        return in_array($order->status, TrackingSettings::STATUS_ORDER, true)
            && ! ($order->payment_method === 'bkash' && in_array($order->payment_status, ['unpaid', 'failed'], true));
    }

    /** Sendable, and far enough along: it has reached the chosen status or a later one. */
    public static function eligible(Order $order): bool
    {
        $flow = TrackingSettings::STATUS_ORDER;

        return self::sendable($order)
            && array_search($order->status, $flow, true) >= array_search(TrackingSettings::purchaseStatus(), $flow, true);
    }

    /** Called when an order is created or its status / payment changes: queue the Purchase the first time it qualifies. */
    public static function maybeDispatch(Order $order): void
    {
        if ($order->purchase_triggered_at !== null || ! self::configured() || ! self::eligible($order)) {
            return;
        }

        $order->forceFill(['purchase_triggered_at' => now()])->saveQuietly();

        SendServerPurchase::dispatch($order->id)->afterCommit();
    }

    /** The admin's "send again": whatever has not gone out yet is sent now, whatever the status. */
    public static function resend(Order $order): void
    {
        if ($order->purchase_triggered_at === null) {
            $order->forceFill(['purchase_triggered_at' => now()])->saveQuietly();
        }

        SendServerPurchase::dispatch($order->id, force: true)->afterCommit();
    }

    // ── hashing (Advanced Matching) ──────────────────────────────────────

    private static function hash(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : hash('sha256', $value);
    }

    /** Bangladeshi numbers as digits with the country code: 01712345678 → 8801712345678. */
    public static function phoneDigits(?string $phone): string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);

        return str_starts_with($digits, '01') ? '88'.$digits : $digits;
    }

    /** Lowercase, letters and digits only (how Meta wants a city). */
    private static function plain(?string $value): string
    {
        // \p{M}: the vowel signs of Bangla (া ি ু …) are marks, not letters — dropping them would garble the city.
        return preg_replace('/[^\p{L}\p{M}\p{N}]+/u', '', mb_strtolower(trim((string) $value)));
    }

    // ── what is sent ─────────────────────────────────────────────────────

    /** @return array<string, mixed> one Conversions API event */
    public function metaEvent(Order $order): array
    {
        $order->loadMissing('items');

        $parts = preg_split('/\s+/u', mb_strtolower(trim((string) $order->customer_name)), 2, PREG_SPLIT_NO_EMPTY) ?: [];

        $user = array_filter([
            'client_ip_address' => $order->ip_address,
            'client_user_agent' => $order->client_user_agent,
            'fbp' => $order->fbp,
            'fbc' => $order->fbc,
            'ph' => array_filter([self::hash(self::phoneDigits($order->phone))]) ?: null,
            'em' => array_filter([self::hash(mb_strtolower((string) $order->email))]) ?: null,
            'fn' => array_filter([self::hash($parts[0] ?? null)]) ?: null,
            'ln' => array_filter([self::hash($parts[1] ?? null)]) ?: null,
            'ct' => array_filter([self::hash(self::plain($order->district))]) ?: null,
            'zp' => array_filter([self::hash(preg_replace('/\D+/', '', (string) $order->postcode))]) ?: null,
            'country' => [self::hash('bd')],
        ]);

        return array_filter([
            'event_name' => 'Purchase',
            'event_time' => ($order->purchase_triggered_at ?? now())->timestamp,
            'event_id' => Tracking::purchase($order)['event_id'],
            'action_source' => 'website',
            'event_source_url' => $order->event_source_url,
            'user_data' => $user,
            'custom_data' => [
                'currency' => 'BDT',
                'value' => (float) $order->total,
                'order_id' => $order->invoice_no,
                'content_type' => 'product',
                'content_ids' => $order->items->pluck('product_id')->map(fn ($id) => (string) $id)->values()->all(),
                'contents' => $order->items->map(fn ($i) => ['id' => (string) $i->product_id, 'quantity' => (int) $i->quantity, 'item_price' => (float) $i->unit_price])->values()->all(),
                'num_items' => (int) $order->items->sum('quantity'),
            ],
        ], fn ($v) => $v !== null && $v !== '');
    }

    /** @return array<string, mixed> a GA4 Measurement Protocol body with one purchase */
    public function ga4Body(Order $order): array
    {
        $order->loadMissing('items');

        $time = ($order->purchase_triggered_at ?? now())->timestamp;
        $parts = preg_split('/\s+/u', mb_strtolower(trim((string) $order->customer_name)), 2, PREG_SPLIT_NO_EMPTY) ?: [];
        $phone = self::phoneDigits($order->phone);

        $address = array_filter([
            'sha256_first_name' => self::hash($parts[0] ?? null),
            'sha256_last_name' => self::hash($parts[1] ?? null),
            'city' => $order->district ?: null,
            'postal_code' => $order->postcode ?: null,
            'country' => 'BD',
        ]);

        return [
            // The browser's GA client id when the order saved one; else a steady stand-in for this order.
            'client_id' => $order->ga_client_id ?: sprintf('%u.%u', crc32((string) $order->invoice_no), $order->created_at?->timestamp ?? $time),
            'timestamp_micros' => $time * 1_000_000,
            'user_data' => array_filter([
                'sha256_phone_number' => $phone !== '' ? [self::hash('+'.$phone)] : null,
                'sha256_email_address' => filled($order->email) ? [self::hash(mb_strtolower(trim($order->email)))] : null,
                'address' => [$address],
            ]),
            'events' => [[
                'name' => 'purchase',
                'params' => [
                    'transaction_id' => $order->invoice_no,
                    'value' => (float) $order->total,
                    'currency' => 'BDT',
                    'shipping' => (float) $order->shipping_fee,
                    'engagement_time_msec' => 100,
                    'items' => $order->items->map(fn ($i) => [
                        'item_id' => (string) $i->product_id,
                        'item_name' => $i->product_name,
                        'price' => (float) $i->unit_price,
                        'quantity' => (int) $i->quantity,
                    ])->values()->all(),
                ],
            ]],
        ];
    }

    // ── sending ──────────────────────────────────────────────────────────

    /**
     * Send one event to the Conversions API.
     *
     * @param  array<string, mixed>  $event
     * @return array{ok: bool, retry: bool, message: string}
     */
    public function sendMeta(string $pixelId, string $token, array $event, ?string $testCode = null): array
    {
        $body = array_filter(['data' => [$event], 'access_token' => $token, 'test_event_code' => $testCode]);

        try {
            $response = Http::timeout(15)->acceptJson()->asJson()
                ->post(sprintf(self::META_URL, config('services.meta.graph_version', self::GRAPH_VERSION), $pixelId), $body);
        } catch (\Throwable $e) {
            return $this->failure($e instanceof ConnectionException ? 'Could not reach Meta: '.$e->getMessage() : $e->getMessage(), true, [$token]);
        }

        if ($response->successful() && (int) $response->json('events_received', 0) >= 1) {
            return ['ok' => true, 'retry' => false, 'message' => 'Meta received the event (events_received: '.(int) $response->json('events_received').', fbtrace_id: '.$response->json('fbtrace_id', '—').').'];
        }

        $error = $response->json('error.message') ?: ($response->successful() ? 'Meta accepted the request but received 0 events.' : 'HTTP '.$response->status());

        return $this->failure("Meta: {$error}", $this->retryable($response), [$token]);
    }

    /**
     * Send a purchase to GA4's Measurement Protocol (or, with $debug, to its validator, which records nothing).
     *
     * @param  array<string, mixed>  $body
     * @return array{ok: bool, retry: bool, message: string}
     */
    public function sendGa4(string $measurementId, string $secret, array $body, bool $debug = false): array
    {
        try {
            $response = Http::timeout(15)->asJson()
                ->post(($debug ? self::GA4_DEBUG_URL : self::GA4_URL).'?'.http_build_query(['measurement_id' => $measurementId, 'api_secret' => $secret]), $body);
        } catch (\Throwable $e) {
            return $this->failure($e instanceof ConnectionException ? 'Could not reach Google Analytics: '.$e->getMessage() : $e->getMessage(), true, [$secret]);
        }

        if (! $response->successful()) {
            return $this->failure('Google Analytics: HTTP '.$response->status(), $this->retryable($response), [$secret]);
        }

        if ($debug) {
            $messages = collect($response->json('validationMessages', []))->pluck('description')->filter()->all();

            return $messages === []
                ? ['ok' => true, 'retry' => false, 'message' => 'Google Analytics accepted the test purchase (no validation problems).']
                : $this->failure('Google Analytics: '.implode(' | ', $messages), false, [$secret]);
        }

        return ['ok' => true, 'retry' => false, 'message' => 'Google Analytics accepted the purchase.'];
    }

    /** Server trouble, rate limits and timeouts are worth another try; a rejected token or a bad request is not. */
    private function retryable(Response $response): bool
    {
        return $response->serverError() || in_array($response->status(), [408, 429], true);
    }

    private function failure(string $message, bool $retry, array $secrets): array
    {
        return ['ok' => false, 'retry' => $retry, 'message' => self::sanitize($message, $secrets)];
    }

    /** Never let a token or secret out in a message: the saved ones, the given ones and anything shaped like one in a URL. */
    public static function sanitize(string $message, array $secrets = []): string
    {
        $secrets = array_filter([...$secrets, TrackingSettings::capiToken(), TrackingSettings::ga4Secret()], fn ($s) => is_string($s) && strlen($s) >= 4);

        foreach ($secrets as $secret) {
            $message = str_replace($secret, '[hidden]', $message);
        }

        $message = preg_replace('/(access_token|api_secret)=[^&\s"\']+/i', '$1=[hidden]', $message);

        return mb_substr($message, 0, 500);
    }

    // ── "Test connection" ────────────────────────────────────────────────

    /**
     * A test Purchase to Meta with the Test Event Code — it shows only in Events Manager → Test events.
     * Refused without a code: without one it would count as a real purchase.
     *
     * @return array{ok: bool, retry: bool, message: string}
     */
    public function testMeta(string $pixelId, string $token, ?string $testCode): array
    {
        if (blank($testCode)) {
            return ['ok' => false, 'retry' => false, 'message' => 'Enter a Test Event Code first (Events Manager → Test events). Without one the test would count as a real purchase.'];
        }

        $event = [
            'event_name' => 'Purchase',
            'event_time' => now()->timestamp,
            'event_id' => 'test-'.bin2hex(random_bytes(6)),
            'action_source' => 'website',
            'user_data' => ['client_ip_address' => '203.0.113.7', 'client_user_agent' => 'Mozilla/5.0 (connection test)', 'ph' => [self::hash('8801700000000')], 'country' => [self::hash('bd')]],
            'custom_data' => ['currency' => 'BDT', 'value' => 1.0, 'order_id' => 'TEST', 'content_type' => 'product', 'content_ids' => ['test'], 'num_items' => 1],
        ];

        $result = $this->sendMeta($pixelId, $token, $event, $testCode);

        if ($result['ok']) {
            $result['message'] .= " Open Events Manager → Test events (code {$testCode}) to see it.";
        }

        return $result;
    }

    /** A sample purchase through GA4's validator (nothing is recorded). */
    public function testGa4(string $measurementId, string $secret): array
    {
        $body = [
            'client_id' => '1234567890.1234567890',
            'events' => [['name' => 'purchase', 'params' => ['transaction_id' => 'TEST', 'value' => 1.0, 'currency' => 'BDT', 'items' => [['item_id' => 'test', 'item_name' => 'Test', 'price' => 1.0, 'quantity' => 1]]]]],
        ];

        return $this->sendGa4($measurementId, $secret, $body, debug: true);
    }
}
