<?php

namespace App\Support\Nalozi;

use App\Enums\ListingStatus;
use App\Enums\OrderStatus;
use App\Models\Listing;
use App\Models\Order;
use App\Models\SavedSearch;
use App\Models\User;
use App\Services\Messages\Messenger;
use Illuminate\Support\Facades\DB;

/**
 * The account menu (sidebar on a computer, list on the phone's account page): sections with an icon,
 * a count and the active item. Counts are computed once per request (kept on the request).
 */
final class AccountMenu
{
    /**
     * @return list<array{title: ?string, items: list<array{path: string, label: string, icon: string, count: ?int, alert: bool}>}>
     */
    public static function groups(User $user): array
    {
        $c = self::counts($user);
        $item = fn (string $path, string $label, string $icon, ?int $count = null, bool $alert = false) => compact('path', 'label', 'icon', 'count', 'alert');

        $groups = [
            ['title' => null, 'items' => [
                $item('moj-nalog', 'Pregled', 'grid'),
                $item('moj-nalog/oglasi', 'Moji oglasi', 'list', $c['listings']),
                $item('moj-nalog/poruke', 'Poruke', 'chat', $c['unread'] ?: null, $c['unread'] > 0),
            ]],
            ['title' => 'Sačuvano', 'items' => [
                $item('moj-nalog/omiljeni', 'Omiljeni oglasi', 'heart', $c['favorites'] ?: null),
                $item('moj-nalog/pretrage', 'Sačuvane pretrage', 'bookmark', $c['searches'] ?: null),
            ]],
            ['title' => 'Nalog', 'items' => array_values(array_filter([
                $user->isFirm() ? $item('moj-nalog/firma', 'Profil firme', 'shop') : null,
                $item('moj-nalog/narudzbine', 'Narudžbine', 'receipt', $c['orders'] ?: null, $c['orders'] > 0),
                $item('moj-nalog/obavestenja', 'Obaveštenja', 'bell'),
                $item('moj-nalog/podesavanja', 'Podešavanja naloga', 'settings'),
            ]))],
        ];

        return $groups;
    }

    /** Path of the menu item for the current page (the longest one that matches). */
    public static function active(User $user, string $path): ?string
    {
        $path = trim($path, '/');

        return collect(self::groups($user))->pluck('items')->flatten(1)->pluck('path')
            ->filter(fn ($item) => $path === $item || str_starts_with($path, $item.'/'))
            ->sortByDesc(fn ($item) => strlen($item))
            ->first();
    }

    /** @return array{listings: int, unread: int, favorites: int, searches: int, orders: int} */
    public static function counts(User $user): array
    {
        $key = 'account_counts_'.$user->id;
        if (is_array($cached = request()->attributes->get($key))) {
            return $cached;
        }

        $counts = [
            'listings' => Listing::query()->where('user_id', $user->id)
                ->where('status', ListingStatus::Published)
                ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                ->count(),
            'unread' => app(Messenger::class)->unreadTotal($user),
            'favorites' => DB::table('favorites')->where('user_id', $user->id)->count(),
            'searches' => SavedSearch::query()->where('user_id', $user->id)->count(),
            'orders' => Order::query()->where('user_id', $user->id)->where('status', OrderStatus::Pending)->count(),
        ];
        request()->attributes->set($key, $counts);

        return $counts;
    }
}
