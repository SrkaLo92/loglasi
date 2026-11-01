<?php

namespace App\Filament\Resources\Info;

use App\Filament\Resources\Info\Pages\ManageInfoServiceNotices;
use App\Filament\Support\StaffAccess;
use App\Models\InfoServiceNotice;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/** Outages and service notices. Power outages arrive on their own (info:power-outages); water and roads are entered here. */
class InfoServiceNoticeResource extends Resource
{
    use StaffAccess;

    protected static ?string $model = InfoServiceNotice::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBolt;

    protected static string|UnitEnum|null $navigationGroup = 'Info';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'servisna informacija';

    protected static ?string $pluralModelLabel = 'servisne informacije';

    protected static ?string $navigationLabel = 'Servisne info';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('kind')->label('Vrsta')->options(InfoServiceNotice::KINDS)->default('water')->required(),
            TextInput::make('place')->label('Mesto / naselje')->maxLength(80),
            Textarea::make('area')->label('Ulice ili opis')->required()->rows(3),
            DateTimePicker::make('starts_at')->label('Od')->seconds(false)->required(),
            DateTimePicker::make('ends_at')->label('Do')->seconds(false)->after('starts_at'),
            TextInput::make('source_name')->label('Izvor')->default('JKP Vodovod Loznica')->maxLength(120),
            TextInput::make('source_url')->label('Link izvora')->url()->maxLength(700),
            Toggle::make('published')->label('Prikazano na sajtu')->default(true),
        ])->columns(['default' => 1, 'md' => 2]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('starts_at', 'desc')
            ->columns([
                TextColumn::make('kind')->label('Vrsta')->badge()->formatStateUsing(fn (string $state) => InfoServiceNotice::KINDS[$state] ?? $state),
                TextColumn::make('starts_at')->label('Od')->dateTime('j. n. Y. H:i')->sortable(),
                TextColumn::make('ends_at')->label('Do')->dateTime('H:i')->placeholder('—'),
                TextColumn::make('place')->label('Mesto')->placeholder('—')->searchable(),
                TextColumn::make('area')->label('Ulice')->limit(80)->wrap()->searchable(),
                TextColumn::make('source_name')->label('Izvor')->toggleable(),
                IconColumn::make('published')->label('Prikazano')->boolean(),
            ])
            ->filters([SelectFilter::make('kind')->label('Vrsta')->options(InfoServiceNotice::KINDS)])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageInfoServiceNotices::route('/')];
    }
}
