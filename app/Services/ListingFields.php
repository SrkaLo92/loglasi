<?php

namespace App\Services;

use App\Enums\FieldFilter;
use App\Enums\FieldType;
use App\Models\Category;
use App\Models\Field;
use App\Models\Listing;
use App\Models\ListingFieldValue;
use App\Support\Catalog\FieldValue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Vrednosti dodatnih polja oglasa: pravila za formu, provera i upis,
 * prikaz (labela + vrednost + jedinica) i filteri za pretragu (f[ključ]=...).
 *
 * Kako se čuva (listing_field_values):
 * - text, select: jedan red, `value`
 * - number, year: jedan red, `value` (tekst) i `value_num` (za opseg)
 * - multiselect: jedan red po izabranoj vrednosti
 * - checkbox: red sa `value` = "1" samo kad je označeno
 */
class ListingFields
{
    /** Najveći broj koji staje u value_num (decimal 16,2). */
    private const NUMBER_MAX = 99999999999999;

    public const YEAR_MIN = 1900;

    /** Values shown first in the picker ("Najtraženije"), by field key. */
    public const POPULAR = [
        'marka_automobila' => ['volkswagen', 'opel', 'audi', 'bmw', 'mercedes-benz', 'skoda', 'peugeot', 'renault', 'fiat', 'ford', 'toyota', 'zastava'],
    ];

    /**
     * Key facts shown right under the price on the listing page, like the spec strip on polovniautomobili
     * and AutoScout24: the first four of these that the listing has, in this order.
     */
    public const HIGHLIGHTS = [
        'godina', 'kilometraza', 'gorivo', 'snaga', 'menjac',
        'velicina_nekretnine', 'sobe', 'sprat', 'godina_izgradnje', 'povrsina_placa',
        'memorija', 'ram', 'nosivost', 'radni_sati', 'stanje',
    ];

    public function __construct(private readonly Catalog $catalog) {}

    /**
     * Keys of the key facts for a listing, from the rows of values(): the first four HIGHLIGHTS it has.
     *
     * @param  list<array{key: string, items?: list<string>}>  $values
     * @return list<string>
     */
    public static function highlights(array $values): array
    {
        $have = array_column(array_filter($values, fn (array $f) => ($f['items'] ?? []) === []), 'key');

        return array_slice(array_values(array_intersect(self::HIGHLIGHTS, $have)), 0, 4);
    }

    // ---------------------------------------------------------------- forma

    /**
     * Pravila za Validator, npr. ['fields' => ['array'], 'fields.marka' => ['required', ...]].
     *
     * @return array<string, list<mixed>>
     */
    public function rules(Category|int $category, string $prefix = 'fields', bool $requireAll = true): array
    {
        $rules = [$prefix => ['nullable', 'array']];

        foreach ($this->catalog->fieldsFor($category) as $field) {
            $name = $prefix.'.'.$field->key;
            $presence = $field->required && $requireAll ? 'required' : 'nullable';
            $values = $this->optionValues($field);

            $rules[$name] = match ($field->type) {
                FieldType::Text => [$presence, 'string', 'max:255'],
                FieldType::Number => [$presence, 'numeric', 'min:0', 'max:'.self::NUMBER_MAX],
                FieldType::Year => [$presence, 'integer', 'min:'.self::YEAR_MIN, 'max:'.((int) date('Y') + 1)],
                FieldType::Select => [$presence, 'string', Rule::in($values)],
                FieldType::Multiselect => [$presence, 'array', ...($field->required && $requireAll ? ['min:1'] : []), 'max:'.max(1, count($values))],
                FieldType::Checkbox => [$presence, 'boolean'],
            };

            if ($field->type === FieldType::Multiselect) {
                $rules[$name.'.*'] = ['string', 'distinct', Rule::in($values)];
            }
        }

        return $rules;
    }

