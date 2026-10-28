<?php

namespace App\Filament\Resources\Users;

use App\Enums\AccountType;
use App\Enums\Role;
use App\Filament\Resources\Firms\FirmResource;
use App\Filament\Support\Notify;
use App\Filament\Support\Options;
use App\Models\User;
use App\Services\Admin\UserAdministration;
use App\Services\ListingParking;
use App\Support\ListingLimits;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;

/** Account actions (users table, user page, reports). Permissions come from UserPolicy. */
class UserActions
{
    private static function service(): UserAdministration
    {
        return app(UserAdministration::class);
    }

    /** Moderators work on listings and reports only; account changes other than blocking are for admins. */
    private static function isAdmin(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->isAdmin();
    }

    public static function verifyPhone(): Action
    {
        return Action::make('verifyPhone')
            ->label('Potvrdi telefon')
            ->icon(Heroicon::OutlinedDevicePhoneMobile)
            ->color('success')
            ->visible(fn (User $record) => filled($record->phone) && ! $record->isPhoneVerified() && self::isAdmin())
            ->action(fn (User $record) => Notify::run(fn () => self::service()->verifyPhone($record), 'Telefon je potvrđen.'));
    }

    public static function unverifyPhone(): Action
    {
        return Action::make('unverifyPhone')
            ->label('Skini potvrdu telefona')
            ->icon(Heroicon::OutlinedNoSymbol)
            ->color('gray')
            ->visible(fn (User $record) => $record->isPhoneVerified() && self::isAdmin())
            ->requiresConfirmation()
            ->action(fn (User $record) => Notify::run(fn () => self::service()->unverifyPhone($record), 'Potvrda telefona je skinuta.'));
    }

    public static function block(): Action
    {
        return Action::make('block')
            ->label('Blokiraj')
            ->icon(Heroicon::OutlinedLockClosed)
            ->color('danger')
            ->visible(fn (User $record) => ! $record->isBlocked() && Gate::allows('block', $record))
            ->requiresConfirmation()
            ->modalHeading('Blokirati nalog?')
            ->modalDescription('Korisnik se odmah odjavljuje sa sajta i iz aplikacije i ne može ponovo da se prijavi.')
            ->modalSubmitActionLabel('Blokiraj')
            ->action(fn (User $record) => Notify::run(fn () => self::service()->block($record), 'Nalog je blokiran.'));
    }

    /** For good: listings, messages, favourites and personal data go; orders stay for bookkeeping. Admin only. */
    public static function deleteAccount(): Action
    {
        return Action::make('deleteAccount')
            ->label('Obriši nalog')
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->visible(fn (User $record) => self::isAdmin() && ! $record->isStaff() && Gate::allows('delete', $record))
            ->modalHeading('Trajno obrisati nalog?')
            ->modalDescription(fn (User $record) => 'Brišu se svi oglasi ('.$record->listings()->count().'), poruke, omiljeni, sačuvane pretrage, ocene, profil firme i lični podaci (ime, mejl, telefon). Narudžbine i računi ostaju zbog knjigovodstva. Ovo ne može da se vrati. Ako samo želite da ga sprečite, koristite „Blokiraj”.')
            ->schema([
                TextInput::make('reason')->label('Razlog (vidi se samo u dnevniku izmena)')->required()->maxLength(255),
                TextInput::make('confirm_email')
                    ->label(fn (User $record) => 'Za potvrdu upišite mejl korisnika: '.$record->email)
                    ->required()
                    ->rule(fn (User $record) => fn (string $attribute, $value, \Closure $fail) => mb_strtolower(trim((string) $value)) === mb_strtolower($record->email)
                        ? null : $fail('Mejl se ne poklapa.')),
            ])
            ->modalSubmitActionLabel('Obriši trajno')
            ->action(function (User $record, array $data, Action $action) {
                if (Notify::run(fn () => self::service()->deleteAccount($record, $data['reason']), 'Nalog je obrisan.')) {
                    $action->redirect(UserResource::getUrl('index'));
                }
            });
    }

    public static function unblock(): Action
    {
        return Action::make('unblock')
            ->label('Odblokiraj')
            ->icon(Heroicon::OutlinedLockOpen)
            ->color('gray')
            ->visible(fn (User $record) => $record->isBlocked() && Gate::allows('block', $record))
            ->requiresConfirmation()
            ->action(fn (User $record) => Notify::run(fn () => self::service()->unblock($record), 'Nalog je odblokiran.'));
    }

