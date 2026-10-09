<?php

namespace App\Services\Sms;

use App\Models\Order;
use App\Support\SiteSettingsHelper;

/**
 * Customer SMS wording, Bangla and English. Deliberately short: a message
 * containing any Bangla character is a "unicode" SMS, which fits only 70
 * characters per segment (160 for plain English) and is billed per segment.
 * Admin picks the language in Payment > SMS Settings (default Bangla).
 */
class SmsTemplates
{
    public const LANGUAGES = ['bn' => 'বাংলা', 'en' => 'ইংরেজি (সস্তা — ১৬০ অক্ষর/এসএমএস)'];

    private const TEMPLATES = [
        'bn' => [
            'confirmed' => '{site}: আপনার অর্ডার {invoice} কনফার্ম হয়েছে। মোট {total} টাকা। ধন্যবাদ!',
            'shipped' => '{site}: আপনার অর্ডার {invoice} পাঠানো হয়েছে।{tracking}',
            'delivered' => '{site}: আপনার অর্ডার {invoice} ডেলিভারি সম্পন্ন হয়েছে। ধন্যবাদ!',
        ],
        'en' => [
            'confirmed' => '{site}: Your order {invoice} is confirmed. Total Tk {total}. Thank you!',
            'shipped' => '{site}: Your order {invoice} has been shipped.{tracking}',
            'delivered' => '{site}: Your order {invoice} has been delivered. Thank you!',
        ],
    ];

    public static function render(string $event, string $language, Order $order): ?string
    {
        $template = self::TEMPLATES[$language][$event] ?? self::TEMPLATES['bn'][$event] ?? null;

        if ($template === null) {
            return null;
        }

        $tracking = filled($order->tracking_code)
            ? ($language === 'en' ? " Tracking: {$order->tracking_code}" : " ট্র্যাকিং: {$order->tracking_code}")
            : '';

        return strtr($template, [
            '{site}' => $language === 'en' ? SiteSettingsHelper::siteNameEn() : SiteSettingsHelper::siteName(),
            '{invoice}' => (string) $order->invoice_no,
            '{total}' => number_format((float) $order->total),
            '{tracking}' => $tracking,
        ]);
    }
}
