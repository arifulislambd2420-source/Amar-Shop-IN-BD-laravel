<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use App\Jobs\SendOrderToCourier;
use App\Models\Order;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditOrder extends EditRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Opens the printable/downloadable invoice (token-gated route) in
            // a new tab; admin reaches it via the order's own token.
            Action::make('invoice')
                ->label('ইনভয়েস ডাউনলোড')
                ->icon(Heroicon::OutlinedPrinter)
                ->color('gray')
                ->url(fn (Order $record): string => route('order.invoice', $record->order_token), shouldOpenInNewTab: true),

            // Courier dispatch — Steadfast makes a real API call; Pathao and
            // RedX are mocked, exactly matching the old Next.js app. Every
            // action requires being inside the admin Filament panel (the
            // `admin` guard), and each service's own guard blocks
            // double-dispatch once a consignment_id is already set.
            ActionGroup::make([
                Action::make('steadfast')
                    ->label('স্টিডফাস্টে পাঠান')
                    ->requiresConfirmation()
                    ->action(fn (Order $record) => $this->sendToCourier($record, 'steadfast', 'Steadfast')),
                Action::make('pathao')
                    ->label('পাঠাওয়ে পাঠান (ডেমো)')
                    ->requiresConfirmation()
                    ->action(fn (Order $record) => $this->sendToCourier($record, 'pathao', 'Pathao')),
                Action::make('redx')
                    ->label('রেডএক্সে পাঠান (ডেমো)')
                    ->requiresConfirmation()
                    ->action(fn (Order $record) => $this->sendToCourier($record, 'redx', 'RedX')),
            ])
                ->label('কুরিয়ারে পাঠান')
                ->icon(Heroicon::OutlinedTruck)
                ->button(),

            // Shown only for a cancelled order (OrderResource::canDelete).
            DeleteAction::make(),
        ];
    }

    /** Queue the courier call (SendOrderToCourier); the result appears on the order shortly. */
    protected function sendToCourier(Order $record, string $courierKey, string $courier): void
    {
        if ($record->consignment_id) {
            Notification::make()->title('ইতিমধ্যে কুরিয়ারে পাঠানো হয়েছে')->body("কনসাইনমেন্ট আইডি: {$record->consignment_id}")->warning()->send();

            return;
        }

        SendOrderToCourier::dispatch($record->id, $courierKey);

        Notification::make()
            ->title("{$courier}-এ পাঠানো কিউতে দেওয়া হয়েছে")
            ->body('কিছুক্ষণ পর পেজ রিফ্রেশ করলে কনসাইনমেন্ট আইডি (অথবা ব্যর্থ হলে কারণ) দেখা যাবে।')
            ->success()
            ->send();
    }
}
