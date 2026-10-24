<?php

namespace App\Http\Controllers\Search;

use App\Http\Api\ApiResponse;
use App\Http\Api\ListingFormatter;
use App\Http\Controllers\Controller;
use App\Services\ListingSearch;
use Illuminate\Http\Request;

/** GET /listings: search for the mobile app, {items, page, total} as in the contract. */
class ListingApiController extends Controller
{
    public function index(Request $request, ListingSearch $search)
    {
        $params = $search->validate($request->query());
        $page = $search->paginate($params);

        $total = $page->total();

        // The app's "Prikaži N oglasa" button asks only for the number, like the site's filter panel.
        if ($request->headers->get('X-Lg-Count') === '1') {
            return response()->json(['count' => $total])->header('Cache-Control', 'no-store, private')->header('Vary', 'X-Lg-Count');
        }

        $pages = (int) ceil($total / $params['per_page']);

        return ApiResponse::json($request, [
            'items' => $page->getCollection()->map(fn ($listing) => ListingFormatter::card($listing))->values()->all(),
            'page' => $params['page'],
            'total' => $total,
        ], ApiResponse::CACHE_DEFAULT, 200, [
            'X-WP-Total' => (string) $total,
            'X-WP-TotalPages' => (string) $pages,
            'Vary' => 'X-Lg-Count',
        ]);
    }
}
