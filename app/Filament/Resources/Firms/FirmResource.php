<?php

namespace App\Filament\Resources\Firms;

use App\Filament\Resources\Firms\Pages\EditFirm;
use App\Filament\Resources\Firms\Pages\ListFirms;
use App\Filament\Resources\Users\UserResource;
use App\Filament\Support\Options;
use App\Filament\Support\StaffAccess;
use App\Models\Firm;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/** Firm profiles. A firm is created from the user page ("Pretvori u firmu"); Biznis is granted there too. */
class FirmResource extends Resource
{
    use StaffAccess;

    protected static ?string $model = Firm::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static string|UnitEnum|null $navigationGroup = 'Moderacija';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'firma';

    protected static ?string $pluralModelLabel = 'firme';

    protected static ?string $navigationLabel = 'Firme';

    protected static ?string $recordTitleAttribute = 'name';

    public const DAYS = [
        'mon' => 'Ponedeljak',
        'tue' => 'Utorak',
        'wed' => 'Sreda',
        'thu' => 'Četvrtak',
        'fri' => 'Petak',
        'sat' => 'Subota',
        'sun' => 'Nedelja',
    ];

    public static function canCreate(): bool
    {
        return false;
    }

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
        $image = fn (string $name, string $label) => FileUpload::make($name)
            ->label($label)
            ->image()
            ->disk('public')
            ->directory(fn (?Firm $record) => 'firme/'.($record?->id ?? 'nove'))
            ->visibility('public')
            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
            ->maxSize(5120)
            ->imageEditor(false);

        return $schema->components([
            Section::make('Profil')
                ->columns(2)
                ->schema([
                    TextInput::make('name')->label('Naziv firme')->required()->maxLength(255),
                    Toggle::make('verified')
                        ->label('Proverena firma')
                        ->helperText('Oznaka „Proverena firma” na profilu.')
                        ->inline(false),
                    Textarea::make('description')->label('Opis delatnosti')->rows(6)->maxLength(5000)->columnSpanFull(),
                    $image('logo_path', 'Logo'),
                    $image('cover_path', 'Naslovna slika'),
                ]),
            Section::make('Adresa i kontakt')
                ->columns(2)
                ->schema([
                    TextInput::make('address')->label('Adresa')->maxLength(255),
                    Select::make('location_id')->label('Mesto')->options(fn () => Options::locations())->searchable(),
                    TextInput::make('lat')->label('Geografska širina')->numeric()->minValue(-90)->maxValue(90),
                    TextInput::make('lng')->label('Geografska dužina')->numeric()->minValue(-180)->maxValue(180),
                    TextInput::make('website')->label('Sajt')->url()->maxLength(255),
                    TextInput::make('facebook')->label('Facebook')->url()->maxLength(255),
                    TextInput::make('instagram')->label('Instagram')->url()->maxLength(255),
                ]),
            Section::make('Radno vreme')
                ->description('Na primer „08:00-16:00”. Prazno = ne radi.')
                ->columns(['default' => 1, 'sm' => 2, 'lg' => 4])
                ->schema(collect(self::DAYS)->map(fn ($label, $key) => TextInput::make('working_hours.'.$key)
                    ->label($label)
                    ->maxLength(32)
                    ->rule('nullable')
                    ->rule('regex:/^([01]\d|2[0-3]):[0-5]\d\s*-\s*([01]\d|2[0-4]):[0-5]\d$/'))->values()->all()),
            Section::make('Podaci firme')
                ->columns(2)
                ->schema([
                    TextInput::make('pib')->label('PIB')->maxLength(16)->rule('regex:/^\d{8,9}$/'),
                    TextInput::make('mb')->label('Matični broj')->maxLength(16)->rule('regex:/^\d{8}$/'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['user', 'location']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                ImageColumn::make('logo_path')->label('Logo')->disk('public')->circular()->imageSize(40),
                TextColumn::make('name')->label('Naziv')->searchable()->sortable()->description(fn (Firm $r) => $r->location?->name),
                TextColumn::make('user.email')
                    ->label('Nalog')
                    ->searchable()
                    ->url(fn (Firm $r) => $r->user ? UserResource::getUrl('view', ['record' => $r->user]) : null),
                TextColumn::make('biznis_until')
                    ->label('Biznis do')
                    ->date('j. n. Y.')
                    ->placeholder('Nema paket')
                    ->color(fn (Firm $r) => $r->hasBiznis() ? 'success' : 'gray')
                    ->sortable(),
                IconColumn::make('founding_free')->label('Besplatno')->boolean(),
                IconColumn::make('verified_at')->label('Proverena')->state(fn (Firm $r) => $r->verified_at !== null)->boolean(),
                TextColumn::make('created_at')->label('Dodata')->date('j. n. Y.')->sortable(),
            ])
            ->filters([
                TernaryFilter::make('biznis')
                    ->label('Biznis paket')
                    ->trueLabel('Aktivan')
                    ->falseLabel('Nema')
                    ->queries(
                        true: fn (Builder $query) => $query->where('biznis_until', '>', now()),
                        false: fn (Builder $query) => $query->where(fn ($w) => $w->whereNull('biznis_until')->orWhere('biznis_until', '<=', now())),
                    ),
                TernaryFilter::make('founding_free')->label('Besplatno (prvih 30)'),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFirms::route('/'),
            'edit' => EditFirm::route('/{record}/edit'),
        ];
    }
}
