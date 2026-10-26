<?php

namespace App\Support;

use App\Enums\ListingStatus;
use App\Models\Category;
use App\Models\Listing;
use App\Models\User;
use App\Services\Payments\ListingSlots;
use App\Services\Payments\PlusSeller;
use App\Support\Firms\FirmPackage;
use Carbon\CarbonImmutable;

/**
 * How many ACTIVE listings a user may have (pending or published and not expired).
 * Private users: setting private_active_limit (10). Firms with an active package: the package limit
 * (Vizitka 5, Start 50, Pro, Max and the free first year unlimited; FirmPackage::listingLimit).
 * A firm without an active package has the private limit. Extra listings bought for 30 days
 * (ListingSlots, payments module) are added to the private limit. Plus prodavac (PlusSeller) raises it
 * to 100, except for listings in services and jobs (pass the category).
 * A slot frees when a listing expires, is sold, rejected or deleted.
 * The API keeps the contract names (monthly_limit, used_this_month, resets_at).
 */
class ListingLimits
{
    /** What is returned as the limit when it is unlimited (the app expects an integer). */
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

    /** Whether the user can post one more listing (for the listing module). */
    public static function canCreate(User $user, ?Category $category = null): bool
    {
        $limit = static::monthlyLimit($user, $category);

        return $limit === null || static::activeCount($user) < $limit;
    }

    /** null = no limit. */
    public static function monthlyLimit(User $user, ?Category $category = null): ?int
    {
        if ($user->isFirm()) {
            $firm = $user->firm;
            if ($firm !== null && FirmPackage::isActive($firm)) {
                return FirmPackage::listingLimit($firm);
            }
        }

        // Extra listings bought for 30 days (payments: +3 / +10) raise the private limit.
        $limit = (int) Settings::get('private_active_limit') + ListingSlots::extra($user);
        if (PlusSeller::isActive($user) && ($category === null || PlusSeller::appliesTo($category))) {
            $limit = max($limit, PlusSeller::listingLimit());
        }

        return $limit;
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
