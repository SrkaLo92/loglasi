<?php

namespace App\Filament\Resources\Info\Pages;

use App\Filament\Resources\Info\InfoPostResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageInfoPosts extends ManageRecords
{
    protected static string $resource = InfoPostResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Nova objava')];
    }
}
