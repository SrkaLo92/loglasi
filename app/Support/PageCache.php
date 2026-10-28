<?php

namespace App\Support;

use App\Support\Listing\Stats;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Store of whole pages for guests (see GuestPageCache). An entry is the page with the request's CSRF
 * token and CSP nonce swapped for placeholders, plus the counters to run on every hit (a listing view
 * is still counted when the page comes from the cache).
 */
class PageCache
{
    public const TOKEN = '__LG_CSRF_TOKEN__';

    public const NONCE = '__LG_CSP_NONCE__';

    private const FLUSHED_KEY = 'page_cache.flushed_at';

    /** @var list<array{0: string, 1: int}> counters registered while the page was built */
    private static array $hits = [];

    /** A listing view or firm profile view that must also be counted when the page is served from the cache. */
    public static function onHit(string $type, int $id): void
    {
        self::$hits[] = [$type, $id];
    }

    /** @return list<array{0: string, 1: int}> */
    public static function takeHits(): array
    {
        [$hits, self::$hits] = [self::$hits, []];

        return $hits;
    }

    /** Pages built before now are not served again (content changed); the keys stay, so no files pile up. */
    public static function flush(): void
    {
        Cache::forever(self::FLUSHED_KEY, microtime(true));
    }

    public static function key(Request $request): string
    {
        return 'page_cache.'.sha1($request->getSchemeAndHttpHost().$request->getPathInfo());
    }

    /** @return array{at: float, status: int, headers: array<string, mixed>, body: string, hits: list<array{0: string, 1: int}>}|null */
    public static function get(Request $request): ?array
    {
        $entry = Cache::get(self::key($request));
        if (! is_array($entry) || $entry['at'] < (float) Cache::get(self::FLUSHED_KEY, 0)) {
            return null;
        }
        $raw = base64_decode((string) $entry['body'], true);
        $body = $raw === false ? false : gzdecode($raw);

        return $body === false ? null : ['body' => $body] + $entry;
    }

    /** @param  list<array{0: string, 1: int}>  $hits */
    public static function put(Request $request, int $status, array $headers, string $body, array $hits): void
    {
        // Base64: the database cache store keeps values in a text column, which rejects raw gzip bytes.
        // A failed write only means the next visitor renders the page again.
        try {
            Cache::put(self::key($request), [
                'at' => microtime(true),
                'status' => $status,
                'headers' => $headers,
                'body' => base64_encode((string) gzencode($body, 6)),
                'hits' => $hits,
            ], (int) config('page_cache.ttl', 120));
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /** Counts the views of a cached page for this visitor: once per session, no bots, no database. */
    public static function count(array $hits, Request $request): void
    {
        if ($hits === [] || Stats::isBot($request->userAgent())) {
            return;
        }
        foreach ($hits as [$type, $id]) {
            $key = $type === 'firm' ? Stats::SESSION_PROFILES : Stats::SESSION_VIEWS;
            if (in_array($type, ['listing', 'firm'], true) && Stats::rememberInSession($request, $key, $id)) {
                Stats::queueView($type, $id);
            }
        }
    }
}
