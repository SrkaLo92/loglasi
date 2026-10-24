<?php

namespace App\Support\Home;

use App\Models\Category;
use App\Services\Catalog;
use Illuminate\Support\Facades\Cache;

/**
 * Footer column from the old site: the biggest categories.
 * Plain values cached for 60 s and cleared together with the home sections.
 */
class FooterSections
{
    public const CACHE_KEY = 'footer.sections';

    public const TTL = 60;

    public const CATEGORIES = 5;

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /** @return array{categories: list<array{name: string, url: string}>} */
    public static function get(): array
    {
        return Cache::remember(self::CACHE_KEY, self::TTL, fn () => self::build());
    }

    private static function build(): array
    {
        $catalog = app(Catalog::class);
        $counts = $catalog->counts();

        $categories = $catalog->children(null)
            ->filter(fn (Category $c) => ($counts[$c->id] ?? 0) > 0)
            ->sortByDesc(fn (Category $c) => $counts[$c->id])
            ->take(self::CATEGORIES)
            ->map(fn (Category $c) => ['name' => $c->name, 'url' => $c->url()])
            ->values()->all();

        return ['categories' => $categories];
    }
}
