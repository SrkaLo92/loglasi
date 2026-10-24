<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserActions;
use App\Filament\Resources\Users\UserResource;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewUser extends ViewRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        $actions = UserActions::all();

        return [
            UserActions::verifyPhone(),
            EditAction::make(),
            ActionGroup::make(array_slice($actions, 1))->label('Još')->button()->color('gray'),
        ];
    }
}