    /**
     * Poruke na srpskom za pravila iz rules().
     *
     * @return array<string, string>
     */
    public function messages(Category|int $category, string $prefix = 'fields'): array
    {
        $messages = [$prefix.'.array' => 'Dodatna polja nisu u ispravnom obliku.'];

        foreach ($this->catalog->fieldsFor($category) as $field) {
            $name = $prefix.'.'.$field->key;
            $label = '„'.$field->label.'”';
            $messages += [
                $name.'.required' => 'Polje '.$label.' je obavezno.',
                $name.'.string' => 'Polje '.$label.' mora biti tekst.',
                $name.'.max' => match ($field->type) {
                    FieldType::Text => 'Polje '.$label.' može imati najviše 255 znakova.',
                    FieldType::Multiselect => 'Za polje '.$label.' izabrano je previše vrednosti.',
                    default => 'Vrednost polja '.$label.' je prevelika.',
                },
                $name.'.min' => match ($field->type) {
                    FieldType::Multiselect => 'Za polje '.$label.' izaberite bar jednu vrednost.',
                    FieldType::Year => 'Godina u polju '.$label.' nije ispravna.',
                    default => 'Vrednost polja '.$label.' ne može biti negativna.',
                },
                $name.'.numeric' => 'Polje '.$label.' mora biti broj.',
                $name.'.integer' => 'Polje '.$label.' mora biti ceo broj.',
                $name.'.boolean' => 'Polje '.$label.' može biti samo da ili ne.',
                $name.'.array' => 'Polje '.$label.' nije u ispravnom obliku.',
                $name.'.in' => 'Izabrana vrednost za '.$label.' ne postoji.',
                $name.'.*.in' => 'Izabrana vrednost za '.$label.' ne postoji.',
                $name.'.*.string' => 'Izabrana vrednost za '.$label.' ne postoji.',
                $name.'.*.distinct' => 'Ista vrednost za '.$label.' je izabrana dva puta.',
            ];
            if ($field->type === FieldType::Year) {
                $messages[$name.'.max'] = 'Godina u polju '.$label.' nije ispravna.';
            }
        }

        return $messages;
    }

    /**
     * Proverava ulaz (samo deo sa poljima, npr. $request->input('fields', [])) i vraća
     * očišćene vrednosti [ključ => vrednost] spremne za save(). Nepoznati ključevi se odbacuju.
     * $requireAll = false (admin): obavezna polja mogu ostati prazna, npr. kod starog oglasa iz uvoza.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, string|list<string>|bool>
     *
     * @throws ValidationException greške su pod ključem "{$prefix}.{ključ}"
     */
    public function validate(Category|int $category, array $input, string $prefix = 'fields', bool $requireAll = true): array
    {
        $input = $this->prepareNumbers($category, $input);

        $validator = Validator::make(
            [$prefix => $input],
            $this->rules($category, $prefix, $requireAll),
            $this->messages($category, $prefix),
        );
        // Make and model errors come together, with the other field errors.
        $validator->after(function ($validator) use ($category, $prefix) {
            $values = $this->normalize($category, (array) ($validator->getData()[$prefix] ?? []));
            foreach ($this->dependencyErrors($category, $values, $prefix) as $key => $message) {
                if (! $validator->errors()->has($key)) {
                    $validator->errors()->add($key, $message);
                }
            }
        });
        $validated = $validator->validate();

        return $this->normalize($category, (array) ($validated[$prefix] ?? []));
    }

    /**
     * Brojevi kako ih ljudi kucaju: "180.000", "180 000 km", "1,6" postaju 180000 i 1.6.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function prepareNumbers(Category|int $category, array $input): array
    {
        foreach ($this->catalog->fieldsFor($category) as $field) {
            if (! in_array($field->type, [FieldType::Number, FieldType::Year], true) || ! is_string($input[$field->key] ?? null)) {
                continue;
            }
            $input[$field->key] = self::typedNumber($input[$field->key], $field->unit, $field->type === FieldType::Year);
        }

        return $input;
    }

    /** "180.000", "180 000 km", "1,6", "2014." => "180000", "180000", "1.6", "2014"; anything else unchanged. */
    public static function typedNumber(string $raw, ?string $unit = null, bool $year = false): string
    {
        $value = preg_replace('/[\s\x{00A0}]+/u', '', $raw);
        if ($unit) {
            $value = preg_replace('/'.preg_quote(preg_replace('/\s+/u', '', $unit), '/').'$/iu', '', $value);
        }
        if ($year) {
            $value = rtrim($value, '.');
        }

        return match (true) {
            (bool) preg_match('/^\d{1,3}(\.\d{3})+$/', $value) => str_replace('.', '', $value),
            (bool) preg_match('/^\d{1,3}(\.\d{3})*,\d+$/', $value) => str_replace(['.', ','], ['', '.'], $value),
            (bool) preg_match('/^\d+,\d+$/', $value) => str_replace(',', '.', $value),
            default => $value,
        };
    }

