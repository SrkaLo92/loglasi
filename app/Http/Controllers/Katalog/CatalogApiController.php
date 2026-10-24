<?php

namespace App\Http\Controllers\Katalog;

use App\Http\Api\ApiError;
use App\Http\Api\ApiResponse;
use App\Http\Api\CatalogFormatter;
use App\Http\Controllers\Controller;
use App\Services\Catalog;
use App\Services\ListingFields;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** GET /categories, /locations, /fields?category=ID, /categories/{id}/fields, /prices. */
class CatalogApiController extends Controller
{
    public function __construct(private readonly Catalog $catalog) {}

    /** Stablo kategorija sa brojem objavljenih oglasa (uključuje potkategorije). */
    public function categories(Request $request): Response|JsonResponse
    {
        $tree = array_map([CatalogFormatter::class, 'categoryNode'], $this->catalog->tree());

        return ApiResponse::json($request, $tree, ApiResponse::CACHE_CATALOG);
    }

    /** Mesta. */
    public function locations(Request $request): Response|JsonResponse
    {
        $locations = $this->catalog->locations()->map([CatalogFormatter::class, 'locationRef'])->values();

        return ApiResponse::json($request, $locations, ApiResponse::CACHE_CATALOG);
    }

    /** Polja i filteri za kategoriju: ?category=ID ili /categories/{id}/fields (roditelj: polja potkategorija za filtere). */
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

    /** Aktuelne cene iz admina (RSD). */
    public function prices(Request $request): Response|JsonResponse
    {
        $prices = $this->catalog->prices()
            ->map([CatalogFormatter::class, 'price'])
            ->filter()
            ->values();

        return ApiResponse::json($request, $prices, ApiResponse::CACHE_CATALOG);
    }
}