    public static function makeFirm(): Action
    {
        return Action::make('makeFirm')
            ->label('Pretvori u firmu')
            ->icon(Heroicon::OutlinedBuildingStorefront)
            ->color('primary')
            ->visible(fn (User $record) => $record->account_type !== AccountType::Firm && Gate::allows('changeAccountType', $record))
            ->schema([
                TextInput::make('firm_name')
                    ->label('Naziv firme')
                    ->default(fn (User $record) => $record->firm?->name ?? $record->name)
                    ->required()->maxLength(255),
            ])
            ->modalHeading('Napraviti nalog firme')
            ->modalDescription('Nalog dobija profil firme i oznaku „Firma”. Paket se dodeljuje na strani firme.')
            ->modalSubmitActionLabel('Pretvori u firmu')
            ->action(fn (User $record, array $data) => Notify::run(fn () => self::service()->makeFirm($record, $data['firm_name']), 'Nalog je sada nalog firme.'));
    }

    public static function makePrivate(): Action
    {
        return Action::make('makePrivate')
            ->label('Vrati na privatno lice')
            ->icon(Heroicon::OutlinedUser)
            ->color('gray')
            ->visible(fn (User $record) => $record->account_type === AccountType::Firm && Gate::allows('changeAccountType', $record))
            ->requiresConfirmation()
            ->modalDescription('Profil firme ostaje sačuvan, ali se ne prikazuje dok je nalog privatan.')
            ->action(fn (User $record) => Notify::run(fn () => self::service()->makePrivate($record), 'Nalog je vraćen na privatno lice.'));
    }

    /** Packages are managed on the firm page (FirmActions). */
    public static function firmPackage(): Action
    {
        return Action::make('firmPackage')
            ->label('Paket firme')
            ->icon(Heroicon::OutlinedSparkles)
            ->color('warning')
            ->visible(fn (User $record) => $record->firm !== null && Gate::allows('changeAccountType', $record))
            ->url(fn (User $record) => FirmResource::getUrl('edit', ['record' => $record->firm]));
    }

    public static function changeRole(): Action
    {
        return Action::make('changeRole')
            ->label('Promeni ulogu')
            ->icon(Heroicon::OutlinedShieldCheck)
            ->color('gray')
            ->visible(fn (User $record) => Gate::allows('changeRole', $record))
            ->fillForm(fn (User $record) => ['role' => $record->role?->value])
            ->schema([
                Select::make('role')->label('Uloga')->options(Options::enum(Role::class))->required(),
            ])
            ->modalDescription('Moderator odobrava oglase i rešava prijave. Administrator može sve, i podešavanja, cene i narudžbine.')
            ->modalSubmitActionLabel('Sačuvaj')
            ->action(fn (User $record, array $data) => Notify::run(fn () => self::service()->changeRole($record, Role::from($data['role'])), 'Uloga je promenjena.'));
    }

    /** Plus prodavac or extra listings for free (private accounts). Firms get packages on the firm page. */
    public static function giftPackage(): Action
    {
        return Action::make('giftPackage')
            ->label('Dodeli paket besplatno')
            ->icon(Heroicon::OutlinedGift)
            ->color('success')
            ->visible(fn (User $record) => ! $record->isFirm() && self::isAdmin())
            ->schema([
                Select::make('product')->label('Paket')->options(fn () => UserAdministration::giftOptions())->required()->native(false),
            ])
            ->modalHeading('Dodeliti paket besplatno')
            ->modalDescription(fn (User $record) => self::giftsText($record).'Paket radi isto kao kupljen: Plus prodavac se nadovezuje na onaj koji traje, a dodatni oglasi važe 30 dana od danas. Ne pravi se narudžbina ni račun.')
            ->modalSubmitActionLabel('Dodeli')
            ->action(fn (User $record, array $data) => Notify::run(fn () => self::service()->giftPackage($record, $data['product']), 'Paket je dodeljen.'));
    }

    public static function endGifts(): Action
    {
        return Action::make('endGifts')
            ->label('Prekini dodeljene pakete')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('gray')
            ->visible(function (User $record) {
                $gifts = UserAdministration::runningGifts($record);

                return self::isAdmin() && ($gifts['plus_until'] !== null || $gifts['slots'] > 0);
            })
            ->requiresConfirmation()
            ->modalDescription(fn (User $record) => self::giftsText($record).'Prekidaju se samo besplatno dodeljeni paketi. Kupljeni ostaju.')
            ->action(fn (User $record) => Notify::run(fn () => self::service()->endGifts($record), 'Dodeljeni paketi su prekinuti.'));
    }

