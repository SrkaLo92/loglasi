<?php

namespace App\Filament\Resources\Info\Pages;

use App\Filament\Resources\Info\InfoSourceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageInfoSources extends ManageRecords
{
    protected static string $resource = InfoSourceResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
