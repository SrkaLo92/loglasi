<?php

namespace App\Services;

use App\Enums\Currency;
use App\Models\Listing;
use App\Models\SavedSearch;
use App\Support\Price;
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
 * as ListingFields::apply(). The seller's own listings never match.
 *
 * If the search module's ListingSearch later gets an in-memory or id-restricted matcher,
 * match() can delegate to it; its signature does not need to change.
 */
class SavedSearchMatcher
{
    private const DIACRITICS = [
        'č' => 'c', 'ć' => 'c', 'š' => 's', 'ž' => 'z', 'đ' => 'dj',
        'Č' => 'c', 'Ć' => 'c', 'Š' => 's', 'Ž' => 'z', 'Đ' => 'dj',
    ];

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

            $words = null; // computed only when a candidate has a keyword
            $values = null;

            foreach ($candidates as $search) {
                if ($search['user_id'] === $listing->user_id) {
                    continue;
                }
                if ($search['location'] !== null && $search['location'] !== $listing->location_id) {
                    continue;
                }
                if (! $this->priceMatches($search, $listing)) {
                    continue;
                }
                if ($search['words'] !== []) {
                    $words ??= $this->words($listing->title.' '.$listing->description);
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
            $category = $this->catalog->categoryBySlug((string) $query['kategorija']);
            if ($category === null || ! $this->catalog->isVisible($category)) {
                return null; // category removed or hidden: nothing can match
            }
            $categories = $this->catalog->descendantIds($category);
        }

        $location = null;
        if (! empty($query['mesto'])) {
            $location = $this->catalog->locationBySlug((string) $query['mesto'])?->id;
            if ($location === null) {
                return null;
            }
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
            'user_id' => $search->user_id,
            'categories' => $categories,
            'location' => $location,
            'min' => isset($query['price_min']) ? max(0.01, Price::toEur((float) $query['price_min'], $currency)) : null,
            'max' => isset($query['price_max']) ? Price::toEur((float) $query['price_max'], $currency) : null,
            'words' => isset($query['keyword']) ? array_slice(array_values(array_unique($this->words((string) $query['keyword']))), 0, 10) : [],
            'filters' => $filters,
        ];
    }

    private function priceMatches(array $search, Listing $listing): bool
    {
        if ($search['min'] === null && $search['max'] === null) {
            return true;
        }
        if ($listing->price_eur === null) {
            return false;
        }
        $eur = (float) $listing->price_eur;

        // Rounded the same way as the DECIMAL(14,2) comparison in SQL.
        return ($search['min'] === null || $eur >= round($search['min'], 2))
            && ($search['max'] === null || $eur <= round($search['max'], 2));
    }

    /** @param  list<string>  $needles @param  list<string>  $haystack */
    private function wordsMatch(array $needles, array $haystack): bool
    {
        foreach ($needles as $needle) {
            $found = false;
            foreach ($haystack as $word) {
                if (str_starts_with($word, $needle)) {
                    $found = true;
                    break;
                }
            }
            if (! $found) {
                return false;
            }
        }

        return true;
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

    /**
     * Normalized words, the same rules as the search index (no diacritics, lower case,
     * letters and digits only): "Ćevapi, Lešnica" -> ["cevapi", "lesnica"].
     *
     * @return list<string>
     */
    private function words(string $text): array
    {
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = mb_strtolower(strtr($text, self::DIACRITICS), 'UTF-8');
        if (class_exists(\Normalizer::class)) {
            $text = (string) preg_replace('/\p{Mn}+/u', '', (string) \Normalizer::normalize($text, \Normalizer::FORM_D));
        }
        $text = (string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', $text);

        return array_values(array_unique(array_filter(explode(' ', $text), 'strlen')));
    }
}
