<?php

namespace App\Filament\Resources\Locations;

use App\Filament\Resources\Locations\Pages\ManageLocations;
use App\Filament\Support\SlugInput;
use App\Filament\Support\StaffAccess;
use App\Models\Location;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class LocationResource extends Resource
{
    use StaffAccess;

    protected static ?string $model = Location::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;

    protected static string|UnitEnum|null $navigationGroup = 'Katalog';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'mesto';

    protected static ?string $pluralModelLabel = 'mesta';

    protected static ?string $navigationLabel = 'Mesta';

    protected static ?string $recordTitleAttribute = 'name';

    public static function canDelete(Model $record): bool
    {
        return static::writable() && ! $record->listings()->withTrashed()->exists();
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Naziv')->required()->maxLength(255)->live(onBlur: true)->afterStateUpdated(SlugInput::fillFrom()),
            SlugInput::make('Javna adresa /oglasi/{kategorija}/{slug}/.'),
            TextInput::make('sort')->label('Redosled')->integer()->minValue(0)->default(0),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount('listings'))
            ->defaultSort('sort')
            ->reorderable('sort')
            ->columns([
                TextColumn::make('name')->label('Naziv')->searchable(),
                TextColumn::make('slug')->label('Slug')->searchable(),
                TextColumn::make('listings_count')->label('Oglasi'),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageLocations::route('/')];
    }
}
