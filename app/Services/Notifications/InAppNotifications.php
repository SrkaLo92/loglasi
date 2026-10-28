<?php

namespace App\Services\Notifications;

use App\Http\Api\ListingFormatter;
use App\Models\Listing;
use App\Models\SavedSearch;
use App\Models\SellerRating;
use App\Models\User;
use App\Models\UserNotification;
use App\Notifications\InAppPushNotification;
use App\Support\Pages\Pricing;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * "Obaveštenja": what happened to the user's listings and searches, shown under the bell on the site
 * and in the apps. Approved/rejected listings and saves also go to the phone as a push
 * (InAppPushNotification); ratings and saved searches have their own push. Messages are not here:
 * they have their own unread badge.
 *
 *   $inApp->favorited($listing, $saver);            // once per person and listing
 *   $inApp->unread($user);                          // badge, at most 99 counted
 *   $inApp->page($user, before: null);              // newest first, keyset on id
 */
class InAppNotifications
{
    public const FAVORITE = 'favorite';

    public const RATING = 'rating';

    public const LISTING_APPROVED = 'listing_approved';

    public const LISTING_REJECTED = 'listing_rejected';

    public const SAVED_SEARCH = 'saved_search';

    public const PER_PAGE = 30;

    /** Kept this long; older ones are removed by notifications:prune-in-app. */
    public const KEEP_DAYS = 90;

    /** Someone saved the listing. Not for the owner's own save, and only the first save by a person. */
    public function favorited(Listing $listing, User $saver): void
    {
        if ((int) $listing->user_id === (int) $saver->id) {
            return;
        }

        $exists = UserNotification::query()
            ->where('type', self::FAVORITE)->where('listing_id', $listing->id)->where('actor_id', $saver->id)
            ->exists();
        if (! $exists) {
            $this->push($this->add((int) $listing->user_id, self::FAVORITE, ['actor_id' => $saver->id, 'listing_id' => $listing->id], ['title' => $listing->title]));
        }
    }

    public function rated(SellerRating $rating): void
    {
        $rating->loadMissing('rater:id,name', 'listing:id,title');
        $this->add((int) $rating->seller_id, self::RATING, ['actor_id' => $rating->rater_id, 'listing_id' => $rating->listing_id], [
            'score' => (int) $rating->score,
            'name' => $rating->rater?->name,
            'title' => $rating->listing?->title,
        ]);
    }

    public function moderated(Listing $listing, bool $approved, ?string $reason = null): void
    {
        $item = $this->add((int) $listing->user_id, $approved ? self::LISTING_APPROVED : self::LISTING_REJECTED, ['listing_id' => $listing->id], array_filter([
            'title' => $listing->title,
            'reason' => $approved ? null : Str::limit((string) $reason, 300),
        ], fn ($v) => $v !== null && $v !== ''));

        // Sent right away: the owner is usually waiting for this one.
        $this->push($item, now: true);
    }

    /** New listings for a saved search (from the matcher's digest), one notification per search and run. */
    public function savedSearchMatches(User $user, SavedSearch $search, int $count): void
    {
        if ($count > 0) {
            $this->add((int) $user->id, self::SAVED_SEARCH, ['saved_search_id' => $search->id], [
                'name' => $search->name ?: 'Sačuvana pretraga',
                'count' => $count,
            ]);
        }
    }

    /** Badge of the signed-in user for the header (once per request; 0 for guests and error pages). */
    public static function badge(): int
    {
        return once(function () {
            $user = auth()->user();
            try {
                return $user ? app(self::class)->unread($user) : 0;
            } catch (\Throwable) {
                return 0; // error pages render without a database
            }
        });
    }

    /** Unread count for the badge: counting stops at 100 ("99+"). */
    public function unread(User $user): int
    {
        return UserNotification::query()->where('user_id', $user->id)->whereNull('read_at')->limit(100)->get(['id'])->count();
    }

    /** @return array{items: Collection<int, UserNotification>, next: ?int} newest first; $before = last id of the previous page */
    public function page(User $user, ?int $before = null, int $perPage = self::PER_PAGE): array
    {
        $rows = UserNotification::query()
            ->where('user_id', $user->id)
            ->when($before, fn ($q) => $q->where('id', '<', $before))
            ->orderByDesc('id')
            ->limit($perPage + 1)
            ->with(['user:id,slug', 'savedSearch', 'listing' => fn ($q) => $q->select(['id', 'title', 'slug', 'status', 'user_id'])->with('coverImage')])
            ->get();

        $more = $rows->count() > $perPage;
        $items = $rows->take($perPage)->values();

        return ['items' => $items, 'next' => $more ? (int) $items->last()->id : null];
    }

