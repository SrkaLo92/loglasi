<?php

namespace App\Services\Messages;

use Illuminate\Support\Facades\DB;

/** Daily counters in listing_daily_stats (one row per listing per day, Europe/Belgrade). */
class ListingStats
{
    public const COLUMNS = ['messages', 'favorites'];

    public static function increment(int $listingId, string $column): void
    {
        if (! in_array($column, self::COLUMNS, true)) {
            throw new \InvalidArgumentException("Unknown stats column {$column}.");
        }

        // One statement: insert the day's row or add one to it (no read-modify-write race).
        DB::table('listing_daily_stats')->upsert(
            ['listing_id' => $listingId, 'date' => now('Europe/Belgrade')->toDateString(), $column => 1],
            ['listing_id', 'date'],
            [$column => DB::raw($column.' + 1')],
        );
    }
}
