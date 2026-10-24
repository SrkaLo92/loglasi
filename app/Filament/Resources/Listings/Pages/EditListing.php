<?php

namespace App\Filament\Resources\Listings\Pages;

use App\Enums\FieldType;
use App\Filament\Resources\Listings\ListingActions;
use App\Filament\Resources\Listings\ListingResource;
use App\Filament\Support\AuditHooks;
use App\Models\AuditLog;
use App\Models\Listing;
use App\Services\Catalog;
use App\Services\ListingFields;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EditListing extends EditRecord
{
    protected static string $resource = ListingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ListingActions::approve(),
            ListingActions::reject(),
            ListingActions::feature(),
            ListingActions::unfeature(),
            ListingActions::bump(),
            ListingActions::expire(),
            ListingActions::open(),
            DeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    /** Extra field values come from listing_field_values. */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Listing $listing */
        $listing = $this->getRecord();
        $listing->loadMissing('fieldValues');
        $values = [];

        foreach (app(Catalog::class)->fieldsFor($listing->category_id) as $field) {
            $raw = $listing->fieldValues->where('field_id', $field->id)->pluck('value')->filter(fn ($v) => $v !== null && $v !== '')->values()->all();
            if ($raw === []) {
                continue;
            }
            $values[$field->key] = match ($field->type) {
                FieldType::Multiselect => $raw,
                FieldType::Checkbox => true,
                default => $raw[0],
            };
        }

        $data['fields'] = $values;

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Listing $record */
        $fieldsInput = (array) ($data['fields'] ?? []);
        unset($data['fields']);

        $categoryId = (int) ($data['category_id'] ?? $record->category_id);

        try {
            // Staff may save an old listing that lacks a field the category now asks for.
            $values = app(ListingFields::class)->validate($categoryId, $fieldsInput, requireAll: false);
        } catch (ValidationException $e) {
            throw ValidationException::withMessages(collect($e->errors())->mapWithKeys(fn ($m, $k) => ['data.'.$k => $m])->all());
        }

        $before = $record->getOriginal();
        $oldFields = app(ListingFields::class)->values($record->loadMissing('fieldValues'));

        DB::transaction(function () use ($record, $data, $values) {
            $record->forceFill($data)->save();
            app(ListingFields::class)->save($record, $values);
        });

        $changes = AuditHooks::diff($before, $record);
        $newFields = app(ListingFields::class)->values($record->load('fieldValues'));
        if ($oldFields !== $newFields) {
            $changes['fields'] = [
                collect($oldFields)->pluck('value', 'key')->all(),
                collect($newFields)->pluck('value', 'key')->all(),
            ];
        }
        if ($changes !== []) {
            AuditLog::record('listing.update', $record, $changes);
        }

        return $record;
    }
}
