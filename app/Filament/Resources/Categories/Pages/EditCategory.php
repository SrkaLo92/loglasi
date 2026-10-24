<?php

namespace App\Filament\Resources\Categories\Pages;

use App\Filament\Resources\Categories\CategoryResource;
use App\Filament\Support\AuditsPageWrites;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCategory extends EditRecord
{
    use AuditsPageWrites;

    protected static string $resource = CategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }

    /** Icons imported as a full URL are not files on disk: keep them unless a new file is uploaded. */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $original = (string) $this->getRecord()->getOriginal('icon_path');

        if (blank($data['icon_path'] ?? null) && preg_match('~^https?://~i', $original)) {
            $data['icon_path'] = $original;
        }

        return $data;
    }
}