    /**
     * A dependent field (model) needs its parent (make) and must be one of the parent's options.
     *
     * @param  array<string, string|list<string>|bool>  $values
     * @return array<string, string> error per input key
     */
    public function dependencyErrors(Category|int $category, array $values, string $prefix = 'fields'): array
    {
        $fields = $this->catalog->fieldsFor($category)->keyBy('key');
        $errors = [];

        foreach ($fields as $field) {
            if ($field->depends_on === null || $field->depends_on === '' || ! isset($values[$field->key])) {
                continue;
            }
            $parent = $fields->get($field->depends_on);
            if ($parent === null) {
                continue;
            }

            $parentValues = (array) ($values[$parent->key] ?? []);
            if ($parentValues === []) {
                $errors[$prefix.'.'.$field->key] = 'Prvo izaberite polje „'.$parent->label.'”.';

                continue;
            }

            $allowed = $this->optionValues($field, $parentValues);
            foreach ((array) $values[$field->key] as $value) {
                if (! in_array((string) $value, $allowed, true)) {
                    $errors[$prefix.'.'.$field->key] = 'Izabrana vrednost za „'.$field->label.'” ne pripada izabranoj vrednosti polja „'.$parent->label.'”.';
                    break;
                }
            }
        }

        return $errors;
    }

    /**
     * Svodi već proverene vrednosti na oblik za upis: prazno se izbacuje,
     * brojevi bez nepotrebnih decimala, checkbox kao bool.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, string|list<string>|bool>
     */
    public function normalize(Category|int $category, array $values): array
    {
        $out = [];

        foreach ($this->catalog->fieldsFor($category) as $field) {
            $raw = $values[$field->key] ?? null;

            $value = match ($field->type) {
                FieldType::Checkbox => filter_var($raw, FILTER_VALIDATE_BOOLEAN) ? true : null,
                FieldType::Multiselect => array_values(array_unique(array_filter(array_map('strval', (array) $raw), 'strlen'))) ?: null,
                FieldType::Number, FieldType::Year => is_numeric($raw) ? $this->numberString((float) $raw) : null,
                default => is_scalar($raw) && trim((string) $raw) !== '' ? trim((string) $raw) : null,
            };

            if ($value !== null) {
                $out[$field->key] = $value;
            }
        }

        return $out;
    }

    /**
     * Upisuje vrednosti oglasa (zamenjuje postojeće). Ulaz je rezultat validate()/normalize().
     *
     * @param  array<string, string|list<string>|bool>  $values
     */
    public function save(Listing $listing, array $values): void
    {
        $rows = [];

        foreach ($this->catalog->fieldsFor($listing->category_id) as $field) {
            $value = $values[$field->key] ?? null;
            if ($value === null || $value === false || $value === [] || $value === '') {
                continue;
            }

            foreach ($this->rowsFor($field, $value) as $row) {
                $rows[] = $row + ['listing_id' => $listing->id, 'field_id' => $field->id];
            }
        }

        DB::transaction(function () use ($listing, $rows) {
            ListingFieldValue::query()->where('listing_id', $listing->id)->delete();
            if ($rows !== []) {
                ListingFieldValue::query()->insert($rows);
            }
        });

        $listing->unsetRelation('fieldValues');
    }

    // ---------------------------------------------------------------- prikaz

