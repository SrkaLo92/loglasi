<?php

namespace App\Http\Controllers\Listing\Api;

use App\Http\Api\ApiError;
use App\Http\Api\ApiResponse;
use App\Http\Api\ListingFormatter;
use App\Http\Controllers\Controller;
use App\Models\Firm;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** GET /firms/{id}: public firm profile. */
class FirmApiController extends Controller
{
    public function show(Request $request, int $id): Response
    {
        $firm = $id > 0 ? Firm::query()->with(['user', 'location'])->find($id) : null;
        $user = $firm?->user;

        if ($user === null || ! $user->isFirm() || $user->isBlocked()) {
            throw ApiError::notFound('Firma ne postoji.');
        }

        return ApiResponse::json($request, ListingFormatter::firm($firm), ApiResponse::CACHE_CATALOG);
    }
}
