<?php

namespace App\Support;

use App\Enums\ListingStatus;
use App\Models\Listing;
use App\Models\User;
use App\Support\Firms\FirmPackage;
use Carbon\CarbonImmutable;

/**
 * How many ACTIVE listings a user may have (pending or published and not expired).
 * Private users: setting private_active_limit (5). Firms with an active package: the package limit
 * (Start 20, Pro 100, Max and the free first year unlimited; FirmPackage::listingLimit).
 * A firm without an active package has the private limit.
 * A slot frees when a listing expires, is sold, rejected or deleted.
 * The API keeps the contract names (monthly_limit, used_this_month, resets_at).
 */
class ListingLimits
{
    /** Šta se vraća kao limit kad je neograničeno (aplikacija očekuje ceo broj). */
    public const UNLIMITED = 999;

    /** @return array{monthly_limit:int, used_this_month:int, remaining:int, resets_at:string, unlimited:bool} */
    public static function for(User $user): array
    {
        $limit = static::monthlyLimit($user);
        $used = static::activeCount($user);
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

        return $limit === null || static::activeCount($user) < $limit;
    }

    /** null = bez ograničenja. */
    public static function monthlyLimit(User $user): ?int
    {
        if ($user->isFirm()) {
            $firm = $user->firm;
            if ($firm !== null && FirmPackage::isActive($firm)) {
                return FirmPackage::listingLimit($firm);
            }
        }

        return (int) Settings::get('private_active_limit');
    }

    /** Listings that use a slot: waiting for review or published and not expired (drafts do not). */
    public static function activeCount(User $user): int
    {
        return Listing::query()
            ->where('user_id', $user->id)
            ->whereIn('status', [ListingStatus::Pending, ListingStatus::Published])
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
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