    /**
     * Vrednosti za prikaz (FieldValue iz ugovora), redosledom polja kategorije:
     * [['key', 'label', 'value', 'display'], ...]. Prazna polja se preskaču.
     * Za listu oglasa unapred učitati: $listings->load('fieldValues').
     *
     * @return list<array{key: string, label: string, value: string, display: string}>
     */
    public function values(Listing $listing): array
    {
        $stored = $listing->fieldValues->groupBy('field_id');
        $out = [];

        foreach ($this->catalog->fieldsFor($listing->category_id) as $field) {
            /** @var Collection<int, ListingFieldValue>|null $rows */
            $rows = $stored->get($field->id);
            if ($rows === null || $rows->isEmpty()) {
                continue;
            }

            $raw = $rows->pluck('value')->filter(fn ($v) => FieldValue::meaningful($field->type, $v))->values()->all();
            if ($raw === []) {
                continue;
            }

            $labels = $field->type === FieldType::Multiselect ? $this->optionLabels($field) : [];
            $out[] = [
                'key' => $field->key,
                'label' => $field->label,
                'value' => implode(',', $raw),
                'display' => $this->display($field, $raw),
                'type' => $field->type->value,
                // Multiselect values one by one (equipment as a list of chips on the listing page).
                'items' => $field->type === FieldType::Multiselect ? array_map(fn ($v) => $labels[$v] ?? $v, $raw) : [],
            ];
        }

        return $out;
    }

    /**
     * Tekst za prikaz: "180.000 km", "2014.", "Dizel", "Lično preuzimanje, Dostava", "Da".
     *
     * @param  list<string>  $raw
     */
    public function display(Field $field, array $raw): string
    {
        $labels = $this->optionLabels($field);

        $text = match ($field->type) {
            FieldType::Number => implode(', ', array_map(fn ($v) => is_numeric($v) ? $this->formatNumber((float) $v) : $v, $raw)),
            FieldType::Year => implode(', ', array_map(fn ($v) => $v.'.', $raw)),
            FieldType::Checkbox => 'Da',
            FieldType::Select, FieldType::Multiselect => implode(', ', array_map(fn ($v) => $labels[$v] ?? $v, $raw)),
            FieldType::Text => implode(', ', $raw),
        };

        if (! $field->unit) {
            return $text;
        }

        // Snaga: "85 kW (116 KS)", kao na polovniautomobili.
        if ($field->type === FieldType::Number && $field->unit === 'kW' && count($raw) === 1 && is_numeric($raw[0])) {
            return $text.' kW ('.number_format((float) $raw[0] * 1.35962, 0, ',', '.').' KS)';
        }

        return $text.' '.$field->unit;
    }

    /** Vrednost jednog polja na oglasu (prva), npr. value('stanje', $oglas) === 'novo'. */
    public function value(string $key, Listing $listing): ?string
    {
        foreach ($this->values($listing) as $value) {
            if ($value['key'] === $key) {
                return explode(',', $value['value'])[0];
            }
        }

        return null;
    }

    /** `condition` iz ugovora: "novo", "polovno" ili null, iz polja Stanje. */
    public function condition(Listing $listing): ?string
    {
        $value = $this->value('stanje', $listing);

        return match (true) {
            $value === null => null,
            $value === 'novo' => 'novo',
            default => 'polovno',
        };
    }

    // ---------------------------------------------------------------- pretraga

    /**
     * Polja koja se koriste u pretrazi (filter različit od none), sa imenima parametara.
     * `filter` je u obliku iz ugovora: exact | multi | range.
     *
     * Polja potkategorija su uključena (Catalog::filterFieldsFor), pa i „Automobili” filtrira po marki.
     *
     * @return list<array{key: string, label: string, type: string, filter: string, unit: ?string, depends_on: ?string, options: list<array{value: string, label: string, parent?: string}>, params: list<string>}>
     */
    public function filters(Category|int $category): array
    {
        $out = [];

        foreach ($this->catalog->filterFieldsFor($category) as $field) {
            if ($field->filter === FieldFilter::None) {
                continue;
            }

            $out[] = [
                'key' => $field->key,
                'label' => $field->label,
                'type' => $field->type->value,
                'filter' => self::apiFilter($field->filter),
                'unit' => $field->unit,
                'depends_on' => $this->dependsOn($field),
                'options' => $this->options($field),
                'params' => $field->filter === FieldFilter::Range
                    ? [$field->key.'_od', $field->key.'_do']
                    : [$field->key],
            ];
        }

        return $out;
    }

