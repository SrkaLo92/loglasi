<?php

namespace App\Filament\Resources\Listings;

use App\Enums\AccountType;
use App\Enums\Currency;
use App\Enums\FieldType;
use App\Enums\ListingStatus;
use App\Enums\ReportStatus;
use App\Filament\Resources\Listings\Pages\EditListing;
use App\Filament\Resources\Listings\Pages\ListListings;
use App\Filament\Resources\Users\UserResource;
use App\Filament\Support\Options;
use App\Filament\Support\StaffAccess;
use App\Models\Field;
use App\Models\Listing;
use App\Models\User;
use App\Rules\PhoneNumber;
use App\Services\Catalog;
use App\Support\Phone;
use BackedEnum;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\PaginationMode;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class ListingResource extends Resource
{
    use StaffAccess;

    protected static ?string $model = Listing::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = 'Moderacija';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'oglas';

    protected static ?string $pluralModelLabel = 'oglasi';

    protected static ?string $navigationLabel = 'Oglasi';

    protected static ?string $recordTitleAttribute = 'title';

    /** LIKE on 200k titles is too slow for the global search box; the table search uses FULLTEXT. */
    protected static bool $isGloballySearchable = false;

    public static function getNavigationBadge(): ?string
    {
        $count = Listing::query()->where('status', ListingStatus::Pending)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Čeka odobrenje';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['user', 'category', 'location']);
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()->withTrashed();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Oglas')
                ->columns(2)
                ->schema([
                    TextInput::make('title')->label('Naslov')->required()->maxLength(255)->columnSpanFull(),
                    TextInput::make('slug')
                        ->label('Adresa (slug)')
                        ->helperText('Deo adrese /oglas/{slug}/. Promena menja javnu adresu oglasa.')
                        ->required()->maxLength(255)
                        ->rule('regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
                        ->unique(ignoreRecord: true),
                    Select::make('category_id')
                        ->label('Kategorija')
                        ->options(fn () => Options::categories())
                        ->searchable()->required()->live(),
                    Select::make('location_id')
                        ->label('Mesto')
                        ->options(fn () => Options::locations())
                        ->searchable(),
                    TextInput::make('contact_phone')
                        ->label('Telefon za oglas')
                        ->helperText('Prazno = telefon sa naloga.')
                        ->rule(new PhoneNumber)
                        ->formatStateUsing(fn (?string $state) => $state ? Phone::display($state) : null)
                        ->dehydrateStateUsing(fn (?string $state) => filled($state) ? Phone::normalize($state) : null),
                    Textarea::make('description')->label('Opis')->rows(10)->maxLength(10000)->columnSpanFull(),
                ]),
            Section::make('Cena')
                ->columns(2)
                ->schema([
                    TextInput::make('price')
                        ->label('Cena')
                        ->helperText('Prazno = „Dogovor”.')
                        ->numeric()->minValue(0)->maxValue(999999999999),
                    Select::make('currency')
                        ->label('Valuta')
                        ->options([Currency::EUR->value => 'EUR (€)', Currency::RSD->value => 'RSD (din)'])
                        ->required()->default(Currency::EUR->value),
                ]),
            Section::make('Dodatna polja')
                ->description('Polja zavise od kategorije.')
                ->columns(2)
                ->schema(fn (Get $get) => static::fieldComponents($get('category_id'))),
            Section::make('Status i rokovi')
                ->columns(2)
                ->schema([
                    Select::make('status')
                        ->label('Status')
                        ->options(Options::enum(ListingStatus::class))
                        ->required(),
                    TextInput::make('rejection_reason')->label('Razlog odbijanja')->maxLength(255),
                    DateTimePicker::make('published_at')->label('Objavljen')->seconds(false),
                    DateTimePicker::make('expires_at')->label('Ističe')->seconds(false),
                    DateTimePicker::make('featured_until')->label('Istaknut do')->seconds(false),
                    DateTimePicker::make('bumped_at')->label('Poslednje podizanje')->seconds(false),
                ]),
            Section::make('Mapa')
                ->columns(2)
                ->collapsed()
                ->schema([
                    TextInput::make('lat')->label('Geografska širina')->numeric()->minValue(-90)->maxValue(90),
                    TextInput::make('lng')->label('Geografska dužina')->numeric()->minValue(-180)->maxValue(180),
                ]),
        ]);
    }

    /**
     * Inputs for the category's extra fields, under "fields.{key}". Validation
     * and saving go through ListingFields (EditListing).
     *
     * @return array<int, mixed>
     */
    public static function fieldComponents(mixed $categoryId): array
    {
        if (! is_numeric($categoryId) || app(Catalog::class)->category((int) $categoryId) === null) {
            return [];
        }

        return app(Catalog::class)->fieldsFor((int) $categoryId)->map(function (Field $field) {
            $name = 'fields.'.$field->key;
            $label = $field->label.($field->unit ? ' ('.$field->unit.')' : '');
            $options = collect((array) $field->options)->mapWithKeys(fn ($o) => [(string) ($o['value'] ?? '') => (string) ($o['label'] ?? $o['value'] ?? '')])->all();
            $required = (bool) ($field->required ?? false);

            return match ($field->type) {
                FieldType::Select => Select::make($name)->label($label)->options($options)->required($required),
                FieldType::Multiselect => CheckboxList::make($name)->label($label)->options($options)->columns(2)->required($required),
                FieldType::Checkbox => Checkbox::make($name)->label($label),
                FieldType::Number => TextInput::make($name)->label($label)->numeric()->minValue(0)->required($required),
                FieldType::Year => TextInput::make($name)->label($label)->integer()->minValue(1900)->maxValue((int) date('Y') + 1)->required($required),
                default => TextInput::make($name)->label($label)->maxLength(255)->required($required),
            };
        })->values()->all();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount(['reports as open_reports_count' => fn ($q) => $q->where('status', ReportStatus::Open)]))
            ->defaultSort('created_at', 'desc')
            ->paginationMode(PaginationMode::Simple)
            ->splitSearchTerms(false)
            ->columns([
                TextColumn::make('id')->label('ID')->sortable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('title')
                    ->label('Naslov')
                    ->searchable(query: fn (Builder $query, string $search) => static::search($query, $search))
                    ->limit(60)
                    ->tooltip(fn (Listing $record) => $record->title)
                    ->description(fn (Listing $record) => $record->category?->name.($record->location ? ' · '.$record->location->name : '')),
                TextColumn::make('user.name')
                    ->label('Korisnik')
                    ->url(fn (Listing $record) => $record->user ? UserResource::getUrl('view', ['record' => $record->user]) : null)
                    ->description(fn (Listing $record) => $record->user?->account_type === AccountType::Firm ? 'Firma' : 'Privatno lice'),
                TextColumn::make('price_eur')
                    ->label('Cena')
                    ->sortable()
                    ->formatStateUsing(fn (Listing $record) => $record->priceLabel())
                    ->placeholder('Dogovor'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (ListingStatus $state) => $state->label())
                    ->color(fn (ListingStatus $state) => match ($state) {
                        ListingStatus::Pending => 'warning',
                        ListingStatus::Published => 'success',
                        ListingStatus::Rejected => 'danger',
                        default => 'gray',
                    }),
                IconColumn::make('featured')
                    ->label('Istaknut')
                    ->state(fn (Listing $record) => $record->isFeatured())
                    ->boolean()
                    ->trueIcon(Heroicon::Star)->trueColor('warning')
                    ->falseIcon(Heroicon::OutlinedMinus)->falseColor('gray')
                    ->tooltip(fn (Listing $record) => $record->featured_until?->format('j. n. Y. H:i')),
                TextColumn::make('open_reports_count')
                    ->label('Prijave')
                    ->badge()
                    ->color(fn (int $state) => $state > 0 ? 'danger' : 'gray')
                    ->sortable(),
                TextColumn::make('views')->label('Pregledi')->numeric(thousandsSeparator: '.')->sortable()->toggleable(),
                TextColumn::make('created_at')->label('Postavljen')->dateTime('j. n. Y. H:i')->sortable(),
                TextColumn::make('expires_at')->label('Ističe')->date('j. n. Y.')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->searchable()
            ->filters([
                SelectFilter::make('status')->label('Status')->options(Options::enum(ListingStatus::class)),
                SelectFilter::make('category')
                    ->label('Kategorija')
                    ->options(fn () => Options::categories())
                    ->searchable()
                    ->query(fn (Builder $query, array $data) => filled($data['value'] ?? null)
                        ? $query->whereIn('category_id', app(Catalog::class)->descendantIds((int) $data['value']))
                        : $query),
                SelectFilter::make('seller')
                    ->label('Prodavac')
                    ->options([AccountType::Private->value => 'Privatna lica', AccountType::Firm->value => 'Firme'])
                    ->query(fn (Builder $query, array $data) => filled($data['value'] ?? null)
                        ? $query->whereHas('user', fn ($q) => $q->where('account_type', $data['value']))
                        : $query),
                TernaryFilter::make('reported')
                    ->label('Prijavljen')
                    ->trueLabel('Ima otvorene prijave')
                    ->falseLabel('Bez otvorenih prijava')
                    ->queries(
                        true: fn (Builder $query) => $query->whereHas('reports', fn ($r) => $r->where('status', ReportStatus::Open)),
                        false: fn (Builder $query) => $query->whereDoesntHave('reports', fn ($r) => $r->where('status', ReportStatus::Open)),
                    ),
                TernaryFilter::make('featured')
                    ->label('Istaknut')
                    ->trueLabel('Istaknuti')
                    ->falseLabel('Nisu istaknuti')
                    ->queries(
                        true: fn (Builder $query) => $query->where('featured_until', '>', now()),
                        false: fn (Builder $query) => $query->where(fn ($w) => $w->whereNull('featured_until')->orWhere('featured_until', '<=', now())),
                    ),
                SelectFilter::make('user_id')
                    ->label('Korisnik')
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search) => User::query()
                        ->where(fn ($w) => $w->where('name', 'like', '%'.addcslashes($search, '%_\\').'%')->orWhere('email', 'like', '%'.addcslashes($search, '%_\\').'%'))
                        ->limit(20)->pluck('name', 'id')->all())
                    ->getOptionLabelUsing(fn ($value) => User::query()->whereKey($value)->value('name')),
                TrashedFilter::make()->label('Obrisani'),
            ])
            ->recordActions([
                ListingActions::approve(),
                ListingActions::reject(),
                ActionGroup::make([
                    EditAction::make(),
                    ListingActions::feature(),
                    ListingActions::unfeature(),
                    ListingActions::bump(),
                    ListingActions::expire(),
                    ListingActions::open(),
                    DeleteAction::make(),
                    RestoreAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    ListingActions::bulkApprove(),
                    ListingActions::bulkReject(),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * Title search at 200k listings: ID for a number, FULLTEXT (title, description) for words
     * of 3+ letters, title prefix for shorter input.
     */
    public static function search(Builder $query, string $search): Builder
    {
        $search = trim($search);
        if ($search === '') {
            return $query;
        }
        if (ctype_digit($search)) {
            return $query->whereKey((int) $search);
        }

        $words = array_values(array_filter(
            preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($search)) ?: [],
            fn ($w) => mb_strlen($w) >= 3,
        ));

        if ($words === [] || ! in_array($query->getConnection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            return $query->where('title', 'like', addcslashes($search, '%_\\').'%');
        }

        $boolean = implode(' ', array_map(fn ($w) => '+'.$w.'*', array_slice($words, 0, 8)));

        return $query->whereFullText(['title', 'description'], $boolean, ['mode' => 'boolean']);
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return ['Status' => $record->status?->label(), 'Korisnik' => $record->user?->name];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListListings::route('/'),
            'edit' => EditListing::route('/{record}/edit'),
        ];
    }
}
