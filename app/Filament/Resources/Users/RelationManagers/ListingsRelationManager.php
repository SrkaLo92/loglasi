<?php

namespace App\Filament\Resources\Users\RelationManagers;

use App\Enums\ListingStatus;
use App\Filament\Resources\Listings\ListingActions;
use App\Filament\Resources\Listings\ListingResource;
use App\Models\Listing;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ListingsRelationManager extends RelationManager
{
    protected static string $relationship = 'listings';

    protected static ?string $title = 'Oglasi';

    protected static ?string $modelLabel = 'oglas';

    protected static ?string $pluralModelLabel = 'oglasi';

    public function isReadOnly(): bool
    {
        return true;
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()?->isStaff() ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('category'))
            ->defaultSort('created_at', 'desc')
            ->recordUrl(fn (Listing $record) => ListingResource::getUrl('edit', ['record' => $record]))
            ->columns([
                TextColumn::make('title')->label('Naslov')->searchable()->limit(60)->description(fn (Listing $r) => $r->category?->name),
                TextColumn::make('price_eur')->label('Cena')->formatStateUsing(fn (Listing $r) => $r->priceLabel())->placeholder('Dogovor'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (ListingStatus $state) => $state->label())
                    ->color(fn (ListingStatus $state) => match ($state) {
                        ListingStatus::Pending => 'warning',
                        ListingStatus::Published => 'success',
                        ListingStatus::Rejected => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('created_at')->label('Postavljen')->date('j. n. Y.')->sortable(),
            ])
            ->recordActions([
                ListingActions::approve(),
                ListingActions::reject(),
            ]);
    }
}
