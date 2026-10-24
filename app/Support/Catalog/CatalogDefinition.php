<?php

namespace App\Support\Catalog;

use App\Enums\FieldType;
use App\Services\Catalog;
use App\Services\ListingFields;
use App\Services\SearchIndex;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Makes fields, their options and the fields of every category match database/seeders/data/catalog/
 * (fields.php, links.php, cars.php), moves stored values to the new options (WordPress model slugs,
 * Da/Ne to a checkbox), drops values no option has, and takes the parent's name off subcategory names
 * ("Automobili | Džip/SUV" under "Automobili" becomes "Džip/SUV"). Safe to run again.
 */
final class CatalogDefinition
{
    public const DIR = 'seeders/data/catalog';

    /** @var array<string, int> */
    private array $counts = [];

    /** @var list<string> */
    private array $notes = [];

    /** @var array<int, true> */
    private array $touched = [];

    /** @return array<string, array<string, mixed>> */
    public static function fields(): array
    {
        return require database_path(self::DIR.'/fields.php');
    }

    /** @return list<array<string, mixed>> */
    public static function links(): array
    {
        return require database_path(self::DIR.'/links.php');
    }

    /** "Benzin + gas" => "benzin-gas", "Građevinsko" => "gradjevinsko". */
    public static function slug(string $label): string
    {
        return Str::slug(strtr($label, ['đ' => 'dj', 'Đ' => 'Dj']));
    }

    /**
     * Options of a definition as stored: [{value, label}] or [{value, label, parent}].
     *
     * @param  list<string|list<string>>  $list
     * @return list<array{value: string, label: string, parent?: string}>
     */
    public static function options(array $list): array
    {
        $out = [];
        foreach ($list as $item) {
            if (is_string($item)) {
                $out[] = ['value' => self::slug($item), 'label' => $item];

                continue;
            }
            $option = ['value' => (string) $item[0], 'label' => (string) $item[1]];
            if (isset($item[2])) {
                $option['parent'] = (string) $item[2];
            }
            $out[] = $option;
        }

        return $out;
    }

