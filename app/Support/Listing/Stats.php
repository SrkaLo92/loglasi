<?php

namespace App\Support\Listing;

use App\Models\Firm;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Views and phone reveals: listings.views / phone_reveals plus listing_daily_stats.
 * Counted with query-builder increments, so model events (catalog and sitemap caches) do not fire.
 */
class Stats
{
    /** Session key with the listing IDs already counted in this session. */
    public const SESSION_VIEWS = 'listing.viewed';

    public const SESSION_REVEALS = 'listing.revealed';

    public const SESSION_PROFILES = 'listing.profiles_viewed';

    /** How long the API remembers a viewer (no session there). */
    private const API_VIEW_TTL = 6 * 3600;

    private const BOT_PATTERN = '/bot|crawl|spider|slurp|preview|facebookexternalhit|headless|lighthouse|curl|wget|python|httpclient|scrapy/i';

    public static function isBot(?string $userAgent): bool
    {
        $userAgent = trim((string) $userAgent);

        return $userAgent === '' || (bool) preg_match(self::BOT_PATTERN, $userAgent);
    }

    /** Owner and staff do not inflate the numbers. */
    public static function counts(Listing $listing, ?User $viewer): bool
    {
        return $viewer === null || ($viewer->id !== $listing->user_id && ! $viewer->isStaff());
    }

    /** Web page view: once per session, no bots, no owner. Returns true when counted. */
    public static function viewFromWeb(Listing $listing, Request $request): bool
    {
        if (! $listing->isActive() || static::isBot($request->userAgent()) || ! static::counts($listing, $request->user())) {
            return false;
        }
        if (! static::rememberInSession($request, self::SESSION_VIEWS, $listing->id)) {
            return false;
        }
        static::increment($listing, 'views');

        return true;
    }

    /** API detail view: once per viewer (token user or IP + app) every few hours. */
    public static function viewFromApi(Listing $listing, Request $request, ?User $viewer): bool
    {
        if (! $listing->isActive() || static::isBot($request->userAgent()) || ! static::counts($listing, $viewer)) {
            return false;
        }
        $who = $viewer ? 'u'.$viewer->id : 'ip'.sha1($request->ip().'|'.$request->userAgent());
        if (! Cache::add('listing.api_view.'.$listing->id.'.'.$who, 1, self::API_VIEW_TTL)) {
            return false;
        }
        static::increment($listing, 'views');

        return true;
    }

    /**
     * Phone reveal: remembered in the session (web) so the number stays visible, and counted
     * once per session (web) or once a day per user (API). Returns true when counted.
     */
    public static function reveal(Listing $listing, ?Request $request, ?User $viewer): bool
    {
        if ($request && $request->hasSession()) {
            $new = static::rememberInSession($request, self::SESSION_REVEALS, $listing->id);
        } else {
            $new = $viewer === null || Cache::add('listing.api_reveal.'.$listing->id.'.'.$viewer->id, 1, 24 * 3600);
        }
        if (! $new || ! static::counts($listing, $viewer)) {
            return false;
        }
        static::increment($listing, 'phone_reveals');

        return true;
    }

    /** Whether this session already revealed the phone of a listing. */
    public static function revealed(Request $request, Listing $listing): bool
    {
        return $request->hasSession() && in_array($listing->id, (array) $request->session()->get(self::SESSION_REVEALS, []), true);
    }

    /** Firm profile view: once per session, no bots, not the owner. */
    public static function profileView(Firm $firm, Request $request): bool
    {
        $viewer = $request->user();
        if (! $firm->exists || static::isBot($request->userAgent()) || ($viewer && ($viewer->id === $firm->user_id || $viewer->isStaff()))) {
            return false;
        }
        if (! static::rememberInSession($request, self::SESSION_PROFILES, $firm->id)) {
            return false;
        }
        DB::table('firms')->where('id', $firm->id)->increment('profile_views');
        $firm->profile_views = (int) $firm->profile_views + 1;

        return true;
    }

    /** +1 on the listing column and on today's row (Europe/Belgrade). */
    public static function increment(Listing $listing, string $column): void
    {
        DB::table('listings')->where('id', $listing->id)->increment($column);
        $listing->setAttribute($column, (int) $listing->getAttribute($column) + 1);
        $listing->syncOriginalAttribute($column);

        $date = now(Text::TIMEZONE)->toDateString();
        DB::table('listing_daily_stats')->upsert(
            [['listing_id' => $listing->id, 'date' => $date, $column => 1]],
            ['listing_id', 'date'],
            [$column => DB::raw($column.' + 1')],
        );
    }

    /**
     * Totals for the last N days over all listings of a user (firm statistics).
     *
     * @return array{views: int, phone_reveals: int, messages: int, favorites: int}
     */
    public static function totalsForUser(int $userId, int $days = 30): array
    {
        $from = now(Text::TIMEZONE)->subDays($days - 1)->toDateString();
        $row = DB::table('listing_daily_stats as s')
            ->join('listings as l', 'l.id', '=', 's.listing_id')
            ->where('l.user_id', $userId)
            ->where('s.date', '>=', $from)
            ->selectRaw('COALESCE(SUM(s.views), 0) as views, COALESCE(SUM(s.phone_reveals), 0) as phone_reveals, COALESCE(SUM(s.messages), 0) as messages, COALESCE(SUM(s.favorites), 0) as favorites')
            ->first();

        return [
            'views' => (int) ($row->views ?? 0),
            'phone_reveals' => (int) ($row->phone_reveals ?? 0),
            'messages' => (int) ($row->messages ?? 0),
            'favorites' => (int) ($row->favorites ?? 0),
        ];
    }

    private static function rememberInSession(Request $request, string $key, int $id): bool
    {
        if (! $request->hasSession()) {
            return true;
        }
        $seen = (array) $request->session()->get($key, []);
        if (in_array($id, $seen, true)) {
            return false;
        }
        // Keep the session small: the last 200 IDs are enough.
        $seen[] = $id;
        $request->session()->put($key, array_slice($seen, -200));

        return true;
    }
}
