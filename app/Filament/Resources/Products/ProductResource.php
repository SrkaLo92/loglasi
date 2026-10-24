<?php

namespace App\Filament\Resources\Products;

use App\Filament\Resources\Products\Pages\ManageProducts;
use App\Filament\Support\StaffAccess;
use App\Models\Product;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/** Price list (products). Admin only. Codes are fixed: the site and the app know them. */
class ProductResource extends Resource
{
    use StaffAccess;

    protected static bool $adminOnly = true;

    protected static ?string $model = Product::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static string|UnitEnum|null $navigationGroup = 'Prodaja';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'usluga';

    protected static ?string $pluralModelLabel = 'cene usluga';

    protected static ?string $navigationLabel = 'Cene';

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('code')
                ->label('Šifra')
                ->helperText('Npr. bump, featured_7, business_pro_monthly. Prodaje se samo usluga koju modul plaćanja poznaje. Ne menja se posle unosa.')
                ->required()->maxLength(32)
                ->rule('regex:/^[a-z0-9_]+$/')
                ->unique(ignoreRecord: true)
                ->disabledOn('edit'),
            TextInput::make('name')->label('Naziv')->required()->maxLength(255),
            TextInput::make('price_rsd')->label('Cena (din)')->integer()->minValue(0)->maxValue(10000000)->required(),
            TextInput::make('duration_days')->label('Trajanje (dana)')->helperText('Prazno = jednokratno (podizanje).')->integer()->minValue(1)->maxValue(3650),
            TextInput::make('listing_limit')->label('Limit oglasa')->helperText('Paketi firmi: aktivnih oglasa. Dodatna mesta: koliko oglasa dodaje. Prazno = bez ograničenja.')->integer()->minValue(0)->maxValue(100000),
            TextInput::make('sort')->label('Redosled')->integer()->minValue(0)->default(0),
            Toggle::make('is_active')->label('Aktivna (prikazuje se u cenovniku i aplikaciji)')->default(true),
            Toggle::make('display_only')
                ->label('Samo prikaz (u cenovniku, bez naručivanja)')
                ->helperText('Za usluge koje se još ne prodaju preko sajta.')
                ->default(false),
            Textarea::make('description')->label('Opis')->helperText('Prazno = podrazumevani opis u cenovniku.')->rows(3)->maxLength(1000),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort')
            ->paginated(false)
            ->columns([
                TextColumn::make('name')->label('Usluga')->description(fn (Product $r) => $r->code),
                TextColumn::make('price_rsd')->label('Cena')->formatStateUsing(fn ($state) => number_format((int) $state, 0, ',', '.').' din'),
                TextColumn::make('duration_days')->label('Trajanje')->formatStateUsing(fn ($state) => $state ? $state.' dana' : 'jednokratno')->placeholder('jednokratno'),
                TextColumn::make('listing_limit')->label('Limit oglasa')->placeholder('—'),
                IconColumn::make('is_active')->label('Aktivna')->boolean(),
                IconColumn::make('display_only')->label('Samo prikaz')->boolean(),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageProducts::route('/')];
    }
}
