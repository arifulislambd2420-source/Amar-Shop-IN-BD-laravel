<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditOrder extends EditRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Invoice print — simple stub for now; the printable invoice view
            // is wired up in a later phase.
            Action::make('invoice')
                ->label('Print Invoice')
                ->icon(Heroicon::OutlinedPrinter)
                ->color('gray')
                ->action(fn () => Notification::make()
                    ->title('Invoice printing is wired up in a later phase.')
                    ->info()
                    ->send()),

            // Courier dispatch — simple stubs for now; the live courier APIs
            // (Steadfast / Pathao / RedX) are integrated in a later phase.
            ActionGroup::make([
                Action::make('steadfast')
                    ->label('Send to Steadfast')
                    ->action(fn (Order $record) => $this->courierStub('Steadfast')),
                Action::make('pathao')
                    ->label('Send to Pathao')
                    ->action(fn (Order $record) => $this->courierStub('Pathao')),
                Action::make('redx')
                    ->label('Send to RedX')
                    ->action(fn (Order $record) => $this->courierStub('RedX')),
            ])
                ->label('Send to Courier')
                ->icon(Heroicon::OutlinedTruck)
                ->button(),
        ];
    }

    protected function courierStub(string $courier): void
    {
        Notification::make()
            ->title("{$courier} dispatch is integrated in a later phase.")
            ->info()
            ->send();
    }
}
