<?php

namespace App\Filament\Resources\GoneUrls\Pages;

use App\Filament\Resources\GoneUrls\GoneUrlResource;
use App\Filament\Support\SitemapAction;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageGoneUrls extends ManageRecords
{
    protected static string $resource = GoneUrlResource::class;

    protected function getHeaderActions(): array
    {
        return [SitemapAction::make(), CreateAction::make()];
    }
}
