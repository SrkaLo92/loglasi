<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Field;
use App\Models\Listing;
use App\Models\Location;
use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Katalog: stablo kategorija sa brojem oglasa, mesta, polja po kategoriji, cene.
 *
 * Sve se čita iz keša (jedan zapis po vrsti), a modeli se prave iz keširanih nizova.
 * Keš briše KatalogServiceProvider (događaji modela); posle masovnih izmena preko
 * Query Builder-a (npr. Listing::where(...)->update()) pozvati flushCounts() ili flush().
 * Vraćeni modeli služe za čitanje; za izmenu učitati model iz baze.
 */
class Catalog
{
    public const KEY_STRUCTURE = 'katalog.struktura';

    public const KEY_COUNTS = 'katalog.brojevi';

    public const KEY_LOCATIONS = 'katalog.mesta';

    public const KEY_PRICES = 'katalog.cene';

    /** Rezervni rok keša, za slučaj izmene mimo događaja modela. */
    private const STRUCTURE_TTL = 3600;

    private const COUNTS_TTL = 600;

    /** Zaštita od petlje u stablu (roditelj koji pokazuje na potomka). */
    private const MAX_DEPTH = 10;

    /** @var Collection<int, Category>|null */
    private ?Collection $categories = null;

    /** @var Collection<int, Field>|null */
    private ?Collection $fields = null;

    /** @var array<int, list<array{field_id: int, required: bool, sort: int}>>|null */
    private ?array $pivot = null;

    /** @var array<int, int>|null */
    private ?array $counts = null;

    /** Children of each parent (0 = root), in category order; built once per request. @var array<int, list<Category>>|null */
    private ?array $byParent = null;

    /** @var array<string, Category>|null */
    private ?array $bySlug = null;

    /** @var array<int, list<int>>|null */
    private ?array $childIds = null;

    /** @var array<int, Collection<int, Field>> */
    private array $fieldsFor = [];

    // ---------------------------------------------------------------- kategorije

    /**
     * Sve kategorije (i neaktivne), po ID-ju, poređane po sort pa nazivu.
     *
     * @return Collection<int, Category>
     */
    public function categories(): Collection
    {
        $this->loadStructure();

        return $this->categories;
    }

    public function category(int $id): ?Category
    {
        return $this->categories()->get($id);
    }

    public function categoryBySlug(string $slug): ?Category
    {
        $this->loadStructure();

        return $this->bySlug[$slug] ?? null;
    }

    /**
     * Kategorija po putanji iz URL-a: "automobili" ili "vozila/automobili".
     * Kod više delova svaki mora biti roditelj sledećeg. Samo aktivne kategorije.
     */
    public function categoryByPath(string $path): ?Category
    {
        $slugs = array_values(array_filter(explode('/', trim($path, '/')), 'strlen'));
        if ($slugs === []) {
            return null;
        }

        $category = $this->categoryBySlug((string) end($slugs));
        if ($category === null || ! $this->isVisible($category)) {
            return null;
        }

        if (count($slugs) > 1) {
            $chain = $this->breadcrumbs($category)->pluck('slug')->all();
            if (array_slice($chain, -count($slugs)) !== $slugs) {
                return null;
            }
        }

        return $category;
    }

    /**
     * Direktna deca, poređana.
     *
     * @return Collection<int, Category>
     */
    public function children(Category|int|null $parent, bool $activeOnly = true): Collection
    {
        $parentId = $parent instanceof Category ? $parent->id : $parent;
        $this->loadStructure();
        $children = collect($this->byParent[(int) $parentId] ?? []);

        return $activeOnly ? $children->filter(fn (Category $c) => (bool) $c->is_active)->values() : $children;
    }

    /**
     * Roditelji od korena do direktnog roditelja (bez same kategorije).
     *
     * @return Collection<int, Category>
     */
    public function ancestors(Category|int $category): Collection
    {
        $crumbs = $this->breadcrumbs($category);

        return $crumbs->slice(0, max(0, $crumbs->count() - 1))->values();
    }

    /**
     * Putanja za mrvice: od korena do same kategorije.
     *
     * @return Collection<int, Category>
     */
    public function breadcrumbs(Category|int $category): Collection
    {
        $current = $this->category($category instanceof Category ? $category->id : $category);
        $chain = [];
        $seen = [];

        while ($current !== null && ! isset($seen[$current->id]) && count($chain) <= self::MAX_DEPTH) {
            $seen[$current->id] = true;
            array_unshift($chain, $current);
            $current = $current->parent_id ? $this->category((int) $current->parent_id) : null;
        }

        return collect($chain);
    }

