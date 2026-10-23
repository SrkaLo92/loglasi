<?php

namespace App\Support\Stranice;

use App\Models\Category;
use App\Models\Location;
use Illuminate\Support\Facades\Cache;
use Throwable;

/** Podaci za zaglavlje i donju navigaciju (keširano, radi i kad baza nije dostupna). */
class Navigacija
{
    private const TTL = 600;

    public static function link(string $key): string
    {
        return url_slash((string) config('stranice.linkovi.'.$key, '/'));
    }

    /** @return array<int, array{slug: string, name: string}> Glavne aktivne kategorije za izbor u pretrazi. */
    public static function kategorije(): array
    {
        return self::safe('stranice.nav.kategorije', fn () => Category::query()
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
        return self::safe('stranice.nav.mesta', fn () => Location::query()
            ->orderBy('sort')
            ->orderBy('name')
            ->get(['slug', 'name'])
            ->map(fn (Location $l) => ['slug' => $l->slug, 'name' => $l->name])
            ->all());
    }

    /** Ključ aktivne stavke donje navigacije za trenutnu putanju. */
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
        Cache::forget('stranice.nav.kategorije');
        Cache::forget('stranice.nav.mesta');
    }

    /** @return array<int, array{slug: string, name: string}> */
    private static function safe(string $key, callable $callback): array
    {
        try {
            return Cache::remember($key, self::TTL, $callback);
        } catch (Throwable) {
            // Strana greške (500, 503) mora da se prikaže i bez baze.
            return [];
        }
    }
}
