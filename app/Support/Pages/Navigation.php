<?php

namespace App\Support\Pages;

use App\Models\Category;
use App\Models\Location;
use Illuminate\Support\Facades\Cache;
use Throwable;

/** Data for the header and bottom navigation (cached, works even when the database is down). */
class Navigation
{
    private const TTL = 600;

    public static function link(string $key): string
    {
        return url_slash((string) config('pages.links.'.$key, '/'));
    }

    /** @return array<int, array{slug: string, name: string}> Main active categories for the search picker. */
    public static function kategorije(): array
    {
        return self::safe('pages.nav.categories', fn () => Category::query()
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->orderBy('sort')
            ->orderBy('name')
            ->get(['slug', 'name'])
            ->map(fn (Category $c) => ['slug' => $c->slug, 'name' => $c->name])
            ->all());
    }

    /** @return array<int, array{slug: string, name: string}> */
    public static function mesta(): array
    {
        return self::safe('pages.nav.places', fn () => Location::query()
            ->orderBy('sort')
            ->orderBy('name')
            ->get(['slug', 'name'])
            ->map(fn (Location $l) => ['slug' => $l->slug, 'name' => $l->name])
            ->all());
    }

    /** Key of the active bottom navigation item for the current path. */
    public static function aktivna(string $path): string
    {
        $path = trim($path, '/');

        return match (true) {
            $path === '' => 'pocetna',
            str_starts_with($path, 'postavi-oglas') => 'postavi',
            str_starts_with($path, 'moj-nalog/poruke') => 'poruke',
            str_starts_with($path, 'moj-nalog'), str_starts_with($path, 'login-registracija') => 'nalog',
            $path === 'oglasi', str_starts_with($path, 'oglasi/'), str_starts_with($path, 'oglas/') => 'pretraga',
            default => '',
        };
    }

    public static function forget(): void
    {
        Cache::forget('pages.nav.categories');
        Cache::forget('pages.nav.places');
    }

    /** @return array<int, array{slug: string, name: string}> */
    private static function safe(string $key, callable $callback): array
    {
        try {
            return Cache::remember($key, self::TTL, $callback);
        } catch (Throwable) {
            // An error page (500, 503) must render without the database too.
            return [];
        }
    }
}
