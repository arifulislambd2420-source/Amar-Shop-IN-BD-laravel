<?php

namespace App\Services\Payment;

use App\Models\Order;
use App\Models\SiteSetting;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * bKash Tokenized Checkout (Checkout URL) integration.
 *
 * Credentials come from site_settings first (admin-editable, encrypted for
 * app_secret/password — see App\Filament\Pages\PaymentSettings, the exact
 * same pattern as CourierSettings/steadfast_secret_key), falling back to
 * config('services.bkash.*') / .env so a fresh install still works before
 * anyone has opened that admin page.
 *
 * Flow: grantToken() -> createPayment() (redirect the customer to the
 * returned bkashURL) -> bKash redirects back to our callback with a
 * paymentID -> executePayment() is the server-to-server call that actually
 * confirms what happened; the callback's own "status" query parameter is
 * never trusted on its own (see App\Http\Controllers\Payment\
 * BkashCallbackController).
 *
 * Note: bKash's API returns HTTP 200 for most business-logic failures too
 * (the error is in the JSON body — "statusMessage"/"msg"), so every method
 * here checks the actual payload fields it needs rather than relying on
 * the HTTP status code.
 */
class BkashService
{
    private const TOKEN_CACHE_KEY = 'bkash:id_token';

    // bKash's own token lifetime is ~1 hour; cache a bit under that so we
    // never try to use one that just expired.
    private const TOKEN_TTL_SECONDS = 3300;

    public function configured(): bool
    {
        return filled($this->credential('bkash_app_key'))
            && filled($this->credential('bkash_app_secret'))
            && filled($this->credential('bkash_username'))
            && filled($this->credential('bkash_password'));
    }

    /**
     * @throws RuntimeException on missing credentials or any bKash/HTTP failure.
     */
    public function grantToken(): string
    {
        return Cache::remember(self::TOKEN_CACHE_KEY, self::TOKEN_TTL_SECONDS, function () {
            if (! $this->configured()) {
                throw new RuntimeException('bKash credentials are not configured in Payment Settings.');
            }

            $response = $this->post('/tokenized/checkout/token/grant', [
                'app_key' => $this->credential('bkash_app_key'),
                'app_secret' => $this->credential('bkash_app_secret'),
            ], [
                'username' => $this->credential('bkash_username'),
                'password' => $this->credential('bkash_password'),
            ]);

            $token = $response->json('id_token');

            if (blank($token)) {
                throw new RuntimeException('bKash token grant failed: '.($response->json('msg') ?? $response->body()));
            }

            return $token;
        });
    }

    /**
     * Creates a bKash payment session for an order that has ALREADY been
     * created with payment_method=bkash and payment_status=unpaid (see
     * OrderService::createOrder($reserveStock: false)). Stores bKash's
     * paymentID on the order immediately (before the customer has paid
     * anything) so the callback can look the order back up.
     *
     * @return array{paymentID: string, bkashURL: string}
     *
     * @throws RuntimeException on any bKash/HTTP failure.
     */
    public function createPayment(Order $order): array
    {
        // The amount sent to bKash is the order's own server-computed
        // total (set by OrderService from DB prices) — never anything
        // passed in from the client/request.
        $response = $this->post('/tokenized/checkout/create', [
            'mode' => '0011',
            'payerReference' => $order->phone,
            'callbackURL' => route('payment.bkash.callback'),
            'amount' => number_format((float) $order->total, 2, '.', ''),
            'currency' => 'BDT',
            'intent' => 'sale',
            'merchantInvoiceNumber' => $order->invoice_no,
        ], [
            'Authorization' => $this->grantToken(),
            'X-APP-Key' => $this->credential('bkash_app_key'),
        ]);

        $paymentId = $response->json('paymentID');
        $bkashUrl = $response->json('bkashURL');

        if (blank($paymentId) || blank($bkashUrl)) {
            throw new RuntimeException('bKash payment creation failed: '.($response->json('statusMessage') ?? $response->body()));
        }

        $order->update(['transaction_id' => $paymentId]);

        return ['paymentID' => $paymentId, 'bkashURL' => $bkashUrl];
    }

    /**
     * The authoritative, server-to-server confirmation of what actually
     * happened to a payment — this is what BkashCallbackController bases
     * its decision on, never the callback's own query-string "status".
     * A non-success transactionStatus in the response is NOT an exception
     * here — the caller inspects that.
     *
     * @return array<string, mixed> the raw bKash response.
     *
     * @throws RuntimeException on a connection/HTTP failure.
     */
    public function executePayment(string $paymentId): array
    {
        $response = $this->post('/tokenized/checkout/execute', [
            'paymentID' => $paymentId,
        ], [
            'Authorization' => $this->grantToken(),
            'X-APP-Key' => $this->credential('bkash_app_key'),
        ]);

        return $response->json() ?? [];
    }

    /**
     * Re-checks a payment's status without side effects — used when a
     * callback is revisited/retried and execute() may already have run
     * (bKash rejects a second execute() for the same paymentID).
     *
     * @return array<string, mixed>
     */
    public function queryPayment(string $paymentId): array
    {
        $response = $this->post('/tokenized/checkout/payment/status', [
            'paymentID' => $paymentId,
        ], [
            'Authorization' => $this->grantToken(),
            'X-APP-Key' => $this->credential('bkash_app_key'),
        ]);

        return $response->json() ?? [];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $headers  Extra headers beyond username/password (used only by the token-grant call).
     *
     * @throws RuntimeException on a connection failure.
     */
    private function post(string $path, array $payload, array $headers = []): Response
    {
        try {
            return $this->client()
                ->withHeaders($headers)
                ->post($path, $payload);
        } catch (ConnectionException $e) {
            throw new RuntimeException('Could not reach bKash: '.$e->getMessage());
        }
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl())
            ->timeout(20)
            ->connectTimeout(10)
            ->acceptJson()
            ->asJson();
    }

    private function baseUrl(): string
    {
        return $this->credential('bkash_base_url') ?: config('services.bkash.base_url');
    }

    /**
     * Reads one credential: site_settings first (decrypting app_secret/
     * password, falling back to the raw stored value if it predates
     * encryption — same DecryptException-fallback pattern as
     * SteadfastCourierService), then config('services.bkash.*') as the
     * .env-backed default.
     */
    private function credential(string $settingKey): ?string
    {
        $value = SiteSetting::where('setting_key', $settingKey)->value('setting_value');

        if (in_array($settingKey, ['bkash_app_secret', 'bkash_password'], true) && filled($value)) {
            try {
                $value = Crypt::decryptString($value);
            } catch (DecryptException) {
                // Pre-existing plaintext value — use as-is.
            }
        }

        if (filled($value)) {
            return $value;
        }

        $configKey = Str::after($settingKey, 'bkash_');

        return config("services.bkash.{$configKey}");
    }
}