    /**
     * Proverava f[...] iz zahteva prema poljima kategorije (null = bez kategorije, nema polja)
     * i vraća uslove za apply(): [ključ => ['values' => [...], 'min' => ?float, 'max' => ?float]].
     *
     * @throws ValidationException poruka pod ključem "f" (API ga pretvara u 400 invalid_param)
     */
    public function parseFilters(Category|int|null $category, mixed $input): array
    {
        if ($input === null || $input === '' || $input === []) {
            return [];
        }

        $fail = fn (string $message) => throw ValidationException::withMessages(['f' => $message]);

        if (! is_array($input)) {
            $fail('Filteri polja se šalju kao f[ključ]=vrednost.');
        }

        $fields = $category === null ? collect() : $this->catalog->filterFieldsFor($category)->keyBy('key');
        $out = [];

        foreach ($input as $param => $raw) {
            if (! is_string($param) || ! is_scalar($raw)) {
                $fail('Filteri polja se šalju kao f[ključ]=vrednost.');
            }

            [$key, $bound] = $this->splitParam($param, $fields);
            /** @var Field|null $field */
            $field = $fields->get($key);

            if ($field === null || $field->filter === FieldFilter::None) {
                $fail('Nepoznat filter: '.preg_replace('/[^a-z0-9_-]/', '', strtolower($param)).'.');
            }

            $raw = trim((string) $raw);
            if ($raw === '') {
                continue;
            }

            $out[$key] ??= ['values' => [], 'min' => null, 'max' => null];

            if ($field->filter === FieldFilter::Range) {
                $raw = self::typedNumber($raw, $field->unit, $field->type === FieldType::Year);
                if ($bound === null || ! is_numeric($raw)) {
                    $fail('Za „'.$field->label.'” se šalju brojevi kao _od i _do.');
                }
                $out[$key][$bound === 'od' ? 'min' : 'max'] = (float) $raw;

                continue;
            }

            $values = array_values(array_unique(array_filter(array_map('trim', explode(',', $raw)), 'strlen')));
            if ($field->filter !== FieldFilter::Multi && count($values) > 1) {
                $fail('Za „'.$field->label.'” je dozvoljena jedna vrednost.');
            }

            $allowed = $this->filterValues($field);
            if ($allowed !== [] && array_diff($values, $allowed) !== []) {
                $fail('Neispravna vrednost za „'.$field->label.'”.');
            }

            $out[$key]['values'] = $values;
        }

        foreach ($out as $key => $condition) {
            if ($condition['min'] !== null && $condition['max'] !== null && $condition['min'] > $condition['max']) {
                $fail('Za „'.$fields->get($key)->label.'” vrednost „od” ne može biti veća od „do”.');
            }
        }

        return $out;
    }

    /**
     * Dodaje uslove iz parseFilters() na upit oglasa (jedan EXISTS po polju, koristi indekse
     * field_id+value i field_id+value_num).
     *
     * @param  Builder<Listing>  $query
     * @return Builder<Listing>
     */
    public function apply(Builder $query, array $conditions): Builder
    {
        $table = $query->getModel()->getTable();

        foreach ($conditions as $key => $condition) {
            $field = $this->catalog->field($key);
            if ($field === null) {
                continue;
            }

            $values = $condition['values'];

            if ($values === [] && $condition['min'] === null && $condition['max'] === null) {
                continue;
            }

            $query->whereExists(function (QueryBuilder $sub) use ($table, $field, $values, $condition) {
                $sub->selectRaw('1')
                    ->from('listing_field_values')
                    ->whereColumn('listing_field_values.listing_id', $table.'.id')
                    ->where('listing_field_values.field_id', $field->id);

                if ($values !== []) {
                    $sub->whereIn('listing_field_values.value', $values);
                }
                if ($condition['min'] !== null) {
                    $sub->where('listing_field_values.value_num', '>=', $condition['min']);
                }
                if ($condition['max'] !== null) {
                    $sub->where('listing_field_values.value_num', '<=', $condition['max']);
                }
            });
        }

        return $query;
    }

