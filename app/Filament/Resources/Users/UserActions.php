<?php

namespace App\Filament\Resources\Users;

use App\Enums\AccountType;
use App\Enums\Role;
use App\Filament\Support\Notify;
use App\Filament\Support\Options;
use App\Models\User;
use App\Services\Admin\UserAdministration;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

/** Account actions (users table, user page, reports). Permissions come from UserPolicy. */
class UserActions
{
    private static function service(): UserAdministration
    {
        return app(UserAdministration::class);
    }

    public static function verifyPhone(): Action
    {
        return Action::make('verifyPhone')
            ->label('Potvrdi telefon')
            ->icon(Heroicon::OutlinedDevicePhoneMobile)
            ->color('success')
            ->visible(fn (User $record) => filled($record->phone) && ! $record->isPhoneVerified() && Gate::allows('verifyPhone', $record))
            ->action(fn (User $record) => Notify::run(fn () => self::service()->verifyPhone($record), 'Telefon je potvrđen.'));
    }

    public static function unverifyPhone(): Action
    {
        return Action::make('unverifyPhone')
            ->label('Skini potvrdu telefona')
            ->icon(Heroicon::OutlinedNoSymbol)
            ->color('gray')
            ->visible(fn (User $record) => $record->isPhoneVerified() && Gate::allows('verifyPhone', $record))
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
            ->modalDescription('Nalog dobija profil firme i oznaku „Firma”. Biznis paket se uključuje posebno.')
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

    public static function grantBiznis(): Action
    {
        return Action::make('grantBiznis')
            ->label('Biznis paket')
            ->icon(Heroicon::OutlinedSparkles)
            ->color('warning')
            ->visible(fn (User $record) => Gate::allows('changeAccountType', $record))
            ->fillForm(fn (User $record) => [
                'until' => ($record->firm?->biznis_until && $record->firm->biznis_until->isFuture())
                    ? $record->firm->biznis_until
                    : now()->addDays(365)->endOfDay(),
                'founding_free' => (bool) $record->firm?->founding_free,
            ])
            ->schema([
                DateTimePicker::make('until')->label('Važi do')->seconds(false)->required()->after('now'),
                Toggle::make('founding_free')
                    ->label('Besplatno (jedna od prvih firmi)')
                    ->helperText(fn () => sprintf(
                        'Zauzeto %d od %d besplatnih mesta.',
                        UserAdministration::foundingTaken(),
                        UserAdministration::foundingTotal(),
                    )),
            ])
            ->modalHeading('Uključiti Biznis paket')
            ->modalDescription('Ako nalog još nije firma, postaje firma.')
            ->modalSubmitActionLabel('Sačuvaj')
            ->action(fn (User $record, array $data) => Notify::run(
                fn () => self::service()->grantBiznis($record, Carbon::parse($data['until']), (bool) $data['founding_free']),
                'Biznis paket je uključen.',
            ));
    }

    public static function revokeBiznis(): Action
    {
        return Action::make('revokeBiznis')
            ->label('Isključi Biznis paket')
            ->icon(Heroicon::OutlinedXMark)
            ->color('gray')
            ->visible(fn (User $record) => (bool) $record->firm?->hasBiznis() && Gate::allows('changeAccountType', $record))
            ->requiresConfirmation()
            ->action(fn (User $record) => Notify::run(fn () => self::service()->revokeBiznis($record), 'Biznis paket je isključen.'));
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
            ->modalDescription('Moderator odobrava oglase, proverava telefone i rešava prijave. Administrator može sve, i podešavanja i cene.')
            ->modalSubmitActionLabel('Sačuvaj')
            ->action(fn (User $record, array $data) => Notify::run(fn () => self::service()->changeRole($record, Role::from($data['role'])), 'Uloga je promenjena.'));
    }

    /** @return list<Action> */
    public static function all(): array
    {
        return [
            self::verifyPhone(),
            self::unverifyPhone(),
            self::makeFirm(),
            self::makePrivate(),
            self::grantBiznis(),
            self::revokeBiznis(),
            self::changeRole(),
            self::block(),
            self::unblock(),
        ];
    }
}
