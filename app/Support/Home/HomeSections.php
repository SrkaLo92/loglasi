<?php

namespace App\Support\Home;

use App\Enums\AccountType;
use App\Enums\UserStatus;
use App\Models\Firm;
use App\Services\Catalog;
use App\Services\ListingSearch;
use App\Support\Firms\FirmDirectory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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
        FooterSections::flush();
    }

    /**
     * Sections ready for the view. The cache holds only ids and plain values (models are
     * never serialized); listings of all sections are then loaded with one query.
     *
     * @return array<string, mixed>
     */
    public static function get(): array
    {
        $data = Cache::remember(self::CACHE_KEY, self::TTL, fn () => (new self)->build());

        return (new self)->hydrate($data);
    }

    /** @return array<string, mixed> plain data for the cache */
    public function build(): array
    {
        $counts = $this->catalog->counts();

        // A listing in the Premium section is not repeated among the featured ones.
        $premium = $this->premium(HomeSettings::int('home.premium_count', 1, 8));
        $featuredCount = HomeSettings::int('home.featured_count', 1, 12);
        $featured = array_values(array_diff($this->search->ids(['featured_only' => true], $featuredCount + count($premium)), $premium));

        return [
            'total' => $this->search->count([]),
            'categories_total' => count(array_filter($counts)),
            'premium' => $premium,
            'featured' => array_slice($featured, 0, $featuredCount),
            'latest' => $this->search->ids(['featured_first' => false], HomeSettings::int('home.latest_count', 1, 24)),
            'tabs' => $this->tabs($counts),
            'firms' => $this->firms(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function hydrate(array $data): array
    {
        $data['premium'] ??= []; // cached before the section existed
        $ids = array_merge($data['premium'], $data['featured'], $data['latest'], ...array_column($data['tabs'], 'ids'));
        $listings = $this->search->listings(array_values(array_unique($ids)))->keyBy('id');
        $pick = fn (array $ids) => (new Collection(array_map(fn (int $id) => $listings->get($id), $ids)))->filter()->values();

        // Premium XL + početna ended within the cache minute: left out.
        $data['premium'] = $pick($data['premium'])->filter(fn ($l) => $l->isPremiumHome())->values();
        $data['featured'] = $pick($data['featured']);
        $data['latest'] = $pick($data['latest']);
        $data['tabs'] = array_values(array_filter(array_map(function (array $tab) use ($pick) {
            $category = $this->catalog->category($tab['id']);
            $items = $pick($tab['ids']);

            return $category && $items->isNotEmpty() ? ['category' => $category, 'count' => $tab['count'], 'listings' => $items] : null;
        }, $data['tabs'])));

        return $data;
    }

    /**
     * "Premium XL + početna": active listings with a running premium_home_until, a random
     * $limit of them for each cache period (so all of them take turns). Index premium_home_until.
     *
     * @return list<int>
     */
    private function premium(int $limit): array
    {
        $ids = DB::table('listings')
            ->join('listing_search', 'listing_search.id', '=', 'listings.id') // active listings only
            ->where('listings.premium_home_until', '>', now())
            ->orderByDesc('listings.premium_home_until')
            ->limit(200)
            ->pluck('listings.id')->map(fn ($id) => (int) $id)->all();

        shuffle($ids);

        return array_slice($ids, 0, $limit);
    }

    /** @return list<array{id: int, count: int, ids: list<int>}> */
    private function tabs(array $counts): array
    {
        $tabs = [];
        foreach (array_slice(HomeSettings::list('home.tabs_categories'), 0, 8) as $slug) {
            $category = $this->catalog->categoryBySlug($slug);
            if ($category === null || ! $this->catalog->isVisible($category) || ($counts[$category->id] ?? 0) < 1) {
                continue;
            }
            $ids = $this->search->ids(['category' => $category->id], HomeSettings::int('home.tabs_count', 1, 12));
            if ($ids !== []) {
                $tabs[] = ['id' => $category->id, 'count' => $counts[$category->id], 'ids' => $ids];
            }
        }

        return $tabs;
    }

    /**
     * Firm strip: active firm accounts, those with a Biznis package first.
     *
     * @return array{total: int, items: list<array{name: string, url: string, logo: ?string, initials: string}>}
     */
    /**
     * Firm cards: active firm accounts, firms with a package first, with their place and number of active listings.
     *
     * @return array{total: int, items: list<array<string, mixed>>}
     */
    private function firms(): array
    {
        $query = Firm::query()->whereHas('user', fn ($u) => $u
            ->where('account_type', AccountType::Firm)->where('status', UserStatus::Active));
        $total = (clone $query)->count();

        if ($total === 0 || $total < HomeSettings::int('home.firms_min', 1, 100)) {
            return ['total' => $total, 'items' => []];
        }

        $firms = $query->with('user:id,slug')
            ->orderByRaw('(package_ends_at IS NOT NULL AND package_ends_at > ?) DESC', [now()])
            ->orderByDesc('id')
            ->limit(HomeSettings::int('home.firms_max', 1, 24))
            ->get(['id', 'user_id', 'name', 'activity', 'location_id', 'logo_path', 'cover_path']);

        // One grouped query for all cards (listing_search holds only visible listings).
        $counts = DB::table('listing_search')
            ->whereIn('user_id', $firms->pluck('user_id')->all())
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->groupBy('user_id')
            ->pluck(DB::raw('COUNT(*)'), 'user_id');

        $items = $firms->map(fn (Firm $f) => [
            'name' => $f->name,
            'url' => url_slash('/korisnik/'.$f->user->slug),
            'logo' => self::storageUrl($f->logo_path),
            'cover' => self::storageUrl($f->cover_path),
            'initials' => self::initials($f->name),
            'activity' => trim((string) $f->activity),
            'place' => $f->location_id ? (string) $this->catalog->location((int) $f->location_id)?->name : '',
            'count' => (int) ($counts[$f->user_id] ?? 0),
        ])->values()->all();

        // Same number as the /firme/ directory (firms with a package), cached there.
        return ['total' => $total, 'items' => $items, 'directory_total' => count(FirmDirectory::all())];
    }

    private static function storageUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        return preg_match('~^https?://~i', $path) ? $path : Storage::disk('public')->url($path);
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
