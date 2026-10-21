<?php

namespace App\Filament\Pages\Settings;

use App\Filament\Support\SettingsPage;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;

/** Site settings — Delivery & Orders: delivery charges, free-delivery minimum, order cooldown and the low-stock limit. */
class DeliverySettings extends SettingsPage
{
    protected static ?string $slug = 'site-delivery';

    protected static ?int $navigationSort = 6;

    protected static function label(): string
    {
        return 'ডেলিভারি ও অর্ডার';
    }

    protected function keys(): array
    {
        return ['delivery_fee_dhaka', 'delivery_fee_outside', 'delivery_free_min', 'delivery_cost_per_order', 'order_cooldown_minutes', 'low_stock_threshold'];
    }

    protected function sections(): array
    {
        return [
            Section::make(__('ডেলিভারি'))
                ->description(__('চেকআউটে জেলা অনুযায়ী ডেলিভারি চার্জ বসে। চার্জ খালি রাখলে ডিফল্ট (:dhaka / :outside টাকা) প্রযোজ্য।', ['dhaka' => config('site.delivery.dhaka'), 'outside' => config('site.delivery.outside')]))
                ->columns(['default' => 1, 'md' => 3])
                ->schema([
                    TextInput::make('delivery_fee_dhaka')
                        ->label(__('ঢাকার ভেতরে চার্জ (৳)'))
                        ->numeric()->minValue(0)->maxValue(100000)
                        ->placeholder((string) config('site.delivery.dhaka')),
                    TextInput::make('delivery_fee_outside')
                        ->label(__('ঢাকার বাইরে চার্জ (৳)'))
                        ->numeric()->minValue(0)->maxValue(100000)
                        ->placeholder((string) config('site.delivery.outside')),
                    TextInput::make('delivery_free_min')
                        ->label(__('ফ্রি ডেলিভারির ন্যূনতম অর্ডার (৳)'))
                        ->numeric()->minValue(0)->maxValue(10000000)
                        ->helperText(__('খালি = ফ্রি ডেলিভারি বন্ধ। কুপন ছাড়ের আগের সাবটোটাল ধরা হয়।')),
                    TextInput::make('delivery_cost_per_order')
                        ->label(__('আমার ডেলিভারি খরচ — প্রতি অর্ডারে (৳)'))
                        ->numeric()->minValue(0)->maxValue(100000)
                        ->placeholder('0')
                        ->helperText(__('কুরিয়ারকে আপনি যা দেন (গড়)। ড্যাশবোর্ডের লাভের হিসাবে খরচ হিসেবে বাদ যায়। কাস্টমারের দেওয়া ডেলিভারি চার্জের সাথে এর কোনো সম্পর্ক নেই।')),
                ]),
            Section::make(__('অর্ডার ও স্টক'))
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    TextInput::make('order_cooldown_minutes')
                        ->label(__('একই ফোন থেকে পরপর অর্ডারের বিরতি (মিনিট)'))
                        ->numeric()->minValue(0)->maxValue(1440)
                        ->placeholder('10')
                        ->helperText(__('এই সময়ের মধ্যে একই ফোন থেকে আরেকটি অর্ডার নেওয়া হবে না। খালি = ১০ মিনিট, ০ = বন্ধ।')),
                    TextInput::make('low_stock_threshold')
                        ->label(__('কম স্টকের সীমা (পিস)'))
                        ->numeric()->minValue(0)->maxValue(100000)
                        ->placeholder('5')
                        ->helperText(__('স্টক এর সমান বা কম হলে ড্যাশবোর্ডে সতর্কতা আসবে। খালি = ৫।')),
                ]),
        ];
    }
}
