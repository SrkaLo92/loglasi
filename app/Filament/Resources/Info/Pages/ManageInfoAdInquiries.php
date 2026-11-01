<?php

namespace App\Filament\Resources\Info\Pages;

use App\Filament\Resources\Info\InfoAdInquiryResource;
use Filament\Resources\Pages\ManageRecords;

class ManageInfoAdInquiries extends ManageRecords
{
    protected static string $resource = InfoAdInquiryResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
