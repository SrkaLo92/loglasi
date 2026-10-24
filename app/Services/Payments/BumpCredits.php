<?php

namespace App\Services\Payments;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Bumps bought as a pack (Paket 10 podizanja); they do not expire. */
final class BumpCredits
{
    public static function remaining(User $user): int
    {
        return (int) DB::table('bump_credits')->where('user_id', $user->getKey())->sum(DB::raw('granted - used'));
    }

    public static function grant(User $user, int $bumps, ?int $orderId = null): int
    {
        return (int) DB::table('bump_credits')->insertGetId([
            'user_id' => $user->getKey(),
            'order_id' => $orderId,
            'granted' => $bumps,
            'used' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** Uses one bump from the oldest pack with bumps left; false when none is left. Atomic. */
    public static function consume(User $user): bool
    {
        $ids = DB::table('bump_credits')->where('user_id', $user->getKey())
            ->whereColumn('used', '<', 'granted')->orderBy('id')->limit(3)->pluck('id');

        foreach ($ids as $id) {
            $updated = DB::table('bump_credits')->where('id', $id)->whereColumn('used', '<', 'granted')
                ->update(['used' => DB::raw('used + 1'), 'updated_at' => now()]);
            if ($updated === 1) {
                return true;
            }
        }

        return false;
    }

    /** Unused bumps of an order's pack; they are taken back (used = granted). Returns how many. */
    public static function revoke(int $orderId): int
    {
        $row = DB::table('bump_credits')->where('order_id', $orderId)->lockForUpdate()->first();
        if ($row === null) {
            return 0;
        }
        DB::table('bump_credits')->where('id', $row->id)->update(['used' => $row->granted, 'updated_at' => now()]);

        return max(0, (int) $row->granted - (int) $row->used);
    }

    /** @return array{granted: int, used: int}|null */
    public static function ofOrder(int $orderId): ?array
    {
        $row = DB::table('bump_credits')->where('order_id', $orderId)->first(['granted', 'used']);

        return $row ? ['granted' => (int) $row->granted, 'used' => (int) $row->used] : null;
    }
}
