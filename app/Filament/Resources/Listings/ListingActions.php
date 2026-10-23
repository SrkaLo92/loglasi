<?php

namespace App\Filament\Resources\Listings;

use App\Enums\ListingStatus;
use App\Filament\Support\Notify;
use App\Models\Listing;
use App\Services\Admin\ListingModeration;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;

/** Moderation actions, shared by the listings table, the edit page, reports and user pages. */
class ListingActions
{
    private static function service(): ListingModeration
    {
        return app(ListingModeration::class);
    }

    public static function approve(): Action
    {
        return Action::make('approve')
            ->label('Odobri')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->visible(fn (Listing $record) => ! $record->trashed() && $record->status !== ListingStatus::Published)
            ->requiresConfirmation()
            ->modalHeading('Odobriti oglas?')
            ->modalDescription('Oglas se objavljuje, a vlasnik dobija obaveštenje mejlom.')
            ->modalSubmitActionLabel('Odobri')
            ->action(fn (Listing $record) => Notify::run(fn () => self::service()->approve($record), 'Oglas je odobren.'));
    }

    public static function reject(): Action
    {
        return Action::make('reject')
            ->label('Odbij')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->visible(fn (Listing $record) => ! $record->trashed() && $record->status !== ListingStatus::Rejected)
            ->schema([self::reasonField()])
            ->modalHeading('Odbiti oglas?')
            ->modalDescription('Razlog se prikazuje vlasniku oglasa i šalje mu se mejlom.')
            ->modalSubmitActionLabel('Odbij')
            ->action(fn (Listing $record, array $data) => Notify::run(fn () => self::service()->reject($record, $data['reason']), 'Oglas je odbijen.'));
    }

    public static function feature(): Action
    {
        return Action::make('feature')
            ->label('Istakni besplatno')
            ->icon(Heroicon::OutlinedStar)
            ->color('warning')
            ->visible(fn (Listing $record) => ! $record->trashed())
            ->schema([
                TextInput::make('days')
                    ->label('Broj dana')
                    ->helperText('Ako je oglas već istaknut, dani se dodaju na postojeći rok.')
                    ->integer()->minValue(1)->maxValue(365)->default(7)->required(),
            ])
            ->modalHeading('Istaći oglas bez plaćanja')
            ->modalSubmitActionLabel('Istakni')
            ->action(fn (Listing $record, array $data) => Notify::run(fn () => self::service()->feature($record, (int) $data['days']), 'Oglas je istaknut.'));
    }

    public static function unfeature(): Action
    {
        return Action::make('unfeature')
            ->label('Skini isticanje')
            ->icon(Heroicon::OutlinedNoSymbol)
            ->color('gray')
            ->visible(fn (Listing $record) => $record->isFeatured())
            ->requiresConfirmation()
            ->action(fn (Listing $record) => Notify::run(fn () => self::service()->unfeature($record), 'Isticanje je skinuto.'));
    }

    public static function bump(): Action
    {
        return Action::make('bump')
            ->label('Podigni na vrh')
            ->icon(Heroicon::OutlinedArrowUpCircle)
            ->color('gray')
            ->visible(fn (Listing $record) => ! $record->trashed() && $record->status === ListingStatus::Published)
            ->requiresConfirmation()
            ->modalDescription('Oglas se vraća na vrh liste, kao da je upravo postavljen.')
            ->action(fn (Listing $record) => Notify::run(fn () => self::service()->bump($record), 'Oglas je podignut na vrh.'));
    }

    public static function open(): Action
    {
        return Action::make('open')
            ->label('Otvori na sajtu')
            ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
            ->color('gray')
            ->visible(fn (Listing $record) => ! $record->trashed())
            ->url(fn (Listing $record) => $record->url(), shouldOpenInNewTab: true);
    }

    public static function bulkApprove(): BulkAction
    {
        return BulkAction::make('approve')
            ->label('Odobri izabrane')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->requiresConfirmation()
            ->deselectRecordsAfterCompletion()
            ->action(function (Collection $records) {
                $done = 0;
                foreach ($records as $listing) {
                    if (! $listing->trashed() && $listing->status !== ListingStatus::Published) {
                        self::service()->approve($listing);
                        $done++;
                    }
                }
                Notify::run(fn () => null, 'Odobreno oglasa: '.$done.'.');
            });
    }

    public static function bulkReject(): BulkAction
    {
        return BulkAction::make('reject')
            ->label('Odbij izabrane')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->schema([self::reasonField()])
            ->deselectRecordsAfterCompletion()
            ->action(function (Collection $records, array $data) {
                $done = 0;
                foreach ($records as $listing) {
                    if (! $listing->trashed() && $listing->status !== ListingStatus::Rejected) {
                        self::service()->reject($listing, $data['reason']);
                        $done++;
                    }
                }
                Notify::run(fn () => null, 'Odbijeno oglasa: '.$done.'.');
            });
    }

    public static function reasonField(): Textarea
    {
        return Textarea::make('reason')
            ->label('Razlog odbijanja')
            ->helperText('Na primer: „Fotografije nisu vaše” ili „Oglas je u pogrešnoj kategoriji”.')
            ->required()
            ->maxLength(ListingModeration::REASON_MAX)
            ->rows(3);
    }
}
