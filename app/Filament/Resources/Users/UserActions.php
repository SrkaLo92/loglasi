<?php

namespace App\Filament\Resources\Users;

use App\Enums\AccountType;
use App\Enums\Role;
use App\Filament\Resources\Firms\FirmResource;
use App\Filament\Support\Notify;
use App\Filament\Support\Options;
use App\Models\User;
use App\Services\Admin\UserAdministration;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
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

    /** @return list<Action> */
    public static function all(): array
    {
        return [
            self::verifyPhone(),
            self::unverifyPhone(),
            self::makeFirm(),
            self::makePrivate(),
            self::firmPackage(),
            self::changeRole(),
            self::block(),
            self::unblock(),
        ];
    }
}
