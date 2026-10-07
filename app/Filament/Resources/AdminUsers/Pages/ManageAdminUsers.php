<?php

namespace App\Filament\Resources\AdminUsers\Pages;

use App\Filament\Resources\AdminUsers\AdminUserResource;
use Filament\Resources\Pages\ManageRecords;

class ManageAdminUsers extends ManageRecords
{
    protected static string $resource = AdminUserResource::class;
}
