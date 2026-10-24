<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Filament\Support\AuditsPageWrites;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    use AuditsPageWrites;

    protected static string $resource = UserResource::class;
}
