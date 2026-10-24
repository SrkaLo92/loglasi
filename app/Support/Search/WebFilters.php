<?php

namespace App\Support\Search;

use App\Enums\Currency;
use App\Models\Category;
use App\Models\Location;
use App\Services\Catalog;
use App\Services\ListingFields;
use App\Services\ListingSearch;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Search state of a web list (/oglasi/, /oglasi/{kategorija}/, /oglasi/{kategorija}/{mesto}/).
 *
 * Public query names (as the header form and the old site): keyword (also s, q),
 * kategorija, mesto, cena_od, cena_do, valuta, prodavac (privatno|firma), sortiranje,
 * f[ključ]. They are mapped to ListingSearch parameters. Invalid values are dropped,
 * never shown as an error page.
 */
class WebFilters
{
    public const PER_PAGE = 24;

    /** sortiranje => ListingSearch sort */
    public const SORTS = [
        'najnovije' => 'newest',
        'cena-rastuce' => 'price_asc',
        'cena-opadajuce' => 'price_desc',
        'relevantnost' => 'relevance',
    ];

    public const SORT_LABELS = [
        'najnovije' => 'Najnovije prvo',
        'cena-rastuce' => 'Najniža cena',
        'cena-opadajuce' => 'Najviša cena',
        'relevantnost' => 'Najrelevantnije',
    ];

    /** Query keys this page understands (others are ignored, kept out of links). */
    private const KEYS = ['keyword', 'kategorija', 'mesto', 'cena_od', 'cena_do', 'valuta', 'prodavac', 'sortiranje', 'f'];

    /** Clean query (no empty values, no path parts). */
    public array $query = [];

    /** ListingSearch parameters. */
    public array $params = [];

    /** True when some field filters were dropped as invalid. */
    public bool $droppedFilters = false;

    public function __construct(
        public readonly ?Category $category,
        public readonly ?Location $location,
        public readonly int $page = 1,
    ) {}

    /**
     * Reads the request. Returns the filters, or a URL to redirect to when the
     * request can be written cleaner (empty fields, kategorija/mesto that belong in the path).
     */
    public static function fromRequest(Request $request, ?Category $category, ?Location $location, int $page): self|string
    {
        $catalog = app(Catalog::class);
        $raw = $request->query();
        $query = [];

        // WordPress search (?s=) and the app name (?q=) mean the keyword.
        foreach (['keyword', 's', 'q'] as $alias) {
            if (! isset($query['keyword']) && is_string($raw[$alias] ?? null) && trim($raw[$alias]) !== '') {
                $query['keyword'] = mb_substr(trim($raw[$alias]), 0, ListingSearch::Q_MAX);
            }
        }

        foreach (['kategorija', 'mesto', 'cena_od', 'cena_do', 'valuta', 'prodavac', 'sortiranje'] as $key) {
            if (is_string($raw[$key] ?? null) && trim($raw[$key]) !== '') {
                $query[$key] = trim($raw[$key]);
            }
        }

        if (is_array($raw['f'] ?? null)) {
            foreach ($raw['f'] as $key => $value) {
                // Checkboxes of a GET form arrive as f[key][]: join them as "a,b".
                $value = is_array($value) ? implode(',', array_filter(array_map(fn ($v) => is_scalar($v) ? trim((string) $v) : '', $value), 'strlen')) : $value;
                if (is_string($key) && is_scalar($value) && trim((string) $value) !== '') {
                    $query['f'][$key] = trim((string) $value);
                }
            }
        }

        // Category and place from the query go to the path when they can.
        $moved = false;
        if (isset($query['kategorija'])) {
            $fromQuery = $catalog->categoryBySlug($query['kategorija']);
            if ($fromQuery !== null && $catalog->isVisible($fromQuery)) {
                if ($category === null || $category->id !== $fromQuery->id) {
                    $category = $fromQuery;
                    $location = null;
                    $moved = true;
                }
            } else {
                $moved = true;
            }
            unset($query['kategorija']);
        }

        if (isset($query['mesto'])) {
            $place = $catalog->locationBySlug($query['mesto']);
            if ($place === null) {
                unset($query['mesto']);
                $moved = true;
            } elseif ($category !== null) {
                $moved = $moved || $location?->id !== $place->id;
                $location = $place;
                unset($query['mesto']);
            }
        }

        // A currency without a price means nothing; the default sort is not written.
        if (! isset($query['cena_od']) && ! isset($query['cena_do'])) {
            unset($query['valuta']);
        }
        if (($query['sortiranje'] ?? null) === 'najnovije') {
            unset($query['sortiranje']);
        }

        $filters = new self($category, $location, $moved ? 1 : $page);
        $filters->query = $filters->sortQuery($query);

        $known = array_intersect_key($raw, array_flip(self::KEYS));
        if (! isset($known['keyword']) && isset($query['keyword'])) {
            $known['keyword'] = $query['keyword']; // ?s= or ?q= are read as they are, no redirect
        }
        if ($moved || self::differs($known, $filters->query)) {
            return $filters->url();
        }

        $filters->resolveParams();

        return $filters;
    }