    /**
     * Applies the definition. With $dryRun everything runs in a transaction that is rolled back,
     * so the counts are exact and nothing changes.
     *
     * @return array{counts: array<string, int>, notes: list<string>}
     */
    public function apply(bool $dryRun = false): array
    {
        $this->counts = [];
        $this->notes = [];
        $this->touched = [];

        DB::beginTransaction();
        try {
            $fields = $this->syncFields();
            $this->moveValues($fields);
            $this->syncLinks($fields);
            $this->renameCategories();
            $this->moveSavedSearches();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        if ($dryRun) {
            DB::rollBack();
        } else {
            DB::commit();
            app(Catalog::class)->flush();
            if ($this->touched !== []) {
                app(SearchIndex::class)->syncIds(array_keys($this->touched));
            }
        }

        return ['counts' => $this->counts, 'notes' => $this->notes];
    }

    // ---------------------------------------------------------------- fields

    /** @return array<string, object> key => fields row (after the update) */
    private function syncFields(): array
    {
        $existing = DB::table('fields')->get()->keyBy('key');
        $sort = 0;

        foreach (self::fields() as $key => $def) {
            $sort += 10;
            $type = FieldType::from($def['type']);
            $hasOptions = in_array($type, [FieldType::Select, FieldType::Multiselect], true);
            $row = $existing->get($key);

            if ($row === null && $hasOptions && ! isset($def['options'])) {
                $this->notes[] = "{$key}: nema ga u bazi, a opcije dolaze samo iz uvoza, preskočeno";

                continue;
            }

            $values = [
                'label' => $def['label'],
                'type' => $type->value,
                'filter' => $def['filter'] ?? 'none',
                'unit' => $def['unit'] ?? null,
                'depends_on' => $def['depends_on'] ?? null,
                'sort' => $sort,
                'updated_at' => now(),
            ];
            if (isset($def['options'])) {
                $values['options'] = json_encode(self::options($def['options']), JSON_UNESCAPED_UNICODE);
            } elseif (! $hasOptions) {
                $values['options'] = null;
            }

            if ($row === null) {
                DB::table('fields')->insert($values + ['key' => $key, 'created_at' => now()]);
                $this->add('polja dodata');
            } else {
                DB::table('fields')->where('id', $row->id)->update($values);
                $this->add('polja ažurirana');
            }
        }

        return DB::table('fields')->get()->keyBy('key')->all();
    }

    // ---------------------------------------------------------------- values

    /** @param  array<string, object>  $fields */
    private function moveValues(array $fields): void
    {
        foreach (self::fields() as $key => $def) {
            $field = $fields[$key] ?? null;
            if ($field === null) {
                continue;
            }
            $type = FieldType::from($field->type);

            foreach ((array) ($def['values'] ?? []) as $old => $new) {
                $rows = DB::table('listing_field_values')->where('field_id', $field->id)->where('value', (string) $old);
                $this->touch((clone $rows)->pluck('listing_id')->all());
                $changed = $new === null
                    ? $rows->delete()
                    : $rows->update(['value' => (string) $new, 'value_num' => $type === FieldType::Checkbox ? 1 : null]);
                $this->add('vrednosti prenete', $changed);
            }

            match ($type) {
                FieldType::Number, FieldType::Year => $this->cleanNumbers($field, $type),
                FieldType::Select, FieldType::Multiselect => $this->cleanChoices($field),
                FieldType::Checkbox => $this->drop($field->id, fn ($q) => $q->where('value', '!=', '1')),
                default => null,
            };
        }

        $this->cleanDependents($fields);
    }

    private function cleanNumbers(object $field, FieldType $type): void
    {
        $rows = DB::table('listing_field_values')->where('field_id', $field->id)->get(['id', 'listing_id', 'value', 'value_num']);
        foreach ($rows as $row) {
            $value = ListingFields::typedNumber((string) $row->value, $field->unit, $type === FieldType::Year);
            if (! is_numeric($value) || ! FieldValue::meaningful($type, $value) || ($type === FieldType::Year && (int) $value > (int) date('Y') + 1)) {
                DB::table('listing_field_values')->where('id', $row->id)->delete();
                $this->touch([$row->listing_id]);
                $this->add('neispravne vrednosti obrisane');

                continue;
            }
            $number = (float) $value;
            $text = floor($number) === $number ? (string) (int) $number : (string) $number;
            if ($text !== (string) $row->value || $row->value_num === null || (float) $row->value_num !== $number) {
                DB::table('listing_field_values')->where('id', $row->id)->update(['value' => $text, 'value_num' => $number]);
                $this->add('brojevi ispravljeni');
            }
        }
    }

    private function cleanChoices(object $field): void
    {
        $allowed = array_column((array) json_decode((string) $field->options, true), 'value');
        $this->drop($field->id, fn ($q) => $q->whereNotIn('value', $allowed ?: ['']));
    }

    /** A model that is not one of the listing's make's models is dropped. @param  array<string, object>  $fields */
    private function cleanDependents(array $fields): void
    {
        foreach ($fields as $field) {
            $parent = $field->depends_on ? ($fields[$field->depends_on] ?? null) : null;
            if ($parent === null) {
                continue;
            }
            $parentOf = array_column((array) json_decode((string) $field->options, true), 'parent', 'value');
            $parents = DB::table('listing_field_values')->where('field_id', $parent->id)->pluck('value', 'listing_id');

            $rows = DB::table('listing_field_values')->where('field_id', $field->id)->get(['id', 'listing_id', 'value']);
            foreach ($rows as $row) {
                $parentValue = $parentOf[$row->value] ?? null;
                if (! isset($parents[$row->listing_id]) && $parentValue !== null && $parentValue !== '') {
                    // The model is known, the make was not filled: take the model's make.
                    DB::table('listing_field_values')->insert(['listing_id' => $row->listing_id, 'field_id' => $parent->id, 'value' => $parentValue, 'value_num' => null]);
                    $parents[$row->listing_id] = $parentValue;
                    $this->touch([$row->listing_id]);
                    $this->add('marke dopunjene iz modela');
                }
                if (($parents[$row->listing_id] ?? null) !== $parentValue) {
                    DB::table('listing_field_values')->where('id', $row->id)->delete();
                    $this->touch([$row->listing_id]);
                    $this->add('modeli bez odgovarajuće marke obrisani');
                }
            }
        }
    }

    private function drop(int $fieldId, \Closure $where): void
    {
        $query = $where(DB::table('listing_field_values')->where('field_id', $fieldId));
        $this->touch((clone $query)->pluck('listing_id')->all());
        $this->add('vrednosti bez opcije obrisane', $query->delete());
    }

    // ---------------------------------------------------------------- category fields

    /** @param  array<string, object>  $fields */
    private function syncLinks(array $fields): void
    {
        $categories = DB::table('categories')->get(['id', 'parent_id', 'slug'])->keyBy('id');
        $hasChildren = $categories->pluck('parent_id')->filter()->flip();
        $bySlug = $categories->keyBy('slug');

        $chain = function (object $category) use ($categories): array {
            $slugs = [];
            for ($c = $category, $guard = 0; $c !== null && $guard < 20; $c = $c->parent_id ? $categories->get($c->parent_id) : null, $guard++) {
                $slugs[] = $c->slug;
            }

            return $slugs;
        };

        $wanted = [];
        foreach (self::links() as $i => $rule) {
            $targets = [];
            foreach ((array) ($rule['in'] ?? []) as $slug) {
                if ($bySlug->has($slug)) {
                    $targets[] = $bySlug->get($slug);
                } else {
                    $this->notes[] = 'pravilo '.($i + 1).": kategorija {$slug} ne postoji";
                }
            }
            if (isset($rule['under'])) {
                foreach ($categories as $category) {
                    if (! $hasChildren->has($category->id) && ($rule['under'] === '*' || in_array($rule['under'], $chain($category), true))) {
                        $targets[] = $category;
                    }
                }
            }
            $except = (array) ($rule['except'] ?? []);

            foreach ($targets as $category) {
                if ($except !== [] && array_intersect($except, $chain($category)) !== []) {
                    continue;
                }
                foreach ($rule['fields'] as $key) {
                    if (! isset($fields[$key])) {
                        continue;
                    }
                    $wanted[$category->id][$key] ??= ['required' => false];
                    if (in_array($key, (array) ($rule['required'] ?? []), true)) {
                        $wanted[$category->id][$key]['required'] = true;
                    }
                }
            }
        }

        $before = DB::table('category_field')->get()->map(fn ($r) => $r->category_id.':'.$r->field_id.':'.(int) $r->required.':'.$r->sort)->all();

        DB::table('category_field')->delete();
        $rows = [];
        foreach ($wanted as $categoryId => $keys) {
            $sort = 0;
            foreach ($keys as $key => $link) {
                $rows[] = ['category_id' => $categoryId, 'field_id' => $fields[$key]->id, 'required' => $link['required'], 'sort' => $sort += 10];
            }
        }
        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('category_field')->insert($chunk);
        }

        $after = array_map(fn ($r) => $r['category_id'].':'.$r['field_id'].':'.(int) $r['required'].':'.$r['sort'], $rows);
        $this->add('veze kategorija-polje', count($rows));
        $this->add('veze kategorija-polje promenjene', count(array_diff($after, $before)) + count(array_diff($before, $after)));
    }