    /**
     * Normal limit, a personal number or no limit (a private deal, never shown publicly). Active listings
     * over a lower limit go to drafts and come back with "Vrati oglase iz nacrta".
     */
    public static function listingLimit(): Action
    {
        return Action::make('listingLimit')
            ->label('Limit oglasa')
            ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
            ->color('gray')
            ->visible(fn () => self::isAdmin())
            ->fillForm(fn (User $record) => [
                'mode' => $record->listing_unlimited ? UserAdministration::LIMIT_UNLIMITED
                    : ($record->listing_limit !== null ? UserAdministration::LIMIT_NUMBER : UserAdministration::LIMIT_STANDARD),
                'limit' => $record->listing_limit,
            ])
            ->schema([
                Radio::make('mode')
                    ->label('Limit aktivnih oglasa')
                    ->options(fn (User $record) => [
                        UserAdministration::LIMIT_STANDARD => 'Kao svi ostali ('.(ListingLimits::standardLimit($record) ?? 'bez ograničenja').')',
                        UserAdministration::LIMIT_NUMBER => 'Lični broj',
                        UserAdministration::LIMIT_UNLIMITED => 'Bez limita',
                    ])
                    ->required()->live(),
                TextInput::make('limit')
                    ->label('Broj aktivnih oglasa')
                    ->integer()->minValue(1)->maxValue(10000)->live(debounce: 500)
                    ->visible(fn (Get $get) => $get('mode') === UserAdministration::LIMIT_NUMBER)
                    ->required(fn (Get $get) => $get('mode') === UserAdministration::LIMIT_NUMBER),
                Text::make(fn (User $record, Get $get) => self::limitWarning($record, (string) $get('mode'), $get('limit'))),
            ])
            ->modalDescription(fn (User $record) => 'Sada ima '.ListingLimits::activeCount($record).' aktivnih oglasa. Važi trajno, dok ne promenite. Nigde se ne prikazuje javno.')
            ->modalSubmitActionLabel('Sačuvaj')
            ->action(function (User $record, array $data) {
                $parked = 0;
                $limit = filled($data['limit'] ?? null) ? (int) $data['limit'] : null;
                Notify::run(function () use ($record, $data, $limit, &$parked) {
                    $parked = self::service()->setListingLimit($record, $data['mode'], $limit);
                }, 'Limit oglasa je sačuvan.');
                if ($parked > 0) {
                    Notification::make()->title($parked.' oglasa je prebačeno u nacrte.')->warning()->send();
                }
            });
    }

    private static function limitWarning(User $record, string $mode, mixed $limit): string
    {
        $over = UserAdministration::limitPreview($record, $mode, filled($limit) ? (int) $limit : null);

        return $over > 0
            ? 'Pažnja: '.$over.' najstarijih aktivnih oglasa preći će u nacrte. Vraćaju se dugmetom „Vrati oglase iz nacrta” kad bude mesta.'
            : 'Nijedan oglas neće preći u nacrte.';
    }

    public static function restoreParked(): Action
    {
        return Action::make('restoreParked')
            ->label('Vrati oglase iz nacrta')
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('success')
            ->visible(fn (User $record) => self::isAdmin() && app(ListingParking::class)->parkedCount($record) > 0)
            ->requiresConfirmation()
            ->modalDescription(function (User $record) {
                $limit = ListingLimits::monthlyLimit($record);
                $room = $limit === null ? 'bez limita' : max(0, $limit - ListingLimits::activeCount($record)).' slobodnih mesta';

                return 'U nacrtima čeka '.app(ListingParking::class)->parkedCount($record).' oglasa (sklonjeni zbog limita). Sada ima '.$room
                    .'. Vraća se koliko staje, bez nove provere, na pun rok trajanja.';
            })
            ->action(function (User $record) {
                $restored = self::service()->restoreParked($record);
                $restored > 0
                    ? Notification::make()->title('Vraćeno je '.$restored.' oglasa.')->success()->send()
                    : Notification::make()->title('Nema slobodnih mesta. Prvo podignite limit.')->warning()->send();
            });
    }

    private static function giftsText(User $record): string
    {
        $gifts = UserAdministration::runningGifts($record);
        $parts = array_filter([
            $gifts['plus_until'] ? 'Plus prodavac do '.$gifts['plus_until']->format('d.m.Y.') : null,
            $gifts['slots'] > 0 ? '+'.$gifts['slots'].' oglasa' : null,
        ]);

        return $parts ? 'Sada dodeljeno: '.implode(', ', $parts).'. ' : '';
    }

    /** @return list<Action> */
    public static function all(): array
    {
        return [
            self::verifyPhone(),
            self::unverifyPhone(),
            self::makeFirm(),
            self::makePrivate(),
            self::firmPackage(),
            self::giftPackage(),
            self::endGifts(),
            self::listingLimit(),
            self::restoreParked(),
            self::changeRole(),
            self::block(),
            self::unblock(),
            self::deleteAccount(),
        ];
    }
}
