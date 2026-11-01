<?php

namespace App\Filament\Resources\Info\Pages;

use App\Filament\Resources\Info\InfoBusinessResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageInfoBusinesses extends ManageRecords
{
    protected static string $resource = InfoBusinessResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
