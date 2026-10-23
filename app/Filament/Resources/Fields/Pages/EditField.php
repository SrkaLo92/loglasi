<?php

namespace App\Filament\Resources\Fields\Pages;

use App\Filament\Resources\Fields\FieldResource;
use App\Filament\Support\AuditsPageWrites;
use Filament\Resources\Pages\EditRecord;

class EditField extends EditRecord
{
    use AuditsPageWrites;

    protected static string $resource = FieldResource::class;
}
