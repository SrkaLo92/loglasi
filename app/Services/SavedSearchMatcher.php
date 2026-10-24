<?php

namespace App\Services;

use App\Enums\Currency;
use App\Models\Listing;
use App\Models\SavedSearch;
use App\Support\Price;
use App\Support\Search\SearchText;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Matches a batch of newly published listings against saved searches, in memory.
 *
 * Built for scale: the hourly job loads only the listings published since the previous
 * run (indexed window on status + published_at) in chunks, and each chunk is matched
 * against all searches with one pass. Searches are bucketed by category, so a listing is
 * only compared with the searches of its category tree and the ones without a category.
 * No query per search.
 *
 * Semantics follow the /oglasi/ search: category includes subcategories, location is
 * exact, price bounds compare price_eur (no price never matches a bound), every keyword
 * word must start a word of the title or description (diacritics ignored), f[...] filters
 * as ListingFields::apply() (text rules from App\Support\Search\SearchText). The seller's own listings never match.
 *
 * If the search module's ListingSearch later gets an in-memory or id-restricted matcher,
 * match() can delegate to it; its signature does not need to change.
 */
class SavedSearchMatcher
{
    /** Longest word start compared; longer keyword words are compared by their first PREFIX_MAX letters. */
    private const PREFIX_MAX = 24;

    /** @var array<string, mixed> lookups of one match() call (category / location by slug) */
    private array $memo = [];

    public function __construct(
        private readonly Catalog $catalog,
        private readonly ListingFields $fields,
    ) {}

    /**
     * @param  iterable<Listing>  $listings  active listings with fieldValues loaded
     * @param  iterable<SavedSearch>  $searches
     * @return array<int, list<int>> saved search id => matching listing ids
     */
    public function match(iterable $listings, iterable $searches): array
    {
        $this->memo = [];
        [$byCategory, $anyCategory] = $this->compile($searches);
        if ($byCategory === [] && $anyCategory === []) {
            return [];
        }

        $result = [];

        foreach ($listings as $listing) {
            $candidates = array_merge($anyCategory, $byCategory[$listing->category_id] ?? []);
            if ($candidates === []) {
                continue;
            }

            // Read the attributes once: Eloquent accessors are too slow in the inner loop.
            $userId = (int) $listing->user_id;
            $locationId = $listing->location_id === null ? null : (int) $listing->location_id;
            $eur = $listing->price_eur === null ? null : (float) $listing->price_eur;
            $words = null; // computed only when a candidate has a keyword
            $values = null;

            foreach ($candidates as $search) {
                if ($search['user_id'] === $userId) {
                    continue;
                }
                if ($search['location'] !== null && $search['location'] !== $locationId) {
                    continue;
                }
                if (($search['min'] !== null || $search['max'] !== null) && ! $this->priceMatches($search, $eur)) {
                    continue;
                }
                if ($search['words'] !== []) {
                    $words ??= $this->prefixes($listing->title.' '.$listing->description);
                    if (! $this->wordsMatch($search['words'], $words)) {
                        continue;
                    }
                }
                if ($search['filters'] !== []) {
                    $values ??= $listing->fieldValues->groupBy('field_id');
                    if (! $this->filtersMatch($search['filters'], $values)) {
                        continue;
                    }
                }

                $result[$search['id']][] = $listing->id;
            }
        }

        return $result;
    }

    /**
     * @return array{0: array<int, list<array>>, 1: list<array>}
     */
    private function compile(iterable $searches): array
    {
        $byCategory = [];
        $any = [];

        foreach ($searches as $search) {
            $compiled = $this->compileOne($search);
            if ($compiled === null) {
                continue;
            }

            if ($compiled['categories'] === null) {
                $any[] = $compiled;

                continue;
            }
            foreach ($compiled['categories'] as $id) {
                $byCategory[$id][] = $compiled;
            }
        }

        return [$byCategory, $any];
    }

