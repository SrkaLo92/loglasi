<?php

namespace App\Filament\Resources\Info\Pages;

use App\Filament\Resources\Info\InfoServiceNoticeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageInfoServiceNotices extends ManageRecords
{
    protected static string $resource = InfoServiceNoticeResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
