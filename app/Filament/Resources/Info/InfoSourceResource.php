<?php

namespace App\Filament\Resources\Info;

use App\Filament\Resources\Info\Pages\ManageInfoSources;
use App\Filament\Support\StaffAccess;
use App\Models\InfoSource;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/** Outlets and institutions the news routine reads. Inactive sources are skipped; "consent" records a written OK. */
class InfoSourceResource extends Resource
{
    use StaffAccess;

    protected static bool $adminOnly = true;

    protected static ?string $model = InfoSource::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRss;

    protected static string|UnitEnum|null $navigationGroup = 'Info';

    protected static ?int $navigationSort = 6;

    protected static ?string $modelLabel = 'izvor';

    protected static ?string $pluralModelLabel = 'izvori';

    protected static ?string $navigationLabel = 'Izvori vesti';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Naziv')->required()->maxLength(120),
            Select::make('kind')->label('Vrsta')->options(['portal' => 'Lokalni medij', 'institution' => 'Institucija'])->default('portal')->required(),
            TextInput::make('url')->label('Sajt')->url()->required(),
            TextInput::make('feed_url')->label('RSS (ako postoji)')->url(),
            Toggle::make('active')->label('Aktivan')->default(true),
            Toggle::make('consent')->label('Imamo pisanu saglasnost'),
            Textarea::make('notes')->label('Napomene (uslovi preuzimanja, kontakt)')->rows(3)->columnSpanFull(),
        ])->columns(['default' => 1, 'md' => 2]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->label('Naziv')->searchable()->url(fn (InfoSource $record) => $record->url, true),
                TextColumn::make('kind')->label('Vrsta')->formatStateUsing(fn (string $state) => $state === 'portal' ? 'Medij' : 'Institucija'),
                IconColumn::make('active')->label('Aktivan')->boolean(),
                IconColumn::make('consent')->label('Saglasnost')->boolean(),
                TextColumn::make('notes')->label('Napomene')->limit(60)->placeholder('—'),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageInfoSources::route('/')];
    }
}
