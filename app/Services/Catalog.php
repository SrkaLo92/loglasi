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
 * Catalog: the category tree with listing counts, places, fields per category, prices.
 *
 * Everything is read from the cache (one entry per kind), and models are built from cached arrays.
 * The cache is cleared by CatalogServiceProvider (model events); after bulk changes through
 * the Query Builder (e.g. Listing::where(...)->update()) call flushCounts() or flush().
 * The returned models are for reading; to change one, load the model from the database.
 */
class Catalog
{
    public const KEY_STRUCTURE = 'katalog.struktura';

    public const KEY_COUNTS = 'katalog.brojevi';

    public const KEY_LOCATIONS = 'katalog.mesta';

    public const KEY_PRICES = 'katalog.cene';

    /** Fallback cache lifetime, in case something changes without model events. */
    private const STRUCTURE_TTL = 3600;

    private const COUNTS_TTL = 600;

    /** Guard against a loop in the tree (a parent that points to a descendant). */
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

    /** @var array<int, Collection<int, Field>> */
    private array $filterFieldsFor = [];

    // ---------------------------------------------------------------- kategorije

    /**
     * All categories (inactive too), by ID, ordered by sort, then name.
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
     * Category by a URL path: "automobili" or "vozila/automobili".
     * With several parts, each must be the parent of the next. Active categories only.
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
     * Direct children, ordered.
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
     * Parents from the root to the direct parent (without the category itself).
     *
     * @return Collection<int, Category>
     */
    public function ancestors(Category|int $category): Collection
    {
        $crumbs = $this->breadcrumbs($category);

        return $crumbs->slice(0, max(0, $crumbs->count() - 1))->values();
    }

    /**
     * Breadcrumb path: from the root to the category itself.
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
     * ID of the category and all its subcategories, for the listing filter (whereIn('category_id', ...)).
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

    /** Active category whose parents are all active. */
    public function isVisible(Category|int $category): bool
    {
        $crumbs = $this->breadcrumbs($category);

        return $crumbs->isNotEmpty()
            && $crumbs->every(fn (Category $c) => $c->is_active)
            && $crumbs->first()->parent_id === null;
    }

    /**
     * Tree of active categories: [['category' => Category, 'count' => int, 'children' => [...]], ...].
     * A root is a category without a parent.
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
     * Number of published listings per category, with subcategories. A listing has one
     * category, so it is counted exactly once in the parent.
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

    // ---------------------------------------------------------------- fields

    /**
     * Fields for a category: inherited from the parents, then its own. Order: fields closer to the
     * root first, then `sort` from the link, then the field's `sort`. A category can change
     * `required` for an inherited field. Each field has the attributes `required` (bool) and `position`.
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

    /**
     * Fields a list of this category can be filtered by: its own (and inherited) fields, then the
     * fields of its subcategories. On "Automobili" that is
     * make, model, year... of the body types, although "Automobili" itself has no fields.
     * Every field is optional here (`required` false).
     *
     * @return Collection<int, Field>
     */
    public function filterFieldsFor(Category|int $category): Collection
    {
        $id = $category instanceof Category ? $category->id : $category;
        if (isset($this->filterFieldsFor[$id])) {
            return $this->filterFieldsFor[$id];
        }

        $own = $this->fieldsFor($id);
        $picked = $own->keyBy('id')->all();
        $map = $this->childrenMap();

        // Leaves with the most fields lead (on "Automobili" the body types, not "Auto delovi"), so the
        // filters keep the order of the form: make, model, year... and what other leaves add comes after.
        $leaves = [];
        foreach ($this->descendantIds($id, false) as $descendantId) {
            if (! isset($map[$descendantId]) && $this->category($descendantId)?->is_active) {
                $leaves[] = $this->fieldsFor($descendantId);
            }
        }
        usort($leaves, fn ($a, $b) => $b->count() <=> $a->count());

        $extra = [];
        foreach ($leaves as $fields) {
            foreach ($fields as $field) {
                if (! isset($picked[$field->id]) && ! isset($extra[$field->id])) {
                    $extra[$field->id] = $field;
                }
            }
        }

        $list = $own->all();
        foreach ($extra as $field) {
            $copy = clone $field;
            $copy->setAttribute('required', false);
            $list[] = $copy;
        }

        return $this->filterFieldsFor[$id] = collect($list)->values();
    }

    public function field(string $key): ?Field
    {
        $this->loadStructure();

        return $this->fields->first(fn (Field $f) => $f->key === $key);
    }

    // ---------------------------------------------------------------- places and prices

    /**
     * Places by sort, then name.
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
     * Active products (prices from the admin), by sort.
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

    // ---------------------------------------------------------------- cache

    /** Clears the cache of categories, fields and counts. */
    public function flush(): void
    {
        Cache::forget(self::KEY_STRUCTURE);
        $this->categories = $this->fields = null;
        $this->pivot = $this->byParent = $this->bySlug = $this->childIds = null;
        $this->fieldsFor = $this->filterFieldsFor = [];
        $this->flushCounts();
    }

    /** Clears only the listing counts (after a listing changes). */
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
