<?php

namespace App\Http\Controllers\Messages\Api;

use App\Http\Api\ApiError;
use App\Http\Api\ApiResponse;
use App\Http\Api\ListingFormatter;
use App\Http\Controllers\Controller;
use App\Models\Listing;
use App\Services\Messages\Favorites;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/** GET /me/favorites, PUT/DELETE /me/favorites/{id} */
class FavoriteApiController extends Controller
{
    public function __construct(private Favorites $favorites) {}

    /** Saved listings, newest saved first; deleted and hidden listings are left out, expired ones have status "expired". */
    public function index(Request $request)
    {
        $validator = Validator::make($request->query(), [
            'page' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'cursor' => ['nullable', 'string', 'max:64'],
        ]);
        if ($validator->fails()) {
            throw ApiError::invalidParams(array_map(fn () => 'Neispravna vrednost.', $validator->errors()->toArray()));
        }
        $query = $validator->validated();

        $page = $this->favorites->page(
            $request->user(),
            $query['cursor'] ?? null,
            (int) ($query['per_page'] ?? 50),
            (int) ($query['page'] ?? 1),
            ListingFormatter::CARD_RELATIONS,
        );

        $data = $page['items']->map(fn (Listing $l) => ListingFormatter::card($l))->values();

        return ApiResponse::json($request, $data, ApiResponse::CACHE_PRIVATE, 200, $page['next'] ? ['X-Next-Cursor' => $page['next']] : []);
    }

    public function update(Request $request, int $id)
    {
        $listing = Listing::query()->find($id);
        if (! $listing || ! $this->favorites->canSave($listing)) {
            throw ApiError::notFound('Oglas ne postoji ili više nije aktivan.');
        }

        $this->favorites->add($request->user(), $listing);

        return response()->noContent();
    }

    public function destroy(Request $request, int $id)
    {
        $this->favorites->remove($request->user(), $id);

        return response()->noContent();
    }
}