    /**
     * ID kategorije i svih potkategorija, za filter oglasa (whereIn('category_id', ...)).
     *
     * @return list<int>
     */
    public function descendantIds(Category|int $category, bool $includeSelf = true): array
    {
        $rootId = $category instanceof Category ? $category->id : $category;
        $byParent = $this->childrenMap();

        $ids = $includeSelf ? [$rootId] : [];
        $queue = [[$rootId, 0]];
        $seen = [$rootId => true];

        while ($queue) {
            [$id, $depth] = array_shift($queue);
            if ($depth >= self::MAX_DEPTH) {
                continue;
            }
            foreach ($byParent[$id] ?? [] as $childId) {
                if (! isset($seen[$childId])) {
                    $seen[$childId] = true;
                    $ids[] = $childId;
                    $queue[] = [$childId, $depth + 1];
                }
            }
        }

        return $ids;
    }

    /** Aktivna kategorija čiji su svi roditelji aktivni. */
    public function isVisible(Category|int $category): bool
    {
        $crumbs = $this->breadcrumbs($category);

        return $crumbs->isNotEmpty()
            && $crumbs->every(fn (Category $c) => $c->is_active)
            && $crumbs->first()->parent_id === null;
    }

    /**
     * Stablo aktivnih kategorija: [['category' => Category, 'count' => int, 'children' => [...]], ...].
     * Koren je kategorija bez roditelja.
     *
     * @return list<array{category: Category, count: int, children: array}>
     */
    public function tree(): array
    {
        $counts = $this->counts();

        $build = function (?int $parentId, int $depth) use (&$build, $counts): array {
            if ($depth > self::MAX_DEPTH) {
                return [];
            }

            return $this->children($parentId)
                ->map(fn (Category $c) => [
                    'category' => $c,
                    'count' => $counts[$c->id] ?? 0,
                    'children' => $build($c->id, $depth + 1),
                ])
                ->all();
        };

        return $build(null, 0);
    }

    /**
     * Broj objavljenih oglasa po kategoriji, sa potkategorijama. Oglas ima jednu
     * kategoriju, pa se u roditelju broji tačno jednom.
     *
     * @return array<int, int>
     */
    public function counts(): array
    {
        if ($this->counts !== null) {
            return $this->counts;
        }

        $own = Cache::remember(self::KEY_COUNTS, self::COUNTS_TTL, fn () => Listing::query()
            ->published()
            ->toBase()
            ->selectRaw('category_id, COUNT(*) AS total')
            ->groupBy('category_id')
            ->pluck('total', 'category_id')
            ->map(fn ($n) => (int) $n)
            ->all());

        // Each category's own listings are added to it and to every ancestor (one walk up per category).
        $categories = $this->categories();
        $counts = array_fill_keys($categories->keys()->all(), 0);
        foreach ($own as $categoryId => $total) {
            $seen = [];
            for ($id = (int) $categoryId, $depth = 0; $id && isset($counts[$id]) && ! isset($seen[$id]) && $depth <= self::MAX_DEPTH; $depth++) {
                $seen[$id] = true;
                $counts[$id] += $total;
                $parentId = $categories[$id]->parent_id;
                $id = $parentId === null ? 0 : (int) $parentId;
            }
        }

        return $this->counts = $counts;
    }

    public function count(Category|int $category): int
    {
        return $this->counts()[$category instanceof Category ? $category->id : $category] ?? 0;
    }

    // ---------------------------------------------------------------- polja

    /**
     * Polja za kategoriju: nasleđena od roditelja pa sopstvena. Redosled: polja bližeg
     * korenu prvo, pa `sort` iz veze, pa `sort` polja. Kategorija može da promeni
     * `required` za nasleđeno polje. Svako polje ima atribute `required` (bool) i `position`.
     *
     * @return Collection<int, Field>
     */
    public function fieldsFor(Category|int $category): Collection
    {
        $id = $category instanceof Category ? $category->id : $category;
        if (isset($this->fieldsFor[$id])) {
            return $this->fieldsFor[$id];
        }

        $this->loadStructure();
        $picked = [];

        foreach ($this->breadcrumbs($id)->values() as $depth => $node) {
            foreach ($this->pivot[$node->id] ?? [] as $link) {
                $fieldId = $link['field_id'];
                if (isset($picked[$fieldId])) {
                    $picked[$fieldId]['required'] = $link['required'];

                    continue;
                }
                $picked[$fieldId] = $link + ['depth' => $depth];
            }
        }

        $list = [];
        foreach ($picked as $fieldId => $link) {
            $source = $this->fields->get($fieldId);
            if ($source === null) {
                continue;
            }
            $field = clone $source;
            $field->setAttribute('required', $link['required']);
            $field->setAttribute('position', $link['sort']);
            $list[] = [$link['depth'], $link['sort'], (int) $field->sort, $field->id, $field];
        }

        usort($list, fn ($a, $b) => array_slice($a, 0, 4) <=> array_slice($b, 0, 4));

        return $this->fieldsFor[$id] = collect(array_column($list, 4))->values();
    }

