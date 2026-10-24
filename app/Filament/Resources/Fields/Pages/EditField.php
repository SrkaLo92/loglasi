<?php

namespace App\Filament\Resources\Fields\Pages;

use App\Filament\Resources\Fields\FieldResource;
use App\Filament\Support\AuditsPageWrites;
use Filament\Resources\Pages\EditRecord;

class EditField extends EditRecord
{
    use AuditsPageWrites;

    protected static string $resource = FieldResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $options = (array) ($data['options'] ?? []);
        if (count($options) > FieldResource::TEXT_OPTIONS_FROM) {
            $data['options_text'] = FieldResource::optionsToText($options);
            $data['options'] = [];
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (array_key_exists('options_text', $data)) {
            if ($data['options_text'] !== null) {
                $data['options'] = FieldResource::textToOptions((string) $data['options_text'], filled($data['depends_on'] ?? null));
            }
            unset($data['options_text']);
        }
        if (blank($data['depends_on'] ?? null)) {
            $data['depends_on'] = null;
        }

        return $data;
    }
}
