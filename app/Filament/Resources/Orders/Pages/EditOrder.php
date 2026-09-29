<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use App\Services\Courier\PathaoCourierService;
use App\Services\Courier\RedxCourierService;
use App\Services\Courier\SteadfastCourierService;
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
                    ->action(fn (Order $record) => $this->sendToCourier($record, app(SteadfastCourierService::class), 'Steadfast')),
                Action::make('pathao')
                    ->label('Send to Pathao (mock)')
                    ->requiresConfirmation()
                    ->action(fn (Order $record) => $this->sendToCourier($record, app(PathaoCourierService::class), 'Pathao')),
                Action::make('redx')
                    ->label('Send to RedX (mock)')
                    ->requiresConfirmation()
                    ->action(fn (Order $record) => $this->sendToCourier($record, app(RedxCourierService::class), 'RedX')),
            ])
                ->label('Send to Courier')
                ->icon(Heroicon::OutlinedTruck)
                ->button(),
        ];
    }

    protected function sendToCourier(Order $record, SteadfastCourierService|PathaoCourierService|RedxCourierService $service, string $courier): void
    {
        try {
            $updated = $service->dispatch($record);

            Notification::make()
                ->title("Order sent to {$courier}")
                ->body("Consignment ID: {$updated->consignment_id}")
                ->success()
                ->send();

            $this->fillForm();
        } catch (RuntimeException $e) {
            Notification::make()
                ->title("Could not send to {$courier}")
                ->body($e->getMessage())
                ->danger()
                ->send();
        } catch (Throwable $e) {
            Notification::make()
                ->title("Could not send to {$courier}")
                ->body('Unexpected error: '.$e->getMessage())
                ->danger()
                ->send();
        }
    }
}
