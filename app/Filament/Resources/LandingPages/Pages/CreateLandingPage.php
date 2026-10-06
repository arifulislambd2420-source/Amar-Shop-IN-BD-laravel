<?php

namespace App\Filament\Resources\LandingPages\Pages;

use App\Filament\Resources\LandingPages\LandingPageResource;
use Filament\Resources\Pages\CreateRecord;

class CreateLandingPage extends CreateRecord
{
    protected static string $resource = LandingPageResource::class;

    /**
     * Block-builder pages have no fixed headline field, but the column is
     * NOT NULL (and the browser <title> uses it), so default it to the title.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['headline'] = filled($data['headline'] ?? null) ? $data['headline'] : $data['title'];

        return $data;
    }
}