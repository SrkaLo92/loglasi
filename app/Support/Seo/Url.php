<?php

namespace App\Support\Seo;

use Illuminate\Support\Str;

/** Helpers for paths and parameters: trailing slash, tracking (utm), canonical domain. */
class Url
{
    /** Whether the parameter is for tracking (utm_*, fbclid, gclid...) and does not change the page content. */
    public static function isTracking(string $key): bool
    {
        return Str::is(config('seo.tracking_params', []), strtolower($key));
    }

    /**
     * Parameters without the tracking ones.
     *
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public static function withoutTracking(array $query): array
    {
        return array_filter($query, fn ($key) => ! static::isTracking((string) $key), ARRAY_FILTER_USE_KEY);
    }

    /** Whether the last part of the path looks like a file (sitemap.xml, slika.jpg). */
    public static function hasExtension(string $path): bool
    {
        $last = (string) Str::afterLast(rtrim($path, '/'), '/');

        return (bool) preg_match('/\.[A-Za-z0-9]{1,8}$/', $last);
    }

    /** Path with a slash at the start and the end (except for files): /oglasi/automobili/ */
    public static function normalizePath(string $path): string
    {
        $path = '/'.ltrim($path, '/');

        if ($path === '/' || static::hasExtension($path)) {
            return $path;
        }

        return rtrim($path, '/').'/';
    }

    /** Absolute URL on the canonical domain (https://loglasi.rs/...). */
    public static function canonical(string $pathOrUrl, array $query = []): string
    {
        if (preg_match('#^https?://#i', $pathOrUrl)) {
            $url = static::rebase($pathOrUrl);
        } else {
            $url = config('seo.base_url').static::normalizePath($pathOrUrl);
        }

        return $query === [] ? $url : $url.'?'.http_build_query($query);
    }

    /**
     * A URL on the app's own domain (APP_URL, e.g. from url_slash()) moves to the canonical
     * domain, so every page type gives the same host; other domains stay as they are.
     */
    public static function rebase(string $url): string
    {
        $parts = parse_url($url);
        $own = parse_url((string) config('app.url'), PHP_URL_HOST);
        $base = parse_url((string) config('seo.base_url'), PHP_URL_HOST);
        $host = $parts['host'] ?? null;

        if ($host === null || $host === $base || ($host !== $own && $host !== request()->getHost())) {
            return $url;
        }

        return config('seo.base_url').static::normalizePath($parts['path'] ?? '/').(isset($parts['query']) ? '?'.$parts['query'] : '');
    }

    /** Redirect target: absolute URL on the request's domain (so a local check does not jump to the live site). */
    public static function target(string $root, string $pathOrUrl, array $query = []): string
    {
        if (preg_match('#^https?://#i', $pathOrUrl)) {
            $url = $pathOrUrl;
        } else {
            [$path, $own] = array_pad(explode('?', $pathOrUrl, 2), 2, null);
            $url = rtrim($root, '/').static::normalizePath($path);

            if ($own !== null && $own !== '') {
                parse_str($own, $ownQuery);
                $query = array_merge($ownQuery, $query);
            }
        }

        return $query === [] ? $url : $url.(str_contains($url, '?') ? '&' : '?').http_build_query($query);
    }
}
