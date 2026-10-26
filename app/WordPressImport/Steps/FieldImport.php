<?php

namespace App\WordPressImport\Steps;

use App\Enums\FieldFilter;
use App\Enums\FieldType;
use App\WordPressImport\Text;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Dodatna polja oglasa. Listivo čuva polja izbora kao taksonomije listivo_<ID>,
 * a broj/tekst kao meta listivo_<ID>; <ID> je objava sa definicijom polja (naslov = naziv).
 * Polja se pronalaze iz podataka, a config('wordpress_import.fields') može da pregazi ključ, naziv, tip i jedinicu.
 */
class FieldImport extends Step
{
    /** @var array<string, true> */
    private array $usedKeys = [];

    public function run(): void
    {
        $definitions = array_merge($this->taxonomyFields(), $this->metaFields(), $this->loglasiFields());

        $sort = 0;
        foreach ($definitions as $wpKey => $def) {
            $this->report->wp('fields');
            $def = array_merge($def, (array) (config('wordpress_import.fields')[$wpKey] ?? []));

            $type = FieldType::tryFrom((string) $def['type']) ?? FieldType::Text;
            $filter = FieldFilter::tryFrom((string) ($def['filter'] ?? '')) ?? match ($type) {
                FieldType::Number => FieldFilter::Range,
                FieldType::Select => FieldFilter::Select,
                FieldType::Multiselect => FieldFilter::Multi,
                default => FieldFilter::None,
            };

            $existing = DB::table('fields')->where('wp_key', $wpKey)->first();
            $key = $this->uniqueKey((string) ($def['key'] ?? '') ?: Str::slug($def['label'], '_'), $wpKey);

            $values = [
                'key' => $key,
                'label' => Str::limit($def['label'], 250, ''),
                'type' => $type->value,
                'filter' => $filter->value,
                'unit' => $def['unit'] ?? null,
                'options' => $def['options'] ? json_encode($def['options'], JSON_UNESCAPED_UNICODE) : null,
                'updated_at' => now(),
            ];

            if ($existing) {
                DB::table('fields')->where('id', $existing->id)->update($values);
            } else {
                DB::table('fields')->insert($values + ['wp_key' => $wpKey, 'sort' => ++$sort, 'created_at' => now()]);
            }
            $this->report->imported('fields');
            $this->report->note('fields', "{$wpKey} → {$key} („{$def['label']}”, {$type->value}, filter {$filter->value})");
        }
    }

    private function uniqueKey(string $key, string $wpKey): string
    {
        $key = Str::limit(trim($key, '_') ?: 'polje', 60, '');
        $base = $key;
        $i = 2;
        while (isset($this->usedKeys[$key]) || DB::table('fields')->where('key', $key)->where(fn ($q) => $q->whereNull('wp_key')->orWhere('wp_key', '!=', $wpKey))->exists()) {
            $key = $base.'_'.$i++;
        }
        $this->usedKeys[$key] = true;

        return $key;
    }

    /** Taksonomije listivo_<ID> koje oglasi koriste (bez kategorije, mesta i valute). */
    private function taxonomyFields(): array
    {
        $exclude = [config('wordpress_import.tax_category'), config('wordpress_import.tax_location'), $this->wp->taxonomyOfTerm((int) config('wordpress_import.currency_term_eur'))];

        $all = $this->wp->table('term_taxonomy')->where('taxonomy', 'like', 'listivo\_%')->distinct()->pluck('taxonomy')
            ->filter(fn ($t) => preg_match('/^listivo_\d+$/', $t) && ! in_array($t, $exclude, true))->values()->all();

        $used = $this->listingTerms()
            ->whereIn('tt.taxonomy', $all ?: [''])
            ->distinct()->pluck('tt.taxonomy')->all();

        foreach (array_diff($all, $used) as $unused) {
            $this->report->note('fields', "{$unused}: nijedan oglas ga ne koristi, nije preneto");
        }

        $multi = $this->listingTerms()
            ->whereIn('tt.taxonomy', $used ?: [''])
            ->groupBy('tr.object_id', 'tt.taxonomy')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('tt.taxonomy')->unique()->all();

        $labels = $this->labels($used);
        $out = [];
        foreach ($used as $taxonomy) {
            $options = [];
            foreach ($this->wp->terms($taxonomy) as $term) {
                $options[] = ['value' => rawurldecode((string) $term->slug), 'label' => Text::title((string) $term->name)];
            }
            $out[$taxonomy] = [
                'label' => $labels[$taxonomy],
                'type' => in_array($taxonomy, $multi, true) ? 'multiselect' : 'select',
                'options' => $options,
            ];
        }

        return $out;
    }