    // ---------------------------------------------------------------- API oblik

    /**
     * Field iz ugovora: key, label, type, required, unit, depends_on, options, filter.
     * Zavisno polje (model) ima depends_on = ključ roditelja (marka), a svaka opcija `parent`.
     *
     * @return array<string, mixed>
     */
    public function toApi(Field $field): array
    {
        return [
            'key' => $field->key,
            'label' => $field->label,
            'type' => $field->type->value,
            'required' => (bool) $field->required,
            'unit' => $field->unit !== null && $field->unit !== '' ? $field->unit : null,
            'depends_on' => $this->dependsOn($field),
            'options' => $this->options($field),
            'filter' => self::apiFilter($field->filter),
        ];
    }

    public function dependsOn(Field $field): ?string
    {
        return $field->depends_on !== null && $field->depends_on !== '' ? (string) $field->depends_on : null;
    }

    /** U ugovoru se jedan izbor zove "exact". */
    public static function apiFilter(FieldFilter $filter): string
    {
        return $filter === FieldFilter::Select ? 'exact' : $filter->value;
    }

    // ---------------------------------------------------------------- pomoćno

    /**
     * Opcije izbora; kod zavisnog polja svaka ima i `parent` (vrednost opcije roditelja).
     *
     * @return list<array{value: string, label: string, parent?: string}>
     */
    public function options(Field $field): array
    {
        $dependent = $this->dependsOn($field) !== null;
        $out = [];
        foreach ((array) $field->options as $option) {
            if (is_array($option) && isset($option['value'], $option['label'])) {
                $item = ['value' => (string) $option['value'], 'label' => (string) $option['label']];
                if ($dependent) {
                    $item['parent'] = (string) ($option['parent'] ?? '');
                }
                $out[] = $item;
            }
        }

        return $out;
    }

    /**
     * Vrednosti opcija; sa $parents samo opcije tih roditelja.
     *
     * @param  list<string>|null  $parents
     * @return list<string>
     */
    private function optionValues(Field $field, ?array $parents = null): array
    {
        $options = $this->options($field);
        if ($parents !== null) {
            $parents = array_map('strval', $parents);
            $options = array_filter($options, fn ($o) => in_array($o['parent'] ?? '', $parents, true));
        }

        return array_values(array_column($options, 'value'));
    }

    /** @return array<string, string> */
    private function optionLabels(Field $field): array
    {
        return array_column($this->options($field), 'label', 'value');
    }

    /** @return list<string> */
    private function filterValues(Field $field): array
    {
        return $field->type === FieldType::Checkbox ? ['1'] : $this->optionValues($field);
    }

    /** @return list<array{value: string, value_num: ?float}> */
    private function rowsFor(Field $field, mixed $value): array
    {
        return match ($field->type) {
            FieldType::Checkbox => [['value' => '1', 'value_num' => 1.0]],
            FieldType::Multiselect => array_map(fn ($v) => ['value' => (string) $v, 'value_num' => null], (array) $value),
            FieldType::Number, FieldType::Year => [['value' => (string) $value, 'value_num' => (float) $value]],
            default => [['value' => mb_substr((string) $value, 0, 255), 'value_num' => null]],
        };
    }

    /** "godiste_od" => ["godiste", "od"] kad je godiste polje sa opsegom. */
    private function splitParam(string $param, Collection $fields): array
    {
        $param = strtolower($param);

        if (preg_match('/^(.+)_(od|do)$/', $param, $m)
            && $fields->has($m[1])
            && $fields->get($m[1])->filter === FieldFilter::Range) {
            return [$m[1], $m[2]];
        }

        return [$param, null];
    }

    private function numberString(float $number): string
    {
        return floor($number) === $number ? (string) (int) $number : rtrim(rtrim(number_format($number, 2, '.', ''), '0'), '.');
    }

    private function formatNumber(float $number): string
    {
        if (floor($number) === $number) {
            return number_format($number, 0, ',', '.');
        }

        return rtrim(rtrim(number_format($number, 2, ',', '.'), '0'), ',');
    }
}
