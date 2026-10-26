<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * Site settings changed from the admin (exchange rate, limits, texts...).
 * All values are read from one cached array.
 */
class Settings
{
    private const CACHE_KEY = 'settings.all';

    /** Default values when the database has no row. */
    public const DEFAULTS = [
        'eur_rate' => 117.2,               // 1 € = ... din
        'private_active_limit' => 10,      // active listings a private user may have for free
        'listing_duration_days' => 30,     // a listing lasts a month, renewal is free
        'moderation' => 'all',             // all | new_users | none (all: every listing waits for an admin)
        'founding_firms_total' => 30,      // first 30 firms free
        'contact_email' => 'office@loglasi.rs',
        'contact_phone' => '+381655415128',     // footer and FAQ, E.164
        'contact_address' => '15300 Loznica, Srbija',
        'new_condition_firms_only' => true,
        // Google: tag (gtag.js, production only, after cookie consent) and Search Console meta tag.
        'google_tag_id' => 'GT-MKTJD4NV',         // Google tag, destination GA4 G-EKVEVJTR90
        'ga4_measurement_id' => 'G-EKVEVJTR90',
        'google_site_verification' => '',         // content of <meta name="google-site-verification">
    ];

    public static function get(string $key, mixed $default = null): mixed
    {
        $all = static::all();

        return array_key_exists($key, $all) ? $all[$key] : ($default ?? (self::DEFAULTS[$key] ?? null));
    }

    public static function set(string $key, mixed $value): void
    {
        Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget(self::CACHE_KEY);
    }

    /** @return array<string, mixed> */
    public static function all(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, fn () => Setting::query()->pluck('value', 'key')->all());
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
