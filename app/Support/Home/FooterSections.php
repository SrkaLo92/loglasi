<?php

namespace App\Support\Home;

use App\Models\Category;
use App\Models\Listing;
use App\Services\Catalog;
use App\Services\ListingSearch;
use Illuminate\Support\Facades\Cache;

/**
 * Footer columns from the old site: the biggest categories and the newest listings.
 * Plain values cached for 60 s and cleared together with the home sections.
 * The newest listings are loaded by the page with a small JSON request, so listing
 * titles never end up in the HTML of every page.
 */
class FooterSections
{
    public const CACHE_KEY = 'footer.sections';

    public const TTL = 60;

    public const CATEGORIES = 5;

    public const LATEST_KEY = 'footer.latest';

    public const LATEST = 4;

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
        Cache::forget(self::LATEST_KEY);
    }

    /** @return list<array{title: string, price: string, url: string}> */
    public static function latest(): array
    {
        return Cache::remember(self::LATEST_KEY, self::TTL, function () {
            $search = app(ListingSearch::class);

            return $search->listings($search->ids(['featured_first' => false], self::LATEST))
                ->map(fn (Listing $l) => ['title' => (string) $l->title, 'price' => $l->priceLabel(), 'url' => '/oglas/'.$l->slug.'/'])
                ->values()->all();
        });
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
