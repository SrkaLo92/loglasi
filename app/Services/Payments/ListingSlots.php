<?php

namespace App\Services\Payments;

use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/** Extra active listings bought for 30 days (+3 / +10 / +30), added to the private limit. */
final class ListingSlots
{
    /** Extra slots running right now (several purchases add up). */
    public static function extra(User $user): int
    {
        $now = now();

        return (int) DB::table('listing_slot_grants')
            ->where('user_id', $user->getKey())
            ->where('ends_at', '>', $now)
            ->where('starts_at', '<=', $now)
            ->sum('slots');
    }

    public static function grant(User $user, int $slots, CarbonInterface $startsAt, CarbonInterface $endsAt, ?int $orderId = null): int
    {
        return (int) DB::table('listing_slot_grants')->insertGetId([
            'user_id' => $user->getKey(),
            'order_id' => $orderId,
            'slots' => $slots,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** Ends an order's slots now (refund). */
    public static function endForOrder(int $orderId): void
    {
        DB::table('listing_slot_grants')->where('order_id', $orderId)->where('ends_at', '>', now())
            ->update(['ends_at' => now(), 'updated_at' => now()]);
    }
}
