<?php

namespace App\Http\Controllers\Search;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Location;
use App\Services\Catalog;
use App\Services\ListingFields;
use App\Services\ListingSearch;
use App\Support\Search\WebFilters;
use App\Support\Seo;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Web lists: /oglasi/, /oglasi/{kategorija}/, /oglasi/{kategorija}/{mesto}/ and their
 * /page/N/ pages. Filters are a GET form, so everything works without JavaScript.
 */
class ListingListController extends Controller
{
    public function __construct(
        private readonly Catalog $catalog,
        private readonly ListingSearch $search,
        private readonly ListingFields $fields,
    ) {}

    public function all(Request $request, ?string $page = null)
    {
        return $this->show($request, null, null, $page);
    }

    public function category(Request $request, string $category, ?string $page = null)
    {
        return $this->show($request, $this->findCategory($category), null, $page);
    }

    /** Second part of the path: a place, or a subcategory (old nested address, 301 to /oglasi/{sub}/). */
    public function categoryPlace(Request $request, string $category, string $second, ?string $page = null)
    {
        $parent = $this->findCategory($category);

        $child = $this->catalog->categoryBySlug($second);
        if ($child !== null && (int) $child->parent_id === $parent->id && $this->catalog->isVisible($child)) {
            $query = $request->getQueryString();

            return redirect()->to(url_slash('/oglasi/'.$child->slug.($page ? '/page/'.$page : '')).($query ? '?'.$query : ''), 301);
        }

        $location = $this->catalog->locationBySlug($second);
        if ($location === null) {
            throw new NotFoundHttpException;
        }

        return $this->show($request, $parent, $location, $page);
    }

    /** /?s=... from the old WordPress search: the same list, no redirect (noindex). */
    public function legacySearch(Request $request)
    {
        return $this->show($request, null, null, null);
    }

    private function show(Request $request, ?Category $category, ?Location $location, ?string $pathPage)
    {
        $page = $this->page($request, $pathPage);
        if ($pathPage !== null && $page === 1) {
            // /oglasi/page/1/ is the list itself.
            $query = $request->getQueryString();

            return redirect()->to((new WebFilters($category, $location))->url([], 1).($query ? '?'.$query : ''), 301);
        }

        $filters = WebFilters::fromRequest($request, $category, $location, $page);
        if (is_string($filters)) {
            return redirect()->to($filters, 302);
        }

        $results = $this->search->paginate($filters->params);
        if ($page > 1 && $page > $results->lastPage()) {
            throw new NotFoundHttpException;
        }
        $results->getCollection()->load('fieldValues');

        $total = $results->total();
        $this->seo($filters, $total);

        if ($filters->category && ! $filters->location && $page === 1 && ! $filters->hasFilters()) {
            Seo::get()->itemList($results->getCollection(), '/oglasi/'.$filters->category->slug.'/');
        }

        return view('search.list', [
            'filters' => $filters,
            'results' => $results,
            'total' => $total,
            'category' => $filters->category,
            'location' => $filters->location,
            'crumbs' => $filters->category ? $this->catalog->breadcrumbs($filters->category) : collect(),
            'categoryLinks' => $this->categoryLinks($filters),
            'fieldFilters' => $filters->category ? $this->fields->filters($filters->category) : [],
            'locations' => $this->catalog->locations()->values(),
        ]);
    }

    private function findCategory(string $slug): Category
    {
        $category = $this->catalog->categoryBySlug($slug);
        if ($category === null || ! $this->catalog->isVisible($category)) {
            throw new NotFoundHttpException;
        }

        return $category;
    }

    /** Page from /page/N/ or the WordPress ?page= / ?paged=. */
    private function page(Request $request, ?string $pathPage): int
    {
        foreach ([$pathPage, $request->query('paged'), $request->query('page')] as $value) {
            if (is_string($value) && preg_match('/^[1-9]\d{0,5}$/', $value)) {
                return (int) $value;
            }
        }
        if ($pathPage !== null) {
            throw new NotFoundHttpException;
        }

        return 1;
    }

    /**
     * Title, description, robots and canonical by the SEO rules. They describe the clean page,
     * so with filters the count is the cached one of the page without them.
     */
    private function seo(WebFilters $filters, int $total): void
    {
        $count = $filters->hasFilters()
            ? $this->search->count(['category' => $filters->category?->id, 'location' => $filters->location?->id])
            : $total;

        if ($filters->category) {
            Seo::for($filters->category, ['location' => $filters->location, 'count' => $count]);
        } else {
            Seo::get()->listings($count);
        }

        $keyword = trim((string) ($filters->params['keyword'] ?? $filters->params['q'] ?? ''));
        if ($keyword !== '') {
            Seo::get()->title(Seo::withSuffix(Seo::text('search.title', ['{upit}' => $keyword])));
        }
    }

    /**
     * Category navigation of the sidebar: up link, the current category, its children.
     *
     * @return array{up: ?array, current: ?array, items: list<array>}
     */
    private function categoryLinks(WebFilters $filters): array
    {
        $counts = $this->catalog->counts();
        $link = fn (Category $c) => [
            'name' => $c->name,
            'count' => $counts[$c->id] ?? 0,
            'url' => $filters->urlFor($c, $filters->location),
        ];

        $current = $filters->category;
        if ($current === null) {
            $items = $this->catalog->children(null)->map($link)->filter(fn ($i) => $i['count'] > 0)->values()->all();

            return ['up' => null, 'current' => null, 'items' => $items];
        }

        $children = $this->catalog->children($current)->map($link)->filter(fn ($i) => $i['count'] > 0)->values()->all();
        $parent = $current->parent_id ? $this->catalog->category((int) $current->parent_id) : null;

        return [
            'up' => $parent
                ? ['name' => $parent->name, 'url' => $filters->urlFor($parent, $filters->location)]
                : ['name' => 'Svi oglasi', 'url' => $filters->urlFor(null, $filters->location)],
            'current' => ['name' => $current->name, 'count' => $counts[$current->id] ?? 0],
            'items' => $children,
        ];
    }
}
