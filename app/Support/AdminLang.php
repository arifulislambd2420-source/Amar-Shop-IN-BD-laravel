<?php

namespace App\Support;

use Filament\Forms\Components\Field;
use Filament\Infolists\Components\Entry;
use Filament\Tables\Columns\Column;
use Filament\Tables\Filters\BaseFilter;

/**
 * Bangla for the admin panel.
 *
 * Filament's own texts (Save, Cancel, "Move up", pagination, ...) come from
 * its bundled `bn` translation once the locale is `bn` — see
 * App\Http\Middleware\SetAdminLocale. This class covers what Filament cannot
 * know: when a field, table column or filter has no label of its own,
 * Filament makes one from its *name* ("customer_name" → "Customer name"),
 * which is English. Here every such name gets a Bangla default; a label set
 * on the component itself still wins (configureUsing runs first).
 */
class AdminLang
{
    /** Default Bangla label per field / column / filter name. */
    public const FIELDS = [
        'active' => 'চালু',
        'address' => 'ঠিকানা',
        'advance_amount' => 'অগ্রিম',
        'alt' => 'ছবির বিবরণ',
        'approved' => 'অনুমোদিত',
        'blocks' => 'ব্লক',
        'brand.name' => 'ব্র্যান্ড',
        'home_sections' => 'হোমপেজের সেকশন',
        'packages' => 'প্যাকেজ',
        'uploads' => 'ছবি আপলোড',
        'brand_id' => 'ব্র্যান্ড',
        'category' => 'ক্যাটাগরি',
        'category.name' => 'ক্যাটাগরি',
        'category_id' => 'ক্যাটাগরি',
        'code' => 'কোড',
        'comment' => 'মন্তব্য',
        'consignment_id' => 'কনসাইনমেন্ট আইডি',
        'content' => 'লেখা',
        'cost_price' => 'ক্রয়মূল্য',
        'courier_status' => 'কুরিয়ারের অবস্থা',
        'cover' => 'কভার',
        'created_at' => 'তৈরির সময়',
        'customer_name' => 'গ্রাহকের নাম',
        'description' => 'বিবরণ',
        'discount' => 'ছাড়',
        'discount_type' => 'ছাড়ের ধরন',
        'discount_value' => 'ছাড়ের পরিমাণ',
        'district' => 'জেলা',
        'email' => 'ইমেইল',
        'end_time' => 'শেষ হবে',
        'flash_price' => 'ফ্ল্যাশ দাম',
        'headline' => 'শিরোনাম',
        'image' => 'ছবি',
        'invoice_no' => 'ইনভয়েস',
        'ip' => 'আইপি',
        'ip_address' => 'আইপি ঠিকানা',
        'is_active' => 'চালু',
        'is_flagged' => 'চিহ্নিত',
        'key' => 'কী',
        'label' => 'নাম',
        'line_total' => 'লাইন মোট',
        'link' => 'লিংক',
        'logo' => 'লোগো',
        'message' => 'বার্তা',
        'min_spend' => 'ন্যূনতম কেনাকাটা',
        'name' => 'নাম',
        'notes' => 'নোট',
        'orders_count' => 'অর্ডার',
        'password' => 'পাসওয়ার্ড',
        'payment_method' => 'পেমেন্ট পদ্ধতি',
        'payment_status' => 'পেমেন্ট স্ট্যাটাস',
        'phone' => 'ফোন',
        'position' => 'অবস্থান',
        'postcode' => 'পোস্টকোড',
        'price' => 'দাম',
        'product.name' => 'পণ্য',
        'product_id' => 'পণ্য',
        'product_name' => 'পণ্যের নাম',
        'products_count' => 'পণ্য',
        'published_at' => 'প্রকাশের সময়',
        'quantity' => 'পরিমাণ',
        'rating' => 'রেটিং',
        'reason' => 'কারণ',
        'role' => 'ভূমিকা',
        'sale_price' => 'ছাড়ের দাম',
        'shipping_fee' => 'ডেলিভারি চার্জ',
        'slug' => 'লিংকের অংশ (স্লাগ)',
        'sort_order' => 'ক্রম',
        'status' => 'স্ট্যাটাস',
        'stock' => 'স্টক',
        'subject' => 'বিষয়',
        'subtotal' => 'সাবটোটাল',
        'tags' => 'ট্যাগ',
        'template' => 'টেমপ্লেট',
        'text' => 'লেখা',
        'thana' => 'থানা',
        'title' => 'শিরোনাম',
        'total' => 'মোট',
        'tracking_code' => 'ট্র্যাকিং কোড',
        'unit_price' => 'একক দাম',
        'updated_at' => 'হালনাগাদ',
        'upload' => 'আপলোড',
        'url' => 'লিংক',
        'username' => 'ইউজারনেম',
        'valid_until' => 'মেয়াদ',
        'views' => 'ভিজিট',
    ];

    /** Give every field, column, filter and entry without its own label a Bangla one. */
    public static function register(): void
    {
        $label = function ($component): void {
            $name = method_exists($component, 'getName') ? (string) $component->getName() : '';

            if (isset(self::FIELDS[$name])) {
                $component->label(self::FIELDS[$name]);
            }
        };

        foreach ([Field::class, Column::class, BaseFilter::class, Entry::class] as $class) {
            if (class_exists($class)) {
                $class::configureUsing($label);
            }
        }
    }
}