    public function field(string $key): ?Field
    {
        $this->loadStructure();

        return $this->fields->first(fn (Field $f) => $f->key === $key);
    }

    // ---------------------------------------------------------------- mesta i cene

    /**
     * Mesta po sort pa nazivu.
     *
     * @return Collection<int, Location>
     */
    public function locations(): Collection
    {
        $rows = Cache::remember(self::KEY_LOCATIONS, self::STRUCTURE_TTL, fn () => Location::query()
            ->orderBy('sort')->orderBy('name')->get()
            ->map(fn (Location $l) => $l->getAttributes())->all());

        return Location::hydrate($rows)->keyBy('id');
    }

    public function location(int $id): ?Location
    {
        return $this->locations()->get($id);
    }

    public function locationBySlug(string $slug): ?Location
    {
        return $this->locations()->first(fn (Location $l) => $l->slug === $slug);
    }

    /**
     * Aktivni proizvodi (cene iz admina), po sort.
     *
     * @return Collection<int, Product>
     */
    public function prices(): Collection
    {
        $rows = Cache::remember(self::KEY_PRICES, self::STRUCTURE_TTL, fn () => Product::query()
            ->where('is_active', true)->orderBy('sort')->orderBy('id')->get()
            ->map(fn (Product $p) => $p->getAttributes())->all());

        return Product::hydrate($rows);
    }

    // ---------------------------------------------------------------- keš

    /** Briše keš kategorija, polja i brojeva. */
    public function flush(): void
    {
        Cache::forget(self::KEY_STRUCTURE);
        $this->categories = $this->fields = null;
        $this->pivot = $this->byParent = $this->bySlug = $this->childIds = null;
        $this->fieldsFor = [];
        $this->flushCounts();
    }

    /** Briše samo brojeve oglasa (posle promene oglasa). */
    public function flushCounts(): void
    {
        Cache::forget(self::KEY_COUNTS);
        $this->counts = null;
    }

    public function flushLocations(): void
    {
        Cache::forget(self::KEY_LOCATIONS);
    }

    public function flushPrices(): void
    {
        Cache::forget(self::KEY_PRICES);
    }

    private function loadStructure(): void
    {
        if ($this->categories !== null) {
            return;
        }

        $data = Cache::remember(self::KEY_STRUCTURE, self::STRUCTURE_TTL, fn () => [
            'categories' => Category::query()->orderBy('sort')->orderBy('name')->orderBy('id')->get()
                ->map(fn (Category $c) => $c->getAttributes())->all(),
            'fields' => Field::query()->orderBy('sort')->orderBy('id')->get()
                ->map(fn (Field $f) => $f->getAttributes())->all(),
            'pivot' => DB::table('category_field')->orderBy('sort')->orderBy('field_id')
                ->get(['category_id', 'field_id', 'required', 'sort'])
                ->map(fn ($row) => (array) $row)->all(),
        ]);

        $this->categories = Category::hydrate($data['categories'])->keyBy('id');
        $this->fields = Field::hydrate($data['fields'])->keyBy('id');

        $this->byParent = $this->bySlug = [];
        foreach ($this->categories as $category) {
            $this->byParent[(int) $category->parent_id][] = $category;
            $this->bySlug[$category->slug] ??= $category;
        }

        $this->pivot = [];
        foreach ($data['pivot'] as $row) {
            $this->pivot[(int) $row['category_id']][] = [
                'field_id' => (int) $row['field_id'],
                'required' => (bool) $row['required'],
                'sort' => (int) $row['sort'],
            ];
        }
    }

    /** @return array<int, list<int>> */
    private function childrenMap(): array
    {
        $this->loadStructure();
        if ($this->childIds !== null) {
            return $this->childIds;
        }
        $map = [];
        foreach ($this->byParent as $parentId => $children) {
            if ($parentId !== 0) {
                $map[$parentId] = array_map(fn (Category $c) => (int) $c->id, $children);
            }
        }

        return $this->childIds = $map;
    }
}
