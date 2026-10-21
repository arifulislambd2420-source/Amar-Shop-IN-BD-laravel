<?php

namespace App\Filament\Resources\IpBlocks\Pages;

use App\Filament\Resources\IpBlocks\IpBlockResource;
use App\Models\SiteSetting;
use App\Support\AutoBlock;
use App\Support\SiteSettingsHelper;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Icons\Heroicon;

class ManageIpBlocks extends ManageRecords
{
    protected static string $resource = IpBlockResource::class;

    /** A proxy is in front of the shop but not trusted: every visitor looks like one IP, so blocking by IP can't work. */
    public function getSubheading(): ?string
    {
        return AutoBlock::behindUntrustedProxy(request())
            ? __('⚠ সাইটের সামনে একটি প্রক্সি আছে, কিন্তু অ্যাপ সেটি বিশ্বাস করছে না — তাই সব ভিজিটরের আইপি একই দেখাচ্ছে এবং আইপি ব্লক/স্বয়ংক্রিয় ব্লক ঠিকমতো কাজ করবে না (নিরাপত্তার জন্য স্বয়ংক্রিয় ব্লক এই অবস্থায় কাউকে ব্লক করে না)। সার্ভারের .env-এ TRUSTED_PROXIES ঠিক করুন (DEPLOY.md দেখুন), তারপর php artisan config:cache চালান।')
            : null;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('autoBlockSettings')
                ->label(__('স্বয়ংক্রিয় ব্লকের সীমা'))
                ->icon(Heroicon::OutlinedShieldExclamation)
                ->color('gray')
                ->modalHeading(__('স্বয়ংক্রিয় ব্লক'))
                ->modalDescription(__('কোনো আইপি অল্প সময়ে অস্বাভাবিক বেশি রিকোয়েস্ট পাঠালে নিজে থেকে কিছু সময়ের জন্য ব্লক হবে। ব্লক হওয়া আইপি নিচের তালিকায় "স্বয়ংক্রিয়" হিসেবে দেখায়, চাইলে আগেই মুছে দিতে পারেন। সাইন-ইন করা অ্যাডমিন (ও গত ৩০ দিনে অ্যাডমিন প্যানেলে ঢোকা আইপি) কখনো ব্লক হয় না।'))
                ->modalSubmitActionLabel(__('সেভ করুন'))
                ->fillForm(fn (): array => [
                    'autoblock_enabled' => AutoBlock::enabled(),
                    'autoblock_max_requests' => AutoBlock::maxRequests(),
                    'autoblock_window_seconds' => AutoBlock::windowSeconds(),
                    'autoblock_block_minutes' => AutoBlock::blockMinutes(),
                ])
                ->schema([
                    Toggle::make('autoblock_enabled')->label(__('স্বয়ংক্রিয় ব্লক চালু')),
                    TextInput::make('autoblock_max_requests')
                        ->label(__('সর্বোচ্চ রিকোয়েস্ট'))
                        ->helperText(__('এর বেশি হলে ব্লক। একজন সাধারণ ভিজিটর মিনিটে এর চেয়ে অনেক কম পাঠায়।'))
                        ->numeric()->minValue(10)->maxValue(100000)->required(),
                    TextInput::make('autoblock_window_seconds')
                        ->label(__('কত সেকেন্ডের মধ্যে'))
                        ->numeric()->minValue(5)->maxValue(3600)->required(),
                    TextInput::make('autoblock_block_minutes')
                        ->label(__('কত মিনিট ব্লক থাকবে'))
                        ->numeric()->minValue(1)->maxValue(43200)->required(),
                ])
                ->action(function (array $data): void {
                    foreach (['autoblock_enabled', 'autoblock_max_requests', 'autoblock_window_seconds', 'autoblock_block_minutes'] as $key) {
                        SiteSetting::updateOrCreate(
                            ['setting_key' => $key],
                            ['setting_value' => (string) ($key === 'autoblock_enabled' ? (int) (bool) $data[$key] : (int) $data[$key])],
                        );
                        SiteSettingsHelper::forget($key);
                    }

                    Notification::make()->title(__('স্বয়ংক্রিয় ব্লকের সীমা সেভ হয়েছে।'))->success()->send();
                }),
            CreateAction::make(),
        ];
    }
}
