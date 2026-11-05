<?php

namespace App\Http\Controllers\Users\Api;

use App\Http\Api\ApiError;
use App\Http\Api\ListingFormatter;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Users\UserBlocks;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

/**
 * GET /me/blocks: users I blocked, newest first: {data: [{id, name, blocked_at}]}.
 * POST /users/{id}/block, DELETE /users/{id}/block: 204, both idempotent.
 */
class BlockApiController extends Controller
{
    public function index(Request $request, UserBlocks $blocks): JsonResponse
    {
        return response()->json(['data' => $blocks->list($request->user())->map(fn ($row) => [
            'id' => (int) $row->id,
            'name' => $row->deleted_at ? 'Obrisan korisnik' : (string) $row->name,
            'blocked_at' => $row->blocked_at ? ListingFormatter::date(Carbon::parse($row->blocked_at)) : null,
        ])->values()->all()])->header('Cache-Control', 'no-store, private');
    }

    public function store(Request $request, int $id, UserBlocks $blocks): Response
    {
        $target = User::query()->find($id);
        if ($target === null || $target->id === $request->user()->id) {
            throw ApiError::notFound('Korisnik ne postoji.');
        }
        $blocks->block($request->user(), $target->id);

        return response()->noContent();
    }

    public function destroy(Request $request, int $id, UserBlocks $blocks): Response
    {
        $blocks->unblock($request->user(), $id);

        return response()->noContent();
    }
}
