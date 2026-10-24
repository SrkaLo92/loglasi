<?php

namespace App\Services;

use App\Enums\AccountType;
use App\Enums\Currency;
use App\Models\Firm;
use App\Models\Listing;
use App\Models\SearchEntry;
use App\Support\Price;
use App\Support\Search\SearchText;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Listing search shared by the web lists and the mobile API (GET /listings).
 *
 * Parameters are the ones of the API contract (q, category, location, price_min,
 * price_max, currency, seller_type, firm, f[...], sort, page, per_page):
 *
 *   $params = $search->validate($request->query());   // ValidationException with Serbian messages
 *   $page = $search->paginate($params);                // LengthAwarePaginator, card relations loaded
 *   $query = $search->query($params);                  // Builder, filtered and ordered
 *
 * Order: promoted listings first (rank: TOP 3, priority 2, highlighted 1), then the chosen sort.
 * Only active listings: published, not deleted, not expired. See docs/moduli/search.md.
 */
class ListingSearch
{
    public const PER_PAGE_DEFAULT = 20;

    public const PER_PAGE_MAX = 50;

    public const SORTS = ['newest', 'price_asc', 'price_desc', 'relevance'];

    public const Q_MAX = 100;

    /** Deepest offset served; later pages are empty (deep OFFSET is slow on a big table). */
    public const MAX_OFFSET = 10000;

    /** Seconds a total count is cached (also cleared by any index change). */
    private const COUNT_TTL = 60;

    public function __construct(
        private readonly Catalog $catalog,
        private readonly ListingFields $fields,
    ) {}

    /** Relations a listing card needs (resources/views/components/listing-card.blade.php). */
    public static function cardRelations(): array
    {
        return [
            'category:id,parent_id,name,slug',
            'location:id,name,slug',
            'coverImage',
            'user:id,name,slug,account_type',
        ];
    }

