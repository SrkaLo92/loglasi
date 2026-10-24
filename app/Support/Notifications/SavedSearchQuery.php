<?php

namespace App\Support\Notifications;

use App\Enums\Currency;
use App\Models\Category;
use App\Services\Catalog;
use App\Services\ListingFields;
use App\Support\Price;
use Illuminate\Validation\ValidationException;

/**
 * The query of a saved search, in the parameters of the /oglasi/ page:
 * keyword, kategorija (slug), mesto (slug), price_min, price_max, currency, f[...].
 *
 * normalize() validates the input and returns only the set parameters in a fixed order,
 * so the same search always has the same hash and the stored JSON can rebuild the URL.
 */
class SavedSearchQuery
{
    public const KEYWORD_MAX = 100;

    public const PRICE_MAX = 1_000_000_000;

    public function __construct(
        private readonly Catalog $catalog,
        private readonly ListingFields $fields,
    ) {}

    /**
     * @return array{keyword?: string, kategorija?: string, mesto?: string, price_min?: int, price_max?: int, currency?: string, f?: array<string, string>}
     *
     * @throws ValidationException
     */
    public function normalize(array $input): array
    {
        $errors = [];
        $out = [];

        $keyword = $this->string($input['keyword'] ?? null);
        if ($keyword !== null) {
            $keyword = trim((string) preg_replace('/\s+/u', ' ', $keyword));
            if (mb_strlen($keyword) > self::KEYWORD_MAX) {
                $errors['keyword'] = 'Tekst pretrage može imati najviše '.self::KEYWORD_MAX.' znakova.';
            } elseif ($keyword !== '') {
                $out['keyword'] = $keyword;
            }
        }

        $category = null;
        $slug = $this->string($input['kategorija'] ?? null);
        if ($slug !== null && $slug !== '') {
            $category = str_contains($slug, '/') ? $this->catalog->categoryByPath($slug) : $this->catalog->categoryBySlug($slug);
            if ($category === null || ! $this->catalog->isVisible($category)) {
                $errors['kategorija'] = 'Izabrana kategorija ne postoji.';
                $category = null;
            } else {
                $out['kategorija'] = $category->slug;
            }
        }

        $place = $this->string($input['mesto'] ?? null);
        if ($place !== null && $place !== '') {
            $location = $this->catalog->locationBySlug($place);
            if ($location === null) {
                $errors['mesto'] = 'Izabrano mesto ne postoji.';
            } else {
                $out['mesto'] = $location->slug;
            }
        }

        foreach (['price_min', 'price_max'] as $key) {
            $raw = $this->string($input[$key] ?? null);
            if ($raw === null || trim($raw) === '') {
                continue;
            }
            $raw = str_replace(['.', ' '], '', trim($raw)); // "5.000" as typed on the site
            if (! ctype_digit($raw) || (int) $raw > self::PRICE_MAX) {
                $errors[$key] = 'Cena mora biti ceo broj veći ili jednak nuli.';
            } else {
                $out[$key] = (int) $raw;
            }
        }
        if (isset($out['price_min'], $out['price_max']) && $out['price_min'] > $out['price_max']) {
            $errors['price_min'] = 'Najniža cena ne može biti veća od najviše.';
        }

        $currency = $this->string($input['currency'] ?? null);
        if ($currency !== null && $currency !== '' && Currency::tryFrom(strtoupper($currency)) === null) {
            $errors['currency'] = 'Dozvoljene vrednosti su EUR i RSD.';
        } elseif (isset($out['price_min']) || isset($out['price_max'])) {
            $out['currency'] = $currency ? strtoupper($currency) : Currency::EUR->value;
        }

        $f = $input['f'] ?? null;
        if (is_array($f)) {
            // Empty inputs of the filter form (range "od" left blank) arrive as "" or null.
            $f = array_filter($f, fn ($v) => ! ($v === null || (is_string($v) && trim($v) === '')));
        }
        if ($f !== null && $f !== '' && $f !== []) {
            try {
                $this->fields->parseFilters($category, $f);
                $clean = array_map(fn ($v) => trim((string) $v), $f);
                ksort($clean);
                $out['f'] = $clean;
            } catch (ValidationException $e) {
                $errors['f'] = $e->errors()['f'][0] ?? 'Neispravan filter.';
            }
        }

        if ($errors === [] && $out === []) {
            $errors['keyword'] = 'Izaberite bar jedan uslov pretrage (tekst, kategoriju, mesto, cenu ili filter).';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $order = ['keyword', 'kategorija', 'mesto', 'price_min', 'price_max', 'currency', 'f'];

        return array_replace(array_intersect_key(array_flip($order), $out), $out);
    }

    public static function hash(array $query): string
    {
        return sha1(json_encode($query, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /** URL of the results page for the stored query. */
    public static function url(array $query): string
    {
        return url_slash('oglasi').($query === [] ? '' : '?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986));
    }

    /** Short Serbian description, the default name: "golf · Automobili · Loznica · do 5.000 €". */
    public function describe(array $query): string
    {
        $parts = [];

        if (isset($query['keyword'])) {
            $parts[] = '„'.$query['keyword'].'”';
        }
        if (isset($query['kategorija']) && ($category = $this->catalog->categoryBySlug($query['kategorija'])) instanceof Category) {
            $parts[] = $category->name;
        }
        if (isset($query['mesto']) && ($location = $this->catalog->locationBySlug($query['mesto']))) {
            $parts[] = $location->name;
        }

        $currency = Currency::tryFrom($query['currency'] ?? '') ?? Currency::EUR;
        $min = isset($query['price_min']) ? Price::label((float) $query['price_min'], $currency) : null;
        $max = isset($query['price_max']) ? Price::label((float) $query['price_max'], $currency) : null;
        if ($min !== null && $max !== null) {
            $parts[] = $min.' – '.$max;
        } elseif ($min !== null) {
            $parts[] = 'od '.$min;
        } elseif ($max !== null) {
            $parts[] = 'do '.$max;
        }

        if (isset($query['f'])) {
            $count = count($query['f']);
            $parts[] = $count === 1 ? '1 filter' : $count.' filtera';
        }

        return mb_substr($parts === [] ? 'Svi oglasi' : implode(' · ', $parts), 0, 120);
    }

    private function string(mixed $value): ?string
    {
        return is_scalar($value) ? (string) $value : null;
    }
}
