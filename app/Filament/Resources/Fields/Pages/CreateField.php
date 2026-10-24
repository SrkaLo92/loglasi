<?php

namespace App\Filament\Resources\Fields\Pages;

use App\Filament\Resources\Fields\FieldResource;
use App\Filament\Support\AuditsPageWrites;
use Filament\Resources\Pages\CreateRecord;

class CreateField extends CreateRecord
{
    use AuditsPageWrites;

    protected static string $resource = FieldResource::class;
}
