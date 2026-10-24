<?php

namespace App\Filament\Resources\Firms;

use App\Filament\Support\Notify;
use App\Models\Firm;
use App\Models\User;
use App\Services\Admin\FirmAdministration;
use App\Support\Firms\FirmPackage;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Support\Icons\Heroicon;

/** Package actions on a firm (firms table, firm page). Admin only. */
class FirmActions
{
    private static function service(): FirmAdministration
    {
        return app(FirmAdministration::class);
    }

    private static function isAdmin(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->isAdmin();
    }

    /** @return array<string, string> */
    public static function packageOptions(): array
    {
        return collect(FirmPackage::PACKAGES)
            ->mapWithKeys(fn (string $code) => [$code => (string) config('firms.packages.'.$code.'.label', $code)])
            ->all();
    }

    public static function grantPackage(): Action
    {
        return Action::make('grantPackage')
            ->label('Dodeli paket')
            ->icon(Heroicon::OutlinedSparkles)
            ->color('warning')
            ->visible(fn () => self::isAdmin())
            ->fillForm(fn (Firm $record) => [
                'package' => in_array($record->package, FirmPackage::PACKAGES, true) ? $record->package : 'pro',
                'period' => $record->package_period ?? 'yearly',
            ])
            ->schema([
                Select::make('package')->label('Paket')->options(self::packageOptions())->required()->native(false),
                Radio::make('period')->label('Trajanje')->options(['monthly' => 'Mesec dana', 'yearly' => 'Godinu dana'])->required()->inline(),
            ])
            ->modalHeading('Dodeliti paket')
            ->modalDescription('Isti paket koji već traje produžava se od datuma isteka; drugi paket počinje odmah. Za vreme besplatne godine paket počinje kad ona istekne. Nalog postaje nalog firme.')
            ->modalSubmitActionLabel('Dodeli')
            ->action(fn (Firm $record, array $data) => Notify::run(
                fn () => self::service()->grantPackage($record, $data['package'], $data['period']),
                'Paket je dodeljen.',
            ));
    }

    public static function grantFreeYear(): Action
    {
        return Action::make('grantFreeYear')
            ->label('Prvih 30 firmi: besplatna godina')
            ->icon(Heroicon::OutlinedGift)
            ->color('success')
            ->visible(fn (Firm $record) => self::isAdmin() && ! $record->founding_free)
            ->requiresConfirmation()
            ->modalHeading('Dodeliti besplatnu godinu?')
            ->modalDescription(fn () => sprintf(
                'Firma dobija 12 meseci besplatno i zauzima jedno od mesta u akciji „Prvih 30 firmi”. Slobodno je još %d od %d.',
                FirmAdministration::foundingLeft(),
                FirmAdministration::foundingTotal(),
            ))
            ->modalSubmitActionLabel('Dodeli')
            ->action(fn (Firm $record) => Notify::run(fn () => self::service()->grantFreeYear($record), 'Besplatna godina je dodeljena.'));
    }

    public static function endPackage(): Action
    {
        return Action::make('endPackage')
            ->label('Prekini paket')
            ->icon(Heroicon::OutlinedXMark)
            ->color('gray')
            ->visible(fn (Firm $record) => self::isAdmin() && FirmPackage::isActive($record))
            ->requiresConfirmation()
            ->modalDescription('Paket ističe odmah. Mesto u akciji „Prvih 30 firmi” ostaje zauzeto.')
            ->action(fn (Firm $record) => Notify::run(fn () => self::service()->endPackage($record), 'Paket je prekinut.'));
    }

    /** @return list<Action> */
    public static function all(): array
    {
        return [self::grantPackage(), self::grantFreeYear(), self::endPackage()];
    }
}
