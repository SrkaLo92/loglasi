<?php

namespace App\Support\Firms;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Firm statistics from listing_daily_stats (views, phone reveals, messages, favourites per listing
 * per day, Europe/Belgrade dates). Each query walks the firm's listings through the
 * listings(user_id, ...) index and then the (listing_id, date) primary key range of each listing,
 * so its cost depends on the firm's own rows, not on the size of the table. Results are cached
 * for config('firms.stats.cache_ttl') seconds per firm and period.
 */
class FirmStats
{
    public const METRICS = ['views', 'phone_reveals', 'messages', 'favorites'];

    /**
     * @return array{from: string, to: string, days: int, totals: array<string, int>, daily: list<array{date: string, views: int, phone_reveals: int, messages: int, favorites: int}>}
     */
    public static function daily(int $userId, int $days): array
    {
        [$from, $to] = static::range($days);

        return Cache::remember(static::key('daily', $userId, $days, $to), static::ttl(), function () use ($userId, $from, $to, $days) {
            $rows = DB::table('listing_daily_stats as s')
                ->join('listings as l', 'l.id', '=', 's.listing_id')
                ->where('l.user_id', $userId)
                ->whereBetween('s.date', [$from->toDateString(), $to->toDateString()])
                ->groupBy('s.date')
                ->selectRaw('s.date as day, SUM(s.views) as views, SUM(s.phone_reveals) as phone_reveals, SUM(s.messages) as messages, SUM(s.favorites) as favorites')
                ->get()
                ->keyBy(fn ($row) => substr((string) $row->day, 0, 10));

            $daily = [];
            $totals = array_fill_keys(self::METRICS, 0);
            for ($date = $from; $date <= $to; $date = $date->addDay()) {
                $key = $date->toDateString();
                $row = $rows[$key] ?? null;
                $point = ['date' => $key];
                foreach (self::METRICS as $metric) {
                    $point[$metric] = (int) ($row->{$metric} ?? 0);
                    $totals[$metric] += $point[$metric];
                }
                $daily[] = $point;
            }

            return [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'days' => $days,
                'totals' => $totals,
                'daily' => $daily,
            ];
        });
    }

    /**
     * Totals per listing in the period, most viewed first (listings without activity are left out).
     *
     * @return list<array{id: int, title: string, slug: string, status: string, deleted: bool, views: int, phone_reveals: int, messages: int, favorites: int}>
     */
    public static function perListing(int $userId, int $days, ?int $limit = null): array
    {
        [$from, $to] = static::range($days);
        $limit ??= (int) config('firms.stats.top_listings', 50);

        return Cache::remember(static::key('listings', $userId, $days, $to).'.'.$limit, static::ttl(), function () use ($userId, $from, $to, $limit) {
            return DB::table('listing_daily_stats as s')
                ->join('listings as l', 'l.id', '=', 's.listing_id')
                ->where('l.user_id', $userId)
                ->whereBetween('s.date', [$from->toDateString(), $to->toDateString()])
                ->groupBy('l.id', 'l.title', 'l.slug', 'l.status', 'l.deleted_at')
                ->selectRaw('l.id, l.title, l.slug, l.status, l.deleted_at, SUM(s.views) as views, SUM(s.phone_reveals) as phone_reveals, SUM(s.messages) as messages, SUM(s.favorites) as favorites')
                ->orderByDesc('views')
                ->orderByDesc('phone_reveals')
                ->orderBy('l.id')
                ->limit($limit)
                ->get()
                ->map(fn ($row) => [
                    'id' => (int) $row->id,
                    'title' => (string) $row->title,
                    'slug' => (string) $row->slug,
                    'status' => (string) $row->status,
                    'deleted' => $row->deleted_at !== null,
                    'views' => (int) $row->views,
                    'phone_reveals' => (int) $row->phone_reveals,
                    'messages' => (int) $row->messages,
                    'favorites' => (int) $row->favorites,
                ])
                ->all();
        });
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} first and last day, today included */
    public static function range(int $days): array
    {
        $to = CarbonImmutable::now(config('app.timezone'))->startOfDay();

        return [$to->subDays(max(1, $days) - 1), $to];
    }

    private static function key(string $kind, int $userId, int $days, CarbonImmutable $to): string
    {
        return 'firms.stats.'.$kind.'.'.$userId.'.'.$days.'.'.$to->toDateString();
    }

    private static function ttl(): int
    {
        return (int) config('firms.stats.cache_ttl', 300);
    }
}
