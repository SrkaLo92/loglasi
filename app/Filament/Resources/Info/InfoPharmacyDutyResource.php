<?php

namespace App\Filament\Resources\Info;

use App\Filament\Resources\Info\Pages\ManageInfoPharmacyDuties;
use App\Filament\Support\StaffAccess;
use App\Models\InfoPharmacyDuty;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/** Pharmacy on duty, one row per day, entered from the monthly schedule. */
class InfoPharmacyDutyResource extends Resource
{
    use StaffAccess;

    protected static ?string $model = InfoPharmacyDuty::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPlusCircle;

    protected static string|UnitEnum|null $navigationGroup = 'Info';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'dežurstvo apoteke';

    protected static ?string $pluralModelLabel = 'dežurne apoteke';

    protected static ?string $navigationLabel = 'Dežurne apoteke';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            DatePicker::make('date')->label('Datum')->required()->unique(ignoreRecord: true),
            TextInput::make('name')->label('Apoteka')->required()->maxLength(120),
            TextInput::make('address')->label('Adresa')->maxLength(160),
            TextInput::make('phone')->label('Telefon')->tel()->maxLength(40),
            TextInput::make('hours')->label('Radno vreme')->placeholder('0–24')->maxLength(60),
        ])->columns(['default' => 1, 'md' => 2]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('date', 'desc')
            ->columns([
                TextColumn::make('date')->label('Datum')->date('D j. n. Y.')->sortable(),
                TextColumn::make('name')->label('Apoteka')->searchable(),
                TextColumn::make('address')->label('Adresa')->placeholder('—'),
                TextColumn::make('phone')->label('Telefon')->placeholder('—'),
                TextColumn::make('hours')->label('Vreme')->placeholder('—'),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageInfoPharmacyDuties::route('/')];
    }
}
