<?php

namespace App\Filament\Resources\Info\Pages;

use App\Filament\Resources\Info\InfoAdResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageInfoAds extends ManageRecords
{
    protected static string $resource = InfoAdResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Nova reklama')];
    }
}
