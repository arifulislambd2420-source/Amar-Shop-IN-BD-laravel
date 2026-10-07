<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IncompleteOrder extends Model
{
    public const OPEN = 'open';
    public const CONVERTED = 'converted';
    public const DISMISSED = 'dismissed';

    protected $fillable = [
        'session_id', 'source', 'landing_page_id', 'name', 'phone', 'district', 'address',
        'items', 'total', 'status', 'order_id', 'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'items' => 'array',
            'total' => 'decimal:2',
        ];
    }

    public function landingPage(): BelongsTo
    {
        return $this->belongsTo(LandingPage::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Remember (or refresh) an unfinished order from this browser session.
     * Only a plausible Bangladeshi mobile number is stored — no number, no row.
     *
     * @param  list<array{product_id:int, variant_id:?int, name:string, quantity:int, unit_price:float}>  $items
     */
    public static function capture(string $sessionId, string $source, ?int $landingPageId, string $phone, ?string $name, ?string $district, ?string $address, array $items, ?string $ip): ?self
    {
        $digits = preg_replace('/\D+/', '', $phone);

        if (! preg_match('/^(?:880|0)?1[3-9]\d{8}$/', $digits) || $items === []) {
            return null;
        }

        $total = array_sum(array_map(fn ($i) => $i['unit_price'] * $i['quantity'], $items));

        // A phone that already has an open row for this source/page is the same lead.
        $row = static::query()
            ->where('status', self::OPEN)
            ->where('source', $source)
            ->where('landing_page_id', $landingPageId)
            ->where(fn ($q) => $q->where('session_id', $sessionId)->orWhere('phone', $digits))
            ->first();

        $values = [
            'session_id' => $sessionId,
            'phone' => $digits,
            'name' => $name ?: null,
            'district' => $district ?: null,
            'address' => $address ?: null,
            'items' => $items,
            'total' => $total,
            'ip_address' => $ip,
        ];

        if ($row) {
            $row->update($values);

            return $row;
        }

        return static::create($values + ['source' => $source, 'landing_page_id' => $landingPageId, 'status' => self::OPEN]);
    }

    /**
     * Turn this lead into a real COD order (admin action). Uses the prices the
     * customer saw when the lead was captured; the one-order-per-phone
     * cooldown is bypassed because an admin is acting on a phone call.
     *
     * @param  array{customer_name:string, phone:string, district:string, thana:string, address:string}  $data
     *
     * @throws \RuntimeException when stock is gone or a product no longer exists
     */
    public function convertToOrder(array $data): Order
    {
        $lines = collect($this->items)->map(fn ($i) => [
            'product_id' => (int) $i['product_id'],
            'variant_id' => $i['variant_id'] ?? null,
            'quantity' => (int) $i['quantity'],
            'unit_price_override' => isset($i['unit_price']) ? (float) $i['unit_price'] : null,
        ])->all();

        $order = app(\App\Services\OrderService::class)->createOrder($lines, [
            'customer_name' => $data['customer_name'],
            'phone' => $data['phone'],
            'email' => null,
            'district' => $data['district'],
            'thana' => $data['thana'],
            'postcode' => null,
            'address' => $data['address'],
            'notes' => 'অসম্পূর্ণ অর্ডার থেকে রূপান্তর (admin)',
            'payment_method' => 'cod',
        ], null, $this->landing_page_id, true, true);

        // markConverted() (run by the order's own flow) may already have closed it.
        $this->update(['status' => self::CONVERTED, 'order_id' => $order->id]);

        return $order;
    }

    /** The customer placed the order after all: close every open lead for this phone. */
    public static function markConverted(string $phone, int $orderId): void
    {
        $variants = app(\App\Services\OrderRiskService::class)->phoneVariants(preg_replace('/\D+/', '', $phone));

        // Stored phones are digits as typed (01…, 8801…); match on the bare 10 digits.
        static::query()
            ->where('status', self::OPEN)
            ->where(fn ($q) => collect($variants)->each(fn ($v) => $q->orWhere('phone', $v)))
            ->update(['status' => self::CONVERTED, 'order_id' => $orderId]);
    }
}
