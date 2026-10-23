<?php

namespace App\Support\Home;

use App\Enums\AccountType;
use App\Enums\UserStatus;
use App\Models\Category;
use App\Models\Firm;
use App\Services\Catalog;
use App\Services\ListingSearch;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * Data of the home page sections (ported from loglasi-core inc/home), cached for 60 s.
 * The cache is cleared on any change to listings, images, categories or firms
 * (SearchServiceProvider), so new listings show at once.
 */
class HomeSections
{
    public const CACHE_KEY = 'home.sections';

    public const TTL = 60;

    private Catalog $catalog;

    private ListingSearch $search;

    public function __construct()
    {
        $this->catalog = app(Catalog::class);
        $this->search = app(ListingSearch::class);
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /** @return array<string, mixed> */
    public static function get(): array
    {
        return Cache::remember(self::CACHE_KEY, self::TTL, fn () => (new self)->build());
    }

    /** @return array<string, mixed> */
    public function build(): array
    {
        $counts = $this->catalog->counts();

        return [
            'total' => $this->search->count([]),
            'chips' => $this->chips($counts),
            'categories' => $this->categories($counts),
            'categories_total' => count(array_filter($counts)),
            'featured' => $this->search->take(['featured_only' => true], HomeSettings::int('home.featured_count', 1, 12)),
            'latest' => $this->search->take([], HomeSettings::int('home.latest_count', 1, 24)),
            'tabs' => $this->tabs($counts),
            'firms' => $this->firms(),
        ];
    }

    /**
     * „Često se traži”: terms from the settings (a term that is a category name or slug
     * links to the category, others to the search), or the biggest categories without children.
     *
     * @return list<array{label: string, url: string}>
     */
    private function chips(array $counts): array
    {
        $terms = HomeSettings::list('home.intro_terms');

        if ($terms !== []) {
            $chips = [];
            foreach (array_slice($terms, 0, 12) as $term) {
                $slug = str($term)->slug()->value();
                $category = $this->catalog->categories()->first(fn (Category $c) => $c->slug === $slug
                    || mb_strtolower($c->name) === mb_strtolower($term));
                $chips[] = [
                    'label' => $term,
                    'url' => $category && $this->catalog->isVisible($category)
                        ? $category->url()
                        : url_slash('/oglasi').'?'.http_build_query(['keyword' => $term]),
                ];
            }

            return $chips;
        }

        $parents = $this->catalog->categories()->pluck('parent_id')->filter()->flip();

        return $this->catalog->categories()
            ->filter(fn (Category $c) => ($counts[$c->id] ?? 0) > 0 && $this->catalog->isVisible($c))
            ->sortBy([
                fn (Category $a, Category $b) => isset($parents[$a->id]) <=> isset($parents[$b->id]),
                fn (Category $a, Category $b) => ($counts[$b->id] ?? 0) <=> ($counts[$a->id] ?? 0),
                fn (Category $a, Category $b) => strcmp($a->name, $b->name),
            ])
            ->take(HomeSettings::int('home.intro_terms_count', 0, 12))
            ->map(fn (Category $c) => ['label' => $c->name, 'url' => $c->url()])
            ->values()->all();
    }

    /** @return list<array{category: Category, count: int}> top categories with the most listings */
    private function categories(array $counts): array
    {
        return $this->catalog->children(null)
            ->filter(fn (Category $c) => ($counts[$c->id] ?? 0) > 0)
            ->sortBy([
                fn (Category $a, Category $b) => $counts[$b->id] <=> $counts[$a->id],
                fn (Category $a, Category $b) => strcmp($a->name, $b->name),
            ])
            ->take(HomeSettings::int('home.categories_count', 1, 47))
            ->map(fn (Category $c) => ['category' => $c, 'count' => $counts[$c->id]])
            ->values()->all();
    }

    /** @return list<array{category: Category, count: int, listings: Collection}> */
    private function tabs(array $counts): array
    {
        $tabs = [];
        foreach (array_slice(HomeSettings::list('home.tabs_categories'), 0, 8) as $slug) {
            $category = $this->catalog->categoryBySlug($slug);
            if ($category === null || ! $this->catalog->isVisible($category) || ($counts[$category->id] ?? 0) < 1) {
                continue;
            }
            $listings = $this->search->take(['category' => $category->id], HomeSettings::int('home.tabs_count', 1, 12));
            if ($listings->isNotEmpty()) {
                $tabs[] = ['category' => $category, 'count' => $counts[$category->id], 'listings' => $listings];
            }
        }

        return $tabs;
    }

    /**
     * Firm strip: active firm accounts, those with a Biznis package first.
     *
     * @return array{total: int, items: list<array{name: string, url: string, logo: ?string, initials: string}>}
     */
    private function firms(): array
    {
        $query = Firm::query()->whereHas('user', fn ($u) => $u
            ->where('account_type', AccountType::Firm)->where('status', UserStatus::Active));
        $total = (clone $query)->count();

        if ($total < HomeSettings::int('home.firms_min', 1, 100)) {
            return ['total' => $total, 'items' => []];
        }

        $items = $query->with('user:id,slug')
            ->orderByRaw('(biznis_until IS NOT NULL AND biznis_until > ?) DESC', [now()])
            ->orderByDesc('id')
            ->limit(HomeSettings::int('home.firms_max', 1, 24))
            ->get(['id', 'user_id', 'name', 'logo_path'])
            ->map(fn (Firm $f) => [
                'name' => $f->name,
                'url' => url_slash('/korisnik/'.$f->user->slug),
                'logo' => $f->logo_path ? (preg_match('~^https?://~i', $f->logo_path) ? $f->logo_path : Storage::disk('public')->url($f->logo_path)) : null,
                'initials' => self::initials($f->name),
            ])->all();

        return ['total' => $total, 'items' => $items];
    }

    public static function initials(string $name): string
    {
        $out = '';
        foreach (array_slice(preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [], 0, 2) as $word) {
            $out .= mb_strtoupper(mb_substr($word, 0, 1));
        }

        return $out !== '' ? $out : '?';
    }
}
