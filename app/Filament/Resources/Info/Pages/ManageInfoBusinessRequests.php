<?php

namespace App\Filament\Resources\Info\Pages;

use App\Filament\Resources\Info\InfoBusinessRequestResource;
use Filament\Resources\Pages\ManageRecords;

class ManageInfoBusinessRequests extends ManageRecords
{
    protected static string $resource = InfoBusinessRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
