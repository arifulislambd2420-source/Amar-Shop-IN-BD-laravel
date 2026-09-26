<?php

namespace App\Filament\Resources\IpBlocks\Pages;

use App\Filament\Resources\IpBlocks\IpBlockResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageIpBlocks extends ManageRecords
{
    protected static string $resource = IpBlockResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
