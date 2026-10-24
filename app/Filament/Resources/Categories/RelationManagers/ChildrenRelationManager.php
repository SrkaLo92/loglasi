<?php

namespace App\Filament\Resources\Categories\RelationManagers;

use App\Filament\Resources\Categories\CategoryResource;
use App\Models\Category;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** Subcategories: order by drag and drop, open to edit. */
class ChildrenRelationManager extends RelationManager
{
    protected static string $relationship = 'children';

    protected static ?string $title = 'Potkategorije';

    protected static ?string $modelLabel = 'potkategorija';

    protected static ?string $pluralModelLabel = 'potkategorije';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()?->isStaff() ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->reorderable('sort')
            ->recordUrl(fn (Category $record) => CategoryResource::getUrl('edit', ['record' => $record]))
            ->columns([
                TextColumn::make('name')->label('Naziv'),
                TextColumn::make('slug')->label('Slug'),
                IconColumn::make('is_active')->label('Aktivna')->boolean(),
            ])
            ->headerActions([
                Action::make('new')
                    ->label('Nova potkategorija')
                    ->icon(Heroicon::OutlinedPlus)
                    ->url(fn () => CategoryResource::getUrl('create', ['parent' => $this->getOwnerRecord()->getKey()])),
            ]);
    }
}
