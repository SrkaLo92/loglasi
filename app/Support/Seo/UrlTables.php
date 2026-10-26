<?php

namespace App\Support\Seo;

use App\Models\GoneUrl;
use App\Models\Redirect;
use Illuminate\Support\Facades\Cache;

/**
 * The `redirects` and `gone_urls` tables as cached maps (read on every request).
 * The cache is cleared when a row changes (SeoServiceProvider).
 */
class UrlTables
{
    private const REDIRECTS_KEY = 'seo.redirects.map';

    private const GONE_KEY = 'seo.gone.map';

    /** Comparison key: normalized path, decoded, lower case, with the query minus tracking. */
    public static function key(string $path, array $query = []): string
    {
        $key = mb_strtolower(Url::normalizePath(rawurldecode($path)));
        $query = Url::withoutTracking($query);

        if ($query !== []) {
            ksort($query);
            $key .= '?'.http_build_query($query);
        }

        return $key;
    }

    /** Key from a table row ("/stari-slug/", "/?p=123", "https://loglasi.rs/x"). */
    public static function keyFromStored(string $stored): string
    {
        $parts = parse_url(trim($stored));
        $query = [];
        parse_str($parts['query'] ?? '', $query);

        return static::key($parts['path'] ?? '/', $query);
    }

    /** @return array<string, array{id: int, to: string, code: int}> */
    public static function redirects(): array
    {
        return Cache::rememberForever(self::REDIRECTS_KEY, function () {
            $map = [];

            foreach (Redirect::query()->get(['id', 'from_path', 'to_path', 'status_code']) as $row) {
                $map[static::keyFromStored($row->from_path)] = [
                    'id' => $row->id,
                    'to' => $row->to_path,
                    'code' => in_array((int) $row->status_code, [301, 302, 307, 308], true) ? (int) $row->status_code : 301,
                ];
            }

            return $map;
        });
    }

    /** @return array<string, int> path => id */
    public static function gone(): array
    {
        return Cache::rememberForever(self::GONE_KEY, function () {
            $map = [];

            foreach (GoneUrl::query()->get(['id', 'path']) as $row) {
                $map[static::keyFromStored($row->path)] = $row->id;
            }

            return $map;
        });
    }

    /** @return array{id: int, to: string, code: int}|null */
    public static function findRedirect(string $path, array $query = []): ?array
    {
        $map = static::redirects();

        return $map[static::key($path, $query)] ?? $map[static::key($path)] ?? null;
    }

    public static function findGone(string $path): ?int
    {
        return static::gone()[static::key($path)] ?? null;
    }

    public static function flushRedirects(): void
    {
        Cache::forget(self::REDIRECTS_KEY);
    }

    public static function flushGone(): void
    {
        Cache::forget(self::GONE_KEY);
    }
}
