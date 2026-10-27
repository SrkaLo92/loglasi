<?php

namespace App\Services\Messages;

use App\Support\Listing\Stats;

/** Daily counters in listing_daily_stats (one row per listing per day, Europe/Belgrade). */
class ListingStats
{
    public const COLUMNS = ['messages', 'favorites'];

    public static function increment(int $listingId, string $column): void
    {
        if (! in_array($column, self::COLUMNS, true)) {
            throw new \InvalidArgumentException("Unknown stats column {$column}.");
        }

        Stats::addDaily($listingId, $column);
    }
}