    // ---------------------------------------------------------------- names, saved searches

    private function renameCategories(): void
    {
        $categories = DB::table('categories')->get(['id', 'parent_id', 'name'])->keyBy('id');
        foreach ($categories as $category) {
            $parent = $category->parent_id ? $categories->get($category->parent_id) : null;
            if ($parent === null) {
                continue;
            }
            $prefix = mb_strtolower(trim($parent->name)).' |';
            $name = trim($category->name);
            if (! str_starts_with(mb_strtolower($name), $prefix)) {
                continue;
            }
            $rest = trim(mb_substr($name, mb_strlen($prefix)));
            if ($rest === '') {
                continue;
            }
            $rest = mb_strtoupper(mb_substr($rest, 0, 1)).mb_substr($rest, 1);
            DB::table('categories')->where('id', $category->id)->update(['name' => $rest, 'updated_at' => now()]);
            $this->add('nazivi kategorija skraćeni');
        }
    }

    /** Saved searches keep working after their option values moved (f[model_automobila]=a4 => audi-a4). */
    private function moveSavedSearches(): void
    {
        $maps = array_filter(array_map(fn ($def) => (array) ($def['values'] ?? []), self::fields()));
        if ($maps === []) {
            return;
        }

        foreach (DB::table('saved_searches')->get(['id', 'query']) as $search) {
            $query = json_decode((string) $search->query, true);
            if (! is_array($query) || ! is_array($query['f'] ?? null)) {
                continue;
            }
            $changed = false;
            foreach ($query['f'] as $param => $raw) {
                $map = $maps[$param] ?? null;
                if ($map === null || ! is_scalar($raw)) {
                    continue;
                }
                $values = array_map('trim', explode(',', (string) $raw));
                $moved = array_values(array_filter(array_map(fn ($v) => array_key_exists($v, $map) ? $map[$v] : $v, $values), fn ($v) => $v !== null && $v !== ''));
                if ($moved !== $values) {
                    $changed = true;
                    if ($moved === []) {
                        unset($query['f'][$param]);
                    } else {
                        $query['f'][$param] = implode(',', $moved);
                    }
                }
            }
            if ($changed) {
                DB::table('saved_searches')->where('id', $search->id)->update(['query' => json_encode($query, JSON_UNESCAPED_UNICODE)]);
                $this->add('sačuvane pretrage prenete');
            }
        }
    }

    // ---------------------------------------------------------------- report

    private function add(string $what, int $n = 1): void
    {
        $this->counts[$what] = ($this->counts[$what] ?? 0) + $n;
    }

    /** @param  list<int|string>  $listingIds */
    private function touch(array $listingIds): void
    {
        foreach ($listingIds as $id) {
            $this->touched[(int) $id] = true;
        }
    }
}