    /** Marks the given notifications (or all) as read. @param  list<int>|null  $ids */
    public function markRead(User $user, ?array $ids = null): int
    {
        return UserNotification::query()
            ->where('user_id', $user->id)->whereNull('read_at')
            ->when($ids !== null, fn ($q) => $q->whereIn('id', array_slice($ids, 0, 200)))
            ->update(['read_at' => now()]);
    }

    /**
     * Texts and target of one notification (site and API alike).
     *
     * @return array{type: string, icon: string, tone: string, title: string, body: ?string, url: string}
     */
    public function present(UserNotification $n): array
    {
        $d = (array) $n->data;
        $title = $n->listing?->title ?? ($d['title'] ?? 'oglas');
        $listingUrl = $n->listing ? $n->listing->url() : url_slash('moj-nalog/oglasi');

        [$icon, $tone, $text, $body, $url] = match ($n->type) {
            self::FAVORITE => ['heart', 'red', 'Neko je sačuvao vaš oglas', '„'.$title.'”', $listingUrl],
            self::RATING => ['star', 'amber', ($d['name'] ?? 'Kupac').' vas je ocenio sa '.($d['score'] ?? '?').' '.Pricing::plural((int) ($d['score'] ?? 0), 'zvezdicom', 'zvezdice', 'zvezdica'),
                isset($d['title']) ? 'Oglas „'.$d['title'].'”' : null, $n->user ? url_slash('korisnik/'.$n->user->slug.'/ocene') : url_slash('moj-nalog')],
            self::LISTING_APPROVED => ['check', 'green', 'Vaš oglas je objavljen', '„'.$title.'”', $listingUrl],
            self::LISTING_REJECTED => ['alert', 'orange', 'Vaš oglas nije odobren', '„'.$title.'”'.(isset($d['reason']) ? ' · '.$d['reason'] : ''), url_slash('moj-nalog/oglasi').'?status=odbijeni'],
            self::SAVED_SEARCH => ['bookmark', 'blue', ($d['count'] ?? 0).' '.Pricing::plural((int) ($d['count'] ?? 0), 'novi oglas', 'nova oglasa', 'novih oglasa').' za vašu pretragu',
                '„'.($d['name'] ?? 'Sačuvana pretraga').'”', $n->savedSearch?->url() ?? url_slash('moj-nalog/pretrage')],
            default => ['bell', 'gray', 'Obaveštenje', null, url_slash('moj-nalog')],
        };

        return ['type' => $n->type, 'icon' => $icon, 'tone' => $tone, 'title' => $text, 'body' => $body, 'url' => $url];
    }

    /** API shape (GET /api/v1/me/notifications). */
    public function toApi(UserNotification $n): array
    {
        $p = $this->present($n);
        $cover = $n->listing?->coverImage;

        return [
            'id' => (int) $n->id,
            'type' => $p['type'],
            'title' => $p['title'],
            'body' => $p['body'],
            'url' => $p['url'],
            'listing_id' => $n->listing_id ? (int) $n->listing_id : null,
            'saved_search_id' => $n->saved_search_id ? (int) $n->saved_search_id : null,
            'image' => $cover ? ListingFormatter::image($cover) : null,
            'read' => $n->read_at !== null,
            'created_at' => ListingFormatter::date($n->created_at),
        ];
    }

    /** Removes notifications older than KEEP_DAYS. */
    public function prune(): int
    {
        return UserNotification::query()->where('created_at', '<', now()->subDays(self::KEEP_DAYS))->delete();
    }

    /** @param  array<string, int|null>  $refs */
    private function add(int $userId, string $type, array $refs, array $data): ?UserNotification
    {
        if ($userId <= 0) {
            return null;
        }

        return UserNotification::query()->create(['user_id' => $userId, 'type' => $type, 'data' => $data, 'created_at' => now()] + $refs);
    }

    /** Push to the owner's phones; a push failure never breaks the action that caused it. */
    private function push(?UserNotification $item, bool $now = false): void
    {
        $user = $item?->user;
        if ($user === null || $user->trashed()) {
            return;
        }

        try {
            $now ? $user->notifyNow(new InAppPushNotification($item)) : $user->notify(new InAppPushNotification($item));
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