    /** Path of this list for a page, with the given query. */
    public function url(?array $query = null, ?int $page = null): string
    {
        $page ??= $this->page;
        $path = '/oglasi/';
        if ($this->category) {
            $path .= $this->category->slug.'/';
            if ($this->location) {
                $path .= $this->location->slug.'/';
            }
        }
        if ($page > 1) {
            $path .= 'page/'.$page.'/';
        }

        $query = $this->sortQuery($query ?? $this->query);

        return url_slash($path).($query === [] ? '' : '?'.http_build_query($query));
    }

    /** Action of the filter form: the category path without the place (the place is a form field). */
    public function formAction(): string
    {
        return (new self($this->category, null, 1))->url([], 1);
    }

    /** Place chosen in the path or the query. */
    public function placeSlug(): ?string
    {
        return $this->location?->slug ?? ($this->query['mesto'] ?? null);
    }

    /** Same list without one parameter (chip "×"); $field for f[...]. */
    public function urlWithout(string $key, ?string $field = null): string
    {
        $query = $this->query;
        if ($key === 'f' && $field !== null) {
            unset($query['f'][$field]);
            if (($query['f'] ?? []) === []) {
                unset($query['f']);
            }
        } elseif ($key === 'cena') {
            unset($query['cena_od'], $query['cena_do'], $query['valuta']);
        } else {
            unset($query[$key]);
        }

        return $this->url($query, 1);
    }

    /** Clean list: path only (category and place stay). */
    public function urlClear(): string
    {
        return $this->url([], 1);
    }

    /** List of another category/place with the same filters (field filters are dropped). */
    public function urlFor(?Category $category, ?Location $location = null): string
    {
        $other = new self($category, $category ? $location : null, 1);
        $query = $this->query;
        unset($query['f']);
        if ($category === null && $location !== null) {
            $query['mesto'] = $location->slug;
        }

        return $other->url($query, 1);
    }

    /** Anything chosen besides the category and place in the path. */
    public function hasFilters(): bool
    {
        return $this->query !== [];
    }

    public function sort(): string
    {
        return $this->query['sortiranje'] ?? 'najnovije';
    }

    public function currency(): Currency
    {
        return Currency::tryFrom(strtoupper($this->query['valuta'] ?? 'EUR')) ?? Currency::EUR;
    }

    public function fieldValue(string $key): string
    {
        return (string) ($this->query['f'][$key] ?? '');
    }

    /** @return list<string> */
    public function fieldValues(string $key): array
    {
        return array_values(array_filter(explode(',', $this->fieldValue($key)), 'strlen'));
    }

    /** Number of chosen filters, for "Filteri (2)". */
    public function count(): int
    {
        $n = count($this->query['f'] ?? []);
        foreach (['keyword', 'prodavac', 'mesto'] as $key) {
            $n += isset($this->query[$key]) ? 1 : 0;
        }
        $n += isset($this->query['cena_od']) || isset($this->query['cena_do']) ? 1 : 0;

        return $n;
    }

