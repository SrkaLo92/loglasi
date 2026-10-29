<?php

namespace App\Filament\Resources\Firms;

use App\Filament\Resources\Firms\Pages\EditFirm;
use App\Filament\Resources\Firms\Pages\ListFirms;
use App\Filament\Resources\Users\UserResource;
use App\Filament\Support\CsvExport;
use App\Filament\Support\Options;
use App\Filament\Support\StaffAccess;
use App\Models\Firm;
use App\Models\FirmMonthlyCredit;
use App\Rules\PublicName;
use App\Support\Firms\FirmCredits;
use App\Support\Firms\FirmPackage;
use App\Support\Firms\WorkingHoursInput;
use App\Support\Listing\WorkingHours;
use BackedEnum;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/** Firm profiles and packages. A firm account is made from the user page ("Pretvori u firmu"). */
class FirmResource extends Resource
{
    use StaffAccess;

    protected static bool $adminOnly = true;

    protected static ?string $model = Firm::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static string|UnitEnum|null $navigationGroup = 'Moderacija';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'firma';

    protected static ?string $pluralModelLabel = 'firme';

    protected static ?string $navigationLabel = 'Firme';

    protected static ?string $recordTitleAttribute = 'name';

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'pib', 'mb', 'user.email'];
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return array_filter(['PIB' => $record->pib, 'Paket' => $record->package ? ucfirst($record->package) : null, 'Vlasnik' => $record->user?->email]);
    }

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
            Section::make('Paket')
                ->columns(['default' => 1, 'md' => 3])
                ->schema([
                    TextEntry::make('package_label')
                        ->label('Trenutni paket')
                        ->state(fn (?Firm $record) => FirmPackage::label($record) ?? 'Nema aktivan paket'),
                    TextEntry::make('package_ends_at')
                        ->label('Važi do')
                        ->state(fn (?Firm $record) => FirmPackage::isActive($record) ? $record->package_ends_at : null)
                        ->dateTime('j. n. Y. H:i')
                        ->placeholder('—'),
                    TextEntry::make('credits')
                        ->label('Preostalo ovog meseca')
                        ->state(fn (?Firm $record) => $record ? self::creditsText($record) : null)
                        ->placeholder('—'),
                ]),
            Section::make('Profil')
                ->columns(2)
                ->schema([
                    TextInput::make('name')->label('Naziv firme')->required()->maxLength(255)->rule(new PublicName),
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
                ->description('Na primer „08–16” ili „Zatvoreno”. Prazno = ne prikazuje se.')
                ->columns(['default' => 1, 'sm' => 2, 'lg' => 4])
                ->schema(collect(WorkingHours::DAYS)->map(fn (array $names, int $day) => TextInput::make('working_hours.'.$day)
                    ->label($names[0])
                    ->maxLength(WorkingHoursInput::MAX_LENGTH))->values()->all()),
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
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['user', 'location'])
                ->withCount(['listings as active_listings_count' => fn ($q) => $q->active()]))
            ->defaultSort('created_at', 'desc')
            ->headerActions([
                CsvExport::action('firme', [
                    'ID' => fn (Firm $f) => $f->id,
                    'Naziv' => fn (Firm $f) => $f->name,
                    'Mesto' => fn (Firm $f) => $f->location?->name,
                    'E-pošta naloga' => fn (Firm $f) => $f->user?->email,
                    'Telefon' => fn (Firm $f) => $f->phone ?: $f->user?->phone,
                    'Paket' => fn (Firm $f) => FirmPackage::label($f),
                    'Važi do' => fn (Firm $f) => $f->package_ends_at,
                    'Aktivnih oglasa' => fn (Firm $f) => $f->active_listings_count,
                    'Prvih 30' => fn (Firm $f) => (bool) $f->founding_free,
                    'Proverena' => fn (Firm $f) => $f->verified_at !== null,
                    'Dodata' => fn (Firm $f) => $f->created_at,
                ], ['user', 'location']),
            ])
            ->columns([
                ImageColumn::make('logo_path')->label('Logo')->disk('public')->circular()->imageSize(40),
                TextColumn::make('name')->label('Naziv')->searchable()->sortable()->description(fn (Firm $r) => $r->location?->name),
                TextColumn::make('user.email')
                    ->label('Nalog')
                    ->searchable()
                    ->url(fn (Firm $r) => $r->user ? UserResource::getUrl('view', ['record' => $r->user]) : null),
                TextColumn::make('package')
                    ->label('Paket')
                    ->state(fn (Firm $r) => FirmPackage::label($r))
                    ->placeholder('Nema paket')
                    ->badge()
                    ->color(fn (Firm $r) => FirmPackage::isActive($r) ? 'success' : 'gray'),
                TextColumn::make('package_ends_at')
                    ->label('Važi do')
                    ->date('j. n. Y.')
                    ->description(fn (Firm $r) => $r->package_ends_at && $r->package_ends_at->isFuture()
                        ? 'još '.(int) now()->startOfDay()->diffInDays($r->package_ends_at->copy()->startOfDay()).' d.' : null)
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('active_listings_count')->label('Aktivnih oglasa')->sortable()->toggleable(),
                IconColumn::make('founding_free')->label('Prvih 30')->boolean(),
                IconColumn::make('verified_at')->label('Proverena')->state(fn (Firm $r) => $r->verified_at !== null)->boolean(),
                TextColumn::make('created_at')->label('Dodata')->date('j. n. Y.')->sortable(),
            ])
            ->filters([
                TernaryFilter::make('active_package')
                    ->label('Paket')
                    ->trueLabel('Aktivan')
                    ->falseLabel('Nema')
                    ->queries(
                        true: fn (Builder $query) => $query->where('package_ends_at', '>', now()),
                        false: fn (Builder $query) => $query->where(fn (Builder $query) => $query->whereNull('package_ends_at')->orWhere('package_ends_at', '<=', now())),
                    ),
                SelectFilter::make('package')->label('Vrsta paketa')->options(FirmActions::packageOptions()),
                TernaryFilter::make('founding_free')->label('Prvih 30 firmi'),
                SelectFilter::make('ends_within')
                    ->label('Paket ističe')
                    ->options(['7' => 'za 7 dana', '14' => 'za 14 dana', '30' => 'za 30 dana', 'expired' => 'već istekao'])
                    ->query(fn (Builder $query, array $data) => match ($data['value'] ?? null) {
                        'expired' => $query->whereNotNull('package_ends_at')->where('package_ends_at', '<=', now()),
                        '7', '14', '30' => $query->whereBetween('package_ends_at', [now(), now()->addDays((int) $data['value'])]),
                        default => $query,
                    }),
                TernaryFilter::make('verified')
                    ->label('Proverena')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('verified_at'),
                        false: fn (Builder $query) => $query->whereNull('verified_at'),
                    ),
            ])
            ->recordActions([EditAction::make(), ActionGroup::make(FirmActions::all())]);
    }

    /** "Podizanja 7 · Isticanja 2 · Premium XL 0" from FirmCredits (firms module). */
    public static function creditsText(Firm $firm): ?string
    {
        if (! FirmPackage::isActive($firm)) {
            return null;
        }
        $month = FirmPackage::monthlyCredits($firm);
        if (array_sum($month) === 0) {
            return 'Paket nema uključene promocije';
        }
        // Read only: the month row is created by the firms module on first use, not by opening this page.
        $row = FirmMonthlyCredit::query()->where('firm_id', $firm->id)->where('month', FirmCredits::month()->toDateString())->first();
        $left = [];
        foreach (FirmCredits::TYPES as $type) {
            $granted = $row ? max((int) $row->{$type.'_granted'}, $month[$type]) : $month[$type];
            $left[$type] = max(0, $granted - (int) ($row?->{$type.'_used'} ?? 0));
        }

        return sprintf(
            'Podizanja %d/%d · Isticanja %d/%d · Premium XL %d/%d',
            $left['bumps'], $month['bumps'], $left['highlights'], $month['highlights'], $left['premium_xl'], $month['premium_xl'],
        );
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFirms::route('/'),
            'edit' => EditFirm::route('/{record}/edit'),
        ];
    }
}
