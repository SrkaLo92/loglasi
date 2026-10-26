<?php

namespace App\WordPressImport;

use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/** Reads from the WordPress database (connection "wordpress", table prefix from WP_DB_PREFIX). */
class WordPress
{
    public const CONNECTION = 'wordpress';

    public function __construct(private ?Connection $db = null) {}

    /** Registers the connection from config/wordpress_import.php if it is not set up yet. */
    public static function register(): void
    {
        if (! config('database.connections.'.self::CONNECTION)) {
            config(['database.connections.'.self::CONNECTION => config('wordpress_import.connection')]);
        }
    }

    public function db(): Connection
    {
        return $this->db ??= DB::connection(self::CONNECTION);
    }

    public function table(string $name): Builder
    {
        return $this->db()->table($name);
    }

    public function prefix(): string
    {
        return $this->db()->getTablePrefix();
    }

    public function hasTable(string $name): bool
    {
        return $this->db()->getSchemaBuilder()->hasTable($name);
    }

    /** @return array<string, string> */
    public function options(array $names): array
    {
        return $this->table('options')->whereIn('option_name', $names)->pluck('option_value', 'option_name')->all();
    }

    /**
     * Meta per object: [id => [key => [values]]].
     *
     * @param  int[]  $ids
     * @return array<int, array<string, array<int, string|null>>>
     */
    public function postMeta(array $ids, ?array $keys = null): array
    {
        return $this->meta('postmeta', 'post_id', $ids, $keys);
    }

    /** @return array<int, array<string, array<int, string|null>>> */
    public function userMeta(array $ids, ?array $keys = null): array
    {
        return $this->meta('usermeta', 'user_id', $ids, $keys);
    }

    /** @return array<int, array<string, array<int, string|null>>> */
    private function meta(string $table, string $column, array $ids, ?array $keys): array
    {
        $out = [];
        foreach (array_chunk(array_values(array_unique($ids)), 1000) as $chunk) {
            $query = $this->table($table)->whereIn($column, $chunk)->orderBy($table === 'usermeta' ? 'umeta_id' : 'meta_id');
            if ($keys !== null) {
                $query->whereIn('meta_key', $keys);
            }
            foreach ($query->get([$column, 'meta_key', 'meta_value']) as $row) {
                $out[(int) $row->{$column}][(string) $row->meta_key][] = $row->meta_value;
            }
        }

        return $out;
    }

    /**
     * Object terms: [object_id => [taxonomy => [term_id, ...]]].
     *
     * @param  int[]  $ids
     * @param  string[]  $taxonomies
     * @return array<int, array<string, int[]>>
     */
    public function objectTerms(array $ids, array $taxonomies): array
    {
        $out = [];
        if (! $taxonomies) {
            return $out;
        }
        foreach (array_chunk(array_values(array_unique($ids)), 1000) as $chunk) {
            $rows = $this->table('term_relationships as tr')
                ->join('term_taxonomy as tt', 'tt.term_taxonomy_id', '=', 'tr.term_taxonomy_id')
                ->whereIn('tr.object_id', $chunk)
                ->whereIn('tt.taxonomy', $taxonomies)
                ->orderBy('tr.term_order')
                ->get(['tr.object_id', 'tt.taxonomy', 'tt.term_id']);
            foreach ($rows as $row) {
                $out[(int) $row->object_id][(string) $row->taxonomy][] = (int) $row->term_id;
            }
        }

        return $out;
    }

    /**
     * All terms of a taxonomy, parents before children.
     *
     * @return array<int, object{term_id:int, name:string, slug:string, parent:int, description:string}>
     */
    public function terms(string $taxonomy): array
    {
        $rows = $this->table('term_taxonomy as tt')
            ->join('terms as t', 't.term_id', '=', 'tt.term_id')
            ->where('tt.taxonomy', $taxonomy)
            ->orderBy('t.term_id')
            ->get(['t.term_id', 't.name', 't.slug', 'tt.parent', 'tt.description']);

        $byId = [];
        foreach ($rows as $row) {
            $row->term_id = (int) $row->term_id;
            $row->parent = (int) $row->parent;
            $byId[$row->term_id] = $row;
        }

        $depth = function (int $id) use ($byId): int {
            $d = 0;
            while (isset($byId[$id]) && $byId[$id]->parent > 0 && $d < 20) {
                $id = $byId[$id]->parent;
                $d++;
            }

            return $d;
        };
        foreach ($byId as $row) {
            $row->depth = $depth($row->term_id);
        }

        uasort($byId, fn ($a, $b) => [$a->depth, $a->term_id] <=> [$b->depth, $b->term_id]);

        return $byId;
    }

    /** @return array<int, array<int|string, mixed>> */
    public function termMeta(array $termIds): array
    {
        $out = [];
        foreach (array_chunk($termIds, 1000) as $chunk) {
            foreach ($this->table('termmeta')->whereIn('term_id', $chunk)->get(['term_id', 'meta_key', 'meta_value']) as $row) {
                $out[(int) $row->term_id][(string) $row->meta_key] ??= $row->meta_value;
            }
        }

        return $out;
    }

    /** Taxonomy the term belongs to, or null. */
    public function taxonomyOfTerm(int $termId): ?string
    {
        $tax = $this->table('term_taxonomy')->where('term_id', $termId)->value('taxonomy');

        return $tax === null ? null : (string) $tax;
    }
}
