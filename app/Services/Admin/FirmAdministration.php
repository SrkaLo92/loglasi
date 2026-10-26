<?php

namespace App\Services\Admin;

use App\Enums\AccountType;
use App\Models\AuditLog;
use App\Models\Firm;
use App\Models\User;
use App\Services\OrderFulfillment;
use App\Support\Firms\FirmPackage;
use App\Support\Pages\Pricing;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Manual package changes from the admin panel. Terms are computed by FirmPackage (firms module);
 * the free first year of the first 30 firms goes through OrderFulfillment, which guards the places.
 * Permissions are checked by the caller (admin only).
 */
class FirmAdministration
{
    public function __construct(private readonly OrderFulfillment $fulfillment) {}

    /** Starts or extends a package term, the same way a paid order does. */
    public function grantPackage(Firm $firm, string $package, string $period): Firm
    {
        if (! in_array($package, FirmPackage::PACKAGES, true) || ! in_array($period, FirmPackage::PERIODS, true)) {
            throw new DomainException('Nepoznat paket.');
        }

        DB::transaction(function () use ($firm, $package, $period) {
            $before = $this->snapshot($firm);
            FirmPackage::grant($firm, $package, $period);
            $this->ensureFirmAccount($firm);
            AuditLog::record('firm.grant_package', $firm, ['before' => $before, 'after' => $this->snapshot($firm)]);
        });

        $this->forgetCaches($firm);

        return $firm;
    }

    /** One of the free places of the first 30 firms (12 months). */
    public function grantFreeYear(Firm $firm): Firm
    {
        if ($firm->founding_free) {
            throw new DomainException('Firma je već dobila besplatnu godinu.');
        }

        $this->fulfillment->grantFoundingFreeYear($firm, auth()->user());
        $this->ensureFirmAccount($firm);
        $this->forgetCaches($firm);

        return $firm;
    }

    /** Ends the current term now (the place among the first 30 stays taken). */
    public function endPackage(Firm $firm): Firm
    {
        if (! FirmPackage::isActive($firm)) {
            throw new DomainException('Firma nema aktivan paket.');
        }

        $before = $this->snapshot($firm);
        $firm->forceFill([
            'package_ends_at' => now(),
            'free_program_ends_at' => $firm->free_program ? now() : $firm->free_program_ends_at,
        ])->save();
        AuditLog::record('firm.end_package', $firm, ['before' => $before, 'after' => $this->snapshot($firm)]);
        $this->forgetCaches($firm);

        return $firm;
    }

    public static function foundingTotal(): int
    {
        return OrderFulfillment::foundingTotal();
    }

    public static function foundingLeft(): int
    {
        return OrderFulfillment::foundingLeft();
    }

    /** @return array<string, mixed> */
    private function snapshot(Firm $firm): array
    {
        return [
            'package' => $firm->package,
            'package_period' => $firm->package_period,
            'package_ends_at' => $firm->package_ends_at?->toIso8601String(),
            'free_program' => (bool) $firm->free_program,
        ];
    }

    private function ensureFirmAccount(Firm $firm): void
    {
        $user = $firm->user;
        if ($user instanceof User && $user->account_type !== AccountType::Firm) {
            $user->forceFill(['account_type' => AccountType::Firm])->save();
        }
    }

    /** The firm directory cache is cleared by the firms module on every save. */
    private function forgetCaches(Firm $firm): void
    {
        Pricing::forgetFounding();
    }
}
