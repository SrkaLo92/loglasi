<?php

namespace App\Services\Admin;

use App\Enums\ListingStatus;
use App\Enums\OrderStatus;
use App\Enums\ReportStatus;
use App\Enums\UserStatus;
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

    private const QUEUE_KEY = 'admin.stats.queue';

    public function __construct(private readonly Catalog $catalog) {}

    /**
     * "Waiting for you" counters. Cached briefly (indexed counts, but the dashboard polls);
     * admin actions call forgetQueue() so the numbers follow the work.
     *
     * @return array{listings: int, phones: int, orders: int, reports: int}
     */
    public function queue(): array
    {
        return Cache::remember(self::QUEUE_KEY, 30, fn () => [
            'listings' => Listing::query()->where('status', ListingStatus::Pending)->count(),
            'phones' => $this->phonesToVerify()->count(),
            'orders' => Order::query()->where('status', OrderStatus::Pending)->count(),
            'reports' => Report::query()->where('status', ReportStatus::Open)->count(),
        ]);
    }

    public static function forgetQueue(): void
    {
        Cache::forget(self::QUEUE_KEY);
    }

    /** Real (non-test) orders whose money arrived; revenue is net of refunds. */
    private function paidOrders()
    {
        return Order::query()
            ->whereIn('status', [OrderStatus::Paid, OrderStatus::Activated, OrderStatus::Refunded])
            ->where('is_test', false);
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
     * @return array{listings: int, users: int, firms: int, views: int, reveals: int, orders: int, revenue: int}
     */
    /**
     * Listings per status right now, plus all users and firms (cached TTL seconds).
     *
     * @return array{all: int, published: int, pending: int, rejected: int, expired: int, users: int, blocked: int, app_users: int, firms: int, firms_paid: int}
     */
    public function counts(): array
    {
        return Cache::remember('admin.stats.counts', self::TTL, function () {
            $byStatus = Listing::query()->toBase()->selectRaw('status, COUNT(*) AS n')->groupBy('status')->pluck('n', 'status');
            $get = fn (ListingStatus $s) => (int) ($byStatus[$s->value] ?? 0);

            return [
                'all' => (int) $byStatus->sum(),
                'published' => $get(ListingStatus::Published),
                'pending' => $get(ListingStatus::Pending),
                'rejected' => $get(ListingStatus::Rejected),
                'expired' => $get(ListingStatus::Expired),
                'users' => User::query()->count(),
                'blocked' => User::query()->where('status', UserStatus::Blocked)->count(),
                'app_users' => User::query()->whereHas('devices')->count(),
                'firms' => Firm::query()->count(),
                'firms_paid' => Firm::query()->where('package_ends_at', '>', now())->count(),
            ];
        });
    }

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
                'orders' => $this->paidOrders()->where('paid_at', '>=', $since)->count(),
                'revenue' => (int) $this->paidOrders()->where('paid_at', '>=', $since)->sum(DB::raw('amount_rsd - COALESCE(refund_amount_rsd, 0)')),
            ];
        });
    }

    /**
     * Per day for the last N days, every day present (0 when empty).
     *
     * @return array{labels: list<string>, dates: list<string>, listings: list<int>, users: list<int>, views: list<int>, orders: list<int>, revenue: list<int>}
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

            $orders = $this->paidOrders()
                ->where('paid_at', '>=', $since)
                ->groupByRaw('DATE(paid_at)')
                ->selectRaw('DATE(paid_at) AS d, COUNT(*) AS n, SUM(amount_rsd - COALESCE(refund_amount_rsd, 0)) AS r')
                ->toBase()
                ->get()
                ->keyBy(fn ($row) => substr((string) $row->d, 0, 10));

            $out = ['labels' => [], 'dates' => [], 'listings' => [], 'users' => [], 'views' => [], 'orders' => [], 'revenue' => []];
            for ($i = 0; $i < $days; $i++) {
                $date = $since->addDays($i)->toDateString();
                $out['dates'][] = $date;
                $out['labels'][] = $since->addDays($i)->format('j. n.');
                $out['listings'][] = $listings[$date] ?? 0;
                $out['users'][] = $users[$date] ?? 0;
                $out['views'][] = $views[$date] ?? 0;
                $out['orders'][] = (int) ($orders[$date]->n ?? 0);
                $out['revenue'][] = (int) ($orders[$date]->r ?? 0);
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

    /**
     * Saved searches, alert mails and app devices (read-only page). Cached for 10 minutes.
     *
     * @return array{searches: array<string, int>, matches: array<string, int>, devices: array<string, int>, platforms: array<string, int>, top: list<array{name: string, count: int}>}
     */
    public function notifications(): array
    {
        return Cache::remember('admin.stats.notifications', 600, function () {
            $week = now()->subDays(7);
            $month = now()->subDays(30);
            $searches = DB::table('saved_searches')
                ->selectRaw('COUNT(*) AS total, COALESCE(SUM(notify), 0) AS notify, COALESCE(SUM(created_at >= ?), 0) AS week, COUNT(DISTINCT user_id) AS users', [$week])
                ->first();
            $matches = DB::table('saved_search_matches')
                ->where('created_at', '>=', $month)
                ->selectRaw('COUNT(*) AS month, COALESCE(SUM(notified_at IS NOT NULL), 0) AS sent, COALESCE(SUM(notified_at IS NULL), 0) AS waiting, COALESCE(SUM(created_at >= ?), 0) AS week', [$week])
                ->first();
            $devices = DB::table('devices')
                ->selectRaw('COUNT(*) AS total, COUNT(DISTINCT user_id) AS users, COALESCE(SUM(last_seen_at >= ?), 0) AS active', [$month])
                ->first();

            return [
                'searches' => ['total' => (int) $searches->total, 'notify' => (int) $searches->notify, 'week' => (int) $searches->week, 'users' => (int) $searches->users],
                'matches' => ['month' => (int) $matches->month, 'week' => (int) $matches->week, 'sent' => (int) $matches->sent, 'waiting' => (int) $matches->waiting],
                'devices' => ['total' => (int) $devices->total, 'users' => (int) $devices->users, 'active' => (int) $devices->active],
                'platforms' => DB::table('devices')->groupBy('platform')->selectRaw('platform, COUNT(*) AS n')->pluck('n', 'platform')->map(fn ($n) => (int) $n)->all(),
                'top' => DB::table('saved_searches')
                    ->whereNotNull('query_hash')
                    ->groupBy('query_hash')
                    ->selectRaw('MIN(name) AS name, COUNT(*) AS n')
                    ->orderByDesc('n')
                    ->limit(10)
                    ->get()
                    ->map(fn ($row) => ['name' => (string) $row->name, 'count' => (int) $row->n])
                    ->all(),
            ];
        });
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
