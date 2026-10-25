<?php

namespace App\Filament\Resources\SellerRatings;

use App\Filament\Resources\SellerRatings\Pages\ListSellerRatings;
use App\Filament\Resources\Users\UserResource;
use App\Filament\Support\Notify;
use App\Filament\Support\StaffAccess;
use App\Models\SellerRating;
use App\Services\Ratings\SellerRatings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/** Seller ratings: reported ones first; staff hide a rating that breaks the rules or show it again. */
class SellerRatingResource extends Resource
{
    use StaffAccess;

    protected static bool $readOnly = true;

    protected static ?string $model = SellerRating::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedStar;

    protected static string|UnitEnum|null $navigationGroup = 'Moderacija';

    protected static ?int $navigationSort = 5;

    protected static ?string $modelLabel = 'ocena';

    protected static ?string $pluralModelLabel = 'ocene';

    protected static ?string $navigationLabel = 'Ocene prodavaca';

    public static function getNavigationBadge(): ?string
    {
        $count = SellerRating::query()->whereNotNull('reported_at')->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['seller', 'rater', 'listing']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('score')->label('Ocena')->formatStateUsing(fn (int $state) => str_repeat('★', $state).str_repeat('☆', 5 - $state))->sortable(),
                TextColumn::make('seller.name')->label('Prodavac')
                    ->url(fn (SellerRating $r) => $r->seller ? UserResource::getUrl('view', ['record' => $r->seller]) : null)->searchable(),
                TextColumn::make('rater.name')->label('Ocenio')
                    ->url(fn (SellerRating $r) => $r->rater ? UserResource::getUrl('view', ['record' => $r->rater]) : null)->searchable(),
                TextColumn::make('comment')->label('Komentar')->limit(80)->wrap()->placeholder('—'),
                TextColumn::make('reply')->label('Odgovor prodavca')->limit(60)->wrap()->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('report_reason')->label('Prijava')->limit(80)->wrap()->placeholder('—')
                    ->description(fn (SellerRating $r) => $r->reported_at?->format('j. n. Y. H:i')),
                TextColumn::make('status')->label('Status')->badge()
                    ->formatStateUsing(fn (string $state) => $state === SellerRating::VISIBLE ? 'Vidljiva' : 'Sakrivena')
                    ->color(fn (string $state) => $state === SellerRating::VISIBLE ? 'success' : 'gray'),
                TextColumn::make('created_at')->label('Data')->dateTime('j. n. Y. H:i')->sortable(),
            ])
            ->filters([
                SelectFilter::make('state')->label('Prikaz')
                    ->options(['reported' => 'Prijavljene', 'hidden' => 'Sakrivene', 'low' => 'Ocene 1 i 2'])
                    ->query(fn (Builder $query, array $data) => match ($data['value'] ?? null) {
                        'reported' => $query->whereNotNull('reported_at'),
                        'hidden' => $query->where('status', SellerRating::HIDDEN),
                        'low' => $query->where('score', '<=', 2),
                        default => $query,
                    }),
            ])
            ->recordActions([
                Action::make('hide')->label('Sakrij')->icon(Heroicon::OutlinedEyeSlash)->color('danger')
                    ->visible(fn (SellerRating $r) => $r->isVisible())
                    ->requiresConfirmation()
                    ->modalDescription('Ocena se više ne prikazuje i ne ulazi u prosek prodavca.')
                    ->action(fn (SellerRating $r) => Notify::run(fn () => app(SellerRatings::class)->setVisible($r, false, auth()->user()), 'Ocena je sakrivena.')),
                Action::make('show')->label(fn (SellerRating $r) => $r->isVisible() ? 'Prijava neosnovana' : 'Prikaži ponovo')->icon(Heroicon::OutlinedEye)->color('gray')
                    ->visible(fn (SellerRating $r) => ! $r->isVisible() || $r->reported_at !== null)
                    ->action(fn (SellerRating $r) => Notify::run(fn () => app(SellerRatings::class)->setVisible($r, true, auth()->user()), 'Ocena je vidljiva.')),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSellerRatings::route('/'),
        ];
    }
}
