<?php

namespace App\Support\Nalozi;

use App\Enums\ListingStatus;
use App\Models\Listing;
use App\Models\User;
use App\Services\Payments\PlusSeller;
use App\Support\ListingLimits;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * What the account overview shows: things that need the user's attention, numbers for the last
 * 30 days and the newest listings. A handful of small queries, all by user_id.
 */
final class AccountOverview
{
    /** Listings that expire within this many days are listed as a task. */
    public const EXPIRING_DAYS = 3;

    public function __construct(public readonly User $user) {}

    /**
     * Tasks, most important first: [tone, icon, text, link label, url]; tone is warn or info.
     *
     * @return list<array{tone: string, icon: string, text: string, action: string, url: string}>
     */
    public function tasks(): array
    {
        $counts = AccountMenu::counts($this->user);
        $byStatus = $this->statusCounts();
        $task = fn (string $tone, string $icon, string $text, string $action, string $url) => compact('tone', 'icon', 'text', 'action', 'url');
        $tasks = [];

        if ($counts['unread'] > 0) {
            $tasks[] = $task('warn', 'chat', 'Imate '.self::plural($counts['unread'], 'novu poruku', 'nove poruke', 'novih poruka').'.', 'Otvori poruke', url_slash('moj-nalog/poruke'));
        }
        if (($expiring = $this->expiringCount()) > 0) {
            $tasks[] = $task('warn', 'clock', self::plural($expiring, 'oglas ističe', 'oglasa ističu', 'oglasa ističe').' u naredna '.self::EXPIRING_DAYS.' dana. Obnova je besplatna.', 'Pogledaj', url_slash('moj-nalog/oglasi'));
        }
        if ($byStatus['rejected'] > 0) {
            $tasks[] = $task('warn', 'alert', self::plural($byStatus['rejected'], 'oglas je odbijen', 'oglasa su odbijena', 'oglasa je odbijeno').'. Pogledajte razlog i ispravite ga.', 'Pogledaj', url_slash('moj-nalog/oglasi').'?status=odbijeni');
        }
        if ($counts['orders'] > 0) {
            $tasks[] = $task('warn', 'receipt', self::plural($counts['orders'], 'narudžbina čeka', 'narudžbine čekaju', 'narudžbina čeka').' uplatu.', 'Narudžbine', url_slash('moj-nalog/narudzbine'));
        }
        if ($byStatus['draft'] > 0) {
            $tasks[] = $task('info', 'list', 'Imate '.self::plural($byStatus['draft'], 'nedovršen oglas', 'nedovršena oglasa', 'nedovršenih oglasa').'.', 'Nastavi', url_slash('moj-nalog/oglasi').'?status=nacrti');
        }
        if ($byStatus['pending'] > 0) {
            $tasks[] = $task('info', 'clock', self::plural($byStatus['pending'], 'oglas čeka', 'oglasa čekaju', 'oglasa čeka').' proveru. Obično traje kratko.', 'Pogledaj', url_slash('moj-nalog/oglasi').'?status=na-cekanju');
        }
        if ($this->user->location_id === null) {
            $tasks[] = $task('info', 'pin', 'Dodajte svoje mesto, da bi kupci znali odakle ste.', 'Dodaj', url_slash('moj-nalog/podesavanja'));
        }

        return $tasks;
    }

    /** @return array{active: int, limit: ?int, remaining: ?int, views: int, reveals: int, saved: int, plus_until: ?Carbon} */
    public function stats(): array
    {
        $limits = ListingLimits::for($this->user);
        $ids = Listing::query()->where('user_id', $this->user->id)->pluck('id');

        $recent = $ids->isEmpty() ? null : DB::table('listing_daily_stats')
            ->whereIn('listing_id', $ids)
            ->where('date', '>=', now()->subDays(29)->toDateString())
            ->selectRaw('COALESCE(SUM(views), 0) as views, COALESCE(SUM(phone_reveals), 0) as reveals')
            ->first();

        return [
            'active' => $limits['used_this_month'],
            'limit' => $limits['unlimited'] ? null : $limits['monthly_limit'],
            'remaining' => $limits['unlimited'] ? null : $limits['remaining'],
            'views' => (int) ($recent->views ?? 0),
            'reveals' => (int) ($recent->reveals ?? 0),
            'saved' => $ids->isEmpty() ? 0 : DB::table('favorites')->whereIn('listing_id', $ids)->count(),
            'plus_until' => $this->user->isFirm() ? null : PlusSeller::activeUntil($this->user),
        ];
    }

    /** The newest listings that are not drafts, with their cover image. */
    public function recentListings(int $limit = 4): Collection
    {
        return Listing::query()->where('user_id', $this->user->id)
            ->where('status', '!=', ListingStatus::Draft)
            ->with(['images', 'category'])
            ->latest('updated_at')
            ->limit($limit)
            ->get();
    }

    /** @return array{draft: int, pending: int, rejected: int} */
    private function statusCounts(): array
    {
        $rows = Listing::query()->where('user_id', $this->user->id)
            ->whereIn('status', [ListingStatus::Draft, ListingStatus::Pending, ListingStatus::Rejected])
            ->groupBy('status')->selectRaw('status, COUNT(*) as aggregate')->pluck('aggregate', 'status');

        return [
            'draft' => (int) ($rows[ListingStatus::Draft->value] ?? 0),
            'pending' => (int) ($rows[ListingStatus::Pending->value] ?? 0),
            'rejected' => (int) ($rows[ListingStatus::Rejected->value] ?? 0),
        ];
    }

    private function expiringCount(): int
    {
        return Listing::query()->where('user_id', $this->user->id)
            ->where('status', ListingStatus::Published)
            ->whereBetween('expires_at', [now(), now()->addDays(self::EXPIRING_DAYS)])
            ->count();
    }

    /** "1 oglas", "3 oglasa", "5 oglasa" (Serbian plural forms). */
    public static function plural(int $n, string $one, string $few, string $many): string
    {
        $form = $n % 10 === 1 && $n % 100 !== 11 ? $one
            : (in_array($n % 10, [2, 3, 4], true) && ! in_array($n % 100, [12, 13, 14], true) ? $few : $many);

        return $n.' '.$form;
    }
}
