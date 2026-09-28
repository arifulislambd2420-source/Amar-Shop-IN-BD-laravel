<?php

namespace App\Services\Courier;

use App\Models\Order;
use App\Models\SiteSetting;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Real Steadfast Courier integration — a faithful port of the old app's
 * src/app/api/admin/orders/[id]/steadfast/route.ts: same endpoint, same
 * Api-Key/Secret-Key headers, same payload shape, same double-dispatch
 * guard, and the same fields written back onto the order.
 */
class SteadfastCourierService
{
    public const ENDPOINT = 'https://portal.steadfast.com.bd/api/v1/create_order';

    /**
     * @throws RuntimeException on missing credentials, already-sent orders,
     *                          or any Steadfast API/HTTP failure.
     */
    public function dispatch(Order $order): Order
    {
        if ($order->consignment_id) {
            throw new RuntimeException('Order is already sent to a courier.');
        }

        $settings = SiteSetting::whereIn('setting_key', ['steadfast_api_key', 'steadfast_secret_key'])
            ->pluck('setting_value', 'setting_key');

        $apiKey = $settings->get('steadfast_api_key');
        $secretKey = $settings->get('steadfast_secret_key');

        if (blank($apiKey) || blank($secretKey)) {
            throw new RuntimeException('Steadfast Courier API keys are not configured in settings.');
        }

        // CourierSettings::save() encrypts this value. Fall back to the raw
        // stored value if decryption fails, so a secret saved before this
        // change (still plaintext) keeps working — it will be re-encrypted
        // next time it's saved from the admin panel.
        try {
            $secretKey = Crypt::decryptString($secretKey);
        } catch (DecryptException) {
            // Pre-existing plaintext value — use as-is.
        }

        $payload = [
            'invoice' => (string) $order->id,
            'recipient_name' => $order->customer_name,
            'recipient_phone' => $order->phone,
            'recipient_address' => trim("{$order->address}, {$order->thana}, {$order->district}"),
            'cod_amount' => $order->payment_method === 'cod' ? (float) $order->total : 0,
            'note' => $order->notes ?: '',
        ];

        try {
            $response = Http::timeout(15)->withHeaders([
                'Api-Key' => $apiKey,
                'Secret-Key' => $secretKey,
                'Content-Type' => 'application/json',
            ])->post(self::ENDPOINT, $payload);
        } catch (ConnectionException $e) {
            throw new RuntimeException('Could not reach Steadfast: '.$e->getMessage());
        }

        $data = $response->json();

        if (($data['status'] ?? null) !== 200) {
            throw new RuntimeException($data['message'] ?? 'Failed to create parcel on Steadfast');
        }

        $consignmentId = $data['consignment']['consignment_id'] ?? null;
        $trackingCode = $data['consignment']['tracking_code'] ?? null;
        $courierStatus = $data['consignment']['status'] ?? 'pending';

        if (! $consignmentId || ! $trackingCode) {
            throw new RuntimeException('Invalid response from Steadfast API');
        }

        $order->update([
            'consignment_id' => $consignmentId,
            'tracking_code' => $trackingCode,
            'courier_status' => $courierStatus,
            'status' => 'processing',
        ]);

        return $order;
    }
}
