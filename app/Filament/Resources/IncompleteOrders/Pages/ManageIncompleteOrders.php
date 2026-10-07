<?php

namespace App\Filament\Resources\IncompleteOrders\Pages;

use App\Filament\Resources\IncompleteOrders\IncompleteOrderResource;
use Filament\Resources\Pages\ManageRecords;

class ManageIncompleteOrders extends ManageRecords
{
    protected static string $resource = IncompleteOrderResource::class;
}
