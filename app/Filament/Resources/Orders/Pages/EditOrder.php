<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use App\Jobs\SendOrderToCourier;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use RuntimeException;
use Throwable;

class EditOrder extends EditRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Opens the printable/downloadable invoice (token-gated route) in
            // a new tab; admin reaches it via the order's own token.
            Action::make('invoice')
                ->label('Download Invoice')
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
                    ->label('Send to Steadfast')
                    ->requiresConfirmation()
                    ->action(fn (Order $record) => $this->sendToCourier($record, 'steadfast', 'Steadfast')),
                Action::make('pathao')
                    ->label('Send to Pathao (mock)')
                    ->requiresConfirmation()
                    ->action(fn (Order $record) => $this->sendToCourier($record, 'pathao', 'Pathao')),
                Action::make('redx')
                    ->label('Send to RedX (mock)')
                    ->requiresConfirmation()
                    ->action(fn (Order $record) => $this->sendToCourier($record, 'redx', 'RedX')),
            ])
                ->label('Send to Courier')
                ->icon(Heroicon::OutlinedTruck)
                ->button(),
        ];
    }

    /** Queue the courier call (SendOrderToCourier); the result appears on the order shortly. */
    protected function sendToCourier(Order $record, string $courierKey, string $courier): void
    {
        if ($record->consignment_id) {
            Notification::make()->title("Already sent to a courier")->body("Consignment ID: {$record->consignment_id}")->warning()->send();

            return;
        }

        SendOrderToCourier::dispatch($record->id, $courierKey);

        Notification::make()
            ->title("{$courier}-এ পাঠানো কিউতে দেওয়া হয়েছে")
            ->body('কিছুক্ষণ পর পেজ রিফ্রেশ করলে Consignment ID (অথবা ব্যর্থ হলে কারণ) দেখা যাবে।')
            ->success()
            ->send();
    }
}
