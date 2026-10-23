<?php

namespace App\Filament\Resources\Categories\RelationManagers;

use App\Filament\Resources\Fields\FieldResource;
use App\Models\Field;
use Filament\Actions\AttachAction;
use Filament\Actions\DetachAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** Extra fields of a category (category_field). Subcategories inherit them. The catalog cache is flushed by AuditHooks. */
class FieldsRelationManager extends RelationManager
{
    protected static string $relationship = 'fields';

    protected static ?string $title = 'Dodatna polja';

    protected static ?string $modelLabel = 'polje';

    protected static ?string $pluralModelLabel = 'polja';

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
            ->recordTitleAttribute('label')
            ->description('Potkategorije nasleđuju polja. Potkategorija može ponovo da veže polje da bi promenila „Obavezno”.')
            ->columns([
                TextColumn::make('label')->label('Polje')->url(fn (Field $record) => FieldResource::getUrl('edit', ['record' => $record])),
                TextColumn::make('key')->label('Ključ'),
                TextColumn::make('type')->label('Vrsta')->formatStateUsing(fn ($state) => $state->label()),
                IconColumn::make('required')->label('Obavezno')->boolean(),
                TextColumn::make('sort')->label('Redosled'),
            ])
            ->headerActions([
                AttachAction::make()
                    ->label('Dodaj polje')
                    ->preloadRecordSelect()
                    ->schema(fn (AttachAction $action) => [
                        $action->getRecordSelect()->label('Polje'),
                        Toggle::make('required')->label('Obavezno'),
                        TextInput::make('sort')->label('Redosled')->integer()->minValue(0)->default(0),
                    ]),
            ])
            ->recordActions([
                EditAction::make()->label('Izmeni'),
                DetachAction::make()->label('Ukloni'),
            ]);
    }
}
