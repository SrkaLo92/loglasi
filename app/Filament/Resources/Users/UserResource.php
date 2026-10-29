<?php

namespace App\Filament\Resources\Users;

use App\Enums\AccountType;
use App\Enums\ListingStatus;
use App\Enums\Role;
use App\Enums\UserStatus;
use App\Filament\Resources\Firms\FirmResource;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Filament\Resources\Users\RelationManagers\ListingsRelationManager;
use App\Filament\Resources\Users\RelationManagers\OrdersRelationManager;
use App\Filament\Support\CsvExport;
use App\Filament\Support\Options;
use App\Models\User;
use App\Rules\PhoneNumber;
use App\Rules\PublicName;
use App\Services\Admin\AdminStats;
use App\Services\ListingParking;
use App\Support\Firms\FirmPackage;
use App\Support\LikelyFirm;
use App\Support\ListingLimits;
use App\Support\Phone;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/** Accounts. Authorization comes from App\Policies\UserPolicy (nalozi module). */
class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|UnitEnum|null $navigationGroup = 'Moderacija';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'korisnik';

    protected static ?string $pluralModelLabel = 'korisnici';

    protected static ?string $navigationLabel = 'Korisnici';

    protected static ?string $recordTitleAttribute = 'name';

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

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'email', 'phone'];
    }

    /** Short number = user ID, longer number = phone in any spelling (064 123 4567, +381...), else name or email. */
    protected static function applyGlobalSearchAttributeConstraints(Builder $query, string $search): void
    {
        $search = trim($search);
        $digits = (string) preg_replace('/\D+/', '', $search);

        if (preg_match('/^#?\d{1,6}$/', $search)) {
            $query->whereKey((int) $digits);
        } elseif (strlen($digits) >= 6 && preg_match('/^[\d\s+\/().-]+$/', $search)) {
            $query->where('phone', 'like', '%'.substr($digits, -8).'%');
        } else {
            $like = '%'.addcslashes($search, '%_\\').'%';
            $query->where(fn (Builder $q) => $q->where('name', 'like', $like)->orWhere('email', 'like', $like));
        }
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()
            ->withCount(['listings as active_listings_count' => fn (Builder $q) => $q->active()]);
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return array_filter([
            'Br.' => (string) $record->getKey(),
            'Mejl' => $record->email,
            'Telefon' => $record->phone ? Phone::display($record->phone, true) : null,
            'Aktivnih oglasa' => (string) $record->active_listings_count,
        ]);
    }

    public static function getGlobalSearchResultActions(Model $record): array
    {
        return [Action::make('site')->label('Profil na sajtu')->url($record->profileUrl(), shouldOpenInNewTab: true)];
    }

    public static function getNavigationBadge(): ?string
    {
        $count = app(AdminStats::class)->queue()['phones'];

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Telefoni za proveru';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Podaci')
                ->columns(2)
                ->schema([
                    TextInput::make('name')->label('Ime i prezime')->required()->maxLength(255)->rule(new PublicName),
                    TextInput::make('email')
                        ->label('E-pošta')
                        ->email()->required()->maxLength(255)
                        ->unique(ignoreRecord: true)
                        ->dehydrateStateUsing(fn (?string $state) => mb_strtolower(trim((string) $state))),
                    TextInput::make('phone')
                        ->label('Telefon')
                        ->rule(new PhoneNumber)
                        ->formatStateUsing(fn (?string $state) => $state ? Phone::display($state) : null)
                        ->dehydrateStateUsing(fn (?string $state) => filled($state) ? Phone::normalize($state) : null)
                        ->helperText('Promena broja skida potvrdu telefona.'),
                    TextInput::make('slug')
                        ->label('Adresa profila (slug)')
                        ->helperText('/korisnik/{slug}/')
                        ->required()->maxLength(255)
                        ->rule('regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
                        ->unique(ignoreRecord: true),
                    Select::make('location_id')->label('Mesto')->options(fn () => Options::locations())->searchable(),
                ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Nalog')
                ->columns(3)
                ->schema([
                    TextEntry::make('name')->label('Ime i prezime'),
                    TextEntry::make('email')->label('E-pošta')->copyable(),
                    TextEntry::make('phone')
                        ->label('Telefon')
                        ->formatStateUsing(fn (?string $state) => $state ? Phone::display($state) : null)
                        ->placeholder('Nije unet')
                        ->copyable(),
                    IconEntry::make('phone_verified_at')
                        ->label('Telefon potvrđen')
                        ->state(fn (User $record) => $record->isPhoneVerified())
                        ->boolean(),
                    TextEntry::make('account_type')
                        ->label('Vrsta naloga')
                        ->badge()
                        ->formatStateUsing(fn (AccountType $state) => $state->label())
                        ->color(fn (AccountType $state) => $state === AccountType::Firm ? 'warning' : 'gray'),
                    TextEntry::make('role')->label('Uloga')->formatStateUsing(fn (Role $state) => $state->label()),
                    TextEntry::make('status')
                        ->label('Status')
                        ->badge()
                        ->formatStateUsing(fn (UserStatus $state) => $state->label())
                        ->color(fn (UserStatus $state) => $state === UserStatus::Blocked ? 'danger' : 'success'),
                    TextEntry::make('location.name')->label('Mesto')->placeholder('—'),
                    TextEntry::make('slug')
                        ->label('Javni profil')
                        ->url(fn (User $record) => url_slash('korisnik/'.$record->slug), shouldOpenInNewTab: true)
                        ->formatStateUsing(fn (string $state) => '/korisnik/'.$state.'/'),
                    TextEntry::make('created_at')->label('Registrovan')->dateTime('j. n. Y. H:i'),
                    TextEntry::make('last_login_at')->label('Poslednja prijava')->dateTime('j. n. Y. H:i')->placeholder('—'),
                    TextEntry::make('listing_limit_info')
                        ->label('Aktivnih oglasa / limit')
                        ->state(function (User $record) {
                            $limit = ListingLimits::monthlyLimit($record);

                            $parked = app(ListingParking::class)->parkedCount($record);

                            return ListingLimits::activeCount($record).' / '.($limit ?? 'bez limita')
                                .($record->listing_unlimited ? ' (dogovor bez limita)' : ($record->listing_limit !== null ? ' (lični limit)' : ''))
                                .($parked > 0 ? ', '.$parked.' u nacrtima zbog limita' : '');
                        }),
                    TextEntry::make('email_verified_at')->label('E-pošta potvrđena')->dateTime('j. n. Y.')->placeholder('Nije potvrđena'),
                ]),
            Section::make('Firma')
                ->columns(3)
                ->visible(fn (User $record) => $record->firm !== null)
                ->schema([
                    TextEntry::make('firm.name')
                        ->label('Naziv')
                        ->url(fn (User $record) => $record->firm && auth()->user()?->isAdmin() ? FirmResource::getUrl('edit', ['record' => $record->firm]) : null),
                    TextEntry::make('firm_package')
                        ->label('Paket')
                        ->state(fn (User $record) => FirmPackage::label($record->firm))
                        ->placeholder('Nema paket'),
                    TextEntry::make('firm_package_ends_at')
                        ->label('Važi do')
                        ->state(fn (User $record) => FirmPackage::isActive($record->firm) ? $record->firm->package_ends_at : null)
                        ->dateTime('j. n. Y.')
                        ->placeholder('—'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->withCount(['listings', 'listings as active_listings_count' => fn ($q) => $q->active(), 'devices'])
                ->with('firm'))
            ->headerActions([
                CsvExport::action('korisnici', [
                    'ID' => fn (User $u) => $u->id,
                    'Ime' => fn (User $u) => $u->name,
                    'E-pošta' => fn (User $u) => $u->email,
                    'Telefon' => fn (User $u) => $u->phone,
                    'Telefon potvrđen' => fn (User $u) => $u->phone_verified_at !== null,
                    'Vrsta' => fn (User $u) => $u->account_type,
                    'Uloga' => fn (User $u) => $u->role,
                    'Status' => fn (User $u) => $u->status,
                    'Oglasa' => fn (User $u) => $u->listings_count,
                    'Aktivnih oglasa' => fn (User $u) => $u->active_listings_count,
                    'Aplikacija' => fn (User $u) => $u->devices_count > 0,
                    'Registrovan' => fn (User $u) => $u->created_at,
                    'Poslednja prijava' => fn (User $u) => $u->last_login_at,
                ]),
            ])
            // "065 541 5128" is one phone number, not three words.
            ->splitSearchTerms(false)
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')
                    ->label('Ime')
                    ->searchable()
                    ->sortable()
                    ->description(fn (User $record) => $record->email),
                TextColumn::make('email')->label('E-pošta')->searchable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('phone')
                    ->label('Telefon')
                    ->searchable(query: function (Builder $query, string $search) {
                        // "065 123..." is stored as "+38165123...": search by digits without the leading zero.
                        $digits = ltrim((string) preg_replace('/\D+/', '', $search), '0');

                        return strlen($digits) < 3 ? $query : $query->where('phone', 'like', '%'.$digits.'%');
                    })
                    ->formatStateUsing(fn (?string $state) => $state ? Phone::display($state) : null)
                    ->placeholder('—'),
                IconColumn::make('phone_verified')
                    ->label('Potvrđen')
                    ->state(fn (User $record) => filled($record->phone) ? $record->isPhoneVerified() : null)
                    ->boolean(),
                TextColumn::make('account_type')
                    ->label('Vrsta')
                    ->badge()
                    ->formatStateUsing(fn (AccountType $state) => $state->label())
                    ->color(fn (AccountType $state) => $state === AccountType::Firm ? 'warning' : 'gray')
                    // Price list of 25 Oct 2026: a private account that looks like a firm gets a warning.
                    ->description(fn (User $record) => ($why = LikelyFirm::reasons($record)) ? 'Verovatno firma: '.implode(', ', $why) : null),
                TextColumn::make('role')
                    ->label('Uloga')
                    ->formatStateUsing(fn (Role $state) => $state->label())
                    ->toggleable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (UserStatus $state) => $state->label())
                    ->color(fn (UserStatus $state) => $state === UserStatus::Blocked ? 'danger' : 'success'),
                TextColumn::make('listings_count')->label('Oglasi')->sortable()
                    ->description(fn (User $record) => $record->active_listings_count.' aktivnih'),
                IconColumn::make('has_app')
                    ->label('Aplikacija')
                    ->state(fn (User $record) => $record->devices_count > 0)
                    ->boolean()
                    ->trueIcon(Heroicon::OutlinedDevicePhoneMobile)
                    ->falseIcon(Heroicon::OutlinedMinus)
                    ->toggleable(),
                TextColumn::make('created_at')->label('Registrovan')->date('j. n. Y.')->sortable(),
                TextColumn::make('last_login_at')->label('Poslednja prijava')->since()->dateTimeTooltip('j. n. Y. H:i')->sortable()->placeholder('nikad')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('account_type')->label('Vrsta naloga')->options(Options::enum(AccountType::class)),
                Filter::make('likely_firm')
                    ->label('Verovatno firma (više od 50 oglasa, isti artikli, usluge)')
                    ->query(fn (Builder $query) => LikelyFirm::scope($query)),
                SelectFilter::make('role')->label('Uloga')->options(Options::enum(Role::class)),
                SelectFilter::make('status')->label('Status')->options(Options::enum(UserStatus::class)),
                SelectFilter::make('registered')
                    ->label('Registrovan')
                    ->options(['1' => 'danas', '7' => 'poslednjih 7 dana', '30' => 'poslednjih 30 dana'])
                    ->query(fn (Builder $query, array $data) => filled($data['value'] ?? null)
                        ? $query->where('created_at', '>=', now()->subDays((int) $data['value'] - 1)->startOfDay()) : $query),
                SelectFilter::make('inactive')
                    ->label('Nije se prijavio')
                    ->options(['30' => 'više od 30 dana', '90' => 'više od 90 dana', 'never' => 'nikad'])
                    ->query(fn (Builder $query, array $data) => match ($data['value'] ?? null) {
                        'never' => $query->whereNull('last_login_at'),
                        '30', '90' => $query->where(fn ($q) => $q->whereNull('last_login_at')->orWhere('last_login_at', '<', now()->subDays((int) $data['value']))),
                        default => $query,
                    }),
                TernaryFilter::make('has_app')
                    ->label('Aplikacija')
                    ->trueLabel('Koristi aplikaciju')
                    ->falseLabel('Ne koristi')
                    ->queries(
                        true: fn (Builder $query) => $query->whereHas('devices'),
                        false: fn (Builder $query) => $query->whereDoesntHave('devices'),
                    ),
                SelectFilter::make('limit_deal')
                    ->label('Limit oglasa')
                    ->options(['unlimited' => 'Bez limita', 'personal' => 'Lični broj', 'parked' => 'Ima oglase u nacrtima zbog limita'])
                    ->query(fn (Builder $query, array $data) => match ($data['value'] ?? null) {
                        'unlimited' => $query->where('listing_unlimited', true),
                        'personal' => $query->whereNotNull('listing_limit'),
                        'parked' => $query->whereHas('listings', fn (Builder $q) => $q->whereNotNull('parked_at')->where('status', ListingStatus::Draft)),
                        default => $query,
                    }),
                TernaryFilter::make('has_active')
                    ->label('Aktivni oglasi')
                    ->trueLabel('Ima aktivnih')
                    ->falseLabel('Nema aktivnih')
                    ->queries(
                        true: fn (Builder $query) => $query->whereHas('listings', fn ($q) => $q->active()),
                        false: fn (Builder $query) => $query->whereDoesntHave('listings', fn ($q) => $q->active()),
                    ),
                TernaryFilter::make('phone_verified')
                    ->label('Telefon')
                    ->trueLabel('Potvrđen')
                    ->falseLabel('Nije potvrđen')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('phone_verified_at'),
                        false: fn (Builder $query) => $query->whereNotNull('phone')->whereNull('phone_verified_at'),
                    ),
            ])
            ->recordActions([
                UserActions::verifyPhone(),
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                    ...array_slice(UserActions::all(), 1),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            ListingsRelationManager::class,
            OrdersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'view' => ViewUser::route('/{record}'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}
