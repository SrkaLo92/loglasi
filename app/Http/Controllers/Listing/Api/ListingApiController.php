<?php

namespace App\Http\Controllers\Listing\Api;

use App\Http\Api\ApiError;
use App\Http\Api\ApiResponse;
use App\Http\Api\ListingFormatter;
use App\Http\Controllers\Controller;
use App\Models\Listing;
use App\Models\User;
use App\Support\Listing\Seller;
use App\Support\Listing\SimilarListings;
use App\Support\Listing\Stats;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/** GET /listings/{id}, GET /listings/{id}/similar, POST /listings/{id}/contact */
class ListingApiController extends Controller
{
    private const NOT_FOUND = 'Oglas ne postoji ili više nije aktivan.';

    public function show(Request $request, int $id): Response
    {
        $viewer = $this->viewer();
        $listing = $this->find($id, $viewer, 'view');

        Stats::viewFromApi($listing, $request, $viewer);
        $data = ListingFormatter::detail($listing, $viewer);

        // The view count is left out of the ETag, so 304 keeps working while views are counted.
        $stable = $data;
        unset($stable['views']);

        return ApiResponse::json(
            $request,
            $data,
            $viewer ? ApiResponse::CACHE_PRIVATE : ApiResponse::CACHE_DEFAULT,
            headers: ['ETag' => ApiResponse::etag((string) json_encode($stable))],
        );
    }

    public function similar(Request $request, int $id): Response
    {
        $listing = $this->find($id, null, 'view');
        $cards = SimilarListings::for($listing)->map(fn (Listing $l) => ListingFormatter::card($l))->values()->all();

        return ApiResponse::json($request, $cards, ApiResponse::CACHE_CATALOG);
    }

    /** Seller phone; requires login, counted as a contact in the statistics. */
    public function contact(Request $request, int $id): Response
    {
        $viewer = $request->user();
        $listing = $this->find($id, $viewer, 'revealPhone');
        $seller = Seller::forListing($listing);

        if ($seller->hasPhone()) {
            Stats::reveal($listing, null, $viewer);
        }

        return ApiResponse::json($request, [
            'phone' => $seller->hasPhone() ? $seller->phone : null,
            'phone_verified' => $seller->user->isPhoneVerified(),
        ], 'private, no-store');
    }

    /** Bearer token is optional on public routes (owner and staff see their pending listings). */
    private function viewer(): ?User
    {
        $user = Auth::guard('sanctum')->user();

        return $user instanceof User ? $user : null;
    }

    private function find(int $id, ?User $viewer, string $ability): Listing
    {
        $listing = $id > 0 ? Listing::query()->with(ListingFormatter::DETAIL_RELATIONS)->find($id) : null;

        if ($listing === null || Gate::forUser($viewer)->denies($ability, $listing)) {
            throw ApiError::notFound(self::NOT_FOUND);
        }

        return $listing;
    }
}
