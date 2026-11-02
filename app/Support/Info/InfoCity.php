<?php

namespace App\Support\Info;

/**
 * The city the portal shows. For now always the default (INFO_CITY); a city switch (URL prefix or
 * choice remembered in a cookie) can later set it, and every info query already follows it.
 */
final class InfoCity
{
    private static ?string $current = null;

    public static function current(): string
    {
        return self::$current ?? (string) config('info.city', 'loznica');
    }

    public static function set(?string $city): void
    {
        self::$current = $city !== null && isset(config('info.cities')[$city]) ? $city : null;
    }

    /** Filtering starts only once the portal covers more than one city. */
    public static function filtering(): bool
    {
        return count((array) config('info.cities')) > 1;
    }

    public static function name(?string $city = null): string
    {
        return config('info.cities.'.($city ?? self::current()).'.name') ?? 'Loznica';
    }
}
