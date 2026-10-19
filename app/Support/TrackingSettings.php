<?php

namespace App\Support;

use App\Models\SiteSetting;
use Illuminate\Support\Facades\Crypt;

/**
 * The server-side tracking settings (Admin → GTM / Pixel). The two secrets — the Meta
 * Conversions API access token and the GA4 API secret — are stored encrypted (Laravel's
 * Crypt, i.e. APP_KEY) and are never shown again: the admin screen only gets the last four
 * characters. Nothing here writes a secret to a log or to a page.
 */
class TrackingSettings
{
    public const CAPI_TOKEN = 'capi_access_token';

    public const CAPI_TEST_CODE = 'capi_test_event_code';

    public const GA4_ID = 'ga4_measurement_id';

    public const GA4_SECRET = 'ga4_api_secret';

    public const PURCHASE_STATUS = 'tracking_purchase_status';

    /** A GA4 measurement ID: G- and 6–12 letters/digits. */
    public const GA4_ID_PATTERN = '/^G-[A-Z0-9]{6,12}$/';

    /** The default status that sends the server-side Purchase. */
    public const DEFAULT_STATUS = 'processing';

    /**
     * Order statuses in the order an order moves through them: reaching the chosen one — or any
     * later one — sends Purchase (so skipping "confirmed" straight to "shipped" still counts).
     * Cancelled and on-hold orders never send it.
     */
    public const STATUS_ORDER = ['pending', 'processing', 'shipped', 'out_for_delivery', 'delivered', 'completed'];

    private static function raw(string $key): string
    {
        return trim((string) (SiteSetting::where('setting_key', $key)->value('setting_value') ?? ''));
    }

    public static function secret(string $key): ?string
    {
        $value = self::raw($key);

        if ($value === '') {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (\Throwable) {
            return null; // APP_KEY changed or the value is damaged: treated as "not set"
        }
    }

    /** Save a secret encrypted; '' removes it. */
    public static function putSecret(string $key, string $plain): void
    {
        SiteSetting::updateOrCreate(
            ['setting_key' => $key],
            ['setting_value' => $plain === '' ? '' : Crypt::encryptString($plain)],
        );
    }

    /** "••••••••abcd" for a saved secret, null when none. Only the last four characters are ever shown. */
    public static function masked(string $key): ?string
    {
        $secret = self::secret($key);

        return $secret === null ? null : '••••••••'.mb_substr($secret, -4);
    }

    public static function capiToken(): ?string
    {
        return self::secret(self::CAPI_TOKEN);
    }

    public static function capiTestCode(): ?string
    {
        $code = self::raw(self::CAPI_TEST_CODE);

        return $code !== '' ? $code : null;
    }

    public static function ga4Id(): ?string
    {
        $id = strtoupper(self::raw(self::GA4_ID));

        return preg_match(self::GA4_ID_PATTERN, $id) ? $id : null;
    }

    public static function ga4Secret(): ?string
    {
        return self::secret(self::GA4_SECRET);
    }

    /** The status (or a later one) from which the server-side Purchase is sent. */
    public static function purchaseStatus(): string
    {
        $status = self::raw(self::PURCHASE_STATUS);

        return in_array($status, self::STATUS_ORDER, true) ? $status : self::DEFAULT_STATUS;
    }

    /** Meta Conversions API is ready: a Pixel ID and an access token. */
    public static function capiReady(): bool
    {
        return preg_replace('/\D+/', '', self::raw('meta_pixel_id')) !== '' && self::capiToken() !== null;
    }

    /** GA4 Measurement Protocol is ready: a measurement ID and an API secret. */
    public static function ga4Ready(): bool
    {
        return self::ga4Id() !== null && self::ga4Secret() !== null;
    }
}
