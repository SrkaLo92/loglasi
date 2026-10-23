<?php

namespace App\Filament\Resources\Fields;

use App\Enums\FieldFilter;
use App\Enums\FieldType;
use App\Filament\Resources\Fields\Pages\CreateField;
use App\Filament\Resources\Fields\Pages\EditField;
use App\Filament\Resources\Fields\Pages\ListFields;
use App\Filament\Resources\Fields\RelationManagers\CategoriesRelationManager;
use App\Filament\Support\Options;
use App\Filament\Support\StaffAccess;
use App\Models\Field;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/** Extra listing fields (brand, mileage, area...). */
class FieldResource extends Resource
{
    use StaffAccess;

    protected static ?string $model = Field::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static string|UnitEnum|null $navigationGroup = 'Katalog';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'polje';

    protected static ?string $pluralModelLabel = 'polja';

    protected static ?string $navigationLabel = 'Dodatna polja';

    protected static ?string $recordTitleAttribute = 'label';

    public static function form(Schema $schema): Schema
    {
        $hasOptions = fn (Get $get) => in_array($get('type'), [FieldType::Select->value, FieldType::Multiselect->value, FieldType::Select, FieldType::Multiselect], true);

        return $schema->components([
            Section::make('Polje')
                ->columns(2)
                ->schema([
                    TextInput::make('label')->label('Naziv')->required()->maxLength(255),
                    TextInput::make('key')
                        ->label('Ključ')
                        ->helperText('Za API i pretragu (f[ključ]=...). Mala slova, cifre i donja crta. Ne menjajte ga posle uvoza.')
                        ->required()->maxLength(64)
                        ->rule('regex:/^[a-z][a-z0-9_]*$/')
                        ->unique(ignoreRecord: true),
                    Select::make('type')->label('Vrsta')->options(Options::enum(FieldType::class))->required()->live(),
                    Select::make('filter')
                        ->label('Filter u pretrazi')
                        ->options(Options::enum(FieldFilter::class))
                        ->default(FieldFilter::None->value)
                        ->required(),
                    TextInput::make('unit')->label('Jedinica')->helperText('Na primer km, m², cm³.')->maxLength(16),
                    TextInput::make('sort')->label('Redosled')->integer()->minValue(0)->default(0),
                ]),
            Section::make('Opcije')
                ->visible($hasOptions)
                ->schema([
                    Repeater::make('options')
                        ->label('Opcije izbora')
                        ->schema([
                            TextInput::make('value')->label('Vrednost')->required()->maxLength(64)->rule('regex:/^[a-z0-9_-]+$/')->helperText('Mala slova, bez razmaka.'),
                            TextInput::make('label')->label('Naziv')->required()->maxLength(255),
                        ])
                        ->columns(2)
                        ->reorderable()
                        ->addActionLabel('Dodaj opciju')
                        ->defaultItems(0),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount('categories'))
            ->defaultSort('sort')
            ->columns([
                TextColumn::make('label')->label('Naziv')->searchable()->sortable(),
                TextColumn::make('key')->label('Ključ')->searchable(),
                TextColumn::make('type')->label('Vrsta')->formatStateUsing(fn (FieldType $state) => $state->label()),
                TextColumn::make('filter')->label('Filter')->formatStateUsing(fn (FieldFilter $state) => $state->label()),
                TextColumn::make('unit')->label('Jedinica')->placeholder('—'),
                TextColumn::make('categories_count')->label('Kategorije'),
            ])
            ->filters([
                SelectFilter::make('type')->label('Vrsta')->options(Options::enum(FieldType::class)),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getRelations(): array
    {
        return [CategoriesRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFields::route('/'),
            'create' => CreateField::route('/create'),
            'edit' => EditField::route('/{record}/edit'),
        ];
    }
}
