<?php

namespace App\Filament\Resources\GoneUrls;

use App\Filament\Resources\GoneUrls\Pages\ManageGoneUrls;
use App\Filament\Support\PathInput;
use App\Filament\Support\StaffAccess;
use App\Models\GoneUrl;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/** Addresses that answer 410 "permanently removed" (table `gone_urls`). */
class GoneUrlResource extends Resource
{
    use StaffAccess;

    protected static ?string $model = GoneUrl::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedNoSymbol;

    protected static string|UnitEnum|null $navigationGroup = 'SEO';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'uklonjena adresa';

    protected static ?string $pluralModelLabel = 'uklonjene adrese';

    protected static ?string $navigationLabel = 'Uklonjene adrese (410)';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            PathInput::make('path', 'Adresa')
                ->helperText('Putanja bez domena, npr. /korisnik/spam-nalog/.')
                ->unique(ignoreRecord: true),
            TextInput::make('note')->label('Napomena')->maxLength(255),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('path')->label('Adresa')->searchable()->wrap(),
                TextColumn::make('note')->label('Napomena')->placeholder('—')->wrap(),
                TextColumn::make('hits')->label('Pogodaka')->numeric(thousandsSeparator: '.')->sortable(),
                TextColumn::make('last_hit_at')->label('Poslednji pogodak')->dateTime('j. n. Y. H:i')->placeholder('—')->sortable(),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageGoneUrls::route('/')];
    }
}
