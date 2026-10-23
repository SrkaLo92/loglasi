<?php

namespace App\Filament\Resources\Firms\Pages;

use App\Filament\Resources\Firms\FirmResource;
use App\Filament\Resources\Users\UserResource;
use App\Filament\Support\AuditHooks;
use App\Models\AuditLog;
use App\Models\Firm;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

class EditFirm extends EditRecord
{
    protected static string $resource = FirmResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('user')
                ->label('Nalog i Biznis paket')
                ->icon(Heroicon::OutlinedUser)
                ->color('gray')
                ->url(fn () => UserResource::getUrl('view', ['record' => $this->getRecord()->user_id])),
            Action::make('profile')
                ->label('Javni profil')
                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                ->color('gray')
                ->visible(fn () => $this->getRecord()->user !== null)
                ->url(fn () => url_slash('korisnik/'.$this->getRecord()->user->slug), shouldOpenInNewTab: true),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['verified'] = $this->getRecord()->verified_at !== null;

        // Hours imported as [{day, open, close}] are shown per day as well.
        $hours = (array) ($data['working_hours'] ?? []);
        if (array_is_list($hours)) {
            $data['working_hours'] = collect($hours)
                ->filter(fn ($row) => is_array($row) && isset($row['day'], $row['open'], $row['close']))
                ->mapWithKeys(fn ($row) => [strtolower(substr((string) $row['day'], 0, 3)) => $row['open'].'-'.$row['close']])
                ->all();
        }

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Firm $record */
        $verified = (bool) ($data['verified'] ?? false);
        unset($data['verified']);

        $data['working_hours'] = collect((array) ($data['working_hours'] ?? []))
            ->only(array_keys(FirmResource::DAYS))
            ->map(fn ($v) => trim((string) $v) === '' ? null : preg_replace('/\s*-\s*/', '-', trim((string) $v)))
            ->filter()
            ->all() ?: null;

        $before = $record->getOriginal();
        $record->fill($data);
        $record->forceFill(['verified_at' => $verified ? ($record->verified_at ?? now()) : null]);
        $record->save();

        $changes = AuditHooks::diff($before, $record);
        if ($changes !== []) {
            AuditLog::record('firm.update', $record, $changes);
        }

        return $record;
    }
}
