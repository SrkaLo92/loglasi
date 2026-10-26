<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Api\ApiError;
use App\Http\Api\ApiResponse;
use App\Http\Api\CatalogFormatter;
use App\Http\Controllers\Controller;
use App\Services\Catalog;
use App\Services\ListingFields;
use App\Support\Search\CategorySheet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** GET /categories, /locations, /fields?category=ID, /categories/{id}/fields, /fields/{key}/options, /prices. */
class CatalogApiController extends Controller
{
    public function __construct(private readonly Catalog $catalog) {}

    /** Category tree with the number of published listings (subcategories included). */
    public function categories(Request $request): Response|JsonResponse
    {
        $tree = array_map([CatalogFormatter::class, 'categoryNode'], $this->catalog->tree());

        return ApiResponse::json($request, $tree, ApiResponse::CACHE_CATALOG);
    }

    /** "Sve kategorije" for the app: most wanted, then themes of tiles (the same as the site's sheet). */
    public function browse(Request $request): Response|JsonResponse
    {
        $item = fn (array $c) => [
            'id' => $c['id'],
            'slug' => $c['slug'],
            'name' => $c['name'],
            'parent_name' => $c['path'],
            'icon' => $c['icon'],
            'image' => $c['image'],
            'count' => $c['count'],
            'color' => $c['color'],
        ];
        $sheet = CategorySheet::get();

        return ApiResponse::json($request, [
            'main' => array_map(fn (array $c) => $item($c) + ['accent' => $c['accent'], 'tagline' => $c['tagline']], $sheet['main']),
            'popular' => array_map($item, $sheet['popular']),
            'themes' => array_map(fn ($t) => ['name' => $t['name'], 'items' => array_map($item, $t['items'])], $sheet['themes']),
        ], ApiResponse::CACHE_CATALOG);
    }

    /** Mesta. */
    public function locations(Request $request): Response|JsonResponse
    {
        $locations = $this->catalog->locations()->map([CatalogFormatter::class, 'locationRef'])->values();

        return ApiResponse::json($request, $locations, ApiResponse::CACHE_CATALOG);
    }

    /** Fields and filters for a category: ?category=ID or /categories/{id}/fields (a parent gets its subcategories' fields for filters). */
    public function fields(Request $request, ListingFields $fields, ?string $id = null): Response|JsonResponse
    {
        $param = $id === null ? 'category' : 'id';
        $raw = $id ?? $request->query('category');

        if ($raw === null || $raw === '') {
            throw ApiError::missingParams($param);
        }
        if (! is_string($raw) || ! preg_match('/^[1-9]\d{0,9}$/', $raw)) {
            throw ApiError::invalidParams([$param => 'Mora biti pozitivan ceo broj.']);
        }

        $category = $this->catalog->category((int) $raw);
        if ($category === null || ! $this->catalog->isVisible($category)) {
            throw ApiError::notFound('Kategorija ne postoji.');
        }

        // A last-level category gives its form fields; a parent gives what its list can be filtered by.
        $out = $this->catalog->filterFieldsFor($category)->map(fn ($field) => $fields->toApi($field))->values();

        return ApiResponse::json($request, $out, ApiResponse::CACHE_CATALOG);
    }

    /**
     * Options of a dependent field for the chosen parents (models of a make): ?parent=audi,bmw.
     * The site loads them on demand instead of putting ~1,900 models in every page.
     */
    public function options(Request $request, string $key): Response|JsonResponse
    {
        $field = $this->catalog->field($key);
        if ($field === null || ! $field->depends_on) {
            throw ApiError::notFound('Polje ne postoji.');
        }
        $parents = array_slice(array_values(array_filter(array_map('trim', explode(',', (string) $request->query('parent', ''))), 'strlen')), 0, 20);
        if ($parents === []) {
            throw ApiError::missingParams('parent');
        }

        $out = collect((array) $field->options)
            ->filter(fn ($o) => is_array($o) && in_array((string) ($o['parent'] ?? ''), $parents, true))
            ->map(fn (array $o) => ['value' => (string) $o['value'], 'label' => (string) $o['label'], 'parent' => (string) $o['parent']])
            ->values();

        return ApiResponse::json($request, $out, ApiResponse::CACHE_CATALOG);
    }

    /** Current prices from the admin (RSD). */
    public function prices(Request $request): Response|JsonResponse
    {
        $prices = $this->catalog->prices()
            ->map([CatalogFormatter::class, 'price'])
            ->filter()
            ->values();

        return ApiResponse::json($request, $prices, ApiResponse::CACHE_CATALOG);
    }
}
