<?php

namespace App\Services\Admin;

use App\Enums\ListingStatus;
use App\Enums\OrderStatus;
use App\Enums\ReportStatus;
use App\Models\Firm;
use App\Models\Listing;
use App\Models\Message;
use App\Models\Order;
use App\Models\RefreshToken;
use App\Models\Report;
use App\Models\User;
use App\Services\Catalog;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/** Numbers for the admin dashboard and GET /admin/stats. Short cache, so the dashboard stays fast. */
class AdminStats
{
    public const PERIODS = ['today' => 1, '7d' => 7, '30d' => 30];

    private const TTL = 120;

    public function __construct(private readonly Catalog $catalog) {}

    /**
     * "Waiting for you" counters (not cached: they change with every action).
     *
     * @return array{listings: int, phones: int, orders: int, reports: int}
     */
    public function queue(): array
    {
        return [
            'listings' => Listing::query()->where('status', ListingStatus::Pending)->count(),
            'phones' => $this->phonesToVerify()->count(),
            'orders' => Order::query()->where('status', OrderStatus::Pending)->count(),
            'reports' => Report::query()->where('status', ReportStatus::Open)->count(),
        ];
    }

    /** Users with a phone number that is not verified yet. */
    public function phonesToVerify()
    {
        return User::query()->whereNotNull('phone')->where('phone', '!=', '')->whereNull('phone_verified_at');
    }

    /** First moment of a period: today = midnight, 7d = midnight 6 days ago... (Europe/Belgrade). */
    public static function since(string $period): CarbonImmutable
    {
        $days = self::PERIODS[$period] ?? 7;

        return CarbonImmutable::now(config('app.timezone'))->startOfDay()->subDays($days - 1);
    }

    /**
     * Totals for one period.
     *
     * @return array{listings: int, users: int, firms: int, views: int, reveals: int, revenue: int}
     */
    public function totals(string $period): array
    {
        return Cache::remember('admin.stats.totals.'.$period, self::TTL, function () use ($period) {
            $since = self::since($period);
            $stats = DB::table('listing_daily_stats')
                ->where('date', '>=', $since->toDateString())
                ->selectRaw('COALESCE(SUM(views), 0) AS views, COALESCE(SUM(phone_reveals), 0) AS reveals, COALESCE(SUM(messages), 0) AS messages')
                ->first();

            return [
                'listings' => Listing::withTrashed()->where('created_at', '>=', $since)->count(),
                'users' => User::withTrashed()->where('created_at', '>=', $since)->count(),
                'firms' => Firm::query()->where('created_at', '>=', $since)->count(),
                'views' => (int) $stats->views,
                'reveals' => (int) $stats->reveals,
                'revenue' => (int) Order::query()->where('status', OrderStatus::Paid)->where('paid_at', '>=', $since)->sum('amount_rsd'),
            ];
        });
    }

    /**
     * Per day for the last N days, every day present (0 when empty).
     *
     * @return array{labels: list<string>, dates: list<string>, listings: list<int>, users: list<int>, views: list<int>}
     */
    public function daily(int $days = 30): array
    {
        return Cache::remember('admin.stats.daily.'.$days, self::TTL, function () use ($days) {
            $since = CarbonImmutable::now(config('app.timezone'))->startOfDay()->subDays($days - 1);

            $listings = $this->perDay(Listing::withTrashed()->toBase(), $since);
            $users = $this->perDay(User::withTrashed()->toBase(), $since);
            $views = DB::table('listing_daily_stats')
                ->where('date', '>=', $since->toDateString())
                ->groupBy('date')
                ->selectRaw('date AS d, SUM(views) AS n')
                ->pluck('n', 'd')
                ->mapWithKeys(fn ($n, $d) => [substr((string) $d, 0, 10) => (int) $n])
                ->all();

            $out = ['labels' => [], 'dates' => [], 'listings' => [], 'users' => [], 'views' => []];
            for ($i = 0; $i < $days; $i++) {
                $date = $since->addDays($i)->toDateString();
                $out['dates'][] = $date;
                $out['labels'][] = $since->addDays($i)->format('j. n.');
                $out['listings'][] = $listings[$date] ?? 0;
                $out['users'][] = $users[$date] ?? 0;
                $out['views'][] = $views[$date] ?? 0;
            }

            return $out;
        });
    }

    /**
     * Main categories by number of active listings (from the catalog cache, no extra query).
     *
     * @return list<array{name: string, count: int}>
     */
    public function topCategories(int $limit = 8): array
    {
        $rows = array_map(fn (array $node) => [
            'name' => html_entity_decode($node['category']->name, ENT_QUOTES, 'UTF-8'),
            'count' => (int) $node['count'],
        ], $this->catalog->tree());

        usort($rows, fn ($a, $b) => $b['count'] <=> $a['count']);

        return array_slice(array_values(array_filter($rows, fn ($r) => $r['count'] > 0)), 0, $limit);
    }

    /** AdminStats from the API contract. */
    public function api(string $period): array
    {
        return Cache::remember('admin.stats.api.'.$period, self::TTL, fn () => $this->computeApi($period));
    }

    private function computeApi(string $period): array
    {
        $since = self::since($period);
        $totals = $this->totals($period);
        $daily = $this->daily(self::PERIODS[$period] ?? 7);

        $avg = Listing::withTrashed()
            ->whereNotNull('published_at')
            ->where('published_at', '>=', $since)
            ->whereColumn('published_at', '>=', 'created_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, created_at, published_at)) AS m')
            ->value('m');

        $points = fn (array $values) => array_map(fn ($date, $value) => ['date' => $date, 'value' => (int) $value], $daily['dates'], $values);

        return [
            'pending_listings' => Listing::query()->where('status', ListingStatus::Pending)->count(),
            'avg_approval_minutes' => (int) round((float) $avg),
            'active_listings' => Listing::query()->where('status', ListingStatus::Published)
                ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))->count(),
            'new_listings' => $totals['listings'],
            'new_users' => $totals['users'],
            'new_firms' => $totals['firms'],
            'views' => $totals['views'],
            'contacts' => $totals['reveals'],
            'messages' => Message::query()->where('created_at', '>=', $since)->count(),
            'orders_awaiting_payment' => Order::query()->where('status', OrderStatus::Pending)->count(),
            'revenue_paid' => $totals['revenue'],
            'app_active_users' => RefreshToken::query()
                ->where(fn ($q) => $q->where('last_used_at', '>=', $since)->orWhere('created_at', '>=', $since))
                ->distinct()->count('user_id'),
            'listings_daily' => $points($daily['listings']),
            'users_daily' => $points($daily['users']),
            'by_category' => array_map(fn ($r) => ['category' => $r['name'], 'active' => $r['count']], $this->topCategories(50)),
        ];
    }

    public static function forget(): void
    {
        foreach (array_keys(self::PERIODS) as $period) {
            Cache::forget('admin.stats.totals.'.$period);
        }
        foreach (array_keys(self::PERIODS) as $period) {
            Cache::forget('admin.stats.api.'.$period);
        }
        foreach ([1, 7, 30] as $days) {
            Cache::forget('admin.stats.daily.'.$days);
        }
    }

    /** @return array<string, int> date => count */
    private function perDay($query, CarbonImmutable $since): array
    {
        return $query
            ->where('created_at', '>=', $since)
            ->groupByRaw('DATE(created_at)')
            ->selectRaw('DATE(created_at) AS d, COUNT(*) AS n')
            ->pluck('n', 'd')
            ->mapWithKeys(fn ($n, $d) => [substr((string) $d, 0, 10) => (int) $n])
            ->all();
    }
}
