<?php

namespace App\Support\Info;

use Illuminate\Http\Request;

/** Absolute links for the info portal (its own host) and back to the classifieds site. */
final class InfoUrl
{
    public static function host(): string
    {
        return (string) config('info.domain');
    }

    public static function base(): string
    {
        $scheme = parse_url((string) config('info.main_url'), PHP_URL_SCHEME) ?: 'https';

        return $scheme.'://'.self::host();
    }

    /** info.loglasi.rs/<path>/ with the trailing slash the site uses everywhere. */
    public static function to(string $path = '/'): string
    {
        $path = trim($path, '/');

        return self::base().'/'.($path === '' ? '' : $path.'/');
    }

    /** loglasi.rs/<path>/ */
    public static function main(string $path = '/'): string
    {
        $path = trim($path, '/');

        return rtrim((string) config('info.main_url'), '/').'/'.($path === '' ? '' : $path.'/');
    }

    public static function isInfoRequest(?Request $request = null): bool
    {
        $request ??= request();

        return config('info.enabled') && strcasecmp($request->getHost(), self::host()) === 0;
    }
}
