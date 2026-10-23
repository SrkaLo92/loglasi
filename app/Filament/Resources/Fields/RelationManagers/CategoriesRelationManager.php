<?php

namespace App\Filament\Resources\Fields\RelationManagers;

use App\Filament\Resources\Categories\CategoryResource;
use App\Models\Category;
use Filament\Actions\AttachAction;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** Categories that use this field. The catalog cache is flushed by AuditHooks. */
class CategoriesRelationManager extends RelationManager
{
    protected static string $relationship = 'categories';

    protected static ?string $title = 'Kategorije';

    protected static ?string $modelLabel = 'kategorija';

    protected static ?string $pluralModelLabel = 'kategorije';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()?->isStaff() ?? false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Toggle::make('required')->label('Obavezno'),
            TextInput::make('sort')->label('Redosled')->integer()->minValue(0)->default(0),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')->label('Kategorija')->url(fn (Category $record) => CategoryResource::getUrl('edit', ['record' => $record])),
                IconColumn::make('required')->label('Obavezno')->boolean(),
                TextColumn::make('sort')->label('Redosled'),
            ])
            ->headerActions([
                AttachAction::make()
                    ->label('Dodaj u kategoriju')
                    ->preloadRecordSelect()
                    ->schema(fn (AttachAction $action) => [
                        $action->getRecordSelect()->label('Kategorija'),
                        Toggle::make('required')->label('Obavezno'),
                        TextInput::make('sort')->label('Redosled')->integer()->minValue(0)->default(0),
                    ]),
            ])
            ->recordActions([
                EditAction::make()->label('Izmeni'),
                DetachAction::make()->label('Ukloni'),
            ])
            ->toolbarActions([DetachBulkAction::make()->label('Ukloni izabrane')]);
    }
}