    /** Meta listivo_<ID> na oglasima (broj ili tekst), bez cene i galerije. */
    private function metaFields(): array
    {
        $priceField = 'listivo_'.(int) config('wordpress_import.price_field_id');
        $exclude = array_filter([$priceField, config('wordpress_import.meta.gallery'), config('wordpress_import.meta.featured'), config('wordpress_import.meta.expires'), config('wordpress_import.meta.lat'), config('wordpress_import.meta.lng'), config('wordpress_import.meta.listing_phone')]);

        $keys = $this->wp->table('postmeta as pm')
            ->join('posts as p', 'p.ID', '=', 'pm.post_id')
            ->where('p.post_type', config('wordpress_import.post_type'))
            ->where('pm.meta_key', 'like', 'listivo\_%')
            ->distinct()->pluck('pm.meta_key')
            ->filter(fn ($k) => preg_match('/^listivo_\d+$/', $k) && ! in_array($k, $exclude, true))->values()->all();

        $labels = $this->labels($keys);
        $out = [];
        foreach ($keys as $key) {
            $values = $this->wp->table('postmeta')->where('meta_key', $key)->where('meta_value', '!=', '')->limit(500)->pluck('meta_value');
            $numeric = $values->isNotEmpty() && $values->every(fn ($v) => Text::number($v) !== null);
            $out[$key] = ['label' => $labels[$key], 'type' => $numeric ? 'number' : 'text', 'options' => []];
        }

        return $out;
    }

    /** Privremena polja loglasi-core (loglasi_stanje...), samo ako ih neki oglas ima. */
    private function loglasiFields(): array
    {
        $defs = (array) config('wordpress_import.meta_fields');
        if (! $defs) {
            return [];
        }

        $used = $this->wp->table('postmeta as pm')
            ->join('posts as p', 'p.ID', '=', 'pm.post_id')
            ->where('p.post_type', config('wordpress_import.post_type'))
            ->whereIn('pm.meta_key', array_keys($defs))
            ->distinct()->pluck('pm.meta_key')->all();

        $out = [];
        foreach ($used as $metaKey) {
            $def = $defs[$metaKey];
            $def['options'] = array_map(fn (string $label) => ['value' => Str::slug($label), 'label' => $label], $def['options'] ?? []);
            $out[$metaKey] = $def;
        }

        return $out;
    }

    private function listingTerms()
    {
        return $this->wp->table('term_relationships as tr')
            ->join('term_taxonomy as tt', 'tt.term_taxonomy_id', '=', 'tr.term_taxonomy_id')
            ->join('posts as p', 'p.ID', '=', 'tr.object_id')
            ->where('p.post_type', config('wordpress_import.post_type'));
    }

    /**
     * Naziv polja = naslov Listivo objave sa istim ID-jem.
     *
     * @param  string[]  $keys
     * @return array<string, string>
     */
    private function labels(array $keys): array
    {
        $ids = array_map(fn ($k) => (int) substr($k, strlen('listivo_')), $keys);
        $titles = $ids ? $this->wp->table('posts')->whereIn('ID', $ids)->where('post_type', '!=', config('wordpress_import.post_type'))->pluck('post_title', 'ID') : collect();

        $out = [];
        foreach ($keys as $i => $key) {
            $title = Text::title((string) ($titles[$ids[$i]] ?? ''));
            if ($title === '') {
                $title = 'Polje '.$ids[$i];
                $this->report->note('fields', "{$key}: naziv nije pronađen, upisano „{$title}” (ispraviti u adminu)");
            }
            $out[$key] = $title;
        }

        return $out;
    }
}