    /**
     * Chips of the chosen filters: label and URL without it.
     *
     * @return list<array{label: string, url: string}>
     */
    public function chips(): array
    {
        $chips = [];
        if (isset($this->query['keyword'])) {
            $chips[] = ['label' => '„'.$this->query['keyword'].'”', 'url' => $this->urlWithout('keyword')];
        }
        if (isset($this->query['mesto']) && ($place = app(Catalog::class)->locationBySlug($this->query['mesto']))) {
            $chips[] = ['label' => $place->name, 'url' => $this->urlWithout('mesto')];
        }
        if (isset($this->query['cena_od']) || isset($this->query['cena_do'])) {
            $unit = $this->currency()->label();
            $from = isset($this->params['price_min']) && $this->params['price_min'] !== null ? number_format($this->params['price_min'], 0, ',', '.') : null;
            $to = isset($this->params['price_max']) && $this->params['price_max'] !== null ? number_format($this->params['price_max'], 0, ',', '.') : null;
            $label = match (true) {
                $from !== null && $to !== null => $from.' – '.$to.' '.$unit,
                $from !== null => 'od '.$from.' '.$unit,
                $to !== null => 'do '.$to.' '.$unit,
                default => null,
            };
            if ($label !== null) {
                $chips[] = ['label' => 'Cena '.$label, 'url' => $this->urlWithout('cena')];
            }
        }
        if (isset($this->query['prodavac'])) {
            $chips[] = ['label' => $this->query['prodavac'] === 'firma' ? 'Samo firme' : 'Privatna lica', 'url' => $this->urlWithout('prodavac')];
        }

        if ($this->category && isset($this->query['f'])) {
            $fields = collect(app(ListingFields::class)->filters($this->category))->keyBy('key');
            foreach (array_keys($this->query['f']) as $param) {
                $chips[] = ['label' => $this->fieldChipLabel($param, $fields), 'url' => $this->urlWithout('f', $param)];
            }
        }

        return $chips;
    }

    /** Hidden inputs for "Sačuvaj pretragu": the whole search as /oglasi/ query names. */
    public function savedSearchQuery(): array
    {
        $query = $this->query;
        if ($this->category) {
            $query = ['kategorija' => $this->category->slug] + $query;
        }
        if ($this->location) {
            $query['mesto'] = $this->location->slug;
        }

        return $query;
    }

    /** Builds the ListingSearch parameters; invalid parts are dropped. */
    private function resolveParams(): void
    {
        $sort = self::SORTS[$this->query['sortiranje'] ?? 'najnovije'] ?? null;
        $seller = match ($this->query['prodavac'] ?? null) {
            'privatno' => 'private',
            'firma' => 'firm',
            default => null,
        };

        $input = array_filter([
            'q' => $this->query['keyword'] ?? null,
            'category' => $this->category?->id,
            'location' => $this->location?->id ?? (isset($this->query['mesto']) ? app(Catalog::class)->locationBySlug($this->query['mesto'])?->id : null),
            'price_min' => isset($this->query['cena_od']) ? preg_replace('/[\s.]/', '', $this->query['cena_od']) : null,
            'price_max' => isset($this->query['cena_do']) ? preg_replace('/[\s.]/', '', $this->query['cena_do']) : null,
            'currency' => $this->query['valuta'] ?? null,
            'seller_type' => $seller,
            'sort' => $sort,
            'f' => $this->query['f'] ?? null,
            'page' => $this->page,
            'per_page' => self::PER_PAGE,
        ], fn ($v) => $v !== null);

        $search = app(ListingSearch::class);
        for ($try = 0; $try < 3; $try++) {
            try {
                $this->params = $search->validate($input);

                return;
            } catch (ValidationException $e) {
                foreach (array_keys($e->errors()) as $key) {
                    if ($key === 'f') {
                        $this->droppedFilters = true;
                    }
                    unset($input[$key]);
                    if ($key === 'price_min') {
                        unset($input['price_max']);
                    }
                }
            }
        }

        $this->params = ListingSearch::defaults();
    }

    private function fieldChipLabel(string $param, $fields): string
    {
        $value = $this->query['f'][$param];
        if (preg_match('/^(.+)_(od|do)$/', $param, $m) && $fields->has($m[1])) {
            $field = $fields->get($m[1]);

            return $field['label'].' '.($m[2] === 'od' ? 'od ' : 'do ').$value.($field['unit'] ? ' '.$field['unit'] : '');
        }

        $field = $fields->get($param);
        if ($field === null) {
            return $value;
        }
        if ($field['type'] === 'checkbox') {
            return $field['label'];
        }

        $labels = collect($field['options'])->pluck('label', 'value');
        $names = array_map(fn ($v) => $labels[$v] ?? $v, explode(',', $value));

        return $field['label'].': '.implode(', ', $names);
    }

    /** Stable order of keys so equal searches have equal URLs. */
    private function sortQuery(array $query): array
    {
        $out = [];
        foreach (self::KEYS as $key) {
            if (isset($query[$key])) {
                $out[$key] = $query[$key];
            }
        }
        if (isset($out['f'])) {
            ksort($out['f']);
        }

        return $out;
    }

    private static function differs(array $raw, array $clean): bool
    {
        $norm = function ($value) use (&$norm) {
            if (is_array($value)) {
                ksort($value);

                return array_map($norm, $value);
            }

            return (string) $value;
        };

        return $norm($raw) != $norm($clean);
    }
}
