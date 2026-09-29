<?php

namespace App\Services\Sms;

use App\Models\Order;
use App\Models\SiteSetting;
use App\Models\SmsLog;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * SMS notifications through BulkSMSBD's simple HTTP API (POST form:
 * api_key, senderid, number, message, type; success = response_code 202).
 * There was no gateway hint anywhere in the repo, so this follows the
 * most common Bangladeshi provider's documented pattern — see the notes in
 * the hand-off about verifying it against a real account.
 *
 * Credentials: site_settings first (admin-editable; api_key encrypted with
 * the same DecryptException-fallback as the Courier secret and bKash
 * credentials), then config('services.sms.*') / .env.
 *
 * Nothing here ever throws: an SMS problem must never break an order flow.
 * Every failure is caught, written to the log, and recorded on sms_logs.
 */
class SmsService
{
    public const EVENT_CONFIRMED = 'confirmed';

    public const EVENT_SHIPPED = 'shipped';

    public const EVENT_DELIVERED = 'delivered';

    /**
     * Spend guard: anyone can type any phone number at checkout, so cap how
     * many SMS a single number can receive per rolling 24h regardless of
     * how many orders reference it.
     */
    public const MAX_PER_PHONE_PER_DAY = 10;

    private const SETTING_KEYS = ['sms_enabled', 'sms_api_key', 'sms_sender_id', 'sms_language'];

    private ?array $settings = null;

    /** Off unless explicitly enabled (admin toggle, else SMS_ENABLED) AND credentials exist. */
    public function enabled(): bool
    {
        $toggle = $this->setting('sms_enabled');
        $on = $toggle === null ? (bool) config('services.sms.enabled') : $toggle === '1';

        return $on && $this->configured();
    }

    public function configured(): bool
    {
        return filled($this->apiKey()) && filled($this->senderId());
    }

    /**
     * Sends the SMS for one order event, at most once per (order, event).
     * Safe to call from anywhere, any number of times; never throws.
     */
    public function sendOrderEvent(int $orderId, string $event): void
    {
        try {
            if (! $this->enabled()) {
                return;
            }

            $order = Order::find($orderId);

            if (! $order) {
                return;
            }

            $number = $this->normalizeNumber((string) $order->phone);
            $message = SmsTemplates::render($event, $this->language(), $order);

            if ($message === null) {
                return;
            }

            $log = $this->claim($order->id, $event, $number ?? (string) $order->phone, $message);

            if (! $log) {
                return; // already sent (or in flight) for this order+event
            }

            if ($number === null) {
                $log->update(['status' => 'skipped', 'response' => 'Not a valid Bangladeshi mobile number.']);

                return;
            }

            $recent = SmsLog::where('phone', $number)
                ->whereIn('status', ['pending', 'sent'])
                ->where('created_at', '>=', now()->subDay())
                ->count();

            if ($recent > self::MAX_PER_PHONE_PER_DAY) {
                $log->update(['status' => 'skipped', 'response' => 'Daily SMS limit for this number reached.']);

                return;
            }

            $result = $this->send($number, $message);

            $log->update([
                'status' => $result['ok'] ? 'sent' : 'failed',
                'response' => mb_substr($result['response'], 0, 1000),
            ]);
        } catch (Throwable $e) {
            Log::warning('SMS order event failed', ['order_id' => $orderId, 'event' => $event, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Raw gateway call. Returns instead of throwing; also used directly by
     * the admin "Send test SMS" action.
     *
     * @return array{ok: bool, response: string}
     */
    public function send(string $number, string $message): array
    {
        try {
            $response = Http::asForm()
                ->timeout(8)
                ->connectTimeout(4)
                ->post(config('services.sms.url'), [
                    'api_key' => $this->apiKey(),
                    'senderid' => $this->senderId(),
                    'number' => $number,
                    'message' => $message,
                    // Any non-ASCII (Bangla) character makes it a unicode SMS.
                    'type' => mb_check_encoding($message, 'ASCII') ? 'text' : 'unicode',
                ]);

            $body = $response->body();
            $ok = $response->successful() && (int) $response->json('response_code') === 202;

            if (! $ok) {
                Log::warning('SMS gateway rejected message', ['number' => $number, 'status' => $response->status(), 'body' => mb_substr($body, 0, 500)]);
            }

            return ['ok' => $ok, 'response' => $body];
        } catch (Throwable $e) {
            Log::warning('SMS gateway unreachable', ['number' => $number, 'error' => $e->getMessage()]);

            return ['ok' => false, 'response' => 'Could not reach the SMS gateway: '.$e->getMessage()];
        }
    }

    /** Bangladeshi mobile in 8801XXXXXXXXX form, or null if it isn't one. */
    public function normalizeNumber(string $raw): ?string
    {
        $digits = preg_replace('/\D/', '', $raw) ?? '';

        $number = match (true) {
            str_starts_with($digits, '880') => $digits,
            str_starts_with($digits, '0') => '88'.$digits,
            str_starts_with($digits, '1') && strlen($digits) === 10 => '880'.$digits,
            default => $digits,
        };

        return preg_match('/^8801[3-9]\d{8}$/', $number) ? $number : null;
    }

    /**
     * Atomically reserves the right to send this (order, event) SMS. The
     * unique key on sms_logs means two racing requests (or a status flipped
     * back and forth) can't both win. A previously *failed* row may be
     * retried; sent/pending/skipped never are.
     */
    private function claim(int $orderId, string $event, string $phone, string $message): ?SmsLog
    {
        try {
            return SmsLog::create([
                'order_id' => $orderId,
                'event' => $event,
                'phone' => $phone,
                'message' => $message,
                'status' => 'pending',
            ]);
        } catch (UniqueConstraintViolationException) {
            $retry = SmsLog::where('order_id', $orderId)
                ->where('event', $event)
                ->where('status', 'failed')
                ->update(['status' => 'pending', 'phone' => $phone, 'message' => $message]);

            return $retry ? SmsLog::where('order_id', $orderId)->where('event', $event)->first() : null;
        }
    }

    public function language(): string
    {
        $language = $this->setting('sms_language');

        return array_key_exists($language, SmsTemplates::LANGUAGES) ? $language : 'bn';
    }

    private function apiKey(): ?string
    {
        $value = $this->setting('sms_api_key');

        if (filled($value)) {
            try {
                $value = Crypt::decryptString($value);
            } catch (DecryptException) {
                // Pre-existing plaintext value — use as-is.
            }
        }

        return filled($value) ? $value : config('services.sms.api_key');
    }

    private function senderId(): ?string
    {
        $value = $this->setting('sms_sender_id');

        return filled($value) ? $value : config('services.sms.sender_id');
    }

    /** All sms_* rows in one query per instance. */
    private function setting(string $key): ?string
    {
        $this->settings ??= SiteSetting::whereIn('setting_key', self::SETTING_KEYS)
            ->pluck('setting_value', 'setting_key')
            ->all();

        return $this->settings[$key] ?? null;
    }
}