    /**
     * Checks the parameters the way the WordPress API did and returns them normalized.
     * All errors at once, under the parameter name.
     *
     * @return array{q: ?string, category: ?int, location: ?int, price_min: ?int, price_max: ?int, currency: Currency, seller_type: ?string, firm: ?int, filters: array, sort: string, page: int, per_page: int}
     *
     * @throws ValidationException
     */
    public function validate(array $input): array
    {
        $errors = [];
        $params = self::defaults();

        if (array_key_exists('q', $input) && $input['q'] !== null) {
            if (! is_string($input['q']) || mb_strlen($input['q']) > self::Q_MAX) {
                $errors['q'] = 'Pojam za pretragu može imati najviše 100 znakova.';
            } else {
                $q = trim(strip_tags($input['q']));
                $params['q'] = $q === '' ? null : $q;
            }
        }

        foreach (['category', 'location', 'firm'] as $key) {
            if (! $this->present($input, $key)) {
                continue;
            }
            if (is_scalar($input[$key]) && preg_match('/^[1-9]\d{0,9}$/', (string) $input[$key])) {
                $params[$key] = (int) $input[$key];
            } else {
                $errors[$key] = 'Mora biti pozitivan ceo broj.';
            }
        }

        foreach (['price_min', 'price_max'] as $key) {
            if (! $this->present($input, $key)) {
                continue;
            }
            if (is_scalar($input[$key]) && preg_match('/^\d{1,12}$/', (string) $input[$key])) {
                $params[$key] = (int) $input[$key];
            } else {
                $errors[$key] = 'Cena mora biti ceo broj, nula ili veći.';
            }
        }

        if ($params['price_min'] !== null && $params['price_max'] !== null && $params['price_min'] > $params['price_max']) {
            $errors['price_min'] = 'Najniža cena ne može biti veća od najviše.';
        }

        if ($this->present($input, 'currency')) {
            $currency = is_string($input['currency']) ? Currency::tryFrom(strtoupper($input['currency'])) : null;
            if ($currency === null) {
                $errors['currency'] = 'Dozvoljene vrednosti su EUR i RSD.';
            } else {
                $params['currency'] = $currency;
            }
        }

        if ($this->present($input, 'seller_type')) {
            $type = $input['seller_type'];
            if (! is_string($type) || ! in_array($type, ['private', 'firm', 'business'], true)) {
                $errors['seller_type'] = 'Dozvoljene vrednosti su private i firm.';
            } else {
                // "business" is the Listivo name for a firm account.
                $params['seller_type'] = $type === 'private' ? 'private' : 'firm';
            }
        }

        if ($this->present($input, 'sort')) {
            if (! is_string($input['sort']) || ! in_array($input['sort'], self::SORTS, true)) {
                $errors['sort'] = 'Dozvoljene vrednosti su newest, price_asc, price_desc i relevance.';
            } else {
                $params['sort'] = $input['sort'];
            }
        }

        if ($this->present($input, 'page')) {
            if (is_scalar($input['page']) && preg_match('/^[1-9]\d{0,5}$/', (string) $input['page'])) {
                $params['page'] = (int) $input['page'];
            } else {
                $errors['page'] = 'Stranica mora biti ceo broj, 1 ili veći.';
            }
        }

        if ($this->present($input, 'per_page')) {
            $value = $input['per_page'];
            if (is_scalar($value) && preg_match('/^\d{1,3}$/', (string) $value) && (int) $value >= 1 && (int) $value <= self::PER_PAGE_MAX) {
                $params['per_page'] = (int) $value;
            } else {
                $errors['per_page'] = 'Broj oglasa po stranici mora biti od 1 do 50.';
            }
        }

        if (array_key_exists('f', $input) && $input['f'] !== null && $input['f'] !== '' && $input['f'] !== []) {
            // Fields of the category (and its parents). Without a valid category every filter is unknown.
            $category = isset($errors['category']) ? null : $params['category'];
            try {
                $params['filters'] = $this->fields->parseFilters($category, $input['f']);
            } catch (ValidationException $e) {
                $errors['f'] = (string) collect($e->errors())->flatten()->first();
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $params;
    }

    /** Parameters without any filter. */
    public static function defaults(): array
    {
        return [
            'q' => null,
            'category' => null,
            'location' => null,
            'price_min' => null,
            'price_max' => null,
            'currency' => Currency::EUR,
            'seller_type' => null,
            'firm' => null,
            'filters' => [],
            'featured_only' => false, // home page section, not an API parameter
            'featured_first' => true, // false: plain newest order (home page "latest" section)
            'sort' => 'newest',
            'page' => 1,
            'per_page' => self::PER_PAGE_DEFAULT,
        ];
    }

    /**
     * Filtered and ordered query on the search index (listing_search), selecting ids.
     * Each filter path has an index that also gives the sort order (see the migration).
     */
    public function query(array $params): Builder
    {
        $params += self::defaults();
        $query = SearchEntry::query()->select('listing_search.id');

        $this->applyCategory($query, $params['category']);

        if ($params['featured_only']) {
            $query->where('listing_search.featured', '>', 0);
        }

        if ($params['location'] !== null) {
            $query->where('listing_search.location_id', $params['location']);
        }

        $this->applyPrice($query, $params['price_min'], $params['price_max'], $params['currency']);
        $this->applySeller($query, $params['seller_type'], $params['firm']);

        if ($params['filters'] !== []) {
            // One EXISTS per field on listing_field_values (field_id, value / value_num indexes).
            $this->fields->apply($query, $params['filters']);
        }

        $relevance = $this->applyText($query, $params['q']);
        $this->applyOrder($query, $params['sort'], $relevance, $params['featured_first'] ?? true);

        return $query;
    }

    /**
     * One page of results: listings with the relations of the listing card.
     * Totals are cached until the index changes; pages deeper than MAX_OFFSET are empty.
     */
    public function paginate(array $params): LengthAwarePaginator
    {
        $params += self::defaults();
        $query = $this->query($params);
        $total = $this->count($params, $query);
        $offset = ($params['page'] - 1) * $params['per_page'];

        $ids = $total === 0 || $offset >= min($total, self::MAX_OFFSET + $params['per_page'])
            ? []
            : $query->offset($offset)->limit($params['per_page'])->pluck('listing_search.id')->map(fn ($id) => (int) $id)->all();

        return new Paginator($this->listings($ids), $total, $params['per_page'], $params['page'], [
            'path' => Paginator::resolveCurrentPath(),
            'pageName' => 'page',
        ]);
    }

    /** The first $limit results (home sections, similar listings). */
    public function take(array $params, int $limit): Collection
    {
        return $this->listings($this->ids($params, $limit));
    }

    /** @return list<int> ids of the first $limit results */
    public function ids(array $params, int $limit): array
    {
        return $this->query($params)->limit($limit)->pluck('listing_search.id')->map(fn ($id) => (int) $id)->all();
    }

    /** Number of results, cached for a minute and until any listing changes. */
    public function count(array $params, ?Builder $query = null): int
    {
        $params += self::defaults();
        $key = 'search.count.'.SearchIndex::version().'.'.md5(serialize([
            $params['q'], $params['category'], $params['location'], $params['price_min'], $params['price_max'],
            $params['currency']->value, $params['seller_type'], $params['firm'], $params['filters'], $params['featured_only'],
        ]));

        return (int) Cache::remember($key, self::COUNT_TTL, function () use ($params, $query) {
            $query ??= $this->query($params);

            return (clone $query)->toBase()->getCountForPagination();
        });
    }

    /**
     * Listings by id, in the given order, with the card relations.
     *
     * @param  list<int>  $ids
     */
    public function listings(array $ids): Collection
    {
        if ($ids === []) {
            return new Collection;
        }

        $position = array_flip($ids);

        return Listing::query()->whereKey($ids)->with(self::cardRelations())->get()
            ->sortBy(fn (Listing $l) => $position[$l->id])->values();
    }

    private function present(array $input, string $key): bool
    {
        return array_key_exists($key, $input) && $input[$key] !== null && $input[$key] !== '';
    }

    private function applyCategory(Builder $query, ?int $categoryId): void
    {
        if ($categoryId === null) {
            return;
        }

        $category = $this->catalog->category($categoryId);
        if ($category === null || ! $this->catalog->isVisible($category)) {
            $query->whereRaw('1 = 0');

            return;
        }

        $depth = $this->catalog->breadcrumbs($category)->count();
        if ($depth >= 1 && $depth <= 3) {
            $query->where('listing_search.cat'.$depth, $category->id);
        } else {
            $query->whereIn('listing_search.category_id', $this->catalog->descendantIds($category));
        }
    }

    /** Bounds are in $currency and compared with price_eur; "Dogovor" (no price) never matches. */
    private function applyPrice(Builder $query, ?int $min, ?int $max, Currency $currency): void
    {
        if ($min === null && $max === null) {
            return;
        }

        $query->where('listing_search.no_price', false);

        if ($min !== null) {
            $query->where('listing_search.price_eur', '>=', max(0.01, Price::toEur($min, $currency)));
        }
        if ($max !== null) {
            $query->where('listing_search.price_eur', '<=', Price::toEur($max, $currency));
        }
    }

    private function applySeller(Builder $query, ?string $sellerType, ?int $firmId): void
    {
        if ($firmId !== null) {
            $userId = Firm::query()->whereKey($firmId)
                ->whereHas('user', fn (Builder $u) => $u->where('account_type', AccountType::Firm))
                ->value('user_id');

            if ($userId === null || $sellerType === 'private') {
                $query->whereRaw('1 = 0');

                return;
            }

            $query->where('listing_search.user_id', $userId);

            return;
        }

        if ($sellerType !== null) {
            $query->where('listing_search.is_firm', $sellerType === 'firm');
        }
    }

    /**
     * Every word is required: FULLTEXT in boolean mode with prefix matching ("+golf*");
     * short words and stopwords match their padded copy ("+a4qqq", see SearchText).
     * Without MySQL/MariaDB (no FULLTEXT) words match the start of a word with LIKE.
     * Returns the relevance expression, or null.
     *
     * @return array{0: string, 1: string}|null [sql, binding]
     */
    private function applyText(Builder $query, ?string $q): ?array
    {
        $words = $q === null ? [] : SearchText::words($q);
        if ($words === []) {
            return null;
        }

        if (in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            $against = SearchText::booleanQuery($q);
            $sql = 'MATCH(listing_search.search_text) AGAINST(? IN BOOLEAN MODE)';
            $query->whereRaw($sql, [$against]);

            return [$sql, $against];
        }

        foreach ($words as $word) {
            // Words contain only letters and digits (SearchText), so there is nothing to escape.
            $query->where(fn (Builder $w) => $w
                ->where('listing_search.search_text', 'like', $word.'%')
                ->orWhere('listing_search.search_text', 'like', '% '.$word.'%'));
        }

        return null;
    }

    /** Featured first, then the chosen sort; every branch matches an index of listing_search. */
    private function applyOrder(Builder $query, string $sort, ?array $relevance, bool $featuredFirst = true): void
    {
        if (! $featuredFirst && $sort === 'newest') {
            $query->orderByDesc('listing_search.sort_at')->orderByDesc('listing_search.id'); // ls_newest

            return;
        }

        $query->orderByDesc('listing_search.featured');

        if ($sort === 'price_asc' || $sort === 'price_desc') {
            // "Dogovor" (no price) goes last.
            $query->orderBy('listing_search.no_price')
                ->orderBy('listing_search.price_eur', $sort === 'price_asc' ? 'asc' : 'desc')
                ->orderBy('listing_search.id', $sort === 'price_asc' ? 'asc' : 'desc');

            return;
        }

        if ($sort === 'relevance' && $relevance !== null) {
            $query->orderByRaw($relevance[0].' DESC', [$relevance[1]]);
        }

        $query->orderByDesc('listing_search.sort_at')->orderByDesc('listing_search.id');
    }
}
