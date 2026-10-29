<?php

namespace App\Filament\Resources\Reports;

use App\Enums\ReportStatus;
use App\Filament\Resources\Listings\ListingActions;
use App\Filament\Resources\Listings\ListingResource;
use App\Filament\Resources\Reports\Pages\ListReports;
use App\Filament\Resources\Users\UserResource;
use App\Filament\Support\Notify;
use App\Filament\Support\Options;
use App\Filament\Support\StaffAccess;
use App\Models\Listing;
use App\Models\Report;
use App\Models\User;
use App\Services\Admin\AdminStats;
use App\Services\Admin\ListingModeration;
use App\Services\Admin\ReportHandling;
use App\Services\Admin\UserAdministration;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

/** Reports of listings and users. Closing and quick actions go through the admin services. */
class ReportResource extends Resource
{
    use StaffAccess;

    protected static bool $readOnly = true;

    protected static ?string $model = Report::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFlag;

    protected static string|UnitEnum|null $navigationGroup = 'Moderacija';

    protected static ?int $navigationSort = 4;

    protected static ?string $modelLabel = 'prijava';

    protected static ?string $pluralModelLabel = 'prijave';

    protected static ?string $navigationLabel = 'Prijave';

    public static function getNavigationBadge(): ?string
    {
        $count = app(AdminStats::class)->queue()['reports'];

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with([
            'reporter',
            'reportable' => fn (MorphTo $morph) => $morph->morphWith([Listing::class => ['user']]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('target')
                    ->label('Prijavljeno')
                    ->state(fn (Report $r) => static::targetLabel($r))
                    ->description(fn (Report $r) => $r->reportable instanceof Listing ? 'Oglas' : ($r->reportable instanceof User ? 'Korisnik' : 'Obrisano'))
                    ->url(fn (Report $r) => static::targetUrl($r))
                    ->wrap(),
                TextColumn::make('reason')->label('Razlog')->badge()->formatStateUsing(fn (?string $state) => ReportHandling::reasonLabel($state)),
                TextColumn::make('details')->label('Napomena')->limit(80)->wrap()->placeholder('—'),
                TextColumn::make('reporter.name')->label('Prijavio')->placeholder('Gost'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (ReportStatus $state) => $state->label())
                    ->color(fn (ReportStatus $state) => match ($state) {
                        ReportStatus::Open => 'danger',
                        ReportStatus::Resolved => 'success',
                        ReportStatus::Dismissed => 'gray',
                    }),
                TextColumn::make('created_at')->label('Primljena')->dateTime('j. n. Y. H:i')->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->label('Status')->options(Options::enum(ReportStatus::class)),
                SelectFilter::make('reason')->label('Razlog')->options(ReportHandling::REASONS),
                SelectFilter::make('type')
                    ->label('Šta je prijavljeno')
                    ->options(['listing' => 'Oglasi', 'user' => 'Korisnici'])
                    ->query(fn (Builder $query, array $data) => match ($data['value'] ?? null) {
                        'listing' => $query->where('reportable_type', (new Listing)->getMorphClass()),
                        'user' => $query->where('reportable_type', (new User)->getMorphClass()),
                        default => $query,
                    }),
            ])
            ->recordActions([
                static::resolveAction(),
                static::dismissAction(),
                ActionGroup::make([
                    static::rejectListingAction(),
                    static::deleteListingAction(),
                    static::blockUserAction(),
                ])->label('Brza akcija')->icon(Heroicon::OutlinedBolt),
            ]);
    }

    public static function targetLabel(Report $report): string
    {
        return match (true) {
            $report->reportable instanceof Listing => $report->reportable->title,
            $report->reportable instanceof User => $report->reportable->name,
            default => '#'.$report->reportable_id,
        };
    }

    public static function targetUrl(Report $report): ?string
    {
        return match (true) {
            $report->reportable instanceof Listing => ListingResource::getUrl('edit', ['record' => $report->reportable]),
            $report->reportable instanceof User => UserResource::getUrl('view', ['record' => $report->reportable]),
            default => null,
        };
    }

    /** The account behind the report: the reported user or the listing owner. */
    public static function targetUser(Report $report): ?User
    {
        return match (true) {
            $report->reportable instanceof User => $report->reportable,
            $report->reportable instanceof Listing => $report->reportable->user,
            default => null,
        };
    }

    public static function resolveAction(): Action
    {
        return Action::make('resolve')
            ->label('Rešeno')
            ->icon(Heroicon::OutlinedCheck)
            ->color('success')
            ->visible(fn (Report $record) => $record->status === ReportStatus::Open)
            ->action(fn (Report $record) => Notify::run(fn () => app(ReportHandling::class)->resolve($record), 'Prijava je rešena.'));
    }

    public static function dismissAction(): Action
    {
        return Action::make('dismiss')
            ->label('Odbaci')
            ->icon(Heroicon::OutlinedXMark)
            ->color('gray')
            ->visible(fn (Report $record) => $record->status === ReportStatus::Open)
            ->requiresConfirmation()
            ->modalDescription('Prijava se zatvara bez akcije.')
            ->action(fn (Report $record) => Notify::run(fn () => app(ReportHandling::class)->dismiss($record), 'Prijava je odbačena.'));
    }

    public static function rejectListingAction(): Action
    {
        return Action::make('rejectListing')
            ->label('Odbij oglas')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->visible(fn (Report $record) => $record->reportable instanceof Listing && ! $record->reportable->trashed())
            ->schema(ListingActions::reasonFields())
            ->modalDescription('Oglas se skida sa sajta, vlasnik dobija razlog mejlom, a sve otvorene prijave za oglas se zatvaraju.')
            ->action(fn (Report $record, array $data) => Notify::run(function () use ($record, $data) {
                app(ListingModeration::class)->reject($record->reportable, $data['reason']);
                app(ReportHandling::class)->resolveAllFor($record->reportable, 'listing.reject');
            }, 'Oglas je odbijen, prijave su zatvorene.'));
    }

    public static function deleteListingAction(): Action
    {
        return Action::make('deleteListing')
            ->label('Obriši oglas')
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->visible(fn (Report $record) => $record->reportable instanceof Listing && ! $record->reportable->trashed())
            ->requiresConfirmation()
            ->modalDescription('Oglas se briše (adresa daje 410), a sve otvorene prijave za oglas se zatvaraju.')
            ->action(fn (Report $record) => Notify::run(function () use ($record) {
                $listing = $record->reportable;
                app(ReportHandling::class)->resolveAllFor($listing, 'listing.delete');
                app(ListingModeration::class)->delete($listing);
            }, 'Oglas je obrisan, prijave su zatvorene.'));
    }

    public static function blockUserAction(): Action
    {
        return Action::make('blockUser')
            ->label(fn (Report $record) => $record->reportable instanceof Listing ? 'Blokiraj vlasnika oglasa' : 'Blokiraj korisnika')
            ->icon(Heroicon::OutlinedLockClosed)
            ->color('danger')
            ->visible(function (Report $record) {
                $user = static::targetUser($record);

                return $user !== null && ! $user->isBlocked() && Gate::allows('block', $user);
            })
            ->requiresConfirmation()
            ->modalDescription('Nalog se blokira i odjavljuje sa svih uređaja. Prijava se zatvara kao rešena.')
            ->action(fn (Report $record) => Notify::run(function () use ($record) {
                app(UserAdministration::class)->block(static::targetUser($record));
                app(ReportHandling::class)->resolve($record, 'user.block');
            }, 'Nalog je blokiran, prijava je zatvorena.'));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReports::route('/'),
        ];
    }
}