    private function compileOne(SavedSearch $search): ?array
    {
        $query = (array) $search->query;

        $category = null;
        $categories = null;
        if (! empty($query['kategorija'])) {
            $slug = (string) $query['kategorija'];
            $this->memo['c:'.$slug] ??= (function () use ($slug) {
                $category = $this->catalog->categoryBySlug($slug);

                // Removed or hidden category: nothing can match.
                return $category === null || ! $this->catalog->isVisible($category)
                    ? false
                    : [$category, $this->catalog->descendantIds($category)];
            })();
            if ($this->memo['c:'.$slug] === false) {
                return null;
            }
            [$category, $categories] = $this->memo['c:'.$slug];
        }

        $location = null;
        if (! empty($query['mesto'])) {
            $slug = (string) $query['mesto'];
            $this->memo['l:'.$slug] ??= $this->catalog->locationBySlug($slug)?->id ?? false;
            if ($this->memo['l:'.$slug] === false) {
                return null;
            }
            $location = $this->memo['l:'.$slug];
        }

        $currency = Currency::tryFrom((string) ($query['currency'] ?? '')) ?? Currency::EUR;

        $filters = [];
        if (! empty($query['f'])) {
            try {
                foreach ($this->fields->parseFilters($category, $query['f']) as $key => $condition) {
                    $field = $this->catalog->field($key);
                    if ($field !== null) {
                        $filters[$field->id] = $condition;
                    }
                }
            } catch (ValidationException) {
                Log::debug('Saved search skipped: its filters no longer fit the category.', ['saved_search_id' => $search->id]);

                return null;
            }
        }

        return [
            'id' => $search->id,
            'user_id' => (int) $search->user_id,
            'categories' => $categories,
            'location' => $location,
            'min' => isset($query['price_min']) ? round(max(0.01, Price::toEur((float) $query['price_min'], $currency)), 2) : null,
            'max' => isset($query['price_max']) ? round(Price::toEur((float) $query['price_max'], $currency), 2) : null,
            'words' => isset($query['keyword'])
                ? array_values(array_unique(array_map(fn ($w) => mb_substr($w, 0, self::PREFIX_MAX), SearchText::words((string) $query['keyword']))))
                : [],
            'filters' => $filters,
        ];
    }

    private function priceMatches(array $search, ?float $eur): bool
    {
        if ($eur === null) {
            return false; // "Dogovor" never matches a price bound
        }

        // Bounds are rounded like the DECIMAL(14,2) comparison in SQL (compileOne).
        return ($search['min'] === null || $eur >= $search['min'])
            && ($search['max'] === null || $eur <= $search['max']);
    }

    /**
     * Every keyword word must start a word of the listing: one hash lookup per word.
     *
     * @param  list<string>  $needles  @param  array<string, true>  $prefixes
     */
    private function wordsMatch(array $needles, array $prefixes): bool
    {
        foreach ($needles as $needle) {
            if (! isset($prefixes[$needle])) {
                return false;
            }
        }

        return true;
    }

    /**
     * All word starts of a text as a set: "golf" -> g, go, gol, golf.
     * Keyword words longer than PREFIX_MAX are cut to it in compileOne(), like here.
     *
     * @return array<string, true>
     */
    private function prefixes(string $text): array
    {
        $set = [];
        // Same normalization as the search index (listings_search.text): no diacritics, lower case.
        foreach (array_unique(explode(' ', SearchText::forListing($text, null))) as $word) {
            if ($word === '') {
                continue;
            }
            $length = min(mb_strlen($word), self::PREFIX_MAX);
            for ($i = 1; $i <= $length; $i++) {
                $set[mb_substr($word, 0, $i)] = true;
            }
        }

        return $set;
    }

    /** @param  array<int, array{values: list<string>, min: ?float, max: ?float}>  $filters */
    private function filtersMatch(array $filters, $valuesByField): bool
    {
        foreach ($filters as $fieldId => $condition) {
            if ($condition['values'] === [] && $condition['min'] === null && $condition['max'] === null) {
                continue;
            }

            $rows = $valuesByField->get($fieldId);
            if ($rows === null) {
                return false;
            }

            $ok = $rows->contains(fn ($row) => ($condition['values'] === [] || in_array((string) $row->value, $condition['values'], true))
                && ($condition['min'] === null || ($row->value_num !== null && (float) $row->value_num >= $condition['min']))
                && ($condition['max'] === null || ($row->value_num !== null && (float) $row->value_num <= $condition['max'])));

            if (! $ok) {
                return false;
            }
        }

        return true;
    }
}
