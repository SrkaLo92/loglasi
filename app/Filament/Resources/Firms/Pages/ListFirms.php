<?php

namespace App\Filament\Resources\Firms\Pages;

use App\Filament\Resources\Firms\FirmResource;
use App\Services\Admin\FirmAdministration;
use Filament\Resources\Pages\ListRecords;

class ListFirms extends ListRecords
{
    protected static string $resource = FirmResource::class;

    public function getSubheading(): ?string
    {
        return sprintf(
            'Prvih 30 firmi: slobodno još %d od %d mesta za besplatnu godinu.',
            FirmAdministration::foundingLeft(),
            FirmAdministration::foundingTotal(),
        );
    }
}
