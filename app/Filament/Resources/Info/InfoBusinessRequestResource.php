<?php

namespace App\Filament\Resources\Info;

use App\Filament\Resources\Info\Pages\ManageInfoBusinessRequests;
use App\Filament\Support\StaffAccess;
use App\Models\InfoBusinessRequest;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use UnitEnum;

/** Requests from firms in the directory: take over the profile, correct data or remove the entry. */
class InfoBusinessRequestResource extends Resource
{
    use StaffAccess;

    protected static ?string $model = InfoBusinessRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static string|UnitEnum|null $navigationGroup = 'Info';

    protected static ?int $navigationSort = 5;

    protected static ?string $modelLabel = 'zahtev firme';

    protected static ?string $pluralModelLabel = 'zahtevi firmi';

    protected static ?string $navigationLabel = 'Zahtevi firmi';

    public static function getNavigationBadge(): ?string
    {
        $count = InfoBusinessRequest::query()->whereNull('handled_at')->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('kind')->label('Zahtev')->badge()->formatStateUsing(fn (string $state) => InfoBusinessRequest::KINDS[$state] ?? $state)
                    ->color(fn (string $state) => $state === 'remove' ? 'danger' : 'info'),
                TextColumn::make('business.name')->label('Firma')->searchable()->url(fn (InfoBusinessRequest $r) => $r->business?->url(), true),
                TextColumn::make('name')->label('Ime')->searchable(),
                TextColumn::make('email')->label('Mejl')->copyable(),
                TextColumn::make('phone')->label('Telefon')->placeholder('—'),
                TextColumn::make('message')->label('Poruka')->limit(80)->wrap()->placeholder('—'),
                TextColumn::make('created_at')->label('Stiglo')->since()->sortable(),
                TextColumn::make('handled_at')->label('Rešeno')->dateTime('j. n. H:i')->placeholder('—'),
            ])
            ->filters([TernaryFilter::make('handled_at')->label('Rešeno')->nullable()->default(false)])
            ->recordActions([
                Action::make('handled')->label('Rešeno')->icon(Heroicon::OutlinedCheck)->color('success')
                    ->visible(fn (InfoBusinessRequest $r) => $r->handled_at === null)
                    ->action(fn (InfoBusinessRequest $r) => $r->update(['handled_at' => now()])),
                Action::make('hideBusiness')->label('Sakrij firmu')->icon(Heroicon::OutlinedEyeSlash)->color('danger')
                    ->visible(fn (InfoBusinessRequest $r) => $r->kind === 'remove' && $r->business?->status === 'listed')
                    ->requiresConfirmation()
                    ->action(function (InfoBusinessRequest $r) {
                        $r->business?->update(['status' => 'hidden']);
                        $r->update(['handled_at' => now()]);
                    }),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageInfoBusinessRequests::route('/')];
    }
}
