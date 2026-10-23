<?php

namespace App\Support;

use App\Models\Listing;
use App\Models\Product;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * Koliko novih oglasa korisnik može da postavi u ovom kalendarskom mesecu.
 * Privatna lica: podešavanje private_monthly_limit (10). Firme sa paketom: products.listing_limit,
 * a paket bez upisanog limita (Biznis) je neograničen. Firma bez aktivnog paketa ima limit privatnog lica.
 */
class ListingLimits
{
    /** Šta se vraća kao limit kad je neograničeno (aplikacija očekuje ceo broj). */
    public const UNLIMITED = 999;

    /** @return array{monthly_limit:int, used_this_month:int, remaining:int, resets_at:string, unlimited:bool} */
    public static function for(User $user): array
    {
        $limit = static::monthlyLimit($user);
        $used = static::usedThisMonth($user);
        $unlimited = $limit === null;
        $monthly = $unlimited ? self::UNLIMITED : $limit;

        return [
            'monthly_limit' => $monthly,
            'used_this_month' => $used,
            'remaining' => $unlimited ? max(0, self::UNLIMITED - $used) : max(0, $monthly - $used),
            'resets_at' => static::resetsAt()->utc()->format('Y-m-d\TH:i:s\Z'),
            'unlimited' => $unlimited,
        ];
    }

    /** Da li korisnik može da postavi još jedan oglas (za modul oglasa). */
    public static function canCreate(User $user): bool
    {
        $limit = static::monthlyLimit($user);

        return $limit === null || static::usedThisMonth($user) < $limit;
    }

    /** null = bez ograničenja. */
    public static function monthlyLimit(User $user): ?int
    {
        if ($user->isFirm()) {
            $firm = $user->firm;
            if ($firm !== null && $firm->hasBiznis()) {
                $limit = Product::query()->where('code', Product::BIZNIS_12M)->value('listing_limit');

                return $limit === null ? null : (int) $limit;
            }
        }

        return (int) Settings::get('private_monthly_limit');
    }

    /** Oglasi napravljeni ovog meseca, računaju se i obrisani (brisanje ne vraća mesto). */
    public static function usedThisMonth(User $user): int
    {
        return Listing::withTrashed()
            ->where('user_id', $user->id)
            ->where('created_at', '>=', static::monthStart())
            ->count();
    }

    public static function monthStart(): CarbonImmutable
    {
        return CarbonImmutable::now(config('app.timezone'))->startOfMonth();
    }

    public static function resetsAt(): CarbonImmutable
    {
        return static::monthStart()->addMonth();
    }
}
