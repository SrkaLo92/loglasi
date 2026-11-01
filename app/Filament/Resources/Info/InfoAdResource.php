<?php

namespace App\Filament\Resources\Info;

use App\Filament\Resources\Info\Pages\ManageInfoAds;
use App\Filament\Support\StaffAccess;
use App\Models\InfoAd;
use App\Models\InfoBusiness;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
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

/** Paid ad slots on the info portal. One running ad per slot (and category) is shown. */
class InfoAdResource extends Resource
{
    use StaffAccess;

    protected static ?string $model = InfoAd::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static string|UnitEnum|null $navigationGroup = 'Info';

    protected static ?int $navigationSort = 6;

    protected static ?string $modelLabel = 'reklama';

    protected static ?string $pluralModelLabel = 'reklame';

    protected static ?string $navigationLabel = 'Reklame';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('slot')->label('Mesto prikaza')->options(InfoAd::SLOTS)->required()->live(),
            Select::make('info_business_id')->label('Firma iz imenika')
                ->options(fn () => InfoBusiness::query()->orderBy('name')->pluck('name', 'id'))->searchable()->placeholder('—'),
            TextInput::make('category')->label('Delatnost (samo za vrh kategorije)')->maxLength(80)
                ->visible(fn ($get) => $get('slot') === 'category_top')
                ->datalist(fn () => InfoBusiness::query()->whereNotNull('category')->distinct()->orderBy('category')->pluck('category')->all()),
            TextInput::make('title')->label('Naslov')->required()->maxLength(120),
            Textarea::make('text')->label('Tekst')->rows(2)->maxLength(300),
            FileUpload::make('image_path')->label('Slika')->image()->disk('public')->directory('info-reklame')->maxSize(4096),
            TextInput::make('link_url')->label('Link (prazno = profil firme)')->url()->maxLength(500),
            DatePicker::make('starts_on')->label('Od')->required()->default(now('Europe/Belgrade')->toDateString()),
            DatePicker::make('ends_on')->label('Do (prazno = bez kraja)'),
            Toggle::make('active')->label('Aktivna')->default(true),
        ])->columns(['default' => 1, 'md' => 2]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('starts_on', 'desc')
            ->columns([
                TextColumn::make('slot')->label('Mesto')->badge()->formatStateUsing(fn (string $state) => InfoAd::SLOTS[$state] ?? $state),
                TextColumn::make('title')->label('Naslov')->searchable()->wrap(),
                TextColumn::make('business.name')->label('Firma')->placeholder('—'),
                TextColumn::make('starts_on')->label('Od')->date('j. n. Y.')->sortable(),
                TextColumn::make('ends_on')->label('Do')->date('j. n. Y.')->placeholder('bez kraja'),
                IconColumn::make('active')->label('Aktivna')->boolean(),
                TextColumn::make('clicks')->label('Klikovi')->numeric()->sortable(),
            ])
            ->filters([SelectFilter::make('slot')->label('Mesto')->options(InfoAd::SLOTS)])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageInfoAds::route('/')];
    }
}
