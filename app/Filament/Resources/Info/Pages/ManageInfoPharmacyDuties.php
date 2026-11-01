<?php

namespace App\Filament\Resources\Info\Pages;

use App\Filament\Resources\Info\InfoPharmacyDutyResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageInfoPharmacyDuties extends ManageRecords
{
    protected static string $resource = InfoPharmacyDutyResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
